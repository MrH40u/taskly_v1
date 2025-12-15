<?php
// update_schema.php - Run this once to add projects support
require 'config/db.php';

try {
    // Create Projects Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        color VARCHAR(7) DEFAULT '#6366f1',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Projects table created.<br>";

    // Add project_id to tasks if not exists
    $result = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'project_id'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN project_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE tasks ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL");
        echo "Added project_id column to tasks.<br>";
    } else {
        echo "project_id column already exists.<br>";
    }

    // Add completed_at column if not exists
    $result = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'completed_at'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN completed_at DATETIME DEFAULT NULL");
        echo "Added completed_at column to tasks.<br>";
    } else {
        echo "completed_at column already exists.<br>";
    }

    // Add duration column if not exists (in minutes)
    $result = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'duration'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN duration INT DEFAULT NULL");
        echo "Added duration column to tasks.<br>";
    } else {
        echo "duration column already exists.<br>";
    }

    // Insert default ASTREE project if not exists
    $stmt = $pdo->query("SELECT id FROM projects WHERE name = 'ASTREE'");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO projects (name, description, color) VALUES ('ASTREE', 'Projet par défaut', '#6366f1')");
        echo "Default project 'ASTREE' created.<br>";

        // Get ASTREE id and update existing tasks
        $stmt = $pdo->query("SELECT id FROM projects WHERE name = 'ASTREE'");
        $astree = $stmt->fetch();
        if ($astree) {
            $pdo->exec("UPDATE tasks SET project_id = {$astree['id']} WHERE project_id IS NULL");
            echo "Existing tasks assigned to ASTREE.<br>";
        }
    }

    // Fix tasks where status is 'done' but completed_at is NULL
    // Set their status to 'in_progress'
    $updated = $pdo->exec("UPDATE tasks SET status = 'in_progress' WHERE status = 'done' AND completed_at IS NULL");
    if ($updated > 0) {
        echo "Fixed {$updated} task(s) with status 'done' but no completion date (set to 'in_progress').<br>";
    }

    echo "<br>Schema update completed successfully!";

} catch (PDOException $e) {
    die("Schema update failed: " . $e->getMessage());
}
?>