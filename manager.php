<?php
session_start();
include "php/db.php";
include "sidenav.php";
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// prepare manager info and pending leave IDs
$currentUserId = $_SESSION['user_id'];
$manager_department = null;
$pending = [];
$stmt = $conn->prepare('SELECT emp_id, department_name FROM employees WHERE user_id = ? LIMIT 1');
if ($stmt) {
  $stmt->bind_param('i', $currentUserId);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $manager_department = $r['department_name'] ?? null;
  $stmt->close();
}
if ($manager_department) {
  $pq = $conn->prepare("SELECT l.leave_id FROM leave_requests l JOIN employees e ON l.employee_id = e.emp_id WHERE l.status = 'Pending' AND e.department_name = ? ORDER BY l.applied_date ASC");
  if ($pq) {
    $pq->bind_param('s', $manager_department);
    $pq->execute();
    $res = $pq->get_result();
    while ($row = $res->fetch_assoc()) $pending[] = (int)$row['leave_id'];
    $pq->close();
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>EMS Dashboard</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <!-- NAVBAR -->
  <header class="navbar">
    <img src="./assets/employee.png" alt="" class="nav-logo">
    <h1 class="logo">EMS</h1>
    <button class="logout-btn" onclick="logout()">Logout</button>
  </header>

  <!-- MAIN CONTENT -->
  <main id="main-content" class="main">
    <h1>Welcome</h1>
    <script>
      (function(){
        try {
          var pending = <?= json_encode($pending) ?> || [];
          if (!pending || !pending.length) return;
          var storageKey = 'approve_pending_<?= (int)$currentUserId ?>';
          var prevRaw = localStorage.getItem(storageKey);
          var prev = prevRaw ? JSON.parse(prevRaw) : [];
          var newOnes = pending.filter(function(id){ return prev.indexOf(id) === -1; });
          if (newOnes.length) {
            var msg = 'New leave request(s) please review.';
            try {
              var container = document.createElement('div');
              container.className = 'alert alert-success';
              container.style.margin = '12px 0';
              container.textContent = msg;
              var main = document.getElementById('main-content') || document.body;
              main.insertBefore(container, main.firstChild);
            } catch (e) {
              alert(msg);
            }
          }
          localStorage.setItem(storageKey, JSON.stringify(pending));
        } catch (e) {
          console.error('Pending notify failed', e);
        }
      })();
    </script>
  </main>

  <script>
    function logout(){
      window.location.href="login.php"
    }
  </script>
</body>
</html>
