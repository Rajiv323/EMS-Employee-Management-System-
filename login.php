<?php
require "php/db.php";
session_start();

$error = '';

// Check if there's an error message in session from a previous attempt
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both email and password";
        header("Location: login.php");
        exit;
    }
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $_SESSION['login_error'] = "User not found";
        header("Location: login.php");
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Use password_verify() for hashed passwords
    if ($password != $user["password"]) {
        $_SESSION['login_error'] = "Incorrect password";
        header("Location: login.php");
        exit;
    }
    
    // Login successful
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_id'] = $user['user_id'];
    
    if ($user['role'] == 'manager') {
        header("Location: manager.php");
    } elseif ($user['role'] == 'employee') {
        header("Location: employee.php");
    } elseif ($user['role'] == 'HR') {
        header("Location: hr.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Login</title>
    <link rel="stylesheet" href="css/login.css"/>
  </head>
  <body>
    <div class="main-container">
      <div class="inner">
        <img src="assets/employee.png" class="login-logo" alt="logo" />
        <h1 class="logo-name">EMS</h1>
        <p class="logo-name1">Employee Management System</p>
      </div>
      <div class="login-box">
        <div class="login-container">
          <h2>Login</h2>
          <form action="login.php" method="post">
            <input type="email" name="username" placeholder="Email" required/><br />
            <input type="password" name="password" placeholder="Password" required/><br />
            <button>Login</button><br><br>
            <a href="forgot-password.php" class="forgot">forgot password</a>
          </form>
          <?php 
            if (!empty($error)) {
                echo '<p class="error">'.$error.'</p>';
            }
          ?>
        </div>
      </div>
    </div>
  </body>
</html>
