<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$vaiTro = $_SESSION['vai_tro'] ?? null;
if (!is_array($vaiTro) || !in_array('xem_hoadon', $vaiTro, true)) {
    header('Location: ' . url('index.php'));
    exit;
}

$host = '127.0.0.1'; $db = 'circlek'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('<div style="padding:20px;color:#e11d48;font-family:sans-serif;">Không thể kết nối CSDL.</div>');
}

try {
    $tenKhachColumn = $pdo->query("SHOW COLUMNS FROM hoadon LIKE 'ten_khach_nhap'")->fetch();
    if (!$tenKhachColumn) {
        $pdo->exec("ALTER TABLE hoadon ADD COLUMN ten_khach_nhap VARCHAR(100) NULL");
    }
} catch (PDOException $e) {
    die('Không thể cập nhật cột tên khách hàng: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$cuaHangId = (int)($_SESSION['cuahang_id'] ?? 0);
if ($cuaHangId <= 0) {
    die('<div style="padding:20px;color:#e11d48;font-family:sans-serif;">Tài khoản chưa được gán cửa hàng.</div>');
}

if (($_GET['ajax'] ?? '') === 'chi_tiet') {
    header('Content-Type: application/json; charset=utf-8');
    $hoadonId = (int)($_GET['id'] ?? 0);
    try {
        $paymentColumns = $pdo->query("SHOW COLUMNS FROM thanhtoan")->fetchAll(PDO::FETCH_COLUMN, 0);
        $paymentGivenSql = in_array('tien_khach_dua', $paymentColumns, true) ? 'tt.tien_khach_dua' : 'NULL AS tien_khach_dua';
        $paymentChangeSql = in_array('tien_thoi', $paymentColumns, true) ? 'tt.tien_thoi' : 'NULL AS tien_thoi';
        $sqlDetail = "SELECT hd.*, ch.ma_cua_hang, ch.ten_cua_hang, ch.dia_chi AS dia_chi_ch,
                             ch.so_dien_thoai AS sdt_ch, nv.ho_ten AS ten_nv,
                             COALESCE(NULLIF(TRIM(hd.ten_khach_nhap), ''), kh.ho_ten, 'Khách lẻ') AS ten_kh,
                             kh.so_dien_thoai AS sdt_kh, kh.diem_tich_luy, kh.ngay_dang_ky,
                             ttd.hang_the, km.ten_km, km.ma_voucher,
                             tt.phuong_thuc, tt.so_tien_thanh_toan, tt.thoi_gian_thanh_toan,
                             $paymentGivenSql, $paymentChangeSql
                      FROM hoadon hd
                      LEFT JOIN cuahang ch ON ch.id = hd.cuahang_id
                      LEFT JOIN nhanvien nv ON nv.id = hd.nhanvien_id
                      LEFT JOIN khachhang kh ON kh.id = hd.khachhang_id
                      LEFT JOIN thetichdiem ttd ON ttd.id = kh.thetichdiem_id
                      LEFT JOIN khuyenmai km ON km.id = hd.khuyenmai_id
                      LEFT JOIN thanhtoan tt ON tt.id = (
                          SELECT MAX(t2.id) FROM thanhtoan t2 WHERE t2.hoadon_id = hd.id
                      )
                      WHERE hd.id = ? AND hd.cuahang_id = ?";
        $stmtHd = $pdo->prepare($sqlDetail);
        $stmtHd->execute([$hoadonId, $cuaHangId]);
        $hd = $stmtHd->fetch();
        if (!$hd) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn tại cửa hàng của bạn.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmtCt = $pdo->prepare("SELECT ct.so_luong, ct.don_gia, ct.thanh_tien,
                                        sp.ten_san_pham, sp.ma_san_pham
                                 FROM chitiethoadon ct
                                 JOIN sanpham sp ON sp.id = ct.sanpham_id
                                 WHERE ct.hoadon_id = ? ORDER BY ct.id ASC");
        $stmtCt->execute([$hoadonId]);
        $hd['chi_tiet'] = $stmtCt->fetchAll();
        $hd['so_mat_hang'] = count($hd['chi_tiet']);
        $hd['so_san_pham'] = array_sum(array_map(static fn($sp) => (int)$sp['so_luong'], $hd['chi_tiet']));
        $hd['vat_tinh_tu_hoa_don'] = max(0, (float)$hd['khach_phai_tra'] - ((float)$hd['tong_tien'] - (float)$hd['tien_giam_gia']));
        echo json_encode(['success' => true, 'hoadon' => $hd], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Không thể tải chi tiết hóa đơn.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$ngayXem   = trim($_GET['ngay'] ?? '');
$timKhach  = trim($_GET['khach'] ?? '');

$where  = "WHERE hd.cuahang_id = ?";
$params = [$cuaHangId];
if ($ngayXem !== '') {
    $where .= " AND DATE(hd.ngay_tao) = ?";
    $params[] = $ngayXem;
}
if ($timKhach !== '') {
    $where .= " AND (kh.ho_ten LIKE ? OR hd.ten_khach_nhap LIKE ? OR kh.so_dien_thoai LIKE ?)";
    $params[] = "%$timKhach%"; $params[] = "%$timKhach%"; $params[] = "%$timKhach%";
}

$stmt = $pdo->prepare("SELECT hd.id, hd.ngay_tao, hd.tong_tien, hd.tien_giam_gia, hd.khach_phai_tra, hd.trang_thai,
                               COALESCE(NULLIF(TRIM(hd.ten_khach_nhap), ''), kh.ho_ten, 'Khách lẻ') AS ten_kh,
                               nv.ho_ten AS ten_nv
                        FROM hoadon hd
                        LEFT JOIN khachhang kh ON kh.id = hd.khachhang_id
                        LEFT JOIN nhanvien nv ON nv.id = hd.nhanvien_id
                        $where
                        ORDER BY hd.ngay_tao DESC, hd.id DESC
                        LIMIT 200");
$stmt->execute($params);
$dsHoaDon = $stmt->fetchAll();

$tenCH = $pdo->prepare("SELECT ten_cua_hang FROM cuahang WHERE id = ?");
$tenCH->execute([$cuaHangId]);
$tenCuaHang = $tenCH->fetchColumn();

$pageTitle  = 'Xem hóa đơn';
$activeMenu = 'xem_hoadon';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-receipt"></i></span>
                Hóa đơn - <?= htmlspecialchars($tenCuaHang) ?>
            </h5>
            <div class="panel-subtitle ms-5">Chỉ xem, không chỉnh sửa được - <span class="ms-2 badge rounded-pill bg-light text-dark border" style="font-size:0.85rem;">Tổng: <?= count($dsHoaDon) ?> hóa đơn</span></div>
        </div>
    </div>

    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass s-ico"></i>
            <input type="text" name="khach" value="<?= htmlspecialchars($timKhach) ?>" placeholder="Tìm theo tên khách hàng, SĐT...">
        </div>
        <input type="date" name="ngay" class="form-control" style="width: auto;" value="<?= htmlspecialchars($ngayXem) ?>">
        <button type="submit" class="btn-ck btn-ck-soft"><i class="fa-solid fa-filter"></i> Lọc</button>
        <?php if ($ngayXem !== '' || $timKhach !== ''): ?>
            <a href="index.php" class="btn-ck btn-ck-soft text-danger"><i class="fa-solid fa-rotate-left"></i> Đặt lại</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Mã HĐ</th><th>Ngày giờ</th><th>Khách hàng</th><th>Nhân viên bán</th><th class="text-end">Khách phải trả</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($dsHoaDon)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-receipt fs-3 d-block mb-2"></i>Không có hóa đơn nào khớp bộ lọc.</td></tr>
                <?php else: foreach ($dsHoaDon as $hd): ?>
                <tr>
                    <td><span class="order-code">#HD-<?= (int)$hd['id'] ?></span></td>
                    <td class="small"><?= date('d/m/Y H:i', strtotime($hd['ngay_tao'])) ?></td>
                    <td><?= htmlspecialchars($hd['ten_kh']) ?></td>
                    <td><?= htmlspecialchars($hd['ten_nv'] ?? '') ?></td>
                    <td class="text-end"><span class="num-font fw-bold text-danger"><?= number_format((float)$hd['khach_phai_tra']) ?> ₫</span></td>
                    <td><?= htmlspecialchars($hd['trang_thai']) ?></td>
                    <td class="text-end"><button type="button" class="action-icon-btn sm" title="Xem chi tiết" onclick="xemChiTiet(<?= (int)$hd['id'] ?>)"><i class="fa-solid fa-eye"></i></button></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalChiTiet" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="ctTitle">Chi tiết hóa đơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body" id="ctBody">
                <div class="text-center py-4 text-muted">Đang tải...</div>
            </div>
        </div>
    </div>
</div>

<style>
#modalChiTiet .modal-content { border: 0; border-radius: 18px; overflow: hidden; }
#modalChiTiet .modal-header { border-bottom: 1px solid #e9eef5; }
#modalChiTiet .modal-body { background: #f8fafc; }
#modalChiTiet .ct-card { background: #fff; border: 1px solid #e7ecf3; border-radius: 13px; padding: 18px; height: 100%; }
#modalChiTiet .ct-card-title { font-size: .92rem; color: #172033; font-weight: 800; margin-bottom: 13px; }
#modalChiTiet .ct-field { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 9px; font-size: .86rem; }
#modalChiTiet .ct-field > span:first-child { color: #64748b; flex-shrink: 0; }
#modalChiTiet .ct-field > span:last-child { color: #1e293b; font-weight: 650; text-align: right; overflow-wrap: anywhere; }
#modalChiTiet .ct-table th { background: #f1f5f9; color: #475569; font-size: .78rem; white-space: nowrap; }
#modalChiTiet .ct-table td { font-size: .86rem; vertical-align: middle; }
#modalChiTiet .ct-total { color: #e11d48; font-size: 1.38rem; font-weight: 850; }
#modalChiTiet .ct-note { font-size: .76rem; color: #94a3b8; }
</style>
<script>
function ctEsc(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function ctMoney(value) {
    return Number(value || 0).toLocaleString('vi-VN') + ' ₫';
}
function ctNumber(value) {
    return Number(value || 0).toLocaleString('vi-VN');
}
function ctTime(value) {
    if (!value) return 'Chưa ghi nhận';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return ctEsc(value);
    return parsed.toLocaleString('vi-VN', {hour12: false});
}
function ctField(label, value) {
    return `<div class="ct-field"><span>${ctEsc(label)}</span><span>${value}</span></div>`;
}
function xemChiTiet(id) {
    const body = document.getElementById('ctBody');
    document.getElementById('ctTitle').innerText = 'Chi tiết hóa đơn #HD-' + id;
    body.innerHTML = '<div class="text-center py-4 text-muted">Đang tải...</div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalChiTiet')).show();
    fetch('index.php?ajax=chi_tiet&id=' + encodeURIComponent(id))
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Không thể tải chi tiết hóa đơn.');
            return data.hoadon;
        })
        .then(hd => {
            const items = Array.isArray(hd.chi_tiet) ? hd.chi_tiet : [];
            const rows = items.map((ct, i) => `
                <tr>
                    <td class="text-center">${i + 1}</td>
                    <td class="text-nowrap">${ctEsc(ct.ma_san_pham)}</td>
                    <td>${ctEsc(ct.ten_san_pham)}</td>
                    <td class="text-end text-nowrap">${ctMoney(ct.don_gia)}</td>
                    <td class="text-center">${ctNumber(ct.so_luong)}</td>
                    <td class="text-end text-nowrap fw-semibold">${ctMoney(ct.thanh_tien)}</td>
                </tr>`).join('');
            const hasMember = hd.khachhang_id !== null && Number(hd.khachhang_id) > 0;
            const hasPayment = hd.thoi_gian_thanh_toan !== null;
            const cash = hd.phuong_thuc === 'Tiền mặt';
            const customerGiven = hd.tien_khach_dua !== null && hd.tien_khach_dua !== '';
            const changeRecorded = hd.tien_thoi !== null && hd.tien_thoi !== '';
            const payAmount = hd.so_tien_thanh_toan !== null ? ctMoney(hd.so_tien_thanh_toan) : 'Chưa ghi nhận';
            const givenAmount = cash && customerGiven ? ctMoney(hd.tien_khach_dua) : 'Chưa ghi nhận';
            const changeAmount = cash && changeRecorded ? ctMoney(hd.tien_thoi) : 'Chưa ghi nhận';
            const method = hasPayment ? ctEsc(hd.phuong_thuc || 'Không xác định') : 'Chưa thanh toán';
            const subtotal = Number(hd.tong_tien || 0);
            const discount = Number(hd.tien_giam_gia || 0);
            const vat = Number(hd.vat_tinh_tu_hoa_don || 0);
            const total = Number(hd.khach_phai_tra || 0);
            const memberDetails = hasMember ? `
                ${ctField('Hạng thẻ hiện tại', ctEsc(hd.hang_the || 'Chưa có hạng'))}
                ${ctField('Điểm tích lũy hiện tại', ctNumber(hd.diem_tich_luy) + ' điểm')}
                ${ctField('Ngày đăng ký', ctTime(hd.ngay_dang_ky))}
                <div class="ct-note">Điểm và hạng thẻ là dữ liệu hiện tại, không phải thời điểm lập hóa đơn.</div>` :
                '<div class="ct-note">Khách chưa liên kết tài khoản thành viên.</div>';
            body.innerHTML = `
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="ct-card">
                            <div class="ct-card-title"><i class="fa-solid fa-store me-2 text-primary"></i>Thông tin hóa đơn & cửa hàng</div>
                            ${ctField('Mã hóa đơn', '<strong>#HD-' + Number(hd.id) + '</strong>')}
                            ${ctField('Trạng thái', ctEsc(hd.trang_thai || '-'))}
                            ${ctField('Cửa hàng', ctEsc(hd.ten_cua_hang || '-'))}
                            ${ctField('Mã cửa hàng', ctEsc(hd.ma_cua_hang || '-'))}
                            ${ctField('Địa chỉ', ctEsc(hd.dia_chi_ch || '-'))}
                            ${ctField('SĐT cửa hàng', ctEsc(hd.sdt_ch || '-'))}
                            ${ctField('Ngày lập', ctTime(hd.ngay_tao))}
                            ${ctField('Ngày thanh toán', ctTime(hd.thoi_gian_thanh_toan))}
                            ${ctField('Nhân viên bán', ctEsc(hd.ten_nv || '-'))}
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="ct-card">
                            <div class="ct-card-title"><i class="fa-solid fa-user me-2 text-primary"></i>Khách hàng & ưu đãi</div>
                            ${ctField('Khách hàng', ctEsc(hd.ten_kh || 'Khách lẻ'))}
                            ${ctField('Số điện thoại', ctEsc(hd.sdt_kh || '-'))}
                            ${memberDetails}
                            <hr class="my-3">
                            ${ctField('Chương trình ưu đãi', ctEsc(hd.ten_km || 'Không áp dụng'))}
                            ${ctField('Mã voucher', ctEsc(hd.ma_voucher || '-'))}
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="ct-card">
                            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
                                <div class="ct-card-title mb-0"><i class="fa-solid fa-basket-shopping me-2 text-primary"></i>Danh sách sản phẩm</div>
                                <div class="text-muted small">${ctNumber(hd.so_mat_hang)} mặt hàng · ${ctNumber(hd.so_san_pham)} sản phẩm</div>
                            </div>
                            <div class="table-responsive">
                                <table class="table ct-table mb-0">
                                    <thead><tr><th>STT</th><th>Mã SP</th><th>Tên sản phẩm</th><th class="text-end">Đơn giá</th><th class="text-center">SL</th><th class="text-end">Thành tiền</th></tr></thead>
                                    <tbody>${rows || '<tr><td colspan="6" class="text-center text-muted py-3">Không có sản phẩm</td></tr>'}</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="ct-card">
                            <div class="ct-card-title"><i class="fa-solid fa-credit-card me-2 text-primary"></i>Chi tiết thanh toán</div>
                            ${ctField('Hình thức', method)}
                            ${ctField('Số tiền giao dịch', payAmount)}
                            ${cash ? ctField('Tiền khách đưa', givenAmount) + ctField('Tiền thối lại', changeAmount) : ''}
                            ${!hasPayment ? '<div class="ct-note">Hóa đơn chưa có bản ghi thanh toán thành công.</div>' : ''}
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="ct-card">
                            <div class="ct-card-title"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Tổng kết tiền</div>
                            ${ctField('Tạm tính', ctMoney(subtotal))}
                            ${ctField('Giảm giá / khuyến mãi', '- ' + ctMoney(discount))}
                            ${ctField('Thuế GTGT trong tổng tiền', ctMoney(vat))}
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center gap-2"><strong>KHÁCH PHẢI TRẢ</strong><span class="ct-total">${ctMoney(total)}</span></div>
                        </div>
                    </div>
                </div>`;
        })
        .catch(error => {
            body.innerHTML = '<div class="text-center py-4 text-danger">' + ctEsc(error.message || 'Lỗi kết nối, vui lòng thử lại.') + '</div>';
        });
}
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>