<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../login.php");
    exit;
}

$filter = $_GET['filter'] ?? 'books';
$books = $digitals = $research = [];

if ($filter === 'books') {
    $stmt = $pdo->query("SELECT * FROM material_books WHERE available > 0 AND status = 'Available'");
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($filter === 'digital') {
    $stmt = $pdo->query("SELECT * FROM material_digital_media WHERE status = 'Available'");
    $digitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($filter === 'archival') {
    $stmt = $pdo->query("SELECT * FROM material_research WHERE status = 'Available'");
    $research = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Available Materials</title>
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
                <h2>Available Materials</h2>
            </div>

            <div class="btn-group mb-3" role="group">
                <a href="?filter=books" class="btn btn-outline-primary <?= $filter === 'books' ? 'active' : '' ?>">Books</a>
                <a href="?filter=digital" class="btn btn-outline-success <?= $filter === 'digital' ? 'active' : '' ?>">Digital Media</a>
                <a href="?filter=archival" class="btn btn-outline-warning <?= $filter === 'archival' ? 'active' : '' ?>">Archival</a>
            </div>

            <?php if ($filter === 'books'): ?>
                <h4>Books</h4>
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Available</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td><?= htmlspecialchars($book['title']) ?></td>
                                <td><?= htmlspecialchars($book['author']) ?></td>
                                <td><?= htmlspecialchars($book['isbn']) ?></td>
                                <td><?= $book['available'] ?> / <?= $book['quantity'] ?></td>
                                <td>
                                    <a href="reserve.php?id=<?= $book['id'] ?>&type=book" class="btn btn-primary btn-sm">Reserve</a>
                                    <a href="borrow.php?id=<?= $book['id'] ?>&type=book" class="btn btn-success btn-sm">Borrow</a>
                                    <a href="return.php?id=<?= $book['id'] ?>&type=book" class="btn btn-secondary btn-sm">Return</a>
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
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($digitals as $media): ?>
                            <tr>
                                <td><?= htmlspecialchars($media['title']) ?></td>
                                <td><?= htmlspecialchars($media['author']) ?></td>
                                <td><?= htmlspecialchars($media['media_type']) ?></td>
                                <td>
                                    <a href="reserve.php?id=<?= $media['id'] ?>&type=digital" class="btn btn-primary btn-sm">Reserve</a>
                                    <a href="borrow.php?id=<?= $media['id'] ?>&type=digital" class="btn btn-success btn-sm">Borrow</a>
                                    <a href="return.php?id=<?= $media['id'] ?>&type=digital" class="btn btn-secondary btn-sm">Return</a>
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($research as $arch): ?>
                            <tr>
                                <td><?= htmlspecialchars($arch['title']) ?></td>
                                <td><?= htmlspecialchars($arch['author']) ?></td>
                                <td><?= htmlspecialchars($arch['description']) ?></td>
                                <td>
                                    <a href="reserve.php?id=<?= $arch['id'] ?>&type=archival" class="btn btn-primary btn-sm">Reserve</a>
                                    <a href="borrow.php?id=<?= $arch['id'] ?>&type=archival" class="btn btn-success btn-sm">Borrow</a>
                                    <a href="return.php?id=<?= $arch['id'] ?>&type=archival" class="btn btn-secondary btn-sm">Return</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>