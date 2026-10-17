<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Thanh toán đơn hàng';
$activeMenu = 'thanhtoan';

require ROOT_PATH . '/includes/header.php';
?>

<div class="row g-4">
    <div class="col-12 col-xl-7">
<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-credit-card"></i></span>
                Phương thức thanh toán
            </h5>
            <div class="panel-subtitle ms-5">Chọn cách khách trả tiền</div>
        </div>
        
    </div>
        <label class="store-item" style="cursor: pointer;">
            <input class="form-check-input mt-0" type="radio" name="pttt" checked>
            <span class="store-tag" style="background: var(--emerald)"><i class="fa-solid fa-money-bill-wave"></i></span>
            <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Tiền mặt</div><small style="color: var(--ink-500); font-size: 0.78rem;">Nhập số tiền khách đưa, hệ thống tính tiền thối</small></div>
        </label>
        <label class="store-item" style="cursor: pointer;">
            <input class="form-check-input mt-0" type="radio" name="pttt">
            <span class="store-tag" style="background: var(--ocean)"><i class="fa-solid fa-credit-card"></i></span>
            <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Thẻ ATM / Visa</div><small style="color: var(--ink-500); font-size: 0.78rem;">Quẹt thẻ qua máy POS</small></div>
        </label>
        <label class="store-item" style="cursor: pointer;">
            <input class="form-check-input mt-0" type="radio" name="pttt">
            <span class="store-tag" style="background: var(--violet)"><i class="fa-solid fa-qrcode"></i></span>
            <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">VNPay QR</div><small style="color: var(--ink-500); font-size: 0.78rem;">Khách quét mã QR để thanh toán</small></div>
        </label>
        <label class="store-item" style="cursor: pointer;">
            <input class="form-check-input mt-0" type="radio" name="pttt">
            <span class="store-tag" style="background: var(--ck-red)"><i class="fa-solid fa-wallet"></i></span>
            <div class="flex-grow-1"><div class="fw-bold" style="font-size: 0.9rem;">Ví MoMo</div><small style="color: var(--ink-500); font-size: 0.78rem;">Thanh toán bằng ví điện tử</small></div>
        </label>
        <div class="row g-3 mt-2">
            <div class="col-md-6"><label class="form-label" for="ttKhachDua">Tiền khách đưa (₫)</label><input type="number" class="form-control" id="ttKhachDua" placeholder=""></div>
            <div class="col-md-6"><label class="form-label" for="ttTienThoi">Tiền thối lại (₫)</label><input type="text" class="form-control" id="ttTienThoi" placeholder="0" readonly></div>
        </div>
</div>
    </div>
    <div class="col-12 col-xl-5">
<div class="bento-panel fade-up d-1">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))"><i class="fa-solid fa-receipt"></i></span>
                Tóm tắt đơn hàng
            </h5>
            <div class="panel-subtitle ms-5">Kiểm tra lại trước khi thanh toán</div>
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
            <span class="fw-bold">Cần thanh toán</span>
            <span class="num-font fw-bold" style="font-size: 1.6rem; color: var(--ck-red);">77,760 ₫</span>
        </div>
    </div>
    <button type="button" class="btn-ck btn-ck-primary w-100 mt-3"><i class="fa-solid fa-circle-check"></i> Xác nhận thanh toán</button>
    <button type="button" class="btn-ck btn-ck-soft w-100 mt-2">Quay lại giỏ hàng</button>
</div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
