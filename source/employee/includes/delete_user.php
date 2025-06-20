<?php
session_start();
include '../../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];

    // Attempt to delete from both employees and customers
    $employeeStmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
    $employeeDeleted = $employeeStmt->execute([$id]);

    if (!$employeeDeleted || $employeeStmt->rowCount() === 0) {
        // If no employee was deleted, try deleting a customer
        $customerStmt = $pdo->prepare("DELETE FROM customer WHERE customer_id = ?");
        $customerDeleted = $customerStmt->execute([$id]);

        if ($customerDeleted && $customerStmt->rowCount() > 0) {
            $_SESSION['success'] = "Customer deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete user. Record not found.";
        }
    } else {
        $_SESSION['success'] = "Employee deleted successfully.";
    }

    header("Location: ../manage_users.php");
    exit;
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: ../manage_users.php");
    exit;
}