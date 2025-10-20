<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../db.php';

// Handle product deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = $_GET['delete'];
    
    // First, get the image path to delete the file
    $stmt = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    // Delete the product
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    
    // Delete the associated image file if it exists
    if (!empty($product['image_url']) && file_exists("../uploads/" . $product['image_url'])) {
        unlink("../uploads/" . $product['image_url']);
    }
    
    // Redirect to prevent form resubmission
    header("Location: products.php?deleted=1");
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && !empty($_POST['selected_products'])) {
    $selected_products = $_POST['selected_products'];
    $placeholders = implode(',', array_fill(0, count($selected_products), '?'));
    $types = str_repeat('i', count($selected_products));
    
    if ($_POST['bulk_action'] === 'delete') {
        // Get image paths before deletion
        $stmt = $conn->prepare("SELECT image_url FROM products WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$selected_products);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Delete image files
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['image_url']) && file_exists("../uploads/" . $row['image_url'])) {
                unlink("../uploads/" . $row['image_url']);
            }
        }
        
        // Delete products
        $stmt = $conn->prepare("DELETE FROM products WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$selected_products);
        $stmt->execute();
        
        header("Location: products.php?bulk_deleted=1");
        exit();
    } elseif ($_POST['bulk_action'] === 'update_status') {
        // Handle bulk status update if needed
        // Example: $status = $_POST['status'];
        // $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id IN ($placeholders)");
        // $stmt->bind_param("s" . $types, $status, ...$selected_products);
        // $stmt->execute();
        // header("Location: products.php?bulk_updated=1");
        // exit();
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id_desc';

// Build the query
$query = "SELECT * FROM products WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

if (!empty($category)) {
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

// Add sorting
switch ($sort) {
    case 'name_asc':
        $query .= " ORDER BY name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY name DESC";
        break;
    case 'price_asc':
        $query .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY price DESC";
        break;
    case 'stock_low':
        $query .= " ORDER BY stock_quantity ASC";
        break;
    case 'id_desc':
    default:
        $query .= " ORDER BY id DESC";
        break;
}

// Get total count for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
if ($stmt = $conn->prepare($count_query)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_products = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_products = 0;
}

// Add pagination
$per_page = 10;
$total_pages = ceil($total_products / $per_page);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$query .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// Execute the query
$products = [];
if ($stmt = $conn->prepare($query)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Get all categories for filter
$categories = [];
$categories_result = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #f093fb;
            --danger-color: #ff6b6b;
            --info-color: #4facfe;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            min-height: 100vh;
            background: var(--gradient-primary);
            color: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .sidebar a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 15px 20px;
            display: block;
            transition: all 0.3s ease;
            border-radius: 10px;
            margin: 5px 10px;
            position: relative;
            overflow: hidden;
        }

        .sidebar a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .sidebar a:hover::before {
            left: 100%;
        }

        .sidebar a:hover {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .sidebar .active {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transform: translateX(5px);
        }

        .main-content {
            padding: 30px;
            background: transparent;
        }

        .product-image {
            max-width: 60px;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .table {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            background: white;
        }

        .table thead th {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 20px;
            font-weight: 600;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: scale(1.01);
        }

        .table th {
            white-space: nowrap;
        }

        .sort-link {
            color: inherit;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sort-link:hover {
            color: rgba(255, 255, 255, 0.8);
            transform: scale(1.05);
        }

        .sort-arrow {
            margin-left: 5px;
            transition: all 0.3s ease;
        }

        .btn {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: var(--gradient-primary);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .btn-success {
            background: var(--gradient-success);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }

        .btn-warning {
            background: var(--gradient-warning);
            box-shadow: 0 5px 15px rgba(240, 147, 251, 0.4);
        }

        .btn-danger {
            background: var(--gradient-danger);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        .badge {
            border-radius: 20px;
            padding: 8px 15px;
            font-weight: 600;
        }

        .alert {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .stats-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 3px solid transparent;
            background-clip: padding-box;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 20px;
            padding: 3px;
            background: var(--gradient-primary);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
        }

        .stats-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .page-header {
            background: var(--gradient-primary);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .page-header h2 {
            margin: 0;
            font-weight: 700;
        }

        .floating-action {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .floating-action .btn {
            border-radius: 50%;
            width: 60px;
            height: 60px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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
                        <li><a href="products.php" class="active"><i class="fas fa-box me-2"></i>Products</a></li>
                        <li><a href="users.php"><i class="fas fa-users me-2"></i>Users</a></li>
                        <li><a href="orders.php"><i class="fas fa-shopping-cart me-2"></i>Orders</a></li>
                        <li class="mt-5"><a href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Products</h2>
                    <a href="add_product.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Product
                    </a>
                </div>

                <?php if (isset($_GET['added'])): ?>
                    <div class="alert alert-success">Product added successfully!</div>
                <?php endif; ?>
                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success">Product updated successfully!</div>
                <?php endif; ?>
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">Product deleted successfully!</div>
                <?php endif; ?>
                <?php if (isset($_GET['bulk_deleted'])): ?>
                    <div class="alert alert-success">Selected products have been deleted!</div>
                <?php endif; ?>

                <!-- Filters and Search -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search products...">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                                <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="sort" class="form-label">Sort By</label>
                                <select class="form-select" id="sort" name="sort" onchange="this.form.submit()">
                                    <option value="id_desc" <?php echo ($sort === 'id_desc') ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="name_asc" <?php echo ($sort === 'name_asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                                    <option value="name_desc" <?php echo ($sort === 'name_desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                                    <option value="price_asc" <?php echo ($sort === 'price_asc') ? 'selected' : ''; ?>>Price (Low to High)</option>
                                    <option value="price_desc" <?php echo ($sort === 'price_desc') ? 'selected' : ''; ?>>Price (High to Low)</option>
                                    <option value="stock_low" <?php echo ($sort === 'stock_low') ? 'selected' : ''; ?>>Low Stock</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <a href="products.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="card">
                    <div class="card-body">
                        <form method="post" id="bulkActionForm">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                                </div>
                                            </th>
                                            <th width="80">Image</th>
                                            <th>
                                                <a href="?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo ($sort === 'name_asc') ? 'name_desc' : 'name_asc'; ?>" class="sort-link">
                                                    Product
                                                    <?php if ($sort === 'name_asc'): ?>
                                                        <i class="fas fa-sort-up sort-arrow"></i>
                                                    <?php elseif ($sort === 'name_desc'): ?>
                                                        <i class="fas fa-sort-down sort-arrow"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-sort sort-arrow"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th class="text-end">
                                                <a href="?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo ($sort === 'price_asc') ? 'price_desc' : 'price_asc'; ?>" class="sort-link">
                                                    Price
                                                    <?php if ($sort === 'price_asc'): ?>
                                                        <i class="fas fa-sort-up sort-arrow"></i>
                                                    <?php elseif ($sort === 'price_desc'): ?>
                                                        <i class="fas fa-sort-down sort-arrow"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-sort sort-arrow"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th class="text-center">Stock</th>
                                            <th>Category</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($products)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <div class="text-muted mb-2">
                                                        <i class="fas fa-box-open fa-3x"></i>
                                                    </div>
                                                    <h5>No products found</h5>
                                                    <p class="text-muted">
                                                        <?php echo !empty($search) ? 'Try adjusting your search or filters.' : 'Get started by adding your first product.' ?>
                                                    </p>
                                                    <a href="add_product.php" class="btn btn-primary mt-2">
                                                        <i class="fas fa-plus me-1"></i> Add Product
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input product-checkbox" type="checkbox" 
                                                                   name="selected_products[]" value="<?php echo $product['id']; ?>">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($product['image_url'])): ?>
                                                            <img src="../uploads/<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                                alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                                class="product-image">
                                                        <?php else: ?>
                                                            <div class="text-muted small">No image</div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                        <div class="text-muted small">ID: <?php echo $product['id']; ?></div>
                                                    </td>
                                                    <td class="text-end">
                                                        $<?php echo number_format($product['price'], 2); ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php 
                                                        $stock_class = '';
                                                        if ($product['stock_quantity'] <= 0) {
                                                            $stock_class = 'text-danger';
                                                            $stock_text = 'Out of Stock';
                                                        } elseif ($product['stock_quantity'] < 10) {
                                                            $stock_class = 'text-warning';
                                                            $stock_text = $product['stock_quantity'] . ' left';
                                                        } else {
                                                            $stock_text = $product['stock_quantity'];
                                                        }
                                                        ?>
                                                        <span class="<?php echo $stock_class; ?>">
                                                            <?php echo $stock_text; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($product['category'])): ?>
                                                            <span class="badge bg-secondary">
                                                                <?php echo htmlspecialchars($product['category']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">Uncategorized</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?delete=<?php echo $product['id']; ?>" 
                                                           class="btn btn-sm btn-outline-danger" 
                                                           title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete this product?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (!empty($products)): ?>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center">
                                            <select name="bulk_action" class="form-select form-select-sm me-2" style="width: auto;">
                                                <option value="">Bulk Actions</option>
                                                <option value="delete">Delete Selected</option>
                                                <!-- Add more bulk actions as needed -->
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" id="applyBulkAction">
                                                Apply
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($total_pages > 1): ?>
                                            <nav class="float-end">
                                                <ul class="pagination pagination-sm mb-0">
                                                    <?php if ($page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo $sort; ?>">
                                                                &laquo; Previous
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo $sort; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    
                                                    <?php if ($page < $total_pages): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo $sort; ?>">
                                                                Next &raquo;
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </nav>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="mt-4 text-muted small">
                    Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select all checkboxes
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Bulk action form submission
        document.getElementById('bulkActionForm').addEventListener('submit', function(e) {
            const bulkAction = this.elements['bulk_action'].value;
            const selectedProducts = document.querySelectorAll('.product-checkbox:checked');
            
            if (selectedProducts.length === 0 && bulkAction) {
                e.preventDefault();
                alert('Please select at least one product.');
                return false;
            }
            
            if (bulkAction === 'delete' && !confirm('Are you sure you want to delete the selected products?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>
