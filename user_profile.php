<?php
include 'includes/db_connect.php';
session_start();


$user_id = (int)$_SESSION['user_id'];
$message = "";

// Submit Inquiry
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = (int)$_POST['product_id'];
    $type = trim($_POST['type']);
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if ($csrf_token !== $_SESSION['csrf_token']) {
        $message = "<div class='alert alert-danger'>Invalid CSRF token.</div>";
    } elseif (empty($product_id) || empty($type)) {
        $message = "<div class='alert alert-danger'>Product ID and inquiry type are required.</div>";
    } else {
        $admin_id = 1; // Assume admin ID 1
        $stmt = $conn->prepare("INSERT INTO enquiry (User_ID_fk, admin_id_fk, Product_ID_fk, Enquiry_Type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $user_id, $admin_id, $product_id, $type);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Inquiry sent successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error sending inquiry: " . $conn->error . "</div>";
        }
        $stmt->close();
    }
}

// Fetch user data
$user_sql = "SELECT first_name, last_name, email FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Generate CSRF token for the form
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<?php include 'includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velvet Vogue - User Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        .profile-card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            background: white;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 2rem auto;
            transition: transform 0.3s ease;
        }
        .profile-card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background: linear-gradient(90deg, #ff7e5f, #feb47b);
            color: white;
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .card-header i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .card-body {
            padding: 2.5rem;
        }
        .profile-info {
            margin-bottom: 2rem;
            text-align: center;
        }
        .profile-info h2 {
            font-size: 2rem;
            color: #ff7e5f;
            margin-bottom: 1rem;
        }
        .profile-info p {
            font-size: 1.1rem;
            color: #333;
        }
        .form-label {
            font-weight: bold;
            color: #333;
        }
        .form-control {
            border-radius: 12px;
            border: 2px solid #dee2e6;
            padding: 0.8rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus {
            border-color: #ff7e5f;
            box-shadow: 0 0 8px rgba(255, 126, 95, 0.3);
        }
        .btn-primary {
            background: linear-gradient(90deg, #ff7e5f, #feb47b);
            border: none;
            border-radius: 12px;
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
            font-weight: bold;
            text-transform: uppercase;
            color: white;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #ff5e3a, #ff9f55);
            transform: translateY(-3px);
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 1.8rem;
            color: #ff7e5f;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-card">
            <div class="card-header">
                <i class="fas fa-user-circle"></i>
                <h2 class="mb-0">User Profile</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($message)) echo $message; ?>

                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <h3 class="section-title">Send Inquiry</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label for="product_id" class="form-label">Product ID</label>
                        <input type="number" name="product_id" id="product_id" class="form-control" placeholder="Enter Product ID" required>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Inquiry Type</label>
                        <input type="text" name="type" id="type" class="form-control" placeholder="e.g., Size Availability" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Inquiry</button>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>