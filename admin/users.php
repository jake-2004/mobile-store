<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../db.php';

// Get all customers (users with role = 'customer')
$customers = [];
$customers_query = "SELECT u.*, 
                    COUNT(o.id) as total_orders,
                    COALESCE(SUM(o.total_amount), 0) as total_spent,
                    MAX(o.created_at) as last_order_date
                    FROM users u 
                    LEFT JOIN orders o ON u.id = o.user_id 
                    WHERE u.role = 'customer' 
                    GROUP BY u.id 
                    ORDER BY u.created_at DESC";

$result = $conn->query($customers_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Handle view customer orders
$customer_orders = [];
$selected_customer = null;
if (isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
    $customer_id = (int)$_GET['customer_id'];
    
    // Get customer details
    $customer_query = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $customer_query->bind_param("i", $customer_id);
    $customer_query->execute();
    $selected_customer = $customer_query->get_result()->fetch_assoc();
    
    if ($selected_customer) {
        // Get customer orders with order items
        $orders_query = "SELECT o.*, 
                        GROUP_CONCAT(
                            CONCAT(p.name, ' (Qty: ', oi.quantity, ')') 
                            SEPARATOR ', '
                        ) as products
                        FROM orders o
                        LEFT JOIN order_items oi ON o.id = oi.order_id
                        LEFT JOIN products p ON oi.product_id = p.id
                        WHERE o.user_id = ?
                        GROUP BY o.id
                        ORDER BY o.created_at DESC";
        
        $orders_stmt = $conn->prepare($orders_query);
        $orders_stmt->bind_param("i", $customer_id);
        $orders_stmt->execute();
        $orders_result = $orders_stmt->get_result();
        
        while ($order = $orders_result->fetch_assoc()) {
            $customer_orders[] = $order;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Mobile Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
        }
        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }
        .sidebar a:hover {
            color: white;
            background-color: #495057;
        }
        .sidebar .active {
            background-color: #007bff;
            color: white;
        }
        .main-content {
            padding: 20px;
        }
        .customer-card {
            transition: transform 0.2s;
        }
        .customer-card:hover {
            transform: translateY(-2px);
        }
        .order-status {
            font-size: 0.85em;
        }
        .back-btn {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-3">
                    <h4 class="text-center mb-4">Admin Panel</h4>
                    <ul class="nav flex-column">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                        <li><a href="products.php"><i class="fas fa-box me-2"></i>Products</a></li>
                        <li><a href="users.php" class="active"><i class="fas fa-users me-2"></i>Users</a></li>
                        <li><a href="orders.php"><i class="fas fa-shopping-cart me-2"></i>Orders</a></li>
                        <li class="mt-5"><a href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <?php if ($selected_customer): ?>
                    <!-- Customer Order History View -->
                    <div class="back-btn">
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Users
                        </a>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-user me-2"></i>Customer Orders</h2>
                    </div>
                    
                    <!-- Customer Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Customer Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Username:</strong> <?php echo htmlspecialchars($selected_customer['username']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($selected_customer['email'] ?? 'Not provided'); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($selected_customer['phone'] ?? 'Not provided'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Total Orders:</strong> <?php echo count($customer_orders); ?></p>
                                    <p><strong>Total Spent:</strong> $<?php echo number_format($selected_customer['total_spent'] ?? 0, 2); ?></p>
                                    <p><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($selected_customer['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Orders List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Order History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($customer_orders)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">This customer hasn't placed any orders yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Date</th>
                                                <th>Products</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Payment</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customer_orders as $order): ?>
                                                <tr>
                                                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                                    <td>
                                                        <small class="text-muted"><?php echo htmlspecialchars($order['products'] ?? 'No products'); ?></small>
                                                    </td>
                                                    <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $order['status'] === 'delivered' ? 'success' : 
                                                                ($order['status'] === 'pending' ? 'warning' : 
                                                                ($order['status'] === 'cancelled' ? 'danger' : 'info')); 
                                                        ?> order-status">
                                                            <?php echo ucfirst($order['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $order['payment_status'] === 'paid' ? 'success' : 
                                                                ($order['payment_status'] === 'failed' ? 'danger' : 'warning'); 
                                                        ?> order-status">
                                                            <?php echo ucfirst($order['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Users List View -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-users me-2"></i>Customers Management</h2>
                        <div class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Total Customers: <?php echo count($customers); ?>
                        </div>
                    </div>
                    
                    <?php if (empty($customers)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No customers found</h4>
                            <p class="text-muted">Customers will appear here once they register.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($customers as $customer): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card customer-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($customer['username']); ?></h6>
                                                    <small class="text-muted">Customer ID: #<?php echo $customer['id']; ?></small>
                                                </div>
                                            </div>
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-4">
                                                    <div class="border-end">
                                                        <h6 class="mb-0 text-primary"><?php echo $customer['total_orders']; ?></h6>
                                                        <small class="text-muted">Orders</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="border-end">
                                                        <h6 class="mb-0 text-success">$<?php echo number_format($customer['total_spent'], 0); ?></h6>
                                                        <small class="text-muted">Spent</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <h6 class="mb-0 text-info">
                                                        <?php echo $customer['last_order_date'] ? date('M d', strtotime($customer['last_order_date'])) : 'Never'; ?>
                                                    </h6>
                                                    <small class="text-muted">Last Order</small>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($customer['email'] ?? 'No email'); ?>
                                                </small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Member since <?php echo date('M Y', strtotime($customer['created_at'])); ?>
                                                </small>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <a href="users.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-eye me-2"></i>View Orders
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
