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
               0 AS renewal_count,  -- Assuming renewals aren't tracked in your current schema
               c.email,
               c.phone_num AS phone,
               t.status
        FROM material_transactions t
        JOIN customer c ON t.customer_id = c.customer_id
        JOIN material_books b ON t.material_id = b.id
        WHERE t.material_type = 'book' 
          AND t.action IN ('Borrow', 'Grant Access')
          AND t.status IN ('Reserved', 'Borrowed')
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
               0 AS renewal_count,  -- Assuming renewals aren't tracked in your current schema
               c.email,
               c.phone_num AS phone,
               t.status
        FROM material_transactions t
        JOIN customer c ON t.customer_id = c.customer_id
        JOIN material_digital_media d ON t.material_id = d.id
        WHERE t.material_type = 'digital' 
          AND t.action IN ('Borrow', 'Grant Access')
          AND t.status IN ('Reserved', 'Borrowed')
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
               0 AS renewal_count,  -- Assuming renewals aren't tracked in your current schema
               c.email,
               c.phone_num AS phone,
               t.status
        FROM material_transactions t
        JOIN customer c ON t.customer_id = c.customer_id
        JOIN material_research r ON t.material_id = r.id
        WHERE t.material_type = 'research' 
          AND t.action IN ('Borrow', 'Grant Access')
          AND t.status IN ('Reserved', 'Borrowed')
    ");
    $stmt->execute();
    $researchLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine all loans
    $allLoans = array_merge($bookLoans, $digitalLoans, $researchLoans);
    
    // Add additional calculated fields
    foreach ($allLoans as &$loan) {
        $loan['is_overdue'] = strtotime($loan['current_due_date']) < time();
        $loan['is_renewable'] = !$loan['is_overdue']; // Simple logic - can renew if not overdue
        
        // Format dates for display
        $loan['borrow_date'] = date('Y-m-d', strtotime($loan['borrow_date']));
        $loan['current_due_date'] = date('Y-m-d', strtotime($loan['current_due_date']));
        $loan['original_due_date'] = date('Y-m-d', strtotime($loan['original_due_date']));
    }
    
    return $allLoans;
}

$activeLoans = getActiveLoans($pdo);

// Handle renewal action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $loanId = $_POST['loan_id'] ?? 0;
        $customerName = $_POST['customer_name'] ?? '';
        $bookTitle = $_POST['book_title'] ?? '';
        $currentDueDate = $_POST['current_due_date'] ?? '';
        
        try {
            switch ($action) {
                case 'renew_loan':
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
                        <tr class="<?= $loan['is_overdue'] ? 'overdue' : ($loan['renewal_count'] > 0 ? 'renewed' : ($isDueSoon ? 'due-soon' : 'renewable')) ?>">
                            <td>
                                <?= htmlspecialchars($loan['customer_name']) ?> (ID: <?= $loan['customer_id'] ?>)<br>
                                <small class="text-muted"><?= htmlspecialchars($loan['email']) ?><br><?= htmlspecialchars($loan['phone']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($loan['book_title']) ?> (ID: <?= $loan['book_id'] ?>)</td>
                            <td><?= $loan['borrow_date'] ?></td>
                            <td>
                                <?= $loan['current_due_date'] ?>
                                <?php if ($isDueSoon): ?>
                                    <span class="badge bg-warning">Due in <?= ceil($dueInDays) ?> days</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($loan['is_overdue']): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                <?php elseif ($loan['status'] === 'Reserved'): ?>
                                    <span class="badge bg-info">Reserved</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Borrowed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-2">
                                    <?php if ($loan['is_renewable']): ?>
                                        <form method="POST" class="mb-2">
                                            <input type="hidden" name="action" value="renew_loan">
                                            <input type="hidden" name="loan_id" value="<?= $loan['loan_id'] ?>">
                                            <input type="hidden" name="customer_name" value="<?= htmlspecialchars($loan['customer_name']) ?>">
                                            <input type="hidden" name="book_title" value="<?= htmlspecialchars($loan['book_title']) ?>">
                                            <input type="hidden" name="current_due_date" value="<?= $loan['current_due_date'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm w-100" onclick="return confirm('Renew this loan for another 7 days?')">
                                                <i class="fas fa-redo"></i> Renew
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm w-100" disabled title="<?= $loan['is_overdue'] ? 'Cannot renew overdue items' : 'Not renewable' ?>">
                                            <i class="fas fa-ban"></i> Can't Renew
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
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="alert alert-info mt-4">
                <h5><i class="fas fa-info-circle"></i> Loan Management Policies</h5>
                <ul>
                    <li>Books can be renewed if they're not overdue</li>
                    <li>Each renewal extends the due date by 7 days</li>
                    <li>Overdue books cannot be renewed</li>
                    <li>Reminders can be sent manually or automatically</li>
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

// Auto-select SMS if phone is clicked
document.getElementById('display_phone').addEventListener('click', function() {
    document.querySelector('#reminderModal select[name="reminder_type"]').value = 'sms';
});
</script>
</body>
</html>