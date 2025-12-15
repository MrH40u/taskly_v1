<?php
// includes/functions.php
session_start();

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: /Taskly/auth/login.php");
        exit;
    }
}

function requireAdmin()
{
    requireLogin();
    if (!isAdmin()) {
        die("Access Denied. Admins only.");
    }
}

function cleanInput($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}
?>