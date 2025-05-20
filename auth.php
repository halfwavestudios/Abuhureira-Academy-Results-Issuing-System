<?php

session_start();

if (isset($_SESSION['name'])) {
  header('location: ./home.php');
  exit;
}

if (!isset($_POST['submit'])) {
  header('location: ./index1.php');
  exit;
}

$conn = mysqli_connect('localhost', 'abuhurei_sribuhost', 'Sonik9200', 'abuhurei_sribuhost');
$username = $_POST['username'];
$password = $_POST['password'];

$query = "SELECT * FROM users WHERE username='$username' AND password='$password'";

$result = mysqli_query($conn, $query);
$users_num = mysqli_num_rows($result);

if ($users_num < 1) {
  $_SESSION['login_failed'] = true;
  header('location: ./index.php');
  exit;
}

$user = mysqli_fetch_assoc($result);
$_SESSION['name'] = $user['name'];

header('location: ./home.php');
exit;
