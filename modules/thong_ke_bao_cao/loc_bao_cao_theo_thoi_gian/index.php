<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

if (!isset($conn) || !$conn) {
    $conn = mysqli_connect('localhost', 'root', '', 'circlek');
    if ($conn) {
        mysqli_set_charset($conn, 'utf8mb4');
    }
}

$pageTitle  = 'Lọc báo cáo theo thời gian';
$activeMenu = 'locbaocao';

$queryCH = "SELECT id, ten_cua_hang FROM cuahang";
$resCH = mysqli_query($conn, $queryCH);
$listCH = [];
while ($ch = mysqli_fetch_assoc($resCH)) {
    $listCH[] = $ch;
}

$queryReport = "SELECT h.ngay_tao, c.ten_cua_hang, COUNT(h.id) AS so_don, SUM(h.tong_tien) AS doanh_thu 
                FROM hoadon h 
                LEFT JOIN cuahang c ON h.cuahang_id = c.id 
                GROUP BY h.ngay_tao, c.ten_cua_hang 
                ORDER BY h.ngay_tao DESC LIMIT 10";
$resReport = mysqli_query($conn, $queryReport);
$listReport = [];
while ($r = mysqli_fetch_assoc($resReport)) {
    $listReport[] = $r;
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--violet), #ec4899)"><i class="fa-solid fa-filter"></i></span>
                Bộ lọc báo cáo
            </h5>
            <div class="panel-subtitle ms-5">Chọn khoảng thời gian và phạm vi cần xem</div>
        </div>
    </div>
    <div class="mb-3">
        <div class="segmented-control" role="group">
            <button type="button" class="seg-btn">Hôm nay</button>
            <button type="button" class="seg-btn active">7 Ngày</button>
            <button type="button" class="seg-btn">30 Ngày</button>
            <button type="button" class="seg-btn">Quý này</button>
            <button type="button" class="seg-btn">Tùy chọn</button>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label" for="lbTu">Từ ngày</label>
            <input type="date" class="form-control" id="lbTu">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="lbDen">Đến ngày</label>
            <input type="date" class="form-control" id="lbDen">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="lbCh">Cửa hàng</label>
            <select class="form-select" id="lbCh">
                <option value="">Toàn chuỗi</option>
                <?php foreach ($listCH as $store): ?>
                    <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['ten_cua_hang']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="lbLoai">Loại báo cáo</label>
            <select class="form-select" id="lbLoai">
                <option>Doanh thu</option>
                <option>Sản phẩm bán chạy</option>
                <option>Tồn kho</option>
                <option>Khách hàng</option>
            </select>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="button" id="btnExport" class="btn-ck btn-ck-soft"><i class="fa-solid fa-file-export"></i> Xuất Excel</button>
        <button type="button" class="btn-ck btn-ck-primary"><i class="fa-solid fa-magnifying-glass-chart"></i> Áp dụng bộ lọc</button>
    </div>
</div>

<div class="bento-panel fade-up d-1 mt-4">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-table"></i></span>
                Kết quả báo cáo
            </h5>
            <div class="panel-subtitle ms-5">Hiển thị theo bộ lọc đã chọn</div>
        </div>
    </div>
    <div class="table-responsive">
        <table id="reportTable" class="table table-modern mb-0">
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Cửa hàng</th>
                    <th class="text-end">Số đơn</th>
                    <th class="text-end">Doanh thu</th>
                    <th>Tăng trưởng</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($listReport)): ?>
                    <?php foreach ($listReport as $row): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($row['ngay_tao'])); ?></td>
                        <td><?php echo htmlspecialchars($row['ten_cua_hang'] ?? 'Circle K Toàn hệ thống'); ?></td>
                        <td class="text-end"><span class="num-font fw-bold"><?php echo number_format($row['so_don'], 0, ',', '.'); ?></span></td>
                        <td class="text-end"><span class="num-font fw-bold"><?php echo number_format($row['doanh_thu'], 0, ',', '.'); ?> ₫</span></td>
                        <td><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 8.5%</span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td><?php echo date('d/m/Y'); ?></td>
                        <td>Circle K Nguyễn Chí Thanh</td>
                        <td class="text-end"><span class="num-font fw-bold">652</span></td>
                        <td class="text-end"><span class="num-font fw-bold">56,452,500 ₫</span></td>
                        <td><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 8.5%</span></td>
                    </tr>
                    <tr>
                        <td><?php echo date('d/m/Y'); ?></td>
                        <td>Circle K Trần Phú</td>
                        <td class="text-end"><span class="num-font fw-bold">488</span></td>
                        <td class="text-end"><span class="num-font fw-bold">37,635,000 ₫</span></td>
                        <td><span class="trend-pill trend-down"><i class="fa-solid fa-arrow-trend-down"></i> 2.1%</span></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.querySelectorAll('.segmented-control').forEach(control => {
        const buttons = control.querySelectorAll('.seg-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                buttons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
    });

    document.getElementById("btnExport").addEventListener("click", function() {
        let table = document.getElementById("reportTable");
        let rows = table.querySelectorAll("tr");
        let csv = [];
        for (let i = 0; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll("td, th");
            for (let j = 0; j < cols.length; j++) row.push(cols[j].innerText);
            csv.push(row.join(","));
        }
        let csvContent = "data:text/csv;charset=utf-8,\uFEFF" + csv.join("\n");
        let encodedUri = encodeURI(csvContent);
        let link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "bao_cao_circle_k.csv");
        document.body.appendChild(link);
        link.click();
    });
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>