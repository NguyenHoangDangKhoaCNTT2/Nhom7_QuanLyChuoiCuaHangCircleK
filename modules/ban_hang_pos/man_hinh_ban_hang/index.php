<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Màn hình bán hàng (POS)';
$activeMenu = 'pos';

require ROOT_PATH . '/includes/header.php';
?>

<div class="row g-4">
    <div class="col-12 col-xl-8">
<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-cash-register"></i></span>
                Chọn sản phẩm
            </h5>
            <div class="panel-subtitle ms-5">Quét mã vạch hoặc bấm vào sản phẩm để thêm vào giỏ</div>
        </div>
        <div class="segmented-control" role="group"><button type="button" class="seg-btn active">Tất cả</button><button type="button" class="seg-btn">Đồ uống</button><button type="button" class="seg-btn">Đồ ăn</button><button type="button" class="seg-btn">Snack</button></div>
    </div>
    <div class="search-bar"><i class="fa-solid fa-magnifying-glass s-ico"></i><input type="text" placeholder="Quét mã vạch hoặc tìm sản phẩm..."></div>
    <div class="row g-2 mt-2">
            <div class="col-6 col-md-4">
                <a href="#" class="prod-row text-decoration-none" style="border: 1px solid var(--border-light); color: inherit;">
                    <span class="prod-rank-icon" style="color: #f97316; background: color-mix(in srgb, #f97316 12%, transparent);"><i class="fa-solid fa-mug-hot"></i></span>
                    <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.88rem;">Cà phê sữa đá lon</div><small class="num-font" style="color: var(--ink-500);">30,000 ₫</small></div>
                </a>
            </div>
            <div class="col-6 col-md-4">
                <a href="#" class="prod-row text-decoration-none" style="border: 1px solid var(--border-light); color: inherit;">
                    <span class="prod-rank-icon" style="color: #e11d48; background: color-mix(in srgb, #e11d48 12%, transparent);"><i class="fa-solid fa-bowl-food"></i></span>
                    <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.88rem;">Mì Hảo Hảo ly</div><small class="num-font" style="color: var(--ink-500);">12,000 ₫</small></div>
                </a>
            </div>
            <div class="col-6 col-md-4">
                <a href="#" class="prod-row text-decoration-none" style="border: 1px solid var(--border-light); color: inherit;">
                    <span class="prod-rank-icon" style="color: #0ea5e9; background: color-mix(in srgb, #0ea5e9 12%, transparent);"><i class="fa-solid fa-bottle-water"></i></span>
                    <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.88rem;">Nước khoáng Aquafina</div><small class="num-font" style="color: var(--ink-500);">10,000 ₫</small></div>
                </a>
            </div>
            <div class="col-6 col-md-4">
                <a href="#" class="prod-row text-decoration-none" style="border: 1px solid var(--border-light); color: inherit;">
                    <span class="prod-rank-icon" style="color: #f59e0b; background: color-mix(in srgb, #f59e0b 12%, transparent);"><i class="fa-solid fa-bread-slice"></i></span>
                    <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.88rem;">Bánh mì Sandwich</div><small class="num-font" style="color: var(--ink-500);">22,000 ₫</small></div>
                </a>
            </div>
            <div class="col-6 col-md-4">
                <a href="#" class="prod-row text-decoration-none" style="border: 1px solid var(--border-light); color: inherit;">
                    <span class="prod-rank-icon" style="color: #8b5cf6; background: color-mix(in srgb, #8b5cf6 12%, transparent);"><i class="fa-solid fa-bolt"></i></span>
                    <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.88rem;">Nước tăng lực Sting</div><small class="num-font" style="color: var(--ink-500);">14,000 ₫</small></div>
                </a>
            </div>
            <div class="col-6 col-md-4">
                <a href="#" class="prod-row text-decoration-none" style="border: 1px solid var(--border-light); color: inherit;">
                    <span class="prod-rank-icon" style="color: #10b981; background: color-mix(in srgb, #10b981 12%, transparent);"><i class="fa-solid fa-cookie-bite"></i></span>
                    <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.88rem;">Snack khoai tây</div><small class="num-font" style="color: var(--ink-500);">18,000 ₫</small></div>
                </a>
            </div>
    </div>
</div>
    </div>
    <div class="col-12 col-xl-4">
<div class="bento-panel fade-up d-1">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))"><i class="fa-solid fa-basket-shopping"></i></span>
                Giỏ hàng
            </h5>
            <div class="panel-subtitle ms-5">Đơn đang bán</div>
        </div>
        <span class="order-code">#HD1025</span>
    </div>
        <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid var(--border-subtle);">
            <div><div class="fw-semibold" style="font-size: 0.9rem;">Cà phê sữa đá lon</div><small style="color: var(--ink-500);">2 × 30,000 ₫</small></div>
            <span class="num-font fw-bold">60,000 ₫</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid var(--border-subtle);">
            <div><div class="fw-semibold" style="font-size: 0.9rem;">Mì Hảo Hảo ly</div><small style="color: var(--ink-500);">1 × 12,000 ₫</small></div>
            <span class="num-font fw-bold">12,000 ₫</span>
        </div>
    <div class="mt-3 pt-3" style="border-top: 1px solid var(--border-light);">
        <div class="d-flex justify-content-between mb-2" style="color: var(--ink-500); font-size: 0.9rem;"><span>Tạm tính</span><span class="num-font fw-bold">72,000 ₫</span></div><div class="d-flex justify-content-between mb-2" style="color: var(--ink-500); font-size: 0.9rem;"><span>Khuyến mãi</span><span class="num-font fw-bold">- 0 ₫</span></div><div class="d-flex justify-content-between mb-2" style="color: var(--ink-500); font-size: 0.9rem;"><span>VAT 8%</span><span class="num-font fw-bold">5,760 ₫</span></div>
        <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="fw-bold">Tổng cộng</span>
            <span class="num-font fw-bold" style="font-size: 1.6rem; color: var(--ck-red);">77,760 ₫</span>
        </div>
    </div>
    <button type="button" class="btn-ck btn-ck-primary w-100 mt-3"><i class="fa-solid fa-credit-card"></i> Thanh toán</button>
    <button type="button" class="btn-ck btn-ck-soft w-100 mt-2">Hủy đơn</button>
</div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
