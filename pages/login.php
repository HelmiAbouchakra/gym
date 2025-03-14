<?php session_start();?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/login_styles.css">
  <title>Login | PowerFit</title>
</head>

<body class="login-body">

  <form action="../actions/login_action.php" method="post" class="login-form">
    <div class="social-login">
      <button type="button" class="google-button">
        <img src="../assets/images/google.svg" alt="google icon">
        Login with Google
      </button>
    </div>

    <div class="social-login">
      <button type="button" class="facebook-button">
        <img src="../assets/images/facebook.svg" alt="facebook icon">
        Login with Facebook
      </button>
    </div>

    <div class="divider">
      <span>or continue with</span>
    </div>

    <div class="email">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" required placeholder="Put your email">
    </div>

    <div class="password">
      <label for="password">Password</label>
      <input type="password" name="password" id="password" required placeholder="Put your password">
    </div>

    <a href="forgot_password.php"
      style="color: #fff; align-self: flex-start; margin-left: 0; text-decoration: none; font-size: 0.9rem;">
      Forgot Password?
    </a>

    <button type="submit" class="login-button">LOGIN</button>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="error-message">
      <?php
        echo $_SESSION['error'];
        unset($_SESSION['error']);
      ?>
    </div>
    <?php endif; ?>

    <div>
      <p>New to Powerfit? <a href="register.php" style="color: #ff4d4d;">Register</a></p>
    </div>
  </form>
</body>

</html>
