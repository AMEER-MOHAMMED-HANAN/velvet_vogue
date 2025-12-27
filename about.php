<?php
include 'includes/db_connect.php';
session_start();

// Initialize variables
$team_members = [];
$company_stats = [];

// If no team members in database, use sample data
if (empty($team_members)) {
    $team_members = [
        [
            'name' => 'Sarah Johnson',
            'position' => 'Founder & CEO',
            'bio' => 'With over 10 years in fashion industry, Sarah founded Velvet Vogue to bring luxury fashion to everyone.',
            'image_url' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Michael Chen',
            'position' => 'Creative Director',
            'bio' => 'Michael brings innovative design concepts and ensures every collection tells a unique story.',
            'image_url' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Emily Rodriguez',
            'position' => 'Head of Operations',
            'bio' => 'Emily ensures seamless operations and exceptional customer experience across all touchpoints.',
            'image_url' => 'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'David Kim',
            'position' => 'Fashion Curator',
            'bio' => 'David scouts the latest trends and ensures our collections stay ahead of the fashion curve.',
            'image_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
        ]
    ];
}

// Company statistics
$company_stats = [
    ['number' => '50,000+', 'label' => 'Happy Customers'],
    ['number' => '5+', 'label' => 'Years of Excellence'],
    ['number' => '100+', 'label' => 'Brand Partners'],
    ['number' => '24/7', 'label' => 'Customer Support']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Velvet Vogue</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--off-white);
            color: var(--deep-navy);
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Cinzel', serif;
            font-weight: 600;
            letter-spacing: 1px;
        }

        /* Hero Section */
        .about-hero {
            background: linear-gradient(rgba(10, 25, 49, 0.85), rgba(10, 25, 49, 0.9)), 
                        url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80') no-repeat center center/cover;
            padding: 8rem 0 6rem;
            color: var(--off-white);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .about-hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(to top, var(--off-white), transparent);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .about-title {
            font-size: 4.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, var(--soft-gold), var(--warm-taupe));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .about-subtitle {
            font-size: 1.4rem;
            margin-bottom: 2rem;
            color: var(--warm-taupe);
            line-height: 1.6;
            font-weight: 300;
            letter-spacing: 1px;
        }

        .gold-divider {
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, var(--soft-gold), var(--burgundy));
            margin: 3rem auto;
            border-radius: 2px;
        }

        /* Mission Section */
        .mission-section {
            padding: 6rem 2rem;
            background: var(--off-white);
            position: relative;
        }

        .mission-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .mission-text h2 {
            font-size: 3rem;
            font-weight: 700;
            color: var(--deep-navy);
            margin-bottom: 1.5rem;
        }

        .mission-text h2 span {
            background: linear-gradient(45deg, var(--deep-navy), var(--burgundy));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .mission-text p {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--charcoal-gray);
            margin-bottom: 1.5rem;
        }

        .mission-image {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(10, 25, 49, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--warm-taupe);
        }

        .mission-image:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 30px 80px rgba(10, 25, 49, 0.15);
        }

        .mission-image img {
            width: 100%;
            height: 500px;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .mission-image:hover img {
            transform: scale(1.05);
        }

        /* Values Section */
        .values-section {
            padding: 6rem 2rem;
            background: var(--deep-navy);
            color: var(--off-white);
            position: relative;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--off-white);
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: var(--warm-taupe);
            max-width: 600px;
            margin: 0 auto;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .value-card {
            background: rgba(247, 247, 242, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2.5rem;
            text-align: center;
            border: 1px solid rgba(184, 169, 154, 0.2);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .value-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .value-card:hover {
            transform: translateY(-10px);
            border-color: var(--soft-gold);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .value-card:hover::before {
            left: 100%;
        }

        .value-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: var(--light-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--soft-gold);
            border: 2px solid rgba(212, 175, 55, 0.3);
        }

        .value-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--off-white);
        }

        .value-description {
            color: var(--warm-taupe);
            line-height: 1.6;
        }

        /* Stats Section */
        .stats-section {
            padding: 6rem 2rem;
            background: var(--off-white);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .stat-card {
            text-align: center;
            padding: 3rem 2rem;
            border-radius: 15px;
            background: var(--off-white);
            transition: all 0.4s ease;
            border: 2px solid var(--warm-taupe);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--soft-gold), var(--burgundy), var(--olive-green));
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(10, 25, 49, 0.1);
            border-color: var(--deep-navy);
        }

        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            background: linear-gradient(45deg, var(--deep-navy), var(--burgundy));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            font-family: 'Cinzel', serif;
        }

        .stat-label {
            font-size: 1.2rem;
            color: var(--charcoal-gray);
            font-weight: 600;
        }

        /* Team Section */
        .team-section {
            padding: 6rem 2rem;
            background: var(--deep-navy);
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 3rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .team-card {
            background: var(--off-white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .team-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--soft-gold), var(--burgundy));
            z-index: 2;
        }

        .team-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
        }

        .team-image-container {
            position: relative;
            overflow: hidden;
            height: 300px;
        }

        .team-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .team-card:hover .team-image {
            transform: scale(1.1);
        }

        .team-content {
            padding: 2rem;
        }

        .team-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--deep-navy);
            margin-bottom: 0.5rem;
            font-family: 'Cinzel', serif;
        }

        .team-position {
            color: var(--burgundy);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .team-bio {
            color: var(--charcoal-gray);
            line-height: 1.6;
            font-size: 0.95rem;
        }

        /* Story Section */
        .story-section {
            padding: 6rem 2rem;
            background: var(--off-white);
            position: relative;
        }

        .story-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--warm-taupe), transparent);
        }

        .story-content {
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
        }

        .story-text {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--charcoal-gray);
            margin-bottom: 2rem;
            text-align: left;
        }

        .story-highlight {
            background: linear-gradient(45deg, var(--burgundy), var(--soft-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        /* CTA Section */
        .cta-section {
            padding: 6rem 2rem;
            background: linear-gradient(135deg, var(--deep-navy) 0%, rgba(10, 25, 49, 0.9) 100%);
            color: var(--off-white);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.05) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .cta-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            margin: 0 auto;
        }

        .cta-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--off-white);
        }

        .cta-subtitle {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: var(--warm-taupe);
        }

        .cta-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary, .btn-outline {
            padding: 1rem 2.5rem;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.4s ease;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: var(--burgundy);
            color: var(--off-white);
            border: 2px solid var(--burgundy);
        }

        .btn-primary:hover {
            background: transparent;
            color: var(--burgundy);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(128, 0, 32, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--off-white);
            border: 2px solid var(--soft-gold);
        }

        .btn-outline:hover {
            background: var(--soft-gold);
            color: var(--deep-navy);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3);
        }

        /* Floating Elements */
        .floating-element {
            position: fixed;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(184, 169, 154, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
            animation: float 15s infinite ease-in-out;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(50px, 30px) rotate(120deg); }
            66% { transform: translate(-30px, 50px) rotate(240deg); }
        }

        .floating-element:nth-child(1) {
            top: 10%;
            left: 5%;
        }

        .floating-element:nth-child(2) {
            bottom: 20%;
            right: 10%;
            animation-delay: 2s;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .mission-content {
                gap: 3rem;
            }
            
            .values-grid, .stats-grid, .team-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .about-title {
                font-size: 3rem;
            }
            
            .mission-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .mission-text h2 {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 2.5rem;
            }
            
            .values-grid, .stats-grid, .team-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .cta-title {
                font-size: 2.5rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-primary, .btn-outline {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .about-title {
                font-size: 2.5rem;
            }
            
            .about-subtitle {
                font-size: 1.1rem;
            }
            
            .mission-section, .values-section, .stats-section, .team-section, .story-section, .cta-section {
                padding: 4rem 1rem;
            }
            
            .stat-number {
                font-size: 2.8rem;
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

        .mission-content, .value-card, .stat-card, .team-card {
            animation: fadeInUp 0.8s ease forwards;
            opacity: 0;
        }

        .value-card:nth-child(1) { animation-delay: 0.1s; }
        .value-card:nth-child(2) { animation-delay: 0.2s; }
        .value-card:nth-child(3) { animation-delay: 0.3s; }
        .value-card:nth-child(4) { animation-delay: 0.4s; }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        
        .team-card:nth-child(1) { animation-delay: 0.1s; }
        .team-card:nth-child(2) { animation-delay: 0.2s; }
        .team-card:nth-child(3) { animation-delay: 0.3s; }
        .team-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body class="about-page">
    <!-- Floating Background Elements -->
    <div class="floating-element"></div>
    <div class="floating-element"></div>

    <!-- Hero Section -->
    <section class="about-hero">
        <div class="hero-content">
            <h1 class="about-title">Our Story</h1>
            <div class="gold-divider"></div>
            <p class="about-subtitle">
                Discover the passion, dedication, and vision that drives Velvet Vogue to deliver exceptional fashion experiences.
            </p>
        </div>
    </section>

    <!-- Mission Section -->
    <section class="mission-section">
        <div class="mission-content">
            <div class="mission-text">
                <h2>Our <span>Mission</span></h2>
                <p>
                    At Velvet Vogue, we believe that fashion is more than just clothing—it's a form of self-expression, 
                    a confidence booster, and a way to tell your unique story to the world. Founded in 2018, we've been 
                    on a journey to make luxury fashion accessible to everyone without compromising on quality or style.
                </p>
                <p>
                    Our mission is to empower individuals to express their authentic selves through carefully curated 
                    collections that blend timeless elegance with contemporary trends. We're committed to sustainable 
                    practices, ethical sourcing, and creating pieces that you'll love for years to come.
                </p>
            </div>
            <div class="mission-image">
                <img src="https://images.unsplash.com/photo-1560493676-04071c5f467b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Our Mission">
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section class="values-section">
        <div class="section-header">
            <h2 class="section-title">Our Values</h2>
            <p class="section-subtitle">The principles that guide everything we do</p>
        </div>
        <div class="values-grid">
            <div class="value-card">
                <div class="value-icon">
                    <i class="bi bi-gem"></i>
                </div>
                <h3 class="value-title">Quality First</h3>
                <p class="value-description">
                    We never compromise on quality. Every piece is carefully crafted with premium materials 
                    and attention to detail that exceeds expectations.
                </p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="bi bi-heart"></i>
                </div>
                <h3 class="value-title">Customer Love</h3>
                <p class="value-description">
                    Our customers are at the heart of everything we do. We're committed to providing 
                    exceptional service and creating memorable shopping experiences.
                </p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="bi bi-tree"></i>
                </div>
                <h3 class="value-title">Sustainability</h3>
                <p class="value-description">
                    We're dedicated to sustainable fashion practices, from eco-friendly materials to 
                    ethical manufacturing processes that respect our planet.
                </p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="bi bi-lightbulb"></i>
                </div>
                <h3 class="value-title">Innovation</h3>
                <p class="value-description">
                    We continuously push boundaries in fashion design and technology to bring you 
                    innovative styles and seamless shopping experiences.
                </p>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="section-header">
            <h2 class="section-title" style="color: var(--deep-navy);">By The Numbers</h2>
            <p class="section-subtitle" style="color: var(--charcoal-gray);">Our journey in numbers</p>
        </div>
        <div class="stats-grid">
            <?php foreach ($company_stats as $stat): ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stat['number']; ?></div>
                    <div class="stat-label"><?php echo $stat['label']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team-section">
        <div class="section-header">
            <h2 class="section-title">Meet Our Team</h2>
            <p class="section-subtitle">The passionate people behind Velvet Vogue</p>
        </div>
        <div class="team-grid">
            <?php foreach ($team_members as $member): ?>
                <div class="team-card">
                    <div class="team-image-container">
                        <img src="<?php echo $member['image_url']; ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" class="team-image">
                    </div>
                    <div class="team-content">
                        <h3 class="team-name"><?php echo htmlspecialchars($member['name']); ?></h3>
                        <p class="team-position"><?php echo htmlspecialchars($member['position']); ?></p>
                        <p class="team-bio"><?php echo htmlspecialchars($member['bio']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Story Section -->
    <section class="story-section">
        <div class="story-content">
            <h2 class="section-title" style="color: var(--deep-navy); margin-bottom: 3rem;">Our Journey</h2>
            <div class="story-text">
                <p>
                    Velvet Vogue began as a small boutique in 2018, founded by Sarah Johnson with a simple vision: 
                    to make luxury fashion accessible to everyone. What started as a single store has now grown into 
                    a beloved brand with thousands of satisfied customers worldwide.
                </p>
                <p>
                    Throughout our journey, we've remained committed to our core values while adapting to the evolving 
                    fashion landscape. We've embraced e-commerce, expanded our product lines, and built relationships 
                    with ethical manufacturers who share our commitment to quality and sustainability.
                </p>
                <p>
                    Today, we're proud to be more than just a fashion brand—we're a community of style enthusiasts 
                    who believe that everyone deserves to feel confident and beautiful in what they wear. 
                    <span class="story-highlight">Our story is still being written, and we're excited to have you with us on this journey.</span>
                </p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-content">
            <h2 class="cta-title">Join Our Fashion Journey</h2>
            <p class="cta-subtitle">
                Be part of our story and discover fashion that makes you feel confident and beautiful.
            </p>
            <div class="cta-buttons">
                <a href="products.php" class="btn-primary">
                    <i class="bi bi-bag me-2"></i>Shop Collection
                </a>
                <a href="contact.php" class="btn-outline">
                    <i class="bi bi-envelope me-2"></i>Get In Touch
                </a>
            </div>
        </div>
    </section>

    <script>
        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.style.transition = 'all 0.8s ease';
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.querySelectorAll('.mission-content, .value-card, .stat-card, .team-card').forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(40px)';
            observer.observe(element);
        });

        // Initialize animations on load
        document.addEventListener('DOMContentLoaded', function() {
            // Trigger initial animation for elements already in view
            setTimeout(() => {
                document.querySelectorAll('.mission-content, .value-card, .stat-card, .team-card').forEach(element => {
                    const rect = element.getBoundingClientRect();
                    if (rect.top < window.innerHeight - 100) {
                        element.style.opacity = '1';
                        element.style.transform = 'translateY(0)';
                        element.style.transition = 'all 0.8s ease';
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