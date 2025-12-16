<?php
// auth/logout.php
session_start();
session_destroy();
header("Location: /taskly_v1/auth/login.php");
exit;
?>