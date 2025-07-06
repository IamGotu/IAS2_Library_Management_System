<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Function to log activity
function logActivity($pdo, $userId, $role, $actionDesc) {
    if ($role === 'employee') {
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name FROM employees WHERE employee_id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name FROM customer WHERE customer_id = ?");
    }
    $stmt->execute([$userId]);
    $fullName = trim(preg_replace('/\s+/', ' ', $stmt->fetchColumn()));

    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, user_role, full_name, action) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $role, $fullName, $actionDesc]);
}

// Function to get returned materials from database
function getReturnedMaterials($pdo, $typeFilter = 'all') {
    $query = "SELECT 
                t.transaction_id,
                CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS customer_name,
                c.customer_id,
                t.material_type,
                t.material_id,
                t.action_date AS borrow_date,
                t.due_date,
                t.return_date,
                t.status,
                t.late_fee,
                CASE 
                    WHEN t.return_date > t.due_date THEN DATEDIFF(t.return_date, t.due_date)
                    ELSE 0
                END AS days_late,
                CASE 
                    WHEN t.material_type = 'book' THEN b.title
                    WHEN t.material_type = 'digital' THEN d.title
                    WHEN t.material_type = 'research' THEN r.title
                END AS material_title
              FROM material_transactions t
              JOIN customer c ON t.customer_id = c.customer_id
              LEFT JOIN material_books b ON t.material_type = 'book' AND t.material_id = b.id
              LEFT JOIN material_digital_media d ON t.material_type = 'digital' AND t.material_id = d.id
              LEFT JOIN material_research r ON t.material_type = 'research' AND t.material_id = r.id
              WHERE t.status = 'Returned'";
    
    $params = [];
    
    // Apply type filter if not 'all'
    if ($typeFilter !== 'all') {
        $query .= " AND t.material_type = ?";
        $params[] = $typeFilter;
    }
    
    $query .= " ORDER BY t.return_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the results for display
    $formattedResults = [];
    foreach ($results as $row) {
        $formattedResults[] = [
            'transaction_id' => $row['transaction_id'],
            'customer_name' => $row['customer_name'],
            'customer_id' => $row['customer_id'],
            'material_title' => $row['material_title'],
            'material_id' => $row['material_id'],
            'material_type' => ucfirst($row['material_type']),
            'borrow_date' => date('Y-m-d', strtotime($row['borrow_date'])),
            'due_date' => date('Y-m-d', strtotime($row['due_date'])),
            'return_date' => date('Y-m-d', strtotime($row['return_date'])),
            'days_late' => $row['days_late'],
            'status' => $row['status'],
            'is_late' => $row['days_late'] > 0,
            'late_fee' => $row['late_fee']
        ];
    }
    
    return $formattedResults;
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    $reportType = $_POST['report_type'] ?? 'all';
    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Generated returned materials report: " . ucfirst($reportType));
    $_SESSION['success'] = "Returned materials report generated";
    header("Location: returned_materials.php");
    exit;
}

// Get returned materials from database
$returnedMaterials = getReturnedMaterials($pdo);

// Filter by material type if requested
$typeFilter = $_GET['type'] ?? 'all';
if ($typeFilter !== 'all') {
    $returnedMaterials = getReturnedMaterials($pdo, $typeFilter);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Returned Materials History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .late { background-color: #ffdddd; }
        .returned { background-color: #ddffdd; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library System - Returned Materials</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>Returned Materials History</h2>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="fas fa-file-alt"></i> Generate Report
                    </button>
                    <button class="btn btn-secondary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="mb-3">
                <div class="btn-group" role="group">
                    <a href="?type=all" class="btn btn-outline-dark <?= $typeFilter === 'all' ? 'active' : '' ?>">All Types</a>
                    <a href="?type=book" class="btn btn-outline-primary <?= $typeFilter === 'book' ? 'active' : '' ?>">Books</a>
                    <a href="?type=digital" class="btn btn-outline-info <?= $typeFilter === 'digital' ? 'active' : '' ?>">Digital Media</a>
                    <a href="?type=research" class="btn btn-outline-secondary <?= $typeFilter === 'research' ? 'active' : '' ?>">Archival</a>
                </div>
            </div>

            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Transaction ID</th>
                        <th>Customer</th>
                        <th>Material</th>
                        <th>Type</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Days Late</th>
                        <th>Late Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($returnedMaterials as $item): ?>
                        <tr class="<?= $item['is_late'] ? 'late' : 'returned' ?>">
                            <td>#<?= $item['transaction_id'] ?></td>
                            <td><?= $item['customer_name'] ?> (ID: <?= $item['customer_id'] ?>)</td>
                            <td><?= $item['material_title'] ?> (ID: <?= $item['material_id'] ?>)</td>
                            <td><?= $item['material_type'] ?></td>
                            <td><?= $item['borrow_date'] ?></td>
                            <td><?= $item['due_date'] ?></td>
                            <td><?= $item['return_date'] ?></td>
                            <td>
                                Returned
                                <?php if ($item['is_late']): ?>
                                    <span class="badge bg-danger">Late</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $item['days_late'] > 0 ? $item['days_late'] : '-' ?></td>
                            <td><?= $item['late_fee'] > 0 ? '₱' . number_format($item['late_fee'], 2) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Report Generation Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Returned Materials Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="generate_report">
                    <div class="mb-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select" required>
                            <option value="all">All Returned Items</option>
                            <option value="late">Late Returns</option>
                            <option value="recent">Recent Returns (Last 30 Days)</option>
                            <option value="books">Returned Books Only</option>
                            <option value="digital">Digital Media Only</option>
                            <option value="research">Archival Materials Only</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Time Period</label>
                        <select name="time_period" class="form-select">
                            <option value="7">Last 7 Days</option>
                            <option value="30">Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>