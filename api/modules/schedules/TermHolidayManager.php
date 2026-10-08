<?php
namespace App\API\Modules\schedules;

use App\API\Services\ReadReplicaService;
use Exception;
use PDO;

/**
 * Term & Holiday Manager
 * Read-side queries for academic terms, holidays, and timetable entries.
 * Runs entirely against the normalized (live) schema.
 */
class TermHolidayManager
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // STUDENT: Get all schedules relevant to a student (classes, exams, events, holidays)
    public function getStudentSchedules($studentId, $termId = null)
    {
        $params = ['student_id' => $studentId];
        $sql = "SELECT
                    cs.*
                FROM vw_timetable_entries cs
                WHERE cs.class_id = (
                    SELECT ayc.class_id
                    FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " se
                    JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.id = se.academic_year_class_stream_id
                    JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
                    WHERE se.student_id = :student_id
                      AND se.enrollment_status = 'active'
                    ORDER BY se.id DESC
                    LIMIT 1
                )
                  AND cs.status = 'scheduled'";
        if ($termId) {
            $sql .= " AND cs.academic_year_term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $holidaysSql = "SELECT d.id, d.date, cdt.code AS day_type, cdt.name AS day_type_name, d.title, d.description, ayt.term_id AS term_id
                        FROM " . ReadReplicaService::qualifiedRef("academic_year_calendar_days") . " d
                        JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " ac ON ac.id = d.academic_year_calendar_id
                        JOIN calendar_day_types cdt ON cdt.id = d.calendar_day_type_id
                        LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.id = ac.academic_year_term_id
                        WHERE cdt.code IN ('public_holiday', 'school_holiday', 'holiday')";
        $holidayParams = [];
        if ($termId) {
            $holidaysSql .= " AND ac.academic_year_term_id = :term_id";
            $holidayParams['term_id'] = $termId;
        }
        $holidaysSql .= " ORDER BY d.date ASC";
        $holidayStmt = $this->db->prepare($holidaysSql);
        $holidayStmt->execute($holidayParams);
        $holidays = $holidayStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'schedules' => $schedules,
            'holidays' => $holidays
        ];
    }

    // STAFF: Get all schedules relevant to a staff member (teaching, invigilation, events, holidays)
    public function getStaffSchedules($staffId, $termId = null)
    {
        $params = ['staff_id' => $staffId];
        $sql = "SELECT
                    cs.*
                FROM vw_timetable_entries cs
                WHERE cs.teacher_id = :staff_id
                  AND cs.status = 'scheduled'";
        if ($termId) {
            $sql .= " AND cs.academic_year_term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $teaching = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $holidaySql = "SELECT d.id, d.date, cdt.code AS day_type, cdt.name AS day_type_name, d.title, d.description, ayt.term_id AS term_id
                       FROM " . ReadReplicaService::qualifiedRef("academic_year_calendar_days") . " d
                       JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " ac ON ac.id = d.academic_year_calendar_id
                       JOIN calendar_day_types cdt ON cdt.id = d.calendar_day_type_id
                       LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.id = ac.academic_year_term_id
                       WHERE cdt.code IN ('public_holiday', 'school_holiday', 'holiday')";
        $holidayParams = [];
        if ($termId) {
            $holidaySql .= " AND ac.academic_year_term_id = :term_id";
            $holidayParams['term_id'] = $termId;
        }
        $holidaySql .= " ORDER BY d.date ASC";
        $holidayStmt = $this->db->prepare($holidaySql);
        $holidayStmt->execute($holidayParams);
        $holidays = $holidayStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'teaching' => $teaching,
            'holidays' => $holidays
        ];
    }

    // ADMIN: Get a full overview of terms, holidays, and all schedules for a given term
    public function getAdminTermOverview($termId)
    {
        // Term details
        $stmt = $this->db->prepare("
            SELECT
                ayt.academic_year_term_id AS id,
                ayt.academic_year_id,
                ayt.term_id,
                ayt.opening_date,
                ayt.half_term_start,
                ayt.half_term_end,
                ayt.closing_date,
                ayt.term_period_status AS status,
                ayt.term_name,
                ayt.term_code,
                ayt.year_code AS academic_year
            FROM " . ReadReplicaService::qualifiedRef('academic_term') . " ayt
            WHERE ayt.academic_year_term_id = :term_id
        ");
        $stmt->execute(['term_id' => $termId]);
        $term = $stmt->fetch(PDO::FETCH_ASSOC);

        // Holiday/special-day calendar entries
        $stmt = $this->db->prepare("
            SELECT calendar_day_id AS id, calendar_date AS date,
                   day_type_code AS day_type, day_type_name,
                   day_title AS title, day_description AS description
            FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('calendar_day_type') . "
            WHERE academic_year_term_id = :term_id
              AND day_type_code IN ('public_holiday', 'school_holiday', 'holiday', 'special_event')
            ORDER BY calendar_date ASC
        ");
        $stmt->execute(['term_id' => $termId]);
        $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // All class schedules
        $stmt = $this->db->prepare("
            SELECT
                cs.*,
                cs.class_name,
                cs.subject_name,
                cs.room_name,
                cs.teacher_name
            FROM " . ReadReplicaService::qualifiedRef("activity_schedule") . "
            WHERE cs.academic_year_term_id = :term_id
              AND cs.status = 'scheduled'
            ORDER BY cs.day_of_week, cs.start_time
        ");
        $stmt->execute(['term_id' => $termId]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Term-linked activity/exam counts for quick admin view
        $activityStmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM " . ReadReplicaService::qualifiedRef("activity_schedule") . "
            JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.id = :term_id
            WHERE asch.schedule_date BETWEEN ayt.opening_date AND ayt.closing_date
        ");
        $activityStmt->execute(['term_id' => $termId]);
        $activitiesCount = (int) ($activityStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $examStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM exam_schedules WHERE academic_year_term_id = :term_id");
        $examStmt->execute(['term_id' => $termId]);
        $examsCount = (int) ($examStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        return [
            'term' => $term,
            'holidays' => $holidays,
            'schedules' => $schedules,
            'summary' => [
                'total_schedules' => count($schedules),
                'activities_count' => $activitiesCount,
                'exams_count' => $examsCount,
            ],
        ];
    }
}
