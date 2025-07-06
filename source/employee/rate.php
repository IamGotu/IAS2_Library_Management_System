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

// Function to get average ratings for books
function getBookRatings($pdo) {
    $stmt = $pdo->query("
        SELECT b.id AS book_id, b.title, b.author, 
               COALESCE(AVG(r.rating), 0) AS average_rating,
               COUNT(r.review_id) AS review_count
        FROM material_books b
        LEFT JOIN book_reviews r ON b.id = r.book_id
        GROUP BY b.id
        ORDER BY b.title
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get average ratings for digital media
function getDigitalMediaRatings($pdo) {
    $stmt = $pdo->query("
        SELECT d.id AS media_id, d.title, d.author, 
               COALESCE(AVG(r.rating), 0) AS average_rating,
               COUNT(r.review_id) AS review_count
        FROM material_digital_media d
        LEFT JOIN digital_media_reviews r ON d.id = r.media_id
        GROUP BY d.id
        ORDER BY d.title
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get average ratings for research materials
function getResearchMaterialRatings($pdo) {
    $stmt = $pdo->query("
        SELECT r.id AS research_id, r.title, r.author, 
               COALESCE(AVG(rev.rating), 0) AS average_rating,
               COUNT(rev.review_id) AS review_count
        FROM material_research r
        LEFT JOIN research_material_reviews rev ON r.id = rev.research_id
        GROUP BY r.id
        ORDER BY r.title
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $materialType = $_POST['material_type'] ?? '';
    $materialId = $_POST['material_id'] ?? 0;
    $materialTitle = $_POST['material_title'] ?? '';
    $rating = $_POST['rating'] ?? 0;
    $reviewText = $_POST['review_text'] ?? '';
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_role'];
    
    try {
        // Validate input
        if ($rating < 1 || $rating > 5) {
            throw new Exception("Invalid rating value");
        }
        
        // Check if material exists
        $materialExists = false;
        $tableName = '';
        
        switch ($materialType) {
            case 'book':
                $stmt = $pdo->prepare("SELECT id FROM material_books WHERE id = ?");
                $tableName = 'book_reviews';
                break;
            case 'digital':
                $stmt = $pdo->prepare("SELECT id FROM material_digital_media WHERE id = ?");
                $tableName = 'digital_media_reviews';
                break;
            case 'research':
                $stmt = $pdo->prepare("SELECT id FROM material_research WHERE id = ?");
                $tableName = 'research_material_reviews';
                break;
            default:
                throw new Exception("Invalid material type");
        }
        
        $stmt->execute([$materialId]);
        $materialExists = $stmt->fetch();
        
        if (!$materialExists) {
            throw new Exception("Material not found");
        }
        
        // Insert the review
        $stmt = $pdo->prepare("
            INSERT INTO $tableName 
            (".($materialType === 'book' ? 'book_id' : ($materialType === 'digital' ? 'media_id' : 'research_id')).", 
             user_id, user_role, rating, review_text)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $materialId,
            $userId,
            $userRole,
            $rating,
            $reviewText
        ]);
        
        $actionDesc = "Submitted review for $materialType '$materialTitle' (Rating: $rating/5)";
        logActivity($pdo, $userId, $userRole, $actionDesc);
        
        $_SESSION['success'] = "Thank you for your review of '$materialTitle'!";
        header("Location: rate.php");
        exit;
        
    } catch (Exception $e) {
        logActivity($pdo, $userId, $userRole, "Failed to submit review: " . $e->getMessage());
        $_SESSION['error'] = "Error submitting review: " . $e->getMessage();
        header("Location: rate.php");
        exit;
    }
}

// Get all materials with their average ratings
$books = getBookRatings($pdo);
$digitalMedia = getDigitalMediaRatings($pdo);
$researchMaterials = getResearchMaterialRatings($pdo);

// Function to format rating for display
function formatRating($rating) {
    return number_format($rating, 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Material Reviews and Ratings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rating-stars { color: #ffc107; }
        .material-card { transition: transform 0.2s; }
        .material-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .review-form textarea { min-height: 120px; }
        .nav-pills .nav-link.active { background-color: #0d6efd; }
        .tab-content { padding: 20px 0; }
        .material-type-badge {
            font-size: 0.7rem;
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library System - Material Reviews</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-4">
                <h2>Material Reviews and Ratings</h2>
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

            <ul class="nav nav-pills mb-3" id="materialTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="books-tab" data-bs-toggle="pill" data-bs-target="#books" type="button" role="tab">
                        Books (<?= count($books) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="digital-tab" data-bs-toggle="pill" data-bs-target="#digital" type="button" role="tab">
                        Digital Media (<?= count($digitalMedia) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="research-tab" data-bs-toggle="pill" data-bs-target="#research" type="button" role="tab">
                        Research Materials (<?= count($researchMaterials) ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="materialTabsContent">
                <!-- Books Tab -->
                <div class="tab-pane fade show active" id="books" role="tabpanel">
                    <?php if (empty($books)): ?>
                        <div class="alert alert-info">No books available for review.</div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($books as $book): ?>
                                <div class="col">
                                    <div class="card h-100 material-card">
                                        <div class="card-header bg-light position-relative">
                                            <h5 class="card-title mb-0"><?= htmlspecialchars($book['title']) ?></h5>
                                            <span class="badge bg-primary material-type-badge">Book</span>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($book['author']) ?></h6>
                                            
                                            <div class="mb-3">
                                                <div class="rating-stars mb-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i <= $book['average_rating'] ? '' : '-half-alt' ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-2"><?= formatRating($book['average_rating']) ?> (<?= $book['review_count'] ?> reviews)</span>
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
                                                "This book has received feedback from our readers."
                                            </p>
                                        </div>
                                        <div class="card-footer bg-white">
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal"
                                                data-material-type="book"
                                                data-material-id="<?= $book['book_id'] ?>"
                                                data-material-title="<?= htmlspecialchars($book['title']) ?>">
                                                <i class="fas fa-edit"></i> Write a Review
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Digital Media Tab -->
                <div class="tab-pane fade" id="digital" role="tabpanel">
                    <?php if (empty($digitalMedia)): ?>
                        <div class="alert alert-info">No digital media available for review.</div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($digitalMedia as $media): ?>
                                <div class="col">
                                    <div class="card h-100 material-card">
                                        <div class="card-header bg-light position-relative">
                                            <h5 class="card-title mb-0"><?= htmlspecialchars($media['title']) ?></h5>
                                            <span class="badge bg-success material-type-badge">Digital</span>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($media['author']) ?></h6>
                                            
                                            <div class="mb-3">
                                                <div class="rating-stars mb-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i <= $media['average_rating'] ? '' : '-half-alt' ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-2"><?= formatRating($media['average_rating']) ?> (<?= $media['review_count'] ?> reviews)</span>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar bg-warning" role="progressbar" 
                                                         style="width: <?= ($media['average_rating'] / 5) * 100 ?>%" 
                                                         aria-valuenow="<?= $media['average_rating'] ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="5"></div>
                                                </div>
                                            </div>
                                            
                                            <p class="card-text text-muted">
                                                "This digital media has received feedback from our users."
                                            </p>
                                        </div>
                                        <div class="card-footer bg-white">
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal"
                                                data-material-type="digital"
                                                data-material-id="<?= $media['media_id'] ?>"
                                                data-material-title="<?= htmlspecialchars($media['title']) ?>">
                                                <i class="fas fa-edit"></i> Write a Review
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Research Materials Tab -->
                <div class="tab-pane fade" id="research" role="tabpanel">
                    <?php if (empty($researchMaterials)): ?>
                        <div class="alert alert-info">No research materials available for review.</div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($researchMaterials as $research): ?>
                                <div class="col">
                                    <div class="card h-100 material-card">
                                        <div class="card-header bg-light position-relative">
                                            <h5 class="card-title mb-0"><?= htmlspecialchars($research['title']) ?></h5>
                                            <span class="badge bg-info material-type-badge">Research</span>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($research['author']) ?></h6>
                                            
                                            <div class="mb-3">
                                                <div class="rating-stars mb-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i <= $research['average_rating'] ? '' : '-half-alt' ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-2"><?= formatRating($research['average_rating']) ?> (<?= $research['review_count'] ?> reviews)</span>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar bg-warning" role="progressbar" 
                                                         style="width: <?= ($research['average_rating'] / 5) * 100 ?>%" 
                                                         aria-valuenow="<?= $research['average_rating'] ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="5"></div>
                                                </div>
                                            </div>
                                            
                                            <p class="card-text text-muted">
                                                "This research material has received feedback from our users."
                                            </p>
                                        </div>
                                        <div class="card-footer bg-white">
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal"
                                                data-material-type="research"
                                                data-material-id="<?= $research['research_id'] ?>"
                                                data-material-title="<?= htmlspecialchars($research['title']) ?>">
                                                <i class="fas fa-edit"></i> Write a Review
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">Submit Material Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" class="review-form">
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" name="material_type" id="review_material_type">
                <input type="hidden" name="material_id" id="review_material_id">
                <input type="hidden" name="material_title" id="review_material_title">
                
                <div class="modal-body">
                    <div class="mb-4">
                        <h6>You're reviewing: <span id="display_material_title" class="fw-bold"></span></h6>
                        <span id="display_material_type" class="badge"></span>
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
                        <textarea class="form-control" id="review_text" name="review_text" 
                                  placeholder="Share your thoughts about this material..."></textarea>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="agree_terms" required>
                        <label class="form-check-label" for="agree_terms">
                            I confirm this is my honest opinion about this material
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
                    <li>Share your honest opinion about the material</li>
                    <li>Focus on the content and your personal experience</li>
                    <li>Keep reviews respectful and constructive</li>
                    <li>Mention what you liked or didn't like</li>
                </ul>
                
                <h6 class="mt-4"><i class="fas fa-times-circle text-danger"></i> Don't:</h6>
                <ul>
                    <li>Include spoilers without warning</li>
                    <li>Use offensive language or personal attacks</li>
                    <li>Submit reviews for materials you haven't used</li>
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
// Initialize review modal with material data
document.getElementById('reviewModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const materialType = button.getAttribute('data-material-type');
    const materialId = button.getAttribute('data-material-id');
    const materialTitle = button.getAttribute('data-material-title');
    
    const modal = this;
    modal.querySelector('#review_material_type').value = materialType;
    modal.querySelector('#review_material_id').value = materialId;
    modal.querySelector('#review_material_title').value = materialTitle;
    modal.querySelector('#display_material_title').textContent = materialTitle;
    
    // Set material type badge
    const typeBadge = modal.querySelector('#display_material_type');
    typeBadge.textContent = materialType.charAt(0).toUpperCase() + materialType.slice(1);
    typeBadge.className = 'badge ' + 
        (materialType === 'book' ? 'bg-primary' : 
         materialType === 'digital' ? 'bg-success' : 'bg-info');
    
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