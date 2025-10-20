<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

include '../db.php';

// Get user details
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

if (!$user) {
    header("Location: ../login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $message_type = 'error';
        } else {
            // Update user profile
            $update_stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            
            if ($update_stmt === false) {
                $message = "Database error: " . $conn->error;
                $message_type = 'error';
            } else {
                $update_stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $address, $user_id);
                
                if ($update_stmt->execute()) {
                    $message = "Profile updated successfully!";
                    $message_type = 'success';
                    
                    // Refresh user data
                    $user_query->execute();
                    $user = $user_query->get_result()->fetch_assoc();
                } else {
                    $message = "Error updating profile: " . $update_stmt->error;
                    $message_type = 'error';
                }
            }
        }
        
    } elseif (isset($_POST['update_password'])) {
        // Update password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = "All password fields are required.";
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match.";
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = "New password must be at least 6 characters long.";
            $message_type = 'error';
        } elseif (!password_verify($current_password, $user['password'])) {
            $message = "Current password is incorrect.";
            $message_type = 'error';
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $password_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($password_stmt === false) {
                $message = "Database error: " . $conn->error;
                $message_type = 'error';
            } else {
                $password_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($password_stmt->execute()) {
                    $message = "Password updated successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error updating password: " . $password_stmt->error;
                    $message_type = 'error';
                }
            }
        }
        
    } elseif (isset($_POST['update_profile_pic'])) {
        // Update profile picture
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            
            // Create profiles directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                // Delete old profile picture if exists
                if (!empty($user['profile_pic']) && file_exists($upload_dir . $user['profile_pic'])) {
                    unlink($upload_dir . $user['profile_pic']);
                }
                
                $profile_name = 'profile_' . $user_id . '_' . uniqid() . '.' . $file_extension;
                $target_file = $upload_dir . $profile_name;
                
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                    // Update database
                    $pic_stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    
                    if ($pic_stmt === false) {
                        $message = "Database error: " . $conn->error;
                        $message_type = 'error';
                    } else {
                        $pic_stmt->bind_param("si", $profile_name, $user_id);
                        
                        if ($pic_stmt->execute()) {
                            $message = "Profile picture updated successfully!";
                            $message_type = 'success';
                            
                            // Refresh user data
                            $user_query->execute();
                            $user = $user_query->get_result()->fetch_assoc();
                        } else {
                            $message = "Error updating profile picture: " . $pic_stmt->error;
                            $message_type = 'error';
                        }
                    }
                } else {
                    $message = "Error uploading profile picture.";
                    $message_type = 'error';
                }
            } else {
                $message = "Invalid file type. Only JPG, JPEG, PNG & GIF are allowed.";
                $message_type = 'error';
            }
        } else {
            $message = "Please select a profile picture.";
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Admin Dashboard</title>
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
        .main-content {
            padding: 20px;
        }
        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .profile-pic-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .profile-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .nav-pills .nav-link {
            border-radius: 10px;
            margin-right: 10px;
        }
        .nav-pills .nav-link.active {
            background-color: #6c757d;
        }
        .form-control:focus {
            border-color: #6c757d;
            box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        }
        .btn-primary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-primary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
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
                        <li><a href="orders.php"><i class="fas fa-shopping-cart me-2"></i>Orders</a></li>
                        <li><a href="profile.php" class="active"><i class="fas fa-user me-2"></i>My Profile</a></li>
                        <li class="mt-5"><a href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>My Profile</h2>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['username']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Profile Picture Section -->
                    <div class="col-md-4">
                        <div class="card profile-card mb-4">
                            <div class="card-body text-center">
                                <?php if (!empty($user['profile_pic'])): ?>
                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_pic']); ?>" 
                                         class="profile-pic mb-3" alt="Profile Picture">
                                <?php else: ?>
                                    <div class="profile-pic-placeholder mb-3">
                                        <i class="fas fa-user fa-4x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <h5 class="card-title"><?php echo htmlspecialchars($user['username']); ?></h5>
                                <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <input type="file" class="form-control" name="profile_pic" accept="image/*" required>
                                        <div class="form-text">JPG, PNG, GIF only. Max 2MB.</div>
                                    </div>
                                    <button type="submit" name="update_profile_pic" class="btn btn-primary btn-sm">
                                        <i class="fas fa-upload me-1"></i>Update Picture
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Information Section -->
                    <div class="col-md-8">
                        <div class="card profile-card">
                            <div class="card-body">
                                <ul class="nav nav-pills mb-4" id="profileTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile" type="button" role="tab">
                                            <i class="fas fa-user me-2"></i>Profile Info
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="password-tab" data-bs-toggle="pill" data-bs-target="#password" type="button" role="tab">
                                            <i class="fas fa-lock me-2"></i>Change Password
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content" id="profileTabsContent">
                                    <!-- Profile Information Tab -->
                                    <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                        <form method="POST">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="first_name" class="form-label">First Name</label>
                                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                                               value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="last_name" class="form-label">Last Name</label>
                                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                                               value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="phone" class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="address" class="form-label">Address</label>
                                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Account Information</label>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p class="text-muted mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                                                        <p class="text-muted mb-1"><strong>Role:</strong> <?php echo ucfirst($user['role']); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p class="text-muted mb-1"><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                                                        <p class="text-muted mb-1"><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($user['updated_at'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" name="update_profile" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Update Profile
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Change Password Tab -->
                                    <div class="tab-pane fade" id="password" role="tabpanel">
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                                <div class="form-text">Password must be at least 6 characters long.</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                            </div>
                                            
                                            <button type="submit" name="update_password" class="btn btn-primary">
                                                <i class="fas fa-key me-2"></i>Update Password
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Profile picture preview
        document.querySelector('input[name="profile_pic"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.querySelector('.profile-pic') || document.querySelector('.profile-pic-placeholder');
                    if (img) {
                        if (img.classList.contains('profile-pic-placeholder')) {
                            img.innerHTML = '<img src="' + e.target.result + '" class="profile-pic" alt="Profile Picture">';
                        } else {
                            img.src = e.target.result;
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>


