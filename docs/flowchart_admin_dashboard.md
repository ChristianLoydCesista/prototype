# Program Flowchart: Admin Dashboard System

## 1. Overview

This document provides the flowchart and pseudocode for the Admin Dashboard functionality in the Arteche Community Intelligence System (CIS).

## 2. Flowchart Diagram - Main Process

```
+-----------------------------------------------------------------------------+
|                     ADMIN DASHBOARD PROCESS                                  |
+-----------------------------------------------------------------------------+

    +------------------+
    |  Admin accesses  |
    |  dashboard.php  |
    +--------+---------+
             |
             v
    +---------------------------+
    |  Authentication Check      |------------------+
    |  Is admin session        |                  |
    |  active?                 |                  |
    +------------+-------------+                  |
                 | YES                             | NO
                 v                                |
    +------------------+                         |
    |  Redirect to    |                         |
    |  admin_login    |                         |
    +------------------+                         |
                                                 |
                                                 v
                              +--------------------------------+
                              |     Get Session Variables       |
                              |  - barangay_id               |
                              |  - role (super_admin)        |
                              |  - username, full_name      |
                              +---------------+----------------+
                                              |
                                              v
                              +--------------------------------+
                              |     Determine User Role        |
                              +---------------+----------------+
                                              |
                      +-----------------------+-----------------------+
                      |                                               |
                      v                                               v
           +------------------+                          +------------------+
           |  Super Admin     |                          |  Barangay Admin  |
           |  (role =        |                          |  (role !=        |
           |   super_admin)  |                          |   super_admin)   |
           +--------+---------+                          +--------+----------+
                    |                                              |
                    v                                              v
           +------------------+                          +------------------+
           |  Get ALL         |                          |  Get Assigned   |
           |  barangays      |                          |  barangay only |
           +--------+---------+                          +--------+----------+
                    |                                              |
                    v                                              v
           +------------------+                          +------------------+
           |  Allow barangay  |                          |  Force restrict |
           |  selection       |                          |  to assigned    |
           |  via GET params  |                          |  barangay_id   |
           +--------+---------+                          +--------+----------+
                    |                                              |
                    v                                              v
           +------------------+                          +------------------+
           |  SECURITY CHECK: |                          |  Get barangay   |
           |  Barangay admin  |                          |  details        |
           |  CANNOT access   |                          +--------+---------+
           |  other barangay  |                                   |
           +--------+---------+                                   v
                    |                                   +------------------+
                    v                                   |  Set viewing     |
           +------------------+                         |  scope          |
           |  Get selected    |                         +--------+---------+
           |  barangay        |                                  |
           +--------+---------+                                  v
                    |                                   +------------------+
                    v                                   |  Fetch Statistics|
           +------------------+                         +--------+---------+
           |  Determine       |                                 |
           |  viewing scope   |                                 v
           +--------+---------+                         +------------------+
                    |                                   |  Fetch Data:   |
                    v                                   |  - Households  |
           +------------------+                         |  - Requests    |
           |  Initialize       |                         |  - Summary     |
           |  Pagination       |                         +--------+---------+
           |  Variables       |                                  |
           +--------+---------+                                  v
                    |                                   +------------------+
                    v                                   |  Render HTML    |
           +------------------+                         |  Dashboard      |
           |  Fetch Statistics|                         +--------+---------+
           |  - Total HH                                   |
           |  - Avg Income                                  |
           |  - 4Ps Count                                   |
           |  - Pending Requests                            |
           |  - Risk Distribution                           |
           +--------+---------+                                  |
                    |                                           |
                    v                                           v
           +------------------+                         +------------------+
           |  Fetch Households|                         |  DISPLAY PAGE  |
           |  with coords    |                         +------------------+
           +--------+---------+
                    |
                    v
           +------------------+
           |  Fetch Pending  |
           |  Document       |
           |  Requests       |
           +--------+---------+
                    |
                    v
           +------------------+
           |  Super Admin:    |
           |  Fetch barangay |
           |  summary        |
           +--------+---------+
                    |
                    v
           +------------------+
           |  Calculate       |
           |  Pagination      |
           |  Info           |
           +--------+---------+
                    |
                    v
           +------------------+
           |  Render HTML    |
           |  Dashboard      |
           +------------------+
```

## 3. Flowchart - RBAC Logic

```
+-----------------------------------------------------------------------------+
|                     ROLE-BASED ACCESS CONTROL (RBAC)                          |
+-----------------------------------------------------------------------------+

    +------------------+
    |  Get session    |
    |  role          |
    +--------+---------+
             |
             v
    +---------------------------+
    |  Is role = 'super_admin'?  |
    +------------+---------------+
             |
    +--------+--------+
    |                 |
    v YES             v NO
    |                 |
+----------+    +----------+
| SUPER    |    | BARANGAY |
| ADMIN    |    | ADMIN    |
+------+---+    +----+-----+
       |              |
       v              v
+------+----+   +----+-----+
| Get ALL  |   | Get ONLY|
| barangays|   | assigned|
+------+---+   | barangay|
       |      +----+-----+
       v            |
+------+----+      |
|Check GET |      |
|params   |      |
+------+---+      |
       |          |
+------+-----+   +----+-----+
| barangay_id |   | SECURITY |
| provided?   |   | CHECK   |
+------+-----+   +----+-----+
       |              |
+------+-----+       |
| YES        |NO      |
+------+-----+       |
       |              v
       v         +---------+
+------+----+    | Prevent |
| Get     |    | Access  |
| selected |    | Other   |
| barangay|    | Barangay|
+------+---+    +----+----+
       |              |
       v              v
+------+----+    +---------+
|View data|    | Redirect|
|for that |    | to base |
|barangay |    | dashboard|
+------+---+    +---------+
```

## 4. Flowchart - Statistics Data Fetching

```
+-----------------------------------------------------------------------------+
|                    STATISTICS FETCHING PROCESS                               |
+-----------------------------------------------------------------------------+

    +------------------+
    |  Get viewing    |
    |  scope         |
    +--------+---------+
             |
             v
    +---------------------------+
    |  Viewing ALL barangays?   |
    +------------+---------------+
             |
    +--------+--------+
    |                 |
    v YES             v NO
    |                 |
+----------+    +----------+
| Query    |    | Query    |
| ALL data |    | data for |
| from     |    | SPECIFIC |
| households|    | barangay |
+------+---+    +----+-----+
       |              |
       v              v
+------+---+    +----+-----+
| COUNT(*)|    | Prepare |
| FROM    |    | stmt    |
|households|   +----+-----+
+------+---+         |
       |             v
       v        +---------+
+------+---+    | Bind    |
| AVG    |    | barangay|
|income  |    | _id     |
+------+---+    +----+-----+
       |             |
       v             v
+------+---+    +---------+
| COUNT  |    | Execute |
| four_ps|    | query   |
|='Yes'  |    +----+-----+
+------+---+         |
       |             v
       v        +---------+
+------+---+    | Get     |
| COUNT  |    | results |
|pending |    +----+-----+
|requests|         |
+------+---+         |
       |             v
       v        +---------+
+------+---+    | Return  |
| Risk  |    | stats   |
|Distrib|    +---------+
+------+---+
```

## 5. Pseudocode

### 5.1 Main Dashboard Processing

```
BEGIN

    REQUIRE_ONCE __DIR__ . '/../../shared/bootstrap.php'
    conn = getDB()

    // ========================================
    // AUTHENTICATION CHECK
    // ========================================
    IF NOT ISSET($_SESSION['admin']) OR $_SESSION['admin'] !== TRUE THEN
        REDIRECT TO "../admin_login.php"
        EXIT
    END IF

    // Get session variables
    admin_barangay_id = $_SESSION['barangay_id'] ?? NULL
    is_super_admin = ($_SESSION['role'] ?? '') === 'super_admin'
    username = $_SESSION['username'] ?? 'Admin'
    full_name = $_SESSION['full_name'] ?? username

    // ========================================
    // RBAC LOGIC
    // ========================================
    IF NOT is_super_admin THEN
        // BARANGAY ADMIN
        selected_barangay_id = admin_barangay_id

        IF selected_barangay_id IS NULL THEN
            session_destroy()
            REDIRECT TO "../admin_login.php?error=no_barangay_assigned"
            EXIT
        END IF

        // Get barangay details
        stmt = conn.prepare("SELECT * FROM barangays WHERE id = ?")
        stmt.bind_param("i", selected_barangay_id)
        stmt.execute()
        selected_barangay = stmt.get_result().fetch_assoc()
        stmt.close()

        viewing_all_barangays = FALSE

        // Get only assigned barangay
        stmt = conn.prepare("SELECT * FROM barangays WHERE id = ?")
        stmt.bind_param("i", selected_barangay_id)
        stmt.execute()
        barangays = stmt.get_result().fetch_all(MYSQLI_ASSOC)
        stmt.close()

        // Security: Prevent access to other barangays
        IF ISSET($_GET['barangay_id']) AND $_GET['barangay_id'] != selected_barangay_id THEN
            error_log("SECURITY: Barangay admin attempted to access barangay_id=" + $_GET['barangay_id'])
            REDIRECT TO "dashboard.php"
            EXIT
        END IF

    ELSE
        // SUPER ADMIN
        barangays = conn.query("SELECT * FROM barangays ORDER BY name").fetch_all(MYSQLI_ASSOC)

        // Handle barangay selection via GET
        IF ISSET($_GET['barangay_id']) AND $_GET['barangay_id'] !== '' THEN
            selected_barangay_id = intval($_GET['barangay_id'])
            stmt = conn.prepare("SELECT * FROM barangays WHERE id = ?")
            stmt.bind_param("i", selected_barangay_id)
            stmt.execute()
            selected_barangay = stmt.get_result().fetch_assoc()
            stmt.close()

            IF NOT selected_barangay THEN
                selected_barangay_id = NULL
                selected_barangay = NULL
            END IF
        ELSE
            selected_barangay_id = NULL
            selected_barangay = NULL
        END IF

        viewing_all_barangays = (selected_barangay_id IS NULL)
    END IF

    // ========================================
    // PAGINATION SETUP
    // ========================================
    page = ISSET($_GET['page']) ? max(1, intval($_GET['page'])) : 1
    limit = ISSET($_GET['limit']) ? intval($_GET['limit']) : 25
    search = ISSET($_GET['search']) ? trim($_GET['search']) : ''
    sort = ISSET($_GET['sort']) ? $_GET['sort'] : 'id'
    order = ISSET($_GET['order']) ? $_GET['order'] : 'DESC'

    // Validate pagination
    limit = IN_ARRAY(limit, [10, 25, 50, 100]) ? limit : 25
    offset = (page - 1) * limit

    // Allowed sort columns
    allowed_sorts = ['id', 'name', 'household_size', 'income_monthly', 'risk_score', 'survey_date']
    sort = IN_ARRAY(sort, allowed_sorts) ? sort : 'id'
    order = IN_ARRAY(strtoupper(order), ['ASC', 'DESC']) ? strtoupper(order) : 'DESC'

    // ========================================
    // FETCH STATISTICS
    // ========================================
    stats = [
        'total_households' => 0,
        'avg_income' => 0,
        'four_ps_count' => 0,
        'pending_requests' => 0,
        'low_risk' => 0,
        'medium_risk' => 0,
        'high_risk' => 0
    ]

    IF viewing_all_barangays THEN
        // SUPER ADMIN: ALL STATISTICS
        result = safeQuery(conn, "SELECT COUNT(*) as cnt FROM households")
        stats['total_households'] = result ? result.fetch_assoc()['cnt'] : 0

        result = safeQuery(conn, "SELECT AVG(income_monthly) as avg_inc FROM households")
        stats['avg_income'] = result ? round(result.fetch_assoc()['avg_inc'] ?? 0) : 0

        result = safeQuery(conn, "SELECT COUNT(*) as cnt FROM households WHERE four_ps='Yes'")
        stats['four_ps_count'] = result ? result.fetch_assoc()['cnt'] : 0

        result = safeQuery(conn, "SELECT COUNT(*) as cnt FROM citizen_requests cr JOIN citizens c ON cr.citizen_id = c.id WHERE cr.status IN ('Submitted', 'Under Review')")
        stats['pending_requests'] = result ? result.fetch_assoc()['cnt'] : 0

        // Risk distribution
        result = safeQuery(conn, "SELECT
            SUM(CASE WHEN risk_score <= 30 THEN 1 ELSE 0 END) as low,
            SUM(CASE WHEN risk_score > 30 AND risk_score <= 60 THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN risk_score > 60 THEN 1 ELSE 0 END) as high
            FROM households")

        IF result AND row = result.fetch_assoc() THEN
            stats['low_risk'] = row['low'] ?? 0
            stats['medium_risk'] = row['medium'] ?? 0
            stats['high_risk'] = row['high'] ?? 0
        END IF

    ELSEIF selected_barangay_id THEN
        // SPECIFIC BARANGAY STATISTICS
        stmt = conn.prepare("SELECT COUNT(*) as cnt FROM households WHERE barangay_id = ?")
        stmt.bind_param("i", selected_barangay_id)
        stmt.execute()
        stats['total_households'] = stmt.get_result().fetch_assoc()['cnt'] ?? 0
        stmt.close()

        stmt = conn.prepare("SELECT AVG(income_monthly) as avg_inc FROM households WHERE barangay_id = ?")
        stmt.bind_param("i", selected_barangay_id)
        stmt.execute()
        stats['avg_income'] = round(stmt.get_result().fetch_assoc()['avg_inc'] ?? 0)
        stmt.close()

        stmt = conn.prepare("SELECT COUNT(*) as cnt FROM households WHERE four_ps='Yes' AND barangay_id = ?")
        stmt.bind_param("i", selected_barangay_id)
        stmt.execute()
        stats['four_ps_count'] = stmt.get_result().fetch_assoc()['cnt'] ?? 0
        stmt.close()

        // Pending requests for barangay
        stmt = conn.prepare("SELECT COUNT(*) as cnt FROM citizen_requests cr JOIN citizens c ON cr.citizen_id = c.id WHERE cr.status IN ('Submitted', 'Under Review') AND c.barangay_id = ?")
        stmt.bind_param("i", selected_barangay_id)
        stmt.execute()
        stats['pending_requests'] = stmt.get_result().fetch_assoc()['cnt'] ?? 0
        stmt.close()

        // Risk distribution for barangay
        stmt = conn.prepare("SELECT
            SUM(CASE WHEN risk_score <= 30 THEN 1 ELSE 0 END) as low,
            SUM(CASE WHEN risk_score > 30 AND risk_score <= 60 THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN risk_score > 60 THEN 1 ELSE 0 END) as high
            FROM households WHERE barangay_id = ?")
        stmt.bind_param("i", selected_barangay_id)
        stmt.execute()
        risk_counts = stmt.get_result().fetch_assoc()
        stmt.close()

        stats['low_risk'] = risk_counts['low'] ?? 0
        stats['medium_risk'] = risk_counts['medium'] ?? 0
        stats['high_risk'] = risk_counts['high'] ?? 0
    END IF

    // ========================================
    // FETCH HOUSEHOLDS WITH COORDINATES
    // ========================================
    IF viewing_all_barangays THEN
        count_result = conn.query("SELECT COUNT(*) as total FROM households h WHERE h.latitude IS NOT NULL AND h.longitude IS NOT NULL")
        total_households = count_result ? (count_result.fetch_assoc()['total'] ?? 0) : 0

        stmt = conn.prepare("SELECT h.*, b.name as barangay_name FROM households h LEFT JOIN barangays b ON h.barangay_id = b.id WHERE h.latitude IS NOT NULL AND h.longitude IS NOT NULL ORDER BY " + sort + " " + order + " LIMIT ? OFFSET ?")
        stmt.bind_param("ii", limit, offset)
        stmt.execute()
        households = stmt.get_result().fetch_all(MYSQLI_ASSOC)
        stmt.close()

    ELSEIF selected_barangay_id THEN
        count_stmt = conn.prepare("SELECT COUNT(*) as total FROM households h WHERE h.barangay_id = ? AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL")
        count_stmt.bind_param("i", selected_barangay_id)
        count_stmt.execute()
        total_households = count_stmt.get_result().fetch_assoc()['total'] ?? 0
        count_stmt.close()

        stmt = conn.prepare("SELECT h.*, b.name as barangay_name FROM households h LEFT JOIN barangays b ON h.barangay_id = b.id WHERE h.barangay_id = ? AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL ORDER BY " + sort + " " + order + " LIMIT ? OFFSET ?")
        stmt.bind_param("iii", selected_barangay_id, limit, offset)
        stmt.execute()
        households = stmt.get_result().fetch_all(MYSQLI_ASSOC)
        stmt.close()
    END IF

    // Calculate pagination
    total_pages = ceil(total_households / limit)
    showing_from = (offset + 1)
    showing_to = min(offset + limit, total_households)

    // ========================================
    // FETCH PENDING REQUESTS
    // ========================================
    IF viewing_all_barangays THEN
        result = safeQuery(conn, "SELECT cr.id, cr.request_number, cr.status, cr.submitted_at, cr.purpose, CONCAT(c.first_name, ' ', c.last_name) as citizen_name, dt.name as document_name, b.name as barangay_name FROM citizen_requests cr JOIN citizens c ON cr.citizen_id = c.id JOIN document_types dt ON cr.document_type_id = dt.id LEFT JOIN barangays b ON c.barangay_id = b.id WHERE cr.status IN ('Submitted', 'Under Review') ORDER BY cr.submitted_at DESC LIMIT 20")
        pending_requests = result ? result.fetch_all(MYSQLI_ASSOC) : []

    ELSEIF selected_barangay_id THEN
        stmt = conn.prepare("SELECT cr.id, cr.request_number, cr.status, cr.submitted_at, cr.purpose, CONCAT(c.first_name, ' ', c.last_name) as citizen_name, dt.name as document_name FROM citizen_requests cr JOIN citizens c ON cr.citizen_id = c.id JOIN document_types dt ON cr.document_type_id = dt.id WHERE cr.status IN ('Submitted', 'Under Review') AND c.barangay_id = ? ORDER BY cr.submitted_at DESC LIMIT 10")
        stmt.bind_param("i", selected_barangay_id)
        stmt.execute()
        pending_requests = stmt.get_result().fetch_all(MYSQLI_ASSOC)
        stmt.close()
    END IF

    // ========================================
    // FETCH BARANGAY SUMMARY (Super Admin only)
    // ========================================
    IF is_super_admin AND viewing_all_barangays THEN
        count_result = safeQuery(conn, "SELECT COUNT(*) as total FROM barangays")
        total_barangays = count_result ? (count_result.fetch_assoc()['total'] ?? 0) : 0

        stmt = conn.prepare("SELECT b.id, b.name, b.latitude, b.longitude, COUNT(DISTINCT h.id) as household_count, COALESCE(AVG(h.income_monthly), 0) as avg_income, SUM(CASE WHEN h.four_ps = 'Yes' THEN 1 ELSE 0 END) as four_ps_count, COALESCE(AVG(h.risk_score), 0) as avg_risk_score, (SELECT COUNT(*) FROM citizen_requests cr JOIN citizens c ON cr.citizen_id = c.id WHERE cr.status IN ('Submitted', 'Under Review') AND c.barangay_id = b.id) as pending_requests FROM barangays b LEFT JOIN households h ON b.id = h.barangay_id GROUP BY b.id ORDER BY b.name LIMIT ? OFFSET ?")
        stmt.bind_param("ii", barangay_limit, barangay_offset)
        stmt.execute()
        barangay_summary = stmt.get_result().fetch_all(MYSQLI_ASSOC)
        stmt.close()
    END IF

    // Calculate percentages for risk distribution
    total_risk = stats['low_risk'] + stats['medium_risk'] + stats['high_risk']
    low_percent = total_risk > 0 ? round((stats['low_risk'] / total_risk) * 100) : 0
    medium_percent = total_risk > 0 ? round((stats['medium_risk'] / total_risk) * 100) : 0
    high_percent = total_risk > 0 ? round((stats['high_risk'] / total_risk) * 100) : 0

    // ========================================
    // RENDER HTML DASHBOARD
    // ========================================
    DISPLAY HTML with:
    - Modern navbar with breadcrumb
    - Stats cards (households, income, 4Ps, requests)
    - Risk distribution chart
    - Map (if barangay has coordinates)
    - Barangay summary table (super admin)
    - Pending requests table
    - Recent households table
    - Sidebar with navigation

END
```

## 6. Key Components

### 6.1 User Roles

| Role           | Access Level    | Description                           |
| -------------- | --------------- | ------------------------------------- |
| super_admin    | All barangays   | Full system access, can view all data |
| barangay_admin | Single barangay | Restricted to assigned barangay only  |

### 6.2 Session Variables

| Variable                    | Description                              |
| --------------------------- | ---------------------------------------- |
| $\_SESSION['admin']         | Admin session indicator (TRUE)           |
| $\_SESSION['user_id']       | User database ID                         |
| $\_SESSION['username']      | Login username                           |
| $\_SESSION['full_name']     | Display name                             |
| $\_SESSION['role']          | User role (super_admin, barangay_admin)  |
| $\_SESSION['barangay_id']   | Assigned barangay (NULL for super_admin) |
| $\_SESSION['barangay_name'] | Assigned barangay name                   |

### 6.3 Statistics Displayed

| Stat              | Description                   | Query Source           |
| ----------------- | ----------------------------- | ---------------------- |
| Total Households  | Count of households           | households table       |
| Average Income    | AVG(income_monthly)           | households table       |
| 4Ps Beneficiaries | Count of 4Ps = 'Yes'          | households table       |
| Pending Requests  | Document requests with status | citizen_requests table |
| Low Risk          | Risk score <= 30              | households table       |
| Medium Risk       | Risk score 31-60              | households table       |
| High Risk         | Risk score > 60               | households table       |

### 6.4 Pagination Parameters

| Parameter      | Default | Options                                                           |
| -------------- | ------- | ----------------------------------------------------------------- |
| page           | 1       | Any positive integer                                              |
| limit          | 25      | 10, 25, 50, 100                                                   |
| sort           | id      | id, name, household_size, income_monthly, risk_score, survey_date |
| order          | DESC    | ASC, DESC                                                         |
| search         | empty   | Search string                                                     |
| barangay_page  | 1       | For barangay table pagination                                     |
| barangay_limit | 10      | 5, 10, 20, 50                                                     |

## 7. Security Features

### 7.1 Authentication Check

- Every page load checks for valid admin session
- Redirects to login page if not authenticated

### 7.2 Role-Based Access Control (RBAC)

- Super admins can view all barangays
- Barangay admins can only view their assigned barangay
- Security check prevents barangay admins from accessing other barangays via URL parameters

### 7.3 SQL Injection Prevention

- Prepared statements for all database queries
- Input sanitization and validation

### 7.4 Error Handling

- Try-catch blocks for database queries
- Safe query helper function
- Graceful handling of missing data

## 8. Map Features

### 8.1 Main Map (Specific Barangay)

- Leaflet.js integration
- Satellite and street view layers
- Household markers with risk-based colors
- Heatmap visualization toggle
- Popup with household details
- Navigation to household view and document request

### 8.2 Mini Map (All Barangays)

- Overview of all barangay locations
- Color-coded by average risk score
- Click to navigate to specific barangay dashboard

## 9. Risk Score Legend

| Level  | Range  | Color            | Description                  |
| ------ | ------ | ---------------- | ---------------------------- |
| Low    | 0-30   | Green (#10b981)  | Minimal intervention needed  |
| Medium | 31-60  | Yellow (#f59e0b) | Monitoring recommended       |
| High   | 61-100 | Red (#ef4444)    | Priority assistance required |

## 10. User Experience Flow

```
1. Admin logs in successfully
           |
           v
2. Dashboard loads with session check
           |
           v
3. System determines user role
           |
    +------+------+
    |             |
    v             v
Super Admin   Barangay Admin
    |             |
    v             v
View all or   View only
select        assigned
barangay      barangay
    |             |
    v             v
Fetch statistics based on scope
           |
           v
Display stats cards and charts
           |
           v
Fetch and display data tables
           |
           v
Render complete dashboard
```

## 11. Related Files

| File                  | Purpose                          |
| --------------------- | -------------------------------- |
| dashboard.php         | Main admin dashboard             |
| admin_login.php       | Login page                       |
| document_requests.php | Document requests management     |
| survey.php            | Household survey form            |
| household.php         | Household management             |
| view_household.php    | Household details                |
| manage_barangays.php  | Super admin: barangay management |
| manage_users.php      | Super admin: user management     |
| reports.php           | Super admin: reports generation  |

---

Document Version: 1.0
Last Updated: 2026
Author: Technical Documentation
