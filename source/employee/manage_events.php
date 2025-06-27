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

// Generate random events data
function generateRandomEvents($count = 10) {
    $events = [];
    $eventTypes = ['Book Reading', 'Workshop', 'Lecture', 'Book Club', 'Children\'s Hour', 'Author Visit'];
    $locations = ['Main Hall', 'Conference Room', 'Children\'s Area', 'Outdoor Garden', 'Auditorium'];
    $statuses = ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'];
    
    for ($i = 0; $i < $count; $i++) {
        $startDate = date('Y-m-d H:i:s', strtotime('+'.rand(0, 30).' days '.rand(9, 18).':'.rand(0, 59).':00'));
        $endDate = date('Y-m-d H:i:s', strtotime($startDate.' + '.rand(1, 3).' hours'));
        $registrationCount = rand(0, 50);
        $capacity = rand(20, 100);
        
        $events[] = [
            'event_id' => rand(1000, 9999),
            'title' => $eventTypes[array_rand($eventTypes)] . ' ' . ['Session', 'Event', 'Program', 'Meeting'][rand(0, 3)] . ' ' . rand(1, 10),
            'description' => 'Join us for this special '.$eventTypes[array_rand($eventTypes)].' featuring guest speakers and activities.',
            'event_type' => $eventTypes[array_rand($eventTypes)],
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'location' => $locations[array_rand($locations)],
            'max_capacity' => $capacity,
            'registered_count' => min($registrationCount, $capacity),
            'status' => $statuses[array_rand($statuses)],
            'organizer' => ['Librarian', 'Guest Speaker', 'Community Partner'][rand(0, 2)]
        ];
    }
    
    // Sort by start date
    usort($events, function($a, $b) {
        return strtotime($a['start_datetime']) - strtotime($b['start_datetime']);
    });
    
    return $events;
}

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $eventId = $_POST['event_id'] ?? 0;
    $eventTitle = $_POST['event_title'] ?? '';
    
    try {
        switch ($action) {
            case 'add_event':
                // In a real system, this would insert into database
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                    "Added new event: " . $_POST['title']);
                $_SESSION['success'] = "Event '".htmlspecialchars($_POST['title'])."' added successfully";
                break;
                
            case 'update_event':
                // In a real system, this would update database
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                    "Updated event #$eventId: $eventTitle");
                $_SESSION['success'] = "Event '$eventTitle' updated successfully";
                break;
                
            case 'cancel_event':
                // In a real system, this would update status
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                    "Cancelled event #$eventId: $eventTitle");
                $_SESSION['success'] = "Event '$eventTitle' cancelled successfully";
                break;
                
            case 'delete_event':
                // In a real system, this would delete from database
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                    "Deleted event #$eventId: $eventTitle");
                $_SESSION['success'] = "Event '$eventTitle' deleted successfully";
                break;
                
            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
            "Failed to process event action '$action': " . $e->getMessage());
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: manage_events.php");
    exit;
}

$events = generateRandomEvents();
$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') {
    $events = array_filter($events, fn($event) => $event['status'] === $filter);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Library Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        .event-card { transition: transform 0.2s; }
        .event-card:hover { transform: translateY(-5px); }
        .capacity-bar { height: 10px; }
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
                <a class="navbar-brand" href="#">Library System - Events Management</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-4">
                <h2>Library Events and Programs</h2>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="fas fa-plus"></i> Add New Event
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

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="btn-group" role="group">
                        <a href="?filter=all" class="btn btn-outline-dark <?= $filter === 'all' ? 'active' : '' ?>">All Events</a>
                        <a href="?filter=Upcoming" class="btn btn-outline-primary <?= $filter === 'Upcoming' ? 'active' : '' ?>">Upcoming</a>
                        <a href="?filter=Ongoing" class="btn btn-outline-success <?= $filter === 'Ongoing' ? 'active' : '' ?>">Ongoing</a>
                        <a href="?filter=Completed" class="btn btn-outline-secondary <?= $filter === 'Completed' ? 'active' : '' ?>">Completed</a>
                        <a href="?filter=Cancelled" class="btn btn-outline-danger <?= $filter === 'Cancelled' ? 'active' : '' ?>">Cancelled</a>
                    </div>
                </div>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($events as $event): 
                    $statusClass = strtolower($event['status']);
                    $percentFull = ($event['registered_count'] / $event['max_capacity']) * 100;
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
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <small>Registration: <?= $event['registered_count'] ?>/<?= $event['max_capacity'] ?></small>
                                        <small><?= number_format($percentFull, 0) ?>% full</small>
                                    </div>
                                    <div class="progress capacity-bar">
                                        <div class="progress-bar <?= $percentFull >= 90 ? 'bg-danger' : ($percentFull >= 75 ? 'bg-warning' : 'bg-success') ?>" 
                                             role="progressbar" style="width: <?= $percentFull ?>%" 
                                             aria-valuenow="<?= $percentFull ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Organizer: <?= $event['organizer'] ?></small>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <div class="d-flex justify-content-between">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editEventModal"
                                        data-event-id="<?= $event['event_id'] ?>"
                                        data-event-title="<?= htmlspecialchars($event['title']) ?>"
                                        data-event-description="<?= htmlspecialchars($event['description']) ?>"
                                        data-event-type="<?= $event['event_type'] ?>"
                                        data-start-datetime="<?= $event['start_datetime'] ?>"
                                        data-end-datetime="<?= $event['end_datetime'] ?>"
                                        data-location="<?= $event['location'] ?>"
                                        data-max-capacity="<?= $event['max_capacity'] ?>"
                                        data-status="<?= $event['status'] ?>"
                                        data-organizer="<?= $event['organizer'] ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <?php if ($event['status'] === 'Upcoming' || $event['status'] === 'Ongoing'): ?>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelEventModal"
                                            data-event-id="<?= $event['event_id'] ?>"
                                            data-event-title="<?= htmlspecialchars($event['title']) ?>">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#deleteEventModal"
                                        data-event-id="<?= $event['event_id'] ?>"
                                        data-event-title="<?= htmlspecialchars($event['title']) ?>">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_event">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Event Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Event Type</label>
                            <select class="form-select" name="event_type" required>
                                <option value="Book Reading">Book Reading</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Lecture">Lecture</option>
                                <option value="Book Club">Book Club</option>
                                <option value="Children's Hour">Children's Hour</option>
                                <option value="Author Visit">Author Visit</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date & Time</label>
                            <input type="datetime-local" class="form-control" name="start_datetime" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date & Time</label>
                            <input type="datetime-local" class="form-control" name="end_datetime" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <select class="form-select" name="location" required>
                                <option value="Main Hall">Main Hall</option>
                                <option value="Conference Room">Conference Room</option>
                                <option value="Children's Area">Children's Area</option>
                                <option value="Outdoor Garden">Outdoor Garden</option>
                                <option value="Auditorium">Auditorium</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Capacity</label>
                            <input type="number" class="form-control" name="max_capacity" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Organizer</label>
                            <input type="text" class="form-control" name="organizer" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="Upcoming" selected>Upcoming</option>
                                <option value="Ongoing">Ongoing</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_event">
                <input type="hidden" name="event_id" id="edit_event_id">
                <input type="hidden" name="event_title" id="edit_event_title">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Event Title</label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Event Type</label>
                            <select class="form-select" name="event_type" id="edit_event_type" required>
                                <option value="Book Reading">Book Reading</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Lecture">Lecture</option>
                                <option value="Book Club">Book Club</option>
                                <option value="Children's Hour">Children's Hour</option>
                                <option value="Author Visit">Author Visit</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date & Time</label>
                            <input type="datetime-local" class="form-control" name="start_datetime" id="edit_start_datetime" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date & Time</label>
                            <input type="datetime-local" class="form-control" name="end_datetime" id="edit_end_datetime" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <select class="form-select" name="location" id="edit_location" required>
                                <option value="Main Hall">Main Hall</option>
                                <option value="Conference Room">Conference Room</option>
                                <option value="Children's Area">Children's Area</option>
                                <option value="Outdoor Garden">Outdoor Garden</option>
                                <option value="Auditorium">Auditorium</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Capacity</label>
                            <input type="number" class="form-control" name="max_capacity" id="edit_max_capacity" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Organizer</label>
                            <input type="text" class="form-control" name="organizer" id="edit_organizer" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="Upcoming">Upcoming</option>
                                <option value="Ongoing">Ongoing</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Event Modal -->
<div class="modal fade" id="cancelEventModal" tabindex="-1" aria-labelledby="cancelEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Cancel Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="cancel_event">
                <input type="hidden" name="event_id" id="cancel_event_id">
                <input type="hidden" name="event_title" id="cancel_event_title">
                <div class="modal-body">
                    <p>Are you sure you want to cancel this event?</p>
                    <div class="alert alert-warning">
                        <strong>Note:</strong> Cancelling an event will notify all registered participants.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cancellation Reason (Optional)</label>
                        <textarea class="form-control" name="reason" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep Event</button>
                    <button type="submit" class="btn btn-warning">Yes, Cancel Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Event Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_event">
                <input type="hidden" name="event_id" id="delete_event_id">
                <input type="hidden" name="event_title" id="delete_event_title">
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete this event?</p>
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> This action cannot be undone. All event data will be lost.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep Event</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Initialize edit modal with event data
document.getElementById('editEventModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const modal = this;
    
    modal.querySelector('#edit_event_id').value = button.getAttribute('data-event-id');
    modal.querySelector('#edit_event_title').value = button.getAttribute('data-event-title');
    modal.querySelector('#edit_title').value = button.getAttribute('data-event-title');
    modal.querySelector('#edit_description').value = button.getAttribute('data-event-description');
    modal.querySelector('#edit_event_type').value = button.getAttribute('data-event-type');
    modal.querySelector('#edit_start_datetime').value = formatDateTimeForInput(button.getAttribute('data-start-datetime'));
    modal.querySelector('#edit_end_datetime').value = formatDateTimeForInput(button.getAttribute('data-end-datetime'));
    modal.querySelector('#edit_location').value = button.getAttribute('data-location');
    modal.querySelector('#edit_max_capacity').value = button.getAttribute('data-max-capacity');
    modal.querySelector('#edit_status').value = button.getAttribute('data-status');
    modal.querySelector('#edit_organizer').value = button.getAttribute('data-organizer');
});

// Initialize cancel modal with event data
document.getElementById('cancelEventModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const modal = this;
    
    modal.querySelector('#cancel_event_id').value = button.getAttribute('data-event-id');
    modal.querySelector('#cancel_event_title').value = button.getAttribute('data-event-title');
    modal.querySelector('.modal-body p').textContent = 
        `Are you sure you want to cancel the event "${button.getAttribute('data-event-title')}"?`;
});

// Initialize delete modal with event data
document.getElementById('deleteEventModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const modal = this;
    
    modal.querySelector('#delete_event_id').value = button.getAttribute('data-event-id');
    modal.querySelector('#delete_event_title').value = button.getAttribute('data-event-title');
    modal.querySelector('.modal-body p').textContent = 
        `Are you sure you want to permanently delete the event "${button.getAttribute('data-event-title')}"?`;
});

// Helper function to format datetime for input field
function formatDateTimeForInput(datetimeStr) {
    const dt = new Date(datetimeStr);
    const pad = num => num.toString().padStart(2, '0');
    return `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
}

// Initialize datetime pickers
flatpickr("input[type=datetime-local]", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    minDate: "today"
});
</script>
</body>
</html>