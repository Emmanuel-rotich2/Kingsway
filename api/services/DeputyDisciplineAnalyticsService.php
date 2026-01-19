<?php
namespace App\API\Services;

class DeputyDisciplineAnalyticsService
{
    private HeadteacherAnalyticsService $headteacher;

    public function __construct()
    {
        $this->headteacher = new HeadteacherAnalyticsService();
    }

    /**
     * Returns a discipline-centric view for deputy heads.
     * Surfaces cases, attendance, communications, and key events.
     */
    public function getFullDashboardData(): array
    {
        $full = $this->headteacher->getFullDashboardData();

        $cards = $full['cards'] ?? [];
        $charts = $full['charts'] ?? [];
        $tables = $full['tables'] ?? [];

        return [
            'cards' => array_filter([
                'discipline_cases' => $cards['discipline_cases'] ?? null,
                'attendance_today' => $cards['attendance_today'] ?? null,
                'parent_communications' => $cards['parent_communications'] ?? null,
            ]),
            'charts' => array_filter([
                'discipline_trend' => $charts['discipline_trend'] ?? null,
                'attendance_trend' => $charts['attendance_trend'] ?? null,
            ]),
            'tables' => array_filter([
                'discipline_cases' => $tables['discipline_cases'] ?? null,
                'upcoming_events' => $tables['upcoming_events'] ?? null,
            ]),
            'timestamp' => $full['timestamp'] ?? date('Y-m-d H:i:s'),
        ];
    }
}
