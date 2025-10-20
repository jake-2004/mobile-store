<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

include '../db.php';

// Get customer details
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

$message = '';
$message_type = '';

// Handle cart operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['product_id'])) {
        // Add product to cart
        $product_id = (int)$_POST['product_id'];
        
        // Check if product exists and has stock
        $product_query = $conn->prepare("SELECT id, name, stock_quantity FROM products WHERE id = ?");
        $product_query->bind_param("i", $product_id);
        $product_query->execute();
        $product = $product_query->get_result()->fetch_assoc();
        
        if (!$product) {
            $message = "Product not found.";
            $message_type = 'error';
        } elseif ($product['stock_quantity'] <= 0) {
            $message = "Sorry, " . $product['name'] . " is out of stock.";
            $message_type = 'error';
        } else {
            // Check if item already exists in cart
            $existing_query = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $existing_query->bind_param("ii", $user_id, $product_id);
            $existing_query->execute();
            $existing_item = $existing_query->get_result()->fetch_assoc();
            
            if ($existing_item) {
                // Update quantity if item exists
                $new_quantity = $existing_item['quantity'] + 1;
                if ($new_quantity > $product['stock_quantity']) {
                    $message = "Cannot add more items. Only " . $product['stock_quantity'] . " available in stock.";
                    $message_type = 'error';
                } else {
                    $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $update_stmt->bind_param("ii", $new_quantity, $existing_item['id']);
                    $update_stmt->execute();
                    $message = "Cart updated successfully.";
                    $message_type = 'success';
                }
            } else {
                // Add new item to cart
                $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
                $insert_stmt->bind_param("ii", $user_id, $product_id);
                $insert_stmt->execute();
                $message = "Item added to cart successfully.";
                $message_type = 'success';
            }
        }
        
    } elseif (isset($_POST['update_quantity'])) {
        // Update cart item quantity
        $cart_id = (int)$_POST['cart_id'];
        $new_quantity = (int)$_POST['quantity'];
        
        if ($new_quantity <= 0) {
            // Remove item from cart
            $delete_stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $delete_stmt->bind_param("ii", $cart_id, $user_id);
            $delete_stmt->execute();
            $message = "Item removed from cart.";
        } else {
            // Check stock availability before updating
            $stock_check_query = $conn->prepare("SELECT p.stock_quantity, p.name FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?");
            $stock_check_query->bind_param("ii", $cart_id, $user_id);
            $stock_check_query->execute();
            $stock_info = $stock_check_query->get_result()->fetch_assoc();
            
            if (!$stock_info) {
                $message = "Cart item not found.";
                $message_type = 'error';
            } elseif ($new_quantity > $stock_info['stock_quantity']) {
                $message = "Cannot update quantity. Only " . $stock_info['stock_quantity'] . " available in stock for " . $stock_info['name'] . ".";
                $message_type = 'error';
            } else {
                // Update quantity
                $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                $update_stmt->bind_param("iii", $new_quantity, $cart_id, $user_id);
                $update_stmt->execute();
                $message = "Cart updated successfully.";
                $message_type = 'success';
            }
        }
        
    } elseif (isset($_POST['remove_item'])) {
        // Remove item from cart
        $cart_id = (int)$_POST['cart_id'];
        $delete_stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $cart_id, $user_id);
        $delete_stmt->execute();
        $message = "Item removed from cart.";
        $message_type = 'success';
        
    } elseif (isset($_POST['checkout'])) {
        // Process checkout
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
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Create order
                        $order_stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, payment_method, payment_status, shipping_address) VALUES (?, ?, 'pending', ?, 'pending', ?)");
                        $order_stmt->bind_param("idss", $user_id, $total_amount, $payment_method, $shipping_address);
                        $order_stmt->execute();
                        $order_id = $conn->insert_id;
                        
                        // Create order items and update stock
                        foreach ($cart_items as $item) {
                            // Insert order item
                            $order_item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                            $order_item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                            $order_item_stmt->execute();
                            
                            // Update product stock
                            $new_stock = $item['stock_quantity'] - $item['quantity'];
                            $stock_stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                            $stock_stmt->bind_param("ii", $new_stock, $item['product_id']);
                            $stock_stmt->execute();
                        }
                        
                        // Clear cart
                        $clear_cart_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                        $clear_cart_stmt->bind_param("i", $user_id);
                        $clear_cart_stmt->execute();
                        
                        // Commit transaction
                        $conn->commit();
                        
                        $message = "Order placed successfully! Order ID: #" . $order_id;
                        $message_type = 'success';
                        
                        // Redirect to orders page
                        header("Location: my_orders.php?order_placed=1&order_id=" . $order_id);
                        exit();
                        
                    } catch (Exception $e) {
                        // Rollback transaction
                        $conn->rollback();
                        $message = "Error processing order: " . $e->getMessage();
                        $message_type = 'error';
                    }
                }
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

if ($cart_stmt === false) {
    die("Database error: " . $conn->error);
}

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
    <title>My Cart - Mobile Shop</title>
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
            --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
            position: relative;
            overflow: hidden;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            margin: 5px 10px;
            border-radius: 10px;
            padding: 15px 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .cart-item {
            border: none;
            border-radius: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }

        .cart-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .cart-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .cart-item:hover .product-image {
            transform: scale(1.1);
        }

        .quantity-input {
            width: 70px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .quantity-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .checkout-summary {
            background: white;
            border-radius: 20px;
            padding: 30px;
            position: sticky;
            top: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 3px solid transparent;
            background-clip: padding-box;
            position: relative;
            overflow: hidden;
        }

        .checkout-summary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 20px;
            padding: 3px;
            background: var(--gradient-success);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
        }

        .btn {
            border-radius: 25px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--gradient-primary);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .btn-success {
            background: var(--gradient-success);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }

        .btn-warning {
            background: var(--gradient-warning);
            box-shadow: 0 5px 15px rgba(240, 147, 251, 0.4);
        }

        .btn-danger {
            background: var(--gradient-danger);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        .badge {
            border-radius: 20px;
            padding: 8px 15px;
            font-weight: 600;
        }

        .badge.bg-primary {
            background: var(--gradient-primary) !important;
        }

        .alert {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .page-header {
            background: var(--gradient-primary);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .page-header h2 {
            margin: 0;
            font-weight: 700;
        }

        .total-amount {
            background: var(--gradient-success);
            color: white;
            padding: 15px 25px;
            border-radius: 15px;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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
                    <h1 class="h2">My Cart</h1>
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
                            <h4>Cart Items (<?php echo count($cart_items); ?>)</h4>
                            
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item">
                                    <div class="card-body">
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
                                            
                                            <div class="col-md-4">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <?php if (!empty($item['category'])): ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category']); ?></span>
                                                <?php endif; ?>
                                                <p class="text-muted mb-0 small">
                                                    Stock: <?php echo $item['stock_quantity']; ?> available
                                                </p>
                                            </div>
                                            
                                            <div class="col-md-2">
                                                <span class="h6 mb-0">$<?php echo number_format($item['price'], 2); ?></span>
                                            </div>
                                            
                                            <div class="col-md-2">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" name="quantity" class="form-control quantity-input" 
                                                               value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock_quantity']; ?>">
                                                        <button type="submit" name="update_quantity" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-sync"></i>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                            
                                            <div class="col-md-2 text-end">
                                                <div class="h6 mb-1">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" name="remove_item" class="btn btn-outline-danger btn-sm" 
                                                            onclick="return confirm('Remove this item from cart?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($cart_items)): ?>
                        <div class="col-lg-4">
                            <div class="checkout-summary">
                                <h5 class="mb-3">Order Summary</h5>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span>$<?php echo number_format($total_amount, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Shipping:</span>
                                    <span>Free</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-3">
                                    <strong>Total:</strong>
                                    <strong>$<?php echo number_format($total_amount, 2); ?></strong>
                                </div>
                                
                                <!-- Checkout Form -->
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="shipping_address" class="form-label">Shipping Address</label>
                                        <textarea class="form-control" id="shipping_address" name="shipping_address" 
                                                  rows="3" required placeholder="Enter your complete shipping address"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="payment_method" class="form-label">Payment Method</label>
                                        <select class="form-select" id="payment_method" name="payment_method" required>
                                            <option value="">Select Payment Method</option>
                                            <option value="Credit Card">Credit Card</option>
                                            <option value="Debit Card">Debit Card</option>
                                            <option value="PayPal">PayPal</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Cash on Delivery">Cash on Delivery</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" name="checkout" class="btn btn-success w-100">
                                        <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                                    </button>
                                </form>
                                
                                <div class="text-center mt-3">
                                    <a href="products.php" class="btn btn-outline-primary">
                                        <i class="fas fa-plus me-2"></i>Continue Shopping
                                    </a>
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