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
$editProduct = null;

// Fetch categories for dropdown
$categories_result = $conn->query("SELECT category_id, name FROM categories ORDER BY name");
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[$row['category_id']] = $row['name'];
    }
}

// Check if we're editing an existing product
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT p.*, pi.image_url, pi.image_id FROM products p LEFT JOIN product_images pi ON p.product_id = pi.product_id_fk WHERE p.product_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $editProduct = $result->fetch_assoc();
    }
    $stmt->close();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit_product_id'])) {
        // UPDATE EXISTING PRODUCT
        $product_id = intval($_POST['edit_product_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        $category_id = intval($_POST['category_id_fk']);

        // Validate category exists
        if (!array_key_exists($category_id, $categories)) {
            $message = "<div class='alert alert-danger'>Invalid category selected.</div>";
        } elseif (empty($name) || empty($description) || $price <= 0 || $stock < 0) {
            $message = "<div class='alert alert-danger'>All fields are required and valid.</div>";
        } else {
            $sql = "UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category_id_fk = ? WHERE product_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdiii", $name, $description, $price, $stock, $category_id, $product_id);

            if ($stmt->execute()) {
                $stmt->close();

                // Handle image update
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $uploadOk = 1;
                    $targetDir = "../images/";
                    $fileName = time() . "_" . basename($_FILES["image"]["name"]);
                    $targetFilePath = $targetDir . $fileName;
                    $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

                    // Create directory if it doesn't exist
                    if (!file_exists($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }

                    $check = getimagesize($_FILES["image"]["tmp_name"]);
                    if ($check === false) {
                        $message = "<div class='alert alert-danger'>File is not an image.</div>";
                        $uploadOk = 0;
                    }

                    if ($_FILES["image"]["size"] > 5000000) {
                        $message = "<div class='alert alert-danger'>Image too large (max 5MB).</div>";
                        $uploadOk = 0;
                    }

                    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $message = "<div class='alert alert-danger'>Only JPG, JPEG, PNG & WEBP allowed.</div>";
                        $uploadOk = 0;
                    }

                    if ($uploadOk == 1) {
                        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                            $imageUrl = "images/" . $fileName;
                            
                            $checkImg = $conn->prepare("SELECT image_id FROM product_images WHERE product_id_fk = ?");
                            $checkImg->bind_param("i", $product_id);
                            $checkImg->execute();
                            $imgResult = $checkImg->get_result();
                            
                            if ($imgResult->num_rows > 0) {
                                $imgSql = "UPDATE product_images SET image_url = ? WHERE product_id_fk = ?";
                            } else {
                                $imgSql = "INSERT INTO product_images (product_id_fk, image_url) VALUES (?, ?)";
                            }
                            
                            $imgStmt = $conn->prepare($imgSql);
                            if ($imgResult->num_rows > 0) {
                                $imgStmt->bind_param("si", $imageUrl, $product_id);
                            } else {
                                $imgStmt->bind_param("is", $product_id, $imageUrl);
                            }
                            $imgStmt->execute();
                            $imgStmt->close();
                            $checkImg->close();
                            
                            $message = "<div class='alert alert-success'>Product & image updated successfully!</div>";
                        } else {
                            $message = "<div class='alert alert-danger'>Error uploading image. Check directory permissions.</div>";
                        }
                    }
                } else {
                    $message = "<div class='alert alert-success'>Product updated successfully!</div>";
                }
                
                header("Location: add_product.php?success=updated");
                exit();
            } else {
                $message = "<div class='alert alert-danger'>Error updating product: " . $stmt->error . "</div>";
            }
        }
    } else {
        // ADD NEW PRODUCT
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        $category_id = intval($_POST['category_id_fk']);

        // Validate category exists
        if (!array_key_exists($category_id, $categories)) {
            $message = "<div class='alert alert-danger'>Invalid category selected.</div>";
        } elseif (empty($name) || empty($description) || $price <= 0 || $stock < 0) {
            $message = "<div class='alert alert-danger'>All fields are required and valid.</div>";
        } else {
            $sql = "INSERT INTO products (name, description, price, stock, category_id_fk) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdii", $name, $description, $price, $stock, $category_id);

            if ($stmt->execute()) {
                $product_id = $conn->insert_id;
                $stmt->close();

                // Handle Image Upload
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $targetDir = "../images/";
                    $fileName = time() . "_" . basename($_FILES["image"]["name"]);
                    $targetFilePath = $targetDir . $fileName;
                    $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

                    // Create directory if it doesn't exist
                    if (!file_exists($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }

                    $check = getimagesize($_FILES["image"]["tmp_name"]);
                    if ($check === false) {
                        $message = "<div class='alert alert-danger'>File is not an image.</div>";
                    } elseif ($_FILES["image"]["size"] > 5000000) {
                        $message = "<div class='alert alert-danger'>Image too large (max 5MB).</div>";
                    } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $message = "<div class='alert alert-danger'>Only JPG, JPEG, PNG & WEBP allowed.</div>";
                    } else {
                        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                            $imageUrl = "images/" . $fileName;
                            $imgSql = "INSERT INTO product_images (product_id_fk, image_url) VALUES (?, ?)";
                            $imgStmt = $conn->prepare($imgSql);
                            $imgStmt->bind_param("is", $product_id, $imageUrl);
                            $imgStmt->execute();
                            $imgStmt->close();
                            $message = "<div class='alert alert-success'>Product & image added successfully!</div>";
                        } else {
                            $message = "<div class='alert alert-danger'>Error uploading image. Check directory permissions.</div>";
                        }
                    }
                } else {
                    $message = "<div class='alert alert-success'>Product added (no image).</div>";
                }
                
                header("Location: add_product.php?success=added");
                exit();
            } else {
                $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
            }
        }
    }
}

// Handle delete operation
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    // First, get the image URL to delete the file
    $imgStmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id_fk = ?");
    $imgStmt->bind_param("i", $delete_id);
    $imgStmt->execute();
    $imgResult = $imgStmt->get_result();
    
    $conn->begin_transaction();
    try {
        // Delete product image file if exists
        if ($imgResult->num_rows > 0) {
            $imageData = $imgResult->fetch_assoc();
            $imagePath = "../" . $imageData['image_url'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        $imgStmt->close();
        
        $deleteImg = $conn->prepare("DELETE FROM product_images WHERE product_id_fk = ?");
        $deleteImg->bind_param("i", $delete_id);
        $deleteImg->execute();
        $deleteImg->close();
        
        $deleteProduct = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $deleteProduct->bind_param("i", $delete_id);
        
        if ($deleteProduct->execute()) {
            $conn->commit();
            header("Location: add_product.php?success=deleted");
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
        case 'added':
            $message = "<div class='alert alert-success'>Product added successfully!</div>";
            break;
        case 'updated':
            $message = "<div class='alert alert-success'>Product updated successfully!</div>";
            break;
        case 'deleted':
            $message = "<div class='alert alert-success'>Product deleted successfully!</div>";
            break;
    }
}

// Fetch products for listing
$result = $conn->query("SELECT p.product_id, p.name, p.description, p.price, p.stock, p.category_id_fk, c.name as category_name, pi.image_url FROM products p LEFT JOIN product_images pi ON p.product_id = pi.product_id_fk LEFT JOIN categories c ON p.category_id_fk = c.category_id ORDER BY p.product_id DESC");
$total_products = $result ? $result->num_rows : 0;

$total_value = 0;
$low_stock_count = 0;
if ($result && $result->num_rows > 0) {
    $products_data = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($products_data as $product) {
        $total_value += $product['price'] * $product['stock'];
        if ($product['stock'] < 10) {
            $low_stock_count++;
        }
    }
    $result->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editProduct ? 'Edit' : 'Add'; ?> Luxury Item - Velvet Vogue</title>
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
            font-family: 'Playfair Display', 'Times New Roman', serif;
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
            font-family: 'Playfair Display', serif;
        }

        .header-subtitle {
            color: #7F8C8D;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            font-style: italic;
        }

        /* Form Card */
        .form-card {
            background: linear-gradient(135deg, #FFFFFF, #F8F9FA);
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .form-card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #ECF0F1;
            padding: 1.5rem 2rem;
            position: relative;
        }

        .form-card-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
        }

        .form-card-body {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            align-items: start;
        }

        .form-main {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-side {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.05), rgba(44, 62, 80, 0.05));
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Form Elements */
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-control {
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 0.75rem 1rem;
            background: #FFFFFF;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
            background: #FFFFFF;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        /* Image Preview */
        .image-upload-section {
            text-align: center;
        }

        .image-preview {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, #ECF0F1, #D5DBDB);
            border: 2px dashed var(--border);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .image-preview:hover {
            border-color: var(--primary);
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .upload-hint {
            color: #7F8C8D;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .upload-info {
            color: var(--primary);
            font-size: 0.85rem;
            font-style: italic;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: #ECF0F1;
            padding: 0.9rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 62, 80, 0.3);
        }

        .btn-outline-secondary {
            border: 1px solid var(--border);
            color: var(--dark);
            padding: 0.9rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
            background: transparent;
        }

        .btn-outline-secondary:hover {
            background: var(--border);
            border-color: var(--border);
            color: var(--dark);
        }

        .btn-outline-primary {
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 0.9rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: #ECF0F1;
        }

        /* Products Overview */
        .products-overview {
            margin-top: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary);
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: linear-gradient(135deg, #FFFFFF, #F8F9FA);
            border-radius: 16px;
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
            height: 200px;
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

        .product-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--primary);
            color: #ECF0F1;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .product-card-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
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
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .product-category {
            color: #7F8C8D;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: auto;
        }

        .btn-action {
            flex: 1;
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #7F8C8D;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #A9DFBF, #82E0AA);
            color: var(--dark);
            border: 1px solid var(--success);
        }

        .alert-danger {
            background: linear-gradient(135deg, #F9EBEA, #F5B7B1);
            color: var(--dark);
            border: 1px solid var(--danger);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .product-actions {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main role="main" class="main-content">
        <!-- Dashboard Header -->
        <div class="dashboard-header fade-in">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="header-title"><?php echo $editProduct ? 'Edit Luxury Item' : 'Add New Luxury Item'; ?></h1>
                    <p class="header-subtitle"><?php echo $editProduct ? 'Refine your luxury collection' : 'Curate exquisite additions to your collection'; ?></p>
                </div>
            </div>
        </div>

        <?php if ($message) echo $message; ?>

        <!-- Add/Edit Product Section -->
        <div class="form-card fade-in">
            <div class="form-card-header">
                <h3 class="mb-0"><?php echo $editProduct ? 'Edit Item Details' : 'New Luxury Item'; ?></h3>
                <p class="mb-0 opacity-75"><?php echo $editProduct ? 'Update your luxury item details' : 'Create a new luxury item for your collection'; ?></p>
            </div>
            <div class="form-card-body">
                <form id="productForm" method="POST" enctype="multipart/form-data" novalidate>
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="edit_product_id" value="<?php echo $editProduct['product_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-main">
                            <div class="mb-4">
                                <label class="form-label">Luxury Item Name</label>
                                <input type="text" name="name" id="name" class="form-control" 
                                       placeholder="E.g. Elegant Silk Evening Gown" 
                                       value="<?php echo $editProduct ? htmlspecialchars($editProduct['name']) : ''; ?>" 
                                       required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="5" 
                                          placeholder="Describe the luxury item, materials, craftsmanship, and unique features..." 
                                          required><?php echo $editProduct ? htmlspecialchars($editProduct['description']) : ''; ?></textarea>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Price (₱)</label>
                                    <input type="number" step="0.01" name="price" id="price" class="form-control" 
                                           placeholder="0.00" 
                                           value="<?php echo $editProduct ? $editProduct['price'] : ''; ?>" 
                                           required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Stock Quantity</label>
                                    <input type="number" name="stock" id="stock" class="form-control" 
                                           placeholder="Available units" 
                                           value="<?php echo $editProduct ? $editProduct['stock'] : ''; ?>" 
                                           required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Collection Category</label>
                                    <select name="category_id_fk" id="category_id_fk" class="form-control" required>
                                        <option value="">Select Collection</option>
                                        <?php foreach ($categories as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" 
                                                <?php if ($editProduct && $editProduct['category_id_fk'] == $id) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <aside class="form-side">
                            <div class="image-upload-section">
                                <label class="form-label">Luxury Item Image</label>
                                <div class="image-preview" id="imagePreview">
                                    <?php if ($editProduct && !empty($editProduct['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($editProduct['image_url']); ?>" 
                                             alt="Current luxury item image">
                                    <?php else: ?>
                                        <div class="d-flex flex-column align-items-center justify-content-center h-100">
                                            <i class="bi bi-camera display-4 text-muted mb-2"></i>
                                            <span class="upload-hint">No image selected</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="image" id="image" class="form-control" accept="image/*">
                                <small class="upload-hint d-block mt-2">Max 5MB. JPG / PNG / WEBP recommended.</small>
                                <?php if ($editProduct && !empty($editProduct['image_url'])): ?>
                                    <small class="upload-info">Current image will be replaced with new upload</small>
                                <?php endif; ?>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" id="submitBtn" class="btn btn-primary mb-2">
                                    <i class="bi bi-<?php echo $editProduct ? 'check-lg' : 'plus-lg'; ?> me-2"></i>
                                    <?php echo $editProduct ? 'Update Luxury Item' : 'Add to Collection'; ?>
                                </button>
                                <button type="button" id="resetBtn" class="btn btn-outline-secondary mb-2" onclick="resetForm()">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset Form
                                </button>
                                <?php if ($editProduct): ?>
                                    <a href="add_product.php" class="btn btn-outline-primary">
                                        <i class="bi bi-x-lg me-2"></i>Cancel Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </aside>
                    </div>
                </form>
            </div>
        </div>

        <!-- Products Overview Section -->
        <div class="products-overview">
            <div class="section-header">
                <h4 class="section-title">Luxury Collection Overview</h4>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary fs-6"><?php echo $total_products; ?> Items</span>
                    <span class="badge bg-success fs-6">₱<?php echo number_format($total_value, 0); ?> Value</span>
                </div>
            </div>

            <div class="products-grid">
                <?php if ($result && $result->num_rows > 0): 
                    while ($row = $result->fetch_assoc()): ?>
                    <div class="product-card fade-in">
                        <div class="product-image-container">
                            <?php if (!empty($row['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($row['image_url']); ?>" 
                                     class="product-image" 
                                     alt="<?php echo htmlspecialchars($row['name']); ?>">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <i class="bi bi-gem text-muted display-4"></i>
                                </div>
                            <?php endif; ?>
                            <div class="product-badge">
                                Stock: <?php echo $row['stock']; ?>
                            </div>
                        </div>
                        
                        <div class="product-card-body">
                            <h5 class="product-title"><?php echo htmlspecialchars($row['name']); ?></h5>
                            <p class="product-description"><?php echo htmlspecialchars($row['description']); ?></p>
                            <div class="product-price">₱<?php echo number_format($row['price'], 2); ?></div>
                            <div class="product-category">
                                <small>Collection: <?php echo htmlspecialchars($row['category_name']); ?></small>
                            </div>
                            
                            <div class="product-actions">
                                <button type="button" class="btn btn-primary btn-action" 
                                        data-bs-toggle="modal" data-bs-target="#productModal<?php echo $row['product_id']; ?>">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <a href="add_product.php?edit=<?php echo $row['product_id']; ?>" 
                                    class="btn btn-outline-primary btn-icon" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="add_product.php?delete=<?php echo $row['product_id']; ?>" 
                                    class="btn btn-outline-danger btn-icon" title="Delete"
                                    onclick="return confirm('Are you sure you want to delete this luxury item? This action cannot be undone.');">
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
                                                    <span class="text-muted"><i class="bi bi-gem fs-1"></i></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 bg-light rounded-3 h-100">
                                                <h5 class="fw-bold mb-4 text-primary">Luxury Item Details</h5>
                                                <div class="mb-3">
                                                    <label class="text-muted mb-1">Item ID</label>
                                                    <p class="fw-semibold mb-0">#<?php echo $row['product_id']; ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-muted mb-1">Description</label>
                                                    <p class="mb-0"><?php echo htmlspecialchars($row['description']); ?></p>
                                                </div>
                                                <div class="row g-3 mb-3">
                                                    <div class="col-6">
                                                        <label class="text-muted mb-1">Price</label>
                                                        <p class="fw-bold fs-5 mb-0 text-primary">₱<?php echo number_format($row['price'], 2); ?></p>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="text-muted mb-1">Stock</label>
                                                        <p class="fw-bold fs-5 mb-0"><?php echo $row['stock']; ?> units</p>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-muted mb-1">Collection</label>
                                                    <p class="mb-0"><?php echo htmlspecialchars($row['category_name']); ?></p>
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
                                        <a href="add_product.php?delete=<?php echo $row['product_id']; ?>" 
                                           class="btn btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this luxury item? This action cannot be undone.')">
                                            <i class="bi bi-trash me-1"></i> Delete Item
                                        </a>
                                        <a href="add_product.php?edit=<?php echo $row['product_id']; ?>" 
                                           class="btn btn-primary">
                                            <i class="bi bi-pencil me-1"></i> Edit Item
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-gem"></i>
                        <h5>No Luxury Items Found</h5>
                        <p class="text-muted">Begin curating your luxury collection by adding exquisite items above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>

<script>
    // Image preview
    document.addEventListener('DOMContentLoaded', function() {
        const imageInput = document.getElementById('image');
        const preview = document.getElementById('imagePreview');
        const submitBtn = document.getElementById('submitBtn');

        if (imageInput) {
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                preview.innerHTML = '';
                if (!file) {
                    const hint = document.createElement('div');
                    hint.className = 'd-flex flex-column align-items-center justify-content-center h-100';
                    hint.innerHTML = '<i class="bi bi-camera display-4 text-muted mb-2"></i><span class="upload-hint">No image selected</span>';
                    preview.appendChild(hint);
                    return;
                }

                if (!file.type.startsWith('image/')) {
                    preview.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100"><span class="upload-hint text-danger">Invalid file type</span></div>';
                    return;
                }

                const img = document.createElement('img');
                img.alt = 'Preview';
                img.src = URL.createObjectURL(file);
                img.onload = () => URL.revokeObjectURL(img.src);
                preview.appendChild(img);
            });
        }

        // Basic client-side validation
        const form = document.getElementById('productForm');
        if (form) {
            const requiredFields = ['name','description','price','stock','category_id_fk'];
            const checkValidity = () => {
                let valid = true;
                requiredFields.forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    const val = (el.value || '').toString().trim();
                    if (val.length === 0) valid = false;
                    if ((id === 'price' || id === 'stock') && Number(val) <= 0) valid = false;
                    if (id === 'category_id_fk' && Number(val) <= 0) valid = false;
                });
                submitBtn.disabled = !valid;
                submitBtn.style.cursor = valid ? 'pointer' : 'not-allowed';
                submitBtn.style.opacity = valid ? '1' : '0.6';
            };

            form.addEventListener('input', checkValidity);
            checkValidity();
        }
    });

    function resetForm() {
        const form = document.getElementById('productForm');
        if (form) form.reset();
        const preview = document.getElementById('imagePreview');
        if (preview) {
            <?php if ($editProduct && !empty($editProduct['image_url'])): ?>
                preview.innerHTML = '<img src="../<?php echo htmlspecialchars($editProduct['image_url']); ?>" alt="Current luxury item image">';
            <?php else: ?>
                preview.innerHTML = '<div class="d-flex flex-column align-items-center justify-content-center h-100"><i class="bi bi-camera display-4 text-muted mb-2"></i><span class="upload-hint">No image selected</span></div>';
            <?php endif; ?>
        }
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) submitBtn.disabled = false;
    }
</script>
<?php 
$conn->close();
?>