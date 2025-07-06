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

// Generate receipt PDF (simplified version - in production use a PDF library)
function generateReceipt($pdo, $transactionId, $paymentAmount, $paymentMethod) {
    // Get transaction details
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
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
        WHERE t.transaction_id = ?
    ");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create a simple HTML receipt (in production, generate a PDF)
    $receiptNumber = 'RCPT-' . str_pad($transactionId, 6, '0', STR_PAD_LEFT);
    $receiptDate = date('Y-m-d H:i:s');
    
    $receiptContent = "
        <h3>Library Payment Receipt</h3>
        <p>Receipt #: $receiptNumber</p>
        <p>Date: $receiptDate</p>
        <hr>
        <p><strong>Customer:</strong> {$transaction['customer_name']}</p>
        <p><strong>Material:</strong> {$transaction['material_title']} ({$transaction['material_type']})</p>
        <p><strong>Transaction ID:</strong> $transactionId</p>
        <hr>
        <p><strong>Payment Method:</strong> $paymentMethod</p>
        <p><strong>Amount Paid:</strong> ₱$paymentAmount</p>
        <p><strong>Late Fee:</strong> ₱{$transaction['late_fee']}</p>
        <hr>
        <p>Thank you for your payment!</p>
        <p>Library System</p>
    ";

    // Store receipt in database
    $stmt = $pdo->prepare("
        INSERT INTO payment_receipts 
        (transaction_id, receipt_number, payment_amount, payment_method, receipt_content, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $transactionId,
        $receiptNumber,
        $paymentAmount,
        $paymentMethod,
        $receiptContent
    ]);

    return $receiptNumber;
}

// Process auto-returns
function processAutoReturns($pdo) {
    // Get all expired digital/archival materials that haven't been marked returned
    $stmt = $pdo->prepare("
        SELECT transaction_id, material_type, material_id 
        FROM material_transactions 
        WHERE material_type IN ('digital', 'research') 
          AND due_date <= NOW() 
          AND status IN ('Reserved', 'Borrowed')
    ");
    $stmt->execute();
    $expiredMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($expiredMaterials as $material) {
        // Mark as returned
        $stmt = $pdo->prepare("
            UPDATE material_transactions 
            SET status = 'Returned', return_date = NOW() 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$material['transaction_id']]);
        
        // Log the auto-return
        logActivity($pdo, 0, 'system', 
            "Automatically returned {$material['material_type']} (ID: {$material['material_id']}) - Transaction #{$material['transaction_id']}");
    }
}

// Process auto-returns before getting active loans
processAutoReturns($pdo);

// Get actual active loans from database
function getActiveLoans($pdo) {
    $loans = [];
    
    // Get book loans
    $stmt = $pdo->prepare("
        SELECT t.transaction_id AS loan_id, 
               CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS customer_name,
               c.customer_id,
               b.title AS book_title,
               b.id AS book_id,
               t.action_date AS borrow_date,
               t.due_date AS current_due_date,
               t.due_date AS original_due_date,
               0 AS renewal_count,
               c.email,
               c.phone_num AS phone,
               t.status,
               t.late_fee,
               'book' AS material_type
        FROM material_transactions t
        JOIN customer c ON t.customer_id = c.customer_id
        JOIN material_books b ON t.material_id = b.id
        WHERE t.material_type = 'book' 
          AND t.action IN ('Borrow', 'Grant Access')
          AND t.status IN ('Reserved', 'Borrowed', 'Overdue')
    ");
    $stmt->execute();
    $bookLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get digital media loans
    $stmt = $pdo->prepare("
        SELECT t.transaction_id AS loan_id, 
               CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS customer_name,
               c.customer_id,
               d.title AS book_title,
               d.id AS book_id,
               t.action_date AS borrow_date,
               t.due_date AS current_due_date,
               t.due_date AS original_due_date,
               0 AS renewal_count,
               c.email,
               c.phone_num AS phone,
               t.status,
               t.late_fee,
               'digital' AS material_type
        FROM material_transactions t
        JOIN customer c ON t.customer_id = c.customer_id
        JOIN material_digital_media d ON t.material_id = d.id
        WHERE t.material_type = 'digital' 
          AND t.action IN ('Borrow', 'Grant Access')
          AND t.status IN ('Reserved', 'Borrowed', 'Overdue')
    ");
    $stmt->execute();
    $digitalLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get research material loans
    $stmt = $pdo->prepare("
        SELECT t.transaction_id AS loan_id, 
               CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS customer_name,
               c.customer_id,
               r.title AS book_title,
               r.id AS book_id,
               t.action_date AS borrow_date,
               t.due_date AS current_due_date,
               t.due_date AS original_due_date,
               0 AS renewal_count,
               c.email,
               c.phone_num AS phone,
               t.status,
               t.late_fee,
               'research' AS material_type
        FROM material_transactions t
        JOIN customer c ON t.customer_id = c.customer_id
        JOIN material_research r ON t.material_id = r.id
        WHERE t.material_type = 'research' 
          AND t.action IN ('Borrow', 'Grant Access')
          AND t.status IN ('Reserved', 'Borrowed', 'Overdue')
    ");
    $stmt->execute();
    $researchLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine all loans
    $allLoans = array_merge($bookLoans, $digitalLoans, $researchLoans);
    
    // Add additional calculated fields
    foreach ($allLoans as &$loan) {
        $loan['is_overdue'] = strtotime($loan['current_due_date']) < time();
        $loan['is_renewable'] = !$loan['is_overdue'] && $loan['material_type'] === 'book';
        $loan['is_returnable'] = $loan['material_type'] === 'book';
        
        // Format dates for display
        $loan['borrow_date'] = date('Y-m-d', strtotime($loan['borrow_date']));
        $loan['current_due_date'] = date('Y-m-d', strtotime($loan['current_due_date']));
        $loan['original_due_date'] = date('Y-m-d', strtotime($loan['original_due_date']));
        
        // Get material type display name
        switch($loan['material_type']) {
            case 'book': $loan['material_type_display'] = 'Book'; break;
            case 'digital': $loan['material_type_display'] = 'Digital Media'; break;
            case 'research': $loan['material_type_display'] = 'Archival Material'; break;
            default: $loan['material_type_display'] = $loan['material_type'];
        }
    }
    
    return $allLoans;
}

$activeLoans = getActiveLoans($pdo);

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $loanId = $_POST['loan_id'] ?? 0;
        $customerName = $_POST['customer_name'] ?? '';
        $bookTitle = $_POST['book_title'] ?? '';
        $currentDueDate = $_POST['current_due_date'] ?? '';
        $materialType = $_POST['material_type'] ?? '';
        
        try {
            switch ($action) {
                case 'renew_loan':
                    // Only books can be renewed
                    if ($materialType !== 'book') {
                        throw new Exception("Only books can be renewed");
                    }
                    
                    // Update due date in database
                    $newDueDate = date('Y-m-d H:i:s', strtotime($currentDueDate.' +7 days'));
                    
                    $stmt = $pdo->prepare("UPDATE material_transactions SET due_date = ? WHERE transaction_id = ?");
                    $stmt->execute([$newDueDate, $loanId]);
                    
                    // Format for display
                    $displayDate = date('Y-m-d', strtotime($newDueDate));
                    
                    // Log the renewal action
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                        "Renewed loan #$loanId for '$bookTitle' (Customer: $customerName). New due date: $displayDate");
                    
                    $_SESSION['success'] = "Successfully renewed loan for '$bookTitle' (New due date: $displayDate)";
                    break;
                    
                case 'return_loan':
                    // Only books can be manually returned
                    if ($materialType !== 'book') {
                        throw new Exception("Only books can be manually returned");
                    }
                    
                    $isOverdue = strtotime($currentDueDate) < time();
                    
                    if ($isOverdue) {
                        // Calculate late fee (50 per day)
                        $daysLate = floor((time() - strtotime($currentDueDate)) / (60 * 60 * 24));
                        $lateFee = $daysLate * 50;
                        
                        // Mark as overdue with fee (don't set return date yet)
                        $stmt = $pdo->prepare("UPDATE material_transactions SET status = 'Overdue', late_fee = ? WHERE transaction_id = ?");
                        $stmt->execute([$lateFee, $loanId]);
                        
                        $_SESSION['success'] = "Book returned late. $daysLate day(s) overdue. Late fee: ₱$lateFee (Please collect payment before marking as returned)";
                    } else {
                        // Mark as returned (no fee)
                        $returnDate = date('Y-m-d H:i:s');
                        $stmt = $pdo->prepare("UPDATE material_transactions SET status = 'Returned', return_date = ? WHERE transaction_id = ?");
                        $stmt->execute([$returnDate, $loanId]);
                        
                        // Update book availability
                        $stmt = $pdo->prepare("UPDATE material_books SET available = available + 1 WHERE id = (SELECT material_id FROM material_transactions WHERE transaction_id = ?)");
                        $stmt->execute([$loanId]);
                        
                        $_SESSION['success'] = "Book returned on time. No late fee.";
                    }
                    
                    // Log the return action
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                        "Processed return for loan #$loanId of '$bookTitle' (Customer: $customerName). " . 
                        ($isOverdue ? "Overdue with fee: ₱$lateFee" : "Returned on time"));
                    break;
                    
                case 'process_payment':
                    $paymentAmount = (float)$_POST['payment_amount'];
                    $paymentMethod = $_POST['payment_method'] ?? 'cash';
                    $transactionId = $_POST['loan_id'];
                    
                    // Get the transaction details
                    $stmt = $pdo->prepare("SELECT * FROM material_transactions WHERE transaction_id = ?");
                    $stmt->execute([$transactionId]);
                    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$transaction) {
                        throw new Exception("Transaction not found");
                    }
                    
                    // Validate payment amount
                    if ($paymentAmount < $transaction['late_fee']) {
                        throw new Exception("Payment amount must be at least ₱{$transaction['late_fee']}");
                    }
                    
                    // Generate receipt
                    $receiptNumber = generateReceipt($pdo, $transactionId, $paymentAmount, $paymentMethod);
                    
                    // Mark as returned
                    $returnDate = date('Y-m-d H:i:s');
                    $stmt = $pdo->prepare("
                        UPDATE material_transactions 
                        SET status = 'Returned', return_date = ?
                        WHERE transaction_id = ?
                    ");
                    $stmt->execute([$returnDate, $transactionId]);
                    
                    // Update book availability if it's a book
                    if ($transaction['material_type'] === 'book') {
                        $stmt = $pdo->prepare("UPDATE material_books SET available = available + 1 WHERE id = ?");
                        $stmt->execute([$transaction['material_id']]);
                    }
                    
                    // Log the payment and return
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                        "Processed payment of ₱$paymentAmount for transaction #$transactionId. Receipt #$receiptNumber");
                    
                    $_SESSION['success'] = "Payment processed successfully. Receipt #$receiptNumber generated.";
                    break;
                    
                case 'send_reminder':
                    $reminderType = $_POST['reminder_type'] ?? 'email';
                    $customerEmail = $_POST['customer_email'] ?? '';
                    $customerPhone = $_POST['customer_phone'] ?? '';
                    $dueDate = $_POST['due_date'] ?? '';
                    
                    // Simulate sending reminder
                    $message = "Reminder: Your book '$bookTitle' is due on $dueDate. Please return or renew it.";
                    
                    if ($reminderType === 'email') {
                        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                            "Sent email reminder to $customerEmail for loan #$loanId: $message");
                        $_SESSION['success'] = "Email reminder sent to $customerEmail for '$bookTitle'";
                    } else {
                        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                            "Sent SMS reminder to $customerPhone for loan #$loanId: $message");
                        $_SESSION['success'] = "SMS reminder sent to $customerPhone for '$bookTitle'";
                    }
                    break;
                    
                case 'send_bulk_reminders':
                    $daysBeforeDue = (int)$_POST['days_before'] ?? 3;
                    $reminderType = $_POST['bulk_reminder_type'] ?? 'email';
                    
                    // Find loans due in the specified timeframe
                    $cutoffDate = date('Y-m-d', strtotime("+$daysBeforeDue days"));
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM material_transactions 
                        WHERE due_date <= ? 
                          AND status IN ('Reserved', 'Borrowed')
                    ");
                    $stmt->execute([$cutoffDate]);
                    $count = $stmt->fetchColumn();
                    
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                        "Sent bulk $reminderType reminders for $count loans due in $daysBeforeDue days");
                    $_SESSION['success'] = "Sent $count $reminderType reminders for items due in $daysBeforeDue days";
                    break;
                    
                default:
                    throw new Exception("Invalid action");
            }
        } catch (Exception $e) {
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                "Failed to process action '$action': " . $e->getMessage());
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header("Location: borrowed_materials.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Renew Book Loans and Send Reminders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .overdue { background-color: #ffdddd; }
        .renewed { background-color: #ffffdd; }
        .renewable { background-color: #ddffdd; }
        .due-soon { background-color: #ffeedd; }
        .returned { background-color: #e6f7ff; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library System - Loan Management</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>Active Loan Materials</h2>
                <div>
                    <button class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#bulkReminderModal">
                        <i class="fas fa-bell"></i> Bulk Reminders
                    </button>
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
                        <th>Customer</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeLoans as $loan): 
                        $dueInDays = floor((strtotime($loan['current_due_date']) - time()) / (60 * 60 * 24));
                        $isDueSoon = $dueInDays <= 3 && $dueInDays >= 0;
                    ?>
                        <tr class="<?= $loan['is_overdue'] ? 'overdue' : ($loan['status'] === 'Returned' ? 'returned' : ($loan['renewal_count'] > 0 ? 'renewed' : ($isDueSoon ? 'due-soon' : 'renewable'))) ?>">
                            <td>
                                <?= htmlspecialchars($loan['customer_name']) ?> (ID: <?= $loan['customer_id'] ?>)<br>
                                <small class="text-muted"><?= htmlspecialchars($loan['email']) ?><br><?= htmlspecialchars($loan['phone']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($loan['book_title']) ?> (ID: <?= $loan['book_id'] ?>)</td>
                            <td><?= $loan['material_type_display'] ?></td>
                            <td><?= $loan['borrow_date'] ?></td>
                            <td>
                                <?= $loan['current_due_date'] ?>
                                <?php if ($isDueSoon): ?>
                                    <span class="badge bg-warning">Due in <?= ceil($dueInDays) ?> days</span>
                                <?php endif; ?>
                                <?php if ($loan['is_overdue'] && $loan['status'] !== 'Returned'): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($loan['status'] === 'Returned'): ?>
                                    <span class="badge bg-secondary">Returned</span>
                                    <?php if ($loan['late_fee'] > 0): ?>
                                        <br><small class="text-danger">Late fee: ₱<?= $loan['late_fee'] ?></small>
                                    <?php endif; ?>
                                <?php elseif ($loan['is_overdue']): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                    <?php if ($loan['late_fee'] > 0): ?>
                                        <br><small class="text-danger">Fee: ₱<?= $loan['late_fee'] ?></small>
                                    <?php endif; ?>
                                <?php elseif ($loan['status'] === 'Reserved'): ?>
                                    <span class="badge bg-info">Reserved</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Borrowed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-2">
                                    <?php if ($loan['status'] !== 'Returned'): ?>
                                        <?php if ($loan['is_renewable']): ?>
                                            <form method="POST" class="mb-2">
                                                <input type="hidden" name="action" value="renew_loan">
                                                <input type="hidden" name="loan_id" value="<?= $loan['loan_id'] ?>">
                                                <input type="hidden" name="customer_name" value="<?= htmlspecialchars($loan['customer_name']) ?>">
                                                <input type="hidden" name="book_title" value="<?= htmlspecialchars($loan['book_title']) ?>">
                                                <input type="hidden" name="current_due_date" value="<?= $loan['current_due_date'] ?>">
                                                <input type="hidden" name="material_type" value="<?= $loan['material_type'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm w-100" onclick="return confirm('Renew this loan for another 7 days?')">
                                                    <i class="fas fa-redo"></i> Renew
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm w-100" disabled title="<?= $loan['is_overdue'] ? 'Cannot renew overdue items' : ($loan['material_type'] !== 'book' ? 'Only books can be renewed' : 'Not renewable') ?>">
                                                <i class="fas fa-ban"></i> Can't Renew
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($loan['is_returnable']): ?>
                                            <?php if ($loan['is_overdue'] && $loan['status'] === 'Overdue'): ?>
                                                <button class="btn btn-warning btn-sm w-100" data-bs-toggle="modal" data-bs-target="#paymentModal" 
                                                    data-loan-id="<?= $loan['loan_id'] ?>"
                                                    data-late-fee="<?= $loan['late_fee'] ?>"
                                                    data-book-title="<?= htmlspecialchars($loan['book_title']) ?>">
                                                    <i class="fas fa-money-bill-wave"></i> Pay Fee
                                                </button>
                                            <?php else: ?>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="return_loan">
                                                    <input type="hidden" name="loan_id" value="<?= $loan['loan_id'] ?>">
                                                    <input type="hidden" name="customer_name" value="<?= htmlspecialchars($loan['customer_name']) ?>">
                                                    <input type="hidden" name="book_title" value="<?= htmlspecialchars($loan['book_title']) ?>">
                                                    <input type="hidden" name="current_due_date" value="<?= $loan['current_due_date'] ?>">
                                                    <input type="hidden" name="material_type" value="<?= $loan['material_type'] ?>">
                                                    <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('Mark this material as returned?')">
                                                        <i class="fas fa-undo"></i> Return
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm w-100" disabled title="This material type will automatically be marked as returned when due">
                                                <i class="fas fa-check"></i> Auto-return
                                            </button>
                                        <?php endif; ?>
                                        
                                        <div class="btn-group w-100" role="group">
                                            <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#reminderModal" 
                                                data-loan-id="<?= $loan['loan_id'] ?>" 
                                                data-book-title="<?= htmlspecialchars($loan['book_title']) ?>" 
                                                data-due-date="<?= $loan['current_due_date'] ?>"
                                                data-customer-email="<?= htmlspecialchars($loan['email']) ?>"
                                                data-customer-phone="<?= htmlspecialchars($loan['phone']) ?>">
                                                <i class="fas fa-bell"></i> Remind
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm w-100" disabled>
                                            <i class="fas fa-check"></i> Returned
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="alert alert-info mt-4">
                <h5><i class="fas fa-info-circle"></i> Loan Management Policies</h5>
                <ul>
                    <li><strong>Books:</strong> Can be renewed if not overdue, must be physically returned</li>
                    <li><strong>Digital Media:</strong> Automatically marked as returned after 7 days</li>
                    <li><strong>Archival Materials:</strong> Automatically marked as returned after 7 days</li>
                    <li><strong>Late Fees:</strong> ₱50 per day for overdue books (must be paid before marking as returned)</li>
                    <li>Each renewal extends the due date by 7 days (books only)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Single Reminder Modal -->
<div class="modal fade" id="reminderModal" tabindex="-1" aria-labelledby="reminderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Due Date Reminder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_reminder">
                <input type="hidden" name="loan_id" id="reminder_loan_id">
                <input type="hidden" name="book_title" id="reminder_book_title">
                <input type="hidden" name="due_date" id="reminder_due_date">
                <input type="hidden" name="customer_email" id="reminder_customer_email">
                <input type="hidden" name="customer_phone" id="reminder_customer_phone">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reminder Type</label>
                        <select name="reminder_type" class="form-select" required>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recipient Email</label>
                        <input type="text" class="form-control" id="display_email" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recipient Phone</label>
                        <input type="text" class="form-control" id="display_phone" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message Preview</label>
                        <textarea class="form-control" rows="3" id="message_preview" readonly></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reminder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Process Late Fee Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="loan_id" id="payment_loan_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Book Title</label>
                        <input type="text" class="form-control" id="payment_book_title" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Late Fee</label>
                        <input type="text" class="form-control" id="payment_late_fee" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Amount (₱)</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="payment_amount" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="gcash">GCash</option>
                            <option value="paymaya">PayMaya</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Process Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Reminder Modal -->
<div class="modal fade" id="bulkReminderModal" tabindex="-1" aria-labelledby="bulkReminderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Bulk Reminders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_bulk_reminders">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reminder Type</label>
                        <select name="bulk_reminder_type" class="form-select" required>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Send Reminders For Items Due In</label>
                        <select name="days_before" class="form-select" required>
                            <option value="1">1 Day</option>
                            <option value="2">2 Days</option>
                            <option value="3" selected>3 Days</option>
                            <option value="7">7 Days</option>
                        </select>
                    </div>
                    <div class="alert alert-warning">
                        This will send reminders to all customers with items due in the selected timeframe.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Bulk Reminders</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize reminder modal with data
document.getElementById('reminderModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const loanId = button.getAttribute('data-loan-id');
    const bookTitle = button.getAttribute('data-book-title');
    const dueDate = button.getAttribute('data-due-date');
    const email = button.getAttribute('data-customer-email');
    const phone = button.getAttribute('data-customer-phone');
    
    const modal = this;
    modal.querySelector('#reminder_loan_id').value = loanId;
    modal.querySelector('#reminder_book_title').value = bookTitle;
    modal.querySelector('#reminder_due_date').value = dueDate;
    modal.querySelector('#reminder_customer_email').value = email;
    modal.querySelector('#reminder_customer_phone').value = phone;
    
    // Display fields
    modal.querySelector('#display_email').value = email;
    modal.querySelector('#display_phone').value = phone;
    modal.querySelector('#message_preview').value = 
        `Dear customer,\n\nThis is a reminder that your book "${bookTitle}" is due on ${dueDate}.\n\nPlease return or renew it to avoid late fees.\n\nThank you,\nLibrary Staff`;
});

// Initialize payment modal with data
document.getElementById('paymentModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const loanId = button.getAttribute('data-loan-id');
    const lateFee = button.getAttribute('data-late-fee');
    const bookTitle = button.getAttribute('data-book-title');
    
    const modal = this;
    modal.querySelector('#payment_loan_id').value = loanId;
    modal.querySelector('#payment_late_fee').value = '₱' + lateFee;
    modal.querySelector('#payment_book_title').value = bookTitle;
    
    // Set minimum payment amount
    modal.querySelector('input[name="payment_amount"]').min = lateFee;
    modal.querySelector('input[name="payment_amount"]').value = lateFee;
});

// Auto-select SMS if phone is clicked
document.getElementById('display_phone').addEventListener('click', function() {
    document.querySelector('#reminderModal select[name="reminder_type"]').value = 'sms';
});
</script>
</body>
</html>