<div class="bg-dark text-white p-3" style="width: 250px; height: 100vh;">
    <div class="text-center mb-4">
        <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="rounded-circle" width="80" alt="Profile">
        <p class="mt-2 mb-0"><?= htmlspecialchars($_SESSION['user_name']) ?></p>
        <a href="edit_profile.php" class="text-info small">Edit Profile</a>
    </div>
    <ul class="nav nav-pills flex-column">
        <li class="nav-item mb-2">
            <a class="nav-link text-white" href="../employee/dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
        </li>

        <?php
        // Define which roles can access "Manage Users"
        $allowed_roles = [1, 2, 6]; // Library Admin, Librarian, IT Personnel

        if (in_array($_SESSION['role_id'], $allowed_roles)) {
            echo '
            <li class="nav-item mb-2">
                <a class="nav-link text-white" href="../employee/manage_users.php">
                    <i class="fas fa-users me-2"></i>Manage Users
                </a>
            </li>';
        }
        ?>

        <li class="nav-item">
            <a class="nav-link text-white" href="../employee/materials_management.php"><i class="fa-solid fa-book-open-reader me-2"></i>Manage Materials</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="../employee/available_materials.php"><i class="fas fa-list me-2"></i>Available Materials</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="../employee/renew_loans.php"><i class="fa-solid fa-calendar me-2">‌</i>Borrowed Materials</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="../employee/late_fees.php"><i class="fa-solid fa-money-check-dollar me-2">‌</i>Fees</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="../employee/borrow_history.php"><i class="fa-solid fa-history me-2">‌</i>Borrow History</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="../employee/manage_events.php"><i class="fa-solid fa-calendar-days me-2">‌</i>Manage Events</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="../employee/rate.php"><i class="fa-solid fa-pen me-2">‌</i>Material Rating</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="../employee/activity_logs.php"><i class="fa-solid fa-business-time me-2">‌</i>Activity Logs</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="../employee/system_maintenance.php"><i class="fa-solid fa-bug me-2">‌</i>System Maintenance</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="./includes/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </li>
    </ul>
</div>