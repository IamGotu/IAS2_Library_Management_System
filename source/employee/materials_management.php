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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_material'])) {
    $type = $_POST['material_type'];
    $status = 'Available';

    $pdo->beginTransaction();
    try {
        switch ($type) {
            case 'Book':
                $title = $_POST['book_title'];
                $author = $_POST['book_author'];
                $isbn = $_POST['book_isbn'];
                $publisher = $_POST['book_publisher'];
                $year = $_POST['book_year'];
                $quantity = $_POST['book_quantity'];
                $genre = $_POST['book_genre'];
                $available = $quantity;

                $stmt = $pdo->prepare("INSERT INTO material_books (title, author, isbn, publisher, year_published, quantity, available, status, genre) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $author, $isbn, $publisher, $year, $quantity, $available, $status, $genre]);
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Added new book: $title");
                break;

            case 'Digital Media':
                $title = $_POST['digital_title'];
                $author = $_POST['digital_author'];
                $isbn = $_POST['digital_isbn'];
                $publisher = $_POST['digital_publisher'];
                $year = $_POST['digital_year'];
                $media_type = $_POST['digital_type'];
                $tags = $_POST['digital_tags'];

                $stmt = $pdo->prepare("INSERT INTO material_digital_media (title, author, isbn, publisher, year_published, media_type, status, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $author, $isbn, $publisher, $year, $media_type, $status, $tags]);
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Added new digital media: $title");
                break;

            case 'Archival':
                $title = $_POST['archival_title'];
                $author = $_POST['archival_author'];
                $isbn = $_POST['archival_isbn'];
                $publisher = $_POST['archival_publisher'];
                $year = $_POST['archival_year'];
                $description = $_POST['archival_description'];
                $collection = $_POST['archival_collection'];

                $stmt = $pdo->prepare("INSERT INTO material_research (title, author, isbn, publisher, year_published, description, status, collection) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $author, $isbn, $publisher, $year, $description, $status, $collection]);
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

// Handle genre management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_genres'])) {
    $action = $_POST['genre_action'];
    $genreId = $_POST['genre_id'] ?? null;
    $genreName = $_POST['genre_name'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO book_genres (genre_name) VALUES (?)");
                $stmt->execute([$genreName]);
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Added new book genre: $genreName");
                $_SESSION['success'] = "Book genre '$genreName' added successfully";
                break;
                
            case 'update':
                if ($genreId) {
                    $stmt = $pdo->prepare("UPDATE book_genres SET genre_name = ? WHERE genre_id = ?");
                    $stmt->execute([$genreName, $genreId]);
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Updated book genre #$genreId to $genreName");
                    $_SESSION['success'] = "Book genre updated successfully";
                }
                break;
                
            case 'delete':
                if ($genreId) {
                    $stmt = $pdo->prepare("DELETE FROM book_genres WHERE genre_id = ?");
                    $stmt->execute([$genreId]);
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Deleted book genre #$genreId");
                    $_SESSION['success'] = "Book genre deleted successfully";
                }
                break;
        }
    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Failed to manage genre: " . $e->getMessage());
        $_SESSION['error'] = "Error managing genre: " . $e->getMessage();
    }
    
    header("Location: materials_management.php");
    exit;
}

// Handle tag management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_tags'])) {
    $action = $_POST['tag_action'];
    $tagId = $_POST['tag_id'] ?? null;
    $tagName = $_POST['tag_name'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO material_tags (tag_name) VALUES (?)");
                $stmt->execute([$tagName]);
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Added new tag: $tagName");
                $_SESSION['success'] = "Tag '$tagName' added successfully";
                break;
                
            case 'update':
                if ($tagId) {
                    $stmt = $pdo->prepare("UPDATE material_tags SET tag_name = ? WHERE tag_id = ?");
                    $stmt->execute([$tagName, $tagId]);
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Updated tag #$tagId to $tagName");
                    $_SESSION['success'] = "Tag updated successfully";
                }
                break;
                
            case 'delete':
                if ($tagId) {
                    $stmt = $pdo->prepare("DELETE FROM material_tags WHERE tag_id = ?");
                    $stmt->execute([$tagId]);
                    logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Deleted tag #$tagId");
                    $_SESSION['success'] = "Tag deleted successfully";
                }
                break;
        }
    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Failed to manage tag: " . $e->getMessage());
        $_SESSION['error'] = "Error managing tag: " . $e->getMessage();
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
                    // Fetch existing data
                    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
                    $stmt->execute([$materialId]);
                    $material = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($material) {
                        $_SESSION['edit_material'] = [
                            'id' => $materialId,
                            'table' => $table,
                            'data' => $material
                        ];
                    }
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

// Handle material update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_material'])) {
    $table = $_POST['material_table'];
    $materialId = $_POST['material_id'];
    
    try {
        switch ($table) {
            case 'material_books':
                $stmt = $pdo->prepare("UPDATE material_books SET 
                    title = ?, author = ?, isbn = ?, publisher = ?, 
                    year_published = ?, quantity = ?, available = ?, 
                    status = ?, genre = ? 
                    WHERE id = ?");
                $stmt->execute([
                    $_POST['book_title'], $_POST['book_author'], $_POST['book_isbn'], 
                    $_POST['book_publisher'], $_POST['book_year'], $_POST['book_quantity'],
                    $_POST['book_available'], $_POST['book_status'], $_POST['book_genre'],
                    $materialId
                ]);
                break;
                
            case 'material_digital_media':
                $stmt = $pdo->prepare("UPDATE material_digital_media SET 
                    title = ?, author = ?, isbn = ?, publisher = ?, 
                    year_published = ?, media_type = ?, status = ?, tags = ? 
                    WHERE id = ?");
                $stmt->execute([
                    $_POST['digital_title'], $_POST['digital_author'], $_POST['digital_isbn'], 
                    $_POST['digital_publisher'], $_POST['digital_year'], $_POST['digital_type'],
                    $_POST['digital_status'], $_POST['digital_tags'], $materialId
                ]);
                break;
                
            case 'material_research':
                $stmt = $pdo->prepare("UPDATE material_research SET 
                    title = ?, author = ?, isbn = ?, publisher = ?, 
                    year_published = ?, description = ?, status = ?, collection = ? 
                    WHERE id = ?");
                $stmt->execute([
                    $_POST['archival_title'], $_POST['archival_author'], $_POST['archival_isbn'], 
                    $_POST['archival_publisher'], $_POST['archival_year'], $_POST['archival_description'],
                    $_POST['archival_status'], $_POST['archival_collection'], $materialId
                ]);
                break;
        }
        
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Updated material in $table (ID: $materialId)");
        $_SESSION['success'] = "Material updated successfully";
        unset($_SESSION['edit_material']);
    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Failed to update material: " . $e->getMessage());
        $_SESSION['error'] = "Error updating material: " . $e->getMessage();
    }
    
    header("Location: materials_management.php");
    exit;
}

// Fetch data for display
$filter = $_GET['filter'] ?? 'books';
$books = $digitals = $research = [];
$genres = [];
$tags = [];

// Fetch genres
$stmtGenres = $pdo->query("SELECT * FROM book_genres");
$genres = $stmtGenres->fetchAll(PDO::FETCH_ASSOC);

// Fetch tags
$stmtTags = $pdo->query("SELECT * FROM material_tags");
$tags = $stmtTags->fetchAll(PDO::FETCH_ASSOC);

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
                <a class="navbar-brand" href="#">Library System - Materials Management</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>Materials Management</h2>
                <div>
                    <button class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#manageGenresModal">
                        <i class="fas fa-tags"></i> Manage Book Genres
                    </button>
                    <button class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#manageTagsModal">
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
                                        <th>Genre</th>
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
                                                <span class="badge bg-info">
                                                    <?= htmlspecialchars($book['genre']) ?>
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
                                        <th>Tags</th>
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
                                                <?php if (!empty($media['tags'])): ?>
                                                    <?php foreach (explode(',', $media['tags']) as $tag): ?>
                                                        <span class="badge bg-secondary me-1"><?= htmlspecialchars(trim($tag)) ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
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
                                        <th>Collection</th>
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
                                                <span class="badge bg-warning text-dark">
                                                    <?= htmlspecialchars($arch['collection']) ?>
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
                <button type="button" class="btn btn-outline-primary w-100 mb-2 py-3" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addBookModal">
                    <i class="fas fa-book fa-2x mb-2"></i><br>
                    Add Book
                </button>
                <button type="button" class="btn btn-outline-success w-100 mb-2 py-3" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addDigitalModal">
                    <i class="fas fa-compact-disc fa-2x mb-2"></i><br>
                    Add Digital Media
                </button>
                <button type="button" class="btn btn-outline-warning w-100 py-3" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addArchivalModal">
                    <i class="fas fa-archive fa-2x mb-2"></i><br>
                    Add Archival
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1" aria-labelledby="addBookModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add New Book</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="add_material" value="1">
                <input type="hidden" name="material_type" value="Book">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="book_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="book_title" name="book_title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="book_author" class="form-label">Author</label>
                            <input type="text" class="form-control" id="book_author" name="book_author" required>
                        </div>
                        <div class="col-md-6">
                            <label for="book_isbn" class="form-label">ISBN</label>
                            <input type="text" class="form-control" id="book_isbn" name="book_isbn" required>
                        </div>
                        <div class="col-md-6">
                            <label for="book_publisher" class="form-label">Publisher</label>
                            <input type="text" class="form-control" id="book_publisher" name="book_publisher" required>
                        </div>
                        <div class="col-md-4">
                            <label for="book_year" class="form-label">Year Published</label>
                            <input type="number" class="form-control" id="book_year" name="book_year" required>
                        </div>
                        <div class="col-md-4">
                            <label for="book_quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="book_quantity" name="book_quantity" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label for="book_genre" class="form-label">Genre</label>
                            <select class="form-select" id="book_genre" name="book_genre" required>
                                <?php foreach ($genres as $genre): ?>
                                    <option value="<?= htmlspecialchars($genre['genre_name']) ?>"><?= htmlspecialchars($genre['genre_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Book</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Digital Media Modal -->
<div class="modal fade" id="addDigitalModal" tabindex="-1" aria-labelledby="addDigitalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Add New Digital Media</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="add_material" value="1">
                <input type="hidden" name="material_type" value="Digital Media">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="digital_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="digital_title" name="digital_title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="digital_author" class="form-label">Author</label>
                            <input type="text" class="form-control" id="digital_author" name="digital_author" required>
                        </div>
                        <div class="col-md-6">
                            <label for="digital_isbn" class="form-label">ISBN</label>
                            <input type="text" class="form-control" id="digital_isbn" name="digital_isbn" required>
                        </div>
                        <div class="col-md-6">
                            <label for="digital_publisher" class="form-label">Publisher</label>
                            <input type="text" class="form-control" id="digital_publisher" name="digital_publisher" required>
                        </div>
                        <div class="col-md-4">
                            <label for="digital_year" class="form-label">Year Published</label>
                            <input type="number" class="form-control" id="digital_year" name="digital_year" required>
                        </div>
                        <div class="col-md-4">
                            <label for="digital_type" class="form-label">Media Type</label>
                            <select class="form-select" id="digital_type" name="digital_type" required>
                                <option value="eBook">eBook</option>
                                <option value="Audiobook">Audiobook</option>
                                <option value="Video">Video</option>
                                <option value="Podcast">Podcast</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="digital_tags" class="form-label">Tags</label>
                            <select class="form-select" id="digital_tags" name="digital_tags[]" multiple>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?= htmlspecialchars($tag['tag_name']) ?>"><?= htmlspecialchars($tag['tag_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Digital Media</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Archival Modal -->
<div class="modal fade" id="addArchivalModal" tabindex="-1" aria-labelledby="addArchivalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Add New Archival Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="add_material" value="1">
                <input type="hidden" name="material_type" value="Archival">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="archival_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="archival_title" name="archival_title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="archival_author" class="form-label">Author</label>
                            <input type="text" class="form-control" id="archival_author" name="archival_author" required>
                        </div>
                        <div class="col-md-6">
                            <label for="archival_isbn" class="form-label">ISBN</label>
                            <input type="text" class="form-control" id="archival_isbn" name="archival_isbn" required>
                        </div>
                        <div class="col-md-6">
                            <label for="archival_publisher" class="form-label">Publisher</label>
                            <input type="text" class="form-control" id="archival_publisher" name="archival_publisher" required>
                        </div>
                        <div class="col-md-4">
                            <label for="archival_year" class="form-label">Year Published</label>
                            <input type="number" class="form-control" id="archival_year" name="archival_year" required>
                        </div>
                        <div class="col-md-8">
                            <label for="archival_collection" class="form-label">Collection</label>
                            <input type="text" class="form-control" id="archival_collection" name="archival_collection" required>
                        </div>
                        <div class="col-12">
                            <label for="archival_description" class="form-label">Description</label>
                            <textarea class="form-control" id="archival_description" name="archival_description" rows="3" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Add Archival</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Material Modal -->
<?php if (isset($_SESSION['edit_material'])): ?>
    <?php $material = $_SESSION['edit_material']['data']; ?>
    <div class="modal fade show" id="editMaterialModal" tabindex="-1" aria-labelledby="editMaterialModalLabel" aria-hidden="false" style="display: block; padding-right: 17px;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Edit Material</h5>
                    <a href="materials_management.php" class="btn-close" aria-label="Close"></a>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="update_material" value="1">
                        <input type="hidden" name="material_id" value="<?= $material['id'] ?>">
                        <input type="hidden" name="material_table" value="<?= $_SESSION['edit_material']['table'] ?>">
                        
                        <?php if ($_SESSION['edit_material']['table'] === 'material_books'): ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="book_title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="book_title" name="book_title" value="<?= htmlspecialchars($material['title']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="book_author" class="form-label">Author</label>
                                    <input type="text" class="form-control" id="book_author" name="book_author" value="<?= htmlspecialchars($material['author']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="book_isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="book_isbn" name="book_isbn" value="<?= htmlspecialchars($material['isbn']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="book_publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="book_publisher" name="book_publisher" value="<?= htmlspecialchars($material['publisher']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="book_year" class="form-label">Year Published</label>
                                    <input type="number" class="form-control" id="book_year" name="book_year" value="<?= htmlspecialchars($material['year_published']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="book_quantity" class="form-label">Quantity</label>
                                    <input type="number" class="form-control" id="book_quantity" name="book_quantity" min="1" value="<?= htmlspecialchars($material['quantity']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="book_available" class="form-label">Available</label>
                                    <input type="number" class="form-control" id="book_available" name="book_available" min="0" value="<?= htmlspecialchars($material['available']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="book_status" class="form-label">Status</label>
                                    <select class="form-select" id="book_status" name="book_status" required>
                                        <option value="Available" <?= $material['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                                        <option value="Unavailable" <?= $material['status'] === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="book_genre" class="form-label">Genre</label>
                                    <select class="form-select" id="book_genre" name="book_genre" required>
                                        <?php foreach ($genres as $genre): ?>
                                            <option value="<?= htmlspecialchars($genre['genre_name']) ?>" <?= $material['genre'] === $genre['genre_name'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($genre['genre_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php elseif ($_SESSION['edit_material']['table'] === 'material_digital_media'): ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="digital_title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="digital_title" name="digital_title" value="<?= htmlspecialchars($material['title']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="digital_author" class="form-label">Author</label>
                                    <input type="text" class="form-control" id="digital_author" name="digital_author" value="<?= htmlspecialchars($material['author']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="digital_isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="digital_isbn" name="digital_isbn" value="<?= htmlspecialchars($material['isbn']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="digital_publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="digital_publisher" name="digital_publisher" value="<?= htmlspecialchars($material['publisher']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="digital_year" class="form-label">Year Published</label>
                                    <input type="number" class="form-control" id="digital_year" name="digital_year" value="<?= htmlspecialchars($material['year_published']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="digital_type" class="form-label">Media Type</label>
                                    <select class="form-select" id="digital_type" name="digital_type" required>
                                        <option value="eBook" <?= $material['media_type'] === 'eBook' ? 'selected' : '' ?>>eBook</option>
                                        <option value="Audiobook" <?= $material['media_type'] === 'Audiobook' ? 'selected' : '' ?>>Audiobook</option>
                                        <option value="Video" <?= $material['media_type'] === 'Video' ? 'selected' : '' ?>>Video</option>
                                        <option value="Podcast" <?= $material['media_type'] === 'Podcast' ? 'selected' : '' ?>>Podcast</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="digital_status" class="form-label">Status</label>
                                    <select class="form-select" id="digital_status" name="digital_status" required>
                                        <option value="Available" <?= $material['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                                        <option value="Unavailable" <?= $material['status'] === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="digital_tags" class="form-label">Tags</label>
                                    <select class="form-select" id="digital_tags" name="digital_tags[]" multiple>
                                        <?php 
                                        $selectedTags = !empty($material['tags']) ? explode(',', $material['tags']) : [];
                                        foreach ($tags as $tag): 
                                            $isSelected = in_array(trim($tag['tag_name']), array_map('trim', $selectedTags));
                                        ?>
                                            <option value="<?= htmlspecialchars($tag['tag_name']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tag['tag_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php elseif ($_SESSION['edit_material']['table'] === 'material_research'): ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="archival_title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="archival_title" name="archival_title" value="<?= htmlspecialchars($material['title']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="archival_author" class="form-label">Author</label>
                                    <input type="text" class="form-control" id="archival_author" name="archival_author" value="<?= htmlspecialchars($material['author']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="archival_isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="archival_isbn" name="archival_isbn" value="<?= htmlspecialchars($material['isbn']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="archival_publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="archival_publisher" name="archival_publisher" value="<?= htmlspecialchars($material['publisher']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="archival_year" class="form-label">Year Published</label>
                                    <input type="number" class="form-control" id="archival_year" name="archival_year" value="<?= htmlspecialchars($material['year_published']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="archival_status" class="form-label">Status</label>
                                    <select class="form-select" id="archival_status" name="archival_status" required>
                                        <option value="Available" <?= $material['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                                        <option value="Unavailable" <?= $material['status'] === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="archival_collection" class="form-label">Collection</label>
                                    <input type="text" class="form-control" id="archival_collection" name="archival_collection" value="<?= htmlspecialchars($material['collection']) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label for="archival_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="archival_description" name="archival_description" rows="3" required><?= htmlspecialchars($material['description']) ?></textarea>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="modal-footer mt-3">
                            <a href="materials_management.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Manage Genres Modal -->
<div class="modal fade" id="manageGenresModal" tabindex="-1" aria-labelledby="manageGenresModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Manage Book Genres</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="manage_genres" value="1">
                    <div class="mb-3">
                        <label for="genre_action" class="form-label">Action</label>
                        <select class="form-select" id="genre_action" name="genre_action" required>
                            <option value="add">Add New Genre</option>
                            <option value="update">Update Existing Genre</option>
                            <option value="delete">Delete Genre</option>
                        </select>
                    </div>
                    <div class="mb-3" id="genre_id_container">
                        <label for="genre_id" class="form-label">Select Genre</label>
                        <select class="form-select" id="genre_id" name="genre_id">
                            <?php foreach ($genres as $genre): ?>
                                <option value="<?= $genre['genre_id'] ?>"><?= htmlspecialchars($genre['genre_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="genre_name_container">
                        <label for="genre_name" class="form-label">Genre Name</label>
                        <input type="text" class="form-control" id="genre_name" name="genre_name" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Manage Tags Modal -->
<div class="modal fade" id="manageTagsModal" tabindex="-1" aria-labelledby="manageTagsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Manage Tags</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="manage_tags" value="1">
                    <div class="mb-3">
                        <label for="tag_action" class="form-label">Action</label>
                        <select class="form-select" id="tag_action" name="tag_action" required>
                            <option value="add">Add New Tag</option>
                            <option value="update">Update Existing Tag</option>
                            <option value="delete">Delete Tag</option>
                        </select>
                    </div>
                    <div class="mb-3" id="tag_id_container">
                        <label for="tag_id" class="form-label">Select Tag</label>
                        <select class="form-select" id="tag_id" name="tag_id">
                            <?php foreach ($tags as $tag): ?>
                                <option value="<?= $tag['tag_id'] ?>"><?= htmlspecialchars($tag['tag_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="tag_name_container">
                        <label for="tag_name" class="form-label">Tag Name</label>
                        <input type="text" class="form-control" id="tag_name" name="tag_name" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // =============================================
    // Enhanced Tab Switching and Form Handling
    // =============================================
    const materialTabs = document.querySelectorAll('#materialTabs .nav-link');
    const mainMaterialTypeInput = document.querySelector('#addMaterialModal input[name="material_type"]');
    const addMaterialForm = document.querySelector('#addMaterialModal form');
    
    // Function to handle tab switching
    function handleTabSwitch(tab) {
        const targetId = tab.getAttribute('data-bs-target');
        const activeTab = document.querySelector(targetId);
        
        // Remove active class from all tabs and add to current
        materialTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Remove show/active classes from all tab panes and add to current
        document.querySelectorAll('#materialTabContent .tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });
        activeTab.classList.add('show', 'active');
        
        // Update the main material type input with the value from the active tab
        const tabMaterialTypeInput = activeTab.querySelector('input[name="material_type"]');
        if (tabMaterialTypeInput) {
            mainMaterialTypeInput.value = tabMaterialTypeInput.value;
        }
        
        // Handle required fields
        updateRequiredFields(activeTab);
    }

    // Function to update required fields for active tab
    function updateRequiredFields(activeTab) {
        // Remove required attributes from all fields in all tabs
        document.querySelectorAll('#materialTabContent [required]').forEach(field => {
            field.removeAttribute('required');
            field.removeAttribute('aria-required');
        });
        
        // Add required attributes only to active tab's fields
        activeTab.querySelectorAll('input, select, textarea').forEach(field => {
            if (field.hasAttribute('data-required') || field.classList.contains('required-field')) {
                field.setAttribute('required', '');
                field.setAttribute('aria-required', 'true');
            }
        });
    }

    // Initialize tab handling
    materialTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            handleTabSwitch(this);
        });
    });

    // Initialize with the default tab (Book)
    const defaultTab = document.querySelector('#materialTabs .nav-link.active');
    if (defaultTab) {
        handleTabSwitch(defaultTab);
    }

    // =============================================
    // Form Submission Handling
    // =============================================
    if (addMaterialForm) {
        addMaterialForm.addEventListener('submit', function(e) {
            const activeTab = document.querySelector('#materialTabContent .tab-pane.active');
            if (!activeTab) return;
            
            // Update material type before submission
            const tabMaterialTypeInput = activeTab.querySelector('input[name="material_type"]');
            if (tabMaterialTypeInput) {
                mainMaterialTypeInput.value = tabMaterialTypeInput.value;
            }
            
            const inputs = activeTab.querySelectorAll('[required]');
            let isValid = true;
            
            // Validate all required fields
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                    
                    // Add error message if not already present
                    if (!input.nextElementSibling || !input.nextElementSibling.classList.contains('invalid-feedback')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'This field is required';
                        input.parentNode.appendChild(errorDiv);
                    }
                } else {
                    input.classList.remove('is-invalid');
                    const errorDiv = input.nextElementSibling;
                    if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                        errorDiv.remove();
                    }
                }
            });
            
            // Validate digital tags (if on digital media tab)
            if (activeTab.id === 'digital-form') {
                const tagsSelect = activeTab.querySelector('#digital_tags');
                if (tagsSelect && tagsSelect.selectedOptions.length === 0) {
                    tagsSelect.classList.add('is-invalid');
                    isValid = false;
                    
                    if (!tagsSelect.nextElementSibling || !tagsSelect.nextElementSibling.classList.contains('invalid-feedback')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'Please select at least one tag';
                        tagsSelect.parentNode.appendChild(errorDiv);
                    }
                } else if (tagsSelect) {
                    tagsSelect.classList.remove('is-invalid');
                    const errorDiv = tagsSelect.nextElementSibling;
                    if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                        errorDiv.remove();
                    }
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                const firstInvalid = activeTab.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            }
        });
    }

    // =============================================
    // Genre Management Form Handling
    // =============================================
    const genreAction = document.getElementById('genre_action');
    const genreIdContainer = document.getElementById('genre_id_container');
    const genreNameContainer = document.getElementById('genre_name_container');
    
    function updateGenreForm() {
        if (genreAction.value === 'add') {
            genreIdContainer.style.display = 'none';
            genreNameContainer.style.display = 'block';
            document.getElementById('genre_name').required = true;
            document.getElementById('genre_id').required = false;
        } else if (genreAction.value === 'update') {
            genreIdContainer.style.display = 'block';
            genreNameContainer.style.display = 'block';
            document.getElementById('genre_name').required = true;
            document.getElementById('genre_id').required = true;
        } else if (genreAction.value === 'delete') {
            genreIdContainer.style.display = 'block';
            genreNameContainer.style.display = 'none';
            document.getElementById('genre_name').required = false;
            document.getElementById('genre_id').required = true;
        }
    }
    
    if (genreAction) {
        genreAction.addEventListener('change', updateGenreForm);
        updateGenreForm();
    }

    // =============================================
    // Tag Management Form Handling
    // =============================================
    const tagAction = document.getElementById('tag_action');
    const tagIdContainer = document.getElementById('tag_id_container');
    const tagNameContainer = document.getElementById('tag_name_container');
    
    function updateTagForm() {
        if (tagAction.value === 'add') {
            tagIdContainer.style.display = 'none';
            tagNameContainer.style.display = 'block';
            document.getElementById('tag_name').required = true;
            document.getElementById('tag_id').required = false;
        } else if (tagAction.value === 'update') {
            tagIdContainer.style.display = 'block';
            tagNameContainer.style.display = 'block';
            document.getElementById('tag_name').required = true;
            document.getElementById('tag_id').required = true;
        } else if (tagAction.value === 'delete') {
            tagIdContainer.style.display = 'block';
            tagNameContainer.style.display = 'none';
            document.getElementById('tag_name').required = false;
            document.getElementById('tag_id').required = true;
        }
    }
    
    if (tagAction) {
        tagAction.addEventListener('change', updateTagForm);
        updateTagForm();
    }

    // =============================================
    // Edit Material Modal Handling
    // =============================================
    <?php if (isset($_SESSION['edit_material'])): ?>
        var editModal = new bootstrap.Modal(document.getElementById('editMaterialModal'));
        editModal.show();
        
        document.getElementById('editMaterialModal').addEventListener('hidden.bs.modal', function () {
            window.location.href = 'materials_management.php';
        });
    <?php endif; ?>

    // Initialize Select2 for multiple tag selection (if using Select2)
    if ($ && $.fn.select2) {
        $('#digital_tags').select2({
            placeholder: "Select tags",
            allowClear: true
        });
    }
});
</script>
</body>
</html>