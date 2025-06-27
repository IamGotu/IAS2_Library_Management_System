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

// Handle material addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['material_type'])) {
    $rand = rand(1000, 9999);
    $type = $_POST['material_type'];
    $year = date("Y");
    $status = 'Available';

    $pdo->beginTransaction();
    try {
        switch ($type) {
            case 'Book':
                $title = "Book Title $rand";
                $author = "Book Author $rand";
                $isbn = "ISBN$rand";
                $publisher = "Book Publisher $rand";
                $quantity = 5;
                $available = 5;

                $stmt = $pdo->prepare("INSERT INTO material_books (title, author, isbn, publisher, year_published, quantity, available, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $author, $isbn, $publisher, $year, $quantity, $available, $status]);
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Added new book: $title");
                break;

            case 'Digital Media':
                $title = "Digital Media Title $rand";
                $author = "Digital Media Author $rand";
                $isbn = "ISBN$rand";
                $publisher = "Digital Media Publisher $rand";
                $media_type = 'eBook';

                $stmt = $pdo->prepare("INSERT INTO material_digital_media (title, author, isbn, publisher, year_published, media_type, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $author, $isbn, $publisher, $year, $media_type, $status]);
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Added new digital media: $title");
                break;

            case 'Archival':
                $title = "Archival Title $rand";
                $author = "Archival Author $rand";
                $isbn = "ISBN$rand";
                $publisher = "Archival Publisher $rand";
                $description = "Archived material description for $title";

                $stmt = $pdo->prepare("INSERT INTO material_research (title, author, isbn, publisher, year_published, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $author, $isbn, $publisher, $year, $description, $status]);
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Added new archival material: $title");
                break;
        }

        $pdo->commit();
        $_SESSION['success'] = "$type '$title' added successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Failed to add $type: " . $e->getMessage());
        $_SESSION['error'] = "Error adding $type: " . $e->getMessage();
    }
    header("Location: materials_management.php");
    exit;
}

// Handle genre/tag management logging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $itemId = $_POST['item_id'] ?? '';
    $itemName = $_POST['item_name'] ?? '';
    
    try {
        switch ($action) {
            case 'update_genre':
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Updated book genre #$itemId: $itemName");
                $_SESSION['success'] = "Book genre '$itemName' update logged";
                break;
                
            case 'update_tag':
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Updated tag collection #$itemId: $itemName");
                $_SESSION['success'] = "Tag collection '$itemName' update logged";
                break;
        }
    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Failed to log $action: " . $e->getMessage());
        $_SESSION['error'] = "Error logging activity: " . $e->getMessage();
    }
    
    header("Location: materials_management.php");
    exit;
}

// Handle material edits/deletes
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $materialId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $table = $_GET['table'] ?? '';

    try {
        switch ($action) {
            case 'edit':
                if ($materialId && $table) {
                    $newTitle = "Edited Material " . rand(1000, 9999);
                    $stmt = $pdo->prepare("UPDATE $table SET title = ? WHERE id = ?");
                    $stmt->execute([$newTitle, $materialId]);
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Edited material ID $materialId in $table to '$newTitle'");
                    $_SESSION['success'] = "Material ID $materialId updated to '$newTitle'.";
                }
                break;

            case 'delete':
                if ($materialId && $table) {
                    // Get material info before deleting for logging
                    $stmt = $pdo->prepare("SELECT title FROM $table WHERE id = ?");
                    $stmt->execute([$materialId]);
                    $materialTitle = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
                    $stmt->execute([$materialId]);
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Deleted material from $table: $materialTitle (ID: $materialId)");
                    $_SESSION['success'] = "Material ID $materialId deleted successfully.";
                }
                break;
        }
    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Failed to $action material: " . $e->getMessage());
        $_SESSION['error'] = "Error performing action: " . $e->getMessage();
    }

    header("Location: materials_management.php");
    exit;
}

$filter = $_GET['filter'] ?? 'books';
$books = $digitals = $research = [];

if ($filter === 'books') {
    $stmtBooks = $pdo->query("SELECT * FROM material_books");
    $books = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);
}
if ($filter === 'digital') {
    $stmtDigital = $pdo->query("SELECT * FROM material_digital_media");
    $digitals = $stmtDigital->fetchAll(PDO::FETCH_ASSOC);
}
if ($filter === 'archival') {
    $stmtResearch = $pdo->query("SELECT * FROM material_research");
    $research = $stmtResearch->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Materials Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-hover:hover {
            transform: translateY(-5px);
            transition: transform 0.2s;
        }
        .badge-genre {
            background-color: #17a2b8;
        }
        .badge-tag {
            background-color: #6c757d;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library System - Materials Management</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>Materials Management</h2>
                <div>
                    <button class="btn btn-info me-2" id="manageGenresBtn">
                        <i class="fas fa-tags"></i> Manage Book Genres
                    </button>
                    <button class="btn btn-secondary me-2" id="manageTagsBtn">
                        <i class="fas fa-hashtag"></i> Manage Tags
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#selectMaterialTypeModal">
                        <i class="fas fa-plus"></i> Add Material
                    </button>
                </div>
            </div>

            <div class="btn-group mb-3" role="group">
                <a href="?filter=books" class="btn btn-outline-primary <?= $filter === 'books' ? 'active' : '' ?>">Books</a>
                <a href="?filter=digital" class="btn btn-outline-success <?= $filter === 'digital' ? 'active' : '' ?>">Digital Media</a>
                <a href="?filter=archival" class="btn btn-outline-warning <?= $filter === 'archival' ? 'active' : '' ?>">Archival</a>
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

            <?php if ($filter === 'books'): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Books</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>ISBN</th>
                                        <th>Publisher</th>
                                        <th>Available</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($books as $book): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($book['title']) ?></td>
                                            <td><?= htmlspecialchars($book['author']) ?></td>
                                            <td><?= htmlspecialchars($book['isbn']) ?></td>
                                            <td><?= htmlspecialchars($book['publisher']) ?></td>
                                            <td><?= $book['available'] ?> / <?= $book['quantity'] ?></td>
                                            <td>
                                                <span class="badge bg-<?= $book['status'] === 'Available' ? 'success' : 'danger' ?>">
                                                    <?= htmlspecialchars($book['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?action=edit&id=<?= $book['id'] ?>&table=material_books" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?action=delete&id=<?= $book['id'] ?>&table=material_books" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this book?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($filter === 'digital'): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">Digital Media</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Media Type</th>
                                        <th>Publisher</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($digitals as $media): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($media['title']) ?></td>
                                            <td><?= htmlspecialchars($media['author']) ?></td>
                                            <td><?= htmlspecialchars($media['media_type']) ?></td>
                                            <td><?= htmlspecialchars($media['publisher']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $media['status'] === 'Available' ? 'success' : 'danger' ?>">
                                                    <?= htmlspecialchars($media['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?action=edit&id=<?= $media['id'] ?>&table=material_digital_media" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?action=delete&id=<?= $media['id'] ?>&table=material_digital_media" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this digital media?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($filter === 'archival'): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">Archival Materials</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Description</th>
                                        <th>Publisher</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($research as $arch): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($arch['title']) ?></td>
                                            <td><?= htmlspecialchars($arch['author']) ?></td>
                                            <td><?= htmlspecialchars(substr($arch['description'], 0, 50)) . (strlen($arch['description']) > 50 ? '...' : '') ?></td>
                                            <td><?= htmlspecialchars($arch['publisher']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $arch['status'] === 'Available' ? 'success' : 'danger' ?>">
                                                    <?= htmlspecialchars($arch['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?action=edit&id=<?= $arch['id'] ?>&table=material_research" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?action=delete&id=<?= $arch['id'] ?>&table=material_research" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this archival material?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Select Material Type Modal -->
<div class="modal fade" id="selectMaterialTypeModal" tabindex="-1" aria-labelledby="selectMaterialTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add New Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <form method="POST" action="">
                    <input type="hidden" name="material_type" value="Book">
                    <button type="submit" class="btn btn-outline-primary w-100 mb-2 py-3">
                        <i class="fas fa-book fa-2x mb-2"></i><br>
                        Add Book
                    </button>
                </form>
                <form method="POST" action="">
                    <input type="hidden" name="material_type" value="Digital Media">
                    <button type="submit" class="btn btn-outline-success w-100 mb-2 py-3">
                        <i class="fas fa-compact-disc fa-2x mb-2"></i><br>
                        Add Digital Media
                    </button>
                </form>
                <form method="POST" action="">
                    <input type="hidden" name="material_type" value="Archival">
                    <button type="submit" class="btn btn-outline-warning w-100 py-3">
                        <i class="fas fa-archive fa-2x mb-2"></i><br>
                        Add Archival
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for logging genre/tag management -->
<form id="logForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="logAction">
    <input type="hidden" name="item_id" id="logItemId">
    <input type="hidden" name="item_name" id="logItemName">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Manage Genres button click
    document.getElementById('manageGenresBtn').addEventListener('click', function() {
        const randomId = Math.floor(1000 + Math.random() * 9000);
        document.getElementById('logAction').value = 'update_genre';
        document.getElementById('logItemId').value = randomId;
        document.getElementById('logItemName').value = 'Book Genre Program ' + randomId;
        document.getElementById('logForm').submit();
    });

    // Handle Manage Tags button click
    document.getElementById('manageTagsBtn').addEventListener('click', function() {
        const randomId = Math.floor(1000 + Math.random() * 9000);
        document.getElementById('logAction').value = 'update_tag';
        document.getElementById('logItemId').value = randomId;
        document.getElementById('logItemName').value = 'Tag Collection ' + randomId;
        document.getElementById('logForm').submit();
    });
});
</script>
</body>
</html>