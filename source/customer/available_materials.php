<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../login.php");
    exit;
}

// Get current user's role
$stmt = $pdo->prepare("SELECT role_id FROM customer WHERE customer_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUserRole = $stmt->fetchColumn();

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

function revokeExpiredAccess($pdo) {
    $currentDate = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("SELECT * FROM material_transactions 
                          WHERE action = 'Grant Access' 
                          AND due_date IS NOT NULL 
                          AND due_date < ?");
    $stmt->execute([$currentDate]);
    $expiredAccess = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($expiredAccess as $access) {
        if ($access['material_type'] === 'book') {
            $stmtUpdate = $pdo->prepare("UPDATE material_books SET available = available + 1 WHERE id = ?");
            $stmtUpdate->execute([$access['material_id']]);
        }
        
        logActivity($pdo, 0, 'system', "Automatically revoked access to {$access['material_type']} ID {$access['material_id']} for customer ID {$access['customer_id']} (Expired)");
        
        $stmtDelete = $pdo->prepare("DELETE FROM material_transactions WHERE transaction_id = ?");
        $stmtDelete->execute([$access['transaction_id']]);
    }
}

revokeExpiredAccess($pdo);

$filter = $_GET['filter'] ?? 'books';
$search = $_GET['search'] ?? '';

// If user is not a researcher and tries to access archival directly, redirect to books
if ($filter === 'archival' && $currentUserRole != 10) {
    $filter = 'books';
    $_SESSION['error'] = "Only researchers can access archival materials.";
    header("Location: ?filter=books");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $materialType = $_POST['material_type'];
    $materialId = (int)$_POST['material_id'];
    $customerId = (int)$_SESSION['user_id']; // Always use logged-in customer's ID
    $action = $_POST['action'];

    $logUserId = $_SESSION['user_id'];
    $logUserRole = $_SESSION['user_role'];

    // Additional check for archival materials
    if ($materialType === 'research') {
        if ($currentUserRole != 10) {
            $_SESSION['error'] = "Only researchers are allowed to request archival materials.";
            header("Location: ?filter=archival");
            exit;
        }
    }

    if ($action === 'Reserve' || $action === 'Request Access') {
        // Check if customer already has a pending request or granted access
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM material_transactions 
                                   WHERE material_type = ? 
                                   AND material_id = ? 
                                   AND customer_id = ? 
                                   AND (action = ? OR action = 'Grant Access')");
        $actionToCheck = ($materialType === 'book') ? 'Reserve' : 'Request Access';
        $stmtCheck->execute([$materialType, $materialId, $customerId, $actionToCheck]);
        
        if ($stmtCheck->fetchColumn() > 0) {
            $_SESSION['error'] = "You already have a pending or granted access for this material.";
            header("Location: ?filter=$filter");
            exit;
        }

        if ($materialType === 'book') {
            $stmtCheck = $pdo->prepare("SELECT available FROM material_books WHERE id = ? AND available > 0");
            $stmtCheck->execute([$materialId]);
            if (!$stmtCheck->fetchColumn()) {
                $_SESSION['error'] = "No available copies to reserve.";
                header("Location: ?filter=$filter");
                exit;
            }
        }
        
        $actionValue = ($materialType === 'book') ? 'Reserve' : 'Request Access';
        $stmt = $pdo->prepare("INSERT INTO material_transactions (material_type, material_id, customer_id, action) VALUES (?, ?, ?, ?)");
        $stmt->execute([$materialType, $materialId, $customerId, $actionValue]);
        
        logActivity($pdo, $logUserId, $logUserRole, "Requested access to $materialType ID $materialId");
    } 
    elseif ($action === 'Cancel Reservation' || $action === 'Cancel Request') {
        $requestAction = ($materialType === 'book') ? 'Reserve' : 'Request Access';
        $stmtCancel = $pdo->prepare("DELETE FROM material_transactions WHERE material_type = ? AND material_id = ? AND customer_id = ? AND action = ? LIMIT 1");
        $stmtCancel->execute([$materialType, $materialId, $customerId, $requestAction]);
        
        logActivity($pdo, $logUserId, $logUserRole, "Cancelled request for $materialType ID $materialId");
    }

    $_SESSION['success'] = "$action successful.";
    header("Location: ?filter=$filter");
    exit;
}

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

$stmtTrans = $pdo->prepare("
    SELECT material_type, material_id, customer_id, action, due_date
    FROM material_transactions
    WHERE action IN ('Reserve', 'Request Access', 'Grant Access')
    AND customer_id = ?
");
$stmtTrans->execute([$_SESSION['user_id']]);
$userRequests = $stmtTrans->fetchAll(PDO::FETCH_ASSOC);

$requestMap = [];
foreach ($userRequests as $req) {
    $key = $req['material_type'].'_'.$req['material_id'];
    if (!isset($requestMap[$key])) {
        $requestMap[$key] = [];
    }
    $requestMap[$key][] = $req;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Available Materials</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .access-list {
            max-height: 150px;
            overflow-y: auto;
        }
    </style>
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
                    <?php if ($currentUserRole == 10): ?>
                        <a href="?filter=archival" class="btn btn-outline-warning <?= $filter === 'archival' ? 'active' : '' ?>">Archival</a>
                    <?php endif; ?>
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
                        $requestKey = $materialType.'_'.$materialId;
                        $hasUserRequest = isset($requestMap[$requestKey]);
                        
                        $userHasPendingRequest = false;
                        $userHasGrantedAccess = false;
                        
                        if ($hasUserRequest) {
                            foreach ($requestMap[$requestKey] as $req) {
                                if ($req['action'] === 'Request Access' || $req['action'] === 'Reserve') {
                                    $userHasPendingRequest = true;
                                }
                                if ($req['action'] === 'Grant Access') {
                                    $userHasGrantedAccess = true;
                                }
                            }
                        }
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
                                <div class="d-flex flex-column gap-2">
                                    <?php if (!$userHasPendingRequest && !$userHasGrantedAccess && ($filter === 'books' && $item['available'] > 0 || $filter !== 'books')): ?>
                                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestModal_<?= $materialId ?>">
                                            <?= ($filter === 'books') ? 'Reserve' : 'Request Access' ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($userHasPendingRequest): ?>
                                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal_<?= $materialId ?>">
                                            <?= ($filter === 'books') ? 'Cancel Reservation' : 'Cancel Request' ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($userHasGrantedAccess): ?>
                                        <div class="access-list mt-2">
                                            <small class="fw-bold">Your access:</small>
                                            <ul class="list-unstyled">
                                                <?php foreach ($requestMap[$requestKey] as $access): 
                                                    if ($access['action'] === 'Grant Access'):
                                                        $dueDate = date('M j, Y', strtotime($access['due_date'])); ?>
                                                        <li class="d-flex justify-content-between align-items-center">
                                                            <span>You have access</span>
                                                            <span class="badge bg-info">Expires <?= $dueDate ?></span>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modals -->
            <?php foreach ($materials as $item):
                $materialId = $item['id'];
                $materialType = $filter === 'books' ? 'book' : ($filter === 'digital' ? 'digital' : 'research');
                ?>
                <!-- Request Modal -->
                <div class="modal fade" id="requestModal_<?= $materialId ?>" tabindex="-1" aria-labelledby="requestModalLabel_<?= $materialId ?>" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <input type="hidden" name="material_type" value="<?= $materialType ?>">
                            <input type="hidden" name="material_id" value="<?= $materialId ?>">
                            <input type="hidden" name="customer_id" value="<?= $_SESSION['user_id'] ?>">
                            <div class="modal-header">
                                <h5 class="modal-title" id="requestModalLabel_<?= $materialId ?>">
                                    <?= ($filter === 'books') ? 'Reserve' : 'Request Access' ?> - <?= htmlspecialchars($item['title']) ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>You are about to <?= ($filter === 'books') ? 'reserve this book' : 'request access to this material' ?>.</p>
                                <?php 
                                // Check if customer already has access
                                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM material_transactions 
                                                           WHERE material_type = ? 
                                                           AND material_id = ? 
                                                           AND customer_id = ? 
                                                           AND (action = ? OR action = 'Grant Access')");
                                $actionToCheck = ($materialType === 'book') ? 'Reserve' : 'Request Access';
                                $stmtCheck->execute([$materialType, $materialId, $_SESSION['user_id'], $actionToCheck]);
                                $hasAccess = $stmtCheck->fetchColumn() > 0;
                                
                                if ($hasAccess): ?>
                                    <div class="alert alert-warning">You already have a pending request or granted access for this material.</div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="action" value="<?= ($filter === 'books') ? 'Reserve' : 'Request Access' ?>" class="btn btn-primary" <?= $hasAccess ? 'disabled' : '' ?>>
                                    <?= ($filter === 'books') ? 'Reserve' : 'Request' ?>
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Cancel Modal -->
                <div class="modal fade" id="cancelModal_<?= $materialId ?>" tabindex="-1" aria-labelledby="cancelModalLabel_<?= $materialId ?>" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <input type="hidden" name="material_type" value="<?= $materialType ?>">
                            <input type="hidden" name="material_id" value="<?= $materialId ?>">
                            <input type="hidden" name="customer_id" value="<?= $_SESSION['user_id'] ?>">
                            <div class="modal-header">
                                <h5 class="modal-title" id="cancelModalLabel_<?= $materialId ?>">
                                    <?= ($filter === 'books') ? 'Cancel Reservation' : 'Cancel Request' ?> - <?= htmlspecialchars($item['title']) ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to cancel your <?= ($filter === 'books') ? 'reservation' : 'request' ?> for this material?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="action" value="<?= ($filter === 'books') ? 'Cancel Reservation' : 'Cancel Request' ?>" class="btn btn-danger">
                                    Yes, Cancel
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep It</button>
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