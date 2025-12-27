<?php
// Start session at the very top
session_start();

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$product_id = intval($_GET['id']);

// Database connection
include 'includes/db_connect.php';

// Initialize variables
$product = null;
$related_products = [];
$message = "";

// Fetch product details
$sql = "SELECT p.*, c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id_fk = c.category_id
        WHERE p.product_id = ?";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    
    // Get product images separately
    $image_sql = "SELECT image_url FROM product_images WHERE product_id_fk = ?";
    $image_stmt = $conn->prepare($image_sql);
    $image_stmt->bind_param("i", $product_id);
    $image_stmt->execute();
    $image_result = $image_stmt->get_result();
    
    $product['image_array'] = [];
    while ($row = $image_result->fetch_assoc()) {
        $product['image_array'][] = $row['image_url'];
    }
    $image_stmt->close();
    
    // If no images, use placeholder
    if (empty($product['image_array'])) {
        $product['image_array'] = ['https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80'];
    }
    
    // Fetch related products (same category)
    $related_sql = "SELECT p.*, 
                   (SELECT image_url FROM product_images WHERE product_id_fk = p.product_id LIMIT 1) as image_url
                   FROM products p 
                   WHERE p.category_id_fk = ? AND p.product_id != ?
                   LIMIT 4";
    
    $related_stmt = $conn->prepare($related_sql);
    $related_stmt->bind_param("ii", $product['category_id_fk'], $product_id);
    $related_stmt->execute();
    $related_result = $related_stmt->get_result();
    
    $related_products = [];
    while ($row = $related_result->fetch_assoc()) {
        if (!$row['image_url']) {
            $row['image_url'] = 'https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80';
        }
        $related_products[] = $row;
    }
    $related_stmt->close();
} else {
    $message = "Product not found.";
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($product['name']) ? htmlspecialchars($product['name']) . ' - Velvet Vogue' : 'Product Not Found'; ?></title>
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

        /* Breadcrumb */
        .breadcrumb-nav {
            margin-bottom: 40px;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
        }

        .breadcrumb-item a {
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb-item a:hover {
            color: var(--highlight);
        }

        .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 500;
        }

        /* Product Detail Container */
        .product-detail-container {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 80px;
        }

        /* Image Gallery */
        .product-gallery {
            padding: 40px;
            background: #f8f9fa;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .main-image-container {
            width: 100%;
            height: 500px;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
            position: relative;
            background: white;
        }

        .main-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: var(--transition);
        }

        .thumbnail-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            border: 3px solid transparent;
            transition: var(--transition);
        }

        .thumbnail:hover {
            transform: translateY(-2px);
            border-color: var(--highlight);
        }

        .thumbnail.active {
            border-color: var(--highlight);
            transform: scale(1.05);
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Product Info */
        .product-info {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .product-header {
            margin-bottom: 30px;
        }

        .product-category {
            display: inline-block;
            background: var(--highlight);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .product-title {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .product-price-section {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .product-price {
            font-size: 2.5rem;
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
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .stock-in .stock-dot {
            background: #27ae60;
            animation: pulse 2s infinite;
        }

        .stock-low .stock-dot {
            background: #f39c12;
        }

        .stock-out .stock-dot {
            background: #e74c3c;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .stock-text {
            font-size: 1rem;
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

        .product-description {
            margin-bottom: 40px;
        }

        .description-title {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .description-content {
            color: var(--dark);
            line-height: 1.8;
            font-size: 1.1rem;
        }

        /* Product Actions */
        .product-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px 20px;
        }

        .quantity-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.5rem;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: var(--transition);
        }

        .quantity-btn:hover {
            background: var(--highlight);
            color: white;
        }

        .quantity-input {
            width: 50px;
            text-align: center;
            border: none;
            background: transparent;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
        }

        .btn-action {
            flex: 1;
            padding: 18px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-cart {
            background: linear-gradient(135deg, var(--highlight), #ff6b8b);
            color: white;
            box-shadow: 0 4px 20px rgba(233, 69, 96, 0.3);
        }

        .btn-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(233, 69, 96, 0.4);
        }

        .btn-wishlist {
            background: white;
            color: var(--primary);
            border: 2px solid #e0e0e0;
        }

        .btn-wishlist:hover {
            border-color: var(--highlight);
            color: var(--highlight);
            transform: translateY(-2px);
        }

        /* Product Details */
        .product-details {
            margin-bottom: 40px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .detail-value {
            font-weight: 500;
            color: var(--primary);
            font-size: 1.1rem;
        }

        /* Related Products */
        .section-title {
            font-size: 2.5rem;
            color: var(--primary);
            text-align: center;
            margin-bottom: 50px;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, var(--highlight), #ff6b8b);
            border-radius: 2px;
        }

        .related-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 80px;
        }

        .related-product-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .related-product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .related-product-info {
            padding: 20px;
        }

        .related-product-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .related-product-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--highlight);
            margin-bottom: 15px;
        }

        /* Modern Footer */
        .modern-footer {
            background: var(--primary);
            color: white;
            padding: 60px 0 30px;
            margin-top: 80px;
        }

        .footer-content {
            text-align: center;
        }

        .footer-logo {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--highlight), #ff6b8b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }

        .footer-text {
            opacity: 0.8;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
            margin-top: 40px;
        }

        .copyright {
            opacity: 0.6;
            font-size: 0.95rem;
        }

        /* No Product State */
        .no-product-container {
            text-align: center;
            padding: 100px 40px;
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-md);
            margin-bottom: 80px;
        }

        .no-product-icon {
            font-size: 6rem;
            color: var(--highlight);
            margin-bottom: 30px;
            opacity: 0.7;
        }

        .no-product-title {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 2.5rem;
        }

        .no-product-text {
            color: var(--gray);
            font-size: 1.2rem;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .product-detail-container {
                flex-direction: column;
            }
            
            .product-gallery {
                height: auto;
            }
            
            .main-image-container {
                height: 400px;
            }
            
            .product-title {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 768px) {
            .page-container {
                padding-top: 80px;
                padding-bottom: 80px;
            }
            
            .product-gallery,
            .product-info {
                padding: 30px;
            }
            
            .main-image-container {
                height: 350px;
            }
            
            .product-title {
                font-size: 2rem;
            }
            
            .product-price {
                font-size: 2rem;
            }
            
            .product-actions {
                flex-direction: column;
            }
            
            .quantity-selector {
                justify-content: center;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .product-title {
                font-size: 1.8rem;
            }
            
            .main-image-container {
                height: 300px;
            }
            
            .thumbnail {
                width: 60px;
                height: 60px;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .related-products-grid {
                grid-template-columns: 1fr;
            }
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
            background: #ff6b8b;
        }
    </style>
</head>
<body>
    <!-- Main Container -->
    <div class="container page-container">
        
        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb-nav">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home me-1"></i>Home</a></li>
                <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                <?php if ($product): ?>
                    <li class="breadcrumb-item"><a href="products.php?category=<?php echo $product['category_id_fk']; ?>">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
                <?php endif; ?>
            </ol>
        </nav>

        <?php if ($product): ?>
            <!-- Product Detail Container -->
            <div class="product-detail-container">
                <div class="row g-0">
                    <!-- Product Images -->
                    <div class="col-lg-6">
                        <div class="product-gallery">
                            <div class="main-image-container">
                                <img id="mainImage" src="<?php echo htmlspecialchars($product['image_array'][0]); ?>" 
                                    class="main-image" 
                                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                                    onerror="this.src='https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80'">
                            </div>
                            <div class="thumbnail-container">
                                <?php foreach ($product['image_array'] as $index => $image): ?>
                                    <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                         onclick="changeImage('<?php echo htmlspecialchars($image); ?>', this)">
                                        <img src="<?php echo htmlspecialchars($image); ?>" 
                                            alt="<?php echo htmlspecialchars($product['name']); ?> - Image <?php echo $index + 1; ?>"
                                            onerror="this.src='https://via.placeholder.com/80x80?text=Image'">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Product Information -->
                    <div class="col-lg-6">
                        <div class="product-info">
                            <div class="product-header">
                                <span class="product-category">
                                    <i class="fas fa-tag me-1"></i>
                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                </span>
                                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                                
                                <div class="product-price-section">
                                    <div class="product-price">
                                        $<?php echo number_format($product['price'], 2); ?>
                                        <span>USD</span>
                                    </div>
                                    
                                    <div class="stock-indicator <?php echo $product['stock'] > 10 ? 'stock-in' : ($product['stock'] > 0 ? 'stock-low' : 'stock-out'); ?>">
                                        <span class="stock-dot"></span>
                                        <span class="stock-text">
                                            <?php echo $product['stock'] > 10 ? 'In Stock' : ($product['stock'] > 0 ? 'Low Stock' : 'Out of Stock'); ?>
                                            (<?php echo $product['stock']; ?> available)
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="product-description">
                                <h3 class="description-title">Description</h3>
                                <div class="description-content">
                                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                                </div>
                            </div>

                            <!-- Product Actions -->
                            <div class="product-actions">
                                <div class="quantity-selector">
                                    <button class="quantity-btn" onclick="updateQuantity(-1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" id="quantity" class="quantity-input" value="1" min="1" 
                                           max="<?php echo $product['stock']; ?>" readonly>
                                    <button class="quantity-btn" onclick="updateQuantity(1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                
                                <button class="btn-action btn-cart" onclick="addToCart(<?php echo $product['product_id']; ?>)">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span>Add to Cart</span>
                                </button>
                                
                                <button class="btn-action btn-wishlist" onclick="addToWishlist(<?php echo $product['product_id']; ?>)">
                                    <i class="fas fa-heart"></i>
                                    <span>Wishlist</span>
                                </button>
                            </div>

                            <!-- Product Details -->
                            <div class="product-details">
                                <h3 class="description-title mb-4">Product Details</h3>
                                <div class="details-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Product ID</span>
                                        <span class="detail-value">#<?php echo str_pad($product['product_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Category</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Price</span>
                                        <span class="detail-value">$<?php echo number_format($product['price'], 2); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Availability</span>
                                        <span class="detail-value"><?php echo $product['stock']; ?> units</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Products -->
            <?php if (!empty($related_products)): ?>
                <div class="related-products-section">
                    <h2 class="section-title">You May Also Like</h2>
                    <div class="related-products-grid">
                        <?php foreach ($related_products as $related): ?>
                            <div class="related-product-card">
                                <a href="product_detail.php?id=<?php echo $related['product_id']; ?>">
                                    <img src="<?php echo htmlspecialchars($related['image_url']); ?>" 
                                         class="related-product-image"
                                         alt="<?php echo htmlspecialchars($related['name']); ?>"
                                         onerror="this.src='https://images.unsplash.com/photo-1544441893-675973e31985?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'">
                                </a>
                                <div class="related-product-info">
                                    <h4 class="related-product-title">
                                        <a href="product_detail.php?id=<?php echo $related['product_id']; ?>" 
                                           style="text-decoration: none; color: inherit;">
                                            <?php echo htmlspecialchars($related['name']); ?>
                                        </a>
                                    </h4>
                                    <div class="related-product-price">
                                        $<?php echo number_format($related['price'], 2); ?>
                                    </div>
                                    <button class="btn-action btn-cart" style="padding: 12px 20px; font-size: 0.9rem;" 
                                            onclick="addToCart(<?php echo $related['product_id']; ?>)">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span>Add to Cart</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Product Not Found -->
            <div class="no-product-container">
                <i class="fas fa-exclamation-circle no-product-icon"></i>
                <h2 class="no-product-title">Product Not Found</h2>
                <p class="no-product-text"><?php echo $message ?: 'The product you are looking for does not exist or has been removed.'; ?></p>
                <a href="products.php" class="btn-action btn-cart" style="display: inline-flex; max-width: 200px; margin: 0 auto;">
                    <i class="fas fa-arrow-left me-2"></i>
                    <span>Back to Products</span>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modern Footer -->
    <footer class="modern-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">VELVET VOGUE</div>
                <p class="footer-text">Premium fashion destination offering exquisite clothing and accessories for the modern individual</p>
                <div class="footer-bottom">
                    <p class="copyright">&copy; <?php echo date('Y'); ?> Velvet Vogue. All rights reserved. | Premium Fashion E-commerce</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Change main image function
        function changeImage(src, element) {
            document.getElementById('mainImage').src = src;
            
            // Remove active class from all thumbnails
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            
            // Add active class to clicked thumbnail
            element.classList.add('active');
        }

        // Update quantity function
        function updateQuantity(change) {
            const quantityInput = document.getElementById('quantity');
            let currentQuantity = parseInt(quantityInput.value);
            const maxQuantity = parseInt(quantityInput.max);
            const minQuantity = parseInt(quantityInput.min);
            
            currentQuantity += change;
            
            if (currentQuantity < minQuantity) currentQuantity = minQuantity;
            if (currentQuantity > maxQuantity) currentQuantity = maxQuantity;
            
            quantityInput.value = currentQuantity;
        }

        // Add to cart function
        function addToCart(productId) {
            const quantity = document.getElementById('quantity')?.value || 1;
            
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    product_id: productId, 
                    quantity: parseInt(quantity) 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Product added to cart!', 'success');
                } else {
                    if (data.message === 'login_required') {
                        showNotification('Please login to add items to cart', 'warning');
                        setTimeout(() => {
                            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname);
                        }, 1500);
                    } else {
                        showNotification(data.message || 'Error adding to cart', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error adding to cart', 'error');
            });
        }

        // Add to wishlist function
        function addToWishlist(productId) {
            showNotification('Added to wishlist!', 'success');
            // Implement wishlist functionality here
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div style="position: fixed; top: 20px; right: 20px; background: ${type === 'success' ? '#27ae60' : type === 'warning' ? '#f39c12' : '#e74c3c'}; color: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); z-index: 9999; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'times-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Add CSS for notifications
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
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
    </script>
</body>
</html>