<?php
session_start();
require_once 'db_connection.php'; // Provides $pdo

// Check if user is logged in and is a school_admin or superadmin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'school_admin' && $_SESSION['role'] !== 'superadmin')) {
    // Redirect to login page or an unauthorized page
    $_SESSION['error_message'] = "You are not authorized to access this page.";
    header('Location: login.php');
    exit;
}

$school_id = $_SESSION['school_id'];
$school_name = $_SESSION['school_name'] ?? 'Your School'; // Fallback for display

// Fetch existing grading policies for this school
$policies = [];
if ($school_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM grading_policies WHERE school_id = :school_id ORDER BY name ASC");
        $stmt->execute([':school_id' => $school_id]);
        $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each policy, fetch its levels
        $stmt_levels = $pdo->prepare("SELECT * FROM grading_policy_levels WHERE grading_policy_id = :policy_id ORDER BY order_index ASC, min_score DESC");
        foreach ($policies as $key => $policy) {
            $stmt_levels->execute([':policy_id' => $policy['id']]);
            $policies[$key]['levels'] = $stmt_levels->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error fetching grading policies: " . $e->getMessage();
        // Optionally log the error
    }
} elseif ($_SESSION['role'] !== 'superadmin') {
    $_SESSION['error_message'] = "School ID not found in session. Please re-login or contact support.";
    // Potentially redirect or disable functionality
}

$school_type_from_session = $_SESSION['school_type'] ?? 'primary'; // Default to primary if not set, or could be an error

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grading Policies - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
        .container { max-width: 900px; }
        .card { margin-bottom: 20px; }
        .table th, .table td { vertical-align: middle; }
        .btn-sm i { margin-right: 5px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><?php echo htmlspecialchars($school_name); ?> Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Grading Policies</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPolicyModal">
                <i class="fas fa-plus"></i> Add New Policy
            </button>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <?php if ($school_id): ?>
            <?php if (empty($policies)): ?>
                <div class="alert alert-info">No grading policies found for your school. Click "Add New Policy" to create one.</div>
            <?php else: ?>
                <?php foreach ($policies as $policy): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>
                                <?php echo htmlspecialchars($policy['name']); ?>
                                <?php if ($policy['is_default']): ?>
                                    <span class="badge bg-success ms-2">Default</span>
                                <?php endif; ?>
                                <small class="text-muted">(Type: <?php echo ucfirst(htmlspecialchars($policy['school_type_applicability'])); ?>)</small>
                            </h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editPolicyModal"
                                        data-policy-id="<?php echo $policy['id']; ?>"
                                        data-policy-name="<?php echo htmlspecialchars($policy['name']); ?>"
                                        data-policy-type="<?php echo htmlspecialchars($policy['school_type_applicability']); ?>"
                                        data-policy-default="<?php echo $policy['is_default']; ?>">
                                    <i class="fas fa-edit"></i> Edit Policy
                                </button>
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addGradeLevelModal" data-policy-id="<?php echo $policy['id']; ?>">
                                    <i class="fas fa-plus-circle"></i> Add Grade Level
                                </button>
                                 <form action="handle_grading_policy.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this policy and all its grade levels?');">
                                    <input type="hidden" name="action" value="delete_policy">
                                    <input type="hidden" name="policy_id" value="<?php echo $policy['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger ms-2">
                                        <i class="fas fa-trash"></i> Delete Policy
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($policy['levels'])): ?>
                                <p class="text-muted">No grade levels defined for this policy yet.</p>
                            <?php else: ?>
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Label</th>
                                            <th>Min Score</th>
                                            <th>Max Score</th>
                                            <th>Comment</th>
                                            <th>Points</th>
                                            <th>Order</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($policy['levels'] as $level): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($level['grade_label']); ?></td>
                                                <td><?php echo htmlspecialchars($level['min_score']); ?></td>
                                                <td><?php echo htmlspecialchars($level['max_score']); ?></td>
                                                <td><?php echo htmlspecialchars($level['comment']); ?></td>
                                                <td><?php echo htmlspecialchars($level['points']); ?></td>
                                                <td><?php echo htmlspecialchars($level['order_index']); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editGradeLevelModal"
                                                            data-level-id="<?php echo $level['id']; ?>"
                                                            data-policy-id="<?php echo $policy['id']; ?>"
                                                            data-grade-label="<?php echo htmlspecialchars($level['grade_label']); ?>"
                                                            data-min-score="<?php echo htmlspecialchars($level['min_score']); ?>"
                                                            data-max-score="<?php echo htmlspecialchars($level['max_score']); ?>"
                                                            data-comment="<?php echo htmlspecialchars($level['comment']); ?>"
                                                            data-points="<?php echo htmlspecialchars($level['points']); ?>"
                                                            data-order-index="<?php echo htmlspecialchars($level['order_index']); ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <form action="handle_grading_policy.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this grade level?');">
                                                        <input type="hidden" name="action" value="delete_grade_level">
                                                        <input type="hidden" name="level_id" value="<?php echo $level['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php elseif ($_SESSION['role'] === 'superadmin'): ?>
            <div class="alert alert-warning">Superadmin view: Please select a school to manage their grading policies, or implement global policy management if required.</div>
        <?php else: ?>
            <div class="alert alert-danger">Cannot manage grading policies. School information is missing.</div>
        <?php endif; ?>
    </div>

    <!-- Add Policy Modal -->
    <div class="modal fade" id="addPolicyModal" tabindex="-1" aria-labelledby="addPolicyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="handle_grading_policy.php" method="POST">
                    <input type="hidden" name="action" value="create_policy">
                    <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPolicyModalLabel">Add New Grading Policy</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="policy_name" class="form-label">Policy Name</label>
                            <input type="text" class="form-control" id="policy_name" name="policy_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="policy_school_type" class="form-label">Applicable School Type</label>
                            <select class="form-select" id="policy_school_type" name="policy_school_type" required>
                                <option value="primary" <?php echo ($school_type_from_session == 'primary') ? 'selected' : ''; ?>>Primary</option>
                                <option value="secondary" <?php echo ($school_type_from_session == 'secondary') ? 'selected' : ''; ?>>Secondary</option>
                                <option value="other" <?php echo ($school_type_from_session == 'other') ? 'selected' : ''; ?>>Other</option>
                                <option value="any">Any (General)</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="policy_is_default" name="policy_is_default">
                            <label class="form-check-label" for="policy_is_default">
                                Set as default policy for this school type
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Policy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Policy Modal -->
    <div class="modal fade" id="editPolicyModal" tabindex="-1" aria-labelledby="editPolicyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="handle_grading_policy.php" method="POST">
                    <input type="hidden" name="action" value="update_policy">
                    <input type="hidden" name="policy_id" id="edit_policy_id">
                    <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPolicyModalLabel">Edit Grading Policy</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_policy_name" class="form-label">Policy Name</label>
                            <input type="text" class="form-control" id="edit_policy_name" name="policy_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_policy_school_type" class="form-label">Applicable School Type</label>
                            <select class="form-select" id="edit_policy_school_type" name="policy_school_type" required>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                                <option value="other">Other</option>
                                <option value="any">Any (General)</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="edit_policy_is_default" name="policy_is_default">
                            <label class="form-check-label" for="edit_policy_is_default">
                                Set as default policy for this school type
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Grade Level Modal -->
    <div class="modal fade" id="addGradeLevelModal" tabindex="-1" aria-labelledby="addGradeLevelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="handle_grading_policy.php" method="POST">
                    <input type="hidden" name="action" value="add_grade_level">
                    <input type="hidden" name="policy_id" id="add_level_policy_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addGradeLevelModalLabel">Add Grade Level</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="grade_label" class="form-label">Grade Label (e.g., D1, A)</label>
                                <input type="text" class="form-control" id="grade_label" name="grade_label" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="points" class="form-label">Points (e.g., 1, 9)</label>
                                <input type="number" step="any" class="form-control" id="points" name="points">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="min_score" class="form-label">Min Score (inclusive)</label>
                                <input type="number" step="0.01" class="form-control" id="min_score" name="min_score" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="max_score" class="form-label">Max Score (inclusive)</label>
                                <input type="number" step="0.01" class="form-control" id="max_score" name="max_score" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="comment" class="form-label">Comment (e.g., Excellent)</label>
                            <input type="text" class="form-control" id="comment" name="comment">
                        </div>
                        <div class="mb-3">
                            <label for="order_index" class="form-label">Order (for display, lower numbers first)</label>
                            <input type="number" class="form-control" id="order_index" name="order_index" required value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Grade Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Grade Level Modal -->
    <div class="modal fade" id="editGradeLevelModal" tabindex="-1" aria-labelledby="editGradeLevelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="handle_grading_policy.php" method="POST">
                    <input type="hidden" name="action" value="update_grade_level">
                    <input type="hidden" name="level_id" id="edit_level_id">
                    <input type="hidden" name="policy_id" id="edit_level_policy_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editGradeLevelModalLabel">Edit Grade Level</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_grade_label" class="form-label">Grade Label</label>
                                <input type="text" class="form-control" id="edit_grade_label" name="grade_label" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_points" class="form-label">Points</label>
                                <input type="number" step="any" class="form-control" id="edit_points" name="points">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_min_score" class="form-label">Min Score</label>
                                <input type="number" step="0.01" class="form-control" id="edit_min_score" name="min_score" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_max_score" class="form-label">Max Score</label>
                                <input type="number" step="0.01" class="form-control" id="edit_max_score" name="max_score" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_comment" class="form-label">Comment</label>
                            <input type="text" class="form-control" id="edit_comment" name="comment">
                        </div>
                        <div class="mb-3">
                            <label for="edit_order_index" class="form-label">Order</label>
                            <input type="number" class="form-control" id="edit_order_index" name="order_index" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate Add Grade Level Modal with policy_id
        var addGradeLevelModal = document.getElementById('addGradeLevelModal');
        addGradeLevelModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var policyId = button.getAttribute('data-policy-id');
            var modalPolicyIdInput = addGradeLevelModal.querySelector('#add_level_policy_id');
            modalPolicyIdInput.value = policyId;
        });

        // Populate Edit Grade Level Modal with current data
        var editGradeLevelModal = document.getElementById('editGradeLevelModal');
        editGradeLevelModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var levelId = button.getAttribute('data-level-id');
            var policyId = button.getAttribute('data-policy-id');
            var gradeLabel = button.getAttribute('data-grade-label');
            var minScore = button.getAttribute('data-min-score');
            var maxScore = button.getAttribute('data-max-score');
            var comment = button.getAttribute('data-comment');
            var points = button.getAttribute('data-points');
            var orderIndex = button.getAttribute('data-order-index');

            editGradeLevelModal.querySelector('#edit_level_id').value = levelId;
            editGradeLevelModal.querySelector('#edit_level_policy_id').value = policyId;
            editGradeLevelModal.querySelector('#edit_grade_label').value = gradeLabel;
            editGradeLevelModal.querySelector('#edit_min_score').value = minScore;
            editGradeLevelModal.querySelector('#edit_max_score').value = maxScore;
            editGradeLevelModal.querySelector('#edit_comment').value = comment;
            editGradeLevelModal.querySelector('#edit_points').value = points;
            editGradeLevelModal.querySelector('#edit_order_index').value = orderIndex;
        });

        // Populate Edit Policy Modal
        var editPolicyModal = document.getElementById('editPolicyModal');
        editPolicyModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var policyId = button.getAttribute('data-policy-id');
            var policyName = button.getAttribute('data-policy-name');
            var policyType = button.getAttribute('data-policy-type');
            var policyDefault = button.getAttribute('data-policy-default');

            editPolicyModal.querySelector('#edit_policy_id').value = policyId;
            editPolicyModal.querySelector('#edit_policy_name').value = policyName;
            editPolicyModal.querySelector('#edit_policy_school_type').value = policyType;
            editPolicyModal.querySelector('#edit_policy_is_default').checked = (policyDefault == '1');
        });
    </script>
</body>
</html>
