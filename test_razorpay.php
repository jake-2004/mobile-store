<?php
// Simple Razorpay test page
include 'config/razorpay_config.php';

echo "<h2>Razorpay Integration Test</h2>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

// Test 1: Check if config file exists and is loaded
echo "<h3>1. Configuration Test</h3>";
if (defined('RAZORPAY_KEY_ID')) {
    echo "<p class='success'>✅ Razorpay Key ID: " . RAZORPAY_KEY_ID . "</p>";
} else {
    echo "<p class='error'>❌ Razorpay Key ID not defined</p>";
}

if (defined('RAZORPAY_KEY_SECRET')) {
    echo "<p class='success'>✅ Razorpay Key Secret: " . substr(RAZORPAY_KEY_SECRET, 0, 10) . "...</p>";
} else {
    echo "<p class='error'>❌ Razorpay Key Secret not defined</p>";
}

// Test 2: Check database connection
echo "<h3>2. Database Test</h3>";
include 'db.php';
if ($conn->connect_error) {
    echo "<p class='error'>❌ Database connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p class='success'>✅ Database connected successfully</p>";
    
    // Check if orders table has Razorpay columns
    $check_columns = $conn->query("SHOW COLUMNS FROM orders LIKE 'razorpay_payment_id'");
    if ($check_columns->num_rows > 0) {
        echo "<p class='success'>✅ Razorpay columns exist in orders table</p>";
    } else {
        echo "<p class='error'>❌ Razorpay columns missing from orders table</p>";
        echo "<p class='info'>💡 Run: <a href='setup_razorpay_database.php'>setup_razorpay_database.php</a></p>";
    }
}

// Test 3: Simple Razorpay payment test
echo "<h3>3. Razorpay Payment Test</h3>";
echo "<div style='border:1px solid #ccc;padding:20px;margin:10px 0;'>";
echo "<h4>Test Payment (₹1.00)</h4>";
echo "<button onclick='testPayment()' style='background:#667eea;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;'>Test Payment</button>";
echo "</div>";

echo "<script src='https://checkout.razorpay.com/v1/checkout.js'></script>";
echo "<script>
function testPayment() {
    const options = {
        key: '" . RAZORPAY_KEY_ID . "',
        amount: 100, // ₹1.00 in paise
        currency: 'INR',
        name: 'Mobile Shop Test',
        description: 'Test Payment',
        prefill: {
            name: 'Test User',
            email: 'test@example.com',
            contact: '9999999999'
        },
        theme: {
            color: '#667eea'
        },
        handler: function (response) {
            alert('Payment Success! Payment ID: ' + response.razorpay_payment_id);
        },
        modal: {
            ondismiss: function() {
                alert('Payment cancelled');
            }
        }
    };
    
    const rzp = new Razorpay(options);
    rzp.open();
}
</script>";

echo "<h3>4. Test Cards</h3>";
echo "<div style='background:#f8f9fa;padding:15px;border-radius:5px;'>";
echo "<p><strong>Use these test card details:</strong></p>";
echo "<ul>";
echo "<li><strong>Card Number:</strong> 4111 1111 1111 1111</li>";
echo "<li><strong>Expiry:</strong> Any future date (e.g., 12/25)</li>";
echo "<li><strong>CVV:</strong> Any 3 digits (e.g., 123)</li>";
echo "<li><strong>Name:</strong> Any name</li>";
echo "</ul>";
echo "</div>";

echo "<p><a href='customer/cart.php' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Cart</a></p>";
?>
