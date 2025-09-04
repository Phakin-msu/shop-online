<?php
session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Type: application/json; charset=utf-8');

// รับเฉพาะ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'msg' => 'Invalid method'));
    exit;
}

// รับค่าแบบรองรับ PHP เก่า
$id    = isset($_POST['id']) ? trim($_POST['id']) : '';
$name  = isset($_POST['name']) ? trim($_POST['name']) : '';
$price = isset($_POST['price']) ? (int)$_POST['price'] : 0;

if ($id === '' || $name === '' || $price < 0) {
    echo json_encode(array('ok' => false, 'msg' => 'Invalid payload'));
    exit;
}

// เตรียมตะกร้าใน session
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// เพิ่มสินค้าลงตะกร้า (ถ้ามีอยู่แล้ว เพิ่ม qty)
if (isset($_SESSION['cart'][$id])) {
    $_SESSION['cart'][$id]['qty'] = (int)$_SESSION['cart'][$id]['qty'] + 1;
} else {
    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $_SESSION['cart'][$id] = array(
        'name'  => $safe_name,
        'price' => $price,
        'qty'   => 1
    );
}

// คำนวณจำนวนรวมในตะกร้า
$count = 0;
foreach ($_SESSION['cart'] as $it) {
    $count += isset($it['qty']) ? (int)$it['qty'] : 0;
}

echo json_encode(array('ok' => true, 'count' => $count));
exit;
