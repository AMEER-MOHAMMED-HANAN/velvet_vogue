<?php include 'includes/db_connect.php'; session_start(); ?>
<?php include 'includes/header.php'; ?>
<h1>Welcome to Velvet Vogue</h1>
<p>Trendy casual and formal wear for young adults.</p>

<!-- New Arrivals (Fetch from DB) -->
<h2>New Arrivals</h2>
<div class="row">
    <?php
    $sql = "SELECT p.*, i.image_url FROM products p JOIN product_images i ON p.product_id = i.product_id_fk LIMIT 4"; // Join for images
    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
        echo '<div class="col-md-3">
                <div class="product-card">
                    <img src="' . $row['image_url'] . '" class="product-img">
                    <h5>' . $row['name'] . '</h5>
                    <p>$' . $row['price'] . '</p>
                    <a href="product_details.php?id=' . $row['product_id'] . '" class="btn btn-primary">View</a>
                </div>
              </div>';
    }
    ?>
</div>

<!-- Promotions (Static for simplicity) -->
<h2>Promotions</h2>
<p>20% off on all casual wear! Ends soon.</p>

<?php include 'includes/footer.php'; ?>