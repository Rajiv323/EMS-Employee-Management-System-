<?php
SESSION_START();
include "../php/db.php";
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

// Fetch employees in same department
$dept_employees = [];
if (!empty($employee['department_name'])) {
    $dept = $employee['department_name'];
    $stmt = $conn->prepare("SELECT e.*, u.username AS email FROM employees e LEFT JOIN users u ON e.user_id = u.user_id WHERE e.department_name = ? AND e.user_id != ? ORDER BY e.name ASC");
    if ($stmt) {
        $stmt->bind_param('si', $dept, $currentUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $dept_employees[] = $row;
        $stmt->close();
    }
}
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
  <aside class="sidebar" id="sidebar-menu">
    <div class="user-box">
        <p>
      <img src="<?= htmlspecialchars(!empty($employee['photo']) ? '../assets/' . $employee['photo'] : '../assets/emp.jpg') ?>" class="user-photo">
      <h3><?= htmlspecialchars($employee['name'] ?? 'Manager') ?></h3></p>
        <hr>
        <a href="managerprofile.php" class="menu-item">My Profile</a><br>
        <a href="managerpayslip.php" class="menu-item">My Payslip</a><br>
        <a href="approveleave.php" class="menu-item">Approve Leave</a><br>
    </div>
  </aside>

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

      <div  class="profile-wrapper" style="display: block">
        <h3 style="margin-bottom: 15px;">Department Team</h3>
        <table style="width:100%; border-collapse:collapse; border:1px solid #ddd;">
          <thead>
            <tr style="background-color:#f0f0f0;">
              <th style="padding:10px; border:1px solid #ddd; text-align:left;">Name</th>
              <th style="padding:10px; border:1px solid #ddd; text-align:left;">Email</th>
              <th style="padding:10px; border:1px solid #ddd; text-align:left;">Position</th>
              <th style="padding:10px; border:1px solid #ddd; text-align:left;">Phone</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($dept_employees) === 0): ?>
              <tr>
                <td colspan="4" style="padding:10px; border:1px solid #ddd; text-align:center;">No other employees in this department</td>
              </tr>
            <?php else: ?>
              <?php foreach ($dept_employees as $emp): ?>
                <tr>
                  <td style="padding:10px; border:1px solid #ddd;"><?= htmlspecialchars($emp['name'] ?? '') ?></td>
                  <td style="padding:10px; border:1px solid #ddd;"><?= htmlspecialchars($emp['email'] ?? '') ?></td>
                  <td style="padding:10px; border:1px solid #ddd;"><?= htmlspecialchars($emp['role'] ?? '') ?></td>
                  <td style="padding:10px; border:1px solid #ddd;"><?= htmlspecialchars($emp['phone'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
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
