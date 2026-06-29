# Program Flowchart: Citizen Registration System

## 1. Overview

This document provides the flowchart and pseudocode for the Citizen Registration functionality in the Arteche Community Intelligence System (CIS).

## 2. Flowchart Diagram

```
+-----------------------------------------------------------------------------+
|                    CITIZEN REGISTRATION PROCESS                              |
+-----------------------------------------------------------------------------+

    +------------------+
    |  User accesses   |
    |  registration    |
    |  page           |
    +--------+---------+
             |
             v
    +---------------------------+
    |  Is User Already          |------------------+
    |  Logged In?               |                  |
    +------------+---------------+                  |
                 | YES                             | NO
                 v                                |
    +------------------+                         |
    |  Redirect to    |                         |
    |  dashboard.php  |                         |
    +--------+---------+                         |
             |                                   |
             <-----------------------------------+
                                             |
                                             v
                          +--------------------------------+
                          |     Display Registration       |
                          |     Form (Step 1)            |
                          |  - Personal Info              |
                          |  - Contact Info              |
                          |  - Address                   |
                          |  - Password                  |
                          |  - Terms acceptance         |
                          +---------------+----------------+
                                          |
                                          v
                          +--------------------------------+
                          |    User Submits Form          |
                          |    (POST to register.php)    |
                          +---------------+----------------+
                                          |
                                          v
                          +--------------------------------+
                          |    Get Form Data               |
                          |  - email, phone              |
                          |  - password, confirm_password|
                          |  - first_name, last_name     |
                          |  - middle_name              |
                          |  - birth_date              |
                          |  - address, barangay_id    |
                          +---------------+----------------+
                                          |
                                          v
                          +--------------------------------+
                          |    Validate Required Fields    |
                          |  (email, phone, password,   |
                          |   first_name, last_name,     |
                          |   birth_date, address,      |
                          |   barangay_id)              |
                          +---------------+----------------+
                                          |
                                          v
                          +--------------------------------+
                          |    All Required Fields         |
                          |    Filled?                   |
                          +---------------+----------------+
                                          |
                     +----------------------+----------------------+
                     |                                           |
                     v NO                                        v YES
        +---------------------------+             +---------------------------+
        |  Set Flash Error:        |             |  Validate Password       |
        |  "All required fields   |             |  Match                  |
        |   must be filled"      |             +------------+------------+
        +------------+------------+                          |
                     |                                       |
                     v                                       v
        +---------------------------+          +---------------------------+
        |  Redirect to              |          |  Passwords Match?         |
        |  register_view.php       |          +------------+------------+
        |  (with error)           |                          |
        +---------------------------+     +---------+-----------+
                                         | YES               | NO
                                         v                   v
                              +-------------------+  +----------------------+
                              |  Validate         |  |  Set Flash Error:   |
                              |  Registration     |  |  "Passwords do    |
                              |  Data             |  |   not match"      |
                              +-------+-----------+  +-----------+--------+
                                      |                          |
                                      v                          v
                              +-------------------+  +------------------------+
                              |  Check if Email   |  |  Redirect to          |
                              |  or Phone         |  |  register_view.php   |
                              |  Already Exists   |  |  (with error)        |
                              +-------+-----------+  +------------------------+
                                      |
                                      v
                              +-------------------+
                              |  Email/Phone     |
                              |  Already Exists? |
                              +-------+-----------+
                                      |
                     +------------------+------------------+
                     |                                 |
                     v NO                               v YES
        +---------------------------+  +---------------------------+
        |  Hash Password             |  |  Set Flash Error:        |
        |  (bcrypt)                |  |  "Email or phone         |
        +-------+------------------+  |   already registered"   |
                |                      +------------+------------+
                |                                   |
                v                                   v
        +-------------------+  +---------------------------+
        |  Generate         |  |  Redirect to             |
        |  Verification    |  |  register_view.php       |
        |  Code (6 chars) |  |  (with error)           |
        +-------+---------+  +---------------------------+
                |
                v
        +-------------------+
        |  Insert into     |
        |  citizens table  |
        +-------+---------+
                |
                v
        +-------------------+
        |  Insert          |----------------------+
        |  Successful?      |                      |
        +-------+---------+                      |
                | YES                           | NO
                v                               |
        +-------------------+                   |
        |  Send             |                   |
        |  Verification    |                   |
        |  Email/SMS      |                   |
        +-------+---------+                   |
                |                               |
                v                               |
        +-------------------+                   |
        |  Store in Session|                   |
        |  - verification_ |                   |
        |    email        |                   |
        |  - verification_ |                   |
        |    phone        |                   |
        |  - registration_ |                   |
        |    first_name   |                   |
        |  - demo_        |                   |
        |    verification_ |                   |
        |    code         |                   |
        +-------+---------+                   |
                |                               |
                v                               |
        +-------------------+                   |
        |  Set Flash       |                   |
        |  Success Message |                   |
        +-------+---------+                   |
                |                               |
                v                               |
        +-------------------+                   |
        |  Redirect to     |                   |
        |  verify.php     |                   |
        +-------------------+                   |
                                             |
                                             v
        +---------------------------+
        |  Set Flash Error        |
        |  from Auth errors       |
        +------------+------------+
                     |
                     v
        +---------------------------+
        |  Redirect to             |
        |  register_view.php      |
        +---------------------------+
```

## 3. Pseudocode

### 3.1 Registration Form Page (citizen_register_view.php)

```
BEGIN

    REQUIRE_ONCE '../shared/bootstrap.php'
    auth = NEW Auth()

    // Check if user is already logged in
    IF session.isCitizenLoggedIn() THEN
        REDIRECT TO "citizen_dashboard.php"
        EXIT
    END IF

    // Get barangays list for dropdown
    db = getDB()
    barangays = db.query("SELECT id, name FROM barangays ORDER BY name")

    // Set page title
    pageTitle = "Register - Arteche Citizen Portal"

    // Display HTML Registration Page with:
    // - Navbar with logo
    // - Hero section "Create Account"
    // - Registration form containing:
    //   * Personal Information:
    //     - First Name (required)
    //     - Last Name (required)
    //     - Middle Name (optional)
    //     - Birth Date (required, max 13 years ago)
    //   * Contact Information:
    //     - Email Address (required)
    //     - Mobile Number (required, pattern: 09XXXXXXXXX)
    //   * Address:
    //     - Barangay dropdown (required)
    //     - Complete Address (required)
    //   * Password:
    //     - Password (required, min 8 chars)
    //     - Confirm Password (required)
    //   * Terms:
    //     - Terms of Service checkbox (required)
    // - Terms and Privacy Policy modals
    // - Link to login page
    // - Flash message display area
    // - Footer with privacy notice
    // - JavaScript validation:
    //   * Password match validation
    //   * Age validation (min 13 years)
    //   * Phone number formatting
    //   * Real-time password match indicator

END
```

### 3.2 Registration Processing (citizen_register.php)

```
BEGIN

    REQUIRE_ONCE '../shared/bootstrap.php'
    session = NEW Session()
    auth = NEW Auth()

    // Check if user is already logged in
    IF session.isCitizenLoggedIn() THEN
        REDIRECT TO "citizen_dashboard.php"
        EXIT
    END IF

    // Process only POST requests
    IF request.method !== 'POST' THEN
        session.setFlash('error', 'Invalid request method.')
        REDIRECT TO "citizen_portal.php"
        EXIT
    END IF

    // Get form data
    data = [
        'email' => request.post['email'] ?? '',
        'phone' => request.post['phone'] ?? '',
        'password' => request.post['password'] ?? '',
        'confirm_password' => request.post['confirm_password'] ?? '',
        'first_name' => request.post['first_name'] ?? '',
        'last_name' => request.post['last_name'] ?? '',
        'middle_name' => request.post['middle_name'] ?? '',
        'birth_date' => request.post['birth_date'] ?? '',
        'address' => request.post['address'] ?? '',
        'barangay_id' => request.post['barangay_id'] ?? ''
    ]

    // Validate required fields
    required = ['email', 'phone', 'password', 'confirm_password',
                'first_name', 'last_name', 'birth_date',
                'address', 'barangay_id']

    missing = []
    FOR EACH field IN required DO
        IF data[field] IS EMPTY THEN
            missing[] = field
        END IF
    END FOR

    IF missing IS NOT EMPTY THEN
        session.setFlash('error', 'All required fields must be filled. Missing: ' + implode(', ', missing))
        REDIRECT TO "citizen_register_view.php"
        EXIT
    END IF

    // Validate password match
    IF data['password'] !== data['confirm_password'] THEN
        session.setFlash('error', 'Passwords do not match.')
        REDIRECT TO "citizen_register_view.php"
        EXIT
    END IF

    // Register citizen
    citizenId = auth.register(data)

    IF citizenId IS NOT FALSE THEN
        // Store verification data in session
        session['verification_email'] = data['email']
        session['verification_phone'] = data['phone']
        session['registration_first_name'] = data['first_name']

        // Set success flash message
        session.setFlash('success', 'Registration successful! Please verify your account. Check your email for the verification code.')

        // Redirect to verification page
        REDIRECT TO "citizen_verify.php"
        EXIT
    ELSE
        // Registration failed
        errors = auth.getErrors()
        FOR EACH error IN errors DO
            session.setFlash('error', error)
        END FOR

        REDIRECT TO "citizen_register_view.php"
        EXIT
    END IF

END
```

### 3.3 Auth Registration Method (Auth.php)

```
FUNCTION register(data)
BEGIN
    errors = []

    // Validate registration data
    IF NOT validateRegistration(data) THEN
        RETURN false
    END IF

    // Check if email or phone already exists
    IF citizenExists(data['email'], data['phone']) THEN
        errors[] = 'Email or phone number already registered'
        RETURN false
    END IF

    // Hash password with bcrypt
    hashedPassword = password_hash(data['password'], PASSWORD_BCRYPT)

    // Generate 6-character verification code
    verificationCode = generateVerificationCode()

    // Insert citizen into database
    stmt = db.prepare("
        INSERT INTO citizens (email, phone, password, first_name, last_name,
                            middle_name, birth_date, address, barangay_id,
                            verification_code, account_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")

    stmt.bind_param(data['email'], data['phone'], hashedPassword,
                    data['first_name'], data['last_name'],
                    data['middle_name'], data['birth_date'],
                    data['address'], data['barangay_id'],
                    verificationCode, 'Active')

    IF stmt.execute() THEN
        citizenId = stmt.insert_id
        stmt.close()

        // Send verification email/SMS
        sendVerification(data['email'], data['phone'], verificationCode)

        RETURN citizenId
    END IF

    errors[] = 'Registration failed. Please try again.'
    RETURN false
END
```

### 3.4 Validation Rules (Auth.php)

```
FUNCTION validateRegistration(data)
BEGIN
    errors = []

    // Email validation
    IF data['email'] IS EMPTY OR NOT valid email THEN
        errors[] = 'Valid email is required'
    END IF

    // Phone validation (Philippine format)
    IF data['phone'] IS EMPTY OR NOT matching pattern /^09[0-9]{9}$/ THEN
        errors[] = 'Valid Philippine mobile number is required (09XXXXXXXXX)'
    END IF

    // Password validation
    IF data['password'] IS EMPTY OR length < 8 THEN
        errors[] = 'Password must be at least 8 characters'
    END IF

    // Password match
    IF data['password'] !== data['confirm_password'] THEN
        errors[] = 'Passwords do not match'
    END IF

    // Name validation
    IF data['first_name'] IS EMPTY OR data['last_name'] IS EMPTY THEN
        errors[] = 'First name and last name are required'
    END IF

    // Age validation (minimum 13 years)
    IF data['birth_date'] IS EMPTY OR age < 13 THEN
        errors[] = 'You must be at least 13 years old'
    END IF

    // Barangay validation
    IF data['barangay_id'] IS EMPTY THEN
        errors[] = 'Please select your barangay'
    END IF

    RETURN (errors IS EMPTY)
END
```

## 4. Key Components

### 4.1 Form Fields

| Field            | Type     | Validation            | Required |
| ---------------- | -------- | --------------------- | -------- |
| first_name       | text     | Non-empty             | Yes      |
| last_name        | text     | Non-empty             | Yes      |
| middle_name      | text     | Optional              | No       |
| birth_date       | date     | Must be 13+ years old | Yes      |
| email            | email    | Valid email format    | Yes      |
| phone            | tel      | 09XXXXXXXXX pattern   | Yes      |
| barangay_id      | select   | Must be selected      | Yes      |
| address          | textarea | Non-empty             | Yes      |
| password         | password | Minimum 8 characters  | Yes      |
| confirm_password | password | Must match password   | Yes      |
| terms            | checkbox | Must be checked       | Yes      |

### 4.2 Database Fields (citizens table)

| Field             | Type      | Description                 |
| ----------------- | --------- | --------------------------- |
| id                | INT       | Primary key, auto-increment |
| email             | VARCHAR   | Unique email address        |
| phone             | VARCHAR   | Philippine mobile number    |
| password          | VARCHAR   | Bcrypt hashed password      |
| first_name        | VARCHAR   | Citizen first name          |
| last_name         | VARCHAR   | Citizen last name           |
| middle_name       | VARCHAR   | Citizen middle name         |
| birth_date        | DATE      | Date of birth               |
| address           | TEXT      | Full address                |
| barangay_id       | INT       | Foreign key to barangays    |
| verification_code | VARCHAR   | Email verification code     |
| is_verified       | BOOLEAN   | Email verification status   |
| account_status    | ENUM      | Active/Inactive             |
| created_at        | TIMESTAMP | Registration date           |

### 4.3 Session Variables Set

| Variable                | Purpose               |
| ----------------------- | --------------------- |
| verification_email      | For verification page |
| verification_phone      | For verification page |
| registration_first_name | For email template    |
| demo_verification_code  | For demo display      |

## 5. User Experience Flow

```
1. User clicks "Register Now" on login page
           |
           v
2. Check if already logged in
           |
    +------+------+
    |             |
    v NO          v YES
    |             |
    |        Redirect to Dashboard
    |
    v
3. Display Registration Form
           |
           v
4. User fills in all required fields
           |
           v
5. User accepts Terms of Service
           |
           v
6. User clicks "Create Account"
           |
           v
7. Client-side validation
    - Password match
    - Age validation (13+)
    - Phone format
           |
           v
8. Server-side validation
           |
    +------+------+
    |             |
    v NO          v YES
    |             |
    Error     Check existing user
    |             |
    |        +------+------+
    |        |             |
    |        v NO          v YES
    |        |             |
    |        |        Error: Already registered
    |        |
    v        v
    Create account
    Generate verification code
    Send verification email
           |
           v
    Redirect to Verification Page
```

## 6. Security Features

1. **Password Security**: Bcrypt hashing with cost factor
2. **Input Validation**: Server-side validation for all fields
3. **Duplicate Prevention**: Check for existing email/phone
4. **SQL Injection Prevention**: Prepared statements
5. **Age Verification**: Minimum 13 years old requirement
6. **Terms Acceptance**: Required checkbox
7. **Session-based Verification**: Store code for demo display

## 7. Error Handling

| Error             | User Message                                 | Action              |
| ----------------- | -------------------------------------------- | ------------------- |
| Missing fields    | "All required fields must be filled"         | Redirect with error |
| Password mismatch | "Passwords do not match"                     | Redirect with error |
| Email exists      | "Email or phone already registered"          | Redirect with error |
| Invalid email     | "Valid email is required"                    | Redirect with error |
| Invalid phone     | "Valid Philippine mobile number is required" | Redirect with error |
| Weak password     | "Password must be at least 8 characters"     | Redirect with error |
| Underage          | "You must be at least 13 years old"          | Redirect with error |
| No barangay       | "Please select your barangay"                | Redirect with error |

## 8. Related Files

| File                            | Purpose                      |
| ------------------------------- | ---------------------------- |
| citizen_register_view.php       | Registration form UI         |
| citizen_register.php            | Registration processing      |
| citizen_verify.php              | Email verification page      |
| citizen_portal.php              | Login page (redirect target) |
| citizen_dashboard.php           | Dashboard (authenticated)    |
| app/shared/includes/Auth.php    | Authentication logic         |
| app/shared/includes/Session.php | Session management           |

---

Document Version: 1.0
Last Updated: 2026
Author: Technical Documentation
