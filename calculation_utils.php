<?php

// Function to determine grade and points based on score and grading scale array
function getGradeAndPoints($score, $grading_levels_array, $class_level_group = null) {
    // class_level_group can be used for more specific logic if needed in the future,
    // but for now, we assume grading_levels_array is already filtered for the correct policy.

    if ($score === null || !is_numeric($score) || empty($grading_levels_array)) {
        return ['grade' => '-', 'points' => '-', 'comment' => 'N/A'];
    }

    // Ensure scores are treated as floats for comparison
    $score = floatval($score);

    foreach ($grading_levels_array as $level) {
        if ($score >= (float)$level['min_score'] && $score <= (float)$level['max_score']) {
            return [
                'grade' => $level['grade_label'],
                'points' => $level['points'], // This could be aggregate points or other point system
                'comment' => $level['comment']
            ];
        }
    }
    return ['grade' => 'N/A', 'points' => 'N/A', 'comment' => 'Score out of range']; // Fallback if no range matches
}

// Function to calculate overall grade/division for P4-P7 and Secondary (example logic)
function calculateOverallP4P7Secondary(array $scores_data, array $grading_levels, array $core_subject_codes, $pdo, $batch_id, $student_id, $school_id, $class_level_group) {
    $total_aggregate_points = 0;
    $core_subjects_graded_count = 0;
    $division = 'X'; // Default/Fail
    $comment = '';

    // Ensure core_subject_codes are uppercase for case-insensitive comparison
    $core_subject_codes_upper = array_map('strtoupper', $core_subject_codes);

    foreach ($scores_data as $score_item) {
        if ($score_item['eot_score'] === null || !is_numeric($score_item['eot_score'])) {
            continue;
        }

        $subject_code_upper = strtoupper(trim($score_item['subject_code']));

        if (in_array($subject_code_upper, $core_subject_codes_upper)) {
            $grade_info = getGradeAndPoints(floatval($score_item['eot_score']), $grading_levels, $class_level_group);
            if (isset($grade_info['points']) && is_numeric($grade_info['points'])) {
                $total_aggregate_points += (int)$grade_info['points']; // Assuming points are integers for aggregation
                $core_subjects_graded_count++;
            }
        }
    }

    // This is a placeholder. Actual division boundaries should come from the grading policy or a specific configuration.
    // For example, you might have another table `division_rules` linked to `grading_policies`.
    if ($core_subjects_graded_count < count($core_subject_codes)) {
        $division = 'X';
        $comment = "Incomplete results for core subjects. Division cannot be determined.";
    } else {
        if ($total_aggregate_points >= 4 && $total_aggregate_points <= 12) $division = 'I';
        elseif ($total_aggregate_points >= 13 && $total_aggregate_points <= 23) $division = 'II';
        elseif ($total_aggregate_points >= 24 && $total_aggregate_points <= 29) $division = 'III';
        elseif ($total_aggregate_points >= 30 && $total_aggregate_points <= 34) $division = 'IV';
        else $division = 'U';

        $comment = "Overall performance: Division " . $division . " with " . $total_aggregate_points . " aggregates.";
    }

    return ['aggregate' => $total_aggregate_points, 'division' => $division, 'comment' => $comment];
}


// Function to calculate overall summary for P1-P3 (example logic)
function calculateOverallP1P3(array $scores_data, array $grading_levels, $pdo, $batch_id, $student_id, $school_id, $class_level_group) {
    $total_eot_score = 0;
    $valid_scores_count = 0;

    foreach ($scores_data as $score_item) {
        if ($score_item['eot_score'] !== null && is_numeric($score_item['eot_score'])) {
            $total_eot_score += floatval($score_item['eot_score']);
            $valid_scores_count++;
        }
    }

    $average_score = ($valid_scores_count > 0) ? round($total_eot_score / $valid_scores_count, 2) : 0;
    $overall_grade_info = getGradeAndPoints($average_score, $grading_levels, $class_level_group);

    $comment = "Overall performance: Total Score " . $total_eot_score . ", Average " . $average_score . "%.";
    if(isset($overall_grade_info['comment']) && !empty($overall_grade_info['comment']) && $overall_grade_info['comment'] !== 'N/A') {
        $comment .= " " . $overall_grade_info['comment'];
    }

    $position_in_class = '-';
    $total_students_in_class = '-';

    // Fetch total students for this batch to potentially calculate rank
    // This is a simplified count and doesn't guarantee they all have summaries.
    $stmt_count = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM scores WHERE report_batch_id = :batch_id");
    $stmt_count->execute([':batch_id' => $batch_id]);
    $total_students_in_class = $stmt_count->fetchColumn();


    // NOTE: Actual position calculation is complex and usually done in a batch process.
    // For demonstration, if student_report_summary table has position, we can use it.
    // $stmt_pos = $pdo->prepare("SELECT p1p3_position_in_class FROM student_report_summary WHERE student_id = :student_id AND report_batch_id = :batch_id");
    // $stmt_pos->execute([':student_id' => $student_id, ':batch_id' => $batch_id]);
    // $pos_data = $stmt_pos->fetch(PDO::FETCH_ASSOC);
    // if ($pos_data && isset($pos_data['p1p3_position_in_class'])) {
    //     $position_in_class = $pos_data['p1p3_position_in_class'];
    // }


    return [
        'total_eot_score' => $total_eot_score,
        'average_eot_score' => $average_score,
        'overall_grade' => $overall_grade_info['grade'] ?? '-',
        'comment' => $comment,
        'position_in_class' => $position_in_class, // Placeholder, needs proper calculation
        'total_students_in_class' => $total_students_in_class // Placeholder
    ];
}

// Function to generate class teacher's remark
function generateClassTeacherRemark($summary_data, $student_name, $class_level_group, $school_type, $grading_levels = []) {
    if (!$summary_data) return "Awaiting overall assessment. Keep working hard and ensure all work is submitted.";

    $name = strtok($student_name, " ");

    if (strpos(strtolower($class_level_group), 'primary_lower') !== false) {
        $avg = $summary_data['p1p3_average_eot_score'] ?? null;
        if ($avg === null) return htmlspecialchars($name) . " has some pending results. Overall performance will be assessed once all marks are available. Please ensure all work is completed.";
        if ($avg >= 80) return htmlspecialchars($name) . " has demonstrated excellent understanding and application of concepts. Keep up the great work and continue to explore new ideas!";
        if ($avg >= 70) return htmlspecialchars($name) . " has shown very good progress and understanding. A commendable effort! Continue to strive for excellence.";
        if ($avg >= 60) return htmlspecialchars($name) . " has made good progress this term. Consistent effort will yield even better results. Keep pushing yourself!";
        if ($avg >= 50) return htmlspecialchars($name) . " has shown satisfactory performance. Focus on areas of weakness for improvement and participate more actively.";
        if ($avg >= 40) return htmlspecialchars($name) . " has made some progress but needs to put in more effort in key areas. Consistent practice is recommended.";
        return htmlspecialchars($name) . " needs significant improvement. Consistent effort, regular attendance, and seeking help are advised to improve performance.";
    } else { // P4-P7 or Secondary - based on division
        $division = $summary_data['p4p7_division'] ?? null;
        $aggregates = $summary_data['p4p7_aggregate_points'] ?? null;

        if ($division === null) return htmlspecialchars($name) . " has some pending results. Overall performance will be assessed once all marks are available. Ensure all assessments are completed.";

        switch ($division) {
            case 'I': return htmlspecialchars($name) . " has achieved an excellent result with " . htmlspecialchars($aggregates) . " aggregates. This is a commendable performance. Keep up the dedication and hard work!";
            case 'II': return htmlspecialchars($name) . " has performed very well, attaining Division " . htmlspecialchars($division) . " with " . htmlspecialchars($aggregates) . " aggregates. Your hard work is paying off. Continue to strive for even better!";
            case 'III': return htmlspecialchars($name) . " has shown good effort, resulting in Division " . htmlspecialchars($division) . " with " . htmlspecialchars($aggregates) . " aggregates. With increased focus and consistent revision, you can achieve even better results.";
            case 'IV': return htmlspecialchars($name) . " has made a fair attempt, achieving Division " . htmlspecialchars($division) . " with " . htmlspecialchars($aggregates) . " aggregates. Consistent revision and practice are needed for improvement. Don't be discouraged, keep working hard.";
            case 'U': return htmlspecialchars($name) . " needs to put in significantly more effort to improve performance. Seek assistance from teachers and dedicate more time to studies.";
            case 'X': return htmlspecialchars($name) . " has incomplete results or did not meet the minimum requirements for grading. Please consult the school administration for clarification.";
            default: return htmlspecialchars($name) . " has completed the term with " . htmlspecialchars($aggregates) . " aggregates. Overall performance is " . htmlspecialchars($division) . ". Strive for improvement in the next term.";
        }
    }
}

// Function to generate head teacher's remark
function generateHeadTeacherRemark($summary_data, $student_name, $class_level_group, $school_name, $school_type, $grading_levels = []) {
     if (!$summary_data) return "The school encourages continuous effort and dedication from all students. We look forward to seeing your progress.";

    $name = strtok($student_name, " ");

    if (strpos(strtolower($class_level_group), 'primary_lower') !== false) {
        $avg = $summary_data['p1p3_average_eot_score'] ?? null;
        if ($avg === null) return "The school administration acknowledges " . htmlspecialchars($name) . "'s participation and encourages completion of all assessments for a comprehensive evaluation of progress.";
        if ($avg >= 80) return "An outstanding performance, " . htmlspecialchars($name) . "! " . htmlspecialchars($school_name) . " is proud of your achievements. Keep aiming high and continue to be a role model for your peers.";
        if ($avg >= 70) return "A very good performance, " . htmlspecialchars($name) . ". Your dedication is commendable. " . htmlspecialchars($school_name) . " encourages you to continue to excel and inspire others.";
        if ($avg >= 60) return htmlspecialchars($name) . ", you have made good progress this term. " . htmlspecialchars($school_name) . " appreciates your consistent effort and encourages you to aim for even greater heights next term.";
        if ($avg >= 50) return htmlspecialchars($name) . ", your performance is satisfactory. " . htmlspecialchars($school_name) . " encourages you to identify areas for growth and work towards them diligently. Keep up the effort!";
        return htmlspecialchars($name) . ", there is significant room for improvement. " . htmlspecialchars($school_name) . " encourages you to seek support from teachers and parents and apply yourself more consistently to achieve your potential.";
    } else { // P4-P7 or Secondary - based on division
        $division = $summary_data['p4p7_division'] ?? null;
        $aggregates = $summary_data['p4p7_aggregate_points'] ?? null;

         if ($division === null) return "The school administration acknowledges " . htmlspecialchars($name) . "'s participation and looks forward to seeing complete results for a full evaluation. We encourage continuous effort.";
        switch ($division) {
            case 'I': return "Excellent performance, " . htmlspecialchars($name) . "! " . htmlspecialchars($school_name) . " congratulates you on achieving Division I with " . htmlspecialchars($aggregates) . " aggregates. This reflects your hard work and dedication. Maintain this high standard and continue to inspire.";
            case 'II': return "Well done, " . htmlspecialchars($name) . ", on achieving Division II with " . htmlspecialchars($aggregates) . " aggregates. Your hard work is paying off. " . htmlspecialchars($school_name) . " encourages you to keep striving for excellence and aim even higher.";
            case 'III': return htmlspecialchars($name) . ", a good effort resulting in Division III with " . htmlspecialchars($aggregates) . " aggregates. " . htmlspecialchars($school_name) . " is pleased with your progress and encourages continued focus for even better results in the future.";
            case 'IV': return htmlspecialchars($name) . ", you have passed in Division IV with " . htmlspecialchars($aggregates) . " aggregates. " . htmlspecialchars($school_name) . " encourages you to dedicate more time and effort to your studies for significant improvement. We believe in your potential.";
            case 'U': return htmlspecialchars($name) . ", your performance needs significant improvement. " . htmlspecialchars($school_name) . " urges you to work closely with your teachers and parents to identify challenges and improve your results. Consistent effort is key.";
            case 'X': return htmlspecialchars($name) . " has incomplete results or did not meet the minimum requirements for grading. Please consult the school administration for clarification. The school is committed to supporting your academic journey.";
            default: return htmlspecialchars($name) . ", you have completed the term. " . htmlspecialchars($school_name) . " encourages you to reflect on your performance, identify areas for growth, and aim for continuous improvement in the next term.";
        }
    }
}

?>
```
