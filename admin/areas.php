<?php
require __DIR__ . '/bootstrap.php';
require_permission('areas.manage');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save_map') {
        $mapQuery = trim((string)($_POST['service_map_query'] ?? ''));
        $mapLabelEs = trim((string)($_POST['service_map_label_es'] ?? ''));
        $mapLabelEn = trim((string)($_POST['service_map_label_en'] ?? ''));
        $mapEnabled = isset($_POST['service_map_enabled']) ? '1' : '0';

        save_setting('service_map_query', $mapQuery);
        save_setting('service_map_label_es', $mapLabelEs);
        save_setting('service_map_label_en', $mapLabelEn);
        // Keep the legacy key for backwards compatibility with older templates.
        save_setting('service_map_label', $mapLabelEs !== '' ? $mapLabelEs : $mapLabelEn);
        save_setting('service_map_enabled', $mapEnabled);

        flash('success', 'Service area map settings saved.');
    } elseif ($action === 'save') {
        $name = trim((string)($_POST['area_name'] ?? ''));
        $sort = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name !== '') {
            if ($id) {
                $pdo->prepare(
                    'UPDATE service_areas SET area_name = ?, sort_order = ?, active = ? WHERE id = ?'
                )->execute([$name, $sort, $active, $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO service_areas (area_name, sort_order, active) VALUES (?, ?, ?)'
                )->execute([$name, $sort, $active]);
            }

            flash('success', 'Service area saved.');
        }
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM service_areas WHERE id = ?')->execute([$id]);
        flash('success', 'Service area deleted.');
    }

    header('Location: areas.php');
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM service_areas WHERE id = ?');
    $statement->execute([(int)$_GET['edit']]);
    $edit = $statement->fetch();
}

$rows = $pdo->query(
    'SELECT * FROM service_areas ORDER BY sort_order, id'
)->fetchAll();

$mapQuery = setting('service_map_query', '');
$legacyMapLabel = trim((string)setting('service_map_label', ''));
$mapLabelEs = trim((string)setting('service_map_label_es', $legacyMapLabel !== '' ? $legacyMapLabel : 'Área de cobertura'));
$mapLabelEn = trim((string)setting('service_map_label_en', 'Service coverage'));
$mapEnabled = setting('service_map_enabled', '1') === '1';
$firstVisibleArea = '';
foreach ($rows as $areaRow) {
    if (!empty($areaRow['active'])) {
        $firstVisibleArea = trim((string)($areaRow['area_name'] ?? ''));
        if ($firstVisibleArea !== '') {
            break;
        }
    }
}

$mapPreviewQuery = $mapQuery !== ''
    ? $mapQuery
    : ($firstVisibleArea !== '' ? $firstVisibleArea : 'United States');
$publicMapHasLocation = $mapQuery !== '' || $firstVisibleArea !== '';
$publicMapWillShow = $mapEnabled && $publicMapHasLocation;

$pageTitle = 'Service Areas';
$active = 'areas';
require __DIR__ . '/_header.php';
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">SERVICE AREAS</p>
        <h1>Coverage locations</h1>
        <p class="muted">
            Manage cities, areas or ZIP codes shown on the public landing page.
        </p>
    </div>
</div>

<section class="panel service-map-admin">
    <div class="panel-heading-row service-map-heading">
        <div>
            <p class="eyebrow">PUBLIC MAP</p>
            <h2>Service area map</h2>
            <p class="muted">
                Choose the location visitors should see. The preview below updates after saving.
            </p>
        </div>

        <span class="badge <?= $mapEnabled ? 'contacted' : 'closed' ?>">
            <?= $mapEnabled ? 'Visible on website' : 'Hidden on website' ?>
        </span>
    </div>

    <form method="post" class="service-map-settings-form">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_map">

        <div class="service-map-fields service-map-fields-localized">
            <label class="service-map-location-field">
                Map location or search
                <div class="location-autocomplete" data-location-autocomplete>
                    <input
                        name="service_map_query"
                        value="<?= h($mapQuery) ?>"
                        placeholder="Example: San Pedro Sula, Cortés, Honduras"
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="service-map-suggestions"
                        data-location-input
                    >
                    <div
                        class="location-suggestions"
                        id="service-map-suggestions"
                        role="listbox"
                        hidden
                        data-location-suggestions
                    ></div>
                </div>
                <small>
                    Start typing a city, address or ZIP code to see location suggestions. If left blank,
                    the first visible service area is used automatically.
                </small>
            </label>

            <label>
                Map title (ES)
                <input
                    name="service_map_label_es"
                    value="<?= h($mapLabelEs) ?>"
                    placeholder="Área de cobertura"
                >
                <small>Shown when the public website is viewed in Spanish.</small>
            </label>

            <label>
                Map title (EN)
                <input
                    name="service_map_label_en"
                    value="<?= h($mapLabelEn) ?>"
                    placeholder="Service coverage"
                >
                <small>Shown when the public website is viewed in English.</small>
            </label>
        </div>

        <p class="service-map-public-help">
            <strong>Public behavior:</strong>
            <?php if ($publicMapWillShow): ?>
                the map will be shown on the public website using
                <strong><?= h($mapQuery !== '' ? $mapQuery : $firstVisibleArea) ?></strong>.
            <?php elseif (!$mapEnabled): ?>
                the map is currently hidden on the public website. Turn on <strong>Show map on website</strong> to display it.
            <?php else: ?>
                add a map location above or create at least one visible service area before the map can be shown publicly.
            <?php endif; ?>
        </p>

        <div class="service-map-controls">
            <label class="check-row status-switch service-map-switch">
                <input
                    type="checkbox"
                    name="service_map_enabled"
                    <?= $mapEnabled ? 'checked' : '' ?>
                >
                <span>Show map on website</span>
            </label>

            <button type="submit">Save map settings</button>
        </div>
    </form>

    <div class="service-map-admin-preview">
        <div class="service-map-admin-preview-head">
            <div>
                <strong>Map preview</strong>
                <small><?= h($mapPreviewQuery) ?></small>
            </div>
            <span class="service-map-preview-note">Public preview</span>
        </div>

        <iframe
            title="Service area map preview"
            src="https://www.google.com/maps?q=<?= rawurlencode($mapPreviewQuery) ?>&output=embed"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
        ></iframe>
    </div>
</section>

<section class="panel service-area-entry-panel">
    <div class="panel-heading-row">
        <div>
            <p class="eyebrow">COVERAGE LIST</p>
            <h2><?= $edit ? 'Edit service area' : 'Add service area' ?></h2>
            <p class="muted">
                Add the cities, ZIP codes or coverage names that visitors should see.
            </p>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h((string)($edit['id'] ?? 0)) ?>">

        <div class="three-col service-area-form-grid">
            <label>
                Area / City / ZIP
                <input
                    name="area_name"
                    required
                    value="<?= h($edit['area_name'] ?? '') ?>"
                    placeholder="Example: Washington, DC"
                >
            </label>

            <label>
                Order
                <input
                    type="number"
                    name="sort_order"
                    value="<?= h((string)($edit['sort_order'] ?? 0)) ?>"
                >
            </label>

            <label class="check-row status-switch service-area-visible-switch">
                <input
                    type="checkbox"
                    name="active"
                    <?= !$edit || !empty($edit['active']) ? 'checked' : '' ?>
                >
                <span>Visible</span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit">Save area</button>
            <?php if ($edit): ?>
                <a class="button secondary" href="areas.php">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<div class="list-grid">
    <?php foreach ($rows as $row): ?>
        <article class="list-card">
            <div class="list-head">
                <div>
                    <strong><?= h($row['area_name']) ?></strong>
                    <small>Order <?= (int)$row['sort_order'] ?></small>
                </div>

                <span class="badge <?= $row['active'] ? 'contacted' : 'closed' ?>">
                    <?= $row['active'] ? 'Visible' : 'Hidden' ?>
                </span>
            </div>

            <div class="actions">
                <a class="button secondary small" href="?edit=<?= (int)$row['id'] ?>">
                    Edit
                </a>

                <form
                    method="post"
                    data-swal-confirm="Delete this service area?"
                    data-swal-text="This location will no longer appear on the website."
                >
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button class="button danger small" type="submit">Delete</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</div>


<script>
(() => {
    const root = document.querySelector('[data-location-autocomplete]');
    if (!root) return;

    const input = root.querySelector('[data-location-input]');
    const list = root.querySelector('[data-location-suggestions]');
    if (!input || !list) return;

    let timer = null;
    let controller = null;

    const closeSuggestions = () => {
        list.hidden = true;
        list.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
    };

    const renderSuggestions = (items) => {
        list.innerHTML = '';
        if (!items.length) {
            closeSuggestions();
            return;
        }

        items.forEach((item) => {
            const label = String(item.display_name || '').trim();
            if (!label) return;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'location-suggestion';
            button.setAttribute('role', 'option');
            button.textContent = label;
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                input.value = label;
                closeSuggestions();
                input.focus();
            });
            list.appendChild(button);
        });

        if (list.children.length) {
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        } else {
            closeSuggestions();
        }
    };

    const searchLocations = async () => {
        const query = input.value.trim();
        if (query.length < 3) {
            closeSuggestions();
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();

        try {
            const url = new URL('https://nominatim.openstreetmap.org/search');
            url.searchParams.set('format', 'jsonv2');
            url.searchParams.set('limit', '5');
            url.searchParams.set('addressdetails', '1');
            url.searchParams.set('q', query);

            const response = await fetch(url.toString(), {
                signal: controller.signal,
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) throw new Error('Location lookup failed');

            const results = await response.json();
            renderSuggestions(Array.isArray(results) ? results : []);
        } catch (error) {
            if (error && error.name === 'AbortError') return;
            // Autocomplete is a convenience only; manual entry must continue to work.
            closeSuggestions();
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(searchLocations, 450);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeSuggestions();
    });

    input.addEventListener('blur', () => {
        window.setTimeout(closeSuggestions, 120);
    });
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
