<?php
session_start();
require_once 'db_connection.php'; // Provides $pdo
require_once 'dal.php'; // For logActivity

// Redirect to login if accessed directly without POST or if already logged in
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}
if (isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Already logged in
    exit;
}

$email = $_POST['username'] ?? ''; // The form field is named 'username' but will contain the email
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $_SESSION['login_error_message'] = 'Email and password are required.';
    header('Location: login.php');
    exit;
}

try {
    // Fetch user and associated school details
    $stmt = $pdo->prepare("
        SELECT
            u.id AS user_id,
            u.full_name,
            u.email,
            u.password_hash,
            u.role,
            u.school_id,
            s.name AS school_name,
            s.school_type,
            s.logo_path AS school_logo_path,
            s.motto AS school_motto
        FROM users u
        LEFT JOIN schools s ON u.school_id = s.id
        WHERE u.email = :email
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data && password_verify($password, $user_data['password_hash'])) {
        // Password is correct, start session
        session_regenerate_id(true); // Regenerate session ID for security

        $_SESSION['user_id'] = $user_data['user_id'];
        $_SESSION['full_name'] = $user_data['full_name']; // Using full_name for display
        $_SESSION['email'] = $user_data['email']; // Storing email as it's the login identifier
        $_SESSION['role'] = $user_data['role'];

        // Store school-specific information if the user is associated with a school
        if ($user_data['school_id']) {
            $_SESSION['school_id'] = $user_data['school_id'];
            $_SESSION['school_name'] = $user_data['school_name'];
            $_SESSION['school_type'] = $user_data['school_type'];
            $_SESSION['school_logo_path'] = $user_data['school_logo_path'];
            $_SESSION['school_motto'] = $user_data['school_motto'];
        } else {
            // For superadmins or users not tied to a specific school
            $_SESSION['school_id'] = null;
            $_SESSION['school_name'] = 'Arturo Global Admin'; // Or some other appropriate default
            $_SESSION['school_type'] = null;
            $_SESSION['school_logo_path'] = null; // Or a default admin logo
            $_SESSION['school_motto'] = null;
        }

        // Log activity
        logActivity(
            $pdo,
            $user_data['user_id'],
            $user_data['email'], // Using email for username in log
            'USER_LOGIN',
            "User '" . $user_data['email'] . "' logged in successfully."
        );

        // Redirect to dashboard or intended page
        header('Location: index.php');
        exit;
    } else {
        // Invalid credentials
        $_SESSION['login_error_message'] = 'Invalid email or password.';
        header('Location: login.php');
        exit;
    }

} catch (PDOException $e) {
    // Log error and set a generic error message for the user
    error_log("Login Error: " . $e->getMessage());
    $_SESSION['login_error_message'] = 'An error occurred during login. Please try again later.';
    header('Location: login.php');
    exit;
}
?>
