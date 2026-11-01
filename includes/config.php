<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

define('APP_NAME', 'Circle K Vietnam');
define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));

$__curDir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
$__relPath = trim(str_ireplace(ROOT_PATH, '', $__curDir), '/');
$__depth   = $__relPath === '' ? 0 : substr_count($__relPath, '/') + 1;
$__urlDir  = explode('/', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'));
define('BASE_URL', $__depth > 0 ? implode('/', array_slice($__urlDir, 0, -$__depth)) : implode('/', $__urlDir));

function asset(string $path): string {
    $file = ROOT_PATH . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : time();
    return BASE_URL . '/assets/' . $path . '?v=' . $v;
}

function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function vnd($n): string { return number_format((float)$n, 0, '.', ','); }

function initial(string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $last  = $words ? end($words) : '';
    $chars = preg_split('//u', $last, -1, PREG_SPLIT_NO_EMPTY);
    $first = $chars[0] ?? '?';
    return function_exists('mb_strtoupper') ? mb_strtoupper($first) : strtoupper($first);
}

const CHUC_NANG_HOP_LE = ['phanquyen', 'cuahang', 'nhanvien', 'khachhang', 'nhanvien_khuvuc', 'duyet_nhansu', 'ca_truc',
                           'sanpham', 'nhapkho', 'dieuchuyen', 'tonkho_xem',
                           'pos', 'hoadon', 'khuyenmai', 'thanhtoan', 'xem_hoadon',
                           'dashboard', 'sosanh', 'banchay', 'locbaocao'];

function layVaiTro(PDO $pdo, int $nguoidung_id, int $phanquyen_id, string $username): array {
    $stmt = $pdo->prepare("SELECT nhom FROM phanquyen_tuychinh WHERE nguoidung_id = ?");
    $stmt->execute([$nguoidung_id]);
    $tuyChinh = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($tuyChinh)) {
        $conHopLe = array_values(array_intersect($tuyChinh, CHUC_NANG_HOP_LE));
        if (count($conHopLe) === count($tuyChinh)) {
            return $tuyChinh; 
        }

        $pdo->prepare("DELETE FROM phanquyen_tuychinh WHERE nguoidung_id = ?")->execute([$nguoidung_id]);
    }

    $macDinh = [
        1 => ['phanquyen', 'cuahang', 'nhanvien', 'khachhang', 'sanpham', 'nhapkho', 'dieuchuyen', 'pos', 'hoadon', 'khuyenmai', 'thanhtoan', 'dashboard', 'sosanh', 'banchay', 'locbaocao', 'duyet_nhansu', 'nhanvien_khuvuc', 'ca_truc'], 
        2 => ['dashboard', 'nhanvien_khuvuc', 'ca_truc', 'hoadon', 'nhapkho', 'khuyenmai'], 
        3 => ['dashboard', 'nhanvien', 'ca_truc', 'xem_hoadon', 'tonkho_xem'],
        4 => ['pos', 'thanhtoan', 'xem_hoadon'],   
        5 => ['dieuchuyen', 'nhapkho'],          
    ];
    return $macDinh[$phanquyen_id] ?? ['dashboard'];
}

function layKhuVuc(PDO $pdo, int $quanlytruong_id): array {
    $stmt = $pdo->prepare("SELECT cuahang_id FROM khuvuc_phancong WHERE quanlytruong_id = ? ORDER BY cuahang_id ASC");
    $stmt->execute([$quanlytruong_id]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$thu = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'][(int)date('w')];
$homNay = $thu . ', ' . date('d/m/Y');

$menu = require __DIR__ . '/menu.php';