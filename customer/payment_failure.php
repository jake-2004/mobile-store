<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

include '../db.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Get order details if available
$order = null;
if ($order_id > 0) {
    $order_query = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $order_query->bind_param("ii", $order_id, $_SESSION['user_id']);
    $order_query->execute();
    $order = $order_query->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --danger-color: #ff6b6b;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .failure-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .failure-header {
            background: var(--gradient-danger);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .failure-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .failure-body {
            padding: 40px;
        }

        .error-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
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

        .btn-danger {
            background: var(--gradient-danger);
            border: none;
        }

        .order-info {
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
        <div class="failure-container">
            <div class="failure-header">
                <div class="failure-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h2>Payment Failed</h2>
                <p class="mb-0">We couldn't process your payment</p>
            </div>
            
            <div class="failure-body">
                <?php if ($order): ?>
                    <div class="order-info">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-receipt me-2"></i>Order ID</h6>
                                <p class="mb-0">#<?php echo $order_id; ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-dollar-sign me-2"></i>Amount</h6>
                                <p class="mb-0">₹<?php echo number_format($order['total_amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="error-details">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>What happened?</h5>
                    <p class="mb-3">Your payment could not be processed. This could be due to:</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-danger me-2"></i>Insufficient funds in your account</li>
                        <li><i class="fas fa-check text-danger me-2"></i>Card details entered incorrectly</li>
                        <li><i class="fas fa-check text-danger me-2"></i>Network connectivity issues</li>
                        <li><i class="fas fa-check text-danger me-2"></i>Payment gateway timeout</li>
                        <li><i class="fas fa-check text-danger me-2"></i>Card blocked by your bank</li>
                    </ul>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-warning mt-3">
                            <strong>Technical Details:</strong><br>
                            <small><?php echo htmlspecialchars($error); ?></small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-center">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Don't worry!</strong><br>
                        Your order has been saved and you can retry the payment. No charges have been made to your account.
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <a href="cart.php" class="btn btn-primary">
                            <i class="fas fa-redo me-2"></i>Try Again
                        </a>
                        <a href="products.php" class="btn btn-outline-secondary">
                            <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                        </a>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <div class="card">
                        <div class="card-body">
                            <h6><i class="fas fa-headset me-2"></i>Need Help?</h6>
                            <p class="mb-2">If you continue to experience issues, please contact our support team.</p>
                            <a href="mailto:support@mobileshop.com" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-envelope me-2"></i>Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
