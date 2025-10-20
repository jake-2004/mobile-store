<?php
// Set custom session path
$sessDir = __DIR__ . '/sessions';

// Create sessions directory if it doesn't exist
if (!file_exists($sessDir)) {
    mkdir($sessDir, 0755, true);
}

// Set session save path
session_save_path($sessDir);

// Additional session configuration
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 1440); // 24 minutes

// Start the session
session_start();

include 'db.php';

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'customer'; // Default all registrations to customer
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        // Check if username already exists
        $check_username = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_username->bind_param("s", $username);
        $check_username->execute();
        $check_username->store_result();
        
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();
        
        if ($check_username->num_rows > 0) {
            $error = 'Username already exists';
        } elseif ($check_email->num_rows > 0) {
            $error = 'Email already exists';
        } else {
            // Hash the password and create user
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! You can now <a href="login.php">login</a>.';
                // Clear form
                $_POST = array();
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Mobile Shop</title>
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
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 3px solid transparent;
            background-clip: padding-box;
            position: relative;
            overflow: hidden;
        }

        .register-container::before {
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
            pointer-events: none; /* Allow clicks to pass through */
            z-index: -1; /* Put behind content */
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            padding: 12px 15px;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            transform: translateY(-2px);
        }

        .input-group-text {
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px 0 0 10px;
        }

        .btn-register {
            background: var(--gradient-primary);
            border: none;
            padding: 15px 0;
            font-weight: 600;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-register::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-register:hover::before {
            left: 100%;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .register-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-logo i {
            font-size: 4rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }

        .register-logo h2 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .register-logo p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .strength-weak { width: 30%; background: var(--gradient-danger); }
        .strength-medium { width: 60%; background: var(--gradient-warning); }
        .strength-strong { width: 100%; background: var(--gradient-success); }

        .alert {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .form-text {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .text-muted {
            color: #6c757d !important;
        }

        a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
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

        .register-container {
            animation: fadeInUp 0.6s ease-out;
        }

        .input-group {
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            transform: translateY(-2px);
        }

        /* Ensure buttons are clickable */
        #goToLoginBtn, #signInLink {
            position: relative;
            z-index: 10;
            pointer-events: auto !important;
            cursor: pointer !important;
            display: inline-block !important;
        }

        #goToLoginBtn {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 600;
        }

        #goToLoginBtn:hover {
            background: var(--gradient-primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        #signInLink {
            color: var(--primary-color) !important;
            text-decoration: underline !important;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        #signInLink:hover {
            color: var(--secondary-color) !important;
            text-decoration: underline !important;
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-logo">
                <i class="fas fa-mobile-alt"></i>
                <h2 class="mt-2">Create Customer Account</h2>
                <p class="text-muted">Join our mobile shop community as a customer</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="post" action="" id="registerForm">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    <small class="form-text text-muted">We'll use this to contact you about your orders</small>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                    <small class="form-text text-muted">Password must be at least 6 characters long</small>
                </div>
                
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div id="passwordMatch" class="form-text"></div>
                </div>
                
                
                <button type="submit" class="btn btn-primary w-100 btn-register">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>
            
            <div class="text-center mt-3">
                <p class="mb-0">Already have an account? <a href="login.php" id="signInLink" style="cursor: pointer; text-decoration: underline;">Sign in</a></p>
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="goToLoginBtn">
                    <i class="fas fa-sign-in-alt me-1"></i>Go to Login Page
                </button>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        const password = document.getElementById('password');
        const passwordStrength = document.getElementById('passwordStrength');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('passwordMatch');
        
        password.addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            updatePasswordStrengthIndicator(strength);
        });
        
        confirmPassword.addEventListener('input', function() {
            if (this.value !== password.value) {
                passwordMatch.textContent = 'Passwords do not match';
                passwordMatch.style.color = '#dc3545';
            } else {
                passwordMatch.textContent = 'Passwords match';
                passwordMatch.style.color = '#28a745';
            }
        });
        
        function checkPasswordStrength(password) {
            // Check password length
            if (password.length === 0) return '';
            if (password.length < 6) return 'weak';
            
            // Check for numbers, letters (both cases), and special characters
            const hasNumber = /[0-9]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasUpper = /[A-Z]/.test(password);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            
            const strength = [hasNumber, hasLower, hasUpper, hasSpecial].filter(Boolean).length;
            
            if (strength <= 2) return 'weak';
            if (strength === 3) return 'medium';
            return 'strong';
        }
        
        function updatePasswordStrengthIndicator(strength) {
            passwordStrength.className = 'password-strength';
            
            if (strength === '') {
                passwordStrength.style.display = 'none';
                return;
            }
            
            passwordStrength.style.display = 'block';
            passwordStrength.className = 'password-strength strength-' + strength;
        }
        
        // Initialize
        updatePasswordStrengthIndicator('');
        
        // Ensure all links and buttons work properly
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking for sign in elements...');
            
            // Handle the "Go to Login Page" button
            const goToLoginBtn = document.getElementById('goToLoginBtn');
            if (goToLoginBtn) {
                console.log('Go to Login button found:', goToLoginBtn);
                console.log('Button styles:', window.getComputedStyle(goToLoginBtn));
                console.log('Button position:', goToLoginBtn.getBoundingClientRect());
                
                // Force button to be clickable
                goToLoginBtn.style.position = 'relative';
                goToLoginBtn.style.zIndex = '999';
                goToLoginBtn.style.pointerEvents = 'auto';
                goToLoginBtn.style.cursor = 'pointer';
                goToLoginBtn.style.display = 'inline-block';
                goToLoginBtn.style.opacity = '1';
                goToLoginBtn.style.visibility = 'visible';
                
                
                goToLoginBtn.addEventListener('click', function(e) {
                    console.log('Go to Login button clicked!');
                    console.log('Click event:', e);
                    console.log('Button element:', this);
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Force navigation to login page
                    window.location.href = 'login.php';
                });
                
                // Also add onclick as backup
                goToLoginBtn.onclick = function(e) {
                    console.log('Go to Login button onclick triggered!');
                    e.preventDefault();
                    e.stopPropagation();
                    window.location.href = 'login.php';
                };
                
                // Test if button is actually clickable
                goToLoginBtn.addEventListener('mousedown', function() {
                    console.log('Button mousedown detected!');
                });
                
                goToLoginBtn.addEventListener('mouseup', function() {
                    console.log('Button mouseup detected!');
                });
                
                console.log('Go to Login button configured successfully');
            } else {
                console.error('Go to Login button not found!');
            }
            
            // Handle the "Sign in" link
            const loginLink = document.querySelector('a[href="login.php"]');
            const signInLink = document.getElementById('signInLink');
            const allLinks = document.querySelectorAll('a');
            
            console.log('All links found:', allLinks.length);
            console.log('Login link by href:', loginLink);
            console.log('Sign in link by ID:', signInLink);
            
            // Try both methods
            const targetLink = loginLink || signInLink;
            
            if (targetLink) {
                console.log('Sign in link found:', targetLink);
                console.log('Link href:', targetLink.href);
                console.log('Link styles:', window.getComputedStyle(targetLink));
                console.log('Link position:', targetLink.getBoundingClientRect());
                
                // Force link to be clickable
                targetLink.style.position = 'relative';
                targetLink.style.zIndex = '999';
                targetLink.style.pointerEvents = 'auto';
                targetLink.style.cursor = 'pointer';
                targetLink.style.display = 'inline';
                targetLink.style.opacity = '1';
                targetLink.style.visibility = 'visible';
                targetLink.style.textDecoration = 'underline';
                targetLink.style.color = 'var(--primary-color)';
                
                
                // Remove any existing event listeners and add new one
                targetLink.onclick = function(e) {
                    console.log('Sign in link clicked!');
                    console.log('Event:', e);
                    console.log('Target:', e.target);
                    console.log('Current href:', this.href);
                    
                    // Force navigation
                    window.location.href = 'login.php';
                    return false; // Prevent default behavior issues
                };
                
                // Also add click event listener
                targetLink.addEventListener('click', function(e) {
                    console.log('Sign in link clicked via addEventListener!');
                    e.preventDefault();
                    window.location.href = 'login.php';
                });
                
                // Test if link is actually clickable
                targetLink.addEventListener('mousedown', function() {
                    console.log('Sign in link mousedown detected!');
                });
                
                targetLink.addEventListener('mouseup', function() {
                    console.log('Sign in link mouseup detected!');
                });
                
                console.log('Sign in link configured successfully');
            } else {
                console.error('Sign in link not found!');
                console.log('Available links:', Array.from(allLinks).map(link => ({
                    href: link.href,
                    text: link.textContent,
                    id: link.id
                })));
            }
            
            // Ensure form submission works
            const form = document.getElementById('registerForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('Form submitted');
                    // Let form submit naturally
                });
            }
        });
    </script>
</body>
</html>
