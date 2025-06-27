<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['material_type'])) {
    $rand = rand(1000, 9999);
    $type = $_POST['material_type'];
    $title = "$type Title $rand";
    $author = "$type Author $rand";
    $isbn = "ISBN$rand";
    $publisher = "$type Publisher $rand";
    $year = date("Y");
    $quantity = 5;
    $available = 5;
    $status = 'Available';

    $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, publisher, year_published, quantity, available, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $author, $isbn, $publisher, $year, $quantity, $available, $status]);

    $_SESSION['success'] = "$type '$title' added successfully.";
    header("Location: materials_management.php");
    exit;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $bookId = isset($_GET['book_id']) ? (int)$_GET['book_id'] : null;

    switch ($action) {
        case 'edit':
            if ($bookId) {
                $newTitle = "Edited Material " . rand(1000, 9999);
                $stmt = $pdo->prepare("UPDATE books SET title = ? WHERE book_id = ?");
                $stmt->execute([$newTitle, $bookId]);
                $_SESSION['success'] = "Material ID $bookId updated to '$newTitle'.";
            }
            break;

        case 'delete':
            if ($bookId) {
                $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = ?");
                $stmt->execute([$bookId]);
                $_SESSION['success'] = "Material ID $bookId deleted successfully.";
            }
            break;
    }

    header("Location: materials_management.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM books");
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Materials Management</title>
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
                <h2>Materials Management</h2>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#selectMaterialTypeModal">
                    <i class="fas fa-plus"></i> Add Material
                </button>
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

            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>ISBN</th>
                        <th>Publisher</th>
                        <th>Year</th>
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
                            <td><?= htmlspecialchars($book['year_published']) ?></td>
                            <td><?= htmlspecialchars($book['available']) ?></td>
                            <td><?= htmlspecialchars($book['status']) ?></td>
                            <td>
                                <a href="?action=edit&book_id=<?= $book['book_id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                                <a href="?action=delete&book_id=<?= $book['book_id'] ?>" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Select Material Type Modal -->
<div class="modal fade" id="selectMaterialTypeModal" tabindex="-1" aria-labelledby="selectMaterialTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <form method="POST" action="">
                    <input type="hidden" name="material_type" value="Book">
                    <button type="submit" class="btn btn-outline-primary w-100 mb-2" data-bs-dismiss="modal">Add Book</button>
                </form>
                <form method="POST" action="">
                    <input type="hidden" name="material_type" value="Digital Media">
                    <button type="submit" class="btn btn-outline-success w-100 mb-2" data-bs-dismiss="modal">Add Digital Media</button>
                </form>
                <form method="POST" action="">
                    <input type="hidden" name="material_type" value="Archival">
                    <button type="submit" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">Add Archival</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>