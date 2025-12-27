<?php
// C:\xampp\htdocs\velvet_vogue\Categories.php - USER VIEW

// Use the correct path to db_connect.php
$db_path = __DIR__ . '/includes/db_connect.php';

if (file_exists($db_path)) {
    include $db_path;
} else {
    // If file doesn't exist, create a basic connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "velvet_vogue";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
}

session_start();

// Fetch all categories with product counts
$categories_sql = "SELECT c.*, COUNT(p.product_id) as product_count 
                   FROM categories c 
                   LEFT JOIN products p ON c.category_id = p.category_id_fk 
                   GROUP BY c.category_id 
                   ORDER BY c.name";
$categories_result = $conn->query($categories_sql);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections - Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Colors from the image */
            --deep-navy: #0A1931;
            --off-white: #F7F7F2;
            --warm-taupe: #B8A99A;
            --soft-gold: #D4AF37;
            --charcoal-gray: #4A4A4A;
            --burgundy: #800020;
            --olive-green: #6B8E23;
            
            /* Additional shades */
            --light-taupe: rgba(184, 169, 154, 0.1);
            --light-gold: rgba(212, 175, 55, 0.1);
            --light-burgundy: rgba(128, 0, 32, 0.1);
            --light-olive: rgba(107, 142, 35, 0.1);
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
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Cinzel', serif;
            font-weight: 600;
            letter-spacing: 1.5px;
        }

        /* Hero Section */
        .hero-section {
            height: 60vh;
            background: linear-gradient(rgba(10, 25, 49, 0.85), rgba(10, 25, 49, 0.9)), 
                        url('https://images.unsplash.com/photo-1445205170230-053b83016050?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
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
            font-size: 4.5rem;
            font-weight: 800;
            color: var(--off-white);
            margin-bottom: 1.5rem;
            letter-spacing: 4px;
            line-height: 1.1;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: var(--warm-taupe);
            margin-bottom: 2.5rem;
            font-weight: 300;
            letter-spacing: 1.5px;
            line-height: 1.8;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .gold-divider {
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, var(--soft-gold), var(--burgundy));
            margin: 2.5rem auto;
            border-radius: 2px;
        }

        /* Collections Section */
        .collections-section {
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

        /* Collections Grid */
        .collections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 3rem;
            margin-bottom: 5rem;
        }

        .collection-card {
            background: var(--off-white);
            border: 2px solid var(--warm-taupe);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(10, 25, 49, 0.05);
        }

        .collection-card:hover {
            transform: translateY(-15px);
            border-color: var(--burgundy);
            box-shadow: 0 25px 50px rgba(128, 0, 32, 0.1);
        }

        /* Icon Header */
        .collection-icon-header {
            background: linear-gradient(135deg, var(--light-gold), var(--light-taupe));
            padding: 3rem 2rem;
            text-align: center;
            border-bottom: 2px solid var(--warm-taupe);
            position: relative;
            overflow: hidden;
        }

        .collection-icon-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--soft-gold), var(--olive-green), var(--burgundy));
        }

        .collection-icon {
            font-size: 4.5rem;
            color: var(--deep-navy);
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
            transition: all 0.5s ease;
        }

        .collection-card:hover .collection-icon {
            transform: scale(1.2) rotate(5deg);
            color: var(--burgundy);
        }

        .collection-content {
            padding: 2.5rem;
            position: relative;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .collection-title {
            font-size: 1.8rem;
            color: var(--deep-navy);
            margin-bottom: 1.2rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .collection-description {
            color: var(--charcoal-gray);
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 2rem;
            flex-grow: 1;
        }

        .collection-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--warm-taupe);
            margin-top: auto;
        }

        .collection-count {
            color: var(--burgundy);
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            letter-spacing: 1px;
        }

        .collection-count i {
            font-size: 1.2rem;
            color: var(--soft-gold);
        }

        .btn-explore {
            background: transparent;
            border: 2px solid var(--deep-navy);
            color: var(--deep-navy);
            padding: 1rem 2rem;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            transition: all 0.4s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            position: relative;
            overflow: hidden;
            border-radius: 5px;
        }

        .btn-explore:hover {
            background: var(--deep-navy);
            color: var(--off-white);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(10, 25, 49, 0.15);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 6rem 3rem;
            grid-column: 1 / -1;
            border: 2px dashed var(--warm-taupe);
            background: var(--off-white);
            border-radius: 15px;
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

        .luxury-footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--soft-gold), transparent);
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

        /* Scroll Indicator */
        .scroll-indicator {
            position: fixed;
            bottom: 3rem;
            right: 3rem;
            color: var(--deep-navy);
            font-size: 1.8rem;
            animation: bounce 2s infinite;
            cursor: pointer;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s ease;
            background: var(--off-white);
            border: 2px solid var(--warm-taupe);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            box-shadow: 0 5px 15px rgba(10, 25, 49, 0.1);
        }

        .scroll-indicator:hover {
            background: var(--deep-navy);
            color: var(--off-white);
            border-color: var(--deep-navy);
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .collections-grid {
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

            .collections-grid {
                grid-template-columns: 1fr;
                padding: 0 1rem;
                gap: 2rem;
            }

            .collections-section {
                padding: 4rem 1rem;
            }

            .section-title {
                font-size: 2.5rem;
            }

            .collection-card {
                max-width: 500px;
                margin: 0 auto;
            }

            .scroll-indicator {
                bottom: 2rem;
                right: 2rem;
                width: 45px;
                height: 45px;
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 2.8rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .collection-title {
                font-size: 1.6rem;
            }

            .collection-icon {
                font-size: 3.5rem;
            }

            .btn-explore {
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

        .collection-card {
            animation: fadeInUp 0.8s ease forwards;
            animation-fill-mode: both;
            opacity: 0;
        }

        .collection-card:nth-child(1) { animation-delay: 0.1s; }
        .collection-card:nth-child(2) { animation-delay: 0.2s; }
        .collection-card:nth-child(3) { animation-delay: 0.3s; }
        .collection-card:nth-child(4) { animation-delay: 0.4s; }
        .collection-card:nth-child(5) { animation-delay: 0.5s; }
        .collection-card:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <!-- Floating Background Elements -->
    <div class="floating-element"></div>
    <div class="floating-element"></div>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">EXQUISITE COLLECTIONS</h1>
            <div class="gold-divider"></div>
            <p class="hero-subtitle">Discover our curated luxury collections, where timeless elegance meets contemporary design. Each piece tells a story of craftsmanship and sophistication.</p>
        </div>
    </section>

    <!-- Collections Section -->
    <section class="collections-section">
        <div class="section-header">
            <h2 class="section-title">OUR LUXURY COLLECTIONS</h2>
            <p class="section-subtitle">Browse through our exclusive categories, each meticulously curated to offer the finest in luxury fashion and accessories.</p>
        </div>

        <div class="collections-grid">
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <i class="fas fa-gem"></i>
                    <h3>Collections Coming Soon</h3>
                    <p>We are currently curating our luxury collections. Stay tuned for exquisite additions.</p>
                    <a href="products.php" class="btn-explore">
                        <i class="fas fa-shopping-bag"></i> Shop All Products
                    </a>
                </div>
            <?php else: ?>
                <?php 
                function getCategoryIconClass($categoryName) {
                    $lowerName = strtolower($categoryName);
                    $icons = [
                        'clothing' => 'fa-tshirt',
                        'fashion' => 'fa-tshirt',
                        'electronics' => 'fa-laptop',
                        'shoes' => 'fa-shoe-prints',
                        'footwear' => 'fa-shoe-prints',
                        'accessories' => 'fa-gem',
                        'jewelry' => 'fa-gem',
                        'beauty' => 'fa-spa',
                        'cosmetics' => 'fa-spa',
                        'home' => 'fa-home',
                        'decor' => 'fa-home',
                        'sports' => 'fa-dumbbell',
                        'fitness' => 'fa-dumbbell',
                        'books' => 'fa-book',
                        'watches' => 'fa-clock',
                        'bags' => 'fa-briefcase',
                        'handbags' => 'fa-briefcase'
                    ];
                    
                    foreach ($icons as $key => $icon) {
                        if (strpos($lowerName, $key) !== false) {
                            return $icon;
                        }
                    }
                    return 'fa-gem'; // Default icon
                }
                ?>
                
                <?php foreach ($categories as $category): ?>
                    <div class="collection-card">
                        <div class="collection-icon-header">
                            <i class="fas <?php echo getCategoryIconClass($category['name']); ?> collection-icon"></i>
                        </div>
                        
                        <div class="collection-content">
                            <h3 class="collection-title"><?php echo htmlspecialchars($category['name']); ?></h3>
                            
                            <p class="collection-description">
                                Explore our exclusive <?php echo htmlspecialchars($category['name']); ?> collection featuring premium craftsmanship and sophisticated designs curated for the discerning individual. Discover timeless pieces that embody luxury and elegance.
                            </p>
                            
                            <div class="collection-meta">
                                <span class="collection-count">
                                    <i class="fas fa-gem"></i>
                                    <?php echo $category['product_count']; ?> Luxury Items
                                </span>
                                <a href="products.php?category=<?php echo $category['category_id']; ?>" class="btn-explore">
                                    View <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Luxury Footer -->
    <footer class="luxury-footer">
        <div class="footer-content">
            <div class="footer-logo">VELVET VOGUE</div>
            <p class="footer-text">Crafting timeless luxury since 2010. All rights reserved.</p>
        </div>
    </footer>

    <!-- Scroll to Top -->
    <div class="scroll-indicator" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        <i class="fas fa-chevron-up"></i>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Intersection Observer for animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        // Observe collection cards
        document.querySelectorAll('.collection-card').forEach(card => {
            observer.observe(card);
        });

        // Add hover effects
        const collectionCards = document.querySelectorAll('.collection-card');
        collectionCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '1';
            });
        });

        // Show/hide scroll indicator
        window.addEventListener('scroll', function() {
            const scrollIndicator = document.querySelector('.scroll-indicator');
            if (window.scrollY > 300) {
                scrollIndicator.style.opacity = '1';
            } else {
                scrollIndicator.style.opacity = '0';
            }
        });

        // Initialize scroll indicator
        document.addEventListener('DOMContentLoaded', function() {
            const scrollIndicator = document.querySelector('.scroll-indicator');
            scrollIndicator.style.opacity = '0';
            
            // Add smooth scrolling to all links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
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
        });

        // Add category icon animations
        document.querySelectorAll('.collection-icon').forEach(icon => {
            icon.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.3) rotate(10deg)';
            });
            
            icon.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) rotate(0deg)';
            });
        });
    </script>
</body>
</html>

<?php 
// Close connection
$conn->close();
?>