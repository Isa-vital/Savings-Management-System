<!-- settings/roles.php -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>Role Permissions</h5>
        <div>
            <button class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="bi bi-plus-circle"></i> Add Role
            </button>
            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#permissionHelp">
                <i class="bi bi-question-circle"></i> Help
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Role</th>
                        <?php foreach(get_all_permissions() as $perm): ?>
                        <th class="text-center" title="<?= htmlspecialchars($perm['description']) ?>">
                            <?= $perm['key'] ?>
                        </th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach(get_all_roles() as $role): ?>
                    <tr>
                        <td><strong><?= $role['name'] ?></strong></td>
                        
                        <?php foreach(get_all_permissions() as $perm): ?>
                        <td class="text-center">
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input permission-toggle" 
                                       type="checkbox" 
                                       data-role="<?= $role['id'] ?>" 
                                       data-permission="<?= $perm['id'] ?>"
                                    <?= has_permission($role['id'], $perm['id']) ? 'checked' : '' ?>>
                            </div>
                        </td>
                        <?php endforeach; ?>
                        
                        <td>
                            <button class="btn btn-sm btn-outline-primary edit-role" 
                                    data-roleid="<?= $role['id'] ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <?php if($role['name'] !== 'Super Admin'): ?>
                            <button class="btn btn-sm btn-outline-danger delete-role" 
                                    data-roleid="<?= $role['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>