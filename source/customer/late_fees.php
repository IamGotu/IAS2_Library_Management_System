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

// Generate random late fee data (not from database)
function generateRandomLateFees($count = 10) {
    $lateFees = [];
    $firstNames = ['John', 'Jane', 'Michael', 'Emily', 'David', 'Sarah', 'Robert', 'Lisa', 'William', 'Jessica'];
    $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Miller', 'Davis', 'Garcia', 'Rodriguez', 'Wilson'];
    $bookTitles = [
        'The Great Adventure', 'Programming 101', 'History of the World', 
        'Science Fundamentals', 'Art of Design', 'Mathematics for Everyone',
        'Literature Classics', 'Business Strategies', 'Cooking Masterclass',
        'Health and Wellness'
    ];
    
    for ($i = 0; $i < $count; $i++) {
        $daysLate = rand(1, 30);
        $feePerDay = 5.00;
        $totalFee = $daysLate * $feePerDay;
        
        $lateFees[] = [
            'transaction_id' => rand(1000, 9999),
            'customer_name' => $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
            'customer_id' => rand(100, 999),
            'book_title' => $bookTitles[array_rand($bookTitles)],
            'book_id' => rand(1000, 9999),
            'due_date' => date('Y-m-d', strtotime('-'.rand(5, 30).' days')),
            'return_date' => date('Y-m-d'),
            'days_late' => $daysLate,
            'fee_per_day' => $feePerDay,
            'total_fee' => $totalFee,
            'status' => ['Unpaid', 'Paid', 'Waived'][rand(0, 2)]
        ];
    }
    
    return $lateFees;
}

// Handle fee actions (simulated)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $transactionId = $_POST['transaction_id'] ?? 0;
    $customerName = $_POST['customer_name'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    
    try {
        switch ($action) {
            case 'mark_paid':
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Marked late fee as paid (Simulated Transaction ID: $transactionId for $customerName - Amount: $".number_format($amount, 2).")");
                $_SESSION['success'] = "Successfully marked transaction #$transactionId as paid (simulated)";
                break;
                
            case 'waive_fee':
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Waived late fee (Simulated Transaction ID: $transactionId for $customerName - Amount: $".number_format($amount, 2).")");
                $_SESSION['success'] = "Successfully waived fee for transaction #$transactionId (simulated)";
                break;
                
            case 'generate_report':
                $reportType = $_POST['report_type'] ?? 'current';
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Generated late fee report: " . ucfirst($reportType) . " outstanding fees");
                $_SESSION['success'] = "Late fee report generated (simulated)";
                break;
                
            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Failed to process late fee action: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: late_fees.php");
    exit;
}

// Generate random late fees
$lateFees = generateRandomLateFees(15);

// Filter by status if requested
$statusFilter = $_GET['status'] ?? 'all';
if ($statusFilter !== 'all') {
    $lateFees = array_filter($lateFees, fn($fee) => $fee['status'] === $statusFilter);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Late Fee Simulation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .unpaid { background-color: #ffdddd; }
        .paid { background-color: #ddffdd; }
        .waived { background-color: #ffffdd; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library System - Late Fee Simulation</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>Late Fee Management (Simulation)</h2>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="fas fa-file-alt"></i> Generate Report
                    </button>
                    <button class="btn btn-secondary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh Simulation
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

            <div class="btn-group mb-3" role="group">
                <a href="?status=all" class="btn btn-outline-dark <?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
                <a href="?status=Unpaid" class="btn btn-outline-danger <?= $statusFilter === 'Unpaid' ? 'active' : '' ?>">Unpaid</a>
                <a href="?status=Paid" class="btn btn-outline-success <?= $statusFilter === 'Paid' ? 'active' : '' ?>">Paid</a>
                <a href="?status=Waived" class="btn btn-outline-warning <?= $statusFilter === 'Waived' ? 'active' : '' ?>">Waived</a>
            </div>

            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Transaction ID</th>
                        <th>Customer</th>
                        <th>Book</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Days Late</th>
                        <th>Fee/Day</th>
                        <th>Total Fee</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lateFees as $fee): ?>
                        <tr class="<?= strtolower($fee['status']) ?>">
                            <td>#<?= $fee['transaction_id'] ?></td>
                            <td><?= $fee['customer_name'] ?> (ID: <?= $fee['customer_id'] ?>)</td>
                            <td><?= $fee['book_title'] ?> (ID: <?= $fee['book_id'] ?>)</td>
                            <td><?= $fee['due_date'] ?></td>
                            <td><?= $fee['return_date'] ?></td>
                            <td><?= $fee['days_late'] ?></td>
                            <td>$<?= number_format($fee['fee_per_day'], 2) ?></td>
                            <td>$<?= number_format($fee['total_fee'], 2) ?></td>
                            <td><?= $fee['status'] ?></td>
                            <td>
                                <?php if ($fee['status'] === 'Unpaid'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="mark_paid">
                                        <input type="hidden" name="transaction_id" value="<?= $fee['transaction_id'] ?>">
                                        <input type="hidden" name="customer_name" value="<?= $fee['customer_name'] ?>">
                                        <input type="hidden" name="amount" value="<?= $fee['total_fee'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Mark this fee as paid?')">
                                            <i class="fas fa-check"></i> Mark Paid
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="waive_fee">
                                        <input type="hidden" name="transaction_id" value="<?= $fee['transaction_id'] ?>">
                                        <input type="hidden" name="customer_name" value="<?= $fee['customer_name'] ?>">
                                        <input type="hidden" name="amount" value="<?= $fee['total_fee'] ?>">
                                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Waive this fee?')">
                                            <i class="fas fa-hand-holding-usd"></i> Waive
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">No actions</span>
                                <?php endif; ?>
                            </td>
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
                <h5 class="modal-title">Generate Late Fee Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="generate_report">
                    <div class="mb-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select" required>
                            <option value="current">Current Outstanding Fees</option>
                            <option value="paid">Paid Fees History</option>
                            <option value="waived">Waived Fees History</option>
                            <option value="all">Complete Fee History</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Time Period</label>
                        <select name="time_period" class="form-select">
                            <option value="30">Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                            <option value="all">All Time</option>
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