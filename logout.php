<?php
require 'config.php';

$_SESSION = [];
session_destroy();
header('Location: admin_login.php');
exit();
?>