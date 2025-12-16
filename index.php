<?php
// index.php
require 'includes/functions.php';

if (isLoggedIn()) {
    header("Location: /Taskly/pages/dashboard.php");
} else {
    header("Location: /Taskly/auth/login.php");
}
exit;
?>