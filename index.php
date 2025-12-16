<?php
// index.php
require 'includes/functions.php';

if (isLoggedIn()) {
    header("Location: /taskly_v1/pages/dashboard.php");
} else {
    header("Location: /taskly_v1/auth/login.php");
}
exit;
?>