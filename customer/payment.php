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

// Generate unique order ID for Razorpay
$razorpay_order_id = 'order_' . $order_id . '_' . time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
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
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .payment-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .payment-header {
            background: var(--gradient-primary);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .payment-body {
            padding: 40px;
        }

        .order-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .btn {
            border-radius: 25px;
            padding: 15px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: var(--gradient-success);
            color: white;
        }

        .btn-danger {
            background: var(--gradient-danger);
            color: white;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .payment-method {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }

        .payment-method.selected {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .spinner-border {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-container">
            <div class="payment-header">
                <h2><i class="fas fa-credit-card me-2"></i>Secure Payment</h2>
                <p class="mb-0">Complete your order with Razorpay</p>
            </div>
            
            <div class="payment-body">
                <div class="order-details">
                    <h5><i class="fas fa-receipt me-2"></i>Order Details</h5>
                    <div class="row">
                        <div class="col-6">
                            <strong>Order ID:</strong><br>
                            <span class="text-muted">#<?php echo $order_id; ?></span>
                        </div>
                        <div class="col-6">
                            <strong>Total Amount:</strong><br>
                            <span class="h5 text-primary">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <strong>Shipping Address:</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="text-center mb-4">
                    <h5>Choose Payment Method</h5>
                    <div class="payment-methods">
                        <div class="payment-method selected" data-method="razorpay">
                            <i class="fas fa-credit-card fa-2x mb-2"></i>
                            <div>Credit/Debit Card</div>
                        </div>
                        <div class="payment-method" data-method="upi">
                            <i class="fas fa-mobile-alt fa-2x mb-2"></i>
                            <div>UPI</div>
                        </div>
                        <div class="payment-method" data-method="netbanking">
                            <i class="fas fa-university fa-2x mb-2"></i>
                            <div>Net Banking</div>
                        </div>
                        <div class="payment-method" data-method="wallet">
                            <i class="fas fa-wallet fa-2x mb-2"></i>
                            <div>Wallets</div>
                        </div>
                    </div>
                </div>

                <div class="loading" id="loading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Processing payment...</p>
                </div>

                <div class="text-center">
                    <button class="btn btn-primary btn-lg" id="pay-button" onclick="openRazorpay()">
                        <i class="fas fa-lock me-2"></i>Pay ₹<?php echo number_format($order['total_amount'], 2); ?>
                    </button>
                </div>

                <div class="text-center mt-3">
                    <a href="cart.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Cart
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Razorpay configuration
        const razorpayConfig = {
            key: '<?php echo RAZORPAY_KEY_ID; ?>',
            amount: <?php echo $order['total_amount'] * 100; ?>, // Amount in paise
            currency: '<?php echo RAZORPAY_CURRENCY; ?>',
            name: '<?php echo COMPANY_NAME; ?>',
            description: 'Order #<?php echo $order_id; ?>',
            image: '<?php echo COMPANY_LOGO; ?>',
            prefill: {
                name: '<?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'Customer'); ?>',
                email: '<?php echo htmlspecialchars($_SESSION['user']['email'] ?? 'customer@example.com'); ?>',
                contact: '<?php echo htmlspecialchars($_SESSION['user']['phone'] ?? '9999999999'); ?>'
            },
            notes: {
                order_id: '<?php echo $order_id; ?>',
                user_id: '<?php echo $_SESSION['user_id']; ?>'
            },
            theme: {
                color: '#667eea'
            },
            handler: function (response) {
                // Payment successful
                document.getElementById('loading').style.display = 'block';
                document.getElementById('pay-button').style.display = 'none';
                
                // Submit payment details to server
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
                    // Payment cancelled
                    console.log('Payment cancelled');
                    alert('Payment was cancelled. You can try again.');
                }
            }
        };

        function openRazorpay() {
            const rzp = new Razorpay(razorpayConfig);
            rzp.open();
        }

        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
    </script>
</body>
</html>
