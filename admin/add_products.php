<?php
session_start();
include '../db.php';

if ($_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = $_POST['price'];

    $stmt = $conn->prepare("INSERT INTO products (name, description, price) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $name, $desc, $price);
    $stmt->execute();

    echo "Product added!";
}
?>

<form method="post">
    Name: <input name="name"><br>
    Description: <textarea name="description"></textarea><br>
    Price: <input name="price" type="number" step="0.01"><br>
    <input type="submit" value="Add Product">
</form>
