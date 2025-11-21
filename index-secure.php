<?php
/**
 * FOS-Streaming Secure Login Page
 * Updated for PHP 8.4 with modern security practices
 *
 * Security features:
 * - Password hashing with Argon2id
 * - CSRF protection
 * - Rate limiting
 * - Security logging
 * - Input validation
 *
 * Date: 2025-11-21
 */

// Load configuration and security libraries
require_once 'config.php';
require_once 'lib/Security.php';
require_once 'lib/Validator.php';
require_once 'lib/SecurityLogger.php';

use FOS\Security\Security;
use FOS\Security\Validator;
use FOS\Security\SecurityLogger;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$rateLimitExceeded = false;

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    // Get client IP
    $clientIP = Security::getClientIP();
    $rateLimitKey = 'login_' . $clientIP;

    // Check rate limiting (5 attempts per 15 minutes)
    if (Security::isRateLimited($rateLimitKey, 5, 900)) {
        $error = "Too many login attempts. Please try again in 15 minutes.";
        $rateLimitExceeded = true;
        SecurityLogger::logRateLimitExceeded($rateLimitKey, $clientIP);
    } else {

        // Validate CSRF token
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCSRFToken($csrfToken)) {
            $error = "Invalid security token. Please refresh and try again.";
            SecurityLogger::logCSRFFailure('login');
        } else {

            // Validate inputs
            if (empty($_POST['username']) || empty($_POST['password'])) {
                $error = "Username and Password are required";
                SecurityLogger::logAuthAttempt($_POST['username'] ?? 'empty', false, $clientIP, 'Empty credentials');
            } else {

                // Sanitize username
                $username = Security::cleanString($_POST['username'], 50);
                $password = $_POST['password']; // Don't sanitize password

                // Validate username format
                $usernameValidation = Validator::validateUsername($username);
                if (!$usernameValidation['valid']) {
                    $error = "Invalid username format";
                    SecurityLogger::logAuthAttempt($username, false, $clientIP, 'Invalid format');
                } else {

                    // Find user
                    try {
                        $admin = Admin::where('username', '=', $username)->first();

                        if ($admin) {
                            // Check if password is MD5 (legacy) or modern hash
                            $passwordValid = false;

                            // Check if hash starts with $2y$ (bcrypt) or $argon2 (argon2)
                            if (strpos($admin->password, '$2y$') === 0 ||
                                strpos($admin->password, '$argon2') === 0) {
                                // Modern password hash
                                $passwordValid = Security::verifyPassword($password, $admin->password);

                                // Check if rehash needed (upgrade to Argon2id)
                                if ($passwordValid && Security::needsRehash($admin->password)) {
                                    $admin->password = Security::hashPassword($password);
                                    $admin->save();
                                    SecurityLogger::logSecurityEvent('Password rehashed to Argon2id', [
                                        'username' => $username
                                    ]);
                                }
                            } else {
                                // Legacy MD5 hash - verify and upgrade
                                if ($admin->password === md5($password)) {
                                    $passwordValid = true;

                                    // Upgrade to Argon2id
                                    $admin->password = Security::hashPassword($password);
                                    $admin->save();

                                    SecurityLogger::logSecurityEvent('Password upgraded from MD5 to Argon2id', [
                                        'username' => $username
                                    ]);
                                }
                            }

                            if ($passwordValid) {
                                // Successful login

                                // Regenerate session ID to prevent session fixation
                                session_regenerate_id(true);

                                // Set session variables
                                $_SESSION['user_id'] = $admin->id;
                                $_SESSION['username'] = $admin->username;
                                $_SESSION['login_time'] = time();
                                $_SESSION['last_activity'] = time();
                                $_SESSION['ip_address'] = $clientIP;

                                // Reset rate limiting for this IP
                                Security::resetRateLimit($rateLimitKey);

                                // Log successful login
                                SecurityLogger::logAuthAttempt($username, true, $clientIP);

                                // Redirect to dashboard
                                header("Location: dashboard.php");
                                exit;
                            } else {
                                // Invalid password
                                $error = "Invalid username or password";
                                SecurityLogger::logAuthAttempt($username, false, $clientIP, 'Invalid password');
                            }
                        } else {
                            // User not found
                            $error = "Invalid username or password";
                            SecurityLogger::logAuthAttempt($username, false, $clientIP, 'User not found');
                        }
                    } catch (Exception $e) {
                        // Database error
                        $error = "An error occurred. Please try again later.";
                        SecurityLogger::logSecurityEvent('Login error', [
                            'username' => $username,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
    }
}

// Generate CSRF token for form
$csrfToken = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!-- Meta, title, CSS, favicons, etc. -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>FOS-Streaming Panel - Secure Login</title>

    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="fonts/css/font-awesome.min.css" rel="stylesheet">
    <link href="css/animate.min.css" rel="stylesheet">

    <!-- Custom styling plus plugins -->
    <link href="css/custom.css" rel="stylesheet">
    <link href="css/icheck/flat/green.css" rel="stylesheet">

    <script src="js/jquery.min.js"></script>

    <!-- Security headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">

    <style>
        .security-badge {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            opacity: 0.7;
        }

        .rate-limit-warning {
            background-color: #ff4444;
            color: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
    </style>
</head>

<body style="background:#F7F7F7;">

<div class="">
    <a class="hiddenanchor" id="toregister"></a>
    <a class="hiddenanchor" id="tologin"></a>

    <div id="wrapper">
        <div id="login" class="animate form">
            <?php if ($error != ""): ?>
                <div class="alert <?php echo $rateLimitExceeded ? 'rate-limit-warning' : 'alert-error'; ?>">
                    <?php echo Security::escape($error); ?>
                </div>
            <?php endif; ?>

            <section class="login_content">
                <form action="" method="post">
                    <h1>FOS-Streaming</h1>

                    <?php if ($rateLimitExceeded): ?>
                        <p style="color: #d9534f; margin-bottom: 15px;">
                            <i class="fa fa-lock"></i> Account temporarily locked for security
                        </p>
                    <?php endif; ?>

                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrfToken); ?>">

                    <div>
                        <input type="text"
                               name="username"
                               class="form-control"
                               placeholder="Username"
                               required
                               autocomplete="username"
                               maxlength="50"
                               <?php echo $rateLimitExceeded ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <input type="password"
                               name="password"
                               class="form-control"
                               placeholder="Password"
                               required
                               autocomplete="current-password"
                               <?php echo $rateLimitExceeded ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <input type="submit"
                               name="submit"
                               class="btn btn-default submit"
                               value="<?php echo $rateLimitExceeded ? 'Locked' : 'Log in'; ?>"
                               <?php echo $rateLimitExceeded ? 'disabled' : ''; ?>>
                    </div>
                    <div class="clearfix"></div>
                    <div class="separator">
                        <div class="clearfix"></div>
                        <br/>
                        <div>
                            <p style="font-size: 11px; color: #666;">
                                <i class="fa fa-shield"></i> Secured with Argon2id encryption
                            </p>
                            <p>&copy;2025 All Rights Reserved
                                <a href="https://github.com/theraw/FOS-Streaming-v70" target="_blank" rel="noopener">
                                    FOS-Streaming
                                </a>
                            </p>
                        </div>
                    </div>
                </form>
                <!-- form -->
            </section>
            <!-- content -->
        </div>
    </div>
</div>

<div class="security-badge">
    <i class="fa fa-lock"></i> PHP <?php echo PHP_VERSION; ?> | Secure
</div>

</body>

</html>
