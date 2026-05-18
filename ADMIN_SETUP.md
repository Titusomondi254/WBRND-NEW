# Admin Control Panel - Setup & Documentation

## Overview
The Walbrand Properties Marketplace & Interiors admin control panel provides comprehensive platform management capabilities including user management, property verification, payment tracking, and system configuration.

## ✨ Core Features Implemented

### 1. **Admin Dashboard** (`admin_control_panel.php`)
Real-time platform statistics and overview:
- Total users count
- Pending KYC verifications
- Property statistics (total, pending, verified)
- Revenue tracking
- Pending consultations and documents
- Quick access to recent data

### 2. **User Management** (`admin_users.php`)
Complete user lifecycle management:
- Filter users by status, type, and KYC status
- Verify user KYC documentation
- Suspend/activate user accounts
- View detailed user profiles
- Track user registration dates
- Role assignment (buyer, seller, agent)

### 3. **Property Management** (`admin_properties.php`)
Property verification workflow:
- Review pending property listings
- Approve/reject properties with detailed feedback
- View property details and seller information
- Property image gallery support
- Track verification status
- Process property rejection with reasons

### 4. **System Audit Logs** (`admin_audit_logs.php`)
Complete activity tracking:
- Log all admin actions with timestamps
- Filter by date, action type, and admin
- View IP addresses and user agent information
- Pagination for easy navigation
- Export capability for compliance

### 5. **System Settings** (`admin_settings.php`)
Platform configuration management:
- General platform settings
- Commission rate configuration
- Property verification requirements
- KYC validation rules
- Email template management
- Security policy settings

### 6. **Authentication & Authorization** (`admin_auth.php`)
Secure session management:
- Session timeout (default 1 hour)
- Role-based access control
- Admin action logging
- IP tracking for security
- Session validation on each request

## 🔧 Database Schema Requirements

### Admin Users Table
```sql
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'super_admin', 'moderator') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);
```

### Admin Logs Table
```sql
CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    user_id INT,
    resource_id INT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (admin_id) REFERENCES admin_users(id)
);
```

## 📝 File Structure

```
WBRND/
├── admin_control_panel.php       # Main dashboard
├── admin_login.php               # Authentication page
├── admin_logout.php              # Logout handler
├── admin_auth.php                # Authentication middleware
├── admin_users.php               # User management
├── admin_properties.php           # Property verification
├── admin_settings.php            # System settings
├── admin_audit_logs.php          # Activity logs
├── admin_payments.php            # [Coming Soon]
├── admin_documents.php           # [Coming Soon]
├── admin_consultations.php       # [Coming Soon]
└── ADMIN_SETUP.md               # This file
```

## 🚀 Getting Started

### 1. **Create Admin User**
```php
// Execute in your database directly
INSERT INTO admin_users (name, email, password_hash, role) VALUES (
    'Admin User',
    'admin@walbrandproperties.com',
    '$2y$10$...', // bcrypt hash of your password
    'super_admin'
);
```

### 2. **Create Password Hash**
```php
// Use this PHP code to generate password hash
$password = 'your-secure-password';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
```

### 3. **Access Admin Panel**
- Navigate to: `http://yourdomain.com/admin_login.php`
- Login with admin email and password
- You'll be redirected to the dashboard

### 4. **Configure Settings**
- Go to Settings page
- Configure commission rates
- Set verification requirements
- Update security policies

## 🔐 Security Features

### Authentication
- ✅ Secure password hashing (bcrypt)
- ✅ Session management with timeout
- ✅ XSS protection (htmlspecialchars)
- ✅ SQL injection prevention (prepared statements)
- ✅ CSRF protection ready

### Authorization
- ✅ Role-based access control (RBAC)
- ✅ Session validation on every page
- ✅ Action logging for accountability
- ✅ IP address tracking
- ✅ User agent tracking

### Audit Trail
- ✅ All admin actions logged
- ✅ Timestamp for each action
- ✅ Admin identification
- ✅ Resource tracking
- ✅ Detailed action descriptions

## 📊 Admin Roles

### Super Admin
- Full access to all features
- Can manage other admins
- Can modify system settings
- Access to all reports

### Admin
- User management (verify KYC, suspend accounts)
- Property verification
- View audit logs
- Limited settings access

### Moderator
- View-only access to most features
- Can moderate user content
- Cannot modify system settings
- Limited reporting access

## 🎯 Common Admin Tasks

### Verify User KYC
1. Go to User Management
2. Find user with "Pending KYC" status
3. Click "Verify KYC" button
4. Confirm verification
5. User receives verification email

### Verify Property Listing
1. Go to Property Management
2. Find property with "Pending" status
3. Click "Review" or "View Full Details"
4. Verify all required documents
5. Click "Verify" or "Reject" with reason

### View System Activity
1. Go to Audit Logs
2. Filter by date, action, or admin (optional)
3. Review activity with timestamps
4. Check IP addresses for security

### Update Commission Rates
1. Go to Settings
2. Click "Commission Settings" menu
3. Update commission percentages
4. Save changes
5. All new transactions use new rates

## 🔄 Data Flow

```
User Registration
    ↓
Admin verifies KYC
    ↓
User status: Active
    ↓
User lists property
    ↓
Admin verifies property
    ↓
Property status: Verified
    ↓
Property visible on platform
    ↓
Property transaction
    ↓
Admin tracks payment
    ↓
Commission calculated
```

## 📈 Analytics & Reporting

The admin system provides insights into:
- User growth and activity
- Property listing trends
- Revenue and commissions
- KYC verification rates
- System performance metrics
- User engagement

Detailed analytics page coming soon with:
- Revenue charts
- User growth graphs
- Property metrics
- System performance data

## 🔧 Customization Guide

### Modify Dashboard Statistics
Edit `admin_control_panel.php` to add/modify stat cards:
```php
$result = $conn->query("SELECT COUNT(*) as count FROM your_table");
$stats['custom_stat'] = $result->fetch_assoc()['count'];
```

### Add New Admin Role
1. Update `admin_users` table ENUM
2. Modify role checks in `admin_auth.php`
3. Update permissions in functions

### Customize Email Templates
Edit the email template section in `admin_settings.php`:
1. Go to Email Templates
2. Select template to edit
3. Modify content and variables
4. Save changes

## 📱 Responsive Design
- ✅ Mobile-friendly interface
- ✅ Tablet optimized layout
- ✅ Desktop full features
- ✅ Touch-friendly buttons
- ✅ Flexible grid system

## 🐛 Troubleshooting

### Admin Login Not Working
- Check admin_users table has correct data
- Verify password hash is correct
- Check session is enabled in PHP
- Clear browser cookies

### Audit Logs Not Recording
- Ensure admin_logs table exists
- Check admin_id is valid
- Verify INSERT permissions on table
- Check database connection

### Dashboard Statistics Showing Zero
- Verify table names match your schema
- Check user/property records exist
- Verify SQL queries are correct
- Check database permissions

## 🗓️ Upcoming Features

- [ ] Admin Dashboard Analytics
- [ ] Payment Transaction Management
- [ ] Document Verification Workflow
- [ ] Consultation Management
- [ ] Bulk user operations
- [ ] Two-factor authentication
- [ ] Advanced reporting
- [ ] Email template builder
- [ ] Backup & restore
- [ ] API key management

## 📞 Support & Contact

For issues or questions:
- Email: support@walbrandproperties.com
- Phone: +254113906162

## 📄 API Documentation

The admin system uses prepared statements for security:

```php
// Example: Add new admin user
$stmt = $conn->prepare("INSERT INTO admin_users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $hash, $role);
$stmt->execute();
```

## 🎓 Best Practices

1. **Regular Audits**: Review audit logs weekly
2. **Strong Passwords**: Use 12+ characters with special chars
3. **Role Separation**: Use least privilege principle
4. **Backup Data**: Regularly backup audit logs
5. **Update Settings**: Review and update security settings quarterly
6. **Monitor Activity**: Watch for suspicious patterns
7. **Log Rotation**: Archive old logs periodically

## 📋 Checklist for Setup

- [ ] Create admin_users table
- [ ] Create admin_logs table  
- [ ] Create first admin user
- [ ] Test admin login
- [ ] Configure commission rates
- [ ] Set verification requirements
- [ ] Review security settings
- [ ] Test user verification workflow
- [ ] Test property verification workflow
- [ ] Check audit logs recording

## License
Walbrand Properties Marketplace & Interiors Admin System © 2024
