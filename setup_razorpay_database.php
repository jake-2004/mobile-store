<?php
// Simple database setup for Razorpay integration
include 'db.php';

echo "<h2>Setting up Razorpay Database Integration</h2>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;}</style>";

try {
    // Check if columns already exist
    $check_columns = $conn->query("SHOW COLUMNS FROM orders LIKE 'razorpay_payment_id'");
    
    if ($check_columns->num_rows == 0) {
        echo "<p>Adding Razorpay payment fields to orders table...</p>";
        
        // Add Razorpay payment fields to orders table
        $sql = "ALTER TABLE orders 
                ADD COLUMN razorpay_payment_id VARCHAR(255) NULL,
                ADD COLUMN razorpay_order_id VARCHAR(255) NULL,
                ADD COLUMN razorpay_signature VARCHAR(255) NULL,
                ADD COLUMN payment_currency VARCHAR(10) DEFAULT 'INR',
                ADD COLUMN payment_amount DECIMAL(10,2) NULL";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p class='success'>✅ Razorpay payment fields added to orders table</p>";
        } else {
            echo "<p class='error'>❌ Error adding Razorpay fields: " . $conn->error . "</p>";
        }
        
        // Add indexes for better performance
        $indexes = [
            "CREATE INDEX idx_razorpay_payment_id ON orders(razorpay_payment_id)",
            "CREATE INDEX idx_razorpay_order_id ON orders(razorpay_order_id)"
        ];
        
        foreach ($indexes as $index_sql) {
            if ($conn->query($index_sql) === TRUE) {
                echo "<p class='success'>✅ Index created successfully</p>";
            } else {
                echo "<p class='warning'>⚠️ Index creation: " . $conn->error . "</p>";
            }
        }
        
    } else {
        echo "<p class='success'>✅ Razorpay payment fields already exist in orders table</p>";
    }
    
    // Test the configuration
    echo "<h3>Testing Configuration</h3>";
    
    if (file_exists('config/razorpay_config.php')) {
        include 'config/razorpay_config.php';
        echo "<p class='success'>✅ Razorpay config file found</p>";
        
        if (defined('RAZORPAY_KEY_ID') && RAZORPAY_KEY_ID !== 'rzp_test_YOUR_KEY_ID_HERE') {
            echo "<p class='success'>✅ Razorpay API keys configured</p>";
        } else {
            echo "<p class='warning'>⚠️ Please update your Razorpay API keys in config/razorpay_config.php</p>";
        }
    } else {
        echo "<p class='error'>❌ Razorpay config file not found</p>";
    }
    
    echo "<br><h3>Setup Complete!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Make sure your Razorpay API keys are configured in config/razorpay_config.php</li>";
    echo "<li>Test the payment flow by adding items to cart and proceeding to checkout</li>";
    echo "<li>Use Razorpay test cards for testing: 4111 1111 1111 1111</li>";
    echo "</ul>";
    
    echo "<p><a href='customer/cart.php' style='background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Cart</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
