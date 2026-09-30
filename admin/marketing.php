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

        if ($action === 'product_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $productKey = strtolower(trim((string) ($_POST['product_key'] ?? '')));
            $productKey = preg_replace('/[^a-z0-9_-]+/', '-', $productKey ?: $name) ?: '';
            $productKey = trim($productKey, '-');
            if ($name === '' || $productKey === '') {
                throw new RuntimeException('Solution name and key are required.');
            }

            $logoPath = trim((string) ($_POST['current_logo_path'] ?? ''));
            if (isset($_POST['remove_solution_logo'])) {
                $logoPath = '';
            }
            if (!empty($_FILES['solution_logo']['name'])) {
                $logoPath = upload_image($_FILES['solution_logo'], 'solutions', $productKey . '-logo', 8);
            }

            $values = [
                $productKey,
                $name,
                $logoPath,
                trim((string) ($_POST['accent_color'] ?? '#0A9ED0')) ?: '#0A9ED0',
                trim((string) ($_POST['tagline_es'] ?? '')),
                trim((string) ($_POST['tagline_en'] ?? '')),
                trim((string) ($_POST['description_es'] ?? '')),
                trim((string) ($_POST['description_en'] ?? '')),
                trim((string) ($_POST['features_es'] ?? '')),
                trim((string) ($_POST['features_en'] ?? '')),
                trim((string) ($_POST['cta_label_es'] ?? '')),
                trim((string) ($_POST['cta_label_en'] ?? '')),
                trim((string) ($_POST['cta_url'] ?? '')),
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['active']) ? 1 : 0,
            ];
            if ($id > 0) {
                db()->prepare('UPDATE marketing_products SET product_key=?,name=?,logo_path=?,accent_color=?,tagline_es=?,tagline_en=?,description_es=?,description_en=?,features_es=?,features_en=?,cta_label_es=?,cta_label_en=?,cta_url=?,sort_order=?,active=? WHERE id=?')->execute([...$values, $id]);
            } else {
                db()->prepare('INSERT INTO marketing_products(product_key,name,logo_path,accent_color,tagline_es,tagline_en,description_es,description_en,features_es,features_en,cta_label_es,cta_label_en,cta_url,sort_order,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
            }
            $message = 'Solution saved.';
        }

        if ($action === 'product_delete') {
            db()->prepare('DELETE FROM marketing_products WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
            $message = 'Solution deleted.';
        }

        if ($action === 'testimonial_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $clientName = trim((string) ($_POST['client_name'] ?? ''));
            $quoteEs = trim((string) ($_POST['quote_es'] ?? ''));
            $quoteEn = trim((string) ($_POST['quote_en'] ?? ''));
            if ($clientName === '' || ($quoteEs === '' && $quoteEn === '')) {
                throw new RuntimeException('Client name and at least one testimonial text are required.');
            }
            if ($quoteEs === '') $quoteEs = $quoteEn;
            if ($quoteEn === '') $quoteEn = $quoteEs;
            $values = [
                $clientName,
                trim((string) ($_POST['client_role_es'] ?? '')),
                trim((string) ($_POST['client_role_en'] ?? '')),
                trim((string) ($_POST['solution_name'] ?? '')),
                $quoteEs,
                $quoteEn,
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['active']) ? 1 : 0,
            ];
            if ($id > 0) {
                db()->prepare('UPDATE marketing_testimonials SET client_name=?,client_role_es=?,client_role_en=?,solution_name=?,quote_es=?,quote_en=?,sort_order=?,active=? WHERE id=?')->execute([...$values, $id]);
            } else {
                db()->prepare('INSERT INTO marketing_testimonials(client_name,client_role_es,client_role_en,solution_name,quote_es,quote_en,sort_order,active) VALUES(?,?,?,?,?,?,?,?)')->execute($values);
            }
            $message = 'Client testimonial saved.';
        }

        if ($action === 'testimonial_delete') {
            db()->prepare('DELETE FROM marketing_testimonials WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
            $message = 'Client testimonial deleted.';
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
$projects = db()->query('SELECT * FROM marketing_projects ORDER BY sort_order,id')->fetchAll();
try { $testimonials = db()->query('SELECT * FROM marketing_testimonials ORDER BY sort_order,id')->fetchAll(); } catch (Throwable $e) { $testimonials = []; }

$labels = [
    'hero_kicker' => 'Hero eyebrow',
    'hero_title' => 'Hero title',
    'hero_text' => 'Hero description',
    'hero_primary' => 'Primary CTA',
    'hero_secondary' => 'WhatsApp CTA',
    'solutions_title' => 'Solutions title',
    'solutions_text' => 'Solutions intro',
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
            'services' => 'Services',
            'projects' => 'Projects',
            'testimonials' => 'Client opinions',
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
    <a href="#products">Solutions</a>
    <a href="#testimonials">Client opinions</a>
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
            <span class="eyebrow">SOLUTIONS</span>
            <h2>Corporate solutions directory</h2>
            <p>Create, edit, order and publish the solutions shown on the public website. Each solution can point to its own dedicated website.</p>
        </div>
    </div>

    <form class="marketing-card solution-admin-create" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="product_save">
        <input type="hidden" name="id" value="0">
        <div class="form-grid">
            <label>Solution name<input name="name" required placeholder="Example: ZYNKO"></label>
            <label>Key / slug<input name="product_key" placeholder="zynko"></label>
            <label>Accent color<input type="color" name="accent_color" value="#0A9ED0"></label>
            <label>Order<input type="number" name="sort_order" value="10"></label>
        </div>
        <div class="upload-zone premium-media-zone solution-logo-upload" data-upload-zone>
            <input type="file" name="solution_logo" accept="image/jpeg,image/png,image/webp" data-empty-label="Drop, paste or choose the solution logo">
            <div class="upload-icon" aria-hidden="true">◈</div>
            <strong>Solution logo</strong>
            <small>Upload the official logo. Drag & drop, paste from clipboard, or choose JPG, PNG or WEBP.</small>
            <span class="upload-zone-action">Choose logo</span>
            <div class="upload-selection-name" data-upload-name>Drop, paste or choose the solution logo</div>
            <div class="upload-preview premium-upload-preview" data-upload-preview></div>
        </div>
        <div class="bilingual-grid">
            <label>Short tagline ES<input name="tagline_es" maxlength="180" placeholder="Example: Facturación y gestión empresarial"></label>
            <label>Short tagline EN<input name="tagline_en" maxlength="180" placeholder="Example: Billing and business management"></label>
            <label>Description ES<textarea name="description_es" rows="4" required></textarea></label>
            <label>Description EN<textarea name="description_en" rows="4"></textarea></label>
            <label>Highlights ES<textarea name="features_es" rows="5" placeholder="One item per line"></textarea></label>
            <label>Highlights EN<textarea name="features_en" rows="5" placeholder="One item per line"></textarea></label>
            <label>CTA text ES<input name="cta_label_es" maxlength="100" placeholder="Conocer IZZY"></label>
            <label>CTA text EN<input name="cta_label_en" maxlength="100" placeholder="Explore IZZY"></label>
        </div>
        <label>Dedicated website / CTA URL<input type="url" name="cta_url" placeholder="https://..."><small>Leave it empty until the dedicated website is ready. The public card will show “Website coming soon”. As soon as you save a URL, that message disappears and the website button becomes active.</small></label>
        <label class="toggle-line"><input type="checkbox" name="active" checked><span>Published</span></label>
        <button class="button" type="submit">Add solution</button>
    </form>

    <div class="marketing-card-grid">
        <?php foreach ($products as $product): ?>
            <div class="project-editor-shell">
                <form class="marketing-card" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="product_save">
                    <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                    <input type="hidden" name="current_logo_path" value="<?= h($product['logo_path'] ?? '') ?>">
                    <div class="marketing-brand-row solution-admin-heading">
                        <?php if (!empty($product['logo_path'])): ?>
                            <div class="solution-admin-logo"><img src="../<?= h($product['logo_path']) ?>" alt="<?= h($product['name']) ?>"></div>
                        <?php else: ?>
                            <div class="solution-admin-monogram" style="--product-accent:<?= h($product['accent_color']) ?>"><?= h(strtoupper(substr((string)$product['name'],0,2))) ?></div>
                        <?php endif; ?>
                        <div><span class="eyebrow">SOLUTION</span><h3><?= h($product['name']) ?></h3></div>
                    </div>
                    <div class="form-grid">
                        <label>Name<input name="name" value="<?= h($product['name']) ?>" required></label>
                        <label>Key / slug<input name="product_key" value="<?= h($product['product_key']) ?>" required></label>
                        <label>Accent color<input type="color" name="accent_color" value="<?= h($product['accent_color'] ?: '#0A9ED0') ?>"></label>
                        <label>Order<input type="number" name="sort_order" value="<?= (int)$product['sort_order'] ?>"></label>
                    </div>
                    <div class="upload-zone premium-media-zone solution-logo-upload" data-upload-zone>
                        <input type="file" name="solution_logo" accept="image/jpeg,image/png,image/webp" data-empty-label="Keep current logo or choose a replacement">
                        <div class="upload-icon" aria-hidden="true">◈</div>
                        <strong><?= !empty($product['logo_path']) ? 'Replace solution logo' : 'Add solution logo' ?></strong>
                        <small>Drag & drop, paste from clipboard, or choose JPG, PNG or WEBP.</small>
                        <span class="upload-zone-action">Choose logo</span>
                        <div class="upload-selection-name" data-upload-name><?= !empty($product['logo_path']) ? 'Current logo will be kept' : 'No logo uploaded yet' ?></div>
                        <div class="upload-preview premium-upload-preview" data-upload-preview></div>
                    </div>
                    <?php if (!empty($product['logo_path'])): ?>
                        <label class="check-row remove-media-check"><input type="checkbox" name="remove_solution_logo"> Remove current solution logo when saving</label>
                    <?php endif; ?>
                    <div class="bilingual-grid">
                        <label>Short tagline ES<input name="tagline_es" maxlength="180" value="<?= h($product['tagline_es'] ?? '') ?>"></label>
                        <label>Short tagline EN<input name="tagline_en" maxlength="180" value="<?= h($product['tagline_en'] ?? '') ?>"></label>
                        <label>Description ES<textarea name="description_es" rows="4"><?= h($product['description_es']) ?></textarea></label>
                        <label>Description EN<textarea name="description_en" rows="4"><?= h($product['description_en']) ?></textarea></label>
                        <label>Highlights ES<textarea name="features_es" rows="6"><?= h($product['features_es']) ?></textarea></label>
                        <label>Highlights EN<textarea name="features_en" rows="6"><?= h($product['features_en']) ?></textarea></label>
                        <label>CTA text ES<input name="cta_label_es" maxlength="100" value="<?= h($product['cta_label_es'] ?? '') ?>" placeholder="Conocer la solución"></label>
                        <label>CTA text EN<input name="cta_label_en" maxlength="100" value="<?= h($product['cta_label_en'] ?? '') ?>" placeholder="Explore solution"></label>
                    </div>
                    <label>Dedicated website / CTA URL<input type="url" name="cta_url" value="<?= h($product['cta_url']) ?>" placeholder="https://..."><small>Change this whenever the solution website changes. Empty = “Website coming soon”. Saving a valid URL immediately activates the public website button.</small></label>
                    <label class="toggle-line"><input type="checkbox" name="active" <?= $product['active'] ? 'checked' : '' ?>><span>Published</span></label>
                    <button class="button" type="submit">Save <?= h($product['name']) ?></button>
                </form>
                <form method="post" class="project-delete-form" data-swal-confirm="Delete this solution?" data-swal-text="It will be removed from the public website and contact selector.">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="product_delete"><input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                    <button class="button danger" type="submit">Delete solution</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel" id="testimonials">
    <div class="panel-head">
        <div>
            <span class="eyebrow">CLIENT OPINIONS</span>
            <h2>Real customer experiences</h2>
            <p>Manage client experiences here. The initial demonstration profiles keep the section visually complete. Replace them with approved real customer testimonials whenever you have them.</p>
        </div>
    </div>

    <form class="marketing-card testimonial-admin-create" method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="testimonial_save">
        <input type="hidden" name="id" value="0">
        <div class="form-grid">
            <label>Client / company name<input name="client_name" required placeholder="Company or approved client name"></label>
            <label>Related solution
                <select name="solution_name">
                    <option value="">General / ES MULTISERVICIOS</option>
                    <?php foreach ($products as $productOption): ?>
                        <option value="<?= h($productOption['name']) ?>"><?= h($productOption['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="Soluciones a la medida">Soluciones a la medida</option>
                </select>
            </label>
            <label>Order<input type="number" name="sort_order" value="10"></label>
        </div>
        <div class="bilingual-grid">
            <div>
                <h3>Español</h3>
                <label>Role / context<input name="client_role_es" placeholder="Ej. Gerencia / Clínica / Comercio"></label>
                <label>Opinión<textarea name="quote_es" rows="5" placeholder="Escribe únicamente una opinión real autorizada por el cliente."></textarea></label>
            </div>
            <div>
                <h3>English</h3>
                <label>Role / context<input name="client_role_en" placeholder="Example: Management / Clinic / Retail"></label>
                <label>Opinion<textarea name="quote_en" rows="5" placeholder="Use only a real customer opinion approved for publication."></textarea></label>
            </div>
        </div>
        <label class="toggle-line"><input type="checkbox" name="active" checked><span>Published</span></label>
        <button class="button" type="submit">Add client opinion</button>
    </form>

    <?php if (!$testimonials): ?>
        <div class="empty-state marketing-empty-state">
            <strong>No client opinions available yet.</strong>
            <p>This section stays hidden on the public website until you add a real approved testimonial.</p>
        </div>
    <?php else: ?>
        <div class="marketing-card-grid testimonial-admin-grid">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="project-editor-shell">
                    <form class="marketing-card" method="post">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="testimonial_save">
                        <input type="hidden" name="id" value="<?= (int)$testimonial['id'] ?>">
                        <div class="form-grid">
                            <label>Client / company name<input name="client_name" required value="<?= h($testimonial['client_name']) ?>"></label>
                            <label>Related solution
                                <?php $testimonialSolutionCurrent = trim((string)($testimonial['solution_name'] ?? '')); ?>
                                <select name="solution_name">
                                    <option value="" <?= $testimonialSolutionCurrent === '' ? 'selected' : '' ?>>General / ES MULTISERVICIOS</option>
                                    <?php $knownTestimonialSolution = false; ?>
                                    <?php foreach ($products as $productOption): ?>
                                        <?php $isSelected = strcasecmp($testimonialSolutionCurrent, (string)$productOption['name']) === 0; if ($isSelected) $knownTestimonialSolution = true; ?>
                                        <option value="<?= h($productOption['name']) ?>" <?= $isSelected ? 'selected' : '' ?>><?= h($productOption['name']) ?></option>
                                    <?php endforeach; ?>
                                    <?php $isCustomSolution = strcasecmp($testimonialSolutionCurrent, 'Soluciones a la medida') === 0; if ($isCustomSolution) $knownTestimonialSolution = true; ?>
                                    <option value="Soluciones a la medida" <?= $isCustomSolution ? 'selected' : '' ?>>Soluciones a la medida</option>
                                    <?php if ($testimonialSolutionCurrent !== '' && !$knownTestimonialSolution): ?>
                                        <option value="<?= h($testimonialSolutionCurrent) ?>" selected><?= h($testimonialSolutionCurrent) ?></option>
                                    <?php endif; ?>
                                </select>
                            </label>
                            <label>Order<input type="number" name="sort_order" value="<?= (int)$testimonial['sort_order'] ?>"></label>
                        </div>
                        <div class="bilingual-grid">
                            <div>
                                <h3>Español</h3>
                                <label>Role / context<input name="client_role_es" value="<?= h($testimonial['client_role_es'] ?? '') ?>"></label>
                                <label>Opinión<textarea name="quote_es" rows="5"><?= h($testimonial['quote_es'] ?? '') ?></textarea></label>
                            </div>
                            <div>
                                <h3>English</h3>
                                <label>Role / context<input name="client_role_en" value="<?= h($testimonial['client_role_en'] ?? '') ?>"></label>
                                <label>Opinion<textarea name="quote_en" rows="5"><?= h($testimonial['quote_en'] ?? '') ?></textarea></label>
                            </div>
                        </div>
                        <label class="toggle-line"><input type="checkbox" name="active" <?= $testimonial['active'] ? 'checked' : '' ?>><span>Published</span></label>
                        <button class="button" type="submit">Save opinion</button>
                    </form>
                    <form method="post" class="project-delete-form" data-swal-confirm="Delete this client opinion?" data-swal-text="It will disappear from the public website.">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="testimonial_delete">
                        <input type="hidden" name="id" value="<?= (int)$testimonial['id'] ?>">
                        <button class="button danger" type="submit">Delete opinion</button>
                    </form>
                </div>
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
                    <label>Public project website URL<input type="url" name="project_url" value="<?= h($project['project_url']) ?>" placeholder="https://..."><small>This powers the “Visit website” button on the public project card and can be changed at any time.</small></label>
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
