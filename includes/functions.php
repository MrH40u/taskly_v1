<?php
// includes/functions.php

// Démarrer la session une seule fois
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        header("Location: /taskly_v1/auth/login.php");
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

/**
 * Créer une notification pour un utilisateur
 * @param PDO $pdo Connexion base de données
 * @param int $user_id ID de l'utilisateur destinataire
 * @param string $message Message de la notification
 * @param string|null $link Lien optionnel (ex: /taskly_v1/pages/tasks.php?view=123)
 * @return bool
 */
function createNotification($pdo, $user_id, $message, $link = null)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
        return $stmt->execute([$user_id, $message, $link]);
    } catch (PDOException $e) {
        // En prod, logger l'erreur. Pour l'instant, on ignore pour ne pas bloquer le flux principal.
        return false;
    }
}

/**
 * Récupérer le nombre de notifications non lues
 * @param PDO $pdo
 * @param int $user_id
 * @return int
 */
function getUnreadNotificationCount($pdo, $user_id)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}
