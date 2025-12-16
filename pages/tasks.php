<?php
// pages/tasks.php
require '../config/db.php';
require '../includes/functions.php';
require '../includes/csrf.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Fetch developers for assignment dropdown
$developers = [];
if ($role === 'admin') {
    $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'dev'");
    $developers = $stmt->fetchAll();
}

// Fetch projects for dropdown
$projects = $pdo->query("SELECT id, name, color FROM projects ORDER BY name")->fetchAll();

// Get default project (ASTREE)
$defaultProject = $pdo->query("SELECT id FROM projects WHERE name = 'ASTREE'")->fetch();
$defaultProjectId = $defaultProject ? $defaultProject['id'] : null;

// Handle Add Task (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task') {
    header('Content-Type: application/json');

    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
        exit;
    }

    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }

    $title = cleanInput($_POST['title']);
    $description = cleanInput($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $assigned_to = $_POST['assigned_to'] ?: null;
    $due_date = $_POST['due_date'] ?: null;
    $project_id = $_POST['project_id'] ?: $defaultProjectId; // Default to ASTREE

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Le titre est requis']);
        exit;
    }

    try {
        $sql = "INSERT INTO tasks (title, description, priority, assigned_to, created_by, due_date, project_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $priority, $assigned_to, $user_id, $due_date, $project_id]);
        echo json_encode(['success' => true, 'message' => 'Tâche créée avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

// Handle Update Status (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');

    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
        exit;
    }

    $task_id = $_POST['task_id'];
    $status = $_POST['status'];

    // Check permission
    $stmt = $pdo->prepare("SELECT assigned_to FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task || ($role !== 'admin' && $task['assigned_to'] != $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
    $stmt->execute([$status, $task_id]);
    echo json_encode(['success' => true]);
    exit;
}

// Handle Get Task (AJAX) - for edit/view modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_task') {
    header('Content-Type: application/json');

    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
        exit;
    }

    $task_id = $_POST['task_id'];
    $stmt = $pdo->prepare("
        SELECT t.*, p.name as project_name, u.username as assigned_user 
        FROM tasks t 
        LEFT JOIN projects p ON t.project_id = p.id 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($task) {
        echo json_encode(['success' => true, 'task' => $task]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tâche introuvable']);
    }
    exit;
}

// Handle Update Task (AJAX) - for edit modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_task') {
    header('Content-Type: application/json');

    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
        exit;
    }

    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }

    $task_id = $_POST['task_id'];

    // Check if task is completed
    $stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if ($task && $task['status'] === 'done') {
        echo json_encode(['success' => false, 'message' => 'Tâche terminée, modification impossible']);
        exit;
    }

    $title = cleanInput($_POST['title']);
    $description = cleanInput($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $assigned_to = $_POST['assigned_to'] ?: null;
    $due_date = $_POST['due_date'] ?: null;
    $project_id = $_POST['project_id'];

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Le titre est requis']);
        exit;
    }

    try {
        $sql = "UPDATE tasks SET title=?, description=?, priority=?, assigned_to=?, due_date=?, project_id=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $priority, $assigned_to, $due_date, $project_id, $task_id]);
        echo json_encode(['success' => true, 'message' => 'Tâche mise à jour']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

// Handle Delete Task (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_task') {
    header('Content-Type: application/json');

    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
        exit;
    }

    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }

    $task_id = $_POST['task_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        echo json_encode(['success' => true, 'message' => 'Tâche supprimée']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

// Handle Advance Status (AJAX) - Cycles: todo -> in_progress -> review -> done
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'advance_status') {
    header('Content-Type: application/json');

    // Valider le token CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
        exit;
    }

    $task_id = $_POST['task_id'];

    // Check permission
    $stmt = $pdo->prepare("SELECT assigned_to, status, created_at FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task || ($role !== 'admin' && $task['assigned_to'] != $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }

    if ($task['status'] === 'done') {
        echo json_encode(['success' => false, 'message' => 'Tâche déjà terminée']);
        exit;
    }

    // Define status progression
    $statusFlow = [
        'todo' => 'in_progress',
        'in_progress' => 'review',
        'review' => 'done'
    ];

    $currentStatus = $task['status'];
    $nextStatus = $statusFlow[$currentStatus] ?? 'done';

    try {
        if ($nextStatus === 'done') {
            // Final status: set completed_at and calculate duration
            $sql = "UPDATE tasks SET 
                        status = 'done', 
                        completed_at = NOW(), 
                        duration = TIMESTAMPDIFF(MINUTE, created_at, NOW()) 
                    WHERE id = ?";
        } else {
            // Intermediate status: just update status
            $sql = "UPDATE tasks SET status = ? WHERE id = ?";
        }

        $stmt = $pdo->prepare($sql);
        if ($nextStatus === 'done') {
            $stmt->execute([$task_id]);
        } else {
            $stmt->execute([$nextStatus, $task_id]);
        }

        $statusLabels = [
            'in_progress' => 'En cours',
            'review' => 'En revue',
            'done' => 'Terminé'
        ];

        echo json_encode([
            'success' => true,
            'message' => 'Statut: ' . $statusLabels[$nextStatus],
            'newStatus' => $nextStatus
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch stats
$totalTasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$todoTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'todo'")->fetchColumn();
$inProgressTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'in_progress'")->fetchColumn();
$reviewTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'review'")->fetchColumn();
$doneTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'done'")->fetchColumn();

// Fetch tasks with project info
if ($role === 'admin') {
    $sql = "SELECT t.*, u.username as assigned_user, p.name as project_name, p.color as project_color
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            LEFT JOIN projects p ON t.project_id = p.id
            ORDER BY t.created_at DESC";
    $stmt = $pdo->query($sql);
} else {
    $sql = "SELECT t.*, u.username as assigned_user, p.name as project_name, p.color as project_color
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE t.assigned_to = ? 
            ORDER BY t.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
}
$tasks = $stmt->fetchAll();

include '../includes/header.php';
?>

<!-- Stats Section -->
<div class="stats-grid" style="grid-template-columns: repeat(5, 1fr);">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-tasks"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalTasks; ?></h3>
            <p>Total</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(148,163,184,0.1); color: #94a3b8;"><i class="fas fa-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $todoTasks; ?></h3>
            <p>À Faire</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: #60a5fa;"><i class="fas fa-spinner"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $inProgressTasks; ?></h3>
            <p>En Cours</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(139,92,246,0.1); color: #a78bfa;"><i class="fas fa-eye"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $reviewTasks; ?></h3>
            <p>En Revue</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3><?php echo $doneTasks; ?></h3>
            <p>Terminées</p>
        </div>
    </div>
</div>

<!-- Tasks Section -->
<div class="table-container">
    <div class="table-header">
        <h3 class="table-title">Toutes les Tâches</h3>
        <?php if ($role === 'admin'): ?>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Ajouter une Tâche
            </button>
        <?php endif; ?>
    </div>

    <?php if (count($tasks) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Projet</th>
                    <th>Priorité</th>
                    <th>Statut</th>
                    <th>Durée</th>
                    <th>Assigné à</th>
                    <th>Date limite</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($task['title']); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div
                                    style="width: 10px; height: 10px; border-radius: 3px; background: <?php echo htmlspecialchars($task['project_color'] ?? '#6366f1'); ?>;">
                                </div>
                                <span
                                    style="color: var(--text-secondary);"><?php echo htmlspecialchars($task['project_name'] ?? 'ASTREE'); ?></span>
                            </div>
                        </td>
                        <td><span
                                class="badge badge-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span>
                        </td>
                        <td>
                            <?php
                            $statusLabels = [
                                'todo' => 'À faire',
                                'in_progress' => 'En cours',
                                'review' => 'En revue',
                                'done' => 'Terminé'
                            ];
                            ?>
                            <span class="badge badge-status-<?php echo $task['status']; ?>">
                                <?php echo $statusLabels[$task['status']] ?? $task['status']; ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted);">
                            <?php if ($task['status'] === 'done' && $task['duration']): ?>
                                <?php
                                $mins = $task['duration'];
                                if ($mins < 60) {
                                    echo $mins . ' min';
                                } elseif ($mins < 1440) {
                                    $hours = floor($mins / 60);
                                    $remaining = $mins % 60;
                                    echo $hours . 'h ' . ($remaining > 0 ? $remaining . 'min' : '');
                                } else {
                                    $days = floor($mins / 1440);
                                    $hours = floor(($mins % 1440) / 60);
                                    echo $days . 'j ' . ($hours > 0 ? $hours . 'h' : '');
                                }
                                ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-muted);">
                            <?php echo htmlspecialchars($task['assigned_user'] ?? 'Non assigné'); ?>
                        </td>
                        <td style="color: var(--text-muted);"><?php echo $task['due_date'] ?? '-'; ?></td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <?php if ($task['status'] !== 'done'):
                                    // Button styles based on current status
                                    $btnStyles = [
                                        'todo' => 'background: rgba(148,163,184,0.15); color: #94a3b8;',
                                        'in_progress' => 'background: rgba(59,130,246,0.15); color: #60a5fa;',
                                        'review' => 'background: rgba(139,92,246,0.15); color: #a78bfa;'
                                    ];
                                    $btnIcons = [
                                        'todo' => 'fa-play',
                                        'in_progress' => 'fa-eye',
                                        'review' => 'fa-check'
                                    ];
                                    $btnTitles = [
                                        'todo' => 'Démarrer (En cours)',
                                        'in_progress' => 'Mettre en revue',
                                        'review' => 'Terminer'
                                    ];
                                    $style = $btnStyles[$task['status']] ?? '';
                                    $icon = $btnIcons[$task['status']] ?? 'fa-check';
                                    $title = $btnTitles[$task['status']] ?? 'Avancer';
                                    ?>
                                    <button class="btn btn-sm"
                                        style="<?php echo $style; ?> border: none; border-radius: 8px; padding: 0.5rem 0.75rem;"
                                        onclick="advanceStatus(<?php echo $task['id']; ?>)" title="<?php echo $title; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($role === 'admin' && $task['status'] !== 'done'): ?>
                                    <button class="btn btn-ghost btn-sm" onclick="openEditModal(<?php echo $task['id']; ?>)"
                                        title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-ghost btn-sm" style="color: var(--info);"
                                    onclick="openViewModal(<?php echo $task['id']; ?>)" title="Voir les détails">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($role === 'admin'): ?>
                                    <button class="btn btn-ghost btn-sm" style="color: var(--danger);"
                                        onclick="deleteTask(<?php echo $task['id']; ?>, '<?php echo addslashes(htmlspecialchars($task['title'])); ?>')"
                                        title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>Aucune tâche trouvée.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Add Task Modal -->
<?php if ($role === 'admin'): ?>
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajouter une Tâche</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="addTaskForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add_task">

                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" class="form-control" placeholder="Titre de la tâche" required>
                </div>

                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Description détaillée..."
                        required></textarea>
                </div>

                <div class="form-group">
                    <label>Projet *</label>
                    <select name="project_id" class="form-control" required>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $p['name'] == 'ASTREE' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Priorité *</label>
                        <select name="priority" class="form-control" required>
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
                    <label>Assigner à *</label>
                    <select name="assigned_to" class="form-control" required>
                        <option value="">-- Sélectionner un développeur --</option>
                        <?php foreach ($developers as $dev): ?>
                            <option value="<?php echo $dev['id']; ?>"><?php echo htmlspecialchars($dev['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div id="editTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modifier la Tâche</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editTaskForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update_task">
                <input type="hidden" name="task_id" id="edit_task_id">

                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3" required></textarea>
                </div>

                <div class="form-group">
                    <label>Projet *</label>
                    <select name="project_id" id="edit_project_id" class="form-control" required>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Priorité *</label>
                        <select name="priority" id="edit_priority" class="form-control" required>
                            <option value="low">Basse</option>
                            <option value="medium">Moyenne</option>
                            <option value="high">Haute</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date limite</label>
                        <input type="date" name="due_date" id="edit_due_date" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>Assigner à *</label>
                    <select name="assigned_to" id="edit_assigned_to" class="form-control" required>
                        <option value="">-- Sélectionner un développeur --</option>
                        <?php foreach ($developers as $dev): ?>
                            <option value="<?php echo $dev['id']; ?>"><?php echo htmlspecialchars($dev['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Task Modal -->
    <div id="viewTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Détails de la Tâche</h3>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1.5rem;">
                    <h2 id="view_title" style="margin-bottom: 0.5rem; font-size: 1.25rem;"></h2>
                    <span id="view_status" class="badge"></span>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label
                        style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Description</label>
                    <p id="view_description" style="color: var(--text-secondary); margin-top: 0.25rem;"></p>
                </div>

                <div
                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <div>
                        <label
                            style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Projet</label>
                        <p id="view_project" style="font-weight: 500; margin-top: 0.25rem;"></p>
                    </div>
                    <div>
                        <label
                            style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Priorité</label>
                        <p id="view_priority_container" style="margin-top: 0.25rem;"></p>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Assigné
                            à</label>
                        <p id="view_assigned" style="font-weight: 500; margin-top: 0.25rem;"></p>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Date
                            limite</label>
                        <p id="view_due_date" style="font-weight: 500; margin-top: 0.25rem;"></p>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Créée
                            le</label>
                        <p id="view_created_at" style="font-weight: 500; margin-top: 0.25rem;"></p>
                    </div>
                    <div>
                        <label
                            style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Durée</label>
                        <p id="view_duration" style="font-weight: 500; margin-top: 0.25rem;"></p>
                    </div>
                </div>

                <div class="modal-footer" style="border-top: none; padding-top: 1.5rem;">
                    <button type="button" class="btn btn-ghost" onclick="closeViewModal()">Fermer</button>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<script src="../assets/js/tasks.js"></script>

<?php include '../includes/footer.php'; ?>