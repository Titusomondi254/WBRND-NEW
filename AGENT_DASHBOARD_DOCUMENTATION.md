# Agent Dashboard - Complete Implementation Guide

## Overview
The Agent Dashboard is a comprehensive management system for agents and sellers to track their business metrics, communicate with admins, and manage their service offerings.

---

## Dashboard Sections

### 1. 📞 Leads Section
**Purpose:** Display all potential customers and their contact details  
**Features:**
- Shows up to 10 recent leads with names and contact information
- Displays consultation type and current status
- Status indicators: Pending, Completed, Cancelled, Scheduled
- Link to view all leads: "View All Leads" button

**Data Shown:**
- Lead Name
- Email & Phone Contact
- Consultation Type
- Current Status with color-coded tags

---

### 2. 🏠 Properties Section
**Purpose:** Track property management metrics  
**Statistics Displayed:**
- **Uploaded:** Total properties uploaded by the agent
- **Successful Connections:** Properties with completed transactions/connections
- **Rejected:** Properties that were rejected/not approved

**Calculations:**
- Uploaded: COUNT(properties.id) WHERE seller_id = agent_id
- Successful: COUNT(DISTINCT properties.id) WHERE status = 'completed' AND connected to consultations
- Rejected: COUNT(properties.id) WHERE status = 'rejected'

---

### 3. 👥 Clients Section
**Purpose:** Monitor client satisfaction and engagement metrics  
**Statistics Displayed:**
- **Connected:** Total clients who completed a transaction
- **Happy 😊:** Clients who left positive feedback
- **Complained 😞:** Clients who left negative feedback
- **Failed to Pick:** Clients who cancelled or didn't complete

**Data Sources:**
- Connected: From completed consultations
- Happy: From client_feedback table WHERE feedback_type = 'positive'
- Complained: From client_feedback table WHERE feedback_type = 'negative'
- Failed to Pick: From cancelled consultations

---

### 4. 👀 Viewings Section
**Purpose:** Track property viewing statistics  
**Statistics Displayed:**
- **Assigned by Admin:** All viewing requests assigned to the agent
- **Completed:** Viewings that were successfully completed
- **Pending:** Viewings scheduled but not yet completed
- **Expired:** Viewings that passed the scheduled date without completion

**Status Calculation:**
- Expired: scheduled_date < TODAY AND status != 'completed'

---

### 5. 🚚 Delivery Groups Performance
**Purpose:** Monitor delivery team performance and client feedback  
**Features:**
- **Group Statistics:** Overall delivery metrics
  - Completed Deliveries (✓)
  - Incomplete Deliveries (○)
  - Rescheduled Deliveries (⟳)

- **Group Rankings:** Ordered by client feedback ratings
  - Group Name/ID
  - Total Bookings Count
  - Completed Bookings (count)
  - Pending Bookings (count)
  - Rescheduled Bookings (count)
  - Average Rating (1-5 stars based on client feedback)
  - Feedback Count (how many clients provided ratings)

**Group Rules:**
- Max 5 individuals per delivery group
- Each group can be assigned to multiple agents
- Ratings are based on client feedback/reviews

---

### 6. ✅ Tasks by Category
**Purpose:** Organize and track tasks by service type  
**Features:**
- Interactive bar chart showing task completion rates
- Task breakdown by category with completion/pending stats

**Task Categories Tracked:**
- NightlyFied
- Hotel Reservation
- Student Housing
- Sold Properties
- Delivery
- House Swap
- Cleaning Services
- WIFI Distribution
- CCTV Installation
- Alexa Installation
- Interior Designs

**Task Statistics:**
- Total Completed Tasks
- Total Incomplete Tasks
- Total Pending Tasks

**Chart Visualization:**
- Bar chart with two data series: Completed (Green) vs Pending/Incomplete (Orange)
- Responsive and mobile-friendly

---

### 7. 💬 Messages
**Purpose:** Enable direct communication between agents and admins  

#### Agent Capabilities:
- Send new messages to admin with subject and message body
- Reply to messages from admin
- View message history (limited to 20 most recent)
- Unread message counter (red badge)

#### Admin Capabilities (via admin_messages.php):
- View all agents in a list
- Send new messages to specific agents
- View conversation history with each agent
- Reply to agent messages
- Unread message counter

#### Message Features:
- Message Type Tracking:
  - `agent_to_admin`: Message from agent to admin
  - `admin_to_agent`: Message from admin to agent
  - `reply`: Response to an existing message
- Parent Message Tracking: Replies can reference the original message
- Unread Status: Messages are marked as read/unread
- Timestamps: All messages are dated and timestamped

---

## Database Schema

### Tables Created

#### 1. `client_feedback`
```sql
- id (INT, Primary Key)
- agent_id (INT, Foreign Key to users)
- client_id (INT, Foreign Key to users)
- consultation_id (INT, Foreign Key to consultations)
- feedback_type (ENUM: 'positive', 'negative', 'neutral')
- rating (INT: 1-5)
- comment (TEXT)
- created_at (TIMESTAMP)
```

#### 2. `agent_tasks`
```sql
- id (INT, Primary Key)
- agent_id (INT, Foreign Key to users)
- task_type (VARCHAR(50)) - Service type
- title (VARCHAR(255))
- description (TEXT)
- status (ENUM: 'completed', 'pending', 'incomplete')
- due_date (DATE)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

#### 3. `agent_messages`
```sql
- id (INT, Primary Key)
- sender_id (INT, Foreign Key to users)
- receiver_id (INT, Foreign Key to users)
- title (VARCHAR(255))
- message (TEXT)
- message_type (ENUM: 'admin_to_agent', 'agent_to_admin', 'reply')
- parent_message_id (INT, Foreign Key to agent_messages)
- is_read (TINYINT Boolean)
- created_at (TIMESTAMP)
- read_at (TIMESTAMP)
```

---

## File Structure

### Main Files:
1. **agent_dashboard.php** - Main agent dashboard interface
   - All 7 dashboard sections
   - Real-time data queries
   - Message modal interface
   - Chart.js integration for tasks

2. **send_message_to_admin.php** - Agent message handler
   - Process new messages
   - Handle replies to admin messages
   - Insert into agent_messages table

3. **admin_messages.php** - Admin message management interface
   - List all agents
   - View message conversations
   - Compose and send messages

4. **send_admin_message.php** - Admin message handler
   - Process messages from admin to agents
   - Verify admin privileges

5. **setup_agent_tables.php** - Database initialization
   - Creates necessary tables
   - Sets up indexes

---

## Usage Guide

### For Agents:
1. Navigate to `http://localhost/WBRND/WBRND/agent_dashboard.php`
2. View all 7 sections with real-time data
3. Click "Send Message" to communicate with admin
4. Click "Reply" on any admin message to respond
5. Click section buttons to navigate to detailed views

### For Admins:
1. Navigate to `http://localhost/WBRND/WBRND/admin_messages.php`
2. Select an agent from the left panel
3. View conversation history
4. Type and send messages to agents
5. Receive notifications on new agent messages

---

## Key Features

✅ **Real-time Statistics** - All metrics update based on live database queries

✅ **Visual Indicators** - Color-coded status tags and charts

✅ **Responsive Design** - Works on desktop, tablet, and mobile

✅ **Message Threading** - Support for message replies and conversations

✅ **Unread Tracking** - Badges show unread message counts

✅ **Group Performance** - Delivery group rankings based on client feedback

✅ **Task Categorization** - 11 service categories with visual breakdown

✅ **Error Handling** - Graceful error messages for user feedback

✅ **Session Management** - Secure authentication checks

---

## Styling & UI

- **Color Scheme:**
  - Primary: #f97316 (Orange)
  - Secondary: #1e293b (Dark Blue)
  - Success: #22c55e (Green)
  - Warning: #f59e0b (Amber)
  - Danger: #dc2626 (Red)

- **Components:**
  - Rounded Cards (16px border-radius)
  - Clean Grid Layouts
  - Smooth Hover Effects
  - Professional Typography

---

## Future Enhancements

1. Export dashboard data to PDF/Excel
2. Email notifications for new messages
3. Task assignment and delegation
4. Advanced filtering and search
5. Custom date range reporting
6. Bulk operations (mark multiple as read, etc.)
7. Message attachments
8. Admin broadcast messages to multiple agents
9. Performance analytics dashboard
10. Integration with SMS/WhatsApp notifications

---

## Support & Troubleshooting

**Issue:** Dashboard not loading  
**Solution:** Ensure database tables are created by running setup_agent_tables.php

**Issue:** No messages showing  
**Solution:** Verify agent_messages table exists and check database connection

**Issue:** Delivery groups not displaying  
**Solution:** Check mover_groups and mover_bookings tables in mover system database

**Issue:** Tasks chart not rendering  
**Solution:** Ensure Chart.js library is loaded (already included via CDN)

---

**Created:** May 4, 2026  
**Version:** 1.0 Complete  
**Status:** Production Ready
