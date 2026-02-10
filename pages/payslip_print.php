<?php
SESSION_START();
include "../php/db.php";
// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$currentUserId = $_SESSION['user_id'];
// Fetch employee
$stmt = $conn->prepare("SELECT e.*, u.username AS email FROM employees e LEFT JOIN users u ON e.user_id = u.user_id WHERE e.user_id = ? LIMIT 1");
$stmt->bind_param('i', $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc() ?: [];
$empId = $employee['emp_id'] ?? null;
// Fetch payroll
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

// Calculate overtime and unpaid leave for the current month
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$overtime_hours = 0.0;
$overtime_pay = 0.0;
$unpaid_days = 0;
$unpaid_deduction = 0.0;

if ($empId) {
    // Sum overtime hours for this month. Try both possible column names and use DATE() for matching.
    $overtime_hours = 0.0;
    $query1 = "SELECT SUM(overtime_hours) AS total_hours FROM overtime WHERE DATE(overtime_date) BETWEEN ? AND ? AND employee_id = ?";
    $query2 = "SELECT SUM(overtime_hours) AS total_hours FROM overtime WHERE DATE(overtime_date) BETWEEN ? AND ? AND emp_id = ?";

    $ot_stmt = $conn->prepare($query1);
    if ($ot_stmt) {
      $ot_stmt->bind_param('ssi', $monthStart, $monthEnd, $empId);
      $ot_stmt->execute();
      $ot_res = $ot_stmt->get_result();
      $ot_row = $ot_res->fetch_assoc();
      $overtime_hours = (float)($ot_row['total_hours'] ?? 0);
      $ot_stmt->close();
    } else {
      // try alternative column name
      $ot_stmt = $conn->prepare($query2);
      if ($ot_stmt) {
        $ot_stmt->bind_param('ssi', $monthStart, $monthEnd, $empId);
        $ot_stmt->execute();
        $ot_res = $ot_stmt->get_result();
        $ot_row = $ot_res->fetch_assoc();
        $overtime_hours = (float)($ot_row['total_hours'] ?? 0);
        $ot_stmt->close();
      }
    }

    // Compute unpaid leave days overlapping this month (only approved leaves)
    $lr_stmt = $conn->prepare("SELECT start_date, end_date, total_days, leave_type, status FROM leave_requests WHERE employee_id = ? AND status = 'Approved' AND NOT (end_date < ? OR start_date > ?)");
    if ($lr_stmt) {
      $lr_stmt->bind_param('iss', $empId, $monthStart, $monthEnd);
      $lr_stmt->execute();
      $lr_res = $lr_stmt->get_result();
      while ($lr = $lr_res->fetch_assoc()) {
        $s = max($lr['start_date'], $monthStart);
        $e = min($lr['end_date'], $monthEnd);
        $overlap = (int)((strtotime($e) - strtotime($s)) / 86400) + 1;
        // Treat leave types that indicate unpaid leave
        $lt = strtolower(trim($lr['leave_type']));
        if (in_array($lt, ['unpaid leave', 'unpaid', 'leave without pay', 'without pay'])) {
          $unpaid_days += $overlap;
        }
      }
      $lr_stmt->close();
    }

    // Calculate monetary values
    $basic_salary = (float)($payroll['basic_salary'] ?? 0);
    // hourly assumption: 208 working hours per month (26 days * 8 hours)
    $hourly_rate = $basic_salary / 208.0;
    $overtime_pay = round($overtime_hours * $hourly_rate, 2);
    // unpaid deduction: daily rate = basic / 30
    $daily_rate = $basic_salary / 30.0;
    $unpaid_deduction = round($daily_rate * $unpaid_days, 2);

    // Combined deductions for display (existing payroll deductions + unpaid leave)
    $existing_deductions = (float)($payroll['deductions'] ?? 0);
    $total_deductions = round($existing_deductions + $unpaid_deduction, 2);
    $computed_net = round($basic_salary + $overtime_pay - $total_deductions, 2);
} else {
    $basic_salary = 0;
    $hourly_rate = 0;
    $overtime_pay = 0;
    $unpaid_days = 0;
    $unpaid_deduction = 0;
    $existing_deductions = 0;
    $total_deductions = 0;
    $computed_net = 0;
}

$monthLabel = date('F Y');
// If download requested and a server PDF library exists, attempt server-side PDF generation
$download = isset($_GET['download']) && $_GET['download'] == '1';

// Simple HTML template
ob_start();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Payslip - <?= htmlspecialchars($employee['name'] ?? 'Employee') ?></title>
  <link rel="stylesheet" href="../css/payslip.css">
  <style>
    /* ensure printable formatting */
    body { padding: 24px; font-family: Arial, Helvetica, sans-serif; }
    .payslip-card { width: 700px; margin: 0 auto; }
    .header { display:flex; justify-content:space-between; align-items:center }
    h2, h3 { margin: 0 }
    .breakdown p { margin: 6px 0 }
  </style>
</head>
<body>
  <div class="payslip-card">
    <div class="header">
      <div>
        <h3><?= htmlspecialchars($employee['name'] ?? 'Employee Name') ?></h3>
        <p><?= htmlspecialchars($employee['department_name'] ?? '') ?></p>
      </div>
      <div class="salary-box">
        <p>Total Salary</p>
        <h2>Rs. <?= number_format((float)$computed_net, 2) ?></h2>
      </div>
    </div>
    <hr>
    <div class="details">
      <p><strong>Employee ID:</strong> <?= htmlspecialchars($employee['emp_id'] ?? '--') ?></p>
      <p><strong>Month:</strong> <?= $monthLabel ?></p>
    </div>
    <hr>
    <div class="breakdown">
      <h4>Earnings</h4>
      <p>Basic Salary: Rs. <?= number_format((float)$basic_salary, 2) ?></p>
      <p>Overtime Pay (<?= htmlspecialchars(number_format($overtime_hours, 2)) ?> hrs): Rs. <?= number_format((float)$overtime_pay, 2) ?></p>
      <h4>Deductions</h4>
      <p>Tax / Other: Rs. <?= number_format((float)$existing_deductions, 2) ?></p>
      <p>Unpaid Leave (<?= htmlspecialchars($unpaid_days) ?> days): Rs. <?= number_format((float)$unpaid_deduction, 2) ?></p>
      <p><strong>Total Deductions:</strong> Rs. <?= number_format((float)$total_deductions, 2) ?></p>
    </div>
    <hr>
    <div class="netpay">
      <p>Net Pay:</p>
      <h2>Rs. <?= number_format((float)$computed_net, 2) ?></h2>
    </div>
  </div>

  <?php if (!($download && class_exists('Dompdf\Dompdf'))): ?>
    <script>
      // Auto-print when rendering in a new window (fallback for when no server-side PDF lib)
      window.addEventListener('load', function(){
        // Give some time for styles to apply
        setTimeout(function(){ window.print(); }, 300);
      });
    </script>
  <?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();

if ($download && class_exists('Dompdf\Dompdf')) {
    // Use Dompdf if available
    try {
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        $filename = 'payslip-' . ($employee['emp_id'] ?? 'employee') . '-' . date('Y-m') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $pdf;
        exit;
    } catch (Exception $e) {
        // fall back to HTML printing
        echo $html;
        exit;
    }
} else {
    // No server-side PDF available or not requested for PDF — render printable HTML (auto-print)
    echo $html;
    exit;
}
