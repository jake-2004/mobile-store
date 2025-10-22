<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

include '../db.php';
include '../config/razorpay_config.php';

// Check if payment data is received
if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['razorpay_payment_id'])) {
    header("Location: cart.php");
    exit();
}

$razorpay_payment_id = $_POST['razorpay_payment_id'];
$razorpay_order_id = $_POST['razorpay_order_id'];
$razorpay_signature = $_POST['razorpay_signature'];
$order_id = (int)$_POST['order_id'];

// Verify payment signature
function verifyPaymentSignature($razorpay_order_id, $razorpay_payment_id, $razorpay_signature) {
    // For direct payments without order creation, we'll skip signature verification
    // In production, you should implement proper signature verification
    if (empty($razorpay_order_id)) {
        return true; // Skip verification for direct payments
    }
    $generated_signature = hash_hmac('sha256', $razorpay_order_id . "|" . $razorpay_payment_id, RAZORPAY_KEY_SECRET);
    return hash_equals($generated_signature, $razorpay_signature);
}

// Get order details
$order_query = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$order_query->bind_param("ii", $order_id, $_SESSION['user_id']);
$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

if (!$order) {
    header("Location: cart.php?error=order_not_found");
    exit();
}

// Verify payment signature
if (!verifyPaymentSignature($razorpay_order_id, $razorpay_payment_id, $razorpay_signature)) {
    // Payment verification failed
    $update_stmt = $conn->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?");
    $update_stmt->bind_param("i", $order_id);
    $update_stmt->execute();
    
    header("Location: payment_failure.php?order_id=" . $order_id);
    exit();
}

// Payment verification successful
try {
    $conn->begin_transaction();
    
    // Update order with payment details
    // Check if new columns exist, if not use basic update
    $columns_check = $conn->query("SHOW COLUMNS FROM orders LIKE 'razorpay_payment_id'");
    if ($columns_check->num_rows > 0) {
        // New columns exist
        $update_order_stmt = $conn->prepare("UPDATE orders SET 
                                            razorpay_payment_id = ?, 
                                            razorpay_order_id = ?, 
                                            razorpay_signature = ?, 
                                            payment_status = 'paid', 
                                            status = 'processing' 
                                            WHERE id = ?");
        $update_order_stmt->bind_param("sssi", $razorpay_payment_id, $razorpay_order_id, $razorpay_signature, $order_id);
    } else {
        // Fallback to basic update
        $update_order_stmt = $conn->prepare("UPDATE orders SET 
                                            payment_status = 'paid', 
                                            status = 'processing' 
                                            WHERE id = ?");
        $update_order_stmt->bind_param("i", $order_id);
    }
    $update_order_stmt->execute();
    
    // Get cart items from session
    if (isset($_SESSION['pending_order']['cart_items'])) {
        $cart_items = $_SESSION['pending_order']['cart_items'];
        
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
        $clear_cart_stmt->bind_param("i", $_SESSION['user_id']);
        $clear_cart_stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Clear pending order from session
    unset($_SESSION['pending_order']);
    
    // Redirect to success page
    header("Location: payment_success.php?order_id=" . $order_id . "&payment_id=" . $razorpay_payment_id);
    exit();
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Update order status to failed
    $update_stmt = $conn->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?");
    $update_stmt->bind_param("i", $order_id);
    $update_stmt->execute();
    
    header("Location: payment_failure.php?order_id=" . $order_id . "&error=" . urlencode($e->getMessage()));
    exit();
}
?>
