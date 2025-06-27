<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

function simulateAddBook($pdo) {
    $title = "Simulated Book " . rand(100, 999);
    $author = "Author " . rand(1, 50);
    $isbn = "ISBN" . rand(100000, 999999);
    $publisher = "Publisher Inc.";
    $year = 2024;
    $quantity = 5;

    $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, publisher, year_published, quantity, available) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $author, $isbn, $publisher, $year, $quantity, $quantity]);
    $_SESSION['success'] = "Book '$title' added successfully.";
    header("Location: book_management.php");
    exit;
}

function simulateEditBook($pdo) {
    $stmt = $pdo->query("SELECT book_id FROM books ORDER BY book_id DESC LIMIT 1");
    $book = $stmt->fetch();
    if ($book) {
        $newTitle = "Edited Title " . rand(1000, 9999);
        $stmt = $pdo->prepare("UPDATE books SET title = ? WHERE book_id = ?");
        $stmt->execute([$newTitle, $book['book_id']]);
        $_SESSION['success'] = "Book ID {$book['book_id']} updated to '$newTitle'.";
    } else {
        $_SESSION['error'] = "No books available to edit.";
    }
    header("Location: book_management.php");
    exit;
}

function simulateDeleteBook($pdo) {
    $stmt = $pdo->query("SELECT book_id FROM books ORDER BY book_id DESC LIMIT 1");
    $book = $stmt->fetch();
    if ($book) {
        $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = ?");
        $stmt->execute([$book['book_id']]);
        $_SESSION['success'] = "Book ID {$book['book_id']} deleted successfully.";
    } else {
        $_SESSION['error'] = "No books available to delete.";
    }
    header("Location: book_management.php");
    exit;
}

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'add':
            simulateAddBook($pdo);
            break;
        case 'edit':
            simulateEditBook($pdo);
            break;
        case 'delete':
            simulateDeleteBook($pdo);
            break;
    }
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
                <div>
                    <a href="?action=add" class="btn btn-success"><i class="fas fa-plus"></i> Add Book</a>
                    <a href="?action=edit" class="btn btn-warning"><i class="fas fa-edit"></i> Edit Book</a>
                    <a href="?action=delete" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Book</a>
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

            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>ISBN</th>
                        <th>Publisher</th>
                        <th>Year</th>
                        <th>Available</th>
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