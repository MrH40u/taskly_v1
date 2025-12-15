<?php
// setup_taskly.php
require 'config/db.php';

try {
    // Create Database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS taskly_db");
    $pdo->exec("USE taskly_db");

    echo "Database 'taskly_db' checked.<br>";

    // Users Table
    // Roles: admin, dev
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'dev') NOT NULL DEFAULT 'dev',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Users table created.<br>";

    // Tasks Table
    // Status: todo, in_progress, review, done
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        description TEXT,
        status ENUM('todo', 'in_progress', 'review', 'done') DEFAULT 'todo',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        assigned_to INT,
        created_by INT,
        due_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "Tasks table created.<br>";

    // Seed Admin User
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@taskly.com', $password, 'admin']);
        echo "Default Admin created: admin / admin123<br>";
    }

    // Seed Dev User
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'dev'");
    if ($stmt->fetchColumn() == 0) {
        $password = password_hash('dev123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['dev', 'dev@taskly.com', $password, 'dev']);
        echo "Default Dev created: dev / dev123<br>";
    }

    echo "Setup completed successfully!";

} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>