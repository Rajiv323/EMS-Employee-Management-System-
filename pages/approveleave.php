<?php
session_start();
include "../php/db.php";

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$currentUserId = $_SESSION['user_id'];
$manager_emp_id = null;
$manager_department = null;
$stmt = $conn->prepare('SELECT emp_id, department_name FROM employees WHERE user_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $currentUserId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $manager_emp_id = $r['emp_id'] ?? null;
    $manager_department = $r['department_name'] ?? null;
    $stmt->close();
}
$userQuery = mysqli_query($conn, "SELECT name FROM employees WHERE user_id='$currentUserId'");
$userData = mysqli_fetch_assoc($userQuery);
$currentUserName = $userData['name'];
// handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['leave_id'])) {
  $action = $_POST['action'] === 'approve' ? 'Approved' : 'Rejected';
  $leave_id = (int)$_POST['leave_id'];
  $remarks = trim($_POST['remarks'] ?? '');

  // Ensure the target leave exists before attempting update
  $exists = false;
  $check = $conn->prepare('SELECT 1 FROM leave_requests WHERE leave_id = ? LIMIT 1');
  if ($check) {
    $check->bind_param('i', $leave_id);
    $check->execute();
    $res = $check->get_result();
    $exists = $res && $res->fetch_row();
    $check->close();
  }

  if (!$exists) {
    // log for debugging
    @file_put_contents(__DIR__ . '/approve_debug.log', date('c') . " - leave_id not found: $leave_id\n", FILE_APPEND);
  } else {
    // Verify that the leave being approved is from the manager's department
    $verify = $conn->prepare('SELECT e.department_name FROM leave_requests l JOIN employees e ON l.employee_id = e.emp_id WHERE l.leave_id = ? LIMIT 1');
    if ($verify) {
      $verify->bind_param('i', $leave_id);
      $verify->execute();
      $vr = $verify->get_result()->fetch_assoc();
      $verify->close();
      
      if ($vr && $vr['department_name'] !== $manager_department) {
        // Manager is trying to approve leave from a different department
        @file_put_contents(__DIR__ . '/approve_debug.log', date('c') . " - Unauthorized: manager from {$manager_department} tried to approve leave from {$vr['department_name']}\n", FILE_APPEND);
        header('Location: approveleave.php');
        exit();
      }
    }
    if ($manager_emp_id === null) {
      $sql = 'UPDATE leave_requests SET status = ?, approved_by = NULL, remarks = ? WHERE leave_id = ?';
      $stmt = $conn->prepare($sql);
      if ($stmt) {
        $stmt->bind_param('ssi', $action, $remarks, $leave_id);
        $stmt->execute();
        // log execution and affected rows
        @file_put_contents(__DIR__ . '/approve_debug.log', date('c') . " - SQL: $sql | params: [$action, NULL, $remarks, $leave_id] | affected=" . $stmt->affected_rows . "\n", FILE_APPEND);
        $stmt->close();
      }
    } else {
      $sql = 'UPDATE leave_requests SET status = ?, approved_by = ?, remarks = ? WHERE leave_id = ?';
      $stmt = $conn->prepare($sql);
      if ($stmt) {
        $mgr = (int)$manager_emp_id;
        $stmt->bind_param('sisi', $action, $mgr, $remarks, $leave_id);
        $stmt->execute();
        @file_put_contents(__DIR__ . '/approve_debug.log', date('c') . " - SQL: $sql | params: [$action, $mgr, $remarks, $leave_id] | affected=" . $stmt->affected_rows . "\n", FILE_APPEND);
        $stmt->close();
      }
    }
  }

  header('Location: approveleave.php');
  exit();
}

// fetch pending requests - only from manager's department
$requests = [];
$res = $conn->prepare("SELECT l.*, e.name, e.department_name FROM leave_requests l JOIN employees e ON l.employee_id = e.emp_id WHERE l.status = 'Pending' AND e.department_name = ? ORDER BY l.applied_date ASC");
if ($res) {
    $res->bind_param('s', $manager_department);
    $res->execute();
    $result = $res->get_result();
    while ($row = $result->fetch_assoc()) $requests[] = $row;
    $res->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Approve Leave - EMS</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/approveleave.css" />
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
      <h3><?= htmlspecialchars($currentUserName) ?></h3></p>
        <hr>
        <a href="managerprofile.php" class="menu-item">My Profile</a><br>
        <a href="managerpayslip.php" class="menu-item">My Payslip</a><br>
        <a href="approveleave.php" class="menu-item">Approve Leave</a><br>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main id="main-content">
     <div class="container">
    <h2>Approve Leave Requests</h2>
<hr>
    <div id="leaveList">
      <?php if (count($requests) === 0): ?>
        <p>No pending leave requests.</p>
      <?php else: ?>
        <?php foreach ($requests as $r): ?>
          <div class="leave-card">
            <div class="left">
                <p><strong>Name:</strong> <?= htmlspecialchars($r['name']) ?></p>
                <p><strong>Department:</strong> <?= htmlspecialchars($r['department_name']) ?></p>
                <p><strong>Leave Type:</strong> <?= htmlspecialchars($r['leave_type']) ?></p>
                <p><strong>Reason:</strong> <?= htmlspecialchars($r['reason']) ?></p>
                <p><strong>From:</strong> <?= htmlspecialchars($r['start_date']) ?></p>
                <p><strong>To:</strong> <?= htmlspecialchars($r['end_date']) ?></p>
            </div>
            <hr>
            <div class="right">
                <form method="post" style="display:inline-block">
                  <input type="hidden" name="leave_id" value="<?= (int)$r['leave_id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <button class="approve-btn" type="submit">Approve</button>
                </form>
                <form method="post" style="display:inline-block;">
                  <input type="hidden" name="leave_id" value="<?= (int)$r['leave_id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <button class="reject-btn" type="submit">Reject</button>
                  <input type="text" name="remarks" placeholder="Remarks (optional)" style="margin-top:6px;">
                </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
</div>
  </main>

  <script>
    function logout(){
      window.location.href="../login.php"
    }
  </script>
</body>
</html>
