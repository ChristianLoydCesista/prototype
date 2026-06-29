<?php
// includes/Session.php
class Session
{

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }

    // ============================================
    // CITIZEN SESSION METHODS
    // ============================================

    // Set citizen session
    public function setCitizen($citizen)
    {
        $_SESSION['citizen'] = [
            'id' => $citizen['id'],
            'email' => $citizen['email'],
            'phone' => $citizen['phone'],
            'first_name' => $citizen['first_name'],
            'last_name' => $citizen['last_name'],
            'full_name' => $citizen['first_name'] . ' ' . $citizen['last_name'],
            'barangay_id' => $citizen['barangay_id'],
            'is_verified' => $citizen['is_verified']
        ];

        $_SESSION['citizen_logged_in'] = true;
        $_SESSION['user_type'] = 'citizen';
        $_SESSION['last_activity'] = time();

        // Clear any staff session if exists
        unset($_SESSION['user_logged_in']);
        unset($_SESSION['user']);
    }

    // Check if citizen is logged in
    public function isCitizenLoggedIn()
    {
        if (isset($_SESSION['citizen_logged_in']) && $_SESSION['citizen_logged_in'] === true) {
            // Check session timeout
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
                $this->logout();
                return false;
            }

            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }

    // Get citizen data
    public function getCitizen()
    {
        return isset($_SESSION['citizen']) ? $_SESSION['citizen'] : null;
    }

    // Get citizen ID
    public function getCitizenId()
    {
        return isset($_SESSION['citizen']['id']) ? $_SESSION['citizen']['id'] : null;
    }

    // ============================================
    // STAFF/ADMIN SESSION METHODS
    // ============================================

    // Set staff session
    public function setStaff($user)
    {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'barangay_id' => $user['barangay_id'],
            'role' => $user['role'],
            'citizen_id' => $user['citizen_id']
        ];

        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_type'] = $user['role']; // 'super_admin' or 'barangay_admin'
        $_SESSION['last_activity'] = time();

        // Clear any citizen session if exists
        unset($_SESSION['citizen_logged_in']);
        unset($_SESSION['citizen']);
    }

    // Check if staff is logged in
    public function isStaffLoggedIn()
    {
        if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
            // Check session timeout
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
                $this->logout();
                return false;
            }

            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }

    // Get staff data
    public function getStaff()
    {
        return isset($_SESSION['user']) ? $_SESSION['user'] : null;
    }

    // Get staff ID
    public function getStaffId()
    {
        return isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
    }

    // Get staff role
    public function getStaffRole()
    {
        return isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
    }

    // Check if user is super admin
    public function isSuperAdmin()
    {
        return $this->isStaffLoggedIn() && $_SESSION['user_type'] === 'super_admin';
    }

    // Check if user is barangay admin
    public function isBarangayAdmin()
    {
        return $this->isStaffLoggedIn() && $_SESSION['user_type'] === 'barangay_admin';
    }

    // ============================================
    // GENERAL SESSION METHODS
    // ============================================

    // Check if any user is logged in (citizen or staff)
    public function isLoggedIn()
    {
        return $this->isCitizenLoggedIn() || $this->isStaffLoggedIn();
    }

    // Get user type
    public function getUserType()
    {
        if ($this->isCitizenLoggedIn()) {
            return 'citizen';
        } elseif ($this->isStaffLoggedIn()) {
            return $_SESSION['user_type'];
        }
        return null;
    }

    // Get user's barangay ID (works for both citizen and staff)
    public function getBarangayId()
    {
        if ($this->isCitizenLoggedIn()) {
            return $this->getCitizen()['barangay_id'] ?? null;
        } elseif ($this->isStaffLoggedIn()) {
            return $this->getStaff()['barangay_id'] ?? null;
        }
        return null;
    }

    // Get user's name (works for both citizen and staff)
    public function getUserName()
    {
        if ($this->isCitizenLoggedIn()) {
            $citizen = $this->getCitizen();
            return $citizen['full_name'] ?? $citizen['first_name'] . ' ' . $citizen['last_name'];
        } elseif ($this->isStaffLoggedIn()) {
            $staff = $this->getStaff();
            return $staff['full_name'] ?? $staff['username'];
        }
        return null;
    }

    // ============================================
    // FLASH MESSAGES & ERRORS
    // ============================================

    // Set flash message
    public function setFlash($type, $message)
    {
        $_SESSION['flash'][$type] = $message;
    }

    // Get flash message
    public function getFlash($type)
    {
        if (isset($_SESSION['flash'][$type])) {
            $message = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        return null;
    }

    // Has flash message
    public function hasFlash($type = null)
    {
        if ($type) {
            return isset($_SESSION['flash'][$type]);
        }
        return !empty($_SESSION['flash']);
    }

    // Set error
    public function setError($error)
    {
        $_SESSION['errors'][] = $error;
    }

    // Get errors
    public function getErrors()
    {
        if (isset($_SESSION['errors'])) {
            $errors = $_SESSION['errors'];
            unset($_SESSION['errors']);
            return $errors;
        }
        return [];
    }

    // Has errors
    public function hasErrors()
    {
        return !empty($_SESSION['errors']);
    }

    // ============================================
    // SESSION MANAGEMENT
    // ============================================

    // Regenerate session ID
    public function regenerate()
    {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }

    // Set session data
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    // Get session data
    public function get($key)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    // Remove session data
    public function remove($key)
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    // Logout
    public function logout()
    {
        // Store any redirect URL before clearing session
        $redirect_url = $_SESSION['redirect_url'] ?? null;

        // Unset all session variables
        $_SESSION = array();

        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Destroy session
        session_destroy();

        // Start new session to store redirect if needed
        session_start();
        if ($redirect_url) {
            $_SESSION['redirect_url'] = $redirect_url;
        }
    }

    // Destroy session completely
    public function destroy()
    {
        session_destroy();
    }

    // ============================================
    // REDIRECT METHODS
    // ============================================

    // Set redirect URL
    public function setRedirect($url)
    {
        $_SESSION['redirect_url'] = $url;
    }

    // Get and clear redirect URL
    public function getRedirect()
    {
        if (isset($_SESSION['redirect_url'])) {
            $url = $_SESSION['redirect_url'];
            unset($_SESSION['redirect_url']);
            return $url;
        }
        return null;
    }

    // Check if user has access to requested page
    public function checkAccess($required_role = null, $redirect_url = 'index.php')
    {
        if (!$this->isLoggedIn()) {
            $this->setRedirect($_SERVER['REQUEST_URI']);
            header("Location: on_boarding.html");
            exit;
        }

        if ($required_role) {
            $user_type = $this->getUserType();

            if ($required_role === 'citizen' && !$this->isCitizenLoggedIn()) {
                $this->setFlash('error', 'Citizen access required');
                header("Location: $redirect_url");
                exit;
            }

            if ($required_role === 'staff' && !$this->isStaffLoggedIn()) {
                $this->setFlash('error', 'Staff access required');
                header("Location: $redirect_url");
                exit;
            }

            if ($required_role === 'super_admin' && !$this->isSuperAdmin()) {
                $this->setFlash('error', 'Super admin access required');
                header("Location: $redirect_url");
                exit;
            }

            if ($required_role === 'barangay_admin' && !$this->isBarangayAdmin()) {
                $this->setFlash('error', 'Barangay admin access required');
                header("Location: $redirect_url");
                exit;
            }
        }
    }
}
