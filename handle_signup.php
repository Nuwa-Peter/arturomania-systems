<?php
session_start();
require_once 'db_connection.php'; // Assuming this file establishes the $pdo connection

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

$errors = [];
$school_name = '';
$school_type = '';
$admin_email = '';
$phone_number = '';
$motto = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $school_name = sanitize_input($_POST['school_name']);
    $school_type = isset($_POST['school_type']) ? sanitize_input($_POST['school_type']) : '';
    $admin_email = filter_var(sanitize_input($_POST['email']), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password']; // Will be hashed, not sanitized the same way
    $confirm_password = $_POST['confirm_password'];
    $phone_number = sanitize_input($_POST['phone_number']);
    $motto = sanitize_input($_POST['motto']);

    // Basic Validations
    if (empty($school_name)) {
        $errors[] = "School name is required.";
    }
    if (empty($school_type)) {
        $errors[] = "School type is required.";
    } elseif (!in_array($school_type, ['primary', 'secondary', 'other'])) {
        $errors[] = "Invalid school type selected.";
    }
    if (empty($admin_email)) {
        $errors[] = "A valid administrator email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    // Check if email already exists
    if (empty($errors) && $admin_email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $admin_email]);
        if ($stmt->fetch()) {
            $errors[] = "This email address is already registered.";
        }
    }

    // Handle file upload
    $logo_path_to_save = null;
    if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_extension = strtolower(pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Invalid file type for logo. Only JPG, JPEG, PNG, GIF are allowed.";
        } else {
            $new_filename = uniqid('logo_', true) . '.' . $file_extension;
            $logo_path = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['school_logo']['tmp_name'], $logo_path)) {
                $logo_path_to_save = $logo_path;
            } else {
                $errors[] = "Failed to upload logo.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Create the school record
            $sql_school = "INSERT INTO schools (name, school_type, logo_path, phone_number, motto) VALUES (:name, :school_type, :logo_path, :phone_number, :motto)";
            $stmt_school = $pdo->prepare($sql_school);
            $stmt_school->execute([
                ':name' => $school_name,
                ':school_type' => $school_type,
                ':logo_path' => $logo_path_to_save,
                ':phone_number' => $phone_number,
                ':motto' => $motto
            ]);
            $school_id = $pdo->lastInsertId();

            // 2. Create the admin user for this school
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql_user = "INSERT INTO users (school_id, full_name, email, password_hash, role, is_active) VALUES (:school_id, :full_name, :email, :password_hash, :role, 1)";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([
                ':school_id' => $school_id,
                ':full_name' => $school_name . " Admin", // Or a separate field for admin name if you add it to the form
                ':email' => $admin_email,
                ':password_hash' => $password_hash,
                ':role' => 'school_admin'
            ]);
            $admin_user_id = $pdo->lastInsertId();

            // 3. Update the school record with the admin_user_id
            $sql_update_school = "UPDATE schools SET admin_user_id = :admin_user_id WHERE id = :school_id";
            $stmt_update_school = $pdo->prepare($sql_update_school);
            $stmt_update_school->execute([
                ':admin_user_id' => $admin_user_id,
                ':school_id' => $school_id
            ]);

            // 4. Create default academic year, terms and grading policy for the new school (Example)
            // Default Academic Year
            $current_year = date('Y');
            $stmt_ay = $pdo->prepare("INSERT INTO academic_years (school_id, year_name, is_active) VALUES (:school_id, :year_name, 1)");
            $stmt_ay->execute([':school_id' => $school_id, ':year_name' => $current_year]);
            $academic_year_id = $pdo->lastInsertId();

            // Default Terms
            $terms = [['Term I', 1], ['Term II', 2], ['Term III', 3]];
            $stmt_term = $pdo->prepare("INSERT INTO terms (school_id, term_name, order_index) VALUES (:school_id, :term_name, :order_index)");
            foreach ($terms as $term_data) {
                $stmt_term->execute([
                    ':school_id' => $school_id,
                    ':term_name' => $term_data[0],
                    ':order_index' => $term_data[1]
                ]);
            }

            // Default Grading Policy (Example for primary school)
            $policy_name = ($school_type == 'primary') ? "Default Primary Grading" : "Default Secondary Grading";
            $stmt_gp = $pdo->prepare("INSERT INTO grading_policies (school_id, name, school_type_applicability, is_default) VALUES (:school_id, :name, :school_type, 1)");
            $stmt_gp->execute([
                ':school_id' => $school_id,
                ':name' => $policy_name,
                ':school_type' => $school_type
            ]);
            $grading_policy_id = $pdo->lastInsertId();

            // Default Grading Policy Levels (Example)
            $levels = [];
            if ($school_type == 'primary') {
                $levels = [
                    ['D1', 80, 100, 'Excellent', 1, 1],
                    ['D2', 70, 79, 'Very Good', 2, 2],
                    ['C3', 60, 69, 'Good', 3, 3],
                    ['C4', 50, 59, 'Average', 4, 4],
                    ['P7', 40, 49, 'Pass', 7, 5],
                    ['F9', 0, 39, 'Fail', 9, 6]
                ];
            } elseif ($school_type == 'secondary') {
                 // Example for O-Level like grading
                $levels = [
                    ['D1', 75, 100, 'Distinction 1', 1, 1],
                    ['D2', 70, 74, 'Distinction 2', 2, 2],
                    ['C3', 65, 69, 'Credit 3', 3, 3],
                    ['C4', 60, 64, 'Credit 4', 4, 4],
                    ['C5', 55, 59, 'Credit 5', 5, 5],
                    ['C6', 50, 54, 'Credit 6', 6, 6],
                    ['P7', 45, 49, 'Pass 7', 7, 7],
                    ['P8', 40, 44, 'Pass 8', 8, 8],
                    ['F9', 0, 39, 'Failure', 9, 9]
                ];
            }

            $stmt_gpl = $pdo->prepare("INSERT INTO grading_policy_levels (grading_policy_id, grade_label, min_score, max_score, comment, points, order_index) VALUES (:grading_policy_id, :grade_label, :min_score, :max_score, :comment, :points, :order_index)");
            foreach ($levels as $level) {
                $stmt_gpl->execute([
                    ':grading_policy_id' => $grading_policy_id,
                    ':grade_label' => $level[0],
                    ':min_score' => $level[1],
                    ':max_score' => $level[2],
                    ':comment' => $level[3],
                    ':points' => $level[4],
                    ':order_index' => $level[5]
                ]);
            }

            $pdo->commit();
            $_SESSION['success_message'] = "School account created successfully! You can now log in.";
            header("Location: login.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['signup_errors'] = $errors;
        $_SESSION['signup_data'] = $_POST; // To repopulate the form
        header("Location: signup.php");
        exit;
    }
} else {
    // Not a POST request, redirect to signup page or show an error
    header("Location: signup.php");
    exit;
}
?>
