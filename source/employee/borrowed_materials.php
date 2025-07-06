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
            'is_overdue' => strtotime($dueDate) < time(),
            'email' => strtolower($firstNames[array_rand($firstNames)]) . '.' . strtolower($lastNames[array_rand($lastNames)]) . '@example.com',
            'phone' => '555-' . rand(100, 999) . '-' . rand(1000, 9999)
        ];
    }
    
    return $loans;
}

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
                    // Simulate renewal
                    $newDueDate = date('Y-m-d', strtotime($currentDueDate.' + 14 days'));
                    
                    // Log the renewal action
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                        "Renewed loan #$loanId for '$bookTitle' (Customer: $customerName). New due date: $newDueDate");
                    
                    $_SESSION['success'] = "Successfully renewed loan for '$bookTitle' (New due date: $newDueDate)";
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
                    
                    // Simulate sending bulk reminders
                    $count = rand(3, 10); // Random count for simulation
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
        
        header("Location: renew_loans.php");
        exit;
    }
}

$activeLoans = generateRandomActiveLoans();
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
                <h2>Active Book Loans</h2>
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
                        <th>Loan ID</th>
                        <th>Customer</th>
                        <th>Book Title</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Renewals</th>
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
                            <td>#<?= $loan['loan_id'] ?></td>
                            <td>
                                <?= $loan['customer_name'] ?> (ID: <?= $loan['customer_id'] ?>)<br>
                                <small class="text-muted"><?= $loan['email'] ?><br><?= $loan['phone'] ?></small>
                            </td>
                            <td><?= $loan['book_title'] ?> (ID: <?= $loan['book_id'] ?>)</td>
                            <td><?= $loan['borrow_date'] ?></td>
                            <td>
                                <?= $loan['current_due_date'] ?>
                                <?php if ($isDueSoon): ?>
                                    <span class="badge bg-warning">Due in <?= ceil($dueInDays) ?> days</span>
                                <?php endif; ?>
                            </td>
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
                                <div class="d-flex flex-column gap-2">
                                    <?php if ($loan['is_renewable']): ?>
                                        <form method="POST" class="mb-2">
                                            <input type="hidden" name="action" value="renew_loan">
                                            <input type="hidden" name="loan_id" value="<?= $loan['loan_id'] ?>">
                                            <input type="hidden" name="customer_name" value="<?= $loan['customer_name'] ?>">
                                            <input type="hidden" name="book_title" value="<?= $loan['book_title'] ?>">
                                            <input type="hidden" name="current_due_date" value="<?= $loan['current_due_date'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm w-100" onclick="return confirm('Renew this loan for another 14 days?')">
                                                <i class="fas fa-redo"></i> Renew
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm w-100" disabled title="<?= $loan['renewal_count'] >= 2 ? 'Maximum renewals reached' : 'Not renewable' ?>">
                                            <i class="fas fa-ban"></i> Can't Renew
                                        </button>
                                    <?php endif; ?>
                                    
                                    <div class="btn-group w-100" role="group">
                                        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#reminderModal" 
                                            data-loan-id="<?= $loan['loan_id'] ?>" 
                                            data-book-title="<?= htmlspecialchars($loan['book_title']) ?>" 
                                            data-due-date="<?= $loan['current_due_date'] ?>"
                                            data-customer-email="<?= $loan['email'] ?>"
                                            data-customer-phone="<?= $loan['phone'] ?>">
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
                    <li>Books can be renewed up to 2 times</li>
                    <li>Each renewal extends the due date by 14 days</li>
                    <li>Overdue books may still be renewable at librarian's discretion</li>
                    <li>Reminders are sent automatically 3 days before due date</li>
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