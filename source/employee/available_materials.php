<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$filter = $_GET['filter'] ?? 'books';

// Handle form submissions for Reserve/Borrow/Return/Cancel Reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $materialType = $_POST['material_type'];
    $materialId = (int)$_POST['material_id'];
    $customerId = (int)$_POST['customer_id'];
    $action = $_POST['action'];

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
        // Insert reserve transaction
        $stmt = $pdo->prepare("INSERT INTO material_transactions (material_type, material_id, customer_id, action) VALUES (?, ?, ?, ?)");
        $stmt->execute([$materialType, $materialId, $customerId, $action]);
    } elseif ($action === 'Borrow') {
        // Check availability before borrow
        if ($materialType === 'book') {
            $stmtCheck = $pdo->prepare("SELECT available FROM material_books WHERE id = ? AND available > 0");
            $stmtCheck->execute([$materialId]);
            if (!$stmtCheck->fetchColumn()) {
                $_SESSION['error'] = "No available copies to borrow.";
                header("Location: ?filter=$filter");
                exit;
            }
            // Decrement available count
            $stmtUpdate = $pdo->prepare("UPDATE material_books SET available = available - 1 WHERE id = ?");
            $stmtUpdate->execute([$materialId]);
        }
        // Insert borrow transaction
        $stmt = $pdo->prepare("INSERT INTO material_transactions (material_type, material_id, customer_id, action) VALUES (?, ?, ?, ?)");
        $stmt->execute([$materialType, $materialId, $customerId, $action]);
    } elseif ($action === 'Return') {
        // Find the borrow transaction and mark return by inserting a Return action
        $stmtReturn = $pdo->prepare("SELECT transaction_id FROM material_transactions WHERE material_type = ? AND material_id = ? AND customer_id = ? AND action = 'Borrow' ORDER BY action_date DESC LIMIT 1");
        $stmtReturn->execute([$materialType, $materialId, $customerId]);
        $borrowTransaction = $stmtReturn->fetchColumn();
        if ($borrowTransaction) {
            // Insert return transaction
            $stmt = $pdo->prepare("INSERT INTO material_transactions (material_type, material_id, customer_id, action) VALUES (?, ?, ?, 'Return')");
            $stmt->execute([$materialType, $materialId, $customerId]);
            // Increment available if book
            if ($materialType === 'book') {
                $stmtUpdate = $pdo->prepare("UPDATE material_books SET available = available + 1 WHERE id = ?");
                $stmtUpdate->execute([$materialId]);
            }
        } else {
            $_SESSION['error'] = "No borrow record found for return.";
            header("Location: ?filter=$filter");
            exit;
        }
    } elseif ($action === 'Cancel Reservation') {
        // Find the reserve transaction and remove it
        $stmtCancel = $pdo->prepare("DELETE FROM material_transactions WHERE material_type = ? AND material_id = ? AND customer_id = ? AND action = 'Reserve' LIMIT 1");
        $stmtCancel->execute([$materialType, $materialId, $customerId]);
    }

    $_SESSION['success'] = "$action successful.";
    header("Location: ?filter=$filter");
    exit;
}

// Fetch materials based on filter
switch ($filter) {
    case 'digital':
        $stmt = $pdo->query("SELECT * FROM material_digital_media WHERE status = 'Available'");
        break;
    case 'archival':
        $stmt = $pdo->query("SELECT * FROM material_research WHERE status = 'Available'");
        break;
    default:
        $stmt = $pdo->query("SELECT * FROM material_books WHERE status = 'Available'");
        break;
}
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active customers
$customers = $pdo->query("SELECT customer_id, first_name, last_name, role_id FROM customer WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);

// Fetch current reservations and borrows per material and customer to show Return and Cancel buttons
$stmtTrans = $pdo->prepare("
    SELECT material_type, material_id, customer_id, action, transaction_id
    FROM material_transactions
    WHERE action IN ('Reserve', 'Borrow')
");
$stmtTrans->execute();
$transactions = $stmtTrans->fetchAll(PDO::FETCH_ASSOC);

// Group transactions by material and customer for quick lookup
$transMap = [];
foreach ($transactions as $tr) {
    $key = $tr['material_type'].'_'.$tr['material_id'].'_'.$tr['customer_id'];
    $transMap[$key][] = $tr;
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

            <div class="btn-group mb-3">
                <a href="?filter=books" class="btn btn-outline-primary <?= $filter === 'books' ? 'active' : '' ?>">Books</a>
                <a href="?filter=digital" class="btn btn-outline-success <?= $filter === 'digital' ? 'active' : '' ?>">Digital Media</a>
                <a href="?filter=archival" class="btn btn-outline-warning <?= $filter === 'archival' ? 'active' : '' ?>">Archival</a>
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

                        <!-- Actions Column Update -->
                        <td>
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reserveModal_<?= $materialId ?>">Reserve</button>
                            <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#borrowModal_<?= $materialId ?>">Borrow</button>
                            
                            <!-- Button to open Return modal -->
                            <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#returnModal_<?= $materialId ?>">Return</button>
                            
                            <!-- Button to open Cancel Reservation modal -->
                            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelReservationModal_<?= $materialId ?>">Cancel Reservation</button>
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
                                <?php
                                // Fetch customers who reserved this material ordered by earliest reservation date
                                $reservedCustomersStmt = $pdo->prepare("
                                    SELECT DISTINCT c.customer_id, c.first_name, c.last_name
                                    FROM material_transactions mt
                                    JOIN customer c ON mt.customer_id = c.customer_id
                                    WHERE mt.material_type = :material_type
                                    AND mt.material_id = :material_id
                                    AND mt.action = 'Reserve'
                                    ORDER BY mt.action_date ASC
                                ");
                                $reservedCustomersStmt->execute([
                                    ':material_type' => $materialType,
                                    ':material_id' => $materialId
                                ]);
                                $reservedCustomers = $reservedCustomersStmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <select name="customer_id" class="form-select" required>
                                    <option value="">Select Customer</option>
                                    <?php foreach ($reservedCustomers as $cust): ?>
                                        <option value="<?= $cust['customer_id'] ?>">
                                            <?= htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="action" value="Borrow" class="btn btn-success">Borrow</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Return Modal -->
                <div class="modal fade" id="returnModal_<?= $materialId ?>" tabindex="-1" aria-labelledby="returnModalLabel_<?= $materialId ?>" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <input type="hidden" name="material_type" value="<?= $materialType ?>">
                            <input type="hidden" name="material_id" value="<?= $materialId ?>">
                            <div class="modal-header">
                                <h5 class="modal-title" id="returnModalLabel_<?= $materialId ?>">Return Material - <?= htmlspecialchars($item['title']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <label for="return_customer_id_<?= $materialId ?>" class="form-label">Select Customer who borrowed:</label>
                                <select name="customer_id" id="return_customer_id_<?= $materialId ?>" class="form-select" required>
                                    <option value="">Select Customer</option>
                                    <?php
                                    // Filter customers who currently have a Borrow transaction for this material
                                    foreach ($customers as $cust) {
                                        $key = $materialType . '_' . $materialId . '_' . $cust['customer_id'];
                                        if (!isset($transMap[$key])) continue;

                                        // Check if any borrow exists for this customer & material
                                        $hasBorrow = false;
                                        foreach ($transMap[$key] as $tr) {
                                            if ($tr['action'] === 'Borrow') {
                                                $hasBorrow = true;
                                                break;
                                            }
                                        }
                                        if ($hasBorrow) {
                                            echo '<option value="' . $cust['customer_id'] . '">' . htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="action" value="Return" class="btn btn-warning">Return</button>
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
                                <label for="cancel_customer_id_<?= $materialId ?>" class="form-label">Select Customer who reserved:</label>
                                <select name="customer_id" id="cancel_customer_id_<?= $materialId ?>" class="form-select" required>
                                    <option value="">Select Customer</option>
                                    <?php
                                    // Filter customers who currently have a Reserve transaction for this material
                                    foreach ($customers as $cust) {
                                        $key = $materialType . '_' . $materialId . '_' . $cust['customer_id'];
                                        if (!isset($transMap[$key])) continue;

                                        // Check if any reserve exists for this customer & material
                                        $hasReserve = false;
                                        foreach ($transMap[$key] as $tr) {
                                            if ($tr['action'] === 'Reserve') {
                                                $hasReserve = true;
                                                break;
                                            }
                                        }
                                        if ($hasReserve) {
                                            echo '<option value="' . $cust['customer_id'] . '">' . htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="action" value="Cancel Reservation" class="btn btn-danger">Cancel Reservation</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
