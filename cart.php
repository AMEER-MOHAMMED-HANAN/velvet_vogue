<?php
// Start session at the very beginning
session_start();

// Initialize variables
$message = "";
$cart_items = [];
$total = 0;
$item_count = 0;

// Set user_id for testing (remove this when you have proper login system)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Use user_id = 1 for testing
}
$user_id = (int)$_SESSION['user_id'];

// Include database connection
include 'includes/db_connect.php';

// Check if database connection is successful
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Remove item from cart
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $item_id = (int)$_GET['remove'];
    $remove_sql = "DELETE FROM cart WHERE cart_id = ? AND user_id_fk = ?";
    $stmt = $conn->prepare($remove_sql);
    if ($stmt) {
        $stmt->bind_param("ii", $item_id, $user_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                            <i class='fas fa-check-circle me-2'></i>Item removed successfully!
                            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                        </div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                            <i class='fas fa-exclamation-circle me-2'></i>Error removing item.
                            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                        </div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        <i class='fas fa-exclamation-circle me-2'></i>Database error.
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>";
    }
}

// Fetch cart items
$cart_sql = "SELECT 
                c.cart_id,
                c.quantity,
                p.product_id,
                p.name,
                p.description,
                p.price,
                p.stock,
                pi.image_url
            FROM cart c 
            JOIN products p ON c.product_id_fk = p.product_id 
            LEFT JOIN product_images pi ON p.product_id = pi.product_id_fk
            WHERE c.user_id_fk = ?
            GROUP BY p.product_id";
            
$stmt = $conn->prepare($cart_sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        $cart_items = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// Calculate totals
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
    $item_count += $item['quantity'];
}

// Calculate shipping and tax
$shipping = ($total > 0) ? 5.00 : 0;
$tax = $total * 0.10;
$grand_total = $total + $shipping + $tax;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Velvet Vogue</title>
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
            color: var(--dark-color);
        }

        /* Cart Container */
        .cart-wrapper {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Header */
        .cart-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .cart-title {
            font-size: 3.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }

        .cart-title:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }

        .cart-subtitle {
            font-size: 1.1rem;
            color: var(--gray-color);
            margin-top: 20px;
        }

        /* Cart Content */
        .cart-content {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .cart-content {
                grid-template-columns: 1fr;
            }
        }

        /* Cart Items Section */
        .cart-items-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .cart-stats {
            display: flex;
            gap: 20px;
            font-size: 0.95rem;
            color: var(--gray-color);
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-value {
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Empty Cart */
        .empty-cart {
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
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--dark-color);
        }

        .empty-text {
            color: var(--gray-color);
            max-width: 500px;
            margin: 0 auto 30px;
        }

        /* Cart Items List */
        .cart-items-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Cart Item Card */
        .cart-item-card {
            background: var(--light-color);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .cart-item-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-sm);
        }

        .cart-item-content {
            display: flex;
            gap: 25px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .cart-item-content {
                flex-direction: column;
                text-align: center;
            }
        }

        .product-image {
            width: 140px;
            height: 140px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .cart-item-card:hover .product-image img {
            transform: scale(1.05);
        }

        .product-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--accent-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .product-details {
            flex: 1;
        }

        .product-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark-color);
        }

        .product-description {
            color: var(--gray-color);
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .product-price {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .product-meta {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            color: var(--gray-color);
            margin-bottom: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meta-item i {
            color: var(--primary-color);
        }

        /* Quantity Controls */
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            border-radius: 10px;
            padding: 8px 15px;
            border: 2px solid var(--border-color);
        }

        .qty-btn {
            background: none;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .qty-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .qty-input {
            width: 50px;
            text-align: center;
            border: none;
            background: transparent;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .remove-btn {
            background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remove-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .item-total {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--success-color);
            margin-left: auto;
        }

        /* Order Summary */
        .order-summary {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            position: sticky;
            top: 30px;
            height: fit-content;
        }

        .summary-header {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .summary-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        /* Summary Items */
        .summary-items {
            margin-bottom: 25px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .summary-label {
            color: var(--gray-color);
            font-size: 0.95rem;
        }

        .summary-value {
            font-weight: 600;
            color: var(--dark-color);
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-top: 10px;
            border-top: 2px solid var(--border-color);
        }

        .total-label {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .total-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        /* Checkout Form */
        .checkout-form {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 400;
            color: var(--dark-color);
            transition: var(--transition);
            background: var(--light-color);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.1);
            background: white;
        }

        /* Buttons */
        .btn {
            padding: 16px 28px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
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

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--border-color);
            margin-top: 15px;
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: var(--shadow-sm);
        }

        /* Footer */
        .cart-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cart-wrapper {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .cart-title {
                font-size: 2.5rem;
            }
            
            .cart-items-section,
            .order-summary {
                padding: 25px 20px;
            }
            
            .section-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .quantity-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .item-total {
                margin-left: 0;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .cart-title {
                font-size: 2rem;
            }
            
            .section-title,
            .summary-title {
                font-size: 1.5rem;
            }
            
            .product-name {
                font-size: 1.2rem;
            }
            
            .product-price {
                font-size: 1.4rem;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .cart-item-card {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <div class="cart-wrapper">
        <!-- Cart Header -->
        <div class="cart-header">
            <h1 class="cart-title">
                <i class="fas fa-shopping-cart me-3"></i>
                Your Shopping Bag
            </h1>
            <p class="cart-subtitle">
                Review your selected items and proceed to secure checkout
            </p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-4"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <div class="empty-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h2 class="empty-title">Your shopping bag is empty</h2>
                <p class="empty-text">
                    You haven't added any fashion items to your bag yet. 
                    Explore our latest collections and find your perfect style.
                </p>
                <a href="products.php" class="btn btn-primary" style="width: auto; max-width: 300px; margin: 0 auto;">
                    <i class="fas fa-store me-2"></i>
                    Discover Collections
                </a>
            </div>
        <?php else: ?>
            <!-- Cart Content -->
            <div class="cart-content">
                <!-- Cart Items -->
                <div class="cart-items-section">
                    <div class="section-header">
                        <h3 class="section-title">Shopping Bag</h3>
                        <div class="cart-stats">
                            <div class="stat-item">
                                <i class="fas fa-box"></i>
                                <span>Items: <span class="stat-value"><?php echo $item_count; ?></span></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-layer-group"></i>
                                <span>Products: <span class="stat-value"><?php echo count($cart_items); ?></span></span>
                            </div>
                        </div>
                    </div>

                    <div class="cart-items-list">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item-card">
                                <div class="product-badge">In Stock</div>
                                
                                <div class="cart-item-content">
                                    <!-- Product Image -->
                                    <div class="product-image">
                                        <img src="<?php echo !empty($item['image_url']) ? $item['image_url'] : 'https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80'; ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             onerror="this.src='https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80'">
                                    </div>

                                    <!-- Product Details -->
                                    <div class="product-details">
                                        <h4 class="product-name"><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p class="product-description"><?php echo htmlspecialchars(substr($item['description'] ?? 'Premium quality product', 0, 100)); ?>...</p>
                                        
                                        <div class="product-price">
                                            $<?php echo number_format($item['price'], 2); ?>
                                        </div>

                                        <div class="product-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-cubes"></i>
                                                <span>Stock: <?php echo $item['stock']; ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-tag"></i>
                                                <span>Item #<?php echo $item['product_id']; ?></span>
                                            </div>
                                        </div>

                                        <!-- Quantity Controls -->
                                        <div class="quantity-controls">
                                            <div class="quantity-selector">
                                                <button class="qty-btn" onclick="updateQuantity(<?php echo $item['cart_id']; ?>, -1)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="qty-input" 
                                                       value="<?php echo $item['quantity']; ?>" 
                                                       id="quantity_<?php echo $item['cart_id']; ?>" 
                                                       min="1" max="<?php echo $item['stock']; ?>" readonly>
                                                <button class="qty-btn" onclick="updateQuantity(<?php echo $item['cart_id']; ?>, 1)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>

                                            <a href="cart.php?remove=<?php echo $item['cart_id']; ?>" 
                                               class="remove-btn">
                                                <i class="fas fa-trash-alt"></i>
                                                Remove
                                            </a>

                                            <div class="item-total">
                                                $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="order-summary">
                    <div class="summary-header">
                        <h3 class="summary-title">Order Summary</h3>
                    </div>

                    <div class="summary-items">
                        <div class="summary-row">
                            <span class="summary-label">Subtotal</span>
                            <span class="summary-value">$<?php echo number_format($total, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Shipping</span>
                            <span class="summary-value">$<?php echo number_format($shipping, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Tax (10%)</span>
                            <span class="summary-value">$<?php echo number_format($tax, 2); ?></span>
                        </div>
                    </div>

                    <div class="summary-total">
                        <span class="total-label">Total Amount</span>
                        <span class="total-value">$<?php echo number_format($grand_total, 2); ?></span>
                    </div>

                    <!-- Checkout Form -->
                    <form method="POST" action="checkout.php" class="checkout-form">
                        <div class="form-group">
                            <label for="shipping_address" class="form-label">
                                <i class="fas fa-map-marker-alt me-2"></i>Shipping Address
                            </label>
                            <textarea name="shipping_address" id="shipping_address" class="form-control" rows="4" 
                                      required placeholder="Enter your complete shipping address..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-lock me-2"></i>
                            Secure Checkout ($<?php echo number_format($grand_total, 2); ?>)
                        </button>
                        
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Continue Shopping
                        </a>
                    </form>
                </div>
            </div>

            <!-- Cart Footer -->
            <div class="cart-footer">
                <p>
                    <i class="fas fa-shield-alt me-2"></i>
                    Secure checkout • Free shipping on orders over $50 • 30-day return policy
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function updateQuantity(cartId, change) {
        const quantityInput = document.getElementById('quantity_' + cartId);
        let currentQuantity = parseInt(quantityInput.value);
        const maxQuantity = parseInt(quantityInput.max);
        const minQuantity = parseInt(quantityInput.min);
        
        currentQuantity += change;
        
        if (currentQuantity < minQuantity) {
            currentQuantity = minQuantity;
        }
        
        if (currentQuantity > maxQuantity) {
            alert('Cannot add more than ' + maxQuantity + ' items (stock limited)');
            currentQuantity = maxQuantity;
        }
        
        if (currentQuantity === 0) {
            if (confirm('Remove this item from cart?')) {
                window.location.href = 'cart.php?remove=' + cartId;
            }
            return;
        }
        
        // Update via AJAX
        fetch('update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                cart_id: cartId, 
                quantity: currentQuantity 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                quantityInput.value = currentQuantity;
                // Reload page to update totals
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                alert(data.message || 'Error updating quantity');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating quantity');
        });
    }
    
    // Confirm before removing item
    document.addEventListener('DOMContentLoaded', function() {
        const removeLinks = document.querySelectorAll('.remove-btn');
        removeLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to remove this item from your shopping bag?')) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
</body>
</html>

<?php 
// Close database connection
if (isset($conn)) {
    $conn->close();
}