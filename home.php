<?php
session_start();
// Home page updated for UI improvement

// Database connection with error handling
$db_path = '../includes/db_connect.php';
if (file_exists($db_path)) {
    include $db_path;
} else {
    // If db_connect.php doesn't exist, create a basic connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "vi";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        $db_error = "Database connection failed: " . $conn->connect_error;
    }
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['loggedin']);

// Initialize variables
$new_arrivals = [];
$featured_products = [];
$categories = [];
$db_error = null;

// Fetch data only if connection is established
if (isset($conn) && $conn && !$conn->connect_error) {
    try {
        // Fetch Categories
        $categories_query = "
            SELECT 
                c.category_id,
                c.name as category_name,
                (
                    SELECT COUNT(*) 
                    FROM products p 
                    WHERE p.category_id_fk = c.category_id
                ) as product_count
            FROM categories c
            ORDER BY c.name
            LIMIT 6
        ";
        
        $categories_result = $conn->query($categories_query);
        if ($categories_result && $categories_result->num_rows > 0) {
            while ($row = $categories_result->fetch_assoc()) {
                $categories[] = $row;
            }
        }

        // Fetch New Arrivals with correct image column
        $new_arrivals_query = "
            SELECT 
                na.new_arrivel_id,
                na.arrival_date,
                na.product_id_fk as product_id,
                na.image_url as arrival_image,
                na.price as arrival_price,
                na.size,
                p.name as product_name,
                p.description,
                p.price as product_price,
                p.stock,
                pi.image_url as product_image,
                c.name as category_name
            FROM new_arrivals na
            LEFT JOIN products p ON na.product_id_fk = p.product_id
            LEFT JOIN product_images pi ON na.product_id_fk = pi.product_id_fk
            LEFT JOIN categories c ON p.category_id_fk = c.category_id
            ORDER BY na.arrival_date DESC, na.created_at DESC
            LIMIT 8
        ";
        
        $new_arrivals_result = $conn->query($new_arrivals_query);
        if ($new_arrivals_result && $new_arrivals_result->num_rows > 0) {
            while ($row = $new_arrivals_result->fetch_assoc()) {
                // Always set the 'name' key from product_name
                $row['name'] = isset($row['product_name']) ? $row['product_name'] : 'Unnamed Product';
                
                // Handle image paths
                if (!empty($row['arrival_image'])) {
                    $possible_paths = [
                        $row['arrival_image'],
                        str_replace('../', '', $row['arrival_image']),
                        'images/new_arrivals/' . basename($row['arrival_image']),
                        $row['arrival_image']
                    ];
                    
                    $found_path = '';
                    foreach ($possible_paths as $path) {
                        if (file_exists($path)) {
                            $found_path = $path;
                            break;
                        }
                    }
                    
                    if (!empty($found_path)) {
                        $row['image_url'] = $found_path;
                    } else {
                        if (!empty($row['product_image']) && file_exists($row['product_image'])) {
                            $row['image_url'] = $row['product_image'];
                        } else {
                            $row['image_url'] = 'images/default_product.jpg';
                        }
                    }
                } else {
                    if (!empty($row['product_image']) && file_exists($row['product_image'])) {
                        $row['image_url'] = $row['product_image'];
                    } else {
                        $row['image_url'] = 'images/default_product.jpg';
                    }
                }
                
                $row['price'] = !empty($row['arrival_price']) ? $row['arrival_price'] : (isset($row['product_price']) ? $row['product_price'] : 0.00);
                $row['display_image'] = $row['image_url'];
                $new_arrivals[] = $row;
            }
        }

        // Fetch Featured Products
        $featured_query = "
            SELECT 
                p.product_id,
                p.name as product_name,
                p.description,
                p.price,
                p.stock,
                COALESCE(pi.image_url, 'images/default_product.jpg') as image_url,
                COALESCE(c.name, 'Uncategorized') as category_name,
                CASE 
                    WHEN p.stock > 10 THEN 'In Stock'
                    WHEN p.stock > 0 THEN 'Low Stock'
                    ELSE 'Out of Stock'
                END as stock_status
            FROM products p
            LEFT JOIN product_images pi ON p.product_id = pi.product_id_fk
            LEFT JOIN categories c ON p.category_id_fk = c.category_id
            WHERE p.product_id IS NOT NULL
            ORDER BY p.product_id DESC
            LIMIT 6
        ";
        
        $featured_result = $conn->query($featured_query);
        if ($featured_result && $featured_result->num_rows > 0) {
            while ($row = $featured_result->fetch_assoc()) {
                // Always set the 'name' key from product_name
                $row['name'] = isset($row['product_name']) ? $row['product_name'] : 'Unnamed Product';
                
                if (!empty($row['image_url']) && file_exists($row['image_url'])) {
                    $row['image_url'] = $row['image_url'];
                } elseif (!empty($row['image_url'])) {
                    if (strpos($row['image_url'], 'http') !== 0) {
                        if (file_exists('../' . $row['image_url'])) {
                            $row['image_url'] = '../' . $row['image_url'];
                        } elseif (file_exists($row['image_url'])) {
                            $row['image_url'] = $row['image_url'];
                        } else {
                            $row['image_url'] = 'images/default_product.jpg';
                        }
                    }
                } else {
                    $row['image_url'] = 'images/default_product.jpg';
                }
                
                $featured_products[] = $row;
            }
        }
        
        if (empty($featured_products) && !empty($new_arrivals)) {
            $featured_products = array_slice($new_arrivals, 0, 6);
        }

    } catch (Exception $e) {
        $db_error = "Error fetching data: " . $e->getMessage();
    }
} else {
    $db_error = "Database connection not available. Showing demo content.";
}

// Fallback data
if ($db_error || empty($new_arrivals)) {
    if (isset($conn) && $conn && !$conn->connect_error) {
        $fallback_query = "
            SELECT 
                na.new_arrivel_id,
                na.arrival_date,
                na.product_id_fk as product_id,
                na.image_url,
                na.price,
                na.size,
                p.name as product_name,
                p.description,
                p.stock
            FROM new_arrivals na
            LEFT JOIN products p ON na.product_id_fk = p.product_id
            ORDER BY na.arrival_date DESC
            LIMIT 8
        ";
        
        $fallback_result = $conn->query($fallback_query);
        if ($fallback_result && $fallback_result->num_rows > 0) {
            while ($row = $fallback_result->fetch_assoc()) {
                // Always set the 'name' key
                $row['name'] = isset($row['product_name']) ? $row['product_name'] : 'Unnamed Product';
                
                if (!empty($row['image_url'])) {
                    $possible_paths = [
                        $row['image_url'],
                        str_replace('../', '', $row['image_url']),
                        'images/new_arrivals/' . basename($row['image_url']),
                        $row['image_url']
                    ];
                    
                    $found_path = '';
                    foreach ($possible_paths as $path) {
                        if (file_exists($path)) {
                            $found_path = $path;
                            break;
                        }
                    }
                    
                    if (!empty($found_path)) {
                        $row['image_url'] = $found_path;
                    } else {
                        $row['image_url'] = 'images/default_product.jpg';
                    }
                } else {
                    $row['image_url'] = 'images/default_product.jpg';
                }
                
                $row['display_image'] = $row['image_url'];
                $row['category_name'] = 'New Arrival';
                $new_arrivals[] = $row;
            }
        }
    }
    
    // If still no new arrivals, show demo data
    if (empty($new_arrivals)) {
        $new_arrivals = [
            [
                'product_id' => 1,
                'product_name' => 'Elegant Office Blazer',
                'name' => 'Elegant Office Blazer',
                'description' => 'Premium office wear for professional settings',
                'price' => 99.99,
                'stock' => 15,
                'arrival_date' => date('Y-m-d'),
                'display_image' => 'images/default_product.jpg',
                'image_url' => 'images/default_product.jpg',
                'category_name' => 'Office Wear'
            ]
        ];
    }
    
    if (empty($featured_products)) {
        $featured_products = [
            [
                'product_id' => 1,
                'product_name' => 'Premium Office Suit',
                'name' => 'Premium Office Suit',
                'description' => 'High-quality office wear for professionals',
                'price' => 129.99,
                'stock' => 10,
                'category_name' => 'Office Wear',
                'image_url' => 'images/default_product.jpg',
                'stock_status' => 'In Stock'
            ]
        ];
    }
    
    if (empty($categories)) {
        $categories = [
            ['category_id' => 1, 'name' => 'Office Wear', 'product_count' => 12],
            ['category_id' => 2, 'name' => 'Formal Wear', 'product_count' => 8]
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velvet Vogue | Premium Fashion Collection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos@2.3.4/aos.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --highlight: #e94560;
            --light: #f5f5f5;
            --gold: #ffd700;
            --gray: #8a8a8a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
            color: #333;
            overflow-x: hidden;
            padding: 0;
            margin: 0;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
        }

        /* Modern Navigation */
        .modern-navbar {
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        .modern-navbar.scrolled {
            padding: 0.7rem 0;
            background: rgba(26, 26, 46, 0.98);
        }

        .brand-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--light);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-logo i {
            color: var(--highlight);
        }

        .nav-link-modern {
            color: var(--light) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link-modern:hover {
            color: var(--highlight) !important;
        }

        .nav-link-modern::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--highlight);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link-modern:hover::after {
            width: 100%;
        }

        .nav-link-modern.active {
            color: var(--highlight) !important;
        }

        .nav-link-modern.active::after {
            width: 100%;
        }

        .btn-modern {
            background: linear-gradient(135deg, var(--highlight), #ff6b8b);
            border: none;
            color: white;
            padding: 0.7rem 1.5rem;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(233, 69, 96, 0.3);
            color: white;
        }

        .btn-modern-outline {
            background: transparent;
            border: 2px solid var(--highlight);
            color: var(--highlight);
        }

        .btn-modern-outline:hover {
            background: var(--highlight);
            color: white;
        }

        /* Notification Icon */
        .notification-icon {
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--highlight);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Cart Icon Style */
        .cart-icon-container {
            position: relative;
        }

        .cart-icon-link {
            color: var(--light);
            font-size: 1.2rem;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            border: 2px solid transparent;
        }

        .cart-icon-link:hover {
            color: var(--highlight);
            border-color: var(--highlight);
            background: rgba(233, 69, 96, 0.1);
        }

        /* HERO SECTION - FIXED FOR FULL SCREEN */
        .modern-hero {
            background: linear-gradient(rgba(26, 26, 46, 0.85), rgba(22, 33, 62, 0.92)), 
                        url('https://images.unsplash.com/photo-1445205170230-053b83016050?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            height: 100vh;
            width: 100vw;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            position: relative;
            left: 0;
            top: 0;
        }

        /* Ensure the container takes full height */
        .modern-hero .container {
            height: 100%;
            display: flex;
            align-items: center;
            padding-top: 0;
            padding-bottom: 0;
        }

        /* Remove any padding from the row */
        .modern-hero .row {
            margin-left: 0;
            margin-right: 0;
        }

        .hero-content {
            max-width: 600px;
            z-index: 2;
            padding: 30px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 15px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .hero-title {
            font-size: 4.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: white;
            line-height: 1.1;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 2.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 300;
            line-height: 1.6;
        }

        /* Product Cards */
        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.4s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            height: 100%;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .product-image-container {
            height: 280px;
            overflow: hidden;
            position: relative;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.1);
        }

        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--highlight);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 10;
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .product-price {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--highlight);
            margin-bottom: 0.8rem;
        }

        .product-description {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 1.2rem;
            line-height: 1.5;
            height: 40px;
            overflow: hidden;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-top: 0.8rem;
            border-top: 1px solid #eee;
        }

        .product-category {
            background: var(--accent);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .product-date {
            color: var(--gray);
            font-size: 0.8rem;
        }

        /* Section Styles */
        .section-modern {
            padding: 80px 0;
        }

        .section-title {
            font-size: 2.8rem;
            text-align: center;
            margin-bottom: 3rem;
            color: var(--primary);
            position: relative;
            padding-bottom: 1rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--highlight);
            border-radius: 2px;
        }

        .section-subtitle {
            text-align: center;
            color: var(--gray);
            margin-bottom: 4rem;
            font-size: 1.1rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Category Cards */
        .category-card {
            height: 250px;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }

        .category-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .category-card:hover .category-image {
            transform: scale(1.1);
        }

        .category-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(26, 26, 46, 0.9), transparent);
            padding: 2rem;
            color: white;
        }

        .category-name {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .category-count {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Newsletter */
        .newsletter-section {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }

        .newsletter-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(233, 69, 96, 0.1) 0%, transparent 70%);
        }

        .newsletter-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .newsletter-input {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 30px;
            border-right: none;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .newsletter-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .newsletter-input:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--highlight);
            color: white;
            box-shadow: none;
        }

        /* Modern Footer */
        .modern-footer {
            background: var(--primary);
            color: var(--light);
            padding: 80px 0 30px;
        }

        .footer-title {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .footer-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--highlight);
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.7rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: var(--highlight);
            padding-left: 5px;
        }

        .social-modern {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-icon-modern {
            width: 40px;
            height: 40px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-icon-modern:hover {
            background: var(--highlight);
            color: white;
            border-color: var(--highlight);
            transform: translateY(-3px);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 2rem;
            margin-top: 3rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.8rem;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
            
            .modern-hero {
                height: 100vh;
            }
            
            .product-image-container {
                height: 220px;
            }
            
            .category-card {
                height: 200px;
            }
            
            .brand-logo {
                font-size: 1.5rem;
            }
            
            .nav-link-modern {
                margin: 0 0.3rem;
                padding: 0.3rem 0.8rem;
                font-size: 0.9rem;
            }
            
            .cart-icon-link {
                padding: 0.3rem 0.8rem;
                font-size: 1rem;
            }
            
            .hero-content {
                padding: 20px;
                margin: 20px;
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 2.2rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .hero-content {
                padding: 15px;
                margin: 15px;
            }
            
            .section-modern {
                padding: 50px 0;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-modern {
            animation: fadeInUp 0.8s ease;
        }

        /* Image Fallback */
        .image-fallback {
            width: 100%;
            height: 280px;
            background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }

        .image-fallback i {
            font-size: 3rem;
            opacity: 0.5;
        }

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
            background: #d1345b;
        }

        /* Add padding-top to body to account for fixed navbar */
        body {
            padding-top: 70px;
        }
        
        /* Hero section should start after navbar */
        .modern-hero {
            margin-top: -70px;
        }
    </style>
</head>
<body>
    <!-- Modern Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark modern-navbar fixed-top">
        <div class="container">
            <a class="navbar-brand brand-logo" href="home.php">
                <i class="fas fa-crown"></i> VELVET VOGUE
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#modernNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="modernNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern active" href="home.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="products.php">Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="promotions.php">Promotions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="contact.php">Contact</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <!-- Notification Icon -->
                    <div class="notification-icon">
                        <a href="notifications.php" class="cart-icon-link">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge"></span>
                        </a>
                    </div>
                    
                    <!-- Cart Icon -->
                    <div class="cart-icon-container">
                        <a href="cart.php" class="cart-icon-link">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Cart</span>
                        </a>
                    </div>
                    
                    <!-- User Authentication - Show different options based on login status -->
                    <?php if($isLoggedIn): ?>
                        <!-- User is logged in - Show logout option -->
                        <a href="logout.php" class="btn-modern">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    <?php else: ?>
                        <!-- User is not logged in - Show login option -->
                        <a href="login.php" class="btn-modern">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Modern Hero Section -->
    <section class="modern-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 animate-modern">
                    <h1 class="hero-title">ELEGANCE MEETS MODERNITY</h1>
                    <p class="hero-subtitle">Discover our exclusive collection where timeless elegance meets contemporary design. Experience premium fashion crafted with passion and precision.</p>
                    <div class="d-flex gap-3">
                        <a href="products.php" class="btn-modern">
                            <i class="fas fa-shopping-bag me-2"></i> Shop Now
                        </a>
                        <a href="#new-arrivals" class="btn-modern btn-modern-outline">
                            <i class="fas fa-arrow-down me-2"></i> Explore
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- New Arrivals Section -->
    <section id="new-arrivals" class="section-modern">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">NEW ARRIVALS</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Discover our latest collection - fresh styles added weekly</p>
            
            <div class="row">
                <?php if(empty($new_arrivals)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No new arrivals found. Check back soon for updates!
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($new_arrivals as $index => $product): 
                        $product_name = isset($product['name']) ? $product['name'] : (isset($product['product_name']) ? $product['product_name'] : 'Unnamed Product');
                        $product_description = isset($product['description']) ? $product['description'] : 'No description available';
                        $product_price = isset($product['price']) ? $product['price'] : 0.00;
                        $product_image = isset($product['image_url']) ? $product['image_url'] : 'images/default_product.jpg';
                        $product_arrival_date = isset($product['arrival_date']) ? $product['arrival_date'] : date('Y-m-d');
                        $product_id = isset($product['product_id']) ? $product['product_id'] : 0;
                        $category_name = isset($product['category_name']) ? $product['category_name'] : 'Uncategorized';
                        $days_since = isset($product['arrival_date']) ? floor((time() - strtotime($product['arrival_date'])) / (60 * 60 * 24)) : 0;
                    ?>
                        <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo ($index * 100) + 150; ?>">
                            <div class="product-card">
                                <span class="product-badge">
                                    <i class="fas fa-fire me-1"></i> NEW
                                </span>
                                
                                <div class="product-image-container">
                                    <?php if(isset($product_image) && file_exists($product_image)): ?>
                                        <img src="<?php echo $product_image; ?>" 
                                             alt="<?php echo htmlspecialchars($product_name); ?>" 
                                             class="product-image">
                                    <?php else: ?>
                                        <div class="image-fallback">
                                            <i class="fas fa-tshirt"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info">
                                    <h5 class="product-title"><?php echo htmlspecialchars($product_name); ?></h5>
                                    <p class="product-price">$<?php echo number_format($product_price, 2); ?></p>
                                    <p class="product-description"><?php echo htmlspecialchars(substr($product_description, 0, 80)); ?>...</p>
                                    
                                    <div class="product-meta">
                                        <span class="product-category"><?php echo htmlspecialchars($category_name); ?></span>
                                        <span class="product-date">
                                            <?php 
                                            if(!empty($product_arrival_date)) {
                                                echo date('M d', strtotime($product_arrival_date));
                                                if ($days_since < 7) {
                                                    echo ' <span class="badge bg-info">New</span>';
                                                }
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <?php if($product_id > 0): ?>
                                        <a href="products.php?product_id=<?php echo $product_id; ?>" class="btn-modern w-100">
                                            <i class="fas fa-eye me-2"></i> View Details
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-modern w-100" disabled>
                                            <i class="fas fa-clock me-2"></i> Coming Soon
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Featured Collection -->
    <section class="section-modern" style="background: #f8f9fa;">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">FEATURED COLLECTION</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Handpicked selection of our premium products</p>
            
            <div class="row">
                <?php if(empty($featured_products)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            No featured products available at the moment.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($featured_products as $index => $product): 
                        $product_name = isset($product['name']) ? $product['name'] : (isset($product['product_name']) ? $product['product_name'] : 'Unnamed Product');
                        $product_description = isset($product['description']) ? $product['description'] : 'No description available';
                        $product_price = isset($product['price']) ? $product['price'] : 0.00;
                        $product_image = isset($product['image_url']) ? $product['image_url'] : 'images/default_product.jpg';
                        $product_id = isset($product['product_id']) ? $product['product_id'] : 0;
                        $category_name = isset($product['category_name']) ? $product['category_name'] : 'Uncategorized';
                        $stock_status = isset($product['stock_status']) ? $product['stock_status'] : 'In Stock';
                        $stock = isset($product['stock']) ? $product['stock'] : 0;
                    ?>
                        <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo ($index * 100) + 150; ?>">
                            <div class="product-card">
                                <div class="product-image-container">
                                    <?php if(isset($product_image) && file_exists($product_image)): ?>
                                        <img src="<?php echo $product_image; ?>" 
                                             alt="<?php echo htmlspecialchars($product_name); ?>" 
                                             class="product-image">
                                    <?php else: ?>
                                        <div class="image-fallback">
                                            <i class="fas fa-star"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info">
                                    <h5 class="product-title"><?php echo htmlspecialchars($product_name); ?></h5>
                                    <p class="product-price">$<?php echo number_format($product_price, 2); ?></p>
                                    <p class="product-description"><?php echo htmlspecialchars(substr($product_description, 0, 80)); ?>...</p>
                                    
                                    <div class="product-meta">
                                        <span class="product-category"><?php echo htmlspecialchars($category_name); ?></span>
                                        <span class="badge <?php echo $stock > 10 ? 'bg-success' : ($stock > 0 ? 'bg-warning' : 'bg-danger'); ?>">
                                            <?php echo htmlspecialchars($stock_status); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if($product_id > 0): ?>
                                        <a href="products.php?product_id=<?php echo $product_id; ?>" class="btn-modern w-100">
                                            <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-modern w-100" disabled>
                                            <i class="fas fa-ban me-2"></i> Unavailable
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="section-modern">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">SHOP BY CATEGORY</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Browse our curated collections</p>
            
            <div class="row">
                <?php if(empty($categories)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Categories loading...
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($categories as $index => $category): 
                        $category_name = isset($category['name']) ? $category['name'] : (isset($category['category_name']) ? $category['category_name'] : 'Uncategorized');
                        $category_id = isset($category['category_id']) ? $category['category_id'] : 0;
                        $product_count = isset($category['product_count']) ? $category['product_count'] : 0;
                    ?>
                        <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo ($index * 100) + 150; ?>">
                            <div class="category-card">
                                <div class="category-overlay">
                                    <h4 class="category-name"><?php echo htmlspecialchars($category_name); ?></h4>
                                    <p class="category-count"><?php echo $product_count; ?> Products</p>
                                    <?php if($category_id > 0): ?>
                                        <a href="products.php?category=<?php echo $category_id; ?>" class="btn-modern btn-sm mt-2">
                                            Explore Collection
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-modern btn-sm mt-2" disabled>
                                            Coming Soon
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Newsletter -->
    <section class="newsletter-section">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-6" data-aos="fade-up">
                    <h3 class="newsletter-title">STAY UPDATED</h3>
                    <p class="mb-4 opacity-75">Subscribe to our newsletter for exclusive offers, new arrivals, and fashion tips.</p>
                    <form class="newsletter-form d-flex" method="POST" action="subscribe.php">
                        <input type="email" class="form-control newsletter-input" name="email" placeholder="Enter your email address" required>
                        <button type="submit" class="btn-modern" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                            <i class="fas fa-paper-plane me-2"></i> Subscribe
                        </button>
                    </form>
                    <p class="mt-3 small opacity-75">By subscribing, you agree to our Privacy Policy</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Modern Footer -->
    <footer class="modern-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4" data-aos="fade-up">
                    <h4 class="footer-title">VELVET VOGUE</h4>
                    <p class="opacity-75 mb-3">Premium fashion destination offering exquisite clothing and accessories for the modern individual.</p>
                    <div class="social-modern">
                        <a href="#" class="social-icon-modern"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon-modern"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon-modern"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon-modern"><i class="fab fa-pinterest"></i></a>
                        <a href="#" class="social-icon-modern"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <h4 class="footer-title">SHOP</h4>
                    <ul class="footer-links">
                        <?php if(!empty($categories)): ?>
                            <?php foreach(array_slice($categories, 0, 5) as $category): 
                                $cat_name = isset($category['name']) ? $category['name'] : (isset($category['category_name']) ? $category['category_name'] : 'Category');
                                $cat_id = isset($category['category_id']) ? $category['category_id'] : 0;
                            ?>
                                <?php if($cat_id > 0): ?>
                                    <li><a href="products.php?category=<?php echo $cat_id; ?>"><?php echo htmlspecialchars($cat_name); ?></a></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <li><a href="products.php">All Products</a></li>
                        <li><a href="#new-arrivals">New Arrivals</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <h4 class="footer-title">HELP</h4>
                    <ul class="footer-links">
                        <li><a href="faq.php">FAQs</a></li>
                        <li><a href="shipping.php">Shipping</a></li>
                        <li><a href="returns.php">Returns</a></li>
                        <li><a href="size-guide.php">Size Guide</a></li>
                        <li><a href="contact.php">Contact Us</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <h4 class="footer-title">COMPANY</h4>
                    <ul class="footer-links">
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="careers.php">Careers</a></li>
                        <li><a href="blog.php">Blog</a></li>
                        <li><a href="press.php">Press</a></li>
                        <li><a href="sustainability.php">Sustainability</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 mb-4" data-aos="fade-up" data-aos-delay="400">
                    <h4 class="footer-title">LEGAL</h4>
                    <ul class="footer-links">
                        <li><a href="terms.php">Terms of Service</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="cookies.php">Cookie Policy</a></li>
                        <li><a href="accessibility.php">Accessibility</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom" data-aos="fade-up">
                <p>&copy; <?php echo date('Y'); ?> Velvet Vogue. All rights reserved. | Premium Fashion E-commerce</p>
                <p class="mt-2 small opacity-50">
                    <i class="fas fa-credit-card me-1"></i> We accept: 
                    <i class="fab fa-cc-visa mx-1"></i>
                    <i class="fab fa-cc-mastercard mx-1"></i>
                    <i class="fab fa-cc-amex mx-1"></i>
                    <i class="fab fa-cc-paypal mx-1"></i>
                </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos@2.3.4/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.modern-navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Image error handler
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.product-image');
            images.forEach(img => {
                img.addEventListener('error', function() {
                    this.src = 'images/default_product.jpg';
                    this.style.objectFit = 'cover';
                });
            });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
<?php 
if (isset($conn) && $conn) {
    $conn->close();
}