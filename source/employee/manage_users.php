<?php
session_start();
include '../../config/db_conn.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

// Fetch users from the database
$stmt = $pdo->query("SELECT e.employee_id AS id, e.first_name, e.middle_name, e.last_name, e.purok, e.street, e.barangay, e.city, e.postal_code, e.birthdate, r.role_id, r.role_name, e.email, e.status
                      FROM employees e
                      JOIN roles r ON e.role_id = r.role_id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch roles for the dropdown
$roleStmt = $pdo->query("SELECT role_id, role_name FROM roles");
$roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
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
        <div class="container mt-4">
            <h2 class="mb-4">Manage Users</h2>
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role_name']) ?></td>
                        <td><?= htmlspecialchars($user['status']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $user['id'] ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $user['id'] ?>">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $user['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editModalLabel<?= $user['id'] ?>">Edit User</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="./includes/edit_user.php" method="POST">
                                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
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
                                                        <label class="form-label">Role</label>
                                                        <select class="form-select" name="role_id" required>
                                                            <?php foreach ($roles as $role): ?>
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
                                            <h5 class="modal-title" id="deleteModalLabel<?= $user['id'] ?>">Delete User</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            Are you sure you want to delete this user?
                                        </div>
                                        <div class="modal-footer">
                                            <form action="delete_user.php" method="POST">
                                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
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
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>