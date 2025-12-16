<?php
// setup_notifications.php
require 'config/db.php';

try {
    echo "<h2>Setting up Notifications Table...</h2>";

    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(255),
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "<span style='color: green;'>Success: Table 'notifications' created/checked.</span><br>";

    // Add index on user_id and is_read for performance
    try {
        $pdo->exec("CREATE INDEX idx_notif_user_read ON notifications(user_id, is_read)");
        echo "<span style='color: green;'>Success: Index added.</span><br>";
    } catch (PDOException $e) {
        // Index might already exist
        echo "<span style='color: orange;'>Index might already exist.</span><br>";
    }

    echo "<br><strong>Setup completed!</strong>";

} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
