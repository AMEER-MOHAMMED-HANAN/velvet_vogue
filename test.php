<div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h5>
                                    <p class="card-text text-truncate"><?php echo htmlspecialchars($row['description']); ?></p>
                                    <div class="price">₱<?php echo number_format($row['price'], 2); ?></div>
                                    <div class="d-flex flex-column gap-2">
                                        <button type="button" class="btn btn-primary w-100 d-flex align-items-center justify-content-center" 
                                                style="background: var(--card-gradient); border: none; height: 42px;"
                                                data-bs-toggle="modal" data-bs-target="#productModal<?php echo $row['product_id']; ?>">
                                            <i class="bi bi-eye me-2"></i> View Details
                                        </button>
                                        <div class="d-flex gap-2">
                                            <a href="add_product.php?edit=<?php echo $row['product_id']; ?>" 
                                               class="btn btn-primary btn-icon flex-grow-1" title="Edit">
                                                <i class="bi bi-pencil me-2"></i> Edit
                                            </a>
                                            <a href="delete_product.php?id=<?php echo $row['product_id']; ?>" 
                                           class="btn btn-danger btn-icon" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this product?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </div>


                                ----------------------


                                <?php
// Database connection
include '../includes/db_connect.php'; // Adjust path as needed

// Ensure session is started so sidebar (which checks session) works correctly
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = "";
$editProduct = null;

// Check if we're editing an existing product
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT p.*, pi.image_url 
                           FROM products p 
                           LEFT JOIN product_images pi ON p.product_id = pi.product_id_fk 
                           WHERE p.product_id = ?");
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
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id_fk']);

    // Validate required fields
    if (empty($name) || empty($description) || $price <= 0 || $stock < 0 || $category_id <= 0) {
        $message = "<div class='alert alert-danger'>All fields are required and valid.</div>";
    } else {
        // === 1. Insert Product First ===
        $sql = "INSERT INTO products (name, description, price, stock, category_id_fk) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdii", $name, $description, $price, $stock, $category_id);

        if ($stmt->execute()) {
            $product_id = $conn->insert_id; // Get the new product ID
            $stmt->close();

            // === 2. Handle Image Upload ===
            $uploadOk = 1;
            $imagePath = "";

            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $targetDir = "../admin/images/"; // Folder to store images
                $fileName = time() . "_" . basename($_FILES["image"]["name"]); // Unique name
                $targetFilePath = $targetDir . $fileName;
                $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

                // Validate image
                $check = getimagesize($_FILES["image"]["tmp_name"]);
                if ($check === false) {
                    $message = "<div class='alert alert-danger'>File is not an image.</div>";
                    $uploadOk = 0;
                }

                // Check file size (max 5MB)
                if ($_FILES["image"]["size"] > 5000000) {
                    $message = "<div class='alert alert-danger'>Image too large (max 5MB).</div>";
                    $uploadOk = 0;
                }

                // Allow only JPG, PNG, JPEG
                if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $message = "<div class='alert alert-danger'>Only JPG, JPEG, PNG & WEBP allowed.</div>";
                    $uploadOk = 0;
                }

                // Upload file
                if ($uploadOk == 1) {
                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                        // Save relative path in DB
                        $imageUrl = "../images/" . $fileName;

                        $imgSql = "INSERT INTO product_images (product_id_fk, image_url) VALUES (?, ?)";
                        $imgStmt = $conn->prepare($imgSql);
                        $imgStmt->bind_param("is", $product_id, $imageUrl);
                        $imgStmt->execute();
                        $imgStmt->close();

                        $message = "<div class='alert alert-success'>Product & image added successfully!</div>";
                    } else {
                        $message = "<div class='alert alert-danger'>Error uploading image.</div>";
                    }
                }
            } else {
                $message = "<div class='alert alert-success'>Product added (no image).</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #feb47b;
            --background-light: #f8f9fa;
            --card-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* Page layout */
        body { background: var(--background-light); margin: 0; font-family: 'Segoe UI', system-ui, Arial, sans-serif; }

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            padding: 2rem;
            background: linear-gradient(135deg, #f6f8fb 0%, #f5f6f8 100%);
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
        }

        /* Card / form */
        .card {
            width: 100%;
            max-width: 100%;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(44,62,80,0.08);
            overflow: hidden;
        }

        .card-header {
            background: var(--card-gradient);
            color: #fff;
            padding: 1.25rem 1.5rem;
        }

        .card-body {
            padding: 2rem;
            background: #fff;
        }

        /* Form grid: main inputs + side panel (image preview, submit) */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.5rem;
            align-items: start;
            width: 100%;
        }

        .form-side {
            background: linear-gradient(180deg, rgba(102,126,234,0.03), rgba(118,75,162,0.03));
            border-radius: 10px;
            padding: 1rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .image-preview {
            width: 100%;
            height: 220px;
            background: #f4f7ff;
            border: 1px dashed #e6eefc;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .hint {
            font-size: 0.9rem;
            color: #6c7aa3;
        }

        /* Form elements */
        .form-label { font-weight: 600; color: var(--primary-color); }
        .form-control { border-radius: 8px; padding: 0.75rem 0.9rem; border: 1px solid #e9eef7; }
        .form-control:focus { box-shadow: 0 0 0 4px rgba(102,126,234,0.08); border-color: #667eea; }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            color: #fff;
            padding: 0.9rem 1.25rem;
            border-radius: 10px;
            font-weight: 700;
        }

        .btn-primary:hover {
            filter: brightness(0.9);
            opacity: 0.98;
        }

        /* Product card styles */
        .product-card {
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-card .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 1.25rem;
        }

        .product-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .product-card .card-text {
            color: #6c757d;
            margin-bottom: 0.75rem;
        }

        .product-card .price {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.2rem;
            margin-top: auto;
            margin-bottom: 1rem;
        }

        .product-card .card-footer {
            background: transparent;
            padding: 1rem;
            border-top: 1px solid rgba(0,0,0,0.05);
        }

        .product-card .btn {
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .product-card .btn-icon {
            padding: 0.5rem;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-card .badge {
            font-size: 0.85rem;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
        }

        .product-image-container {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: #f8f9fa;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        /* Modal styles */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .modal-header {
            background: var(--card-gradient);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 2rem;
        }

        /* Section headers */
        .section-header {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .section-header::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -0.5rem;
            width: 60px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        .card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            box-shadow: 0 10px 40px rgba(44,62,80,0.12);
        }

        /* Refresh button */
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: #fff;
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .main-content { 
                margin-left: 0; 
                padding: 1rem; 
                align-items: flex-start; 
            }
            .card { margin-top: 1rem; }
            .form-grid { grid-template-columns: 1fr; }
            .form-side { order: 2; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main role="main" class="main-content">
        <div class="row">
            <!-- Add Product Section -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <div class="me-auto">
                            <h3 class="mb-0 text-white">Add New Product</h3>
                            <p class="mb-0 text-white-50">Create a new product with details and image</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($message) echo $message; ?>

                <form id="addProductForm" method="POST" enctype="multipart/form-data" novalidate>
                    <div class="form-grid">
                        <div class="form-main">
                            <div class="mb-3">
                                <label class="form-label">Product Name</label>
                                <input type="text" name="name" id="name" class="form-control" placeholder="E.g. Elegant Silk Dress" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="4" placeholder="Short description for the product (materials, fit, notes)" required></textarea>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01" name="price" id="price" class="form-control" placeholder="0.00" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Stock</label>
                                    <input type="number" name="stock" id="stock" class="form-control" placeholder="Quantity" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Category ID</label>
                                    <input type="number" name="category_id_fk" id="category_id_fk" class="form-control" placeholder="Category #" required>
                                </div>
                            </div>
                        </div>

                        <aside class="form-side">
                            <div>
                                <label class="form-label">Product Image</label>
                                <div class="image-preview" id="imagePreview">
                                    <span class="hint">No image selected</span>
                                </div>
                                <input type="file" name="image" id="image" class="form-control mt-2" accept="image/*">
                                <small class="text-muted">Max 5MB. JPG / PNG / WEBP recommended.</small>
                            </div>

                            <div style="margin-top:auto;">
                                <button type="submit" id="submitBtn" class="btn btn-primary w-100">Add Product</button>
                                <button type="button" id="resetBtn" class="btn btn-outline-secondary w-100 mt-2" onclick="resetForm()">Reset</button>
                            </div>
                        </aside>
                    </div>
                </form>
            </div>
        </div>
        </div>

        <!-- Product List Section -->
        <?php
        // Fetch products for listing
        $result = $conn->query("SELECT p.product_id, p.name, p.description, p.price, p.stock, p.category_id_fk, pi.image_url 
                               FROM products p 
                               LEFT JOIN product_images pi ON p.product_id = pi.product_id_fk 
                               ORDER BY p.product_id DESC");
        $total_products = $result ? $result->num_rows : 0;
        ?>
        <div class="col-12">
            <div class="row align-items-center mb-4">
                <div class="col">
                    <h4 class="m-0 text-primary">Products Overview</h4>
                    <p class="mb-0 text-muted">Manage and view all products</p>
                </div>
                <div class="col-auto">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="window.location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh List
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card" style="border-radius: 10px; box-shadow: 0 8px 30px rgba(44,62,80,0.08);">
                <div class="card-header" style="background: var(--card-gradient); color: #fff; padding: 1.25rem 1.5rem;">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-white-50"><i class="bi bi-grid-3x3-gap me-2"></i>Total Products: <?php echo $total_products; ?></span>
                        <div class="dropdown">
                            <button class="btn btn-link text-white p-0" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><button class="dropdown-item" onclick="window.location.reload()">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                                </button></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body" style="padding: 2rem; background: #fff;">
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
                    <?php if ($result && $result->num_rows > 0): 
                        while ($row = $result->fetch_assoc()): ?>
                        <div class="col">
                            <div class="card product-card">
                                <div class="product-image-container">
                                    <?php if (!empty($row['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($row['image_url']); ?>" 
                                             class="product-image" 
                                             alt="<?php echo htmlspecialchars($row['name']); ?>">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center h-100">
                                            <span class="text-muted">No image</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <span class="badge bg-primary">Stock: <?php echo $row['stock']; ?></span>
                                    </div>
                                </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h5>
                                        <p class="card-text text-truncate"><?php echo htmlspecialchars($row['description']); ?></p>
                                        <div class="price">₱<?php echo number_format($row['price'], 2); ?></div>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-primary w-100 d-flex align-items-center justify-content-center" 
                                                    data-bs-toggle="modal" data-bs-target="#productModal<?php echo $row['product_id']; ?>">
                                                <i class="bi bi-eye me-1"></i> View Details
                                            </button>
                                            <a href="add_product.php?edit=<?php echo $row['product_id']; ?>" 
                                                class="btn btn-primary btn-icon" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete_product.php?id=<?php echo $row['product_id']; ?>" 
                                                class="btn btn-danger btn-icon" title="Delete"
                                                onclick="return confirm('Are you sure you want to delete this product?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
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
                                                    <div class="rounded overflow-hidden shadow-sm">
                                                        <img src="<?php echo htmlspecialchars($row['image_url']); ?>" 
                                                             class="img-fluid w-100" 
                                                             style="height: 300px; object-fit: cover;"
                                                             alt="<?php echo htmlspecialchars($row['name']); ?>">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="bg-light d-flex align-items-center justify-content-center rounded" style="height: 300px;">
                                                        <span class="text-muted"><i class="bi bi-image fs-2"></i></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 bg-light rounded">
                                                    <h5 class="fw-bold mb-4">Product Details</h5>
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
                                                            <p class="fw-bold fs-5 mb-0 text-primary">₱<?php echo number_format($row['price'], 2); ?></p>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="text-muted mb-1">Stock</label>
                                                            <p class="fw-bold fs-5 mb-0"><?php echo $row['stock']; ?> units</p>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="text-muted mb-1">Category</label>
                                                        <p class="mb-0">Category #<?php echo $row['category_id_fk']; ?></p>
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
                                            <form method="POST" action="delete_product.php" 
                                                  onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.')">
                                                <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="bi bi-trash me-1"></i> Delete Product
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-primary" onclick="showEditForm<?php echo $row['product_id']; ?>()">
                                                <i class="bi bi-pencil me-1"></i> Edit Product
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Edit Form Section (Initially Hidden) -->
                                    <div id="editForm<?php echo $row['product_id']; ?>" class="d-none">
                                        <div class="modal-body border-top">
                                            <h5 class="mb-4">Edit Product Details</h5>
                                            <form method="POST" action="update_product.php" class="needs-validation" novalidate>
                                                <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Product Name</label>
                                                    <input type="text" name="name" class="form-control" 
                                                           value="<?php echo htmlspecialchars($row['name']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea name="description" class="form-control" rows="3" 
                                                              required><?php echo htmlspecialchars($row['description']); ?></textarea>
                                                </div>
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Price</label>
                                                        <input type="number" step="0.01" name="price" class="form-control" 
                                                               value="<?php echo $row['price']; ?>" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Stock</label>
                                                        <input type="number" name="stock" class="form-control" 
                                                               value="<?php echo $row['stock']; ?>" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Category ID</label>
                                                        <input type="number" name="category_id_fk" class="form-control" 
                                                               value="<?php echo $row['category_id_fk']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Update Image</label>
                                                    <input type="file" name="image" class="form-control" accept="image/*">
                                                    <small class="text-muted">Leave empty to keep current image</small>
                                                </div>
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-outline-secondary" 
                                                            onclick="hideEditForm<?php echo $row['product_id']; ?>()">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="btn btn-primary">
                                                        Save Changes
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <script>
                                        function showEditForm<?php echo $row['product_id']; ?>() {
                                            document.getElementById('editForm<?php echo $row['product_id']; ?>').classList.remove('d-none');
                                        }
                                        function hideEditForm<?php echo $row['product_id']; ?>() {
                                            document.getElementById('editForm<?php echo $row['product_id']; ?>').classList.add('d-none');
                                        }
                                    </script>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <h5 class="mt-3">No Products Found</h5>
                                <p class="text-muted">Start by adding your first product using the form above.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
                    const hint = document.createElement('span');
                    hint.className = 'hint';
                    hint.textContent = 'No image selected';
                    preview.appendChild(hint);
                    return;
                }

                if (!file.type.startsWith('image/')) {
                    preview.innerHTML = '<span class="hint text-danger">Invalid file type</span>';
                    return;
                }

                const img = document.createElement('img');
                img.alt = 'Preview';
                img.src = URL.createObjectURL(file);
                img.onload = () => URL.revokeObjectURL(img.src);
                preview.appendChild(img);
            });
        }

        // Basic client-side validation (enables/disables submit)
        const form = document.getElementById('addProductForm');
        if (form) {
            const requiredFields = ['name','description','price','stock','category_id_fk'];
            const checkValidity = () => {
                let valid = true;
                requiredFields.forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    const val = (el.value || '').toString().trim();
                    if (val.length === 0) valid = false;
                    if ((id === 'price' || id === 'stock' || id === 'category_id_fk') && Number(val) <= 0) valid = false;
                });
                submitBtn.disabled = !valid;
                submitBtn.style.cursor = valid ? 'pointer' : 'not-allowed';
            };

            form.addEventListener('input', checkValidity);
            checkValidity();
        }
    });

    function resetForm() {
        const form = document.getElementById('addProductForm');
        if (form) form.reset();
        const preview = document.getElementById('imagePreview');
        if (preview) preview.innerHTML = '<span class="hint">No image selected</span>';
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) submitBtn.disabled = false;
    }
</script>

just include the edit option edit button clicked time edir quey qorking to change and also delete query working to change