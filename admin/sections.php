<?php
require __DIR__ . '/bootstrap.php';
require_permission('sections.manage');

$pdo = db();

$sectionCatalog = [
    'home' => ['label' => 'Inicio / Hero', 'order' => 10, 'menu' => true],
    'solutions' => ['label' => 'Soluciones', 'order' => 20, 'menu' => true],
    'izzy' => ['label' => 'IZZY', 'order' => 30, 'menu' => true],
    'plans' => ['label' => 'Planes de IZZY', 'order' => 35, 'menu' => false],
    'cami' => ['label' => 'CAMI', 'order' => 40, 'menu' => true],
    'services' => ['label' => 'Servicios', 'order' => 50, 'menu' => true],
    'videos' => ['label' => 'Videos', 'order' => 60, 'menu' => false],
    'projects' => ['label' => 'Proyectos', 'order' => 70, 'menu' => true],
    'affiliate' => ['label' => 'Afiliados', 'order' => 80, 'menu' => true],
    'company-artwork' => ['label' => 'Material corporativo', 'order' => 90, 'menu' => false],
    'why' => ['label' => 'Por qué ES MULTISERVICIOS', 'order' => 100, 'menu' => false],
    'contact' => ['label' => 'Contacto', 'order' => 110, 'menu' => true],
];

$ensureSection = $pdo->prepare(
    'INSERT INTO site_sections(section_key,label,sort_order,active)
     VALUES(?,?,?,1)
     ON DUPLICATE KEY UPDATE label=VALUES(label)'
);

foreach ($sectionCatalog as $key => $config) {
    $ensureSection->execute([$key, $config['label'], $config['order']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $keys = $_POST['section_key'] ?? [];
    $orders = $_POST['sort_order'] ?? [];
    $activeSections = $_POST['active'] ?? [];
    $update = $pdo->prepare(
        'UPDATE site_sections SET sort_order=?,active=? WHERE section_key=?'
    );

    foreach ($keys as $index => $key) {
        if (!isset($sectionCatalog[$key])) {
            continue;
        }

        $update->execute([
            (int) ($orders[$index] ?? 0),
            isset($activeSections[$key]) ? 1 : 0,
            $key,
        ]);
    }

    log_activity(
        'sections_update',
        'Updated ES MULTISERVICIOS public page section order and visibility'
    );
    flash('success', 'Page order and visibility saved.');
    header('Location: sections.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($sectionCatalog), '?'));
$statement = $pdo->prepare(
    "SELECT * FROM site_sections
     WHERE section_key IN ($placeholders)
     ORDER BY sort_order,section_key"
);
$statement->execute(array_keys($sectionCatalog));
$rows = $statement->fetchAll();

$pageTitle = 'Section Manager';
$active = 'sections';
require __DIR__ . '/_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">VISUAL STRUCTURE</p>
        <h1>Orden de la landing</h1>
        <p class="muted">
            Arrastra las secciones o usa los botones de subir y bajar. El sitio público
            respetará este orden y el menú seguirá el mismo orden para las secciones
            que forman parte de la navegación principal.
        </p>
    </div>
    <a class="button secondary" href="../?preview=1" target="_blank" rel="noopener">Preview website</a>
</div>

<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="section-sort-list" data-sortable-list>
        <?php foreach ($rows as $row): ?>
            <?php $config = $sectionCatalog[$row['section_key']]; ?>
            <article class="section-sort-card" draggable="true">
                <span class="drag-handle" aria-hidden="true">⋮⋮</span>

                <input
                    type="hidden"
                    name="section_key[]"
                    value="<?= h($row['section_key']) ?>"
                >
                <input
                    type="hidden"
                    name="sort_order[]"
                    value="<?= (int) $row['sort_order'] ?>"
                    data-sort-order
                >

                <div class="section-sort-copy">
                    <div class="section-sort-title-row">
                        <strong><?= h($row['label']) ?></strong>
                        <span class="section-context-badge <?= $config['menu'] ? 'is-menu' : '' ?>">
                            <?= $config['menu'] ? 'Menú + página' : 'Sección de página' ?>
                        </span>
                    </div>
                    <small>
                        #<?= h($row['section_key']) ?> · posición
                        <b data-order-label><?= (int) $row['sort_order'] ?></b>
                    </small>
                </div>

                <div class="section-sort-actions" aria-label="Cambiar orden">
                    <button
                        class="icon-button section-move-button"
                        type="button"
                        data-sort-move="up"
                        title="Subir sección"
                        aria-label="Subir <?= h($row['label']) ?>"
                    >↑</button>
                    <button
                        class="icon-button section-move-button"
                        type="button"
                        data-sort-move="down"
                        title="Bajar sección"
                        aria-label="Bajar <?= h($row['label']) ?>"
                    >↓</button>
                </div>

                <label class="premium-switch compact">
                    <input
                        type="checkbox"
                        name="active[<?= h($row['section_key']) ?>]"
                        <?= $row['active'] ? 'checked' : '' ?>
                    >
                    <span class="switch-ui"></span>
                    <span>
                        <b><?= $row['active'] ? 'Visible' : 'Hidden' ?></b>
                        <small>Public section</small>
                    </span>
                </label>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="form-actions">
        <button type="submit">Guardar orden de la página</button>
    </div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
