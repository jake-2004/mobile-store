<?php
// Simple test page to check authentication system
session_start();
include 'db.php';

echo "<h2>Authentication System Test</h2>";

// Test database connection
if ($conn->connect_error) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>✅ Database connection successful</p>";
}

// Test if users table exists
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<p style='color: green;'>✅ Users table exists</p>";
    
    // Check table structure
    $structure = $conn->query("DESCRIBE users");
    if ($structure) {
        echo "<h3>Users table structure:</h3><ul>";
        while ($row = $structure->fetch_assoc()) {
            echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
        }
        echo "</ul>";
    }
    
    // Check if there are any users
    $user_count = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($user_count) {
        $count = $user_count->fetch_assoc()['count'];
        echo "<p>📊 Total users in database: " . $count . "</p>";
        
        if ($count > 0) {
            echo "<h3>Existing users:</h3><ul>";
            $users = $conn->query("SELECT id, username, email, role FROM users");
            while ($user = $users->fetch_assoc()) {
                echo "<li>ID: " . $user['id'] . ", Username: " . $user['username'] . ", Email: " . $user['email'] . ", Role: " . $user['role'] . "</li>";
            }
            echo "</ul>";
        }
    }
    
} else {
    echo "<p style='color: red;'>❌ Users table does not exist</p>";
    echo "<p><a href='setup_database.php'>Run Database Setup</a></p>";
}

// Test session
if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ User is logged in (ID: " . $_SESSION['user_id'] . ")</p>";
} else {
    echo "<p style='color: orange;'>⚠️ No user logged in</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>Go to Login Page</a> | <a href='register.php'>Go to Register Page</a></p>";
?>
