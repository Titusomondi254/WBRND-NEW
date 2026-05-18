<?php
require_once 'config.php';
require_once 'helpers.php';

secure_session_start();

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Email and password are required";
    } else {
        // Query the user account and admin role metadata
        $query = "SELECT u.id, u.first_name, u.last_name, u.name AS name, u.email, u.password_hash, u.password, u.is_active, u.status, u.user_type,
                         a.role AS admin_role, a.is_active AS admin_role_active
                  FROM users u
                  LEFT JOIN admin_users a ON u.id = a.user_id
                  WHERE u.email = ?
                  LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $is_super_admin = ($user['admin_role'] === 'super_admin' && intval($user['admin_role_active']) === 1 && intval($user['is_active']) === 1 && strtolower($user['status']) !== 'blocked');

            if (!$is_super_admin) {
                if (intval($user['is_active']) === 0 || strtolower($user['status']) === 'blocked') {
                    $error = "Your account is blocked. Contact the system administrator to request reactivation.";
                } else {
                    block_user_account(intval($user['id']), 'Attempted admin login');
                    $error = "Your account has been temporarily blocked because an admin login attempt was detected. Contact the administrator to reactivate your account.";
                }
            } else {
                $loginValid = false;
                if (!empty($user['password_hash'])) {
                    $loginValid = password_verify($password, $user['password_hash']);
                } elseif (!empty($user['password'])) {
                    $loginValid = password_verify($password, $user['password']);
                }

                if ($loginValid) {
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['user_id'] = $user['id']; // Also set user_id for compatibility
                    $_SESSION['admin_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['name'];
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['admin_role'] = $user['admin_role'];
                    $_SESSION['is_admin'] = true;
                    $_SESSION['user_role'] = 'super_admin';

                    $admin_user_id = null;
                    $admin_id_stmt = $conn->prepare("SELECT id FROM admin_users WHERE user_id = ? LIMIT 1");
                    if ($admin_id_stmt) {
                        $admin_id_stmt->bind_param("i", $user['id']);
                        $admin_id_stmt->execute();
                        $admin_id_result = $admin_id_stmt->get_result();
                        if ($admin_id_result && $admin_id_result->num_rows > 0) {
                            $admin_user_row = $admin_id_result->fetch_assoc();
                            $admin_user_id = $admin_user_row['id'];
                        }
                        $admin_id_stmt->close();
                    }

                    if ($admin_user_id) {
                        $_SESSION['admin_user_id'] = $admin_user_id;
                    }

                    try {
                        $log_query = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, created_at)
                                     VALUES (?, 'super_admin_login', 'admin_users', ?, ?, ?, NOW())";
                        $log_stmt = $conn->prepare($log_query);
                        if ($log_stmt) {
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $agent = $_SERVER['HTTP_USER_AGENT'];
                            $log_stmt->bind_param("iiss", $user['id'], $user['id'], $ip, $agent);
                            $log_stmt->execute();
                        }
                    } catch (Exception $e) {
                        error_log("Failed to log admin login: " . $e->getMessage());
                    }
                    
                    header("Location: admin_control_panel.php");
                    exit();
                } else {
                    $error = "Invalid email or password";
                }
            }
        } else {
            $error = "Access denied. Only Super Administrator can access this area.";
        }
    }
}

// Clear any corrupted admin sessions to prevent redirect loops
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    // Destroy the admin session so user can login fresh
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    session_start();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Walbrand Properties Marketplace & Interiors</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color:#eef4fb;
            --secondary-color: #5cfaff;
            --dark-color: #1a1a1a;
            --light-gray: #f8f9fa;
            --danger: #ef4444;
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Segoe UI', 'Roboto', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            background: #eef4fb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .login-header {
            background: #eef4fb;
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .login-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .login-header p {
            font-size: 0.95rem;
            opacity: 0.95;
        }

        .admin-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .login-form {
            padding: 3rem 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.6rem;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 1.5px rgba(255, 123, 0, 0.15);
        }

        .error-message {
            background-color: #fee2e2;
            border-left: 4px solid var(--danger);
            padding: 1rem;
            border-radius: 6px;
            color: #7f1d1d;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1.5rem;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .remember-me label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
            color: #555;
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: #eef4fb;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 123, 0, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-footer {
            padding: 2rem;
            background: var(--light-gray);
            text-align: center;
            font-size: 0.9rem;
            color: #666;
        }

        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .security-notice {
            background: #f0f9ff;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #0c4a6e;
        }

        .security-notice strong {
            display: block;
            margin-bottom: 0.4rem;
        }

        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }

            .login-header {
                padding: 2rem 1.5rem;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }

            .login-form {
                padding: 2rem 1.5rem;
            }
        }
    </style>
    <!-- Mobile Responsive CSS -->
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="admin-badge">ADMIN PORTAL</div>
            <h1>Walbrand Admin</h1>
            <p>Secure Administration Dashboard</p>
        </div>

        <div class="login-form">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="security-notice">
                <strong>Security Notice</strong>
                This is a secure admin portal. Only authorized administrators should have access to this page.
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Administrator Email</label>
                    <input type="email" id="email" name="email" placeholder="admin@walbrandproperties.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me for 7 days</label>
                </div>

                <button type="submit" name="login" class="login-btn">Login to Admin Panel</button>
            </form>
        </div>

        <div class="login-footer">
            <p>🏠 <a href="index.php">Back to Main Website</a></p>
            <p><a href="admin_setup/forgot_password.php">Forgot password?</a></p>
            <p><a href="admin_setup/index.php">Create admin account</a></p>
            <p style="margin-top: 1rem; color: #999; font-size: 0.85rem;">
                Lost access? Contact support: +254113906162
            </p>
        </div>
    </div>
</body>
</html>
