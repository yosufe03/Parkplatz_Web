<?php
/**
 * Security Utilities
 * CSRF tokens, password validation, and other security helpers
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Generate a CSRF token and store it in session
 * @return string The CSRF token
 */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token from POST/GET request
 * @param string|null $token The token to verify (from $_POST or $_GET)
 * @return bool True if token is valid, false otherwise
 */
function verify_csrf_token($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate CSRF token on POST/PUT/DELETE requests
 * Sets error in session and returns false if invalid
 * @return bool True if valid or not a POST request, false if invalid CSRF token
 */
function validate_csrf_on_post() {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
        return true;
    }

    if (!verify_csrf_token()) {
        $_SESSION['error'] = 'Sicherheitstoken ungültig. Bitte versuchen Sie es erneut.';
        return false;
    }

    return true;
}

/**
 * Output a hidden CSRF token field for forms
 * @return string HTML hidden input field
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validate password strength
 * Requirements:
 * - Minimum 8 characters
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one number
 *
 * @param string $password The password to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_password_strength($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = "Passwort muss mindestens 8 Zeichen lang sein.";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Passwort muss mindestens einen Großbuchstaben enthalten.";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Passwort muss mindestens einen Kleinbuchstaben enthalten.";
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Passwort muss mindestens eine Zahl enthalten.";
    }

    return [
        'valid' => empty($errors),
        'message' => !empty($errors) ? implode(" ", $errors) : "Passwort erfüllt die Anforderungen."
    ];
}

/**
 * Sanitize email address
 * @param string $email The email to sanitize
 * @return string Sanitized email
 */
function sanitize_email($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

/**
 * Validate email format
 * @param string $email The email to validate
 * @return bool True if valid email format, false otherwise
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitize username
 * @param string $username The username to sanitize
 * @return string Sanitized username
 */
function sanitize_username($username) {
    // Allow alphanumeric, underscore, hyphen
    return preg_replace('/[^a-zA-Z0-9_-]/', '', trim($username));
}

/**
 * Validate username format
 * @param string $username The username to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_username($username) {
    $username = trim($username);

    if (strlen($username) < 3) {
        return ['valid' => false, 'message' => "Benutzername muss mindestens 3 Zeichen lang sein."];
    }

    if (strlen($username) > 50) {
        return ['valid' => false, 'message' => "Benutzername darf maximal 50 Zeichen lang sein."];
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        return ['valid' => false, 'message' => "Benutzername darf nur Buchstaben, Zahlen, Unterstriche und Bindestriche enthalten."];
    }

    return ['valid' => true, 'message' => "Benutzername ist gültig."];
}

/**
 * Escape HTML output safely
 * @param mixed $value The value to escape
 * @return string Escaped value
 */
function esc_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Escape HTML attributes
 * @param mixed $value The value to escape
 * @return string Escaped value for use in HTML attributes
 */
function esc_attr($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Rate limiting helper - checks if an action has been attempted too many times
 * @param string $action The action identifier (e.g., 'login_attempt')
 * @param int $max_attempts Maximum attempts allowed
 * @param int $time_window Time window in seconds
 * @return bool True if limit exceeded, false otherwise
 */
function is_rate_limited($action, $max_attempts = 5, $time_window = 300) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = "rate_limit_" . $action;

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time()];
    }

    $data = &$_SESSION[$key];

    // Reset if time window has passed
    if (time() - $data['first_attempt'] > $time_window) {
        $data = ['attempts' => 1, 'first_attempt' => time()];
        return false;
    }

    // Check if limit exceeded
    if ($data['attempts'] >= $max_attempts) {
        return true;
    }

    $data['attempts']++;
    return false;
}

/**
 * Clear rate limit for an action
 * @param string $action The action identifier
 */
function clear_rate_limit($action) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = "rate_limit_" . $action;
    unset($_SESSION[$key]);
}

/**
 * Check if user is admin by user ID
 * @param int $user_id The user ID to check
 * @return bool True if user is admin, false otherwise
 */
function is_admin($user_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row && $row['role'] === 'admin';
}

