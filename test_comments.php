<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting test...\n";

try {
    require 'config/db.php';
    echo "DB Config loaded.\n";
    
    require 'includes/functions.php';
    echo "Functions loaded.\n";
    
    require 'includes/classes/TaskRepository.php';
    echo "TaskRepository loaded.\n";

    $repo = new TaskRepository($pdo);
    echo "Repository instantiated.\n";

    // defined user for test
    $userId = 1; 
    $taskId = 1; // Assuming task 1 exists

    echo "Testing getComments...\n";
    $comments = $repo->getComments($taskId);
    print_r($comments);

} catch (Throwable $e) {
    echo "Caught Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
