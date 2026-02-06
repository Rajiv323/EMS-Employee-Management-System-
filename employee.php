<?php
session_start();
include "php/db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location:login.php");
    exit();
}

// Fetch current user info for sidebar
$currentUserId = $_SESSION['user_id'];
$userQuery = mysqli_query($conn, "SELECT name, photo FROM employees WHERE user_id='$currentUserId'");
$userData = mysqli_fetch_assoc($userQuery);
$currentUserName = $userData['name'];
$currentUserPhoto = !empty($userData['photo']) ? $userData['photo'] : 'emp.jpg';

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
            <img src="assets/<?= htmlspecialchars($currentUserPhoto) ?>" class="user-photo">
              <h3><?= htmlspecialchars($currentUserName) ?></h3>
        </p>
        <hr>
        <a href="pages/profile.php" class="menu-item">My Profile</a><br>
        <a href="pages/payslip.php" class="menu-item">My Payslip</a><br>
        <a href="pages/requestleave.php" class="menu-item">Request Leave</a><br>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main id="main-content">
    <h1>Welcome</h1>
  </main>

  <script>
    function logout(){
      window.location.href="login.php"
    }
  </script>
</body>
</html>
