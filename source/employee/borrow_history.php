<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

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

// Generate random returned materials data
function generateRandomReturnedMaterials($count = 20) {
    $history = [];
    $firstNames = ['John', 'Jane', 'Michael', 'Emily', 'David', 'Sarah', 'Robert', 'Lisa', 'William', 'Jessica'];
    $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Miller', 'Davis', 'Garcia', 'Rodriguez', 'Wilson'];
    $bookTitles = [
        'The Great Adventure', 'Programming 101', 'History of the World', 
        'Science Fundamentals', 'Art of Design', 'Mathematics for Everyone',
        'Literature Classics', 'Business Strategies', 'Cooking Masterclass',
        'Health and Wellness'
    ];
    $materialTypes = ['Book', 'E-Book', 'Audiobook', 'Magazine', 'Journal'];
    
    for ($i = 0; $i < $count; $i++) {
        $borrowDate = date('Y-m-d', strtotime('-'.rand(0, 90).' days'));
        $dueDate = date('Y-m-d', strtotime($borrowDate.' + '.rand(7, 21).' days'));
        $returnDate = date('Y-m-d', strtotime($borrowDate.' + '.rand(1, 30).' days'));
        $isLate = strtotime($returnDate) > strtotime($dueDate);
        $daysLate = $isLate ? floor((strtotime($returnDate) - strtotime($dueDate)) / (60 * 60 * 24)) : 0;
        
        $history[] = [
            'transaction_id' => rand(1000, 9999),
            'customer_name' => $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
            'customer_id' => rand(100, 999),
            'material_title' => $bookTitles[array_rand($bookTitles)],
            'material_id' => rand(1000, 9999),
            'material_type' => $materialTypes[array_rand($materialTypes)],
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
            'return_date' => $returnDate,
            'days_late' => $isLate ? $daysLate : 0,
            'status' => 'Returned',
            'is_late' => $isLate
        ];
    }
    
    // Sort by most recent return date
    usort($history, function($a, $b) {
        return strtotime($b['return_date']) - strtotime($a['return_date']);
    });
    
    return $history;
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    $reportType = $_POST['report_type'] ?? 'all';
    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Generated returned materials report: " . ucfirst($reportType));
    $_SESSION['success'] = "Returned materials report generated (simulated)";
    header("Location: returned_materials.php");
    exit;
}

// Generate random returned materials
$returnedMaterials = generateRandomReturnedMaterials(25);

// Filter by material type if requested
$typeFilter = $_GET['type'] ?? 'all';
if ($typeFilter !== 'all') {
    $returnedMaterials = array_filter($returnedMaterials, fn($item) => $item['material_type'] === $typeFilter);
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
                    <a href="?type=Book" class="btn btn-outline-primary <?= $typeFilter === 'Book' ? 'active' : '' ?>">Books</a>
                    <a href="?type=E-Book" class="btn btn-outline-info <?= $typeFilter === 'E-Book' ? 'active' : '' ?>">E-Books</a>
                    <a href="?type=Audiobook" class="btn btn-outline-secondary <?= $typeFilter === 'Audiobook' ? 'active' : '' ?>">Audiobooks</a>
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