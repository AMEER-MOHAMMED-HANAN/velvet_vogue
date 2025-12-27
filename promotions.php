<?php
// Start session (optional, only if you want to track something)
session_start();
require_once 'includes/db_connect.php';

// Initialize variables - SIMPLE AND CLEAN
$is_admin = false; // Default to false since no login
$show_admin_controls = false; // Separate variable for UI control

// If you want to enable admin mode temporarily for testing, uncomment this:
// $show_admin_controls = true;

// Get promotions data
$current_date = date('Y-m-d');
$promotions_sql = "SELECT DISTINCT * FROM promotions ORDER BY start_date DESC";
$promotions_result = $conn->query($promotions_sql);

// Check if we have promotions
$has_promotions = ($promotions_result && $promotions_result->num_rows > 0);

// Get products on promotion
$items_promo_check_sql = "SELECT COUNT(*) as count FROM items_promotion";
$check_result = $conn->query($items_promo_check_sql);
$has_promo_products = false;
$promo_products_result = null;

if ($check_result) {
    $check_row = $check_result->fetch_assoc();
    if ($check_row['count'] > 0) {
        $has_promo_products = true;
        $products_sql = "SELECT DISTINCT p.*, pr.name as promotion_name 
                        FROM products p 
                        INNER JOIN items_promotion ip ON p.product_id = ip.product_id_fk 
                        INNER JOIN promotions pr ON ip.promotion_id_fk = pr.promotion_id
                        WHERE CURDATE() BETWEEN pr.start_date AND pr.end_date
                        ORDER BY p.name";
        $promo_products_result = $conn->query($products_sql);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions - Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Colors from the image ONLY */
            --deep-navy: #0A1931;
            --off-white: #F7F7F2;
            --warm-taupe: #B8A99A;
            --soft-gold: #D4AF37;
            --charcoal-gray: #4A4A4A;
            --burgundy: #800020;
            --olive-green: #6B8E23;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--off-white);
            color: var(--deep-navy);
            min-height: 100vh;
            padding-top: 0;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Cinzel', serif;
            font-weight: 600;
            letter-spacing: 1px;
        }

        /* Hero Section */
        .hero-section {
            height: 60vh;
            background: linear-gradient(rgba(247, 247, 242, 0.95), rgba(247, 247, 242, 0.98)), 
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><pattern id="pattern" x="0" y="0" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M0,40 L40,0" stroke="%230A1931" stroke-width="0.5" opacity="0.1"/><path d="M0,0 L40,40" stroke="%230A1931" stroke-width="0.5" opacity="0.1"/></pattern><rect width="100" height="100" fill="url(%23pattern)"/></svg>');
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid var(--warm-taupe);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
            padding: 3rem;
        }

        .hero-title {
            font-size: 5rem;
            font-weight: 800;
            color: var(--deep-navy);
            margin-bottom: 1.5rem;
            letter-spacing: 4px;
            line-height: 1.1;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: var(--charcoal-gray);
            margin-bottom: 2.5rem;
            font-weight: 400;
            letter-spacing: 1.5px;
            line-height: 1.8;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .gold-divider {
            width: 150px;
            height: 3px;
            background: linear-gradient(90deg, var(--soft-gold), var(--burgundy));
            margin: 2.5rem auto;
            border-radius: 2px;
        }

        /* Promotions Section */
        .promotions-section {
            padding: 6rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 5rem;
        }

        .section-title {
            font-size: 3rem;
            color: var(--deep-navy);
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
            letter-spacing: 2px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, var(--soft-gold), var(--burgundy));
        }

        .section-subtitle {
            color: var(--charcoal-gray);
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
            font-weight: 400;
        }

        /* Admin Controls */
        .admin-controls {
            background: var(--off-white);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 3rem;
            border: 2px solid var(--warm-taupe);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(10, 25, 49, 0.05);
        }

        .admin-info {
            font-size: 1rem;
            color: var(--deep-navy);
            font-weight: 500;
        }

        .btn-add-promotion {
            background: transparent;
            border: 2px solid var(--deep-navy);
            color: var(--deep-navy);
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            border-radius: 5px;
            position: relative;
            overflow: hidden;
        }

        .btn-add-promotion::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(10, 25, 49, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .btn-add-promotion:hover {
            background: var(--deep-navy);
            color: var(--off-white);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(10, 25, 49, 0.15);
        }

        .btn-add-promotion:hover::before {
            left: 100%;
        }

        /* Promotions Grid */
        .promotions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 3rem;
            margin-bottom: 5rem;
        }

        .promotion-card {
            background: var(--off-white);
            border: 2px solid var(--warm-taupe);
            border-radius: 0;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(10, 25, 49, 0.05);
        }

        .promotion-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--burgundy), transparent);
            transform: scaleX(0);
            transition: transform 0.6s ease;
        }

        .promotion-card:hover {
            transform: translateY(-15px);
            border-color: var(--burgundy);
            box-shadow: 0 25px 50px rgba(128, 0, 32, 0.1);
        }

        .promotion-card:hover::before {
            transform: scaleX(1);
        }

        .promotion-header {
            padding: 2rem;
            background: var(--off-white);
            border-bottom: 2px solid var(--warm-taupe);
            position: relative;
            overflow: hidden;
        }

        .promotion-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--soft-gold), var(--olive-green), var(--burgundy));
        }

        .promotion-name {
            font-size: 1.8rem;
            color: var(--deep-navy);
            margin-bottom: 1rem;
            font-weight: 700;
            letter-spacing: 1px;
            position: relative;
            z-index: 2;
        }

        .promotion-type {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            background: var(--deep-navy);
            color: var(--off-white);
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            z-index: 2;
        }

        .promotion-content {
            padding: 2.5rem;
            position: relative;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .promotion-description {
            color: var(--charcoal-gray);
            font-size: 1.1rem;
            line-height: 1.7;
            margin-bottom: 2rem;
            flex-grow: 1;
        }

        .promotion-dates {
            background: var(--off-white);
            padding: 1.5rem;
            border-radius: 5px;
            margin-bottom: 2rem;
            border: 1px solid var(--warm-taupe);
        }

        .date-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .date-label {
            color: var(--charcoal-gray);
            font-size: 1rem;
            font-weight: 500;
        }

        .date-value {
            color: var(--deep-navy);
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.5px;
        }

        .promotion-status {
            padding: 1rem;
            text-align: center;
            font-weight: 700;
            border-radius: 5px;
            margin-top: 1rem;
            font-size: 1.1rem;
            letter-spacing: 1px;
        }

        .status-active {
            background: rgba(107, 142, 35, 0.1);
            color: var(--olive-green);
            border: 1px solid var(--olive-green);
        }

        .status-expired {
            background: rgba(128, 0, 32, 0.1);
            color: var(--burgundy);
            border: 1px solid var(--burgundy);
        }

        .status-upcoming {
            background: rgba(212, 175, 55, 0.1);
            color: var(--soft-gold);
            border: 1px solid var(--soft-gold);
        }

        /* Admin Actions */
        .promotion-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--warm-taupe);
        }

        .btn-action {
            flex: 1;
            padding: 1rem;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
        }

        .btn-edit {
            background: transparent;
            border: 2px solid var(--deep-navy);
            color: var(--deep-navy);
        }

        .btn-edit:hover {
            background: var(--deep-navy);
            color: var(--off-white);
            transform: translateY(-2px);
        }

        .btn-delete {
            background: transparent;
            border: 2px solid var(--burgundy);
            color: var(--burgundy);
        }

        .btn-delete:hover {
            background: var(--burgundy);
            color: var(--off-white);
            transform: translateY(-2px);
        }

        /* Products Section */
        .products-section {
            margin-top: 8rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 6rem 3rem;
            grid-column: 1 / -1;
            border: 2px dashed var(--warm-taupe);
            background: var(--off-white);
            border-radius: 2px;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--deep-navy);
            margin-bottom: 2rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            color: var(--deep-navy);
            margin-bottom: 1.5rem;
            font-size: 2.2rem;
            font-weight: 700;
        }

        .empty-state p {
            color: var(--charcoal-gray);
            margin-bottom: 3rem;
            font-size: 1.1rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Footer */
        .luxury-footer {
            background: var(--deep-navy);
            padding: 4rem 0 3rem;
            border-top: 2px solid var(--warm-taupe);
            margin-top: 5rem;
            position: relative;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            text-align: center;
        }

        .footer-logo {
            font-family: 'Cinzel', serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--off-white);
            margin-bottom: 1.5rem;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .footer-text {
            color: var(--warm-taupe);
            font-size: 1rem;
            margin-bottom: 0;
            letter-spacing: 1px;
        }

        /* Floating Background Elements */
        .floating-element {
            position: fixed;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(184, 169, 154, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
            animation: float 20s infinite ease-in-out;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(100px, 50px) rotate(120deg); }
            66% { transform: translate(-50px, 100px) rotate(240deg); }
        }

        .floating-element:nth-child(1) {
            top: 10%;
            left: 5%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            bottom: 20%;
            right: 10%;
            animation-delay: 5s;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .promotions-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 2rem;
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 3.5rem;
                letter-spacing: 3px;
            }

            .hero-subtitle {
                font-size: 1.1rem;
                padding: 0 1rem;
            }

            .promotions-grid {
                grid-template-columns: 1fr;
                padding: 0 1rem;
                gap: 2rem;
            }

            .promotions-section {
                padding: 4rem 1rem;
            }

            .section-title {
                font-size: 2.5rem;
            }

            .promotion-card {
                max-width: 500px;
                margin: 0 auto;
            }

            .admin-controls {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 2.8rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .promotion-name {
                font-size: 1.6rem;
            }

            .btn-add-promotion {
                padding: 0.8rem 1.5rem;
                font-size: 0.8rem;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .promotion-card {
            animation: fadeInUp 0.8s ease forwards;
            animation-fill-mode: both;
            opacity: 0;
        }

        .promotion-card:nth-child(1) { animation-delay: 0.1s; }
        .promotion-card:nth-child(2) { animation-delay: 0.2s; }
        .promotion-card:nth-child(3) { animation-delay: 0.3s; }
        .promotion-card:nth-child(4) { animation-delay: 0.4s; }
        .promotion-card:nth-child(5) { animation-delay: 0.5s; }
        .promotion-card:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <!-- Floating Background Elements -->
    <div class="floating-element"></div>
    <div class="floating-element"></div>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">SPECIAL PROMOTIONS</h1>
            <div class="gold-divider"></div>
            <p class="hero-subtitle">Discover exclusive offers, limited-time discounts, and special deals on our luxury collections. Don't miss out on these amazing opportunities!</p>
        </div>
    </section>

    <!-- Promotions Section -->
    <section class="promotions-section">
        <div class="section-header">
            <h2 class="section-title">CURRENT PROMOTIONS</h2>
            <p class="section-subtitle">Browse through our exclusive promotions and limited-time offers on luxury collections</p>
        </div>

        <?php if ($show_admin_controls): ?>
        <div class="admin-controls">
            <div class="admin-info">
                <i class="fas fa-user-shield" style="color: var(--deep-navy);"></i> Admin Mode: You can manage promotions
            </div>
            <a href="add_promotion.php" class="btn-add-promotion">
                <i class="fas fa-plus"></i> Add New Promotion
            </a>
        </div>
        <?php endif; ?>

        <div class="promotions-grid">
            <?php if ($has_promotions): 
                while ($promotion = $promotions_result->fetch_assoc()):
                    // Determine promotion status
                    $start_date = $promotion['start_date'];
                    $end_date = $promotion['end_date'];
                    
                    if ($current_date < $start_date) {
                        $status = 'upcoming';
                        $status_text = 'Upcoming';
                    } elseif ($current_date > $end_date) {
                        $status = 'expired';
                        $status_text = 'Expired';
                    } else {
                        $status = 'active';
                        $status_text = 'Active';
                    }
                    
                    // Format dates for display
                    $start_formatted = date('M d, Y', strtotime($start_date));
                    $end_formatted = date('M d, Y', strtotime($end_date));
            ?>
                <div class="promotion-card">
                    <div class="promotion-header">
                        <h3 class="promotion-name"><?php echo htmlspecialchars($promotion['name']); ?></h3>
                        <span class="promotion-type"><?php echo htmlspecialchars(ucfirst($promotion['discount_type'])); ?> Discount</span>
                    </div>
                    
                    <div class="promotion-content">
                        <p class="promotion-description">
                            <?php echo htmlspecialchars($promotion['description']); ?>
                        </p>
                        
                        <div class="promotion-dates">
                            <div class="date-item">
                                <span class="date-label">Start Date:</span>
                                <span class="date-value"><?php echo $start_formatted; ?></span>
                            </div>
                            <div class="date-item">
                                <span class="date-label">End Date:</span>
                                <span class="date-value"><?php echo $end_formatted; ?></span>
                            </div>
                        </div>
                        
                        <div class="promotion-status status-<?php echo $status; ?>">
                            <i class="fas fa-<?php echo $status == 'active' ? 'check-circle' : ($status == 'upcoming' ? 'clock' : 'times-circle'); ?>"></i>
                            <?php echo $status_text; ?>
                        </div>
                        
                        <?php if ($show_admin_controls): ?>
                        <div class="promotion-actions">
                            <a href="edit_promotion.php?id=<?php echo $promotion['promotion_id']; ?>" class="btn-action btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="delete_promotion.php?id=<?php echo $promotion['promotion_id']; ?>" 
                               class="btn-action btn-delete"
                               onclick="return confirm('Are you sure you want to delete this promotion?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php 
                endwhile;
            else: 
            ?>
                <div class="empty-state">
                    <i class="fas fa-tags"></i>
                    <h3>No Promotions Available</h3>
                    <p>There are currently no active promotions. Check back soon for special offers!</p>
                    <?php if ($show_admin_controls): ?>
                    <a href="add_promotion.php" class="btn-add-promotion">
                        <i class="fas fa-plus"></i> Create Your First Promotion
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($has_promo_products && $promo_products_result && $promo_products_result->num_rows > 0): ?>
        <div class="products-section">
            <div class="section-header">
                <h2 class="section-title">PRODUCTS ON SALE</h2>
                <p class="section-subtitle">Explore products currently available with special promotions</p>
            </div>
            
            <div class="promotions-grid">
                <?php while ($product = $promo_products_result->fetch_assoc()): ?>
                    <div class="promotion-card">
                        <div class="promotion-header">
                            <h3 class="promotion-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <span class="promotion-type">On Promotion</span>
                        </div>
                        
                        <div class="promotion-content">
                            <p class="promotion-description">
                                <?php echo !empty($product['description']) ? htmlspecialchars(substr($product['description'], 0, 150)) . '...' : 'No description available'; ?>
                            </p>
                            
                            <div class="promotion-dates">
                                <div class="date-item">
                                    <span class="date-label">Price:</span>
                                    <span class="date-value">$<?php echo number_format($product['price'], 2); ?></span>
                                </div>
                                <div class="date-item">
                                    <span class="date-label">Stock:</span>
                                    <span class="date-value"><?php echo $product['stock']; ?> items</span>
                                </div>
                                <?php if (!empty($product['promotion_name'])): ?>
                                <div class="date-item">
                                    <span class="date-label">Promotion:</span>
                                    <span class="date-value"><?php echo htmlspecialchars($product['promotion_name']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <a href="product-detail.php?id=<?php echo $product['product_id']; ?>" class="btn-action btn-edit" style="margin-top: 1rem; display: block; text-align: center;">
                                <i class="fas fa-shopping-bag"></i> View Product Details
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="luxury-footer">
        <div class="footer-content">
            <div class="footer-logo">VELVET VOGUE</div>
            <p class="footer-text">&copy; 2024 Velvet Vogue. Crafting timeless luxury since 2010.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add animations to promotion cards
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.style.transition = 'all 0.6s ease';
                }
            });
        }, {
            threshold: 0.1
        });

        document.querySelectorAll('.promotion-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            observer.observe(card);
        });

        // Initialize all cards on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.promotion-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
            });
            
            // Trigger animation for cards already in view
            setTimeout(() => {
                document.querySelectorAll('.promotion-card').forEach(card => {
                    const rect = card.getBoundingClientRect();
                    if (rect.top < window.innerHeight - 100) {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }
                });
            }, 100);
        });
    </script>
</body>
</html>

<?php 
$conn->close();
?>