<?php
namespace App\API\Modules\auth;

use App\API\Includes\BaseAPI;
use App\API\Includes\ValidationHelper;
use App\API\Modules\users\UsersAPI;
use App\API\Modules\users\RoleManager;
use App\API\Modules\users\PermissionManager;
use App\API\Modules\users\UserRoleManager;
use App\API\Modules\users\UserPermissionManager;
use App\API\Modules\communications\CommunicationsAPI;
use App\API\Services\AuthSessionService;
use App\API\Services\SystemConfigService;
use App\Config\DashboardRouter;
use App\API\Services\PolicyEngine;
use App\API\Services\Logger;
use App\API\Services\TestAccountAccessService;
use Firebase\JWT\JWT;

class AuthAPI extends BaseAPI
{
    private $usersApi;
    private $roleManager;
    private $permissionManager;
    private $userRoleManager;
    private $userPermissionManager;
    private $communicationsApi;
    private AuthSessionService $authSessionService;

    // New database-driven services
    private ?SystemConfigService $configService = null;

    // Feature flag: use database-driven config (set to true when migration is complete)
    // TEMPORARILY DISABLED due to performance issues in buildLoginResponseFromDatabase
    private bool $useDatabaseConfig = false;

    public function __construct()
    {
        parent::__construct('auth');
        $this->usersApi = new UsersAPI();
        $this->roleManager = new RoleManager($this->db);
        $this->permissionManager = new PermissionManager($this->db);
        $this->userRoleManager = new UserRoleManager($this->db);
        $this->userPermissionManager = new UserPermissionManager($this->db);
        $this->communicationsApi = new CommunicationsAPI();
        $this->authSessionService = new AuthSessionService($this->db);

        // Sidebar navigation is file-driven through SidebarConfigReader.
    }

    /**
     * Get SystemConfigService (lazy load)
     */
    private function getConfigService(): SystemConfigService
    {
        if ($this->configService === null) {
            $this->configService = SystemConfigService::getInstance();
        }
        return $this->configService;
    }
    // Logout user through the same canonical refresh/session revocation path.
    public function logout($data)
    {
        return $this->revokeRefreshToken($data);
    }

    public function forgotPassword($data)
    {
        $identifier = trim($data['email'] ?? '');
        if ($identifier === '') {
            return [
                'success' => false,
                'message' => 'Email is required.'
            ];
        }

        $message = 'If an account exists for that email, password reset instructions have been sent.';

        try {
            $stmt = $this->db->prepare('
                SELECT u.id, p.email, u.username, p.first_name, p.last_name
                FROM users u
                LEFT JOIN persons p ON p.id = u.person_id
                WHERE p.email = ? OR u.username = ?
                LIMIT 1
            ');
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                return [
                    'success' => true,
                    'message' => $message
                ];
            }

            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = $this->hashResetToken($rawToken);

            $this->db->beginTransaction();

            $stmt = $this->db->prepare('UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0');
            $stmt->execute([$user['email']]);

            $stmt = $this->db->prepare('
                INSERT INTO password_resets (email, token, created_at, expires_at, used)
                VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)
            ');
            $stmt->execute([$user['email'], $tokenHash]);

            $this->db->commit();

            $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $resetLink = $this->generateResetLink($rawToken);

            try {
                $this->sendResetEmail($user['email'], $displayName ?: ($user['username'] ?? $user['email']), $resetLink);
            } catch (\Throwable $e) {
                \App\API\Services\Logger::legacyError('Password reset email failed: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('Forgot password failed: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => $message
        ];
    }

    public function verifyResetToken($data)
    {
        $token = trim($data['token'] ?? '');
        if ($token === '') {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset link.'
            ];
        }

        $stmt = $this->db->prepare('
            SELECT id
            FROM password_resets
            WHERE token = ? AND used = 0 AND expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([$this->hashResetToken($token)]);

        if (!$stmt->fetch(\PDO::FETCH_ASSOC)) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset link.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Reset link is valid.'
        ];
    }

    public function resetPassword($data)
    {
        $token = trim($data['token'] ?? '');
        $newPassword = $data['new_password'] ?? $data['password'] ?? null;

        if ($token === '' || !$newPassword) {
            return [
                'success' => false,
                'message' => 'Token and new password are required.'
            ];
        }

        $confirmation = $data['password_confirmation'] ?? $data['confirm_password'] ?? null;
        if ($confirmation !== null && !hash_equals((string)$newPassword, (string)$confirmation)) {
            return [
                'success' => false,
                'message' => 'Password confirmation does not match.'
            ];
        }

        $confirmation = $data['password_confirmation'] ?? $data['confirm_password'] ?? null;
        if ($confirmation !== null && !hash_equals((string)$newPassword, (string)$confirmation)) {
            return [
                'success' => false,
                'message' => 'Password confirmation does not match.'
            ];
        }

        $passwordValidation = ValidationHelper::validatePassword($newPassword);
        if (!$passwordValidation['valid']) {
            return [
                'success' => false,
                'message' => $passwordValidation['error']
            ];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare('
                SELECT id, email
                FROM password_resets
                WHERE token = ? AND used = 0 AND expires_at > NOW()
                LIMIT 1
                FOR UPDATE
            ');
            $stmt->execute([$this->hashResetToken($token)]);
            $reset = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$reset) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset link.'
                ];
            }

            $stmt = $this->db->prepare('
                SELECT u.id
                FROM users u
                JOIN persons p ON p.id = u.person_id
                WHERE p.email = ?
                LIMIT 1
            ');
            $stmt->execute([$reset['email']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset link.'
                ];
            }

            $stmt = $this->db->prepare('
                UPDATE users
                SET password_hash = ?, password_changed_at = NOW(), updated_at = NOW(), force_password_change = 0
                WHERE id = ?
            ');
            $stmt->execute([
                password_hash($newPassword, PASSWORD_DEFAULT),
                $user['id']
            ]);

            $stmt = $this->db->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
            $stmt->execute([$reset['id']]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Password has been reset successfully.'
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('Reset password failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Password reset failed. Please try again.'
            ];
        }
    }

    private function hashResetToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function generateResetLink(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $appBase = preg_replace('#/api$#', '', rtrim($scriptDir, '/'));
        $appBase = ($appBase === '/' || $appBase === '.') ? '' : $appBase;

        return $scheme . '://' . $host . $appBase . '/reset_password.php?token=' . urlencode($token);
    }

    // Login user
    public function login($data)
    {
        // If this is a 2FA-verified login (frontend sends user_id + 2fa_verified),
        // skip password check and go straight to token issuance.
        if (!empty($data['2fa_verified']) && !empty($data['user_id'])) {
            return $this->complete2FALogin((int) $data['user_id'], $data);
        }

        // UsersAPI owns credential verification and telemetry. AuthAPI remains
        // the single token/session issuer for the canonical /auth/login path.
        $result = $this->usersApi->login($data, false);
        if ($result['success']) {
            // Extract user data - it's nested in $result['data']['user']
            $userData = $result['data']['user'] ?? $result['data'];

            // ── 2FA gate ──────────────────────────────────────────────────
            // If the user has 2FA enabled (or policy mandates it), pause the
            // login and return a challenge response. The frontend will show a
            // verification modal, call POST /api/2fa/challenge, then POST
            // /api/2fa/verify, and finally re-submit login with
            // {2fa_verified: true, user_id: <id>}.
            $tfa = new \App\API\Services\TwoFactorService();
            $userId = (int) ($userData['id'] ?? 0);
            $isTestUser = (int) ($userData['is_test_user'] ?? 0) === 1;
            $requiredMethod = $tfa->getRequiredMethod($userId);
            $policyForced   = $tfa->is2FARequiredByPolicy($userId);

            $testMfaBypassAllowed = $isTestUser
                && TestAccountAccessService::environment() === 'development';

            if (!$testMfaBypassAllowed && ($requiredMethod || $policyForced)) {
                // For policy-forced users who haven't set up 2FA yet,
                // return 'setup_required' so the frontend can redirect them.
                if (!$requiredMethod && $policyForced) {
                    return [
                        'success' => true,
                        'status' => 'success',
                        'data' => [
                            'user_id' => $userId,
                            'requires_2fa' => true,
                            'requires_2fa_setup' => true,
                            'setup_required' => true,
                            'method' => null,
                        ],
                        'message' => 'Two-factor authentication must be enabled before you can sign in.',
                    ];
                }

                $challengeToken = $tfa->createLoginChallenge($userId, $requiredMethod);
                return [
                    'success' => true,
                    'status' => 'success',
                    'data' => [
                        'user_id' => $userId,
                        'requires_2fa' => true,
                            'method' => $requiredMethod,
                            'challenge_token' => $challengeToken,
                            'available_methods' => $tfa->getEnabledMethods($userId),
                    ],
                    'message' => 'Two-factor verification required.',
                ];
            }
            if ($testMfaBypassAllowed && ($requiredMethod || $policyForced)) {
                Logger::audit(
                    'test_mfa_bypass',
                    'user',
                    $userId,
                    'MFA was bypassed for an explicitly flagged test account.',
                    ['username' => $userData['username'] ?? null, 'policy_forced' => $policyForced]
                );
            }
            // ── end 2FA gate ──────────────────────────────────────────────

            // DO NOT put permissions in token - they're already in userData
            // Token should only contain authentication info (who you are)
            // Permissions are for authorization (what you can do) - stored in localStorage
            $token = $this->generateToken([
                'user_id' => $userData['id'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'roles' => $userData['roles'] ?? [],
                'display_name' => $userData['first_name'] . ' ' . $userData['last_name']
                // NO permissions in token!
            ]);

            // Get user's primary role for dashboard selection
            $userRoles = $userData['roles'] ?? [];
            $primaryRole = null;
            $primaryRoleId = null;

            if (!empty($userRoles)) {
                $primaryRoleData = $userRoles[0];
                if (is_array($primaryRoleData)) {
                    $primaryRoleId = $primaryRoleData['id'] ?? null;
                    $primaryRole = $primaryRoleData['name'] ?? null;
                } else {
                    $primaryRole = $primaryRoleData;
                }
            }

            // Get all role IDs for multi-role support
            $roleIds = [];
            foreach ($userRoles as $role) {
                if (is_array($role)) {
                    $rid = $role['id'] ?? $role['role_id'] ?? null;
                } else {
                    $rid = $role;
                }
                if ($rid) {
                    $roleIds[] = (int) $rid;
                }
            }
            $roleIds = array_values(array_unique($roleIds));

            // Use database-driven config if available, otherwise fall back to file-based
            if ($this->useDatabaseConfig) {
                $loginData = $this->buildLoginResponseFromDatabase(
                     $userData,
                     $primaryRoleId,
                     $roleIds,
                     $token,
                     filter_var(
                         $data['remember_me'] ?? false,
                         FILTER_VALIDATE_BOOLEAN
                     )
                 );
             } else {
                 $loginData = $this->buildLoginResponseFromFiles(
                     $userData,
                     $primaryRole,
                     $primaryRoleId,
                     $roleIds,
                     $token,
                     filter_var($data['remember_me'] ?? false, FILTER_VALIDATE_BOOLEAN)
                 );
             }

             // Add CSRF token for frontend mutating requests
             $loginData['data']['csrf_token'] = $this->generateCsrfToken(
                 (int) $userData['id']
             );
             $loginData['data']['test_mfa_bypassed'] = $testMfaBypassAllowed;

            try {
                if (!empty($userData['force_password_change'])) {
                    $setupToken = $this->createPasswordSetupInvitation((int)$userData['id']);
                    $loginData['data']['password_setup_required'] = true;
                    $loginData['data']['password_setup_url'] = $this->passwordSetupUrl($setupToken);
                    $loginData['data']['dashboard'] = [
                        'key' => 'reset_default_password',
                        'url' => $loginData['data']['password_setup_url'],
                        'label' => 'Create Password',
                    ];
                } elseif ($this->staffProfileCompletionRequired((int)$userData['id'])) {
                    $loginData['data']['profile_completion_required'] = true;
                    $loginData['data']['dashboard'] = [
                        'key' => 'complete_staff_profile',
                        'url' => 'complete_staff_profile',
                        'label' => 'Complete Staff Profile',
                    ];
                }

                $result = $this->attachTrackedSession(
                    $loginData,
                    (int) $userData['id'],
                    $token
                );
                unset($result['data']['refresh_token']);
                return $result;
            } catch (\Throwable $error) {
                \App\API\Services\Logger::legacyError(
                    'Authenticated session creation failed: ' .
                    $error->getMessage()
                );
                $issuedRefreshToken = (string) (
                    $loginData['data']['refresh_token'] ?? ''
                );
                if ($issuedRefreshToken !== '') {
                    try {
                        $this->authSessionService->revokeByRefreshToken(
                            $issuedRefreshToken
                        );
                    } catch (\Throwable $cleanupError) {
                        \App\API\Services\Logger::legacyError(
                            'Failed to clean up refresh token after session ' .
                            'creation error: ' .
                            $cleanupError->getMessage()
                        );
                    }
                }
                return [
                    'status' => 'error',
                    'message' => 'The authenticated session could not be established',
                    'data' => null,
                ];
            }
        }
        // If not successful, return error (include debug info in dev)
        return [
            'status' => 'error',
            'message' => $result['error'] ?? $result['message'] ?? 'Login failed',
            'data' => [
                'debug' => $result
            ]
        ];
    }

    /**
     * Complete a login that has already passed 2FA verification.
     * Called when the frontend re-submits login with {2fa_verified: true, user_id: int}.
     */
    private function complete2FALogin(int $userId, array $data): array
    {
        $challengeToken = trim((string) ($data['challenge_token'] ?? ''));
        if ($challengeToken === '') return ['status' => 'error', 'message' => 'A valid 2FA challenge is required', 'data' => null];
        $tfa = new \App\API\Services\TwoFactorService();
        if (!$tfa->consumeLoginChallenge($challengeToken, $userId)) {
            return ['status' => 'error', 'message' => 'The 2FA challenge is invalid or expired', 'data' => null];
        }
        // Fetch the full user record
        $userLookup = $this->usersApi->get($userId);
        $userData = ($userLookup['success'] ?? false) ? $userLookup['data'] : null;

        if (!$userData || empty($userData['id'])) {
            return ['status' => 'error', 'message' => 'User not found', 'data' => null];
        }

        try {
            $accessContext = (new TestAccountAccessService($this->db))
                ->requireAccess($userId);
            $userData = array_merge($userData, $accessContext);
        } catch (\DomainException $error) {
            return ['status' => 'error', 'message' => $error->getMessage(), 'data' => null];
        }

        // Fetch roles/permissions (same as normal login)
        $roleIds = [];
        $primaryRoleId = null;
        $primaryRole = null;

        $rolesResult = $this->userRoleManager->getUserRoles($userId);
        $userData['roles'] = $rolesResult['data'] ?? [];
        $permissionsResult = $this->userPermissionManager->getEffectivePermissions($userId);
        $userData['permissions'] = $permissionsResult['data'] ?? [];

        foreach ($userData['roles'] as $role) {
            $rid = is_array($role) ? ($role['id'] ?? $role['role_id'] ?? null) : $role;
            if ($rid) $roleIds[] = (int) $rid;
            if ($primaryRole === null && is_array($role)) {
                $primaryRoleId = $rid;
                $primaryRole = $role['name'] ?? null;
            }
        }
        $roleIds = array_values(array_unique($roleIds));

        $token = $this->generateToken([
            'user_id' => $userData['id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'roles' => $userData['roles'],
            'display_name' => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')),
        ]);

        $rememberMe = filter_var($data['remember_me'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($this->useDatabaseConfig) {
            $loginData = $this->buildLoginResponseFromDatabase($userData, $primaryRoleId, $roleIds, $token, $rememberMe);
        } else {
            $loginData = $this->buildLoginResponseFromFiles($userData, $primaryRole, $primaryRoleId, $roleIds, $token, $rememberMe);
        }

        try {
            if (!empty($userData['force_password_change'])) {
                $setupToken = $this->createPasswordSetupInvitation($userId);
                $loginData['data']['password_setup_required'] = true;
                $loginData['data']['password_setup_url'] = $this->passwordSetupUrl($setupToken);
                $loginData['data']['dashboard'] = ['key' => 'reset_default_password', 'url' => $loginData['data']['password_setup_url'], 'label' => 'Create Password'];
            } elseif ($this->staffProfileCompletionRequired($userId)) {
                $loginData['data']['profile_completion_required'] = true;
                $loginData['data']['dashboard'] = ['key' => 'complete_staff_profile', 'url' => 'complete_staff_profile', 'label' => 'Complete Staff Profile'];
            }

            $loginData['data']['two_factor_verified'] = true;
            $loginData['data']['csrf_token'] = $this->generateCsrfToken($userId);

            $result = $this->attachTrackedSession($loginData, $userId, $token);
            unset($result['data']['refresh_token']);
            return $result;
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('2FA login session creation failed: ' . $error->getMessage());
            return ['status' => 'error', 'message' => 'Session could not be established', 'data' => null];
        }
    }

    /**
     * Build login response using database-driven config
     */
    private function buildLoginResponseFromDatabase(
        array $userData,
        ?int $primaryRoleId,
        array $roleIds,
        string $token,
        bool $rememberMe = false,
        ?string $existingRefreshToken = null
    ): array {
        $userId = $userData['id'];

        // Ensure roles and permissions are present on $userData (some code paths return bare user row)
        if (empty($userData['roles'])) {
            $rolesRes = $this->userRoleManager->getUserRoles($userId);
            $userData['roles'] = $rolesRes['data'] ?? [];
        }
        if (empty($userData['permissions'])) {
            $permsRes = $this->userPermissionManager->getEffectivePermissions($userId);
            $userData['permissions'] = $permsRes['data'] ?? [];
            \App\API\Services\Logger::legacyError("DEBUG: Fetched permissions for user $userId: " . count($userData['permissions']) . " items");
            \App\API\Services\Logger::legacyError("DEBUG: First permission: " . json_encode($userData['permissions'][0] ?? 'EMPTY'));
        }

        $userRoles = $userData['roles'] ?? [];
        $primaryRole = null;
        if (!empty($userRoles)) {
            $firstRole = $userRoles[0];
            if (is_array($firstRole)) {
                $primaryRoleId = $primaryRoleId
                    ?? (isset($firstRole['id'])
                        ? (int) $firstRole['id']
                        : (isset($firstRole['role_id'])
                            ? (int) $firstRole['role_id']
                            : null));
                $primaryRole = $firstRole['name'] ?? null;
            } elseif (is_string($firstRole)) {
                $primaryRole = $firstRole;
            } elseif (is_numeric($firstRole)) {
                $primaryRoleId = $primaryRoleId ?? (int) $firstRole;
            }
        }
        if (empty($roleIds)) {
            foreach ($userRoles as $role) {
                $roleId = is_array($role)
                    ? ($role['id'] ?? $role['role_id'] ?? null)
                    : (is_numeric($role) ? $role : null);
                if ($roleId) {
                    $roleIds[] = (int) $roleId;
                }
            }
            $roleIds = array_values(array_unique($roleIds));
        }

        // Per-item delegation tables have been retired. Keep the response
        // contract explicit without consulting a second permission source.
        $delegatedPermissions = [];

        // Extract permission codes - handle both 'code' and 'permission_code' field names
        $userPermissions = [];
        foreach ($userData['permissions'] ?? [] as $perm) {
            if (is_array($perm)) {
                $code = $perm['code'] ?? $perm['permission_code'] ?? null;
                if ($code) {
                    $userPermissions[] = $code;
                }
            } else {
                $userPermissions[] = $perm;
            }
        }
        $userPermissions = array_values(array_filter(array_unique($userPermissions)));
        \App\API\Services\Logger::legacyError("DEBUG: userPermissions extracted: " . count($userPermissions) . " items");

        // NOTE: Per-item menu delegation (the legacy `role_delegations_items` /
        // `user_delegations_items` tables) has been retired. Those tables no
        // longer exist in the schema, so role-level menu visibility is driven
        // solely by config/role_sidebars.php (the single source of truth).
        // Effective permissions below are the base user/role grants only.

        // Effective permissions for filtering the sidebar
        $effectivePermissions = array_values(array_unique($userPermissions));

        // Single source of truth: sidebar items come from config/role_sidebars.php.
        // (Legacy DB-menu path via MenuBuilderService was retired: it queried the
        // deprecated sidebar_menu_configs / role_delegations_items tables which are
        // no longer part of the schema, and duplicated role_sidebars.php coverage.)
        try {
            // A teacher can hold multiple simultaneous roles (for example
            // class teacher + subject teacher). Build one deduplicated menu
            // from every assigned role instead of using only the primary role.
            $sidebarItems = $this->getHardcodedSidebarItemsForRoles($roleIds ?: [($primaryRoleId ?? 0)]);
            if ($sidebarItems === null) {
                // Fallback only if a role has no entry in role_sidebars.php
                $sidebarItems = [];
            }

            // Resolve dashboard strictly by the user's primary role to avoid cross-role defaults
            $defaultRoute = null;
            $dashboardInfo = null;

            // First, try to get an explicit database mapping for this role
            try {
                $dashboardInfo = $this->getConfigService()->getDashboardForRole($primaryRoleId ?? 0);
                // Normalize dashboard info to ensure name is route key, not full URL
                $dashboardInfo = $this->normalizeDashboardInfo($dashboardInfo);
            } catch (\Exception $e) {
                // Could be missing role_dashboards table or other DB issue
                \App\API\Services\Logger::legacyError('getDashboardForRole failed: ' . $e->getMessage());
                $dashboardInfo = null;
            }

            // If database mapping not available, try to derive a dashboard key for the role using DashboardRouter
            if (empty($dashboardInfo)) {
                try {
                    $roleForDashboard = $primaryRoleId ?? ($primaryRole ?? (!empty($userRoles) ? $userRoles[0] : null));
                    if ($roleForDashboard !== null) {
                        $dashboardKey = DashboardRouter::getDashboardForRole($roleForDashboard);
                        if (!empty($dashboardKey)) {
                            // Attempt to read dashboard record by name
                            $dashboardInfo = $this->getConfigService()->getDashboardByName($dashboardKey);
                            $dashboardInfo = $this->normalizeDashboardInfo($dashboardInfo);
                            $defaultRoute = $dashboardKey;
                        }
                    }
                } catch (\Exception $e) {
                    \App\API\Services\Logger::legacyError('DashboardRouter fallback failed: ' . $e->getMessage());
                }
            }

            // If still no dashboard info, try to find a dashboard by role name (useful when role_dashboards table is absent)
            if (empty($dashboardInfo) && !empty($userData['roles'])) {
                $firstRoleName = is_array($userData['roles'][0]) ? ($userData['roles'][0]['name'] ?? null) : $userData['roles'][0];
                if (!empty($firstRoleName)) {
                    try {
                        $found = $this->getConfigService()->findDashboardForRoleName($firstRoleName);
                        if (!empty($found)) {
                            $dashboardInfo = $this->normalizeDashboardInfo($found);
                            $defaultRoute = $found['name'];
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }

            // Still no dashboard info? fall back to a safe default (system default dashboard)
            if (empty($dashboardInfo)) {
                $defaultRoute = $defaultRoute ?? DashboardRouter::getDefaultDashboard();
                $dashboardInfo = [
                    'name' => $defaultRoute,
                    'display_name' => ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $defaultRoute)))
                ];
            }

            // Ensure defaultRoute is set (route name/key)
            $defaultRoute = $dashboardInfo['name'] ?? $defaultRoute ?? DashboardRouter::getDefaultDashboard();

            // Normalize user permissions to codes and merge delegated permissions
            $userData = $this->normalizeUserPermissions($userData);
            $userData['permissions'] = array_values(array_unique(array_merge($userData['permissions'] ?? [], $delegatedPermissions)));

            // Create a refresh token only for a new login. A refresh exchange
            // reuses its existing token so one browser session remains one
            // user_sessions record instead of spawning a new row per refresh.
            $maxAge = $rememberMe ? (14 * 24 * 60 * 60) : 0;
            $refreshToken = $existingRefreshToken
                ?? $this->generateRefreshToken(
                    $userId,
                    $maxAge > 0
                        ? $maxAge
                        : (7 * 24 * 60 * 60)
                );
            if ($refreshToken && $existingRefreshToken === null) {
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                $expires = $rememberMe ? (time() + $maxAge) : 0;
                setcookie('refresh_token', $refreshToken, $expires, '/', '', $secure, true);
                $cookieParts = [
                    "refresh_token=$refreshToken",
                    'Path=/',
                    'HttpOnly',
                    'SameSite=Lax'
                ];
                if ($maxAge > 0) {
                    $cookieParts[] = "Max-Age=$maxAge";
                }
                if ($secure) {
                    $cookieParts[] = 'Secure';
                }
                header('Set-Cookie: ' . implode('; ', $cookieParts));
            }

            // Determine the role to resolve dashboard for (prefer primary role id)
            $roleForDashboard = $primaryRoleId ?? ($primaryRole ?? (!empty($userRoles) ? $userRoles[0] : null));
            if ($roleForDashboard !== null) {
                $resolvedKey = $dashboardInfo['name'] ?? DashboardRouter::getDashboardForRole($roleForDashboard);
            } else {
                $resolvedKey = $dashboardInfo['name'] ?? DashboardRouter::getDefaultDashboard();
            }

            // Normalize resolvedKey to ensure it's a route name, not full URL
            if (preg_match('/[?&]route=([^&]*)/', $resolvedKey, $matches)) {
                $resolvedKey = $matches[1];
            }

            $resolvedUrl = $dashboardInfo['route'] ?? ('?route=' . $resolvedKey);
            $resolvedLabel = $dashboardInfo['display_name'] ?? ($dashboardInfo['title'] ?? ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $resolvedKey))));

            // If we fell back to system default and role is known, try to find a role-specific dashboard by name
            if ($resolvedKey === DashboardRouter::getDefaultDashboard() && $roleForDashboard !== null) {
                // Try to determine role name from the provided context (prefer userData.roles)
                $roleName = null;
                if (!empty($userData['roles'])) {
                    $firstRole = $userData['roles'][0];
                    if (is_array($firstRole) && !empty($firstRole['name'])) {
                        $roleName = $firstRole['name'];
                    } elseif (is_string($firstRole)) {
                        $roleName = $firstRole;
                    }
                }
                // Fallback to roleForDashboard if it is a string (rare)
                if (empty($roleName) && is_string($roleForDashboard)) {
                    $roleName = $roleForDashboard;
                }
                if (!empty($roleName)) {
                    $found = $this->getConfigService()->findDashboardForRoleName($roleName);
                    if (!empty($found)) {
                        $resolvedKey = $found['name'];
                        $resolvedUrl = $found['route'] ?? ('?route=' . $resolvedKey);
                        $resolvedLabel = $found['display_name'] ?? ($found['title'] ?? $resolvedLabel);
                    }
                }
            }

            // Final normalization of dashboard key
            if (preg_match('/[?&]route=([^&]*)/', $resolvedKey, $matches)) {
                $resolvedKey = $matches[1];
            }

            // Only prepend a dashboard item if no sidebar item already links to this route.
            // This prevents duplicate "Dashboard" / "Director Dashboard" entries when the
            // DB sidebar menus already contain a home/dashboard link for this role.
            if (!$this->sidebarAlreadyHasRoute($sidebarItems, $resolvedKey)) {
                $dashboardMenuItem = [
                    'id' => 'dashboard_' . $primaryRoleId,
                    'label' => $resolvedLabel,
                    'icon' => 'bi-house-door',
                    'url' => $resolvedKey,
                    'route_url' => $resolvedKey,
                    'domain' => 'SCHOOL',
                    'display_order' => -200,
                    'subitems' => [],
                    'show_badge' => false,
                    'badge_source' => null,
                    'badge_color' => 'danger',
                    'open_in_new_tab' => false,
                    'requires_confirmation' => false,
                    'confirmation_message' => null,
                    'css_class' => null,
                    'tooltip' => null
                ];
                array_unshift($sidebarItems, $dashboardMenuItem);
            }

            return [
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'refresh_token' => $refreshToken,
                    'token_expires_in' => JWT_EXPIRY,
                    'remember_me' => $rememberMe,
                    'user' => $userData,
                    'sidebar_items' => $this->normalizeSidebarItems($sidebarItems),
                    'dashboard' => [
                        'key' => $resolvedKey,
                        'url' => $resolvedKey,
                        'label' => $resolvedLabel
                    ],
                    'delegated_permissions' => array_values(array_unique($delegatedPermissions)),
                    'config_source' => 'database'
                ]
            ];
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError("Database config failed, falling back to files: " . $e->getMessage());
            // Fall back to file-based config on error
            return $this->buildLoginResponseFromFiles(
                $userData,
                $userData['roles'][0]['name'] ?? null,
                $primaryRoleId,
                $roleIds,
                $token,
                $rememberMe,
                $existingRefreshToken
            );
        }
    }

    /**
     * Get default route for a role from database
     */
    private function getDefaultRouteForRole(int $roleId): string
    {
        try {
            // Get the dashboard route for this role from role_dashboards -> dashboards -> routes
            $stmt = $this->db->prepare(
                "SELECT r.name 
                 FROM role_dashboards rd
                 JOIN dashboards d ON d.id = rd.dashboard_id
                 JOIN routes_registry r ON r.id = d.route_id
                 WHERE rd.role_id = ? AND rd.is_primary = 1
                 LIMIT 1"
            );
            $stmt->execute([$roleId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && !empty($result['name'])) {
                return $result['name'];
            }

            // Fallback: try to get any dashboard for this role
            $stmt = $this->db->prepare(
                "SELECT r.name 
                 FROM role_dashboards rd
                 JOIN dashboards d ON d.id = rd.dashboard_id
                 JOIN routes_registry r ON r.id = d.route_id
                 WHERE rd.role_id = ?
                 ORDER BY rd.is_primary DESC
                 LIMIT 1"
            );
            $stmt->execute([$roleId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result['name'] ?? 'home';
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError("getDefaultRouteForRole error: " . $e->getMessage());
            return 'home';
        }
    }

    /**
     * Normalize dashboard info to ensure name contains route key, not full URL
     */
    private function normalizeDashboardInfo(?array $dashboardInfo): ?array
    {
        if (!$dashboardInfo) {
            return null;
        }

        // If route contains a full URL with route parameter, extract the route name
        if (isset($dashboardInfo['route']) && preg_match('/[?&]route=([^&]*)/', $dashboardInfo['route'], $matches)) {
            $dashboardInfo['name'] = $matches[1];
        }

        // Also check if name itself is a full URL and extract route
        if (isset($dashboardInfo['name']) && preg_match('/[?&]route=([^&]*)/', $dashboardInfo['name'], $matches)) {
            $dashboardInfo['name'] = $matches[1];
        }

        return $dashboardInfo;
    }

    /**
     * Build login response using file-based config (legacy fallback)
     */
    private function buildLoginResponseFromFiles(
        array $userData,
        ?string $primaryRole,
        ?int $primaryRoleId,
        array $roleIds,
        string $token,
        bool $rememberMe = false,
        ?string $existingRefreshToken = null
    ): array {
        // Generate sidebar menu items based on user's roles and permissions
        $dashboardManager = new \App\API\Includes\DashboardManager();
        $dashboardManager->setUser($userData);

        // Get filtered menu items for user's dashboard
        $sidebarItems = [];
        $defaultDashboard = null;
        $dashboardKey = null;

        // Fast path: use hardcoded sidebar if defined for this role (no DB queries needed)
        $hardcodedSidebar = $this->getHardcodedSidebarItemsForRoles($roleIds ?: [($primaryRoleId ?? 0)]);
        if ($hardcodedSidebar !== null) {
            $sidebarItems = $hardcodedSidebar;
        }

        // Get dashboard key using DashboardRouter (prefer role ID if available)
        if ($primaryRoleId) {
            $dashboardKey = DashboardRouter::getDashboardForRole($primaryRoleId);

            if ($hardcodedSidebar === null) {
                // Only query DB menu items when no hardcoded sidebar exists
                $sidebarItems = $dashboardManager->getMenuItems($primaryRoleId);
            }
            $defaultDashboard = $dashboardManager->getDashboard($primaryRoleId);
        } elseif ($primaryRole) {
            $dashboardKey = DashboardRouter::getDashboardForRole($primaryRole);

            // Fall back to role name lookup if role ID wasn't provided
            if ($hardcodedSidebar === null && $primaryRoleId) {
                $sidebarItems = $dashboardManager->getMenuItems($primaryRoleId);
                $defaultDashboard = $dashboardManager->getDashboard($primaryRoleId);
            }
        }

        \App\API\Services\Logger::legacyError("Login (file-based): Role=$primaryRole (ID: $primaryRoleId), DashboardKey=$dashboardKey, MenuItems=" . count($sidebarItems));

        // If no sidebar items found, try to get first accessible dashboard
        if (empty($sidebarItems)) {
            $defaultDashboard = $dashboardManager->getDefaultDashboard();
            if ($defaultDashboard) {
                $sidebarItems = $defaultDashboard['menu_items'] ?? $defaultDashboard['menus'] ?? [];
            }
        }

        // Normalize user permissions (effective permissions come from DB / stored procedure only)
        $userData = $this->normalizeUserPermissions($userData);

        // Create only on login; refresh exchanges retain the current token.
        $maxAge = $rememberMe ? (14 * 24 * 60 * 60) : 0;
        $refreshToken = $existingRefreshToken
            ?? $this->generateRefreshToken(
                $userData['id'],
                $maxAge > 0
                    ? $maxAge
                    : (7 * 24 * 60 * 60)
            );
        if ($refreshToken && $existingRefreshToken === null) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $expires = $rememberMe ? (time() + $maxAge) : 0;
            setcookie('refresh_token', $refreshToken, $expires, '/', '', $secure, true);
            $cookieParts = [
                "refresh_token=$refreshToken",
                'Path=/',
                'HttpOnly',
                'SameSite=Lax'
            ];
            if ($maxAge > 0) {
                $cookieParts[] = "Max-Age=$maxAge";
            }
            if ($secure) {
                $cookieParts[] = 'Secure';
            }
            header('Set-Cookie: ' . implode('; ', $cookieParts));
        }

        // Determine dashboard details
        $dashboardKeyResolved = $dashboardKey ?? DashboardRouter::getDashboardForRole($primaryRoleId ?? $primaryRole);

        // Normalize dashboard key to ensure it's a route name, not full URL
        if (preg_match('/[?&]route=([^&]*)/', $dashboardKeyResolved, $matches)) {
            $dashboardKeyResolved = $matches[1];
        }
        $dashboardLabel = (
            ($defaultDashboard['label'] ?? null) ? $defaultDashboard['label'] : (
                (($dbDash = $this->getConfigService()->getDashboardByName($dashboardKeyResolved)) && !empty($dbDash['display_name']))
                ? $dbDash['display_name']
                : ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $dashboardKeyResolved)))
            )
        );

        // Final normalization of dashboard key
        if (preg_match('/[?&]route=([^&]*)/', $dashboardKeyResolved, $matches)) {
            $dashboardKeyResolved = $matches[1];
        }

        // Only prepend a dashboard item if no sidebar item already links to this route.
        if (!$this->sidebarAlreadyHasRoute($sidebarItems, $dashboardKeyResolved)) {
            $dashboardMenuItem = [
                'id' => 'dashboard_' . $primaryRoleId,
                'label' => $dashboardLabel,
                'icon' => 'bi-house-door',
                'url' => $dashboardKeyResolved,
                'route_url' => $dashboardKeyResolved,
                'domain' => 'SCHOOL',
                'display_order' => -200,
                'subitems' => [],
                'show_badge' => false,
                'badge_source' => null,
                'badge_color' => 'danger',
                'open_in_new_tab' => false,
                'requires_confirmation' => false,
                'confirmation_message' => null,
                'css_class' => null,
                'tooltip' => null
            ];
            array_unshift($sidebarItems, $dashboardMenuItem);
        }

        return [
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'refresh_token' => $refreshToken,
                'token_expires_in' => JWT_EXPIRY,
                'remember_me' => $rememberMe,
                'user' => $userData,
                'sidebar_items' => $this->normalizeSidebarItems($sidebarItems),
                'dashboard' => [
                    'key' => $dashboardKeyResolved,
                    'url' => $dashboardKeyResolved,
                    'label' => $dashboardLabel
                ],
                'config_source' => 'file'
            ]
        ];
    }

    // Generate JWT token
    private function generateToken($userData)
    {
        $issuedAt = time();
        $expire = $issuedAt + JWT_EXPIRY;

        $payload = array_merge(
            $userData,
            [
                'iat' => $issuedAt,
                'exp' => $expire,
                'iss' => JWT_ISSUER,
                'aud' => JWT_AUDIENCE
            ]
        );

        return JWT::encode($payload, JWT_SECRET, 'HS256');
    }

    // Generate refresh token (stored in DB, expires in 7 days)
    private function generateRefreshToken($userId, int $ttlSeconds = 604800)
    {
        $token = bin2hex(random_bytes(32)); // 64-char hex token
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        try {
            $stmt = $this->db->prepare('
                INSERT INTO refresh_tokens (user_id, token, expires_at) 
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$userId, $token, $expiresAt]);
            return $token;
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('Error generating refresh token: ' . $e->getMessage());
            return null;
        }
    }

    // Validate and exchange refresh token for new access token
    public function exchangeRefreshToken($data)
    {
        $refreshToken = $data['refresh_token'] ?? ($_COOKIE['refresh_token'] ?? null);

        if (!$refreshToken) {
            return [
                'success' => false,
                'code' => 401,
                'message' => 'Refresh token is required'
            ];
        }

        try {
            // Find valid, non-revoked refresh token
            $stmt = $this->db->prepare('
                SELECT rt.id, rt.user_id, rt.expires_at
                FROM refresh_tokens rt
                WHERE rt.token = ? 
                AND rt.expires_at > NOW()
                AND rt.revoked_at IS NULL
                LIMIT 1
            ');
            $stmt->execute([$refreshToken]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result) {
                return [
                    'success' => false,
                    'code' => 401,
                    'message' => 'Invalid or expired refresh token'
                ];
            }

            $userId = (int) $result['user_id'];
            try {
                (new TestAccountAccessService($this->db))->requireAccess($userId);
            } catch (\DomainException $error) {
                $this->authSessionService->revokeByRefreshToken((string) $refreshToken);
                return ['success' => false, 'code' => 403, 'message' => $error->getMessage()];
            }
            $refreshTokenId = (int) $result['id'];
            $refreshSession = $this->authSessionService
                ->validateRefreshSession($userId, $refreshTokenId);

            if (!$refreshSession) {
                $this->authSessionService->revokeByRefreshToken(
                    (string) $refreshToken
                );

                return [
                    'success' => false,
                    'code' => 401,
                    'message' => 'Session expired due to inactivity'
                ];
            }

            // Get user and generate new access token
            $userLookup = $this->usersApi->get($userId);
            // UsersAPI::get() returns a {success, data} envelope; unwrap it.
            $userData = ($userLookup['success'] ?? false) ? $userLookup['data'] : null;

            if (!$userData || empty($userData['id'])) {
                return [
                    'success' => false,
                    'code' => 401,
                    'message' => 'User not found'
                ];
            }

            $rolesResult = $this->userRoleManager->getUserRoles($userId);
            $permissionsResult = $this->userPermissionManager
                ->getEffectivePermissions($userId);
            $userData['roles'] = $rolesResult['data'] ?? [];
            $userData['permissions'] =
                $permissionsResult['data'] ?? [];

            // An already-valid refresh session represents an active login.
            // Do not interrupt a user who is actively working every hour just
            // because the short-lived access JWT is being renewed. The idle
            // session service above still expires inactive sessions after the
            // configured idle window; the next interactive login then passes
            // through the normal MFA gate.

            // Extract permission codes only
            $permissionCodes = [];
            if (!empty($userData['permissions'])) {
                foreach ($userData['permissions'] as $perm) {
                    $code = is_array($perm) ? ($perm['code'] ?? $perm['permission_code'] ?? null) : $perm;
                    if ($code) {
                        $permissionCodes[] = $code;
                    }
                }
            }

            // Generate new access token — same compact format as login().
            // Permissions are NOT included in the JWT (they live in localStorage)
            // to keep the token small and avoid "Request Header Too Large" errors.
            $newToken = $this->generateToken([
                'user_id' => $userData['id'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'roles' => $userData['roles'] ?? [],
                'display_name' => $userData['first_name'] . ' ' . $userData['last_name'],
            ]);

            // Reuse the same proven envelope builder as login so the SPA can
            // rehydrate a full session (user_data / permissions / roles /
            // sidebar / dashboard) from the refresh cookie alone. This is what
            // lets a fresh window / incognito / cleared-cache survive without
            // an immediate logout — web-storage is empty, but the refresh
            // cookie is still valid, so one refresh restores everything.
            $roleIds = [];
            $primaryRole = null;
            foreach ($userData['roles'] ?? [] as $r) {
                $roleId = is_array($r)
                    ? ($r['id'] ?? $r['role_id'] ?? null)
                    : (is_numeric($r) ? $r : null);
                if ($roleId !== null && (int) $roleId > 0) {
                    $roleIds[] = (int) $roleId;
                }
                if ($primaryRole === null && is_array($r)) {
                    $primaryRole = $r['name'] ?? null;
                } elseif (
                    $primaryRole === null &&
                    is_string($r) &&
                    !is_numeric($r)
                ) {
                    $primaryRole = $r;
                }
            }
            $roleIds = array_values(array_unique($roleIds));
            $primaryRoleId = $roleIds[0] ?? null;

            if ($this->useDatabaseConfig) {
                $loginData = $this->buildLoginResponseFromDatabase(
                    $userData,
                    $primaryRoleId,
                    $roleIds,
                    $newToken,
                    false,
                    $refreshToken
                );
            } else {
                $loginData = $this->buildLoginResponseFromFiles(
                    $userData,
                    $primaryRole,
                    $primaryRoleId,
                    $roleIds,
                    $newToken,
                    false,
                    $refreshToken
                );
            }

            // Override the dashboard key when profile completion is required.
            // This matches the login-time check at lines 422–438 so the
            // client-side AppRouteAccess.authorizeRoute() still sees the
            // correct dashboard key after a page refresh (when only the
            // HttpOnly refresh cookie survives, not localStorage).
            if ($this->staffProfileCompletionRequired((int) $userData['id'])) {
                $loginData['data']['profile_completion_required'] = true;
                $loginData['data']['dashboard'] = [
                    'key'   => 'complete_staff_profile',
                    'url'   => 'complete_staff_profile',
                    'label' => 'Complete Staff Profile',
                ];
            }

            $loginData['data']['csrf_token'] = $this->generateCsrfToken(
                (int) $userData['id']
            );

            $refreshResult = $this->attachTrackedSession(
                $loginData,
                (int) $userData['id'],
                $newToken,
                $result
            );
            unset($refreshResult['data']['refresh_token']);
            return $refreshResult;
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('Error exchanging refresh token: ' . $e->getMessage());
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Token refresh failed'
            ];
        }
    }

    // Revoke refresh token (logout)
    public function revokeRefreshToken($data)
    {
        $refreshToken = $data['refresh_token'] ?? ($_COOKIE['refresh_token'] ?? null);

        if (!$refreshToken) {
            return [
                'success' => false,
                'message' => 'Refresh token is required'
            ];
        }

        try {
            $this->authSessionService->revokeByRefreshToken(
                (string) $refreshToken
            );

            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('refresh_token', '', time() - 3600, '/', '', $secure, true);
            header("Set-Cookie: refresh_token=deleted; Path=/; Max-Age=0; HttpOnly; " . ($secure ? 'Secure; ' : '') . "SameSite=Lax");

            return [
                'success' => true,
                'message' => 'Refresh token revoked successfully'
            ];
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('Error revoking refresh token: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Token revocation failed'
            ];
        }
    }

    /**
     * Attach the access token to the existing canonical refresh session.
     */
    private function attachTrackedSession(
        array $loginData,
        int $userId,
        string $accessToken,
        ?array $refreshRecord = null
    ): array {
        if (
            ($loginData['status'] ?? '') !== 'success' ||
            !isset($loginData['data']) ||
            !is_array($loginData['data'])
        ) {
            return $loginData;
        }

        $refreshToken = (string) (
            $loginData['data']['refresh_token'] ?? ''
        );
        if ($refreshRecord === null && $refreshToken !== '') {
            $stmt = $this->db->prepare(
                'SELECT id, user_id, expires_at
                 FROM refresh_tokens
                 WHERE token = ?
                   AND user_id = ?
                   AND revoked_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([$refreshToken, $userId]);
            $resolved = $stmt->fetch(\PDO::FETCH_ASSOC);
            $refreshRecord = $resolved ?: null;
        }

        $refreshTokenId = isset($refreshRecord['id'])
            ? (int) $refreshRecord['id']
            : null;
        $expiresAt = (string) (
            $refreshRecord['expires_at']
                ?? date('Y-m-d H:i:s', time() + JWT_EXPIRY)
        );

        $sessionId = $this->authSessionService->upsertAccessSession(
            $userId,
            $accessToken,
            $refreshTokenId,
            $expiresAt
        );
        $loginData['data']['session_id'] = $sessionId;

        return $loginData;
    }

    private function createPasswordSetupInvitation(int $userId): string
    {
        $stmt = $this->db->prepare('
            SELECT p.email, s.id AS staff_id
            FROM users u
            LEFT JOIN persons p ON p.id = u.person_id
            LEFT JOIN staff s ON s.person_id = u.person_id
            WHERE u.id = ?
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || empty($row['email'])) {
            throw new \RuntimeException('User email is required for password setup.');
        }

        $this->db->prepare("
            UPDATE user_invitations
            SET status = 'revoked', revoked_at = NOW(), updated_at = NOW()
            WHERE user_id = ? AND status = 'pending'
        ")->execute([$userId]);

        $token = bin2hex(random_bytes(32));
        $this->db->prepare("
            INSERT INTO user_invitations
                (user_id, staff_id, email, token_hash, status, expires_at, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 72 HOUR), ?, NOW(), NOW())
        ")->execute([
            $userId,
            $row['staff_id'] ? (int)$row['staff_id'] : null,
            strtolower($row['email']),
            hash('sha256', $token),
            $userId,
        ]);

        return $token;
    }

    private function passwordSetupUrl(string $token): string
    {
        $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $appBase = preg_replace('#/api$#', '', rtrim($scriptDir, '/'));
            $appBase = ($appBase === '/' || $appBase === '.') ? '' : $appBase;
            $baseUrl = $scheme . '://' . $host . $appBase;
        }

        return $baseUrl . '/reset_default_password.php?token=' . rawurlencode($token);
    }

    private function staffProfileCompletionRequired(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare('
                SELECT u.profile_completed_at, s.id AS staff_id, p.phone, p.gender, p.dob, p.email
                FROM users u
                JOIN staff s ON s.person_id = u.person_id
                JOIN persons p ON p.id = s.person_id
                WHERE u.id = ?
                LIMIT 1
            ');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            if (!empty($row['profile_completed_at'])) {
                return false;
            }

            $details = $this->db->prepare("SELECT EXISTS (SELECT 1 FROM person_addresses WHERE person_id=(SELECT person_id FROM staff WHERE id=? ) AND address_type='residential' AND valid_to IS NULL) AS has_address, EXISTS (SELECT 1 FROM person_marital_statuses WHERE person_id=(SELECT person_id FROM staff WHERE id=? ) AND valid_to IS NULL) AS has_marital");
            $details->execute([(int) $row['staff_id'], (int) $row['staff_id']]);
            $detailRow = $details->fetch(\PDO::FETCH_ASSOC) ?: [];
            $hasStaffData = !empty($row['phone'])
                && !empty($row['gender'])
                && !empty($row['dob'])
                && !empty($row['email'])
                && !empty($detailRow['has_address'])
                && !empty($detailRow['has_marital']);

            return !$hasStaffData;
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('Staff profile completion check failed: ' . $error->getMessage());
            return false;
        }
    }

    // Send reset email
    private function sendResetEmail($email, $username, $resetLink)
    {
        $template = "Dear {{username}},\n\n"
            . "A password reset has been requested for your account.\n"
            . "Please click the link below to reset your password:\n\n"
            . "{{resetLink}}\n\n"
            . "This link will expire in 1 hour.\n"
            . "If you did not request this reset, please ignore this email.";

        $subject = 'Password Reset Request';
        $body = $this->parseTemplate($template, [
            'username' => $username,
            'resetLink' => $resetLink
        ]);
        return $this->communicationsApi->sendEmail(
            [$email],
            $subject,
            $body
        );
    }

    public function resetDefaultPassword($data)
    {
        $token = trim($data['token'] ?? '');
        $newPassword = $data['new_password'] ?? $data['password'] ?? null;

        if ($token === '' || !$newPassword) {
            return [
                'success' => false,
                'message' => 'Token and new password are required.'
            ];
        }

        $passwordValidation = ValidationHelper::validatePassword($newPassword);
        if (!$passwordValidation['valid']) {
            return [
                'success' => false,
                'message' => $passwordValidation['error']
            ];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare('
                SELECT ui.id, ui.user_id, ui.staff_id,
                       EXISTS(
                           SELECT 1 FROM users account
                           JOIN parents parent_record ON parent_record.person_id = account.person_id
                           WHERE account.id = ui.user_id AND parent_record.status = "active"
                       ) AS is_parent
                FROM user_invitations ui
                WHERE ui.token_hash = ?
                  AND status = "pending"
                  AND expires_at > NOW()
                LIMIT 1
                FOR UPDATE
            ');
            $stmt->execute([hash('sha256', $token)]);
            $invitation = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$invitation) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Invalid or expired setup link.'
                ];
            }

            $stmt = $this->db->prepare('
                UPDATE users
                SET password_hash = ?,
                    status = "active",
                    password_changed_at = NOW(),
                    force_password_change = 0,
                    updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([
                password_hash($newPassword, PASSWORD_DEFAULT),
                (int) $invitation['user_id']
            ]);

            $stmt = $this->db->prepare('
                UPDATE user_invitations
                SET status = "accepted", accepted_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([(int) $invitation['id']]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Password has been updated. You may now sign in.',
                'data' => [
                    'account_type' => !empty($invitation['is_parent']) && empty($invitation['staff_id'])
                        ? 'parent'
                        : 'staff'
                ]
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('Default password reset failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Password setup failed. Please request a new setup link.'
            ];
        }
    }

    private function parseTemplate($template, $data)
    {
        $parsed = $template;
        foreach ($data as $key => $value) {
            $parsed = str_replace('{{' . $key . '}}', $value, $parsed);
        }
        return $parsed;
    }

    /**
     * Normalize user permissions payload to a flat list of permission codes
     */
    private function normalizeUserPermissions(array $userData): array
    {
        $perms = $userData['permissions'] ?? [];
        $codes = [];
        foreach ($perms as $p) {
            if (is_array($p)) {
                $codes[] = $p['code'] ?? $p['permission_code'] ?? null;
            } else {
                $codes[] = $p;
            }
        }
        $codes = array_values(array_filter(array_unique($codes)));
        $userData['permissions'] = $codes;
        return $userData;
    }

    /**
     * Check if the sidebar already contains a top-level item pointing to a given route name.
     * Used to avoid duplicating the dashboard entry when DB sidebar menus already include it.
     */
    private function sidebarAlreadyHasRoute(array $items, string $routeName): bool
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = $item['url'] ?? '';
            // Normalize to bare route name for comparison
            if (strpos($url, 'route=') !== false) {
                $pos = strpos($url, 'route=');
                $url = substr($url, $pos + strlen('route='));
                $url = strtok($url, '&');
            }
            if ($url === $routeName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return the file-driven sidebar for the given role.
     *
     * Delegates to SidebarConfigReader (the single source of truth), which
     * reads config/role_sidebars.php and normalises the shape used everywhere.
     * Returns [] when the role has no entry, so callers can treat a missing
     * config as "no menu" rather than a hard failure.
     */
    private function getHardcodedSidebarItems(int $roleId): ?array
    {
        if ($roleId <= 0) {
            return [];
        }
        $items = \App\API\Services\SidebarConfigReader::forRole($roleId);
        return $items;
    }

    private function getHardcodedSidebarItemsForRoles(array $roleIds): array
    {
        return \App\API\Services\SidebarConfigReader::forRoles($roleIds);
    }

    /**
     * Normalize sidebar item URLs: convert 'home.php?route=xyz' to 'xyz' recursively
     */
    private function normalizeSidebarItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                $normalized[] = $item;
                continue;
            }
            $it = $item;
            $url = $it['url'] ?? null;
            if ($url && is_string($url)) {
                // handle full query string: home.php?route=...
                if (strpos($url, 'route=') !== false) {
                    $parts = parse_url($url);
                    if (isset($parts['query'])) {
                        parse_str($parts['query'], $qs);
                        if (!empty($qs['route'])) {
                            $it['url'] = $qs['route'];
                        }
                    } else {
                        // fallback: extract after 'route='
                        $pos = strpos($url, 'route=');
                        if ($pos !== false) {
                            $it['url'] = substr($url, $pos + strlen('route='));
                        }
                    }
                }

                // handle legacy file-based pages (e.g., /pages/bank_accounts.php or pages/bank_accounts.php)
                if (strpos($url, 'pages/') !== false) {
                    $base = basename($url); // e.g., bank_accounts.php
                    $base = preg_replace('/\.php$/i', '', $base);
                    if ($base) {
                        $it['url'] = $base;
                    }
                }
            }

            // Recursively normalize subitems
            if (!empty($it['subitems']) && is_array($it['subitems'])) {
                $it['subitems'] = $this->normalizeSidebarItems($it['subitems']);
            }

            $normalized[] = $it;
        }
        return $normalized;
    }

    /**
     * Generate a CSRF token for the frontend to include in mutating requests.
     * Must match CsrfMiddleware::validateToken().
     */
    private function generateCsrfToken(int $userId): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(16));
        $plaintext = $userId . ':' . $timestamp . ':' . $random;
        $signature = hash_hmac('sha256', $plaintext, JWT_SECRET);
        return base64_encode($plaintext . ':' . $signature);
    }
}
