<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velvet Vogue - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #8B4513;
            --primary-light: #A0522D;
            --primary-dark: #654321;
            --accent: #D4AF37;
            --light: #F5F5F5;
            --dark: #2C2C2C;
            --gray: #8A8A8A;
            --success: #28a745;
            --error: #dc3545;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ed 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: var(--dark);
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .brand-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .brand-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 3px;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .brand-header p {
            font-size: 1.1rem;
            color: var(--gray);
            font-weight: 300;
            letter-spacing: 2px;
        }
        
        .registration-card {
            display: flex;
            width: 100%;
            max-width: 1000px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .registration-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .card-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .card-left::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .card-left::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -30px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .welcome-text h2 {
            font-size: 2.2rem;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .welcome-text p {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .features-list {
            list-style: none;
            margin-top: 20px;
        }
        
        .features-list li {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 1rem;
        }
        
        .features-list i {
            margin-right: 10px;
            color: var(--accent);
            font-size: 1.2rem;
        }
        
        .card-right {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h3 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .form-header p {
            color: var(--gray);
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f9f9f9;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
            outline: none;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .password-toggle {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.2);
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 69, 19, 0.3);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: var(--gray);
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .login-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-left-color: var(--error);
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-left-color: var(--success);
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: var(--gray);
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .divider span {
            padding: 0 15px;
            font-size: 0.9rem;
        }
        
        .social-login {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #f5f5f5;
            color: var(--dark);
            border: 1px solid #e1e1e1;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }
        
        .social-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        @media (max-width: 768px) {
            .registration-card {
                flex-direction: column;
            }
            
            .card-left {
                padding: 30px;
            }
            
            .card-right {
                padding: 30px;
            }
            
            .brand-header h1 {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .brand-header h1 {
                font-size: 2rem;
            }
            
            .card-left, .card-right {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand-header">
            <h1>VELVET VOGUE</h1>
            <p>ELEGANCE REDEFINED</p>
        </div>
        
        <div class="registration-card">
            <div class="card-left">
                <div class="welcome-text">
                    <h2>Join Our Exclusive Community</h2>
                    <p>Create an account to unlock personalized shopping experiences, early access to new collections, and member-only discounts.</p>
                    
                    <ul class="features-list">
                        <li><i class="fas fa-check-circle"></i> Personalized style recommendations</li>
                        <li><i class="fas fa-check-circle"></i> Early access to new collections</li>
                        <li><i class="fas fa-check-circle"></i> Exclusive member discounts</li>
                        <li><i class="fas fa-check-circle"></i> Free shipping on orders over $100</li>
                        <li><i class="fas fa-check-circle"></i> Priority customer support</li>
                    </ul>
                </div>
            </div>
            
            <div class="card-right">
                <div class="form-header">
                    <h3>Create Account</h3>
                    <p>Join Velvet Vogue today</p>
                </div>
                
                <!-- Display message -->
                <div class="alert alert-danger" style="display: none;" id="message"></div>
                
                <!-- Registration Form -->
                <form id="registrationForm">
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" name="first_name" id="first_name" class="form-control" placeholder="Enter your first name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" name="last_name" id="last_name" class="form-control" placeholder="Enter your last name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-with-icon">
                            <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-with-icon">
                            <input type="password" name="password" id="password" class="form-control" placeholder="Create a password" required>
                            <button type="button" class="password-toggle" id="passwordToggle">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">Password must be at least 6 characters long</small>
                    </div>
                    
                    <button type="submit" class="btn-register">Create Account</button>
                    
                    <div class="divider">
                        <span>or continue with</span>
                    </div>
                    
                    <div class="social-login">
                        <a href="#" class="social-btn">
                            <i class="fab fa-google"></i>
                        </a>
                        <a href="#" class="social-btn">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-btn">
                            <i class="fab fa-apple"></i>
                        </a>
                    </div>
                    
                    <div class="login-link">
                        Already have an account? <a href="user_login.php">Sign in here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Password visibility toggle
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Form validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const messageEl = document.getElementById('message');
            
            // Reset message
            messageEl.style.display = 'none';
            messageEl.className = 'alert';
            
            // Validation
            if (!firstName || !lastName || !email || !password) {
                showMessage('All fields are required.', 'danger');
                return;
            }
            
            if (!isValidEmail(email)) {
                showMessage('Please enter a valid email address.', 'danger');
                return;
            }
            
            if (password.length < 6) {
                showMessage('Password must be at least 6 characters long.', 'danger');
                return;
            }
            
            // If all validations pass
            showMessage('Registration successful! Redirecting to login...', 'success');
            
            // In a real application, you would submit the form to the server here
            // For demo purposes, we'll just simulate a successful registration
            setTimeout(() => {
                window.location.href = 'user_login.php';
            }, 2000);
        });
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        function showMessage(text, type) {
            const messageEl = document.getElementById('message');
            messageEl.textContent = text;
            messageEl.className = `alert alert-${type}`;
            messageEl.style.display = 'block';
            
            // Scroll to message
            messageEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    </script>
</body>
</html>