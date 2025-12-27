<?php
session_start();
include 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$message = "";

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = (int)$_POST['order_id'];
    
    // Check if user owns this order
    $check_sql = "SELECT order_status FROM orders WHERE order_id = ? AND user_id_fk = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $order_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $order = $check_result->fetch_assoc();
        
        // Only allow cancellation if order is still pending
        if ($order['order_status'] === 'Pending') {
            $cancel_sql = "UPDATE orders SET order_status = 'Cancelled' WHERE order_id = ?";
            $cancel_stmt = $conn->prepare($cancel_sql);
            $cancel_stmt->bind_param("i", $order_id);
            
            if ($cancel_stmt->execute()) {
                // Restore product stock
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
                
                $message = "<div class='alert alert-success'>
                    <i class='fas fa-check-circle'></i>
                    Order #{$order_id} has been cancelled successfully.
                </div>";
            } else {
                $message = "<div class='alert alert-danger'>
                    <i class='fas fa-exclamation-circle'></i>
                    Failed to cancel order. Please try again.
                </div>";
            }
            
            $cancel_stmt->close();
        } else {
            $message = "<div class='alert alert-warning'>
                <i class='fas fa-info-circle'></i>
                This order cannot be cancelled as it's already {$order['order_status']}.
            </div>";
        }
    }
    
    $check_stmt->close();
}

// Fetch user's orders
$orders_sql = "SELECT 
                o.order_id,
                o.total_price_fk as total_amount,
                o.shipping_address,
                o.order_status,
                o.payment_status,
                o.created_at,
                COUNT(oi.order_item_id) as item_count
            FROM orders o
            LEFT JOIN order_items oi ON o.order_id = oi.order_id_fk
            WHERE o.user_id_fk = ?
            GROUP BY o.order_id
            ORDER BY o.created_at DESC";
            
$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Check if user has orders
$has_orders = $orders_result->num_rows > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Velvet Vogue</title>
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
        
        .orders-container {
            max-width: 1200px;
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
        }
        
        .page-subtitle {
            color: var(--gray-color);
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
        }
        
        .alert i {
            margin-right: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
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
        
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        .order-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .order-info h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        
        .order-number {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .order-date {
            color: var(--gray-color);
            font-size: 0.95rem;
        }
        
        .order-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }
        
        .status-badge {
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .status-processing {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
            border: 1px solid rgba(23, 162, 184, 0.3);
        }
        
        .status-shipped {
            background: rgba(0, 123, 255, 0.1);
            color: #004085;
            border: 1px solid rgba(0, 123, 255, 0.3);
        }
        
        .status-delivered {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .status-cancelled {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .payment-badge {
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .payment-paid {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .payment-unpaid {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .order-details {
            margin-bottom: 25px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .detail-label {
            font-size: 0.9rem;
            color: var(--gray-color);
            font-weight: 500;
        }
        
        .detail-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .shipping-address {
            background: var(--light-color);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
        }
        
        .address-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .address-text {
            color: var(--gray-color);
            line-height: 1.6;
            white-space: pre-line;
        }
        
        .order-items {
            margin-top: 30px;
        }
        
        .items-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .items-title i {
            color: var(--primary-color);
        }
        
        .items-table {
            width: 100%;
            background: var(--light-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .table-header {
            display: grid;
            grid-template-columns: 3fr 1fr 1fr 1fr;
            background: var(--primary-color);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 3fr 1fr 1fr 1fr;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
        }
        
        .table-row:last-child {
            border-bottom: none;
        }
        
        .table-row:nth-child(even) {
            background: rgba(255, 255, 255, 0.5);
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-info {
            display: flex;
            align-items: center;
        }
        
        .item-name {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }
        
        .order-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .order-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
            box-shadow: 0 4px 20px rgba(220, 53, 69, 0.25);
        }
        
        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(220, 53, 69, 0.35);
        }
        
        .btn-disabled {
            background: var(--gray-color);
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin-top: 30px;
            transition: var(--transition);
        }
        
        .back-link:hover {
            gap: 12px;
            color: var(--secondary-color);
        }
        
        @media (max-width: 768px) {
            .orders-container {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .page-title {
                font-size: 2.2rem;
            }
            
            .order-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .order-status {
                align-items: flex-start;
            }
            
            .table-header, .table-row {
                grid-template-columns: 2fr 1fr 1fr 1fr;
                font-size: 0.9rem;
            }
            
            .order-footer {
                flex-direction: column;
                gap: 20px;
                align-items: stretch;
            }
            
            .order-actions {
                flex-direction: column;
            }
        }
        
        @media (max-width: 576px) {
            .table-header, .table-row {
                grid-template-columns: 1fr;
                gap: 10px;
                padding: 10px;
            }
            
            .table-header {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="orders-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-box me-3"></i>
                My Orders
            </h1>
            <p class="page-subtitle">View and manage all your orders in one place</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <?php if (!$has_orders): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h2 class="empty-title">No Orders Yet</h2>
                <p class="empty-text">
                    You haven't placed any orders yet. Start shopping and your orders will appear here.
                </p>
                <a href="products.php" class="btn btn-primary" style="width: auto; max-width: 300px; margin: 0 auto;">
                    <i class="fas fa-store me-2"></i>
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Orders List -->
            <div class="orders-list">
                <?php while ($order = $orders_result->fetch_assoc()): ?>
                    <?php
                    // Fetch order items for this order
                    $items_sql = "SELECT 
                                    oi.*,
                                    p.name as product_name,
                                    p.price as product_price,
                                    pi.image_url
                                FROM order_items oi
                                JOIN products p ON oi.product_id_fk = p.product_id
                                LEFT JOIN product_images pi ON p.product_id = pi.product_id_fk
                                WHERE oi.order_id_fk = ?
                                GROUP BY oi.order_item_id";
                    $items_stmt = $conn->prepare($items_sql);
                    $items_stmt->bind_param("i", $order['order_id']);
                    $items_stmt->execute();
                    $items_result = $items_stmt->get_result();
                    
                    // Format date
                    $order_date = date('F j, Y', strtotime($order['created_at']));
                    $order_time = date('g:i A', strtotime($order['created_at']));
                    
                    // Determine status badge class
                    $status_class = '';
                    switch ($order['order_status']) {
                        case 'Pending':
                            $status_class = 'status-pending';
                            break;
                        case 'Processing':
                            $status_class = 'status-processing';
                            break;
                        case 'Shipped':
                            $status_class = 'status-shipped';
                            break;
                        case 'Delivered':
                            $status_class = 'status-delivered';
                            break;
                        case 'Cancelled':
                            $status_class = 'status-cancelled';
                            break;
                        default:
                            $status_class = 'status-pending';
                    }
                    
                    // Determine payment badge class
                    $payment_class = $order['payment_status'] === 'Paid' ? 'payment-paid' : 'payment-unpaid';
                    ?>
                    
                    <div class="order-card">
                        <!-- Order Header -->
                        <div class="order-header">
                            <div class="order-info">
                                <h3>
                                    Order <span class="order-number">#<?php echo $order['order_id']; ?></span>
                                </h3>
                                <div class="order-date">
                                    <i class="far fa-calendar-alt me-2"></i>
                                    <?php echo $order_date; ?> at <?php echo $order_time; ?>
                                </div>
                            </div>
                            
                            <div class="order-status">
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $order['order_status']; ?>
                                </span>
                                <span class="payment-badge <?php echo $payment_class; ?>">
                                    <?php echo $order['payment_status']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Order Details -->
                        <div class="order-details">
                            <div class="details-grid">
                                <div class="detail-item">
                                    <span class="detail-label">Items</span>
                                    <span class="detail-value"><?php echo $order['item_count']; ?> item(s)</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Order Total</span>
                                    <span class="detail-value">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Payment Method</span>
                                    <span class="detail-value">Credit Card</span>
                                </div>
                            </div>
                            
                            <div class="shipping-address">
                                <h4 class="address-title">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Shipping Address
                                </h4>
                                <p class="address-text"><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                            </div>
                        </div>
                        
                        <!-- Order Items -->
                        <div class="order-items">
                            <h4 class="items-title">
                                <i class="fas fa-shopping-basket"></i>
                                Order Items
                            </h4>
                            
                            <div class="items-table">
                                <div class="table-header">
                                    <div>Product</div>
                                    <div>Price</div>
                                    <div>Quantity</div>
                                    <div>Subtotal</div>
                                </div>
                                
                                <?php while ($item = $items_result->fetch_assoc()): ?>
                                    <?php $item_subtotal = $item['quantity'] * $item['unit_price']; ?>
                                    <div class="table-row">
                                        <div class="item-info">
                                            <?php if (!empty($item['image_url'])): ?>
                                                <div class="item-image">
                                                    <img src="<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                </div>
                                            <?php endif; ?>
                                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        </div>
                                        <div>$<?php echo number_format($item['unit_price'], 2); ?></div>
                                        <div><?php echo $item['quantity']; ?></div>
                                        <div>$<?php echo number_format($item_subtotal, 2); ?></div>
                                    </div>
                                <?php endwhile; ?>
                                
                                <?php $items_stmt->close(); ?>
                            </div>
                        </div>
                        
                        <!-- Order Footer -->
                        <div class="order-footer">
                            <div class="order-total">
                                Total: $<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                            
                            <div class="order-actions">
                                <?php if ($order['order_status'] === 'Pending'): ?>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <button type="submit" name="cancel_order" class="btn btn-danger" 
                                                onclick="return confirm('Are you sure you want to cancel this order?')">
                                            <i class="fas fa-times-circle me-2"></i>
                                            Cancel Order
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-disabled" disabled>
                                        <i class="fas fa-times-circle me-2"></i>
                                        Cancel Order
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($order['order_status'] === 'Delivered'): ?>
                                    <a href="#" class="btn btn-outline">
                                        <i class="fas fa-redo me-2"></i>
                                        Buy Again
                                    </a>
                                <?php endif; ?>
                                
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-eye me-2"></i>
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
        
        <!-- Back to Dashboard -->
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Confirm order cancellation
    document.querySelectorAll('form[action*="cancel_order"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Add animation to order cards
    document.addEventListener('DOMContentLoaded', function() {
        const orderCards = document.querySelectorAll('.order-card');
        orderCards.forEach((card, index) => {
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