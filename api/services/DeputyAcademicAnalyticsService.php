<?php
namespace App\API\Services;

class DeputyAcademicAnalyticsService
{
    private HeadteacherAnalyticsService $headteacher;

    public function __construct()
    {
        $this->headteacher = new HeadteacherAnalyticsService();
    }

    /**
     * Returns a focused academic view for deputy heads.
     * Includes admissions, timetables, assessments, comms, and key charts/tables.
     */
    public function getFullDashboardData(): array
    {
        $full = $this->headteacher->getFullDashboardData();

        $cards = $full['cards'] ?? [];
        $charts = $full['charts'] ?? [];
        $tables = $full['tables'] ?? [];

        return [
            'cards' => array_filter([
                'pending_admissions' => $cards['pending_admissions'] ?? null,
                'class_schedules' => $cards['class_schedules'] ?? null,
                'student_assessments' => $cards['student_assessments'] ?? null,
                'parent_communications' => $cards['parent_communications'] ?? null,
                'attendance_today' => $cards['attendance_today'] ?? null,
            ]),
            'charts' => array_filter([
                'attendance_trend' => $charts['attendance_trend'] ?? null,
                'class_performance' => $charts['class_performance'] ?? null,
            ]),
            'tables' => array_filter([
                'pending_admissions' => $tables['pending_admissions'] ?? null,
                'upcoming_events' => $tables['upcoming_events'] ?? null,
            ]),
            'timestamp' => $full['timestamp'] ?? date('Y-m-d H:i:s'),
        ];
    }
}
