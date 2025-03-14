<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/register_styles.css">
  <script src="../assets/js/register.js" defer></script>
  <title>Register | PowerFit</title>
</head>

<body class="register-body">
  <form action="../actions/register_action.php" method="post" enctype="multipart/form-data" class="register-form"
    id="registerForm">
    <!-- Step indicators -->
    <div class="step-indicator">
      <div class="step active" data-step="1">1</div>
      <div class="step-line"></div>
      <div class="step" data-step="2">2</div>
    </div>

    <!-- Step 1: Name and Email -->
    <div class="form-step" id="step1">
      <div class="social-login">
        <button type="button" class="google-button">
          <img src="../assets/images/google.svg" alt="google icon">
          Register with Google
        </button>
      </div>

      <div class="social-login">
        <button type="button" class="facebook-button">
          <img src="../assets/images/facebook.svg" alt="facebook icon">
          Register with Facebook
        </button>
      </div>

      <div class="divider">
        <span>or continue with</span>
      </div>

      <div class="email">
        <label for="username">Username</label>
        <input type="text" name="username" id="username" required placeholder="Enter your username"
          value="<?php echo isset($_SESSION['form_data']['username']) ? htmlspecialchars($_SESSION['form_data']['username']) : ''; ?>">
      </div>

      <div class="email">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" required placeholder="Enter your email"
          value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>">
      </div>

      <button type="button" class="next-button" id="nextStep1">NEXT</button>
    </div>

    <!-- Step 2: Password and Image -->
    <div class="form-step" id="step2" style="display: none;">
      <div class="password">
        <label for="password">Password</label>
        <input type="password" name="password" id="password" required placeholder="Create a password">
      </div>

      <div class="password">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" required
          placeholder="Confirm your password">
      </div>

      <div class="file-upload">
        <label for="profile_image">Profile Image</label>
        <input type="file" name="profile_image" id="profile_image" accept="image/*">
      </div>

      <div class="button-group">
        <button type="button" class="back-button" id="backStep2">BACK</button>
        <button type="submit" name="register" class="login-button">REGISTER</button>
      </div>
    </div>

    <!-- Error and success messages -->
    <?php if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])): ?>
    <div class="error-message">
      <?php
        foreach ($_SESSION['errors'] as $error) {
          echo $error . '<br>';
        }
        unset($_SESSION['errors']);
      ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="success-message">
      <?php
        echo $_SESSION['success_message'];
        unset($_SESSION['success_message']);
      ?>
    </div>
    <?php endif; ?>

    <div>
      <p>Already have an account? <a href="login.php" style="color: #ff4d4d;">Login</a></p>
    </div>
  </form>
</body>

</html>
