# Admin Quick Reference Guide

## 🔐 Login Credentials
- **URL**: `admin_login.php`
- **Email**: admin@walbrandproperties.com
- **Default Password**: Contact system administrator

## 📊 Dashboard Overview
When you log in, you'll see:
- **Total Users**: Number of registered platform users
- **Pending KYC**: Users waiting for identity verification
- **Total Properties**: All property listings on platform
- **Pending Verification**: Properties awaiting approval
- **Verified Properties**: Approved property listings
- **Total Revenue**: All completed transactions
- **Pending Consultations**: Unresolved customer consultations
- **Pending Documents**: Pending ownership document verification

## 👥 User Management (`admin_users.php`)

### Key Functions
- ✅ Verify KYC (approve user identity)
- ✅ Suspend account (temporary disable)
- ✅ Activate account (re-enable suspended account)
- ✅ View user details (profile information)
- ✅ Filter by status, type, KYC status

### User Statuses
- **Active**: Fully verified user
- **Pending Verification**: Awaiting KYC approval
- **Suspended**: Account temporarily disabled
- **Inactive**: User account not activated

### User Types
- **Buyer**: Searching for properties
- **Seller**: Listing properties for sale
- **Agent**: Real estate professional

### Quick Actions
1. Find user card
2. Click appropriate action button
3. Confirm action in modal
4. Action is logged automatically

## 🏠 Property Management (`admin_properties.php`)

### Verification Workflow
1. Property listed by seller
2. Admin reviews property details
3. Check documents and images
4. Approve or reject property
5. Seller notified of decision

### Required Documents
- Title deed
- Survey map
- Property photos (3+ minimum)
- Ownership proof (if agent/seller)

### Rejection Reasons
- **Invalid documents**: Papers not clear or illegible
- **Incomplete information**: Missing required details
- **Suspicious listing**: Potential fraud indicators
- **Non-compliant property**: Doesn't meet standards
- **Policy violation**: Violates platform rules

## 💰 Commission Structure
Default rates configured in Settings:
- **Standard Commission**: 5% per transaction
- **Minimum Commission**: 5,000 KES
- **Agent Commission**: 10% for registered agents
- **Referral Bonus**: 1,000 KES per successful referral

## 📋 Audit Logs (`admin_audit_logs.php`)

### What Gets Logged
- User KYC verifications
- Property approvals/rejections
- Account suspensions
- Admin settings changes
- All admin logins/logouts

### How to Review
1. Go to Audit Logs
2. Filter by date or action type
3. Click on log entry for details
4. Note IP address and timestamp

### Important Dates to Check
- Daily: Check for suspicious activity
- Weekly: Review summary of actions
- Monthly: Generate compliance report

## ⚙️ System Settings (`admin_settings.php`)

### Important Settings
- **Commission Rate**: Edit to change platform fees
- **Session Timeout**: How long before auto-logout
- **KYC Validity**: How often to re-verify users
- **Maintenance Mode**: Temporarily disable platform

### Tabs
1. **General Settings**: Basic platform config
2. **Commission**: Revenue and fee settings
3. **Verification**: KYC and property requirements
4. **Email Templates**: Notification messages
5. **Security**: Login and password policies
6. **Platform Info**: System status and versions

## 🔑 Key Shortcuts

### Navigation
- **Dashboard**: `admin_control_panel.php`
- **Users**: `admin_users.php`
- **Properties**: `admin_properties.php`
- **Logs**: `admin_audit_logs.php`
- **Settings**: `admin_settings.php`
- **Logout**: `admin_logout.php`

## ⚡ Common Tasks

### Task: Verify User KYC
```
1. Go to User Management
2. Find user with "Pending KYC"
3. Click "Verify KYC" button
4. Click "Confirm Verification"
5. User is now verified ✓
```

### Task: Reject Property
```
1. Go to Property Management
2. Find property with "Pending" status
3. Click "Reject" button
4. Enter rejection reason
5. Click "Reject Property"
6. Seller receives notification
```

### Task: Change Commission Rate
```
1. Go to Settings
2. Click "Commission Settings"
3. Update "Commission Rate (%)"
4. Click "Save Changes"
5. New rate applies to future transactions
```

### Task: Find Admin Action
```
1. Go to Audit Logs
2. Filter by date (optional)
3. Filter by action (optional)
4. Click "Filter" button
5. Review matching logs
```

## 📊 Report Types

### User Report
- Total active users
- Users by type (buyer/seller/agent)
- KYC verification rate
- User growth trend

### Property Report
- Total listings
- Verified vs pending
- Properties by location
- Average listing price

### Revenue Report
- Total transactions
- Commission collected
- Average transaction value
- Revenue trend

### Activity Report
- Admin actions
- Security events
- System changes
- User activities

## 🔒 Security Reminders

- ✅ Never share login credentials
- ✅ Always logout when done (button in top right)
- ✅ Session expires after 1 hour of inactivity
- ✅ All actions are logged and auditable
- ✅ Use strong password (12+ characters)
- ✅ Clear browser cache after logout
- ✅ Report suspicious activity immediately

## 📞 Support Contacts

**Quick Support Lines:**
- Email: support@walbrandproperties.com
- Phone: +254113906162
- Hours: 9 AM - 5 PM East African Time

**Common Issues:**
- **Can't login?** Check email spelling, verify account exists
- **Property won't verify?** Check all required documents
- **Commission not showing?** Wait 24 hours, then check again
- **Forgot password?** Contact support, provide identity proof

## 📈 Performance Tips

1. **Batch Operations**: Process multiple KYC verifications at once
2. **Filter Efficiently**: Use filters to narrow down data
3. **Check Logs Daily**: Catch issues early
4. **Schedule Reviews**: Set weekly audit log checks
5. **Update Settings Monthly**: Review and update policies

## 🎯 Best Practices

### Daily
- Review pending KYC verifications
- Process pending property listings
- Check audit logs for issues
- Respond to admin support requests

### Weekly
- Generate activity reports
- Review user complaints
- Check system performance
- Update commission rates if needed

### Monthly
- Review all admin actions (audit trail)
- Update security settings
- Generate compliance reports
- Archive old audit logs
- Review and update system configuration

## 📋 Checklists

### End of Day Checklist
- [ ] All pending verifications reviewed
- [ ] New properties approved/rejected
- [ ] Urgent issues addressed
- [ ] Logged out of admin panel

### Weekly Checklist
- [ ] Audit logs reviewed
- [ ] Settings verified correct
- [ ] Revenue reports checked
- [ ] User feedback reviewed
- [ ] System performance monitored

### Monthly Checklist
- [ ] Commission rates reviewed
- [ ] Verification policies updated
- [ ] Admin account access reviewed
- [ ] Compliance reported
- [ ] Backups verified

## 🔗 Important Links

- Main Website: `index.php`
- User Registration: `login_register.php`
- Admin Help: `ADMIN_SETUP.md`
- System Documentation: `WALBRAND_COMPLETE_DELIVERY.md`

## 💡 Pro Tips

1. **Speed Up Verification**: Use bulk approval for similar items
2. **Track Issues**: Note suspicious activity in logs
3. **Regular Audits**: Check logs every morning
4. **Secure Password**: Use passphrase with mixed characters
5. **Multiple Devices**: Don't use same device for multiple admins
6. **Document Changes**: Always note why settings were changed
7. **Test First**: Test setting changes before full rollout

---

**Last Updated**: 2024
**Version**: 2.0.0
**Support**: support@walbrandproperties.com
