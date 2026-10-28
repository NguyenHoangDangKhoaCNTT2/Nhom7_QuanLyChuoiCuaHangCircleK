<header class="top-navbar">
    <button class="btn-toggle-nav" id="sidebarCollapse" type="button" aria-label="Đóng/mở menu">
        <i class="fa-solid fa-bars-staggered"></i>
    </button>

    <div class="d-none d-sm-block">
        <h1 class="m-0 fw-bold" style="font-size: 1.2rem; color: var(--ink-900);"><?= e($pageTitle) ?></h1>
    </div>

    <div class="d-flex align-items-center ms-auto gap-2 gap-md-3">
        <div class="date-pill d-none d-xl-inline-flex">
            <i class="fa-regular fa-calendar-check" style="color: var(--ck-red)"></i>
            <span><?= e($homNay) ?></span>
            <span class="text-muted">|</span>
            <span id="liveClock" class="num-font fw-bold" style="color: var(--ck-red)"><?= date('H:i:s') ?></span>
        </div>

        <?php if (isset($_SESSION['vai_tro'])): ?>
        <div class="user-pill">
            <span class="user-avatar"><?= e(initial($_SESSION['ho_ten'])) ?></span>
            <div class="d-none d-md-block text-start lh-sm me-1">
                <div class="fw-bold" style="font-size: 0.86rem;"><?= e($_SESSION['ho_ten']) ?></div>
                <small style="font-size: 0.73rem; color: var(--ink-500);"><?= e($_SESSION['chuc_vu'] ?: 'Nhân viên') ?></small>
            </div>
        </div>
        <?php else: ?>
        <a href="<?= e(url('modules/tai_khoan_nhan_su/dang_nhap_phan_quyen/dang_nhap.php')) ?>" class="user-pill text-decoration-none">
            <span class="user-avatar"><i class="fa-solid fa-user" style="font-size:0.9rem;"></i></span>
            <div class="d-none d-md-block text-start lh-sm me-1">
                <div class="fw-bold" style="font-size: 0.86rem; color: var(--ink-900);">Đăng nhập</div>
                <small style="font-size: 0.73rem; color: var(--ink-500);">Chưa đăng nhập</small>
            </div>
        </a>
        <?php endif; ?>
    </div>
</header>