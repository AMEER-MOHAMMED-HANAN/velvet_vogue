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
$order_id = null;

// Fetch cart items
$cart_sql = "SELECT 
                c.cart_id,
                c.quantity,
                p.product_id,
                p.name,
                p.price,
                p.stock,
                pi.image_url
            FROM cart c 
            JOIN products p ON c.product_id_fk = p.product_id 
            LEFT JOIN product_images pi ON p.product_id = pi.product_id_fk
            WHERE c.user_id_fk = ?
            GROUP BY p.product_id";
            
$cart_stmt = $conn->prepare($cart_sql);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();

if ($cart_result->num_rows === 0) {
    header("Location: cart.php");
    exit();
}

$cart_items = $cart_result->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = 5.00;
$tax = $subtotal * 0.10;
$grand_total = $subtotal + $shipping + $tax;

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'card');
    $card_number = trim($_POST['card_number'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv = trim($_POST['card_cvv'] ?? '');
    $card_name = trim($_POST['card_name'] ?? '');
    
    if (empty($shipping_address)) {
        $message = "<div class='alert alert-danger'>Please enter shipping address</div>";
    } elseif (empty($card_number) || strlen(str_replace(' ', '', $card_number)) < 16) {
        $message = "<div class='alert alert-danger'>Please enter valid card number</div>";
    } elseif (empty($card_expiry)) {
        $message = "<div class='alert alert-danger'>Please enter card expiry date</div>";
    } elseif (empty($card_cvv) || strlen($card_cvv) < 3) {
        $message = "<div class='alert alert-danger'>Please enter valid CVV</div>";
    } elseif (empty($card_name)) {
        $message = "<div class='alert alert-danger'>Please enter cardholder name</div>";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // 1. Check stock availability
            $stock_errors = [];
            foreach ($cart_items as $item) {
                if ($item['quantity'] > $item['stock']) {
                    $stock_errors[] = "Only {$item['stock']} units of '{$item['name']}' available";
                }
            }
            
            if (!empty($stock_errors)) {
                throw new Exception(implode("<br>", $stock_errors));
            }
            
            // 2. Create order - USING CORRECT COLUMN NAMES FROM YOUR SCHEMA
            $order_sql = "INSERT INTO orders (user_id_fk, total_price_fk, shipping_address, order_status, payment_status, created_at) 
                         VALUES (?, ?, ?, 'Pending', 'Unpaid', NOW())";
            $order_stmt = $conn->prepare($order_sql);
            if (!$order_stmt) {
                throw new Exception("Failed to prepare order statement: " . $conn->error);
            }
            $order_stmt->bind_param("ids", $user_id, $grand_total, $shipping_address);
            if (!$order_stmt->execute()) {
                throw new Exception("Failed to execute order statement: " . $order_stmt->error);
            }
            $order_id = $order_stmt->insert_id;
            $order_stmt->close();
            
            // 3. Insert order items and update stock
            foreach ($cart_items as $item) {
                // Insert order item
                $order_item_sql = "INSERT INTO order_items (order_id_fk, product_id_fk, quantity, unit_price, payment_status) 
                                  VALUES (?, ?, ?, ?, 'Unpaid')";
                $order_item_stmt = $conn->prepare($order_item_sql);
                if (!$order_item_stmt) {
                    throw new Exception("Failed to prepare order item statement: " . $conn->error);
                }
                $order_item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                if (!$order_item_stmt->execute()) {
                    throw new Exception("Failed to execute order item statement: " . $order_item_stmt->error);
                }
                $order_item_stmt->close();
                
                // Update product stock
                $new_stock = $item['stock'] - $item['quantity'];
                $update_stock_sql = "UPDATE products SET stock = ? WHERE product_id = ?";
                $update_stmt = $conn->prepare($update_stock_sql);
                if (!$update_stmt) {
                    throw new Exception("Failed to prepare stock update statement");
                }
                $update_stmt->bind_param("ii", $new_stock, $item['product_id']);
                if (!$update_stmt->execute()) {
                    throw new Exception("Failed to update stock");
                }
                $update_stmt->close();
            }
            
            // 4. Clear cart
            $clear_cart_sql = "DELETE FROM cart WHERE user_id_fk = ?";
            $clear_stmt = $conn->prepare($clear_cart_sql);
            if (!$clear_stmt) {
                throw new Exception("Failed to prepare clear cart statement");
            }
            $clear_stmt->bind_param("i", $user_id);
            if (!$clear_stmt->execute()) {
                throw new Exception("Failed to clear cart");
            }
            $clear_stmt->close();
            
            // 5. Create payment record - ADJUSTED TO MATCH YOUR SCHEMA
            $payment_sql = "INSERT INTO payments (order_id_fk, payment_method, amount, payment_status, payment_date) 
                           VALUES (?, ?, ?, 'Pending', NOW())";
            $payment_stmt = $conn->prepare($payment_sql);
            if (!$payment_stmt) {
                throw new Exception("Failed to prepare payment statement");
            }
            $payment_stmt->bind_param("isd", $order_id, $payment_method, $grand_total);
            if (!$payment_stmt->execute()) {
                throw new Exception("Failed to create payment record");
            }
            $payment_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Success message
            $message = "<div class='alert alert-success'>
                <i class='fas fa-check-circle'></i>
                <strong>Order Placed Successfully!</strong><br>
                Order #{$order_id} has been confirmed. Total: $" . number_format($grand_total, 2) . "
            </div>";
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert alert-danger'>
                <i class='fas fa-exclamation-circle'></i>
                <strong>Order Failed!</strong><br>
                " . htmlspecialchars($e->getMessage()) . "
            </div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Your CSS styles here (same as before) */
        :root {
            --primary-color: #8a2be2;
            --secondary-color: #5d3fd3;
            --accent-color: #ff6b8b;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-color: #e0e0e0;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.15);
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%); color: var(--dark-color); line-height: 1.6; min-height: 100vh; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Playfair Display', serif; font-weight: 600; }
        .checkout-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .checkout-header { text-align: center; margin-bottom: 40px; }
        .checkout-title { font-size: 2.8rem; font-weight: 700; color: var(--primary-color); margin-bottom: 10px; }
        .checkout-steps { display: flex; justify-content: center; gap: 30px; margin-top: 30px; }
        .step { display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .step-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; background: var(--light-color); color: var(--gray-color); border: 2px solid var(--border-color); }
        .step.active .step-circle { background: var(--primary-color); color: white; border-color: var(--primary-color); }
        .step-label { font-size: 0.9rem; color: var(--gray-color); }
        .step.active .step-label { color: var(--primary-color); font-weight: 600; }
        .checkout-content { display: grid; grid-template-columns: 1fr 400px; gap: 30px; }
        @media (max-width: 992px) { .checkout-content { grid-template-columns: 1fr; } }
        .order-summary-card { background: white; border-radius: 20px; padding: 30px; box-shadow: var(--shadow-md); border: 1px solid var(--border-color); position: sticky; top: 30px; height: fit-content; }
        .summary-header { margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid var(--border-color); }
        .summary-title { font-size: 1.8rem; font-weight: 600; color: var(--dark-color); }
        .order-items { max-height: 300px; overflow-y: auto; margin-bottom: 25px; }
        .order-item { display: flex; gap: 15px; padding: 15px 0; border-bottom: 1px solid var(--border-color); }
        .order-item:last-child { border-bottom: none; }
        .item-image { width: 80px; height: 80px; border-radius: 10px; overflow: hidden; flex-shrink: 0; }
        .item-image img { width: 100%; height: 100%; object-fit: cover; }
        .item-details { flex: 1; }
        .item-name { font-weight: 600; margin-bottom: 5px; color: var(--dark-color); }
        .item-price { color: var(--primary-color); font-weight: 600; }
        .item-quantity { color: var(--gray-color); font-size: 0.9rem; }
        .price-breakdown { margin-bottom: 25px; }
        .price-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed var(--border-color); }
        .price-label { color: var(--gray-color); }
        .price-value { font-weight: 600; color: var(--dark-color); }
        .total-row { padding: 20px 0; margin-top: 10px; border-top: 2px solid var(--border-color); border-bottom: none; }
        .total-label { font-size: 1.3rem; font-weight: 600; }
        .total-value { font-size: 2rem; font-weight: 700; color: var(--primary-color); }
        .checkout-form-container { background: white; border-radius: 20px; padding: 30px; box-shadow: var(--shadow-md); border: 1px solid var(--border-color); }
        .form-section { margin-bottom: 35px; padding-bottom: 30px; border-bottom: 2px solid var(--border-color); }
        .form-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .section-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 25px; color: var(--dark-color); display: flex; align-items: center; gap: 10px; }
        .section-title i { color: var(--primary-color); }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; color: var(--dark-color); font-weight: 500; font-size: 0.95rem; }
        .form-control { width: 100%; padding: 14px; border: 2px solid var(--border-color); border-radius: 12px; font-size: 1rem; font-weight: 400; color: var(--dark-color); transition: var(--transition); background: var(--light-color); }
        .form-control:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.1); background: white; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 576px) { .form-row { grid-template-columns: 1fr; } }
        .payment-methods { display: flex; gap: 15px; margin-top: 15px; }
        .payment-method { flex: 1; text-align: center; }
        .payment-radio { display: none; }
        .payment-label { display: block; padding: 15px; border: 2px solid var(--border-color); border-radius: 12px; cursor: pointer; transition: var(--transition); }
        .payment-radio:checked + .payment-label { border-color: var(--primary-color); background: rgba(138, 43, 226, 0.05); }
        .payment-icon { font-size: 1.5rem; color: var(--primary-color); margin-bottom: 8px; }
        .payment-name { font-size: 0.9rem; font-weight: 500; }
        .btn { padding: 16px 28px; border: none; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; box-shadow: 0 4px 20px rgba(138, 43, 226, 0.25); }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(138, 43, 226, 0.35); }
        .btn-secondary { background: white; color: var(--primary-color); border: 2px solid var(--border-color); margin-top: 15px; }
        .btn-secondary:hover { border-color: var(--primary-color); color: var(--primary-color); transform: translateY(-3px); box-shadow: var(--shadow-sm); }
        .success-container { text-align: center; padding: 50px 30px; }
        .success-icon { font-size: 5rem; color: var(--success-color); margin-bottom: 20px; }
        .success-title { font-size: 2.5rem; margin-bottom: 15px; color: var(--dark-color); }
        .success-text { color: var(--gray-color); max-width: 600px; margin: 0 auto 30px; font-size: 1.1rem; }
        .order-details { background: var(--light-color); border-radius: 15px; padding: 25px; max-width: 500px; margin: 30px auto; text-align: left; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-color); }
        .detail-row:last-child { border-bottom: none; }
        @media (max-width: 768px) {
            .checkout-container { margin: 20px auto; padding: 0 15px; }
            .checkout-title { font-size: 2.2rem; }
            .checkout-steps { gap: 15px; }
            .order-summary-card, .checkout-form-container { padding: 25px 20px; }
        }
        .alert { border-radius: 12px; border: none; box-shadow: var(--shadow-sm); margin-bottom: 30px; }
        .alert i { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="checkout-container">
        <!-- Header -->
        <div class="checkout-header">
            <h1 class="checkout-title">
                <i class="fas fa-lock me-3"></i>
                Secure Checkout
            </h1>
            
            <div class="checkout-steps">
                <div class="step active">
                    <div class="step-circle">1</div>
                    <span class="step-label">Cart</span>
                </div>
                <div class="step active">
                    <div class="step-circle">2</div>
                    <span class="step-label">Checkout</span>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <span class="step-label">Confirmation</span>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-4"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (isset($order_id)): ?>
            <!-- Order Confirmation -->
            <div class="success-container">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="success-title">Order Confirmed!</h2>
                <p class="success-text">
                    Thank you for your purchase. Your order has been successfully placed and is being processed.
                </p>
                
                <div class="order-details">
                    <div class="detail-row">
                        <span>Order Number:</span>
                        <strong>#<?php echo $order_id; ?></strong>
                    </div>
                    <div class="detail-row">
                        <span>Total Amount:</span>
                        <strong>$<?php echo number_format($grand_total, 2); ?></strong>
                    </div>
                    <div class="detail-row">
                        <span>Payment Status:</span>
                        <span class="text-success">Completed</span>
                    </div>
                    <div class="detail-row">
                        <span>Order Status:</span>
                        <span class="text-warning">Processing</span>
                    </div>
                </div>
                
                <a href="orders.php" class="btn btn-primary" style="width: auto; max-width: 300px; margin: 0 auto;">
                    <i class="fas fa-box me-2"></i>
                    View My Orders
                </a>
                <a href="products.php" class="btn btn-secondary" style="width: auto; max-width: 300px; margin: 20px auto 0;">
                    <i class="fas fa-store me-2"></i>
                    Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Checkout Form -->
            <div class="checkout-content">
                <!-- Order Summary -->
                <div class="order-summary-card">
                    <div class="summary-header">
                        <h3 class="summary-title">Order Summary</h3>
                    </div>
                    
                    <div class="order-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <img src="<?php echo !empty($item['image_url']) ? $item['image_url'] : 'https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="item-details">
                                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="item-price">$<?php echo number_format($item['price'], 2); ?></div>
                                    <div class="item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span class="price-label">Subtotal</span>
                            <span class="price-value">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="price-row">
                            <span class="price-label">Shipping</span>
                            <span class="price-value">$<?php echo number_format($shipping, 2); ?></span>
                        </div>
                        <div class="price-row">
                            <span class="price-label">Tax (10%)</span>
                            <span class="price-value">$<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div class="price-row total-row">
                            <span class="total-label">Total</span>
                            <span class="total-value">$<?php echo number_format($grand_total, 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Checkout Form -->
                <div class="checkout-form-container">
                    <form method="POST" id="checkoutForm">
                        <!-- Shipping Information -->
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="fas fa-shipping-fast"></i>
                                Shipping Information
                            </h4>
                            
                            <div class="form-group">
                                <label for="shipping_address" class="form-label">Shipping Address *</label>
                                <textarea name="shipping_address" id="shipping_address" class="form-control" rows="4" 
                                          required placeholder="Enter your complete shipping address"></textarea>
                            </div>
                        </div>
                        
                        <!-- Payment Information -->
                        <div class="form-section">
                            <h4 class="section-title">
                                <i class="fas fa-credit-card"></i>
                                Payment Details
                            </h4>
                            
                            <div class="payment-methods">
                                <div class="payment-method">
                                    <input type="radio" name="payment_method" value="card" id="card" class="payment-radio" checked>
                                    <label for="card" class="payment-label">
                                        <i class="fas fa-credit-card payment-icon"></i>
                                        <div class="payment-name">Card</div>
                                    </label>
                                </div>
                                <div class="payment-method">
                                    <input type="radio" name="payment_method" value="paypal" id="paypal" class="payment-radio">
                                    <label for="paypal" class="payment-label">
                                        <i class="fab fa-paypal payment-icon"></i>
                                        <div class="payment-name">PayPal</div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="card_name" class="form-label">Cardholder Name *</label>
                                <input type="text" name="card_name" id="card_name" class="form-control" 
                                       placeholder="John Doe" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="card_number" class="form-label">Card Number *</label>
                                    <input type="text" name="card_number" id="card_number" class="form-control" 
                                           placeholder="1234 5678 9012 3456" maxlength="19" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="card_expiry" class="form-label">Expiry Date *</label>
                                    <input type="text" name="card_expiry" id="card_expiry" class="form-control" 
                                           placeholder="MM/YY" maxlength="5" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="card_cvv" class="form-label">CVV *</label>
                                    <input type="password" name="card_cvv" id="card_cvv" class="form-control" 
                                           placeholder="123" maxlength="4" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms and Conditions -->
                        <div class="form-section">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> 
                                        and <a href="privacy.php" target="_blank">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-lock me-2"></i>
                            Place Order ($<?php echo number_format($grand_total, 2); ?>)
                        </button>
                        
                        <a href="cart.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to Cart
                        </a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Format card number
    document.getElementById('card_number').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        let formatted = value.replace(/(\d{4})/g, '$1 ').trim();
        e.target.value = formatted.substring(0, 19);
    });
    
    // Format expiry date
    document.getElementById('card_expiry').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        e.target.value = value.substring(0, 5);
    });
    
    // Validate form
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        const cardNumber = document.getElementById('card_number').value.replace(/\s+/g, '');
        const cardExpiry = document.getElementById('card_expiry').value;
        const cardCVV = document.getElementById('card_cvv').value;
        const cardName = document.getElementById('card_name').value;
        
        if (cardNumber.length !== 16) {
            e.preventDefault();
            alert('Please enter a valid 16-digit card number');
            return false;
        }
        
        if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) {
            e.preventDefault();
            alert('Please enter expiry date in MM/YY format');
            return false;
        }
        
        if (cardCVV.length < 3) {
            e.preventDefault();
            alert('Please enter valid CVV (3-4 digits)');
            return false;
        }
        
        if (cardName.trim() === '') {
            e.preventDefault();
            alert('Please enter cardholder name');
            return false;
        }
    });
    </script>
</body>
</html>

<?php 
// Close connections
if (isset($cart_stmt)) $cart_stmt->close();
if (isset($conn)) $conn->close();
?>