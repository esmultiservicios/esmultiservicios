<?php
$pageTitle = $pageTitle ?? "ES MULTISERVICIOS Admin";
$active = $active ?? '';
$flash = take_flash();
$admin = current_admin();
$set = settings();
$brand = $set['admin_brand_name'] ?? "ES MULTISERVICIOS Admin";
$brandLogo = $set['admin_logo_path'] ?? '';
$newEst = (int) db()->query("SELECT COUNT(*) FROM estimate_requests WHERE status='new'")->fetchColumn();
$unreadNotes = unread_notification_count();
$bellNotes = recent_notifications(6);
$maintenance = ($set['maintenance_mode'] ?? '0') === '1';
$avatar = $admin['avatar_path'] ?? '';
$favicon = trim((string) ($set['favicon_path'] ?? '')) ?: 'assets/brand/favicon.png';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= h($pageTitle) ?></title>
    <?php if ($favicon !== ''): ?>
    <link rel="icon" href="../<?= h($favicon) ?>">
    <link rel="shortcut icon" href="../<?= h($favicon) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/vendor/show-notify/showNotify.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<header class="admin-header">
    <div class="admin-head-inner">
        <div class="head-left">
            <button
                class="icon-btn mobile-menu"
                type="button"
                aria-label="Open menu"
                aria-expanded="false"
                data-sidebar-toggle
            >
                <span class="mobile-menu-glyph" aria-hidden="true">☰</span>
            </button>

            <a class="admin-brand" href="dashboard.php">
                <?php if ($brandLogo !== ''): ?><img src="../<?= h($brandLogo) ?>" alt=""><?php endif; ?>
                <span>
                    <strong><?= h($brand) ?></strong>
                    <small>Content Management System</small>
                </span>
            </a>
        </div>

        <div class="top-shortcuts">
            <button class="quick-link admin-find-trigger" type="button" data-admin-find-open title="Quick find">
                <span class="find-glyph" aria-hidden="true">⌕</span>
                <span>Quick find</span>
            </button>

            <a class="quick-link" href="dashboard.php" title="Dashboard">
                <?= icon('dashboard') ?>
                <span>Dashboard</span>
            </a>

            <?php if (user_can('content.view')): ?>
                <a class="quick-link header-extra" href="content.php" title="Page content">
                    <?= icon('edit') ?>
                    <span>Content</span>
                </a>
            <?php endif; ?>

            <?php if (user_can('gallery.manage')): ?>
                <a class="quick-link header-extra" href="gallery.php" title="Gallery">
                    <?= icon('image') ?>
                    <span>Gallery</span>
                </a>
            <?php endif; ?>

            <?php if (user_can('estimates.view')): ?>
                <a class="quick-link" href="estimates.php" title="Estimate requests">
                    <?= icon('mail') ?>
                    <span>Requests</span>
                    <?php if ($newEst): ?>
                        <b><?= $newEst ?></b>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if (user_can('settings.manage')): ?>
                <a
                    class="quick-link status-link <?= $maintenance ? 'maintenance' : '' ?>"
                    href="settings.php#site-status"
                    title="Website status"
                >
                    <span class="status-dot"></span>
                    <span><?= $maintenance ? 'Maintenance' : 'Live' ?></span>
                </a>
            <?php endif; ?>

            <a class="quick-link public-view-link" href="../" target="_blank" rel="noopener" title="View public website">
                <?= icon('eye') ?>
                <span>View site</span>
            </a>
        </div>

        <div class="mobile-header-actions" aria-label="Quick website controls">
            <button class="mobile-view mobile-find" type="button" data-admin-find-open aria-label="Quick find" title="Quick find">
                <span class="mobile-view-glyph" aria-hidden="true">⌕</span>
                <b>Find</b>
            </button>

            <?php if (user_can('settings.manage')): ?>
                <a
                    class="mobile-status <?= $maintenance ? 'maintenance' : '' ?>"
                    href="settings.php#site-status"
                    title="Website status"
                >
                    <span class="status-dot"></span>
                    <b><?= $maintenance ? 'Maintenance' : 'Live' ?></b>
                </a>
            <?php else: ?>
                <span class="mobile-status <?= $maintenance ? 'maintenance' : '' ?>">
                    <span class="status-dot"></span>
                    <b><?= $maintenance ? 'Maintenance' : 'Live' ?></b>
                </span>
            <?php endif; ?>

            <a class="mobile-view" href="../" target="_blank" rel="noopener" title="View website" aria-label="View website">
                <span class="mobile-view-glyph" aria-hidden="true">↗</span>
                <b>Site</b>
            </a>
        </div>

        <details class="notification-bell">
            <summary class="bell-button" aria-label="Notifications" title="Notifications">
                <?= icon('bell') ?>
                <?php if ($unreadNotes): ?>
                    <b><?= $unreadNotes > 99 ? '99+' : $unreadNotes ?></b>
                <?php endif; ?>
            </summary>

            <div class="bell-dropdown">
                <div class="bell-head">
                    <div>
                        <strong>Notifications</strong>
                        <small><?= $unreadNotes ?> unread</small>
                    </div>
                    <a href="notifications.php">View all</a>
                </div>

                <div class="bell-list">
                    <?php if (!$bellNotes): ?>
                        <div class="bell-empty">No notifications yet.</div>
                    <?php endif; ?>

                    <?php foreach ($bellNotes as $note): ?>
                        <a
                            class="bell-item <?= $note['read_by_me'] ? 'read' : '' ?>"
                            href="notification-action.php?id=<?= $note['id'] ?>"
                        >
                            <span class="notification-dot <?= h($note['notification_type']) ?>"></span>
                            <div>
                                <strong><?= h($note['title']) ?></strong>
                                <small><?= h($note['message']) ?></small>
                                <time><?= h($note['created_at']) ?></time>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>

        <details class="profile-menu">
            <summary>
                <?php if ($avatar): ?>
                    <img src="../<?= h($avatar) ?>" alt="Profile">
                <?php else: ?>
                    <span class="avatar-fallback"><?= strtoupper(substr($admin['full_name'] ?: $admin['username'], 0, 1)) ?></span>
                <?php endif; ?>

                <span class="profile-copy">
                    <strong><?= h($admin['full_name'] ?: $admin['username']) ?></strong>
                    <small><?= h($admin['role_name'] ?: 'Administrator') ?> · <?= h($admin['email'] ?: 'No email') ?></small>
                </span>
                <span class="chev">⌄</span>
            </summary>

            <div class="profile-dropdown">
                <div class="profile-head">
                    <strong><?= h($admin['full_name'] ?: $admin['username']) ?></strong>
                    <span><?= h($admin['email'] ?: $admin['username']) ?></span>
                </div>

                <a href="profile.php"><?= icon('user') ?>Profile & security</a>

                <?php if (user_can('email.manage')): ?>
                    <a href="email.php"><?= icon('mail') ?>Email configuration</a>
                <?php endif; ?>

                <?php if (user_can('settings.manage')): ?>
                    <a href="appearance.php"><?= icon('eye') ?>Appearance</a>
                    <a href="settings.php"><?= icon('gear') ?>Site settings</a>
                <?php endif; ?>

                <a href="notifications.php">
                    <?= icon('mail') ?>Notifications
                    <?php if ($unreadNotes): ?>
                        <b class="menu-count"><?= $unreadNotes ?></b>
                    <?php endif; ?>
                </a>
                <hr>
                <a class="logout-link" href="logout.php" data-logout-confirm>Log out</a>
            </div>
        </details>
    </div>
</header>

<div class="admin-shell">
    <aside class="admin-sidebar" data-sidebar>
        <div class="sidebar-label">MANAGE WEBSITE</div>
        <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
            <?= icon('dashboard') ?><span>Dashboard</span>
        </a>

        <?php if (user_can('content.view')): ?>
            <a class="<?= $active === 'content' ? 'active' : '' ?>" href="content.php">
                <?= icon('edit') ?><span>Page content</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('marketing.manage')): ?>
            <a class="<?= $active === 'marketing' ? 'active' : '' ?>" href="marketing.php">
                <?= icon('eye') ?><span>Marketing site ES/EN</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('sections.manage')): ?>
            <a class="<?= $active === 'sections' ? 'active' : '' ?>" href="sections.php">
                <?= icon('dashboard') ?><span>Section manager</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('media.manage')): ?>
            <a class="<?= $active === 'media' ? 'active' : '' ?>" href="media.php">
                <?= icon('image') ?><span>Media Library</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('services.manage')): ?>
            <a class="<?= $active === 'services' ? 'active' : '' ?>" href="services.php">
                <?= icon('tools') ?><span>Services</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('gallery.manage')): ?>
            <a class="<?= $active === 'gallery' ? 'active' : '' ?>" href="gallery.php">
                <?= icon('image') ?><span>Gallery</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('videos.manage')): ?>
            <a class="<?= $active === 'videos' ? 'active' : '' ?>" href="videos.php">
                <?= icon('eye') ?><span>Videos</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('areas.manage')): ?>
            <a class="<?= $active === 'areas' ? 'active' : '' ?>" href="areas.php">
                <?= icon('pin') ?><span>Service areas</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('tips.manage')): ?>
            <a class="<?= $active === 'tips' ? 'active' : '' ?>" href="tips.php">
                <?= icon('bulb') ?><span>Home tips</span>
            </a>
        <?php endif; ?>

        <div class="sidebar-label">BUSINESS</div>

        <?php if (user_can('estimates.view')): ?>
            <a class="<?= $active === 'estimates' ? 'active' : '' ?>" href="estimates.php">
                <?= icon('mail') ?><span>Estimate requests</span>
                <?php if ($newEst): ?><em><?= $newEst ?></em><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (user_can('email.manage')): ?>
            <a class="<?= $active === 'email' ? 'active' : '' ?>" href="email.php">
                <?= icon('mail') ?><span>Email</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('integrations.manage')): ?>
            <a class="<?= $active === 'integrations' ? 'active' : '' ?>" href="integrations.php">
                <?= icon('api') ?><span>Integrations & APIs</span>
            </a>
        <?php endif; ?>

        <div class="sidebar-label">OPTIMIZE</div>

        <?php if (user_can('seo.manage')): ?>
            <a class="<?= $active === 'seo' ? 'active' : '' ?>" href="seo.php">
                <?= icon('eye') ?><span>SEO Manager</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('health.view')): ?>
            <a class="<?= $active === 'health' ? 'active' : '' ?>" href="health.php">
                <?= icon('gear') ?><span>Website Health</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('notifications.view')): ?>
            <a class="<?= $active === 'notifications' ? 'active' : '' ?>" href="notifications.php">
                <?= icon('mail') ?><span>Activity Center</span>
                <?php if ($unreadNotes): ?><em><?= $unreadNotes ?></em><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (user_can('backups.manage')): ?>
            <a class="<?= $active === 'backups' ? 'active' : '' ?>" href="backups.php">
                <?= icon('dashboard') ?><span>Backup & Restore</span>
            </a>
        <?php endif; ?>

        <div class="sidebar-label">ADMINISTRATION</div>

        <?php if (user_can('users.manage')): ?>
            <a class="<?= $active === 'users' ? 'active' : '' ?>" href="users.php">
                <?= icon('users') ?><span>Users</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('roles.manage')): ?>
            <a class="<?= $active === 'roles' ? 'active' : '' ?>" href="roles.php">
                <?= icon('shield') ?><span>Roles & permissions</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('content.approve')): ?>
            <a class="<?= $active === 'approvals' ? 'active' : '' ?>" href="approvals.php">
                <?= icon('approval') ?><span>Approval queue</span>
            </a>
        <?php endif; ?>

        <?php if (user_can('security.manage')): ?>
            <a class="<?= $active === 'security' ? 'active' : '' ?>" href="security.php">
                <?= icon('shield') ?><span>Security Center</span>
            </a>
        <?php endif; ?>

        <div class="sidebar-label">SYSTEM</div>

        <?php if (user_can('settings.manage')): ?>
            <a class="<?= $active === 'appearance' ? 'active' : '' ?>" href="appearance.php">
                <?= icon('eye') ?><span>Appearance</span>
            </a>
            <a class="<?= $active === 'settings' ? 'active' : '' ?>" href="settings.php">
                <?= icon('gear') ?><span>Settings</span>
            </a>
        <?php endif; ?>

        <a class="<?= $active === 'profile' ? 'active' : '' ?>" href="profile.php">
            <?= icon('user') ?><span>Profile & security</span>
        </a>
    </aside>

    <div class="sidebar-backdrop" data-sidebar-backdrop></div>

    <main class="admin-main admin-content">
        <?php if ($flash): ?>
            <div
                hidden
                data-flash-message="<?= h($flash['message']) ?>"
                data-flash-type="<?= h($flash['type']) ?>"
            ></div>
        <?php endif; ?>

        <div class="admin-find" data-admin-find hidden>
            <div class="admin-find-backdrop" data-admin-find-close></div>
            <section class="admin-find-panel" role="dialog" aria-modal="true" aria-label="Quick find">
                <div class="admin-find-head">
                    <div>
                        <strong>Quick find</strong>
                        <small>Type what you want to manage.</small>
                    </div>
                    <button type="button" class="icon-btn" data-admin-find-close aria-label="Close">×</button>
                </div>
                <label class="admin-find-input">
                    <span aria-hidden="true">⌕</span>
                    <input type="search" placeholder="Try: banner, users, gallery, email..." data-admin-find-input>
                </label>
                <div class="admin-find-results" data-admin-find-results></div>
            </section>
        </div>
