<?php
// includes/csrf.php

/**
 * Génère un token CSRF unique
 * @return string Le token CSRF
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valide un token CSRF
 * @param string $token Le token à valider
 * @return bool True si le token est valide, false sinon
 */
function validateCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Génère un champ input hidden pour le token CSRF
 * @return string Le HTML du champ input
 */
function csrfField()
{
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Vérifie le token CSRF depuis $_POST et arrête l'exécution si invalide
 * @param string $errorMessage Message d'erreur personnalisé (optionnel)
 * @return bool True si valide
 */
function requireCSRFToken($errorMessage = "Token de sécurité invalide.")
{
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        die($errorMessage);
    }
    return true;
}

