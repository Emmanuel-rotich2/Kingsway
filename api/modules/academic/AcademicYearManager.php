<?php
namespace App\API\Modules\academic;

use PDO;
use Exception;

/**
 * Academic Year Manager
 * 
 * Handles all academic year operations including:
 * - Creating and managing academic years
 * - Linking with academic_terms table
 * - Managing year transitions
 * - Enrollment periods
 */
class AcademicYearManager
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get current active academic year
     */
    public function getCurrentAcademicYear(): ?array
    {
        $stmt = $this->db->query("
            SELECT * FROM academic_years 
            WHERE is_current = TRUE AND status = 'active'
            LIMIT 1
        ");

        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        return $year ?: null;
    }

    /**
     * Get academic year by ID
     */
    public function getAcademicYear(int $yearId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM academic_years WHERE id = ?");
        $stmt->execute([$yearId]);
        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        return $year ?: null;
    }

    /**
     * Get academic year by code (e.g., "2025/2026")
     */
    public function getAcademicYearByCode(string $yearCode): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM academic_years WHERE year_code = ?");
        $stmt->execute([$yearCode]);
        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        return $year ?: null;
    }

    /**
     * Create new academic year
     */
    public function createAcademicYear(array $data): array
    {
        // Validate required fields
        $required = ['year_code', 'year_name', 'start_date', 'end_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Check if year code already exists
        if ($this->getAcademicYearByCode($data['year_code'])) {
            throw new Exception("Academic year {$data['year_code']} already exists");
        }

        $sql = "INSERT INTO academic_years (
            year_code, year_name, start_date, end_date,
            registration_start, registration_end, status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['year_code'],
            $data['year_name'],
            $data['start_date'],
            $data['end_date'],
            $data['registration_start'] ?? null,
            $data['registration_end'] ?? null,
            $data['status'] ?? 'planning',
            $data['created_by'] ?? null
        ]);

        $yearId = $this->db->lastInsertId();

        // Automatically create 3 terms for this academic year
        $this->createTermsForYear($yearId, $data['start_date'], $data['end_date']);

        return $this->getAcademicYear($yearId);
    }

    /**
     * Automatically create 3 terms for an academic year
     */
    private function createTermsForYear(int $yearId, string $startDate, string $endDate): void
    {
        $year = $this->getAcademicYear($yearId);
        $yearNumber = (int) substr($year['year_code'], 0, 4);

        // Calculate term dates (roughly equal divisions)
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $totalDays = $start->diff($end)->days;
        $termDays = floor($totalDays / 3);

        $terms = [
            [
                'name' => 'Term 1',
                'term_number' => 1,
                'start' => $start->format('Y-m-d'),
                'end' => (clone $start)->add(new \DateInterval("P{$termDays}D"))->format('Y-m-d')
            ],
            [
                'name' => 'Term 2',
                'term_number' => 2,
                'start' => (clone $start)->add(new \DateInterval("P" . ($termDays + 1) . "D"))->format('Y-m-d'),
                'end' => (clone $start)->add(new \DateInterval("P" . ($termDays * 2) . "D"))->format('Y-m-d')
            ],
            [
                'name' => 'Term 3',
                'term_number' => 3,
                'start' => (clone $start)->add(new \DateInterval("P" . ($termDays * 2 + 1) . "D"))->format('Y-m-d'),
                'end' => $end->format('Y-m-d')
            ]
        ];

        $sql = "INSERT INTO academic_terms (name, start_date, end_date, year, term_number, status)
                VALUES (?, ?, ?, ?, ?, 'upcoming')";

        foreach ($terms as $term) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $term['name'],
                $term['start'],
                $term['end'],
                $yearNumber,
                $term['term_number']
            ]);
        }
    }

    /**
     * Set a year as current (and unset others)
     */
    public function setCurrentYear(int $yearId): bool
    {
        $this->db->beginTransaction();

        try {
            // Unset all other years
            $this->db->exec("UPDATE academic_years SET is_current = FALSE");

            // Set this year as current
            $stmt = $this->db->prepare("UPDATE academic_years SET is_current = TRUE WHERE id = ?");
            $stmt->execute([$yearId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update academic year status
     */
    public function updateYearStatus(int $yearId, string $status): bool
    {
        $validStatuses = ['planning', 'registration', 'active', 'closing', 'archived'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status: $status");
        }

        $stmt = $this->db->prepare("UPDATE academic_years SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $yearId]);
    }

    /**
     * Archive an academic year
     */
    public function archiveYear(int $yearId, int $userId, string $notes = null): bool
    {
        $year = $this->getAcademicYear($yearId);
        if (!$year) {
            throw new Exception("Academic year not found");
        }

        $this->db->beginTransaction();

        try {
            // Update academic_years status
            $this->updateYearStatus($yearId, 'archived');

            // Update academic_year_archives table
            $archiveData = $this->getArchiveStats($yearId);

            $sql = "INSERT INTO academic_year_archives (
                academic_year, status, total_students, promoted_count,
                retained_count, transferred_count, graduated_count,
                closure_initiated_by, closure_date, closure_notes, archived_at
            ) VALUES (?, 'archived', ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())
            ON DUPLICATE KEY UPDATE
                status = 'archived',
                total_students = VALUES(total_students),
                promoted_count = VALUES(promoted_count),
                retained_count = VALUES(retained_count),
                transferred_count = VALUES(transferred_count),
                graduated_count = VALUES(graduated_count),
                closure_initiated_by = VALUES(closure_initiated_by),
                closure_date = NOW(),
                closure_notes = VALUES(closure_notes),
                archived_at = NOW()";

            $yearNumber = (int) substr($year['year_code'], 0, 4) + 1; // e.g., 2025/2026 -> 2026

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $yearNumber,
                $archiveData['total_students'],
                $archiveData['promoted_count'],
                $archiveData['retained_count'],
                $archiveData['transferred_count'],
                $archiveData['graduated_count'],
                $userId,
                $notes
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get statistics for archiving
     */
    private function getArchiveStats(int $yearId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_students,
                SUM(CASE WHEN promotion_status = 'promoted' THEN 1 ELSE 0 END) as promoted_count,
                SUM(CASE WHEN promotion_status = 'retained' THEN 1 ELSE 0 END) as retained_count,
                SUM(CASE WHEN promotion_status = 'transferred' THEN 1 ELSE 0 END) as transferred_count,
                SUM(CASE WHEN promotion_status = 'graduated' THEN 1 ELSE 0 END) as graduated_count
            FROM class_enrollments
            WHERE academic_year_id = ?
        ");
        $stmt->execute([$yearId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get next academic year code
     */
    public function getNextYearCode(string $currentCode): string
    {
        // Extract years from code like "2025/2026"
        if (preg_match('/^(\d{4})\/(\d{4})$/', $currentCode, $matches)) {
            $year1 = (int) $matches[1] + 1;
            $year2 = (int) $matches[2] + 1;
            return "{$year1}/{$year2}";
        }

        throw new Exception("Invalid year code format: $currentCode");
    }

    /**
     * Create next academic year based on current one
     */
    public function createNextYear(int $userId): array
    {
        $currentYear = $this->getCurrentAcademicYear();
        if (!$currentYear) {
            throw new Exception("No current academic year found");
        }

        $nextYearCode = $this->getNextYearCode($currentYear['year_code']);

        // Calculate dates (start from January of next year)
        $currentEnd = new \DateTime($currentYear['end_date']);
        $nextStart = (clone $currentEnd)->add(new \DateInterval('P45D')); // 45 days after current year ends
        $nextEnd = (clone $nextStart)->add(new \DateInterval('P320D')); // ~320 days (11 months)

        return $this->createAcademicYear([
            'year_code' => $nextYearCode,
            'year_name' => "Academic Year {$nextYearCode}",
            'start_date' => $nextStart->format('Y-m-d'),
            'end_date' => $nextEnd->format('Y-m-d'),
            'status' => 'planning',
            'created_by' => $userId
        ]);
    }

    /**
     * Get all academic years
     */
    public function getAllYears(array $filters = []): array
    {
        $sql = "SELECT * FROM academic_years WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY start_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get terms for an academic year
     */
    public function getTermsForYear(int $yearId): array
    {
        $year = $this->getAcademicYear($yearId);
        if (!$year) {
            throw new Exception("Academic year not found");
        }

        $yearNumber = (int) substr($year['year_code'], 0, 4);

        $stmt = $this->db->prepare("
            SELECT * FROM academic_terms 
            WHERE year = ? 
            ORDER BY term_number ASC
        ");
        $stmt->execute([$yearNumber]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get current active term
     */
    public function getCurrentTerm(): ?array
    {
        $stmt = $this->db->query("
            SELECT * FROM academic_terms 
            WHERE status = 'current' 
            LIMIT 1
        ");
        $term = $stmt->fetch(PDO::FETCH_ASSOC);
        return $term ?: null;
    }
}
