<?php
namespace App\API\Modules\Reports;
use App\API\Includes\BaseAPI;

class AdmissionsReportManager extends BaseAPI
{
    public function getAdmissionStats($filters = [])
    {
        // Example: Get total admissions by year, class, gender
        $where = [];
        $params = [];
        if (!empty($filters['year'])) {
            $where[] = 'YEAR(admission_date) = ?';
            $params[] = $filters['year'];
        }
        if (!empty($filters['class_id'])) {
            $where[] = 'class_id = ?';
            $params[] = $filters['class_id'];
        }
        $sql = "SELECT YEAR(admission_date) as year, class_id, gender, COUNT(*) as total
                FROM students
                WHERE status IN ('active','alumni')";
        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY year, class_id, gender';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getConversionRates($filters = [])
    {
        // Example: Calculate conversion rate from applicants to admitted students
        $sql = "SELECT
                    COUNT(DISTINCT a.id) AS total_applicants,
                    COUNT(DISTINCT s.id) AS total_admitted,
                    (COUNT(DISTINCT s.id) / NULLIF(COUNT(DISTINCT a.id),0)) * 100 AS conversion_rate
                FROM admissions_applications a
                LEFT JOIN students s ON s.application_id = a.id
                WHERE a.status = 'submitted'";
        $stmt = $this->db->query($sql);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    public function getAlumniStats($filters = [])
    {
        // Example: Get alumni count by graduation year and gender
        $where = [];
        $params = [];
        if (!empty($filters['graduation_year'])) {
            $where[] = 'YEAR(graduation_date) = ?';
            $params[] = $filters['graduation_year'];
        }
        $sql = "SELECT YEAR(graduation_date) as graduation_year, gender, COUNT(*) as alumni_count
                FROM students
                WHERE status = 'alumni'";
        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY graduation_year, gender';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
