<?php
SESSION_START();
include "../php/db.php";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>EMS Dashboard</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/payroll.css">
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
    <img src="<?= htmlspecialchars(!empty($currentUserPhoto) ? '../assets/' . $currentUserPhoto : '../assets/emp.jpg') ?>" class="user-photo">
    <h3><?= htmlspecialchars($currentUserName) ?></h3></p>
        <hr>
        <a href="manageemployee.php" class="menu-item">Manage Employees</a><br>
        <a href="payroll.php" class="menu-item">Manage Payroll</a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main id="main-content">
     <div class="main">
        <div class="toolbar">
            <h2>Manage Payroll</h2>
            <button class="btn" onclick="openAddPayroll()">+ Add Payroll</button>
        </div>

        <div class="detail-card">

            <?php
            // Fetch payroll joined with employees. Use COALESCE to tolerate different column names and fall back to employee salary.
            $payrollQuery = "SELECT e.emp_id, e.name, e.department_name AS department, e.role, p.basic_salary, p.deductions, p.net_salary FROM payroll p JOIN employees e ON p.emp_id = e.emp_id ORDER BY e.emp_id ASC";
            $result = mysqli_query($conn, $payrollQuery);
            ?>

            <table class="payroll-table">
                <thead>
                    <tr>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Base Salary</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="payrollBody">
                   <?php
                   if ($result && mysqli_num_rows($result) > 0) {
                       while ($row = mysqli_fetch_assoc($result)) {
                           $empId = htmlspecialchars($row['emp_id'], ENT_QUOTES);
                           $name = htmlspecialchars($row['name'], ENT_QUOTES);
                           $department = htmlspecialchars($row['department'], ENT_QUOTES);
                           $role = htmlspecialchars($row['role'], ENT_QUOTES);
                           $base = number_format((float)$row['basic_salary'], 2, '.', '');
                           $deductions = number_format((float)$row['deductions'], 2, '.', '');
                           $net = number_format((float)$row['net_salary'], 2, '.', '');
                           echo '<tr data-empid="'.$empId.'" data-name="'.$name.'" data-department="'.$department.'" data-role="'.$role.'" data-base="'.$base.'" data-deductions="'.$deductions.'" data-net="'.$net.'">';
                           echo '<td>'.$empId.'</td>';
                           echo '<td>'.$name.'</td>';
                           echo '<td>'.$department.'</td>';
                           echo '<td>'.$role.'</td>';
                           echo '<td>'.number_format((float)$base, 2).'</td>';
                           echo '<td>'.number_format((float)$deductions, 2).'</td>';
                           echo '<td>'.number_format((float)$net, 2).'</td>';
                           echo '<td><button class="btn small" type="button" onclick="openEditPayroll(this)">Edit</button></td>';
                           echo '</tr>';
                       }
                   } else {
                       echo '<tr><td colspan="8">No payroll records found.</td></tr>';
                   }
                   ?>
                </tbody>
            </table>

        </div>
    </div>

    <!-- POPUP FORM -->
    <div id="popup" class="popup">
        <div class="popup-content">
            <h3 id="popupTitle">Add Payroll Entry</h3>

            <form id="payrollForm">
                <input type="hidden" id="payrollEmpId" name="emp_id">

                <label>Employee ID:</label>
                <select id="pEmpIdDisplay" name="emp_id_select" onchange="populateEmployeeDetails()">
                    <option value="">Select Employee</option>
                    <?php
                    // Fetch all employees for dropdown
                    $empQuery = "SELECT emp_id, name, department_name, role, salary FROM employees ORDER BY emp_id ASC";
                    $empResult = mysqli_query($conn, $empQuery);
                    if ($empResult && mysqli_num_rows($empResult) > 0) {
                        while ($empRow = mysqli_fetch_assoc($empResult)) {
                            echo '<option value="'.$empRow['emp_id'].'" data-name="'.$empRow['name'].'" data-department="'.$empRow['department_name'].'" data-role="'.$empRow['role'].'" data-salary="'.$empRow['salary'].'">'.$empRow['emp_id'].' - '.$empRow['name'].'</option>';
                        }
                    }
                    ?>
                </select>

                <label>Employee Name:</label>
                <input type="text" id="pName" name="name" readonly>

                <label>Department:</label>
                <input type="text" id="pDept" name="department" readonly>

                <label>Role:</label>
                <input type="text" id="pRole" name="role" readonly>

                <label>Base Salary:</label>
                <input type="number" id="pSalary" name="base_salary" step="0.01" oninput="calculateDeductions()">

                <label>Deductions:</label>
                <input type="number" id="pDeductions" name="deductions" step="0.01" readonly>

                <label>Net Salary:</label>
                <input type="number" id="pNet" name="net_salary" readonly>

                

                <div class="popup-buttons">
                    <button type="button" class="save-btn" id="addPayrollBtn" onclick="submitAddPayroll()">Add</button>
                    <button type="button" class="save-btn" id="updatePayrollBtn" style="display:none" onclick="submitUpdatePayroll()">Update</button>
                    <button type="button" class="close-btn" onclick="closePayrollPopup()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

  </main>

  <script>
    function logout(){
      window.location.href="../login.php"
    }
    function openAddPayroll(){
        document.getElementById('popupTitle').textContent = 'Add Payroll Entry';
        document.getElementById('payrollForm').reset();
        document.getElementById('payrollEmpId').value = '';
        document.getElementById('pEmpIdDisplay').value = '';
        document.getElementById('pName').value = '';
        document.getElementById('pDept').value = '';
        document.getElementById('pRole').value = '';
        document.getElementById('pSalary').value = '';
        document.getElementById('pDeductions').value = '';
        document.getElementById('pNet').value = '';
        document.getElementById('addPayrollBtn').style.display = 'inline-block';
        document.getElementById('updatePayrollBtn').style.display = 'none';
        document.getElementById('popup').style.display = 'flex';
        recalcNet();
    }
    function populateEmployeeDetails(){
        var select = document.getElementById('pEmpIdDisplay');
        var selectedOption = select.options[select.selectedIndex];
        var empId = select.value;
        
        if (empId) {
            document.getElementById('payrollEmpId').value = empId;
            document.getElementById('pName').value = selectedOption.dataset.name || '';
            document.getElementById('pDept').value = selectedOption.dataset.department || '';
            document.getElementById('pRole').value = selectedOption.dataset.role || '';
            document.getElementById('pSalary').value = selectedOption.dataset.salary || '';
            calculateDeductions();
        } else {
            document.getElementById('payrollEmpId').value = '';
            document.getElementById('pName').value = '';
            document.getElementById('pDept').value = '';
            document.getElementById('pRole').value = '';
            document.getElementById('pSalary').value = '';
            document.getElementById('pDeductions').value = '';
            document.getElementById('pNet').value = '';
        }
    }
    function openEditPayroll(button){
        var tr = button.closest('tr');
        var ds = tr.dataset;
        document.getElementById('popupTitle').textContent = 'Edit Payroll Entry';
        document.getElementById('payrollEmpId').value = ds.empid || '';
        document.getElementById('pEmpIdDisplay').value = ds.empid || '';
        document.getElementById('pName').value = ds.name || '';
        document.getElementById('pDept').value = ds.department || '';
        document.getElementById('pRole').value = ds.role || '';
        document.getElementById('pSalary').value = parseFloat(ds.base) || 0;
        document.getElementById('pDeductions').value = parseFloat(ds.deductions) || 0;
        document.getElementById('pNet').value = parseFloat(ds.net) || 0;
        document.getElementById('addPayrollBtn').style.display = 'none';
        document.getElementById('updatePayrollBtn').style.display = 'inline-block';
        document.getElementById('popup').style.display = 'flex';
    }
    function closePayrollPopup(){
        document.getElementById("popup").style.display="none";
    }
    function calculateDeductions(){
        var base = parseFloat(document.getElementById('pSalary').value) || 0;
        var deductions = (base * 0.05).toFixed(2);
        document.getElementById('pDeductions').value = deductions;
        recalcNet();
    }
    function recalcNet(){
        var base = parseFloat(document.getElementById('pSalary').value) || 0;
        var ded = parseFloat(document.getElementById('pDeductions').value) || 0;
        document.getElementById('pNet').value = (base - ded).toFixed(2);
    }

    function submitUpdatePayroll(){
        var form = document.getElementById('payrollForm');
        var fd = new FormData(form);
        fetch('update_payroll.php', { method: 'POST', body: fd })
          .then(res => res.json())
          .then(json => {
              if (json.status === 'success') {
                  var empid = document.getElementById('payrollEmpId').value;
                  var tr = document.querySelector('tr[data-empid="'+empid+'"]');
                  if (tr) {
                      tr.dataset.base = document.getElementById('pSalary').value;
                      tr.dataset.deductions = document.getElementById('pDeductions').value;
                      tr.dataset.net = document.getElementById('pNet').value;
                      tr.cells[4].innerText = parseFloat(document.getElementById('pSalary').value).toFixed(2);
                      tr.cells[5].innerText = parseFloat(document.getElementById('pDeductions').value).toFixed(2);
                      tr.cells[6].innerText = parseFloat(document.getElementById('pNet').value).toFixed(2);
                  }
                  closePayrollPopup();
              } else {
                  alert('Error: ' + (json.message || 'Unable to update'));
              }
          }).catch(err => alert('Network error'));
    }

    function submitAddPayroll(){
        var empId = document.getElementById('payrollEmpId').value;
        if (!empId) {
            alert('Error: Please select an employee');
            return;
        }
        var form = document.getElementById('payrollForm');
        var fd = new FormData(form);
        // Ensure emp_id is included
        fd.set('emp_id', empId);
        fetch('update_payroll.php', { method: 'POST', body: fd })
          .then(res => res.json())
          .then(json => {
              if (json.status === 'success') {
                  // simple approach: reload to show new row
                  location.reload();
              } else {
                  alert('Error: ' + (json.message || 'Unable to add'));
              }
          }).catch(err => alert('Network error'));
    }
  </script>
</body>
</html>
