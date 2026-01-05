<?php
/**
 * Policy Engine Service
 * 
 * Evaluates system policies against user actions.
 * Implements the rule engine for deny/allow/restrict/require/audit policies.
 * 
 * @package App\Services
 * @since 2025-12-28
 */

namespace App\Services;

use App\Database\Database;
use Exception;

class PolicyEngine
{
    private static ?PolicyEngine $instance = null;
    private Database $db;
    private array $policyCache = [];
    private bool $cacheLoaded = false;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): PolicyEngine
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load and cache active policies
     */
    private function loadPolicies(): void
    {
        if ($this->cacheLoaded) {
            return;
        }

        $stmt = $this->db->query(
            "SELECT * FROM system_policies 
             WHERE is_active = 1
             AND (effective_from IS NULL OR effective_from <= NOW())
             AND (effective_until IS NULL OR effective_until > NOW())
             ORDER BY priority DESC"
        );

        $this->policyCache = $stmt->fetchAll();
        $this->cacheLoaded = true;
    }

    /**
     * Clear policy cache (call after policy changes)
     */
    public function clearCache(): void
    {
        $this->policyCache = [];
        $this->cacheLoaded = false;
    }

    /**
     * Evaluate all policies for a route access attempt
     * 
     * @param array $context Contains user_id, role_id, route info, permission info
     * @return array ['allowed' => bool, 'reason' => string, 'policy' => ?string]
     */
    public function evaluate(array $context): array
    {
        $this->loadPolicies();

        $result = [
            'allowed' => true,
            'reason' => 'no_policy_match',
            'policy' => null,
            'audit_log' => []
        ];

        foreach ($this->policyCache as $policy) {
            $evaluation = $this->evaluatePolicy($policy, $context);

            // Log audit policies
            if ($policy['rule_type'] === 'audit' && $evaluation['matches']) {
                $result['audit_log'][] = [
                    'policy' => $policy['name'],
                    'context' => $context,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                continue; // Audit policies don't affect authorization
            }

            if ($evaluation['matches']) {
                switch ($policy['rule_type']) {
                    case 'deny':
                        return [
                            'allowed' => false,
                            'reason' => 'denied_by_policy',
                            'policy' => $policy['name'],
                            'policy_display_name' => $policy['display_name'],
                            'description' => $policy['description']
                        ];

                    case 'allow':
                        // Allow policies can override default deny
                        $result = [
                            'allowed' => true,
                            'reason' => 'allowed_by_policy',
                            'policy' => $policy['name']
                        ];
                        break;

                    case 'restrict':
                        // Restrict policies modify context (e.g., read-only)
                        $result['restrictions'][] = [
                            'policy' => $policy['name'],
                            'expression' => $policy['rule_expression']
                        ];
                        break;

                    case 'require':
                        // Require policies enforce additional conditions
                        $requireMet = $this->checkRequirement($policy, $context);
                        if (!$requireMet) {
                            return [
                                'allowed' => false,
                                'reason' => 'requirement_not_met',
                                'policy' => $policy['name'],
                                'description' => $policy['description']
                            ];
                        }
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * Evaluate a single policy against context
     */
    private function evaluatePolicy(array $policy, array $context): array
    {
        $ruleExpression = json_decode($policy['rule_expression'], true);

        if (!$ruleExpression) {
            return ['matches' => false, 'error' => 'Invalid rule expression'];
        }

        // Check if policy applies to this target
        if (!$this->policyApplies($policy, $context)) {
            return ['matches' => false, 'reason' => 'policy_not_applicable'];
        }

        // Evaluate the rule expression
        $matches = $this->evaluateRuleExpression($ruleExpression, $context);

        return ['matches' => $matches];
    }

    /**
     * Check if a policy applies to the given context
     */
    private function policyApplies(array $policy, array $context): bool
    {
        $appliesTo = $policy['applies_to'];
        $targetIds = json_decode($policy['target_ids'] ?? '[]', true) ?: [];

        switch ($appliesTo) {
            case 'global':
                return true;

            case 'role':
                $roleId = $context['role_id'] ?? null;
                return empty($targetIds) || in_array($roleId, $targetIds);

            case 'user':
                $userId = $context['user_id'] ?? null;
                return empty($targetIds) || in_array($userId, $targetIds);

            case 'route':
                $routeId = $context['route']['id'] ?? null;
                return empty($targetIds) || in_array($routeId, $targetIds);

            case 'domain':
                $domain = $context['route']['domain'] ?? null;
                return empty($targetIds) || in_array($domain, $targetIds);

            default:
                return false;
        }
    }

    /**
     * Evaluate a rule expression (JSON DSL)
     * 
     * Expression format:
     * {
     *   "condition": "AND|OR",
     *   "rules": [
     *     {"field": "role_id", "operator": "=", "value": 2},
     *     {"field": "route.domain", "operator": "=", "value": "SCHOOL"}
     *   ]
     * }
     * 
     * Nested expressions:
     * {
     *   "condition": "OR",
     *   "rules": [
     *     {"condition": "AND", "rules": [...]},
     *     {"field": "...", "operator": "...", "value": "..."}
     *   ]
     * }
     */
    private function evaluateRuleExpression(array $expression, array $context): bool
    {
        // Check if this is a leaf rule or a nested expression
        if (isset($expression['field'])) {
            return $this->evaluateSingleRule($expression, $context);
        }

        if (!isset($expression['condition']) || !isset($expression['rules'])) {
            return false;
        }

        $condition = strtoupper($expression['condition']);
        $results = [];

        foreach ($expression['rules'] as $rule) {
            $results[] = $this->evaluateRuleExpression($rule, $context);
        }

        if (empty($results)) {
            return false;
        }

        switch ($condition) {
            case 'AND':
                return !in_array(false, $results, true);
            case 'OR':
                return in_array(true, $results, true);
            case 'NOT':
                return !$results[0];
            case 'XOR':
                $trueCount = count(array_filter($results));
                return $trueCount === 1;
            default:
                return false;
        }
    }

    /**
     * Evaluate a single rule
     */
    private function evaluateSingleRule(array $rule, array $context): bool
    {
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? '=';
        $expected = $rule['value'] ?? null;

        // Resolve the field value from context
        $actual = $this->resolveFieldValue($field, $context);

        return $this->compareValues($actual, $operator, $expected);
    }

    /**
     * Resolve a dot-notation field path to its value
     */
    private function resolveFieldValue(string $field, array $context)
    {
        $parts = explode('.', $field);
        $current = $context;

        foreach ($parts as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } elseif (is_object($current) && property_exists($current, $part)) {
                $current = $current->$part;
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Compare values using the specified operator
     */
    private function compareValues($actual, string $operator, $expected): bool
    {
        $operator = strtoupper(trim($operator));

        switch ($operator) {
            case '=':
            case '==':
            case 'EQ':
            case 'EQUALS':
                return $actual == $expected;

            case '===':
            case 'STRICT_EQUALS':
                return $actual === $expected;

            case '!=':
            case '<>':
            case 'NEQ':
            case 'NOT_EQUALS':
                return $actual != $expected;

            case '>':
            case 'GT':
                return $actual > $expected;

            case '>=':
            case 'GTE':
                return $actual >= $expected;

            case '<':
            case 'LT':
                return $actual < $expected;

            case '<=':
            case 'LTE':
                return $actual <= $expected;

            case 'IN':
                return is_array($expected) && in_array($actual, $expected);

            case 'NOT_IN':
            case 'NOT IN':
                return is_array($expected) && !in_array($actual, $expected);

            case 'CONTAINS':
                return is_string($actual) && is_string($expected) && str_contains($actual, $expected);

            case 'NOT_CONTAINS':
            case 'NOT CONTAINS':
                return is_string($actual) && is_string($expected) && !str_contains($actual, $expected);

            case 'STARTS_WITH':
            case 'STARTS':
                return is_string($actual) && is_string($expected) && str_starts_with($actual, $expected);

            case 'ENDS_WITH':
            case 'ENDS':
                return is_string($actual) && is_string($expected) && str_ends_with($actual, $expected);

            case 'MATCHES':
            case 'REGEX':
                return is_string($actual) && is_string($expected) && preg_match($expected, $actual);

            case 'IS_NULL':
            case 'NULL':
                return $actual === null;

            case 'IS_NOT_NULL':
            case 'NOT_NULL':
                return $actual !== null;

            case 'IS_EMPTY':
            case 'EMPTY':
                return empty($actual);

            case 'IS_NOT_EMPTY':
            case 'NOT_EMPTY':
                return !empty($actual);

            case 'IS_TRUE':
            case 'TRUE':
                return $actual === true || $actual === 1 || $actual === '1';

            case 'IS_FALSE':
            case 'FALSE':
                return $actual === false || $actual === 0 || $actual === '0';

            case 'BETWEEN':
                if (is_array($expected) && count($expected) === 2) {
                    return $actual >= $expected[0] && $actual <= $expected[1];
                }
                return false;

            case 'NOT_BETWEEN':
            case 'NOT BETWEEN':
                if (is_array($expected) && count($expected) === 2) {
                    return $actual < $expected[0] || $actual > $expected[1];
                }
                return false;

            default:
                return false;
        }
    }

    /**
     * Check if a 'require' policy requirement is met
     */
    private function checkRequirement(array $policy, array $context): bool
    {
        $ruleExpression = json_decode($policy['rule_expression'], true);

        if (!$ruleExpression || !isset($ruleExpression['requires'])) {
            return true; // No specific requirement
        }

        return $this->evaluateRuleExpression($ruleExpression['requires'], $context);
    }

    /**
     * Enforce policy on a route access
     * 
     * @param int $userId
     * @param int $roleId
     * @param array $route Route information from database
     * @param array $permissions User's effective permissions
     * @return array
     */
    public function enforceRouteAccess(int $userId, int $roleId, array $route, array $permissions = []): array
    {
        $context = [
            'user_id' => $userId,
            'role_id' => $roleId,
            'route' => $route,
            'permissions' => $permissions,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $result = $this->evaluate($context);

        // Log audit entries if any
        if (!empty($result['audit_log'])) {
            $this->logAuditEntries($result['audit_log']);
        }

        return $result;
    }

    /**
     * Log audit entries
     */
    private function logAuditEntries(array $entries): void
    {
        try {
            foreach ($entries as $entry) {
                $this->db->query(
                    "INSERT INTO audit_logs (action, details, user_id, status, created_at)
                     VALUES ('policy_audit', ?, ?, 'info', NOW())",
                    [
                        json_encode($entry),
                        $entry['context']['user_id'] ?? null
                    ]
                );
            }
        } catch (Exception $e) {
            error_log("Failed to log policy audit: " . $e->getMessage());
        }
    }

    /**
     * Create common policy presets
     */
    public static function createPreset(string $preset): array
    {
        $presets = [
            'system_admin_no_school' => [
                'name' => 'system_admin_no_school_access',
                'display_name' => 'System Admin - No School Operations',
                'rule_type' => 'deny',
                'priority' => 100,
                'rule_expression' => [
                    'condition' => 'AND',
                    'rules' => [
                        ['field' => 'role_id', 'operator' => '=', 'value' => 2],
                        ['field' => 'route.domain', 'operator' => '=', 'value' => 'SCHOOL']
                    ]
                ],
                'applies_to' => 'role',
                'target_ids' => [2]
            ],
            'school_no_system' => [
                'name' => 'school_roles_no_system_access',
                'display_name' => 'School Roles - No System Administration',
                'rule_type' => 'deny',
                'priority' => 99,
                'rule_expression' => [
                    'condition' => 'AND',
                    'rules' => [
                        ['field' => 'role_id', 'operator' => 'NOT IN', 'value' => [2]],
                        ['field' => 'route.domain', 'operator' => '=', 'value' => 'SYSTEM']
                    ]
                ],
                'applies_to' => 'domain',
                'target_ids' => ['SYSTEM']
            ],
            'delegated_readonly' => [
                'name' => 'delegated_permissions_readonly',
                'display_name' => 'Delegated Permissions - Read Only',
                'rule_type' => 'restrict',
                'priority' => 80,
                'rule_expression' => [
                    'condition' => 'AND',
                    'rules' => [
                        ['field' => 'permission.is_delegated', 'operator' => '=', 'value' => true]
                    ],
                    'restrictions' => [
                        'deny_actions' => ['create', 'update', 'delete']
                    ]
                ],
                'applies_to' => 'global',
                'target_ids' => []
            ],
            'maintenance_deny_all' => [
                'name' => 'maintenance_mode_deny',
                'display_name' => 'Maintenance Mode - Deny All Except System Admin',
                'rule_type' => 'deny',
                'priority' => 200,
                'rule_expression' => [
                    'condition' => 'AND',
                    'rules' => [
                        ['field' => 'role_id', 'operator' => 'NOT IN', 'value' => [2]],
                        ['field' => 'system.maintenance_mode', 'operator' => '=', 'value' => true]
                    ]
                ],
                'applies_to' => 'global',
                'target_ids' => [],
                'is_active' => false // Activate during maintenance
            ]
        ];

        return $presets[$preset] ?? [];
    }

    /**
     * Validate a policy rule expression
     */
    public function validateRuleExpression(array $expression): array
    {
        $errors = [];

        if (isset($expression['field'])) {
            // Leaf rule
            if (empty($expression['field'])) {
                $errors[] = 'Rule field cannot be empty';
            }
            if (empty($expression['operator'])) {
                $errors[] = 'Rule operator cannot be empty';
            }
        } elseif (isset($expression['condition'])) {
            // Compound rule
            if (!in_array(strtoupper($expression['condition']), ['AND', 'OR', 'NOT', 'XOR'])) {
                $errors[] = "Invalid condition: {$expression['condition']}";
            }
            if (empty($expression['rules']) || !is_array($expression['rules'])) {
                $errors[] = 'Compound rule must have a rules array';
            } else {
                foreach ($expression['rules'] as $index => $rule) {
                    $subErrors = $this->validateRuleExpression($rule);
                    foreach ($subErrors as $subError) {
                        $errors[] = "Rule[$index]: $subError";
                    }
                }
            }
        } else {
            $errors[] = 'Rule must have either a field or a condition';
        }

        return $errors;
    }
}
