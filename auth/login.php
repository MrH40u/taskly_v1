<?php
// auth/login.php
require '../config/db.php';
require '../includes/functions.php';
require '../includes/csrf.php';

if (isLoggedIn()) {
    header("Location: /taskly_v1/pages/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Token de sécurité invalide. Veuillez réessayer.";
    } else {
        $username = cleanInput($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = "Veuillez remplir tous les champs.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];

                header("Location: /taskly_v1/pages/dashboard.php");
                exit;
            } else {
                $error = "Identifiants invalides.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Taskly</title>
    <link rel="stylesheet" href="/taskly_v1/assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="auth-page">

    <div class="auth-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <i class="fas fa-tasks" style="font-size: 2.5rem; color: var(--primary);"></i>
            <h2 style="margin-top: 1rem;">Connexion à Taskly</h2>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Gérez vos tâches efficacement</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>
            
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" class="form-control"
                    placeholder="Entrez votre nom d'utilisateur" required>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" class="form-control"
                    placeholder="Entrez votre mot de passe" required>
            </div>

            <button type="submit" class="btn btn-primary full-width" style="margin-top: 0.5rem;">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>
    </div>

</body>

</html>