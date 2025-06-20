<?php
session_start();
include '../config/db_conn.php';

$error = '';
// Display session error messages if set
if (isset($_SESSION['email_error'])) {
    $error = $_SESSION['email_error'];
    unset($_SESSION['email_error']);
} elseif (isset($_SESSION['password_error'])) {
    $error = $_SESSION['password_error'];
    unset($_SESSION['password_error']);
} elseif (isset($_SESSION['role_error'])) {
    $error = $_SESSION['role_error'];
    unset($_SESSION['role_error']);
}

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Search in employees table first
    $sql = "SELECT * FROM employees WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    $user_role = 'employee';

    // If not found, search in customer table
    if (!$user) {
        $sql = "SELECT * FROM customer WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        $user_role = 'customer';
    }

    if ($user) {
        $isPasswordValid = password_verify($password, $user['password']);

        if ($isPasswordValid) {
            // Set session values
            $fullName = $user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name'];
            $_SESSION['user_id'] = ($user_role === 'employee') ? $user['employee_id'] : $user['customer_id'];
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_role'] = $user_role;
            $_SESSION['role_id'] = $user['role_id'];

            // Fetch role name
            $roleStmt = $pdo->prepare("SELECT role_name FROM roles WHERE role_id = :role_id");
            $roleStmt->execute(['role_id' => $user['role_id']]);
            $role = $roleStmt->fetchColumn();
            $_SESSION['role_name'] = $role;

            // Define redirection based on role name
            $employeeRoles = [
                'Library Admin',
                'Librarian',
                'Assistant Librarian',
                'Volunteer',
                'Content Curator',
                'IT Personnel'
            ];

            $customerRoles = [
                'Member (Adult)',
                'Member (Senior)',
                'Member (Youth)',
                'Researcher'
            ];

            if (in_array($role, $employeeRoles)) {
                header("Location: /./employee/dashboard.php");
            } elseif (in_array($role, $customerRoles)) {
                header("Location: ../customer/dashboard.php");
            } else {
                $_SESSION['role_error'] = "Access denied. No valid role assigned.";
                header("Location: login.php");
            }
            exit();
        } else {
            $_SESSION['password_error'] = "Invalid password.";
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['email_error'] = "Invalid email address.";
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center align-items-center" style="height:100vh;">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header text-center">
                    <h4>Library Login</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
            </div>
            <p class="text-center mt-3 text-muted">&copy; <?= date('Y') ?> Library Management System</p>
        </div>
    </div>
</div>
</body>
</html>