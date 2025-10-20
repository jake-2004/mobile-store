<?php
// Database setup script
echo "<h2>Database Setup</h2>";

// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "mobile_shop";

try {
    // Connect to MySQL server (without selecting database)
    $conn = new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "✅ Connected to MySQL server<br>";
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname";
    if ($conn->query($sql) === TRUE) {
        echo "✅ Database '$dbname' created or already exists<br>";
    } else {
        echo "❌ Error creating database: " . $conn->error . "<br>";
    }
    
    // Select the database
    $conn->select_db($dbname);
    echo "✅ Selected database '$dbname'<br>";
    
    // Create users table
    $users_sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'customer') DEFAULT 'customer',
        phone VARCHAR(20),
        address TEXT,
        profile_pic VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($users_sql) === TRUE) {
        echo "✅ Users table created or already exists<br>";
    } else {
        echo "❌ Error creating users table: " . $conn->error . "<br>";
    }
    
    // Create products table
    $products_sql = "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        image_url VARCHAR(255),
        stock_quantity INT NOT NULL DEFAULT 0,
        category VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($products_sql) === TRUE) {
        echo "✅ Products table created or already exists<br>";
    } else {
        echo "❌ Error creating products table: " . $conn->error . "<br>";
    }
    
    // Create orders table
    $orders_sql = "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        shipping_address TEXT,
        payment_method VARCHAR(50),
        payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($orders_sql) === TRUE) {
        echo "✅ Orders table created or already exists<br>";
    } else {
        echo "❌ Error creating orders table: " . $conn->error . "<br>";
    }
    
    // Create order_items table
    $order_items_sql = "CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        price DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($order_items_sql) === TRUE) {
        echo "✅ Order items table created or already exists<br>";
    } else {
        echo "❌ Error creating order items table: " . $conn->error . "<br>";
    }
    
    // Create cart table
    $cart_sql = "CREATE TABLE IF NOT EXISTS cart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($cart_sql) === TRUE) {
        echo "✅ Cart table created or already exists<br>";
    } else {
        echo "❌ Error creating cart table: " . $conn->error . "<br>";
    }
    
    // Check if admin user exists, if not create one
    $admin_check = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if ($admin_check->num_rows == 0) {
        $admin_username = "admin";
        $admin_email = "admin@mobileshop.com";
        $admin_password = password_hash("admin123", PASSWORD_BCRYPT);
        $admin_sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')";
        $stmt = $conn->prepare($admin_sql);
        $stmt->bind_param("sss", $admin_username, $admin_email, $admin_password);
        
        if ($stmt->execute()) {
            echo "✅ Default admin user created (username: admin, email: admin@mobileshop.com, password: admin123)<br>";
        } else {
            echo "❌ Error creating admin user: " . $stmt->error . "<br>";
        }
    } else {
        echo "✅ Admin user already exists<br>";
    }
    
    $conn->close();
    echo "<br><strong>✅ Database setup completed successfully!</strong><br>";
    echo "<a href='login.php'>Go to Login Page</a>";
    
} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "<br>";
}
?>
