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
      </div>
      <div class="login-box">
        <div class="login-container">
          <h2>Login</h2>
          <form action="login.php" method="post">
          <input type="email" name="username" placeholder="Email" required/><br />
          <input type="password" name="password" placeholder="Password" required/><br />
          <button>Login</button><br><br>
            <a href="forgot-password.html" class="forgot">forgot password</a>
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


<?php
require "php/db.php";
session_start();

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $error="Users Not Found";
}else{
$user = $result->fetch_assoc();
 if ($password !== $user["password"]) {
            $error = "Incorrect password";
        } else {
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            if($user['role'] == 'manager'){
              header("Location:manager.html");
              $_SESSION['user_id']=$user['user_id'];
            }elseif($user['role']=='employee'){
              header("Location:employee.html");
              $_SESSION['user_id']=$user['user_id'];
            }elseif($user['role']=='HR'){
            header("Location:hr.html");
            $_SESSION['user_id']=$user['user_id'];
            }
            exit;
        }
    }
?>
