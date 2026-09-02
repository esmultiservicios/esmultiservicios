<?php
require __DIR__ . '/bootstrap.php';
require_permission('backups.manage');

$pdo = db();
$error = '';
$dir = ROOT_DIR . '/uploads/backups';

if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$tables = [
    'site_content',
    'settings',
    'services',
    'gallery',
    'service_areas',
    'tips',
    'correo_tipo',
    'correo',
    'api_integrations',
    'site_sections',
    'media_library',
];

function backup_dataset(PDO $pdo, array $tables): array
{
    $data = [
        'version' => 2,
        'created_at' => date('c'),
        'tables' => [],
    ];

    foreach ($tables as $table) {
        $data['tables'][$table] = $pdo
            ->query("SELECT * FROM `$table`")
            ->fetchAll();
    }

    return $data;
}

function create_full_backup(PDO $pdo, array $tables, string $dir): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException(
            'PHP ZipArchive is required for full backups. Enable the ZIP extension in your hosting PHP settings.'
        );
    }

    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException(
            'The backup folder is not writable. Check the permissions for uploads/backups.'
        );
    }

    $name = 'es-cms-core-backup-' . date('Ymd-His') . '.zip';
    $fullPath = $dir . '/' . $name;

    $zip = new ZipArchive();
    if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create backup ZIP.');
    }

    $zip->addFromString(
        'database.json',
        json_encode(
            backup_dataset($pdo, $tables),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        )
    );

    $uploadRoot = ROOT_DIR . '/uploads';
    if (is_dir($uploadRoot)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $uploadRoot,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $realPath = $file->getPathname();
            if (str_starts_with($realPath, $dir . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $relativePath = 'uploads/' . str_replace(
                '\\',
                '/',
                substr($realPath, strlen($uploadRoot) + 1)
            );

            $zip->addFile($realPath, $relativePath);
        }
    }

    $zip->close();

    return [$name, 'uploads/backups/' . $name];
}

function restore_full_backup(PDO $pdo, array $tables, string $zipPath): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException(
            'PHP ZipArchive is required to restore this backup.'
        );
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not open backup ZIP.');
    }

    $json = $zip->getFromName('database.json');
    $data = $json !== false ? json_decode($json, true) : null;

    if (!is_array($data['tables'] ?? null)) {
        $zip->close();
        throw new RuntimeException('Backup database payload is invalid.');
    }

    $pdo->beginTransaction();

    try {
        foreach ($tables as $table) {
            if (!array_key_exists($table, $data['tables'])) {
                continue;
            }

            $pdo->exec("DELETE FROM `$table`");

            foreach ($data['tables'][$table] as $row) {
                if (!$row) {
                    continue;
                }

                $columns = array_keys($row);
                $sql = "INSERT INTO `$table` (`"
                    . implode('`,`', $columns)
                    . '`) VALUES ('
                    . implode(',', array_fill(0, count($columns), '?'))
                    . ')';

                $pdo->prepare($sql)->execute(array_values($row));
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $zip->close();
        throw $exception;
    }

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);

        if (
            !is_string($name)
            || !str_starts_with($name, 'uploads/')
            || str_contains($name, '../')
            || str_starts_with($name, 'uploads/backups/')
        ) {
            continue;
        }

        $target = ROOT_DIR . '/' . $name;

        if (str_ends_with($name, '/')) {
            if (!is_dir($target)) {
                @mkdir($target, 0755, true);
            }
            continue;
        }

        $parent = dirname($target);
        if (!is_dir($parent)) {
            @mkdir($parent, 0755, true);
        }

        $stream = $zip->getStream($name);
        if ($stream) {
            $output = fopen($target, 'wb');
            if ($output) {
                stream_copy_to_stream($stream, $output);
                fclose($output);
            }
            fclose($stream);
        }
    }

    $zip->close();
}

$zipAvailable = class_exists('ZipArchive');
$backupDirectoryReady = is_dir($dir) && is_writable($dir);
$uploadsDirectoryReady = is_dir(ROOT_DIR . '/uploads') && is_readable(ROOT_DIR . '/uploads');
$backupReady = $zipAvailable && $backupDirectoryReady && $uploadsDirectoryReady;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            if (!$backupReady) {
                throw new RuntimeException(
                    'Backup prerequisites are not ready. Review the Hosting readiness section below.'
                );
            }

            [$name, $relativePath] = create_full_backup($pdo, $tables, $dir);

            $pdo->prepare(
                'INSERT INTO site_backups (backup_name, file_path, created_by) VALUES (?, ?, ?)'
            )->execute([
                $name,
                $relativePath,
                (int)$_SESSION['escms_admin_id'],
            ]);

            log_activity(
                'backup_create',
                'Created full website backup',
                ['file' => $name]
            );

            flash(
                'success',
                'Full backup created with database content and uploaded files.'
            );
        } elseif ($action === 'restore') {
            $id = (int)($_POST['id'] ?? 0);
            $statement = $pdo->prepare('SELECT * FROM site_backups WHERE id = ?');
            $statement->execute([$id]);
            $backup = $statement->fetch();

            if (!$backup) {
                throw new RuntimeException('Backup not found.');
            }

            restore_full_backup(
                $pdo,
                $tables,
                ROOT_DIR . '/' . $backup['file_path']
            );

            log_activity(
                'backup_restore',
                'Restored full website backup',
                ['backup_id' => $id]
            );

            admin_notify(
                'warning',
                'Backup restored',
                'A full website backup was restored.',
                'backups.php'
            );

            flash('success', 'Backup restored successfully.');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $statement = $pdo->prepare(
                'SELECT file_path FROM site_backups WHERE id = ?'
            );
            $statement->execute([$id]);
            $filePath = $statement->fetchColumn();

            if ($filePath && is_file(ROOT_DIR . '/' . $filePath)) {
                @unlink(ROOT_DIR . '/' . $filePath);
            }

            $pdo->prepare('DELETE FROM site_backups WHERE id = ?')->execute([$id]);

            log_activity(
                'backup_delete',
                'Deleted website backup',
                ['backup_id' => $id]
            );

            flash('success', 'Backup deleted.');
        }

        header('Location: backups.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$rows = $pdo->query(
    'SELECT * FROM site_backups ORDER BY id DESC'
)->fetchAll();

$pageTitle = 'Backup & Restore';
$active = 'backups';
require __DIR__ . '/_header.php';
?>

<div class="page-heading backup-page-heading">
    <div>
        <p class="eyebrow">BACKUP & RESTORE</p>
        <h1>Protect website content</h1>
        <p class="muted">
            Create a ZIP restore point containing CMS data and uploaded files before important changes.
        </p>
    </div>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <button type="submit" <?= $backupReady ? '' : 'disabled' ?>>
            Create full backup now
        </button>
    </form>
</div>

<?php if ($error): ?>
    <div class="alert error"><?= h($error) ?></div>
<?php endif; ?>

<section class="panel backup-readiness-panel">
    <div class="panel-heading-row">
        <div>
            <p class="eyebrow">HOSTING READINESS</p>
            <h2><?= $backupReady ? 'Backup system is ready' : 'Hosting setup needs attention' ?></h2>
            <p class="muted">
                The CMS checks the server automatically so you know what must be enabled before creating a backup.
            </p>
        </div>

        <span class="badge <?= $backupReady ? 'contacted' : 'closed' ?>">
            <?= $backupReady ? 'Ready' : 'Action required' ?>
        </span>
    </div>

    <div class="backup-check-grid">
        <article class="backup-check-card <?= $zipAvailable ? 'ok' : 'warn' ?>">
            <span><?= $zipAvailable ? '✓' : '!' ?></span>
            <div>
                <strong>PHP ZIP extension</strong>
                <small>
                    <?= $zipAvailable
                        ? 'ZipArchive is available.'
                        : 'ZipArchive is not enabled on this PHP installation.' ?>
                </small>
            </div>
        </article>

        <article class="backup-check-card <?= $backupDirectoryReady ? 'ok' : 'warn' ?>">
            <span><?= $backupDirectoryReady ? '✓' : '!' ?></span>
            <div>
                <strong>Backup folder</strong>
                <small>
                    <?= $backupDirectoryReady
                        ? 'uploads/backups is writable.'
                        : 'uploads/backups is not writable.' ?>
                </small>
            </div>
        </article>

        <article class="backup-check-card <?= $uploadsDirectoryReady ? 'ok' : 'warn' ?>">
            <span><?= $uploadsDirectoryReady ? '✓' : '!' ?></span>
            <div>
                <strong>Uploads folder</strong>
                <small>
                    <?= $uploadsDirectoryReady
                        ? 'Uploaded website files can be read.'
                        : 'The uploads folder cannot be read.' ?>
                </small>
            </div>
        </article>
    </div>

    <?php if (!$backupReady): ?>
        <div class="backup-hosting-help">
            <strong>How to fix it</strong>
            <p>
                In cPanel this is commonly under <b>Select PHP Version</b>, <b>PHP Extensions</b>
                or your hosting PHP manager. Enable the <b>zip</b> extension. If your provider does
                not expose that option, ask support to enable <b>php-zip / ZipArchive</b> for this site.
            </p>
            <p>
                Folder problems normally require write permission for <code>uploads/backups</code>.
                The exact cPanel menu can vary by hosting provider.
            </p>
        </div>
    <?php endif; ?>
</section>

<div class="backup-grid">
    <?php if (!$rows): ?>
        <div class="empty-state backup-empty-state">
            <strong>No backups yet</strong>
            <p>
                <?= $backupReady
                    ? 'Create a restore point before making major site changes.'
                    : 'Complete the hosting checks above, then create your first restore point.' ?>
            </p>
        </div>
    <?php endif; ?>

    <?php foreach ($rows as $backup): ?>
        <article class="backup-card">
            <div>
                <span><?= icon('dashboard') ?></span>
                <strong><?= h($backup['backup_name']) ?></strong>
                <small><?= h($backup['created_at']) ?></small>
            </div>

            <div class="actions">
                <a
                    class="button secondary small"
                    href="../<?= h($backup['file_path']) ?>"
                    download
                >
                    Download ZIP
                </a>

                <form
                    method="post"
                    data-swal-confirm="Restore this backup?"
                    data-swal-text="Current CMS data and uploaded images may be replaced by this restore point."
                >
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="id" value="<?= (int)$backup['id'] ?>">
                    <button class="button small" type="submit">Restore</button>
                </form>

                <form
                    method="post"
                    data-swal-confirm="Delete this backup?"
                    data-swal-text="This backup ZIP will be permanently removed."
                >
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$backup['id'] ?>">
                    <button class="button danger-lite small" type="submit">Delete</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
