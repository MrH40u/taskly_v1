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
    // 1. Task Distribution by Status
    $statusSql = "SELECT status, COUNT(*) as count FROM tasks";
    if ($role !== 'admin') {
        $statusSql .= " WHERE assigned_to = ?";
    }
    $statusSql .= " GROUP BY status";
    
    $stmt = $pdo->prepare($statusSql);
    $stmt->execute($role !== 'admin' ? [$user_id] : []);
    $statusData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['todo' => 5, 'done' => 3]

    // Normalize keys
    $statuses = ['todo', 'in_progress', 'review', 'done'];
    $normalizedStatusData = [];
    foreach ($statuses as $s) {
        $normalizedStatusData[$s] = $statusData[$s] ?? 0;
    }

    // 2. Tasks per Project (Top 5)
    $projectSql = "SELECT p.name, COUNT(t.id) as count 
                   FROM projects p 
                   LEFT JOIN tasks t ON p.id = t.project_id";
    
    // For non-admins, filter tasks they are assigned to? Or show all project tasks?
    // Let's filter by assignment for non-admins to be consistent
    if ($role !== 'admin') {
        $projectSql .= " AND t.assigned_to = ?";
    }
    
    $projectSql .= " GROUP BY p.id ORDER BY count DESC LIMIT 5";
    
    $stmt = $pdo->prepare($projectSql);
    $stmt->execute($role !== 'admin' ? [$user_id] : []);
    $projectData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Task Priority Distribution
    $prioritySql = "SELECT priority, COUNT(*) as count FROM tasks";
    if ($role !== 'admin') {
        $prioritySql .= " WHERE assigned_to = ?";
    }
    $prioritySql .= " GROUP BY priority";
    $stmt = $pdo->prepare($prioritySql);
    $stmt->execute($role !== 'admin' ? [$user_id] : []);
    $priorityData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $normalizedPriorityData = [
        'low' => $priorityData['low'] ?? 0,
        'medium' => $priorityData['medium'] ?? 0,
        'high' => $priorityData['high'] ?? 0
    ];

    echo json_encode([
        'success' => true,
        'statusData' => $normalizedStatusData,
        'projectData' => $projectData,
        'priorityData' => $normalizedPriorityData
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
