<?php
// auth/logout.php
session_start();
session_destroy();
header("Location: /Taskly/auth/login.php");
exit;
?>