<?php
session_start();
require_once 'db_connection.php'; // Provides $pdo

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_error_message'] = "You must be logged in to access this page.";
    header('Location: login.php');
    exit;
}

$school_id_session = $_SESSION['school_id'] ?? null;
$role = $_SESSION['role'] ?? null;

if (!$school_id_session && $role !== 'superadmin') {
    $_SESSION['error_message'] = "School information not found or user not authorized.";
    header('Location: index.php'); // Redirect to dashboard or login
    exit;
}

$batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);

if (!$batch_id) {
    $_SESSION['error_message'] = "Batch ID is required to view processed data.";
    header('Location: view_report_archives.php'); // Or wherever batches are listed
    exit;
}

$batch_info = null;
$students_in_batch = [];

// Determine the school_id to use for queries
$query_school_id = $school_id_session;
if ($role === 'superadmin' && !$school_id_session) { // Superadmin not impersonating a specific school
    // For superadmin, try to get school_id from the batch if not already set in session (e.g. via school switcher)
    $stmt_batch_school = $pdo->prepare("SELECT school_id FROM report_batch_settings WHERE id = :batch_id");
    $stmt_batch_school->execute([':batch_id' => $batch_id]);
    $query_school_id = $stmt_batch_school->fetchColumn();
    if (!$query_school_id) {
        $_SESSION['error_message'] = "Batch not found or school could not be determined for this batch.";
        header('Location: index.php'); // Or report archives page
        exit;
    }
}


try {
    // Fetch batch information to verify ownership and display details
    $stmt_batch = $pdo->prepare("
        SELECT rb.id, rb.school_id, ay.year_name, t.term_name, c.class_name
        FROM report_batch_settings rb
        JOIN academic_years ay ON rb.academic_year_id = ay.id
        JOIN terms t ON rb.term_id = t.id
        JOIN classes c ON rb.class_id = c.id
        WHERE rb.id = :batch_id AND rb.school_id = :query_school_id
    ");
    $stmt_batch->execute([':batch_id' => $batch_id, ':query_school_id' => $query_school_id]);
    $batch_info = $stmt_batch->fetch(PDO::FETCH_ASSOC);

    if (!$batch_info) {
        $_SESSION['error_message'] = "Batch not found or you are not authorized to view this batch for the selected school.";
        header('Location: index.php'); // Or report archives page
        exit;
    }

    // Fetch students and their summary data for this batch
    // We select students who have at least one score in the scores table for the given batch
    $stmt_students = $pdo->prepare("
        SELECT DISTINCT s.id as student_id, s.student_name, s.student_identifier,
               srs.p1p3_total_eot_score, srs.p1p3_average_eot_score,
               srs.p4p7_aggregate_points, srs.p4p7_division
        FROM students s
        JOIN scores sc ON s.id = sc.student_id
        LEFT JOIN student_report_summary srs ON s.id = srs.student_id AND srs.report_batch_id = :batch_id
        WHERE s.school_id = :query_school_id AND sc.report_batch_id = :batch_id
        ORDER BY s.student_name ASC
    ");
    // Note: The join with scores table (sc.report_batch_id = :batch_id) ensures we only get students who have scores in THIS batch.
    $stmt_students->execute([':batch_id' => $batch_id, ':query_school_id' => $batch_info['school_id']]);
    $students_in_batch = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    error_log("Error in view_processed_data.php: " . $e->getMessage());
}

$current_school_name = $_SESSION['school_name'] ?? 'School Portal';
if ($role === 'superadmin' && $batch_info) {
    $stmt_school_details = $pdo->prepare("SELECT name FROM schools WHERE id = :school_id");
    $stmt_school_details->execute([':school_id' => $batch_info['school_id']]);
    $current_school_name = $stmt_school_details->fetchColumn() . " (Superadmin View)";
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Processed Data - <?php echo htmlspecialchars($current_school_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><?php echo htmlspecialchars($current_school_name); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="view_report_archives.php">Report Archives</a></li>
                     <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'school_admin' || $_SESSION['role'] === 'superadmin')): ?>
                        <li class="nav-item"><a class="nav-link" href="manage_grading.php">Grading Policies</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($batch_info): ?>
            <h3>Processed Data for: <?php echo htmlspecialchars($batch_info['class_name'] . ' - ' . $batch_info['term_name'] . ' ' . $batch_info['year_name']); ?></h3>

            <?php if (!empty($students_in_batch)): ?>
                <table class="table table-striped table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Total Score (EOT)</th> <!-- For P1-P3 -->
                            <th>Average Score (EOT)</th> <!-- For P1-P3 -->
                            <th>Aggregate (P4-P7/Sec)</th>
                            <th>Division (P4-P7/Sec)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; foreach ($students_in_batch as $student): ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><?php echo htmlspecialchars($student['student_identifier'] ?? $student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['p1p3_total_eot_score'] ?? '-'); ?></td>
                                <td><?php echo ($student['p1p3_average_eot_score'] !== null) ? htmlspecialchars(number_format($student['p1p3_average_eot_score'], 2)) . '%' : '-'; ?></td>
                                <td><?php echo htmlspecialchars($student['p4p7_aggregate_points'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['p4p7_division'] ?? '-'); ?></td>
                                <td>
                                    <a href="generate_report_card.php?batch_id=<?php echo $batch_id; ?>&student_id=<?php echo $student['student_id']; ?>" class="btn btn-sm btn-primary" target="_blank" title="View Report Card">
                                        <i class="fas fa-file-pdf"></i> Report
                                    </a>
                                    <!-- Add link to edit marks if needed -->
                                    <!-- <a href="edit_marks.php?batch_id=<?php echo $batch_id; ?>&student_id=<?php echo $student['student_id']; ?>" class="btn btn-sm btn-warning" title="Edit Marks">
                                        <i class="fas fa-edit"></i> Edit
                                    </a> -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No students with processed data found for this batch. Ensure scores have been imported and calculations run.</div>
            <?php endif; ?>
        <?php else: ?>
            <!-- This message might already be handled by the redirect if batch_info is not found -->
            <div class="alert alert-warning">Batch information could not be loaded.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
