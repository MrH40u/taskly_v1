<?php
// pages/notifications.php
require '../config/db.php';
require '../includes/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];

// Handle Mark as Read (AJAX or POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        header("Location: notifications.php");
        exit;
    }
    if ($_POST['action'] === 'mark_read' && isset($_POST['notif_id'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['notif_id'], $user_id]);
        // Ajax response would be better, but simple redirect for now if not ajax
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
             header("Location: notifications.php");
             exit;
        } else {
             echo json_encode(['success' => true]);
             exit;
        }
    }
}

// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="notifications-container">
    <div class="page-header">
        <h2>Vos Notifications</h2>
        <?php if (count($notifications) > 0): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-sm btn-ghost"> Tout marquer comme lu</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="notification-list">
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                    <div class="notification-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="notification-content">
                        <p class="message"><?php echo htmlspecialchars($notif['message']); ?></p>
                        <span class="time"><?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?></span>
                        <?php if ($notif['link']): ?>
                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="link">Voir</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$notif['is_read']): ?>
                        <div class="notification-actions">
                             <form method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notif_id" value="<?php echo $notif['id']; ?>">
                                <button type="submit" class="btn-icon" title="Marquer comme lu"><i class="fas fa-check"></i></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>Aucune notification pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .notifications-container {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .notification-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .notification-item {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        transition: all 0.2s;
    }

    .notification-item.unread {
        border-left: 4px solid var(--primary);
        background: rgba(99, 102, 241, 0.05);
    }

    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--bg-main);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        flex-shrink: 0;
    }

    .notification-content {
        flex: 1;
    }

    .message {
        margin: 0 0 0.25rem 0;
        color: var(--text-primary);
        font-size: 0.95rem;
    }

    .time {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .link {
        font-size: 0.8rem;
        color: var(--primary);
        margin-left: 0.5rem;
        font-weight: 500;
    }

    .notification-actions .btn-icon {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: 0.2s;
    }
    
    .notification-actions .btn-icon:hover {
        background: var(--bg-main);
        color: var(--success);
    }

    .empty-state {
        text-align: center;
        padding: 4rem 1rem;
        color: var(--text-muted);
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
</style>

<?php include '../includes/footer.php'; ?>
