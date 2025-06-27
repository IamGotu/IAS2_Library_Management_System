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

// Log page access
logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], "Accessed System Maintenance");

// Generate random backup history data
function generateBackupHistory($count = 10) {
    $backups = [];
    $backupTypes = ['Full Database', 'Incremental', 'User Data', 'Transaction Logs', 'Media Files'];
    $statuses = ['Completed', 'Failed', 'In Progress', 'Scheduled'];
    $storageLocations = ['Local Server', 'Cloud Storage', 'External HDD', 'NAS'];
    
    for ($i = 0; $i < $count; $i++) {
        $backupDate = date('Y-m-d H:i:s', strtotime('-'.rand(0, 30).' days '.rand(0, 23).':'.rand(0, 59).':00'));
        $size = rand(100, 5000) / 10; // MB
        $duration = rand(1, 120); // minutes
        
        $backups[] = [
            'backup_id' => 'BK-' . rand(1000, 9999),
            'backup_type' => $backupTypes[array_rand($backupTypes)],
            'backup_date' => $backupDate,
            'status' => $statuses[array_rand($statuses)],
            'size_mb' => $size,
            'duration_min' => $duration,
            'storage_location' => $storageLocations[array_rand($storageLocations)],
            'initiated_by' => ['System Admin', 'Automated Job', 'IT Staff'][rand(0, 2)],
            'notes' => $statuses[array_rand($statuses)] === 'Failed' ? 
                'Error code: ' . rand(100, 999) . '. Connection timeout.' : 
                'Backup completed successfully.'
        ];
    }
    
    // Sort by date (newest first)
    usort($backups, function($a, $b) {
        return strtotime($b['backup_date']) - strtotime($a['backup_date']);
    });
    
    return $backups;
}
// Handle maintenance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $backupId = $_POST['backup_id'] ?? '';
    
    try {
        switch ($action) {
            case 'initiate_backup':
                $backupType = $_POST['backup_type'] ?? '';
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                    "Initiated {$backupType} backup");
                $_SESSION['success'] = "{$backupType} backup initiated successfully";
                break;
                
            case 'restore_backup':
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                    "Restored backup: {$backupId}");
                $_SESSION['success'] = "Backup {$backupId} restoration initiated";
                break;
                
            case 'delete_backup':
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                    "Deleted backup: {$backupId}");
                $_SESSION['success'] = "Backup {$backupId} deleted successfully";
                break;
                
            case 'run_maintenance':
                $task = $_POST['task'] ?? '';
                logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                    "Ran maintenance task: {$task}");
                $_SESSION['success'] = "Maintenance task '{$task}' completed successfully";
                break;
                
            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
            "Failed to process maintenance action '{$action}': " . $e->getMessage());
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: system_maintenance.php");
    exit;
}

$backups = generateBackupHistory();
$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') {
    $backups = array_filter($backups, fn($backup) => $backup['status'] === $filter);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Maintenance & Backups</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .backup-card { transition: transform 0.2s; }
        .backup-card:hover { transform: translateY(-5px); }
        .completed { border-left: 4px solid #198754; }
        .failed { border-left: 4px solid #dc3545; }
        .in-progress { border-left: 4px solid #0d6efd; }
        .scheduled { border-left: 4px solid #ffc107; }
        .progress-thin { height: 6px; }
        .backup-type-badge { font-size: 0.8em; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include './includes/sidebar.php'; ?>

    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="#">Library System - Maintenance & Backups</a>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-4">
                <h2>System Maintenance & Data Backups</h2>
                <div>
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#backupModal">
                        <i class="fas fa-database"></i> New Backup
                    </button>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#maintenanceModal">
                        <i class="fas fa-tools"></i> Run Maintenance
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

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="btn-group" role="group">
                        <a href="?filter=all" class="btn btn-outline-dark <?= $filter === 'all' ? 'active' : '' ?>">All Backups</a>
                        <a href="?filter=Completed" class="btn btn-outline-success <?= $filter === 'Completed' ? 'active' : '' ?>">Completed</a>
                        <a href="?filter=Failed" class="btn btn-outline-danger <?= $filter === 'Failed' ? 'active' : '' ?>">Failed</a>
                        <a href="?filter=In Progress" class="btn btn-outline-primary <?= $filter === 'In Progress' ? 'active' : '' ?>">In Progress</a>
                        <a href="?filter=Scheduled" class="btn btn-outline-warning <?= $filter === 'Scheduled' ? 'active' : '' ?>">Scheduled</a>
                    </div>
                </div>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($backups as $backup): 
                    $statusClass = strtolower(str_replace(' ', '-', $backup['status']));
                    $isInProgress = $backup['status'] === 'In Progress';
                ?>
                    <div class="col">
                        <div class="card h-100 backup-card <?= $statusClass ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><?= $backup['backup_id'] ?></h5>
                                <span class="badge bg-<?= 
                                    $statusClass === 'completed' ? 'success' : 
                                    ($statusClass === 'failed' ? 'danger' : 
                                    ($statusClass === 'in-progress' ? 'primary' : 'warning'))
                                ?>"><?= $backup['status'] ?></span>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted"><i class="fas fa-calendar-day"></i> <?= date('M j, Y', strtotime($backup['backup_date'])) ?></span>
                                    <span class="text-muted"><i class="fas fa-clock"></i> <?= date('g:i a', strtotime($backup['backup_date'])) ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="badge backup-type-badge bg-info text-dark"><?= $backup['backup_type'] ?></span>
                                    <span class="badge backup-type-badge bg-light text-dark"><i class="fas fa-hdd"></i> <?= $backup['storage_location'] ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span><i class="fas fa-weight-hanging"></i> <?= $backup['size_mb'] ?> MB</span>
                                        <span><i class="fas fa-stopwatch"></i> <?= $backup['duration_min'] ?> min</span>
                                    </div>
                                    <?php if ($isInProgress): ?>
                                        <div class="progress progress-thin mt-1">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                role="progressbar" style="width: <?= rand(20, 95) ?>%" 
                                                aria-valuenow="<?= rand(20, 95) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">Initiated by: <?= $backup['initiated_by'] ?></small>
                                </div>
                                
                                <div class="alert alert-<?= $backup['status'] === 'Failed' ? 'danger' : 'light' ?> p-2 mb-0">
                                    <small><i class="fas fa-info-circle"></i> <?= $backup['notes'] ?></small>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <div class="d-flex justify-content-between">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#restoreModal"
                                        data-backup-id="<?= $backup['backup_id'] ?>"
                                        data-backup-type="<?= $backup['backup_type'] ?>"
                                        data-backup-date="<?= date('M j, Y g:i a', strtotime($backup['backup_date'])) ?>">
                                        <i class="fas fa-undo"></i> Restore
                                    </button>
                                    
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteBackupModal"
                                        data-backup-id="<?= $backup['backup_id'] ?>"
                                        data-backup-date="<?= date('M j, Y', strtotime($backup['backup_date'])) ?>">
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

<!-- Backup Modal -->
<div class="modal fade" id="backupModal" tabindex="-1" aria-labelledby="backupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Create New Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="initiate_backup">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Backup Type</label>
                        <select class="form-select" name="backup_type" required>
                            <option value="Full Database">Full Database Backup</option>
                            <option value="Incremental">Incremental Backup</option>
                            <option value="User Data">User Data Only</option>
                            <option value="Transaction Logs">Transaction Logs</option>
                            <option value="Media Files">Media Files</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Storage Location</label>
                        <select class="form-select" name="storage_location" required>
                            <option value="Local Server">Local Server</option>
                            <option value="Cloud Storage">Cloud Storage</option>
                            <option value="External HDD">External HDD</option>
                            <option value="NAS">Network Attached Storage</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Backup Name (Optional)</label>
                        <input type="text" class="form-control" name="backup_name" placeholder="e.g., Pre-Maintenance Backup">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="compress" id="compressBackup" checked>
                        <label class="form-check-label" for="compressBackup">
                            Compress Backup (Recommended)
                        </label>
                    </div>
                    <div class="alert alert-warning">
                        <strong>Note:</strong> System performance may be affected during backup process.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Initiate Backup</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Restore From Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="restore_backup">
                <input type="hidden" name="backup_id" id="restore_backup_id">
                <div class="modal-body">
                    <p>You are about to restore from backup: <strong id="restore_backup_info"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Restore Scope</label>
                        <select class="form-select" name="restore_scope" required>
                            <option value="full">Full System Restore</option>
                            <option value="data_only">Data Only (Preserve Configuration)</option>
                            <option value="users_only">User Accounts Only</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="verify_backup" id="verifyBackup" checked>
                        <label class="form-check-label" for="verifyBackup">
                            Verify Backup Integrity Before Restore
                        </label>
                    </div>
                    
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> Restoring will overwrite current data. Ensure you have a current backup before proceeding.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Confirm Restore</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Backup Modal -->
<div class="modal fade" id="deleteBackupModal" tabindex="-1" aria-labelledby="deleteBackupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_backup">
                <input type="hidden" name="backup_id" id="delete_backup_id">
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete the backup from <strong id="delete_backup_date"></strong>?</p>
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> This action cannot be undone. Backup files will be permanently removed from storage.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Backup</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Maintenance Modal -->
<div class="modal fade" id="maintenanceModal" tabindex="-1" aria-labelledby="maintenanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">System Maintenance Tasks</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="run_maintenance">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Maintenance Task</label>
                        <select class="form-select" name="task" required>
                            <option value="Database Optimization">Database Optimization</option>
                            <option value="Index Rebuilding">Index Rebuilding</option>
                            <option value="Disk Cleanup">Disk Cleanup</option>
                            <option value="Log Rotation">Log Rotation</option>
                            <option value="Integrity Check">System Integrity Check</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Schedule</label>
                        <select class="form-select" name="schedule" required>
                            <option value="now">Run Now</option>
                            <option value="offhours">Schedule During Off-Hours</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="notify_users" id="notifyUsers">
                        <label class="form-check-label" for="notifyUsers">
                            Notify Users About Possible Downtime
                        </label>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> Some tasks may require system restart or cause temporary service interruption.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Run Maintenance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize restore modal with backup data
document.getElementById('restoreModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const modal = this;
    
    modal.querySelector('#restore_backup_id').value = button.getAttribute('data-backup-id');
    modal.querySelector('#restore_backup_info').textContent = 
        `${button.getAttribute('data-backup-type')} (${button.getAttribute('data-backup-date')})`;
});

// Initialize delete backup modal with backup data
document.getElementById('deleteBackupModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const modal = this;
    
    modal.querySelector('#delete_backup_id').value = button.getAttribute('data-backup-id');
    modal.querySelector('#delete_backup_date').textContent = button.getAttribute('data-backup-date');
});

// Simulate backup progress for in-progress backups
document.querySelectorAll('.progress-bar-animated').forEach(bar => {
    let progress = parseInt(bar.style.width);
    const interval = setInterval(() => {
        progress += Math.random() * 5;
        if (progress >= 100) {
            progress = 100;
            clearInterval(interval);
        }
        bar.style.width = `${progress}%`;
        bar.setAttribute('aria-valuenow', progress);
    }, 1000);
});
</script>
</body>
</html>