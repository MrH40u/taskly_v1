<?php
// pages/edit_task.php
require '../config/db.php';
require '../includes/functions.php';
require '../includes/csrf.php';

requireLogin();

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$task_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();

$stmt = $pdo->prepare("SELECT t.*, p.name as project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    die("Tâche introuvable.");
}

if (!$is_admin && $task['assigned_to'] != $user_id) {
    die("Accès non autorisé.");
}

// Check if task is completed - prevent modification
$isCompleted = ($task['status'] === 'done');

$developers = [];
$projects = [];
if ($is_admin) {
    $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'dev'");
    $developers = $stmt->fetchAll();
    $projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Token de sécurité invalide.";
    } elseif ($isCompleted) {
        $error = "Cette tâche est terminée et ne peut plus être modifiée.";
    } else {
        $status = $_POST['status'];
        
        if ($is_admin) {
            $title = cleanInput($_POST['title']);
            $description = cleanInput($_POST['description']);
            $priority = $_POST['priority'];
            $assigned_to = $_POST['assigned_to'] ?: null;
            $due_date = $_POST['due_date'] ?: null;
            $project_id = $_POST['project_id'];

            $sql = "UPDATE tasks SET title=?, description=?, status=?, priority=?, assigned_to=?, due_date=?, project_id=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $description, $status, $priority, $assigned_to, $due_date, $project_id, $task_id]);
        } else {
            $sql = "UPDATE tasks SET status=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $task_id]);
        }
        
        $success = "Tâche mise à jour !";
        $stmt = $pdo->prepare("SELECT t.*, p.name as project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        
        // Update completed status after save
        $isCompleted = ($task['status'] === 'done');
    }
}

include '../includes/header.php';
?>

<div class="form-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2>Détails de la tâche</h2>
        <?php if ($isCompleted): ?>
            <span class="badge badge-status-done" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                <i class="fas fa-lock"></i> Tâche terminée
            </span>
        <?php endif; ?>
    </div>
    
    <?php if ($error): ?> <div class="alert alert-danger"><?php echo $error; ?></div> <?php endif; ?>
    <?php if ($success): ?> <div class="alert alert-success"><?php echo $success; ?></div> <?php endif; ?>

    <?php if ($isCompleted): ?>
        <!-- Read-only view for completed tasks -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 0.75rem;"><?php echo htmlspecialchars($task['title']); ?></h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem;"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <div>
                    <p style="color: var(--text-muted); font-size: 0.875rem;">Projet</p>
                    <p style="font-weight: 500;"><?php echo htmlspecialchars($task['project_name'] ?? 'ASTREE'); ?></p>
                </div>
                <div>
                    <p style="color: var(--text-muted); font-size: 0.875rem;">Priorité</p>
                    <span class="badge badge-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span>
                </div>
                <div>
                    <p style="color: var(--text-muted); font-size: 0.875rem;">Statut</p>
                    <span class="badge badge-status-done">Terminé</span>
                </div>
                <div>
                    <p style="color: var(--text-muted); font-size: 0.875rem;">Date limite</p>
                    <p style="font-weight: 500;"><?php echo $task['due_date'] ?? 'Non définie'; ?></p>
                </div>
            </div>
        </div>
        
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <a href="tasks.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Retour aux tâches
            </a>
        </div>
    <?php else: ?>
        <!-- Editable form for non-completed tasks -->
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <?php if ($is_admin): ?>
                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($task['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Projet *</label>
                    <select name="project_id" class="form-control" required>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $task['project_id'] == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($task['title']); ?></h3>
                    <p style="color: var(--text-muted); margin-bottom: 1rem;"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                    <p><strong>Projet:</strong> <?php echo htmlspecialchars($task['project_name'] ?? 'ASTREE'); ?></p>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Statut</label>
                <select name="status" class="form-control">
                    <option value="todo" <?php echo $task['status'] == 'todo' ? 'selected' : ''; ?>>À faire</option>
                    <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>En cours</option>
                    <option value="review" <?php echo $task['status'] == 'review' ? 'selected' : ''; ?>>En revue</option>
                    <option value="done" <?php echo $task['status'] == 'done' ? 'selected' : ''; ?>>Terminé</option>
                </select>
            </div>

            <?php if ($is_admin): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Priorité *</label>
                        <select name="priority" class="form-control" required>
                            <option value="low" <?php echo $task['priority'] == 'low' ? 'selected' : ''; ?>>Basse</option>
                            <option value="medium" <?php echo $task['priority'] == 'medium' ? 'selected' : ''; ?>>Moyenne</option>
                            <option value="high" <?php echo $task['priority'] == 'high' ? 'selected' : ''; ?>>Haute</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Date limite</label>
                        <input type="date" name="due_date" class="form-control" value="<?php echo $task['due_date']; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Assigner à *</label>
                    <select name="assigned_to" class="form-control" required>
                        <option value="">-- Non assigné --</option>
                        <?php foreach ($developers as $dev): ?>
                            <option value="<?php echo $dev['id']; ?>" <?php echo $task['assigned_to'] == $dev['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dev['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <p><strong>Priorité:</strong> <span class="badge badge-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span></p>
                    <p style="margin-top: 0.5rem;"><strong>Date limite:</strong> <?php echo $task['due_date'] ?? 'Non définie'; ?></p>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
                <a href="tasks.php" class="btn btn-ghost">Retour</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
