<aside id="sidebar" aria-label="Menu điều hướng">
    <div class="brand-box">
        <div class="brand-icon">K</div>
        <div>
            <div class="brand-name">CIRCLE K</div>
            <div class="brand-sub">Enterprise OS</div>
        </div>
    </div>

    <?php foreach ($menu as $group): ?>
    <div class="menu-label"><?= e($group['label']) ?></div>
    <ul class="menu-list">
        <?php foreach ($group['items'] as $m): ?>
        <li class="nav-item-ck">
            <a href="<?= e(url($m['href'])) ?>"
               class="nav-link-ck<?= $m['key'] === $activeMenu ? ' active' : '' ?>"
               style="--item-color: <?= e($m['color']) ?>"
               <?= $m['key'] === $activeMenu ? 'aria-current="page"' : '' ?>>
                <span class="nav-ico"><i class="fa-solid <?= e($m['icon']) ?>"></i></span>
                <span><?= e($m['label']) ?></span>
                <?php if (!empty($m['badge'])): ?>
                    <span class="nav-badge"><?= e($m['badge']) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endforeach; ?>

    <div class="sidebar-footer">
        <div class="sys-status">
            <span class="pulse-dot"></span>
            <span>Cloud Sync: <strong>Hoạt động</strong></span>
        </div>
        <a href="#" class="btn-logout">
            <i class="fa-solid fa-power-off"></i> Đăng xuất ca
        </a>
    </div>
</aside>
