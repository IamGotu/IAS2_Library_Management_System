<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $bookId = isset($_GET['book_id']) ? (int)$_GET['book_id'] : null;

    switch ($action) {
        case 'add':
            $rand = rand(1000, 9999);
            $title = "Simulated Book $rand";
            $author = "SimAuthor $rand";
            $isbn = "ISBN$rand";
            $publisher = "Publisher $rand";
            $year = date("Y");
            $quantity = 5;
            $available = 5;
            $status = 'Available';

            $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, publisher, year_published, quantity, available, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $author, $isbn, $publisher, $year, $quantity, $available, $status]);

            $_SESSION['success'] = "Simulated Book '$title' added successfully.";
            break;

        case 'edit':
            if ($bookId) {
                $newTitle = "Edited Title " . rand(1000, 9999);
                $stmt = $pdo->prepare("UPDATE books SET title = ? WHERE book_id = ?");
                $stmt->execute([$newTitle, $bookId]);
                $_SESSION['success'] = "Book ID $bookId updated to '$newTitle'.";
            }
            break;

        case 'delete':
            if ($bookId) {
                $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = ?");
                $stmt->execute([$bookId]);
                $_SESSION['success'] = "Book ID $bookId deleted successfully.";
            }
            break;

        case 'borrow':
            if ($bookId) {
                $stmt = $pdo->prepare("SELECT * FROM books WHERE book_id = ? AND available > 0 AND status = 'Requested'");
                $stmt->execute([$bookId]);
                $book = $stmt->fetch();
                if ($book) {
                    $pdo->prepare("UPDATE books SET available = available - 1, status = 'Borrowed' WHERE book_id = ?")->execute([$bookId]);
                    $_SESSION['success'] = "Book ID $bookId borrowed successfully.";
                } else {
                    $_SESSION['error'] = "Book ID $bookId is not available or not requested for borrowing.";
                }
            }
            break;

        case 'return':
            if ($bookId) {
                $stmt = $pdo->prepare("SELECT * FROM books WHERE book_id = ? AND status = 'Borrowed'");
                $stmt->execute([$bookId]);
                $book = $stmt->fetch();
                if ($book) {
                    $pdo->prepare("UPDATE books SET available = available + 1, status = 'Available' WHERE book_id = ?")->execute([$bookId]);
                    $_SESSION['success'] = "Book ID $bookId returned successfully.";
                } else {
                    $_SESSION['error'] = "Book ID $bookId was not borrowed.";
                }
            }
            break;
    }
    header("Location: book_management.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM books");
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Books</title>
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
                <h2>Book Management</h2>
                <a href="?action=add" class="btn btn-success"><i class="fas fa-plus"></i> Add Book</a>
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
                                <a href="?action=borrow&book_id=<?= $book['book_id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-book"></i></a>
                                <a href="?action=return&book_id=<?= $book['book_id'] ?>" class="btn btn-info btn-sm"><i class="fas fa-undo"></i></a>
                            </td>
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