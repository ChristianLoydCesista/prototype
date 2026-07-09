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

    public function __construct()
    {
        $this->db = getDB();
        $this->mailer = new Mailer();
    }

    // Register new citizen
    public function register($data)
    {
        $this->errors = [];

        if (!$this->validateRegistration($data)) {
            return false;
        }

        if ($this->citizenExists($data['email'], $data['phone'])) {
            $this->errors[] = 'Email or phone number already registered';
            return false;
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
        $verificationCode = $this->generateVerificationCode();
        $middleName = $data['middle_name'] ?? '';
        $activeStatus = 'Inactive';

        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare("
            INSERT INTO citizens (
                email, phone, password, first_name, last_name, middle_name,
                birth_date, address, barangay_id, verification_code,
                verification_created_at, verification_attempts,
                last_verification_sent, account_status, is_verified
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, NOW(), ?, 0)
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

            if (!$stmt->execute()) {
                throw new Exception('Citizen insert failed');
            }

            $citizenId = $stmt->insert_id;
            $stmt->close();

            $fullName = trim($data['first_name'] . ' ' . $data['last_name']);

            $emailSent = $this->mailer->sendVerificationEmail(
                $data['email'],
                $fullName,
                $verificationCode
            );

            if (!$emailSent) {
                throw new Exception('Verification email failed to send');
            }

            $_SESSION['verification_email'] = $data['email'];
            $_SESSION['verification_phone'] = $data['phone'];

            if (defined('APP_ENV') && APP_ENV === 'development') {
                $_SESSION['demo_verification_code'] = $verificationCode;
            }

            $this->db->commit();
            return $citizenId;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Registration error: ' . $e->getMessage());
            $this->errors[] = 'Registration failed. Please check your email settings and try again.';
            return false;
        }
    }

    // Login citizen - CORRECTED VERSION
    public function login($emailOrPhone, $password)
    {
        $this->errors = [];

        // Find citizen by email or phone - FIXED QUERY
        $stmt = $this->db->prepare("
        SELECT id, email, phone, password, first_name, last_name, is_verified, barangay_id
        FROM citizens 
        WHERE (email = ? OR phone = ?)
        ");

        $stmt->bind_param("ss", $emailOrPhone, $emailOrPhone);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $this->errors[] = 'Account not found';
            $stmt->close();
            return false;
        }

        $citizen = $result->fetch_assoc();

        // Verify password
        if (!password_verify($password, $citizen['password'])) {
            $this->errors[] = 'Invalid password';
            $stmt->close();
            return false;
        }

        // Check if account is verified
        if (!$citizen['is_verified']) {
            $this->errors[] = 'Please verify your email/phone first';
            $stmt->close();
            return false;
        }

        $stmt->close();

        // Update last login
        $this->updateLastLogin($citizen['id']);

        return $citizen;
    }

    // Verify account
    public function verifyAccount($email, $code)
    {
        $this->errors = [];

        $code = trim($code);

        if (!preg_match('/^[0-9]{6}$/', $code)) {
            $this->errors[] = 'Invalid verification code format';
            return false;
        }

        $stmt = $this->db->prepare("
        SELECT id, verification_code, verification_created_at, verification_attempts, is_verified, account_status
        FROM citizens
        WHERE email = ?
        LIMIT 1
    ");

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $citizen = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$citizen) {
            $this->errors[] = 'Account not found';
            return false;
        }

        if ((int)$citizen['is_verified'] === 1) {
            $this->errors[] = 'Account is already verified';
            return false;
        }

        if ((int)$citizen['verification_attempts'] >= 5) {
            $this->errors[] = 'Too many failed attempts. Please request a new code.';
            return false;
        }

        if (empty($citizen['verification_created_at']) || strtotime($citizen['verification_created_at']) < strtotime('-24 hours')) {
            $this->errors[] = 'Verification code expired. Please request a new code.';
            return false;
        }

        if (!hash_equals($citizen['verification_code'], $code)) {
            $stmt = $this->db->prepare("
            UPDATE citizens 
            SET verification_attempts = verification_attempts + 1 
            WHERE id = ?
        ");
            $stmt->bind_param("i", $citizen['id']);
            $stmt->execute();
            $stmt->close();

            $this->errors[] = 'Invalid verification code';
            return false;
        }

        $stmt = $this->db->prepare("
        UPDATE citizens
        SET is_verified = 1,
            account_status = 'Active',
            verification_code = NULL,
            verification_created_at = NULL,
            verification_attempts = 0
        WHERE id = ?
    ");

        $stmt->bind_param("i", $citizen['id']);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
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

    // Create remember me token
    public function createRememberToken($citizenId)
    {
        $selector = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $this->db->prepare("
        INSERT INTO remember_tokens (citizen_id, selector, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
        $stmt->bind_param("isss", $citizenId, $selector, $tokenHash, $expires);
        $stmt->execute();
        $stmt->close();

        return $selector . ':' . $token;
    }

    public function loginWithRememberToken($cookieValue)
    {
        if (strpos($cookieValue, ':') === false) {
            return false;
        }

        [$selector, $token] = explode(':', $cookieValue, 2);
        $tokenHash = hash('sha256', $token);

        $stmt = $this->db->prepare("
        SELECT rt.id, rt.token_hash, c.*
        FROM remember_tokens rt
        JOIN citizens c ON rt.citizen_id = c.id
        WHERE rt.selector = ?
          AND rt.expires_at > NOW()
          AND c.is_verified = 1
          AND c.account_status = 'Active'
        LIMIT 1
    ");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !hash_equals($row['token_hash'], $tokenHash)) {
            return false;
        }

        return $row;
    }

    public function deleteRememberToken($cookieValue)
    {
        if (strpos($cookieValue, ':') === false) {
            return;
        }

        [$selector] = explode(':', $cookieValue, 2);

        $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE selector = ?");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $stmt->close();
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
        return (string) random_int(100000, 999999);
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

    private function updateLastLogin($citizenId)
    {
        $stmt = $this->db->prepare("
            UPDATE citizens SET last_login = NOW() WHERE id = ?
        ");

        $stmt->bind_param("i", $citizenId);
        $stmt->execute();
        $stmt->close();
    }

    // Get errors
    public function getErrors()
    {
        return $this->errors;
    }
}
