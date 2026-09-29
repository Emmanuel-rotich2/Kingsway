<?php
declare(strict_types=1);

namespace App\API\Services;

use DateTimeImmutable;
use PDO;
use Throwable;

/** Shared gate for the personal-profile and school-assignment onboarding steps. */
final class StaffProfileCompletionService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Returns true when a staff member must finish personal details or the
     * school must finish the employment assignment before dashboard access.
     */
    public function isRequired(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(<<<'SQL'
                SELECT s.id AS staff_id,
                       COALESCE(NULLIF(sep.position,''), NULLIF(s.position,'')) AS position,
                       COALESCE(sep.employment_date, s.employment_date) AS employment_date,
                       COALESCE(NULLIF(sep.contract_type,''), NULLIF(s.contract_type,'')) AS contract_type,
                       s.staff_type_id, s.staff_category_id,
                       p.phone, p.gender, p.dob, p.email,
                       EXISTS (
                           SELECT 1 FROM person_addresses pa
                           WHERE pa.person_id=p.id AND pa.address_type='residential'
                             AND pa.valid_to IS NULL AND NULLIF(TRIM(pa.address_line),'') IS NOT NULL
                       ) AS has_address,
                       (EXISTS (
                           SELECT 1 FROM staff_department_assignments sda
                           JOIN departments d ON d.id=sda.department_id
                           WHERE sda.staff_id=s.id
                             AND (sda.effective_to IS NULL OR sda.effective_to>=CURDATE())
                             AND d.status='active'
                       ) OR EXISTS (
                           SELECT 1 FROM staff_employment_profiles active_sep
                           JOIN departments d ON d.id=active_sep.department_id
                           WHERE active_sep.staff_id=s.id AND active_sep.status='active'
                             AND d.status='active'
                       )) AS has_department,
                       EXISTS (
                           SELECT 1 FROM staff_types st
                           JOIN staff_categories sc ON sc.staff_type_id=st.id
                           WHERE st.id=s.staff_type_id AND sc.id=s.staff_category_id
                             AND st.is_active=1 AND sc.is_active=1
                       ) AS has_classification
                FROM users u
                JOIN staff s ON s.person_id=u.person_id
                JOIN persons p ON p.id=s.person_id
                LEFT JOIN staff_employment_profiles sep ON sep.id=(
                    SELECT current_sep.id
                    FROM staff_employment_profiles current_sep
                    WHERE current_sep.staff_id=s.id AND current_sep.status='active'
                    ORDER BY COALESCE(current_sep.employment_date,'1000-01-01') DESC,
                             current_sep.updated_at DESC,current_sep.id DESC
                    LIMIT 1
                )
                WHERE u.id=?
                LIMIT 1
                SQL);
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                // A school staff role without its required staff/person graph is
                // a broken lifecycle state, not a non-staff account. Keep it
                // outside the application until the school repairs the link.
                // Parents and system-only operators are not staff profiles.
                $staffRole = $this->db->prepare(<<<'SQL'
                    SELECT EXISTS (
                        SELECT 1
                        FROM users u
                        JOIN user_roles ur ON ur.user_id=u.id
                        JOIN roles r ON r.id=ur.role_id
                        WHERE u.id=? AND u.status='active'
                          AND r.scope='school' AND r.is_active=1
                          AND LOWER(TRIM(r.name)) NOT IN ('parent','system administrator')
                    )
                    SQL);
                $staffRole->execute([$userId]);
                return (bool)$staffRole->fetchColumn();
            }

            $employmentDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($row['employment_date'] ?? ''));
            $assignmentComplete = !empty($row['position'])
                && $employmentDate !== false
                && $employmentDate->format('Y-m-d') === (string)$row['employment_date']
                && in_array((string)($row['contract_type'] ?? ''), ['permanent', 'contract', 'temporary'], true)
                && !empty($row['has_department'])
                && !empty($row['has_classification']);

            $dob = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($row['dob'] ?? ''));
            $personalProfileComplete = PhoneNumberNormalizer::isValid((string)($row['phone'] ?? ''))
                && in_array(strtolower(trim((string)($row['gender'] ?? ''))), ['male', 'female', 'other'], true)
                && $dob !== false
                && $dob->format('Y-m-d') === (string)$row['dob']
                && $dob <= new DateTimeImmutable('today')
                && filter_var((string)($row['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false
                && !empty($row['has_address']);

            return !$assignmentComplete || !$personalProfileComplete;
        } catch (Throwable $error) {
            Logger::legacyError('[StaffProfileCompletion] Profile gate query failed: ' . $error->getMessage());
            // Fail closed for staff lifecycle checks when profile state cannot be read.
            return true;
        }
    }
}
