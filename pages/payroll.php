<?php
require_once 'db.php'; 

header('Content-Type: application/json');

$action = $_GET['action'] ?? null;

switch ($action) {
    case 'add-employee':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode(addEmployee($data));
        break;

    case 'list-employees':
        echo json_encode(listEmployees());
        break;

    case 'process-payroll':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode(processPayroll($data));
        break;

    case 'payroll-report':
        echo json_encode(payrollReport());
        break;

    default:
        http_response_code(404);
        echo json_encode(['status'=>'error','message'=>'Action not found']);
        break;
}

function addEmployee($data) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO employees (first_name,last_name,email,phone,position,department,salary,date_hired) VALUES (?,?,?,?,?,?,?,?)");
    $success = $stmt->execute([
        $data['first_name'],
        $data['last_name'],
        $data['email'],
        $data['phone'] ?? '',
        $data['position'] ?? '',
        $data['department'] ?? '',
        $data['salary'],
        $data['date_hired']
    ]);
    return $success ? ['status'=>'success','message'=>'Employee added'] : ['status'=>'error','message'=>'Failed to add employee'];
}

function listEmployees() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM employees ORDER BY last_name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function processPayroll($data) {
    global $pdo;
    $netSalary = $data['basic_salary'] + ($data['allowances'] ?? 0) - ($data['deductions'] ?? 0);
    $stmt = $pdo->prepare("INSERT INTO payroll (employee_id,pay_period_start,pay_period_end,basic_salary,allowances,deductions,net_salary,status) VALUES (?,?,?,?,?,?,?,?)");
    $success = $stmt->execute([
        $data['employee_id'],
        $data['pay_period_start'],
        $data['pay_period_end'],
        $data['basic_salary'],
        $data['allowances'] ?? 0,
        $data['deductions'] ?? 0,
        $netSalary,
        'Paid'
    ]);
    return $success ? ['status'=>'success','message'=>'Payroll processed','net_salary'=>$netSalary] : ['status'=>'error','message'=>'Failed to process payroll'];
}

function payrollReport() {
    global $pdo;
    $stmt = $pdo->query("SELECT p.id,p.employee_id,e.first_name,e.last_name,p.basic_salary,p.allowances,p.deductions,p.net_salary,p.payment_date FROM payroll p JOIN employees e ON p.employee_id=e.id ORDER BY p.payment_date DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
