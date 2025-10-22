<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

include '../db.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$payment_id = isset($_GET['payment_id']) ? $_GET['payment_id'] : '';

// Get order details
$order_query = $conn->prepare("SELECT o.*, u.username, u.email FROM orders o 
                              JOIN users u ON o.user_id = u.id 
                              WHERE o.id = ? AND o.user_id = ?");
$order_query->bind_param("ii", $order_id, $_SESSION['user_id']);
$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

if (!$order) {
    header("Location: cart.php");
    exit();
}

// Get order items
$items_query = $conn->prepare("SELECT oi.*, p.name, p.image_url FROM order_items oi 
                              JOIN products p ON oi.product_id = p.id 
                              WHERE oi.order_id = ?");
$items_query->bind_param("i", $order_id);
$items_query->execute();
$order_items = $items_query->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #4facfe;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .success-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .success-header {
            background: var(--gradient-success);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .success-body {
            padding: 40px;
        }

        .order-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .order-item {
            border: none;
            border-radius: 10px;
            margin-bottom: 15px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
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

        .btn-success {
            background: var(--gradient-success);
            border: none;
        }

        .payment-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-container">
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Payment Successful!</h2>
                <p class="mb-0">Your order has been placed successfully</p>
            </div>
            
            <div class="success-body">
                <div class="payment-info">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-receipt me-2"></i>Order ID</h6>
                            <p class="mb-0">#<?php echo $order_id; ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-credit-card me-2"></i>Payment ID</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($payment_id); ?></p>
                        </div>
                    </div>
                </div>

                <div class="order-details">
                    <h5><i class="fas fa-shopping-bag me-2"></i>Order Summary</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Customer:</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($order['username']); ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Total Amount:</strong><br>
                            <span class="h5 text-primary">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Payment Status:</strong><br>
                            <span class="badge bg-success">Paid</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Order Status:</strong><br>
                            <span class="badge bg-info">Processing</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <strong>Shipping Address:</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($order_items)): ?>
                    <h5><i class="fas fa-list me-2"></i>Order Items</h5>
                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
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
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <span class="text-muted">Qty: <?php echo $item['quantity']; ?></span>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <span class="h6 mb-0">₹<?php echo number_format($item['price'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>What's next?</strong><br>
                        You will receive an email confirmation shortly. Our team will process your order and update you on the shipping status.
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <a href="my_orders.php" class="btn btn-primary">
                            <i class="fas fa-box me-2"></i>View My Orders
                        </a>
                        <a href="products.php" class="btn btn-success">
                            <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
