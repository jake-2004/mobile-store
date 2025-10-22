<?php
// Script to update database for Razorpay integration
include 'db.php';

echo "<h2>Updating Database for Razorpay Integration</h2>";

try {
    // Check if columns already exist
    $check_columns = $conn->query("SHOW COLUMNS FROM orders LIKE 'razorpay_payment_id'");
    
    if ($check_columns->num_rows == 0) {
        // Add Razorpay payment fields to orders table
        $sql = "ALTER TABLE orders 
                ADD COLUMN razorpay_payment_id VARCHAR(255) NULL,
                ADD COLUMN razorpay_order_id VARCHAR(255) NULL,
                ADD COLUMN razorpay_signature VARCHAR(255) NULL,
                ADD COLUMN payment_currency VARCHAR(10) DEFAULT 'INR',
                ADD COLUMN payment_amount DECIMAL(10,2) NULL";
        
        if ($conn->query($sql) === TRUE) {
            echo "✅ Razorpay payment fields added to orders table<br>";
        } else {
            echo "❌ Error adding Razorpay fields: " . $conn->error . "<br>";
        }
        
        // Add indexes for better performance
        $index1 = "CREATE INDEX idx_razorpay_payment_id ON orders(razorpay_payment_id)";
        $index2 = "CREATE INDEX idx_razorpay_order_id ON orders(razorpay_order_id)";
        
        if ($conn->query($index1) === TRUE) {
            echo "✅ Index on razorpay_payment_id created<br>";
        } else {
            echo "⚠️ Index on razorpay_payment_id already exists or error: " . $conn->error . "<br>";
        }
        
        if ($conn->query($index2) === TRUE) {
            echo "✅ Index on razorpay_order_id created<br>";
        } else {
            echo "⚠️ Index on razorpay_order_id already exists or error: " . $conn->error . "<br>";
        }
        
    } else {
        echo "✅ Razorpay payment fields already exist in orders table<br>";
    }
    
    echo "<br><strong>Database update completed!</strong><br>";
    echo "<a href='customer/cart.php'>Go to Cart</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

$conn->close();
?>
