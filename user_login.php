<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velvet Vogue - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #8B4513; /* Saddle brown - elegant and warm */
            --primary-light: #A0522D; /* Sienna */
            --primary-dark: #654321; /* Dark brown */
            --accent-color: #DAA520; /* Goldenrod */
            --text-dark: #2C1810; /* Deep brown */
            --text-light: #7A6652; /* Muted brown */
            --bg-light: #FDF5E6; /* Old lace */
            --success-color: #2E8B57; /* Sea green */
        }
        
        body {
            background: linear-gradient(135deg, #F5F5DC 0%, #FDF5E6 100%);
            font-family: 'Georgia', 'Times New Roman', serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
        }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 950px;
            min-height: 600px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            background: white;
        }

        .brand-section {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .brand-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }

        .brand-logo {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            letter-spacing: 2px;
            position: relative;
            z-index: 2;
            font-family: 'Playfair Display', serif;
        }

        .brand-logo span {
            color: var(--accent-color);
        }

        .brand-tagline {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
            font-style: italic;
        }

        .brand-features {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
            position: relative;
            z-index: 2;
        }

        .brand-features li {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-features i {
            margin-right: 10px;
            color: var(--accent-color);
        }

        .login-section {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .login-header h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-family: 'Playfair Display', serif;
        }

        .login-header p {
            color: var(--text-light);
            font-size: 1rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e1e1e1;
            padding: 0.9rem 1.2rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(139, 69, 19, 0.15);
        }

        .input-group-text {
            background: white;
            border: 2px solid #e1e1e1;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 10px;
            padding: 0.9rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.3);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e1e1e1;
        }

        .divider span {
            padding: 0 1rem;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .social-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e1e1e1;
            background: white;
            color: var(--text-dark);
            transition: all 0.3s ease;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }

        .social-btn.google:hover {
            background: #db4437;
            color: white;
            border-color: #db4437;
        }

        .social-btn.facebook:hover {
            background: #3b5998;
            color: white;
            border-color: #3b5998;
        }

        .social-btn.twitter:hover {
            background: #1da1f2;
            color: white;
            border-color: #1da1f2;
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-light);
        }

        .register-link a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: none;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
        }

        .alert-success {
            background-color: rgba(46, 139, 87, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .alert i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .success-animation {
            animation: fadeInOut 5s ease-in-out;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-10px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-10px); }
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 450px;
            }
            
            .brand-section {
                padding: 2rem;
            }
            
            .login-section {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Brand Section -->
        <div class="brand-section">
            <div class="brand-logo">Velvet<span>Vogue</span></div>
            <p class="brand-tagline">Where elegance meets fashion</p>
            <ul class="brand-features">
                <li><i class="fas fa-check-circle"></i> Exclusive fashion collections</li>
                <li><i class="fas fa-check-circle"></i> Personalized style recommendations</li>
                <li><i class="fas fa-check-circle"></i> Premium quality materials</li>
            </ul>
            <div class="mt-4">
                <p>New to Velvet Vogue?</p>
                <a href="user_register.php" class="btn btn-outline-light">Create Account</a>
            </div>
        </div>

        <!-- Login Section -->
        <div class="login-section">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your account to continue</p>
            </div>

            <!-- Display message -->
            <div id="message-container">
                <!-- Messages will be displayed here -->
            </div>

            <!-- Login Form -->
            <form method="POST" id="loginForm">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>
                    <a href="#" class="text-decoration-none" style="color: var(--primary-color);">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-login w-100">Sign In</button>
            </form>

            <div class="divider">
                <span>Or continue with</span>
            </div>

            <div class="social-login">
                <a href="#" class="social-btn google">
                    <i class="fab fa-google"></i>
                </a>
                <a href="#" class="social-btn facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#" class="social-btn twitter">
                    <i class="fab fa-twitter"></i>
                </a>
            </div>

            <div class="register-link">
                Don't have an account? <a href="user_register.php">Register here</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to display messages
        function showMessage(message, type) {
            const messageContainer = document.getElementById('message-container');
            
            // Clear any existing messages
            messageContainer.innerHTML = '';
            
            // Create the message element
            const messageEl = document.createElement('div');
            messageEl.className = `alert alert-${type} d-flex align-items-center`;
            
            // Set icon based on message type
            let icon = '';
            if (type === 'success') {
                icon = '<i class="fas fa-check-circle"></i>';
                messageEl.classList.add('success-animation');
            } else if (type === 'danger') {
                icon = '<i class="fas fa-exclamation-circle"></i>';
            }
            
            messageEl.innerHTML = `${icon} ${message}`;
            
            // Add to container
            messageContainer.appendChild(messageEl);
            
            // If it's a success message, redirect after a delay
            if (type === 'success') {
                setTimeout(() => {
                    window.location.href = 'home.php';
                }, 2000);
            }
        }

        // Simulate form submission for demo purposes
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            // Simple validation
            if (!email || !password) {
                showMessage('Please fill in all fields', 'danger');
                return;
            }
            
            // For demo purposes, show success message
            // In a real application, this would be handled by PHP
            showMessage('Login successful! Redirecting to dashboard...', 'success');
            
            // In a real application, the form would submit to the PHP script
            // and the PHP would handle the authentication and redirect
        });

        // Demo success message on page load (for demonstration)
        // Remove this in production
        window.addEventListener('DOMContentLoaded', function() {
            // Check if URL has a success parameter for demo
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('demo') && urlParams.get('demo') === 'success') {
                showMessage('Login successful! Redirecting to dashboard...', 'success');
            }
        });
    </script>
</body>
</html>