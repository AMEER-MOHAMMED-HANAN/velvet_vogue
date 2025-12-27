    <?php
    include '../includes/db_connect.php';
    session_start();

    // Check if admin is logged in
    if (!isset($_SESSION['Admin_id'])) {
        header("Location: /velvet_vogue/admin_login.php");
        exit();
    }

    // Reset message on page load to avoid stale alerts
    $message = "";

    // Check if new_arrivals table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'new_arrivals'");
    if ($table_check->num_rows == 0) {
        // Create the table if it doesn't exist
        $create_table_sql = "CREATE TABLE new_arrivals (
            new_arrivel_id INT AUTO_INCREMENT PRIMARY KEY,
            product_id_fk INT NOT NULL,
            arrival_date DATE NOT NULL,
            image_url VARCHAR(500),
            price DECIMAL(10,2),
            size VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id_fk) REFERENCES products(product_id) ON DELETE CASCADE
        )";
        
        if ($conn->query($create_table_sql)) {
            $message = "<div class='alert alert-success'>New arrivals table created successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error creating table: " . $conn->error . "</div>";
        }
    } else {
        // Check if columns exist and add them if missing
        $columns_to_check = ['image_url', 'price', 'size'];
        foreach ($columns_to_check as $column) {
            $check_column = $conn->query("SHOW COLUMNS FROM new_arrivals LIKE '$column'");
            if ($check_column->num_rows == 0) {
                if ($column == 'image_url') {
                    $conn->query("ALTER TABLE new_arrivals ADD COLUMN image_url VARCHAR(500)");
                } elseif ($column == 'price') {
                    $conn->query("ALTER TABLE new_arrivals ADD COLUMN price DECIMAL(10,2)");
                } elseif ($column == 'size') {
                    $conn->query("ALTER TABLE new_arrivals ADD COLUMN size VARCHAR(50)");
                }
            }
        }
    }

    // Fetch existing products for dropdown
    $products = [];
    $product_result = $conn->query("SELECT product_id, name, price FROM products ORDER BY name");
    if ($product_result) {
        while ($row = $product_result->fetch_assoc()) {
            $products[] = $row;
        }
    }

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_new_arrival'])) {
        $product_id = intval($_POST['product_id']);
        $arrival_date = trim($_POST['arrival_date']);
        $price = floatval(trim($_POST['price']));
        $size = trim($_POST['size']);

        // Basic validation
        if (empty($product_id) || empty($arrival_date) || empty($price) || empty($size)) {
            $message = "<div class='alert alert-danger'>All fields are required.</div>";
        } else {
            // Validate if product exists
            $check_product = $conn->prepare("SELECT product_id, name FROM products WHERE product_id = ?");
            $check_product->bind_param("i", $product_id);
            $check_product->execute();
            $product_result = $check_product->get_result();
            
            if ($product_result->num_rows == 0) {
                $message = "<div class='alert alert-danger'>Selected product does not exist.</div>";
                $check_product->close();
            } else {
                $product_data = $product_result->fetch_assoc();
                $check_product->close();
                
                // Validate date format
                if (!DateTime::createFromFormat('Y-m-d', $arrival_date)) {
                    $message = "<div class='alert alert-danger'>Invalid date format. Use YYYY-MM-DD.</div>";
                } else {
                    // Check if this product is already marked as new arrival
                    $check_existing = $conn->prepare("SELECT new_arrivel_id FROM new_arrivals WHERE product_id_fk = ?");
                    $check_existing->bind_param("i", $product_id);
                    $check_existing->execute();
                    $check_existing->store_result();
                    
                    if ($check_existing->num_rows > 0) {
                        $message = "<div class='alert alert-warning'>This product is already marked as a new arrival.</div>";
                        $check_existing->close();
                    } else {
                        $check_existing->close();
                        
                        // Handle image upload
                        $image_url = '';
                        $upload_ok = true;
                        
                        if (isset($_FILES["image"]) && $_FILES["image"]["error"] == UPLOAD_ERR_OK) {
                            $target_dir = "../images/new_arrivals/";
                            $original_filename = basename($_FILES["image"]["name"]);
                            $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                            $unique_filename = time() . "_" . uniqid() . "." . $file_extension;
                            $target_file = $target_dir . $unique_filename;
                            
                            // Check if image file is a actual image
                            $check = getimagesize($_FILES["image"]["tmp_name"]);
                            if ($check === false) {
                                $message = "<div class='alert alert-danger'>File is not an image.</div>";
                                $upload_ok = false;
                            }
                            
                            // Check file size (2MB maximum)
                            if ($_FILES["image"]["size"] > 2000000) {
                                $message = "<div class='alert alert-danger'>Sorry, your file is too large (max 2MB).</div>";
                                $upload_ok = false;
                            }
                            
                            // Allow certain file formats
                            $allowed_types = array("jpg", "jpeg", "png", "gif");
                            if (!in_array($file_extension, $allowed_types)) {
                                $message = "<div class='alert alert-danger'>Only JPG, JPEG, PNG, and GIF files are allowed.</div>";
                                $upload_ok = false;
                            }
                            
                            if ($upload_ok) {
                                if (!file_exists($target_dir)) {
                                    mkdir($target_dir, 0755, true);
                                }
                                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                                    $image_url = $target_file;
                                } else {
                                    $message = "<div class='alert alert-danger'>Sorry, there was an error uploading your file.</div>";
                                    $upload_ok = false;
                                }
                            }
                        } else {
                            // No image uploaded, use default placeholder
                            $image_url = '';
                            $upload_ok = true;
                        }
                        
                        if ($upload_ok) {
                            // Insert new arrival
                            $sql = "INSERT INTO new_arrivals (product_id_fk, arrival_date, image_url, price, size) VALUES (?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            
                            if ($stmt === false) {
                                $message = "<div class='alert alert-danger'>Prepare failed: " . $conn->error . "</div>";
                            } else {
                                $stmt->bind_param("issds", $product_id, $arrival_date, $image_url, $price, $size);
                                
                                if ($stmt->execute()) {
                                    $message = "<div class='alert alert-success'>Product '{$product_data['name']}' successfully marked as new arrival!</div>";
                                    // Clear form
                                    $_POST = array();
                                } else {
                                    $message = "<div class='alert alert-danger'>Failed to add new arrival. Error: " . $conn->error . "</div>";
                                }
                                $stmt->close();
                            }
                        }
                    }
                }
            }
        }
    }

    // Handle delete action
    if (isset($_GET['delete'])) {
        $arrival_id = intval($_GET['delete']);
        
        // Get image URL before deleting to remove the file
        $get_image = $conn->prepare("SELECT image_url FROM new_arrivals WHERE new_arrivel_id = ?");
        $get_image->bind_param("i", $arrival_id);
        $get_image->execute();
        $image_result = $get_image->get_result();
        $image_data = $image_result->fetch_assoc();
        $get_image->close();
        
        // Delete the record
        $stmt = $conn->prepare("DELETE FROM new_arrivals WHERE new_arrivel_id = ?");
        $stmt->bind_param("i", $arrival_id);
        
        if ($stmt->execute()) {
            // Delete the image file if it exists
            if (!empty($image_data['image_url']) && file_exists($image_data['image_url'])) {
                unlink($image_data['image_url']);
            }
            $message = "<div class='alert alert-success'>New arrival removed successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to remove new arrival. Error: " . $conn->error . "</div>";
        }
        $stmt->close();
    }

    // Fetch existing new arrivals for display
    $new_arrivals = [];
    $arrivals_result = $conn->query("
        SELECT na.new_arrivel_id, na.arrival_date, na.image_url, na.price, na.size, 
            p.product_id, p.name as product_name
        FROM new_arrivals na 
        JOIN products p ON na.product_id_fk = p.product_id 
        ORDER BY na.arrival_date DESC
    ");

    if ($arrivals_result) {
        while ($row = $arrivals_result->fetch_assoc()) {
            $new_arrivals[] = $row;
        }
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin - Manage New Arrivals</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary-color: #2c3e50;
                --secondary-color: #3498db;
                --accent-color: #e74c3c;
            }

            body {
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                min-height: 100vh;
            }

            .main-content {
                margin-left: 250px;
                padding: 30px;
                transition: all 0.3s ease;
                min-height: 100vh;
            }

            .page-header {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                color: white;
                padding: 2rem;
                border-radius: 15px;
                margin-bottom: 2rem;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }

            .card {
                border: none;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                margin-bottom: 2rem;
            }

            .card-header {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                color: white;
                padding: 1.5rem;
                border-bottom: none;
                border-radius: 15px 15px 0 0 !important;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--secondary-color), #2980b9);
                border: none;
                border-radius: 25px;
                padding: 0.75rem 1.5rem;
                font-weight: 600;
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
            }

            .btn-danger {
                background: linear-gradient(135deg, #e74c3c, #c0392b);
                border: none;
                border-radius: 25px;
            }

            .table th {
                background-color: #f8f9fa;
                color: var(--primary-color);
                font-weight: 600;
                padding: 1rem;
            }

            .product-image {
                width: 80px;
                height: 80px;
                object-fit: cover;
                border-radius: 10px;
                border: 2px solid #e9ecef;
            }

            .image-placeholder {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6c757d;
                border-radius: 10px;
                border: 2px dashed #dee2e6;
            }

            .stats-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 15px;
                padding: 1.5rem;
                text-align: center;
                margin-bottom: 1rem;
            }

            .size-badge {
                background: linear-gradient(135deg, #27ae60, #2ecc71);
                color: white;
                padding: 0.3rem 0.8rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 500;
            }

            @media (max-width: 768px) {
                .main-content {
                    margin-left: 0;
                    padding: 15px;
                }
            }
        </style>
    </head>
    <body>
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <main role="main" class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h1 class="h3 mb-0"><i class="fas fa-star me-2"></i>Manage New Arrivals</h1>
                        <p class="mb-0 opacity-75">Add new arrival products with images, prices, and sizes</p>
                    </div>
                    <div class="col-auto">
                        <a href="admin_dashboard.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($message)) echo $message; ?>

            <div class="row">
                <!-- Add New Arrival Form -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Add New Arrival</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="product_id" class="form-label">Select Product *</label>
                                    <select class="form-select" id="product_id" name="product_id" required>
                                        <option value="">Choose a product...</option>
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?php echo $product['product_id']; ?>" 
                                                <?php echo (isset($_POST['product_id']) && $_POST['product_id'] == $product['product_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($product['name']); ?> ($<?php echo number_format($product['price'], 2); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($products)): ?>
                                        <div class="alert alert-warning mt-2">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            No products available. Please add products first from the products page.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="arrival_date" class="form-label">Arrival Date *</label>
                                    <input type="date" class="form-control" id="arrival_date" name="arrival_date" 
                                        value="<?php echo isset($_POST['arrival_date']) ? htmlspecialchars($_POST['arrival_date']) : date('Y-m-d'); ?>" 
                                        required>
                                </div>

                                <div class="mb-3">
                                    <label for="price" class="form-label">Price ($) *</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                        step="0.01" min="0" 
                                        value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" 
                                        required placeholder="Enter price">
                                </div>

                                <div class="mb-3">
                                    <label for="size" class="form-label">Size *</label>
                                    <select class="form-select" id="size" name="size" required>
                                        <option value="">Select size...</option>
                                        <option value="XS" <?php echo (isset($_POST['size']) && $_POST['size'] == 'XS') ? 'selected' : ''; ?>>XS</option>
                                        <option value="S" <?php echo (isset($_POST['size']) && $_POST['size'] == 'S') ? 'selected' : ''; ?>>S</option>
                                        <option value="M" <?php echo (isset($_POST['size']) && $_POST['size'] == 'M') ? 'selected' : ''; ?>>M</option>
                                        <option value="L" <?php echo (isset($_POST['size']) && $_POST['size'] == 'L') ? 'selected' : ''; ?>>L</option>
                                        <option value="XL" <?php echo (isset($_POST['size']) && $_POST['size'] == 'XL') ? 'selected' : ''; ?>>XL</option>
                                        <option value="XXL" <?php echo (isset($_POST['size']) && $_POST['size'] == 'XXL') ? 'selected' : ''; ?>>XXL</option>
                                        <option value="One Size" <?php echo (isset($_POST['size']) && $_POST['size'] == 'One Size') ? 'selected' : ''; ?>>One Size</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="image" class="form-label">Product Image</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <div class="form-text">Upload a product image (JPG, PNG, GIF - max 2MB). Optional.</div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="add_new_arrival" class="btn btn-primary" <?php echo empty($products) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-check me-2"></i> Add New Arrival
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats & Recent Arrivals -->
                <div class="col-lg-6 mb-4">
                    <div class="row">
                        <div class="col-6">
                            <div class="stats-card">
                                <h4 class="mb-1"><?php echo count($new_arrivals); ?></h4>
                                <small>New Arrivals</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stats-card">
                                <h4 class="mb-1"><?php echo count($products); ?></h4>
                                <small>Total Products</small>
                            </div>
                        </div>
                    </div>

                    <!-- Recent New Arrivals -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i> Recent New Arrivals</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($new_arrivals)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-star fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">No new arrivals yet. Add new arrivals using the form.</p>
                                </div>
                            <?php else: ?>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach (array_slice($new_arrivals, 0, 8) as $arrival): ?>
                                        <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                            <?php if (!empty($arrival['image_url'])): ?>
                                                <img src="<?php echo $arrival['image_url']; ?>" alt="<?php echo htmlspecialchars($arrival['product_name']); ?>" class="product-image me-3">
                                            <?php else: ?>
                                                <div class="image-placeholder me-3">
                                                    <i class="fas fa-image fa-lg"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <strong><?php echo htmlspecialchars($arrival['product_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    Arrived: <?php echo date('M j, Y', strtotime($arrival['arrival_date'])); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-success d-block mb-1">$<?php echo number_format($arrival['price'], 2); ?></span>
                                                <span class="size-badge"><?php echo htmlspecialchars($arrival['size']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Arrivals List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i> All New Arrivals</h5>
                    <span class="badge bg-primary"><?php echo count($new_arrivals); ?> items</span>
                </div>
                <div class="card-body">
                    <?php if (empty($new_arrivals)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No New Arrivals</h5>
                            <p class="text-muted">Add new arrivals using the form above.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Product Name</th>
                                        <th>Price</th>
                                        <th>Size</th>
                                        <th>Arrival Date</th>
                                        <th>Days Since</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($new_arrivals as $arrival): 
                                        $days_since = floor((time() - strtotime($arrival['arrival_date'])) / (60 * 60 * 24));
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($arrival['image_url'])): ?>
                                                    <img src="<?php echo $arrival['image_url']; ?>" alt="<?php echo htmlspecialchars($arrival['product_name']); ?>" class="product-image">
                                                <?php else: ?>
                                                    <div class="image-placeholder">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($arrival['product_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">ID: <?php echo $arrival['product_id']; ?></small>
                                            </td>
                                            <td class="fw-bold">$<?php echo number_format($arrival['price'], 2); ?></td>
                                            <td>
                                                <span class="size-badge"><?php echo htmlspecialchars($arrival['size']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($arrival['arrival_date'])); ?>
                                                <br>
                                                <small class="text-muted"><?php echo $arrival['arrival_date']; ?></small>
                                            </td>
                                            <td>
                                                <?php if ($days_since == 0): ?>
                                                    <span class="badge bg-success">Today</span>
                                                <?php elseif ($days_since == 1): ?>
                                                    <span class="badge bg-info">Yesterday</span>
                                                <?php elseif ($days_since < 7): ?>
                                                    <span class="badge bg-primary"><?php echo $days_since; ?> days</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo $days_since; ?> days</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?delete=<?php echo $arrival['new_arrivel_id']; ?>" 
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Are you sure you want to remove \"<?php echo addslashes($arrival['product_name']); ?>\" from new arrivals?')">
                                                    <i class="fas fa-trash me-1"></i> Remove
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Set minimum date to today
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('arrival_date').min = today;
                
                // Auto-focus on product select
                document.getElementById('product_id').focus();

                // Image preview functionality
                const imageInput = document.getElementById('image');
                if (imageInput) {
                    imageInput.addEventListener('change', function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            // Validate file size
                            if (file.size > 2 * 1024 * 1024) {
                                alert('File size must be less than 2MB.');
                                e.target.value = '';
                                return;
                            }
                        }
                    });
                }
            });
        </script>
    </body>
    </html>
    <?php 
    $conn->close();
    ?>