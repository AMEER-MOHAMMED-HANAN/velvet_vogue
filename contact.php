<?php
include 'includes/db_connect.php';
session_start();

// Initialize variables
$message = "";
$success_message = "";

// Check if contact_messages table exists, create it if it doesn't
$check_table_sql = "SHOW TABLES LIKE 'contact_messages'";
$table_result = $conn->query($check_table_sql);

if ($table_result && $table_result->num_rows == 0) {
    // Create the table
    $create_table_sql = "CREATE TABLE contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('unread', 'read', 'replied') DEFAULT 'unread'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($create_table_sql)) {
        // Table created successfully
        error_log("Contact messages table created successfully");
    } else {
        error_log("Error creating contact_messages table: " . $conn->error);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message_text = trim($_POST['message']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message_text)) {
        $message = "<div class='alert alert-danger'>Please fill in all required fields.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert alert-danger'>Please enter a valid email address.</div>";
    } else {
        // Insert contact message into database
        $sql = "INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ssss", $name, $email, $subject, $message_text);
            
            if ($stmt->execute()) {
                $success_message = "<div class='alert alert-success'>Thank you for your message! We'll get back to you within 24 hours.</div>";
                // Clear form fields
                $_POST = array();
            } else {
                $message = "<div class='alert alert-danger'>Sorry, there was an error sending your message. Please try again.</div>";
                // Log the error for debugging
                error_log("Database insert error: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $message = "<div class='alert alert-danger'>Database connection error. Please try again later.</div>";
            // Log the error for debugging
            error_log("Prepare statement error: " . $conn->error);
        }
    }
}

// Contact information
$contact_info = [
    [
        'icon' => 'bi-geo-alt',
        'title' => 'Visit Our Store',
        'details' => ['123 Fashion Street', 'Style District', 'Manila, 1000'],
        'link' => '#'
    ],
    [
        'icon' => 'bi-telephone',
        'title' => 'Call Us',
        'details' => ['+63 2 1234 5678', 'Mon-Fri: 9AM-6PM'],
        'link' => 'tel:+63212345678'
    ],
    [
        'icon' => 'bi-envelope',
        'title' => 'Email Us',
        'details' => ['hello@velvetvogue.com', 'support@velvetvogue.com'],
        'link' => 'mailto:hello@velvetvogue.com'
    ],
    [
        'icon' => 'bi-clock',
        'title' => 'Store Hours',
        'details' => ['Monday - Saturday: 10AM-8PM', 'Sunday: 11AM-6PM'],
        'link' => '#'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Velvet Vogue</title>
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
            margin: 0;
            padding: 0;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Cinzel', serif;
            font-weight: 600;
            letter-spacing: 1px;
            margin: 0;
        }

        /* Hero Section */
        .contact-hero {
            background: linear-gradient(rgba(10, 25, 49, 0.85), rgba(10, 25, 49, 0.9)), 
                        url('https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80') no-repeat center center/cover;
            padding: 8rem 0 6rem;
            color: var(--off-white);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .contact-hero::after {
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

        .contact-title {
            font-size: 4.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, var(--soft-gold), var(--warm-taupe));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .contact-subtitle {
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

        /* Contact Section */
        .contact-section {
            padding: 6rem 2rem;
            background: var(--off-white);
        }

        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
        }

        /* Contact Info */
        .contact-info {
            background: var(--off-white);
            border-radius: 15px;
            padding: 3rem;
            border: 2px solid var(--warm-taupe);
            box-shadow: 0 20px 60px rgba(10, 25, 49, 0.1);
        }

        .info-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--deep-navy);
            margin-bottom: 1.5rem;
        }

        .info-subtitle {
            font-size: 1.1rem;
            color: var(--charcoal-gray);
            margin-bottom: 3rem;
            line-height: 1.6;
        }

        .contact-methods {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .contact-method {
            display: flex;
            gap: 1.5rem;
            padding: 1.5rem;
            border-radius: 10px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--warm-taupe);
            background: var(--off-white);
        }

        .contact-method:hover {
            transform: translateX(10px);
            border-color: var(--burgundy);
            box-shadow: 0 10px 30px rgba(128, 0, 32, 0.1);
        }

        .method-icon {
            width: 60px;
            height: 60px;
            background: var(--light-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--soft-gold);
            border: 2px solid rgba(212, 175, 55, 0.3);
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .contact-method:hover .method-icon {
            background: var(--soft-gold);
            color: var(--deep-navy);
            transform: scale(1.1);
        }

        .method-content h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--deep-navy);
            margin-bottom: 0.5rem;
        }

        .method-details {
            color: var(--charcoal-gray);
            line-height: 1.5;
        }

        .method-details p {
            margin: 0.3rem 0;
        }

        .method-link {
            color: var(--burgundy);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .method-link:hover {
            color: var(--deep-navy);
            text-decoration: underline;
        }

        /* Contact Form */
        .contact-form-container {
            background: var(--off-white);
            border-radius: 15px;
            padding: 3rem;
            border: 2px solid var(--warm-taupe);
            box-shadow: 0 20px 60px rgba(10, 25, 49, 0.1);
        }

        .form-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--deep-navy);
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            font-size: 1.1rem;
            color: var(--charcoal-gray);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .contact-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--deep-navy);
            font-size: 0.95rem;
        }

        .form-label .required {
            color: var(--burgundy);
        }

        .form-control {
            border: 2px solid var(--warm-taupe);
            border-radius: 8px;
            padding: 1rem 1.2rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--off-white);
            font-family: inherit;
            color: var(--deep-navy);
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: var(--burgundy);
            box-shadow: 0 0 0 3px rgba(128, 0, 32, 0.1);
            transform: translateY(-2px);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--warm-taupe);
            opacity: 0.7;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 150px;
        }

        .btn-submit {
            background: var(--burgundy);
            border: 2px solid var(--burgundy);
            border-radius: 8px;
            padding: 1.2rem 2.5rem;
            color: var(--off-white);
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.4s ease;
            cursor: pointer;
            margin-top: 1rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            width: 100%;
            box-sizing: border-box;
        }

        .btn-submit:hover {
            background: transparent;
            color: var(--burgundy);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(128, 0, 32, 0.3);
        }

        /* Map Section */
        .map-section {
            padding: 6rem 2rem;
            background: var(--deep-navy);
            color: var(--off-white);
        }

        .map-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .map-title {
            font-size: 2.8rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 3rem;
            color: var(--off-white);
        }

        .map-wrapper {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            height: 400px;
            border: 2px solid var(--warm-taupe);
        }

        .map-placeholder {
            background: linear-gradient(135deg, var(--deep-navy) 0%, rgba(10, 25, 49, 0.9) 100%);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--off-white);
            font-size: 1.2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .map-placeholder::before {
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

        /* FAQ Section */
        .faq-section {
            padding: 6rem 2rem;
            background: var(--off-white);
            position: relative;
        }

        .faq-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--warm-taupe), transparent);
        }

        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-title {
            font-size: 2.8rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 3rem;
            color: var(--deep-navy);
        }

        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .faq-item {
            background: var(--off-white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(10, 25, 49, 0.1);
            border: 1px solid var(--warm-taupe);
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            border-color: var(--burgundy);
            box-shadow: 0 15px 40px rgba(128, 0, 32, 0.1);
        }

        .faq-question {
            padding: 1.5rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--deep-navy);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s ease;
        }

        .faq-question:hover {
            background: var(--light-taupe);
        }

        .faq-question::after {
            content: '+';
            font-size: 1.8rem;
            font-weight: 300;
            color: var(--soft-gold);
            transition: all 0.3s ease;
        }

        .faq-item.active .faq-question::after {
            content: '−';
            transform: rotate(0deg);
            color: var(--burgundy);
        }

        .faq-answer {
            padding: 0 2rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s ease;
            color: var(--charcoal-gray);
            line-height: 1.6;
            background: var(--light-taupe);
        }

        .faq-item.active .faq-answer {
            padding: 0 2rem 1.5rem;
            max-height: 500px;
        }

        /* Alert Styles */
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 5px;
        }

        .alert-success {
            background: var(--light-olive);
            color: var(--olive-green);
            border-color: var(--olive-green);
        }

        .alert-success::before {
            background: var(--olive-green);
        }

        .alert-danger {
            background: var(--light-burgundy);
            color: var(--burgundy);
            border-color: var(--burgundy);
        }

        .alert-danger::before {
            background: var(--burgundy);
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
            .contact-container {
                gap: 3rem;
            }
        }

        @media (max-width: 768px) {
            .contact-title {
                font-size: 3rem;
            }
            
            .contact-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .contact-info, .contact-form-container {
                padding: 2rem;
            }
            
            .contact-method {
                padding: 1rem;
            }
            
            .method-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
            
            .faq-question {
                padding: 1rem 1.5rem;
            }
            
            .faq-answer {
                padding: 0 1.5rem;
            }
            
            .faq-item.active .faq-answer {
                padding: 0 1.5rem 1rem;
            }
            
            .map-title, .faq-title {
                font-size: 2.2rem;
            }
            
            .info-title, .form-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .contact-title {
                font-size: 2.5rem;
            }
            
            .contact-subtitle {
                font-size: 1.1rem;
            }
            
            .contact-section, .map-section, .faq-section {
                padding: 4rem 1rem;
            }
            
            .btn-submit {
                padding: 1rem 2rem;
                font-size: 1rem;
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

        .contact-method, .contact-form-container, .faq-item, .map-wrapper {
            animation: fadeInUp 0.8s ease forwards;
            opacity: 0;
        }

        .contact-method:nth-child(1) { animation-delay: 0.1s; }
        .contact-method:nth-child(2) { animation-delay: 0.2s; }
        .contact-method:nth-child(3) { animation-delay: 0.3s; }
        .contact-method:nth-child(4) { animation-delay: 0.4s; }
        
        .faq-item:nth-child(1) { animation-delay: 0.1s; }
        .faq-item:nth-child(2) { animation-delay: 0.2s; }
        .faq-item:nth-child(3) { animation-delay: 0.3s; }
        .faq-item:nth-child(4) { animation-delay: 0.4s; }
        .faq-item:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body class="contact-page">
    <!-- Floating Background Elements -->
    <div class="floating-element"></div>
    <div class="floating-element"></div>

    <!-- Hero Section -->
    <section class="contact-hero">
        <div class="hero-content">
            <h1 class="contact-title">Get In Touch</h1>
            <div class="gold-divider"></div>
            <p class="contact-subtitle">
                We'd love to hear from you. Whether you have a question about our products, need styling advice, 
                or just want to say hello, we're here to help.
            </p>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="contact-container">
            <!-- Contact Information -->
            <div class="contact-info">
                <h2 class="info-title">Let's Connect</h2>
                <p class="info-subtitle">
                    Reach out to us through any of these channels. Our team is always ready to assist you 
                    with your fashion needs and provide exceptional customer service.
                </p>
                
                <div class="contact-methods">
                    <?php foreach ($contact_info as $method): ?>
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="bi <?php echo $method['icon']; ?>"></i>
                            </div>
                            <div class="method-content">
                                <h3><?php echo $method['title']; ?></h3>
                                <div class="method-details">
                                    <?php foreach ($method['details'] as $detail): ?>
                                        <p>
                                            <?php if ($method['link'] !== '#'): ?>
                                                <a href="<?php echo $method['link']; ?>" class="method-link">
                                                    <?php echo $detail; ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo $detail; ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="contact-form-container">
                <h2 class="form-title">Send Us a Message</h2>
                <p class="form-subtitle">Fill out the form below and we'll get back to you as soon as possible.</p>
                
                <?php 
                if (!empty($message)) echo $message;
                if (!empty($success_message)) echo $success_message;
                ?>
                
                <form class="contact-form" method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                               required placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required placeholder="Enter your email address">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Subject <span class="required">*</span></label>
                        <input type="text" name="subject" class="form-control" 
                               value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" 
                               required placeholder="What is this regarding?">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Message <span class="required">*</span></label>
                        <textarea name="message" class="form-control" required 
                                  placeholder="Tell us how we can help you..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-send me-2"></i>Send Message
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="map-section">
        <div class="map-container">
            <h2 class="map-title">Visit Our Store</h2>
            <div class="map-wrapper">
                <div class="map-placeholder">
                    <div style="position: relative; z-index: 2;">
                        <i class="bi bi-map display-1 mb-3" style="color: var(--soft-gold);"></i>
                        <h3>Velvet Vogue Flagship Store</h3>
                        <p>123 Fashion Street, Style District, Manila, 1000</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="faq-container">
            <h2 class="faq-title">Frequently Asked Questions</h2>
            
            <div class="faq-list">
                <div class="faq-item">
                    <div class="faq-question">What are your store hours?</div>
                    <div class="faq-answer">
                        Our flagship store is open Monday through Saturday from 10:00 AM to 8:00 PM, 
                        and on Sundays from 11:00 AM to 6:00 PM. Holiday hours may vary.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">Do you offer international shipping?</div>
                    <div class="faq-answer">
                        Yes! We offer international shipping to most countries. Shipping costs and delivery 
                        times vary depending on your location. You can view shipping options at checkout.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">What is your return policy?</div>
                    <div class="faq-answer">
                        We offer a 30-day return policy for all unworn items with original tags attached. 
                        Sale items may have different return conditions. Please see our Returns & Exchanges 
                        page for complete details.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">How can I track my order?</div>
                    <div class="faq-answer">
                        Once your order ships, you'll receive a confirmation email with your tracking number 
                        and a link to track your package. You can also track your order by logging into your 
                        account on our website.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">Do you offer styling services?</div>
                    <div class="faq-answer">
                        Absolutely! We offer complimentary personal styling services both in-store and virtually. 
                        Contact us to schedule an appointment with one of our expert stylists.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // FAQ Accordion functionality
        document.addEventListener('DOMContentLoaded', function() {
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                
                question.addEventListener('click', () => {
                    // Close all other items
                    faqItems.forEach(otherItem => {
                        if (otherItem !== item && otherItem.classList.contains('active')) {
                            otherItem.classList.remove('active');
                        }
                    });
                    
                    // Toggle current item
                    item.classList.toggle('active');
                });
            });

            // Form validation enhancement
            const contactForm = document.querySelector('.contact-form');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    const requiredFields = this.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.style.borderColor = 'var(--burgundy)';
                        } else {
                            field.style.borderColor = 'var(--warm-taupe)';
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            }

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
            document.querySelectorAll('.contact-method, .contact-form-container, .faq-item, .map-wrapper').forEach(element => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(40px)';
                observer.observe(element);
            });

            // Initialize animations on load
            setTimeout(() => {
                document.querySelectorAll('.contact-method, .contact-form-container, .faq-item, .map-wrapper').forEach(element => {
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