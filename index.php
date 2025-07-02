<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Optional: Set a flash message to explain why they are on the login page
    // $_SESSION['login_error_message'] = "You must be logged in to access this page.";
    header('Location: login.php');
    exit;
}

// No specific data processing needed on the dashboard itself for this initial version,
// but session_start() is good practice if we add user-specific elements later.
date_default_timezone_set('Africa/Kampala'); // Or user's preferred timezone

require_once 'db_connection.php'; // Ensure $pdo is available

// --- Dashboard Progress Data Fetching ---
$current_year_name = date('Y');
$current_academic_year_id = null; // Initialize
$dashboard_progress_data = [];   // Initialize
$classes_list = [];              // Initialize
$terms_list = [];                // Initialize
$school_id = $_SESSION['school_id'] ?? null; // Get school_id from session

if ($school_id) { // Only proceed if school_id is set
    try {
        // Fetch the active academic year for the current school
        $stmt_ay = $pdo->prepare("SELECT id FROM academic_years WHERE school_id = :school_id AND year_name = :year_name AND is_active = 1 LIMIT 1");
        if (!$stmt_ay) {
            throw new Exception("Failed to prepare statement for academic years.");
        }
        $stmt_ay->execute([':school_id' => $school_id, ':year_name' => $current_year_name]);
        $current_academic_year_id = $stmt_ay->fetchColumn();

        if (!$current_academic_year_id) {
            // If current year is not active or not found, try to get any active year for the school
            $stmt_ay_active = $pdo->prepare("SELECT id FROM academic_years WHERE school_id = :school_id AND is_active = 1 ORDER BY year_name DESC LIMIT 1");
            if (!$stmt_ay_active) {
                throw new Exception("Failed to prepare statement for active academic year.");
            }
            $stmt_ay_active->execute([':school_id' => $school_id]);
            $current_academic_year_id = $stmt_ay_active->fetchColumn();
            if ($current_academic_year_id) {
                 // Fetch the name of this active year to display
                $stmt_ay_name = $pdo->prepare("SELECT year_name FROM academic_years WHERE id = :id");
                $stmt_ay_name->execute([':id' => $current_academic_year_id]);
                $current_year_name = $stmt_ay_name->fetchColumn();
            }
        }


        if ($current_academic_year_id) {
            $stmt_classes = $pdo->prepare("SELECT id, class_name FROM classes WHERE school_id = :school_id ORDER BY class_name ASC");
            if (!$stmt_classes) {
                throw new Exception("Failed to prepare statement for classes.");
            }
            $stmt_classes->execute([':school_id' => $school_id]);
            $classes_list = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

            $stmt_terms = $pdo->prepare("SELECT id, term_name FROM terms WHERE school_id = :school_id ORDER BY order_index ASC, id ASC");
            if (!$stmt_terms) {
                throw new Exception("Failed to prepare statement for terms.");
            }
            $stmt_terms->execute([':school_id' => $school_id]);
            $terms_list = $stmt_terms->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($classes_list) && !empty($terms_list)) {
                $stmt_batch_check = $pdo->prepare(
                    "SELECT id FROM report_batch_settings
                     WHERE school_id = :school_id
                       AND academic_year_id = :academic_year_id
                       AND class_id = :class_id
                       AND term_id = :term_id
                     LIMIT 1"
                );
                if (!$stmt_batch_check) {
                    throw new Exception("Failed to prepare statement for batch check.");
                }

                $stmt_summary_check = $pdo->prepare(
                    "SELECT COUNT(id) FROM student_report_summary WHERE report_batch_id = :report_batch_id LIMIT 1"
                );
                if (!$stmt_summary_check) {
                    throw new Exception("Failed to prepare statement for summary check.");
                }

                foreach ($classes_list as $class_item) {
                    $class_id = $class_item['id'];
                    $class_name = $class_item['class_name'];
                    $dashboard_progress_data[$class_name] = [];

                    foreach ($terms_list as $term_item) {
                        $term_id = $term_item['id'];
                        $term_name_key = $term_item['term_name'];

                        $stmt_batch_check->execute([
                            ':school_id' => $school_id,
                            ':academic_year_id' => $current_academic_year_id,
                            ':class_id' => $class_id,
                            ':term_id' => $term_id
                        ]);
                        $report_batch_id = $stmt_batch_check->fetchColumn();
                        $status = "Pending";

                        if ($report_batch_id) {
                            $stmt_summary_check->execute([':report_batch_id' => $report_batch_id]);
                            $summary_count = $stmt_summary_check->fetchColumn();
                            if ($summary_count !== false && $summary_count > 0) {
                                $status = "Completed";
                            } else {
                                $status = "Data Imported";
                            }
                        }
                        $dashboard_progress_data[$class_name][$term_name_key] = [
                            'status' => $status,
                            'report_batch_id' => $report_batch_id
                        ];
                    }
                }
            } else {
                 $_SESSION['info_message'] = "Dashboard Progress: No classes or terms found for your school. Please configure them in settings.";
            }
        } else {
            $_SESSION['info_message'] = "Dashboard Progress: No active academic year found for your school for " . htmlspecialchars(date('Y')) . ". Please set an active academic year in settings. Progress display may be incomplete.";
        }
    } catch (PDOException $e) {
        error_log("Dashboard Progress Data Fetching Error (PDO): " . $e->getMessage());
        $_SESSION['error_message'] = "Could not load dashboard progress data due to a database error.";
    } catch (Exception $e) {
        error_log("Dashboard Progress Data Fetching Error: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while fetching dashboard data: " . $e->getMessage();
    }
} else if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'superadmin') {
    // Logged in user is not a superadmin and has no school_id associated
    $_SESSION['error_message'] = "Your user account is not associated with a school. Please contact support.";
}
// --- End Dashboard Progress Data Fetching ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo isset($_SESSION['school_name']) ? htmlspecialchars($_SESSION['school_name']) : 'Arturomania Systems'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet"> <!-- Font Awesome for icons -->
    <!-- School-specific favicon can be implemented later if desired -->
    <link rel="icon" type="image/png" href="<?php echo isset($_SESSION['school_logo_path']) && !empty($_SESSION['school_logo_path']) ? htmlspecialchars($_SESSION['school_logo_path']) : 'aslogo.png'; ?>">
    <style>
        body {
            background-color: #f4f7f6; /* A more neutral background */
            display: flex;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        #sidebar {
            min-width: 280px; /* Increased width */
            max-width: 280px; /* Increased width */
            background: #007bff; /* Bootstrap primary blue */
            color: #fff;
            transition: all 0.3s;
            position: fixed; /* Fixed Sidebar */
            height: 100%;
            overflow-y: auto;
        }
        #sidebar.active {
            margin-left: -280px; /* Adjusted for increased width */
        }
        #sidebar .sidebar-header {
            padding: 20px;
            background: #0069d9; /* Darker blue */
            text-align: center;
        }
        #sidebar .sidebar-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 2px solid #fff;
        }
        #sidebar ul.components {
            padding: 20px 0;
            border-bottom: 1px solid #47748b;
        }
        #sidebar ul p {
            color: #fff;
            padding: 10px;
            text-align: center;
            font-size: 0.9em;
        }
        #sidebar ul li a {
            padding: 12px 20px;
            font-size: 1.1em;
            display: block;
            color: #f8f9fa; /* Lighter text for better contrast on blue */
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }
        #sidebar ul li a:hover {
            color: #007bff; /* Blue text */
            background: #fff; /* White background */
        }
        #sidebar ul li.active > a, a[aria-expanded="true"] {
            color: #fff;
            background: #0062cc; /* Slightly darker blue for active */
        }
        #content {
            width: calc(100% - 280px); /* Adjust based on new sidebar width */
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
            margin-left: 280px; /* Match new sidebar width */
            background-color: #e0f7fa; /* Ensure content background matches body */
        }
        #content.active {
            width: 100%;
            margin-left: 0;
        }
        .navbar-custom {
            background-color: #ffffff; /* White navbar */
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .datetime-display {
            font-size: 0.9em;
            color: #dee2e6; /* Brighter color for date/time */
        }
        .main-content-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px; /* Hide sidebar by default on smaller screens */
            }
            #sidebar.active {
                margin-left: 0; /* Show sidebar when active */
            }
            #content {
                width: 100%;
                margin-left: 0;
            }
            #content.active { /* When sidebar is open on small screen, content might need to shift or be overlaid */
                 margin-left: 250px; /* Example: shift content if sidebar is not an overlay */
            }
             #sidebarCollapse span { display: block; } /* Always show toggler */
        }

        /* Activity Feed Dropdown Item Text Styling */
        #adminActivityDropdownBody .dropdown-item-text {
            white-space: normal; /* Allow text to wrap */
            word-wrap: break-word; /* Break long words */
            line-height: 1.3; /* Adjust line height for readability */
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
        }
        #adminActivityDropdownBody .dropdown-item-text strong {
            color: #0056b3; /* Theme color for username */
        }
         #adminActivityDropdownBody .dropdown-item-text .text-muted[title] { /* Hint that description is truncated */
            text-decoration: underline dotted;
            text-decoration-color: #6c757d; /* Bootstrap's text-muted color */
            text-decoration-thickness: 1px;
        }

    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <!-- Placeholder for school logo -->
            <img src="<?php echo isset($_SESSION['school_logo_path']) ? htmlspecialchars($_SESSION['school_logo_path']) : 'images/default_logo.png'; ?>"
                 alt="<?php echo isset($_SESSION['school_name']) ? htmlspecialchars($_SESSION['school_name']) : 'School'; ?> Logo"
                 onerror="this.src='images/default_logo.png'; this.alt='Default School Logo';"
                 style="width: 80px; height: 80px; border-radius: 50%; margin-bottom: 10px; border: 2px solid #fff;">
            <h5><?php echo isset($_SESSION['school_name']) ? htmlspecialchars($_SESSION['school_name']) : '[School Name]'; ?></h5>
            <p class="datetime-display"><?php echo date("D, d M Y H:i"); ?></p>
        </div>

        <ul class="list-unstyled components">
            <!-- This "Main Navigation" title could be dynamic too if needed -->
            <p><?php echo isset($_SESSION['school_name']) ? htmlspecialchars($_SESSION['school_name']) : 'School'; ?> Menu</p>
            <li class="active"> <!-- Example: make dashboard link active by default -->
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard Home</a>
            </li>
            <li>
                <a href="#templateSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fas fa-file-excel"></i> Download Template</a>
                <ul class="collapse list-unstyled" id="templateSubmenu">
                    <li>
                        <a href="download_template.php?type=lower" style="padding-left: 30px;"><i class="fas fa-child fa-fw me-2"></i>Lower Primary</a>
                    </li>
                    <li>
                        <a href="download_template.php?type=upper" style="padding-left: 30px;"><i class="fas fa-user-graduate fa-fw me-2"></i>Upper Primary</a>
                    </li>
                </ul>
            </li>
            <li>
                <a href="data_entry.php"><i class="fas fa-edit"></i> Marks Entry</a> <!-- Was "Generate New Reports" -->
            </li>
            <li>
                <a href="view_report_archives.php"><i class="fas fa-archive"></i> View Report Archives</a> <!-- New page, replaces old "View Processed Data" & "Report Archives" submenu -->
            </li>
            <li>
                <a href="summary_sheet.php"><i class="fas fa-chart-pie"></i> Summary Sheets</a> <!-- Direct link, summary_sheet.php handles batch selection -->
            </li>
            <li>
                <a href="#studentReportsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fas fa-user-graduate"></i> Student Analytics</a>
                <ul class="collapse list-unstyled" id="studentReportsSubmenu">
                    <li>
                        <a href="historical_performance.php" style="white-space: nowrap;"><i class="fas fa-history"></i> Historical Performance</a>
                    </li>
                    <li>
                        <a href="comparative_analysis.php"><i class="fas fa-balance-scale"></i> Comparative Analysis</a>
                    </li>
                </ul>
            </li>
            <li>
                <a href="about.php"><i class="fas fa-book-reader"></i> User Manual</a> <!-- Changed icon and text -->
            </li>
            <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'school_admin' || $_SESSION['role'] === 'superadmin')): ?>
            <li>
                <a href="manage_grading.php"><i class="fas fa-balance-scale-right"></i> Grading Policies</a>
            </li>
            <?php endif; ?>
            <!--
            <li>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
            -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
            <li class="mt-3 pt-2 border-top border-secondary-subtle"> <!-- Visually separate admin links -->
                <p class="text-white-50 small ps-3 text-uppercase">Administration</p>
            </li>
            <li>
                <a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Page Content -->
    <div id="content">
        <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-info">
                    <i class="fas fa-align-left"></i>
                    <span>Toggle Sidebar</span>
                </button>

                <!-- Right aligned navbar items -->
                <div class="ms-auto d-flex align-items-center">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
                        <!-- Superadmin Activity Feed Bell -->
                        <div class="nav-item dropdown me-2">
                            <a class="nav-link" href="#" id="adminActivityBellLink" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Recent Activity">
                                <i class="fas fa-bell text-secondary"></i>
                                <span id="adminActivityBadge" class="badge rounded-pill bg-danger ms-1" style="display:none; font-size: 0.6em; vertical-align: top; margin-left: -2px;"></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="adminActivityBellLink" id="adminActivityDropdown" style="width: 380px; max-height: 450px; overflow-y: auto;">
                                <li><h6 class="dropdown-header bg-light py-2">Recent System Activity</h6></li>
                                <li><div id="adminActivityDropdownBody" class="p-2">
                                    <p class="text-center text-muted my-3">Loading activities...</p>
                                </div></li>
                                <li><hr class="dropdown-divider my-0"></li>
                                <li><a class="dropdown-item text-center py-1 small" id="adminDismissNotificationsLink" href="#">Mark All as Read</a></li>
                                <li><a class="dropdown-item text-center py-1 small" href="view_activity_log.php">View All Logs</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id'])): // Check if user is logged in ?>
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-dark" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1 fa-lg"></i>
                                <?php
                                    // Display full_name if available, otherwise email
                                    echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email'] ?? 'User');
                                ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <!-- Link to a profile page if you create one -->
                                <!-- <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog me-2"></i>My Profile</a></li> -->
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-address-card me-2"></i>About User</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Others (Placeholder)</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Optional: Show a Login button if user is not logged in, though page protection should handle this -->
                        <!-- <a href="login.php" class="btn btn-primary">Login</a> -->
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <div class="container-fluid pt-3 text-center"> <!-- Centering container for h2 and card -->
            <h2 class="mb-3"><?php echo isset($_SESSION['school_name']) ? htmlspecialchars($_SESSION['school_name']) : 'School'; ?> Dashboard</h2> <!-- Added margin-bottom to h2 -->
            <div class="main-content-card d-inline-block" style="max-width: 800px; width: 100%;"> <!-- d-inline-block and max-width for centering block elements -->
                <p style="text-align: justify;">Welcome to the <?php echo isset($_SESSION['school_name']) ? htmlspecialchars($_SESSION['school_name']) : 'School'; ?> Report Card System Dashboard. Use the sidebar to navigate through the available options. You can generate new reports, view summaries, or download templates.</p>
                <!-- More dashboard widgets/summaries can go here later -->
                <!-- Note: Data fetching logic above this (lines 20-85) needs to be updated with school_id from session -->
            </div>

            <?php if (!empty($dashboard_progress_data) && !empty($classes_list) && !empty($terms_list) && $current_academic_year_id): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Data Progress for Academic Year <?php echo htmlspecialchars($current_year_name); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Class</th> <!-- Changed back -->
                                    <?php foreach ($terms_list as $term): ?>
                                        <th>Term <?php echo htmlspecialchars($term['term_name']); ?></th> <!-- Prepended "Term " -->
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes_list as $class_item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($class_item['class_name']); ?></strong></td>
                                        <?php foreach ($terms_list as $term_item): ?>
                                            <?php
                                                $status_info = $dashboard_progress_data[$class_item['class_name']][$term_item['term_name']] ?? ['status' => 'Pending', 'report_batch_id' => null];
                                                $status_text = $status_info['status'];
                                                $batch_id_for_link = $status_info['report_batch_id'];
                                                $badge_class = 'bg-secondary'; // Default for Pending
                                                $icon = 'fas fa-hourglass-half'; // Default for Pending
                                                $link = '#'; // Default link

                                                if ($status_text === 'Completed') {
                                                    $badge_class = 'bg-success';
                                                    $icon = 'fas fa-check-circle';
                                                    if ($batch_id_for_link) {
                                                        $link = 'view_processed_data.php?batch_id=' . htmlspecialchars($batch_id_for_link);
                                                    }
                                                } elseif ($status_text === 'Data Imported') {
                                                    $badge_class = 'bg-warning text-dark'; // Ensure text is dark on yellow
                                                    $icon = 'fas fa-file-import';
                                                     if ($batch_id_for_link) {
                                                        $link = 'view_processed_data.php?batch_id=' . htmlspecialchars($batch_id_for_link);
                                                    }
                                                } else { // Pending
                                                     // Link to data_entry.php, potentially pre-filling class, term, year would be an enhancement
                                                     // For now, a general link or no link.
                                                     // Let's make it link to data_entry.php for now.
                                                     $link = 'data_entry.php';
                                                }
                                            ?>
                                            <td>
                                                <?php if ($link !== '#'): ?>
                                                    <a href="<?php echo $link; ?>" class="text-decoration-none">
                                                        <span class="badge <?php echo $badge_class; ?> p-2">
                                                            <i class="<?php echo $icon; ?> me-1"></i>
                                                            <?php echo htmlspecialchars($status_text); ?>
                                                        </span>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge <?php echo $badge_class; ?> p-2">
                                                        <i class="<?php echo $icon; ?> me-1"></i>
                                                        <?php echo htmlspecialchars($status_text); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php elseif (!$current_academic_year_id && isset($_SESSION['user_id'])): // Show message if year not found but user logged in ?>
                <!-- Message about academic year not found is already handled by session message display at top of page -->
            <?php endif; ?>
        </div>
         <footer class="mt-auto py-3 bg-light text-center">
            <div class="container">
                <span class="text-muted">&copy; <?php echo date("Y"); ?> <?php echo isset($_SESSION['school_name']) ? htmlspecialchars($_SESSION['school_name']) : 'Arturomania Systems'; ?>.
                    <?php echo isset($_SESSION['school_motto']) ? htmlspecialchars($_SESSION['school_motto']) : 'Empowering Education.'; ?>
                </span>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var sidebarCollapse = document.getElementById('sidebarCollapse');
            var sidebar = document.getElementById('sidebar');
            var content = document.getElementById('content');

            if(sidebarCollapse) {
                sidebarCollapse.addEventListener('click', function () {
                    sidebar.classList.toggle('active');
                    content.classList.toggle('active');
                });
            }

            // Superadmin Activity Feed
            const adminActivityBellLink = document.getElementById('adminActivityBellLink');
            const adminActivityDropdownBody = document.getElementById('adminActivityDropdownBody');
            const adminActivityBadge = document.getElementById('adminActivityBadge');
            const adminDismissNotificationsLink = document.getElementById('adminDismissNotificationsLink');
            let newestActivityTimestamp = null; // To store the timestamp of the newest fetched activity

            // This value will be updated by fetchInitialDismissTimestamp
            let dbLastDismissedTimestamp = null;

            async function fetchInitialDismissTimestamp() {
                if (adminActivityBellLink && <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') ? 'true' : 'false'; ?>) {
                    try {
                        const response = await fetch('api_get_dismissal_timestamp.php');
                        if (!response.ok) throw new Error('Failed to fetch dismissal timestamp - Network error');
                        const data = await response.json();
                        if (data.success && data.timestamp) {
                            // Assuming API now returns UTC 'YYYY-MM-DD HH:MM:SS' from DB
                            dbLastDismissedTimestamp = data.timestamp;
                            console.log('Fetched initial dbLastDismissedTimestamp (UTC string from DB):', dbLastDismissedTimestamp);
                        } else if (data.success && data.timestamp === null) {
                            console.log('Initial dbLastDismissedTimestamp is null (from DB).');
                            dbLastDismissedTimestamp = null;
                        } else {
                            console.warn('API_get_dismissal_timestamp did not return success or valid timestamp:', data);
                            dbLastDismissedTimestamp = null;
                        }
                    } catch (error) {
                        console.error('Error fetching initial dismissal timestamp:', error);
                        dbLastDismissedTimestamp = null;
                    }
                }
            }

            /**
             * Parses an EAT timestamp string (DD/MM/YYYY HH:MM:SS) into a JavaScript Date object (internally UTC).
             * @param {string} eatTimestampStr The EAT timestamp string.
             * @returns {Date|null} A Date object or null if parsing fails.
             */
            function parseEatTimestampToDateObject(eatTimestampStr) {
                if (!eatTimestampStr || typeof eatTimestampStr !== 'string') {
                    console.warn('parseEatTimestampToDateObject: Invalid input', eatTimestampStr);
                    return null;
                }
                // Regex for DD/MM/YYYY HH:MM:SS
                const parts = eatTimestampStr.match(/^(\d{2})\/(\d{2})\/(\d{4})\s(\d{2}):(\d{2}):(\d{2})$/);
                if (!parts) {
                    console.error('parseEatTimestampToDateObject: Invalid EAT timestamp format:', eatTimestampStr);
                    return null;
                }

                const day = parseInt(parts[1], 10);
                const month = parseInt(parts[2], 10) - 1; // JS months are 0-indexed
                const year = parseInt(parts[3], 10);
                const hours = parseInt(parts[4], 10);
                const minutes = parseInt(parts[5], 10);
                const seconds = parseInt(parts[6], 10);

                // Construct an ISO 8601 string with EAT offset (+03:00)
                // YYYY-MM-DDTHH:MM:SS+03:00
                // Ensure components are padded if needed (though regex ensures 2 digits for most)
                const isoEatStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}T${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}+03:00`;

                const d = new Date(isoEatStr);
                if (isNaN(d.getTime())) {
                    console.error('parseEatTimestampToDateObject: Failed to parse EAT timestamp string into valid Date using ISO:', isoEatStr, 'Original:', eatTimestampStr);
                    return null;
                }
                // console.log('parseEatTimestampToDateObject: Parsed', eatTimestampStr, '(EAT) to Date Object:', d.toISOString(), d);
                return d; // This Date object correctly represents the EAT instant.
            }


            function formatRelativeTime(timestamp) { // timestamp is DD/MM/YYYY HH:MM:SS EAT
                const dateObj = parseEatTimestampToDateObject(timestamp);
                if (!dateObj || isNaN(dateObj.getTime())) {
                    return timestamp; // Return original if parsing fails
                }
                const now = new Date();
                const diffInSeconds = Math.floor((now - dateObj) / 1000);

                if (diffInSeconds < 5) return `just now`;
                if (diffInSeconds < 60) return `${diffInSeconds}s ago`;
                const diffInMinutes = Math.floor(diffInSeconds / 60);
                if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
                const diffInHours = Math.floor(diffInMinutes / 60);
                if (diffInHours < 24) return `${diffInHours}h ago`;
                const diffInDays = Math.floor(diffInHours / 24);
                if (diffInDays < 7) return `${diffInDays}d ago`;

                return past.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }); // Example: 12 Jan
            }

            function fetchAdminActivityFeed() {
                if (!adminActivityDropdownBody) return; // Only run if the element exists (i.e., user is superadmin)

                fetch('api_admin_activity.php?limit=10')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(activities => {
                        adminActivityDropdownBody.innerHTML = ''; // Clear previous items or "Loading..."
                        if (activities.length > 0) {
                            // Determine the newest timestamp from the fetched activities
                            newestActivityTimestamp = activities[0].timestamp; // Assuming activities are sorted DESC by timestamp
                            console.log('Admin Feed: Fetched activities:', JSON.parse(JSON.stringify(activities)));
                            console.log('Admin Feed: Current dbLastDismissedTimestamp for comparison:', dbLastDismissedTimestamp);

                            activities.forEach(activity => {
                                const itemDiv = document.createElement('div');
                                itemDiv.classList.add('dropdown-item-text', 'small', 'border-bottom', 'pb-1', 'mb-1');

                                let actionTypeFormatted = activity.action_type.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
                                let descriptionSnippet = activity.description.length > 70 ? activity.description.substring(0, 67) + '...' : activity.description;

                                itemDiv.innerHTML = `
                                    <strong>${activity.username}</strong>: ${actionTypeFormatted}<br>
                                    <span class="text-muted" title="${activity.description}">${descriptionSnippet}</span><br>
                                    <small class="text-muted fst-italic">${formatRelativeTime(activity.timestamp)}</small>
                                `;
                                adminActivityDropdownBody.appendChild(itemDiv);
                            });

                            // Badge Logic: Count activities newer than the dbLastDismissedTimestamp
                            let newActivitiesCount = 0;
                            if (dbLastDismissedTimestamp) {
                                activities.forEach(activity => {
                            if (dbLastDismissedTimestamp) {
                                // dbLastDismissedTimestamp is UTC 'YYYY-MM-DD HH:MM:SS' from DB
                                const dismissedUtcDate = new Date(dbLastDismissedTimestamp.replace(' ', 'T') + 'Z');
                                if (isNaN(dismissedUtcDate.getTime())) {
                                    console.error('Admin Feed: Invalid dbLastDismissedTimestamp after parsing:', dbLastDismissedTimestamp);
                                    newActivitiesCount = activities.length; // Treat all as new if dismiss TS is bad
                                    console.log('Admin Feed: Invalid dbLastDismissedTimestamp, all activities considered new.');
                                } else {
                                    console.log('Admin Feed: DismissedDate object (UTC) for comparison:', dismissedUtcDate.toISOString());
                                    activities.forEach(activity => {
                                        // activity.timestamp is EAT 'DD/MM/YYYY HH:MM:SS'
                                        const activityDateObject = parseEatTimestampToDateObject(activity.timestamp);

                                        if (activityDateObject && !isNaN(activityDateObject.getTime())) {
                                            const isNew = activityDateObject > dismissedUtcDate;
                                            console.log(`Comparing EAT Activity (${activity.timestamp} -> ${activityDateObject.toISOString()}) > UTC Dismissed (${dbLastDismissedTimestamp} -> ${dismissedUtcDate.toISOString()}) = ${isNew}`);
                                            if (isNew) {
                                                newActivitiesCount++;
                                            }
                                        } else {
                                            console.warn('Admin Feed: Could not parse activity timestamp, cannot compare:', activity.timestamp);
                                            // Optionally count as new or skip, here skipping
                                        }
                                    });
                                }
                            } else {
                                // If no dismiss timestamp from DB, all fetched activities are considered new
                                newActivitiesCount = activities.length;
                                console.log('Admin Feed: No dbLastDismissedTimestamp, all activities considered new.');
                            }
                            console.log('Admin Feed: Calculated newActivitiesCount:', newActivitiesCount);

                            if (adminActivityBadge) {
                                if (newActivitiesCount > 0) {
                                    adminActivityBadge.textContent = newActivitiesCount > 9 ? '9+' : newActivitiesCount.toString();
                                    adminActivityBadge.style.display = 'inline-block';
                                } else {
                                    adminActivityBadge.style.display = 'none';
                                }
                            }

                        } else {
                            adminActivityDropdownBody.innerHTML = '<p class="text-center text-muted my-3">No recent activity.</p>';
                            if (adminActivityBadge) {
                                adminActivityBadge.style.display = 'none';
                            }
                             console.log('Admin Feed: No activities fetched or activities array is empty.');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching admin activity feed:', error);
                        adminActivityDropdownBody.innerHTML = '<p class="text-center text-danger my-3">Could not load activity.</p>';
                        if (adminActivityBadge) {
                             adminActivityBadge.style.display = 'none';
                        }
                    });
            }

            if (adminActivityBellLink) {
                // Initial fetch of dismissal timestamp when page loads for superadmin
                fetchInitialDismissTimestamp().then(() => {
                    fetchAdminActivityFeed(); // Initial feed fetch after getting dismiss timestamp
                });

                var activityDropdownInstance = new bootstrap.Dropdown(adminActivityBellLink);

                adminActivityBellLink.addEventListener('show.bs.dropdown', async function () {
                    // Re-fetch dismissal timestamp in case it was updated elsewhere or by another admin session (though less likely for this specific TS)
                    // More importantly, ensures dbLastDismissedTimestamp is fresh if initial fetch failed.
                    await fetchInitialDismissTimestamp();
                    fetchAdminActivityFeed(); // This will now use the potentially updated dbLastDismissedTimestamp
                });

                if (adminDismissNotificationsLink) {
                    adminDismissNotificationsLink.addEventListener('click', function(e) {
                        e.preventDefault();

                        let utcTimestampToStore;
                        if (newestActivityTimestamp) { // newestActivityTimestamp is DD/MM/YYYY HH:MM:SS EAT
                            const parsedDate = parseEatTimestampToDateObject(newestActivityTimestamp);
                            if (parsedDate && !isNaN(parsedDate.getTime())) {
                                // Convert the EAT date object to a 'YYYY-MM-DD HH:MM:SS' UTC string
                                utcTimestampToStore = parsedDate.toISOString().slice(0, 19).replace('T', ' ');
                            } else {
                                // Fallback if parsing newestActivityTimestamp fails, use current UTC time
                                console.warn('Admin Feed: Failed to parse newestActivityTimestamp, using current UTC time for dismissal.');
                                utcTimestampToStore = new Date().toISOString().slice(0, 19).replace('T', ' ');
                            }
                        } else {
                            // Feed was empty, use current UTC time
                            utcTimestampToStore = new Date().toISOString().slice(0, 19).replace('T', ' ');
                        }
                        console.log('Admin Feed: "Mark All as Read" clicked. UTC Timestamp to store in DB:', utcTimestampToStore);

                        // Update in DB
                        fetch('api_update_dismissal_timestamp.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'timestamp=' + encodeURIComponent(timestampToStore)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                dbLastDismissedTimestamp = timestampToStore; // Update local JS variable
                                if (adminActivityBadge) {
                                    adminActivityBadge.style.display = 'none';
                                    adminActivityBadge.textContent = '0';
                                }
                                console.log('Dismissal timestamp updated in DB.');
                            } else {
                                console.error('Failed to update dismissal timestamp in DB:', data.error);
                                // Optionally inform user of failure
                            }
                        })
                        .catch(error => {
                            console.error('Error updating dismissal timestamp:', error);
                        });

                        // Close the dropdown
                        activityDropdownInstance.hide();
                    });
                }

                // Initial fetch of dismissal timestamp when page loads for superadmin
                fetchInitialDismissTimestamp().then(() => {
                    fetchAdminActivityFeed(); // Initial feed fetch after getting dismiss timestamp
                });

            } else { // Not a superadmin or element not found
                 if (adminActivityBellLink) adminActivityBellLink.style.display = 'none'; // Hide bell if not superadmin
            }
        });
    </script>
</body>
</html>
