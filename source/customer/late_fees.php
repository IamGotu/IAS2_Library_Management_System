<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../login.php");
    exit;
}

// Get late fees for the current customer
function getCustomerLateFees($pdo, $customerId) {
    // Paid fees (already processed)
    $paidFees = [];
    $stmt = $pdo->prepare("
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
            50.00 AS fee_per_day,
            t.late_fee AS total_fee,
            'Paid' AS status,
            pr.receipt_number,
            pr.payment_amount,
            pr.payment_method,
            pr.created_at AS payment_date
        FROM material_transactions t
        JOIN customer c ON t.customer_id = c.customer_id
        LEFT JOIN material_books b ON t.material_type = 'book' AND t.material_id = b.id
        LEFT JOIN material_digital_media d ON t.material_type = 'digital' AND t.material_id = d.id
        LEFT JOIN material_research r ON t.material_type = 'research' AND t.material_id = r.id
        LEFT JOIN payment_receipts pr ON t.transaction_id = pr.transaction_id
        WHERE t.customer_id = ? 
          AND t.late_fee > 0 
          AND t.status = 'Returned'
        ORDER BY t.return_date DESC
    ");
    $stmt->execute([$customerId]);
    $paidFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Unpaid fees (overdue but not yet returned)
    $unpaidFees = [];
    $stmt = $pdo->prepare("
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
            NULL AS return_date,
            DATEDIFF(NOW(), t.due_date) AS days_late,
            50.00 AS fee_per_day,
            t.late_fee AS total_fee,
            'Unpaid' AS status,
            NULL AS receipt_number,
            NULL AS payment_amount,
            NULL AS payment_method,
            NULL AS payment_date
        FROM material_transactions t
        JOIN customer c ON t.customer_id = c.customer_id
        LEFT JOIN material_books b ON t.material_type = 'book' AND t.material_id = b.id
        LEFT JOIN material_digital_media d ON t.material_type = 'digital' AND t.material_id = d.id
        LEFT JOIN material_research r ON t.material_type = 'research' AND t.material_id = r.id
        WHERE t.customer_id = ? 
          AND t.late_fee > 0 
          AND t.status = 'Overdue'
        ORDER BY t.due_date DESC
    ");
    $stmt->execute([$customerId]);
    $unpaidFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return array_merge($paidFees, $unpaidFees);
}

// Handle print receipt action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'print_receipt') {
        $transactionId = $_POST['transaction_id'] ?? 0;
        
        try {
            // Get transaction details
            $stmt = $pdo->prepare("
                SELECT 
                    t.transaction_id,
                    CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS customer_name,
                    c.customer_id,
                    CASE 
                        WHEN t.material_type = 'book' THEN b.title
                        WHEN t.material_type = 'digital' THEN d.title
                        WHEN t.material_type = 'research' THEN r.title
                    END AS material_title,
                    t.material_type,
                    t.due_date,
                    t.return_date,
                    t.late_fee,
                    pr.receipt_number,
                    pr.payment_amount,
                    pr.payment_method,
                    pr.created_at AS payment_date
                FROM material_transactions t
                JOIN customer c ON t.customer_id = c.customer_id
                LEFT JOIN material_books b ON t.material_type = 'book' AND t.material_id = b.id
                LEFT JOIN material_digital_media d ON t.material_type = 'digital' AND t.material_id = d.id
                LEFT JOIN material_research r ON t.material_type = 'research' AND t.material_id = r.id
                LEFT JOIN payment_receipts pr ON t.transaction_id = pr.transaction_id
                WHERE t.transaction_id = ? AND t.customer_id = ?
            ");
            $stmt->execute([$transactionId, $_SESSION['user_id']]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception("Transaction not found or not authorized");
            }
            
            // Generate receipt HTML
            $receiptNumber = $transaction['receipt_number'] ?? 'PENDING-' . str_pad($transactionId, 6, '0', STR_PAD_LEFT);
            $receiptDate = $transaction['payment_date'] ?? date('Y-m-d H:i:s');
            $paymentAmount = $transaction['payment_amount'] ?? $transaction['late_fee'];
            $paymentMethod = $transaction['payment_method'] ?? 'Pending';
            
            $receiptContent = "
                <h3>Library Payment Receipt</h3>
                <p>Receipt #: $receiptNumber</p>
                <p>Date: $receiptDate</p>
                <hr>
                <p><strong>Customer:</strong> {$transaction['customer_name']} (ID: {$transaction['customer_id']})</p>
                <p><strong>Material:</strong> {$transaction['material_title']} ({$transaction['material_type']})</p>
                <p><strong>Transaction ID:</strong> $transactionId</p>
                <hr>
                <p><strong>Payment Method:</strong> $paymentMethod</p>
                <p><strong>Amount Due:</strong> ₱" . number_format($transaction['late_fee'], 2) . "</p>
                <p><strong>Amount Paid:</strong> ₱" . number_format($paymentAmount, 2) . "</p>
                <hr>
                <p>Thank you for your payment!</p>
                <p>Library System</p>
            ";
            
            // Output the receipt
            header('Content-Type: text/html');
            echo $receiptContent;
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: late_fees.php");
            exit;
        }
    }
}

// Get late fees for the current customer
$lateFees = getCustomerLateFees($pdo, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Late Fees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .paid { background-color: #ddffdd; }
        .unpaid { background-color: #ffdddd; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">My Late Fees</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>My Late Fees</h2>
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
                        <tr class="<?= $fee['status'] === 'Paid' ? 'paid' : 'unpaid' ?>">
                            <td>#<?= $fee['transaction_id'] ?></td>
                            <td><?= $fee['material_title'] ?></td>
                            <td><?= $fee['due_date'] ?></td>
                            <td><?= $fee['return_date'] ?? 'Not returned' ?></td>
                            <td><?= $fee['days_late'] ?></td>
                            <td>₱<?= number_format($fee['fee_per_day'], 2) ?></td>
                            <td>₱<?= number_format($fee['total_fee'], 2) ?></td>
                            <td>
                                <?php if ($fee['status'] === 'Paid'): ?>
                                    <span class="badge bg-success">Paid</span>
                                    <br><small><?= $fee['payment_date'] ?></small>
                                <?php else: ?>
                                    <span class="badge bg-danger">Unpaid</span>
                                <?php endif; ?>
                            </td>
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
                            <td colspan="9" class="text-center">No late fees found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="alert alert-info mt-4">
                <h5><i class="fas fa-info-circle"></i> Late Fee Information</h5>
                <ul>
                    <li><strong>Late Fee Rate:</strong> ₱50 per day for overdue items</li>
                    <li><strong>Payment:</strong> Please visit the library to pay any outstanding fees</li>
                    <li><strong>Receipts:</strong> You can print receipts for both paid and pending fees</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>