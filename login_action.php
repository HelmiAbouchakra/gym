<?php
session_start();
require_once __DIR__ . '/config/db_config.php';
if($_SERVER["REQUEST_METHOD"] == "POST"){
  $email=htmlspecialchars($_POST['email']);
  $password=trim($_POST['password']);
  if(empty($email) || empty($password)){
    $_SESSION['error']="All fields are required";
    header("Location: login.php");
    exit();
}
    $sql="SELECT * FROM users WHERE email=:email";
    $stmt=$pdo->prepare($sql);
    $stmt->bindparam(":email",$email,PDO::PARAM_STR);
    $stmt->execute();
    $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if($email==$user['email'] && $password==$user['password']) {
      header("Location: index.php");
      exit();
    }elseif($user["role"]=="admin"){
      header("Location: adminpanel.php");
      exit();
    }else{
      $_SESSION['error']="Invalid email or password";
      header("Location: login.php");
      exit();
    } 
}?>