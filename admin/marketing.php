<?php
require __DIR__ . '/bootstrap.php';
require_permission('marketing.manage');

$pageTitle = 'ES MULTISERVICIOS Marketing';
$active = 'marketing';
$message = '';
$type = 'success';

$copyKeys = [
    'hero_kicker',
    'hero_title',
    'hero_text',
    'hero_primary',
    'hero_secondary',
    'solutions_title',
    'solutions_text',
    'izzy_title',
    'izzy_text',
    'cami_title',
    'cami_text',
    'services_title',
    'services_text',
    'affiliate_title',
    'affiliate_text',
    'affiliate_cta',
    'affiliate_single_title',
    'affiliate_single_text',
    'affiliate_team_title',
    'affiliate_team_text',
    'affiliate_support_text',
    'affiliate_disclaimer',
    'projects_title',
    'why_title',
    'contact_title',
    'contact_text',
    'support_cta',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'copy') {
            $statement = db()->prepare(
                'INSERT INTO landing_content(content_key,lang,content_value)
                 VALUES(?,?,?)
                 ON DUPLICATE KEY UPDATE content_value=VALUES(content_value)'
            );

            foreach (['es', 'en'] as $lang) {
                foreach ($copyKeys as $key) {
                    $statement->execute([
                        $key,
                        $lang,
                        trim((string) ($_POST[$key . '_' . $lang] ?? '')),
                    ]);
                }
            }

            log_activity('marketing.copy', 'Updated bilingual marketing copy');
            $message = 'Bilingual marketing content saved.';
        }

        if ($action === 'product') {
            $id = (int) ($_POST['id'] ?? 0);
            $statement = db()->prepare(
                'UPDATE marketing_products
                 SET description_es=?,description_en=?,features_es=?,features_en=?,cta_url=?,sort_order=?,active=?
                 WHERE id=?'
            );
            $statement->execute([
                trim((string) ($_POST['description_es'] ?? '')),
                trim((string) ($_POST['description_en'] ?? '')),
                trim((string) ($_POST['features_es'] ?? '')),
                trim((string) ($_POST['features_en'] ?? '')),
                trim((string) ($_POST['cta_url'] ?? '')),
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['active']) ? 1 : 0,
                $id,
            ]);
            $message = 'Product updated.';
        }

        if ($action === 'plan_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $values = [
                trim((string) ($_POST['product_key'] ?? 'izzy')),
                trim((string) ($_POST['name_es'] ?? '')),
                trim((string) ($_POST['name_en'] ?? '')),
                trim((string) ($_POST['description_es'] ?? '')),
                trim((string) ($_POST['description_en'] ?? '')),
                trim((string) ($_POST['price_label_es'] ?? '')),
                trim((string) ($_POST['price_label_en'] ?? '')),
                trim((string) ($_POST['features_es'] ?? '')),
                trim((string) ($_POST['features_en'] ?? '')),
                trim((string) ($_POST['badge_es'] ?? '')),
                trim((string) ($_POST['badge_en'] ?? '')),
                trim((string) ($_POST['cta_url'] ?? '')),
                isset($_POST['featured']) ? 1 : 0,
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['active']) ? 1 : 0,
            ];

            if ($id > 0) {
                $statement = db()->prepare(
                    'UPDATE marketing_plans
                     SET product_key=?,name_es=?,name_en=?,description_es=?,description_en=?,price_label_es=?,price_label_en=?,features_es=?,features_en=?,badge_es=?,badge_en=?,cta_url=?,featured=?,sort_order=?,active=?
                     WHERE id=?'
                );
                $statement->execute([...$values, $id]);
            } else {
                $statement = db()->prepare(
                    'INSERT INTO marketing_plans(product_key,name_es,name_en,description_es,description_en,price_label_es,price_label_en,features_es,features_en,badge_es,badge_en,cta_url,featured,sort_order,active)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $statement->execute($values);
            }

            $message = 'Plan saved.';
        }

        if ($action === 'plan_delete') {
            db()->prepare('DELETE FROM marketing_plans WHERE id=?')
                ->execute([(int) ($_POST['id'] ?? 0)]);
            $message = 'Plan deleted.';
        }

        if ($action === 'project_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $imagePath = trim((string) ($_POST['current_image_path'] ?? ''));

            if (isset($_POST['remove_project_image'])) {
                $imagePath = '';
            }

            if (!empty($_FILES['project_image']['name'])) {
                $imagePath = upload_image($_FILES['project_image'], 'projects', 'project', 10);
            }

            $values = [
                trim((string) ($_POST['title'] ?? '')),
                trim((string) ($_POST['category_es'] ?? '')),
                trim((string) ($_POST['category_en'] ?? '')),
                trim((string) ($_POST['description_es'] ?? '')),
                trim((string) ($_POST['description_en'] ?? '')),
                $imagePath,
                trim((string) ($_POST['project_url'] ?? '')),
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['active']) ? 1 : 0,
            ];

            if ($id > 0) {
                $statement = db()->prepare(
                    'UPDATE marketing_projects
                     SET title=?,category_es=?,category_en=?,description_es=?,description_en=?,image_path=?,project_url=?,sort_order=?,active=?
                     WHERE id=?'
                );
                $statement->execute([...$values, $id]);
            } else {
                $statement = db()->prepare(
                    'INSERT INTO marketing_projects(title,category_es,category_en,description_es,description_en,image_path,project_url,sort_order,active)
                     VALUES(?,?,?,?,?,?,?,?,?)'
                );
                $statement->execute($values);
            }

            $message = 'Project saved.';
        }

        if ($action === 'project_delete') {
            db()->prepare('DELETE FROM marketing_projects WHERE id=?')
                ->execute([(int) ($_POST['id'] ?? 0)]);
            $message = 'Project deleted.';
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $type = 'error';
    }
}

$copy = [
    'es' => landing_content('es'),
    'en' => landing_content('en'),
];
$products = db()->query('SELECT * FROM marketing_products ORDER BY sort_order,id')->fetchAll();
$plans = db()->query('SELECT * FROM marketing_plans ORDER BY product_key,sort_order,id')->fetchAll();
$projects = db()->query('SELECT * FROM marketing_projects ORDER BY sort_order,id')->fetchAll();

$labels = [
    'hero_kicker' => 'Hero eyebrow',
    'hero_title' => 'Hero title',
    'hero_text' => 'Hero description',
    'hero_primary' => 'Primary CTA',
    'hero_secondary' => 'WhatsApp CTA',
    'solutions_title' => 'Solutions title',
    'solutions_text' => 'Solutions intro',
    'izzy_title' => 'IZZY title',
    'izzy_text' => 'IZZY intro',
    'cami_title' => 'CAMI title',
    'cami_text' => 'CAMI intro',
    'services_title' => 'Services title',
    'services_text' => 'Services intro',
    'affiliate_title' => 'Affiliate title',
    'affiliate_text' => 'Affiliate description',
    'affiliate_cta' => 'Affiliate CTA',
    'affiliate_single_title' => 'Affiliate 1-client title',
    'affiliate_single_text' => 'Affiliate 1-client benefit',
    'affiliate_team_title' => 'Affiliate 3+ clients title',
    'affiliate_team_text' => 'Affiliate 3+ clients benefit',
    'affiliate_support_text' => 'Affiliate support message',
    'affiliate_disclaimer' => 'Affiliate disclaimer',
    'projects_title' => 'Projects title',
    'why_title' => 'Why us title',
    'contact_title' => 'Contact title',
    'contact_text' => 'Contact description',
    'support_cta' => 'Support CTA',
];

require __DIR__ . '/_header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">MARKETING WEBSITE</span>
        <h1>ES MULTISERVICIOS landing page</h1>
        <p>Manage the bilingual public website from one organized workspace. Every major public section can be previewed here before you finish.</p>
    </div>
    <a class="button" href="../?preview=1" target="_blank" rel="noopener">Preview full website</a>
</div>

<?php if ($message): ?>
    <div class="notice <?= $type === 'error' ? 'error' : 'success' ?>"><?= h($message) ?></div>
<?php endif; ?>

<section class="panel marketing-live-preview-panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">LIVE PREVIEW</p>
            <h2>Preview every marketing section</h2>
            <p class="muted">Choose a section below. The preview uses the real public landing page and keeps the administrator separate from the customer-facing website.</p>
        </div>
    </div>

    <div class="marketing-preview-toolbar" data-marketing-preview-toolbar>
        <?php
        $previewSections = [
            'home' => 'Home',
            'solutions' => 'Solutions',
            'izzy' => 'IZZY',
            'cami' => 'CAMI',
            'services' => 'Services',
            'plans' => 'IZZY Plans',
            'projects' => 'Projects',
            'affiliate' => 'Affiliates',
            'contact' => 'Contact',
        ];
        foreach ($previewSections as $section => $label):
        ?>
            <button type="button" data-preview-section="<?= h($section) ?>"><?= h($label) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="marketing-preview-frame">
        <iframe
            src="../?preview=1#home"
            title="ES MULTISERVICIOS public website preview"
            loading="lazy"
            data-marketing-preview
        ></iframe>
    </div>
</section>

<div class="admin-marketing-tabs">
    <a href="#copy">ES / EN content</a>
    <a href="#products">Products</a>
    <a href="#plans">Plans</a>
    <a href="#projects">Projects</a>
</div>

<section class="panel" id="copy">
    <div class="panel-head">
        <div>
            <span class="eyebrow">BILINGUAL CONTENT</span>
            <h2>Public landing copy</h2>
            <p>Spanish and English stay together so both versions remain consistent.</p>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="copy">

        <div class="bilingual-grid">
            <div>
                <h3>Español</h3>
                <?php foreach ($labels as $key => $label): ?>
                    <label>
                        <?= h($label) ?>
                        <textarea name="<?= h($key) ?>_es" rows="<?= $key === 'hero_text' || str_contains($key, 'text') ? 3 : 2 ?>"><?= h($copy['es'][$key] ?? '') ?></textarea>
                    </label>
                <?php endforeach; ?>
            </div>

            <div>
                <h3>English</h3>
                <?php foreach ($labels as $key => $label): ?>
                    <label>
                        <?= h($label) ?>
                        <textarea name="<?= h($key) ?>_en" rows="<?= $key === 'hero_text' || str_contains($key, 'text') ? 3 : 2 ?>"><?= h($copy['en'][$key] ?? '') ?></textarea>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button class="button" type="submit">Save bilingual content</button>
    </form>
</section>

<section class="panel" id="products">
    <div class="panel-head">
        <div>
            <span class="eyebrow">PRODUCTS</span>
            <h2>IZZY & CAMI</h2>
            <p>Edit the public descriptions and feature lists. The real public product presentation is visible in the preview above.</p>
        </div>
    </div>

    <div class="marketing-card-grid">
        <?php foreach ($products as $product): ?>
            <form class="marketing-card" method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="product">
                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">

                <div class="marketing-brand-row">
                    <img src="../<?= h($product['logo_path']) ?>" alt="<?= h($product['name']) ?>">
                    <div>
                        <span class="eyebrow">PRODUCT</span>
                        <h3><?= h($product['name']) ?></h3>
                    </div>
                </div>

                <label>
                    Descripción ES
                    <textarea name="description_es" rows="3"><?= h($product['description_es']) ?></textarea>
                </label>
                <label>
                    Description EN
                    <textarea name="description_en" rows="3"><?= h($product['description_en']) ?></textarea>
                </label>
                <label>
                    Funciones ES
                    <textarea name="features_es" rows="7"><?= h($product['features_es']) ?></textarea>
                </label>
                <label>
                    Features EN
                    <textarea name="features_en" rows="7"><?= h($product['features_en']) ?></textarea>
                </label>

                <div class="form-grid">
                    <label>
                        CTA / anchor
                        <input name="cta_url" value="<?= h($product['cta_url']) ?>">
                    </label>
                    <label>
                        Order
                        <input type="number" name="sort_order" value="<?= (int) $product['sort_order'] ?>">
                    </label>
                </div>

                <label class="toggle-line">
                    <input type="checkbox" name="active" <?= $product['active'] ? 'checked' : '' ?>>
                    <span>Published</span>
                </label>

                <button class="button" type="submit">Save <?= h($product['name']) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel" id="plans">
    <div class="panel-head">
        <div>
            <span class="eyebrow">PLANS</span>
            <h2>Commercial plans</h2>
            <p>Manage IZZY pricing and plan features. Keep one plan marked as Featured to give it stronger visual emphasis on the public website.</p>
        </div>
    </div>

    <form class="marketing-card plan-editor" method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="plan_save">
        <input type="hidden" name="id" value="0">

        <div class="form-grid">
            <label>
                Product
                <select name="product_key">
                    <option value="izzy">IZZY</option>
                    <option value="cami">CAMI</option>
                </select>
            </label>
            <label>Order<input type="number" name="sort_order" value="10"></label>
            <label>Name ES<input name="name_es" required></label>
            <label>Name EN<input name="name_en" required></label>
            <label>Price label ES<input name="price_label_es"></label>
            <label>Price label EN<input name="price_label_en"></label>
            <label>Badge ES<input name="badge_es"></label>
            <label>Badge EN<input name="badge_en"></label>
        </div>

        <div class="bilingual-grid">
            <label>Description ES<textarea name="description_es" rows="3"></textarea></label>
            <label>Description EN<textarea name="description_en" rows="3"></textarea></label>
            <label>Features ES<textarea name="features_es" rows="6" placeholder="One feature per line"></textarea></label>
            <label>Features EN<textarea name="features_en" rows="6" placeholder="One feature per line"></textarea></label>
        </div>

        <label>CTA URL / WhatsApp URL<input name="cta_url"></label>

        <div class="check-row">
            <label><input type="checkbox" name="featured"> Featured</label>
            <label><input type="checkbox" name="active" checked> Published</label>
        </div>

        <button class="button" type="submit">Add plan</button>
    </form>

    <?php if ($plans): ?>
        <div class="marketing-plan-admin-grid">
            <?php foreach ($plans as $plan): ?>
                <article class="marketing-plan-admin-card <?= $plan['featured'] ? 'is-featured' : '' ?>">
                    <div class="marketing-plan-admin-head">
                        <div>
                            <span class="eyebrow"><?= h(strtoupper($plan['product_key'])) ?></span>
                            <h3><?= h($plan['name_es']) ?></h3>
                            <strong><?= h($plan['price_label_es']) ?></strong>
                        </div>
                        <?php if ($plan['featured']): ?>
                            <span class="status-pill">Featured</span>
                        <?php endif; ?>
                    </div>

                    <form class="marketing-plan-edit-form" method="post">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="plan_save">
                        <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">

                        <div class="form-grid">
                            <label>Product
                                <select name="product_key">
                                    <option value="izzy" <?= $plan['product_key'] === 'izzy' ? 'selected' : '' ?>>IZZY</option>
                                    <option value="cami" <?= $plan['product_key'] === 'cami' ? 'selected' : '' ?>>CAMI</option>
                                </select>
                            </label>
                            <label>Order<input type="number" name="sort_order" value="<?= (int) $plan['sort_order'] ?>"></label>
                            <label>Name ES<input name="name_es" value="<?= h($plan['name_es']) ?>" required></label>
                            <label>Name EN<input name="name_en" value="<?= h($plan['name_en']) ?>" required></label>
                            <label>Price label ES<input name="price_label_es" value="<?= h($plan['price_label_es']) ?>"></label>
                            <label>Price label EN<input name="price_label_en" value="<?= h($plan['price_label_en']) ?>"></label>
                            <label>Badge ES<input name="badge_es" value="<?= h($plan['badge_es']) ?>"></label>
                            <label>Badge EN<input name="badge_en" value="<?= h($plan['badge_en']) ?>"></label>
                        </div>

                        <div class="bilingual-grid">
                            <label>Description ES<textarea name="description_es" rows="3"><?= h($plan['description_es']) ?></textarea></label>
                            <label>Description EN<textarea name="description_en" rows="3"><?= h($plan['description_en']) ?></textarea></label>
                            <label>Features ES<textarea name="features_es" rows="8"><?= h($plan['features_es']) ?></textarea></label>
                            <label>Features EN<textarea name="features_en" rows="8"><?= h($plan['features_en']) ?></textarea></label>
                        </div>

                        <label>CTA URL / WhatsApp URL<input name="cta_url" value="<?= h($plan['cta_url']) ?>"></label>

                        <div class="check-row">
                            <label><input type="checkbox" name="featured" <?= $plan['featured'] ? 'checked' : '' ?>> Featured</label>
                            <label><input type="checkbox" name="active" <?= $plan['active'] ? 'checked' : '' ?>> Published</label>
                        </div>

                        <button class="button" type="submit">Save plan</button>
                    </form>

                    <form method="post" data-swal-confirm="Delete this plan?" data-swal-text="This plan will stop appearing on the public website.">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="plan_delete">
                        <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                        <button class="button danger" type="submit">Delete plan</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel" id="projects">
    <div class="panel-head">
        <div>
            <span class="eyebrow">PROJECTS</span>
            <h2>Projects & case studies</h2>
            <p>Each project can have its own logo or cover image. Uploading an image is optional, but recommended for a professional project card.</p>
        </div>
    </div>

    <div class="marketing-card-grid">
        <?php foreach ($projects as $project): ?>
            <div class="project-editor-shell">
            <form class="marketing-card project-editor-card" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="project_save">
                <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                <input type="hidden" name="current_image_path" value="<?= h($project['image_path'] ?? '') ?>">

                <?php if (!empty($project['image_path'])): ?>
                    <div class="project-admin-preview">
                        <span>Current public image</span>
                        <img src="../<?= h($project['image_path']) ?>" alt="<?= h($project['title']) ?>">
                    </div>
                <?php endif; ?>

                <label>Project<input name="title" value="<?= h($project['title']) ?>" required></label>

                <div class="form-grid">
                    <label>Category ES<input name="category_es" value="<?= h($project['category_es']) ?>"></label>
                    <label>Category EN<input name="category_en" value="<?= h($project['category_en']) ?>"></label>
                </div>

                <label>Description ES<textarea name="description_es" rows="4"><?= h($project['description_es']) ?></textarea></label>
                <label>Description EN<textarea name="description_en" rows="4"><?= h($project['description_en']) ?></textarea></label>

                <div class="upload-zone premium-media-zone project-image-upload" data-upload-zone>
                    <input type="file" name="project_image" accept="image/jpeg,image/png,image/webp" data-empty-label="Drop, paste or choose a project image">
                    <div class="upload-icon" aria-hidden="true">▣</div>
                    <strong>Drop project logo or cover here</strong>
                    <small>Drag & drop, paste from clipboard, or click to choose JPG, PNG or WEBP.</small>
                    <span class="upload-zone-action">Choose image</span>
                    <div class="upload-selection-name" data-upload-name>Drop, paste or choose a project image</div>
                    <div class="upload-preview premium-upload-preview" data-upload-preview></div>
                </div>

                <?php if (!empty($project['image_path'])): ?>
                    <label class="check-row remove-media-check">
                        <input type="checkbox" name="remove_project_image">
                        Remove current project image when saving
                    </label>
                <?php endif; ?>

                <div class="form-grid">
                    <label>Project URL<input name="project_url" value="<?= h($project['project_url']) ?>"></label>
                    <label>Order<input type="number" name="sort_order" value="<?= (int) $project['sort_order'] ?>"></label>
                </div>

                <label class="toggle-line">
                    <input type="checkbox" name="active" <?= $project['active'] ? 'checked' : '' ?>>
                    <span>Published</span>
                </label>

                <div class="form-actions">
                    <button class="button" type="submit">Save project</button>
                </div>
            </form>

            <form
                class="project-delete-form"
                method="post"
                data-swal-confirm="Delete this project?"
                data-swal-text="The project will be removed from the public website."
            >
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="project_delete">
                <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                <button class="button danger" type="submit">Delete project</button>
            </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
(() => {
    const iframe = document.querySelector('[data-marketing-preview]');
    const buttons = Array.from(document.querySelectorAll('[data-preview-section]'));

    if (!iframe || !buttons.length) {
        return;
    }

    const setSection = (section, button) => {
        iframe.src = `../?preview=1#${encodeURIComponent(section)}`;
        buttons.forEach((item) => item.classList.toggle('active', item === button));
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => setSection(button.dataset.previewSection || 'home', button));
    });

    buttons[0].classList.add('active');
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
