<?php
require __DIR__.'/bootstrap.php';
require_permission('email.manage');
require_once __DIR__.'/../core/EmailService.php';

$pdo = db();
$mailer = new EmailService();
$error = '';

function email_schema_has_column(PDO $pdo, string $column): bool
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'correo' AND COLUMN_NAME = ?");
    $st->execute([$column]);
    return (int)$st->fetchColumn() > 0;
}

$emailSchemaReady = email_schema_has_column($pdo, 'destinatario') && email_schema_has_column($pdo, 'copia');

function email_config_payload(array $source, ?array $old = null): array
{
    $method = in_array(($source['metodo_envio'] ?? 'SMTP'), ['SMTP', 'GRAPH'], true)
        ? (string)$source['metodo_envio']
        : 'SMTP';

    $password = trim((string)($source['password'] ?? ''));
    $clientSecret = trim((string)($source['client_secret'] ?? ''));

    $payload = [
        'correo_tipo_id' => (int)($source['correo_tipo_id'] ?? 1),
        'metodo_envio' => $method,
        'server' => trim((string)($source['server'] ?? '')),
        'correo' => trim((string)($source['correo'] ?? '')),
        'destinatario' => trim((string)($source['destinatario'] ?? '')),
        'copia' => trim((string)($source['copia'] ?? '')),
        'password' => $password !== '' ? secret_encrypt($password) : (string)($old['password'] ?? ''),
        'port' => (int)($source['port'] ?? 587),
        'smtp_secure' => strtolower(trim((string)($source['smtp_secure'] ?? 'tls'))),
        'tenant_id' => trim((string)($source['tenant_id'] ?? '')) ?: null,
        'client_id' => trim((string)($source['client_id'] ?? '')) ?: null,
        'client_secret' => $clientSecret !== '' ? secret_encrypt($clientSecret) : (string)($old['client_secret'] ?? ''),
        'graph_user' => trim((string)($source['graph_user'] ?? '')) ?: null,
        'save_to_sent_items' => isset($source['save_to_sent_items']) ? 1 : 0,
        'estado' => isset($source['estado']) ? 1 : 2,
    ];

    if ($method === 'GRAPH') {
        $payload['server'] = 'graph.microsoft.com';
        $payload['port'] = 0;
        $payload['smtp_secure'] = '';
        // Graph User / mailbox is the sender. Do not require the same address twice.
        $payload['correo'] = trim((string)($payload['graph_user'] ?? ''));
    } else {
        $payload['tenant_id'] = null;
        $payload['client_id'] = null;
        $payload['client_secret'] = '';
        $payload['graph_user'] = null;
        $payload['save_to_sent_items'] = 0;
    }

    return $payload;
}

function save_email_config(PDO $pdo, array $cfg, int $id = 0): int
{
    $params = [
        $cfg['correo_tipo_id'], $cfg['metodo_envio'], $cfg['server'], $cfg['correo'],
        $cfg['destinatario'] ?: null, $cfg['copia'] ?: null, $cfg['password'], $cfg['port'],
        $cfg['smtp_secure'], $cfg['tenant_id'], $cfg['client_id'], $cfg['client_secret'],
        $cfg['graph_user'], $cfg['save_to_sent_items'], $cfg['estado'],
    ];

    if ($id > 0) {
        $sql = 'UPDATE correo SET correo_tipo_id=?,metodo_envio=?,server=?,correo=?,destinatario=?,copia=?,password=?,port=?,smtp_secure=?,tenant_id=?,client_id=?,client_secret=?,graph_user=?,save_to_sent_items=?,estado=? WHERE correo_id=?';
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        return $id;
    }

    $sql = 'INSERT INTO correo(correo_tipo_id,metodo_envio,server,correo,destinatario,copia,password,port,smtp_secure,tenant_id,client_id,client_secret,graph_user,save_to_sent_items,estado,fecha_registro) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())';
    $pdo->prepare($sql)->execute($params);
    return (int)$pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'save') {
            if (!$emailSchemaReady) {
                throw new RuntimeException('Email database update pending. Run database-update.sql once, then reload this page.');
            }
            $old = $id ? $mailer->configById($id) : null;
            $cfg = email_config_payload($_POST, $old);

            if ($cfg['estado'] === 1) {
                $errors = $mailer->validateConfig($cfg);
                if ($errors) throw new RuntimeException(implode(' ', $errors));
            } else {
                // Even inactive configurations must have a valid transport sender,
                // but Graph uses Graph User / mailbox as the sender automatically.
                $sender = $cfg['metodo_envio'] === 'GRAPH'
                    ? trim((string)($cfg['graph_user'] ?? ''))
                    : trim((string)($cfg['correo'] ?? ''));
                if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException($cfg['metodo_envio'] === 'GRAPH'
                        ? 'Enter a valid Graph User / mailbox.'
                        : 'Enter a valid SMTP user / sender email.');
                }
            }

            $pdo->beginTransaction();
            $savedId = save_email_config($pdo, $cfg, $id);
            if ($cfg['estado'] === 1) {
                $off = $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=? AND correo_id<>?');
                $off->execute([$cfg['correo_tipo_id'], $savedId]);
            }
            $pdo->commit();

            log_activity('email_configuration_saved', 'Saved email delivery configuration', [
                'correo_id' => $savedId,
                'correo_tipo_id' => $cfg['correo_tipo_id'],
                'method' => $cfg['metodo_envio'],
            ]);
            flash('success', 'Email configuration saved.');
            header('Location: email.php?edit='.$savedId);
            exit;
        }

        if ($action === 'delete' && $id) {
            $pdo->prepare('DELETE FROM correo WHERE correo_id=?')->execute([$id]);
            log_activity('email_configuration_deleted', 'Deleted email delivery configuration', ['correo_id' => $id]);
            flash('success', 'Email configuration deleted.');
            header('Location: email.php');
            exit;
        }

        if ($action === 'toggle' && $id) {
            $cfg = $mailer->configById($id);
            if (!$cfg) throw new RuntimeException('Email configuration not found.');

            if ((int)$cfg['estado'] === 1) {
                $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_id=?')->execute([$id]);
                flash('success', 'Email configuration deactivated.');
            } else {
                $errors = $mailer->validateConfig($cfg);
                if ($errors) throw new RuntimeException('Cannot activate this configuration. '.implode(' ', $errors));

                $pdo->beginTransaction();
                $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=?')->execute([(int)$cfg['correo_tipo_id']]);
                $pdo->prepare('UPDATE correo SET estado=1 WHERE correo_id=?')->execute([$id]);
                $pdo->commit();
                flash('success', 'Email configuration activated.');
            }
            header('Location: email.php?edit='.$id);
            exit;
        }

        if ($action === 'test' && $id) {
            $res = $mailer->test($id, trim((string)($_POST['test_to'] ?? '')));
            flash($res['success'] ? 'success' : 'error', $res['message']);
            header('Location: email.php?edit='.$id);
            exit;
        }

        if ($action === 'copy' && $id) {
            if (!$emailSchemaReady) {
                throw new RuntimeException('Email database update pending. Run database-update.sql once, then reload this page.');
            }
            $source = $mailer->configById($id);
            if (!$source) throw new RuntimeException('Email configuration not found.');

            $targetTypes = array_values(array_unique(array_filter(
                array_map('intval', (array)($_POST['target_types'] ?? [])),
                static fn(int $type): bool => $type > 0 && $type !== (int)$source['correo_tipo_id']
            )));
            if (!$targetTypes) throw new RuntimeException('Choose at least one different email purpose to copy this configuration to.');

            $validTypes = $pdo->query('SELECT correo_tipo_id FROM correo_tipo')->fetchAll(PDO::FETCH_COLUMN);
            $validTypes = array_map('intval', $validTypes);
            $targetTypes = array_values(array_intersect($targetTypes, $validTypes));
            if (!$targetTypes) throw new RuntimeException('No valid destination email purpose was selected.');

            $created = 0;
            $pdo->beginTransaction();
            foreach ($targetTypes as $targetType) {
                $copy = $source;
                unset($copy['correo_id'], $copy['fecha_registro'], $copy['updated_at']);
                $copy['correo_tipo_id'] = $targetType;
                $copy['estado'] = 1;

                $errors = $mailer->validateConfig($copy);
                if ($errors) {
                    throw new RuntimeException('The copied configuration cannot be activated for one of the selected purposes. '.implode(' ', $errors));
                }

                $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=?')->execute([$targetType]);
                save_email_config($pdo, $copy, 0);
                $created++;
            }
            $pdo->commit();

            log_activity('email_configuration_copied', 'Copied email delivery configuration to other purposes', [
                'source_correo_id' => $id,
                'target_types' => $targetTypes,
            ]);
            flash('success', $created.' email configuration'.($created === 1 ? ' was' : 's were').' copied and activated.');
            header('Location: email.php?edit='.$id);
            exit;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException) {
            $error = 'The email configuration could not be saved. Verify that database-update.sql has been applied and try again.';
        } else {
            $error = $e->getMessage();
        }
    }
}

$types = $pdo->query('SELECT * FROM correo_tipo ORDER BY correo_tipo_id')->fetchAll();
$rows = $pdo->query('SELECT c.*,t.nombre tipo_nombre FROM correo c JOIN correo_tipo t ON t.correo_tipo_id=c.correo_tipo_id ORDER BY c.correo_id DESC')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) $edit = $mailer->configById((int)$_GET['edit']);

$pageTitle = 'Email Configuration';
$active = 'email';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">EMAIL</p>
        <h1>SMTP & Microsoft Graph</h1>
        <p class="muted">Configure purpose-specific senders, recipients and secure delivery for website messages.</p>
    </div>
    <a class="button secondary" href="email.php"><?= icon('plus') ?> New configuration</a>
</div>

<?php if (!$emailSchemaReady): ?>
    <div class="alert error">Email database update pending. Run <strong>database-update.sql</strong> once, then use <strong>Recheck / reload</strong>. Existing settings are not deleted.</div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert error"><?= h($error) ?></div>
<?php endif; ?>

<section class="panel animate-in">
    <div class="panel-heading">
        <div class="panel-icon"><?= icon('mail') ?></div>
        <div>
            <h2><?= $edit ? 'Edit configuration' : 'Add email configuration' ?></h2>
            <p>Passwords and Client Secret VALUE are encrypted before being stored. Saved secrets are never displayed again.</p>
        </div>
    </div>

    <form method="post" class="email-config-form">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h((string)($edit['correo_id'] ?? 0)) ?>">

        <div class="three-col email-config-topline">
            <label>Email purpose
                <select name="correo_tipo_id">
                    <?php foreach ($types as $t): ?>
                        <option value="<?= (int)$t['correo_tipo_id'] ?>" <?= (int)($edit['correo_tipo_id'] ?? 1) === (int)$t['correo_tipo_id'] ? 'selected' : '' ?>><?= h($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Method
                <select name="metodo_envio" data-method-select>
                    <option value="SMTP" <?= ($edit['metodo_envio'] ?? 'SMTP') === 'SMTP' ? 'selected' : '' ?>>SMTP</option>
                    <option value="GRAPH" <?= ($edit['metodo_envio'] ?? '') === 'GRAPH' ? 'selected' : '' ?>>Microsoft Graph</option>
                </select>
            </label>

            <label class="premium-switch email-active-switch">
                <input type="checkbox" name="estado" <?= !$edit || (int)($edit['estado'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span class="switch-ui" aria-hidden="true"></span>
                <span><b>Active configuration</b><small>Incomplete settings cannot be activated.</small></span>
            </label>
        </div>

        <div class="two-col email-address-grid">
            <label>Internal destination
                <input type="email" name="destinatario" value="<?= h($edit['destinatario'] ?? '') ?>" placeholder="Optional — leave blank to use the sender mailbox">
                <small>Optional. If you enter an address, messages use it as the internal destination. If left blank, the selected SMTP user or Microsoft Graph mailbox is used automatically.</small>
            </label>
            <label class="email-cc-field">Optional hidden copy (BCC / CCO)
                <input name="copia" value="<?= h($edit['copia'] ?? '') ?>" placeholder="Optional — leave blank for no hidden copy">
                <small>Optional. Leave blank to send no hidden copy. If you add one or more addresses, they are sent as BCC/CCO through the same selected SMTP or Microsoft Graph connection and are not visible to other recipients. Separate multiple addresses with commas or semicolons.</small>
            </label>
        </div>

        <div data-method="SMTP" class="email-method-panel">
            <div class="method-heading">
                <div>
                    <strong>SMTP connection</strong>
                    <small>For Gmail use smtp.gmail.com, port 587 and TLS. Use an app password when your account requires it.</small>
                </div>
            </div>
            <div class="two-col email-method-grid smtp-primary-grid">
                <label class="email-field">SMTP server<input name="server" value="<?= h(($edit['metodo_envio'] ?? 'SMTP') === 'SMTP' ? ($edit['server'] ?? '') : '') ?>" placeholder="smtp.gmail.com"></label>
                <label class="email-field">SMTP user / sender email
                    <input type="email" name="correo" autocomplete="email" value="<?= h(($edit['metodo_envio'] ?? 'SMTP') === 'SMTP' ? ($edit['correo'] ?? '') : '') ?>" placeholder="sender@example.com">
                    <small>This address is used as the sender and as the SMTP login username.</small>
                </label>
            </div>
            <div class="two-col email-method-grid smtp-options-grid">
                <label class="email-field">Port<input type="number" min="1" max="65535" name="port" value="<?= h((string)($edit['port'] ?? 587)) ?>"></label>
                <label class="email-field">Security
                    <select name="smtp_secure">
                        <option value="tls" <?= ($edit['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ($edit['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    </select>
                </label>
            </div>
            <label class="email-field email-secret-field smtp-password-field">SMTP password / app password
                <input type="password" name="password" autocomplete="new-password" placeholder="<?= $edit && !empty($edit['password']) ? 'Saved securely — leave blank to keep it' : 'Enter SMTP password or app password' ?>">
            </label>
        </div>

        <div data-method="GRAPH" class="email-method-panel">
            <div class="method-heading">
                <div>
                    <strong>Microsoft Graph connection</strong>
                    <small>Uses Tenant ID, Client ID and Client Secret VALUE. The mailbox password is never requested.</small>
                </div>
            </div>
            <div class="two-col email-method-grid">
                <label class="email-field">Tenant ID<input name="tenant_id" value="<?= h($edit['tenant_id'] ?? '') ?>" autocomplete="off"></label>
                <label class="email-field">Client ID<input name="client_id" value="<?= h($edit['client_id'] ?? '') ?>" autocomplete="off"></label>
            </div>
            <div class="two-col email-method-grid graph-credentials-grid">
                <label class="email-field email-secret-field">Client Secret VALUE
                    <input type="password" name="client_secret" autocomplete="new-password" placeholder="<?= $edit && !empty($edit['client_secret']) ? 'Saved securely — leave blank to keep it' : 'Enter Microsoft Entra Client Secret VALUE' ?>">
                </label>
                <label class="email-field graph-user-field">Graph User / mailbox
                    <input type="email" name="graph_user" value="<?= h($edit['graph_user'] ?? '') ?>" placeholder="mailbox@example.com">
                    <small>This mailbox is used as the sender by Microsoft Graph. The mailbox password is not required.</small>
                </label>
            </div>
            <label class="premium-switch sent-items-switch">
                <input type="checkbox" name="save_to_sent_items" <?= !$edit || !empty($edit['save_to_sent_items']) ? 'checked' : '' ?>>
                <span class="switch-ui" aria-hidden="true"></span>
                <span><b>Save a copy in Sent Items</b><small>Microsoft Graph will keep the message in the sender mailbox.</small></span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit"><?= icon('save') ?> Save email configuration</button>
            <?php if ($edit): ?><a class="button secondary" href="email.php">Cancel edit</a><?php endif; ?>
        </div>
    </form>
</section>

<?php if ($edit): ?>
<section class="panel email-test-panel animate-in">
    <div class="panel-heading">
        <div class="panel-icon"><?= icon('mail') ?></div>
        <div>
            <h2>Test this configuration</h2>
            <p>Send a real message through the selected SMTP or Microsoft Graph connection.</p>
        </div>
    </div>
    <form method="post" class="test-email-form">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="test">
        <input type="hidden" name="id" value="<?= (int)$edit['correo_id'] ?>">
        <label>Test destination email<input type="email" name="test_to" placeholder="Optional — uses configured destination or sender mailbox" value="<?= h($edit['destinatario'] ?: ($edit['metodo_envio'] === 'GRAPH' ? ($edit['graph_user'] ?? '') : ($edit['correo'] ?? ''))) ?>"><small>Optional. Leave blank to use Internal destination; if that is also blank, the SMTP user or Graph mailbox is used.</small></label>
        <div class="form-actions"><button class="button"><?= icon('mail') ?> Send real test</button></div>
    </form>
</section>

<section class="panel email-copy-panel animate-in">
    <div class="panel-heading">
        <div class="panel-icon"><?= icon('tools') ?></div>
        <div>
            <h2>Copy this connection</h2>
            <p>Reuse the same SMTP or Microsoft Graph credentials for other email purposes without configuring them one by one.</p>
        </div>
    </div>
    <form method="post" class="email-copy-form">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="copy">
        <input type="hidden" name="id" value="<?= (int)$edit['correo_id'] ?>">
        <div class="email-copy-types">
            <?php foreach ($types as $t): if ((int)$t['correo_tipo_id'] === (int)$edit['correo_tipo_id']) continue; ?>
                <label class="check-row">
                    <input type="checkbox" name="target_types[]" value="<?= (int)$t['correo_tipo_id'] ?>">
                    <span><b><?= h($t['nombre']) ?></b><small>Copy sender, method, encrypted credentials, destination and hidden copy (BCC/CCO).</small></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="copy-warning">Copying activates the new configuration for each selected purpose and deactivates any previous active configuration of that same purpose.</div>
        <div class="form-actions"><button type="submit"><?= icon('tools') ?> Copy to selected purposes</button></div>
    </form>
</section>
<?php else: ?>
<div class="info-strip"><strong>Want to test or reuse it?</strong><span>Save the configuration first. Test and copy tools will appear immediately.</span></div>
<?php endif; ?>

<div class="section-heading">
    <div><p class="eyebrow">CONFIGURED SENDERS</p><h2>Email connections</h2></div>
</div>

<?php if (!$rows): ?>
<div class="empty-state"><strong>No email configured</strong><p>Add SMTP or Microsoft Graph above.</p></div>
<?php else: ?>
<div class="email-grid">
    <?php foreach ($rows as $r): ?>
    <article class="email-card animate-in">
        <div class="list-head">
            <div>
                <span class="email-method"><?= h($r['metodo_envio']) ?></span>
                <h3><?= h($r['tipo_nombre']) ?></h3>
                <small><?= h($r['correo']) ?></small>
            </div>
            <span class="badge <?= (int)$r['estado'] === 1 ? 'contacted' : 'closed' ?>"><?= (int)$r['estado'] === 1 ? 'Active' : 'Inactive' ?></span>
        </div>
        <p class="muted"><?= $r['metodo_envio'] === 'GRAPH' ? 'Microsoft 365 / Graph mailbox: '.h($r['graph_user'] ?: $r['correo']) : 'SMTP: '.h($r['server']).':'.(int)$r['port'] ?></p>
        <?php if (!empty($r['destinatario'])): ?><p class="email-card-meta"><b>Destination:</b> <?= h($r['destinatario']) ?></p><?php endif; ?>
        <?php if (!empty($r['copia'])): ?><p class="email-card-meta"><b>BCC / CCO:</b> <?= h($r['copia']) ?></p><?php endif; ?>
        <div class="actions email-card-actions">
            <form method="post" class="email-card-test">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="test">
                <input type="hidden" name="id" value="<?= (int)$r['correo_id'] ?>">
                <input type="email" name="test_to" placeholder="Test destination">
                <button class="button small">Send test</button>
            </form>
            <details class="action-menu">
                <summary>Actions ▾</summary>
                <nav>
                    <a href="?edit=<?= (int)$r['correo_id'] ?>">Edit / Copy</a>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$r['correo_id'] ?>">
                        <button><?= (int)$r['estado'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                    </form>
                    <form method="post" data-swal-confirm="Delete this email configuration?" data-swal-text="Email delivery using this configuration will stop.">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$r['correo_id'] ?>">
                        <button class="danger-text">Delete</button>
                    </form>
                </nav>
            </details>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__.'/_footer.php'; ?>
