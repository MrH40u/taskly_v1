<?php
// pages/users.php
require '../config/db.php';
require '../includes/functions.php';

requireAdmin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = cleanInput($_POST['username']);
    $email = cleanInput($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (empty($username) || empty($password) || empty($email)) {
        $error = "Tous les champs sont requis.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Utilisateur ou email déjà existant.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashed, $role])) {
                $success = "Utilisateur créé avec succès !";
            } else {
                $error = "Erreur lors de la création.";
            }
        }
    }
}

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

include '../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start;">
    <!-- Create User Form -->
    <div class="form-card" style="max-width: 100%;">
        <h3 style="margin-bottom: 1.5rem;">Nouvel Utilisateur</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div> <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div> <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="create_user" value="1">
            <div class="form-group">
                <label>Nom d'utilisateur</label>
                <input type="text" name="username" class="form-control" placeholder="ex: jdupont" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" placeholder="ex: j.dupont@email.com" required>
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" class="form-control" placeholder="Minimum 6 caractères" required>
            </div>
            <div class="form-group">
                <label>Rôle</label>
                <select name="role" class="form-control">
                    <option value="dev">Développeur</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary full-width">
                <i class="fas fa-user-plus"></i> Ajouter
            </button>
        </form>
    </div>

    <!-- Users List -->
    <div class="table-container">
        <div class="table-header">
            <h3 class="table-title">Liste des Utilisateurs</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Date d'inscription</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div class="user-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">
                                    <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                </div>
                                <span style="font-weight: 500;"><?php echo htmlspecialchars($u['username']); ?></span>
                            </div>
                        </td>
                        <td style="color: var(--text-muted);"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td>
                            <span class="badge"
                                style="background: <?php echo $u['role'] == 'admin' ? 'rgba(239,68,68,0.15)' : 'rgba(59,130,246,0.15)'; ?>; color: <?php echo $u['role'] == 'admin' ? '#f87171' : '#60a5fa'; ?>;">
                                <?php echo $u['role'] == 'admin' ? 'Admin' : 'Dev'; ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>