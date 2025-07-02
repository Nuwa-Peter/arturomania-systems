<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Arturomania Systems</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .signup-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        .signup-container h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #555;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="password"],
        .form-group input[type="file"],
        .form-group textarea,
        .form-group select { /* Added select here */
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-group input[type="file"] {
            padding: 5px;
        }
        .btn-submit {
            background-color: #2575fc;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1em;
            width: 100%;
            transition: background-color 0.3s;
        }
        .btn-submit:hover {
            background-color: #1a5db3;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #2575fc;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php session_start(); ?>
    <div class="signup-container">
        <h2>Create Your School Account</h2>
        <form action="handle_signup.php" method="POST" enctype="multipart/form-data">
            <?php
            if (isset($_SESSION['signup_errors']) && !empty($_SESSION['signup_errors'])) {
                echo '<div class="alert alert-danger" role="alert">';
                foreach ($_SESSION['signup_errors'] as $error) {
                    echo htmlspecialchars($error) . '<br>';
                }
                echo '</div>';
                unset($_SESSION['signup_errors']);
            }
            $signup_data = $_SESSION['signup_data'] ?? [];
            unset($_SESSION['signup_data']);
            ?>
            <div class="form-group">
                <label for="school_name">School Name</label>
                <input type="text" id="school_name" name="school_name" value="<?php echo htmlspecialchars($signup_data['school_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="school_type">School Type</label>
                <select id="school_type" name="school_type" required>
                    <option value="" disabled <?php echo empty($signup_data['school_type']) ? 'selected' : ''; ?>>Select school type</option>
                    <option value="primary" <?php echo (isset($signup_data['school_type']) && $signup_data['school_type'] == 'primary') ? 'selected' : ''; ?>>Primary School</option>
                    <option value="secondary" <?php echo (isset($signup_data['school_type']) && $signup_data['school_type'] == 'secondary') ? 'selected' : ''; ?>>Secondary School</option>
                    <option value="other" <?php echo (isset($signup_data['school_type']) && $signup_data['school_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="email">Administrator Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($signup_data['email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($signup_data['phone_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="school_logo">School Logo</label>
                <input type="file" id="school_logo" name="school_logo" accept="image/*">
            </div>
            <div class="form-group">
                <label for="motto">School Motto (will appear in footer)</label>
                <textarea id="motto" name="motto"><?php echo htmlspecialchars($signup_data['motto'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn-submit">Sign Up</button>
        </form>
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>
