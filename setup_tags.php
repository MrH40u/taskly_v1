<?php
// setup_tags.php
require 'config/db.php';

try {
    echo "<h2>Setting up Tags...</h2>";

    // 1. Create Tags Table
    $sql = "CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        color VARCHAR(20) NOT NULL DEFAULT 'gray'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
    echo "<span style='color: green;'>Success: Table 'tags' created/checked.</span><br>";

    // 2. Create Task Queries Table (Many-to-Many)
    $sql = "CREATE TABLE IF NOT EXISTS task_tags (
        task_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (task_id, tag_id),
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
    echo "<span style='color: green;'>Success: Table 'task_tags' created/checked.</span><br>";

    // 3. Seed Default Tags
    $defaultTags = [
        ['name' => 'Bug', 'color' => 'red'],
        ['name' => 'Feature', 'color' => 'blue'],
        ['name' => 'Enhancement', 'color' => 'green'],
        ['name' => 'Urgent', 'color' => 'orange'],
        ['name' => 'Documentation', 'color' => 'purple'],
        ['name' => 'Design', 'color' => 'pink'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO tags (name, color) VALUES (?, ?)");
    foreach ($defaultTags as $tag) {
        $stmt->execute([$tag['name'], $tag['color']]);
    }
    echo "<span style='color: green;'>Success: Default tags seeded.</span><br>";

    echo "<br><strong>Setup completed!</strong>";

} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
