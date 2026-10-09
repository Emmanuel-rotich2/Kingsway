<?php
namespace App\API\Modules\reports;

use App\API\Services\ReadReplicaService;
use App\API\Includes\BaseAPI;

class DisciplineReportManager extends BaseAPI
{
    public function getConductCasesStats($filters = [])
    {
        // Count conduct cases by type and status
        try {
            $sql = "SELECT type AS case_type, status, COUNT(*) as total
                    FROM discipline_incidents
                    GROUP BY type, status
                    ORDER BY total DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getDisciplinaryTrends($filters = [])
    {
        // Count conduct cases per month for trend analysis
        try {
            $where = ['1=1'];
            $params = [];
            if (!empty($filters['date_from'])) { $where[] = 'di.incident_date >= ?'; $params[] = $filters['date_from']; }
            if (!empty($filters['date_to'])) { $where[] = 'di.incident_date <= ?'; $params[] = $filters['date_to']; }
            if (!empty($filters['class_id'])) { $where[] = 'ayc.class_id = ?'; $params[] = (int) $filters['class_id']; }
            if (!empty($filters['category'])) { $where[] = 'di.type = ?'; $params[] = $filters['category']; }
            $sql = "SELECT DATE_FORMAT(di.incident_date, '%Y-%m') AS period,
                           COALESCE(di.type, 'Uncategorised') AS category,
                           COUNT(*) AS incident_count,
                           SUM(CASE WHEN di.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count
                    FROM discipline_incidents di
                    JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON sae.id = di.student_academic_enrollment_id
                    JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.id = sae.academic_year_class_stream_id
                    JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY period, di.type
                    ORDER BY period DESC, category";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
