<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

// Mark notification as read
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id = ? AND recipient_id = ?");
    $stmt->execute([$_POST['notification_id'], $_SESSION['user_id']]);
    header('Location: notifications.php');
    exit;
}

// Mark all notifications as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE recipient_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    header('Location: notifications.php');
    exit;
}

// Pagination settings
$notifications_per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $notifications_per_page;

// Get total number of notifications
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM notifications 
    WHERE recipient_id = ?
");
$count_stmt->execute([$_SESSION['user_id']]);
$total_notifications = $count_stmt->fetchColumn();
$total_pages = ceil($total_notifications / $notifications_per_page);

// Get notifications for the current page
$stmt = $pdo->prepare("
    SELECT 
        n.*,
        CASE 
            WHEN n.type = 'task' THEN t.task_name
            WHEN n.type = 'project' THEN p.service
            ELSE NULL
        END as reference_name
    FROM notifications n
    LEFT JOIN tasks t ON n.type = 'task' AND n.reference_id = t.task_id
    LEFT JOIN projects p ON n.type = 'project' AND n.reference_id = p.project_id
    WHERE n.recipient_id = ?
    ORDER BY n.created_at DESC
    LIMIT " . $notifications_per_page . " OFFSET " . $offset
);

// Execute with just the user_id
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug information
echo "<!-- Total notifications: " . $total_notifications . " -->";
echo "<!-- Total pages: " . $total_pages . " -->";
echo "<!-- Current page: " . $page . " -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Admin Dashboard</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .notification-item {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item.unread {
            border-left-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }
        .notification-item.task {
            border-left-color: #198754;
        }
        .notification-item.project {
            border-left-color: #dc3545;
        }
        .notification-item.system {
            border-left-color: #ffc107;
        }
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .pagination {
            margin-top: 20px;
        }
        .page-link.active {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>

    <div class="engineer-main-content">
        <div class="container-fluid px-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1">Notifications</h3>
                    <p class="text-muted mb-0">View all your notifications</p>
                </div>
                <?php if (!empty($notifications)): ?>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="mark_all_read" class="btn btn-light">
                            <i class="fas fa-check-double me-2"></i>Mark All as Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Notifications List -->
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                            <h5>No Notifications</h5>
                            <p class="text-muted">You don't have any notifications yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="list-group-item notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['type']; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <?php
                                            $icon = match($notification['type']) {
                                                'task' => 'fas fa-tasks',
                                                'project' => 'fas fa-project-diagram',
                                                'system' => 'fas fa-cog',
                                                default => 'fas fa-bell'
                                            };
                                            ?>
                                            <i class="<?php echo $icon; ?> fa-lg me-3"></i>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <small class="notification-time">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('M j, Y, g:i a', strtotime($notification['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                                <button type="submit" name="mark_read" class="btn btn-sm btn-light">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-center p-3">
                                <nav aria-label="Notifications pagination">
                                    <ul class="pagination mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page - 1); ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page + 1); ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 