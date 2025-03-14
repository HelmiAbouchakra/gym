<?php
session_start();
require_once __DIR__ . '/config/db_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/styles.css">
    <title>Login</title>
</head>
<body class="login-body">
  
    <form action="login_action.php" method="post" class="login-form">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800">
  <!-- Background Circle -->
  <circle cx="400" cy="400" r="380" fill="black"/>
  <circle cx="400" cy="400" r="360" fill="#111"/>
  
  <!-- Outer Ring -->
  <circle cx="400" cy="400" r="340" fill="none" stroke="#FF6B00" stroke-width="16"/>
  
  <!-- Dumbbell Icon -->
  <g>
    <!-- Left Weight -->
    <rect x="150" y="340" width="60" height="120" rx="10" fill="#FF6B00"/>
    <!-- Right Weight -->
    <rect x="590" y="340" width="60" height="120" rx="10" fill="#FF6B00"/>
    <!-- Bar -->
    <rect x="210" y="380" width="380" height="40" fill="#FF6B00"/>
    <!-- Center Grip -->
    <rect x="350" y="370" width="100" height="60" rx="10" fill="#111"/>
  </g>
  
  <!-- Text -->
  <text x="400" y="540" font-family="Arial, sans-serif" font-size="84" font-weight="bold" text-anchor="middle" fill="#FF6B00">POWER</text>
  <text x="400" y="630" font-family="Arial, sans-serif" font-size="84" font-weight="bold" text-anchor="middle" fill="white">FIT</text>
  
  <!-- Accent Lines -->
  <line x1="280" y1="670" x2="520" y2="670" stroke="#FF6B00" stroke-width="8"/>
  <line x1="300" y1="690" x2="500" y2="690" stroke="#FF6B00" stroke-width="4"/>
</svg>
      <div>
        <button type="submit" class="google-button"><svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="20" height="20" viewBox="0 0 48 48">
<path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"></path><path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"></path><path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"></path><path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"></path>
</svg><p >Login with Google</p><p></p></button>
</div>
          <div> 
            <button type="submit" class="facebook-button"><svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="20" height="20" viewBox="0,0,256,256">
<g fill="#ffffff" fill-rule="nonzero" stroke="none" stroke-width="1" stroke-linecap="butt" stroke-linejoin="miter" stroke-miterlimit="10" stroke-dasharray="" stroke-dashoffset="0" font-family="none" font-weight="none" font-size="none" text-anchor="none" style="mix-blend-mode: normal"><g transform="scale(5.12,5.12)"><path d="M30.14063,2c-3.27025,0 -6.09523,0.99694 -8.07812,2.9668c-1.9829,1.96986 -3.0625,4.85703 -3.0625,8.40039v4.63281h-6c-0.55226,0.00006 -0.99994,0.44774 -1,1v8c0.00006,0.55226 0.44774,0.99994 1,1h6v19c0.00006,0.55226 0.44774,0.99994 1,1h8c0.55226,-0.00006 0.99994,-0.44774 1,-1v-19h7c0.50395,-0.00003 0.92915,-0.37501 0.99219,-0.875l1,-8c0.03584,-0.28475 -0.05237,-0.57117 -0.24221,-0.78642c-0.18983,-0.21524 -0.46299,-0.33856 -0.74998,-0.33858h-8v-4c0,-1.11667 0.88333,-2 2,-2h6c0.55226,-0.00006 0.99994,-0.44774 1,-1v-7.6543c-0.00063,-0.50124 -0.37221,-0.9246 -0.86914,-0.99023c-0.88367,-0.11725 -4.07368,-0.35547 -6.99023,-0.35547zM30.14063,4c2.43785,0 4.79448,0.19505 5.85938,0.29492v5.70508h-5c-2.19733,0 -4,1.80267 -4,4v5c0.00006,0.55226 0.44774,0.99994 1,1h7.86719l-0.75,6h-7.11719c-0.55226,0.00006 -0.99994,0.44774 -1,1v19h-6v-19c-0.00006,-0.55226 -0.44774,-0.99994 -1,-1h-6v-6h6c0.55226,-0.00006 0.99994,-0.44774 1,-1v-5.63281c0,-3.14464 0.9203,-5.44028 2.47266,-6.98242c1.55235,-1.54214 3.79722,-2.38477 6.66797,-2.38477z"></path></g></g>
</svg><p>Login with Facebook</p><p></p></button>
</div>
      
      <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
  <tr>
    <td style="width: 40%; padding: 0;">
      <hr style="height: 1px; border: none; background-color: #777; margin: 0;">
    </td>
    <td style="white-space: nowrap; text-align: center; padding: 0 15px; color: #fff; font-size: 14px;">
      or continue with
    </td>
    <td style="width: 40%; padding: 0;">
      <hr style="height: 1px; border: none; background-color: #777; margin: 0;">
    </td>
  </tr>
</table>
      <div class="email">
          <label for="email">Email</label>
          <input type="email" name="email" id="email" required placeholder="Put your email">
        </div>
      <div class="password">
        <label for="password">Password</label>
        <input type="password" name="password" id="password" required  placeholder="Put your password">
      </div>
      <?php
        if(isset($_SESSION["error"])){
          echo "<p style='color: red'>".$_SESSION["error"]."</p>";
          unset($_SESSION["error"]);
        }      
        
      ?>
      <a href="forgot-password.php" style="color: #fff; align-self:start; margin-left:20px"  ><p class="hover">Forgot Password?</p></a>
      <button type="submit" class="login-button"><a href="loginauth.php">Login</a></button>
   
      <div> 
      <p>New to Powerfit? <a href="register.php" style="color: #7FDBFF; ">Register</a></p>
    </div>
    </form>
</body> 
</html>