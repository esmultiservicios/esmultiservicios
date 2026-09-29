<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_permission('widgets.manage');

$message = '';
$type = 'success';

$validPositions = ['left', 'right'];
$validModes = ['none', 'whatsapp', 'external', 'both'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $mode = (string)($_POST['widget_mode'] ?? 'whatsapp');
        if (!in_array($mode, $validModes, true)) {
            $mode = 'whatsapp';
        }

        $waEnabled = in_array($mode, ['whatsapp', 'both'], true);
        $externalEnabled = in_array($mode, ['external', 'both'], true);
        $waPosition = in_array($_POST['whatsapp_position'] ?? 'right', $validPositions, true)
            ? (string)$_POST['whatsapp_position'] : 'right';
        $externalPosition = in_array($_POST['external_position'] ?? 'right', $validPositions, true)
            ? (string)$_POST['external_position'] : 'right';

        $externalName = trim((string)($_POST['external_name'] ?? 'External chat'));
        if ($externalName === '') {
            $externalName = 'External chat';
        }

        save_setting('whatsapp_enabled', $waEnabled ? '1' : '0');
        save_setting('whatsapp_position', $waPosition);
        save_setting('whatsapp_order', (string)max(0, (int)($_POST['whatsapp_order'] ?? 10)));
        save_setting('whatsapp_show_desktop', isset($_POST['whatsapp_show_desktop']) ? '1' : '0');
        save_setting('whatsapp_show_mobile', isset($_POST['whatsapp_show_mobile']) ? '1' : '0');

        save_setting('floating_external_enabled', $externalEnabled ? '1' : '0');
        save_setting('floating_external_name', $externalName);
        save_setting('floating_external_position', $externalPosition);
        save_setting('floating_external_order', (string)max(0, (int)($_POST['external_order'] ?? 20)));
        save_setting('floating_external_show_desktop', isset($_POST['external_show_desktop']) ? '1' : '0');
        save_setting('floating_external_show_mobile', isset($_POST['external_show_mobile']) ? '1' : '0');
        save_setting('floating_external_snippet', trim((string)($_POST['external_snippet'] ?? '')));
        save_setting('floating_widget_gap', (string)max(8, min(32, (int)($_POST['widget_gap'] ?? 12))));

        $message = 'Floating widgets saved.';
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $type = 'error';
    }
}

$cfg = settings();
$waOn = ($cfg['whatsapp_enabled'] ?? '1') === '1';
$externalOn = ($cfg['floating_external_enabled'] ?? '0') === '1';
$currentMode = $waOn && $externalOn ? 'both' : ($waOn ? 'whatsapp' : ($externalOn ? 'external' : 'none'));

$pageTitle = 'Floating Widgets';
$active = 'widgets';
require __DIR__ . '/_header.php';
?>
<div class="page-heading animate-in">
    <div>
        <p class="eyebrow">WIDGETS</p>
        <h1>Floating Widgets</h1>
        <p class="muted">Choose WhatsApp, an external chat widget, or both. Position and order are managed without editing frontend files.</p>
    </div>
</div>

<?php if ($message): ?>
    <div hidden data-flash-message="<?= h($message) ?>" data-flash-type="<?= h($type) ?>"></div>
<?php endif; ?>

<form class="panel form-stack widget-manager" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <section class="settings-block">
        <div class="section-heading">
            <div>
                <h2>Active widgets</h2>
                <p>Select exactly what should appear on the public website.</p>
            </div>
        </div>

        <div class="widget-mode-grid" role="radiogroup" aria-label="Active floating widgets">
            <?php
            $modes = [
                'whatsapp' => ['WhatsApp only', 'Show only the WhatsApp launcher.'],
                'external' => ['External chat only', 'Show only the configured external widget.'],
                'both' => ['WhatsApp + external chat', 'Show both and keep them automatically separated.'],
                'none' => ['None', 'Hide all managed floating chat widgets.'],
            ];
            foreach ($modes as $value => [$title, $desc]):
            ?>
                <label class="widget-mode-card <?= $currentMode === $value ? 'is-selected' : '' ?>">
                    <input type="radio" name="widget_mode" value="<?= h($value) ?>" <?= $currentMode === $value ? 'checked' : '' ?>>
                    <span class="widget-mode-indicator" aria-hidden="true"></span>
                    <span>
                        <b><?= h($title) ?></b>
                        <small><?= h($desc) ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="widget-manager-grid">
        <section class="settings-block widget-config-card" data-widget-config="whatsapp">
            <div class="section-heading">
                <div>
                    <h2>WhatsApp</h2>
                    <p>Uses the public WhatsApp number and default message already configured in Site Settings.</p>
                </div>
                <span class="widget-status-pill">Managed</span>
            </div>

            <div class="form-grid widget-form-grid">
                <label>
                    <span>Position</span>
                    <select name="whatsapp_position">
                        <option value="left" <?= ($cfg['whatsapp_position'] ?? 'right') === 'left' ? 'selected' : '' ?>>Left</option>
                        <option value="right" <?= ($cfg['whatsapp_position'] ?? 'right') === 'right' ? 'selected' : '' ?>>Right</option>
                    </select>
                    <small>Choose the corner used by the WhatsApp launcher.</small>
                </label>

                <label>
                    <span>Order</span>
                    <input type="number" min="0" name="whatsapp_order" value="<?= h($cfg['whatsapp_order'] ?? '10') ?>">
                    <small>Lower values stay closer to the bottom edge when widgets share a side.</small>
                </label>
            </div>

            <div class="widget-visibility-grid">
                <label class="toggle-line">
                    <input type="checkbox" name="whatsapp_show_desktop" <?= ($cfg['whatsapp_show_desktop'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span><b>Desktop / tablet</b><small>Show on larger screens.</small></span>
                </label>
                <label class="toggle-line">
                    <input type="checkbox" name="whatsapp_show_mobile" <?= ($cfg['whatsapp_show_mobile'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span><b>Mobile</b><small>Show on phones.</small></span>
                </label>
            </div>
        </section>

        <section class="settings-block widget-config-card" data-widget-config="external">
            <div class="section-heading">
                <div>
                    <h2>External chat widget</h2>
                    <p>For a trusted support/chat provider. The CMS controls its host container, position, order and visibility.</p>
                </div>
                <span class="widget-status-pill">Optional</span>
            </div>

            <div class="form-grid widget-form-grid">
                <label>
                    <span>Name</span>
                    <input name="external_name" maxlength="80" value="<?= h($cfg['floating_external_name'] ?? 'External chat') ?>">
                    <small>Administrative label and accessibility name.</small>
                </label>

                <label>
                    <span>Position</span>
                    <select name="external_position">
                        <option value="left" <?= ($cfg['floating_external_position'] ?? 'right') === 'left' ? 'selected' : '' ?>>Left</option>
                        <option value="right" <?= ($cfg['floating_external_position'] ?? 'right') === 'right' ? 'selected' : '' ?>>Right</option>
                    </select>
                    <small>Can share a side with WhatsApp without overlapping.</small>
                </label>

                <label>
                    <span>Order</span>
                    <input type="number" min="0" name="external_order" value="<?= h($cfg['floating_external_order'] ?? '20') ?>">
                    <small>Lower values stay closer to the bottom edge.</small>
                </label>
            </div>

            <div class="widget-visibility-grid">
                <label class="toggle-line">
                    <input type="checkbox" name="external_show_desktop" <?= ($cfg['floating_external_show_desktop'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span><b>Desktop / tablet</b><small>Show on larger screens.</small></span>
                </label>
                <label class="toggle-line">
                    <input type="checkbox" name="external_show_mobile" <?= ($cfg['floating_external_show_mobile'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span><b>Mobile</b><small>Show on phones.</small></span>
                </label>
            </div>

            <label class="widget-snippet-field">
                <span>Trusted embed snippet</span>
                <textarea name="external_snippet" rows="9" spellcheck="false" placeholder="Paste the trusted widget snippet here..."><?= h($cfg['floating_external_snippet'] ?? '') ?></textarea>
                <small>Only paste code from a provider you trust. Provider scripts that create their own fixed launcher may also need position settings inside that provider.</small>
            </label>
        </section>
    </div>

    <section class="settings-block">
        <div class="section-heading">
            <div>
                <h2>Spacing & collision safety</h2>
                <p>Managed widgets on the same side are stacked automatically. Social floating icons are also moved when needed to avoid collisions.</p>
            </div>
        </div>

        <div class="form-grid widget-form-grid">
            <label>
                <span>Gap between widgets</span>
                <input type="number" min="8" max="32" name="widget_gap" value="<?= h($cfg['floating_widget_gap'] ?? '12') ?>">
                <small>Recommended range: 8–20 px.</small>
            </label>
        </div>

        <div class="widget-layout-preview" aria-hidden="true">
            <div class="widget-preview-side">
                <span>LEFT</span>
                <i></i><i></i>
            </div>
            <div class="widget-preview-phone"></div>
            <div class="widget-preview-side">
                <span>RIGHT</span>
                <i></i><i></i>
            </div>
        </div>
    </section>

    <div class="form-actions widget-save-actions">
        <button class="button" type="submit">Save widget configuration</button>
    </div>
</form>

<script>
(() => {
    const cards = [...document.querySelectorAll('.widget-mode-card')];
    const sync = () => {
        cards.forEach(card => card.classList.toggle('is-selected', !!card.querySelector('input:checked')));
    };
    cards.forEach(card => card.addEventListener('change', sync));
    sync();
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
