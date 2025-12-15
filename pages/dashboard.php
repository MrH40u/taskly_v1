<?php
// pages/dashboard.php
require '../config/db.php';
require '../includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Fetch stats
$totalTasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$todoTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'todo'")->fetchColumn();
$inProgressTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'in_progress'")->fetchColumn();
$doneTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'done'")->fetchColumn();

// Fetch tasks based on role
if ($role === 'admin') {
    $sql = "SELECT t.*, u.username as assigned_user 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            ORDER BY t.created_at DESC LIMIT 10";
    $stmt = $pdo->query($sql);
} else {
    $sql = "SELECT t.*, u.username as assigned_user 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            WHERE t.assigned_to = ? 
            ORDER BY t.created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
}
$tasks = $stmt->fetchAll();

include '../includes/header.php';
?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-tasks"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalTasks; ?></h3>
            <p>Total Tâches</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $todoTasks; ?></h3>
            <p>À Faire</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-spinner"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $inProgressTasks; ?></h3>
            <p>En Cours</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $doneTasks; ?></h3>
            <p>Terminées</p>
        </div>
    </div>
</div>

<!-- Tasks Table -->
<div class="table-container">
    <div class="table-header">
        <h3 class="table-title">Tâches Récentes</h3>
        <?php if ($role === 'admin'): ?>
            <a href="create_task.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nouvelle Tâche
            </a>
        <?php endif; ?>
    </div>

    <?php if (count($tasks) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Priorité</th>
                    <th>Statut</th>
                    <th>Assigné à</th>
                    <th>Date limite</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($task['title']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $task['priority']; ?>">
                                <?php echo ucfirst($task['priority']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-status-<?php echo $task['status']; ?>">
                                <?php
                                $statuses = ['todo' => 'À faire', 'in_progress' => 'En cours', 'review' => 'Revue', 'done' => 'Terminé'];
                                echo $statuses[$task['status']] ?? $task['status'];
                                ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted);">
                            <?php echo htmlspecialchars($task['assigned_user'] ?? 'Non assigné'); ?></td>
                        <td style="color: var(--text-muted);"><?php echo $task['due_date'] ?? '-'; ?></td>
                        <td>
                            <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-ghost">
                                <i class="fas fa-eye"></i> Voir
                            </a>
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

<?php include '../includes/footer.php'; ?>