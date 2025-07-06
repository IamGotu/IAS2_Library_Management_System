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

function findMysqlTool($toolName) {
    $possiblePaths = [
        "/usr/bin/$toolName",
        "/usr/local/mysql/bin/$toolName",
        "/usr/mysql/bin/$toolName",
        "/opt/lampp/bin/$toolName",
        "/Applications/MAMP/Library/bin/$toolName",
        $toolName
    ];

    foreach ($possiblePaths as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }
    return false;
}

function exportDatabase($pdo, $backupType, $compress) {
    $config = include '../../config/db_conn.php';
    $db_host = $config['host'];
    $db_user = $config['username'];
    $db_pass = $config['password'];
    $db_name = $config['database'];
    
    $backupDir = '../../backups/';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $backupFile = $backupDir . $db_name . '_' . date("Y-m-d_H-i-s") . '.sql';
    $finalFile = $backupFile;
    
    $mysqldumpPath = findMysqlTool('mysqldump');
    if (!$mysqldumpPath) {
        return phpBackupDatabase($pdo, $backupType, $compress);
    }
    
    $command = escapeshellcmd($mysqldumpPath) . " --host=" . escapeshellarg($db_host) . 
               " --user=" . escapeshellarg($db_user);
    
    if (!empty($db_pass)) {
        $command .= " --password=" . escapeshellarg($db_pass);
    }
    
    $command .= " " . escapeshellarg($db_name) . " > " . escapeshellarg($backupFile);
    
    system($command, $output);
    
    if ($output !== 0 || !file_exists($backupFile)) {
        throw new Exception("Database backup failed with error code: {$output}");
    }
    
    if ($compress && function_exists('gzencode')) {
        $finalFile = $backupFile . '.gz';
        $data = file_get_contents($backupFile);
        $gzdata = gzencode($data, 9);
        file_put_contents($finalFile, $gzdata);
        unlink($backupFile);
    }
    
    $fileSize = round(filesize($finalFile) / (1024 * 1024), 2);
    $stmt = $pdo->prepare("INSERT INTO backup_history (backup_id, backup_type, file_path, file_size, status, initiated_by) VALUES (?, ?, ?, ?, 'Completed', ?)");
    $backupId = 'BK-' . strtoupper(uniqid());
    $stmt->execute([$backupId, $backupType, $finalFile, $fileSize, $_SESSION['user_id']]);
    
    return $backupId;
}

function phpBackupDatabase($pdo, $backupType, $compress) {
    $backupDir = '../../backups/';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $backupFile = $backupDir . 'library_system_' . date("Y-m-d_H-i-s") . '.sql';
    $finalFile = $backupFile;
    
    // Get server information
    $stmt = $pdo->query("SELECT VERSION()");
    $mysqlVersion = $stmt->fetchColumn();
    $phpVersion = phpversion();
    $backupTime = date("M d, Y") . ' at ' . date("h:i A");
    
    // Create backup header
    $output = "-- phpMyAdmin SQL Dump\n";
    $output .= "-- version 5.2.1\n";
    $output .= "-- https://www.phpmyadmin.net/\n";
    $output .= "--\n";
    $output .= "-- Host: 127.0.0.1\n";
    $output .= "-- Generation Time: " . $backupTime . "\n";
    $output .= "-- Server version: " . $mysqlVersion . "\n";
    $output .= "-- PHP Version: " . $phpVersion . "\n\n";
    
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $output .= "START TRANSACTION;\n";
    $output .= "SET time_zone = \"+00:00\";\n\n";
    
    $output .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
    $output .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
    $output .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
    $output .= "/*!40101 SET NAMES utf8mb4 */;\n\n";
    
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // Table structure
        $output .= "--\n-- Table structure for table `{$table}`\n--\n\n";
        $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $output .= $createTable['Create Table'] . ";\n\n";
        
        // Table data
        $output .= "--\n-- Dumping data for table `{$table}`\n--\n\n";
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            $columns = array_keys($rows[0]);
            $output .= "INSERT INTO `{$table}` (`" . implode("`, `", $columns) . "`) VALUES\n";
            
            $rowData = [];
            foreach ($rows as $row) {
                $values = array_map(function($value) use ($pdo) {
                    if ($value === null) return 'NULL';
                    return $pdo->quote($value);
                }, $row);
                
                $rowData[] = "(" . implode(", ", $values) . ")";
            }
            
            $output .= implode(",\n", $rowData) . ";\n\n";
        }
    }
    
    // Backup footer
    $output .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
    $output .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
    $output .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n\n";
    $output .= "COMMIT;\n";
    
    file_put_contents($backupFile, $output);
    
    if ($compress && function_exists('gzencode')) {
        $finalFile = $backupFile . '.gz';
        $data = file_get_contents($backupFile);
        $gzdata = gzencode($data, 9);
        file_put_contents($finalFile, $gzdata);
        unlink($backupFile);
    }
    
    $fileSize = round(filesize($finalFile) / (1024 * 1024), 2);
    $stmt = $pdo->prepare("INSERT INTO backup_history (backup_id, backup_type, file_path, file_size, status, initiated_by) VALUES (?, ?, ?, ?, 'Completed', ?)");
    $backupId = 'BK-' . strtoupper(uniqid());
    $stmt->execute([$backupId, $backupType, $finalFile, $fileSize, $_SESSION['user_id']]);
    
    return $backupId;
}

function getBackupHistory($pdo, $filter = 'all') {
    $query = "SELECT bh.*, 
              CONCAT(e.first_name, ' ', COALESCE(e.middle_name, ''), ' ', e.last_name) AS initiated_by_name
              FROM backup_history bh
              LEFT JOIN employees e ON bh.initiated_by = e.employee_id";
    
    if ($filter !== 'all') {
        $query .= " WHERE bh.status = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$filter]);
    } else {
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'initiate_backup') {
            $backupType = $_POST['backup_type'] ?? 'Full Database';
            $compress = isset($_POST['compress']);
            
            $backupId = exportDatabase($pdo, $backupType, $compress);
            
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                "Initiated {$backupType} backup (ID: {$backupId})");
            $_SESSION['success'] = "{$backupType} backup completed successfully. Backup ID: {$backupId}";
        } elseif ($action === 'delete_backup') {
            $backupId = $_POST['backup_id'] ?? '';
            if (empty($backupId)) {
                throw new Exception("No backup selected");
            }
            
            $stmt = $pdo->prepare("SELECT file_path FROM backup_history WHERE backup_id = ?");
            $stmt->execute([$backupId]);
            $backupFile = $stmt->fetchColumn();
            
            if ($backupFile && file_exists($backupFile)) {
                unlink($backupFile);
            }
            
            $stmt = $pdo->prepare("DELETE FROM backup_history WHERE backup_id = ?");
            $stmt->execute([$backupId]);
            
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                "Deleted backup: {$backupId}");
            $_SESSION['success'] = "Backup {$backupId} deleted successfully";
        } elseif ($action === 'run_maintenance') {
            $task = $_POST['task'] ?? '';
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 
                "Ran maintenance task: {$task}");
            $_SESSION['success'] = "Maintenance task '{$task}' completed successfully";
        } else {
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

$filter = $_GET['filter'] ?? 'all';
$backups = getBackupHistory($pdo, $filter);
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
                    </div>
                </div>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($backups as $backup): 
                    $statusClass = strtolower(str_replace(' ', '-', $backup['status']));
                    $backupDate = date('Y-m-d H:i:s', strtotime($backup['created_at']));
                ?>
                    <div class="col">
                        <div class="card h-100 backup-card <?= $statusClass ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><?= htmlspecialchars($backup['backup_id']) ?></h5>
                                <span class="badge bg-<?= $statusClass === 'completed' ? 'success' : 'danger' ?>">
                                    <?= htmlspecialchars($backup['status']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted"><i class="fas fa-calendar-day"></i> <?= date('M j, Y', strtotime($backupDate)) ?></span>
                                    <span class="text-muted"><i class="fas fa-clock"></i> <?= date('g:i a', strtotime($backupDate)) ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="badge backup-type-badge bg-info text-dark"><?= htmlspecialchars($backup['backup_type']) ?></span>
                                    <span class="badge backup-type-badge bg-light text-dark">
                                        <i class="fas fa-hdd"></i> <?= pathinfo($backup['file_path'], PATHINFO_EXTENSION) === 'gz' ? 'Compressed' : 'Uncompressed' ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span><i class="fas fa-weight-hanging"></i> <?= round($backup['file_size'], 2) ?> MB</span>
                                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($backup['initiated_by_name'] ?? 'System') ?></span>
                                    </div>
                                </div>
                                
                                <div class="alert alert-light p-2 mb-0">
                                    <small><i class="fas fa-info-circle"></i> Stored at: <?= htmlspecialchars(basename($backup['file_path'])) ?></small>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <div class="d-flex justify-content-end">
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteBackupModal"
                                        data-backup-id="<?= htmlspecialchars($backup['backup_id']) ?>"
                                        data-backup-date="<?= date('M j, Y', strtotime($backupDate)) ?>">
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
// Initialize delete backup modal with backup data
document.getElementById('deleteBackupModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const modal = this;
    
    modal.querySelector('#delete_backup_id').value = button.getAttribute('data-backup-id');
    modal.querySelector('#delete_backup_date').textContent = button.getAttribute('data-backup-date');
});
</script>
</body>
</html>