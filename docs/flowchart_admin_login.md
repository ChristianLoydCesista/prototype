# Program Flowchart: Admin Login System

## 1. Overview

This document provides the flowchart and pseudocode for the Admin Login functionality in the Arteche Community Intelligence System (CIS).

## 2. Flowchart Diagram

```
+-----------------------------------------------------------------------------+
|                         ADMIN LOGIN PROCESS                                 |
+-----------------------------------------------------------------------------+

    +------------------+
    |  User accesses   |
    |  admin_login.php |
    +--------+---------+
             |
             v
    +---------------------------+
    |  Is Admin Session          |------------------+
    |  Already Active?           |                  |
    +------------+---------------+                  |
                 | YES                             | NO
                 v                                |
    +------------------+                         |
    |  Redirect to     |                         |
    |  dashboard.php   |                         |
    +--------+---------+                         |
             |                                   |
             <-----------------------------------+
                                             |
                                             v
                          +--------------------------------+
                          |     Display Admin Login Page   |
                          |   (HTML with Bootstrap UI)    |
                          +---------------+----------------+
                                          |
                                          v
                          +--------------------------------+
                          |    User Submits Login Form     |
                          |       (POST method)           |
                          +---------------+----------------+
                                          |
                                          v
                          +--------------------------------+
                          |    Get Form Data               |
                          |  - username (sanitized)       |
                          |  - password                   |
                          +---------------+----------------+
                                          |
                                          v
                          +--------------------------------+
                          |    Database Query              |
                          |  SELECT user.*, barangay.name|
                          |  FROM users                  |
                          |  LEFT JOIN barangays         |
                          |  WHERE username = ?          |
                          |  (Prepared Statement)        |
                          +---------------+----------------+
                                          |
                                          v
                          +--------------------------------+
                          |    User Found in Database?     |
                          +---------------+----------------+
                                          |
                     +---------------------+---------------------+
                     |                                           |
                     v NO                                        v YES
            +------------------------+                    +------------------------+
            |  Set Error:          |                    |  Verify Password      |
            |  "Invalid username   |                    |  (Supports bcrypt &  |
            |   or password!"     |                    |   MD5 legacy)        |
            +--------+-------------+                    +------------+-----------+
                     |                                              |
                     v                                              v
            +------------------------+                    +------------------------+
            |  Display Login Page   |                    |  Password Valid?      |
            |  with Error Message   |                    +------------+-----------+
                     |                                              |
                     v                                +-------------+-------------+
            +------------------------+                        | NO              | YES
            |  Display Login        |                        v                 v
            |  Page with Error     |            +--------------------+   +--------------------+
            +------------------------+            |  Set Error:       |   |  Create Admin     |
                                                 |  "Invalid         |   |  Session         |
                                                 |   username or     |   |  - admin = true  |
                                                 |   password!"     |   |  - user_id       |
                                                 +--------+---------+   |  - username      |
                                                          |             |  - full_name     |
                                                          v             |  - role          |
                                                 +--------------------+   |  - barangay_id   |
                                                 |  Display Login    |   |  - barangay_name |
                                                 |  Page with Error |   +--------+---------+
                                                 +--------------------+            |
                                                                               v
                                                                    +----------------------+
                                                                    |  Log Activity       |
                                                                    |  INSERT INTO        |
                                                                    |  activity_logs      |
                                                                    |  (user_id, action, |
                                                                    |   details, ip)     |
                                                                    +--------+-----------+
                                                                               |
                                                                               v
                                                                    +----------------------+
                                                                    |  Redirect to        |
                                                                    |  shared/dashboard   |
                                                                    +----------------------+
```

### Password Migration Flow

```
                         +---------------------+
                         |  Password Hash      |
                         |  Format Check       |
                         +--------+------------+
                                 |
                +----------------+----------------+
                |                                 |
                v                                 v
     +-------------------+            +-------------------+
     |  Starts with      |            |  Does NOT Start |
     |  "$2y$" (bcrypt)|            |  with "$2y$"    |
     +--------+----------+            +--------+----------+
              |                                 |
              v                                 v
     +-------------------+            +-------------------+
     |  Use             |            |  Check MD5      |
     |  password_verify |            |  (legacy)       |
     +--------+----------+            +--------+----------+
              |                                 |
     +--------+--------+             +---------+----------+
     |                 |             |                  |
     v                 v             v                  v
  +------+        +------+    +-------+         +-------+
  |Valid?|        |Invalid|   |Match? |         |Invalid|
  +--+---+        +------+    +---+---+         +-------+
     |                                     |
     v                                     v
  +--------------------+      +------------------------+
  |  Return TRUE      |      |  Generate bcrypt      |
  |  (Login Success)  |      |  hash of password     |
  +--------------------+      +------------+---------+
                                         |
                                         v
                                +------------------------+
                                |  UPDATE users table   |
                                |  SET password =       |
                                |  new bcrypt hash      |
                                +------------------------+
```

## 3. Pseudocode

### 3.1 Admin Login Page (admin_login.php)

```
BEGIN

    REQUIRE_ONCE __DIR__ . '/../shared/bootstrap.php'

    IF ISSET($_SESSION['admin']) AND $_SESSION['admin'] IS TRUE THEN
        REDIRECT TO "shared/dashboard.php"
        EXIT
    END IF

    conn = getDB()
    error = ''

    IF $_SERVER['REQUEST_METHOD'] == 'POST' THEN
        username = HTMLSPECIALCHARS($_POST['username'] ?? '')
        password = $_POST['password'] ?? ''

        sql = "SELECT u.*, b.name as barangay_name
               FROM users u
               LEFT JOIN barangays b ON u.barangay_id = b.id
               WHERE u.username = ?"

        stmt = conn.prepare(sql)
        stmt.bind_param("s", username)
        stmt.execute()
        result = stmt.get_result()

        IF result.num_rows == 1 THEN
            user = result.fetch_assoc()

            passwordValid = FALSE

            IF SUBSTRING(user['password'], 0, 4) == "$2y$" THEN
                passwordValid = password_verify(password, user['password'])
            ELSE
                IF md5(password) == user['password'] THEN
                    passwordValid = TRUE
                    newHash = password_hash(password, PASSWORD_BCRYPT)
                    updateStmt = conn.prepare("UPDATE users SET password = ? WHERE id = ?")
                    updateStmt.bind_param("si", newHash, user['id'])
                    updateStmt.execute()
                    updateStmt.close()
                END IF
            END IF

            IF passwordValid IS TRUE THEN
                $_SESSION['admin'] = TRUE
                $_SESSION['user_id'] = user['id']
                $_SESSION['username'] = user['username']
                $_SESSION['full_name'] = user['full_name']
                $_SESSION['role'] = user['role']
                $_SESSION['barangay_id'] = user['barangay_id']
                $_SESSION['barangay_name'] = user['barangay_name'] ?? 'Super Admin'

                ip = $_SERVER['REMOTE_ADDR']
                logStmt = conn.prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, 'Login', 'User logged in', ?)")
                logStmt.bind_param("is", user['id'], ip)
                logStmt.execute()
                logStmt.close()

                REDIRECT TO "shared/dashboard.php"
                EXIT
            ELSE
                error = "Invalid username or password!"
            END IF
        ELSE
            error = "Invalid username or password!"
        END IF

        stmt.close()
    END IF

    DISPLAY HTML Login Page with:
    - Bootstrap styled login card
    - Logo: "Arteche CI System"
    - Subtitle: "Municipality of Arteche, Eastern Samar"
    - Error message display
    - Username input field
    - Password input field
    - Login submit button
    - Demo accounts reference

END
```

## 4. Key Components

### 4.1 Database Tables

| Table         | Purpose              |
| ------------- | -------------------- |
| users         | Admin/staff accounts |
| barangays     | Barangay information |
| activity_logs | Audit trail          |

### 4.2 Session Variables

| Variable                    | Description              |
| --------------------------- | ------------------------ |
| $\_SESSION['admin']         | Session indicator (TRUE) |
| $\_SESSION['user_id']       | User database ID         |
| $\_SESSION['username']      | Login username           |
| $\_SESSION['full_name']     | Display name             |
| $\_SESSION['role']          | User role                |
| $\_SESSION['barangay_id']   | Assigned barangay        |
| $\_SESSION['barangay_name'] | Barangay display name    |

### 4.3 User Roles

| Role           | Description          |
| -------------- | -------------------- |
| super_admin    | System Administrator |
| barangay_admin | Barangay Captain     |
| barangay_staff | Staff Member         |

## 5. Security Features

1. Bcrypt password hashing with $2y$ prefix
2. Automatic MD5 to bcrypt migration
3. Prepared statements for SQL injection prevention
4. Input sanitization with htmlspecialchars()
5. Activity logging with IP address

## 6. User Experience Flow

```
1. Admin navigates to admin_login.php
           |
           v
2. Check if admin session exists
           |
    +------+------+
    |             |
    v NO          v YES
    |             |
    |        Redirect to Dashboard
    |
    v
3. Display Login Form
           |
           v
4. Enter credentials
           |
           v
5. Submit form
           |
           v
6. Query database for user
           |
    +------+------+
    |             |
    v NO          v YES
    |             |
    Error    Verify Password
    |             |
    |        +------+------+
    |        |             |
    |        v NO          v YES
    |        |             |
    |    Error        Create Session
    |        |             |
    |        |        Log Activity
    |        |             |
    |        |        Redirect
    |        |             |
    +-------><-----------+
```

## 7. Demo Accounts

| Role           | Username     | Password  |
| -------------- | ------------ | --------- |
| Super Admin    | admin        | admin123  |
| Barangay Admin | tangbo_admin | tangbo123 |

---

Document Version: 1.0
Last Updated: 2026
Author: Technical Documentation
