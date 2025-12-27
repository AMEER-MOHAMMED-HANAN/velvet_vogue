<?php
include 'includes/db_connect.php';
session_start();

// Initialize variables
$message = "";

// Initialize variables for header.php
$logged_in_user = isset($_SESSION['email']) ? $_SESSION['email'] : null;
$cart_count = 0;

// Get cart count if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        $user_id = $_SESSION['user_id'];
        $cart_sql = "SELECT SUM(quantity) as total_items FROM cart WHERE user_id_fk = ?";
        $cart_stmt = $conn->prepare($cart_sql);
        if ($cart_stmt) {
            $cart_stmt->bind_param("i", $user_id);
            $cart_stmt->execute();
            $cart_result = $cart_stmt->get_result();
            if ($cart_row = $cart_result->fetch_assoc()) {
                $cart_count = $cart_row['total_items'] ?: 0;
            }
            $cart_stmt->close();
        }
    } catch (Exception $e) {
        $cart_count = 0;
    }
}

// ADD TO CART FUNCTIONALITY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        $message = "<div class='alert alert-warning'>Please login to add items to cart</div>";
    } else {
        $user_id = $_SESSION['user_id'];
        $product_id = (int)$_POST['product_id'];
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        
        // Check if product exists and has stock
        $check_sql = "SELECT stock, name FROM products WHERE product_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $product_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $message = "<div class='alert alert-danger'>Product not found</div>";
        } else {
            $product = $check_result->fetch_assoc();
            
            // Check if product already in cart
            $cart_check_sql = "SELECT cart_id, quantity FROM cart WHERE user_id_fk = ? AND product_id_fk = ?";
            $cart_check_stmt = $conn->prepare($cart_check_sql);
            $cart_check_stmt->bind_param("ii", $user_id, $product_id);
            $cart_check_stmt->execute();
            $cart_check_result = $cart_check_stmt->get_result();
            
            if ($cart_check_result->num_rows > 0) {
                // Update existing item
                $cart_item = $cart_check_result->fetch_assoc();
                $new_quantity = $cart_item['quantity'] + $quantity;
                
                if ($new_quantity > $product['stock']) {
                    $message = "<div class='alert alert-warning'>Cannot add more. Only " . $product['stock'] . " items in stock</div>";
                } else {
                    $update_sql = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ii", $new_quantity, $cart_item['cart_id']);
                    
                    if ($update_stmt->execute()) {
                        $message = "<div class='alert alert-success'>" . $product['name'] . " quantity updated in cart</div>";
                    } else {
                        $message = "<div class='alert alert-danger'>Failed to update cart</div>";
                    }
                    $update_stmt->close();
                }
            } else {
                // Add new item to cart
                if ($quantity > $product['stock']) {
                    $message = "<div class='alert alert-warning'>Only " . $product['stock'] . " items available</div>";
                } else {
                    $insert_sql = "INSERT INTO cart (user_id_fk, product_id_fk, quantity) VALUES (?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
                    
                    if ($insert_stmt->execute()) {
                        $message = "<div class='alert alert-success'>" . $product['name'] . " added to cart successfully</div>";
                    } else {
                        $message = "<div class='alert alert-danger'>Failed to add to cart</div>";
                    }
                    $insert_stmt->close();
                }
            }
            $cart_check_stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch categories for filter
$cat_sql = "SELECT category_id, name FROM categories";
$cat_stmt = $conn->prepare($cat_sql);
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();
$categories = $cat_result->fetch_all(MYSQLI_ASSOC);
$cat_stmt->close();

// Build product query with filters
$where = "1=1";
$params = [];
$types = "";

// Category filter
if (isset($_GET['category']) && $_GET['category'] != "") {
    $where .= " AND p.category_id_fk = ?";
    $params[] = (int)$_GET['category'];
    $types .= "i";
}

// Search filter
$search = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchParam = "%" . $search . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

// Sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
$order_by = "p.name ASC";
switch ($sort) {
    case 'price_asc':
        $order_by = "p.price ASC";
        break;
    case 'price_desc':
        $order_by = "p.price DESC";
        break;
    case 'newest':
        $order_by = "p.product_id DESC";
        break;
    default:
        $order_by = "p.name ASC";
}

// Fetch products with GROUP BY to avoid duplicates
$sql = "SELECT p.product_id, p.name, p.description, p.price, p.stock, 
               c.name as category_name,
               GROUP_CONCAT(DISTINCT pi.image_url) as images
        FROM products p 
        LEFT JOIN product_images pi ON p.product_id = pi.product_id_fk
        LEFT JOIN categories c ON p.category_id_fk = c.category_id
        WHERE $where
        GROUP BY p.product_id
        ORDER BY $order_by";
        
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$products = [];

while ($row = $result->fetch_assoc()) {
    // Convert images string to array
    if ($row['images']) {
        $row['image_array'] = explode(',', $row['images']);
    } else {
        $row['image_array'] = ['https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80'];
    }
    $products[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --highlight: #e94560;
            --light: #f8f9fa;
            --dark: #121212;
            --gray: #8a8a8a;
            --gold: #ffd700;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 8px 30px rgba(0,0,0,0.12);
            --shadow-lg: 0 20px 60px rgba(0,0,0,0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            line-height: 1.2;
        }

        /* Page Container */
        .page-container {
            min-height: 100vh;
            padding-top: 100px;
            padding-bottom: 100px;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 60px;
            position: relative;
        }

        .page-title {
            font-size: 4rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 4px;
            background: linear-gradient(90deg, var(--highlight), #ff6b8b);
            border-radius: 2px;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
            font-weight: 400;
        }

        /* Modern Filter Section */
        .modern-filter-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 60px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .modern-filter-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--highlight), var(--accent));
        }

        .filter-main {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .filter-group {
            flex: 1;
        }

        .filter-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Search Box */
        .search-box-wrapper {
            position: relative;
            min-width: 300px;
        }

        .search-box {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            background: white;
            color: var(--primary);
            transition: var(--transition);
        }

        .search-box:focus {
            outline: none;
            border-color: var(--highlight);
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 1.1rem;
        }

        /* Category Select */
        .category-select-wrapper {
            min-width: 250px;
        }

        .category-select {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            background: white;
            color: var(--primary);
            cursor: pointer;
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%231a1a2e' viewBox='0 0 16 16'%3E%3Cpath d='M8 11.5l4-4H4l4 4z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            padding-right: 50px;
        }

        .category-select:focus {
            outline: none;
            border-color: var(--highlight);
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }

        /* Sort Select */
        .sort-select-wrapper {
            min-width: 200px;
        }

        .sort-select {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            background: white;
            color: var(--primary);
            cursor: pointer;
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%231a1a2e' viewBox='0 0 16 16'%3E%3Cpath d='M8 11.5l4-4H4l4 4z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            padding-right: 50px;
        }

        .sort-select:focus {
            outline: none;
            border-color: var(--highlight);
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 15px;
            align-self: flex-end;
        }

        .btn-filter {
            padding: 15px 35px;
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
        }

        .btn-apply {
            background: linear-gradient(135deg, var(--highlight), #ff6b8b);
            color: white;
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
        }

        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 69, 96, 0.4);
        }

        .btn-reset {
            background: white;
            color: var(--primary);
            border: 2px solid #e0e0e0;
            text-decoration: none;
        }

        .btn-reset:hover {
            border-color: var(--highlight);
            color: var(--highlight);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* Search Results Header */
        .search-results-header {
            margin-bottom: 40px;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 15px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-md);
        }

        .search-results-info h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .search-results-count {
            font-size: 1rem;
            opacity: 0.9;
        }

        .clear-search-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clear-search-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            color: white;
        }

        /* Modern Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 80px;
        }

        /* Modern Product Card */
        .modern-product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            position: relative;
            border: 1px solid rgba(0,0,0,0.05);
            height: 100%;
        }

        .modern-product-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .product-image-wrapper {
            position: relative;
            height: 280px;
            overflow: hidden;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .modern-product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, transparent 60%, rgba(0,0,0,0.3));
        }

        .product-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--highlight);
            color: white;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            z-index: 2;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
        }

        .product-content {
            padding: 25px;
            position: relative;
        }

        .product-header {
            margin-bottom: 15px;
        }

        .product-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .product-category {
            display: inline-block;
            background: #f0f8ff;
            color: var(--accent);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 12px;
            border: 1px solid #d1e3ff;
        }

        .product-description {
            color: var(--gray);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .product-price {
            font-size: 2rem;
            font-weight: 800;
            color: var(--highlight);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .product-price span {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray);
            margin-left: 5px;
        }

        .stock-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stock-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .stock-in .stock-dot {
            background: #27ae60;
        }

        .stock-low .stock-dot {
            background: #f39c12;
        }

        .stock-out .stock-dot {
            background: #e74c3c;
        }

        .stock-text {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stock-in .stock-text {
            color: #27ae60;
        }

        .stock-low .stock-text {
            color: #f39c12;
        }

        .stock-out .stock-text {
            color: #e74c3c;
        }

        .product-actions {
            display: flex;
            gap: 12px;
        }

        .btn-view {
            flex: 1;
            padding: 14px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            text-align: center;
        }

        .btn-view:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(26, 26, 46, 0.2);
            color: white;
        }

        /* Add to Cart Button */
        .btn-cart {
            width: 50px;
            height: 50px;
            background: var(--highlight);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .btn-cart:hover {
            background: #ff6b8b;
            transform: translateY(-2px) rotate(5deg);
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
        }

        .btn-cart i {
            font-size: 1.2rem;
        }

        /* Quantity Selector */
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 8px 15px;
            border: 2px solid #e0e0e0;
        }

        .qty-btn {
            background: none;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .qty-btn:hover {
            background: var(--highlight);
            color: white;
        }

        .qty-input {
            width: 40px;
            text-align: center;
            border: none;
            background: transparent;
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
        }

        /* Add to Cart Form */
        .add-to-cart-form {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* No Products State */
        .no-products-container {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            grid-column: 1 / -1;
            border: 2px dashed #e0e0e0;
        }

        .no-products-icon {
            font-size: 5rem;
            color: var(--highlight);
            margin-bottom: 30px;
            opacity: 0.7;
        }

        .no-products-title {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 2.2rem;
        }

        .no-products-text {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Messages Styling */
        .message-container {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
        }

        .alert {
            animation: slideInRight 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .filter-main {
                flex-wrap: wrap;
            }
            
            .search-box-wrapper,
            .category-select-wrapper,
            .sort-select-wrapper {
                flex: 1 1 calc(33.333% - 20px);
                min-width: auto;
            }
        }

        @media (max-width: 992px) {
            .page-title {
                font-size: 3rem;
            }
            
            .filter-main {
                flex-direction: column;
                gap: 20px;
            }
            
            .search-box-wrapper,
            .category-select-wrapper,
            .sort-select-wrapper {
                width: 100%;
                flex: 1 1 100%;
            }
            
            .filter-actions {
                justify-content: center;
                width: 100%;
            }
            
            .add-to-cart-form {
                flex-direction: column;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .page-container {
                padding-top: 80px;
                padding-bottom: 80px;
            }
            
            .page-title {
                font-size: 2.5rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }
            
            .product-image-wrapper {
                height: 240px;
            }
            
            .filter-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-filter {
                width: 100%;
                justify-content: center;
            }
            
            .search-results-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 2rem;
            }
            
            .page-subtitle {
                font-size: 1rem;
            }
            
            .modern-filter-section {
                padding: 20px;
            }
            
            .product-content {
                padding: 20px;
            }
            
            .product-title {
                font-size: 1.2rem;
            }
            
            .product-price {
                font-size: 1.8rem;
            }
            
            .product-actions {
                flex-direction: column;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modern-product-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        .modern-product-card:nth-child(1) { animation-delay: 0.1s; }
        .modern-product-card:nth-child(2) { animation-delay: 0.2s; }
        .modern-product-card:nth-child(3) { animation-delay: 0.3s; }
        .modern-product-card:nth-child(4) { animation-delay: 0.4s; }
        .modern-product-card:nth-child(5) { animation-delay: 0.5s; }
        .modern-product-card:nth-child(6) { animation-delay: 0.6s; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--highlight);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #ff6b8b;
        }
    </style>
</head>
<body>
    <!-- Message Container -->
    <?php if ($message): ?>
        <div class="message-container">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Main Container -->
    <div class="container page-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Our Collection</h1>
            <p class="page-subtitle">Discover exquisite fashion pieces curated with luxury and elegance in mind</p>
        </div>

        <!-- Display Search Results Info -->
        <?php if (!empty($search)): ?>
            <div class="search-results-header">
                <div class="search-results-info">
                    <h3>Search Results for "<?php echo htmlspecialchars($search); ?>"</h3>
                    <div class="search-results-count">
                        Found <?php echo count($products); ?> product<?php echo count($products) != 1 ? 's' : ''; ?>
                        <?php if (isset($_GET['category']) && $_GET['category'] != ""): ?>
                            in <?php echo htmlspecialchars($categories[array_search($_GET['category'], array_column($categories, 'category_id'))]['name'] ?? 'selected category'); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="?" class="clear-search-btn">
                    <i class="fas fa-times"></i>
                    Clear Search
                </a>
            </div>
        <?php endif; ?>

        <!-- Modern Filter Section -->
        <div class="modern-filter-section">
            <form method="GET" id="filterForm">
                <div class="filter-main">
                    <!-- Search Box -->
                    <div class="filter-group">
                        <label class="filter-label">Search Products</label>
                        <div class="search-box-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" 
                                   name="search" 
                                   class="search-box" 
                                   placeholder="Search by product name or description..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <div class="category-select-wrapper">
                            <select name="category" class="category-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" 
                                        <?php echo isset($_GET['category']) && $_GET['category'] == $cat['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Sort Filter -->
                    <div class="filter-group">
                        <label class="filter-label">Sort By</label>
                        <div class="sort-select-wrapper">
                            <select name="sort" class="sort-select">
                                <option value="name_asc" <?php echo ($sort == 'name_asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                                <option value="name_desc" <?php echo ($sort == 'name_desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                                <option value="price_asc" <?php echo ($sort == 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_desc" <?php echo ($sort == 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filter Actions -->
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter btn-apply">
                            <i class="fas fa-filter"></i>
                            <span>Apply Filters</span>
                        </button>
                        <a href="?" class="btn-filter btn-reset">
                            <i class="fas fa-rotate"></i>
                            <span>Reset All</span>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="products-grid">
            <?php if (empty($products)): ?>
                <div class="no-products-container">
                    <?php if (!empty($search)): ?>
                        <i class="fas fa-search no-products-icon"></i>
                        <h3 class="no-products-title">No Products Found</h3>
                        <p class="no-products-text">
                            No products found for "<?php echo htmlspecialchars($search); ?>"
                            <?php if (isset($_GET['category']) && $_GET['category'] != ""): ?>
                                in <?php echo htmlspecialchars($categories[array_search($_GET['category'], array_column($categories, 'category_id'))]['name'] ?? 'selected category'); ?>
                            <?php endif; ?>
                        </p>
                        <p class="no-products-text mb-4">Try different keywords or browse all categories</p>
                    <?php else: ?>
                        <i class="fas fa-box-open no-products-icon"></i>
                        <h3 class="no-products-title">No Products Available</h3>
                        <p class="no-products-text">There are currently no products available in this category. Please check back later.</p>
                    <?php endif; ?>
                    <a href="?" class="btn-filter btn-reset" style="display: inline-flex; align-items: center; gap: 10px; padding: 14px 30px;">
                        <i class="fas fa-rotate"></i>
                        <span>Reset Filters</span>
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="modern-product-card">
                        <div class="product-image-wrapper">
                            <img src="<?php echo htmlspecialchars($product['image_array'][0]); ?>" 
                                class="product-image" 
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                onerror="this.src='https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80'">
                            <div class="product-overlay"></div>
                            <span class="product-badge">
                                <i class="fas fa-star me-1"></i>PREMIUM
                            </span>
                        </div>
                        
                        <div class="product-content">
                            <div class="product-header">
                                <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <?php if (!empty($product['category_name'])): ?>
                                    <span class="product-category">
                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($product['category_name']); ?>
                                    </span>
                                <?php endif; ?>
                                <p class="product-description">
                                    <?php echo htmlspecialchars(substr($product['description'], 0, 120)) . (strlen($product['description']) > 120 ? '...' : ''); ?>
                                </p>
                            </div>
                            
                            <div class="product-price-section">
                                <div class="product-price">
                                    $<?php echo number_format($product['price'], 2); ?>
                                    <span>USD</span>
                                </div>
                                
                                <div class="stock-indicator <?php echo $product['stock'] > 10 ? 'stock-in' : ($product['stock'] > 0 ? 'stock-low' : 'stock-out'); ?>">
                                    <span class="stock-dot"></span>
                                    <span class="stock-text">
                                        <?php echo $product['stock'] > 10 ? 'In Stock' : ($product['stock'] > 0 ? 'Low Stock' : 'Out of Stock'); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="product-actions">
                                <a href="product_detail.php?id=<?php echo $product['product_id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i>
                                    <span>View Details</span>
                                </a>
                                
                                <!-- Add to Cart Form -->
                                <form method="POST" class="add-to-cart-form">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <div class="quantity-selector">
                                        <button type="button" class="qty-btn minus-btn" onclick="decreaseQuantity(this)">-</button>
                                        <input type="number" name="quantity" class="qty-input" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                        <button type="button" class="qty-btn plus-btn" onclick="increaseQuantity(this, <?php echo $product['stock']; ?>)">+</button>
                                    </div>
                                    <button type="submit" name="add_to_cart" class="btn-cart">
                                        <i class="fas fa-shopping-cart"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quantity control functions
        function increaseQuantity(button, maxStock) {
            const form = button.closest('form');
            const quantityInput = form.querySelector('.qty-input');
            let currentValue = parseInt(quantityInput.value);
            
            if (currentValue < maxStock) {
                quantityInput.value = currentValue + 1;
            } else {
                alert('Cannot add more than ' + maxStock + ' items (stock limited)');
            }
        }

        function decreaseQuantity(button) {
            const form = button.closest('form');
            const quantityInput = form.querySelector('.qty-input');
            let currentValue = parseInt(quantityInput.value);
            
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        }

        // Auto-hide messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => {
                        if (alert.parentElement) {
                            alert.remove();
                        }
                    }, 300);
                }, 5000);
            });

            // Add animation for slide out
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);

            // Auto-focus search input if it has value
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                searchInput.focus();
                searchInput.select();
            }
        });

        // Real-time search suggestions (optional feature)
        let searchTimeout;
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                
                // Optional: Add debouncing for real-time search
                // searchTimeout = setTimeout(function() {
                //     if (searchInput.value.length >= 2) {
                //         // You could implement AJAX search suggestions here
                //         console.log('Searching for:', searchInput.value);
                //     }
                // }, 300);
            });
        }
    </script>
</body>
</html>

<?php 
// Close connection
$conn->close();
?>