<?php
// setup_attachments.php
require 'config/db.php';

try {
    echo "<h2>Setting up Attachments...</h2>";

    // 1. Create Table
    $sql = "CREATE TABLE IF NOT EXISTS attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        uploaded_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "<span style='color: green;'>Success: Table 'attachments' created/checked.</span><br>";

    // 2. Create Uploads Directory
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) {
            echo "<span style='color: green;'>Success: Directory 'uploads' created.</span><br>";
        } else {
            echo "<span style='color: red;'>Failed: Could not create directory 'uploads'. Check permissions.</span><br>";
        }
    } else {
        echo "<span style='color: orange;'>Directory 'uploads' already exists.</span><br>";
    }

    echo "<br><strong>Setup completed!</strong>";

} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
