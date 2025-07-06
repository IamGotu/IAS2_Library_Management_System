<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../login.php");
    exit;
}

// Function to get events from database
function getEvents($pdo, $filter = 'all') {
    $query = "SELECT * FROM events";
    $params = [];
    
    if ($filter !== 'all') {
        $query .= " WHERE status = ?";
        $params[] = $filter;
    }
    
    $query .= " ORDER BY start_datetime ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get events from database
$filter = $_GET['filter'] ?? 'all';
$events = getEvents($pdo, $filter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .event-card { transition: transform 0.2s; }
        .event-card:hover { transform: translateY(-5px); }
        .upcoming { border-left: 4px solid #0d6efd; }
        .ongoing { border-left: 4px solid #198754; }
        .completed { border-left: 4px solid #6c757d; }
        .cancelled { border-left: 4px solid #dc3545; }
        .event-type-badge { font-size: 0.8em; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">Library Events</a>
            </div>
        </nav>

        <div class="container mt-4">
            <h2 class="mb-4">Library Events Calendar</h2>

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

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="btn-group" role="group">
                        <a href="?filter=all" class="btn btn-outline-dark <?= $filter === 'all' ? 'active' : '' ?>">All Events</a>
                        <a href="?filter=Upcoming" class="btn btn-outline-primary <?= $filter === 'Upcoming' ? 'active' : '' ?>">Upcoming</a>
                        <a href="?filter=Ongoing" class="btn btn-outline-success <?= $filter === 'Ongoing' ? 'active' : '' ?>">Ongoing</a>
                        <a href="?filter=Completed" class="btn btn-outline-secondary <?= $filter === 'Completed' ? 'active' : '' ?>">Past Events</a>
                    </div>
                </div>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($events as $event): 
                    $statusClass = strtolower($event['status']);
                    $isPastEvent = strtotime($event['end_datetime']) < time();
                ?>
                    <div class="col">
                        <div class="card h-100 event-card <?= $statusClass ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><?= htmlspecialchars($event['title']) ?></h5>
                                <span class="badge bg-<?= 
                                    $statusClass === 'upcoming' ? 'primary' : 
                                    ($statusClass === 'ongoing' ? 'success' : 
                                    ($statusClass === 'completed' ? 'secondary' : 'danger'))
                                ?>"><?= $event['status'] ?></span>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted"><i class="fas fa-calendar-day"></i> <?= date('M j, Y', strtotime($event['start_datetime'])) ?></span>
                                    <span class="text-muted"><i class="fas fa-clock"></i> <?= date('g:i a', strtotime($event['start_datetime'])) ?> - <?= date('g:i a', strtotime($event['end_datetime'])) ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="badge event-type-badge bg-info text-dark"><?= $event['event_type'] ?></span>
                                    <span class="badge event-type-badge bg-light text-dark"><i class="fas fa-map-marker-alt"></i> <?= $event['location'] ?></span>
                                </div>
                                
                                <p class="card-text"><?= $event['description'] ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Organizer: <?= $event['organizer'] ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($events)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            No events found matching your criteria.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>