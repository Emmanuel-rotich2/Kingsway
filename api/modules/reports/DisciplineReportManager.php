<?php
namespace App\API\Modules\Reports;
use App\API\Includes\BaseAPI;

class DisciplineReportManager extends BaseAPI
{
    public function getConductCasesStats($filters = [])
    {
        // Example: Count conduct cases by type and status
        $sql = "SELECT case_type, status, COUNT(*) as total
                FROM conduct_cases
                GROUP BY case_type, status";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getDisciplinaryTrends($filters = [])
    {
        // Example: Count cases per month for trend analysis
        $sql = "SELECT YEAR(date_reported) as year, MONTH(date_reported) as month, COUNT(*) as total
                FROM conduct_cases
                GROUP BY year, month
                ORDER BY year DESC, month DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
