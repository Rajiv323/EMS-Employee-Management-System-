<?php
session_start();
include "../php/db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch current user info for sidebar
$currentUserId = $_SESSION['user_id'];
$userQuery = mysqli_query($conn, "SELECT name FROM employees WHERE user_id='$currentUserId'");
$userData = mysqli_fetch_assoc($userQuery);
$currentUserName = $userData['name'];

// Handle Add Employee
if (isset($_POST['addEmployee'])) {
  // Basic validation and sanitization
  $name       = trim($_POST['name'] ?? '');
  $email      = trim($_POST['email'] ?? '');
  $password   = trim($_POST['password'] ?? '');
  $role       = trim($_POST['role'] ?? '');
  $department = trim($_POST['department'] ?? '');
  $phone      = trim($_POST['phone'] ?? '');
  $salary     = floatval($_POST['salary'] ?? 0);
  $address    = trim($_POST['address'] ?? '');

  if ($name === '' || $email === '' || $password === '') {
    die('Name, email and password are required');
  }

  // start transaction to ensure both inserts succeed
  mysqli_begin_transaction($conn);

// Use prepared statements 
  $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
  if ($stmt === false) {
    mysqli_rollback($conn);
    die('Prepare failed (users insert): ' . $conn->error);
  }
  $stmt->bind_param('sss', $email, $password, $role);
  if (!$stmt->execute()) {
    mysqli_rollback($conn);
    die('Error adding user: ' . $stmt->error);
  }
  $user_id = $conn->insert_id;
  $stmt->close();
  // Insert employee row using the detected department column
  $sql = "INSERT INTO employees (user_id, name, role, department_name, salary, address, phone) VALUES (?, ?, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  if ($stmt === false) {
    mysqli_rollback($conn);
    die('Prepare failed (employees insert): ' . $conn->error . ' -- SQL: ' . $sql);
  }
  $stmt->bind_param('isssdss', $user_id, $name, $role, $department, $salary, $address, $phone);
  if (!$stmt->execute()) {
    mysqli_rollback($conn);
    die('Error adding employee: ' . $stmt->error);
  }
  $stmt->close();

  mysqli_commit($conn);
  header('Location: manageemployee.php');
  exit();
}

// Handle Delete Employee
if (isset($_POST['deleteEmployee'])) {
  $emp_id = (int) $_POST['emp_id'];

  // Find associated user and delete both in transaction
  mysqli_begin_transaction($conn);
  $stmt = $conn->prepare('SELECT user_id FROM employees WHERE emp_id = ?');
  if ($stmt === false) {
    mysqli_rollback($conn);
    die('Prepare failed (select user): ' . $conn->error);
  }
  $stmt->bind_param('i', $emp_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $user_id = null;
  if ($r = $res->fetch_assoc()) $user_id = (int)$r['user_id'];
  $stmt->close();

  $stmt = $conn->prepare('DELETE FROM employees WHERE emp_id = ?');
  if ($stmt === false) {
    mysqli_rollback($conn);
    die('Prepare failed (delete employee): ' . $conn->error);
  }
  $stmt->bind_param('i', $emp_id);
  if (!$stmt->execute()) {
    mysqli_rollback($conn);
    die('Error deleting employee: ' . $stmt->error);
  }
  $stmt->close();

  if (!is_null($user_id)) {
    $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
    if ($stmt === false) {
      mysqli_rollback($conn);
      die('Prepare failed (delete user): ' . $conn->error);
    }
    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
      mysqli_rollback($conn);
      die('Error deleting user: ' . $stmt->error);
    }
    $stmt->close();
  }

  mysqli_commit($conn);
  header('Location: manageemployee.php');
  exit();
}

// Handle Edit Employee
if (isset($_POST['editEmployee'])) {
  $emp_id     = (int) ($_POST['emp_id'] ?? 0);
  $name       = trim($_POST['name'] ?? '');
  $email      = trim($_POST['email'] ?? '');
  $password   = trim($_POST['password'] ?? '');
  $role       = trim($_POST['role'] ?? '');
  $department = trim($_POST['department'] ?? '');
  $phone      = trim($_POST['phone'] ?? '');
  $salary     = floatval($_POST['salary'] ?? 0);
  $address    = trim($_POST['address'] ?? '');

  mysqli_begin_transaction($conn);
  $sql = "UPDATE employees SET name = ?, role = ?, department_name = ?, salary = ?, address = ?, phone = ? WHERE emp_id = ?";
  $stmt = $conn->prepare($sql);
  if ($stmt === false) {
    mysqli_rollback($conn);
    die('Prepare failed (update employee): ' . $conn->error . ' -- SQL: ' . $sql);
  }
  $stmt->bind_param('sssdssi', $name, $role, $department, $salary, $address, $phone, $emp_id);
  if (!$stmt->execute()) {
    mysqli_rollback($conn);
    die('Error updating employee: ' . $stmt->error);
  }
  $stmt->close();

  // Update user's username/password/role if user exists
  $stmt = $conn->prepare('SELECT user_id FROM employees WHERE emp_id = ?');
  if ($stmt === false) {
    mysqli_rollback($conn);
    die('Prepare failed (select user for update): ' . $conn->error);
  }
  $stmt->bind_param('i', $emp_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $user_id = null;
  if ($r = $res->fetch_assoc()) $user_id = (int)$r['user_id'];
  $stmt->close();

  if (!is_null($user_id)) {
    $updates = [];
    $types = '';
    $values = [];
    if ($email !== '') { $updates[] = 'username = ?'; $types .= 's'; $values[] = $email; }
    if ($password !== '') { $updates[] = 'password = ?'; $types .= 's'; $values[] = password_hash($password, PASSWORD_DEFAULT); }
    if ($role !== '') { $updates[] = 'role = ?'; $types .= 's'; $values[] = $role; }
    if (count($updates) > 0) {
      $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE user_id = ?';
      $types .= 'i';
      $values[] = $user_id;
      $stmt = $conn->prepare($sql);
      if ($stmt === false) {
        mysqli_rollback($conn);
        die('Prepare failed (update users dynamic): ' . $conn->error);
      }
      // bind params dynamically (use references)
      $tmp = array_merge([$types], $values);
      $refs = [];
      foreach ($tmp as $k => $v) $refs[$k] = &$tmp[$k];
      call_user_func_array([$stmt, 'bind_param'], $refs);
      if (!$stmt->execute()) {
        mysqli_rollback($conn);
        die('Error updating user: ' . $stmt->error);
      }
      $stmt->close();
    }
  }

  mysqli_commit($conn);
  header('Location: manageemployee.php');
  exit();
}

// Fetch employees (include linked user email and user_id)
$result = mysqli_query($conn, "SELECT e.*, u.username AS email, u.user_id AS user_id FROM employees e LEFT JOIN users u ON e.user_id = u.user_id ORDER BY e.emp_id ASC");
?>


<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Employee Details</title>
  </head>

  <body></body>
</html>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>EMS Dashboard</title>
    <link rel="stylesheet" href="../css/style.css" />
    <link rel="stylesheet" href="../css/manage_employee.css" />
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
        <img src="../assets/emp.jpg" class="user-photo" />
        <h3><?= htmlspecialchars($currentUserName) ?></h3>

        <hr />
        <a href="manageemployee.html" class="menu-item">Manage Employees</a
        ><br />
        <a href="payroll.html" class="menu-item">Manage Payroll</a>
      </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main id="main-content">
      <main class="main" id="employee-detail-box">
        <div class="toolbar">
          <h2>Employees</h2>
          <button class="btn" id="addEmployeeBtn" onclick="popup()">
            + Add Employee
          </button>
        </div>

        <div id="msg" style="display: none"></div>

        <div class="detail-card">
          <table class="employee-table" aria-describedby="msg">
            <thead>
              <tr>
                <th>I.D</th>
                <th>Name</th>
                <th>Department</th>
                <th>Role</th>
                <th>Contact</th>
                <th>Salary</th>
                <th style="width: 160px">Actions</th>
              </tr>
            </thead>
            <tbody id="employeesTbody">
               <?php while ($row = mysqli_fetch_assoc($result)) { 
                ?>
                <tr data-empid="<?= $row['emp_id'] ?>" data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>" data-email="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES) ?>" data-address="<?= htmlspecialchars($row['address'] ?? '', ENT_QUOTES) ?>" data-department="<?= htmlspecialchars($row['department_name']) ?>" data-role="<?= htmlspecialchars($row['role'] ?? '', ENT_QUOTES) ?>" data-phone="<?= htmlspecialchars($row['phone'] ?? '', ENT_QUOTES) ?>" data-salary="<?= htmlspecialchars($row['salary'] ?? '', ENT_QUOTES) ?>">
                    <td><?= $row['emp_id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['department_name']) ?></td>
                    <td><?= htmlspecialchars($row['role'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['salary'] ?? '') ?></td>
                    <td>
                    <button onClick="editEmployee(this)">:</button>
                    <div class="editemp" style="display:none;">
                      <button type="button" class="edit-btn" data-empid="<?= $row['emp_id'] ?>">Edit</button>
                      <button type="button" class="delete-btn" data-empid="<?= $row['emp_id'] ?>">Delete</button>
                    </div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
          </table>
        </div>
        <div class="popup" id="popup">
          <div class="popup-content">
            <h3>Add Employee</h3>
                <form action="manageemployee.php" method="POST" id="employeeForm">
                <label>Name:</label>
                <input type="text" id="empName" name="name"/>
                
                <label>Email</label>
                <input type="email" name="email">

                <label>Password</label>
                <input type="password" name="password">

                <label>Address</label>
                <input type="text" name="address">

                <label>Department:</label>
                <input type="text" id="empDept" name="department"/>

                <label>Role</label>
                <input type="text" id="empPost" name="role"/>

                <label>Contact:</label>
                <input type="text" id="empContact" name="phone"/>

                <label>Salary:</label>
                <input type="number" id="empSalary" name="salary"/>
                <input type="hidden" id="empId" name="emp_id" value="" />

                <div class="popup-buttons">
                <button class="save-btn" type="submit" name="addEmployee" id="saveBtn">Add</button>
                <button class="close-btn" onclick="cancel()">Cancel</button>
                </div>
            </form>
          </div>
        </div>
      </main>
    </main>

    <script>
      function logout() {
        window.location.href = "../login.php";
      }
      function popup() {
        document.getElementById("popup").style.display = "flex";
      }

    function editEmployee(button) {
        const editDiv = button.nextElementSibling; // the corresponding div
        editDiv.style.display = "flex";

    // Click outside to hide
        function hideOutside(event) {
            if (!editDiv.contains(event.target) && event.target !== button) {
            editDiv.style.display = "none";
            document.removeEventListener("click", hideOutside);
            }
        }

        // Delay adding listener to avoid immediate hide when clicking the button
        setTimeout(() => {
            document.addEventListener("click", hideOutside);
        }, 0);
    }

    // Wire edit and delete buttons inside the dropdown
        document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('edit-btn')) {
            const empId = e.target.getAttribute('data-empid');
            const row = e.target.closest('tr');
            if (!row) return;
            document.getElementById('empId').value = empId;
            document.getElementById('empName').value = row.dataset.name || '';
            document.querySelector('input[name="email"]').value = row.dataset.email || '';
            document.querySelector('input[name="address"]').value = row.dataset.address || '';
            document.querySelector('input[name="department"]').value = row.dataset.department || '';
            document.querySelector('input[name="role"]').value = row.dataset.role || '';
            document.querySelector('input[name="phone"]').value = row.dataset.phone || '';
            document.getElementById('empSalary').value = row.dataset.salary || '';

            const saveBtn = document.getElementById('saveBtn');
            saveBtn.textContent = 'Save';
            saveBtn.name = 'editEmployee';
            document.getElementById('popup').style.display = 'flex';
        }

        if (e.target && e.target.classList.contains('delete-btn')) {
            const empId = e.target.getAttribute('data-empid');
            if (!confirm('Delete this employee? This cannot be undone.')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manageemployee.php';
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'emp_id';
            idInput.value = empId;
            const delInput = document.createElement('input');
            delInput.type = 'hidden';
            delInput.name = 'deleteEmployee';
            delInput.value = '1';
            form.appendChild(idInput);
            form.appendChild(delInput);
            document.body.appendChild(form);
            form.submit();
        }
    });

// Reset form to add mode
        function cancel() {
        document.getElementById('popup').style.display = 'none';
        const saveBtn = document.getElementById('saveBtn');
        saveBtn.textContent = 'Add';
        saveBtn.name = 'addEmployee';
        document.getElementById('employeeForm').reset();
        document.getElementById('empId').value = '';
        }
    </script>
  </body>
</html>
