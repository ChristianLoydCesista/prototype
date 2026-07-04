<?php
// includes/Auth.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/Mailer.php';

class Auth
{
    private $db;
    private $errors = [];
    private $mailer;

    private $maxAttempts;
    private $lockoutSeconds;

    public function __construct()
    {
        $this->db = getDB();
        $this->mailer = new Mailer();

        // Use constants if defined, otherwise fallback to secure defaults
        $this->maxAttempts   = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
        $this->lockoutSeconds = defined('LOCKOUT_SECONDS') ? LOCKOUT_SECONDS : 900;
    }



    // Register new citizen
    public function register($data)
    {
        $this->errors = [];

        // Validate inputs
        if (!$this->validateRegistration($data)) {
            return false;
        }

        // Check if email/phone already exists
        if ($this->citizenExists($data['email'], $data['phone'])) {
            $this->errors[] = 'Email or phone number already registered';
            return false;
        }

        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);

        // Generate verification code
        $verificationCode = $this->generateVerificationCode();

        // Insert citizen
        $middleName = $data['middle_name'] ?? '';
        $activeStatus = 'Active';

        $stmt = $this->db->prepare("
            INSERT INTO citizens (email,
                                phone,
                                password,
                                first_name,
                                last_name,
                                middle_name,
                                birth_date,
                                address, 
                                barangay_id,
                                verification_code,
                                account_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssssssiss",
            $data['email'],
            $data['phone'],
            $hashedPassword,
            $data['first_name'],
            $data['last_name'],
            $middleName,
            $data['birth_date'],
            $data['address'],
            $data['barangay_id'],
            $verificationCode,
            $activeStatus
        );

        if ($stmt->execute()) {
            $citizenId = $stmt->insert_id;
            $stmt->close();

            // Send verification email/SMS (placeholder)
            $this->sendVerification($data['email'], $data['phone'], $verificationCode);

            return $citizenId;
        }

        $this->errors[] = 'Registration failed. Please try again.';
        return false;
    }

    // ============= LOGIN WITH RATE LIMITING & GENERIC ERRORS =============

     public function login($emailOrPhone, $password)
    {
        $this->errors = [];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        try {
            // 1. Find citizen by email or phone
            $stmt = $this->db->prepare("
                SELECT id, email, phone, password, first_name, last_name,
                       is_verified, barangay_id,
                       failed_attempts, locked_until
                FROM citizens
                WHERE email = ? OR phone = ?
            ");
            $stmt->bind_param("ss", $emailOrPhone, $emailOrPhone);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                // Generic message – do NOT reveal existence
                $this->errors[] = 'Invalid credentials';
                $this->logFailedAttempt($emailOrPhone, $ip, 'User not found');
                $stmt->close();
                return false;
            }

            $citizen = $result->fetch_assoc();
            $stmt->close();

            // 2. Check if account is locked
            if ($citizen['locked_until'] !== null && strtotime($citizen['locked_until']) > time()) {
                $this->errors[] = 'Account temporarily locked. Please try again later.';
                $this->logFailedAttempt($emailOrPhone, $ip, 'Account locked');
                return false;
            }

            // 3. Verify password
            if (!password_verify($password, $citizen['password'])) {
                $this->incrementFailedAttempts($citizen['id']);
                $this->errors[] = 'Invalid credentials';
                $this->logFailedAttempt($emailOrPhone, $ip, 'Invalid password');
                return false;
            }

            // 4. Check if account is verified
            if (!$citizen['is_verified']) {
                $this->errors[] = 'Please verify your account first.';
                $this->logFailedAttempt($emailOrPhone, $ip, 'Unverified account');
                return false;
            }

            // 5. Success – reset attempts, update last login
            $this->resetFailedAttempts($citizen['id']);
            $this->updateLastLogin($citizen['id']);

            // 6. Re‑hash password if algorithm/cost changed
            if (password_needs_rehash($citizen['password'], PASSWORD_BCRYPT, ['cost' => PASSWORD_COST])) {
                $this->updatePasswordHash($citizen['id'], $password);
            }

            // 7. Log success
            error_log(sprintf(
                "[%s] Successful login: %s (ID: %d) from IP %s",
                date('Y-m-d H:i:s'),
                $citizen['email'],
                $citizen['id'],
                $ip
            ) . "\n", 3, LOG_PATH . '/auth.log');

            return $citizen;
        } catch (mysqli_sql_exception $e) {
            error_log("Auth::login DB error: " . $e->getMessage());
            $this->errors[] = 'System error. Please try again later.';
            return false;
        }
    }

    // ============= RATE‑LIMITING HELPERS =============

    private function incrementFailedAttempts($citizenId)
    {
        $stmt = $this->db->prepare("
            UPDATE citizens
            SET failed_attempts = failed_attempts + 1,
                last_failed_attempt = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("i", $citizenId);
        $stmt->execute();
        $stmt->close();

        // Check if threshold reached → lock account
        $stmt = $this->db->prepare("
            UPDATE citizens
            SET locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE id = ? AND failed_attempts >= ?
        ");
        $stmt->bind_param("iii", $this->lockoutSeconds, $citizenId, $this->maxAttempts);
        $stmt->execute();
        $stmt->close();
    }

    private function resetFailedAttempts($citizenId)
    {
        $stmt = $this->db->prepare("
            UPDATE citizens
            SET failed_attempts = 0,
                last_failed_attempt = NULL,
                locked_until = NULL
            WHERE id = ?
        ");
        $stmt->bind_param("i", $citizenId);
        $stmt->execute();
        $stmt->close();
    }

    private function updateLastLogin($citizenId)
    {
        $stmt = $this->db->prepare("UPDATE citizens SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param("i", $citizenId);
        $stmt->execute();
        $stmt->close();
    }

    private function updatePasswordHash($citizenId, $plainPassword)
    {
        $newHash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
        $stmt = $this->db->prepare("UPDATE citizens SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $newHash, $citizenId);
        $stmt->execute();
        $stmt->close();
    }

    private function logFailedAttempt($identifier, $ip, $reason)
    {
        $msg = sprintf(
            "[%s] Failed login attempt: %s (IP: %s) - %s",
            date('Y-m-d H:i:s'),
            $identifier,
            $ip,
            $reason
        );
        error_log($msg . "\n", 3, LOG_PATH . '/auth.log');
    }

    // ============= REMEMBER ME =============

    public function createRememberToken($citizenId)
    {
        $token = bin2hex(random_bytes(32));
        $hash = password_hash($token, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE citizen_id = ?");
        $stmt->bind_param("i", $citizenId);
        $stmt->execute();
        $stmt->close();

        $stmt = $this->db->prepare("INSERT INTO remember_tokens (citizen_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $citizenId, $hash, $expires);
        $stmt->execute();
        $stmt->close();

        return $token;
    }

     public function loginWithRememberToken($token)
    {
        $stmt = $this->db->prepare("
            SELECT citizen_id, token_hash
            FROM remember_tokens
            WHERE expires_at > NOW()
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        while ($row = $result->fetch_assoc()) {
            if (password_verify($token, $row['token_hash'])) {
                return $this->getCitizen($row['citizen_id']);
            }
        }
        return false;
    }

    public function rotateRememberToken($citizenId)
    {
        return $this->createRememberToken($citizenId);
    }

    // Verify account
    public function verifyAccount($email, $code)
    {
        $stmt = $this->db->prepare("
            UPDATE citizens 
            SET is_verified = TRUE, verification_code = NULL 
            WHERE email = ? AND verification_code = ? AND is_verified = FALSE
        ");

        $stmt->bind_param("ss", $email, $code);
        $success = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    // Send password reset
    public function sendPasswordReset($email)
    {
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store token in database (create password_resets table)
        $stmt = $this->db->prepare("
            INSERT INTO password_resets (email, token, expires_at) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
        ");

        $stmt->bind_param("sssss", $email, $token, $expires, $token, $expires);
        $stmt->execute();
        $stmt->close();

        // Send reset email (placeholder)
        // $resetLink = SITE_URL . "citizen_reset_password.php?token=" . $token;
        $resetLink = rtrim(SITE_URL, '/') . '/citizen_forgot_password.php?token=' . urlencode($token);
        return $resetLink;
    }

    // Reset password
    public function resetPassword($token, $newPassword)
    {
        // Verify token
        $stmt = $this->db->prepare("
            SELECT email FROM password_resets 
            WHERE token = ? AND expires_at > NOW()
        ");

        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            $this->errors[] = 'Invalid or expired token';
            return false;
        }

        $row = $result->fetch_assoc();
        $email = $row['email'];
        $stmt->close();

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);

        $stmt = $this->db->prepare("
            UPDATE citizens SET password = ? WHERE email = ?
        ");

        $stmt->bind_param("ss", $hashedPassword, $email);
        $success = $stmt->execute();
        $stmt->close();

        // Delete used token
        if ($success) {
            $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->close();
        }

        return $success;
    }

    // Get citizen by ID
    public function getCitizen($id)
    {
        $stmt = $this->db->prepare("
            SELECT c.*, b.name as barangay_name 
            FROM citizens c 
            LEFT JOIN barangays b ON c.barangay_id = b.id 
            WHERE c.id = ?
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $citizen = $result->fetch_assoc();
        $stmt->close();

        return $citizen;
    }

    // Update profile
    public function updateProfile($citizenId, $data)
    {
        $middleName = $data['middle_name'] ?? '';

        $stmt = $this->db->prepare("
            UPDATE citizens
            SET first_name = ?, last_name = ?, middle_name = ?,
                birth_date = ?, address = ?, barangay_id = ?, phone = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sssssisi",
            $data['first_name'],
            $data['last_name'],
            $middleName,
            $data['birth_date'],
            $data['address'],
            $data['barangay_id'],
            $data['phone'],
            $citizenId
        );

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    // Change password
    public function changePassword($citizenId, $currentPassword, $newPassword)
    {
        // Get current password hash
        $stmt = $this->db->prepare("SELECT password FROM citizens WHERE id = ?");
        $stmt->bind_param("i", $citizenId);
        $stmt->execute();
        $result = $stmt->get_result();
        $citizen = $result->fetch_assoc();
        $stmt->close();

        // Verify current password
        if (!password_verify($currentPassword, $citizen['password'])) {
            $this->errors[] = 'Current password is incorrect';
            return false;
        }

        // Update to new password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);

        $stmt = $this->db->prepare("UPDATE citizens SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $citizenId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    // ============= NEW FUNCTIONS FOR CITIZEN PROFILE =============

    /**
     * Verify citizen password for profile changes
     * @param int $citizenId
     * @param string $password
     * @return bool
     */
    public function verifyCitizenPassword($citizenId, $password)
    {
        $stmt = $this->db->prepare("SELECT password FROM citizens WHERE id = ?");
        $stmt->bind_param("i", $citizenId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return false;
        }

        $citizen = $result->fetch_assoc();
        $stmt->close();

        return password_verify($password, $citizen['password']);
    }

    /**
     * Update citizen password
     * @param int $citizenId
     * @param string $newPassword
     * @return bool
     */
    public function updateCitizenPassword($citizenId, $newPassword)
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);

        $stmt = $this->db->prepare("UPDATE citizens SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $citizenId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Update citizen avatar
     * @param int $citizenId
     * @param string $avatarFilename
     * @return bool
     */
    public function updateAvatar($citizenId, $avatarFilename)
    {
        $stmt = $this->db->prepare("UPDATE citizens SET avatar = ? WHERE id = ?");
        $stmt->bind_param("si", $avatarFilename, $citizenId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Get citizen by email
     * @param string $email
     * @return array|null
     */
    public function getCitizenByEmail($email)
    {
        $stmt = $this->db->prepare("
            SELECT c.*, b.name as barangay_name 
            FROM citizens c 
            LEFT JOIN barangays b ON c.barangay_id = b.id 
            WHERE c.email = ?
        ");

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $citizen = $result->fetch_assoc();
        $stmt->close();

        return $citizen;
    }

    /**
     * Get citizen by phone
     * @param string $phone
     * @return array|null
     */
    public function getCitizenByPhone($phone)
    {
        $stmt = $this->db->prepare("
            SELECT c.*, b.name as barangay_name 
            FROM citizens c 
            LEFT JOIN barangays b ON c.barangay_id = b.id 
            WHERE c.phone = ?
        ");

        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        $citizen = $result->fetch_assoc();
        $stmt->close();

        return $citizen;
    }

    /**
     * Update profile with all fields (extended version)
     * @param int $citizenId
     * @param array $data
     * @return bool
     */
    public function updateFullProfile($citizenId, $data)
    {
        $allowedFields = [
            'first_name',
            'last_name',
            'middle_name',
            'suffix',
            'email',
            'phone',
            'birth_date',
            'gender',
            'address',
            'barangay',
            'civil_status',
            'occupation'
        ];

        $sets = [];
        $params = [];
        $types = "";

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = ?";
                $params[] = $data[$field];
                $types .= "s"; // All strings for simplicity
            }
        }

        if (empty($sets)) {
            return false;
        }

        // Add updated_at
        $sets[] = "updated_at = NOW()";

        // Add citizen_id at the end for WHERE clause
        $params[] = $citizenId;
        $types .= "i";

        $sql = "UPDATE citizens SET " . implode(", ", $sets) . " WHERE id = ?";

        $stmt = $this->db->prepare($sql);

        // Dynamic binding using call_user_func_array
        $bindParams = array_merge([$types], $params);
        $stmt->bind_param(...$bindParams);

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Check if email exists for another user
     * @param string $email
     * @param int $excludeId
     * @return bool
     */
    public function emailExists($email, $excludeId = null)
    {
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT id FROM citizens WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $excludeId);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM citizens WHERE email = ?");
            $stmt->bind_param("s", $email);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Check if phone exists for another user
     * @param string $phone
     * @param int $excludeId
     * @return bool
     */
    public function phoneExists($phone, $excludeId = null)
    {
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT id FROM citizens WHERE phone = ? AND id != ?");
            $stmt->bind_param("si", $phone, $excludeId);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM citizens WHERE phone = ?");
            $stmt->bind_param("s", $phone);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Get activity logs for citizen
     * @param int $citizenId
     * @param int $limit
     * @return array
     */
    public function getActivityLogs($citizenId, $limit = 10)
    {
        // Check if activity_logs table exists
        $tableCheck = $this->db->query("SHOW TABLES LIKE 'activity_logs'");
        if ($tableCheck->num_rows === 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT * FROM activity_logs 
            WHERE citizen_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");

        $stmt->bind_param("ii", $citizenId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $logs;
    }

    /**
     * Log citizen activity
     * @param int $citizenId
     * @param string $action
     * @param string $description
     * @return bool
     */
    public function logActivity($citizenId, $action, $description)
    {
        // Check if activity_logs table exists
        $tableCheck = $this->db->query("SHOW TABLES LIKE 'activity_logs'");
        if ($tableCheck->num_rows === 0) {
            return false;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (citizen_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("issss", $citizenId, $action, $description, $ip, $userAgent);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    // ============= PRIVATE HELPER METHODS =============

    private function validateRegistration($data)
    {
        error_log("DEBUG validateRegistration called");

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = 'Valid email is required';
            error_log("Email validation failed");
        }

        if (empty($data['phone']) || !preg_match('/^09[0-9]{9}$/', $data['phone'])) {
            $this->errors[] = 'Valid Philippine mobile number is required (09XXXXXXXXX)';
            error_log("Phone validation failed");
        }

        if (empty($data['password']) || strlen($data['password']) < 8) {
            $this->errors[] = 'Password must be at least 8 characters';
            error_log("Password length validation failed: " . strlen($data['password']));
        }

        // Debug password comparison
        error_log("Password compare: [" . $data['password'] . "] vs [" . $data['confirm_password'] . "]");

        if ($data['password'] !== $data['confirm_password']) {
            $this->errors[] = 'Passwords do not match';
            error_log("Password match validation failed");
        }

        if (empty($data['first_name']) || empty($data['last_name'])) {
            $this->errors[] = 'First name and last name are required';
            error_log("Name validation failed");
        }

        if (empty($data['birth_date']) || strtotime($data['birth_date']) > strtotime('-13 years')) {
            $this->errors[] = 'You must be at least 13 years old';
            error_log("Age validation failed");
        }

        if (empty($data['barangay_id'])) {
            $this->errors[] = 'Please select your barangay';
            error_log("Barangay validation failed");
        }

        error_log("Validation errors: " . print_r($this->errors, true));
        return empty($this->errors);
    }

    private function citizenExists($email, $phone)
    {
        $stmt = $this->db->prepare("
            SELECT id FROM citizens WHERE email = ? OR phone = ?
        ");

        $stmt->bind_param("ss", $email, $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    private function generateVerificationCode()
    {
        return strtoupper(substr(md5(uniqid()), 0, 6));
    }

    // Send verification email using Mailer class
    private function sendVerification($email, $phone, $code)
    {
        // Store in session for demo display (backup)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['demo_verification_code'] = $code;
        $_SESSION['demo_email'] = $email;
        $_SESSION['demo_phone'] = $phone;

        // Get user name from registration data if available
        $name = $_SESSION['registration_first_name'] ?? 'Citizen';

        // Send real verification email
        $result = $this->mailer->sendVerificationEmail($email, $name, $code);

        // Log the email sending
        if ($result) {
            error_log("Email verification sent to: $email");
        } else {
            error_log("Failed to send email verification to: $email");
        }

        return $result;
    }

    // Get errors
    public function getErrors()
    {
        return $this->errors;
    }
}
