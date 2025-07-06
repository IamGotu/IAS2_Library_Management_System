<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Process auto-returns for this customer
function processAutoReturns($pdo, $customerId) {
    // Get all expired digital/archival materials for this customer that haven't been marked returned
    $stmt = $pdo->prepare("
        SELECT transaction_id, material_type, material_id 
        FROM material_transactions 
        WHERE customer_id = ?
          AND material_type IN ('digital', 'research') 
          AND due_date <= NOW() 
          AND status IN ('Reserved', 'Borrowed')
    ");
    $stmt->execute([$customerId]);
    $expiredMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($expiredMaterials as $material) {
        // Mark as returned
        $stmt = $pdo->prepare("
            UPDATE material_transactions 
            SET status = 'Returned', return_date = NOW() 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$material['transaction_id']]);
    }
}

// Process auto-returns before getting active loans
processAutoReturns($pdo, $_SESSION['user_id']);

// Get active loans for the logged-in customer
function getCustomerLoans($pdo, $customerId) {
    $loans = [];
    
    // Get book loans
    $stmt = $pdo->prepare("
        SELECT t.transaction_id AS loan_id, 
               b.title AS book_title,
               b.id AS book_id,
               t.action_date AS borrow_date,
               t.due_date AS current_due_date,
               t.due_date AS original_due_date,
               t.status,
               t.late_fee,
               'book' AS material_type
        FROM material_transactions t
        JOIN material_books b ON t.material_id = b.id
        WHERE t.customer_id = ?
          AND t.material_type = 'book' 
          AND t.action IN ('Borrow', 'Grant Access')
          AND t.status IN ('Reserved', 'Borrowed', 'Overdue')
    ");
    $stmt->execute([$customerId]);
    $bookLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get digital media loans
    $stmt = $pdo->prepare("
        SELECT t.transaction_id AS loan_id, 
               d.title AS book_title,
               d.id AS book_id,
               t.action_date AS borrow_date,
               t.due_date AS current_due_date,
               t.due_date AS original_due_date,
               t.status,
               t.late_fee,
               'digital' AS material_type
        FROM material_transactions t
        JOIN material_digital_media d ON t.material_id = d.id
        WHERE t.customer_id = ?
          AND t.material_type = 'digital' 
          AND t.action IN ('Borrow', 'Grant Access')
          AND t.status IN ('Reserved', 'Borrowed', 'Overdue')
    ");
    $stmt->execute([$customerId]);
    $digitalLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get research material loans
    $stmt = $pdo->prepare("
        SELECT t.transaction_id AS loan_id, 
               r.title AS book_title,
               r.id AS book_id,
               t.action_date AS borrow_date,
               t.due_date AS current_due_date,
               t.due_date AS original_due_date,
               t.status,
               t.late_fee,
               'research' AS material_type
        FROM material_transactions t
        JOIN material_research r ON t.material_id = r.id
        WHERE t.customer_id = ?
          AND t.material_type = 'research' 
          AND t.action IN ('Borrow', 'Grant Access')
          AND t.status IN ('Reserved', 'Borrowed', 'Overdue')
    ");
    $stmt->execute([$customerId]);
    $researchLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine all loans
    $allLoans = array_merge($bookLoans, $digitalLoans, $researchLoans);
    
    // Add additional calculated fields
    foreach ($allLoans as &$loan) {
        $loan['is_overdue'] = strtotime($loan['current_due_date']) < time();
        $loan['is_renewable'] = !$loan['is_overdue'] && $loan['material_type'] === 'book';
        
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

$customerLoans = getCustomerLoans($pdo, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Borrowed Materials</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .overdue { background-color: #ffdddd; }
        .due-soon { background-color: #ffeedd; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">My Borrowed Materials</a>
            </div>
        </nav>

        <div class="container mt-4">
            <h2>My Currently Borrowed Materials</h2>

            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customerLoans as $loan): 
                        $dueInDays = floor((strtotime($loan['current_due_date']) - time()) / (60 * 60 * 24));
                        $isDueSoon = $dueInDays <= 3 && $dueInDays >= 0;
                    ?>
                        <tr class="<?= $loan['is_overdue'] ? 'overdue' : ($isDueSoon ? 'due-soon' : '') ?>">
                            <td><?= htmlspecialchars($loan['book_title']) ?></td>
                            <td><?= $loan['material_type_display'] ?></td>
                            <td><?= $loan['borrow_date'] ?></td>
                            <td>
                                <?= $loan['current_due_date'] ?>
                                <?php if ($isDueSoon): ?>
                                    <span class="badge bg-warning">Due in <?= ceil($dueInDays) ?> days</span>
                                <?php endif; ?>
                                <?php if ($loan['is_overdue']): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($loan['is_overdue']): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                    <?php if ($loan['late_fee'] > 0): ?>
                                        <br><small class="text-danger">Late fee: ₱<?= $loan['late_fee'] ?></small>
                                    <?php endif; ?>
                                <?php elseif ($loan['status'] === 'Reserved'): ?>
                                    <span class="badge bg-info">Reserved</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Borrowed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($customerLoans)): ?>
                        <tr>
                            <td colspan="5" class="text-center">You currently have no borrowed materials</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="alert alert-info mt-4">
                <h5><i class="fas fa-info-circle"></i> Borrowing Policies</h5>
                <ul>
                    <li><strong>Books:</strong> Loan period is 7 days</li>
                    <li><strong>Digital Media:</strong> Automatically returned after 7 days</li>
                    <li><strong>Archival Materials:</strong> Automatically returned after 7 days</li>
                    <li><strong>Late Fees:</strong> ₱50 per day for overdue books</li>
                    <li>Please contact library staff for renewals or questions</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>