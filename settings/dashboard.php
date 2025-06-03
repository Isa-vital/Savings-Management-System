<!-- settings/dashboard.php -->
<div class="row">
    <!-- User Management Card -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-people-fill me-2"></i> User Access Control
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="settings/users.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-person-gear me-2"></i> Manage Users
                    </a>
                    <a href="settings/roles.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-shield-lock me-2"></i> Role Permissions
                    </a>
                    <a href="settings/audit.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-clock-history me-2"></i> Access Logs
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- System Config Card -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <i class="bi bi-gear-fill me-2"></i> System Configuration
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="settings/general.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-sliders me-2"></i> General Settings
                    </a>
                    <a href="settings/notifications.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-bell-fill me-2"></i> Notification Templates
                    </a>
                    <a href="settings/backup.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-database-fill me-2"></i> Data Backup
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>