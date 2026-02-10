<?php
session_start();
include "php/db.php";
include 'sidenav.php';
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location:login.php");
    exit();
}
// get current user employee id and their leave statuses
$currentUserId = $_SESSION['user_id'];
$emp_id = null;
$leave_map = [];
$stmt = $conn->prepare('SELECT emp_id FROM employees WHERE user_id = ? LIMIT 1');
if ($stmt) {
  $stmt->bind_param('i', $currentUserId);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $emp_id = $r['emp_id'] ?? null;
  $stmt->close();
}
if ($emp_id) {
  $lr = $conn->prepare('SELECT leave_id, status FROM leave_requests WHERE employee_id = ?');
  if ($lr) {
    $lr->bind_param('i', $emp_id);
    $lr->execute();
    $res = $lr->get_result();
    while ($row = $res->fetch_assoc()) {
      $leave_map[(int)$row['leave_id']] = $row['status'];
    }
    $lr->close();
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
  </main>

  <script>
    function logout(){
      window.location.href="login.php"
    }
  </script>
  <script>
    // Notify employee when any of their leave request statuses changed since last visit
    (function(){
      try {
        var leaveMap = <?= json_encode($leave_map) ?> || {};
        var storageKey = 'leave_status_<?= (int)$currentUserId ?>';
        var prevRaw = localStorage.getItem(storageKey);
        var prev = prevRaw ? JSON.parse(prevRaw) : null;

        var changes = [];
        if (prev) {
          Object.keys(leaveMap).forEach(function(id){
            if (prev[id] && prev[id] !== leaveMap[id]) {
              changes.push({id: id, from: prev[id], to: leaveMap[id]});
            }
          });
        }

        // persist current map
        localStorage.setItem(storageKey, JSON.stringify(leaveMap));

        if (changes.length) {
          var msgs = changes.map(function(c){ return 'Your Leave request is ' + c.to; });
          var container = document.createElement('div');
          container.className = 'alert alert-success';
          container.style.margin = '12px';
          container.textContent = msgs.join(' | ');
          var main = document.getElementById('main-content') || document.body;
          main.insertBefore(container, main.firstChild);
        }
      } catch (e) {
        console.error('Leave status notify failed', e);
      }
    })();
  </script>
</body>
</html>
