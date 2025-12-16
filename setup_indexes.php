<?php
// setup_indexes.php
require 'config/db.php';

echo "<h2>Applying Database Indexes...</h2>";

function createIndex($pdo, $table, $indexName, $column) {
    echo "Creating index <strong>$indexName</strong> on <strong>$table($column)</strong>... ";
    try {
        // Check if index exists (MySQL specific)
        $check = $pdo->prepare("SHOW INDEX FROM $table WHERE Key_name = ?");
        $check->execute([$indexName]);
        
        if ($check->rowCount() > 0) {
            echo "<span style='color: orange;'>Skipped (Already exists)</span><br>";
        } else {
            $pdo->exec("CREATE INDEX $indexName ON $table($column)");
            echo "<span style='color: green;'>Success</span><br>";
        }
    } catch (PDOException $e) {
        echo "<span style='color: red;'>Failed: " . $e->getMessage() . "</span><br>";
    }
}

try {
    // Tasks Table Indexes
    createIndex($pdo, 'tasks', 'idx_tasks_assigned_to', 'assigned_to');
    createIndex($pdo, 'tasks', 'idx_tasks_project_id', 'project_id');
    createIndex($pdo, 'tasks', 'idx_tasks_status', 'status');
    createIndex($pdo, 'tasks', 'idx_tasks_created_at', 'created_at');
    createIndex($pdo, 'tasks', 'idx_tasks_priority', 'priority');

    // Users Table Indexes
    createIndex($pdo, 'users', 'idx_users_role', 'role');

    // Projects Table Indexes
    createIndex($pdo, 'projects', 'idx_projects_name', 'name');

    echo "<br><strong>Index setup completed!</strong>";

} catch (PDOException $e) {
    die("Global Error: " . $e->getMessage());
}
?>
