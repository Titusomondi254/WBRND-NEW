<?php
/**
 * Admin System API Reference & Documentation
 * 
 * This file documents all the helper functions and APIs available
 * in the admin authentication and logging system.
 */
?>

# Admin System API Reference

## 📚 Available Functions

All these functions are available in `admin_auth.php` after including it.

### Authentication Functions

#### `hasAdminRole($required_role = null)`
Check if current admin has a specific role.

**Parameters:**
- `$required_role` (string|null): Role to check (null = any admin)

**Returns:**
- `boolean`: true if admin has role or is super_admin

**Example:**
```php
if(hasAdminRole('super_admin')) {
    // Only super admins can access
}
```

**Valid Roles:**
- `admin` - Standard admin access
- `moderator` - Limited access
- `super_admin` - Full access (all roles pass)

---

#### `requireAdminRole($required_role)`
Require specific role or terminate.

**Parameters:**
- `$required_role` (string): Required role

**Returns:**
- void (terminates if no permission)

**Example:**
```php
requireAdminRole('super_admin');
// Code below only runs if user is super_admin
```

---

#### `getAdminInfo()`
Get current admin's information.

**Parameters:**
- None

**Returns:**
- Array with keys: id, name, email, role

**Example:**
```php
$admin = getAdminInfo();
echo "Welcome, " . $admin['name']; // Welcome, John Doe
```

---

### Logging Functions

#### `logAdminAction($action, $details = '', $user_id = null, $resource_id = null)`
Log an admin action to the database.

**Parameters:**
- `$action` (string, required): Action name (e.g., 'verify_kyc', 'suspend_account')
- `$details` (string): Additional details about the action
- `$user_id` (int): Related user ID if applicable
- `$resource_id` (int): Related resource ID (property, document, etc)

**Returns:**
- `boolean`: true if logged successfully

**Example:**
```php
logAdminAction('verify_kyc', 'Verified user KYC', 123, null);
logAdminAction('reject_property', 'Invalid documents', null, 456);
logAdminAction('update_settings', 'Changed commission to 6%');
```

**Common Actions:**
- `verify_kyc` - User KYC verification
- `reject_kyc` - User KYC rejection
- `verify_property` - Property listing approval
- `reject_property` - Property listing rejection
- `suspend_account` - Account suspension
- `activate_account` - Account reactivation
- `assign_role` - Role assignment
- `update_settings` - Settings modification
- `admin_login` - Admin login
- `admin_logout` - Admin logout

---

## 🔧 Database Operations

### Common Database Queries

#### Get All Pending KYC Verifications
```php
$result = $conn->query("
    SELECT id, name, email, phone, status 
    FROM users 
    WHERE kyc_verified = FALSE AND status = 'pending_verification'
    ORDER BY created_at DESC
");
```

#### Get All Pending Properties
```php
$result = $conn->query("
    SELECT p.*, u.name as seller_name, u.email as seller_email
    FROM properties p
    JOIN users u ON p.seller_id = u.id
    WHERE p.verification_status = 'pending_verification'
    ORDER BY p.created_at DESC
");
```

#### Get Admin Action Logs
```php
$stmt = $conn->prepare("
    SELECT al.*, a.name as admin_name
    FROM admin_logs al
    LEFT JOIN admin_users a ON al.admin_id = a.id
    WHERE DATE(al.created_at) = ?
    ORDER BY al.created_at DESC
");
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();
```

#### Verify User KYC
```php
$stmt = $conn->prepare("
    UPDATE users 
    SET kyc_verified = TRUE, status = 'active' 
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
logAdminAction('verify_kyc', 'KYC verified', $user_id);
```

#### Verify Property
```php
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("
    UPDATE properties 
    SET verification_status = 'verified', 
        verified_by = ?, 
        verified_at = NOW() 
    WHERE id = ?
");
$stmt->bind_param("ii", $admin_id, $property_id);
$stmt->execute();
logAdminAction('verify_property', 'Property verified', null, $property_id);
```

---

## 🔐 Session Management

### Session Variables Available
After login, these become available:
```php
$_SESSION['admin_id']      // Admin user ID (int)
$_SESSION['admin_name']    // Admin full name (string)
$_SESSION['admin_email']   // Admin email (string)
$_SESSION['admin_role']    // Admin role (string)
$_SESSION['is_admin']      // Admin flag (boolean)
$_SESSION['last_activity'] // Last activity timestamp (int)
```

### Check Session Status
```php
// Check if admin is logged in
if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    // User is logged in
}

// Get admin name
$admin_name = $_SESSION['admin_name'] ?? 'Unknown';

// Get admin ID
$admin_id = $_SESSION['admin_id'];
```

---

## 📊 Prepared Statements Examples

### Example 1: Verify User
```php
$user_id = 123;
$status = 'active';

$stmt = $conn->prepare("UPDATE users SET status = ?, kyc_verified = TRUE WHERE id = ?");
$stmt->bind_param("si", $status, $user_id);
if($stmt->execute()) {
    logAdminAction('verify_kyc', "Verified user ID: $user_id", $user_id);
}
```

### Example 2: Suspend Account
```php
$user_id = 456;
$reason = "Policy violation";

$stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
$stmt->bind_param("i", $user_id);
if($stmt->execute()) {
    logAdminAction('suspend_account', "Reason: $reason", $user_id);
}
```

### Example 3: Search Users
```php
$search_email = "user@example.com";

$stmt = $conn->prepare("
    SELECT id, name, email, status, kyc_verified FROM users 
    WHERE email LIKE ? 
    LIMIT 10
");
$search = "%$search_email%";
$stmt->bind_param("s", $search);
$stmt->execute();
$results = $stmt->get_result();

while($row = $results->fetch_assoc()) {
    echo "Found: " . $row['name'];
}
```

---

## 🎯 Integration Examples

### Integrate with Custom Page

Create a new admin page:

```php
<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php'; // Include this!

// Now you can use all admin functions

// Check permission (optional)
requireAdminRole('super_admin');

// Get admin info
$admin = getAdminInfo();
echo "Admin: " . htmlspecialchars($admin['name']);

// Log an action
if($some_action) {
    logAdminAction('custom_action', 'Description here');
}

// Use database
$result = $conn->query("SELECT * FROM users LIMIT 10");
?>
```

### Add New Admin Function

Add to `admin_auth.php`:

```php
// Function to get user statistics
function getUserStatistics() {
    global $conn;
    
    return [
        'total' => $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'],
        'verified' => $conn->query("SELECT COUNT(*) as c FROM users WHERE kyc_verified = TRUE")->fetch_assoc()['c'],
        'pending' => $conn->query("SELECT COUNT(*) as c FROM users WHERE kyc_verified = FALSE")->fetch_assoc()['c'],
    ];
}

// Usage:
$stats = getUserStatistics();
echo "Total: " . $stats['total'];
```

---

## 🛡️ Security Best Practices

### Always Use Prepared Statements
❌ WRONG:
```php
$result = $conn->query("SELECT * FROM users WHERE id = " . $_GET['id']);
```

✅ RIGHT:
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_GET['id']);
$stmt->execute();
$result = $stmt->get_result();
```

### Always Escape Output
❌ WRONG:
```php
echo $_SESSION['admin_name']; // XSS vulnerability!
```

✅ RIGHT:
```php
echo htmlspecialchars($_SESSION['admin_name']);
```

### Always Log Important Actions
```php
// Log when important things happen
logAdminAction('verify_kyc', 'User: ' . $user_id, $user_id);
logAdminAction('settings_change', 'Commission: 5% -> 6%');
```

### Always Check Roles
```php
// Check role before allowing action
if(!hasAdminRole('super_admin')) {
    http_response_code(403);
    die('Access denied');
}
```

---

## 🐛 Common Issues & Solutions

### Issue: Session Expires Too Quickly
**Solution:** Adjust timeout in admin_auth.php
```php
$session_timeout = 3600; // Change to higher value (in seconds)
```

### Issue: Logs Not Recording
**Solution:** Check admin_logs table exists
```sql
-- Verify table exists
DESCRIBE admin_logs;

-- Create if missing
CREATE TABLE admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(255),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Issue: Can't Verify User
**Solution:** Check user record exists
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
if($stmt->get_result()->num_rows === 0) {
    error_log("User not found: $user_id");
}
```

### Issue: Permission Denied
**Solution:** Check admin_users table and roles
```php
// Verify admin user exists
$stmt = $conn->prepare("SELECT role FROM admin_users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows === 0) {
    session_destroy();
    header("Location: admin_login.php");
}
```

---

## 📈 Performance Tips

### Optimize Database Queries
```php
// Use indexes
CREATE INDEX idx_status ON users(status);
CREATE INDEX idx_kyc ON users(kyc_verified);

// Use LIMIT
$result = $conn->query("SELECT * FROM users LIMIT 1000");

// Use specific columns
$result = $conn->query("SELECT id, name, email FROM users");
```

### Cache Frequently Used Data
```php
// Cache admin info
if(!isset($_SESSION['admin_info_cache'])) {
    $_SESSION['admin_info_cache'] = getAdminInfo();
}
$admin = $_SESSION['admin_info_cache'];
```

### Batch Operations
```php
// Update multiple records
$ids = [1, 2, 3, 4, 5];
$ids_string = implode(',', $ids);
$conn->query("UPDATE users SET status = 'active' WHERE id IN ($ids_string)");
```

---

## 📞 API Support

For issues or questions about the admin API:
- Email: support@walbrandproperties.com
- Reference: Admin System v2.0.0

---

**Last Updated:** 2024
**Version:** 2.0.0
**Compatibility:** PHP 7.4+, MySQL 5.7+
