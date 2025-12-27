<?php
include '../includes/db_connect.php';
session_start();

// Check if admin is logged in


$message = "";

// Handle add new category
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);

    // Basic validation
    if (empty($name)) {
        $message = "<div class='alert alert-danger'>Category name is required.</div>";
    } else {
        // Check if category already exists
        $check_stmt = $conn->prepare("SELECT category_id FROM categories WHERE name = ?");
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $message = "<div class='alert alert-warning'>Category '$name' already exists.</div>";
        } else {
            // Insert new category
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Category '$name' added successfully!</div>";
                // Clear form
                $_POST['name'] = '';
            } else {
                $message = "<div class='alert alert-danger'>Error adding category: " . $conn->error . "</div>";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle edit category
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_category'])) {
    $category_id = intval($_POST['category_id']);
    $name = trim($_POST['edit_name']);

    // Basic validation
    if (empty($name)) {
        $message = "<div class='alert alert-danger'>Category name is required.</div>";
    } else {
        // Check if category already exists (excluding current category)
        $check_stmt = $conn->prepare("SELECT category_id FROM categories WHERE name = ? AND category_id != ?");
        $check_stmt->bind_param("si", $name, $category_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $message = "<div class='alert alert-warning'>Category '$name' already exists.</div>";
        } else {
            // Update category
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE category_id = ?");
            $stmt->bind_param("si", $name, $category_id);
            
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Category updated successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error updating category: " . $conn->error . "</div>";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle delete category
if (isset($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    
    // Check if category is being used by any products
    $check_products = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE category_id_fk = ?");
    $check_products->bind_param("i", $category_id);
    $check_products->execute();
    $result = $check_products->get_result();
    $product_count = $result->fetch_assoc()['product_count'];
    $check_products->close();
    
    if ($product_count > 0) {
        $message = "<div class='alert alert-danger'>Cannot delete category. There are $product_count products associated with this category.</div>";
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
        $stmt->bind_param("i", $category_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Category deleted successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error deleting category: " . $conn->error . "</div>";
        }
        $stmt->close();
    }
}

// Fetch category for editing
$edit_category = null;
if (isset($_GET['edit'])) {
    $category_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_category = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all categories
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
if (!$categories_result) {
    die("Database error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2C3E50;
            --secondary-color: #34495E;
            --accent-color: #3498DB;
            --success-color: #27AE60;
            --warning-color: #F39C12;
            --danger-color: #E74C3C;
            --info-color: #2980B9;
            --light-color: #ECF0F1;
            --dark-color: #2C3E50;
            --border-color: #BDC3C7;
            --text-muted: #7F8C8D;
        }

        body {
            background: linear-gradient(135deg, #ECF0F1 0%, #D5DBDB 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .main-content.collapsed {
            margin-left: 60px;
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
            background: linear-gradient(135deg, #FFFFFF, #F8F9FA);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            border-bottom: none;
            border-radius: 15px 15px 0 0 !important;
        }

        .table th {
            background-color: #F8F9FA;
            color: var(--primary-color);
            font-weight: 600;
            padding: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--border-color);
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #F8F9FA;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-block;
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--success-color), #2ECC71);
            color: white;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--danger-color), #C0392B);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
            color: white;
        }

        .btn-add {
            background: linear-gradient(135deg, var(--accent-color), var(--info-color));
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }

        .btn-update {
            background: linear-gradient(135deg, var(--warning-color), #E67E22);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.4);
        }

        .btn-back {
            background: linear-gradient(135deg, #95A5A6, #7F8C8D);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(149, 165, 166, 0.4);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #95A5A6, #7F8C8D);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(149, 165, 166, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--border-color);
        }

        .category-id {
            font-weight: 600;
            color: var(--primary-color);
        }

        .category-name {
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            background: #FFFFFF;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .edit-form {
            background: linear-gradient(135deg, #FEF9E7, #FCF3CF);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--warning-color);
        }

        /* Alert Styles */
        .alert-success {
            background: linear-gradient(135deg, #D5F4E6, #A9DFBF);
            border: 1px solid var(--success-color);
            color: var(--dark-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, #FADBD8, #F5B7B1);
            border: 1px solid var(--danger-color);
            color: var(--dark-color);
        }

        .alert-warning {
            background: linear-gradient(135deg, #FDEBD0, #FAD7A0);
            border: 1px solid var(--warning-color);
            color: var(--dark-color);
        }

        /* Stats Cards */
        .card.bg-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
        }

        .card.bg-success {
            background: linear-gradient(135deg, var(--success-color), #229954) !important;
        }

        .card.bg-info {
            background: linear-gradient(135deg, var(--info-color), var(--accent-color)) !important;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .btn-action {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
        }

        /* Animation for new elements */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: fadeInUp 0.6s ease;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #D5DBDB;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
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
                    <h1 class="h3 mb-0"><i class="fas fa-tags me-2"></i>Manage Categories</h1>
                    <p class="mb-0 opacity-75">Add, edit, or remove product categories</p>
                </div>
                <div class="col-auto">
                    <a href="admin_dashboard.php" class="btn btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert-container">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Edit Category Form (shown when editing) -->
        <?php if (isset($edit_category)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class='mb-0'><i class='fas fa-edit me-2'></i>Edit Category</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="category_id" value="<?php echo $edit_category['category_id']; ?>">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Category Name *</label>
                                <input type="text" class="form-control" id="edit_name" name="edit_name" 
                                       value="<?php echo htmlspecialchars($edit_category['name']); ?>" 
                                       required placeholder="Enter category name">
                                <div class="form-text">Update the category name as needed.</div>
                            </div>
                        </div>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="categories.php" class="btn btn-cancel me-md-2">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                        <button type="submit" name="edit_category" class="btn btn-update">
                            <i class="fas fa-save me-1"></i>Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Add Category Form (hidden when editing) -->
        <?php if (!isset($edit_category)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class='mb-0'><i class='fas fa-plus-circle me-2'></i>Add New Category</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="name" class="form-label">Category Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       required placeholder="Enter category name">
                                <div class="form-text">This will be displayed to customers.</div>
                            </div>
                        </div>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-redo me-1"></i>Reset
                        </button>
                        <button type="submit" name="add_category" class="btn btn-add">
                            <i class="fas fa-save me-1"></i>Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Categories List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Categories</h5>
                <span class="badge bg-primary fs-6"><?php echo $categories_result->num_rows; ?> categories</span>
            </div>
            <div class="card-body p-0">
                <?php if ($categories_result->num_rows === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-tags"></i>
                        <h4>No Categories Found</h4>
                        <p>Get started by adding your first category using the form above.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th width="100">ID</th>
                                    <th>Category Name</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $categories_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <span class="category-id">#<?php echo $row['category_id']; ?></span>
                                        </td>
                                        <td>
                                            <span class="category-name"><?php echo htmlspecialchars($row['name']); ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?edit=<?php echo $row['category_id']; ?>" 
                                                   class="btn btn-action btn-edit me-2">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </a>
                                                <a href="?delete=<?php echo $row['category_id']; ?>" 
                                                   class="btn btn-action btn-delete"
                                                   onclick="return confirm('Are you sure you want to delete the category \"<?php echo addslashes($row['name']); ?>\"? This action cannot be undone.')">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $categories_result->num_rows; ?></h4>
                                <p class="card-text">Total Categories</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tags fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title">Active</h4>
                                <p class="card-text">All Categories</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title">Manage</h4>
                                <p class="card-text">Product Organization</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-cog fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });

            // Add smooth animations to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.1}s`;
            });

            // Focus on appropriate field
            <?php if (isset($edit_category)): ?>
                document.getElementById('edit_name')?.focus();
            <?php else: ?>
                document.getElementById('name')?.focus();
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php 
$conn->close();
?>