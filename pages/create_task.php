<?php
// pages/create_task.php
require '../config/db.php';
require '../includes/functions.php';
require '../includes/csrf.php';

requireAdmin();

$error = '';
$success = '';

$stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'dev'");
$developers = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Token de sécurité invalide.";
    } else {
        $title = cleanInput($_POST['title']);
        $description = cleanInput($_POST['description']);
        $priority = $_POST['priority'];
        $assigned_to = $_POST['assigned_to'] ?: null;
        $due_date = $_POST['due_date'];

        if (empty($title)) {
            $error = "Le titre est requis.";
        } else {
            try {
                $sql = "INSERT INTO tasks (title, description, priority, assigned_to, created_by, due_date) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $description, $priority, $assigned_to, $_SESSION['user_id'], $due_date]);
                $success = "Tâche créée avec succès !";
                header("refresh:1;url=dashboard.php");
            } catch (PDOException $e) {
                $error = "Erreur: " . $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="form-card">
    <h2 style="margin-bottom: 1.5rem;">Créer une nouvelle tâche</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div> <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div> <?php endif; ?>

    <form method="POST" action="">
        <?php echo csrfField(); ?>
        <div class="form-group">
            <label>Titre</label>
            <input type="text" name="title" class="form-control" placeholder="Titre de la tâche" required>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="4"
                placeholder="Description détaillée..."></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Priorité</label>
                <select name="priority" class="form-control">
                    <option value="low">Basse</option>
                    <option value="medium" selected>Moyenne</option>
                    <option value="high">Haute</option>
                </select>
            </div>

            <div class="form-group">
                <label>Date limite</label>
                <input type="date" name="due_date" class="form-control">
            </div>
        </div>

        <div class="form-group">
            <label>Assigner à</label>
            <select name="assigned_to" class="form-control">
                <option value="">-- Non assigné --</option>
                <?php foreach ($developers as $dev): ?>
                    <option value="<?php echo $dev['id']; ?>"><?php echo htmlspecialchars($dev['username']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Créer la tâche
            </button>
            <a href="dashboard.php" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>