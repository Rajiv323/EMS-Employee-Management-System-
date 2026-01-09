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
      <img src="../assets/emp.jpg" class="user-photo">
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
