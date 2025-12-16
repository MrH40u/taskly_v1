<?php

class TaskRepository {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getTaskById($id) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, p.name as project_name, p.color as project_color, u.username as assigned_user 
            FROM tasks t 
            LEFT JOIN projects p ON t.project_id = p.id 
            LEFT JOIN users u ON t.assigned_to = u.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($task) {
            // Attachments
            $stmt_att = $this->pdo->prepare("SELECT * FROM attachments WHERE task_id = ?");
            $stmt_att->execute([$id]);
            $task['attachments'] = $stmt_att->fetchAll(PDO::FETCH_ASSOC);

            // Tags (IDs)
            $stmt_tags = $this->pdo->prepare("SELECT tag_id FROM task_tags WHERE task_id = ?");
            $stmt_tags->execute([$id]);
            $task['tags'] = $stmt_tags->fetchAll(PDO::FETCH_COLUMN);

            // Tags (Info string for display if needed similar to list)
            $stmt_tags_info = $this->pdo->prepare("
                SELECT GROUP_CONCAT(tg.name, ':', tg.color SEPARATOR '|') 
                FROM task_tags tt 
                JOIN tags tg ON tt.tag_id = tg.id 
                WHERE tt.task_id = ?
            ");
            $stmt_tags_info->execute([$id]);
            $task['task_tags_info'] = $stmt_tags_info->fetchColumn();
        }

        return $task;
    }

    public function getAllTasks($filters = [], $limit = 10, $offset = 0) {
        $params = [];
        $whereClauses = [];

        // Role/User constraint
        if (isset($filters['user_id']) && isset($filters['role']) && $filters['role'] !== 'admin') {
            $whereClauses[] = "t.assigned_to = :current_user_id";
            $params[':current_user_id'] = $filters['user_id'];
        }

        // Search
        if (!empty($filters['search'])) {
            $whereClauses[] = "(t.title LIKE :search OR t.description LIKE :search)";
            $params[':search'] = "%" . $filters['search'] . "%";
        }

        // Status
        if (!empty($filters['status']) && in_array($filters['status'], ['todo', 'in_progress', 'review', 'done'])) {
            $whereClauses[] = "t.status = :f_status";
            $params[':f_status'] = $filters['status'];
        }

        // Priority
        if (!empty($filters['priority'])) {
            $whereClauses[] = "t.priority = :f_priority";
            $params[':f_priority'] = $filters['priority'];
        }

        // Project
        if (!empty($filters['project_id'])) {
            $whereClauses[] = "t.project_id = :f_project";
            $params[':f_project'] = $filters['project_id'];
        }

        $whereSQL = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        // Count Total
        $countSql = "SELECT COUNT(*) FROM tasks t $whereSQL";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $total_rows = $countStmt->fetchColumn();

        // Fetch Tasks
        $sql = "SELECT t.*, u.username as assigned_user, p.name as project_name, p.color as project_color,
                (SELECT GROUP_CONCAT(tg.name, ':', tg.color SEPARATOR '|') 
                 FROM task_tags tt 
                 JOIN tags tg ON tt.tag_id = tg.id 
                 WHERE tt.task_id = t.id) as task_tags_info
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_to = u.id 
                LEFT JOIN projects p ON t.project_id = p.id
                $whereSQL
                ORDER BY t.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'tasks' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total_rows
        ];
    }

    public function createTask($data) {
        $sql = "INSERT INTO tasks (title, description, project_id, assigned_to, priority, due_date, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, 'todo', ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['project_id'],
            $data['assigned_to'],
            $data['priority'],
            $data['due_date'],
            $data['created_by']
        ]);
        return $this->pdo->lastInsertId();
    }

    public function updateTask($id, $data) {
        $sql = "UPDATE tasks SET title=?, description=?, priority=?, assigned_to=?, due_date=?, project_id=? WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['title'],
            $data['description'],
            $data['priority'],
            $data['assigned_to'],
            $data['due_date'],
            $data['project_id'],
            $id
        ]);
    }

    public function updateStatus($id, $status) {
        // Handle completion logic if done
        if ($status === 'done') {
            $sql = "UPDATE tasks SET status = 'done', completed_at = NOW(), duration = TIMESTAMPDIFF(MINUTE, created_at, NOW()) WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } else {
            $sql = "UPDATE tasks SET status = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$status, $id]);
        }
    }

    public function deleteTask($id) {
        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function addAttachment($taskId, $filePath, $fileName, $userId) {
        $stmt = $this->pdo->prepare("INSERT INTO attachments (task_id, file_path, filename, uploaded_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$taskId, $filePath, $fileName, $userId]);
        return true;
    }

    public function updateTags($taskId, $tagIds) {
        $this->pdo->prepare("DELETE FROM task_tags WHERE task_id = ?")->execute([$taskId]);
        if (!empty($tagIds) && is_array($tagIds)) {
            $tagStmt = $this->pdo->prepare("INSERT INTO task_tags (task_id, tag_id) VALUES (?, ?)");
            foreach ($tagIds as $tagId) {
                $tagStmt->execute([$taskId, $tagId]);
            }
        }
    }

    public function getStats() {
        return [
            'total' => $this->pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn(),
            'todo' => $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'todo'")->fetchColumn(),
            'in_progress' => $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'in_progress'")->fetchColumn(),
            'review' => $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'review'")->fetchColumn(),
            'done' => $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'done'")->fetchColumn(),
        ];
    }

    public function getChartStats($userId, $role) {
        // 1. Task Distribution by Status
        $statusSql = "SELECT status, COUNT(*) as count FROM tasks";
        if ($role !== 'admin') {
            $statusSql .= " WHERE assigned_to = ?";
        }
        $statusSql .= " GROUP BY status";
        
        $stmt = $this->pdo->prepare($statusSql);
        $stmt->execute($role !== 'admin' ? [$userId] : []);
        $statusData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $statuses = ['todo', 'in_progress', 'review', 'done'];
        $normalizedStatusData = [];
        foreach ($statuses as $s) {
            $normalizedStatusData[$s] = $statusData[$s] ?? 0;
        }

        // 2. Tasks per Project (Top 5)
        $projectSql = "SELECT p.name, COUNT(t.id) as count 
                       FROM projects p 
                       LEFT JOIN tasks t ON p.id = t.project_id";
        
        if ($role !== 'admin') {
            $projectSql .= " AND t.assigned_to = ?";
        }
        
        $projectSql .= " GROUP BY p.id ORDER BY count DESC LIMIT 5";
        
        $stmt = $this->pdo->prepare($projectSql);
        $stmt->execute($role !== 'admin' ? [$userId] : []);
        $projectData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Task Priority Distribution
        $prioritySql = "SELECT priority, COUNT(*) as count FROM tasks";
        if ($role !== 'admin') {
            $prioritySql .= " WHERE assigned_to = ?";
        }
        $prioritySql .= " GROUP BY priority";
        $stmt = $this->pdo->prepare($prioritySql);
        $stmt->execute($role !== 'admin' ? [$userId] : []);
        $priorityData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $normalizedPriorityData = [
            'low' => $priorityData['low'] ?? 0,
            'medium' => $priorityData['medium'] ?? 0,
            'high' => $priorityData['high'] ?? 0
        ];

        return [
            'statusData' => $normalizedStatusData,
            'projectData' => $projectData,
            'priorityData' => $normalizedPriorityData
        ];
    }

    // ===========================
    // COMMENTS
    // ===========================

    public function addComment($taskId, $userId, $comment) {
        $stmt = $this->pdo->prepare("INSERT INTO comments (task_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$taskId, $userId, $comment]);
        return $this->pdo->lastInsertId();
    }

    public function getComments($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.task_id = ? 
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteComment($commentId, $userId, $isAdmin = false) {
        if ($isAdmin) {
            $stmt = $this->pdo->prepare("DELETE FROM comments WHERE id = ?");
            return $stmt->execute([$commentId]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
            return $stmt->execute([$commentId, $userId]);
        }
    }
}
