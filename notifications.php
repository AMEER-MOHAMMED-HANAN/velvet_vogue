<?php
session_start();
include 'includes/db_connect.php';



// Check if notifications table exists
$table_exists = true;
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($table_check->num_rows == 0) {
    $table_exists = false;
    $message = "<div class='alert alert-warning'>
        <i class='fas fa-exclamation-triangle'></i>
        Notifications feature is being set up. Please check back soon.
    </div>";
}

// Handle marking notifications as read - only if table exists
if ($table_exists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_as_read'])) {
        $notification_id = (int)$_POST['notification_id'];
        
        $update_sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id_fk = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $notification_id, $user_id);
        
        if ($update_stmt->execute()) {
            $success = "<div class='alert alert-success'>
                <i class='fas fa-check-circle'></i>
                Notification marked as read.
            </div>";
        }
        $update_stmt->close();
        
    } elseif (isset($_POST['mark_all_read'])) {
        $update_sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id_fk = ? AND is_read = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user_id);
        
        if ($update_stmt->execute()) {
            $success = "<div class='alert alert-success'>
                <i class='fas fa-check-circle'></i>
                All notifications marked as read.
            </div>";
        }
        $update_stmt->close();
        
    } elseif (isset($_POST['clear_all'])) {
        $delete_sql = "DELETE FROM notifications WHERE user_id_fk = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $user_id);
        
        if ($delete_stmt->execute()) {
            $success = "<div class='alert alert-success'>
                <i class='fas fa-check-circle'></i>
                All notifications cleared.
            </div>";
        }
        $delete_stmt->close();
    }
}

// Get unread notifications count - only if table exists
$unread_count = 0;
if ($table_exists) {
    $count_sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id_fk = ? AND is_read = 0";
    $count_stmt = $conn->prepare($count_sql);
    if ($count_stmt) {
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_data = $count_result->fetch_assoc();
        $unread_count = $count_data ? $count_data['unread_count'] : 0;
        $count_stmt->close();
    }
}

// Get all notifications - only if table exists
$notifications_result = null;
if ($table_exists) {
    $notifications_sql = "SELECT 
                            n.notification_id,
                            n.title,
                            n.message,
                            n.notification_type,
                            n.reference_id,
                            n.is_read,
                            n.created_at,
                            n.read_at,
                            o.order_id,
                            o.order_status,
                            p.name as product_name
                        FROM notifications n
                        LEFT JOIN orders o ON n.reference_id = o.order_id
                        LEFT JOIN products p ON n.reference_id = p.product_id
                        WHERE n.user_id_fk = ?
                        ORDER BY n.created_at DESC";
                        
    $notifications_stmt = $conn->prepare($notifications_sql);
    if ($notifications_stmt) {
        $notifications_stmt->bind_param("i", $user_id);
        $notifications_stmt->execute();
        $notifications_result = $notifications_stmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications - Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8a2be2;
            --secondary-color: #5d3fd3;
            --accent-color: #ff6b8b;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-color: #e0e0e0;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.15);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: var(--dark-color);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
        }
        
        .notifications-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-title {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }
        
        .page-title:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        
        .page-subtitle {
            color: var(--gray-color);
            font-size: 1.1rem;
            margin-top: 20px;
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            box-shadow: var(--shadow-sm);
            margin-bottom: 25px;
            padding: 20px;
        }
        
        .alert i {
            margin-right: 10px;
        }
        
        .stats-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }
        
        .stats-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            box-shadow: 0 5px 20px rgba(138, 43, 226, 0.3);
        }
        
        .stats-value {
            font-size: 3rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .stats-label {
            font-size: 1.2rem;
            color: var(--gray-color);
            font-weight: 500;
        }
        
        .actions-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }
        
        .actions-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 20px rgba(138, 43, 226, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(138, 43, 226, 0.35);
        }
        
        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
            box-shadow: 0 4px 20px rgba(255, 193, 7, 0.25);
        }
        
        .btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(255, 193, 7, 0.35);
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
            box-shadow: 0 4px 20px rgba(220, 53, 69, 0.25);
        }
        
        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(220, 53, 69, 0.35);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .notifications-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 25px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 30px;
        }
        
        .empty-icon {
            font-size: 5rem;
            color: var(--gray-color);
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-title {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        
        .empty-text {
            color: var(--gray-color);
            max-width: 500px;
            margin: 0 auto 30px;
            font-size: 1.1rem;
        }
        
        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .notification-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            border: 2px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .notification-card.unread {
            border-color: var(--primary-color);
            background: rgba(138, 43, 226, 0.03);
        }
        
        .notification-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .notification-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-time {
            font-size: 0.85rem;
            color: var(--gray-color);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .notification-badge {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-left: 10px;
        }
        
        .badge-order { background: rgba(138, 43, 226, 0.1); color: var(--primary-color); }
        .badge-promotion { background: rgba(255, 107, 139, 0.1); color: var(--accent-color); }
        .badge-system { background: rgba(108, 117, 125, 0.1); color: var(--gray-color); }
        .badge-delivery { background: rgba(40, 167, 69, 0.1); color: var(--success-color); }
        
        .notification-content {
            color: var(--dark-color);
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .notification-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .notification-order-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .order-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .order-link:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
        }
        
        .notification-unread-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary-color);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        
        .icon-order { background: var(--primary-color); }
        .icon-promotion { background: var(--accent-color); }
        .icon-system { background: var(--gray-color); }
        .icon-delivery { background: var(--success-color); }
        
        @media (max-width: 768px) {
            .notifications-container {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .page-title {
                font-size: 2.2rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .notification-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .notification-footer {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="notifications-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-bell me-3"></i>
                My Notifications
            </h1>
            <p class="page-subtitle">Stay updated with your order status and important alerts</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($success)): ?>
            <?php echo $success; ?>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <!-- Statistics Card -->
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stats-value"><?php echo $unread_count; ?></div>
            <div class="stats-label">Unread Notifications</div>
        </div>

        <!-- Actions Section -->
        <div class="actions-section">
            <h3 class="actions-title">
                <i class="fas fa-cog"></i>
                Notification Actions
            </h3>
            
            <form method="POST" class="actions-grid">
                <button type="submit" name="mark_all_read" class="btn btn-warning" <?php echo !$table_exists ? 'disabled' : ''; ?>>
                    <i class="fas fa-check-double"></i>
                    Mark All as Read
                </button>
                
                <button type="submit" name="clear_all" class="btn btn-danger" 
                        onclick="return confirm('Are you sure you want to clear all notifications?')"
                        <?php echo !$table_exists ? 'disabled' : ''; ?>>
                    <i class="fas fa-trash-alt"></i>
                    Clear All Notifications
                </button>
                
                <a href="orders.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i>
                    View My Orders
                </a>
                
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </form>
        </div>

        <!-- Notifications Section -->
        <div class="notifications-section">
            <h3 class="section-title">
                <i class="fas fa-list"></i>
                All Notifications
            </h3>
            
            <?php if (!$table_exists): ?>
                <!-- Table doesn't exist state -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h2 class="empty-title">Notifications System Not Ready</h2>
                    <p class="empty-text">
                        The notifications system is currently being set up. Please check back soon or contact the administrator.
                    </p>
                    <div class="mt-4">
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Return to Dashboard
                        </a>
                    </div>
                </div>
            <?php elseif ($notifications_result && $notifications_result->num_rows === 0): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-bell-slash"></i>
                    </div>
                    <h2 class="empty-title">No Notifications Yet</h2>
                    <p class="empty-text">
                        You don't have any notifications yet. When you place orders or there are updates, 
                        you'll see them here.
                    </p>
                    <a href="products.php" class="btn btn-primary" style="width: auto; max-width: 300px; margin: 0 auto;">
                        <i class="fas fa-store me-2"></i>
                        Start Shopping
                    </a>
                </div>
            <?php elseif ($notifications_result): ?>
                <!-- Notifications List -->
                <div class="notification-list">
                    <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                        <?php
                        // Format time
                        $created_at = strtotime($notification['created_at']);
                        $current_time = time();
                        $time_diff = $current_time - $created_at;
                        
                        if ($time_diff < 60) {
                            $time_ago = 'Just now';
                        } elseif ($time_diff < 3600) {
                            $minutes = floor($time_diff / 60);
                            $time_ago = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
                        } elseif ($time_diff < 86400) {
                            $hours = floor($time_diff / 3600);
                            $time_ago = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                        } elseif ($time_diff < 604800) {
                            $days = floor($time_diff / 86400);
                            $time_ago = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                        } else {
                            $time_ago = date('M j, Y', $created_at);
                        }
                        
                        // Determine icon and badge based on type
                        $icon_class = '';
                        $badge_class = '';
                        $badge_text = '';
                        
                        switch ($notification['notification_type']) {
                            case 'order_status':
                                $icon_class = 'icon-order';
                                $badge_class = 'badge-order';
                                $badge_text = 'Order Update';
                                break;
                            case 'promotion':
                                $icon_class = 'icon-promotion';
                                $badge_class = 'badge-promotion';
                                $badge_text = 'Promotion';
                                break;
                            case 'delivery':
                                $icon_class = 'icon-delivery';
                                $badge_class = 'badge-delivery';
                                $badge_text = 'Delivery';
                                break;
                            default:
                                $icon_class = 'icon-system';
                                $badge_class = 'badge-system';
                                $badge_text = 'System';
                        }
                        ?>
                        
                        <div class="notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                            <div class="notification-header">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div class="notification-icon <?php echo $icon_class; ?>">
                                        <i class="fas fa-<?php 
                                            echo $notification['notification_type'] === 'order_status' ? 'shopping-bag' : 
                                                ($notification['notification_type'] === 'promotion' ? 'tag' : 
                                                ($notification['notification_type'] === 'delivery' ? 'truck' : 'info-circle')); 
                                        ?>"></i>
                                    </div>
                                    <div>
                                        <h4 class="notification-title">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                            <span class="notification-badge <?php echo $badge_class; ?>">
                                                <?php echo $badge_text; ?>
                                            </span>
                                        </h4>
                                    </div>
                                </div>
                                
                                <div class="notification-time">
                                    <i class="far fa-clock"></i>
                                    <?php echo $time_ago; ?>
                                    
                                    <?php if (!$notification['is_read']): ?>
                                        <div class="notification-unread-dot ml-2" title="Unread"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="notification-content">
                                <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                            </div>
                            
                            <div class="notification-footer">
                                <div class="notification-order-info">
                                    <?php if ($notification['order_id']): ?>
                                        <i class="fas fa-receipt"></i>
                                        <span>Order: <a href="order_details.php?id=<?php echo $notification['order_id']; ?>" class="order-link">
                                            #<?php echo $notification['order_id']; ?>
                                        </a></span>
                                        
                                        <?php if ($notification['order_status']): ?>
                                            <span>•</span>
                                            <span>Status: 
                                                <strong class="<?php 
                                                    echo $notification['order_status'] === 'Delivered' ? 'text-success' : 
                                                    ($notification['order_status'] === 'Cancelled' ? 'text-danger' : 'text-warning');
                                                ?>">
                                                    <?php echo $notification['order_status']; ?>
                                                </strong>
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif ($notification['product_name']): ?>
                                        <i class="fas fa-box"></i>
                                        <span>Product: <?php echo htmlspecialchars($notification['product_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                            <button type="submit" name="mark_as_read" class="btn btn-outline btn-sm">
                                                <i class="fas fa-check me-1"></i>
                                                Mark as Read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($notification['order_id']): ?>
                                        <a href="order_details.php?id=<?php echo $notification['order_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>
                                            View Order
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Database error state -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h2 class="empty-title">Error Loading Notifications</h2>
                    <p class="empty-text">
                        There was an error loading your notifications. Please try again later.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Add animation to notification cards
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.notification-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
    
    // Auto-refresh notifications every 30 seconds - only if table exists
    <?php if ($table_exists): ?>
    setTimeout(function() {
        location.reload();
    }, 30000);
    <?php endif; ?>
    </script>
</body>
</html>

<?php 
// Close connections
if (isset($notifications_stmt) && $notifications_stmt) $notifications_stmt->close();
if (isset($conn)) $conn->close();
?>