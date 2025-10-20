<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../db.php';

$message = '';
$message_type = '';

// Handle order status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    
    $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_status, $order_id);
    
    if ($update_stmt->execute()) {
        $message = "Order status updated successfully.";
        $message_type = 'success';
    } else {
        $message = "Error updating order status.";
        $message_type = 'error';
    }
}

// Handle payment status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_payment'])) {
    $order_id = (int)$_POST['order_id'];
    $new_payment_status = $_POST['payment_status'];
    
    $update_stmt = $conn->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_payment_status, $order_id);
    
    if ($update_stmt->execute()) {
        $message = "Payment status updated successfully.";
        $message_type = 'success';
    } else {
        $message = "Error updating payment status.";
        $message_type = 'error';
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($status_filter)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($payment_filter)) {
    $where_conditions[] = "o.payment_status = ?";
    $params[] = $payment_filter;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR o.id LIKE ? OR o.shipping_address LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all orders with customer details
$orders_query = "SELECT o.*, u.username, u.email, u.phone,
                COUNT(oi.id) as item_count,
                GROUP_CONCAT(
                    CONCAT(p.name, ' (Qty: ', oi.quantity, ')') 
                    SEPARATOR ', '
                ) as products
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN products p ON oi.product_id = p.id
                $where_clause
                GROUP BY o.id
                ORDER BY o.created_at DESC";

$orders_stmt = $conn->prepare($orders_query);

if (!empty($params)) {
    $orders_stmt->bind_param($param_types, ...$params);
}

$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
$orders = [];

while ($order = $orders_result->fetch_assoc()) {
    $orders[] = $order;
}

// Get order statistics
$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
    SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
    SUM(total_amount) as total_revenue
    FROM orders";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Mobile Shop</title>
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
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .order-card {
            border-left: 4px solid #007bff;
        }
        .order-card.pending {
            border-left-color: #ffc107;
        }
        .order-card.processing {
            border-left-color: #17a2b8;
        }
        .order-card.shipped {
            border-left-color: #6f42c1;
        }
        .order-card.delivered {
            border-left-color: #28a745;
        }
        .order-card.cancelled {
            border-left-color: #dc3545;
        }
        .status-badge {
            font-size: 0.8em;
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
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
                        <li><a href="users.php"><i class="fas fa-users me-2"></i>Users</a></li>
                        <li><a href="orders.php" class="active"><i class="fas fa-shopping-cart me-2"></i>Orders</a></li>
                        <li class="mt-5"><a href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-shopping-cart me-2"></i>Orders Management</h2>
                    <div class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Total Orders: <?php echo $stats['total_orders']; ?>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h5 class="text-primary"><?php echo $stats['total_orders']; ?></h5>
                                <small class="text-muted">Total Orders</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h5 class="text-warning"><?php echo $stats['pending_orders']; ?></h5>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h5 class="text-info"><?php echo $stats['processing_orders']; ?></h5>
                                <small class="text-muted">Processing</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h5 class="text-purple"><?php echo $stats['shipped_orders']; ?></h5>
                                <small class="text-muted">Shipped</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h5 class="text-success"><?php echo $stats['delivered_orders']; ?></h5>
                                <small class="text-muted">Delivered</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h5 class="text-success">$<?php echo number_format($stats['total_revenue'], 2); ?></h5>
                                <small class="text-muted">Revenue</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Order Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Payment Status</label>
                            <select name="payment" class="form-select">
                                <option value="">All Payments</option>
                                <option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="failed" <?php echo $payment_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by customer, order ID, or address..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Orders List -->
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No orders found</h4>
                        <p class="text-muted">Orders will appear here once customers place them.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($orders as $order): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card order-card <?php echo $order['status']; ?> h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Order #<?php echo $order['id']; ?></h6>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></small>
                                    </div>
                                    <div class="card-body">
                                        <!-- Customer Info -->
                                        <div class="mb-3">
                                            <h6 class="mb-1">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($order['username']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($order['email'] ?? 'No email'); ?>
                                            </small>
                                        </div>

                                        <!-- Order Details -->
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted">Items:</span>
                                                <span><?php echo $order['item_count']; ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted">Total:</span>
                                                <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Payment:</span>
                                                <span><?php echo ucfirst($order['payment_method'] ?? 'N/A'); ?></span>
                                            </div>
                                        </div>

                                        <!-- Products -->
                                        <?php if ($order['products']): ?>
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <strong>Products:</strong><br>
                                                    <?php echo htmlspecialchars($order['products']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Status Badges -->
                                        <div class="mb-3">
                                            <span class="badge bg-<?php 
                                                echo $order['status'] === 'delivered' ? 'success' : 
                                                    ($order['status'] === 'pending' ? 'warning' : 
                                                    ($order['status'] === 'cancelled' ? 'danger' : 'info')); 
                                            ?> status-badge me-2">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                            <span class="badge bg-<?php 
                                                echo $order['payment_status'] === 'paid' ? 'success' : 
                                                    ($order['payment_status'] === 'failed' ? 'danger' : 'warning'); 
                                            ?> status-badge">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#orderModal<?php echo $order['id']; ?>">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Details Modal -->
                            <div class="modal fade" id="orderModal<?php echo $order['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Order #<?php echo $order['id']; ?> Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Customer Information -->
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <h6>Customer Information</h6>
                                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['username']); ?></p>
                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email'] ?? 'Not provided'); ?></p>
                                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone'] ?? 'Not provided'); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Order Information</h6>
                                                    <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                                                    <p><strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                                                    <p><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method'] ?? 'N/A'); ?></p>
                                                </div>
                                            </div>

                                            <!-- Shipping Address -->
                                            <?php if ($order['shipping_address']): ?>
                                                <div class="mb-4">
                                                    <h6>Shipping Address</h6>
                                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Status Management -->
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Update Order Status</h6>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <div class="input-group">
                                                            <select name="status" class="form-select">
                                                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                                <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                            </select>
                                                            <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                                                        </div>
                                                    </form>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Update Payment Status</h6>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <div class="input-group">
                                                            <select name="payment_status" class="form-select">
                                                                <option value="pending" <?php echo $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                                <option value="failed" <?php echo $order['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                            </select>
                                                            <button type="submit" name="update_payment" class="btn btn-success">Update</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
