<?php
session_start();
include '../../config/db_conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user has permission to manage users (employee role)
if ($_SESSION['user_role'] !== 'employee') {
    header("Location: ../unauthorized.php");
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

// Fetch users from the database
$stmt = $pdo->query("
    SELECT 
        e.employee_id AS id,
        e.first_name, e.middle_name, e.last_name,
        e.purok, e.street, e.barangay, e.city, e.postal_code,
        e.birthdate, r.role_id, r.role_name,
        e.email, e.status, e.phone_num,
        'Employee' AS user_type
    FROM employees e
    JOIN roles r ON e.role_id = r.role_id

    UNION ALL

    SELECT 
        c.customer_id AS id,
        c.first_name, c.middle_name, c.last_name,
        c.purok, c.street, c.barangay, c.city, c.postal_code,
        c.birthdate, r.role_id, r.role_name,
        c.email, c.status, c.phone_num,
        'Customer' AS user_type
    FROM customer c
    JOIN roles r ON c.role_id = r.role_id
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch roles for the dropdown
$roleStmt = $pdo->query("SELECT role_id, role_name, description FROM roles");
$roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

// Separate roles by type
$employeeRoles = array_filter($roles, fn($r) => $r['description'] === 'Employee');
$customerRoles = array_filter($roles, fn($r) => $r['description'] === 'Customer');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
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
        <div class="container mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h2>Manage Users</h2>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#selectUserTypeModal">
                    <i class="fas fa-user-plus"></i> Add User
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

            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['phone_num']) ?></td>
                            <td><?= htmlspecialchars($user['role_name']) ?></td>
                            <td>
                                <span class="badge bg-<?= $user['status'] === 'Active' ? 'success' : 'danger' ?>">
                                    <?= htmlspecialchars($user['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['user_type'] === 'Employee' && $user['id'] == $_SESSION['user_id']): ?>
                                    <span class="text-muted">You</span>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $user['id'] ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $user['id'] ?>">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                <?php endif; ?>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $user['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editModalLabel<?= $user['id'] ?>">Edit <?= $user['user_type'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form action="./includes/edit_user.php" method="POST">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="user_type" value="<?= $user['user_type'] ?>">
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label">First Name</label>
                                                            <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Middle Name</label>
                                                            <input type="text" class="form-control" name="middle_name" value="<?= htmlspecialchars($user['middle_name']) ?>">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Last Name</label>
                                                            <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Purok</label>
                                                            <input type="text" class="form-control" name="purok" value="<?= htmlspecialchars($user['purok']) ?>">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Street</label>
                                                            <input type="text" class="form-control" name="street" value="<?= htmlspecialchars($user['street']) ?>">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Barangay</label>
                                                            <input type="text" class="form-control" name="barangay" value="<?= htmlspecialchars($user['barangay']) ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">City</label>
                                                            <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($user['city']) ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Postal Code</label>
                                                            <input type="text" class="form-control" name="postal_code" value="<?= htmlspecialchars($user['postal_code']) ?>">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Birthdate</label>
                                                            <input type="date" class="form-control" name="birthdate" value="<?= htmlspecialchars($user['birthdate']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Phone Number</label>
                                                            <input type="text" class="form-control" name="phone_num" value="<?= htmlspecialchars($user['phone_num']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Email</label>
                                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Role</label>
                                                            <select class="form-select" name="role_id" required>
                                                                <?php 
                                                                $availableRoles = ($user['user_type'] === 'Customer') ? $customerRoles : $employeeRoles;
                                                                foreach ($availableRoles as $role): ?>
                                                                    <option value="<?= $role['role_id'] ?>" <?= $role['role_id'] == $user['role_id'] ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($role['role_name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Status</label>
                                                            <select class="form-select" name="status">
                                                                <option value="Active" <?= $user['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                                                <option value="Inactive" <?= $user['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="mt-4 text-end">
                                                        <button type="submit" class="btn btn-success">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $user['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteModalLabel<?= $user['id'] ?>">Delete <?= $user['user_type'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                Are you sure you want to delete this <?= strtolower($user['user_type']) ?>?
                                            </div>
                                            <div class="modal-footer">
                                                <form action="./includes/delete_user.php" method="POST">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="user_type" value="<?= $user['user_type'] ?>">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Select User Type Modal -->
            <div class="modal fade" id="selectUserTypeModal" tabindex="-1" aria-labelledby="selectUserTypeModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <button class="btn btn-outline-primary w-100 mb-2" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                Add Employee
                            </button>
                            <button class="btn btn-outline-success w-100" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                Add Customer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Employee Modal -->
            <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form action="./includes/add_employee.php" method="POST">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Employee</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <input name="first_name" class="form-control" placeholder="First Name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input name="middle_name" class="form-control" placeholder="Middle Name">
                                    </div>
                                    <div class="col-md-4">
                                        <input name="last_name" class="form-control" placeholder="Last Name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input name="purok" class="form-control" placeholder="Purok">
                                    </div>
                                    <div class="col-md-4">
                                        <input name="street" class="form-control" placeholder="Street">
                                    </div>
                                    <div class="col-md-4">
                                        <input name="barangay" class="form-control" placeholder="Barangay" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input name="city" class="form-control" placeholder="City" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input name="postal_code" class="form-control" placeholder="Postal Code">
                                    </div>
                                    <div class="col-md-4">
                                        <input name="birthdate" type="date" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input name="phone_num" class="form-control" placeholder="Phone Number" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input name="email" type="email" class="form-control" placeholder="Email" required>
                                    </div>
                                    <div class="col-md-6">
                                        <select name="role_id" class="form-select" required>
                                            <option disabled selected>Select Role</option>
                                            <?php foreach ($employeeRoles as $role): ?>
                                                <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <select name="status" class="form-select">
                                            <option value="Active">Active</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-success">Add Employee</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Customer Modal -->
            <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form action="./includes/add_customer.php" method="POST">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Customer</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <input name="first_name" class="form-control" placeholder="First Name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input name="middle_name" class="form-control" placeholder="Middle Name">
                                    </div>
                                    <div class="col-md-4">
                                        <input name="last_name" class="form-control" placeholder="Last Name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input name="purok" class="form-control" placeholder="Purok">
                                    </div>
                                    <div class="col-md-4">
                                        <input name="street" class="form-control" placeholder="Street">
                                    </div>
                                    <div class="col-md-4">
                                        <input name="barangay" class="form-control" placeholder="Barangay" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input name="city" class="form-control" placeholder="City" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input name="postal_code" class="form-control" placeholder="Postal Code">
                                    </div>
                                    <div class="col-md-4">
                                        <input name="birthdate" type="date" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input name="phone_num" class="form-control" placeholder="Phone Number" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input name="email" type="email" class="form-control" placeholder="Email" required>
                                    </div>
                                    <div class="col-md-6">
                                        <select name="role_id" class="form-select" required>
                                            <option disabled selected>Select Role</option>
                                            <?php foreach ($customerRoles as $role): ?>
                                                <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <select name="status" class="form-select">
                                            <option value="Active">Active</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-success">Add Customer</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>