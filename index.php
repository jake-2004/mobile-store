<?php
include 'db.php';

// Fetch latest products for public landing page
$products = [];
$result = $conn->query("SELECT id, name, description, price, image_url, stock_quantity FROM products ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-card { transition: transform .15s ease, box-shadow .15s ease; }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .product-img { height: 200px; object-fit: cover; }
        .hero {
            background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
            color: #fff;
        }
        a.stretched-link { text-decoration: none; }
    </style>
    <link rel="icon" href="data:,">
    <!-- Prevent default favicon request 404 in local dev -->
    <meta name="description" content="Shop the latest mobile phones and accessories.">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="index.php">
                <i class="fas fa-mobile-alt me-2 text-primary"></i>Mobile Shop
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-primary" href="register.php">Register</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="display-6 fw-bold mb-3">Welcome to Mobile Shop</h1>
                    <p class="lead mb-4">Discover great deals on the latest smartphones and accessories. Login or create an account to explore details and buy.</p>
                    <a href="login.php" class="btn btn-light btn-lg me-2"><i class="fas fa-sign-in-alt me-2"></i>Login</a>
                    <a href="register.php" class="btn btn-outline-light btn-lg"><i class="fas fa-user-plus me-2"></i>Register</a>
                </div>
            </div>
        </div>
    </header>

    <main class="py-5">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0">Latest Products</h2>
                <a href="login.php" class="text-decoration-none">Login to view details <i class="fas fa-arrow-right ms-1"></i></a>
            </div>

            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No products available yet.</h5>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $product): ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <div class="card h-100 product-card">
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="card-img-top product-img">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center product-img">
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text text-muted small mb-3">
                                        <?php echo htmlspecialchars(substr((string)$product['description'], 0, 80)) . (strlen((string)$product['description']) > 80 ? '…' : ''); ?>
                                    </p>
                                    <div class="mt-auto d-flex justify-content-between align-items-center">
                                        <span class="h6 mb-0 text-primary">$<?php echo number_format((float)$product['price'], 2); ?></span>
                                        <small class="text-muted"><?php echo (int)$product['stock_quantity']; ?> in stock</small>
                                    </div>
                                    <a href="login.php" class="stretched-link" aria-label="Login to view product"></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="py-4 border-top mt-5">
        <div class="container text-center small text-muted">
            &copy; <?php echo date('Y'); ?> Mobile Shop. All rights reserved.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>


