<!-- components/global/sidebar.php -->
<div class="app-sidebar" id="sidebar">
    <div class="app-sidebar-brand">
        <img
            src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png"
            alt="Kingsway Preparatory School"
            class="app-sidebar-logo"
            onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';"
        >

        <div class="app-sidebar-brand-copy">
            <strong>Kingsway</strong>
            <span>Preparatory School</span>
        </div>

        <button
            class="app-sidebar-close"
            id="sidebar-mobile-close"
            type="button"
            aria-label="Close navigation"
        >
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav class="app-sidebar-nav" id="sidebarMenu">
        <div class="app-sidebar-section-label">Navigation</div>

        <?php foreach ($sidebar_items as $item): ?>
            <?php
                $itemLabel = htmlspecialchars(
                    (string)($item['label'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                );
                $itemIcon = htmlspecialchars(
                    (string)($item['icon'] ?? 'bi bi-circle'),
                    ENT_QUOTES,
                    'UTF-8'
                );
                $itemUrl = htmlspecialchars(
                    (string)($item['url'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                );
                $submenuId =
                    'submenu-' . md5((string)($item['label'] ?? 'menu'));
            ?>

            <?php if (!empty($item['subitems'])): ?>
                <button
                    class="app-sidebar-item sidebar-toggle"
                    type="button"
                    data-submenu-target="#<?= $submenuId ?>"
                    aria-expanded="false"
                    aria-controls="<?= $submenuId ?>"
                    title="<?= $itemLabel ?>"
                >
                    <span class="app-sidebar-icon">
                        <i class="<?= $itemIcon ?>"></i>
                    </span>
                    <span class="sidebar-text"><?= $itemLabel ?></span>
                    <i class="bi bi-chevron-down app-sidebar-chevron"></i>
                </button>

                <div class="app-sidebar-submenu collapse" id="<?= $submenuId ?>">
                    <?php foreach ($item['subitems'] as $sub): ?>
                        <?php
                            $subLabel = htmlspecialchars(
                                (string)($sub['label'] ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            $subIcon = htmlspecialchars(
                                (string)($sub['icon'] ?? 'bi bi-dot'),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            $subUrl = htmlspecialchars(
                                (string)($sub['url'] ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>
                        <a
                            href="#"
                            data-route="<?= $subUrl ?>"
                            class="app-sidebar-subitem sidebar-link"
                            title="<?= $subLabel ?>"
                        >
                            <span class="app-sidebar-subicon">
                                <i class="<?= $subIcon ?>"></i>
                            </span>
                            <span class="sidebar-text"><?= $subLabel ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <a
                    href="#"
                    data-route="<?= $itemUrl ?>"
                    class="app-sidebar-item sidebar-link"
                    title="<?= $itemLabel ?>"
                >
                    <span class="app-sidebar-icon">
                        <i class="<?= $itemIcon ?>"></i>
                    </span>
                    <span class="sidebar-text"><?= $itemLabel ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="app-sidebar-footer">
        <span class="app-sidebar-footer-icon">
            <i class="bi bi-shield-check"></i>
        </span>
        <div class="sidebar-text">
            <strong>Secure session</strong>
            <small>JWT authenticated</small>
        </div>
    </div>
</div>
