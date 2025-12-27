    <?php
    // Check if admin is authenticated
    if (!isset($_SESSION['Admin_id'])) {
        header("Location: /velvet_vogue/admin_login.php");
        exit();
    }
    ?>

    <nav id="sidebar" class="sidebar">
        <div class="sidebar-sticky">
            <!-- Add Bootstrap Icons CSS -->
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
            
            <div class="sidebar-brand">
                <h3 class="text-center">
                    <i class="bi bi-gem"></i>
                    <span>Velvet Vogue</span>
                </h3>
                <hr class="sidebar-divider">
            </div>

            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link sidebar-btn" href="admin_dashboard.php" id="dashboard-btn">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link sidebar-btn" href="add_product.php" id="add-product-btn">
                        <i class="bi bi-plus-circle"></i>
                        <span>Add Product</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link sidebar-btn" href="view_product.php">
                        <i class="bi bi-grid"></i>
                        <span>View Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link sidebar-btn" href="orders.php" id="orders-btn">
                        <i class="bi bi-bag"></i>
                        <span>Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link sidebar-btn" href="categories.php" id="categories-btn">
                        <i class="bi bi-bookmark"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link sidebar-btn" href="new_arrival.php" id="new-arrivals-btn">
                        <i class="bi bi-stars"></i>
                        <span>New Arrivals</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <hr class="sidebar-divider">
                <a class="nav-link sidebar-btn logout-btn" href="/velvet_vogue/admin/admin_login.php?logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <style>
            .sidebar {
                background: linear-gradient(135deg, #1a1c2d, #2d1a2c);
                color: #fff;
                min-height: 100vh;
                box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
                transition: all 0.3s ease;
                width: 250px;
            }

            .sidebar-brand {
                padding: 1.5rem 1rem;
                color: #f8f9fa;
            }

            .sidebar-brand i {
                color: #feb47b;
                margin-right: 0.5rem;
            }

            .sidebar-divider {
                border-color: rgba(255,255,255,0.1);
                margin: 1rem 0;
            }

            .sidebar-btn {
                color: #e9ecef !important;
                padding: 0.8rem 1.5rem;
                transition: all 0.3s ease;
                border: none;
                background: transparent;
                width: 100%;
                text-align: left;
                border-radius: 0;
                position: relative;
                overflow: hidden;
            }

            .sidebar-btn:hover, .sidebar-btn.active {
                background: rgba(255,255,255,0.1);
                color: #feb47b !important;
                padding-left: 2rem;
            }

            .sidebar-btn i {
                width: 25px;
                margin-right: 0.75rem;
                transition: all 0.3s ease;
            }

            .sidebar-btn:hover i {
                color: #feb47b;
                transform: scale(1.1);
            }

            .sidebar-footer {
                position: absolute;
                bottom: 0;
                width: 100%;
                padding: 1rem;
            }

            .logout-btn {
                color: #dc3545 !important;
                border-radius: 8px;
            }

            .logout-btn:hover {
                background: rgba(220, 53, 69, 0.1) !important;
                color: #ff4757 !important;
            }

            /* Active state styling */
            .sidebar-btn.active {
                background: rgba(255,255,255,0.1);
                border-left: 4px solid #feb47b;
            }

            .sidebar-btn.active i {
                color: #feb47b;
            }

            /* Hover animation */
            .sidebar-btn::after {
                content: '';
                position: absolute;
                width: 100%;
                height: 100%;
                top: 0;
                left: -100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
                transition: 0.5s;
            }

            .sidebar-btn:hover::after {
                left: 100%;
            }
        </style>

        <script>
            // Highlight the active sidebar link based on the current filename
            document.addEventListener('DOMContentLoaded', function() {
                const currentFile = window.location.pathname.split('/').pop();

                document.querySelectorAll('.sidebar-btn').forEach(link => {
                    const href = link.getAttribute('href');
                    if (!href) return;
                    const linkFile = href.split('/').pop();

                    if (linkFile === currentFile) {
                        link.classList.add('active');
                    } else {
                        link.classList.remove('active');
                    }

                    // Keep UI responsive: when a link is clicked, mark it active immediately
                    link.addEventListener('click', () => {
                        document.querySelectorAll('.sidebar-btn').forEach(l => l.classList.remove('active'));
                        link.classList.add('active');
                    });
                });
            });
        </script>
    </nav>

    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: 250px;
            z-index: 100;
            background-color: #343a40;
            transition: all 0.3s;
            padding: 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .sidebar.collapsed {
            width: 60px;
        }
        .sidebar.collapsed .sidebar-heading,
        .sidebar.collapsed .nav-link span {
            display: none;
        }
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }
        .sidebar-sticky {
            position: sticky;
            top: 0;
            height: 100vh;
            padding-top: 1rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar-heading {
            color: #fff;
            padding: 1rem;
            font-size: 1.2rem;
            text-align: center;
            border-bottom: 1px solid #495057;
        }
        .sidebar .nav-link {
            color: #fff;
            font-weight: 500;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #f8f9fa;
            background-color: #495057;
        }
        .sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }
        .content {
            margin-left: 250px;
            padding: 2rem;
            transition: all 0.3s;
        }
        .content.collapsed {
            margin-left: 60px;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }
            .sidebar .sidebar-heading,
            .sidebar .nav-link span {
                display: none;
            }
            .content {
                margin-left: 60px;
            }
        }
    </style>

    <script>
        // Toggle the sidebar collapsed state. Keeps a safe check for content element.
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            if (sidebar) sidebar.classList.toggle('collapsed');
            if (content) content.classList.toggle('collapsed');
        }
    </script>