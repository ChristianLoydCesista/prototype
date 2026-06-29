# Program Flowchart: Citizen Login System

## 1. Overview

This document provides the flowchart and pseudocode for the Citizen Login functionality in the Arteche Community Intelligence System (CIS).

## 2. Flowchart Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        CITIZEN LOGIN PROCESS                                │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────────┐
    │  User accesses   │
    │ citizen_portal   │
    └────────┬─────────┘
             │
             ▼
    ┌──────────────────────────┐
    │  Is User Already         │──────────────────┐
    │  Logged In?              │                  │
    └────────────┬─────────────┘                  │
                 │ YES                             │ NO
                 ▼                                │
    ┌──────────────────┐                         │
    │  Redirect to     │                         │
    │ citizen_dashboard│                         │
    └────────┬─────────┘                         │
             │                                   │
             ◄───────────────────────────────────┘
                                                 │
                                                 ▼
                              ┌────────────────────────────────┐
                              │     Display Login Form         │
                              │  (citizen_portal.php)         │
                              └───────────────┬────────────────┘
                                              │
                                              ▼
                              ┌────────────────────────────────┐
                              │    User Submits Form          │
                              │  (POST to citizen_login.php)  │
                              └───────────────┬────────────────┘
                                              │
                                              ▼
                              ┌────────────────────────────────┐
                              │    Validate Input              │
                              │  - Username (email/phone)      │
                              │  - Password                   │
                              └───────────────┬────────────────┘
                                              │
                                              ▼
                              ┌────────────────────────────────┐
                              │    Are Both Fields             │
                              │    Filled?                     │
                              └───────────────┬────────────────┘
                                              │
                         ┌─────────────────────┴─────────────────────┐
                         │                                           │
                         ▼ NO                                        ▼ YES
            ┌────────────────────────┐                    ┌─────────────────────┐
            │  Set Flash Error:     │                    │  Call Auth->login  │
            │  "Please enter both  │                    │  (username,         │
            │  username and        │                    │   password)         │
            │  password"          │                    └──────────┬──────────┘
            └──────────┬────────────────┘                       │
                       │                                          │
                       ▼                                          ▼
            ┌────────────────────────┐              ┌────────────────────────┐
            │  Redirect to          │              │    Login Successful?   │
            │  citizen_portal.php  │              └────────────┬───────────┘
            │  (with error)        │                       │
            └──────────┬────────────────┘           ┌────────┴────────┐
                       │                             │                 │
                       ◄─────────────────────────────┴─────────────────┘
                                               │ YES                │ NO
                                               ▼                    ▼
                                    ┌───────────────────┐  ┌───────────────────┐
                                    │  Set Citizen      │  │  Get Errors from  │
                                    │  Session          │  │  Auth->getErrors  │
                                    │  (user data)     │  │                   │
                                    └────────┬──────────┘  └────────┬──────────┘
                                             │                      │
                                             ▼                      ▼
                                    ┌───────────────────┐  ┌───────────────────┐
                                    │  Remember Me      │  │  Set Flash Error  │
                                    │  Checked?         │  │  for Each Error   │
                                    └────────┬──────────┘  └────────┬──────────┘
                                             │                      │
                                    ┌────────┴──────────┐           │
                                    ▼                   ▼           │
                         ┌───────────────────┐ ┌───────────────┐      │
                         │      YES          │ │      NO       │      │
                         └────────┬──────────┘ └───────┬───────┘      │
                                  │                    │              │
                                  ▼                    ▼              ▼
                         ┌───────────────────┐ ┌──────────────┐ ┌─────┴──────────┐
                         │ Generate Token     │ │    Skip      │ │ Redirect to    │
                         │ (32 hex chars)    │ │   Token      │ │ citizen_portal │
                         │                   │ │  Generation  │ │ with errors    │
                         └────────┬──────────┘ └──────────────┘ └────────────────┘
                                  │
                                  ▼
                         ┌───────────────────┐
                         │ Set Cookie        │
                         │ (30 days)         │
                         └────────┬──────────┘
                                  │
                                  ▼
                         ┌───────────────────┐
                         │ Set Flash        │
                         │ Success Message   │
                         │ "Welcome back,    │
                         │  [Name]!"        │
                         └────────┬──────────┘
                                  │
                                  ▼
                         ┌───────────────────┐
                         │ Redirect to       │
                         │ citizen_dashboard │
                         └───────────────────┘
```

## 3. Pseudocode

### 3.1 Main Login Page (citizen_portal.php)

```
BEGIN

    // Include bootstrap and initialize auth
    REQUIRE_once '../shared/bootstrap.php'
    auth = NEW Auth()

    // Check if user is already logged in
    IF session.isCitizenLoggedIn() THEN
        REDIRECT TO "citizen_dashboard.php"
        EXIT
    END IF

    // Set page title
    pageTitle = "Login - Arteche Citizen Portal"

    // Display HTML with:
    // - Navbar with logo and back link
    // - Hero section with title "Citizen Portal"
    // - Login form containing:
    //   - Email/Phone input field
    //   - Password input field
    //   - "Remember me" checkbox
    //   - Forgot password link
    //   - Submit button
    //   - Link to registration page
    // - Flash message display area (for errors/success)
    // - Footer with privacy notice

END
```

### 3.2 Login Processing (citizen_login.php)

```
BEGIN

    REQUIRE_once '../shared/bootstrap.php'
    auth = NEW Auth()
    errors = EMPTY_ARRAY

    // Check if already logged in
    IF session.isCitizenLoggedIn() THEN
        REDIRECT TO "citizen_dashboard.php"
        EXIT
    END IF

    // Process only POST requests
    IF request.method !== 'POST' THEN
        REDIRECT TO "citizen_portal.php"
        EXIT
    END IF

    // Get form data
    username = TRIM(request.post['username'])
    password = request.post['password']
    remember = ISSET(request.post['remember'])

    // Validate inputs
    IF username IS EMPTY OR password IS EMPTY THEN
        session.setFlash('error', 'Please enter both username and password')
        REDIRECT TO "citizen_portal.php"
        EXIT
    END IF

    // Attempt login
    citizen = auth.login(username, password)

    IF citizen IS NOT FALSE THEN
        // Login successful

        // Set citizen session data
        session.setCitizen(citizen)

        // Handle remember me functionality
        IF remember IS TRUE THEN
            token = GENERATE_RANDOM_TOKEN(32 bytes, hex format)
            SET_COOKIE('remember_token', token, time + 30 days, '/')
            // Note: Token storage in database not implemented yet
        END IF

        // Set success flash message
        session.setFlash('success', 'Welcome back, ' + citizen['first_name'] + '!')

        // Redirect to dashboard
        REDIRECT TO "citizen_dashboard.php"
        EXIT

    ELSE
        // Login failed

        // Get error messages from auth
        errors = auth.getErrors()

        FOR EACH error IN errors DO
            session.setFlash('error', error)
        END FOR

        REDIRECT TO "citizen_portal.php"
        EXIT
    END IF

END
```

## 4. Key Components

### 4.1 Auth Class Methods Used

| Method        | Description                       | Parameters         | Returns                |
| ------------- | --------------------------------- | ------------------ | ---------------------- |
| `login()`     | Authenticates user credentials    | username, password | Citizen array or false |
| `getErrors()` | Retrieves validation/login errors | None               | Array of error strings |

### 4.2 Session Methods Used

| Method                | Description                                 |
| --------------------- | ------------------------------------------- |
| `isCitizenLoggedIn()` | Checks if citizen session is active         |
| `setCitizen()`        | Stores citizen data in session              |
| `setFlash()`          | Stores temporary message for next page load |
| `hasFlash()`          | Checks if flash message exists              |
| `getFlash()`          | Retrieves and clears flash message          |

### 4.3 Form Fields

| Field    | Type     | Validation | Purpose               |
| -------- | -------- | ---------- | --------------------- |
| username | text     | Required   | Email or phone number |
| password | password | Required   | User password         |
| remember | checkbox | Optional   | Keep user logged in   |

## 5. User Experience Flow

```
1. User navigates to Citizen Portal
   │
   ▼
2. System checks if user is already logged in
   │
   ├─── YES ──► Redirect to Dashboard
   │
   └─── NO ──► Display Login Form
                     │
                     ▼
              User enters credentials
                     │
                     ▼
              User clicks Login button
                     │
                     ▼
              System validates input
                     │
                     ├─── INVALID ──► Show error, redisplay form
                     │
                     └─── VALID ──► Authenticate user
                                        │
                                        ├─── FAILED ──► Show error, redisplay form
                                        │
                                        └─── SUCCESS ──► Create session
                                                           │
                                                           ▼
                                                    Redirect to Dashboard
```

## 6. Security Considerations

1. **Input Validation**: Both username and password must be provided
2. **Password Handling**: Passwords are hashed using bcrypt
3. **Session Management**: User data stored in secure session
4. **Remember Me**: Token-based persistent login (30-day cookie)
5. **Flash Messages**: Errors displayed without page reload
6. **Redirect After POST**: Prevents duplicate form submissions

## 7. Error Handling

| Error Type          | User Message                              | Action                 |
| ------------------- | ----------------------------------------- | ---------------------- |
| Empty fields        | "Please enter both username and password" | Redirect to login form |
| Invalid credentials | "Invalid username or password"            | Redirect to login form |
| Account inactive    | Varies based on auth implementation       | Redirect to login form |

## 8. Related Files

| File                              | Purpose                    |
| --------------------------------- | -------------------------- |
| `citizen_portal.php`              | Login page UI              |
| `citizen_login.php`               | Login processing           |
| `citizen_dashboard.php`           | Redirect target on success |
| `citizen_register_view.php`       | Registration page link     |
| `citizen_forgot_password.php`     | Password recovery link     |
| `app/shared/includes/Auth.php`    | Authentication logic       |
| `app/shared/includes/Session.php` | Session management         |

---

_Document Version: 1.0_
_Last Updated: 2026_
_Author: Technical Documentation_
