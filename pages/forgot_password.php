<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/login_styles.css">
  <title>Change Password | PowerFit</title>
</head>

<body class="login-body">
  <form action="../actions/forgot_action.php" method="post" class="login-form">
    <h2 style="color: #ff6347; text-align: center; margin-bottom: 1.5rem;">Change Password</h2>

    <div class="password">
      <label for="current_password">Current Password</label>
      <input type="password" name="current_password" id="current_password" required
        placeholder="Enter current password">
    </div>

    <div class="password">
      <label for="new_password">New Password</label>
      <input type="password" name="new_password" id="new_password" required placeholder="Enter new password">
    </div>

    <div class="password">
      <label for="confirm_password">Confirm New Password</label>
      <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm new password">
    </div>

    <button type="submit" class="login-button">CHANGE PASSWORD</button>
    <?php if (isset($_SESSION['error'])): ?>
    <div class="error-message">
      <?php
        echo $_SESSION['error'];
        unset($_SESSION['error']);
      ?>
    </div>
    <?php endif; ?>


    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="success-message">
      <?php
        echo $_SESSION['success_message'];
        unset($_SESSION['success_message']); // Clear the success message after displaying
      ?>
    </div>
    <?php endif; ?>

    <div>
      <p><a href="login.php" style="color: #ff4d4d;">Back to Login</a></p>
    </div>
  </form>
</body>

</html>
