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
$userRole = $_SESSION['role'] ?? 'employee';

// Fetch employee record for current user (join to get email)
$stmt = $conn->prepare("SELECT e.*, u.username AS email FROM employees e LEFT JOIN users u ON e.user_id = u.user_id WHERE e.user_id = ? LIMIT 1");
$stmt->bind_param('i', $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc() ?: [];
$emp_id = $employee['emp_id'] ?? null;
$emp_department = $employee['department_name'] ?? null;

$message = '';
$error = '';

// Handle Check-in
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $today = date('Y-m-d');

    if ($action == 'checkin') {
        // Check if already checked in today
        $check_query = $conn->prepare("SELECT attendance_id FROM attendance WHERE emp_id = ? AND attendance_date = ? LIMIT 1");
        $check_query->bind_param("is", $emp_id, $today);
        $check_query->execute();
        $result = $check_query->get_result();

        if ($result->num_rows > 0) {
            $error = "You have already checked in today!";
        } else {
            $check_in_time = date('H:i:s');
            $check_in_time_display=date('h:i A');
            $status = 'Present';
            $insert_query = $conn->prepare("INSERT INTO attendance (emp_id, attendance_date, check_in_time, status) VALUES (?, ?, ?, ?)");
            $insert_query->bind_param("isss", $emp_id, $today, $check_in_time, $status);

            if ($insert_query->execute()) {
                $message = "Check-in successful at " . $check_in_time_display;
            } else {
                $error = "Failed to check in. Please try again.";
            }
        }
    }

    // Handle Check-out (Update)
    elseif ($action == 'checkout') {
        $today = date('Y-m-d');

        $checkout_query = $conn->prepare("SELECT attendance_id FROM attendance WHERE emp_id = ? AND attendance_date = ? LIMIT 1");
        $checkout_query->bind_param("is", $emp_id, $today);
        $checkout_query->execute();
        $result = $checkout_query->get_result();

        if ($result->num_rows == 0) {
            $error = "Please check in first before checking out!";
        } else {
            $message = "Check-out recorded successfully at " . date('h:i A');
        }
    }

    // Handle Add Overtime (Manager or HR)
    elseif ($action == 'add_overtime' && ($userRole == 'manager' || $userRole == 'HR')) {
        $overtime_emp_id = $_POST['emp_id'] ?? null;
        $overtime_date = $_POST['overtime_date'] ?? date('Y-m-d');
        $overtime_hours = $_POST['overtime_hours'] ?? 0;

        if (!$overtime_emp_id || !$overtime_hours) {
            $error = "Please select an employee and enter overtime hours.";
        } else {
            // Check if overtime record exists
            $check_query = $conn->prepare("SELECT overtime_id FROM overtime WHERE emp_id = ? AND overtime_date = ?");
            $check_query->bind_param("is", $overtime_emp_id, $overtime_date);
            $check_query->execute();
            $result = $check_query->get_result();

            if ($result->num_rows > 0) {
                // Update existing record
                $row = $result->fetch_assoc();
                $overtime_id = $row['overtime_id'];
                $update_query = $conn->prepare("UPDATE overtime SET overtime_hours = ? WHERE overtime_id = ?");
                $update_query->bind_param("di", $overtime_hours, $overtime_id);

                if ($update_query->execute()) {
                    $message = "Overtime updated successfully!";
                } else {
                    $error = "Failed to update overtime.";
                }
            } else {
                // Insert new record
                $insert_query = $conn->prepare("INSERT INTO overtime (emp_id, overtime_date, overtime_hours, added_by) VALUES (?, ?, ?, ?)");
                $insert_query->bind_param("isdi", $overtime_emp_id, $overtime_date, $overtime_hours, $emp_id);

                if ($insert_query->execute()) {
                    $message = "Overtime added successfully!";
                } else {
                    $error = "Failed to add overtime.";
                }
            }
        }
    }

    // Handle Generate Report (Manager or HR)
    elseif ($action == 'generate_report' && ($userRole == 'manager' || $userRole == 'HR')) {
        $start_date = $_POST['start_date'] ?? date('Y-m-01');
        $end_date = $_POST['end_date'] ?? date('Y-m-d');

        if ($userRole == 'manager') {
            $report_stmt = $conn->prepare("SELECT a.attendance_date, a.check_in_time, a.status, e.name AS emp_name, e.department_name FROM attendance a JOIN employees e ON a.emp_id = e.emp_id WHERE e.department_name = ? AND a.attendance_date BETWEEN ? AND ? ORDER BY a.attendance_date ASC");
            $report_stmt->bind_param('sss', $emp_department, $start_date, $end_date);
        } else {
            // HR: all employees
            $report_stmt = $conn->prepare("SELECT a.attendance_date, a.check_in_time, a.status, e.name AS emp_name, e.department_name FROM attendance a JOIN employees e ON a.emp_id = e.emp_id WHERE a.attendance_date BETWEEN ? AND ? ORDER BY a.attendance_date ASC");
            $report_stmt->bind_param('ss', $start_date, $end_date);
        }

        if (!$report_stmt->execute()) {
            $error = 'Failed to generate report.';
        } else {
            $report_result = $report_stmt->get_result();

            // Prepare CSV download
            $filename = sprintf('attendance_report_%s_%s_to_%s.csv', $userRole, $start_date, $end_date);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee Name', 'Department', 'Date', 'Check-in Time', 'Status']);

            while ($row = $report_result->fetch_assoc()) {
                $date = $row['attendance_date'];
                $checkin = $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '';
                fputcsv($out, [$row['emp_name'], $row['department_name'], $date, $checkin, $row['status']]);
            }

            fclose($out);
            exit();
        }
    }
}

// Fetch today's attendance status (for employees checking in)
$today = date('Y-m-d');
$today_query = $conn->prepare("SELECT * FROM attendance WHERE emp_id = ? AND attendance_date = ?");
$today_query->bind_param("is", $emp_id, $today);
$today_query->execute();
$today_result = $today_query->get_result();
$today_attendance = $today_result->fetch_assoc();

// Fetch attendance history based on role
if ($userRole == 'employee') {
    // Employee sees only their own attendance
    $history_query = $conn->prepare("SELECT * FROM attendance WHERE emp_id = ? ORDER BY attendance_date DESC LIMIT 30");
    $history_query->bind_param("i", $emp_id);
} elseif ($userRole == 'manager') {
    // Manager sees attendance of employees in their department
    $history_query = $conn->prepare("SELECT a.*, e.name as emp_name FROM attendance a 
                                     JOIN employees e ON a.emp_id = e.emp_id 
                                     WHERE e.department_name = ? ORDER BY a.attendance_date DESC LIMIT 100");
    $history_query->bind_param("s", $emp_department);
} else {
    // HR sees attendance of all employees
    $history_query = $conn->prepare("SELECT a.*, e.name as emp_name FROM attendance a 
                                     JOIN employees e ON a.emp_id = e.emp_id 
                                     ORDER BY a.attendance_date DESC LIMIT 100");
}

$history_query->execute();
$history_result = $history_query->get_result();

// Fetch employees for overtime form
if ($userRole == 'manager') {
    $emp_query = $conn->prepare("SELECT emp_id, name FROM employees WHERE department_name = ? AND emp_id != ? ORDER BY name");
    $emp_query->bind_param("si", $emp_department, $emp_id);
    $emp_query->execute();
    $emp_result = $emp_query->get_result();
} elseif ($userRole == 'HR') {
    // HR can add overtime for managers across departments
    $emp_query = $conn->prepare("SELECT emp_id, name FROM employees WHERE role = 'manager' ORDER BY name");
    $emp_query->execute();
    $emp_result = $emp_query->get_result();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/attendance.css">
</head>

<body>
    <!-- NAVBAR -->
    <header class="navbar">
        <img src="../assets/employee.png" alt="" class="nav-logo">
        <h1 class="logo">EMS</h1>
        <button class="logout-btn" onclick="logout()">Logout</button>
    </header>


    <main class="main">
        <div class="attendance-container">
            <h1>Attendance Management</h1>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Current Time Display -->
            <div class="current-time" id="currentTime"></div>

            <!-- Check-in/Check-out Section -->
            <div class="attendance-card">
                <div class="attendance-header">
                    <h2>Today's Attendance</h2>
                    <?php if ($today_attendance && $today_attendance['status'] == 'Present'): ?>
                        <span class="status-badge status-present">✓ Present</span>
                    <?php else: ?>
                        <span class="status-badge status-absent">✗ Absent</span>
                    <?php endif; ?>
                </div>

                <?php if ($today_attendance): ?>
                    <p><strong>Check-in Time:</strong>
                        <?php echo date('h:i A', strtotime($today_attendance['check_in_time'])); ?>
                    </p>
                <?php endif; ?>

                <div class="button-group">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="checkin">
                        <button type="submit" class="btn-checkin" <?php echo $today_attendance ? 'disabled' : ''; ?>>
                            Check In
                        </button>
                    </form>

                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="checkout">
                        <button type="submit" class="btn-checkout" <?php echo !$today_attendance ? 'disabled' : ''; ?>>
                            Check Out
                        </button>
                    </form>
                </div>
            </div>


            <!-- Overtime Form for Manager or HR -->
            <?php if ($userRole == 'manager' || $userRole == 'HR'): ?>
                <div class="attendance-card">
                    <h2>Add Overtime</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_overtime">
                        <div class="form-group">
                            <label
                                for="emp_id"><?php echo $userRole == 'HR' ? 'Select Manager:' : 'Select Employee:'; ?></label>
                            <select name="emp_id" id="emp_id" required>
                                <option value="">-- Select Employee --</option>
                                <?php
                                $emp_result->data_seek(0);
                                while ($emp = $emp_result->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $emp['emp_id']; ?>"><?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="overtime_date">Date:</label>
                            <input type="date" name="overtime_date" id="overtime_date" value="<?php echo date('Y-m-d'); ?>"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="overtime_hours">Overtime Hours:</label>
                            <input type="number" name="overtime_hours" id="overtime_hours" step="0.5" min="0" max="24"
                                required>
                        </div>
                        <button type="submit" class="btn-submit">Add Overtime</button>
                    </form>
                </div>
            <?php endif; ?>

             <!-- Report Generation for Manager or HR -->
            <?php if ($userRole == 'manager' || $userRole == 'HR'): ?>
                <div class="attendance-card">
                    <h2>Generate Attendance Report</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_report">
                        <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date:</label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" class="btn-submit">Generate Report (CSV)</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Attendance History -->
            <div class="attendance-card">
                <h2>
                    <?php
                    if ($userRole == 'employee') {
                        echo 'My Attendance History (Last 30 Days)';
                    } elseif ($userRole == 'manager') {
                        echo 'Department Attendance (' . htmlspecialchars($emp_department) . ')';
                    } else {
                        echo 'All Employee Attendance';
                    }
                    ?>
                </h2>
                <div class="attendance-history">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <?php if ($userRole != 'employee'): ?>
                                <th>Employee Name</th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Check-in Time</th>
                            <th>Status</th>
                            <th>Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($record = $history_result->fetch_assoc()): ?>
                            <tr>
                                <?php if ($userRole != 'employee'): ?>
                                    <td><?php echo htmlspecialchars($record['emp_name']); ?></td>
                                <?php endif; ?>
                                <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                                <td><?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : 'N/A'; ?>
                                </td>
                                <td>
                                    <span
                                        class="status-badge <?php echo $record['status'] == 'Present' ? 'status-present' : 'status-absent'; ?>">
                                        <?php echo htmlspecialchars($record['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y h:i A', strtotime($record['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </main>
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-NP', {
                timeZone: 'Asia/Kathmandu',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            document.getElementById('currentTime').textContent = timeString;
        }

        updateTime();
        setInterval(updateTime, 1000);
        function logout() {
            window.location.href = "../login.php"
        }
    </script>
</body>

</html>