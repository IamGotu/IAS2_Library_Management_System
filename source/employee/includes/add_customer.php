<?php
session_start();
include '../../../config/db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $purok = trim($_POST['purok'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $birthdate = $_POST['birthdate'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $role_id = (int) ($_POST['role_id'] ?? 0);
    $status = $_POST['status'] ?? 'Active';
    $phone_num = $_POST['phone_num'] ?? '';

    // Default password = last_name + email
    $default_password = $last_name . $email;
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

    // Check if email already exists
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM customer WHERE email = ?");
    $checkStmt->execute([$email]);
    $emailExists = $checkStmt->fetchColumn();

    if ($emailExists > 0) {
        $_SESSION['error'] = "Email already exists for another customer.";
        header("Location: ../manage_users.php");
        exit;
    }

    // Check if phone number already exists
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM customer WHERE phone_num = ?");
    $checkStmt->execute([$phone_num]);
    $phoneNumExists = $checkStmt->fetchColumn();

    if ($phoneNumExists > 0) {
        $_SESSION['error'] = "Phone number already exists for another customer.";
        header("Location: ../manage_users.php");
        exit;
    }

    // Insert new customer
    $stmt = $pdo->prepare("INSERT INTO customer 
        (first_name, middle_name, last_name, purok, street, barangay, city, postal_code, phone_num, birthdate, email, password, role_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt->execute([$first_name, $middle_name, $last_name, $purok, $street, $barangay, $city, $postal_code, $phone_num, $birthdate, $email, $hashed_password, $role_id, $status])) {
        $_SESSION['success'] = "Customer added successfully.";
        header("Location: ../manage_users.php");
        exit;
    } else {
        $_SESSION['error'] = "Failed to add customer.";
        header("Location: ../manage_users.php");
        exit;
    }
} else {
    header("Location: ../manage_users.php");
    exit;
}