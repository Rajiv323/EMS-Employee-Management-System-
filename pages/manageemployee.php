<?php
session_start();
include "../php/db.php";
include "../sidenav.php";
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch current user info for sidebar
$currentUserId = $_SESSION['user_id'];
$userQuery = mysqli_query($conn, "SELECT name, photo FROM employees WHERE user_id='$currentUserId'");
$userData = mysqli_fetch_assoc($userQuery);
$currentUserName = $userData['name'];
$currentUserPhoto = $userData['photo'] ?? null;

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
  $date_of_birth = trim($_POST['date_of_birth'] ?? '');
  $date_of_joining = trim($_POST['date_of_joining'] ?? '');
  if ($name === '' || $email === '' || $password === '') {
    die('Name, email and password are required');
  }

  // Handle optional photo upload
  $photoFilename = null;
  if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];
    // Basic validations
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['error'] !== UPLOAD_ERR_OK) {
      die('File upload error');
    }
    if ($file['size'] > $maxSize) {
      die('Photo too large (max 2MB)');
    }
    $finfo = getimagesize($file['tmp_name']);
    if ($finfo === false) {
      die('Uploaded file is not a valid image');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) {
      die('Unsupported image type');
    }
    $targetDir = __DIR__ . '/../assets/';
    if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);
    $photoFilename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetPath = $targetDir . $photoFilename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
      die('Failed to move uploaded file');
    }
  }

  // start transaction to ensure both inserts succeed
  mysqli_begin_transaction($conn);

  // Use prepared statements for users
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

  // Ensure photo column exists
  $colRes = $conn->query("SHOW COLUMNS FROM employees LIKE 'photo'");
  if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN photo VARCHAR(255) NULL");
  }

  // Insert employee row (include photo if present)
  if ($photoFilename !== null) {
    $sql = "INSERT INTO employees (user_id, name, role, department_name, salary, address, phone, date_of_birth, date_of_joining, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
      mysqli_rollback($conn);
      die('Prepare failed (employees insert with photo): ' . $conn->error . ' -- SQL: ' . $sql);
    }
    $stmt->bind_param('isssdsssss', $user_id, $name, $role, $department, $salary, $address, $phone, $date_of_birth, $date_of_joining, $photoFilename);
  } else {
    $sql = "INSERT INTO employees (user_id, name, role, department_name, salary, address, phone, date_of_birth, date_of_joining) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
      mysqli_rollback($conn);
      die('Prepare failed (employees insert): ' . $conn->error . ' -- SQL: ' . $sql);
    }
    $stmt->bind_param('isssdssss', $user_id, $name, $role, $department, $salary, $address, $phone, $date_of_birth, $date_of_joining);
  }
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
  $stmt = $conn->prepare('SELECT user_id, photo FROM employees WHERE emp_id = ?');
  if ($stmt === false) {
    mysqli_rollback($conn);
    die('Prepare failed (select user): ' . $conn->error);
  }
  $stmt->bind_param('i', $emp_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $user_id = null;
  $photoToDelete = null;
  if ($r = $res->fetch_assoc()) { $user_id = (int)$r['user_id']; $photoToDelete = $r['photo'] ?? null; }
  $stmt->close();

  // Delete related payroll records first (due to foreign key constraint)
  $stmt = $conn->prepare('DELETE FROM payroll WHERE emp_id = ?');
  if ($stmt === false) {
    mysqli_rollback($conn);
    die('Prepare failed (delete payroll): ' . $conn->error);
  }
  $stmt->bind_param('i', $emp_id);
  if (!$stmt->execute()) {
    mysqli_rollback($conn);
    die('Error deleting payroll: ' . $stmt->error);
  }
  $stmt->close();

  // Delete attendance records first
$stmt = $conn->prepare('DELETE FROM attendance WHERE emp_id = ?');
if ($stmt === false) {
  mysqli_rollback($conn);
  die('Prepare failed (delete attendance): ' . $conn->error);
}
$stmt->bind_param('i', $emp_id);
if (!$stmt->execute()) {
  mysqli_rollback($conn);
  die('Error deleting attendance: ' . $stmt->error);
}
$stmt->close();

  // Now delete the employee
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

  // Delete photo file if present
  if (!empty($photoToDelete)) {
    $filePath = __DIR__ . '/../assets/' . $photoToDelete;
    if (file_exists($filePath)) @unlink($filePath);
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
  $date_of_birth = trim($_POST['date_of_birth'] ?? '');
  $date_of_joining = trim($_POST['date_of_joining'] ?? '');

  // Handle photo upload on edit (optional)
  $photoFilename = null;
  if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];
    $maxSize = 2 * 1024 * 1024;
    if ($file['error'] !== UPLOAD_ERR_OK) {
      mysqli_rollback($conn);
      die('File upload error');
    }
    if ($file['size'] > $maxSize) {
      mysqli_rollback($conn);
      die('Photo too large (max 2MB)');
    }
    $finfo = getimagesize($file['tmp_name']);
    if ($finfo === false) {
      mysqli_rollback($conn);
      die('Uploaded file is not a valid image');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) {
      mysqli_rollback($conn);
      die('Unsupported image type');
    }
    $targetDir = __DIR__ . '/../assets/';
    if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);
    $photoFilename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetPath = $targetDir . $photoFilename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
      mysqli_rollback($conn);
      die('Failed to move uploaded file');
    }
  }

  mysqli_begin_transaction($conn);
  // Ensure photo column exists
  $colRes = $conn->query("SHOW COLUMNS FROM employees LIKE 'photo'");
  if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN photo VARCHAR(255) NULL");
  }

  if ($photoFilename !== null) {
    $sql = "UPDATE employees SET name = ?, role = ?, department_name = ?, salary = ?, address = ?, phone = ?, date_of_birth = ?, date_of_joining = ?, photo = ? WHERE emp_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
      mysqli_rollback($conn);
      die('Prepare failed (update employee with photo): ' . $conn->error . ' -- SQL: ' . $sql);
    }
    $stmt->bind_param('sssdsssssi', $name, $role, $department, $salary, $address, $phone, $date_of_birth, $date_of_joining, $photoFilename, $emp_id);
  } else {
    $sql = "UPDATE employees SET name = ?, role = ?, department_name = ?, salary = ?, address = ?, phone = ?, date_of_birth = ?, date_of_joining = ? WHERE emp_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
      mysqli_rollback($conn);
      die('Prepare failed (update employee): ' . $conn->error . ' -- SQL: ' . $sql);
    }
    $stmt->bind_param('sssdssssi', $name, $role, $department, $salary, $address, $phone, $date_of_birth, $date_of_joining, $emp_id);
  }
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
    if ($password !== '') { $updates[] = 'password = ?'; $types .= 's'; $values[] = $password; }
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

// Fetch employees (include linked user email and user_id, and net_salary from payroll)
$result = mysqli_query($conn, "SELECT e.*, u.username AS email, u.user_id AS user_id, COALESCE(p.net_salary, e.salary) AS display_salary FROM employees e LEFT JOIN users u ON e.user_id = u.user_id LEFT JOIN payroll p ON e.emp_id = p.emp_id ORDER BY e.emp_id ASC");
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
    <!-- <aside class="sidebar" id="sidebar-menu">
      <div class="user-box">
        <img src="<?= htmlspecialchars(!empty($currentUserPhoto) ? '../assets/' . $currentUserPhoto : '../assets/emp.jpg') ?>" class="user-photo" />
        <h3><?= htmlspecialchars($currentUserName) ?></h3>

        <hr />
        <a href="manageemployee.php" class="menu-item">Manage Employees</a
        ><br />
        <a href="payroll.php" class="menu-item">Manage Payroll</a>
      </div>
    </aside> -->

    <!-- MAIN CONTENT -->
    <main id="main-content">
      <main class="main" id="employee-detail-box">
        <div class="toolbar">
          <h2>Employees</h2>
          <button class="btn" id="addEmployeeBtn" onclick="popup()">+ Add Employee</button>
        </div>

        <div id="msg" style="display: none"></div>

        <div class="detail-card">
          <table class="employee-table" aria-describedby="msg">
            <thead>
              <tr>
                <th>Emp ID</th>
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
                <tr data-empid="<?= $row['emp_id'] ?>" data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>" data-email="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES) ?>" data-address="<?= htmlspecialchars($row['address'] ?? '', ENT_QUOTES) ?>" data-department="<?= htmlspecialchars($row['department_name']) ?>" data-role="<?= htmlspecialchars($row['role'] ?? '', ENT_QUOTES) ?>" data-phone="<?= htmlspecialchars($row['phone'] ?? '', ENT_QUOTES) ?>" data-salary="<?= htmlspecialchars($row['display_salary'] ?? '', ENT_QUOTES) ?>" data-dob="<?= htmlspecialchars($row['date_of_birth'] ?? '', ENT_QUOTES) ?>" data-doj="<?= htmlspecialchars($row['date_of_joining'] ?? '', ENT_QUOTES) ?>">
                    <td><?= $row['emp_id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['department_name']) ?></td>
                    <td><?= htmlspecialchars($row['role'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['display_salary'] ?? '') ?></td>
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
                <form action="manageemployee.php" method="POST" id="employeeForm" enctype="multipart/form-data">
                <input type="hidden" id="empId" name="emp_id" value="">
                
                <label>Name:</label>
                <input type="text" id="empName" name="name" pattern="[a-zA-Z\s'-]+" title="Name can only contain letters, spaces, hyphens, and apostrophes" required/>
                
                <label>Email</label>
                <input type="email" name="email" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}" title="Please enter a valid email address" required>

                <label>Password</label>
                <input type="password" name="password" id="empPassword">

                <label>Address</label>
                <input type="text" name="address">

                <label>Department:</label>
                <select name="department" id="empDept">
                  <option value="null">Select Department</option>
                  <option value="Human Resource">HR</option>
                  <option value="Logistics">Logistics</option>
                  <option value="Operations">Operations</option>
                </select>

                <label>Role</label>
                <select name="role" id="empPost">
                  <option value="">Select Role</option>
                  <option value="HR">HR</option>
                  <option value="employee">Employee</option>
                  <option value="manager">Manager</option>
                </select>

                <label>Contact:</label>
                <input type="text" id="empContact" name="phone"/>

                <label>Date of Birth:</label>
                <input type="date" id="empDOB" name="date_of_birth"/>

                <label>Date of Joining:</label>
                <input type="date" id="empDOJ" name="date_of_joining"/>

                <label>Salary:</label>
                <input type="number" id="empSalary" name="salary" step="0.01" min="0"/>

                <label>Profile Photo:</label>
                <input type="file" name="photo" accept="image/*">

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

      let currentOpenDropdown = null;

      function editEmployee(button) {
        const editDiv = button.nextElementSibling; // the corresponding div
        
        // Close previously open dropdown
        if (currentOpenDropdown && currentOpenDropdown !== editDiv) {
          currentOpenDropdown.style.display = "none";
        }
        
        // Toggle current dropdown
        if (editDiv.style.display === "flex") {
          editDiv.style.display = "none";
          currentOpenDropdown = null;
        } else {
          editDiv.style.display = "flex";
          currentOpenDropdown = editDiv;
        }
      }

      // Wire edit and delete buttons inside the dropdown
      document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('edit-btn')) {
          e.stopPropagation();
          const empId = e.target.getAttribute('data-empid');
          const row = e.target.closest('tr');
          if (!row) return;
          document.getElementById('empId').value = empId;
          document.getElementById('empName').value = row.dataset.name || '';
          document.querySelector('input[name="email"]').value = row.dataset.email || '';
          document.querySelector('input[name="address"]').value = row.dataset.address || '';
          document.querySelector('select[name="department"]').value = row.dataset.department || '';
          var roleSelect = document.querySelector('select[name="role"]');
          if (roleSelect) roleSelect.value = row.dataset.role || '';
          // Update role enable/disable state after setting department and role
          try { updateRoleOptions(); } catch (e) {}
          document.querySelector('input[name="phone"]').value = row.dataset.phone || '';
          document.getElementById('empSalary').value = row.dataset.salary || '';
          document.getElementById('empDOB').value = row.dataset.dob || '';
          document.getElementById('empDOJ').value = row.dataset.doj || '';
          document.getElementById('empPassword').value = '';

          const saveBtn = document.getElementById('saveBtn');
          saveBtn.textContent = 'Save';
          saveBtn.name = 'editEmployee';
          document.getElementById('popup').style.display = 'flex';
          
          // Close the dropdown after selecting edit
          if (currentOpenDropdown) {
            currentOpenDropdown.style.display = "none";
            currentOpenDropdown = null;
          }
        }

        if (e.target && e.target.classList.contains('delete-btn')) {
          e.stopPropagation();
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

      // Close dropdown when clicking outside
      document.addEventListener('click', function (e) {
        if (currentOpenDropdown && !currentOpenDropdown.contains(e.target) && e.target.tagName !== 'BUTTON') {
          currentOpenDropdown.style.display = "none";
          currentOpenDropdown = null;
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
        document.getElementById('empPassword').removeAttribute('required');
      }

      // Enforce HR role only when department is Human Resource
      var deptSelect = document.querySelector('select[name="department"]');
      var roleSelect = document.querySelector('select[name="role"]');
      function updateRoleOptions() {
        if (!deptSelect || !roleSelect) return;
        var isHRDept = deptSelect.value === 'Human Resource';
        // When HR department selected, only HR role is enabled
        for (var i = 0; i < roleSelect.options.length; i++) {
          var opt = roleSelect.options[i];
          if (isHRDept) {
            // enable HR only
            opt.disabled = opt.value !== 'HR' && opt.value !== '';
          } else {
            // enable all; but keep HR option disabled unless dept is HR
            opt.disabled = false;
            if (opt.value === 'HR') opt.disabled = false; // will be cleared
          }
        }
        // If HR is not allowed but currently selected, clear it
        if (!isHRDept && roleSelect.value === 'HR') {
          roleSelect.value = '';
        }
      }
      if (deptSelect) {
        deptSelect.addEventListener('change', function() {
          // auto-select HR role for HR department
          if (this.value === 'Human Resource' && roleSelect) {
            roleSelect.value = 'HR';
          }
          updateRoleOptions();
        });
      }
      // initialize on load
      updateRoleOptions();

      // Validate on submit to prevent invalid assignment
      var employeeForm = document.getElementById('employeeForm');
      if (employeeForm) {
        employeeForm.addEventListener('submit', function(e) {
          if (roleSelect && deptSelect && roleSelect.value === 'HR' && deptSelect.value !== 'Human Resource') {
            e.preventDefault();
            alert('HR role can only be assigned when Department is Human Resource.');
            return false;
          }
        });
      }

      // Validate name field - only letters, spaces, hyphens, and apostrophes
      document.getElementById('empName').addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z\s'-]/g, '');
      });

      // Validate email field - reject invalid characters
      document.querySelector('input[name="email"]').addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z0-9._%+\-@]/g, '');
      });
    </script>
  </body>
</html>
