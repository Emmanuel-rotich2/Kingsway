<?php
namespace App\API\Modules\Schedules;

use Exception;
use PDO;

/**
 * Term & Holiday Manager
 * Handles CRUD and coordination logic for academic terms and holidays
 */
class TermHolidayManager
{

    // STUDENT: Get all schedules relevant to a student (classes, exams, events, holidays)
    public function getStudentSchedules($studentId, $termId = null)
    {
        $params = ['student_id' => $studentId];
        $sql = "SELECT cs.*, t.name as term_name, s.name as subject_name, r.name as room_name
                FROM class_schedules cs
                JOIN academic_terms t ON cs.term_id = t.id
                JOIN subjects s ON cs.subject_id = s.id
                LEFT JOIN rooms r ON cs.room_id = r.id
                WHERE cs.class_id = (SELECT class_id FROM students WHERE id = :student_id)";
        if ($termId) {
            $sql .= " AND cs.term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Add holidays for the term
        if ($termId) {
            $stmt = $this->db->prepare("SELECT * FROM holidays WHERE term_id = :term_id");
            $stmt->execute(['term_id' => $termId]);
            $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $holidays = [];
        }
        return [
            'schedules' => $schedules,
            'holidays' => $holidays
        ];
    }

    // STAFF: Get all schedules relevant to a staff member (teaching, invigilation, events, holidays)
    public function getStaffSchedules($staffId, $termId = null)
    {
        $params = ['staff_id' => $staffId];
        $sql = "SELECT cs.*, t.name as term_name, s.name as subject_name, r.name as room_name
                FROM class_schedules cs
                JOIN academic_terms t ON cs.term_id = t.id
                JOIN subjects s ON cs.subject_id = s.id
                LEFT JOIN rooms r ON cs.room_id = r.id
                WHERE cs.teacher_id = :staff_id";
        if ($termId) {
            $sql .= " AND cs.term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $teaching = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // TODO: Add invigilation, events, etc. as needed
        // Add holidays for the term
        if ($termId) {
            $stmt = $this->db->prepare("SELECT * FROM holidays WHERE term_id = :term_id");
            $stmt->execute(['term_id' => $termId]);
            $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $holidays = [];
        }
        return [
            'teaching' => $teaching,
            'holidays' => $holidays
        ];
    }

    // ADMIN: Get a full overview of terms, holidays, and all schedules for a given term
    public function getAdminTermOverview($termId)
    {
        // Term details
        $stmt = $this->db->prepare("SELECT * FROM academic_terms WHERE id = :term_id");
        $stmt->execute(['term_id' => $termId]);
        $term = $stmt->fetch(PDO::FETCH_ASSOC);
        // Holidays
        $stmt = $this->db->prepare("SELECT * FROM holidays WHERE term_id = :term_id");
        $stmt->execute(['term_id' => $termId]);
        $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // All class schedules
        $stmt = $this->db->prepare("SELECT cs.*, c.name as class_name, s.name as subject_name, r.name as room_name, t.name as teacher_name
                                    FROM class_schedules cs
                                    JOIN classes c ON cs.class_id = c.id
                                    JOIN subjects s ON cs.subject_id = s.id
                                    LEFT JOIN rooms r ON cs.room_id = r.id
                                    LEFT JOIN staff t ON cs.teacher_id = t.id
                                    WHERE cs.term_id = :term_id");
        $stmt->execute(['term_id' => $termId]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // TODO: Add events, exams, etc. as needed
        return [
            'term' => $term,
            'holidays' => $holidays,
            'schedules' => $schedules
        ];
    }
    // Get detailed info for a term, including holidays, workflow status, and related schedules
    public function getTermDetails($termId)
    {
        $sql = "SELECT t.*, w.status as workflow_status FROM academic_terms t
                LEFT JOIN workflow_instances w ON w.reference_id = t.id AND w.workflow_id = 'term_holiday_scheduling'
                WHERE t.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $termId]);
        $term = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_holidays'] = $stmt->fetchColumn();
        $stmt = $this->db->query("SELECT COUNT(*) as active_terms FROM academic_terms WHERE status = 'active'");
        $stats['active_terms'] = $stmt->fetchColumn();
        $stmt = $this->db->query("SELECT COUNT(*) as inactive_terms FROM academic_terms WHERE status != 'active'");
        $stats['inactive_terms'] = $stmt->fetchColumn();
        return $stats;
    }

    // Cross-link: get all classes/events scheduled in a term
    public function getTermClassesEvents($termId)
    {
        $result = [];
        $stmt = $this->db->prepare("SELECT * FROM class_schedules WHERE term_id = :term_id");
        $stmt->execute(['term_id' => $termId]);
        $result['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Example: events table
        if ($this->db->query("SHOW TABLES LIKE 'events'")) {
            $stmt = $this->db->prepare("SELECT * FROM events WHERE term_id = :term_id");
            $stmt->execute(['term_id' => $termId]);
            $result['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $result;
    }
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // Create a new academic term
    public function createTerm($data)
    {
        $sql = "INSERT INTO academic_terms (name, start_date, end_date, year, status) VALUES (:name, :start_date, :end_date, :year, :status)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year' => $data['year'],
            'status' => $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }

    // Update an academic term
    public function updateTerm($termId, $data)
    {
        $sql = "UPDATE academic_terms SET name = :name, start_date = :start_date, end_date = :end_date, year = :year, status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $termId,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year' => $data['year'],
            'status' => $data['status'] ?? 'active'
        ]);
        return true;
    }

    // Delete an academic term
    public function deleteTerm($termId)
    {
        $sql = "DELETE FROM academic_terms WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $termId]);
        return true;
    }

    // List all academic terms
    public function listTerms($year = null)
    {
        $sql = "SELECT * FROM academic_terms";
        $params = [];
        if ($year) {
            $sql .= " WHERE year = :year";
            $params['year'] = $year;
        }
        $sql .= " ORDER BY start_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create a new holiday
    public function createHoliday($data)
    {
        $sql = "INSERT INTO holidays (name, start_date, end_date, year, status) VALUES (:name, :start_date, :end_date, :year, :status)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year' => $data['year'],
            'status' => $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }

    // Update a holiday
    public function updateHoliday($holidayId, $data)
    {
        $sql = "UPDATE holidays SET name = :name, start_date = :start_date, end_date = :end_date, year = :year, status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $holidayId,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'year' => $data['year'],
            'status' => $data['status'] ?? 'active'
        ]);
        return true;
    }

    // Delete a holiday
    public function deleteHoliday($holidayId)
    {
        $sql = "DELETE FROM holidays WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $holidayId]);
        return true;
    }

    // List all holidays
    public function listHolidays($year = null)
    {
        $sql = "SELECT * FROM holidays";
        $params = [];
        if ($year) {
            $sql .= " WHERE year = :year";
            $params['year'] = $year;
        }
        $sql .= " ORDER BY start_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
