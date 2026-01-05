<?php
/**
 * PaymentsController - Exposes RESTful endpoints for all payment webhooks
 */
namespace App\API\Controllers;

use App\API\Modules\payments\PaymentsAPI;
use Exception;

class PaymentsController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new PaymentsAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Payments API is running']);
    }

    /**
     * GET /api/payments/trends - Alias for collection trends
     */
    public function getTrends($id = null, $data = [], $segments = [])
    {
        // Reuse getCollectionTrends logic
        return $this->getCollectionTrends($id, $data, $segments);
    }

    /**
     * GET /api/payments/revenue-sources - Returns revenue sources breakdown
     */
    public function getRevenueSources($id = null, $data = [], $segments = [])
    {
        try {
            $query = "
                SELECT payment_method AS source, SUM(amount_paid) as total
                FROM payment_transactions
                WHERE status = 'confirmed'
                GROUP BY payment_method
                ORDER BY total DESC
            ";
            $result = $this->db->query($query);
            $sources = $result->fetchAll();
            return $this->success([
                'sources' => $sources,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Revenue sources breakdown');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch revenue sources: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/payments/stats - Get fees collection statistics for dashboard
     * Returns: amount collected, percentage collected, outstanding amount
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;

            // Get fees collected this month
            $monthlyQuery = "
                SELECT COALESCE(SUM(amount), 0) as monthly_collected 
                FROM payment_transactions 
                WHERE status = 'successful' 
                AND YEAR(transaction_date) = YEAR(NOW())
                AND MONTH(transaction_date) = MONTH(NOW())
            ";
            $monthlyResult = $db->query($monthlyQuery);
            $monthlyRow = $monthlyResult->fetch();
            $monthlyCollected = (float) ($monthlyRow['monthly_collected'] ?? 0);

            // Get overdue payment count
            $overdueQuery = "
                SELECT COUNT(DISTINCT student_id) as overdue_count
                FROM student_fee_obligations
                WHERE status = 'overdue'
            ";
            $overdueResult = $db->query($overdueQuery);
            $overdueRow = $overdueResult->fetch();
            $overdueCount = (int) ($overdueRow['overdue_count'] ?? 0);

            // Get total fees expected (sum of active fee obligations)
            $totalFeesQuery = "
                SELECT SUM(balance) as total_expected 
                FROM student_fee_obligations 
                WHERE status = 'active' OR status = 'pending'
            ";
            $totalResult = $db->query($totalFeesQuery);
            $totalRow = $totalResult->fetch();
            $totalExpected = (float) ($totalRow['total_expected'] ?? 0);

            // Get total fees collected (sum of successful payments in last 30 days)
            $collectedQuery = "
                SELECT COALESCE(SUM(amount), 0) as amount_collected 
                FROM payment_transactions 
                WHERE status = 'successful' AND transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ";
            $collectedResult = $db->query($collectedQuery);
            $collectedRow = $collectedResult->fetch();
            $amountCollected = (float) ($collectedRow['amount_collected'] ?? 0);

            // Get outstanding fees
            $outstandingQuery = "
                SELECT COALESCE(SUM(balance), 0) as outstanding 
                FROM student_fee_obligations
                WHERE balance > 0
            ";
            $outstandingResult = $db->query($outstandingQuery);
            $outstandingRow = $outstandingResult->fetch();
            $outstanding = (float) ($outstandingRow['outstanding'] ?? 0);

            $percentage = $totalExpected > 0 ? round(($amountCollected / $totalExpected) * 100, 2) : 0;

            return $this->success([
                'monthly_collected' => $monthlyCollected,
                'amount' => $amountCollected,
                'percentage' => (float) $percentage,
                'outstanding' => $outstanding,
                'total_expected' => $totalExpected,
                'overdue_count' => $overdueCount,
                'period_days' => 30,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Fees collection statistics');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch fees statistics: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/payments/collection-trends - Get fee collection trends over time
     * Returns: monthly collection data and comparison to target
     * SECURITY: Director and Finance roles only
     */
    public function getCollectionTrends($id = null, $data = [], $segments = [])
    {
        try {
            // Get last 12 months of collection data
            $query = "
                SELECT 
                    DATE_FORMAT(payment_date, '%Y-%m') as month,
                    DATE_FORMAT(payment_date, '%b') as month_label,
                    SUM(amount_paid) as collected,
                    COUNT(DISTINCT student_id) as students_paid
                FROM payment_transactions
                WHERE status = 'confirmed'
                AND payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                ORDER BY month ASC
            ";

            $result = $this->db->query($query);
            $monthlyData = $result ? $result->fetchAll() : [];

            // Calculate average monthly target (total annual expected / 12)
            $targetQuery = "
                SELECT SUM(balance) as total_expected 
                FROM student_fee_obligations 
                WHERE status = 'active' OR status = 'pending'
            ";
            $targetResult = $this->db->query($targetQuery);
            $targetRow = $targetResult ? $targetResult->fetch() : [];
            $totalExpected = (float) ($targetRow['total_expected'] ?? 0);
            $monthlyTarget = $totalExpected / 12;

            // Format chart data
            $chartData = [];
            if ($monthlyData && count($monthlyData) > 0) {
                foreach ($monthlyData as $month) {
                    $chartData[] = [
                        'month' => $month['month_label'] ?? substr($month['month'], 5),
                        'collected' => (float) ($month['collected'] ?? 0),
                        'target' => $monthlyTarget,
                        'students_paid' => (int) ($month['students_paid'] ?? 0)
                    ];
                }
            }

            // Calculate totals
            $totalCollected = count($chartData) > 0 ? array_sum(array_column($chartData, 'collected')) : 0;
            $totalTarget = $monthlyTarget * count($chartData);
            $collectionRate = $totalTarget > 0
                ? round(($totalCollected / $totalTarget) * 100, 2)
                : 0;

            return $this->success([
                'chart_data' => $chartData,
                'summary' => [
                    'collected' => (float) $totalCollected,
                    'target' => (float) $totalTarget,
                    'collection_rate' => (float) $collectionRate,
                    'period' => '12 months',
                    'month_target' => (float) $monthlyTarget
                ]
            ], 'Collection trends retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch collection trends: ' . $e->getMessage());
        }
    }

    /**
     * Standard API response handler
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                return $result['success']
                    ? $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful')
                    : $this->badRequest($result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            if (isset($result['status'])) {
                return $result['status'] === 'success'
                    ? $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful')
                    : $this->badRequest($result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            return $this->success($result);
        }

        return $this->success(['result' => $result]);
    }

    /**
     * POST /api/payments/mpesa-b2c-callback
     */
    public function postMpesaB2cCallback($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaB2CCallback($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/mpesa-b2c-timeout
     */
    public function postMpesaB2cTimeout($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaB2CTimeout($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/mpesa-c2b-confirmation
     */
    public function postMpesaC2bConfirmation($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaC2BConfirmation($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-validation
     */
    public function postKcbValidation($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processKcbValidation($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-transfer-callback
     */
    public function postKcbTransferCallback($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processKcbTransferCallback($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-notification
     */
    public function postKcbNotification($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processKcbNotification($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/bank-webhook
     */
    public function postBankWebhook($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processBankWebhook($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }
}


