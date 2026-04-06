<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class AdmissionsReportManager extends BaseAPI
{
    public function getAdmissionStats($filters = [])
    {
        // Get total admissions by year, class, gender
        try {
            $where = ["s.status IN ('active','alumni')"];
            $params = [];
            if (!empty($filters['year'])) {
                $where[] = 'YEAR(s.admission_date) = ?';
                $params[] = $filters['year'];
            }
            if (!empty($filters['class_id'])) {
                $where[] = 'cs.class_id = ?';
                $params[] = $filters['class_id'];
            }
            $sql = "SELECT
                        YEAR(s.admission_date) AS year,
                        c.name AS class_name,
                        s.gender,
                        COUNT(*) AS total
                    FROM students s
                    LEFT JOIN class_streams cs ON cs.id = s.stream_id
                    LEFT JOIN classes c ON c.id = cs.class_id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY year, c.id, c.name, s.gender
                    ORDER BY year DESC, c.name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getConversionRates($filters = [])
    {
        // Calculate conversion rate from applicants to admitted students
        try {
            $sql = "SELECT
                        COUNT(DISTINCT a.id) AS total_applicants,
                        COUNT(DISTINCT s.id) AS total_admitted,
                        ROUND(
                            COUNT(DISTINCT s.id) / NULLIF(COUNT(DISTINCT a.id), 0) * 100,
                            2
                        ) AS conversion_rate
                    FROM admissions_applications a
                    LEFT JOIN students s ON s.application_id = a.id
                    WHERE a.status = 'submitted'";
            $stmt = $this->db->query($sql);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return ['total_applicants' => 0, 'total_admitted' => 0, 'conversion_rate' => 0];
        }
    }

    public function getAlumniStats($filters = [])
    {
        // Get alumni count by graduation year and gender
        try {
            $where = ["s.status = 'alumni'"];
            $params = [];
            if (!empty($filters['graduation_year'])) {
                $where[] = 'YEAR(s.graduation_date) = ?';
                $params[] = $filters['graduation_year'];
            }
            $sql = "SELECT
                        YEAR(s.graduation_date) AS graduation_year,
                        s.gender,
                        COUNT(*) AS alumni_count
                    FROM students s
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY graduation_year, s.gender
                    ORDER BY graduation_year DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
