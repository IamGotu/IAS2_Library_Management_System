<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id'])) {
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

// Generate random books for review
function generateRandomBooks($count = 10) {
    $books = [];
    $titles = [
        'The Great Gatsby', 'To Kill a Mockingbird', '1984', 
        'Pride and Prejudice', 'The Hobbit', 'Animal Farm',
        'Brave New World', 'The Catcher in the Rye', 'Moby Dick',
        'War and Peace', 'Crime and Punishment', 'The Odyssey'
    ];
    $authors = [
        'F. Scott Fitzgerald', 'Harper Lee', 'George Orwell',
        'Jane Austen', 'J.R.R. Tolkien', 'George Orwell',
        'Aldous Huxley', 'J.D. Salinger', 'Herman Melville',
        'Leo Tolstoy', 'Fyodor Dostoevsky', 'Homer'
    ];
    
    for ($i = 0; $i < $count; $i++) {
        $books[] = [
            'book_id' => rand(1000, 9999),
            'title' => $titles[$i % count($titles)],
            'author' => $authors[$i % count($authors)],
            'average_rating' => number_format(rand(30, 50) / 10, 1), // 3.0 to 5.0
            'review_count' => rand(5, 50)
        ];
    }
    
    return $books;
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $bookId = $_POST['book_id'] ?? 0;
    $bookTitle = $_POST['book_title'] ?? '';
    $rating = $_POST['rating'] ?? 0;
    $reviewText = $_POST['review_text'] ?? '';
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_role'];
    
    try {
        // Validate input
        if ($rating < 1 || $rating > 5) {
            throw new Exception("Invalid rating value");
        }
        if (empty(trim($reviewText))) {
            throw new Exception("Review text cannot be empty");
        }
        
        // In a real system, you would insert into a reviews table
        // For simulation, we'll just log the action
        $actionDesc = "Submitted review for '$bookTitle' (Rating: $rating/5)";
        logActivity($pdo, $userId, $userRole, $actionDesc);
        
        $_SESSION['success'] = "Thank you for your review of '$bookTitle'!";
        header("Location: rate.php");
        exit;
        
    } catch (Exception $e) {
        logActivity($pdo, $userId, $userRole, "Failed to submit review: " . $e->getMessage());
        $_SESSION['error'] = "Error submitting review: " . $e->getMessage();
        header("Location: rate.php");
        exit;
    }
}

$books = generateRandomBooks();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Reviews and Ratings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rating-stars { color: #ffc107; }
        .book-card { transition: transform 0.2s; }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .review-form textarea { min-height: 120px; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library System - Book Reviews</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-4">
                <h2>Book Reviews and Ratings</h2>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#reviewGuidelinesModal">
                        <i class="fas fa-info-circle"></i> Review Guidelines
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

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($books as $book): ?>
                    <div class="col">
                        <div class="card h-100 book-card">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0"><?= htmlspecialchars($book['title']) ?></h5>
                            </div>
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($book['author']) ?></h6>
                                
                                <div class="mb-3">
                                    <div class="rating-stars mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i <= $book['average_rating'] ? '' : '-half-alt' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ms-2"><?= $book['average_rating'] ?> (<?= $book['review_count'] ?> reviews)</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" role="progressbar" 
                                             style="width: <?= ($book['average_rating'] / 5) * 100 ?>%" 
                                             aria-valuenow="<?= $book['average_rating'] ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="5"></div>
                                    </div>
                                </div>
                                
                                <p class="card-text text-muted">
                                    "This book has received positive feedback from our readers."
                                </p>
                            </div>
                            <div class="card-footer bg-white">
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal"
                                    data-book-id="<?= $book['book_id'] ?>"
                                    data-book-title="<?= htmlspecialchars($book['title']) ?>">
                                    <i class="fas fa-edit"></i> Write a Review
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">Submit Book Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" class="review-form">
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" name="book_id" id="review_book_id">
                <input type="hidden" name="book_title" id="review_book_title">
                
                <div class="modal-body">
                    <div class="mb-4">
                        <h6>You're reviewing: <span id="display_book_title" class="fw-bold"></span></h6>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Your Rating</label>
                        <div class="rating-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" class="visually-hidden" required>
                                <label for="star<?= $i ?>" class="rating-star"><i class="far fa-star"></i></label>
                            <?php endfor; ?>
                            <span id="rating-value" class="ms-2">0</span>/5
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="review_text" class="form-label">Your Review</label>
                        <textarea class="form-control" id="review_text" name="review_text" required 
                                  placeholder="Share your thoughts about this book..."></textarea>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="agree_terms" required>
                        <label class="form-check-label" for="agree_terms">
                            I confirm this is my honest opinion about this book
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Review Guidelines Modal -->
<div class="modal fade" id="reviewGuidelinesModal" tabindex="-1" aria-labelledby="reviewGuidelinesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="reviewGuidelinesModalLabel">Review Guidelines</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6><i class="fas fa-check-circle text-success"></i> Do:</h6>
                <ul>
                    <li>Share your honest opinion about the book</li>
                    <li>Focus on the content and your personal experience</li>
                    <li>Keep reviews respectful and constructive</li>
                    <li>Mention what you liked or didn't like</li>
                </ul>
                
                <h6 class="mt-4"><i class="fas fa-times-circle text-danger"></i> Don't:</h6>
                <ul>
                    <li>Include spoilers without warning</li>
                    <li>Use offensive language or personal attacks</li>
                    <li>Submit reviews for books you haven't read</li>
                    <li>Include promotional content or links</li>
                </ul>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize review modal with book data
document.getElementById('reviewModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const bookId = button.getAttribute('data-book-id');
    const bookTitle = button.getAttribute('data-book-title');
    
    const modal = this;
    modal.querySelector('#review_book_id').value = bookId;
    modal.querySelector('#review_book_title').value = bookTitle;
    modal.querySelector('#display_book_title').textContent = bookTitle;
    
    // Reset form
    modal.querySelector('form').reset();
    modal.querySelectorAll('.rating-star i').forEach(star => {
        star.classList.remove('fas');
        star.classList.add('far');
    });
    modal.querySelector('#rating-value').textContent = '0';
});

// Star rating interaction
document.querySelectorAll('.rating-star').forEach(star => {
    star.addEventListener('click', function() {
        const stars = this.parentElement.querySelectorAll('.rating-star');
        const rating = this.htmlFor.replace('star', '');
        
        stars.forEach((s, index) => {
            const icon = s.querySelector('i');
            icon.classList.toggle('fas', index < rating);
            icon.classList.toggle('far', index >= rating);
        });
        
        document.getElementById('rating-value').textContent = rating;
    });
});

// Star rating hover effect
document.querySelectorAll('.rating-star').forEach(star => {
    star.addEventListener('mouseover', function() {
        const stars = this.parentElement.querySelectorAll('.rating-star');
        const rating = this.htmlFor.replace('star', '');
        
        stars.forEach((s, index) => {
            const icon = s.querySelector('i');
            if (index < rating) {
                icon.classList.add('fas');
                icon.classList.remove('far');
            }
        });
    });
    
    star.addEventListener('mouseout', function() {
        const stars = this.parentElement.querySelectorAll('.rating-star');
        const currentRating = document.querySelector('input[name="rating"]:checked')?.value || 0;
        
        stars.forEach((s, index) => {
            const icon = s.querySelector('i');
            if (index >= currentRating) {
                icon.classList.remove('fas');
                icon.classList.add('far');
            }
        });
    });
});
</script>
</body>
</html>