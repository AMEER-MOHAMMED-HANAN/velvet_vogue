<?php
ob_start();
include '../includes/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['Admin_id'])) {
    header("Location: /velvet_vogue/admin_login.php");
    exit();
}

// Reset message on page load to avoid stale alerts
$message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval(trim($_POST['price']));
    $stock = intval(trim($_POST['stock']));
    $category_id = intval($_POST['category']);

    // Basic validation
    if (empty($name) || empty($description) || $price <= 0 || $stock < 0 || empty($category_id)) {
        $message = "<div class='alert alert-danger'>All fields are required. Price and stock must be positive.</div>";
    } else {
        $target_dir = "../images/";
        $original_filename = basename($_FILES["image"]["name"]);
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $unique_filename = time() . "_" . preg_replace("/[^a-zA-Z0-9]/", "_", $original_filename);
        $target_file = $target_dir . $unique_filename;
        $upload_ok = true;

        if (isset($_FILES["image"]) && $_FILES["image"]["error"] == UPLOAD_ERR_OK) {
            $check = getimagesize($_FILES["image"]["tmp_name"]);
            if ($check === false) {
                $message = "<div class='alert alert-danger'>File is not an image.</div>";
                $upload_ok = false;
            }

            if ($_FILES["image"]["size"] > 2000000) {
                $message = "<div class='alert alert-danger'>Sorry, your file is too large (max 2MB).</div>";
                $upload_ok = false;
            }

            $allowed_types = array("jpg", "jpeg", "png", "gif");
            if (!in_array($file_extension, $allowed_types)) {
                $message = "<div class='alert alert-danger'>Only JPG, JPEG, PNG, and GIF files are allowed.</div>";
                $upload_ok = false;
            }

            if ($upload_ok) {
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $sql = "INSERT INTO products (name, description, price, stock, category_id_fk) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssdii", $name, $description, $price, $stock, $category_id);

                    if ($stmt->execute()) {
                        $product_id = $conn->insert_id;
                        $image_sql = "INSERT INTO product_images (product_id_fk, image_url) VALUES (?, ?)";
                        $image_stmt = $conn->prepare($image_sql);
                        $image_stmt->bind_param("is", $product_id, $target_file);

                        if ($image_stmt->execute()) {
                            $message = "<div class='alert alert-success'>Product and image added successfully!</div>";
                        } else {
                            $message = "<div class='alert alert-danger'>Failed to add image. Error: " . $conn->error . "</div>";
                        }
                        $image_stmt->close();
                    } else {
                        $message = "<div class='alert alert-danger'>Failed to add product. Error: " . $conn->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='alert alert-danger'>Sorry, there was an error uploading your file. Check directory permissions.</div>";
                }
            }
        } else {
            $message = "<div class='alert alert-danger'>No image uploaded or upload failed. Error code: " . $_FILES["image"]["error"] . "</div>";
        }
    }
}

// Fetch dashboard stats
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$total_categories = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
$total_new_arrivals = $conn->query("SELECT COUNT(*) as count FROM new_arrivals")->fetch_assoc()['count'];

// Fetch stock levels for graph
$stock_levels = $conn->query("SELECT name, stock FROM products WHERE stock > 0 ORDER BY stock ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Fetch recent new arrivals
$recent_new_arrivals = $conn->query("
    SELECT na.*, p.name as product_name, p.price as original_price 
    FROM new_arrivals na 
    JOIN products p ON na.product_id_fk = p.product_id 
    ORDER BY na.arrival_date DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #feb47b;
            --background-light: #f8f9fa;
            --card-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            background-color: var(--background-light);
            font-family: 'Segoe UI', 'Arial', sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            transition: all 0.3s ease;
            min-height: 100vh;
            background: linear-gradient(135deg, #f6f8fb 0%, #f5f6f8 100%);
        }

        /* Dashboard Cards */
        .stats-card {
            background: var(--card-gradient);
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }

        .stats-card .card-body {
            padding: 1.5rem;
            color: white;
        }

        .stats-card .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .stats-card .stats-number {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stats-card .stats-label {
            font-size: 1rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* New Arrivals Section */
        .new-arrival-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .new-arrival-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .new-arrival-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .new-arrival-badge {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        /* Charts Section */
        .chart-container {
            margin-top: 2rem;
        }

        .chart-wrapper {
            position: relative;
            height: 400px;
            width: 100%;
            overflow: hidden;
        }

        .card {
            height: 100%;
            border: none;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        @media (max-width: 768px) {
            .chart-wrapper {
                height: 300px;
            }
            
            .card-header, .card-body {
                padding: 1rem;
            }
        }

        /* Recent Activity Section */
        .activity-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: background-color 0.3s ease;
        }

        .activity-item:hover {
            background-color: rgba(0,0,0,0.02);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #e9a164;
        }

        .dashboard-card p {
            font-size: 1.4rem;
            font-weight: bold;
            color: #007bff;
        }

        .product-list-table {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .product-list-table th {
            background-color: #007bff;
            color: white;
            padding: 10px;
        }

        .product-list-table td {
            padding: 10px;
            color: #333;
        }

        #stockChart {
            max-width: 600px;
            margin: 20px auto;
        }

        .row {
            margin-bottom: 1rem;
        }

        .modal-content {
            border-radius: 10px;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        @media (max-width: 767.98px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart initialization with ResizeObserver
            const chartWrapper = document.querySelector('.chart-wrapper');
            const canvas = document.getElementById('stockChart');
            const ctx = canvas.getContext('2d');
            let chart;

            // Function to update chart size
            const updateChartSize = () => {
                const parent = chartWrapper.getBoundingClientRect();
                canvas.style.width = '100%';
                canvas.style.height = '100%';
                canvas.width = parent.width;
                canvas.height = parent.height;
                
                if (chart) {
                    chart.resize();
                }
            };

            // Set up ResizeObserver
            const resizeObserver = new ResizeObserver(entries => {
                for (let entry of entries) {
                    if (entry.target === chartWrapper) {
                        updateChartSize();
                    }
                }
            });

            // Start observing the wrapper
            resizeObserver.observe(chartWrapper);

            // Also update on window resize for safety
            window.addEventListener('resize', updateChartSize);

            const stockData = {
                labels: <?php echo json_encode(array_column($stock_levels, 'name')); ?>,
                datasets: [{
                    label: 'Stock Level',
                    data: <?php echo json_encode(array_column($stock_levels, 'stock')); ?>,
                    fill: true,
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: 'rgba(102, 126, 234, 1)',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2
                }]
            };

            chart = new Chart(ctx, {
                type: 'line',
                data: stockData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 10,
                            right: 25,
                            bottom: 10,
                            left: 25
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 20,
                                color: '#2c3e50'
                            }
                        },
                        title: {
                            display: true,
                            color: '#2c3e50',
                            font: {
                                size: 5,
                                weight: 'bold'
                            },
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(255, 255, 255, 0.9)',
                            titleColor: '#2c3e50',
                            bodyColor: '#2c3e50',
                            borderColor: 'rgba(102, 126, 234, 0.3)',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return `Stock: ${context.parsed.y} units`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                stepSize: 1,
                                color: '#2c3e50',
                                font: {
                                    size: 12
                                },
                                padding: 10
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#2c3e50',
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: 12
                                },
                                padding: 10
                            }
                        }
                    },
                    animation: {
                        duration: 2000,
                        easing: 'easeInOutQuart'
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    },
                    elements: {
                        line: {
                            tension: 0.4
                        }
                    }
                }
            });

            // Sidebar toggle functionality
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');

            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                });
            }

            // Initialize all tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <main role="main" class="main-content">
        <!-- Top Navigation Bar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4 shadow-sm rounded">
            <div class="container-fluid">
                <button class="navbar-toggler border-0" type="button" id="sidebar-toggle">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <button class="btn btn-link text-dark dropdown-toggle text-decoration-none" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-2"></i>
                            <span>Admin</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/velvet_vogue/admin/admin_login.php?logout">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Dashboard Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 mb-0">Dashboard Overview</h2>
            <div class="btn-group">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New Product
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6 col-lg-3">
                <div class="stats-card h-100">
                    <div class="card-body">
                        <i class="bi bi-box-seam stats-icon"></i>
                        <div class="stats-number"><?php echo $total_products; ?></div>
                        <div class="stats-label">Total Products</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="stats-card h-100">
                    <div class="card-body">
                        <i class="bi bi-tags stats-icon"></i>
                        <div class="stats-number"><?php echo $total_categories; ?></div>
                        <div class="stats-label">Categories</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="stats-card h-100">
                    <div class="card-body">
                        <i class="bi bi-star stats-icon"></i>
                        <div class="stats-number"><?php echo $total_new_arrivals; ?></div>
                        <div class="stats-label">New Arrivals</div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-3">
                 <div class="stats-card h-100">
                    <div class="card-body">
                        <i class="bi bi-graph-up stats-icon"></i>
                        <div class="stats-number">$<?php 
                            $total_sales = $conn->query("SELECT SUM(price) as total FROM products")->fetch_assoc()['total'];
                            // Convert PHP to USD (1 USD = 56 PHP)
                            $usd_total = ($total_sales ?? 0) / 56;
                            echo number_format($usd_total, 2); 
                        ?></div>
                        <div class="stats-label">Total Inventory Value</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and New Arrivals Section -->
        <div class="row">
            <div class="col-12 col-lg-8">
                <div class="chart-container h-99">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h4 class="h5 mb-0">Stock Levels Overview</h4>
                        </div>
                        <div class="card-body p-0 d-flex flex-column">
                            <div class="chart-wrapper flex-grow-1" style="position: relative; min-height: 200px;">
                                <canvas id="stockChart" style="display: block; width: 100%; height: 100%;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <!-- Recent New Arrivals -->
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h4 class="h5 mb-0">Recent New Arrivals</h4>
                        <a href="new_arrivals.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_new_arrivals)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-star text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">No new arrivals yet.</p>
                                <a href="new_arrivals.php" class="btn btn-primary btn-sm">Add New Arrivals</a>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($recent_new_arrivals as $arrival): ?>
                                    <?php 
                                    // Convert PHP price to USD (1 USD = 56 PHP)
                                    $usd_price = number_format($arrival['price'] / 56, 2);
                                    ?>
                                    <div class="new-arrival-card">
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($arrival['image_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($arrival['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($arrival['product_name']); ?>" 
                                                     class="new-arrival-image me-3">
                                            <?php else: ?>
                                                <div class="new-arrival-image me-3 bg-light d-flex align-items-center justify-content-center rounded">
                                                    <i class="bi bi-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($arrival['product_name']); ?></h6>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold text-primary">$<?php echo $usd_price; ?></span>
                                                    <span class="new-arrival-badge"><?php echo htmlspecialchars($arrival['size']); ?></span>
                                                </div>
                                                <small class="text-muted">
                                                    Arrived: <?php echo date('M j, Y', strtotime($arrival['arrival_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product List -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h4 class="h5 mb-0">Product List</h4>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm">
                                More >>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3" style="width: 80px;">ID</th>
                                        <th class="px-4 py-3">Name</th>
                                        <th class="px-4 py-3" style="width: 120px;">Stock</th>
                                        <th class="px-4 py-3" style="width: 120px;">Price (USD)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                // Pagination configuration
                                $items_per_page = 8;
                                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                $offset = ($page - 1) * $items_per_page;

                                // Get total number of products
                                $total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
                                $total_pages = ceil($total_products / $items_per_page);

                                // Get products for current page
                                $products = $conn->query("SELECT product_id, name, stock, price FROM products ORDER BY product_id DESC LIMIT $offset, $items_per_page");
                                while ($row = $products->fetch_assoc()) {
                                    $stockClass = $row['stock'] <= 5 ? 'text-danger' : ($row['stock'] <= 10 ? 'text-warning' : 'text-success');
                                    // Convert PHP price to USD (1 USD = 56 PHP)
                                    $usd_price = number_format($row['price'] / 56, 2);
                                        echo "<tr class='align-middle'>";
                                        echo "<td class='px-4 py-3 text-muted'>#" . $row['product_id'] . "</td>";
                                        echo "<td class='px-4 py-3 fw-medium'>" . htmlspecialchars($row['name']) . "</td>";
                                        echo "<td class='px-4 py-3'><span class='badge rounded-pill {$stockClass} bg-opacity-10 {$stockClass}'>" . $row['stock'] . " units</span></td>";
                                        echo "<td class='px-4 py-3 fw-semibold'>$" . $usd_price . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-3">
                        <nav aria-label="Product navigation">
                            <ul class="pagination justify-content-end mb-0">
                                <?php
                                // Previous button
                                $prevDisabled = ($page <= 1) ? 'disabled' : '';
                                $prevPage = $page - 1;
                                echo "<li class='page-item $prevDisabled'>";
                                echo "<a class='page-link' href='?page=$prevPage' tabindex='-1'>Previous</a>";
                                echo "</li>";

                                // Numbered pages
                                for ($i = 1; $i <= $total_pages; $i++) {
                                    $active = ($i == $page) ? 'active' : '';
                                    echo "<li class='page-item $active'>";
                                    echo "<a class='page-link' href='?page=$i'>$i</a>";
                                    echo "</li>";
                                }

                                // Next button
                                $nextDisabled = ($page >= $total_pages) ? 'disabled' : '';
                                $nextPage = $page + 1;
                                echo "<li class='page-item $nextDisabled'>";
                                echo "<a class='page-link' href='?page=$nextPage'>Next</a>";
                                echo "</li>";
                                ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <style>
            /* Product List Table Styles */
            .table th {
                font-weight: 600;
                color: #2c3e50;
                background-color: #f8f9fa;
                border-bottom: 2px solid #edf2f7;
            }
            
            .table td {
                color: #4a5568;
                border-bottom: 1px solid #edf2f7;
            }

            .table tbody tr:hover {
                background-color: rgba(102, 126, 234, 0.05);
            }

            .badge {
                font-weight: 500;
                padding: 6px 12px;
            }

            .text-danger.bg-opacity-10 {
                background-color: rgba(220, 53, 69, 0.1) !important;
            }

            .text-warning.bg-opacity-10 {
                background-color: rgba(255, 193, 7, 0.1) !important;
            }

            .text-success.bg-opacity-10 {
                background-color: rgba(25, 135, 84, 0.1) !important;
            }

            .pagination .page-link {
                color: #667eea;
                padding: 0.5rem 0.75rem;
                border-color: #edf2f7;
            }

            .pagination .page-item.active .page-link {
                background-color: #667eea;
                border-color: #667eea;
                color: #ffffff !important;
            }

            .btn-outline-primary {
                color: #667eea;
                border-color: #667eea;
            }

            .btn-outline-primary:hover {
                background-color: #667eea;
                border-color: #667eea;
            }
        </style>
    </main>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($message)) echo $message; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Enter product name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" placeholder="Enter product description" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">Price (PHP)</label>
                            <input type="number" name="price" id="price" class="form-control" placeholder="Enter price in PHP" step="0.01" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required>
                            <small class="text-muted">Will be displayed as USD (1 USD = 56 PHP)</small>
                        </div>
                        <div class="mb-3">
                            <label for="stock" class="form-label">Stock</label>
                            <input type="number" name="stock" id="stock" class="form-control" placeholder="Enter stock quantity" value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select name="category" id="category" class="form-select" required>
                                <option value="">Select a category</option>
                                <?php
                                $cat_sql = "SELECT category_id, name FROM categories";
                                $cat_result = $conn->query($cat_sql);
                                while ($cat_row = $cat_result->fetch_assoc()) {
                                    $selected = (isset($_POST['category']) && $_POST['category'] == $cat_row['category_id']) ? 'selected' : '';
                                    echo "<option value='" . $cat_row['category_id'] . "' $selected>" . $cat_row['name'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Image</label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/*" required>
                        </div>
                        <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Stock Levels Chart
            const ctx = document.getElementById('stockChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($stock_levels, 'name')); ?>,
                    datasets: [{
                        label: 'Stock Level',
                        data: <?php echo json_encode(array_column($stock_levels, 'stock')); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Stock Quantity'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Products'
                            }
                        }
                    }
                }
            });

            // Sidebar Toggle
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebar-toggle');
            const mainContent = document.querySelector('.main-content');

            if (toggleBtn && sidebar && mainContent) {
                toggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');
                    if (window.innerWidth <= 767.98) {
                        sidebar.classList.toggle('active');
                    }
                });

                document.addEventListener('click', (event) => {
                    if (window.innerWidth <= 767.98 && !sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                        sidebar.classList.remove('active');
                    }
                });
            }
        });
    </script>
</body>
</html>