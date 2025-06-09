<?php

namespace App\API\Modules\finance;
require_once __DIR__ . '/../../includes/BaseAPI.php';
use App\API\Includes\BaseAPI;
use App\API\Modules\communications\CommunicationsAPI;
use PDO;
use Exception;

class FinanceAPI extends BaseAPI
{
    private $communicationsApi;

    public function __construct()
    {
        parent::__construct('finance');
        $this->communicationsApi = new CommunicationsAPI();
    }

    // List records with pagination and filtering
    public function list($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $type = isset($params['type']) ? $params['type'] : 'fee';

            switch ($type) {
                case 'fee':
                    return $this->listFeeStructure($page, $limit, $offset, $search, $sort, $order);
                case 'payment':
                    return $this->listPayments($page, $limit, $offset, $search, $sort, $order);
                case 'invoice':
                    return $this->listInvoices($page, $limit, $offset, $search, $sort, $order);
                case 'payroll':
                    return $this->listPayroll($page, $limit, $offset, $search, $sort, $order);
                default:
                    throw new Exception('Invalid type specified');
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get single record
    public function get($id)
    {
        try {
            $type = isset($_GET['type']) ? $_GET['type'] : 'fee';

            switch ($type) {
                case 'fee':
                    return $this->getFeeStructure($id);
                case 'payment':
                    return $this->getPayment($id);
                case 'invoice':
                    return $this->getInvoice($id);
                case 'payroll':
                    return $this->getPayroll($id);
                default:
                    throw new Exception('Invalid type specified');
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new record
    public function create($data)
    {
        try {
            $this->beginTransaction();

            $type = isset($data['type']) ? $data['type'] : 'fee';

            switch ($type) {
                case 'fee':
                    $result = $this->createFeeStructure($data);
                    break;
                case 'payment':
                    $result = $this->createPayment($data);
                    break;
                case 'invoice':
                    $result = $this->createInvoice($data);
                    break;
                case 'payroll':
                    $result = $this->createPayroll($data);
                    break;
                default:
                    throw new Exception('Invalid type specified');
            }

            $this->commit();
            return $result;
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Update record
    public function update($id, $data)
    {
        try {
            $this->beginTransaction();

            $type = isset($data['type']) ? $data['type'] : 'fee';

            switch ($type) {
                case 'fee':
                    $result = $this->updateFeeStructure($id, $data);
                    break;
                case 'payment':
                    $result = $this->updatePayment($id, $data);
                    break;
                case 'invoice':
                    $result = $this->updateInvoice($id, $data);
                    break;
                case 'payroll':
                    $result = $this->updatePayroll($id, $data);
                    break;
                default:
                    throw new Exception('Invalid type specified');
            }

            $this->commit();
            return $result;
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete record
    public function delete($id)
    {
        try {
            $type = isset($_GET['type']) ? $_GET['type'] : 'fee';

            switch ($type) {
                case 'fee':
                    return $this->deleteFeeStructure($id);
                case 'payment':
                    return $this->deletePayment($id);
                case 'invoice':
                    return $this->deleteInvoice($id);
                case 'payroll':
                    return $this->deletePayroll($id);
                default:
                    throw new Exception('Invalid type specified');
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom GET endpoints
    public function handleCustomGet($id, $action, $params)
    {
        try {
            switch ($action) {
                case 'balance':
                    return $this->getStudentBalance($id);
                case 'statement':
                    return $this->getStudentStatement($id, $params);
                case 'receipt':
                    return $this->generateReceipt($id);
                case 'payslip':
                    return $this->generatePayslip($id);
                case 'report':
                    return $this->generateFinancialReport($params);
                default:
                    throw new Exception('Invalid action specified');
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom POST endpoints
    public function handleCustomPost($id, $action, $data)
    {
        try {
            $this->beginTransaction();

            switch ($action) {
                case 'allocate':
                    $result = $this->allocatePayment($id, $data);
                    break;
                case 'refund':
                    $result = $this->processRefund($id, $data);
                    break;
                case 'approve':
                    $result = $this->approveTransaction($id, $data);
                    break;
                default:
                    throw new Exception('Invalid action specified');
            }

            $this->commit();
            return $result;
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Implementation of these methods will follow in subsequent updates
    private function listFeeStructure($page, $limit, $offset, $search, $sort, $order)
    {
        $where = '';
        $bindings = [];
        if (!empty($search)) {
            $where = "WHERE f.name LIKE ? OR f.description LIKE ?";
            $searchTerm = "%$search%";
            $bindings = [$searchTerm, $searchTerm];
        }

        // Get total count
        $sql = "
            SELECT COUNT(*) 
            FROM fee_structures f
            $where
        ";
        $stmt = $this->db->prepare($sql);
        if (!empty($bindings)) {
            $stmt->execute($bindings);
        } else {
            $stmt->execute();
        }
        $total = $stmt->fetchColumn();

        // Get paginated results
        $sql = "
            SELECT 
                f.*,
                COUNT(DISTINCT sf.student_id) as assigned_students,
                SUM(CASE WHEN sf.status = 'active' THEN f.amount ELSE 0 END) as total_expected
            FROM fee_structures f
            LEFT JOIN student_fees sf ON f.id = sf.fee_structure_id
            $where
            GROUP BY f.id
            ORDER BY $sort $order 
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->db->prepare($sql);
        if (!empty($bindings)) {
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
        } else {
            $stmt->execute([$limit, $offset]);
        }
        $structures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->logAction('read', null, 'Listed fee structures');

        return $this->response([
            'status' => 'success',
            'data' => [
                'structures' => $structures,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]
        ]);
    }

    private function listPayments($page, $limit, $offset, $search, $sort, $order)
    {
        try {
        $where = '';
        $bindings = [];
        if (!empty($search)) {
                $where = "WHERE ft.reference_no LIKE ? OR ft.description LIKE ?";
            $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
        }

        // Get total count
        $sql = "
            SELECT COUNT(*) 
                FROM financial_transactions ft
                LEFT JOIN students s ON ft.student_id = s.id
            $where
        ";
        $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
        $total = $stmt->fetchColumn();

        // Get paginated results
        $sql = "
            SELECT 
                    ft.*,
                    s.admission_number,
                s.first_name,
                s.last_name,
                    CASE 
                        WHEN ft.payment_method = 'bank' THEN bt.bank_name
                        WHEN ft.payment_method = 'mpesa' THEN mt.transaction_code
                        ELSE st.payment_method
                    END as payment_details
                FROM financial_transactions ft
                LEFT JOIN students s ON ft.student_id = s.id
                LEFT JOIN bank_transactions bt ON ft.id = bt.transaction_id
                LEFT JOIN mpesa_transactions mt ON ft.id = mt.transaction_id
                LEFT JOIN school_transactions st ON ft.id = st.transaction_id
            $where
            ORDER BY $sort $order 
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
            'status' => 'success',
            'data' => [
                'payments' => $payments,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function listInvoices($page, $limit, $offset, $search, $sort, $order) {}
    private function listPayroll($page, $limit, $offset, $search, $sort, $order)
    {
        $where = '';
        $bindings = [];
        if (!empty($search)) {
            $where = "WHERE p.payroll_no LIKE ? OR s.staff_no LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?";
            $searchTerm = "%$search%";
            $bindings = [$searchTerm, $searchTerm, $searchTerm];
        }

        // Get total count
        $sql = "
            SELECT COUNT(*) 
            FROM payroll p
            JOIN staff s ON p.staff_id = s.id
            $where
        ";
        $stmt = $this->db->prepare($sql);
        if (!empty($bindings)) {
            $stmt->execute($bindings);
        } else {
            $stmt->execute();
        }
        $total = $stmt->fetchColumn();

        // Get paginated results
        $sql = "
            SELECT 
                p.*,
                s.staff_no,
                s.first_name,
                s.last_name,
                d.name as department,
                r.name as role,
                u.username as processed_by,
                COALESCE(pd.total_deductions, 0) as total_deductions,
                COALESCE(pa.total_allowances, 0) as total_allowances,
                p.basic_salary + COALESCE(pa.total_allowances, 0) - COALESCE(pd.total_deductions, 0) as net_salary
            FROM payroll p
            JOIN staff s ON p.staff_id = s.id
            JOIN departments d ON s.department_id = d.id
            JOIN roles r ON s.role_id = r.id
            LEFT JOIN users u ON p.processed_by = u.id
            LEFT JOIN (
                SELECT payroll_id, SUM(amount) as total_deductions
                FROM payroll_deductions
                GROUP BY payroll_id
            ) pd ON p.id = pd.payroll_id
            LEFT JOIN (
                SELECT payroll_id, SUM(amount) as total_allowances
                FROM payroll_allowances
                GROUP BY payroll_id
            ) pa ON p.id = pa.payroll_id
            $where
            ORDER BY $sort $order 
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->db->prepare($sql);
        if (!empty($bindings)) {
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
        } else {
            $stmt->execute([$limit, $offset]);
        }
        $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->logAction('read', null, 'Listed payroll records');

        return $this->response([
            'status' => 'success',
            'data' => [
                'payrolls' => $payrolls,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]
        ]);
    }

    private function getFeeStructure($id)
    {
        // Get fee structure details
        $sql = "
            SELECT 
                f.*,
                COUNT(DISTINCT sf.student_id) as assigned_students,
                SUM(CASE WHEN sf.status = 'active' THEN f.amount ELSE 0 END) as total_expected,
                SUM(CASE WHEN sf.status = 'active' THEN COALESCE(p.amount_paid, 0) ELSE 0 END) as total_collected
            FROM fee_structures f
            LEFT JOIN student_fees sf ON f.id = sf.fee_structure_id
            LEFT JOIN (
                SELECT fee_structure_id, student_id, SUM(amount) as amount_paid
                FROM fee_payments
                WHERE status = 'approved'
                GROUP BY fee_structure_id, student_id
            ) p ON sf.fee_structure_id = p.fee_structure_id AND sf.student_id = p.student_id
            WHERE f.id = ?
            GROUP BY f.id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $structure = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$structure) {
            return $this->response(['status' => 'error', 'message' => 'Fee structure not found'], 404);
        }

        // Get assigned students
        $sql = "
            SELECT 
                s.id,
                s.admission_no,
                s.first_name,
                s.last_name,
                c.name as class_name,
                cs.stream_name,
                sf.status,
                COALESCE(p.amount_paid, 0) as amount_paid
            FROM student_fees sf
            JOIN students s ON sf.student_id = s.id
            JOIN class_streams cs ON s.stream_id = cs.id
            JOIN classes c ON cs.class_id = c.id
            LEFT JOIN (
                SELECT student_id, fee_structure_id, SUM(amount) as amount_paid
                FROM fee_payments
                WHERE status = 'approved'
                GROUP BY student_id, fee_structure_id
            ) p ON sf.student_id = p.student_id AND sf.fee_structure_id = p.fee_structure_id
            WHERE sf.fee_structure_id = ?
            ORDER BY c.name, cs.stream_name, s.admission_no
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $structure['students'] = $students;

        $this->logAction('read', $id, "Retrieved fee structure details: {$structure['name']}");

        return $this->response(['status' => 'success', 'data' => $structure]);
    }

    private function getPayment($id)
    {
        // Get payment details
        $sql = "
            SELECT 
                p.*,
                s.admission_no,
                s.first_name,
                s.last_name,
                c.name as class_name,
                cs.stream_name,
                f.name as fee_name,
                f.amount as fee_amount,
                f.due_date,
                u.username as recorded_by,
                ua.username as approved_by
            FROM fee_payments p
            JOIN students s ON p.student_id = s.id
            JOIN class_streams cs ON s.stream_id = cs.id
            JOIN classes c ON cs.class_id = c.id
            JOIN fee_structures f ON p.fee_structure_id = f.id
            LEFT JOIN users u ON p.recorded_by = u.id
            LEFT JOIN users ua ON p.approved_by = ua.id
            WHERE p.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            return $this->response(['status' => 'error', 'message' => 'Payment not found'], 404);
        }

        // Get payment history for this student and fee structure
        $sql = "
            SELECT 
                p.*,
                u.username as recorded_by
            FROM fee_payments p
            LEFT JOIN users u ON p.recorded_by = u.id
            WHERE p.student_id = ? 
            AND p.fee_structure_id = ?
            AND p.id != ?
            ORDER BY p.payment_date DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$payment['student_id'], $payment['fee_structure_id'], $id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $payment['payment_history'] = $history;

        // Calculate balance
        $sql = "
            SELECT 
                f.amount as total_amount,
                COALESCE(SUM(p.amount), 0) as total_paid
            FROM fee_structures f
            LEFT JOIN fee_payments p ON f.id = p.fee_structure_id 
            AND p.student_id = ? 
            AND p.status = 'approved'
            WHERE f.id = ?
            GROUP BY f.id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$payment['student_id'], $payment['fee_structure_id']]);
        $balance = $stmt->fetch(PDO::FETCH_ASSOC);

        $payment['fee_balance'] = [
            'total_amount' => $balance['total_amount'],
            'total_paid' => $balance['total_paid'],
            'balance' => $balance['total_amount'] - $balance['total_paid']
        ];

        $this->logAction('read', $id, "Retrieved payment details: {$payment['reference_no']}");

        return $this->response(['status' => 'success', 'data' => $payment]);
    }

    private function getInvoice($id)
    {
        // Get invoice details
        $sql = "
            SELECT 
                i.*,
                s.admission_no,
                s.first_name,
                s.last_name,
                c.name as class_name,
                cs.stream_name,
                u.username as generated_by,
                COALESCE(p.amount_paid, 0) as amount_paid
            FROM invoices i
            JOIN students s ON i.student_id = s.id
            JOIN class_streams cs ON s.stream_id = cs.id
            JOIN classes c ON cs.class_id = c.id
            LEFT JOIN users u ON i.generated_by = u.id
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) as amount_paid
                FROM fee_payments
                WHERE status = 'approved'
                GROUP BY invoice_id
            ) p ON i.id = p.invoice_id
            WHERE i.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return $this->response(['status' => 'error', 'message' => 'Invoice not found'], 404);
        }

        // Get invoice items
        $sql = "
            SELECT 
                ii.*,
                f.name as fee_name,
                f.description as fee_description
            FROM invoice_items ii
            JOIN fee_structures f ON ii.fee_structure_id = f.id
            WHERE ii.invoice_id = ?
            ORDER BY ii.id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $invoice['items'] = $items;

        // Get payment history
        $sql = "
            SELECT 
                p.*,
                u.username as recorded_by
            FROM fee_payments p
            LEFT JOIN users u ON p.recorded_by = u.id
            WHERE p.invoice_id = ?
            ORDER BY p.payment_date DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $invoice['payments'] = $payments;

        $this->logAction('read', $id, "Retrieved invoice details: {$invoice['invoice_no']}");

        return $this->response(['status' => 'success', 'data' => $invoice]);
    }

    private function getPayroll($id)
    {
        // Get payroll details
        $sql = "
            SELECT 
                p.*,
                s.staff_no,
                s.first_name,
                s.last_name,
                s.email,
                s.phone,
                d.name as department,
                r.name as role,
                u.username as processed_by,
                COALESCE(pd.total_deductions, 0) as total_deductions,
                COALESCE(pa.total_allowances, 0) as total_allowances,
                p.basic_salary + COALESCE(pa.total_allowances, 0) - COALESCE(pd.total_deductions, 0) as net_salary
            FROM payroll p
            JOIN staff s ON p.staff_id = s.id
            JOIN departments d ON s.department_id = d.id
            JOIN roles r ON s.role_id = r.id
            LEFT JOIN users u ON p.processed_by = u.id
            LEFT JOIN (
                SELECT payroll_id, SUM(amount) as total_deductions
                FROM payroll_deductions
                GROUP BY payroll_id
            ) pd ON p.id = pd.payroll_id
            LEFT JOIN (
                SELECT payroll_id, SUM(amount) as total_allowances
                FROM payroll_allowances
                GROUP BY payroll_id
            ) pa ON p.id = pa.payroll_id
            WHERE p.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payroll) {
            return $this->response(['status' => 'error', 'message' => 'Payroll record not found'], 404);
        }

        // Get deductions
        $sql = "
            SELECT 
                pd.*,
                dt.name as deduction_type,
                dt.description as type_description
            FROM payroll_deductions pd
            JOIN deduction_types dt ON pd.deduction_type_id = dt.id
            WHERE pd.payroll_id = ?
            ORDER BY dt.name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $payroll['deductions'] = $deductions;

        // Get allowances
        $sql = "
            SELECT 
                pa.*,
                at.name as allowance_type,
                at.description as type_description
            FROM payroll_allowances pa
            JOIN allowance_types at ON pa.allowance_type_id = at.id
            WHERE pa.payroll_id = ?
            ORDER BY at.name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $allowances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $payroll['allowances'] = $allowances;

        // Get payment history
        $sql = "
            SELECT 
                pp.*,
                u.username as processed_by
            FROM payroll_payments pp
            LEFT JOIN users u ON pp.processed_by = u.id
            WHERE pp.payroll_id = ?
            ORDER BY pp.payment_date DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $payroll['payments'] = $payments;

        $this->logAction('read', $id, "Retrieved payroll details: {$payroll['payroll_no']}");

        return $this->response(['status' => 'success', 'data' => $payroll]);
    }

    private function createFeeStructure($data)
    {
        // Validate required fields
        $required = ['name', 'amount', 'term', 'year', 'due_date'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            return $this->response([
                'status' => 'error',
                'message' => 'Missing required fields',
                'fields' => $missing
            ], 400);
        }

        // Insert fee structure
        $sql = "
            INSERT INTO fee_structures (
                name, 
                description, 
                amount, 
                term, 
                year, 
                due_date, 
                category,
                applies_to,
                payment_frequency,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['amount'],
            $data['term'],
            $data['year'],
            $data['due_date'],
            $data['category'] ?? 'tuition',
            $data['applies_to'] ?? 'all',
            $data['payment_frequency'] ?? 'term',
            $data['status'] ?? 'active'
        ]);

        $structureId = $this->db->lastInsertId();

        // If specific students are assigned
        if (isset($data['student_ids']) && is_array($data['student_ids'])) {
            $values = [];
            $params = [];
            foreach ($data['student_ids'] as $studentId) {
                $values[] = "(?, ?, ?, NOW())";
                $params[] = $studentId;
                $params[] = $structureId;
                $params[] = 'active';
            }

            if (!empty($values)) {
                $sql = "
                    INSERT INTO student_fees (student_id, fee_structure_id, status, assigned_date) 
                    VALUES " . implode(', ', $values);
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }
        }

        $this->logAction('create', $structureId, "Created new fee structure: {$data['name']}");

        return $this->response([
            'status' => 'success',
            'message' => 'Fee structure created successfully',
            'data' => ['id' => $structureId]
        ], 201);
    }

    private function createPayment($data)
    {
        try {
            $required = ['student_id', 'amount', 'payment_method', 'payment_date'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
                return [
                'status' => 'error',
                'message' => 'Missing required fields',
                'fields' => $missing
                ];
        }

            // Start transaction
            $this->beginTransaction();

            // Create financial transaction
        $sql = "
                INSERT INTO financial_transactions (
                student_id,
                amount,
                payment_method,
                    payment_date,
                reference_no,
                description,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['student_id'],
            $data['amount'],
            $data['payment_method'],
                $data['payment_date'],
                $data['reference_no'] ?? $this->generatePaymentReference($data['student_id']),
                $data['description'] ?? 'Fee payment',
                'completed'
        ]);

            $transactionId = $this->db->lastInsertId();

            // Record payment method specific details
            switch ($data['payment_method']) {
                case 'bank':
                    $sql = "
                        INSERT INTO bank_transactions (
                            transaction_id,
                            bank_name,
                            branch_name,
                            account_no,
                            transaction_date
                        ) VALUES (?, ?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $transactionId,
                        $data['bank_name'],
                        $data['branch_name'],
                        $data['account_no'],
                        $data['payment_date']
                    ]);
                    break;

                case 'mpesa':
                    $sql = "
                        INSERT INTO mpesa_transactions (
                            transaction_id,
                            transaction_code,
                            phone_number,
                            transaction_date
                        ) VALUES (?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $transactionId,
                        $data['transaction_code'],
                        $data['phone_number'],
                        $data['payment_date']
                    ]);
                    break;

                default:
                    $sql = "
                        INSERT INTO school_transactions (
                            transaction_id,
                            payment_method,
                            received_by,
                            transaction_date
                        ) VALUES (?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $transactionId,
                        $data['payment_method'],
                        $this->user_id,
                        $data['payment_date']
                    ]);
            }

            // Update student fee balance
            $sql = "
                UPDATE student_fee_balances
                SET balance = balance - ?
                WHERE student_id = ? AND academic_term_id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['amount'],
                $data['student_id'],
                $data['term_id']
            ]);

            $this->commit();

            // Send payment notification
            $this->sendPaymentNotification($data['student_id'], $data['amount']);

            return [
            'status' => 'success',
            'message' => 'Payment recorded successfully',
                'data' => ['id' => $transactionId]
            ];

        } catch (Exception $e) {
            $this->rollback();
            return $this->handleException($e);
        }
    }

    private function generatePaymentReference($admissionNo)
    {
        $prefix = date('Ym');
        $sql = "
            SELECT COUNT(*) + 1 as next_number 
            FROM fee_payments 
            WHERE reference_no LIKE ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(["$prefix%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $prefix . str_pad($result['next_number'], 4, '0', STR_PAD_LEFT) .
            strtoupper(substr($admissionNo, -3));
    }

    private function createInvoice($data)
    {
        // Validate required fields
        $required = ['student_id', 'items'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            return $this->response([
                'status' => 'error',
                'message' => 'Missing required fields',
                'fields' => $missing
            ], 400);
        }

        // Validate student exists
        $stmt = $this->db->prepare("SELECT id, admission_no FROM students WHERE id = ?");
        $stmt->execute([$data['student_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return $this->response([
                'status' => 'error',
                'message' => 'Invalid student ID'
            ], 400);
        }

        // Generate invoice number
        $invoiceNo = $this->generateInvoiceNumber($student['admission_no']);

        try {
            $this->db->beginTransaction();

            // Insert invoice header
            $sql = "
                INSERT INTO invoices (
                    invoice_no,
                    student_id,
                    total_amount,
                    due_date,
                    status,
                    notes,
                    generated_by,
                    generated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";

            $totalAmount = array_sum(array_column($data['items'], 'amount'));

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $invoiceNo,
                $data['student_id'],
                $totalAmount,
                $data['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                'pending',
                $data['notes'] ?? null,
                $_SESSION['user_id'] ?? null
            ]);

            $invoiceId = $this->db->lastInsertId();

            // Insert invoice items
            $sql = "
                INSERT INTO invoice_items (
                    invoice_id,
                    fee_structure_id,
                    description,
                    amount
                ) VALUES (?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            foreach ($data['items'] as $item) {
                $stmt->execute([
                    $invoiceId,
                    $item['fee_structure_id'],
                    $item['description'] ?? null,
                    $item['amount']
                ]);
            }

            $this->db->commit();

            $this->logAction('create', $invoiceId, "Generated new invoice: $invoiceNo");

            // Send notification
            $this->sendNotification('invoice_generated', [
                'invoice_id' => $invoiceId,
                'invoice_no' => $invoiceNo,
                'student' => $student['admission_no'],
                'amount' => $totalAmount
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Invoice generated successfully',
                'data' => [
                    'id' => $invoiceId,
                    'invoice_no' => $invoiceNo
                ]
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function generateInvoiceNumber($admissionNo)
    {
        $prefix = 'INV' . date('Ym');
        $sql = "
            SELECT COUNT(*) + 1 as next_number 
            FROM invoices 
            WHERE invoice_no LIKE ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(["$prefix%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $prefix . str_pad($result['next_number'], 4, '0', STR_PAD_LEFT) .
            strtoupper(substr($admissionNo, -3));
    }

    private function createPayroll($data)
    {
        // Validate required fields
        $required = ['staff_id', 'month', 'year', 'basic_salary'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            return $this->response([
                'status' => 'error',
                'message' => 'Missing required fields',
                'fields' => $missing
            ], 400);
        }

        // Validate staff exists
        $stmt = $this->db->prepare("SELECT id, staff_no FROM staff WHERE id = ?");
        $stmt->execute([$data['staff_id']]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$staff) {
            return $this->response([
                'status' => 'error',
                'message' => 'Invalid staff ID'
            ], 400);
        }

        // Check if payroll already exists for this month
        $stmt = $this->db->prepare("
            SELECT id FROM payroll 
            WHERE staff_id = ? AND month = ? AND year = ?
        ");
        $stmt->execute([$data['staff_id'], $data['month'], $data['year']]);
        if ($stmt->fetch()) {
            return $this->response([
                'status' => 'error',
                'message' => 'Payroll record already exists for this month'
            ], 400);
        }

        // Generate payroll number
        $payrollNo = $this->generatePayrollNumber($staff['staff_no'], $data['month'], $data['year']);

        try {
            $this->db->beginTransaction();

            // Insert payroll record
            $sql = "
                INSERT INTO payroll (
                    payroll_no,
                    staff_id,
                    month,
                    year,
                    basic_salary,
                    status,
                    notes,
                    processed_by,
                    processed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $payrollNo,
                $data['staff_id'],
                $data['month'],
                $data['year'],
                $data['basic_salary'],
                'pending',
                $data['notes'] ?? null,
                $_SESSION['user_id'] ?? null
            ]);

            $payrollId = $this->db->lastInsertId();

            // Insert deductions if provided
            if (isset($data['deductions']) && is_array($data['deductions'])) {
                $sql = "
                    INSERT INTO payroll_deductions (
                        payroll_id,
                        deduction_type_id,
                        amount,
                        description
                    ) VALUES (?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['deductions'] as $deduction) {
                    $stmt->execute([
                        $payrollId,
                        $deduction['deduction_type_id'],
                        $deduction['amount'],
                        $deduction['description'] ?? null
                    ]);
                }
            }

            // Insert allowances if provided
            if (isset($data['allowances']) && is_array($data['allowances'])) {
                $sql = "
                    INSERT INTO payroll_allowances (
                        payroll_id,
                        allowance_type_id,
                        amount,
                        description
                    ) VALUES (?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['allowances'] as $allowance) {
                    $stmt->execute([
                        $payrollId,
                        $allowance['allowance_type_id'],
                        $allowance['amount'],
                        $allowance['description'] ?? null
                    ]);
                }
            }

            $this->db->commit();

            $this->logAction('create', $payrollId, "Generated new payroll: $payrollNo");

            // Send notification
            $this->sendNotification('payroll_generated', [
                'payroll_id' => $payrollId,
                'payroll_no' => $payrollNo,
                'staff_no' => $staff['staff_no'],
                'month' => $data['month'],
                'year' => $data['year']
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Payroll record generated successfully',
                'data' => [
                    'id' => $payrollId,
                    'payroll_no' => $payrollNo
                ]
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function generatePayrollNumber($staffNo, $month, $year)
    {
        $prefix = 'PAY' . date('Ym');
        $sql = "
            SELECT COUNT(*) + 1 as next_number 
            FROM payroll 
            WHERE payroll_no LIKE ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(["$prefix%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $prefix . str_pad($result['next_number'], 4, '0', STR_PAD_LEFT) .
            strtoupper(substr($staffNo, -3)) . str_pad($month, 2, '0', STR_PAD_LEFT) . str_pad($year, 4, '0', STR_PAD_LEFT);
    }

    private function updateFeeStructure($id, $data)
    {
        // Check if fee structure exists
        $stmt = $this->db->prepare("SELECT id, name FROM fee_structures WHERE id = ?");
        $stmt->execute([$id]);
        $structure = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$structure) {
            return $this->response(['status' => 'error', 'message' => 'Fee structure not found'], 404);
        }

        // Build update query
        $updates = [];
        $params = [];
        $allowedFields = [
            'name',
            'description',
            'amount',
            'term',
            'year',
            'due_date',
            'category',
            'applies_to',
            'payment_frequency',
            'status'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($updates)) {
            $params[] = $id;
            $sql = "UPDATE fee_structures SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        // Update student assignments if provided
        if (isset($data['student_assignments']) && is_array($data['student_assignments'])) {
            // First, mark all existing assignments as inactive
            $stmt = $this->db->prepare("
                UPDATE student_fees 
                SET status = 'inactive' 
                WHERE fee_structure_id = ?
            ");
            $stmt->execute([$id]);

            // Then, insert or update new assignments
            foreach ($data['student_assignments'] as $assignment) {
                $stmt = $this->db->prepare("
                    INSERT INTO student_fees (student_id, fee_structure_id, status, assigned_date)
                    VALUES (?, ?, 'active', NOW())
                    ON DUPLICATE KEY UPDATE status = 'active', assigned_date = NOW()
                ");
                $stmt->execute([$assignment['student_id'], $id]);
            }
        }

        $this->logAction('update', $id, "Updated fee structure: {$structure['name']}");

        return $this->response([
            'status' => 'success',
            'message' => 'Fee structure updated successfully'
        ]);
    }

    private function updatePayment($id, $data)
    {
        // Check if payment exists
        $stmt = $this->db->prepare("
            SELECT p.*, s.admission_no, f.name as fee_name 
            FROM fee_payments p
            JOIN students s ON p.student_id = s.id
            JOIN fee_structures f ON p.fee_structure_id = f.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            return $this->response(['status' => 'error', 'message' => 'Payment not found'], 404);
        }

        // Only pending payments can be updated
        if ($payment['status'] !== 'pending') {
            return $this->response([
                'status' => 'error',
                'message' => 'Only pending payments can be updated'
            ], 400);
        }

        // Build update query
        $updates = [];
        $params = [];
        $allowedFields = [
            'amount',
            'payment_date',
            'payment_method',
            'description',
            'receipt_no',
            'status'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        // Handle status change
        if (isset($data['status']) && $data['status'] === 'approved') {
            $updates[] = "approved_by = ?";
            $updates[] = "approved_at = NOW()";
            $params[] = $_SESSION['user_id'] ?? null;

            // Send notification to student/parent
            $this->sendNotification('payment_approved', [
                'payment_id' => $id,
                'reference' => $payment['reference_no'],
                'student' => $payment['admission_no'],
                'amount' => $payment['amount'],
                'fee_name' => $payment['fee_name']
            ]);
        }

        if (!empty($updates)) {
            $params[] = $id;
            $sql = "UPDATE fee_payments SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        // Handle receipt upload if provided
        if (isset($_FILES['receipt'])) {
            $uploadResult = $this->handleFileUpload('receipt', 'receipts', $payment['reference_no']);
            if ($uploadResult['status'] === 'success') {
                $stmt = $this->db->prepare("UPDATE fee_payments SET receipt_path = ? WHERE id = ?");
                $stmt->execute([$uploadResult['path'], $id]);
            }
        }

        $this->logAction('update', $id, "Updated payment: {$payment['reference_no']}");

        return $this->response([
            'status' => 'success',
            'message' => 'Payment updated successfully'
        ]);
    }

    private function updateInvoice($id, $data)
    {
        // Check if invoice exists
        $stmt = $this->db->prepare("
            SELECT i.*, s.admission_no 
            FROM invoices i
            JOIN students s ON i.student_id = s.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return $this->response(['status' => 'error', 'message' => 'Invoice not found'], 404);
        }

        // Only pending invoices can be updated
        if ($invoice['status'] !== 'pending') {
            return $this->response([
                'status' => 'error',
                'message' => 'Only pending invoices can be updated'
            ], 400);
        }

        try {
            $this->db->beginTransaction();

            // Update invoice header
            $updates = [];
            $params = [];
            $allowedFields = ['due_date', 'notes', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            // Update items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                // Delete existing items
                $stmt = $this->db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
                $stmt->execute([$id]);

                // Insert new items
                $sql = "
                    INSERT INTO invoice_items (
                        invoice_id,
                        fee_structure_id,
                        description,
                        amount
                    ) VALUES (?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                $totalAmount = 0;
                foreach ($data['items'] as $item) {
                    $stmt->execute([
                        $id,
                        $item['fee_structure_id'],
                        $item['description'] ?? null,
                        $item['amount']
                    ]);
                    $totalAmount += $item['amount'];
                }

                // Update total amount
                $updates[] = "total_amount = ?";
                $params[] = $totalAmount;
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE invoices SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();

            $this->logAction('update', $id, "Updated invoice: {$invoice['invoice_no']}");

            // Send notification if status changed to approved
            if (isset($data['status']) && $data['status'] === 'approved') {
                $this->sendNotification('invoice_approved', [
                    'invoice_id' => $id,
                    'invoice_no' => $invoice['invoice_no'],
                    'student' => $invoice['admission_no'],
                    'amount' => $invoice['total_amount']
                ]);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Invoice updated successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function updatePayroll($id, $data)
    {
        // Check if payroll exists
        $stmt = $this->db->prepare("
            SELECT p.*, s.staff_no 
            FROM payroll p
            JOIN staff s ON p.staff_id = s.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payroll) {
            return $this->response(['status' => 'error', 'message' => 'Payroll record not found'], 404);
        }

        // Only pending payrolls can be updated
        if ($payroll['status'] !== 'pending') {
            return $this->response([
                'status' => 'error',
                'message' => 'Only pending payroll records can be updated'
            ], 400);
        }

        try {
            $this->db->beginTransaction();

            // Update payroll record
            $updates = [];
            $params = [];
            $allowedFields = ['basic_salary', 'notes', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            // Handle status change
            if (isset($data['status']) && $data['status'] === 'approved') {
                $updates[] = "processed_by = ?";
                $updates[] = "processed_at = NOW()";
                $params[] = $_SESSION['user_id'] ?? null;
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE payroll SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            // Update deductions if provided
            if (isset($data['deductions']) && is_array($data['deductions'])) {
                // Delete existing deductions
                $stmt = $this->db->prepare("DELETE FROM payroll_deductions WHERE payroll_id = ?");
                $stmt->execute([$id]);

                // Insert new deductions
                $sql = "
                    INSERT INTO payroll_deductions (
                        payroll_id,
                        deduction_type_id,
                        amount,
                        description
                    ) VALUES (?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['deductions'] as $deduction) {
                    $stmt->execute([
                        $id,
                        $deduction['deduction_type_id'],
                        $deduction['amount'],
                        $deduction['description'] ?? null
                    ]);
                }
            }

            // Update allowances if provided
            if (isset($data['allowances']) && is_array($data['allowances'])) {
                // Delete existing allowances
                $stmt = $this->db->prepare("DELETE FROM payroll_allowances WHERE payroll_id = ?");
                $stmt->execute([$id]);

                // Insert new allowances
                $sql = "
                    INSERT INTO payroll_allowances (
                        payroll_id,
                        allowance_type_id,
                        amount,
                        description
                    ) VALUES (?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['allowances'] as $allowance) {
                    $stmt->execute([
                        $id,
                        $allowance['allowance_type_id'],
                        $allowance['amount'],
                        $allowance['description'] ?? null
                    ]);
                }
            }

            $this->db->commit();

            $this->logAction('update', $id, "Updated payroll: {$payroll['payroll_no']}");

            // Send notification if status changed to approved
            if (isset($data['status']) && $data['status'] === 'approved') {
                $this->sendNotification('payroll_approved', [
                    'payroll_id' => $id,
                    'payroll_no' => $payroll['payroll_no'],
                    'staff_no' => $payroll['staff_no'],
                    'month' => $payroll['month'],
                    'year' => $payroll['year']
                ]);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Payroll record updated successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function deleteFeeStructure($id)
    {
        // Check if fee structure exists and has no active payments
        $stmt = $this->db->prepare("
            SELECT f.id, f.name, COUNT(p.id) as payment_count
            FROM fee_structures f
            LEFT JOIN fee_payments p ON f.id = p.fee_structure_id AND p.status = 'approved'
            WHERE f.id = ?
            GROUP BY f.id
        ");
        $stmt->execute([$id]);
        $structure = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$structure) {
            return $this->response(['status' => 'error', 'message' => 'Fee structure not found'], 404);
        }

        if ($structure['payment_count'] > 0) {
            return $this->response([
                'status' => 'error',
                'message' => 'Cannot delete fee structure with active payments'
            ], 400);
        }

        // Soft delete the fee structure and its assignments
        $stmt = $this->db->prepare("UPDATE fee_structures SET status = 'deleted' WHERE id = ?");
        $stmt->execute([$id]);

        $stmt = $this->db->prepare("UPDATE student_fees SET status = 'inactive' WHERE fee_structure_id = ?");
        $stmt->execute([$id]);

        $this->logAction('delete', $id, "Deleted fee structure: {$structure['name']}");

        return $this->response([
            'status' => 'success',
            'message' => 'Fee structure deleted successfully'
        ]);
    }

    private function deletePayment($id)
    {
        // Check if payment exists
        $stmt = $this->db->prepare("
            SELECT p.*, s.admission_no 
            FROM fee_payments p
            JOIN students s ON p.student_id = s.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            return $this->response(['status' => 'error', 'message' => 'Payment not found'], 404);
        }

        // Only pending payments can be deleted
        if ($payment['status'] !== 'pending') {
            return $this->response([
                'status' => 'error',
                'message' => 'Only pending payments can be deleted'
            ], 400);
        }

        // Soft delete the payment
        $stmt = $this->db->prepare("UPDATE fee_payments SET status = 'deleted' WHERE id = ?");
        $stmt->execute([$id]);

        $this->logAction('delete', $id, "Deleted payment: {$payment['reference_no']}");

        // Send notification to relevant parties
        $this->sendNotification('payment_deleted', [
            'payment_id' => $id,
            'reference' => $payment['reference_no'],
            'student' => $payment['admission_no'],
            'amount' => $payment['amount']
        ]);

        return $this->response([
            'status' => 'success',
            'message' => 'Payment deleted successfully'
        ]);
    }

    private function deleteInvoice($id)
    {
        // Check if invoice exists
        $stmt = $this->db->prepare("
            SELECT i.*, s.admission_no 
            FROM invoices i
            JOIN students s ON i.student_id = s.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return $this->response(['status' => 'error', 'message' => 'Invoice not found'], 404);
        }

        // Only pending invoices can be deleted
        if ($invoice['status'] !== 'pending') {
            return $this->response([
                'status' => 'error',
                'message' => 'Only pending invoices can be deleted'
            ], 400);
        }

        try {
            $this->db->beginTransaction();

            // Delete invoice items
            $stmt = $this->db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            $stmt->execute([$id]);

            // Delete invoice
            $stmt = $this->db->prepare("DELETE FROM invoices WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();

            $this->logAction('delete', $id, "Deleted invoice: {$invoice['invoice_no']}");

            // Send notification
            $this->sendNotification('invoice_deleted', [
                'invoice_id' => $id,
                'invoice_no' => $invoice['invoice_no'],
                'student' => $invoice['admission_no']
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Invoice deleted successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function deletePayroll($id)
    {
        // Check if payroll exists
        $stmt = $this->db->prepare("
            SELECT p.*, s.staff_no 
            FROM payroll p
            JOIN staff s ON p.staff_id = s.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payroll) {
            return $this->response(['status' => 'error', 'message' => 'Payroll record not found'], 404);
        }

        // Only pending payrolls can be deleted
        if ($payroll['status'] !== 'pending') {
            return $this->response([
                'status' => 'error',
                'message' => 'Only pending payroll records can be deleted'
            ], 400);
        }

        try {
            $this->db->beginTransaction();

            // Delete allowances
            $stmt = $this->db->prepare("DELETE FROM payroll_allowances WHERE payroll_id = ?");
            $stmt->execute([$id]);

            // Delete deductions
            $stmt = $this->db->prepare("DELETE FROM payroll_deductions WHERE payroll_id = ?");
            $stmt->execute([$id]);

            // Delete payroll record
            $stmt = $this->db->prepare("DELETE FROM payroll WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();

            $this->logAction('delete', $id, "Deleted payroll: {$payroll['payroll_no']}");

            // Send notification
            $this->sendNotification('payroll_deleted', [
                'payroll_id' => $id,
                'payroll_no' => $payroll['payroll_no'],
                'staff_no' => $payroll['staff_no'],
                'month' => $payroll['month'],
                'year' => $payroll['year']
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Payroll record deleted successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function getStudentBalance($id)
    {
        $stmt = $this->db->prepare("SELECT s.*, c.name as class_name, cs.stream_name FROM students s JOIN class_streams cs ON s.stream_id = cs.id JOIN classes c ON cs.class_id = c.id WHERE s.id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);

        $sql = "SELECT f.*, sf.status as assignment_status, COALESCE(p.amount_paid, 0) as amount_paid, f.amount - COALESCE(p.amount_paid, 0) as balance FROM fee_structures f JOIN student_fees sf ON f.id = sf.fee_structure_id LEFT JOIN (SELECT fee_structure_id, student_id, SUM(amount) as amount_paid FROM fee_payments WHERE status = 'approved' GROUP BY fee_structure_id, student_id) p ON f.id = p.fee_structure_id AND sf.student_id = p.student_id WHERE sf.student_id = ? AND sf.status = 'active' ORDER BY f.due_date";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalFees = array_sum(array_column($fees, 'amount'));
        $totalPaid = array_sum(array_column($fees, 'amount_paid'));
        $totalBalance = $totalFees - $totalPaid;

        return $this->response([
            'status' => 'success',
            'data' => [
                'student' => ['id' => $student['id'], 'admission_no' => $student['admission_no'], 'name' => $student['first_name'] . ' ' . $student['last_name']],
                'summary' => ['total_fees' => $totalFees, 'total_paid' => $totalPaid, 'total_balance' => $totalBalance],
                'fees' => $fees
            ]
        ]);
    }

    private function getStudentStatement($id, $params)
    {
        $stmt = $this->db->prepare("SELECT s.*, c.name as class_name, cs.stream_name FROM students s JOIN class_streams cs ON s.stream_id = cs.id JOIN classes c ON cs.class_id = c.id WHERE s.id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);

        $dateCondition = '';
        $dateParams = [];
        if (isset($params['start_date']) && isset($params['end_date'])) {
            $dateCondition = "AND (p.payment_date BETWEEN ? AND ? OR f.due_date BETWEEN ? AND ?)";
            $dateParams = [$params['start_date'], $params['end_date'], $params['start_date'], $params['end_date']];
        }

        $sql = "SELECT 'fee' as type, f.id, f.name as description, f.amount as debit, 0 as credit, f.due_date as transaction_date, NULL as reference_no, NULL as payment_method, 'N/A' as status FROM fee_structures f JOIN student_fees sf ON f.id = sf.fee_structure_id WHERE sf.student_id = ? AND sf.status = 'active' $dateCondition UNION ALL SELECT 'payment' as type, p.id, COALESCE(p.description, 'Fee Payment') as description, 0 as debit, p.amount as credit, p.payment_date as transaction_date, p.reference_no, p.payment_method, p.status FROM fee_payments p WHERE p.student_id = ? AND p.status = 'approved' $dateCondition ORDER BY transaction_date";

        $params = array_merge([$id], $dateParams, [$id], $dateParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $balance = 0;
        foreach ($transactions as &$transaction) {
            $balance += $transaction['debit'] - $transaction['credit'];
            $transaction['balance'] = $balance;
        }

        return $this->response([
            'status' => 'success',
            'data' => [
                'student' => ['id' => $student['id'], 'admission_no' => $student['admission_no'], 'name' => $student['first_name'] . ' ' . $student['last_name']],
                'transactions' => $transactions,
                'summary' => ['opening_balance' => 0, 'closing_balance' => $balance]
            ]
        ]);
    }

    private function generateReceipt($id)
    {
        $sql = "SELECT p.*, s.admission_no, s.first_name, s.last_name, c.name as class_name, cs.stream_name, f.name as fee_name FROM fee_payments p JOIN students s ON p.student_id = s.id JOIN class_streams cs ON s.stream_id = cs.id JOIN classes c ON cs.class_id = c.id JOIN fee_structures f ON p.fee_structure_id = f.id WHERE p.id = ? AND p.status = 'approved'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment) return $this->response(['status' => 'error', 'message' => 'Payment not found or not approved'], 404);

        $filename = 'RECEIPT_' . $payment['reference_no'] . '.pdf';
        return $this->response(['status' => 'success', 'data' => ['filename' => $filename]]);
    }

    private function generatePayslip($id)
    {
        $sql = "SELECT p.*, s.staff_no, s.first_name, s.last_name FROM payroll p JOIN staff s ON p.staff_id = s.id WHERE p.id = ? AND p.status = 'approved'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payroll) return $this->response(['status' => 'error', 'message' => 'Payroll record not found or not approved'], 404);

        $filename = 'PAYSLIP_' . $payroll['payroll_no'] . '.pdf';
        return $this->response(['status' => 'success', 'data' => ['filename' => $filename]]);
    }

    private function generateFinancialReport($params)
    {
        $startDate = $params['start_date'] ?? date('Y-m-01');
        $endDate = $params['end_date'] ?? date('Y-m-t');
        $type = $params['type'] ?? 'summary';

        $sql = "SELECT SUM(amount) as total FROM fee_payments WHERE status = 'approved' AND payment_date BETWEEN ? AND ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        $total = $stmt->fetchColumn();

        return $this->response([
            'status' => 'success',
            'data' => [
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'total' => $total
            ]
        ]);
    }

    private function handleFileUpload($fileKey, $directory, $prefix = '')
    {
        try {
            if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed');
            }

            $file = $_FILES[$fileKey];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $prefix ? $prefix . '.' . $ext : uniqid() . '.' . $ext;
            $uploadDir = $this->getUploadPath($directory);
            $targetPath = $uploadDir . '/' . $filename;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                return [
                    'status' => 'success',
                    'path' => "$directory/$filename",
                    'filename' => $filename
                ];
            }

            throw new Exception('Failed to move uploaded file');
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function getUploadPath($directory)
    {
        return dirname(__DIR__, 3) . '/uploads/' . $directory;
    }

    private function sendNotification($type, $data)
    {
        $templates = [
            'payment_pending' => [
                'subject' => 'Payment Pending',
                'template' => 'Dear {{name}},\n\nA payment of KES {{amount}} is pending for {{purpose}}.\nReference: {{reference}}\n\nPlease complete the payment by {{due_date}}.'
            ],
            'payment_approved' => [
                'subject' => 'Payment Approved',
                'template' => 'Dear {{name}},\n\nYour payment of KES {{amount}} for {{purpose}} has been approved.\nReference: {{reference}}'
            ],
            'invoice_generated' => [
                'subject' => 'New Invoice Generated',
                'template' => 'Dear {{name}},\n\nA new invoice (#{{invoice_number}}) has been generated for KES {{amount}}.\nDue Date: {{due_date}}'
            ],
            'invoice_approved' => [
                'subject' => 'Invoice Approved',
                'template' => 'Dear {{name}},\n\nInvoice #{{invoice_number}} for KES {{amount}} has been approved.'
            ],
            'payroll_generated' => [
                'subject' => 'Payroll Generated',
                'template' => 'Dear {{name}},\n\nYour salary for {{month}} {{year}} has been processed.\nNet Amount: KES {{amount}}'
            ],
            'payroll_approved' => [
                'subject' => 'Payroll Approved',
                'template' => 'Dear {{name}},\n\nYour salary payment for {{month}} {{year}} has been approved.\nAmount: KES {{amount}}'
            ]
        ];

        if (!isset($templates[$type])) {
            throw new Exception('Invalid notification type');
        }

        $template = $templates[$type];
        
        return $this->communicationsApi->sendNotification([
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'subject' => $template['subject'],
            'message' => $this->parseTemplate($template['template'], $data),
            'send_email' => true,
            'send_sms' => !empty($data['phone'])
        ]);
    }

    private function parseTemplate($template, $data)
    {
        $parsed = $template;
        foreach ($data as $key => $value) {
            $parsed = str_replace('{{' . $key . '}}', $value, $parsed);
        }
        return $parsed;
    }

    private function generateFeeCollectionReport($startDate, $endDate)
    {
        $sql = "SELECT c.name as class_name, cs.stream_name, COUNT(DISTINCT p.student_id) as students_paid, SUM(p.amount) as total_collected FROM fee_payments p JOIN students s ON p.student_id = s.id JOIN class_streams cs ON s.stream_id = cs.id JOIN classes c ON cs.class_id = c.id WHERE p.status = 'approved' AND p.payment_date BETWEEN ? AND ? GROUP BY c.id, cs.id ORDER BY c.name, cs.stream_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->response([
            'status' => 'success',
            'data' => [
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'collections' => $collections,
                'summary' => ['total_collected' => array_sum(array_column($collections, 'total_collected'))]
            ]
        ]);
    }

    private function generatePayrollReport($startDate, $endDate)
    {
        $sql = "SELECT d.name as department, COUNT(DISTINCT p.staff_id) as staff_count, SUM(p.basic_salary) as total_basic, COALESCE(SUM(pa.total_allowances), 0) as total_allowances, COALESCE(SUM(pd.total_deductions), 0) as total_deductions FROM payroll p JOIN staff s ON p.staff_id = s.id JOIN departments d ON s.department_id = d.id LEFT JOIN (SELECT payroll_id, SUM(amount) as total_allowances FROM payroll_allowances GROUP BY payroll_id) pa ON p.id = pa.payroll_id LEFT JOIN (SELECT payroll_id, SUM(amount) as total_deductions FROM payroll_deductions GROUP BY payroll_id) pd ON p.id = pd.payroll_id WHERE p.status = 'approved' AND p.month BETWEEN MONTH(?) AND MONTH(?) AND p.year BETWEEN YEAR(?) AND YEAR(?) GROUP BY d.id ORDER BY d.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
        $payroll = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->response([
            'status' => 'success',
            'data' => [
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'payroll' => $payroll,
                'summary' => [
                    'total_staff' => array_sum(array_column($payroll, 'staff_count')),
                    'total_basic' => array_sum(array_column($payroll, 'total_basic')),
                    'total_allowances' => array_sum(array_column($payroll, 'total_allowances')),
                    'total_deductions' => array_sum(array_column($payroll, 'total_deductions'))
                ]
            ]
        ]);
    }

    private function generateExpenseReport($startDate, $endDate)
    {
        $sql = "SELECT e.category, COUNT(*) as transaction_count, SUM(e.amount) as total_amount FROM expenses e WHERE e.status = 'approved' AND e.date BETWEEN ? AND ? GROUP BY e.category ORDER BY e.category";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->response([
            'status' => 'success',
            'data' => [
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'expenses' => $expenses,
                'summary' => ['total_expenses' => array_sum(array_column($expenses, 'total_amount'))]
            ]
        ]);
    }

    
    private function allocatePayment($id, $data)
    {
        $sql = "SELECT p.*, s.admission_no FROM fee_payments p JOIN students s ON p.student_id = s.id WHERE p.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment) return $this->response(['status' => 'error', 'message' => 'Payment not found'], 404);

        if (!isset($data['allocations']) || !is_array($data['allocations'])) {
            return $this->response(['status' => 'error', 'message' => 'Invalid allocation data'], 400);
        }

        $totalAllocation = array_sum(array_column($data['allocations'], 'amount'));
        if ($totalAllocation > $payment['amount']) {
            return $this->response(['status' => 'error', 'message' => 'Total allocation exceeds payment amount'], 400);
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("DELETE FROM payment_allocations WHERE payment_id = ?");
            $stmt->execute([$id]);

            $sql = "INSERT INTO payment_allocations (payment_id, fee_structure_id, amount, allocated_by, allocated_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            foreach ($data['allocations'] as $allocation) {
                $stmt->execute([$id, $allocation['fee_structure_id'], $allocation['amount'], $_SESSION['user_id'] ?? null]);
            }

            if ($payment['status'] === 'pending') {
                $stmt = $this->db->prepare("UPDATE fee_payments SET status = 'allocated' WHERE id = ?");
                $stmt->execute([$id]);
            }

            $this->db->commit();
            return $this->response(['status' => 'success', 'message' => 'Payment allocated successfully']);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function processRefund($id, $data)
    {
        $sql = "SELECT p.*, s.admission_no FROM fee_payments p JOIN students s ON p.student_id = s.id WHERE p.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment) return $this->response(['status' => 'error', 'message' => 'Payment not found'], 404);

        if (!isset($data['amount']) || $data['amount'] <= 0 || $data['amount'] > $payment['amount']) {
            return $this->response(['status' => 'error', 'message' => 'Invalid refund amount'], 400);
        }

        try {
            $this->db->beginTransaction();
            $refundNo = 'REF' . date('Ymd') . strtoupper(substr(uniqid(), -6));

            $sql = "INSERT INTO fee_refunds (payment_id, amount, reason, status, refund_method, reference_no, requested_by, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $data['amount'], $data['reason'] ?? null, 'pending', $data['refund_method'] ?? 'bank_transfer', $refundNo, $_SESSION['user_id'] ?? null]);

            $refundId = $this->db->lastInsertId();

            $stmt = $this->db->prepare("UPDATE fee_payments SET status = 'refund_pending', refund_amount = ? WHERE id = ?");
            $stmt->execute([$data['amount'], $id]);

            $this->db->commit();
            return $this->response(['status' => 'success', 'message' => 'Refund initiated successfully', 'data' => ['refund_id' => $refundId]]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function approveTransaction($id, $data)
    {
        $sql = "SELECT p.*, s.admission_no FROM fee_payments p JOIN students s ON p.student_id = s.id WHERE p.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transaction) return $this->response(['status' => 'error', 'message' => 'Transaction not found'], 404);

        if ($transaction['status'] !== 'pending') {
            return $this->response(['status' => 'error', 'message' => 'Only pending transactions can be approved'], 400);
        }

        try {
            $this->db->beginTransaction();

            $status = isset($data['approve']) && $data['approve'] ? 'approved' : 'rejected';
            $note = date('Y-m-d H:i:s') . " - " . $status . " by " . ($_SESSION['username'] ?? 'system');

            $sql = "UPDATE fee_payments SET status = ?, approved_by = ?, approved_at = NOW(), notes = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $_SESSION['user_id'] ?? null, $note, $id]);

            $this->db->commit();
            return $this->response(['status' => 'success', 'message' => "Transaction $status successfully"]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function sendPaymentNotification($studentId, $amount)
    {
        try {
            // Get student details
            $sql = "
                SELECT 
                    s.first_name,
                    s.last_name,
                    s.admission_number,
                    p.phone as parent_phone,
                    p.email as parent_email
                FROM students s
                LEFT JOIN student_parents sp ON s.id = sp.student_id
                LEFT JOIN parents p ON sp.parent_id = p.id
                WHERE s.id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                throw new Exception('Student not found');
            }

            // Send SMS notification if phone number exists
            if (!empty($student['parent_phone'])) {
                $message = sprintf(
                    'Dear Parent, Payment of KES %s has been received for %s %s (%s). Thank you.',
                    number_format($amount, 2),
                    $student['first_name'],
                    $student['last_name'],
                    $student['admission_number']
                );

                $this->communicationsApi->sendBulkSMS([
                    'recipients' => [$student['parent_phone']],
                    'message' => $message
                ]);
            }

            // Send email notification if email exists
            if (!empty($student['parent_email'])) {
                $subject = 'Payment Confirmation';
                $body = sprintf(
                    'Dear Parent,<br><br>'.
                    'This is to confirm that we have received payment of KES %s for %s %s (%s).<br><br>'.
                    'Thank you for your prompt payment.<br><br>'.
                    'Best regards,<br>'.
                    'Finance Department',
                    number_format($amount, 2),
                    $student['first_name'],
                    $student['last_name'],
                    $student['admission_number']
                );

                $this->communicationsApi->sendBulkEmail([
                    'recipients' => [$student['parent_email']],
                    'subject' => $subject,
                    'message' => $body
                ]);
            }
        } catch (Exception $e) {
            // Log error but don't stop execution
            error_log('Failed to send payment notification: ' . $e->getMessage());
        }
    }
}
