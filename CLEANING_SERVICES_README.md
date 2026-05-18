# Walbrand Cleaning Services Marketplace

A comprehensive cleaning services marketplace built on the Walbrand Properties Marketplace platform, featuring real-time job dispatch, M-Pesa escrow payments, and mobile-first interfaces.

## Features

### 🏢 Admin Dashboard
- Real-time overview of requests, assignments, and revenue
- Manual and automatic job assignment
- Provider management and approval system
- Request filtering by location, budget, and property type

### 📱 Cleaner Mobile Dashboard
- PWA-ready mobile interface
- Real-time job notifications
- Online/offline status toggle
- Earnings tracking with escrow status

### 💳 M-Pesa Payment Integration
- STK Push payment initiation
- Escrow system for secure transactions
- Automatic fund release upon job completion
- Payment status polling

### 🔄 Real-Time Job Dispatch
- Instant job assignment notifications
- AJAX polling for updates
- WebSocket-ready architecture
- Prevent double assignments

### 📍 Nairobi Location Segmentation
- Categorized areas (High-End, Upper-Middle, etc.)
- Location-based filtering and matching

## Database Schema

### Core Tables
- `cleaning_requests` - Client service requests
- `service_providers` - Cleaner profiles
- `service_assignments` - Job assignments
- `cleaning_payments` - M-Pesa transactions
- `cleaning_escrow` - Held funds
- `provider_earnings` - Earnings tracking
- `location_areas` - Nairobi segmentation

### Setup
```sql
-- Run the cleaning_services_schema.sql file
-- Then insert initial data
```

## File Structure

```
/admin/
├── dashboard.php          # Admin dashboard
├── manage_requests.php    # Request management
└── assign_job.php         # Job assignment

/cleaner/
├── dashboard.php          # Mobile dashboard
├── login.php             # Provider login
├── jobs.php              # Job history
├── earnings.php          # Earnings tracker
└── profile.php           # Profile management

/api/
├── cleaning_requests.php  # Request details API
├── cleaning_providers.php # Provider list API
├── assign_job.php         # Job assignment API
├── job_action.php         # Cleaner actions API
├── cleaner_status.php     # Online status API
├── check_new_jobs.php     # New job notifications
└── payment_status.php     # Payment polling

/payments/
├── mpesa_stk.php          # STK Push initiation
└── callback.php           # M-Pesa callback handler

cleaning_request.php       # Client request form
cleaning_payment.php       # Payment page
```

## API Endpoints

### Admin APIs
- `GET /api/cleaning_requests?id={id}` - Get request details
- `GET /api/cleaning_providers?online=1` - Get online providers
- `POST /api/assign_job` - Assign job to provider

### Cleaner APIs
- `POST /api/job_action` - Accept/reject/complete jobs
- `POST /api/cleaner_status` - Toggle online status
- `GET /api/check_new_jobs` - Check for new assignments

### Payment APIs
- `POST /payments/mpesa_stk.php` - Initiate M-Pesa payment
- `POST /payments/callback.php` - M-Pesa callback handler
- `GET /api/payment_status?checkout_id={id}` - Check payment status

## M-Pesa Integration

### Setup
1. Register for M-Pesa Daraja API
2. Get consumer key, secret, shortcode, and passkey
3. Update credentials in `payments/mpesa_stk.php`
4. Set callback URL to `https://yourdomain.com/payments/callback.php`

### Flow
1. Client submits request
2. System initiates STK Push
3. Payment goes to escrow
4. Admin assigns job
5. Cleaner completes job
6. Funds released to cleaner

## Real-Time Features

### AJAX Polling
- Admin dashboard refreshes every 30 seconds
- Payment status polling every 5 seconds
- New job checks every 30 seconds

### WebSocket Ready
- Architecture supports WebSocket implementation
- Replace polling with WebSocket connections for better performance

## Security Features

- Prepared statements for all database queries
- Input sanitization and validation
- Session-based authentication
- Role-based access control
- Escrow system prevents premature fund release

## Mobile Optimization

- Bootstrap responsive design
- PWA-ready with service worker support
- Touch-friendly interface
- Offline-capable status toggle

## Installation

1. Run `cleaning_services_schema.sql` in your database
2. Update M-Pesa credentials in `payments/mpesa_stk.php`
3. Configure callback URL in M-Pesa dashboard
4. Access admin at `/admin/dashboard.php`
5. Access cleaner dashboard at `/cleaner/dashboard.php`

## Usage

### For Clients
1. Visit `cleaning_request.php`
2. Fill service request form
3. Complete M-Pesa payment
4. Wait for job assignment

### For Cleaners
1. Register and get approved by admin
2. Login to mobile dashboard
3. Toggle online status
4. Accept/reject/complete jobs

### For Admins
1. Login to admin dashboard
2. Monitor requests and assignments
3. Approve providers
4. Manage payments and escrow

## Future Enhancements

- WebSocket implementation for real-time updates
- Push notifications for mobile
- Advanced matching algorithms
- Rating and review system
- Multi-language support
- Analytics dashboard

## Support

For issues or questions, check the logs in `payments/mpesa_callback.log` for payment debugging.
```

**In `user_dashboard.php`:**
```html
<a href="../cleaning_services/pages/booking.php">Request Cleaning Service</a>
<a href="../cleaning_services/admin/index.php">Cleaning Management (Admin)</a>
```

---

## 📋 Database Schema

### cleaning_categories
```sql
- id (PK)
- name
- category_group (residential, commercial, specialized)
- description
- icon
- is_active
- created_at
```

**Pre-populated Categories:**
- Live-in House Help
- Live-out House Help
- Deep Cleaning Specialist
- Move-in / Move-out Cleaning
- Upholstery Cleaning
- Office Cleaning
- Post-Construction Cleaning
- Public Area Cleaning
- Trained House Help
- Caregiver Services

### cleaning_requests
```sql
- id (PK)
- user_id (FK) → users
- full_name
- phone, email
- location, location_area
- sqm, bedrooms, bathrooms
- service_types (JSON)
- preferred_date, preferred_time
- budget (minimum 5000 KES)
- notes
- status (pending, assigned, in_progress, completed, cancelled)
- assigned_provider_id (FK) → service_providers
- rating, review (after completion)
- created_at, updated_at
```

### service_providers
```sql
- id (PK)
- full_name, email, phone
- id_type, id_number (for verification)
- id_front_path, id_back_path (uploaded documents)
- profile_photo
- bio
- services (JSON array of service names)
- experience_years, experience_level
- location, service_areas (JSON)
- hourly_rate
- rating, total_reviews
- is_approved (admin approval required)
- background_check_status
- created_at, updated_at
```

### service_assignments
```sql
- id (PK)
- request_id (FK) → cleaning_requests
- provider_id (FK) → service_providers
- assigned_by_admin_id (FK) → users
- status (assigned, accepted, rejected, completed, cancelled)
- assignment_date, completion_date
- notes
```

### provider_reviews
```sql
- id (PK)
- request_id, provider_id, customer_id (FKs)
- rating (1-5)
- review_text
- punctuality_rating, quality_rating, professionalism_rating
- created_at, updated_at
```

---

## 🎯 API Endpoints

### Cleaning Requests API
**File:** `api/cleaning_requests_handler.php`

#### Create Request
```php
POST /api/cleaning_requests_handler.php
Parameters:
- action: 'create_request'
- full_name, phone, email
- location, location_area
- sqm, bedrooms, bathrooms
- service_types (array)
- preferred_date, preferred_time
- budget (≥ 5000)
- notes

Returns:
{
    "success": true,
    "message": "Request submitted successfully!",
    "data": {"request_id": 123}
}
```

#### Get User Requests
```php
GET /api/cleaning_requests_handler.php?action=get_user_requests
Returns: Array of user's cleaning requests
```

#### Submit Review
```php
POST /api/cleaning_requests_handler.php
Parameters:
- action: 'submit_review'
- request_id, provider_id
- rating (1-5)
- review_text
- punctuality_rating, quality_rating, professionalism_rating

Returns: Success/error response
```

### Service Providers API
**File:** `api/service_providers_handler.php`

#### Register Provider
```php
POST /api/service_providers_handler.php
Parameters:
- action: 'register_provider'
- full_name, email, phone
- id_type, id_number
- services (array)
- experience_years, experience_level
- location, service_areas (array)
- hourly_rate
- Files: id_front, id_back, profile_photo

Returns: Registration ID on success
```

#### Get Available Providers
```php
GET /api/service_providers_handler.php
Parameters:
- action: 'get_providers'
- service_type (optional)
- location (optional)
- budget (optional)

Returns: Array of approved providers matching criteria
```

#### Get Provider Profile
```php
GET /api/service_providers_handler.php?action=get_provider_profile&provider_id=123
Returns: Full provider profile with recent reviews
```

---

## 👥 User Flows

### Customer Flow (User)
1. **Login** to dashboard
2. **Click** "Book Cleaning" → `cleaning_services/pages/booking.php`
3. **Fill form:**
   - Personal information
   - Select location from categorized dropdown
   - Enter property details (sqm, bedrooms, bathrooms)
   - Select services needed
   - Choose date and time
   - Set budget (minimum 5000 KES)
4. **Submit** request
5. **Admin assigns** suitable provider
6. **Service provided** on agreed date
7. **Review** service provider after completion

### Service Provider Flow
1. **Visit** `cleaning_services/pages/provider_register.php`
2. **Submit registration** with:
   - Personal details
   - ID verification (front & back)
   - Professional information (experience, hourly rate)
   - Select services offered
   - Choose service areas
   - Upload profile photo
3. **Wait for admin** approval (24-48 hours)
4. **Receive assignments** from admin
5. **Complete jobs** and receive reviews
6. **Build reputation** through ratings and reviews

### Admin Flow
1. **Login** with admin credentials
2. **Visit** `cleaning_services/admin/index.php`
3. **View Dashboard:**
   - Total requests, pending, assigned, completed
   - Revenue potential
   - Average budget
4. **Manage Requests Tab:**
   - View all requests
   - Assign providers to pending requests
   - Filter by location, status
5. **Review Providers Tab:**
   - Approve/reject new provider applications
   - Verify documents and background
6. **Approved Providers Tab:**
   - View active providers
   - Check ratings and job history
   - Monitor performance

---

## 💰 Pricing Strategy

### Base Rates by Location Area

The system includes location multipliers for future pricing:

```php
High-End (Karen, Runda, etc.)          → 1.5x multiplier
Upper-Middle (Kilimani, Westlands, etc.) → 1.2x multiplier
Lower-Middle (Ruaka, Kasarani, etc.)    → 1.0x multiplier (base)
Eastlands (Eastleigh, Umoja, etc.)     → 0.9x multiplier
Other Areas                             → 1.0x multiplier
Satellite Towns (Tatu City, etc.)      → 1.1x multiplier
```

### Minimum Budget
- **Global minimum:** KES 5,000 per request
- Users cannot submit requests below this amount

### Provider Rates
- Each provider sets their own hourly rate
- Rates displayed in provider profiles
- Customers see rates before booking

---

## 🔐 Security Features

✓ **Prepared Statements** - All database queries use MySQLi prepared statements
✓ **Input Validation** - Frontend and backend validation
✓ **Authentication** - Requires user login for requests
✓ **Admin Authorization** - Admin functions protected with `require_admin()`
✓ **File Uploads** - Restricted to images, size-limited
✓ **Data Sanitization** - All inputs sanitized with `sanitize()` function
✓ **Email Verification** - Confirmation emails sent for requests and approvals
✓ **Background Checks** - Field for background check status on providers

---

## 📊 Admin Dashboard Features

### Statistics Cards
- **Total Requests** - All-time count
- **Pending** - Awaiting assignment
- **Assigned** - Provider assigned
- **Completed** - Job finished
- **Revenue Potential** - Sum of all budgets
- **Average Budget** - Mean request budget

### Request Management
- View all pending and assigned requests
- See customer details
- Assigned provider information
- Quick filters by location, status
- Assign new providers to requests

### Provider Management
- **Pending Approvals** - New registrations awaiting review
- View full provider profiles
- Check documents (ID front/back)
- View experience and services
- Approve or reject applications
- **Approved Providers** - Active service providers
  - View ratings and reviews
  - See job history
  - Monitor hourly rates

---

## 🎨 Frontend Pages

### 1. Booking Form (`cleaning_services/pages/booking.php`)
**For:** Customers requesting services
**Features:**
- Multi-step form with validation
- Location category selector
- Property details input
- Service multi-select with icons
- Date/time picker
- Budget validation
- Real-time form validation
- Mobile responsive design

### 2. Provider Registration (`cleaning_services/pages/provider_register.php`)
**For:** Cleaners, house helps, caregivers
**Features:**
- Comprehensive registration form
- ID verification upload
- Experience level selection
- Service selection (checkboxes)
- Service area selection
- Profile photo upload
- Drag-and-drop file support
- Email confirmation on submission

### 3. Admin Dashboard (`cleaning_services/admin/index.php`)
**For:** Platform administrators
**Features:**
- Statistics dashboard
- Tabbed interface (Requests, Providers, Approved)
- Request management with assignment
- Provider approval workflow
- Modal dialogs for actions
- Responsive data tables
- Real-time status updates

---

## 🗺️ Location Categories

The system pre-configures Nairobi locations for intelligent matching:

```php
High-End Areas:
  Karen, Runda, Muthaiga, Gigiri, Kitisuru, Lavington, 
  Riverside, Rosslyn, Nyari, Spring Valley, Kyuna

Upper-Middle Class:
  Kilimani, Kileleshwa, Westlands, Parklands, Ridgeways, 
  Woodley, Hurlingham, Lang'ata

Lower-Middle Class:
  Ruaka, Donholm, Kasarani, Imara Daima, Syokimau, 
  Utawala, Dagoretti, Buruburu, Roysambu

Eastlands:
  Eastleigh, Umoja, Kayole, Dandora, Kariobangi, 
  Komarock, Pangani

Other Areas:
  South B, South C, Ongata Rongai, Kikuyu, Ngong, 
  Ruaraka, Madaraka, Kahawa West, Kahawa Wendani

Satellite Towns:
  Tatu City, Kiambu Road Estates, Athi River, Kitengela
```

---

## 📧 Email Notifications

The system sends automated emails:

### Request Confirmation
Sent to customer after booking:
- Request ID
- Service details
- Booking date
- Expected timeline

### Admin Notification
Sent to admin when request submitted:
- Customer details
- Request details
- Link to admin panel for assignment

### Provider Registration Confirmation
Sent when provider applies:
- Registration ID
- Status (pending verification)
- Next steps

### Provider Approval
Sent when provider is approved:
- Approval confirmation
- Account activation
- How to accept assignments

---

## 🔄 Workflow Status Diagram

```
Request Creation
     ↓
Pending (awaiting admin assignment)
     ↓
Admin Assigns Provider
     ↓
Assigned (provider notified)
     ↓
In Progress (service date)
     ↓
Completed (work done)
     ↓
Review Period (customer rates provider)
     ↓
Closed

Or at any point:
     ↓
Cancelled (by user or admin)
```

---

## 🚀 Future Enhancements

1. **Payment Integration**
   - M-Pesa payment gateway
   - Invoice generation
   - Payment tracking

2. **Advanced Analytics**
   - Revenue reports
   - Provider performance metrics
   - Customer satisfaction trends
   - Seasonal demand analysis

3. **Automated Scheduling**
   - Time slot management
   - Google Calendar integration
   - SMS reminders

4. **Messaging System**
   - In-app chat between customer and provider
   - Notifications
   - Dispute resolution

5. **Machine Learning**
   - Provider recommendation engine
   - Price optimization
   - Demand forecasting

6. **Quality Assurance**
   - Photo uploads of completed work
   - Inspector verification
   - Quality scoring

---

## 📞 Support & Maintenance

### Database Backup
```sql
-- Backup cleaning requests
SELECT * INTO OUTFILE '/backup/cleaning_requests.csv'
FROM cleaning_requests;
```

### Common Queries

```sql
-- Find high-value requests
SELECT * FROM cleaning_requests 
WHERE budget >= 20000 
ORDER BY created_at DESC;

-- Top providers by rating
SELECT * FROM service_providers 
WHERE is_approved = TRUE 
ORDER BY rating DESC;

-- Completed requests this month
SELECT COUNT(*) FROM cleaning_requests 
WHERE status = 'completed' 
AND MONTH(created_at) = MONTH(NOW());
```

---

## 📝 License & Credits

Built for Walbrand Properties Marketplace & Interiors - Kenya Real Estate Marketplace
Technology Stack: PHP, MySQL, HTML5, CSS3, JavaScript

---

**Last Updated:** April 2026
**Version:** 1.0
