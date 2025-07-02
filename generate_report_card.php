<?php
session_start();
require_once 'vendor/autoload.php'; // For mPDF
require_once 'db_connection.php'; // Provides $pdo
require_once 'calculation_utils.php'; // For getGradeAndPoints

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to access this page.";
    header('Location: login.php');
    exit;
}

$school_id_session = $_SESSION['school_id'] ?? null;
$role = $_SESSION['role'] ?? null;

if (!$school_id_session && $role !== 'superadmin') {
    die("Error: School information not found or user not authorized.");
}

$batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
$student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);

if (!$batch_id || !$student_id) {
    // Try to get from POST if GET fails (e.g. if form submitted for multiple reports)
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    if(!$batch_id || !$student_id){
        die("Error: Batch ID and Student ID are required.");
    }
}

// If school_id is not in session (e.g. superadmin accessing), try to get it from batch_info
if (!$school_id_session && $role === 'superadmin' && $batch_id) {
    $stmt_school_check = $pdo->prepare("SELECT school_id FROM report_batch_settings WHERE id = :batch_id");
    $stmt_school_check->execute([':batch_id' => $batch_id]);
    $school_id_from_batch = $stmt_school_check->fetchColumn();
    if ($school_id_from_batch) {
        $school_id_session = $school_id_from_batch; // Use this for further queries
    } else {
        die("Error: Could not determine school for the given batch.");
    }
}


try {
    // 1. Fetch Batch Information (and verify school_id for security)
    $stmt_batch = $pdo->prepare("
        SELECT rb.*, ay.year_name, t.term_name, c.class_name, c.class_level_group, gp.id as grading_policy_id, s.name as school_name, s.logo_path as school_logo, s.motto as school_motto
        FROM report_batch_settings rb
        JOIN academic_years ay ON rb.academic_year_id = ay.id
        JOIN terms t ON rb.term_id = t.id
        JOIN classes c ON rb.class_id = c.id
        JOIN schools s ON rb.school_id = s.id
        LEFT JOIN grading_policies gp ON rb.grading_policy_id = gp.id
        WHERE rb.id = :batch_id AND rb.school_id = :school_id_session
    ");
    $stmt_batch->execute([':batch_id' => $batch_id, ':school_id_session' => $school_id_session]);
    $batch_info = $stmt_batch->fetch(PDO::FETCH_ASSOC);

    if (!$batch_info) {
        die("Error: Report batch not found or not authorized for this school.");
    }

    // 2. Fetch Student Information (and verify school_id)
    $stmt_student = $pdo->prepare("
        SELECT s.*
        FROM students s
        WHERE s.id = :student_id AND s.school_id = :school_id_session
    ");
    $stmt_student->execute([':student_id' => $student_id, ':school_id_session' => $school_id_session]);
    $student_info = $stmt_student->fetch(PDO::FETCH_ASSOC);

    if (!$student_info) {
        die("Error: Student not found or not authorized for this school.");
    }

    // 3. Fetch Scores for the student in this batch
    $stmt_scores = $pdo->prepare("
        SELECT sub.subject_name_full, sc.bot_score, sc.mot_score, sc.eot_score, sc.eot_remark, sc.teacher_initials_on_report
        FROM scores sc
        JOIN subjects sub ON sc.subject_id = sub.id
        WHERE sc.report_batch_id = :batch_id AND sc.student_id = :student_id AND sub.school_id = :school_id_session
        ORDER BY sub.subject_name_full ASC
    ");
    $stmt_scores->execute([':batch_id' => $batch_id, ':student_id' => $student_id, ':school_id_session' => $school_id_session]);
    $scores = $stmt_scores->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Grading Policy Levels if a policy is associated with the batch
    $grading_levels = [];
    if ($batch_info['grading_policy_id']) {
        $stmt_levels = $pdo->prepare("SELECT * FROM grading_policy_levels WHERE grading_policy_id = :policy_id ORDER BY order_index ASC, min_score DESC");
        $stmt_levels->execute([':policy_id' => $batch_info['grading_policy_id']]);
        $grading_levels = $stmt_levels->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Attempt to fetch a default grading policy for the school and school type
        $stmt_default_policy = $pdo->prepare("SELECT id FROM grading_policies WHERE school_id = :school_id AND school_type_applicability = :school_type AND is_default = 1 LIMIT 1");
        $stmt_default_policy->execute([':school_id' => $school_id_session, ':school_type' => $batch_info['school_type']]);
        $default_policy_id = $stmt_default_policy->fetchColumn();
        if ($default_policy_id) {
            $stmt_levels = $pdo->prepare("SELECT * FROM grading_policy_levels WHERE grading_policy_id = :policy_id ORDER BY order_index ASC, min_score DESC");
            $stmt_levels->execute([':policy_id' => $default_policy_id]);
            $grading_levels = $stmt_levels->fetchAll(PDO::FETCH_ASSOC);
            if(empty($grading_levels)){
                 error_log("Warning: Default grading policy ID {$default_policy_id} for school ID {$school_id_session} has no grade levels defined.");
            }
        } else {
            error_log("Warning: No grading policy explicitly set for batch ID {$batch_id} and no default policy found for school ID {$school_id_session} and type {$batch_info['school_type']}. Grades and points might not be calculated.");
        }
    }

    // 5. Fetch Summary Data
    $stmt_summary = $pdo->prepare("SELECT * FROM student_report_summary WHERE student_id = :student_id AND report_batch_id = :batch_id");
    $stmt_summary->execute([':student_id' => $student_id, ':batch_id' => $batch_id]);
    $summary_data = $stmt_summary->fetch(PDO::FETCH_ASSOC);


    // Start HTML for PDF
    $html = '<html><head><style>
                body { font-family: sans-serif; font-size: 10pt; }
                .header { text-align: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #000; }
                .school-logo { max-height: 80px; max-width: 150px; display: block; margin-left: auto; margin-right: auto; }
                .report-title { font-size: 16pt; font-weight: bold; margin-top: 5px; }
                .student-info table, .batch-info table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 10px; }
                .student-info td, .batch-info td { padding: 3px; }
                .scores-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 9pt; }
                .scores-table th, .scores-table td { border: 1px solid #888; padding: 6px; text-align: left; }
                .scores-table th { background-color: #e0e0e0; font-weight: bold; }
                .scores-table td.center { text-align: center; }
                .summary-section { margin-top: 15px; font-size: 9pt; }
                .summary-section table { width: 50%; border-collapse: collapse; } /* Adjust width as needed */
                .summary-section th, .summary-section td { border: 1px solid #ccc; padding: 5px; }
                .remarks { margin-top: 15px; border: 1px solid #ccc; padding: 10px; font-size: 9pt; }
                .remarks p { margin-bottom: 5px; }
                .footer { text-align: center; font-size: 8pt; margin-top: 20px; position: fixed; bottom: 0; width:100%; }
                .signature-area { margin-top: 30px; }
                .signature-line { border-bottom: 1px solid #000; width: 200px; display: inline-block; margin-top:20px; }
                .signature-label { font-size: 8pt; }
            </style></head><body>';

    // School Header
    $html .= '<div class="header">';
    if (!empty($batch_info['school_logo']) && file_exists($batch_info['school_logo'])) {
         $html .= '<img src="' . htmlspecialchars($batch_info['school_logo']) . '" alt="School Logo" class="school-logo"><br>';
    } else {
         $html .= '<!-- Logo not found or not specified -->';
    }
    $html .= '<h2>' . htmlspecialchars(strtoupper($batch_info['school_name'])) . '</h2>';
    if(!empty($batch_info['school_motto'])){
        $html .= '<p><em>' . htmlspecialchars($batch_info['school_motto']) . '</em></p>';
    }
    $html .= '<div class="report-title">END OF ' . strtoupper(htmlspecialchars($batch_info['term_name'])) . ' REPORT CARD ' . htmlspecialchars($batch_info['year_name']) . '</div>';
    $html .= '</div>';

    // Student and Batch Info
    $html .= '<div class="student-info">';
    $html .= '<table style="width:100%;">';
    $html .= '<tr><td style="width:15%;"><strong>STUDENT NAME:</strong></td><td style="width:35%; text-transform: uppercase;">' . htmlspecialchars($student_info['student_name']) . '</td>';
    $html .= '<td style="width:15%;"><strong>STUDENT ID:</strong></td><td style="width:35%;">' . htmlspecialchars($student_info['student_identifier'] ?? 'N/A') . '</td></tr>';
    $html .= '<tr><td><strong>CLASS:</strong></td><td>' . htmlspecialchars($batch_info['class_name']) . '</td>';
    $html .= '<td><strong>ACADEMIC YEAR:</strong></td><td>' . htmlspecialchars($batch_info['year_name']) . '</td></tr>';
    $html .= '<tr><td><strong>TERM:</strong></td><td>' . htmlspecialchars($batch_info['term_name']) . '</td>';
    $html .= '<td><strong>REPORT DATE:</strong></td><td>' . date("F j, Y") . '</td></tr>';
    $html .= '</table>';
    $html .= '</div>';

    // Scores Table
    if (!empty($scores)) {
        $html .= '<table class="scores-table">';
        $html .= '<thead><tr><th>SUBJECT</th><th>B.O.T (100)</th><th>M.O.T (100)</th><th>E.O.T (100)</th><th>GRADE</th><th>POINTS</th><th>REMARKS</th><th>TEACHER</th></tr></thead>';
        $html .= '<tbody>';
        $total_eot_score = 0;
        $total_subjects = 0;
        $total_aggregate_points = 0;
        $valid_scores_for_average = 0;

        foreach ($scores as $score) {
            $eot_score_val = is_numeric($score['eot_score']) ? floatval($score['eot_score']) : null;
            $grade_info = getGradeAndPoints($eot_score_val, $grading_levels, $batch_info['class_level_group']);

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($score['subject_name_full']) . '</td>';
            $html .= '<td class="center">' . htmlspecialchars($score['bot_score'] ?? '-') . '</td>';
            $html .= '<td class="center">' . htmlspecialchars($score['mot_score'] ?? '-') . '</td>';
            $html .= '<td class="center">' . htmlspecialchars($score['eot_score'] ?? '-') . '</td>';
            $html .= '<td class="center">' . htmlspecialchars($grade_info['grade'] ?? '-') . '</td>';
            $html .= '<td class="center">' . htmlspecialchars($grade_info['points'] ?? '-') . '</td>';
            $html .= '<td>' . htmlspecialchars($score['eot_remark'] ?? $grade_info['comment'] ?? '-') . '</td>';
            $html .= '<td class="center">' . htmlspecialchars($score['teacher_initials_on_report'] ?? '-') . '</td>';
            $html .= '</tr>';

            if ($eot_score_val !== null) {
                $total_eot_score += $eot_score_val;
                $valid_scores_for_average++;
            }
            if (isset($grade_info['points']) && is_numeric($grade_info['points'])) {
                 // Only sum points for core subjects if that's a requirement (not implemented here yet)
                $total_aggregate_points += floatval($grade_info['points']);
            }
            $total_subjects++;
        }
        $html .= '</tbody></table>';

        // Summary Section
        $html .= '<div class="summary-section">';
        $html .= '<h4>PERFORMANCE SUMMARY</h4>';
        $html .= '<table style="width: 60%; margin-top: 10px;">';

        if ($summary_data) {
            if (strpos(strtolower($batch_info['class_level_group']), 'primary_lower') !== false) { // P1-P3 (Lower Primary)
                $html .= '<tr><td>Total Score (EOT):</td><td><strong>' . htmlspecialchars($summary_data['p1p3_total_eot_score'] ?? $total_eot_score) . '</strong></td></tr>';
                $avg_score = ($summary_data['p1p3_average_eot_score'] !== null) ? $summary_data['p1p3_average_eot_score'] : (($valid_scores_for_average > 0) ? round($total_eot_score / $valid_scores_for_average, 2) : '-');
                $html .= '<tr><td>Average Score (EOT):</td><td><strong>' . htmlspecialchars($avg_score) . '%</strong></td></tr>';
                $html .= '<tr><td>Position in Class (EOT):</td><td><strong>' . htmlspecialchars($summary_data['p1p3_position_in_class'] ?? '-') . ' out of ' . htmlspecialchars($summary_data['p1p3_total_students_in_class'] ?? '-') . '</strong></td></tr>';
            } else { // P4-P7 (Upper Primary) or Secondary
                $html .= '<tr><td>Aggregate Points (EOT):</td><td><strong>' . htmlspecialchars($summary_data['p4p7_aggregate_points'] ?? $total_aggregate_points) . '</strong></td></tr>';
                $html .= '<tr><td>Division (EOT):</td><td><strong>' . htmlspecialchars($summary_data['p4p7_division'] ?? '-') . '</strong></td></tr>';
                 if (strpos(strtolower($batch_info['class_level_group']), 'primary_upper') !== false) {
                     $html .= '<tr><td>Total Students in Class:</td><td><strong>' . htmlspecialchars($summary_data['p1p3_total_students_in_class'] ?? '-') . '</strong></td></tr>';
                 }
            }
        } else {
            // Fallback if no pre-calculated summary
            if (strpos(strtolower($batch_info['class_level_group']), 'primary_lower') !== false) {
                 $html .= '<tr><td>Total Score (EOT):</td><td><strong>' . $total_eot_score . '</strong></td></tr>';
                 $avg_score = ($valid_scores_for_average > 0) ? round($total_eot_score / $valid_scores_for_average, 2) : '-';
                 $html .= '<tr><td>Average Score (EOT):</td><td><strong>' . $avg_score . '%</strong></td></tr>';
            } else {
                 $html .= '<tr><td>Aggregate Points (EOT):</td><td><strong>' . $total_aggregate_points . '</strong></td></tr>';
                 // Division would require grading policy logic here if not pre-calculated
                 $html .= '<tr><td>Division (EOT):</td><td><strong>-</strong></td></tr>';
            }
        }
        $html .= '</table>';
        $html .= '</div>';


        // Remarks
        $html .= '<div class="remarks">';
        $html .= '<strong>Class Teacher\'s Remark:</strong><p>' . nl2br(htmlspecialchars($summary_data['manual_classteachers_remark_text'] ?? $summary_data['auto_classteachers_remark_text'] ?? 'Needs to improve in some areas.')) . '</p>';
        $html .= '<strong>Head Teacher\'s Remark:</strong><p>' . nl2br(htmlspecialchars($summary_data['manual_headteachers_remark_text'] ?? $summary_data['auto_headteachers_remark_text'] ?? 'Good progress, keep it up.')) . '</p>';
        $html .= '</div>';

    } else {
        $html .= '<p class="alert alert-warning">No scores found for this student in this batch. Report card cannot be fully generated.</p>';
    }

    // Footer with Signatures
    $html .= '<div class="signature-area" style="margin-top: 40px;">';
    $html .= '<table style="width:100%; border:none;"><tr>';
    $html .= '<td style="width:50%; text-align:left;">Class Teacher: <span class="signature-line"></span></td>';
    $html .= '<td style="width:50%; text-align:right;">Head Teacher: <span class="signature-line"></span></td>';
    $html .= '</tr></table>';
    $html .= '</div>';


    $html .= '<div class="footer">';
    $html .= '<p>Term Ends: ' . htmlspecialchars($batch_info['term_end_date'] ? date('F j, Y', strtotime($batch_info['term_end_date'])) : 'N/A') . '. Next Term Begins: ' . htmlspecialchars($batch_info['next_term_begin_date'] ? date('F j, Y', strtotime($batch_info['next_term_begin_date'])) : 'N/A') . '</p>';
    $html .= '<p><em>This report is not valid without the official school stamp.</em></p>';
    $html .= '</div>';

    $html .= '</body></html>';

    // mPDF generation
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 25, // Increased bottom margin for footer
        'margin_header' => 10,
        'margin_footer' => 10,
        'default_font' => 'sans-serif'
    ]);

    $mpdf->SetTitle(htmlspecialchars($batch_info['school_name']) . " - Report Card - " . htmlspecialchars($student_info['student_name']));
    $mpdf->SetAuthor(htmlspecialchars($batch_info['school_name']));
    $mpdf->SetCreator('Arturomania School System');

    // Optional: Add a header/footer to mPDF directly
    // $mpdf->SetHeader(htmlspecialchars($batch_info['school_name']).'||Page {PAGENO} of {nb}');
    // $mpdf->SetFooter('Generated by Arturomania Systems||' . date('Y-m-d H:i:s'));

    $mpdf->WriteHTML($html);

    $filename = 'Report_Card_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $student_info['student_name']) . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $batch_info['class_name']) . '_' . $batch_info['term_name'] . '_' . $batch_info['year_name'] . '.pdf';
    $mpdf->Output($filename, 'I'); // 'I' for inline, 'D' for download
    exit;

} catch (PDOException $e) {
    error_log("Report Card Generation Error (PDO): " . $e->getMessage());
    die("Database error generating report card. Please contact support. " . $e->getMessage());
} catch (Exception $e) {
    error_log("Report Card Generation Error: " . $e->getMessage());
    die("An error occurred while generating the report card: " . $e->getMessage());
}

?>
```

Now I need to check and potentially update `calculation_utils.php` to ensure `getGradeAndPoints` works with the new `grading_policy_levels` table structure.
