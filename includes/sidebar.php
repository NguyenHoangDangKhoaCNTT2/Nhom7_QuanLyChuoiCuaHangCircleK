<aside id="sidebar" aria-label="Menu điều hướng">
    <div class="brand-box" style="padding:10px 6px 20px;">
        <img src="<?= e(asset('images/circleK.jpg')) ?>" alt="Circle K" style="width:100%;height:auto;max-height:70px;object-fit:contain;border-radius:12px;">
    </div>

        <?php foreach ($menu as $group): ?>
    <?php if (!empty($group['label'])): ?>
    <div class="menu-label"><?= e($group['label']) ?></div>
    <?php endif; ?>
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
        <?php if (isset($_SESSION['vai_tro'])): ?>
        <a href="<?= e(url('modules/tai_khoan_nhan_su/dang_nhap_phan_quyen/dang_xuat.php')) ?>" class="btn-logout">
            <i class="fa-solid fa-power-off"></i> Đăng xuất
        </a>
        <?php else: ?>
        <a href="<?= e(url('modules/tai_khoan_nhan_su/dang_nhap_phan_quyen/dang_nhap.php')) ?>" class="btn-logout">
            <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
        </a>
        <?php endif; ?>
    </div>
</aside>