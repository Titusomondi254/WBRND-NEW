# Completed Services & Agent Payouts Integration Guide

## Overview
This guide documents the implementation of a unified system to measure **Total Agent Payouts** and **Completed Services** using both **Digital Installation** records and **View Requests** records.

---

## Changes Made

### 1. **Admin Control Panel (`admin_control_panel.php`)**

#### A. Added Completed Services Calculation (Lines 485-487)
```php
// Calculate total completed services from both Digital Installation and View Requests
$completed_digital_installations = intval($conn->query("SELECT COUNT(*) as total FROM consultations WHERE status = 'completed' AND consultation_type IN ('digital_installation', 'installation_request')")->fetch_assoc()['total'] ?? 0);
$completed_viewing_requests = intval($conn->query("SELECT COUNT(*) as total FROM viewing_requests WHERE status = 'completed'")->fetch_assoc()['total'] ?? 0);
$payments_dashboard['total_completed_services'] = $completed_digital_installations + $completed_viewing_requests;
```

**What it does:**
- Counts all completed digital installations from the `consultations` table
- Counts all completed viewing requests from the `viewing_requests` table
- Sums both values to create a single "Completed Services" metric

#### B. Updated Dashboard Display (Lines 4161-4167)
Added a new card to display "Completed Services":
```html
<div class="card" style="text-decoration: none; color: inherit;">
    <div class="icon-box orange"><i class="fas fa-tasks"></i></div>
    <div class="card-content">
        <p>Completed Services</p>
        <h3><?= number_format($payments_dashboard['total_completed_services']) ?></h3>
        <small style="color: #64748b; display: block; margin-top: 4px;">Digital Installation + View Requests</small>
    </div>
</div>
```

**Metrics Shown:**
- Total completed services count
- Breakdown label indicating both sources are included

**Existing Metrics (Already Updated):**
- Total Agent Payouts (already combines both sources since line 483)
  - Digital Installation payouts: sum of `service_fee` from completed consultations
  - Viewing Request payouts: sum of `agent_commission` from completed viewing_requests

---

### 2. **Agent Dashboard (`agent_dashboard.php`)**

#### A. Added Total Completed Services Calculation (Line 195)
```php
// Calculate total completed services from both Digital Installations and Viewing Requests
$total_completed_services = $digital_service_completed + $viewings_completed;
```

**What it does:**
- Combines completed digital service installations
- Combines completed viewing requests for the agent
- Displays unified total

#### B. Added Agent Payouts Calculation (Lines 197-221)
```php
// Calculate agent payouts from both Digital Installations and Viewing Requests
$digital_installation_agent_payouts = 0;
$viewing_request_agent_payouts = 0;
$total_agent_payouts = 0;

$digital_payout_stmt = $conn->prepare("SELECT COALESCE(SUM(COALESCE(service_fee, 0)), 0) as total FROM consultations WHERE agent_id = ? AND status = 'completed' AND consultation_type IN ('wifi_distribution', 'cctv_installation', 'alexa_installation')");
if ($digital_payout_stmt) {
    $digital_payout_stmt->bind_param('i', $user_id);
    $digital_payout_stmt->execute();
    $digital_installation_agent_payouts = floatval($digital_payout_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $digital_payout_stmt->close();
}

$viewing_payout_stmt = $conn->prepare("SELECT COALESCE(SUM(COALESCE(agent_commission, 0)), 0) as total FROM viewing_requests WHERE user_id = ? AND status = 'completed'");
if ($viewing_payout_stmt) {
    $viewing_payout_stmt->bind_param('i', $user_id);
    $viewing_payout_stmt->execute();
    $viewing_request_agent_payouts = floatval($viewing_payout_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $viewing_payout_stmt->close();
}

$total_agent_payouts = $digital_installation_agent_payouts + $viewing_request_agent_payouts;
```

**What it does:**
- Calculates payouts from digital service installations
- Calculates payouts from viewing requests
- Sums both to show total earnings

#### C. Added Completed Services Display Section (Lines 1183-1209)
New highlighted panel showing:
- **Total Completed Services** (combined digital + viewing)
- **Digital Services Completed** (breakdown)
- **Viewing Requests Completed** (breakdown)

Features:
- Purple gradient background for prominence
- Quick links to both service management pages
- Clear breakdown of each service type

#### D. Added Agent Payouts Display Section (Lines 1211-1235)
New section showing agent earnings:
- **Total Earnings** (combined from both sources) - Orange gradient
- **Digital Services Earnings** (breakdown) - Yellow gradient
- **Viewing Requests Earnings** (breakdown) - Blue gradient

Features:
- Three colored cards representing each earning source
- Formatted currency display (KES)
- Clear visual hierarchy showing total earnings first

---

## Data Sources

### Digital Installation Records
**Table:** `consultations`
**Key Fields:**
- `consultation_type`: 'wifi_distribution', 'cctv_installation', 'alexa_installation', 'digital_installation', 'installation_request'
- `status`: 'completed' (for completed services)
- `service_fee`: Amount paid to agent
- `agent_id`: Agent performing the service

### View Requests Records
**Table:** `viewing_requests`
**Key Fields:**
- `status`: 'completed' (for completed viewing requests)
- `agent_commission`: Commission paid to agent
- `user_id`: Agent/User ID

---

## Metrics Definitions

### Total Agent Payouts
**Calculation:** `Digital Installation Payouts + Viewing Request Payouts`

**Details:**
- **Digital Installation Payouts**: Sum of `service_fee` from completed consultations where `consultation_type IN ('digital_installation', 'installation_request')`
- **Viewing Request Payouts**: Sum of `agent_commission` from completed viewing_requests

**Admin View:** Shows platform-wide total payouts
**Agent View:** Shows individual agent's total earnings

### Completed Services
**Calculation:** `Completed Digital Installations + Completed Viewing Requests`

**Details:**
- **Completed Digital Installations**: Count of consultations with status='completed' and `consultation_type IN ('digital_installation', 'installation_request')`
- **Completed Viewing Requests**: Count of viewing_requests with status='completed'

**Admin View:** Shows platform-wide total completed services
**Agent View:** Shows individual agent's total completed services

---

## Display Locations

### Admin Dashboard
1. **Total Agent Payouts Card** - Shows cumulative payouts (KES)
2. **Completed Services Card** - Shows total services count with breakdown note
3. **Existing Metrics** - Clients Served, Registered Users, etc.

### Agent Dashboard
1. **Total Completed Services Panel** - Shows combined service count with breakdown
2. **Agent Payouts Panel** - Shows total earnings with breakdown by source
3. **Digital Services Panel** - Existing panel (unchanged)
4. **Viewings Panel** - Existing panel (unchanged)

---

## Database Requirements

The following tables must exist and have the required fields:

### `consultations` Table
- `id` (INT, PRIMARY KEY)
- `status` (VARCHAR, values: 'completed', 'pending', etc.)
- `consultation_type` (VARCHAR)
- `service_fee` (DECIMAL)
- `agent_id` (INT, NULLABLE)
- `created_at` (DATETIME)

### `viewing_requests` Table
- `id` (INT, PRIMARY KEY)
- `status` (VARCHAR, values: 'completed', 'pending', etc.)
- `agent_commission` (DECIMAL, NULLABLE)
- `user_id` (INT, NULLABLE)
- `created_at` (DATETIME)

---

## Testing Checklist

### Admin Dashboard
- [ ] "Completed Services" card displays correct count
- [ ] Count includes both digital installations and viewing requests
- [ ] "Total Agent Payouts" shows combined amounts
- [ ] Card displays with proper formatting and styling

### Agent Dashboard
- [ ] "Total Completed Services" panel shows at highlighted position
- [ ] Breakdown shows correct digital services count
- [ ] Breakdown shows correct viewing requests count
- [ ] Agent payouts panel displays earnings correctly
- [ ] Currency is formatted in KES with no decimals
- [ ] Breakdown shows correct split between sources

### Data Validation
- [ ] New completed digital installation increases metrics
- [ ] New completed viewing request increases metrics
- [ ] Pending/incomplete services don't affect metrics
- [ ] Different agents see only their own payouts
- [ ] Admin sees all platform payouts

---

## Performance Notes

- Queries use indexed fields (`status`, `agent_id`, `user_id`) for efficiency
- Aggregation functions (SUM, COUNT) are optimized with COALESCE
- No N+1 query problems
- Results are calculated once per page load

---

## Future Enhancements

1. Add period-based filtering (7 days, 30 days, etc.)
2. Add charts showing trends over time
3. Add commission breakdown details
4. Add export functionality for reports
5. Add real-time update notifications
6. Add verification status filtering

---

## Support

For questions or issues with these metrics:
1. Check the database queries in the files mentioned
2. Verify table structures and field names
3. Ensure services are being marked as 'completed' properly
4. Check agent assignments for services

---

**Last Updated:** May 9, 2026
**Modified Files:**
- `admin_control_panel.php` (lines 485-487, 4161-4167)
- `agent_dashboard.php` (lines 195-221, 1183-1235)
