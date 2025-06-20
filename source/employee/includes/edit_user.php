<?php
session_start();
include '../../../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $purok = trim($_POST['purok'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $birthdate = $_POST['birthdate'] ?? null;
    $role_id = $_POST['role_id'] ?? null;
    $status = $_POST['status'] ?? 'Active';

    if ($id && $first_name && $last_name && $barangay && $city && $birthdate && $role_id) {
        $sql = "UPDATE employees SET
                    first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    purok = :purok,
                    street = :street,
                    barangay = :barangay,
                    city = :city,
                    postal_code = :postal_code,
                    birthdate = :birthdate,
                    role_id = :role_id,
                    status = :status
                WHERE employee_id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'first_name' => $first_name,
            'middle_name' => $middle_name,
            'last_name' => $last_name,
            'purok' => $purok,
            'street' => $street,
            'barangay' => $barangay,
            'city' => $city,
            'postal_code' => $postal_code,
            'birthdate' => $birthdate,
            'role_id' => $role_id,
            'status' => $status,
            'id' => $id
        ]);
    }
}

header("Location: ../manage_users.php");
exit;
?>