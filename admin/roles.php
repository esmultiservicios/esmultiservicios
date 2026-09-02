<?php
require __DIR__ . '/bootstrap.php';
require_permission('roles.manage');

$pdo = db();
$error = '';

$requestedView = (string)($_GET['view'] ?? ($_SESSION['escms_role_view'] ?? 'mini'));
$roleView = in_array($requestedView, ['mini', 'detail'], true) ? $requestedView : 'mini';
$_SESSION['escms_role_view'] = $roleView;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $action = (string)($_POST['action'] ?? 'save');

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['role_name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $selected = array_values(
                array_unique(array_map('intval', $_POST['permissions'] ?? []))
            );

            if ($name === '') {
                throw new RuntimeException('Role name is required.');
            }

            if ($id) {
                $statement = $pdo->prepare(
                    'SELECT role_key, is_system FROM admin_roles WHERE id = ?'
                );
                $statement->execute([$id]);
                $role = $statement->fetch();

                if (!$role) {
                    throw new RuntimeException('Role not found.');
                }

                if ($role['role_key'] === 'owner') {
                    throw new RuntimeException(
                        'Owner permissions are protected and always include full access.'
                    );
                }

                $pdo->prepare(
                    'UPDATE admin_roles SET role_name = ?, description = ? WHERE id = ?'
                )->execute([$name, $description, $id]);
            } else {
                $key = strtolower(
                    trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-')
                );

                if (!$key) {
                    throw new RuntimeException('Invalid role name.');
                }

                $pdo->prepare(
                    'INSERT INTO admin_roles (role_key, role_name, description, is_system, active)
                     VALUES (?, ?, ?, 0, 1)'
                )->execute([$key, $name, $description]);

                $id = (int)$pdo->lastInsertId();
            }

            $pdo->prepare(
                'DELETE FROM admin_role_permissions WHERE role_id = ?'
            )->execute([$id]);

            $insertPermission = $pdo->prepare(
                'INSERT INTO admin_role_permissions (role_id, permission_id) VALUES (?, ?)'
            );

            foreach ($selected as $permissionId) {
                $insertPermission->execute([$id, $permissionId]);
            }

            log_activity('role_update', 'Saved role permissions', ['role_id' => $id]);
            flash('success', 'Role permissions saved.');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            $statement = $pdo->prepare(
                'SELECT role_key, is_system FROM admin_roles WHERE id = ?'
            );
            $statement->execute([$id]);
            $role = $statement->fetch();

            if (!$role) {
                throw new RuntimeException('Role not found.');
            }

            if ((int)$role['is_system'] === 1) {
                throw new RuntimeException('System roles cannot be deleted.');
            }

            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM admin_users WHERE role_id = ?'
            );
            $statement->execute([$id]);

            if ((int)$statement->fetchColumn() > 0) {
                throw new RuntimeException(
                    'Move users to another role before deleting this role.'
                );
            }

            $pdo->prepare(
                'DELETE FROM admin_role_permissions WHERE role_id = ?'
            )->execute([$id]);
            $pdo->prepare('DELETE FROM admin_roles WHERE id = ?')->execute([$id]);

            flash('success', 'Role deleted.');
        }

        header('Location: roles.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$edit = null;
$selected = [];

if (isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM admin_roles WHERE id = ?');
    $statement->execute([(int)$_GET['edit']]);
    $edit = $statement->fetch();

    if ($edit) {
        $statement = $pdo->prepare(
            'SELECT permission_id FROM admin_role_permissions WHERE role_id = ?'
        );
        $statement->execute([$edit['id']]);
        $selected = array_map(
            'intval',
            array_column($statement->fetchAll(), 'permission_id')
        );
    }
}

$roles = $pdo->query(
    'SELECT r.*,
        (SELECT COUNT(*) FROM admin_users u WHERE u.role_id = r.id) AS user_count
     FROM admin_roles r
     ORDER BY is_system DESC, id'
)->fetchAll();

$permissions = $pdo->query(
    'SELECT * FROM admin_permissions ORDER BY permission_group, permission_name'
)->fetchAll();

$groups = [];
foreach ($permissions as $permission) {
    $groups[$permission['permission_group']][] = $permission;
}

$totalUsers = array_sum(array_map(
    static fn(array $role): int => (int)$role['user_count'],
    $roles
));
$customRoles = count(array_filter(
    $roles,
    static fn(array $role): bool => (int)$role['is_system'] === 0
));

$pageTitle = 'Roles & Permissions';
$active = 'roles';
require __DIR__ . '/_header.php';
?>

<div class="page-heading roles-page-heading">
    <div>
        <p class="eyebrow">ACCESS CONTROL</p>
        <h1>Roles & permissions</h1>
        <p class="muted">
            Keep access intuitive: give each type of user only the tools needed for their work.
        </p>
    </div>

    <a class="button" href="roles.php?new=1&amp;view=<?= h($roleView) ?>">+ New custom role</a>
</div>

<?php if ($error): ?>
    <div class="alert error"><?= h($error) ?></div>
<?php endif; ?>

<div class="role-view-toolbar" aria-label="Role view options">
    <div>
        <strong>Role directory</strong>
        <small><?= count($roles) ?> roles · <?= $totalUsers ?> assigned users</small>
    </div>

    <div class="view-switch" aria-label="Role view options">
        <a
            class="<?= $roleView === 'mini' ? 'is-active' : '' ?>"
            href="roles.php?view=mini"
            aria-current="<?= $roleView === 'mini' ? 'true' : 'false' ?>"
        >
            Miniature
        </a>
        <a
            class="<?= $roleView === 'detail' ? 'is-active' : '' ?>"
            href="roles.php?view=detail"
            aria-current="<?= $roleView === 'detail' ? 'true' : 'false' ?>"
        >
            Details
        </a>
    </div>
</div>

<div class="roles-layout <?= ($edit || isset($_GET['new'])) ? 'has-editor' : 'no-editor' ?>">
    <section class="role-list role-view-<?= h($roleView) ?>">
        <?php foreach ($roles as $role): ?>
            <article class="role-card <?= $role['role_key'] === 'owner' ? 'protected' : '' ?>">
                <div class="role-card-main">
                    <div class="role-card-title-row">
                        <strong><?= h($role['role_name']) ?></strong>

                        <?php if ($role['role_key'] === 'owner'): ?>
                            <span class="badge success">Full access</span>
                        <?php elseif ($role['is_system']): ?>
                            <span class="badge">System</span>
                        <?php else: ?>
                            <span class="badge">Custom</span>
                        <?php endif; ?>
                    </div>

                    <small class="role-description">
                        <?= h($role['description'] ?: 'Custom access role') ?>
                    </small>

                    <div class="role-meta-row">
                        <span><?= (int)$role['user_count'] ?> user(s)</span>
                        <span><?= $role['is_system'] ? 'System role' : 'Custom role' ?></span>
                    </div>
                </div>

                <div class="actions role-card-actions">
                    <?php if ($role['role_key'] !== 'owner'): ?>
                        <a
                            class="button secondary small"
                            href="?edit=<?= (int)$role['id'] ?>&amp;view=<?= h($roleView) ?>"
                        >
                            Permissions
                        </a>
                    <?php endif; ?>

                    <?php if (!$role['is_system']): ?>
                        <form
                            method="post"
                            data-swal-confirm="Delete this role?"
                            data-swal-text="Only unused custom roles can be removed."
                        >
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$role['id'] ?>">
                            <button class="button danger-lite small" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <?php if ($edit || isset($_GET['new'])): ?>
        <section class="panel role-editor">
            <div class="panel-heading role-editor-heading">
                <div class="panel-icon"><?= icon('shield') ?></div>
                <div>
                    <h2><?= $edit ? 'Edit role' : 'Create custom role' ?></h2>
                    <p>Select the exact actions this role can perform.</p>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

                <label>
                    Role name
                    <input
                        name="role_name"
                        required
                        value="<?= h($edit['role_name'] ?? '') ?>"
                    >
                </label>

                <label>
                    Description
                    <textarea name="description"><?= h($edit['description'] ?? '') ?></textarea>
                </label>

                <div class="permission-groups">
                    <?php foreach ($groups as $group => $items): ?>
                        <fieldset>
                            <legend><?= h($group) ?></legend>

                            <?php foreach ($items as $permission): ?>
                                <label class="permission-check">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="<?= (int)$permission['id'] ?>"
                                        <?= in_array((int)$permission['id'], $selected, true)
                                            ? 'checked'
                                            : '' ?>
                                    >
                                    <span>
                                        <b><?= h($permission['permission_name']) ?></b>
                                        <small><?= h($permission['permission_key']) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button type="submit">Save role</button>
                    <a class="button secondary" href="roles.php?view=<?= h($roleView) ?>">Cancel</a>
                </div>
            </form>
        </section>
    <?php else: ?>
        <aside class="panel role-inspector-empty">
            <div class="role-inspector-icon"><?= icon('shield') ?></div>
            <p class="eyebrow">ROLE DETAILS</p>
            <h2>Select a role to manage permissions</h2>
            <p>
                Choose <strong>Permissions</strong> on a role card to see and edit its access.
                The protected Owner role always keeps full access.
            </p>

            <div class="role-summary-grid">
                <div>
                    <strong><?= count($roles) ?></strong>
                    <span>Total roles</span>
                </div>
                <div>
                    <strong><?= $customRoles ?></strong>
                    <span>Custom roles</span>
                </div>
                <div>
                    <strong><?= $totalUsers ?></strong>
                    <span>Assigned users</span>
                </div>
            </div>

            <a class="button" href="roles.php?new=1&amp;view=<?= h($roleView) ?>">Create custom role</a>
        </aside>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
