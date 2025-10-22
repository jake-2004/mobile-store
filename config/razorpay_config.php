<?php
// Razorpay Configuration
// Replace these with your actual Razorpay API keys

// Test Keys (for development)
define('RAZORPAY_KEY_ID', 'rzp_test_7JZmKUBcWxl6xQ');
define('RAZORPAY_KEY_SECRET', 'DTfkKiBVoQuBxkfAzLuitRdq');

// Live Keys (for production) - uncomment and use these for live environment
// define('RAZORPAY_KEY_ID', 'rzp_live_YOUR_LIVE_KEY_ID_HERE');
// define('RAZORPAY_KEY_SECRET', 'YOUR_LIVE_KEY_SECRET_HERE');

// Currency
define('RAZORPAY_CURRENCY', 'INR');

// Company details for payment page
define('COMPANY_NAME', 'Mobile Shop');
define('COMPANY_LOGO', ''); // Optional - leave empty if no logo

// Webhook secret (for payment verification)
define('RAZORPAY_WEBHOOK_SECRET', 'YOUR_WEBHOOK_SECRET_HERE');

// Success and failure URLs
define('PAYMENT_SUCCESS_URL', 'http://localhost/mini_mobile_shop/customer/payment_success.php');
define('PAYMENT_FAILURE_URL', 'http://localhost/mini_mobile_shop/customer/payment_failure.php');

// Note: 
// 1. Get your API keys from https://dashboard.razorpay.com/
// 2. Replace the placeholder values above with your actual keys
// 3. For production, use live keys and update the URLs accordingly
?>
