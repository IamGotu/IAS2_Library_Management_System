<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

function logActivity($pdo, $userId, $role, $actionDesc) {
    // Fetch full name based on user role
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

$filter = $_GET['filter'] ?? 'books';

// Handle form submissions for Reserve/Borrow/Return/Cancel Reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $materialType = $_POST['material_type'];
    $materialId = (int)$_POST['material_id'];
    $customerId = (int)$_POST['customer_id'];
    $action = $_POST['action'];

    $logUserId = $_SESSION['user_id'];
    $logUserRole = $_SESSION['user_role'];

    // Validate Researcher for Archival
    if ($materialType === 'research' && ($action === 'Reserve' || $action === 'Borrow')) {
        $stmt = $pdo->prepare("SELECT role_id FROM customer WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $roleId = $stmt->fetchColumn();
        if ($roleId != 10) {
            $_SESSION['error'] = "Only researchers are allowed to request archival materials.";
            header("Location: ?filter=archival");
            exit;
        }
    }

    if ($action === 'Reserve') {
        $stmt = $pdo->prepare("INSERT INTO material_transactions (material_type, material_id, customer_id, action) VALUES (?, ?, ?, ?)");
        $stmt->execute([$materialType, $materialId, $customerId, $action]);
        logActivity($pdo, $logUserId, $logUserRole, "Reserved material ID $materialId for customer ID $customerId");
    } elseif ($action === 'Borrow') {
        if ($materialType === 'book') {
            $stmtCheck = $pdo->prepare("SELECT available FROM material_books WHERE id = ? AND available > 0");
            $stmtCheck->execute([$materialId]);
            if (!$stmtCheck->fetchColumn()) {
                $_SESSION['error'] = "No available copies to borrow.";
                header("Location: ?filter=$filter");
                exit;
            }
            $stmtUpdate = $pdo->prepare("UPDATE material_books SET available = available - 1 WHERE id = ?");
            $stmtUpdate->execute([$materialId]);
        }
        $stmt = $pdo->prepare("INSERT INTO material_transactions (material_type, material_id, customer_id, action) VALUES (?, ?, ?, ?)");
        $stmt->execute([$materialType, $materialId, $customerId, $action]);
        logActivity($pdo, $logUserId, $logUserRole, "Borrowed material ID $materialId for customer ID $customerId");
    } elseif ($action === 'Return') {
        $stmtReturn = $pdo->prepare("SELECT transaction_id FROM material_transactions WHERE material_type = ? AND material_id = ? AND customer_id = ? AND action = 'Borrow' ORDER BY action_date DESC LIMIT 1");
        $stmtReturn->execute([$materialType, $materialId, $customerId]);
        $borrowTransaction = $stmtReturn->fetchColumn();
        if ($borrowTransaction) {
            $stmt = $pdo->prepare("INSERT INTO material_transactions (material_type, material_id, customer_id, action) VALUES (?, ?, ?, 'Return')");
            $stmt->execute([$materialType, $materialId, $customerId]);
            if ($materialType === 'book') {
                $stmtUpdate = $pdo->prepare("UPDATE material_books SET available = available + 1 WHERE id = ?");
                $stmtUpdate->execute([$materialId]);
            }
            logActivity($pdo, $logUserId, $logUserRole, "Returned material ID $materialId by customer ID $customerId");
        } else {
            $_SESSION['error'] = "No borrow record found for return.";
            header("Location: ?filter=$filter");
            exit;
        }
    } elseif ($action === 'Cancel Reservation') {
        $stmtCancel = $pdo->prepare("DELETE FROM material_transactions WHERE material_type = ? AND material_id = ? AND customer_id = ? AND action = 'Reserve' LIMIT 1");
        $stmtCancel->execute([$materialType, $materialId, $customerId]);
        logActivity($pdo, $logUserId, $logUserRole, "Cancelled reservation of material ID $materialId by customer ID $customerId");
    }

    $_SESSION['success'] = "$action successful.";
    header("Location: ?filter=$filter");
    exit;
}

// Enhanced activity logs query with role name
$stmt = $pdo->query("
    SELECT 
        l.user_id,
        l.user_role,
        l.full_name,
        r.role_name,
        l.action,
        l.timestamp
    FROM activity_logs l
    LEFT JOIN employees e ON l.user_role = 'employee' AND l.user_id = e.employee_id
    LEFT JOIN customer c ON l.user_role = 'customer' AND l.user_id = c.customer_id
    LEFT JOIN roles r 
        ON (l.user_role = 'employee' AND e.role_id = r.role_id)
        OR (l.user_role = 'customer' AND c.role_id = r.role_id)
    ORDER BY l.timestamp DESC
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library System</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>Activity Logs</h2>
            </div>

            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['user_id']) ?></td>
                            <td><?= htmlspecialchars($log['full_name']) ?></td>
                            <td><?= htmlspecialchars($log['user_role']) ?></td>
                            <td><?= htmlspecialchars($log['role_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td><?= htmlspecialchars($log['timestamp']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
