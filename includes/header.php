<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and set user variables
$logged_in_user = null;
$user_id = null;
$username = null;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'User';
    $logged_in_user = [
        'id' => $user_id,
        'username' => $username,
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

// Check if admin is logged in
$is_admin = isset($_SESSION['Admin_id']);
$admin_id = $_SESSION['Admin_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velvet Vogue - Luxury Fashion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gold: #D4AF37;
            --deep-navy: #0A0F29;
            --soft-ivory: #F5F5F0;
            --charcoal: #2C2C2C;
            --rose-gold: #B76E79;
            --platinum: #E5E4E2;
        }

        .luxury-navbar {
            background: rgba(10, 15, 41, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.2rem 0;
            transition: all 0.4s ease;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        }

        .brand-logo {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--gold), var(--rose-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
            text-decoration: none;
        }

        .nav-luxury-link {
            color: var(--platinum) !important;
            font-weight: 500;
            margin: 0 1rem;
            position: relative;
            padding: 0.5rem 0;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }

        .nav-luxury-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--gold), var(--rose-gold));
            transition: width 0.3s ease;
        }

        .nav-luxury-link:hover {
            color: var(--gold) !important;
        }

        .nav-luxury-link:hover::before {
            width: 100%;
        }

        .luxury-btn {
            background: linear-gradient(135deg, var(--gold), var(--rose-gold));
            border: none;
            color: var(--deep-navy);
            padding: 0.8rem 2rem;
            border-radius: 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .luxury-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
            color: var(--deep-navy);
        }
    </style>
</head>
<body>
    <!-- Luxury Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark luxury-navbar fixed-top">
        <div class="container">
            <a class="navbar-brand brand-logo" href="../home.php">VELVET VOGUE</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#luxuryNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="luxuryNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link nav-luxury-link active" href="../home.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-luxury-link" href="../products.php">Collection</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-luxury-link" href="../categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-luxury-link" href="../about.php">Heritage</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-luxury-link" href="../contact.php">Contact</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if ($logged_in_user): ?>
                        <!-- User is logged in -->
                        <a href="../profile.php" class="nav-link nav-luxury-link me-3">
                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($logged_in_user['username']); ?>
                        </a>
                        <a href="../cart.php" class="luxury-btn position-relative">
                            <i class="fas fa-shopping-bag me-2"></i> Cart
                            <span class="position-absolute top-0 start-100 translate-middle badge bg-danger rounded-0">
                                0
                            </span>
                        </a>
                        <a href="../logout.php" class="nav-link nav-luxury-link ms-3">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    <?php elseif ($is_admin): ?>
                        <!-- Admin is logged in -->
                        <a href="../admin/admin_dashboard.php" class="nav-link nav-luxury-link me-3">
                            <i class="fas fa-crown me-1"></i> Admin Panel
                        </a>
                        <a href="../admin/logout.php" class="nav-link nav-luxury-link">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    <?php else: ?>
                        <!-- No one is logged in -->
                        <a href="../login.php" class="nav-link nav-luxury-link me-3">
                            <i class="fas fa-sign-in-alt me-1"></i> Sign In
                        </a>
                        <a href="../register.php" class="luxury-btn">
                            <i class="fas fa-user-plus me-2"></i> Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>