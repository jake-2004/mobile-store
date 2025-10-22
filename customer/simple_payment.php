<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

include '../db.php';
include '../config/razorpay_config.php';

// Check if order_id is provided
if (!isset($_GET['order_id']) || !isset($_SESSION['pending_order'])) {
    header("Location: cart.php");
    exit();
}

$order_id = (int)$_GET['order_id'];
$pending_order = $_SESSION['pending_order'];

// Verify order belongs to current user
if ($pending_order['order_id'] != $order_id) {
    header("Location: cart.php");
    exit();
}

// Get order details from database
$order_query = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$order_query->bind_param("ii", $order_id, $_SESSION['user_id']);
$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

if (!$order) {
    header("Location: cart.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .payment-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h2>Complete Your Payment</h2>
        <p>Order ID: #<?php echo $order_id; ?></p>
        <p>Amount: ₹<?php echo number_format($order['total_amount'], 2); ?></p>
        
        <button class="btn" onclick="payNow()">
            Pay ₹<?php echo number_format($order['total_amount'], 2); ?>
        </button>
        
        <p class="mt-3">
            <a href="cart.php" class="text-decoration-none">← Back to Cart</a>
        </p>
    </div>

    <script>
        function payNow() {
            const options = {
                key: '<?php echo RAZORPAY_KEY_ID; ?>',
                amount: <?php echo $order['total_amount'] * 100; ?>,
                currency: 'INR',
                name: 'Mobile Shop',
                description: 'Order #<?php echo $order_id; ?>',
                prefill: {
                    name: '<?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'Customer'); ?>',
                    email: '<?php echo htmlspecialchars($_SESSION['user']['email'] ?? 'customer@example.com'); ?>',
                    contact: '<?php echo htmlspecialchars($_SESSION['user']['phone'] ?? '9999999999'); ?>'
                },
                notes: {
                    order_id: '<?php echo $order_id; ?>'
                },
                theme: {
                    color: '#667eea'
                },
                handler: function (response) {
                    // Payment successful
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'payment_verify.php';
                    
                    const fields = {
                        'razorpay_payment_id': response.razorpay_payment_id,
                        'razorpay_order_id': response.razorpay_order_id || '',
                        'razorpay_signature': response.razorpay_signature,
                        'order_id': '<?php echo $order_id; ?>'
                    };
                    
                    for (const [key, value] of Object.entries(fields)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }
                    
                    document.body.appendChild(form);
                    form.submit();
                },
                modal: {
                    ondismiss: function() {
                        alert('Payment was cancelled. You can try again.');
                    }
                }
            };
            
            const rzp = new Razorpay(options);
            rzp.open();
        }
    </script>
</body>
</html>
