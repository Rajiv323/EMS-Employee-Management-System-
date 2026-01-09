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
        <h2>Rs. <?= number_format((float)$payroll['net_salary'], 2) ?></h2>
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
      <p>Basic Salary: Rs. <?= number_format((float)$payroll['basic_salary'], 2) ?></p>
      <h4>Deductions</h4>
      <p>Rs. <?= number_format((float)$payroll['deductions'], 2) ?></p>
    </div>
    <hr>
    <div class="netpay">
      <p>Net Pay:</p>
      <h2>Rs. <?= number_format((float)$payroll['net_salary'], 2) ?></h2>
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
