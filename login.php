<?php
session_start();
$error_message = $_SESSION['login_error_message'] ?? null;
unset($_SESSION['login_error_message']); // Clear error after displaying

$success_message = $_SESSION['success_message'] ?? null; // For messages like "Password updated successfully"
unset($_SESSION['success_message']);

// If user is already logged in, redirect them to index.php
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Arturomania Systems</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Assuming aslogo.png will be in the root or an 'images' folder -->
    <link rel="icon" type="image/png" href="aslogo.png">
    <style>
        body {
            /* Using a gradient similar to welcome.php for consistency */
            background: linear-gradient(to right, #6a11cb, #2575fc);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .login-container {
            background-color: #fff;
            padding: 40px; /* Consistent padding */
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header img {
            width: 70px; /* Adjusted logo size */
            margin-bottom: 10px;
        }
        .login-header h2 {
            color: #333; /* Standard dark color */
            font-weight: 600;
            font-size: 1.8rem;
        }
        .form-floating label {
            padding-left: 0.5rem;
        }
        .btn-primary {
            background-color: #2575fc; /* Main theme color */
            border-color: #2575fc;
            padding: 12px;
            font-size: 1.1rem;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #1a5db3; /* Darker shade for hover */
            border-color: #1a5db3;
        }
        .form-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            font-size: 0.9em;
        }
        .form-links a {
            color: #2575fc;
            text-decoration: none;
        }
        .form-links a:hover {
            text-decoration: underline;
        }
        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <!-- Placeholder for logo, assuming aslogo.png is accessible -->
            <img src="aslogo.png" alt="Arturomania Systems Logo" onerror="this.src='images/logo_placeholder.png'; this.alt='Arturomania Systems Logo';">
            <h2>Arturomania Systems</h2>
            <p class="text-muted">Sign in to manage your school</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form action="handle_login.php" method="post">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username or Email" required autofocus>
                <label for="username"><i class="fas fa-user me-2"></i>Username or Email</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
            </div>
            <button class="w-100 btn btn-lg btn-primary" type="submit"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
            <div class="form-links">
                <a href="forgot_password.php">Forgot Password?</a>
                <a href="signup.php">Create Account</a>
            </div>
        </form>
        <div class="footer-text">
            <p>&copy; <?php echo date('Y'); ?> Arturomania Systems. <a href="welcome.php" style="color: #2575fc; text-decoration:none;">Home</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
