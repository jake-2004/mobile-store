<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

include '../db.php';
include '../config/razorpay_config.php';

// Get customer details
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

$message = '';
$message_type = '';

// Handle checkout form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['proceed_to_payment'])) {
    $shipping_address = trim($_POST['shipping_address']);
    $payment_method = trim($_POST['payment_method']);
    
    if (empty($shipping_address) || empty($payment_method)) {
        $message = "Please provide shipping address and payment method.";
        $message_type = 'error';
    } else {
        // Get cart items
        $cart_query = "SELECT c.*, p.name, p.price, p.stock_quantity 
                      FROM cart c 
                      JOIN products p ON c.product_id = p.id 
                      WHERE c.user_id = ?";
        $cart_stmt = $conn->prepare($cart_query);
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_items = $cart_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (empty($cart_items)) {
            $message = "Your cart is empty.";
            $message_type = 'error';
        } else {
            // Check stock availability
            $stock_ok = true;
            foreach ($cart_items as $item) {
                if ($item['quantity'] > $item['stock_quantity']) {
                    $stock_ok = false;
                    $message = "Insufficient stock for " . $item['name'] . ". Available: " . $item['stock_quantity'];
                    break;
                }
            }
            
            if ($stock_ok) {
                // Calculate total
                $total_amount = 0;
                foreach ($cart_items as $item) {
                    $total_amount += $item['price'] * $item['quantity'];
                }
                
                // Create order with pending payment status
                // First try with new columns, fallback to old structure if columns don't exist
                $order_stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, payment_method, payment_status, shipping_address) VALUES (?, ?, 'pending', ?, 'pending', ?)");
                if ($order_stmt === false) {
                    $message = "Database error: " . $conn->error;
                    $message_type = 'error';
                } else {
                    $order_stmt->bind_param("idss", $user_id, $total_amount, $payment_method, $shipping_address);
                    $order_stmt->execute();
                    $order_id = $conn->insert_id;
                }
                
                // Store order details in session for payment processing
                $_SESSION['pending_order'] = [
                    'order_id' => $order_id,
                    'total_amount' => $total_amount,
                    'cart_items' => $cart_items,
                    'shipping_address' => $shipping_address,
                    'payment_method' => $payment_method
                ];
                
                // Redirect to payment page
                header("Location: simple_payment.php?order_id=" . $order_id);
                exit();
            }
        }
    }
}

// Get cart items with product details
$cart_items = [];
$total_amount = 0;

$cart_query = "SELECT c.*, p.name, p.price, p.stock_quantity, p.image_url, p.category 
              FROM cart c 
              JOIN products p ON c.product_id = p.id 
              WHERE c.user_id = ? 
              ORDER BY c.created_at DESC";

$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();

while ($row = $cart_result->fetch_assoc()) {
    $cart_items[] = $row;
    $total_amount += $row['price'] * $row['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #f093fb;
            --danger-color: #ff6b6b;
            --info-color: #4facfe;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            min-height: 100vh;
            background: var(--gradient-primary);
            color: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            margin: 5px 10px;
            border-radius: 10px;
            padding: 15px 20px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
        }

        .checkout-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            background: white;
        }

        .order-summary {
            background: var(--gradient-success);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .cart-item {
            border: none;
            border-radius: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
        }

        .btn {
            border-radius: 25px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4>Mobile Shop</h4>
                        <p class="text-muted">Customer Panel</p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="cart.php">
                                <i class="fas fa-shopping-cart"></i> My Cart
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_orders.php">
                                <i class="fas fa-box"></i> My Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-th-large"></i> Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Checkout</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['username']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <?php if (empty($cart_items)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                                <h3 class="text-muted">Your Cart is Empty</h3>
                                <p class="text-muted">Add some products to your cart to get started!</p>
                                <a href="products.php" class="btn btn-primary">
                                    <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="checkout-card">
                                <div class="card-body p-4">
                                    <h4 class="mb-4">Order Summary</h4>
                                    
                                    <?php foreach ($cart_items as $item): ?>
                                        <div class="cart-item">
                                            <div class="card-body p-3">
                                                <div class="row align-items-center">
                                                    <div class="col-md-2">
                                                        <?php if (!empty($item['image_url'])): ?>
                                                            <img src="../uploads/<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                                 class="product-image" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                        <?php else: ?>
                                                            <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="col-md-6">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                        <?php if (!empty($item['category'])): ?>
                                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="col-md-2">
                                                        <span class="h6 mb-0">$<?php echo number_format($item['price'], 2); ?></span>
                                                    </div>
                                                    
                                                    <div class="col-md-2">
                                                        <span class="badge bg-primary">Qty: <?php echo $item['quantity']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($cart_items)): ?>
                        <div class="col-lg-4">
                            <div class="checkout-card">
                                <div class="card-body p-4">
                                    <div class="order-summary">
                                        <h5 class="mb-3">Order Total</h5>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal:</span>
                                            <span>$<?php echo number_format($total_amount, 2); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Shipping:</span>
                                            <span>Free</span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                            <strong>Total:</strong>
                                            <strong>$<?php echo number_format($total_amount, 2); ?></strong>
                                        </div>
                                    </div>
                                    
                                    <!-- Checkout Form -->
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label for="shipping_address" class="form-label">Shipping Address</label>
                                            <textarea class="form-control" id="shipping_address" name="shipping_address" 
                                                      rows="3" required placeholder="Enter your complete shipping address"><?php echo isset($_POST['shipping_address']) ? htmlspecialchars($_POST['shipping_address']) : ''; ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="payment_method" class="form-label">Payment Method</label>
                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="">Select Payment Method</option>
                                                <option value="Razorpay" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'Razorpay') ? 'selected' : ''; ?>>Razorpay (Credit/Debit Card, UPI, Net Banking)</option>
                                                <option value="Cash on Delivery" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'Cash on Delivery') ? 'selected' : ''; ?>>Cash on Delivery</option>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" name="proceed_to_payment" class="btn btn-primary w-100">
                                            <i class="fas fa-credit-card me-2"></i>Proceed to Payment
                                        </button>
                                    </form>
                                    
                                    <div class="text-center mt-3">
                                        <a href="cart.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Cart
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
