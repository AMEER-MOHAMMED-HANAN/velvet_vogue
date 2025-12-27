<?php
include '../includes/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = "";

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Detect whether the connection is secure for cookie 'secure' flag
$is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

// Cookie-based login
if (!isset($_SESSION['Admin_id']) && isset($_COOKIE['admin_auth_token'])) {
    $token = $_COOKIE['admin_auth_tokfen'];
    $sql = "SELECT Admin_id FROM admin_tokens WHERE token = ? AND expires_at > NOW()";
    $stmt = $conn->prepare($sql);
    if ($stmt) {

        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $_SESSION['Admin_id'] = $row['Admin_id'];
            header("Location: admin_dashboard.php");
            exit();
        }
        $stmt->close();
    }
    // Delete cookie using options (respecting secure detection)
    setcookie('admin_auth_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Handle login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "<div class='alert alert-danger'>Invalid CSRF token.</div>";
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $sql = "SELECT Admin_id, Password FROM admin WHERE Username = ? AND Password = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $username, $password);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                // Regenerate session id to prevent fixation
                session_regenerate_id(true);
                // Use a consistent session key name
                $_SESSION['Admin_id'] = $admin['Admin_id'];

                // Remember me -> create persistent token
                if (isset($_POST['remember_me'])) {
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $token_sql = "INSERT INTO admin_tokens (Admin_id, token, expires_at) VALUES (?, ?, ?)";
                    $token_stmt = $conn->prepare($token_sql);
                    if ($token_stmt) {
                        $token_stmt->bind_param("iss", $admin['Admin_id'], $token, $expires_at);
                        $token_stmt->execute();
                        $token_stmt->close();
                    }

                    setcookie('admin_auth_token', $token, [
                        'expires' => time() + 30*24*60*60,
                        'path' => '/',
                        'secure' => $is_secure,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                }
                
                // Successful login -> redirect to dashboard
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header("Location: admin_dashboard.php");
                exit();
            } else {
                // Generic message to avoid user enumeration
                $message = "<div class='alert alert-danger'>Invalid username or password.</div>";
            }
            $stmt->close();
        } else {
            // Prepare failed
            $message = "<div class='alert alert-danger'>An error occurred. Please try again later.</div>";
        }
    }
}

// Logout
if (isset($_GET['logout'])) {
    if (isset($_COOKIE['admin_auth_token'])) {
        $token = $_COOKIE['admin_auth_token'];
        $sql = "DELETE FROM admin_tokens WHERE token = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
        setcookie('admin_auth_token', '', time() - 3600, '/', '', true, true);
    }
    session_unset();
    session_destroy();
    header("Location: admin_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Velvet Vogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Arial', sans-serif;
        }
        .login-container {
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .btn-primary {
            background: linear-gradient(90deg, #ff7e5f, #feb47b);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #ff5e3a, #ff9f55);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">Admin Login</h2>
        <?php if ($message) echo $message; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="remember_me" id="remember_me" class="form-check-input">
                <label for="remember_me" class="form-check-label">Remember Me</label>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
        </form>
    </div>

    <script>
        if (document.getElementById('successMessage')) {
            setTimeout(() => location.href = 'admin_dashboard.php', 2000);
        }
    </script>
</body>
</html>