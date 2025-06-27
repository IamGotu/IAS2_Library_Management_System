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
                break;

            case 'Digital Media':
                $title = "Digital Media Title $rand";
                $author = "Digital Media Author $rand";
                $isbn = "ISBN$rand";
                $publisher = "Digital Media Publisher $rand";
                $media_type = 'eBook';

                $stmt = $pdo->prepare("INSERT INTO material_digital_media (title, author, isbn, publisher, year_published, media_type, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $author, $isbn, $publisher, $year, $media_type, $status]);
                break;

            case 'Archival':
                $title = "Archival Title $rand";
                $author = "Archival Author $rand";
                $isbn = "ISBN$rand";
                $publisher = "Archival Publisher $rand";
                $description = "Archived material description for $title";

                $stmt = $pdo->prepare("INSERT INTO material_research (title, author, isbn, publisher, year_published, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $author, $isbn, $publisher, $year, $description, $status]);
                break;
        }

        $pdo->commit();
        $_SESSION['success'] = "$type '$title' added successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error adding $type: " . $e->getMessage();
    }
    header("Location: materials_management.php");
    exit;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $materialId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $table = $_GET['table'] ?? '';

    switch ($action) {
        case 'edit':
            if ($materialId && $table) {
                $newTitle = "Edited Material " . rand(1000, 9999);
                $stmt = $pdo->prepare("UPDATE $table SET title = ? WHERE id = ?");
                $stmt->execute([$newTitle, $materialId]);
                $_SESSION['success'] = "Material ID $materialId updated to '$newTitle'.";
            }
            break;

        case 'delete':
            if ($materialId && $table) {
                $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->execute([$materialId]);
                $_SESSION['success'] = "Material ID $materialId deleted successfully.";
            }
            break;
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
                <h4>Books</h4>
                <table class="table table-bordered">
                    <thead class="table-dark">
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
                                <td><?= htmlspecialchars($book['status']) ?></td>
                                <td>
                                    <a href="?action=edit&id=<?= $book['id'] ?>&table=material_books" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                                    <a href="?action=delete&id=<?= $book['id'] ?>&table=material_books" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($filter === 'digital'): ?>
                <h4>Digital Media</h4>
                <table class="table table-bordered">
                    <thead class="table-dark">
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
                                <td><?= htmlspecialchars($media['status']) ?></td>
                                <td>
                                    <a href="?action=edit&id=<?= $media['id'] ?>&table=material_digital_media" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                                    <a href="?action=delete&id=<?= $media['id'] ?>&table=material_digital_media" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($filter === 'archival'): ?>
                <h4>Archival</h4>
                <table class="table table-bordered">
                    <thead class="table-dark">
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
                                <td><?= htmlspecialchars($arch['description']) ?></td>
                                <td><?= htmlspecialchars($arch['publisher']) ?></td>
                                <td><?= htmlspecialchars($arch['status']) ?></td>
                                <td>
                                    <a href="?action=edit&id=<?= $arch['id'] ?>&table=material_research" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                                    <a href="?action=delete&id=<?= $arch['id'] ?>&table=material_research" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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
                    <button type="submit" class="btn btn-outline-primary w-100 mb-2">Add Book</button>
                </form>
                <form method="POST" action="">
                    <input type="hidden" name="material_type" value="Digital Media">
                    <button type="submit" class="btn btn-outline-success w-100 mb-2">Add Digital Media</button>
                </form>
                <form method="POST" action="">
                    <input type="hidden" name="material_type" value="Archival">
                    <button type="submit" class="btn btn-outline-warning w-100">Add Archival</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
