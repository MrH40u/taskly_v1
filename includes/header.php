<?php
// includes/header.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taskly - Gestion de Tâches</title>
    <link rel="stylesheet" href="/taskly_v1/assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="/taskly_v1/assets/main.js" defer></script>
</head>

<body class="app-layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-tasks"></i>
            <span>Taskly</span>
        </div>

        <nav class="sidebar-nav">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/taskly_v1/pages/dashboard.php"
                    class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>


                <a href="/taskly_v1/pages/tasks.php"
                    class="nav-item <?php echo $current_page == 'tasks.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Gestion des Tâches</span>
                </a>

                <a href="/Taskly/pages/import_export.php"
                    class="nav-item <?php echo $current_page == 'import_export.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-export"></i>
                    <span>Import | Export</span>
                </a>

                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="/taskly_v1/pages/projects.php"
                        class="nav-item <?php echo $current_page == 'projects.php' ? 'active' : ''; ?>">
                        <i class="fas fa-folder"></i>
                        <span>Projets</span>
                    </a>
                    <a href="/taskly_v1/pages/users.php"
                        class="nav-item <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Utilisateurs</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <span class="version">v1.0</span>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <h1 class="page-title">
                    <?php
                    switch ($current_page) {
                        case 'dashboard.php':
                            echo 'Dashboard';
                            break;
                        case 'tasks.php':
                            echo 'Gestion des Tâches';
                            break;
                        case 'projects.php':
                            echo 'Projets';
                            break;
                        case 'create_task.php':
                            echo 'Nouvelle Tâche';
                            break;
                        case 'edit_task.php':
                            echo 'Détails Tâche';
                            break;
                        case 'users.php':
                            echo 'Utilisateurs';
                            break;
                        case 'gestion_fichiers.php':
                            echo 'Gestion des fichiers';
                            break;
                        default:
                            echo 'Taskly';
                    }
                    ?>
                </h1>
            </div>

            <div class="header-right">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Notifications -->
                    <?php
                    // Fetch unread notifications
                    $unreadCount = getUnreadNotificationCount($pdo, $_SESSION['user_id']);
                    ?>
                    <a href="/taskly_v1/pages/notifications.php" class="header-icon notification-icon"
                        style="text-decoration: none;">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="notification-badge"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- User Info -->
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <span
                                class="user-role"><?php echo $_SESSION['user_role'] === 'admin' ? 'Administrateur' : 'Développeur'; ?></span>
                        </div>
                    </div>

                    <!-- Logout Button -->
                    <a href="/taskly_v1/auth/logout.php" class="btn-logout" title="Déconnexion">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Page Content -->
        <main class="main-content">