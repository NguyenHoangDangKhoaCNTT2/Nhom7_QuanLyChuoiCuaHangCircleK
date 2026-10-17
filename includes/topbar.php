<header class="top-navbar">
    <button class="btn-toggle-nav" id="sidebarCollapse" type="button" aria-label="Đóng/mở menu">
        <i class="fa-solid fa-bars-staggered"></i>
    </button>

    <div class="d-none d-sm-block">
        <h1 class="m-0 fw-bold" style="font-size: 1.2rem; color: var(--ink-900);"><?= e($pageTitle) ?></h1>
    </div>

    <div class="search-bar ms-lg-3 d-none d-md-block">
        <i class="fa-solid fa-magnifying-glass s-ico"></i>
        <input type="text" id="quickSearch" placeholder="Tìm hóa đơn, mã SKU, cửa hàng...">
        <span class="kbd-shortcut">Ctrl K</span>
    </div>

    <div class="d-flex align-items-center ms-auto gap-2 gap-md-3">
        <div class="date-pill d-none d-xl-inline-flex">
            <i class="fa-regular fa-calendar-check" style="color: var(--ck-red)"></i>
            <span><?= e($homNay) ?></span>
            <span class="text-muted">|</span>
            <span id="liveClock" class="num-font fw-bold" style="color: var(--ck-red)"><?= date('H:i:s') ?></span>
        </div>

        <div class="dropdown">
            <a class="action-icon-btn" href="#" data-bs-toggle="dropdown" aria-label="Thông báo">
                <i class="fa-regular fa-bell"></i>
                <?php if ($notifs): ?><span class="notif-badge"><?= count($notifs) ?></span><?php endif; ?>
            </a>
            <div class="dropdown-menu dropdown-menu-end dropdown-menu-modern mt-2" style="width: 350px;">
                <div class="d-flex justify-content-between align-items-center px-2 py-1 mb-2">
                    <span class="fw-bold" style="font-size: 0.95rem;">Thông báo hệ thống</span>
                    <?php if ($notifs): ?><span class="badge rounded-pill" style="background: #ffe4e6; color: var(--ck-red);"><?= count($notifs) ?> mới</span><?php endif; ?>
                </div>
                <?php if (!$notifs): ?>
                <div class="text-center py-4" style="font-size: 0.86rem; color: var(--ink-400);">Chưa có thông báo nào</div>
                <?php endif; ?>
                <?php foreach ($notifs as $n): ?>
                <a class="dropdown-item rounded-3 p-2 d-flex align-items-start gap-3 mb-1" href="#" style="white-space: normal;">
                    <span class="d-grid flex-shrink-0 mt-1" style="place-items:center;width:38px;height:38px;border-radius:11px;color:<?= e($n[1]) ?>;background:color-mix(in srgb, <?= e($n[1]) ?> 14%, transparent)">
                        <i class="fa-solid <?= e($n[0]) ?>"></i>
                    </span>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between">
                            <strong style="font-size: 0.84rem; color: var(--ink-900);"><?= e($n[2]) ?></strong>
                            <small style="font-size: 0.72rem; color: var(--ink-400);"><?= e($n[4]) ?></small>
                        </div>
                        <div style="font-size: 0.82rem; color: var(--ink-500);"><?= e($n[3]) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
                <hr class="dropdown-divider my-2">
                <a class="dropdown-item text-center fw-bold rounded-2 py-2" style="font-size: 0.84rem; color: var(--ck-red);" href="#">
                    Xem tất cả cảnh báo <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <div class="dropdown">
            <a class="user-pill dropdown-toggle" href="#" data-bs-toggle="dropdown">
                <span class="user-avatar"><?= e(initial($currentUser['name'])) ?></span>
                <div class="d-none d-md-block text-start lh-sm me-1">
                    <div class="fw-bold" style="font-size: 0.86rem;"><?= e($currentUser['name']) ?></div>
                    <small style="font-size: 0.73rem; color: var(--ink-500);"><?= e($currentUser['role']) ?></small>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-modern mt-2">
                <li><a class="dropdown-item rounded-2 py-2" href="#"><i class="fa-regular fa-id-badge me-2 text-primary"></i> Hồ sơ quản trị</a></li>
                <li><a class="dropdown-item rounded-2 py-2" href="#"><i class="fa-solid fa-sliders me-2 text-secondary"></i> Cấu hình chuỗi</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item rounded-2 py-2 fw-semibold text-danger" href="#"><i class="fa-solid fa-right-from-bracket me-2"></i> Đăng xuất</a></li>
            </ul>
        </div>
    </div>
</header>
