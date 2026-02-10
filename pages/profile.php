<?php
SESSION_START();
include "../php/db.php";
include "../sidenav.php";
// Check login
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>EMS Dashboard</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

  <!-- NAVBAR -->
  <header class="navbar">
    <img src="../assets/employee.png" alt="" class="nav-logo">
    <h1 class="logo">EMS</h1>
    <button class="logout-btn" onclick="logout()">Logout</button>
  </header>

  <!-- SIDEBAR -->
  <!-- <aside class="sidebar" id="sidebar-menu">
    <div class="user-box">
        <p>
      <img src="<?= htmlspecialchars(!empty($employee['photo']) ? '../assets/' . $employee['photo'] : '../assets/emp.jpg') ?>" class="user-photo">
      <h3><?= htmlspecialchars($employee['name'] ?? 'Manager') ?></h3></p>
        <hr>
        <a href="profile.php" class="menu-item">My Profile</a><br>
        <a href="payslip.php" class="menu-item">My Payslip</a><br>
        <a href="requestleave.php" class="menu-item">Request Leave</a><br>
    </div>
  </aside> -->

  <!-- MAIN CONTENT -->
  <main id="main-content" class="main">
    <main>
      <div id="msg" class="message" style="display: none"></div>

      <div class="profile-wrapper" id="profileWrapper">
        <div class="profile-card">
          <img id="avatar" src="<?= htmlspecialchars(!empty($employee['photo']) ? '../assets/' . $employee['photo'] : '../assets/emp.jpg') ?>" alt="avatar" class="avatar" />
          <div class="name" id="fullName"><?= htmlspecialchars($employee['name'] ?? 'No name') ?></div>
          <div class="role small" id="position"><?= htmlspecialchars($employee['role'] ?? '') ?></div>
          <br />
        </div>

        <div class="details">
          <div class="section-title">Personal Details</div>
          <div class="grid">
            <div class="field">
              <div class="label">Email</div>
              <div class="value" id="email"><?= htmlspecialchars($employee['email'] ?? '') ?></div>
            </div>
            <div class="field">
              <div class="label">Phone</div>
              <div class="value" id="phone"><?= htmlspecialchars($employee['phone'] ?? '') ?></div>
            </div>
            <div class="field">
              <div class="label">Date of Birth</div>
              <div class="value" id="dob"><?= htmlspecialchars($employee['date_of_birth'] ?? $employee['dob'] ?? '') ?></div>
            </div>
            <div class="field">
              <div class="label">Address</div>
              <div class="value" id="address"><?= htmlspecialchars($employee['address'] ?? '') ?></div>
            </div>
          </div>

          <div class="section-title" style="margin-top: 18px">
            Employee Details
          </div>
          <div class="grid">
            <div class="field">
              <div class="label">Employee ID</div>
              <div class="value" id="employeeId"><?= htmlspecialchars($employee['emp_id'] ?? '') ?></div>
            </div>
            <div class="field">
              <div class="label">Department</div>
              <div class="value" id="department"><?= htmlspecialchars($employee['department_name'] ?? '') ?></div>
            </div>
            <div class="field">
              <div class="label">Post</div>
              <div class="value" id="manager"><?= htmlspecialchars($employee['role'] ?? '') ?></div>
            </div>
            <div class="field">
              <div class="label">Date Joined</div>
              <div class="value" id="dateJoined"><?= htmlspecialchars($employee['date_of_joining'] ?? $employee['joined_date'] ?? '') ?></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </main>
    <script>
      function logout(){
      window.location.href="../login.php"
    }
    </script>

</body>
</html>
