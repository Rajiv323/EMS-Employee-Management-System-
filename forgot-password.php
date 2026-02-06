<?php
session_start();
include "php/db.php";

$step = 'email'; // 'email' or 'reset'
$email = '';
$message = '';
$messageType = '';

// Check if email is verified and stored in session
if (isset($_SESSION['reset_email'])) {
    $step = 'reset';
    $email = $_SESSION['reset_email'];
}

// Handle email verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Please enter your email address';
        $messageType = 'error';
    } else {
        // Check if email exists in users table
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                // Email found, allow password reset
                $_SESSION['reset_email'] = $email;
                $step = 'reset';
                $message = 'Email verified! You can now reset your password.';
                $messageType = 'success';
            } else {
                $message = 'Email not found in our system';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    if (!isset($_SESSION['reset_email'])) {
        $message = 'Session expired. Please verify your email again.';
        $messageType = 'error';
        $step = 'email';
    } else {
        $email = $_SESSION['reset_email'];
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        
        if (empty($password) || empty($confirmPassword)) {
            $message = 'Please enter both password fields';
            $messageType = 'error';
        } elseif ($password !== $confirmPassword) {
            $message = 'Passwords do not match';
            $messageType = 'error';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters long';
            $messageType = 'error';
        } else {
            // Update password
            
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE username = ?');
            if ($stmt) {
                $stmt->bind_param('ss', $password, $email);
                if ($stmt->execute()) {
                    $message = 'Password reset successfully! Redirecting to login...';
                    $messageType = 'success';
                    unset($_SESSION['reset_email']);
                    header("refresh:2;url=login.php");
                } else {
                    $message = 'Error updating password. Please try again.';
                    $messageType = 'error';
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="css/login.css" />
    <style>
      .success { color: #28a745; }
      .error { color: #dc3545; }
      .password-fields { margin-top: 12px; }
      .password-fields input { margin-bottom: 10px; }
    </style>
  </head>
  <body>
    <div class="main-container">
      <div class="inner">
        <img src="assets/employee.png" class="login-logo" alt="logo" />
        <h1 class="logo-name">EMS</h1>
      </div>

      <div class="login-box">
        <div class="login-container">
          <h2>Reset Password</h2>

          <?php if ($step === 'email'): ?>
            <form method="POST">
              <input type="email" name="email" placeholder="Enter your email" required /><br />
              <input type="hidden" name="action" value="verify" />
              <button type="submit">Verify Email</button>
            </form>
          <?php else: ?>
            <form method="POST">
              <p>Email: <strong><?= htmlspecialchars($email) ?></strong></p>
              <div class="password-fields">
                <input type="password" name="password" placeholder="Enter new password" required /><br />
                <input type="password" name="confirmPassword" placeholder="Confirm password" required /><br />
              </div>
              <input type="hidden" name="action" value="reset" />
              <button type="submit">Reset Password</button>
            </form>
          <?php endif; ?>

          <p id="msg" class="<?= $messageType ?>"><?= htmlspecialchars($message) ?></p>

          <p style="margin-top:12px">
            <a href="login.php">Back to login</a>
          </p>
        </div>
      </div>
    </div>
  </body>
</html>