<?php
// pages/dashboard.php
require '../config/db.php';
require '../includes/functions.php';
require '../includes/classes/TaskRepository.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

$repo = new TaskRepository($pdo);

// Fetch stats
$stats = $repo->getStats();
$totalTasks = $stats['total'];
$todoTasks = $stats['todo'];
$inProgressTasks = $stats['in_progress'];
$doneTasks = $stats['done'];

// Fetch recent tasks (Top 10)
$filters = ['role' => $role, 'user_id' => $user_id];
$recentTasksData = $repo->getAllTasks($filters, 10, 0);
$tasks = $recentTasksData['tasks'];


// Include Chart.js
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
echo '<script src="../assets/js/dashboard_charts.js" defer></script>';

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

<!-- Charts Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Status Distribution -->
    <div class="card" style="padding: 1.5rem; height: 320px;">
        <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--text-secondary);">Répartition par Statut</h3>
        <div style="position: relative; height: 250px;">
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <!-- Project Breakdown -->
    <div class="card" style="padding: 1.5rem; height: 320px;">
        <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--text-secondary);">Tâches par Projet (Top 5)</h3>
        <div style="position: relative; height: 250px;">
            <canvas id="projectChart"></canvas>
        </div>
    </div>

    <!-- Priority Breakdown -->
    <div class="card" style="padding: 1.5rem; height: 320px;">
        <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--text-secondary);">Répartition par Priorité</h3>
        <div style="position: relative; height: 250px;">
            <canvas id="priorityChart"></canvas>
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