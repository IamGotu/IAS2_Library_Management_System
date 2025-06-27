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

// Log page access
logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Accessed Book Loan Renewal System");

// Generate random active loans data
function generateRandomActiveLoans($count = 15) {
    $loans = [];
    $firstNames = ['John', 'Jane', 'Michael', 'Emily', 'David', 'Sarah'];
    $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones'];
    $bookTitles = [
        'The Great Gatsby', 'To Kill a Mockingbird', '1984', 
        'Pride and Prejudice', 'The Hobbit', 'Animal Farm',
        'Brave New World', 'The Catcher in the Rye'
    ];
    
    for ($i = 0; $i < $count; $i++) {
        $borrowDate = date('Y-m-d', strtotime('-'.rand(1, 20).' days'));
        $dueDate = date('Y-m-d', strtotime($borrowDate.' + 14 days'));
        $isRenewable = rand(0, 1) && (strtotime($dueDate) > time() || rand(0, 1));
        $renewalCount = $isRenewable ? rand(0, 2) : 0;
        
        $loans[] = [
            'loan_id' => rand(1000, 9999),
            'customer_name' => $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
            'customer_id' => rand(100, 999),
            'book_title' => $bookTitles[array_rand($bookTitles)],
            'book_id' => rand(1000, 9999),
            'borrow_date' => $borrowDate,
            'original_due_date' => $dueDate,
            'current_due_date' => date('Y-m-d', strtotime($dueDate.' + '.($renewalCount * 14).' days')),
            'renewal_count' => $renewalCount,
            'is_renewable' => $isRenewable,
            'is_overdue' => strtotime($dueDate) < time()
        ];
    }
    
    return $loans;
}

// Handle renewal action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew_loan') {
    $loanId = $_POST['loan_id'];
    $customerName = $_POST['customer_name'];
    $bookTitle = $_POST['book_title'];
    $currentDueDate = $_POST['current_due_date'];
    
    try {
        // Simulate renewal - in a real system this would update the database
        $newDueDate = date('Y-m-d', strtotime($currentDueDate.' + 14 days'));
        
        // Log the renewal action
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
            "Renewed loan #$loanId for '$bookTitle' (Customer: $customerName). New due date: $newDueDate");
        
        $_SESSION['success'] = "Successfully renewed loan for '$bookTitle' (New due date: $newDueDate)";
    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
            "Failed to renew loan #$loanId: " . $e->getMessage());
        $_SESSION['error'] = "Error renewing loan: " . $e->getMessage();
    }
    
    header("Location: renew_loans.php");
    exit;
}

$activeLoans = generateRandomActiveLoans();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Renew Book Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .overdue { background-color: #ffdddd; }
        .renewed { background-color: #ffffdd; }
        .renewable { background-color: #ddffdd; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library System - Renew Book Loans</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>Active Book Loans</h2>
                <button class="btn btn-secondary" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
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

            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Loan ID</th>
                        <th>Customer</th>
                        <th>Book Title</th>
                        <th>Borrow Date</th>
                        <th>Original Due Date</th>
                        <th>Current Due Date</th>
                        <th>Renewals</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeLoans as $loan): ?>
                        <tr class="<?= $loan['is_overdue'] ? 'overdue' : ($loan['renewal_count'] > 0 ? 'renewed' : 'renewable') ?>">
                            <td>#<?= $loan['loan_id'] ?></td>
                            <td><?= $loan['customer_name'] ?> (ID: <?= $loan['customer_id'] ?>)</td>
                            <td><?= $loan['book_title'] ?> (ID: <?= $loan['book_id'] ?>)</td>
                            <td><?= $loan['borrow_date'] ?></td>
                            <td><?= $loan['original_due_date'] ?></td>
                            <td><?= $loan['current_due_date'] ?></td>
                            <td><?= $loan['renewal_count'] ?></td>
                            <td>
                                <?php if ($loan['is_overdue']): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                <?php elseif ($loan['renewal_count'] > 0): ?>
                                    <span class="badge bg-warning">Renewed</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($loan['is_renewable']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="renew_loan">
                                        <input type="hidden" name="loan_id" value="<?= $loan['loan_id'] ?>">
                                        <input type="hidden" name="customer_name" value="<?= $loan['customer_name'] ?>">
                                        <input type="hidden" name="book_title" value="<?= $loan['book_title'] ?>">
                                        <input type="hidden" name="current_due_date" value="<?= $loan['current_due_date'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Renew this loan for another 14 days?')">
                                            <i class="fas fa-redo"></i> Renew
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled title="<?= $loan['renewal_count'] >= 2 ? 'Maximum renewals reached' : 'Not renewable' ?>">
                                        <i class="fas fa-ban"></i> Can't Renew
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="alert alert-info mt-4">
                <h5><i class="fas fa-info-circle"></i> Renewal Policy</h5>
                <ul>
                    <li>Books can be renewed up to 2 times</li>
                    <li>Each renewal extends the due date by 14 days</li>
                    <li>Overdue books may still be renewable at librarian's discretion</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>