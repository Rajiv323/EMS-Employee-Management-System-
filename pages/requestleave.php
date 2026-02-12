<?php
session_start();
include "../php/db.php";
include "../sidenav.php";

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$currentUserId = $_SESSION['user_id'];
$emp_id = null;
$currentUserName = '';
$currentUserPhoto = null;
$stmt = $conn->prepare('SELECT emp_id, name, photo FROM employees WHERE user_id = ? LIMIT 1');
if ($stmt) {
  $stmt->bind_param('i', $currentUserId);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $emp_id = $r['emp_id'] ?? null;
  $currentUserName = $r['name'] ?? '';
  $currentUserPhoto = $r['photo'] ?? null;
  $stmt->close();
}
$message = '';
if (isset($_SESSION['leave_msg'])) {
  $message = $_SESSION['leave_msg'];
  unset($_SESSION['leave_msg']);
}
// today's date for validation and min attributes
$today = date('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leave_type = trim($_POST['leave_type'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (!$emp_id) {
        $message = 'Employee record not found.';
    } elseif ($leave_type === '' || $start_date === '' || $end_date === '') {
        $message = 'Please fill required fields.';
    } else {
        // validate dates server-side: start_date >= today, end_date >= start_date
        try {
            $sd = new DateTime($start_date);
            $ed = new DateTime($end_date);
            $td = new DateTime($today);
        } catch (Exception $e) {
            $message = 'Invalid date format.';
            $sd = $ed = $td = null;
        }

        if ($sd && $ed) {
            if ($sd < $td) {
                $message = 'Start date must be today or a future date.';
            } elseif ($ed < $sd) {
                $message = 'End date must be the same or after the start date.';
            } else {
                // compute total days (inclusive)
                $interval = $sd->diff($ed);
                $total_days = (int)$interval->days + 1;

                $sql = 'INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, total_days, reason, applied_date) VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE())';
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                  $stmt->bind_param('isssis', $emp_id, $leave_type, $start_date, $end_date, $total_days, $reason);
                  if ($stmt->execute()) {
                    $_SESSION['leave_msg'] = 'Leave request submitted.';
                    $stmt->close();
                    header('Location: requestleave.php');
                    exit();
                  } else {
                    $message = 'Error: ' . $stmt->error;
                  }
                  $stmt->close();
                } else {
                  $message = 'DB error: ' . $conn->error;
                }
            }
        }
    }
}

// fetch user leave requests
$requests = [];
if ($emp_id) {
    $stmt = $conn->prepare('SELECT l.*, e.name FROM leave_requests l JOIN employees e ON l.employee_id = e.emp_id WHERE l.employee_id = ? ORDER BY l.applied_date DESC');
    if ($stmt) {
        $stmt->bind_param('i', $emp_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $requests[] = $row;
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Request Leave - EMS</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/requestleave.css" />
</head>
<body>

  <!-- NAVBAR -->
  <header class="navbar">
    <img src="../assets/employee.png" alt="" class="nav-logo">
    <h1 class="logo">EMS</h1>
    <button class="logout-btn" onclick="logout()">Logout</button>
  </header>


  <!-- MAIN CONTENT -->
  <main id="main-content">
    <main class="main">
      <h2>Request Leave</h2>
      <hr>
      <?php if ($message !== ''): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <form action="" method="post">
        <label>Leave Type</label><br>
        <select name="leave_type" id="leaveType" required>
          <option value="">Select Leave Type</option>
          <option value="Sick Leave">Sick Leave</option>
          <option value="Casual Leave">Casual Leave</option>
          <option value="Unpaid Leave">Unpaid Leave</option>
        </select>
        <br>
        <label>Start Date</label>
        <input type="date" name="start_date" id="startDate" required min="<?= htmlspecialchars($today) ?>" />

        <label>End Date</label>
        <input type="date" name="end_date" id="endDate" required min="<?= htmlspecialchars($today) ?>" />
        <br>
        <label>Reason</label>
        <br>
        <textarea name="reason" id="reason" rows="4" placeholder="Write your reason..."></textarea>
        <br>

        <button type="submit" name="submit_leave">Submit Request</button>
      </form>
        <hr>
      <h3 style="margin-top:20px">My Leave Requests</h3>
      <table class="leave-table" style="width:100%; border-collapse:collapse">
        <thead>
          <tr>
            <th>Leave ID</th>
            <th>Type</th>
            <th>From</th>
            <th>To</th>
            <th>Total Days</th>
            <th>Status</th>
            <th>Applied</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $r): ?>
            <tr>
              <td style="text-align:center"><?= htmlspecialchars($r['leave_id']) ?></td>
              <td style="text-align:center"><?= htmlspecialchars($r['leave_type']) ?></td>
              <td style="text-align:center"><?= htmlspecialchars($r['start_date']) ?></td>
              <td style="text-align:center"><?= htmlspecialchars($r['end_date']) ?></td>
              <td style="text-align:center"><?= htmlspecialchars($r['total_days']) ?></td>
              <td style="text-align:center"><?= htmlspecialchars($r['status']) ?></td>
              <td style="text-align:center"><?= htmlspecialchars($r['applied_date']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    </main>
  </main>

  <script>
    function logout(){
      window.location.href="../login.php"
    }
  </script>
  <script>
    // enforce min dates and keep endDate >= startDate
    (function(){
      var today = '<?= $today ?>';
      var sd = document.getElementById('startDate');
      var ed = document.getElementById('endDate');
      if (sd) sd.min = today;
      if (ed) ed.min = today;
      if (sd && ed) {
        sd.addEventListener('change', function(){
          if (sd.value) {
            // ensure end min at least start
            ed.min = sd.value;
            if (ed.value && ed.value < sd.value) ed.value = sd.value;
          }
        });
      }
    })();
  </script>
</body>
</html>
