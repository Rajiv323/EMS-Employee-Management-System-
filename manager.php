<?php
session_start();
include "php/db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch current user info for sidebar
$currentUserId = $_SESSION['user_id'];
$userQuery = mysqli_query($conn, "SELECT name FROM employees WHERE user_id='$currentUserId'");
$userData = mysqli_fetch_assoc($userQuery);
$currentUserName = $userData['name'];

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

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar-menu">
    <div class="user-box">
        <p>
      <img src="assets/emp.jpg" class="user-photo">
              <h3><?= htmlspecialchars($currentUserName) ?></h3>
</p>
        <hr>
        <a href="./pages/managerprofile.php" class="menu-item">My Profile</a><br>
        <a href="./pages/managerpayslip.php" class="menu-item">My Payslip</a><br>
        <a href="./pages/approveleave.php" class="menu-item">Approve Leave</a><br>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main id="main-content" class="main">
    <h1>Welcome</h1>
  </main>

  <script>
    function logout(){
      window.location.href="login.php"
    }
  </script>
</body>
</html>
