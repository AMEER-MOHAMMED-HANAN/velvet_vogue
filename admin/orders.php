<?php
session_start();
include '../includes/db_connect.php';



// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $order_id = (int)$_POST['order_id'];
        $new_status = trim($_POST['status']);
        
        $update_sql = "UPDATE orders SET order_status = ? WHERE order_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $order_id);
        
        if ($update_stmt->execute()) {
            // Update payment status if order is delivered
            if ($new_status === 'Delivered') {
                $payment_sql = "UPDATE payments SET payment_status = 'Completed' WHERE order_id_fk = ?";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->bind_param("i", $order_id);
                $payment_stmt->execute();
                $payment_stmt->close();
                
                // Also update order payment status
                $order_payment_sql = "UPDATE orders SET payment_status = 'Paid' WHERE order_id = ?";
                $order_payment_stmt = $conn->prepare($order_payment_sql);
                $order_payment_stmt->bind_param("i", $order_id);
                $order_payment_stmt->execute();
                $order_payment_stmt->close();
            }
            
            $success = "<div class='alert alert-success'>
                <i class='fas fa-check-circle'></i>
                Order #{$order_id} status updated to '{$new_status}' successfully.
            </div>";
        } else {
            $message = "<div class='alert alert-danger'>
                <i class='fas fa-exclamation-circle'></i>
                Failed to update order status. Please try again.
            </div>";
        }
        
        $update_stmt->close();
    } elseif (isset($_POST['delete_order'])) {
        $order_id = (int)$_POST['order_id'];
        
        // Start transaction for deletion
        $conn->begin_transaction();
        
        try {
            // First, restore product stock
            $restore_sql = "SELECT oi.product_id_fk, oi.quantity 
                          FROM order_items oi 
                          WHERE oi.order_id_fk = ?";
            $restore_stmt = $conn->prepare($restore_sql);
            $restore_stmt->bind_param("i", $order_id);
            $restore_stmt->execute();
            $restore_result = $restore_stmt->get_result();
            
            while ($item = $restore_result->fetch_assoc()) {
                $update_sql = "UPDATE products SET stock = stock + ? WHERE product_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $item['quantity'], $item['product_id_fk']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            $restore_stmt->close();
            
            // Delete payments
            $delete_payments_sql = "DELETE FROM payments WHERE order_id_fk = ?";
            $delete_payments_stmt = $conn->prepare($delete_payments_sql);
            $delete_payments_stmt->bind_param("i", $order_id);
            $delete_payments_stmt->execute();
            $delete_payments_stmt->close();
            
            // Delete order items
            $delete_items_sql = "DELETE FROM order_items WHERE order_id_fk = ?";
            $delete_items_stmt = $conn->prepare($delete_items_sql);
            $delete_items_stmt->bind_param("i", $order_id);
            $delete_items_stmt->execute();
            $delete_items_stmt->close();
            
            // Delete order
            $delete_order_sql = "DELETE FROM orders WHERE order_id = ?";
            $delete_order_stmt = $conn->prepare($delete_order_sql);
            $delete_order_stmt->bind_param("i", $order_id);
            $delete_order_stmt->execute();
            $delete_order_stmt->close();
            
            $conn->commit();
            
            $success = "<div class='alert alert-success'>
                <i class='fas fa-check-circle'></i>
                Order #{$order_id} has been deleted successfully.
            </div>";
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert alert-danger'>
                <i class='fas fa-exclamation-circle'></i>
                Failed to delete order. Error: " . htmlspecialchars($e->getMessage()) . "
            </div>";
        }
    }
}

// Filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT 
            o.order_id,
            o.user_id_fk,
            o.total_price_fk as total_amount,
            o.shipping_address,
            o.order_status,
            o.payment_status,
            o.created_at,
            u.first_name,
            u.last_name,
            u.email,
            u.phone_number,
            COUNT(oi.order_item_id) as item_count,
            SUM(oi.quantity * oi.unit_price) as items_total
        FROM orders o
        JOIN users u ON o.user_id_fk = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id_fk
        WHERE 1=1";

$params = [];
$types = '';

// Apply filters
if ($status_filter !== 'all') {
    $query .= " AND o.order_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $query .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($search)) {
    $query .= " AND (o.order_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$query .= " GROUP BY o.order_id ORDER BY o.created_at DESC";

// Prepare and execute query
$orders_stmt = $conn->prepare($query);

if (!empty($params)) {
    $orders_stmt->bind_param($types, ...$params);
}

$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Get total stats
$stats_sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(total_price_fk) as total_revenue,
                AVG(total_price_fk) as avg_order_value,
                COUNT(CASE WHEN order_status = 'Pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN order_status = 'Processing' THEN 1 END) as processing_orders,
                COUNT(CASE WHEN order_status = 'Shipped' THEN 1 END) as shipped_orders,
                COUNT(CASE WHEN order_status = 'Delivered' THEN 1 END) as delivered_orders,
                COUNT(CASE WHEN order_status = 'Cancelled' THEN 1 END) as cancelled_orders
            FROM orders";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Velvet Vogue Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
        
        .admin-container {
            max-width: 1400px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--gray-color);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filters-section {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            margin-bottom: 40px;
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
        
        .section-title i {
            color: var(--primary-color);
            font-size: 1.8rem;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 10px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 18px;
            font-size: 1rem;
            transition: var(--transition);
            background: var(--light-color);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.15);
            background: white;
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
        
        .btn-sm {
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        .orders-section {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .section-title-large {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .dataTables_wrapper {
            margin-top: 20px;
        }
        
        table.dataTable {
            width: 100% !important;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        table.dataTable thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 18px;
            font-weight: 600;
            border: none;
            position: relative;
        }
        
        table.dataTable thead th:after {
            content: '';
            position: absolute;
            right: 0;
            top: 20%;
            height: 60%;
            width: 1px;
            background: rgba(255, 255, 255, 0.2);
        }
        
        table.dataTable thead th:last-child:after {
            display: none;
        }
        
        table.dataTable tbody td {
            padding: 18px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
            background: white;
        }
        
        table.dataTable tbody tr {
            transition: var(--transition);
        }
        
        table.dataTable tbody tr:hover {
            background: rgba(138, 43, 226, 0.03);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            position: relative;
            overflow: hidden;
        }
        
        .status-badge:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: currentColor;
            opacity: 0.1;
        }
        
        .status-pending { color: var(--warning-color); }
        .status-processing { color: var(--info-color); }
        .status-shipped { color: #6f42c1; }
        .status-delivered { color: var(--success-color); }
        .status-cancelled { color: var(--danger-color); }
        
        .payment-badge {
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .payment-unpaid {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 12px;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
        }
        
        .customer-details {
            display: flex;
            flex-direction: column;
        }
        
        .customer-name {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .customer-email {
            font-size: 0.85rem;
            color: var(--gray-color);
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: var(--light-color);
            color: var(--gray-color);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-sm);
        }
        
        .action-btn.view:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .action-btn.edit:hover {
            background: var(--info-color);
            color: white;
        }
        
        .action-btn.delete:hover {
            background: var(--danger-color);
            color: white;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 25px 30px;
        }
        
        .modal-title {
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .info-card {
            background: var(--light-color);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }
        
        .info-card h6 {
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .product-card {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .product-img {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        
        .product-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .product-price {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .total-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            text-align: center;
        }
        
        .total-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .total-value {
            font-size: 2rem;
            font-weight: 700;
        }
        
        @media (max-width: 992px) {
            .admin-container {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .page-title {
                font-size: 2.2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .order-actions {
                flex-direction: column;
            }
            
            .action-btn {
                width: 35px;
                height: 35px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation (Optional - Add your admin navigation here) -->
    
    <div class="admin-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-shopping-bag me-3"></i>
                Order Management
            </h1>
            <p class="page-subtitle">Track, manage, and analyze all customer orders in one place</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($success)): ?>
            <?php echo $success; ?>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?></div>
                <div class="stat-label">Avg Order Value</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending_orders']); ?></div>
                <div class="stat-label">Pending Orders</div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <h3 class="section-title">
                <i class="fas fa-filter"></i>
                Filter & Search Orders
            </h3>
            
            <form method="GET" class="filter-grid">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tag"></i>
                        Order Status
                    </label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="Shipped" <?php echo $status_filter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt"></i>
                        From Date
                    </label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar-check"></i>
                        To Date
                    </label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-search"></i>
                        Search Orders
                    </label>
                    <input type="text" name="search" class="form-control" placeholder="Order ID, Customer Name or Email" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>
                        Apply Filters
                    </button>
                </div>
                
                <div class="form-group d-flex align-items-end">
                    <a href="orders.php" class="btn btn-outline w-100">
                        <i class="fas fa-redo me-2"></i>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Orders Section -->
        <div class="orders-section">
            <div class="section-header">
                <h2 class="section-title-large">
                    <i class="fas fa-list-alt me-3"></i>
                    All Customer Orders
                </h2>
                <div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="exportToExcel()">
                        <i class="fas fa-file-export me-2"></i>
                        Export to Excel
                    </button>
                </div>
            </div>
            
            <table id="ordersTable" class="table table-hover">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $orders_result->fetch_assoc()): ?>
                        <?php
                        $order_date = date('M j, Y', strtotime($order['created_at']));
                        $order_time = date('g:i A', strtotime($order['created_at']));
                        $customer_initials = strtoupper(substr($order['first_name'], 0, 1) . substr($order['last_name'], 0, 1));
                        ?>
                        <tr>
                            <td>
                                <strong class="text-primary">#<?php echo $order['order_id']; ?></strong>
                            </td>
                            <td>
                                <div class="customer-info">
                                    <div class="customer-avatar">
                                        <?php echo $customer_initials; ?>
                                    </div>
                                    <div class="customer-details">
                                        <span class="customer-name"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span>
                                        <span class="customer-email"><?php echo htmlspecialchars($order['email']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo $order_date; ?></div>
                                <small class="text-muted"><?php echo $order_time; ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-box me-2 text-primary"></i>
                                    <div>
                                        <div><?php echo $order['item_count']; ?> item(s)</div>
                                        <small class="text-muted">$<?php echo number_format($order['items_total'], 2); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong class="text-primary">$<?php echo number_format($order['total_amount'], 2); ?></strong>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                                    <i class="fas fa-circle me-2" style="font-size: 8px;"></i>
                                    <?php echo $order['order_status']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="payment-badge payment-<?php echo strtolower($order['payment_status']); ?>">
                                    <?php echo $order['payment_status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="order-actions">
                                    <button type="button" class="action-btn view" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#viewOrderModal<?php echo $order['order_id']; ?>"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <button type="button" class="action-btn edit" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateStatusModal<?php echo $order['order_id']; ?>"
                                            title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="action-btn delete" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteOrderModal<?php echo $order['order_id']; ?>"
                                            title="Delete Order">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- View Order Modal -->
                        <div class="modal fade" id="viewOrderModal<?php echo $order['order_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-file-invoice me-2"></i>
                                            Order #<?php echo $order['order_id']; ?> Details
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="info-card">
                                                    <h6><i class="fas fa-info-circle"></i> Order Information</h6>
                                                    <p><strong>Order ID:</strong> #<?php echo $order['order_id']; ?></p>
                                                    <p><strong>Order Date:</strong> <?php echo $order_date; ?> at <?php echo $order_time; ?></p>
                                                    <p><strong>Status:</strong> 
                                                        <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                                                            <?php echo $order['order_status']; ?>
                                                        </span>
                                                    </p>
                                                    <p><strong>Payment:</strong> 
                                                        <span class="payment-badge payment-<?php echo strtolower($order['payment_status']); ?>">
                                                            <?php echo $order['payment_status']; ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-card">
                                                    <h6><i class="fas fa-user"></i> Customer Information</h6>
                                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                                                    <?php if (!empty($order['phone_number'])): ?>
                                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone_number']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="info-card mb-4">
                                            <h6><i class="fas fa-map-marker-alt"></i> Shipping Address</h6>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                                        </div>
                                        
                                        <h6 class="mb-3"><i class="fas fa-shopping-basket me-2"></i> Order Items</h6>
                                        <?php
                                        // Fetch order items
                                        $items_sql = "SELECT 
                                                    oi.*,
                                                    p.name as product_name,
                                                    p.product_id,
                                                    pi.image_url
                                                FROM order_items oi
                                                JOIN products p ON oi.product_id_fk = p.product_id
                                                LEFT JOIN (
                                                    SELECT product_id_fk, image_url 
                                                    FROM product_images 
                                                    GROUP BY product_id_fk
                                                ) pi ON p.product_id = pi.product_id_fk
                                                WHERE oi.order_id_fk = ?";
                                        $items_stmt = $conn->prepare($items_sql);
                                        $items_stmt->bind_param("i", $order['order_id']);
                                        $items_stmt->execute();
                                        $items_result = $items_stmt->get_result();
                                        ?>
                                        
                                        <?php while ($item = $items_result->fetch_assoc()): ?>
                                            <?php 
                                            $item_total = $item['quantity'] * $item['unit_price'];
                                            $image_url = !empty($item['image_url']) ? $item['image_url'] : 'https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80';
                                            ?>
                                            <div class="product-card">
                                                <div class="product-img">
                                                    <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                         onerror="this.src='https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80'">
                                                </div>
                                                <div class="product-details">
                                                    <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                    <div class="product-meta">
                                                        <span><i class="fas fa-dollar-sign"></i> $<?php echo number_format($item['unit_price'], 2); ?></span>
                                                        <span><i class="fas fa-box"></i> Qty: <?php echo $item['quantity']; ?></span>
                                                        <span><i class="fas fa-calculator"></i> Subtotal: $<?php echo number_format($item_total, 2); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                        <?php $items_stmt->close(); ?>
                                        
                                        <div class="total-card">
                                            <div class="total-label">Order Total</div>
                                            <div class="total-value">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Update Status Modal -->
                        <div class="modal fade" id="updateStatusModal<?php echo $order['order_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-edit me-2"></i>
                                            Update Order Status
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            
                                            <div class="info-card mb-4">
                                                <h6><i class="fas fa-info-circle"></i> Current Status</h6>
                                                <div class="text-center">
                                                    <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                                                        <?php echo $order['order_status']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group mb-4">
                                                <label for="status<?php echo $order['order_id']; ?>" class="form-label">
                                                    <i class="fas fa-arrow-right"></i>
                                                    Update to New Status
                                                </label>
                                                <select name="status" id="status<?php echo $order['order_id']; ?>" class="form-select" required>
                                                    <option value="Pending" <?php echo $order['order_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="Processing" <?php echo $order['order_status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                                    <option value="Shipped" <?php echo $order['order_status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                    <option value="Delivered" <?php echo $order['order_status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="Cancelled" <?php echo $order['order_status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_status" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>
                                                Update Status
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Order Modal -->
                        <div class="modal fade" id="deleteOrderModal<?php echo $order['order_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Confirm Deletion
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            
                                            <div class="text-center mb-4">
                                                <i class="fas fa-trash fa-3x text-danger mb-3"></i>
                                                <h5>Delete Order #<?php echo $order['order_id']; ?>?</h5>
                                                <p class="text-muted">
                                                    Customer: <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?><br>
                                                    Total: $<?php echo number_format($order['total_amount'], 2); ?>
                                                </p>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-circle"></i>
                                                    <strong>Warning:</strong> This action cannot be undone. Product stock will be restored.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="delete_order" class="btn btn-danger">
                                                <i class="fas fa-trash me-2"></i>
                                                Delete Order
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>
    // Initialize DataTable
    $(document).ready(function() {
        $('#ordersTable').DataTable({
            "pageLength": 25,
            "order": [[0, 'desc']],
            "language": {
                "search": "Search orders:",
                "lengthMenu": "Show _MENU_ orders per page",
                "info": "Showing _START_ to _END_ of _TOTAL_ orders",
                "paginate": {
                    "previous": "<i class='fas fa-chevron-left'></i>",
                    "next": "<i class='fas fa-chevron-right'></i>"
                }
            },
            "initComplete": function() {
                // Add animation to table rows
                $('tbody tr').each(function(i) {
                    $(this).delay(i * 50).animate({
                        opacity: 1
                    }, 300);
                });
            }
        });
    });
    
    // Export to Excel function
    function exportToExcel() {
        const table = document.getElementById('ordersTable');
        const wb = XLSX.utils.table_to_book(table, {sheet: "Orders"});
        XLSX.writeFile(wb, `orders_export_${new Date().toISOString().split('T')[0]}.xlsx`);
    }
    
    // Add animation to cards on page load
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.stat-card');
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
    </script>
</body>
</html>

<?php 
// Close connections
if (isset($orders_stmt)) $orders_stmt->close();
if (isset($conn)) $conn->close();
?>