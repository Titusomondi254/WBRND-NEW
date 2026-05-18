<?php
/**
 * Database Configuration File
 * Walbrand Properties & Interiors - Kenya Real Estate Marketplace
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'walbrand_properties');

// Site configuration
define('SITE_URL', 'http://localhost/WBRND/WBRND/');
define('SITE_NAME', 'Walbrand Properties & Interiors');
define('ADMIN_EMAIL', 'admin@walbrandproperties.com');
define('SUPPORT_PHONE', '+254113906162');

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID_HERE');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET_HERE');
define('GOOGLE_REDIRECT_URI', SITE_URL . 'google_oauth.php');

// File upload directories
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('PROPERTY_UPLOAD_DIR', UPLOAD_DIR . 'properties/');
define('KYC_UPLOAD_DIR', UPLOAD_DIR . 'kyc/');
define('PROFILE_UPLOAD_DIR', UPLOAD_DIR . 'profiles/');

// File upload limits (in bytes)
define('MAX_FILE_SIZE', 5242880); // 5MB
define('MAX_PROPERTY_IMAGES', 10);

// Allowed file types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Session settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('REMEMBER_ME_DURATION', 604800); // 7 days

// Enable mysqli exceptions so reconnect logic can catch server disconnects
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Create a new database connection.
 *
 * @return mysqli
 */
function create_db_connection() {
    $mysqli = mysqli_init();
    if (!$mysqli) {
        throw new Exception('MySQLi initialization failed');
    }

    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($mysqli->connect_error) {
        throw new Exception('Connection failed: ' . $mysqli->connect_error);
    }

    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

/**
 * Get the active database connection.
 * Reconnect automatically if the server has gone away.
 *
 * @return mysqli
 */
function get_db_connection() {
    static $conn = null;
    if ($conn instanceof mysqli) {
        if ($conn->ping()) {
            return $conn;
        }
        $conn->close();
    }

    $conn = create_db_connection();
    return $conn;
}

/**
 * Check whether a table contains a specific column.
 *
 * @param string $table
 * @param string $column
 * @return bool
 */
function column_exists($table, $column) {
    global $conn;
    try {
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && $result->num_rows > 0;
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

/**
 * Execute a database query, reconnecting if the MySQL server has gone away.
 *
 * @param string $sql
 * @return mysqli_result|false
 */
function db_query($sql) {
    global $conn;
    try {
        return $conn->query($sql);
    } catch (mysqli_sql_exception $e) {
        if (in_array($e->getCode(), [2006, 2013], true)) {
            $conn = get_db_connection();
            return $conn->query($sql);
        }
        throw $e;
    }
}

try {
    $conn = get_db_connection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Create required directories if they don't exist
$dirs = [UPLOAD_DIR, PROPERTY_UPLOAD_DIR, KYC_UPLOAD_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Utility functions - Note: sanitize() function moved to helpers.php to avoid conflicts
function sanitize_email($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

function validate_email($email) {
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_phone($phone) {
    // Kenya phone number validation
    return preg_match('/^(\+254|0)[1-9]\d{8}$/', trim($phone));
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function set_error($message) {
    $_SESSION['error_message'] = $message;
}

function get_error() {
    $error = $_SESSION['error_message'] ?? '';
    unset($_SESSION['error_message']);
    return $error;
}

// Include helpers for additional authentication and utility functions
if (!function_exists('sanitize')) {
    require_once __DIR__ . '/helpers.php';
}
?>
