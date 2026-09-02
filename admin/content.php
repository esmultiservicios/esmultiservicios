<?php
require __DIR__ . '/bootstrap.php';
require_permission('content.view');

$pdo = db();

$contentGroups = [
    'home' => [
        'title' => 'Home / Hero',
        'description' => 'The first message visitors see when they open the website.',
        'icon' => '🏠',
        'anchor' => 'home',
        'fields' => [
            'hero_eyebrow' => ['Hero eyebrow', 'input'],
            'hero_title' => ['Hero title', 'input'],
            'hero_text' => ['Hero description', 'textarea'],
            'intro_title' => ['Intro title', 'input'],
            'intro_text' => ['Intro text', 'textarea'],
        ],
    ],
    'about' => [
        'title' => 'About Us',
        'description' => 'Company story, mission and vision.',
        'icon' => '👷',
        'anchor' => 'about',
        'fields' => [
            'about_title' => ['About title', 'input'],
            'about_text' => ['About paragraph 1', 'textarea'],
            'about_text_2' => ['About paragraph 2', 'textarea'],
            'mission' => ['Mission', 'textarea'],
            'vision' => ['Vision', 'textarea'],
        ],
    ],
    'areas' => [
        'title' => 'Service Areas',
        'description' => 'Coverage section headline and support text.',
        'icon' => '📍',
        'anchor' => 'areas',
        'fields' => [
            'areas_title' => ['Service Areas title', 'input'],
            'areas_text' => ['Service Areas text', 'textarea'],
        ],
    ],
    'estimate' => [
        'title' => 'Free Estimate',
        'description' => 'Text above the customer estimate form.',
        'icon' => '📝',
        'anchor' => 'estimate',
        'fields' => [
            'estimate_title' => ['Estimate title', 'input'],
            'estimate_text' => ['Estimate text', 'textarea'],
        ],
    ],
    'contact' => [
        'title' => 'Contact',
        'description' => 'Main contact heading.',
        'icon' => '☎️',
        'anchor' => 'contact',
        'fields' => [
            'contact_title' => ['Contact title', 'input'],
        ],
    ],
];

$sectionNavigator = [
    'home' => [
        'title' => 'Home / Hero',
        'description' => 'Hero and introductory content.',
        'icon' => '🏠',
        'anchor' => 'home',
        'type' => 'content',
    ],
    'about' => [
        'title' => 'About Us',
        'description' => 'Story, mission and vision.',
        'icon' => '👷',
        'anchor' => 'about',
        'type' => 'content',
    ],
    'services' => [
        'title' => 'Services',
        'description' => 'Service cards and sub-services.',
        'icon' => '🛠️',
        'anchor' => 'services',
        'type' => 'module',
        'manage_url' => 'services.php',
        'manage_label' => 'Manage services',
    ],
    'gallery' => [
        'title' => 'Gallery',
        'description' => 'Project images and categories.',
        'icon' => '🖼️',
        'anchor' => 'gallery',
        'type' => 'module',
        'manage_url' => 'gallery.php',
        'manage_label' => 'Manage gallery',
    ],
    'areas' => [
        'title' => 'Service Areas',
        'description' => 'Coverage copy, locations and map.',
        'icon' => '📍',
        'anchor' => 'areas',
        'type' => 'content',
        'manage_url' => 'areas.php',
        'manage_label' => 'Manage locations & map',
    ],
    'tips' => [
        'title' => 'Home Tips',
        'description' => 'Educational tips shown on the website.',
        'icon' => '💡',
        'anchor' => 'tips',
        'type' => 'module',
        'manage_url' => 'tips.php',
        'manage_label' => 'Manage home tips',
    ],
    'estimate' => [
        'title' => 'Free Estimate',
        'description' => 'Headline and introduction for the estimate form.',
        'icon' => '📝',
        'anchor' => 'estimate',
        'type' => 'content',
    ],
    'contact' => [
        'title' => 'Contact',
        'description' => 'Contact heading and contact information.',
        'icon' => '☎️',
        'anchor' => 'contact',
        'type' => 'content',
        'manage_url' => 'settings.php',
        'manage_label' => 'Manage contact details',
    ],
];

$fields = [];
foreach ($contentGroups as $group) {
    foreach ($group['fields'] as $key => $meta) {
        $fields[$key] = $meta[0];
    }
}

$returnSection = (string)($_POST['return_section'] ?? $_GET['section'] ?? 'home');
if (!isset($sectionNavigator[$returnSection])) {
    $returnSection = 'home';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!user_can('content.edit') && !user_can('content.publish') && !user_can('content.approve')) {
        http_response_code(403);
        exit('Access denied.');
    }

    $action = $_POST['action'] ?? 'draft';

    try {
        if ($action === 'draft') {
            $statement = $pdo->prepare(
                'INSERT INTO content_drafts(content_key, content_value, updated_by)
                 VALUES(?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    content_value = VALUES(content_value),
                    updated_by = VALUES(updated_by)'
            );

            foreach ($fields as $key => $label) {
                $statement->execute([
                    $key,
                    trim((string)($_POST[$key] ?? '')),
                    (int)$_SESSION['escms_admin_id'],
                ]);
            }

            log_activity('content_draft', 'Saved landing page draft');
            flash('success', 'Draft saved. The public website has not changed.');
        } elseif ($action === 'submit_approval') {
            if (!user_can('content.edit')) {
                throw new RuntimeException('You cannot submit content for approval.');
            }

            $draftData = draft_content();
            if (!$draftData) {
                throw new RuntimeException('There is no draft to submit.');
            }

            $pdo->exec("UPDATE content_approvals SET status='cancelled', reviewed_at=NOW() WHERE status='pending'");
            $pdo->prepare('INSERT INTO content_approvals(submitted_by, note) VALUES(?, ?)')
                ->execute([
                    (int)$_SESSION['escms_admin_id'],
                    trim((string)($_POST['approval_note'] ?? '')),
                ]);

            log_activity('content_submit_approval', 'Submitted landing page draft for approval');
            $who = current_admin();
            admin_notify(
                'info',
                'Content approval requested',
                ($who['full_name'] ?: $who['username']) . ' submitted landing page changes for review.',
                'approvals.php'
            );
            flash('success', 'Draft submitted for approval.');
        } elseif ($action === 'publish') {
            if (!user_can('content.publish')) {
                throw new RuntimeException('Your role cannot publish directly. Submit the draft for approval instead.');
            }

            $liveData = site_content();
            $pdo->prepare('INSERT INTO content_versions(snapshot_json, note, created_by) VALUES(?, ?, ?)')
                ->execute([
                    json_encode($liveData, JSON_UNESCAPED_UNICODE),
                    'Automatic version before publish',
                    (int)$_SESSION['escms_admin_id'],
                ]);

            $draftData = draft_content();
            if (!$draftData) {
                throw new RuntimeException('There is no draft to publish.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO site_content(content_key, content_value)
                 VALUES(?, ?)
                 ON DUPLICATE KEY UPDATE content_value = VALUES(content_value)'
            );

            foreach ($draftData as $key => $value) {
                $statement->execute([$key, $value]);
            }

            $pdo->exec('DELETE FROM content_drafts');
            log_activity('content_publish', 'Published landing page content');
            admin_notify(
                'success',
                'Website content published',
                'The latest landing page draft is now live.',
                'content.php'
            );
            flash('success', 'Draft published to the public website.');
        } elseif ($action === 'discard') {
            $pdo->exec('DELETE FROM content_drafts');
            log_activity('content_draft_discard', 'Discarded landing page draft');
            flash('info', 'Draft discarded. Live content was preserved.');
        } elseif ($action === 'restore') {
            $versionId = (int)($_POST['version_id'] ?? 0);
            $statement = $pdo->prepare('SELECT snapshot_json FROM content_versions WHERE id = ?');
            $statement->execute([$versionId]);
            $data = json_decode((string)$statement->fetchColumn(), true);

            if (!is_array($data)) {
                throw new RuntimeException('Version could not be restored.');
            }

            $update = $pdo->prepare(
                'INSERT INTO content_drafts(content_key, content_value, updated_by)
                 VALUES(?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    content_value = VALUES(content_value),
                    updated_by = VALUES(updated_by)'
            );

            foreach ($data as $key => $value) {
                $update->execute([$key, (string)$value, (int)$_SESSION['escms_admin_id']]);
            }

            log_activity('content_restore', 'Restored content version to draft', ['version_id' => $versionId]);
            flash('success', 'Version restored as a draft.');
        }

        header('Location: content.php?section=' . rawurlencode($returnSection));
        exit;
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        header('Location: content.php?section=' . rawurlencode($returnSection));
        exit;
    }
}

$live = site_content();
$draft = draft_content();
$editing = $draft ? array_replace($live, $draft) : $live;
$versions = $pdo->query(
    'SELECT id, note, created_at FROM content_versions ORDER BY id DESC LIMIT 10'
)->fetchAll();

$sectionStates = [];
foreach ($sectionNavigator as $sectionKey => $section) {
    if ($section['type'] !== 'content' || !isset($contentGroups[$sectionKey])) {
        $sectionStates[$sectionKey] = [
            'label' => 'Managed',
            'class' => 'managed',
        ];
        continue;
    }

    $hasSectionDraft = false;
    foreach (array_keys($contentGroups[$sectionKey]['fields']) as $fieldKey) {
        if (array_key_exists($fieldKey, $draft) && (string)$draft[$fieldKey] !== (string)($live[$fieldKey] ?? '')) {
            $hasSectionDraft = true;
            break;
        }
    }

    $sectionStates[$sectionKey] = $hasSectionDraft
        ? ['label' => 'Draft', 'class' => 'draft']
        : ['label' => 'Published', 'class' => 'published'];
}

$selectedSection = $returnSection;
$selectedMeta = $sectionNavigator[$selectedSection];
$previewAnchor = $selectedMeta['anchor'];
$previewUrl = '../?preview=1&draft=1#' . rawurlencode($previewAnchor);

$pageTitle = 'Page Content';
$active = 'content';
require __DIR__ . '/_header.php';
?>

<div class="page-heading content-editor-heading">
    <div>
        <p class="eyebrow">PAGE CONTENT</p>
        <h1>Landing page section editor</h1>
        <p class="muted">Choose one section, edit it clearly and preview that exact area before publishing.</p>
    </div>

    <div class="heading-actions">
        <a class="button secondary" href="../?preview=1&draft=1" target="_blank" rel="noopener">Preview full draft</a>
        <a class="button ghost" href="../" target="_blank" rel="noopener">View live site</a>
    </div>
</div>

<div class="draft-status <?= $draft ? 'has-draft' : 'clean' ?>">
    <span class="status-dot"></span>
    <div>
        <strong><?= $draft ? 'Unpublished changes' : 'Website is up to date' ?></strong>
        <small>
            <?= $draft
                ? 'Some landing page content is saved as a draft and is not public yet.'
                : 'There are no pending landing page content changes.' ?>
        </small>
    </div>
</div>

<section class="content-section-switcher" aria-label="Landing page sections" data-section-switcher>
    <?php foreach ($sectionNavigator as $sectionKey => $section): ?>
        <?php $state = $sectionStates[$sectionKey]; ?>
        <button
            type="button"
            class="content-section-tab <?= $sectionKey === $selectedSection ? 'is-active' : '' ?>"
            data-section-tab="<?= h($sectionKey) ?>"
            data-section-title="<?= h($section['title']) ?>"
            data-section-description="<?= h($section['description']) ?>"
            data-section-anchor="<?= h($section['anchor']) ?>"
            aria-pressed="<?= $sectionKey === $selectedSection ? 'true' : 'false' ?>"
        >
            <span class="content-section-tab-icon" aria-hidden="true"><?= $section['icon'] ?></span>
            <span class="content-section-tab-copy">
                <strong><?= h($section['title']) ?></strong>
                <small><?= h($section['description']) ?></small>
            </span>
            <span class="content-section-state <?= h($state['class']) ?>" data-section-state="<?= h($sectionKey) ?>">
                <?= h($state['label']) ?>
            </span>
        </button>
    <?php endforeach; ?>
</section>

<div class="section-editor-context">
    <div>
        <span class="section-context-kicker">Editing now</span>
        <strong data-active-section-title><?= h($selectedMeta['title']) ?></strong>
        <small data-active-section-description><?= h($selectedMeta['description']) ?></small>
    </div>

    <div class="section-context-actions">
        <button type="button" class="button ghost small" data-section-previous>← Previous</button>
        <button type="button" class="button ghost small" data-section-next>Next →</button>
    </div>
</div>

<div class="editor-layout section-focused-editor">
    <div class="section-edit-column">
        <form method="post" class="cms-form section-content-form" data-section-content-form>
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="draft">
            <input type="hidden" name="return_section" value="<?= h($selectedSection) ?>" data-return-section>

            <?php foreach ($contentGroups as $groupKey => $group): ?>
                <section
                    class="content-card section-editor-card animate-in <?= $groupKey === $selectedSection ? 'is-active' : '' ?>"
                    data-content-editor="<?= h($groupKey) ?>"
                    <?= $groupKey === $selectedSection ? '' : 'hidden' ?>
                >
                    <header class="section-editor-card-head">
                        <div class="panel-icon"><?= $group['icon'] ?></div>
                        <div>
                            <p class="eyebrow">EDIT SECTION</p>
                            <h2><?= h($group['title']) ?></h2>
                            <p><?= h($group['description']) ?></p>
                        </div>
                    </header>

                    <div class="field-stack section-field-stack">
                        <?php foreach ($group['fields'] as $key => $meta): ?>
                            <label>
                                <span><?= h($meta[0]) ?></span>

                                <?php if ($meta[1] === 'textarea'): ?>
                                    <textarea name="<?= h($key) ?>"><?= h($editing[$key] ?? '') ?></textarea>
                                <?php else: ?>
                                    <input name="<?= h($key) ?>" value="<?= h($editing[$key] ?? '') ?>">
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($sectionNavigator[$groupKey]['manage_url'])): ?>
                        <div class="section-linked-tool">
                            <div>
                                <strong>More controls are available for this section</strong>
                                <small>Locations, map settings or business information are managed in their dedicated tool.</small>
                            </div>
                            <a class="button secondary small" href="<?= h($sectionNavigator[$groupKey]['manage_url']) ?>">
                                <?= h($sectionNavigator[$groupKey]['manage_label']) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

            <?php foreach ($sectionNavigator as $sectionKey => $section): ?>
                <?php if ($section['type'] !== 'module') continue; ?>

                <section
                    class="content-card section-module-card animate-in <?= $sectionKey === $selectedSection ? 'is-active' : '' ?>"
                    data-module-editor="<?= h($sectionKey) ?>"
                    <?= $sectionKey === $selectedSection ? '' : 'hidden' ?>
                >
                    <div class="section-module-visual" aria-hidden="true"><?= $section['icon'] ?></div>
                    <p class="eyebrow">DEDICATED MANAGER</p>
                    <h2><?= h($section['title']) ?></h2>
                    <p><?= h($section['description']) ?></p>
                    <p class="muted">
                        This section uses its own manager so the editor stays simple. Open it to add, edit, reorder or hide its items.
                    </p>
                    <a class="button" href="<?= h($section['manage_url']) ?>">
                        <?= h($section['manage_label']) ?>
                    </a>
                </section>
            <?php endforeach; ?>

            <div class="savebar section-editor-savebar" data-content-savebar>
                <?php if (user_can('content.edit')): ?>
                    <button type="submit">Save draft</button>

                    <?php if ($draft): ?>
                        <?php if (user_can('content.publish')): ?>
                            <button class="button publish" type="submit" name="action" value="publish">Publish changes</button>
                        <?php else: ?>
                            <button class="button publish" type="button" data-toggle-panel="approval-submit">Submit for approval</button>
                        <?php endif; ?>

                        <button
                            class="button danger-lite"
                            type="submit"
                            name="action"
                            value="discard"
                            data-swal-confirm="Discard this draft?"
                            data-swal-text="Your unpublished content changes will be removed."
                        >
                            Discard draft
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($draft && !user_can('content.publish') && user_can('content.edit')): ?>
            <section class="panel approval-submit-panel is-collapsed" id="approval-submit">
                <h3>Submit draft for approval</h3>
                <p class="muted">An Administrator or Owner can review the preview and publish it.</p>

                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="submit_approval">
                    <input type="hidden" name="return_section" value="<?= h($selectedSection) ?>" data-return-section-copy>

                    <label>
                        Note for reviewer
                        <textarea name="approval_note" placeholder="Optional context about these changes"></textarea>
                    </label>

                    <div class="form-actions">
                        <button>Send for approval</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <aside class="live-preview-panel section-preview-panel" data-section-preview>
        <div class="preview-panel-head section-preview-head">
            <div>
                <strong>Section preview</strong>
                <small>Draft preview focused on <span data-preview-section-name><?= h($selectedMeta['title']) ?></span>.</small>
            </div>
            <a href="<?= h($previewUrl) ?>" target="_blank" rel="noopener" data-preview-open>Open ↗</a>
        </div>

        <div class="preview-device-toolbar" aria-label="Preview device size">
            <span>Preview size</span>
            <div class="preview-device-buttons" role="group">
                <button type="button" class="is-active" data-preview-device="desktop" aria-pressed="true">Desktop</button>
                <button type="button" data-preview-device="tablet" aria-pressed="false">Tablet</button>
                <button type="button" data-preview-device="mobile" aria-pressed="false">Mobile</button>
            </div>
        </div>

        <div class="preview-device-stage" data-preview-stage="desktop">
            <iframe
                src="<?= h($previewUrl) ?>"
                title="Draft website section preview"
                data-section-preview-frame
            ></iframe>
        </div>
    </aside>
</div>

<section class="panel version-panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">HISTORY</p>
            <h2>Recent content versions</h2>
            <p class="muted">Publishing creates a restore point so previous landing page copy can be recovered.</p>
        </div>
    </div>

    <?php if (!$versions): ?>
        <div class="empty-state">
            <strong>No versions yet</strong>
            <p>A version is created before each publish.</p>
        </div>
    <?php else: ?>
        <div class="version-list">
            <?php foreach ($versions as $version): ?>
                <article>
                    <div>
                        <strong><?= h($version['note'] ?: 'Content version') ?></strong>
                        <small><?= h($version['created_at']) ?></small>
                    </div>

                    <?php if (user_can('content.edit')): ?>
                        <form
                            method="post"
                            data-swal-confirm="Restore this version?"
                            data-swal-text="It will be loaded as a draft."
                        >
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="restore">
                            <input type="hidden" name="version_id" value="<?= (int)$version['id'] ?>">
                            <input type="hidden" name="return_section" value="<?= h($selectedSection) ?>" data-return-section-copy>
                            <button class="button secondary small">Restore to draft</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
