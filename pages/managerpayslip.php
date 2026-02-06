<?php
SESSION_START();
include "../php/db.php";
// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$currentUserId = $_SESSION['user_id'];
// Fetch employee record for current user (join to get email)
$stmt = $conn->prepare("SELECT e.*, u.username AS email FROM employees e LEFT JOIN users u ON e.user_id = u.user_id WHERE e.user_id = ? LIMIT 1");
$stmt->bind_param('i', $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc() ?: [];
$empId = $employee['emp_id'] ?? null;
// Default payroll values
$payroll = ['basic_salary' => 0, 'deductions' => 0, 'net_salary' => 0];
if ($empId) {
    $pstmt = $conn->prepare("SELECT basic_salary, deductions, net_salary FROM payroll WHERE emp_id = ? LIMIT 1");
    $pstmt->bind_param('i', $empId);
    $pstmt->execute();
    $pres = $pstmt->get_result();
    if ($prow = $pres->fetch_assoc()) {
        $payroll = $prow;
    }
}

// Fetch department employees with payroll data
$dept_employees_payroll = [];
if (!empty($employee['department_name'])) {
    $dept = $employee['department_name'];
    $sql = "SELECT e.*, u.username AS email, p.basic_salary, p.deductions, p.net_salary FROM employees e LEFT JOIN users u ON e.user_id = u.user_id LEFT JOIN payroll p ON e.emp_id = p.emp_id WHERE e.department_name = ? AND e.user_id != ? ORDER BY e.name ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('si', $dept, $currentUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $dept_employees_payroll[] = $row;
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>EMS Dashboard</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/payslip.css">
</head>
<body>

  <!-- NAVBAR -->
  <header class="navbar">
    <img src="../assets/employee.png" alt="" class="nav-logo">
    <h1 class="logo">EMS</h1>
    <button class="logout-btn" onclick="logout()">Logout</button>
  </header>

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar-menu">
    <div class="user-box">
        <p>
      <img src="<?= htmlspecialchars(!empty($employee['photo']) ? '../assets/' . $employee['photo'] : '../assets/emp.jpg') ?>" class="user-photo">
      <h3><?= htmlspecialchars($employee['name'] ?? 'Manager') ?></h3></p>
        <hr>
        <a href="managerprofile.php" class="menu-item">My Profile</a><br>
        <a href="managerpayslip.php" class="menu-item">My Payslip</a><br>
        <a href="approveleave.php" class="menu-item">Approve Leave</a><br>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main id="main-content">
   <div class="payslip-container">
    <h2>My Payslip</h2>

    <div class="payslip-card">

      <div class="header">
        <div>
          <h3 id="empName"><?= htmlspecialchars($employee['name'] ?? 'Employee Name') ?></h3>
          <p id="empDept"><?= htmlspecialchars($employee['department_name'] ?? '') ?></p>
        </div>

        <div class="salary-box">
          <p>Total Salary</p>
          <h3 id="empSalary">Rs. <?= number_format((float)$payroll['net_salary'], 2) ?></h3 >
        </div>
      </div>

      <hr>

      <div class="details">
        <p><strong>Employee ID:</strong> <span id="empId"><?= htmlspecialchars($employee['emp_id'] ?? '--') ?></span></p>
        <p><strong>Month:</strong> <span id="salaryMonth"><?= date('F Y') ?></span></p>
      </div>

      <hr>

      <div class="breakdown">
        <h4>Earnings</h4>
        <p>Basic Salary: <span id="basic">Rs. <?= number_format((float)$payroll['basic_salary'], 2) ?></span></p>

        <h4>Deductions</h4>
        <p>Tax: <span id="tax">Rs. <?= number_format((float)$payroll['deductions'], 2) ?></span></p>
      </div>

      <hr>

      <div class="netpay">
        <p>Net Pay:</p>
        <h2 id="netPay">Rs. <?= number_format((float)$payroll['net_salary'], 2) ?></h2>
      </div>

      <button onclick="openPayslip()" class="downloadpayslip">Download PDF</button>

    </div>
</div>
<div class="payslip-container">
    <h3 style="margin-top: 40px;">Department Payslips</h3>
    <div class="payslip-card">
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr style="background-color:#f0f0f0;">
            <th style="padding:10px; border:1px solid #ddd; text-align:left;">Employee Name</th>
            <th style="padding:10px; border:1px solid #ddd; text-align:left;">Position</th>
            <th style="padding:10px; border:1px solid #ddd; text-align:center;">Basic Salary</th>
            <th style="padding:10px; border:1px solid #ddd; text-align:center;">Deductions</th>
            <th style="padding:10px; border:1px solid #ddd; text-align:center;">Net Salary</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($dept_employees_payroll) === 0): ?>
            <tr>
              <td colspan="5" style="padding:10px; border:1px solid #ddd; text-align:center;">No other employees in this department</td>
            </tr>
          <?php else: ?>
            <?php foreach ($dept_employees_payroll as $emp): ?>
              <tr>
                <td style="padding:10px; border:1px solid #ddd;"><?= htmlspecialchars($emp['name'] ?? '') ?></td>
                <td style="padding:10px; border:1px solid #ddd;"><?= htmlspecialchars($emp['role'] ?? '') ?></td>
                <td style="padding:10px; border:1px solid #ddd; text-align:center;">Rs. <?= number_format((float)($emp['basic_salary'] ?? 0), 2) ?></td>
                <td style="padding:10px; border:1px solid #ddd; text-align:center;">Rs. <?= number_format((float)($emp['deductions'] ?? 0), 2) ?></td>
                <td style="padding:10px; border:1px solid #ddd; text-align:center;">Rs. <?= number_format((float)($emp['net_salary'] ?? 0), 2) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </main>
  <script>
    function logout(){
      window.location.href="../login.php"
    }
    function openPayslip(){
      // Open printable payslip in a new tab and trigger print; include download=1 to attempt server PDF
      window.open('payslip_print.php?download=1', '_blank');
    }
  </script>
</body>
</html>
