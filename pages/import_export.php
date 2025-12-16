<?php
// pages/import_export.php
require '../config/db.php';
require '../includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Fetch count of tasks for display
if ($role === 'admin') {
    $totalTasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
    $stmt->execute([$user_id]);
    $totalTasks = $stmt->fetchColumn();
}

include '../includes/header.php';
?>

<div class="import-export-container">
    <!-- Export Section -->
    <div class="export-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-file-export"></i>
            </div>
            <div class="section-info">
                <h2>Exporter les Tâches</h2>
                <p>Téléchargez vos tâches au format Excel (.xlsx)</p>
            </div>
        </div>

        <div class="export-stats">
            <div class="stat-item">
                <span class="stat-number"><?php echo $totalTasks; ?></span>
                <span class="stat-label">Tâches à exporter</span>
            </div>
        </div>

        <div class="export-options">
            <h3>Options d'export</h3>
            <form id="exportForm" method="POST" action="/taskly_v1/api/export_tasks.php">
                <div class="form-group">
                    <label for="export_format">Format</label>
                    <select name="format" id="export_format" class="form-control">
                        <option value="csv">Excel / CSV (Compatible)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="export_status">Filtrer par statut</label>
                    <select name="status" id="export_status" class="form-control">
                        <option value="all">Tous les statuts</option>
                        <option value="todo">À faire</option>
                        <option value="in_progress">En cours</option>
                        <option value="review">En revue</option>
                        <option value="done">Terminé</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-download"></i> Exporter maintenant
                </button>
            </form>
        </div>
    </div>

    <!-- Import Section (Coming Soon) -->
    <div class="import-section">
        <div class="section-header">
            <div class="section-icon disabled">
                <i class="fas fa-file-import"></i>
            </div>
            <div class="section-info">
                <h2>Importer des Tâches</h2>
                <p>Importez des tâches depuis un fichier Excel</p>
            </div>
        </div>

        <div class="coming-soon">
            <i class="fas fa-clock"></i>
            <span>Fonctionnalité à venir</span>
        </div>
    </div>
</div>

<style>
    .import-export-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 2rem;
        padding: 1rem 0;
    }

    .export-section,
    .import-section {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 2rem;
        border: 1px solid var(--border-color);
    }

    .section-header {
        display: flex;
        align-items: flex-start;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .section-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .section-icon i {
        font-size: 1.5rem;
        color: white;
    }

    .section-icon.disabled {
        background: var(--bg-secondary);
        opacity: 0.5;
    }

    .section-icon.disabled i {
        color: var(--text-muted);
    }

    .section-info h2 {
        margin: 0 0 0.5rem 0;
        font-size: 1.5rem;
        color: var(--text-primary);
    }

    .section-info p {
        margin: 0;
        color: var(--text-muted);
        font-size: 0.95rem;
    }

    .export-stats {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        text-align: center;
    }

    .stat-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary-color);
    }

    .stat-label {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .export-options h3 {
        font-size: 1.1rem;
        color: var(--text-primary);
        margin-bottom: 1.5rem;
    }

    .export-options .form-group {
        margin-bottom: 1.5rem;
    }

    .export-options label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .export-options .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--bg-secondary);
        color: var(--text-primary);
        font-size: 1rem;
    }

    .export-options .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .btn-lg {
        width: 100%;
        padding: 1rem 2rem;
        font-size: 1.1rem;
        gap: 0.75rem;
    }

    .coming-soon {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        background: var(--bg-secondary);
        border-radius: 12px;
        color: var(--text-muted);
        gap: 1rem;
    }

    .coming-soon i {
        font-size: 2.5rem;
        opacity: 0.5;
    }

    .coming-soon span {
        font-size: 1rem;
        font-weight: 500;
    }

    @media (max-width: 768px) {
        .import-export-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>