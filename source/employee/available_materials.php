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

function getCustomerName($customers, $customerId) {
    foreach ($customers as $customer) {
        if ($customer['customer_id'] == $customerId) {
            return $customer['first_name'].' '.$customer['last_name'];
        }
    }
    return 'Unknown Customer';
}

$filter = $_GET['filter'] ?? 'books';
$search = $_GET['search'] ?? '';

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
        // Check if material is available (but don't decrement quantity yet)
        if ($materialType === 'book') {
            $stmtCheck = $pdo->prepare("SELECT available FROM material_books WHERE id = ? AND available > 0");
            $stmtCheck->execute([$materialId]);
            if (!$stmtCheck->fetchColumn()) {
                $_SESSION['error'] = "No available copies to reserve.";
                header("Location: ?filter=$filter");
                exit;
            }
        }
        
        // Check if already reserved by this customer
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM material_transactions WHERE material_type = ? AND material_id = ? AND customer_id = ? AND action = 'Reserve'");
        $stmtCheck->execute([$materialType, $materialId, $customerId]);
        if ($stmtCheck->fetchColumn() > 0) {
            $_SESSION['error'] = "This customer already reserved this material.";
            header("Location: ?filter=$filter");
            exit;
        }
        
        // Create reservation (without modifying available count)
        $stmt = $pdo->prepare("INSERT INTO material_transactions (material_type, material_id, customer_id, action) VALUES (?, ?, ?, 'Reserve')");
        $stmt->execute([$materialType, $materialId, $customerId]);
        
        logActivity($pdo, $logUserId, $logUserRole, "Reserved material ID $materialId for customer ID $customerId");
    } 
    elseif ($action === 'Borrow') {
        // Check reservation exists
        $stmtCheck = $pdo->prepare("SELECT transaction_id FROM material_transactions WHERE material_type = ? AND material_id = ? AND customer_id = ? AND action = 'Reserve'");
        $stmtCheck->execute([$materialType, $materialId, $customerId]);
        $reservationId = $stmtCheck->fetchColumn();
        
        if (!$reservationId) {
            $_SESSION['error'] = "No reservation found to borrow.";
            header("Location: ?filter=$filter");
            exit;
        }

        // For books, check availability and decrement quantity
        if ($materialType === 'book') {
            $stmtCheck = $pdo->prepare("SELECT available FROM material_books WHERE id = ? AND available > 0 FOR UPDATE");
            $stmtCheck->execute([$materialId]);
            if (!$stmtCheck->fetchColumn()) {
                $_SESSION['error'] = "No available copies to borrow.";
                header("Location: ?filter=$filter");
                exit;
            }
            
            // Now decrement the available count
            $stmtUpdate = $pdo->prepare("UPDATE material_books SET available = available - 1 WHERE id = ?");
            $stmtUpdate->execute([$materialId]);
        }

        // Calculate due date (7 days from now)
        $dueDate = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Create borrow record with due date
        $stmt = $pdo->prepare("INSERT INTO material_transactions (material_type, material_id, customer_id, action, due_date) VALUES (?, ?, ?, 'Borrow', ?)");
        $stmt->execute([$materialType, $materialId, $customerId, $dueDate]);
        
        // Remove the reservation
        $stmtDelete = $pdo->prepare("DELETE FROM material_transactions WHERE transaction_id = ?");
        $stmtDelete->execute([$reservationId]);
        
        logActivity($pdo, $logUserId, $logUserRole, "Borrowed material ID $materialId for customer ID $customerId (Due: $dueDate)");
    } 
    elseif ($action === 'Cancel Reservation') {
        // Delete reservation
        $stmtCancel = $pdo->prepare("DELETE FROM material_transactions WHERE material_type = ? AND material_id = ? AND customer_id = ? AND action = 'Reserve' LIMIT 1");
        $stmtCancel->execute([$materialType, $materialId, $customerId]);
        
        logActivity($pdo, $logUserId, $logUserRole, "Cancelled reservation of material ID $materialId by customer ID $customerId");
    }

    $_SESSION['success'] = "$action successful.";
    header("Location: ?filter=$filter");
    exit;
}

// Query materials - show all available books (available > 0)
$queryParams = [];
$queryWhere = "";

if ($search) {
    $queryWhere .= " AND (title LIKE ? OR author LIKE ?)";
    $searchTerm = "%$search%";
    $queryParams = [$searchTerm, $searchTerm];
}

switch ($filter) {
    case 'digital':
        $query = "SELECT * FROM material_digital_media WHERE 1=1 $queryWhere";
        break;
    case 'archival':
        $query = "SELECT * FROM material_research WHERE 1=1 $queryWhere";
        break;
    default:
        $query = "SELECT * FROM material_books WHERE available > 0 $queryWhere";
        break;
}

$stmt = $pdo->prepare($query);
$stmt->execute($queryParams);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active customers
$customers = $pdo->query("SELECT customer_id, first_name, last_name, role_id FROM customer WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);

// Fetch current reservations
$stmtTrans = $pdo->prepare("
    SELECT material_type, material_id, customer_id, action
    FROM material_transactions
    WHERE action = 'Reserve'
");
$stmtTrans->execute();
$reservations = $stmtTrans->fetchAll(PDO::FETCH_ASSOC);

// Group reservations by material for quick lookup
$reservationMap = [];
foreach ($reservations as $res) {
    $key = $res['material_type'].'_'.$res['material_id'];
    if (!isset($reservationMap[$key])) {
        $reservationMap[$key] = [];
    }
    $reservationMap[$key][] = $res;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Available Materials</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>
    <div class="flex-grow-1">
        <nav class="navbar navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library System</a>
            </div>
        </nav>

        <div class="container mt-4">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between mb-3">
                <div class="btn-group">
                    <a href="?filter=books" class="btn btn-outline-primary <?= $filter === 'books' ? 'active' : '' ?>">Books</a>
                    <a href="?filter=digital" class="btn btn-outline-success <?= $filter === 'digital' ? 'active' : '' ?>">Digital Media</a>
                    <a href="?filter=archival" class="btn btn-outline-warning <?= $filter === 'archival' ? 'active' : '' ?>">Archival</a>
                </div>
                
                <form method="GET" class="d-flex">
                    <input type="hidden" name="filter" value="<?= $filter ?>">
                    <input type="text" name="search" class="form-control me-2" placeholder="Search title or author" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-outline-secondary"><i class="fas fa-search"></i></button>
                    <?php if ($search): ?>
                        <a href="?filter=<?= $filter ?>" class="btn btn-outline-danger ms-2"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <table class="table table-bordered">
                <thead class="table-dark">
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <?php if ($filter === 'books'): ?><th>ISBN</th><th>Available</th><?php endif; ?>
                    <?php if ($filter === 'digital'): ?><th>Media Type</th><?php endif; ?>
                    <?php if ($filter === 'archival'): ?><th>Description</th><?php endif; ?>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $item):
                        $materialId = $item['id'];
                        $materialType = $filter === 'books' ? 'book' : ($filter === 'digital' ? 'digital' : 'research');
                        $reservationKey = $materialType.'_'.$materialId;
                        $hasReservations = isset($reservationMap[$reservationKey]);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($item['title']) ?></td>
                            <td><?= htmlspecialchars($item['author']) ?></td>
                            <?php if ($filter === 'books'): ?>
                                <td><?= htmlspecialchars($item['isbn']) ?></td>
                                <td><?= (int)$item['available'] ?> / <?= (int)$item['quantity'] ?></td>
                            <?php elseif ($filter === 'digital'): ?>
                                <td><?= htmlspecialchars($item['media_type']) ?></td>
                            <?php elseif ($filter === 'archival'): ?>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                            <?php endif; ?>
                            <td>
                                <?php if (($filter === 'books' && $item['available'] > 0) || $filter !== 'books'): ?>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reserveModal_<?= $materialId ?>">Reserve</button>
                                <?php endif; ?>
                                
                                <?php if ($hasReservations): ?>
                                    <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#borrowModal_<?= $materialId ?>">Borrow</button>
                                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelReservationModal_<?= $materialId ?>">Cancel</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modals -->
            <?php foreach ($materials as $item):
                $materialId = $item['id'];
                $materialType = $filter === 'books' ? 'book' : ($filter === 'digital' ? 'digital' : 'research');
                $reservationKey = $materialType.'_'.$materialId;
                ?>
                <!-- Reserve Modal -->
                <div class="modal fade" id="reserveModal_<?= $materialId ?>" tabindex="-1" aria-labelledby="reserveModalLabel_<?= $materialId ?>" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <input type="hidden" name="material_type" value="<?= $materialType ?>">
                            <input type="hidden" name="material_id" value="<?= $materialId ?>">
                            <div class="modal-header">
                                <h5 class="modal-title" id="reserveModalLabel_<?= $materialId ?>">Reserve - <?= htmlspecialchars($item['title']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <select name="customer_id" class="form-select" required>
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $cust):
                                        if ($filter === 'archival' && $cust['role_id'] != 10) continue;
                                        ?>
                                        <option value="<?= $cust['customer_id'] ?>"><?= htmlspecialchars($cust['first_name'].' '.$cust['last_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="action" value="Reserve" class="btn btn-primary">Reserve</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Borrow Modal -->
                <div class="modal fade" id="borrowModal_<?= $materialId ?>" tabindex="-1" aria-labelledby="borrowModalLabel_<?= $materialId ?>" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <input type="hidden" name="material_type" value="<?= $materialType ?>">
                            <input type="hidden" name="material_id" value="<?= $materialId ?>">
                            <div class="modal-header">
                                <h5 class="modal-title" id="borrowModalLabel_<?= $materialId ?>">Borrow - <?= htmlspecialchars($item['title']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (isset($reservationMap[$reservationKey])): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Select Customer:</label>
                                        <select name="customer_id" class="form-select" required>
                                            <option value="">Select Customer</option>
                                            <?php foreach ($reservationMap[$reservationKey] as $reservation): ?>
                                                <option value="<?= $reservation['customer_id'] ?>">
                                                    <?= htmlspecialchars(getCustomerName($customers, $reservation['customer_id'])) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="alert alert-info">
                                        <strong>Due Date:</strong> <?= date('F j, Y', strtotime('+7 days')) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">No reservations found for this material.</div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="action" value="Borrow" class="btn btn-success">Borrow</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Cancel Reservation Modal -->
                <div class="modal fade" id="cancelReservationModal_<?= $materialId ?>" tabindex="-1" aria-labelledby="cancelReservationModalLabel_<?= $materialId ?>" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <input type="hidden" name="material_type" value="<?= $materialType ?>">
                            <input type="hidden" name="material_id" value="<?= $materialId ?>">
                            <div class="modal-header">
                                <h5 class="modal-title" id="cancelReservationModalLabel_<?= $materialId ?>">Cancel Reservation - <?= htmlspecialchars($item['title']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label">Select Customer:</label>
                                <select name="customer_id" class="form-select" required>
                                    <option value="">Select Customer</option>
                                    <?php if (isset($reservationMap[$reservationKey])): ?>
                                        <?php foreach ($reservationMap[$reservationKey] as $reservation): ?>
                                            <option value="<?= $reservation['customer_id'] ?>">
                                                <?= htmlspecialchars(getCustomerName($customers, $reservation['customer_id'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="action" value="Cancel Reservation" class="btn btn-danger">Cancel Reservation</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>