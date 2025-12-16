<?php
// api/stats.php
require '../config/db.php';
require '../includes/functions.php';

header('Content-Type: application/json');

// Ensure user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

try {
    require '../includes/classes/TaskRepository.php';
    $repo = new TaskRepository($pdo);
    
    $stats = $repo->getChartStats($user_id, $role);

    echo json_encode([
        'success' => true,
        'statusData' => $stats['statusData'],
        'projectData' => $stats['projectData'],
        'priorityData' => $stats['priorityData']
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
