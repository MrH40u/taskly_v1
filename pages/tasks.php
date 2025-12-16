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

// Fetch tags for dropdown
$allTags = $pdo->query("SELECT * FROM tags ORDER BY name")->fetchAll();

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
        $task_id = $pdo->lastInsertId();

        // Handle File Upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/';
            $fileName = basename($_FILES['attachment']['name']);
            $targetPath = $uploadDir . time() . '_' . $fileName; // Unique name
            
            // Allow certain file types
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($fileExt, $allowedTypes)) {
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                    $stmt = $pdo->prepare("INSERT INTO attachments (task_id, file_path, filename, uploaded_by) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$task_id, $targetPath, $fileName, $user_id]);
                }
            }
        }
        
        // Handle Tags
        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
            $tagStmt = $pdo->prepare("INSERT INTO task_tags (task_id, tag_id) VALUES (?, ?)");
            foreach ($_POST['tags'] as $tagId) {
                $tagStmt->execute([$task_id, $tagId]);
            }
        }

        // Notify Assignee
        if ($assigned_to && $assigned_to != $user_id) {
            createNotification($pdo, $assigned_to, "On vous a assigné une nouvelle tâche : $title", "/taskly_v1/pages/tasks.php");
        }
        
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
    
    // Notify if Admin changed status for someone else
    if ($role === 'admin' && $task['assigned_to'] != $user_id) {
        createNotification($pdo, $task['assigned_to'], "Le statut de votre tâche a été mis à jour : " . $status, "/taskly_v1/pages/tasks.php");
    }

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

    // Fetch Attachments
    if ($task) {
        $stmt_att = $pdo->prepare("SELECT * FROM attachments WHERE task_id = ?");
        $stmt_att->execute([$task_id]);
        $task['attachments'] = $stmt_att->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Tags
        $stmt_tags = $pdo->prepare("SELECT tag_id FROM task_tags WHERE task_id = ?");
        $stmt_tags->execute([$task_id]);
        $task['tags'] = $stmt_tags->fetchAll(PDO::FETCH_COLUMN); // Returns array of IDs
    }

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
        $stmt->execute([$title, $description, $priority, $assigned_to, $due_date, $project_id, $task_id]);
        
        // Handle File Upload (Edit Mode)
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/';
            $fileName = basename($_FILES['attachment']['name']);
            $targetPath = $uploadDir . time() . '_' . $fileName; 
            
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($fileExt, $allowedTypes)) {
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                    $stmt = $pdo->prepare("INSERT INTO attachments (task_id, file_path, filename, uploaded_by) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$task_id, $targetPath, $fileName, $user_id]);
                }
            }
        }



        // Handle Tags (Sync: Delete all then re-insert)
        $pdo->prepare("DELETE FROM task_tags WHERE task_id = ?")->execute([$task_id]);
        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
            $tagStmt = $pdo->prepare("INSERT INTO task_tags (task_id, tag_id) VALUES (?, ?)");
            foreach ($_POST['tags'] as $tagId) {
                $tagStmt->execute([$task_id, $tagId]);
            }
        }

        // Notify Assignee (New or Old) - Simple approach: Notify the currently assigned person
        if ($assigned_to && $assigned_to != $user_id) {
            createNotification($pdo, $assigned_to, "Une de vos tâches a été modifiée : $title", "/taskly_v1/pages/tasks.php");
        }

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

// View Mode (List or Kanban)
$view = cleanInput($_GET['view'] ?? 'list');
if (!in_array($view, ['list', 'kanban'])) {
    $view = 'list';
}

// Pagination & Filtering
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
// Disable pagination for Kanban (show consistent board) or set high limit
$limit = ($view === 'kanban') ? 1000 : 10; 
$offset = ($page - 1) * $limit;

// Filter Parameters
$search = cleanInput($_GET['search'] ?? '');
$filter_status = cleanInput($_GET['status'] ?? '');
$filter_priority = cleanInput($_GET['priority'] ?? '');
$filter_project = cleanInput($_GET['project_id'] ?? '');

// Build Query Params and Where Clause
$params = [];
$whereClauses = [];

// Base Constraint (Role-based)
if ($role !== 'admin') {
    $whereClauses[] = "t.assigned_to = :current_user_id";
    $params[':current_user_id'] = $user_id;
}

// Search Filter
if (!empty($search)) {
    $whereClauses[] = "(t.title LIKE :search OR t.description LIKE :search)";
    $params[':search'] = "%$search%";
}

// Status Filter
if (!empty($filter_status) && in_array($filter_status, ['todo', 'in_progress', 'review', 'done'])) {
    $whereClauses[] = "t.status = :f_status";
    $params[':f_status'] = $filter_status;
}

// Priority Filter
if (!empty($filter_priority) && in_array($filter_priority, ['low', 'medium', 'high'])) {
    $whereClauses[] = "t.priority = :f_priority";
    $params[':f_priority'] = $filter_priority;
}

// Project Filter
if (!empty($filter_project)) {
    $whereClauses[] = "t.project_id = :f_project";
    $params[':f_project'] = $filter_project;
}

// Construct SQL Parts
$whereSQL = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// 1. Count Total (For Pagination)
$countSql = "SELECT COUNT(*) FROM tasks t $whereSQL";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_rows = $countStmt->fetchColumn();

// 2. Fetch Tasks
$sql = "SELECT t.*, u.username as assigned_user, p.name as project_name, p.color as project_color,
        (SELECT GROUP_CONCAT(tg.name, ':', tg.color SEPARATOR '|') 
         FROM task_tags tt 
         JOIN tags tg ON tt.tag_id = tg.id 
         WHERE tt.task_id = t.id) as task_tags_info
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        LEFT JOIN projects p ON t.project_id = p.id
        $whereSQL
        ORDER BY t.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$stmt->execute();
$tasks = $stmt->fetchAll();
$total_pages = ceil($total_rows / $limit);

// Prepare Kanban Data if needed
$kanbanData = [
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'done' => []
];

if ($view === 'kanban') {
    foreach ($tasks as $task) {
        if (isset($kanbanData[$task['status']])) {
            $kanbanData[$task['status']][] = $task;
        }
    }
}

// Build query string for pagination links
$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);
$pageLink = '?' . ($queryString ? $queryString . '&' : '') . 'page=';

$stmt->bindValue(':offset', $offset, PDO::PARAM_INT); // This line is actually removed by the edit above, but I need to clear the old bind calls
// No content needed here as it's replaced by the block above.
$db_dummy_var = 1; // Dummy line to ensure replacement happens cleanly if target matches


include '../includes/header.php';
?>


<!-- Global CSRF Token for JS -->
<?php echo csrfField(); ?>

<!-- Stats Section -->
<div class="stats-grid" style="grid-template-columns: repeat(5, 1fr);">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-tasks"></i></div>
        <div class="stat-info">
            <h3 id="stat-total"><?php echo $totalTasks; ?></h3>
            <p>Total</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(148,163,184,0.1); color: #94a3b8;"><i class="fas fa-circle"></i>
        </div>
        <div class="stat-info">
            <h3 id="stat-todo"><?php echo $todoTasks; ?></h3>
            <p>À Faire</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: #60a5fa;"><i class="fas fa-spinner"></i>
        </div>
        <div class="stat-info">
            <h3 id="stat-inprogress"><?php echo $inProgressTasks; ?></h3>
            <p>En Cours</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(139,92,246,0.1); color: #a78bfa;"><i class="fas fa-eye"></i>
        </div>
        <div class="stat-info">
            <h3 id="stat-review"><?php echo $reviewTasks; ?></h3>
            <p>En Revue</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3 id="stat-done"><?php echo $doneTasks; ?></h3>
            <p>Terminées</p>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="card mb-4" style="margin-bottom: 2rem; padding: 1.5rem;">
    <form method="GET" action="tasks.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 200px;">
            <label style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem; display: block;">Recherche</label>
            <div style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" name="search" class="form-control" style="padding-left: 2.5rem;" placeholder="Titre ou description..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>

        <div style="min-width: 150px;">
            <label style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem; display: block;">Statut</label>
            <select name="status" class="form-control">
                <option value="">Tous</option>
                <option value="todo" <?php echo $filter_status === 'todo' ? 'selected' : ''; ?>>À faire</option>
                <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>En cours</option>
                <option value="review" <?php echo $filter_status === 'review' ? 'selected' : ''; ?>>En revue</option>
                <option value="done" <?php echo $filter_status === 'done' ? 'selected' : ''; ?>>Terminé</option>
            </select>
        </div>

        <div style="min-width: 150px;">
            <label style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem; display: block;">Priorité</label>
            <select name="priority" class="form-control">
                <option value="">Toutes</option>
                <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Basse</option>
                <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Moyenne</option>
                <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>Haute</option>
            </select>
        </div>

        <div style="min-width: 150px;">
            <label style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem; display: block;">Projet</label>
            <select name="project_id" class="form-control">
                <option value="">Tous</option>
                <?php 
                // Need to re-fetch projects as previous fetch might be consumed or out of scope if not careful, 
                // but $projects is fetched at top of file lines 19-20, so it's safe.
                foreach ($projects as $p): 
                ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $filter_project == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" title="Filtrer">
                <i class="fas fa-filter"></i>
            </button>
            <a href="tasks.php" class="btn btn-ghost" title="Réinitialiser" style="border: 1px solid var(--border-color);">
                <i class="fas fa-undo"></i>
            </a>
        </div>
        
        <!-- View Toggles -->
        <div style="width: 100%; border-top: 1px solid var(--border-color); margin-top: 1rem; padding-top: 1rem; display: flex; justify-content: flex-end;">
            <div style="display: flex; background: var(--bg-main); padding: 4px; border-radius: 8px; border: 1px solid var(--border-color);">
                <button type="submit" name="view" value="list" class="btn btn-sm <?php echo $view === 'list' ? 'btn-primary' : 'btn-ghost'; ?>" style="border-radius: 6px;">
                    <i class="fas fa-list"></i> Liste
                </button>
                <button type="submit" name="view" value="kanban" class="btn btn-sm <?php echo $view === 'kanban' ? 'btn-primary' : 'btn-ghost'; ?>" style="border-radius: 6px;">
                    <i class="fas fa-columns"></i> Kanban
                </button>
            </div>
        </div>
    </form>
</div>

<?php if ($view === 'list'): ?>
<!-- LIST VIEW -->
<div class="table-container">
    <div class="table-header">
        <h3 class="table-title">Toutes les Tâches</h3>
        <?php if ($role === 'admin'): ?>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Ajouter une Tâche
            </button>
        <?php endif; ?>
    </div>

    <?php endif; ?>

    <?php if ($view === 'list'): ?>
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
                        <td style="font-weight: 500;">
                            <?php echo htmlspecialchars($task['title']); ?>
                            <?php if (!empty($task['task_tags_info'])): ?>
                                <div class="task-tags" style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 4px;">
                                    <?php 
                                    $tags = explode('|', $task['task_tags_info']);
                                    foreach ($tags as $tagStr):
                                        list($tagName, $tagColor) = explode(':', $tagStr);
                                    ?>
                                        <span class="badge" style="font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; background-color: <?php echo htmlspecialchars($tagColor); ?>; color: white;">
                                            <?php echo htmlspecialchars($tagName); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
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

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; padding: 1.5rem; border-top: 1px solid var(--border-color);">
            <?php if ($page > 1): ?>
                <a href="<?php echo $pageLink . ($page - 1); ?>&view=list" class="btn btn-sm btn-ghost"><i class="fas fa-chevron-left"></i> Précédent</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo $pageLink . $i; ?>&view=list" class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-ghost'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="<?php echo $pageLink . ($page + 1); ?>&view=list" class="btn btn-sm btn-ghost">Suivant <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- KANBAN VIEW -->
<div class="kanban-board">
    <!-- To Do Column -->
    <div class="kanban-column" data-status="todo" ondrop="drop(event)" ondragover="allowDrop(event)">
        <div class="kanban-column-header header-todo">
            <h3>À Faire <span class="count"><?php echo count($kanbanData['todo']); ?></span></h3>
        </div>
        <div class="kanban-tasks" id="col-todo">
            <?php renderKanbanCards($kanbanData['todo']); ?>
        </div>
    </div>

    <!-- In Progress Column -->
    <div class="kanban-column" data-status="in_progress" ondrop="drop(event)" ondragover="allowDrop(event)">
        <div class="kanban-column-header header-progress">
            <h3>En Cours <span class="count"><?php echo count($kanbanData['in_progress']); ?></span></h3>
        </div>
        <div class="kanban-tasks" id="col-in_progress">
            <?php renderKanbanCards($kanbanData['in_progress']); ?>
        </div>
    </div>

    <!-- Review Column -->
    <div class="kanban-column" data-status="review" ondrop="drop(event)" ondragover="allowDrop(event)">
        <div class="kanban-column-header header-review">
            <h3>En Revue <span class="count"><?php echo count($kanbanData['review']); ?></span></h3>
        </div>
        <div class="kanban-tasks" id="col-review">
            <?php renderKanbanCards($kanbanData['review']); ?>
        </div>
    </div>

    <!-- Done Column -->
    <div class="kanban-column" data-status="done" ondrop="drop(event)" ondragover="allowDrop(event)">
        <div class="kanban-column-header header-done">
            <h3>Terminé <span class="count"><?php echo count($kanbanData['done']); ?></span></h3>
        </div>
        <div class="kanban-tasks" id="col-done">
            <?php renderKanbanCards($kanbanData['done']); ?>
        </div>
    </div>
</div>

<!-- Include Kanban JS only in Kanban view -->
<script src="../assets/js/kanban.js"></script>
<?php endif; ?>

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
                    <label>Pièce jointe (Optionnel)</label>
                    <input type="file" name="attachment" class="form-control">
                    <small style="color: var(--text-muted);">Formats acceptés: PDF, DOCX, JPG, PNG, ZIP...</small>
                </div>

                <div class="form-group">
                    <label>Étiquettes</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php foreach ($allTags as $tag): ?>
                            <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.85rem; cursor: pointer; padding: 4px 8px; border: 1px solid var(--border-color); border-radius: 20px;">
                                <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>">
                                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: <?php echo htmlspecialchars($tag['color']); ?>;"></span>
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </label>
                        <?php endforeach; ?>
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
                    <label>Ajouter une pièce jointe</label>
                    <input type="file" name="attachment" class="form-control">
                    <small style="color: var(--text-muted);">Laissez vide pour conserver les fichiers existants.</small>
                </div>

                <div class="form-group">
                    <label>Étiquettes</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php foreach ($allTags as $tag): ?>
                            <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.85rem; cursor: pointer; padding: 4px 8px; border: 1px solid var(--border-color); border-radius: 20px;">
                                <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>" class="edit-tag-checkbox">
                                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: <?php echo htmlspecialchars($tag['color']); ?>;"></span>
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </label>
                        <?php endforeach; ?>
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

                <div style="margin-top: 1rem;">
                    <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Étiquettes</label>
                    <div id="view_tags_container" style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.25rem;"></div>
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

                </div>

                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.5rem;">Pièces jointes</label>
                    <div id="view_attachments"></div>
                </div>

                <div class="modal-footer" style="border-top: none; padding-top: 1.5rem;">
                    <button type="button" class="btn btn-ghost" onclick="closeViewModal()">Fermer</button>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<script src="../assets/js/tasks.js"></script>

<!-- Helper Function for Kanban Cards -->
<?php
function renderKanbanCards($tasks) {
    foreach ($tasks as $task) {
        $priorityClass = 'border-' . $task['priority'];
        ?>
        <div class="kanban-card <?php echo $priorityClass; ?>" 
             draggable="true" 
             ondragstart="drag(event)" 
             id="task-<?php echo $task['id']; ?>"
             data-task-id="<?php echo $task['id']; ?>"
        <div class="kanban-card <?php echo $priorityClass; ?>" 
             draggable="true" 
             ondragstart="drag(event)" 
             id="task-<?php echo $task['id']; ?>"
             data-task-id="<?php echo $task['id']; ?>"
             onclick="openViewModal(<?php echo $task['id']; ?>)">
            
            <div class="kanban-card-header">
                <span class="project-badge" style="background-color: <?php echo htmlspecialchars($task['project_color'] ?? '#ccc'); ?>">
                    <?php echo htmlspecialchars($task['project_name'] ?? 'Projet'); ?>
                </span>
                <?php if ($task['priority'] === 'high'): ?>
                    <i class="fas fa-exclamation-circle text-danger" title="Priorité Haute"></i>
                <?php endif; ?>
            </div>
            
            <h4 class="kanban-card-title"><?php echo htmlspecialchars($task['title']); ?></h4>
            
            <?php if (!empty($task['task_tags_info'])): ?>
                <div class="kanban-tags">
                <?php 
                    $tags = explode('|', $task['task_tags_info']);
                    foreach ($tags as $tagStr):
                        list($tagName, $tagColor) = explode(':', $tagStr);
                ?>
                    <span class="badge" style="background-color: <?php echo htmlspecialchars($tagColor); ?>; font-size: 0.65rem;">
                        <?php echo htmlspecialchars($tagName); ?>
                    </span>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="kanban-card-footer">
                <div class="assignee">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($task['assigned_user'] ?? 'Na'); ?>
                </div>
                <div class="date">
                    <?php echo $task['due_date'] ? date('d/m', strtotime($task['due_date'])) : ''; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
?>

<?php include '../includes/footer.php'; ?>