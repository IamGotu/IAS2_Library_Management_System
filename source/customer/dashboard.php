<?php
session_start();
include '../../config/db_conn.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Dashboard</title>
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
        <div class="container d-flex justify-content-center align-items-center" style="min-height: 80vh;">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h4>
                    </div>
                    <div class="card-body text-center">
                        <p class="lead">You are now logged in to the Library Management System.</p>
                        <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="Customer" width="100" class="mb-3">
                        <div>
                            <a href="./includes/logout.php" class="btn btn-danger mt-3 w-50">Logout</a>
                        </div>
                    </div>
                </div>
                <p class="text-center mt-3 text-muted">&copy; <?= date('Y') ?> Library Management System</p>
            </div>
        </div>
    </div>
    
</div>
</body>
</html>