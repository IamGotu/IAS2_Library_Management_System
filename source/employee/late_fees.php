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

// Get actual paid transactions from database
function getPaidLateFees($pdo) {
    $stmt = $pdo->query("
        SELECT 
            t.transaction_id,
            CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS customer_name,
            c.customer_id,
            CASE 
                WHEN t.material_type = 'book' THEN b.title
                WHEN t.material_type = 'digital' THEN d.title
                WHEN t.material_type = 'research' THEN r.title
            END AS material_title,
            t.material_id,
            DATE(t.due_date) AS due_date,
            DATE(t.return_date) AS return_date,
            DATEDIFF(t.return_date, t.due_date) AS days_late,
            5.00 AS fee_per_day,
            t.late_fee AS total_fee,
            'Paid' AS status
        FROM material_transactions t
        JOIN customer c ON t.customer_id = c.customer_id
        LEFT JOIN material_books b ON t.material_type = 'book' AND t.material_id = b.id
        LEFT JOIN material_digital_media d ON t.material_type = 'digital' AND t.material_id = d.id
        LEFT JOIN material_research r ON t.material_type = 'research' AND t.material_id = r.id
        WHERE t.late_fee > 0 AND t.status = 'Returned'
        ORDER BY t.return_date DESC
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle print receipt action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'print_receipt') {
        $transactionId = $_POST['transaction_id'] ?? 0;
        
        try {
            // Get receipt data
            $stmt = $pdo->prepare("
                SELECT pr.*, 
                       CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS customer_name,
                       CASE 
                           WHEN t.material_type = 'book' THEN b.title
                           WHEN t.material_type = 'digital' THEN d.title
                           WHEN t.material_type = 'research' THEN r.title
                       END AS material_title,
                       t.material_type
                FROM payment_receipts pr
                JOIN material_transactions t ON pr.transaction_id = t.transaction_id
                JOIN customer c ON t.customer_id = c.customer_id
                LEFT JOIN material_books b ON t.material_type = 'book' AND t.material_id = b.id
                LEFT JOIN material_digital_media d ON t.material_type = 'digital' AND t.material_id = d.id
                LEFT JOIN material_research r ON t.material_type = 'research' AND t.material_id = r.id
                WHERE pr.transaction_id = ?
            ");
            $stmt->execute([$transactionId]);
            $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($receipt) {
                // Log the activity
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                    "Printed receipt for transaction #$transactionId (Amount: ₱".number_format($receipt['payment_amount'], 2).")");
                
                // Output the receipt content directly
                header('Content-Type: text/html');
                echo $receipt['receipt_content'];
                exit;
            } else {
                throw new Exception("Receipt not found");
            }
        } catch (Exception $e) {
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                "Failed to print receipt: " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            header("Location: late_fees.php");
            exit;
        }
    }
}

// Get paid late fees from database
$lateFees = getPaidLateFees($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paid Late Fees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .paid { background-color: #ddffdd; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library System - Paid Late Fees</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>Paid Late Fees</h2>
                <div>
                    <button class="btn btn-secondary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh
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

            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Transaction ID</th>
                        <th>Customer</th>
                        <th>Material</th>
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
                        <tr class="paid">
                            <td>#<?= $fee['transaction_id'] ?></td>
                            <td><?= $fee['customer_name'] ?> (ID: <?= $fee['customer_id'] ?>)</td>
                            <td><?= $fee['material_title'] ?> (ID: <?= $fee['material_id'] ?>)</td>
                            <td><?= $fee['due_date'] ?></td>
                            <td><?= $fee['return_date'] ?></td>
                            <td><?= $fee['days_late'] ?></td>
                            <td>₱<?= number_format($fee['fee_per_day'], 2) ?></td>
                            <td>₱<?= number_format($fee['total_fee'], 2) ?></td>
                            <td><?= $fee['status'] ?></td>
                            <td>
                                <form method="POST" target="_blank">
                                    <input type="hidden" name="action" value="print_receipt">
                                    <input type="hidden" name="transaction_id" value="<?= $fee['transaction_id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-receipt"></i> Print Receipt
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($lateFees)): ?>
                        <tr>
                            <td colspan="10" class="text-center">No paid late fees found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>