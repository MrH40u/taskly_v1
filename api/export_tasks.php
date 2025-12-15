<?php
// api/export_tasks.php
require '../config/db.php';
require '../includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Get export parameters
$format = $_POST['format'] ?? 'csv';
$status_filter = $_POST['status'] ?? 'all';

// Build query
$params = [];
$sql = "SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, t.created_at, 
               u.username as assigned_user, p.name as project_name
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE 1=1";

if ($role !== 'admin') {
    $sql .= " AND t.assigned_to = ?";
    $params[] = $user_id;
}

if ($status_filter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for status/priority translation
function translateStatus($status)
{
    $statuses = ['todo' => 'À faire', 'in_progress' => 'En cours', 'review' => 'En revue', 'done' => 'Terminé'];
    return $statuses[$status] ?? $status;
}

function translatePriority($priority)
{
    $priorities = ['low' => 'Basse', 'medium' => 'Moyenne', 'high' => 'Haute'];
    return $priorities[$priority] ?? $priority;
}

// Generate filename
$filename = 'taches_export_' . date('Y-m-d_His');

if ($format === 'xlsx') {
    // Native Excel Export (HTML Table method - widely supported by Excel)
    $filename .= '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"
        xmlns="http://www.w3.org/TR/REC-html40">

    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Tâches</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            table {
                border-collapse: collapse;
                width: 100%;
            }

            th,
            td {
                border: 1px solid #000000;
                padding: 5px;
                text-align: left;
                vertical-align: top;
            }

            th {
                background-color: #6366F1;
                color: #FFFFFF;
                font-weight: bold;
            }
        </style>
    </head>

    <body>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Titre</th>
                    <th>Description</th>
                    <th>Priorité</th>
                    <th>Statut</th>
                    <th>Assigné à</th>
                    <th>Projet</th>
                    <th>Date limite</th>
                    <th>Date création</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td><?php echo $task['id']; ?></td>
                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                        <td><?php echo htmlspecialchars($task['description']); ?></td>
                        <td><?php echo translatePriority($task['priority']); ?></td>
                        <td><?php echo translateStatus($task['status']); ?></td>
                        <td><?php echo htmlspecialchars($task['assigned_user'] ?? 'Non assigné'); ?></td>
                        <td><?php echo htmlspecialchars($task['project_name'] ?? ''); ?></td>
                        <td><?php echo $task['due_date'] ?? '-'; ?></td>
                        <td><?php echo $task['created_at']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>

    </html>
    <?php
    exit;

} else {
    // CSV Export
    $filename .= '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    // Header
    fputcsv($output, ['ID', 'Titre', 'Description', 'Priorité', 'Statut', 'Assigné à', 'Projet', 'Date limite', 'Date création'], ';');

    // Rows
    foreach ($tasks as $task) {
        fputcsv($output, [
            $task['id'],
            $task['title'],
            $task['description'],
            translatePriority($task['priority']),
            translateStatus($task['status']),
            $task['assigned_user'] ?? 'Non assigné',
            $task['project_name'] ?? '',
            $task['due_date'] ?? '-',
            $task['created_at']
        ], ';');
    }

    fclose($output);
    exit;
}
