<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a worker
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'worker') {
    header('Location: ../admin_login.php');
    exit;
}

// Mark notification as read
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$_POST['notification_id'], $_SESSION['user_id']]);
    header('Location: notifications.php');
    exit;
}

// Mark all notifications as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    header('Location: notifications.php');
    exit;
}

// Pagination settings
$notifications_per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $notifications_per_page;

// Get total number of notifications
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$total_stmt->execute([$_SESSION['user_id']]);
$total_notifications = $total_stmt->fetchColumn();
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
    WHERE n.user_id = :user_id
    ORDER BY n.created_at DESC
    LIMIT :limit OFFSET :offset
");

// Bind parameters properly for LIMIT and OFFSET
$stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $notifications_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Worker Dashboard</title>
    
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
            padding: 1.25rem;
            margin-bottom: 0.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
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
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .notification-icon.task {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .notification-icon.project {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .notification-icon.system {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #344767;
        }

        .notification-message {
            color: #67748e;
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .notification-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mark-read-btn {
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            background-color: white;
            color: #6c757d;
            transition: all 0.2s ease;
        }

        .mark-read-btn:hover {
            background-color: #f8f9fa;
            color: #0d6efd;
        }

        .notifications-header {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .mark-all-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #344767;
            transition: all 0.2s ease;
        }

        .mark-all-btn:hover {
            background-color: #e9ecef;
            color: #0d6efd;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .empty-state i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        .empty-state h5 {
            color: #344767;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #67748e;
            margin-bottom: 0;
        }

        /* Add these new styles for pagination */
        .pagination-container {
            background-color: #f8f9fa;
        }

        .pagination-info {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .pagination-buttons .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        .pagination-buttons .btn:not(:last-child) {
            margin-right: 0.5rem;
        }

        .pagination-buttons .btn i {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php include 'worker_header.php'; ?>

    <div class="engineer-main-content">
        <div class="container-fluid px-4">
            <!-- Page Header -->
            <div class="notifications-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1">Notifications</h3>
                    <p class="text-muted mb-0">Stay updated with your latest activities</p>
                </div>
                <?php if (!empty($notifications)): ?>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="mark_all_read" class="mark-all-btn">
                            <i class="fas fa-check-double me-2"></i>Mark All as Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Notifications List -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h5>No Notifications</h5>
                            <p>You're all caught up! Check back later for new updates.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush p-3">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['type']; ?>">
                                    <div class="d-flex">
                                        <div class="notification-icon <?php echo $notification['type']; ?>">
                                            <?php
                                            $icon = match($notification['type']) {
                                                'task' => 'fas fa-tasks',
                                                'project' => 'fas fa-project-diagram',
                                                'system' => 'fas fa-cog',
                                                default => 'fas fa-bell'
                                            };
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <h6 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="notification-time">
                                                    <i class="fas fa-clock"></i>
                                                    <?php echo date('M j, Y, g:i a', strtotime($notification['created_at'])); ?>
                                                    <?php if ($notification['reference_name']): ?>
                                                        <span class="badge bg-light text-dark ms-2">
                                                            <?php echo htmlspecialchars($notification['reference_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!$notification['is_read']): ?>
                                                    <form method="POST" class="notification-actions">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                                        <button type="submit" name="mark_read" class="mark-read-btn">
                                                            <i class="fas fa-check me-1"></i> Mark as Read
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-container d-flex justify-content-between align-items-center p-3 border-top">
                                <div class="pagination-info">
                                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $notifications_per_page, $total_notifications); ?> of <?php echo $total_notifications; ?> notifications
                                </div>
                                <div class="pagination-buttons">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-chevron-left me-1"></i> Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>" class="btn btn-outline-primary btn-sm">
                                            Next <i class="fas fa-chevron-right ms-1"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
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