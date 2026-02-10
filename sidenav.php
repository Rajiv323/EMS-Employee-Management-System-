<?php
$role = $_SESSION['role'];
// Fetch current user info for sidebar
$currentUserId = $_SESSION['user_id'];
$userQuery = mysqli_query($conn, "SELECT name, photo FROM employees WHERE user_id='$currentUserId'");
$userData = mysqli_fetch_assoc($userQuery);
$currentUserName = $userData['name'];
$currentUserPhoto = !empty($userData['photo']) ? $userData['photo'] : 'emp.jpg';

// Get current page filename for active link detection
$currentPage = basename($_SERVER['PHP_SELF']);

// Helper function to determine if link is active
function isActive($href, $currentPage) {
    $hrefPage = basename($href);
    return $hrefPage === $currentPage ? 'active' : '';
}
?>

<aside class="sidebar" id="sidebar-menu">
    <div class="user-box">
        <p>
            <img src="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '../' : './'; ?>assets/<?= htmlspecialchars(!empty($currentUserPhoto) ? $currentUserPhoto : 'emp.jpg') ?>"
                class="user-photo">
        <h3><?= htmlspecialchars($currentUserName) ?></h3>
        </p>
        <hr>
        
        <?php if ($role == 'HR') { ?>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>attendance.php" class="menu-item <?php echo isActive('attendance.php', $currentPage); ?>">Attendance</a>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>manageemployee.php" class="menu-item <?php echo isActive('manageemployee.php', $currentPage); ?>">Manage Employees</a>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>payroll.php" class="menu-item <?php echo isActive('payroll.php', $currentPage); ?>">Manage Payroll</a>
            <!-- <a href="attendance_report.php">Attendance Report</a>
    <a href="payroll.php">Payroll</a> -->
        <?php } ?>

        <?php if ($role == 'manager') { ?>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>profile.php" class="menu-item <?php echo isActive('profile.php', $currentPage); ?>">My Profile</a>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>payslip.php" class="menu-item <?php echo isActive('payslip.php', $currentPage); ?>">My Payroll</a>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>attendance.php" class="menu-item <?php echo isActive('attendance.php', $currentPage); ?>">Attendance</a>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>approveleave.php" class="menu-item <?php echo isActive('approveleave.php', $currentPage); ?>">Approve Leave</a>
        <?php } ?>

        <?php if ($role == 'employee') { ?>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>profile.php" class="menu-item <?php echo isActive('profile.php', $currentPage); ?>">Profile</a>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>attendance.php" class="menu-item <?php echo isActive('attendance.php', $currentPage); ?>">Attendance</a>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>requestleave.php" class="menu-item <?php echo isActive('requestleave.php', $currentPage); ?>">Request Leave</a>
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>payslip.php" class="menu-item <?php echo isActive('payslip.php', $currentPage); ?>">My Payroll</a>
        <?php } ?>
        
    </div>

</aside>