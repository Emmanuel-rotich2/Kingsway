<?php
declare(strict_types=1);

namespace App\API\Modules\students;

class StudentPermissionService
{
    private const CONTEXTS = [
        'full_management',
        'oversight',
        'academic',
        'discipline',
        'boarding',
        'transport',
        'catering',
        'welfare',
        'teacher_class',
        'subject_teacher',
        'parent_children',
    ];

    private const CONTEXT_ROLE_MAP = [
        'full_management' => ['system_administrator', 'school_administrator', 'admin', 'registrar'],
        'oversight' => ['director', 'director_owner', 'headteacher'],
        'academic' => ['deputy_head_academic', 'headteacher'],
        'discipline' => ['deputy_head_discipline', 'deputy_disc', 'headteacher'],
        'boarding' => ['boarding_master', 'matron', 'housemother', 'boardingmaster'],
        'transport' => ['driver'],
        'catering' => ['cateress', 'catering_manager', 'cook_lead', 'kitchenstaff'],
        'welfare' => ['school_counselor', 'counselor', 'chaplain', 'school_counselor_chaplain'],
        'teacher_class' => ['class_teacher'],
        'subject_teacher' => ['subject_teacher', 'teacher', 'intern_teacher', 'intern_student_teacher'],
        'parent_children' => ['parent', 'guardian'],
    ];

    private const ACTIONS = [
        'full_management' => ['view', 'profile', 'create', 'edit', 'archive', 'import', 'id_cards'],
        'oversight' => ['view', 'profile', 'export_summary'],
        'academic' => ['view', 'profile', 'promotion_tools', 'academic_notes'],
        'discipline' => ['view', 'profile', 'discipline_notes'],
        'boarding' => ['view', 'profile', 'boarding_roll_call'],
        'transport' => ['view', 'transport_attendance'],
        'catering' => ['view', 'meal_planning'],
        'welfare' => ['view', 'profile', 'welfare_notes'],
        'teacher_class' => ['view', 'profile', 'class_notes'],
        'subject_teacher' => ['view', 'profile', 'subject_notes'],
        'parent_children' => ['view', 'profile'],
    ];

    private const FIELD_SETS = [
        'basic' => [
            'id', 'admission_no', 'first_name', 'middle_name', 'last_name', 'full_name',
            'gender', 'photo_url', 'status', 'class_id', 'class_name', 'stream_id',
            'stream_name', 'student_type', 'student_type_name', 'student_type_code',
            'boarding_status',
        ],
        'management' => [
            'date_of_birth', 'admission_date', 'assessment_number', 'assessment_status',
            'nemis_number', 'nemis_status', 'blood_group', 'parent_name', 'parent_phone',
            'parent_email', 'parent_address', 'created_at', 'updated_at',
        ],
        'academic' => ['assessment_number', 'assessment_status', 'admission_date'],
        'discipline' => ['discipline_cases_count', 'open_discipline_cases', 'highest_discipline_severity'],
        'boarding' => ['student_type', 'student_type_name', 'student_type_code', 'boarding_status'],
        'transport' => ['route_id', 'route_name', 'stop_id', 'stop_name'],
        'catering' => ['student_type', 'student_type_name', 'student_type_code', 'boarding_status'],
        'welfare' => ['date_of_birth', 'blood_group', 'parent_name', 'parent_phone'],
        'finance' => ['is_sponsored', 'sponsor_name', 'sponsor_type', 'sponsor_waiver_percentage', 'fee_balance'],
    ];

    public function normalizeContext(?string $context): ?string
    {
        $normalized = strtolower(trim((string) $context));
        $normalized = str_replace('-', '_', $normalized);
        return in_array($normalized, self::CONTEXTS, true) ? $normalized : null;
    }

    public function defaultContextForUser(array $user): ?string
    {
        $roles = $this->roleNames($user);
        foreach (self::CONTEXT_ROLE_MAP as $context => $allowedRoles) {
            if (array_intersect($roles, $allowedRoles)) {
                return $context;
            }
        }

        if ($this->hasAnyPermission($user, ['students_create', 'students_edit', 'students_delete'])) {
            return 'full_management';
        }
        if ($this->hasAnyPermission($user, ['students_view', 'students_view_all'])) {
            return 'oversight';
        }
        if ($this->hasAnyPermission($user, ['students_view_own'])) {
            return 'teacher_class';
        }

        return null;
    }

    public function canAccessContext(array $user, string $context): bool
    {
        if ($this->hasAnyPermission($user, ['*'])) {
            return true;
        }

        $roles = $this->roleNames($user);
        if (!empty(self::CONTEXT_ROLE_MAP[$context]) && array_intersect($roles, self::CONTEXT_ROLE_MAP[$context])) {
            return true;
        }

        if ($context === 'full_management') {
            return $this->hasAnyPermission($user, ['students_create', 'students_edit', 'students_delete']);
        }

        if (in_array($context, ['teacher_class', 'subject_teacher', 'parent_children'], true)) {
            return $this->hasAnyPermission($user, ['students_view_own', 'students_view']);
        }

        return $this->hasAnyPermission($user, ['students_view', 'students_view_all']);
    }

    public function actionsForContext(string $context): array
    {
        return self::ACTIONS[$context] ?? ['view'];
    }

    public function fieldsForContext(string $context): array
    {
        $fields = self::FIELD_SETS['basic'];

        switch ($context) {
            case 'full_management':
                $fields = array_merge($fields, self::FIELD_SETS['management'], self::FIELD_SETS['finance'], self::FIELD_SETS['discipline']);
                break;
            case 'oversight':
                $fields = array_merge($fields, ['admission_date'], self::FIELD_SETS['discipline']);
                break;
            case 'academic':
            case 'teacher_class':
            case 'subject_teacher':
                $fields = array_merge($fields, self::FIELD_SETS['academic']);
                break;
            case 'discipline':
                $fields = array_merge($fields, self::FIELD_SETS['discipline']);
                break;
            case 'boarding':
                $fields = array_merge($fields, self::FIELD_SETS['boarding'], ['parent_name', 'parent_phone']);
                break;
            case 'transport':
                $fields = array_merge(['id', 'admission_no', 'first_name', 'last_name', 'full_name', 'class_name', 'stream_name', 'photo_url'], self::FIELD_SETS['transport']);
                break;
            case 'catering':
                $fields = array_merge(['id', 'admission_no', 'first_name', 'last_name', 'full_name', 'class_name', 'stream_name'], self::FIELD_SETS['catering']);
                break;
            case 'welfare':
                $fields = array_merge($fields, self::FIELD_SETS['welfare']);
                break;
            case 'parent_children':
                $fields = array_merge($fields, ['date_of_birth', 'admission_date']);
                break;
        }

        return array_values(array_unique($fields));
    }

    public function filterStudentFields(array $student, string $context): array
    {
        $allowed = array_flip($this->fieldsForContext($context));
        return array_intersect_key($student, $allowed);
    }

    public function roleNames(array $user): array
    {
        $roles = [];
        foreach (['role_names', 'roles'] as $field) {
            if (empty($user[$field]) || !is_array($user[$field])) {
                continue;
            }
            foreach ($user[$field] as $role) {
                if (is_array($role)) {
                    $roles[] = $role['name'] ?? $role['role_name'] ?? '';
                } elseif (is_object($role)) {
                    $roles[] = $role->name ?? $role->role_name ?? '';
                } else {
                    $roles[] = (string) $role;
                }
            }
        }

        if (!empty($user['role_name'])) {
            $roles[] = (string) $user['role_name'];
        }

        return array_values(array_unique(array_filter(array_map([$this, 'normalizeRoleName'], $roles))));
    }

    private function hasAnyPermission(array $user, array $permissions): bool
    {
        $current = [];
        foreach (['effective_permissions', 'permissions'] as $field) {
            if (!empty($user[$field]) && is_array($user[$field])) {
                $current = array_merge($current, array_map('strtolower', array_map('strval', $user[$field])));
            }
        }
        return (bool) array_intersect(array_map('strtolower', $permissions), $current);
    }

    private function normalizeRoleName(string $roleName): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($roleName)), '_');
    }
}
