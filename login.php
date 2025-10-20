<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require_once 'db.php';

// Initialize error message
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (empty($_POST['username']) || empty($_POST['password'])) {
        $error = 'Please enter both username and password';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        try {
            // Prepare and execute the query
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            if (!$stmt) {
                throw new Exception('Database query error: ' . $conn->error);
            }
            
            $stmt->bind_param("s", $username);
            if (!$stmt->execute()) {
                throw new Exception('Query execution failed: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = strtolower(trim($user['role']));
                    
                    // Determine redirect URL based on role
                    $role = strtolower(trim($user['role']));
                    
                    if ($role === 'customer') {
                        $redirect_path = 'customer/dashboard.php';
                    } else if ($role === 'admin') {
                        $redirect_path = 'admin/dashboard.php';
                    } else {
                        $redirect_path = 'index.php';
                    }
                    
                    // Redirect to appropriate dashboard
                    header("Location: " . $redirect_path);
                    exit();
                } else {
                    $error = 'Invalid username or password';
                }
            } else {
                $error = 'Invalid username or password';
            }
        } catch (Exception $e) {
            // Log the error (in a production environment, you'd log to a file)
            error_log('Login error: ' . $e->getMessage());
            $error = 'An error occurred during login. Please try again.';
        }
    } // Close else block
} // Close POST check


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mobile Shop</title>
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
        .login-container {
            max-width: 400px;
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

        .login-container::before {
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

        .btn-login {
            background: var(--gradient-primary);
            border: none;
            padding: 15px 0;
            font-weight: 600;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo i {
            font-size: 4rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }

        .login-logo h2 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-logo p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .alert {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
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

        .login-container {
            animation: fadeInUp 0.6s ease-out;
        }

        .input-group {
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            border-radius: 0 10px 10px 0;
            border: 2px solid #e9ecef;
            border-left: none;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: var(--gradient-primary);
            border-color: var(--primary-color);
            color: white;
        }

        /* Ensure all buttons and links are clickable */
        #loginButton, #togglePassword, a[href="register.php"] {
            position: relative;
            z-index: 10;
            pointer-events: auto !important;
            cursor: pointer !important;
            display: inline-block !important;
        }

        #loginButton {
            background: var(--gradient-primary);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        #loginButton:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        #togglePassword {
            background: transparent;
            border: 2px solid #e9ecef;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        #togglePassword:hover {
            background: var(--gradient-primary);
            border-color: var(--primary-color);
            color: white;
        }

        a[href="register.php"] {
            color: var(--primary-color) !important;
            text-decoration: underline !important;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        a[href="register.php"]:hover {
            color: var(--secondary-color) !important;
            text-decoration: underline !important;
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-logo">
                <i class="fas fa-mobile-alt"></i>
                <h2 class="mt-2">Mobile Shop</h2>
                <p class="text-muted">Sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="username" 
                            name="username" 
                            autocomplete="username"
                            required 
                            autofocus
                            aria-describedby="usernameHelp"
                        >
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            autocomplete="current-password"
                            required
                            aria-describedby="passwordHelp"
                        >
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-login" id="loginButton">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
                <div class="text-center mt-3">
                    <p class="mb-0">Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Enhanced login form handling with debugging
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Login page loaded, checking for elements...');
        
        // Get form elements
        const form = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const registerLink = document.querySelector('a[href="register.php"]');
        
        console.log('Form found:', !!form);
        console.log('Login button found:', !!loginButton);
        console.log('Toggle password found:', !!togglePassword);
        console.log('Register link found:', !!registerLink);
        
        // If elements don't exist, stop here
        if (!form || !loginButton) {
            console.error('Login form or button not found');
            return;
        }
        
        // Force all elements to be clickable
        [loginButton, togglePassword, registerLink].forEach((element, index) => {
            if (element) {
                console.log(`Element ${index + 1} found:`, element);
                console.log(`Element ${index + 1} styles:`, window.getComputedStyle(element));
                console.log(`Element ${index + 1} position:`, element.getBoundingClientRect());
                
                // Force clickable properties
                element.style.position = 'relative';
                element.style.zIndex = '999';
                element.style.pointerEvents = 'auto';
                element.style.cursor = 'pointer';
                element.style.display = 'inline-block';
                element.style.opacity = '1';
                element.style.visibility = 'visible';
                
                
                console.log(`Element ${index + 1} configured successfully`);
            }
        });
        
        // Toggle password visibility
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                console.log('Toggle password clicked!');
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            });
            
            // Test if button is actually clickable
            togglePassword.addEventListener('mousedown', function() {
                console.log('Toggle password mousedown detected!');
            });
            
            togglePassword.addEventListener('mouseup', function() {
                console.log('Toggle password mouseup detected!');
            });
        }
        
        // Handle register link
        if (registerLink) {
            registerLink.addEventListener('click', function(e) {
                console.log('Register link clicked!');
                // Let the link work naturally
            });
            
            registerLink.addEventListener('mousedown', function() {
                console.log('Register link mousedown detected!');
            });
            
            registerLink.addEventListener('mouseup', function() {
                console.log('Register link mouseup detected!');
            });
        }
        
        // Form submission
        form.onsubmit = function(e) {
            console.log('Form submission started');
            
            // Get form values
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            console.log('Username:', username);
            console.log('Password length:', password.length);
            
            // Simple validation
            if (!username || !password) {
                alert('Please enter both username and password');
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            loginButton.disabled = true;
            loginButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Logging in...';
            
            console.log('Form submitting...');
            
            // Allow form to submit naturally
            return true;
        };
        
        // Test login button click
        if (loginButton) {
            loginButton.addEventListener('click', function() {
                console.log('Login button clicked!');
            });
            
            loginButton.addEventListener('mousedown', function() {
                console.log('Login button mousedown detected!');
            });
            
            loginButton.addEventListener('mouseup', function() {
                console.log('Login button mouseup detected!');
            });
        }
        
        console.log('Login page configuration complete');
    });
    </script>
</body>
</html>
