<?php
session_start();
require_once 'db_connection.php'; // Provides $pdo
require_once 'dal.php'; // For logActivity function

// Function to sanitize input data
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(stripslashes(trim($data)));
}

// Ensure user is logged in and is a school_admin or superadmin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'school_admin' && $_SESSION['role'] !== 'superadmin')) {
    $_SESSION['error_message'] = "Unauthorized access.";
    header('Location: login.php');
    exit;
}

$user_id_acting = $_SESSION['user_id'];
$user_email_acting = $_SESSION['email']; // Assuming email is stored in session
$school_id_session = $_SESSION['school_id']; // School ID from the logged-in user's session

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        if ($action === 'create_policy') {
            $posted_school_id = filter_var($_POST['school_id'], FILTER_SANITIZE_NUMBER_INT);
            if (empty($posted_school_id) || ($posted_school_id != $school_id_session && $_SESSION['role'] !== 'superadmin')) {
                 throw new Exception("School ID mismatch or not provided for non-superadmin.");
            }
            $current_school_id = $_SESSION['role'] === 'superadmin' ? $posted_school_id : $school_id_session;
            if (!$current_school_id) {
                throw new Exception("School ID is required to create a policy.");
            }

            $policy_name = sanitize_input($_POST['policy_name']);
            $policy_school_type = sanitize_input($_POST['policy_school_type']);
            $is_default = isset($_POST['policy_is_default']) ? 1 : 0;

            if (empty($policy_name) || empty($policy_school_type)) {
                throw new Exception("Policy name and school type are required.");
            }
            if (!in_array($policy_school_type, ['primary', 'secondary', 'other', 'any'])) {
                throw new Exception("Invalid applicable school type.");
            }


            if ($is_default == 1) {
                $stmt_unset_default = $pdo->prepare("UPDATE grading_policies SET is_default = 0 WHERE school_id = :school_id AND school_type_applicability = :school_type_applicability");
                $stmt_unset_default->execute([':school_id' => $current_school_id, ':school_type_applicability' => $policy_school_type]);
            }

            $stmt = $pdo->prepare("INSERT INTO grading_policies (school_id, name, school_type_applicability, is_default) VALUES (:school_id, :name, :school_type_applicability, :is_default)");
            $stmt->execute([
                ':school_id' => $current_school_id,
                ':name' => $policy_name,
                ':school_type_applicability' => $policy_school_type,
                ':is_default' => $is_default
            ]);
            $new_policy_id = $pdo->lastInsertId();
            logActivity($pdo, $user_id_acting, $user_email_acting, 'GRADING_POLICY_CREATE', "Created grading policy '{$policy_name}' (ID: {$new_policy_id}) for school ID {$current_school_id}.", 'grading_policies', $new_policy_id);
            $_SESSION['success_message'] = "Grading policy created successfully.";

        } elseif ($action === 'update_policy') {
            $policy_id = filter_var($_POST['policy_id'], FILTER_SANITIZE_NUMBER_INT);

            $stmt_check = $pdo->prepare("SELECT school_id FROM grading_policies WHERE id = :policy_id");
            $stmt_check->execute([':policy_id' => $policy_id]);
            $policy_school_owner_id = $stmt_check->fetchColumn();

            if (!$policy_school_owner_id || ($policy_school_owner_id != $school_id_session && $_SESSION['role'] !== 'superadmin')) {
                throw new Exception("Unauthorized to update this policy or policy not found.");
            }
            $current_school_id = $policy_school_owner_id;

            $policy_name = sanitize_input($_POST['policy_name']);
            $policy_school_type = sanitize_input($_POST['policy_school_type']);
            $is_default = isset($_POST['policy_is_default']) ? 1 : 0;

            if (empty($policy_name) || empty($policy_school_type)) {
                throw new Exception("Policy name and school type are required.");
            }
            if (!in_array($policy_school_type, ['primary', 'secondary', 'other', 'any'])) {
                throw new Exception("Invalid applicable school type.");
            }

            if ($is_default == 1) {
                $stmt_unset_default = $pdo->prepare("UPDATE grading_policies SET is_default = 0 WHERE school_id = :school_id AND school_type_applicability = :school_type_applicability AND id != :policy_id");
                $stmt_unset_default->execute([':school_id' => $current_school_id, ':school_type_applicability' => $policy_school_type, ':policy_id' => $policy_id]);
            }

            $stmt = $pdo->prepare("UPDATE grading_policies SET name = :name, school_type_applicability = :school_type_applicability, is_default = :is_default WHERE id = :policy_id AND school_id = :school_id");
            $stmt->execute([
                ':name' => $policy_name,
                ':school_type_applicability' => $policy_school_type,
                ':is_default' => $is_default,
                ':policy_id' => $policy_id,
                ':school_id' => $current_school_id
            ]);
            logActivity($pdo, $user_id_acting, $user_email_acting, 'GRADING_POLICY_UPDATE', "Updated grading policy '{$policy_name}' (ID: {$policy_id}) for school ID {$current_school_id}.", 'grading_policies', $policy_id);
            $_SESSION['success_message'] = "Grading policy updated successfully.";

        } elseif ($action === 'delete_policy') {
            $policy_id = filter_var($_POST['policy_id'], FILTER_SANITIZE_NUMBER_INT);

            $stmt_check = $pdo->prepare("SELECT school_id, name FROM grading_policies WHERE id = :policy_id");
            $stmt_check->execute([':policy_id' => $policy_id]);
            $policy_info = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$policy_info || ($policy_info['school_id'] != $school_id_session && $_SESSION['role'] !== 'superadmin')) {
                throw new Exception("Unauthorized to delete this policy or policy not found.");
            }

            $stmt_delete_levels = $pdo->prepare("DELETE FROM grading_policy_levels WHERE grading_policy_id = :policy_id");
            $stmt_delete_levels->execute([':policy_id' => $policy_id]);

            $stmt = $pdo->prepare("DELETE FROM grading_policies WHERE id = :policy_id AND school_id = :school_id");
            $stmt->execute([':policy_id' => $policy_id, ':school_id' => $policy_info['school_id']]);
            logActivity($pdo, $user_id_acting, $user_email_acting, 'GRADING_POLICY_DELETE', "Deleted grading policy '{$policy_info['name']}' (ID: {$policy_id}) for school ID {$policy_info['school_id']}.", 'grading_policies', $policy_id);
            $_SESSION['success_message'] = "Grading policy and its levels deleted successfully.";

        } elseif ($action === 'add_grade_level') {
            $policy_id = filter_var($_POST['policy_id'], FILTER_SANITIZE_NUMBER_INT);

            $stmt_check_policy = $pdo->prepare("SELECT school_id FROM grading_policies WHERE id = :policy_id");
            $stmt_check_policy->execute([':policy_id' => $policy_id]);
            $policy_school_owner_id = $stmt_check_policy->fetchColumn();

            if (!$policy_school_owner_id || ($policy_school_owner_id != $school_id_session && $_SESSION['role'] !== 'superadmin')) {
                throw new Exception("Unauthorized to add grade level to this policy or policy not found.");
            }

            $grade_label = sanitize_input($_POST['grade_label']);
            $min_score = filter_var($_POST['min_score'], FILTER_VALIDATE_FLOAT);
            $max_score = filter_var($_POST['max_score'], FILTER_VALIDATE_FLOAT);
            $comment = sanitize_input($_POST['comment']);
            $points_input = trim($_POST['points']);
            $points = ($points_input === '' || $points_input === null) ? null : filter_var($points_input, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            $order_index = filter_var($_POST['order_index'], FILTER_VALIDATE_INT);


            if (empty($grade_label) || $min_score === false || $max_score === false || $order_index === false) {
                throw new Exception("Grade label, min score, max score, and order index are required and must be valid numbers.");
            }
            if ($min_score > $max_score) {
                throw new Exception("Min score cannot be greater than max score.");
            }

            $stmt = $pdo->prepare("INSERT INTO grading_policy_levels (grading_policy_id, grade_label, min_score, max_score, comment, points, order_index) VALUES (:grading_policy_id, :grade_label, :min_score, :max_score, :comment, :points, :order_index)");
            $stmt->execute([
                ':grading_policy_id' => $policy_id,
                ':grade_label' => $grade_label,
                ':min_score' => $min_score,
                ':max_score' => $max_score,
                ':comment' => $comment,
                ':points' => $points,
                ':order_index' => $order_index
            ]);
            $new_level_id = $pdo->lastInsertId();
            logActivity($pdo, $user_id_acting, $user_email_acting, 'GRADE_LEVEL_ADD', "Added grade level '{$grade_label}' to policy ID {$policy_id}.", 'grading_policy_levels', $new_level_id);
            $_SESSION['success_message'] = "Grade level added successfully.";

        } elseif ($action === 'update_grade_level') {
            $level_id = filter_var($_POST['level_id'], FILTER_SANITIZE_NUMBER_INT);
            $policy_id = filter_var($_POST['policy_id'], FILTER_SANITIZE_NUMBER_INT);

            $stmt_check_policy = $pdo->prepare("SELECT school_id FROM grading_policies WHERE id = :policy_id");
            $stmt_check_policy->execute([':policy_id' => $policy_id]);
            $policy_school_owner_id = $stmt_check_policy->fetchColumn();

            if (!$policy_school_owner_id || ($policy_school_owner_id != $school_id_session && $_SESSION['role'] !== 'superadmin')) {
                throw new Exception("Unauthorized to update grade level for this policy or policy not found.");
            }

            $stmt_check_level = $pdo->prepare("SELECT grading_policy_id FROM grading_policy_levels WHERE id = :level_id");
            $stmt_check_level->execute([':level_id' => $level_id]);
            $level_policy_id = $stmt_check_level->fetchColumn();
            if ($level_policy_id != $policy_id) {
                 throw new Exception("Grade level does not belong to the specified policy.");
            }

            $grade_label = sanitize_input($_POST['grade_label']);
            $min_score = filter_var($_POST['min_score'], FILTER_VALIDATE_FLOAT);
            $max_score = filter_var($_POST['max_score'], FILTER_VALIDATE_FLOAT);
            $comment = sanitize_input($_POST['comment']);
            $points_input = trim($_POST['points']);
            $points = ($points_input === '' || $points_input === null) ? null : filter_var($points_input, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            $order_index = filter_var($_POST['order_index'], FILTER_VALIDATE_INT);


            if (empty($grade_label) || $min_score === false || $max_score === false || $order_index === false) {
                throw new Exception("Grade label, min score, max score, and order index are required and must be valid numbers.");
            }
             if ($min_score > $max_score) {
                throw new Exception("Min score cannot be greater than max score.");
            }

            $stmt = $pdo->prepare("UPDATE grading_policy_levels SET grade_label = :grade_label, min_score = :min_score, max_score = :max_score, comment = :comment, points = :points, order_index = :order_index WHERE id = :level_id AND grading_policy_id = :grading_policy_id");
            $stmt->execute([
                ':grade_label' => $grade_label,
                ':min_score' => $min_score,
                ':max_score' => $max_score,
                ':comment' => $comment,
                ':points' => $points,
                ':order_index' => $order_index,
                ':level_id' => $level_id,
                ':grading_policy_id' => $policy_id
            ]);
            logActivity($pdo, $user_id_acting, $user_email_acting, 'GRADE_LEVEL_UPDATE', "Updated grade level ID {$level_id} for policy ID {$policy_id}.", 'grading_policy_levels', $level_id);
            $_SESSION['success_message'] = "Grade level updated successfully.";

        } elseif ($action === 'delete_grade_level') {
            $level_id = filter_var($_POST['level_id'], FILTER_SANITIZE_NUMBER_INT);

            $stmt_check = $pdo->prepare("SELECT gpl.id, gp.school_id FROM grading_policy_levels gpl JOIN grading_policies gp ON gpl.grading_policy_id = gp.id WHERE gpl.id = :level_id");
            $stmt_check->execute([':level_id' => $level_id]);
            $level_info = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$level_info || ($level_info['school_id'] != $school_id_session && $_SESSION['role'] !== 'superadmin')) {
                 throw new Exception("Unauthorized to delete this grade level or grade level not found.");
            }

            $stmt = $pdo->prepare("DELETE FROM grading_policy_levels WHERE id = :level_id");
            $stmt->execute([':level_id' => $level_id]);
            logActivity($pdo, $user_id_acting, $user_email_acting, 'GRADE_LEVEL_DELETE', "Deleted grade level ID {$level_id}.", 'grading_policy_levels', $level_id);
            $_SESSION['success_message'] = "Grade level deleted successfully.";
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Grading Policy Error (PDO): " . $e->getMessage());
        $_SESSION['error_message'] = "Database error processing your request. " . $e->getMessage();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Grading Policy Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }

    header("Location: manage_grading.php");
    exit;
} else {
    // If not a POST request or no action specified, redirect
    $_SESSION['error_message'] = "Invalid request.";
    header("Location: manage_grading.php");
    exit;
}
?>
