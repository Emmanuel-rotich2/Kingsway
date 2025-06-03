
<!--components/global/sidebar.php-->

<div class="sidebar<?php if (!empty($collapsed)) echo ' sidebar-collapsed'; ?>">
    <div class="shadow-sm" style="overflow-y: auto; flex: 1 1 0;">
        <!-- Logo and Title --><link rel="stylesheet" href="king.css">
        <div class="d-flex logo align-items-start" style="width:250px; min-width:60px;background-color:gold;">
            <img src="images/download (16).jpg" alt="Kingsway Logo" style="height:60px; width:60px; object-fit:cover; flex-shrink:0;">
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
                            <a href="<?php echo $sub['url']; ?>" class="list-group-item list-group-item-action ps-5">
                                <i class="<?php echo $sub['icon']; ?> me-2"></i>
                                <?php echo $sub['label']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $item['url']; ?>" class="list-group-item list-group-item-action">
                        <i class="<?php echo $item['icon']; ?> me-2"></i>
                        <span class="sidebar-text"><?php echo $item['label']; ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script src="../../js/index.js" type="text/js"></script>
<