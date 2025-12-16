<?php
// pages/projects.php
require '../config/db.php';
require '../includes/functions.php';
require '../includes/csrf.php';

requireAdmin();

$error = '';
$success = '';

// Handle Add Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Token de sécurité invalide.";
    } elseif ($_POST['action'] === 'add') {
        $name = cleanInput($_POST['name']);
        $description = cleanInput($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#6366f1';

        if (empty($name)) {
            $error = "Le nom du projet est requis.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM projects WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                $error = "Un projet avec ce nom existe déjà.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO projects (name, description, color) VALUES (?, ?, ?)");
                if ($stmt->execute([$name, $description, $color])) {
                    $success = "Projet créé avec succès !";
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['project_id'])) {
        $project_id = $_POST['project_id'];
        // Don't delete ASTREE
        $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch();
        if ($project && $project['name'] === 'ASTREE') {
            $error = "Le projet ASTREE ne peut pas être supprimé.";
        } else {
            // Get ASTREE id for reassignment
            $stmt = $pdo->query("SELECT id FROM projects WHERE name = 'ASTREE'");
            $astree = $stmt->fetch();
            if ($astree) {
                // Reassign tasks to ASTREE
                $pdo->prepare("UPDATE tasks SET project_id = ? WHERE project_id = ?")->execute([$astree['id'], $project_id]);
            }
            $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$project_id]);
            $success = "Projet supprimé. Les tâches ont été réassignées à ASTREE.";
        }
    }
}

// Fetch projects with task count
$projects = $pdo->query("
    SELECT p.*, COUNT(t.id) as task_count 
    FROM projects p 
    LEFT JOIN tasks t ON p.id = t.project_id 
    GROUP BY p.id 
    ORDER BY p.created_at DESC
")->fetchAll();

include '../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start;">
    <!-- Add Project Form -->
    <div class="form-card" style="max-width: 100%;">
        <h3 style="margin-bottom: 1.5rem;">Nouveau Projet</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div> <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div> <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Nom du projet *</label>
                <input type="text" name="name" class="form-control" placeholder="ex: Mon Projet" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"
                    placeholder="Description du projet..."></textarea>
            </div>
            <div class="form-group">
                <label>Couleur</label>
                <input type="color" name="color" class="form-control" value="#6366f1"
                    style="height: 45px; padding: 5px;">
            </div>
            <button type="submit" class="btn btn-primary full-width">
                <i class="fas fa-plus"></i> Créer le projet
            </button>
        </form>
    </div>

    <!-- Projects List -->
    <div class="table-container">
        <div class="table-header">
            <h3 class="table-title">Liste des Projets</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Projet</th>
                    <th>Description</th>
                    <th>Tâches</th>
                    <th>Date création</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $p): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div
                                    style="width: 12px; height: 12px; border-radius: 4px; background: <?php echo htmlspecialchars($p['color']); ?>;">
                                </div>
                                <span style="font-weight: 500;"><?php echo htmlspecialchars($p['name']); ?></span>
                                <?php if ($p['name'] === 'ASTREE'): ?>
                                    <span class="badge"
                                        style="background: var(--primary-light); color: var(--primary); font-size: 0.65rem;">Défaut</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td
                            style="color: var(--text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo htmlspecialchars($p['description'] ?: '-'); ?>
                        </td>
                        <td>
                            <span class="badge" style="background: var(--primary-light); color: var(--primary);">
                                <?php echo $p['task_count']; ?> tâche(s)
                            </span>
                        </td>
                        <td style="color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($p['created_at'])); ?></td>
                        <td>
                            <?php if ($p['name'] !== 'ASTREE'): ?>
                                <form method="POST" style="display: inline;"
                                    onsubmit="return confirm('Supprimer ce projet ? Les tâches seront réassignées à ASTREE.');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="project_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.75rem;">Protégé</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>