<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$filter = $_GET['filter'] ?? 'books';

// Handle form submissions for Reserve/Borrow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $materialType = $_POST['material_type'];
    $materialId = (int)$_POST['material_id'];
    $customerId = (int)$_POST['customer_id'];
    $action = $_POST['action'];

    // Validate Researcher for Archival
    if ($materialType === 'research') {
        $stmt = $pdo->prepare("SELECT role_id FROM customer WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $roleId = $stmt->fetchColumn();
        if ($roleId != 10) {
            $_SESSION['error'] = "Only researchers are allowed to request archival materials.";
            header("Location: ?filter=archival");
            exit;
        }
    }

    // Log to material_transactions
    $stmt = $pdo->prepare("INSERT INTO material_transactions (material_type, material_id, customer_id, action) VALUES (?, ?, ?, ?)");
    $stmt->execute([$materialType, $materialId, $customerId, $action]);

    // Update availability if borrowed
    if ($action === 'Borrow' && $materialType === 'book') {
        $stmt = $pdo->prepare("UPDATE material_books SET available = available - 1 WHERE id = ? AND available > 0");
        $stmt->execute([$materialId]);
    }

    $_SESSION['success'] = "$action successful.";
    header("Location: ?filter=$filter");
    exit;
}

// Fetch materials
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Available Materials</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
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
                        $modalId = 'modal_' . $filter . '_' . $materialId;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($item['title']) ?></td>
                        <td><?= htmlspecialchars($item['author']) ?></td>
                        <?php if ($filter === 'books'): ?>
                            <td><?= htmlspecialchars($item['isbn']) ?></td>
                            <td><?= $item['available'] ?> / <?= $item['quantity'] ?></td>
                        <?php elseif ($filter === 'digital'): ?>
                            <td><?= htmlspecialchars($item['media_type']) ?></td>
                        <?php elseif ($filter === 'archival'): ?>
                            <td><?= htmlspecialchars($item['description']) ?></td>
                        <?php endif; ?>
                        <td>
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">Reserve</button>
                            <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">Borrow</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Render modals after table -->
            <?php foreach ($materials as $item): 
                $materialId = $item['id'];
                $modalId = 'modal_' . $filter . '_' . $materialId;
            ?>
            <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-labelledby="<?= $modalId ?>Label" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" class="modal-content">
                        <input type="hidden" name="material_type" value="<?= $filter === 'books' ? 'book' : ($filter === 'digital' ? 'digital' : 'research') ?>">
                        <input type="hidden" name="material_id" value="<?= $materialId ?>">
                        <div class="modal-header">
                            <h5 class="modal-title" id="<?= $modalId ?>Label">Select Customer</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <select name="customer_id" class="form-select mb-3" required>
                                <option value="">Choose Customer</option>
                                <?php foreach ($customers as $cust): ?>
                                    <?php
                                        if ($filter === 'archival' && $cust['role_id'] != 10) continue;
                                        $fullName = htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']);
                                    ?>
                                    <option value="<?= $cust['customer_id'] ?>"><?= $fullName ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="d-flex justify-content-end">
                                <button type="submit" name="action" value="Reserve" class="btn btn-outline-primary me-2">Reserve</button>
                                <button type="submit" name="action" value="Borrow" class="btn btn-outline-success">Borrow</button>
                            </div>
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