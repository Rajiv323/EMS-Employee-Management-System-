<?php
SESSION_START();
header('Content-Type: application/json');
include "../php/db.php";

$emp_id = isset($_POST['emp_id']) ? intval($_POST['emp_id']) : null;
$base = isset($_POST['base_salary']) ? floatval($_POST['base_salary']) : 0;
$deductions = isset($_POST['deductions']) ? floatval($_POST['deductions']) : 0;
$net = isset($_POST['net_salary']) ? floatval($_POST['net_salary']) : ($base - $deductions);

if (!$emp_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing emp_id']);
    exit;
}

// Check if a payroll row exists for this emp_id
$checkSql = "SELECT COUNT(*) AS cnt FROM payroll WHERE emp_id = ?";
$checkStmt = $conn->prepare($checkSql);
if (!$checkStmt) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}
$checkStmt->bind_param('i', $emp_id);
$checkStmt->execute();
$checkStmt->bind_result($cnt);
$checkStmt->fetch();
$checkStmt->close();

if ($cnt > 0) {
    // existing row -> UPDATE
    $sql = "UPDATE payroll SET basic_salary = ?, deductions = ?, net_salary = ? WHERE emp_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
        exit;
    }
    // types: d = double, d = double, d = double, i = int
    $stmt->bind_param('dddi', $base, $deductions, $net, $emp_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
} else {
    // no existing row -> INSERT
    $sql = "INSERT INTO payroll (emp_id, basic_salary, deductions, net_salary) VALUES (?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
        exit;
    }
    // types: i = int, d = double, d = double, d = double
    $stmt->bind_param('iddd', $emp_id, $base, $deductions, $net);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
}

?>