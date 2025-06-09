<!--components/global/sidebar.php-->
<div class="sidebar" id="sidebar">
    <div class="shadow-sm" style="overflow-y: auto; flex: 1 1 0;">
        <!-- Logo and Title -->
        <div class="d-flex logo align-items-start" style="width:250px; min-width:60px;">
            <img src="/Kingsway/images/download (16).jpg" alt="Kingsway Logo" class="school-logo">
            <div class="ms-2 logo-name">
                <h5 class="mb-0">KINGSWAY PREPARATORY SCHOOL</h5>
            </div>
        </div>
        <div class="list-group list-group-flush" id="sidebarMenu">
            <?php foreach ($sidebar_items as $item): ?>
                <?php if (isset($item['subitems'])): ?>
                    <a href="#submenu-<?php echo md5($item['label']); ?>"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center sidebar-toggle"
                       data-bs-toggle="collapse"
                       aria-expanded="false"
                       aria-controls="submenu-<?php echo md5($item['label']); ?>">
                        <span>
                            <i class="<?php echo $item['icon']; ?> me-2"></i>
                            <span class="sidebar-text"><?php echo $item['label']; ?></span>
                        </span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <div class="collapse" id="submenu-<?php echo md5($item['label']); ?>" data-bs-parent="#sidebarMenu">
                        <?php foreach ($item['subitems'] as $sub): ?>
                            <a href="#" data-route="<?php echo $sub['url']; ?>" class="list-group-item list-group-item-action ps-5 sidebar-link">
                                <i class="<?php echo $sub['icon']; ?> me-2"></i>
                                <?php echo $sub['label']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <a href="#" data-route="<?php echo $item['url']; ?>" class="list-group-item list-group-item-action sidebar-link">
                        <i class="<?php echo $item['icon']; ?> me-2"></i>
                        <span class="sidebar-text"><?php echo $item['label']; ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<!-- No inline script, all sidebar logic handled in index.js -->