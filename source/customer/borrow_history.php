<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Function to get returned materials for the current customer with archival access check
function getCustomerReturnedMaterials($pdo, $customerId, $customerRole, $typeFilter = 'all') {
    $query = "SELECT 
                t.transaction_id,
                t.material_type,
                t.material_id,
                t.action_date AS borrow_date,
                t.due_date,
                t.return_date,
                t.status,
                t.late_fee,
                CASE 
                    WHEN t.return_date > t.due_date THEN DATEDIFF(t.return_date, t.due_date)
                    ELSE 0
                END AS days_late,
                CASE 
                    WHEN t.material_type = 'book' THEN b.title
                    WHEN t.material_type = 'digital' THEN d.title
                    WHEN t.material_type = 'research' THEN r.title
                END AS material_title
              FROM material_transactions t
              LEFT JOIN material_books b ON t.material_type = 'book' AND t.material_id = b.id
              LEFT JOIN material_digital_media d ON t.material_type = 'digital' AND t.material_id = d.id
              LEFT JOIN material_research r ON t.material_type = 'research' AND t.material_id = r.id
              WHERE t.customer_id = ? AND t.status = 'Returned'";
    
    $params = [$customerId];
    
    // Apply type filter if not 'all'
    if ($typeFilter !== 'all') {
        // If user is not a researcher and tries to filter by research materials, show nothing
        if ($typeFilter === 'research' && $customerRole != 10) {
            return [];
        }
        $query .= " AND t.material_type = ?";
        $params[] = $typeFilter;
    } else {
        // If user is not a researcher, exclude research materials from 'all' results
        if ($customerRole != 10) {
            $query .= " AND t.material_type != 'research'";
        }
    }
    
    $query .= " ORDER BY t.return_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the results for display
    $formattedResults = [];
    foreach ($results as $row) {
        $formattedResults[] = [
            'transaction_id' => $row['transaction_id'],
            'material_title' => $row['material_title'],
            'material_id' => $row['material_id'],
            'material_type' => ucfirst($row['material_type']),
            'borrow_date' => date('Y-m-d', strtotime($row['borrow_date'])),
            'due_date' => date('Y-m-d', strtotime($row['due_date'])),
            'return_date' => date('Y-m-d', strtotime($row['return_date'])),
            'days_late' => $row['days_late'],
            'status' => $row['status'],
            'is_late' => $row['days_late'] > 0,
            'late_fee' => $row['late_fee']
        ];
    }
    
    return $formattedResults;
}

// Get current user's role
$stmt = $pdo->prepare("SELECT role_id FROM customer WHERE customer_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userRole = $stmt->fetchColumn();

// Get returned materials for the current customer
$typeFilter = $_GET['type'] ?? 'all';
$returnedMaterials = getCustomerReturnedMaterials($pdo, $_SESSION['user_id'], $userRole, $typeFilter);

// Hide archival filter option if not a researcher
$showArchivalFilter = ($userRole == 10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Borrow History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .late { background-color: #ffdddd; }
        .returned { background-color: #ddffdd; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">My Borrow History</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>My Returned Materials</h2>
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

            <div class="mb-3">
                <div class="btn-group" role="group">
                    <a href="?type=all" class="btn btn-outline-dark <?= $typeFilter === 'all' ? 'active' : '' ?>">All Types</a>
                    <a href="?type=book" class="btn btn-outline-primary <?= $typeFilter === 'book' ? 'active' : '' ?>">Books</a>
                    <a href="?type=digital" class="btn btn-outline-info <?= $typeFilter === 'digital' ? 'active' : '' ?>">Digital</a>
                    <?php if ($showArchivalFilter): ?>
                        <a href="?type=research" class="btn btn-outline-secondary <?= $typeFilter === 'research' ? 'active' : '' ?>">Archival</a>
                    <?php endif; ?>
                </div>
            </div>

            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Transaction ID</th>
                        <th>Material</th>
                        <th>Type</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Days Late</th>
                        <th>Late Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($returnedMaterials as $item): ?>
                        <tr class="<?= $item['is_late'] ? 'late' : 'returned' ?>">
                            <td>#<?= $item['transaction_id'] ?></td>
                            <td><?= $item['material_title'] ?> (ID: <?= $item['material_id'] ?>)</td>
                            <td><?= $item['material_type'] ?></td>
                            <td><?= $item['borrow_date'] ?></td>
                            <td><?= $item['due_date'] ?></td>
                            <td><?= $item['return_date'] ?></td>
                            <td>
                                Returned
                                <?php if ($item['is_late']): ?>
                                    <span class="badge bg-danger">Late</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $item['days_late'] > 0 ? $item['days_late'] : '-' ?></td>
                            <td><?= $item['late_fee'] > 0 ? '₱' . number_format($item['late_fee'], 2) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($returnedMaterials)): ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                <?php 
                                if ($typeFilter === 'research' && $userRole != 10) {
                                    echo "Archival materials are only accessible to researchers";
                                } else {
                                    echo "No returned materials found";
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>