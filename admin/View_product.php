<?php
// Database connection
include '../includes/db_connect.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['Admin_id'])) {
    header("Location: /velvet_vogue/admin_login.php");
    exit();
}

$message = "";

// Handle delete operation
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First delete the image record
        $deleteImg = $conn->prepare("DELETE FROM product_images WHERE product_id_fk = ?");
        $deleteImg->bind_param("i", $delete_id);
        $deleteImg->execute();
        $deleteImg->close();
        
        // Then delete the product
        $deleteProduct = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $deleteProduct->bind_param("i", $delete_id);
        
        if ($deleteProduct->execute()) {
            $conn->commit();
            header("Location: view_products.php?success=deleted");
            exit();
        } else {
            throw new Exception("Error deleting product");
        }
        $deleteProduct->close();
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert alert-danger'>Error deleting product: " . $e->getMessage() . "</div>";
    }
}

// Show success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'deleted':
            $message = "<div class='alert alert-success'>Product deleted successfully!</div>";
            break;
    }
}

// Search and filter functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';

// Build query with filters
$query = "SELECT p.product_id, p.name, p.description, p.price, p.stock, p.category_id_fk, 
                 c.name as category_name, pi.image_url 
          FROM products p 
          LEFT JOIN categories c ON p.category_id_fk = c.category_id 
          LEFT JOIN product_images pi ON p.product_id = pi.product_id_fk 
          WHERE 1=1";

$params = [];
$types = '';

// Add search filter
if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

// Add category filter
if ($category_filter > 0) {
    $query .= " AND p.category_id_fk = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

// Add stock filter
if ($stock_filter === 'low') {
    $query .= " AND p.stock < 10 AND p.stock > 0";
} elseif ($stock_filter === 'out') {
    $query .= " AND p.stock = 0";
} elseif ($stock_filter === 'in') {
    $query .= " AND p.stock >= 10";
}

$query .= " ORDER BY p.product_id DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get categories for filter dropdown
$categories_result = $conn->query("SELECT category_id, name FROM categories ORDER BY name");

// Calculate statistics
$total_products = $result->num_rows;
$total_value = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;

if ($result->num_rows > 0) {
    $products_data = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($products_data as $product) {
        $total_value += $product['price'] * $product['stock'];
        if ($product['stock'] < 10 && $product['stock'] > 0) {
            $low_stock_count++;
        }
        if ($product['stock'] == 0) {
            $out_of_stock_count++;
        }
    }
    // Reset pointer
    $result->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Dashboard - Velvet Vogue Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary: #2C3E50;
            --primary-dark: #1A252F;
            --secondary: #ECF0F1;
            --accent: #3498DB;
            --success: #27AE60;
            --warning: #F39C12;
            --danger: #E74C3C;
            --dark: #2C3E50;
            --light: #ECF0F1;
            --border: #BDC3C7;
            --shadow: 0 4px 6px -1px rgba(44, 62, 80, 0.1), 0 2px 4px -1px rgba(44, 62, 80, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(44, 62, 80, 0.1), 0 4px 6px -2px rgba(44, 62, 80, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #ECF0F1 0%, #D5DBDB 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: var(--dark);
        }

        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        /* Header Styles */
        .dashboard-header {
            background: linear-gradient(135deg, #FFFFFF, #F8F9FA);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .header-subtitle {
            color: #7F8C8D;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            font-style: italic;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #FFFFFF, #F8F9FA);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }

        .stat-card.warning::before { background: var(--warning); }
        .stat-card.danger::before { background: var(--danger); }
        .stat-card.success::before { background: var(--success); }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #ECF0F1;
        }

        .stat-card.warning .stat-icon { background: linear-gradient(135deg, var(--warning), #B8860B); }
        .stat-card.danger .stat-icon { background: linear-gradient(135deg, var(--danger), #8B0000); }
        .stat-card.success .stat-icon { background: linear-gradient(135deg, var(--success), #006400); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #7F8C8D;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Filter Section */
        .filter-section {
            background: linear-gradient(135deg, #FFFFFF, #F8F9FA);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .search-box {
            position: relative;
        }

        .search-box .bi-search {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #7F8C8D;
        }

        .search-box input {
            padding-left: 3rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            height: 48px;
            font-size: 0.95rem;
            background: #FFFFFF;
        }

        .filter-select {
            border-radius: 12px;
            border: 1px solid var(--border);
            height: 48px;
            font-size: 0.95rem;
            background: #FFFFFF;
        }

        .filter-btn {
            background: var(--primary);
            border: none;
            color: #ECF0F1;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            height: 48px;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Products Grid */
        .products-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: linear-gradient(135deg, #FFFFFF, #F8F9FA);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .product-image-container {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: linear-gradient(135deg, #ECF0F1, #D5DBDB);
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-badges {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .stock-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .stock-in { background: rgba(39, 174, 96, 0.9); color: #ECF0F1; }
        .stock-low { background: rgba(243, 156, 18, 0.9); color: #2C3E50; }
        .stock-out { background: rgba(231, 76, 60, 0.9); color: #ECF0F1; }

        .category-badge {
            background: rgba(255, 255, 255, 0.95);
            color: var(--dark);
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--border);
        }

        .product-content {
            padding: 1.5rem;
        }

        .product-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.75rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-description {
            color: #7F8C8D;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-meta {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .product-stock {
            color: #7F8C8D;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .product-actions {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 0.5rem;
        }

        .btn-view {
            background: var(--primary);
            color: #ECF0F1;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            color: #ECF0F1;
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            background: #FFFFFF;
            color: var(--dark);
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-icon:hover {
            background: var(--primary);
            color: #ECF0F1;
            transform: translateY(-2px);
            border-color: var(--primary);
        }

        .btn-edit:hover {
            background: var(--success);
            border-color: var(--success);
        }

        .btn-delete:hover {
            background: var(--danger);
            border-color: var(--danger);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, #FFFFFF, #F8F9FA);
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .empty-icon {
            font-size: 4rem;
            color: #BDC3C7;
            margin-bottom: 1.5rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .empty-text {
            color: #7F8C8D;
            margin-bottom: 2rem;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, #FFFFFF, #F8F9FA);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #ECF0F1;
            border-radius: 20px 20px 0 0;
            padding: 1.5rem 2rem;
            border: none;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 1.5rem 2rem;
            border-radius: 0 0 20px 20px;
        }

        /* Alert Styles */
        .alert-success {
            background: linear-gradient(135deg, #A9DFBF, #82E0AA);
            border: 1px solid #27AE60;
            color: #2C3E50;
        }

        .alert-danger {
            background: linear-gradient(135deg, #F9EBEA, #F5B7B1);
            border: 1px solid #E74C3C;
            color: #2C3E50;
        }

        /* Button Styles */
        .btn-primary {
            background: var(--primary);
            border: none;
            color: #ECF0F1;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            color: #ECF0F1;
        }

        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: #ECF0F1;
        }

        .btn-danger {
            background: var(--danger);
            border: none;
            color: #ECF0F1;
        }

        .btn-danger:hover {
            background: #8B0000;
            color: #ECF0F1;
        }

        .btn-outline-secondary {
            border-color: var(--border);
            color: var(--dark);
        }

        .btn-outline-secondary:hover {
            background: var(--border);
            border-color: var(--border);
            color: var(--dark);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .products-container {
                grid-template-columns: 1fr;
            }
            
            .product-actions {
                grid-template-columns: 1fr;
            }
            
            .header-title {
                font-size: 1.75rem;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #D5DBDB;
        }

        ::-webkit-scrollbar-thumb {
            background: #BDC3C7;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #7F8C8D;
        }

        /* Professional Details */
        .professional-border {
            border: 2px solid transparent;
            background: linear-gradient(135deg, #FFFFFF, #F8F9FA) padding-box,
                        linear-gradient(135deg, var(--primary), var(--accent)) border-box;
        }

        .text-accent {
            color: #3498DB;
        }

        .bg-professional {
            background: linear-gradient(135deg, #2C3E50, #1A252F);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main role="main" class="main-content">
        <!-- Dashboard Header -->
        <div class="dashboard-header fade-in">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="header-title">Product Inventory</h1>
                    <p class="header-subtitle">Manage your product inventory with professional efficiency</p>
                </div>
                <a href="add_product.php" class="btn btn-primary btn-lg" style="background: var(--primary); border: none; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 600;">
                    <i class="bi bi-plus-circle me-2"></i> Add New Product
                </a>
            </div>
        </div>

        <?php if ($message) echo $message; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-box-seam fs-5"></i>
                </div>
                <div class="stat-value"><?php echo $total_products; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="bi bi-currency-dollar fs-5"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($total_value, 0); ?></div>
                <div class="stat-label">Inventory Value</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="bi bi-exclamation-triangle fs-5"></i>
                </div>
                <div class="stat-value"><?php echo $low_stock_count; ?></div>
                <div class="stat-label">Restock Needed</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="bi bi-x-circle fs-5"></i>
                </div>
                <div class="stat-value"><?php echo $out_of_stock_count; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section fade-in">
            <form method="GET" action="view_products.php">
                <div class="filter-grid">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search products by name or description..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div>
                        <select name="category" class="form-select filter-select">
                            <option value="0">All Categories</option>
                            <?php 
                            if ($categories_result && $categories_result->num_rows > 0) {
                                $categories_result->data_seek(0);
                                while($category = $categories_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $category['category_id']; ?>" 
                                    <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php 
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <select name="stock" class="form-select filter-select">
                            <option value="">All Stock</option>
                            <option value="in" <?php echo $stock_filter === 'in' ? 'selected' : ''; ?>>In Stock</option>
                            <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="filter-btn">
                        <i class="bi bi-funnel me-2"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="products-container">
            <?php if ($result && $result->num_rows > 0): 
                while ($row = $result->fetch_assoc()): 
                $stock_class = $row['stock'] == 0 ? 'stock-out' : ($row['stock'] < 10 ? 'stock-low' : 'stock-in');
                $stock_text = $row['stock'] == 0 ? 'Out of Stock' : ($row['stock'] < 10 ? 'Low Stock' : 'In Stock');
            ?>
                <div class="product-card fade-in">
                    <div class="product-image-container">
                        <?php if (!empty($row['image_url'])): ?>
                            <img src="../<?php echo htmlspecialchars($row['image_url']); ?>" 
                                 class="product-image" 
                                 alt="<?php echo htmlspecialchars($row['name']); ?>">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <i class="bi bi-image text-muted display-4"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-badges">
                            <span class="stock-badge <?php echo $stock_class; ?>">
                                <i class="bi bi-circle-fill me-1" style="font-size: 6px;"></i><?php echo $stock_text; ?>
                            </span>
                            <span class="category-badge">
                                <i class="bi bi-tag me-1"></i>
                                <?php echo !empty($row['category_name']) ? htmlspecialchars($row['category_name']) : 'Uncategorized'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="product-content">
                        <h5 class="product-title"><?php echo htmlspecialchars($row['name']); ?></h5>
                        <p class="product-description"><?php echo htmlspecialchars($row['description']); ?></p>
                        
                        <div class="product-meta">
                            <div class="product-price">$<?php echo number_format($row['price'], 2); ?></div>
                            <div class="product-stock"><?php echo $row['stock']; ?> units</div>
                        </div>
                        
                        <div class="product-actions">
                            <button type="button" class="btn-view" 
                                    data-bs-toggle="modal" data-bs-target="#productModal<?php echo $row['product_id']; ?>">
                                <i class="bi bi-eye"></i> View Details
                            </button>
                            <a href="add_product.php?edit=<?php echo $row['product_id']; ?>" 
                                class="btn-icon btn-edit" title="Edit Product">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="view_products.php?delete=<?php echo $row['product_id']; ?>" 
                                class="btn-icon btn-delete" title="Delete Product"
                                onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Product Details Modal -->
                <div class="modal fade" id="productModal<?php echo $row['product_id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold"><?php echo htmlspecialchars($row['name']); ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <?php if (!empty($row['image_url'])): ?>
                                            <div class="rounded-3 overflow-hidden shadow-sm">
                                                <img src="../<?php echo htmlspecialchars($row['image_url']); ?>" 
                                                     class="img-fluid w-100" 
                                                     style="height: 300px; object-fit: cover;"
                                                     alt="<?php echo htmlspecialchars($row['name']); ?>">
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center rounded-3" style="height: 300px;">
                                                <span class="text-muted"><i class="bi bi-image fs-1"></i> No Image Available</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-3 h-100">
                                            <h5 class="fw-bold mb-4 text-primary">Product Details</h5>
                                            <div class="mb-3">
                                                <label class="text-muted mb-1">Product ID</label>
                                                <p class="fw-semibold mb-0">#<?php echo $row['product_id']; ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted mb-1">Description</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($row['description']); ?></p>
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-6">
                                                    <label class="text-muted mb-1">Price</label>
                                                    <p class="fw-bold fs-5 mb-0 text-primary">$<?php echo number_format($row['price'], 2); ?></p>
                                                </div>
                                                <div class="col-6">
                                                    <label class="text-muted mb-1">Stock</label>
                                                    <p class="fw-bold fs-5 mb-0 <?php echo $stock_class; ?>"><?php echo $row['stock']; ?> units</p>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted mb-1">Category</label>
                                                <p class="mb-0 fw-semibold"><?php echo !empty($row['category_name']) ? htmlspecialchars($row['category_name']) : 'Uncategorized'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer justify-content-between">
                                <div>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                        <i class="bi bi-x me-1"></i> Close
                                    </button>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="view_products.php?delete=<?php echo $row['product_id']; ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.')">
                                        <i class="bi bi-trash me-1"></i> Delete Product
                                    </a>
                                    <a href="add_product.php?edit=<?php echo $row['product_id']; ?>" 
                                       class="btn btn-primary">
                                        <i class="bi bi-pencil me-1"></i> Edit Product
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state fade-in">
                        <i class="bi bi-box empty-icon"></i>
                        <h4 class="empty-title">No Products Found</h4>
                        <p class="empty-text">
                            <?php echo !empty($search) ? 'No products match your search criteria. Try adjusting your filters.' : 'Your product inventory is empty. Start by adding your first product!'; ?>
                        </p>
                        <?php if (empty($search)): ?>
                            <a href="add_product.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-plus-circle me-2"></i> Add First Product
                            </a>
                        <?php else: ?>
                            <a href="view_products.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-clockwise me-2"></i> Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Add fade-in animation to elements as they come into view
        document.addEventListener('DOMContentLoaded', function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            });

            document.querySelectorAll('.fade-in').forEach((el) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>
<?php 
$conn->close();
?>