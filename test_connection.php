<?php
// Database connection test script
echo "<h2>Database Connection Test</h2>";

// Test 1: Check if MySQL service is running
echo "<h3>1. Checking MySQL Service Status</h3>";
$connection = @mysqli_connect("localhost", "root", "", "", 3306);
if ($connection) {
    echo "✅ MySQL service is running<br>";
    mysqli_close($connection);
} else {
    echo "❌ MySQL service is not running or not accessible<br>";
    echo "Error: " . mysqli_connect_error() . "<br>";
    echo "<strong>Solution:</strong> Start MySQL service in XAMPP Control Panel<br><br>";
}

// Test 2: Check if database exists
echo "<h3>2. Checking Database</h3>";
$connection = @mysqli_connect("localhost", "root", "");
if ($connection) {
    $result = mysqli_query($connection, "SHOW DATABASES LIKE 'mobile_shop'");
    if (mysqli_num_rows($result) > 0) {
        echo "✅ Database 'mobile_shop' exists<br>";
    } else {
        echo "❌ Database 'mobile_shop' does not exist<br>";
        echo "<strong>Solution:</strong> Create the database or run the setup script<br>";
    }
    mysqli_close($connection);
} else {
    echo "❌ Cannot connect to MySQL server<br>";
}

// Test 3: Check port availability
echo "<h3>3. Checking Port 3306</h3>";
$connection = @fsockopen("localhost", 3306, $errno, $errstr, 5);
if ($connection) {
    echo "✅ Port 3306 is available<br>";
    fclose($connection);
} else {
    echo "❌ Port 3306 is not available<br>";
    echo "Error: $errstr ($errno)<br>";
}

// Test 4: Try different connection methods
echo "<h3>4. Testing Connection Methods</h3>";

// Method 1: mysqli object
echo "Testing mysqli object method...<br>";
try {
    $conn1 = new mysqli("localhost", "root", "", "mobile_shop", 3306);
    if ($conn1->connect_error) {
        echo "❌ mysqli object failed: " . $conn1->connect_error . "<br>";
    } else {
        echo "✅ mysqli object connection successful<br>";
        $conn1->close();
    }
} catch (Exception $e) {
    echo "❌ mysqli object exception: " . $e->getMessage() . "<br>";
}

// Method 2: mysqli_connect function
echo "Testing mysqli_connect function...<br>";
$conn2 = @mysqli_connect("localhost", "root", "", "mobile_shop", 3306);
if (!$conn2) {
    echo "❌ mysqli_connect failed: " . mysqli_connect_error() . "<br>";
} else {
    echo "✅ mysqli_connect successful<br>";
    mysqli_close($conn2);
}

// Test 5: Check for existing connections
echo "<h3>5. System Information</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "MySQL Extension: " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";

echo "<hr>";
echo "<h3>Troubleshooting Steps:</h3>";
echo "<ol>";
echo "<li><strong>Start XAMPP:</strong> Open XAMPP Control Panel and start MySQL service</li>";
echo "<li><strong>Check Port:</strong> Make sure no other application is using port 3306</li>";
echo "<li><strong>Create Database:</strong> If database doesn't exist, create it in phpMyAdmin</li>";
echo "<li><strong>Restart Services:</strong> Stop and start MySQL service in XAMPP</li>";
echo "<li><strong>Check Firewall:</strong> Make sure Windows Firewall isn't blocking MySQL</li>";
echo "</ol>";
?>
