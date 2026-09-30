<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_permission('widgets.manage');

$message = '';
$type = 'success';
$validPositions = ['left', 'right'];
$validKinds = ['embed', 'url'];

function normalize_external_widget(array $row, int $fallbackOrder = 20): array {
    global $validPositions, $validKinds;
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($row['id'] ?? '')) ?: ('widget_' . bin2hex(random_bytes(4)));
    $name = trim((string)($row['name'] ?? 'Widget externo'));
    if ($name === '') $name = 'Widget externo';
    $kind = in_array((string)($row['kind'] ?? 'embed'), $validKinds, true) ? (string)$row['kind'] : 'embed';
    $url = trim((string)($row['url'] ?? ''));
    $snippet = trim((string)($row['snippet'] ?? ''));
    // Friendly recovery: if someone pastes a <script> installation code into the URL field,
    // automatically treat it as installation code instead of rejecting the configuration.
    if ($snippet === '' && preg_match('/<\s*script\b/i', $url)) {
        $snippet = $url;
        $url = '';
        $kind = 'embed';
    }
    $position = in_array((string)($row['position'] ?? 'right'), $validPositions, true) ? (string)$row['position'] : 'right';
    return [
        'id' => $id,
        'name' => mb_substr($name, 0, 80),
        'enabled' => !empty($row['enabled']) ? 1 : 0,
        'kind' => $kind,
        'position' => $position,
        'order' => max(0, min(999, (int)($row['order'] ?? $fallbackOrder))),
        'show_desktop' => !empty($row['show_desktop']) ? 1 : 0,
        'show_mobile' => !empty($row['show_mobile']) ? 1 : 0,
        'url' => $url,
        'snippet' => $snippet,
    ];
}

function external_widgets_from_settings(array $cfg): array {
    $raw = trim((string)($cfg['floating_widgets_json'] ?? ''));
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $out = [];
            foreach ($decoded as $i => $row) {
                if (is_array($row)) $out[] = normalize_external_widget($row, 20 + ($i * 10));
            }
            if ($out) return $out;
        }
    }

    // Backward-compatible migration from the original single external widget.
    $legacyName = trim((string)($cfg['floating_external_name'] ?? 'NIVO Web Chat')) ?: 'NIVO Web Chat';
    return [[
        'id' => 'nivo_web_chat',
        'name' => $legacyName,
        'enabled' => (($cfg['floating_external_enabled'] ?? '0') === '1') ? 1 : 0,
        'kind' => trim((string)($cfg['floating_external_snippet'] ?? '')) !== '' ? 'embed' : 'url',
        'position' => in_array(($cfg['floating_external_position'] ?? 'right'), ['left','right'], true) ? $cfg['floating_external_position'] : 'right',
        'order' => max(0, (int)($cfg['floating_external_order'] ?? 20)),
        'show_desktop' => (($cfg['floating_external_show_desktop'] ?? '1') === '1') ? 1 : 0,
        'show_mobile' => (($cfg['floating_external_show_mobile'] ?? '1') === '1') ? 1 : 0,
        'url' => trim((string)($cfg['floating_external_url'] ?? '')),
        'snippet' => trim((string)($cfg['floating_external_snippet'] ?? '')),
    ]];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $phoneDigits = preg_replace('/\D+/', '', (string)($_POST['whatsapp_phone_digits'] ?? '')) ?: '';
        if ($phoneDigits !== '') save_setting('phone_digits', $phoneDigits);
        save_setting('whatsapp_enabled', isset($_POST['whatsapp_enabled']) ? '1' : '0');
        save_setting('whatsapp_message', trim((string)($_POST['whatsapp_message'] ?? '')));
        save_setting('whatsapp_position', in_array($_POST['whatsapp_position'] ?? 'left', $validPositions, true) ? (string)$_POST['whatsapp_position'] : 'left');
        save_setting('whatsapp_order', (string)max(0, min(999, (int)($_POST['whatsapp_order'] ?? 10))));
        save_setting('whatsapp_show_desktop', isset($_POST['whatsapp_show_desktop']) ? '1' : '0');
        save_setting('whatsapp_show_mobile', isset($_POST['whatsapp_show_mobile']) ? '1' : '0');
        save_setting('floating_widget_gap', (string)max(8, min(40, (int)($_POST['widget_gap'] ?? 12))));

        $externalRows = [];
        foreach ((array)($_POST['widgets'] ?? []) as $i => $row) {
            if (!is_array($row)) continue;
            $normalized = normalize_external_widget($row, 20 + ((int)$i * 10));
            $hasContent = $normalized['kind'] === 'embed'
                ? $normalized['snippet'] !== ''
                : ($normalized['url'] !== '' && filter_var($normalized['url'], FILTER_VALIDATE_URL));
            // Keep incomplete rows so the administrator can prepare them before enabling.
            if ($normalized['enabled'] && !$hasContent) {
                $normalized['enabled'] = 0;
            }
            $externalRows[] = $normalized;
        }
        if (count($externalRows) > 20) $externalRows = array_slice($externalRows, 0, 20);
        save_setting('floating_widgets_json', json_encode($externalRows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Preserve legacy keys with the first external widget for compatibility with older templates.
        $first = $externalRows[0] ?? null;
        if ($first) {
            save_setting('floating_external_enabled', $first['enabled'] ? '1' : '0');
            save_setting('floating_external_name', $first['name']);
            save_setting('floating_external_position', $first['position']);
            save_setting('floating_external_order', (string)$first['order']);
            save_setting('floating_external_show_desktop', $first['show_desktop'] ? '1' : '0');
            save_setting('floating_external_show_mobile', $first['show_mobile'] ? '1' : '0');
            save_setting('floating_external_url', $first['url']);
            save_setting('floating_external_snippet', $first['snippet']);
        } else {
            save_setting('floating_external_enabled', '0');
        }

        $message = 'Floating widgets saved.';
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $type = 'error';
    }
}

$cfg = settings();
$externalWidgets = external_widgets_from_settings($cfg);
$pageTitle = 'Floating Widgets';
$active = 'widgets';
require __DIR__ . '/_header.php';
?>
<div class="page-heading animate-in">
    <div>
        <p class="eyebrow">WIDGETS</p>
        <h1>Widgets flotantes</h1>
        <p class="muted">Administra WhatsApp, NIVO Web Chat y cualquier widget futuro desde un solo lugar. Elige el lado, el orden y dónde debe mostrarse, sin que los botones se monten entre sí.</p>
    </div>
</div>

<?php if ($message): ?>
    <div hidden data-flash-message="<?= h($message) ?>" data-flash-type="<?= h($type) ?>"></div>
<?php endif; ?>

<form class="panel form-stack widget-manager" method="post" id="floating-widget-form">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <section class="settings-block widget-config-card">
        <div class="section-heading">
            <div>
                <h2>WhatsApp</h2>
                <p>Botón de contacto directo. Recomendado: lado izquierdo.</p>
            </div>
            <span class="widget-status-pill">Integrado</span>
        </div>

        <label class="premium-switch widget-master-toggle">
            <input type="checkbox" name="whatsapp_enabled" <?= ($cfg['whatsapp_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span class="switch-ui" aria-hidden="true"></span>
            <span><b>Mostrar WhatsApp</b><small>Activa o desactiva el botón flotante en el sitio público.</small></span>
        </label>

        <div class="form-grid widget-form-grid">
            <label>
                <span>Número de WhatsApp</span>
                <input name="whatsapp_phone_digits" inputmode="numeric" value="<?= h($cfg['phone_digits'] ?? '50489136844') ?>" placeholder="50489136844">
                <small>Solo números, incluyendo el código de país. Ejemplo: 50489136844.</small>
            </label>
            <label>
                <span>Posición</span>
                <select name="whatsapp_position">
                    <option value="left" <?= ($cfg['whatsapp_position'] ?? 'left') === 'left' ? 'selected' : '' ?>>Izquierda</option>
                    <option value="right" <?= ($cfg['whatsapp_position'] ?? 'left') === 'right' ? 'selected' : '' ?>>Derecha</option>
                </select>
            </label>
            <label>
                <span>Orden</span>
                <input type="number" min="0" max="999" name="whatsapp_order" value="<?= h($cfg['whatsapp_order'] ?? '10') ?>">
                <small>Un número menor queda más cerca de la parte inferior.</small>
            </label>
        </div>

        <label>
            <span>Mensaje predeterminado de WhatsApp</span>
            <textarea name="whatsapp_message" rows="3"><?= h($cfg['whatsapp_message'] ?? 'Hola, quiero información sobre las soluciones de ES MULTISERVICIOS.') ?></textarea>
        </label>

        <div class="widget-visibility-grid">
            <label class="premium-switch"><input type="checkbox" name="whatsapp_show_desktop" <?= ($cfg['whatsapp_show_desktop'] ?? '1') === '1' ? 'checked' : '' ?>><span class="switch-ui" aria-hidden="true"></span><span><b>Computadora / tablet</b><small>Mostrar en pantallas grandes.</small></span></label>
            <label class="premium-switch"><input type="checkbox" name="whatsapp_show_mobile" <?= ($cfg['whatsapp_show_mobile'] ?? '1') === '1' ? 'checked' : '' ?>><span class="switch-ui" aria-hidden="true"></span><span><b>Móvil</b><small>Mostrar en teléfonos.</small></span></label>
        </div>
    </section>

    <section class="settings-block">
        <div class="section-heading widget-section-heading">
            <div>
                <h2>Otros widgets</h2>
                <p>Aquí configuras NIVO Web Chat y cualquier otro chat, soporte o botón externo que agregues en el futuro.</p>
            </div>
            <button class="button widget-add-button" type="button" id="add-external-widget">+ Agregar widget</button>
        </div>

        <div class="widget-help-callout">
            <div class="widget-help-icon">i</div>
            <div><strong>¿Dónde pego el código de NIVO?</strong><p>En NIVO Web Chat deja <b>“Código de instalación (recomendado)”</b> y pega el código completo que comienza con <code>&lt;script</code> en el campo <b>“Código de instalación”</b>. El campo URL se usa únicamente cuando un proveedor entrega una dirección https:// directa.</p></div>
        </div>

        <div id="external-widget-list" class="external-widget-list">
            <?php foreach ($externalWidgets as $i => $widget): ?>
                <article class="external-widget-editor" data-widget-editor>
                    <div class="external-widget-editor-head">
                        <div>
                            <strong data-widget-title><?= h($widget['name']) ?></strong>
                            <small>Posición y orden independientes</small>
                        </div>
                        <button class="button danger widget-remove-button" type="button" data-remove-widget>Eliminar</button>
                    </div>
                    <input type="hidden" name="widgets[<?= $i ?>][id]" value="<?= h($widget['id']) ?>">
                    <div class="widget-visibility-grid widget-enabled-row">
                        <label class="premium-switch"><input type="checkbox" name="widgets[<?= $i ?>][enabled]" value="1" <?= $widget['enabled'] ? 'checked' : '' ?>><span class="switch-ui" aria-hidden="true"></span><span><b>Activo</b><small>Mostrar este widget cuando tenga una configuración válida.</small></span></label>
                        <label class="premium-switch"><input type="checkbox" name="widgets[<?= $i ?>][show_desktop]" value="1" <?= $widget['show_desktop'] ? 'checked' : '' ?>><span class="switch-ui" aria-hidden="true"></span><span><b>Computadora / tablet</b><small>Mostrar en pantallas grandes.</small></span></label>
                        <label class="premium-switch"><input type="checkbox" name="widgets[<?= $i ?>][show_mobile]" value="1" <?= $widget['show_mobile'] ? 'checked' : '' ?>><span class="switch-ui" aria-hidden="true"></span><span><b>Móvil</b><small>Mostrar en teléfonos.</small></span></label>
                    </div>
                    <div class="form-grid widget-form-grid">
                        <label><span>Nombre</span><input name="widgets[<?= $i ?>][name]" maxlength="80" value="<?= h($widget['name']) ?>" data-widget-name></label>
                        <label><span>Cómo se instala</span><select name="widgets[<?= $i ?>][kind]" data-widget-kind><option value="embed" <?= $widget['kind'] === 'embed' ? 'selected' : '' ?>>Código de instalación (recomendado)</option><option value="url" <?= $widget['kind'] === 'url' ? 'selected' : '' ?>>URL embebible</option></select><small>Para NIVO selecciona “Código de instalación”.</small></label>
                        <label><span>Posición</span><select name="widgets[<?= $i ?>][position]"><option value="left" <?= $widget['position'] === 'left' ? 'selected' : '' ?>>Izquierda</option><option value="right" <?= $widget['position'] === 'right' ? 'selected' : '' ?>>Derecha</option></select></label>
                        <label><span>Orden</span><input type="number" min="0" max="999" name="widgets[<?= $i ?>][order]" value="<?= (int)$widget['order'] ?>"><small>Un número menor queda más cerca de la parte inferior del lado seleccionado.</small></label>
                    </div>
                    <label class="widget-snippet-field" data-url-field <?= $widget['kind'] === 'url' ? '' : 'hidden' ?>><span>URL embebible del widget</span><input type="url" name="widgets[<?= $i ?>][url]" value="<?= h($widget['url']) ?>" placeholder="https://..."><small>Úsala solo si el proveedor te entrega una URL directa para embeber. No pegues aquí código &lt;script&gt;.</small></label>
                    <label class="widget-snippet-field" data-embed-field <?= $widget['kind'] === 'embed' ? '' : 'hidden' ?>><span>Código de instalación</span><textarea name="widgets[<?= $i ?>][snippet]" rows="6" spellcheck="false" placeholder="Pega aquí el código completo que te entrega ZYNKO (debe comenzar con &lt;script y terminar con &lt;/script&gt;)"><?= h($widget['snippet']) ?></textarea><small><strong>Para NIVO:</strong> pega aquí el código completo &lt;script ...&gt;&lt;/script&gt; que genera ZYNKO. Este es el campo correcto para tu código.</small></label>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="settings-block">
        <div class="section-heading">
            <div><h2>Separación y prevención de cruces</h2><p>Los widgets se organizan por separado a la izquierda y a la derecha. Si varios comparten un lado, el sistema aplica automáticamente el orden y la separación para evitar que se monten.</p></div>
        </div>
        <div class="form-grid widget-form-grid">
            <label><span>Separación entre widgets</span><input type="number" min="8" max="40" name="widget_gap" value="<?= h($cfg['floating_widget_gap'] ?? '12') ?>"><small>Recomendado: 12–20 px.</small></label>
        </div>
        <div class="widget-layout-preview" aria-hidden="true"><div class="widget-preview-side"><span>LEFT</span><i></i><i></i></div><div class="widget-preview-phone"></div><div class="widget-preview-side"><span>RIGHT</span><i></i><i></i></div></div>
    </section>

    <div class="form-actions widget-save-actions"><button class="button" type="submit">Guardar widgets</button></div>
</form>

<template id="external-widget-template">
    <article class="external-widget-editor" data-widget-editor>
        <div class="external-widget-editor-head"><div><strong data-widget-title>New external widget</strong><small>Posición y orden independientes</small></div><button class="button danger widget-remove-button" type="button" data-remove-widget>Eliminar</button></div>
        <input type="hidden" name="widgets[__INDEX__][id]" value="widget___INDEX__">
        <div class="widget-visibility-grid widget-enabled-row">
            <label class="premium-switch"><input type="checkbox" name="widgets[__INDEX__][enabled]" value="1"><span class="switch-ui" aria-hidden="true"></span><span><b>Activo</b><small>Mostrar este widget cuando tenga una configuración válida.</small></span></label>
            <label class="premium-switch"><input type="checkbox" name="widgets[__INDEX__][show_desktop]" value="1" checked><span class="switch-ui" aria-hidden="true"></span><span><b>Computadora / tablet</b><small>Mostrar en pantallas grandes.</small></span></label>
            <label class="premium-switch"><input type="checkbox" name="widgets[__INDEX__][show_mobile]" value="1" checked><span class="switch-ui" aria-hidden="true"></span><span><b>Móvil</b><small>Mostrar en teléfonos.</small></span></label>
        </div>
        <div class="form-grid widget-form-grid">
            <label><span>Nombre</span><input name="widgets[__INDEX__][name]" maxlength="80" value="Nuevo widget" data-widget-name></label>
            <label><span>Cómo se instala</span><select name="widgets[__INDEX__][kind]" data-widget-kind><option value="embed">Código de instalación (recomendado)</option><option value="url">URL embebible</option></select><small>Para scripts como NIVO usa Código de instalación.</small></label>
            <label><span>Posición</span><select name="widgets[__INDEX__][position]"><option value="left">Izquierda</option><option value="right" selected>Derecha</option></select></label>
            <label><span>Orden</span><input type="number" min="0" max="999" name="widgets[__INDEX__][order]" value="30"><small>Un número menor queda más cerca de la parte inferior del lado seleccionado.</small></label>
        </div>
        <label class="widget-snippet-field" data-url-field hidden><span>URL embebible del widget</span><input type="url" name="widgets[__INDEX__][url]" value="" placeholder="https://..."><small>Úsala solo si el proveedor te entrega una URL directa para embeber. No pegues aquí código &lt;script&gt;.</small></label>
        <label class="widget-snippet-field" data-embed-field><span>Código de instalación</span><textarea name="widgets[__INDEX__][snippet]" rows="6" spellcheck="false" placeholder="Pega aquí el código completo que te entrega ZYNKO (debe comenzar con &lt;script y terminar con &lt;/script&gt;)"></textarea><small>Pega el código completo que te entrega el proveedor.</small></label>
    </article>
</template>

<script>
(() => {
    const list = document.getElementById('external-widget-list');
    const template = document.getElementById('external-widget-template');
    const addButton = document.getElementById('add-external-widget');
    let nextIndex = <?= count($externalWidgets) ?>;

    const bindEditor = (editor) => {
        const kind = editor.querySelector('[data-widget-kind]');
        const urlField = editor.querySelector('[data-url-field]');
        const embedField = editor.querySelector('[data-embed-field]');
        const name = editor.querySelector('[data-widget-name]');
        const title = editor.querySelector('[data-widget-title]');
        const syncKind = () => {
            const isUrl = kind && kind.value === 'url';
            if (urlField) urlField.hidden = !isUrl;
            if (embedField) embedField.hidden = isUrl;
        };
        kind?.addEventListener('change', syncKind);
        name?.addEventListener('input', () => { if (title) title.textContent = name.value.trim() || 'Widget externo'; });
        editor.querySelector('[data-remove-widget]')?.addEventListener('click', () => editor.remove());
        syncKind();
    };

    list.querySelectorAll('[data-widget-editor]').forEach(bindEditor);
    addButton?.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        const holder = document.createElement('div');
        holder.innerHTML = html.trim();
        const editor = holder.firstElementChild;
        if (!editor) return;
        list.appendChild(editor);
        bindEditor(editor);
        editor.querySelector('[data-widget-name]')?.focus();
    });
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
