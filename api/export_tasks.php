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

// CSV Export (Safe for Excel, no warnings)
$ending = $format === 'csv' ? '.csv' : '.csv'; // Force .csv extension even if xlsx requested to ensure correct opening
$filename .= $ending;

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

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
