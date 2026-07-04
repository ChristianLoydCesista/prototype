<?php
class Session
{

    // Define a fallback timeout just in case global constant are not set or missing
    private const DEFAULT_SESSION_TIMEOUT = 1800; // 30 minutes

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start([
                'use_strict_mode' => 1,
                'cookie_httponly' => 1,
                'cookie_samesite' => 1
            ]);
        }

        $this->enforceSecurityProtocols();

        /* Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }*/
    }

    // ============================================
    // HANDLE SESSION REGISTRATION AND SECURITY
    // ============================================
    private function enforceSecurityProtocols() {
        $currentTime = time();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // Browser Fingerprinting (Anti-Hijacking)
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $userAgent;
        } elseif ($_SESSION['user_agent'] !== $userAgent) {
            // if the browser changes mid-session, destroy it immediately
            $this->destroy();
            return;
        }

        // Periodic Session Regeneration
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = $currentTime;
        } else if ($currentTime - $_SESSION['created'] > self::DEFAULT_SESSION_TIMEOUT) {
            session_regenerate_id(true);
            $_SESSION['created'] = $currentTime;
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
        unset($_SESSION['user_logged_in'], $_SESSION['user']);
    }

    // Check if citizen is logged in
    public function isCitizenLoggedIn()
    {
        if (isset($_SESSION['citizen_logged_in']) && $_SESSION['citizen_logged_in'] === true) {
            // Check session timeout
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : self::DEFAULT_SESSION_TIMEOUT;

            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
                $this->logout();
                return false;
            }

            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }

    // Get citizen data
    public function getCitizen(): ?array
    {
        return $_SESSION['citizen'] ?? null;
    }

   public function getCitizenId(): ?int
    {
        return $_SESSION['citizen']['id'] ?? null;
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
        unset($_SESSION['citizen_logged_in'], $_SESSION['citizen']);
    }

    // Check if staff is logged in
    public function isStaffLoggedIn()
    {
        if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
            // Check session timeout
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : self::DEFAULT_SESSION_TIMEOUT;
            
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
                $this->logout();
                return false;
            }

            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }

    // Get staff data
    public function getStaff(): ?array
    {
        return isset($_SESSION['user']) ? $_SESSION['user'] : null;
    }

    // Get staff ID
    public function getStaffId(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    // Get staff role
    public function getStaffRole(): ?string
    {
        return $_SESSION['user_type'] ?? null;
    }

    // Check if user is super admin
    public function isSuperAdmin(): bool
    {
        return $this->isStaffLoggedIn() && $_SESSION['user_type'] === 'super_admin';
    }

    // Check if user is barangay admin
    public function isBarangayAdmin(): bool
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
    public function getUserType(): ?string
    {
        if ($this->isCitizenLoggedIn()) {
            return 'citizen';
        } elseif ($this->isStaffLoggedIn()) {
            return $_SESSION['user_type'] ?? null;
        }
        return null;
    }

    // Get user's barangay ID (works for both citizen and staff)
    public function getBarangayId(): ?int
    {
        if ($this->isCitizenLoggedIn()) {
            return $this->getCitizen()['barangay_id'] ?? null;
        } elseif ($this->isStaffLoggedIn()) {
            return $this->getStaff()['barangay_id'] ?? null;
        }
        return null;
    }

    // Get user's name (works for both citizen and staff)
    public function getUserName(): ?string
    {
        if ($this->isCitizenLoggedIn()) {
            $citizen = $this->getCitizen();
            return $citizen['full_name'] ?? ($citizen['first_name'] . ' ' . $citizen['last_name']);
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
    public function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    // Get flash message
    public function getFlash(string $type): ?string
    {
        if (isset($_SESSION['flash'][$type])) {
            $message = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        return null;
    }

    // Has flash message
   public function hasFlash(?string $type = null): bool
    {
        if ($type) {
            return isset($_SESSION['flash'][$type]);
        }
        return !empty($_SESSION['flash']);
    }

    // Set error
    public function setError(string $error): void
    {
        $_SESSION['errors'][] = $error;
    }

    // Get errors
    public function getErrors(): array
    {
        if (isset($_SESSION['errors'])) {
            $errors = $_SESSION['errors'];
            unset($_SESSION['errors']);
            return $errors;
        }
        return [];
    }

    // Has errors
    public function hasErrors(): bool
    {
        return !empty($_SESSION['errors']);
    }

    // ============================================
    // SESSION MANAGEMENT
    // ============================================

    // Regenerate session ID
    public function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }

    // Set session data
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    // Get session data
   public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    // Remove session data
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    // Logout
    public function logout(): void
    {
        $redirect_url = $_SESSION['redirect_url'] ?? null;
        $flash = $_SESSION['flash'] ?? null; // Preserve flash messages across logout

        $_SESSION = [];

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

        session_destroy();
        session_start();

        if ($redirect_url) {
            $_SESSION['redirect_url'] = $redirect_url;
        }
        if ($flash) {
            $_SESSION['flash'] = $flash;
        }
    }

    // Destroy session completely
   public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
    }

    // ============================================
    // REDIRECT METHODS
    // ============================================

    // Set redirect URL
    public function setRedirect(string $url): void
    {
        $_SESSION['redirect_url'] = $url;
    }

    // Get and clear redirect URL
    public function getRedirect(): ?string
    {
        if (isset($_SESSION['redirect_url'])) {
            $url = $_SESSION['redirect_url'];
            unset($_SESSION['redirect_url']);
            return $url;
        }
        return null;
    }

    // Check if user has access to requested page
    public function checkAccess(?string $required_role = null, string $redirect_url = 'index.php'): void
    {
        if (!$this->isLoggedIn()) {
            $this->setRedirect($_SERVER['REQUEST_URI']);
            header("Location: on_boarding.html");
            exit;
        }

        if ($required_role) {
            $role_met = false;
            $error_msg = '';

            switch ($required_role) {
                case 'citizen':
                    $role_met = $this->isCitizenLoggedIn();
                    $error_msg = 'Citizen access required';
                    break;
                case 'staff':
                    $role_met = $this->isStaffLoggedIn();
                    $error_msg = 'Staff access required';
                    break;
                case 'super_admin':
                    $role_met = $this->isSuperAdmin();
                    $error_msg = 'Super admin access required';
                    break;
                case 'barangay_admin':
                    $role_met = $this->isBarangayAdmin();
                    $error_msg = 'Barangay admin access required';
                    break;
            }

            if (!$role_met) {
                $this->setFlash('error', $error_msg);
                header("Location: $redirect_url");
                exit;
            }
        }
    }
}
