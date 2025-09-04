<?php
// order_submit_nodb.php — ยืนยันคำสั่งซื้อ+QR PromptPay (หน้าเดียว จ่าย-อัปสลิปได้เลย)
session_start();

// ---------- CSRF ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_SESSION['last_order'])) { header('Location: index.php'); exit; }
if (!empty($_POST['csrf_token']) && (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
  http_response_code(400); exit('CSRF invalid');
}

// ---------- Helper ----------
function h($v){ return htmlspecialchars($v,ENT_QUOTES,'UTF-8'); }
function nf($n){ return number_format((float)$n,2); }

// ---------- 1) รับออเดอร์จาก Checkout (ครั้งแรก) ----------
if (!empty($_POST['items'])) {
  $items   = isset($_POST['items'])        ? $_POST['items']        : array();
  $ship    = isset($_POST['shipping'])     ? $_POST['shipping']     : array();
  $method  = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'qr';
  $note    = isset($_POST['buyer_note'])   ? $_POST['buyer_note']   : '';
  $shipfee = isset($_POST['shipping_fee']) ? (float)$_POST['shipping_fee'] : 0.0;

  $subtotal = 0.0;
  foreach ($items as $it) {
    $price = !empty($it['price']) ? (float)$it['price'] : 0;
    $qty   = !empty($it['qty'])   ? (int)$it['qty']     : 1;
    if ($qty < 1) $qty = 1;
    $subtotal += $price * $qty; // <- สำคัญ: รวมยอดต่อบรรทัดเข้ายอดสินค้า
  }
  $grand   = $subtotal + $shipfee;
  $orderNo = 'ORD'.date('Ymd-His').'-'.substr(uniqid('',true),-4);

  $_SESSION['last_order'] = array(
    'order_no'     => $orderNo,
    'items'        => $items,
    'shipping'     => $ship,
    'payment'      => $method,
    'note'         => $note,
    'subtotal'     => $subtotal,
    'shipping_fee' => $shipfee,
    'grand_total'  => $grand,
  );
}

// ---------- 2) โหลดออเดอร์จาก SESSION (รอบยืนยัน/อัปสลิป) ----------
if (empty($_SESSION['last_order'])) { header('Location: index.php'); exit; }
$o = $_SESSION['last_order'];

// ---------- ที่อยู่เพื่อแสดง ----------
$ship = isset($o['shipping']) ? $o['shipping'] : array();
$shipAddr = trim(
  (isset($ship['address'])     ? $ship['address']     : '') . ' ' .
  (isset($ship['subdistrict']) ? $ship['subdistrict'] : '') . ' ' .
  (isset($ship['district'])    ? $ship['district']    : '') . ' ' .
  (isset($ship['province'])    ? $ship['province']    : '') . ' ' .
  (isset($ship['zipcode'])     ? $ship['zipcode']     : '')
);

// ---------- QR ร้าน (แก้เป็นของร้านคุณ) ----------
$promptpay_id = '0653306505';
$qrPng = 'https://promptpay.io/'.rawurlencode($promptpay_id).'/'.number_format($o['grand_total'],2,'.','').'.png';

// ---------- 3) รับไฟล์สลิป (จ่ายในหน้านี้) ----------
$paidMsg = '';
if (!empty($_FILES['slip']) && is_uploaded_file($_FILES['slip']['tmp_name'])) {
  $okTypes = array('image/jpeg','image/png','image/webp','image/gif');
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $_FILES['slip']['tmp_name']); finfo_close($finfo);

  if (!in_array($mime,$okTypes)) {
    $paidMsg = 'ไฟล์ต้องเป็นรูปภาพ (JPG/PNG/WebP/GIF)';
  } elseif ($_FILES['slip']['size'] > 5*1024*1024) {
    $paidMsg = 'ไฟล์ใหญ่เกิน 5MB';
  } else {
    $ext = strtolower(pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION));
    // แทน str_contains ด้วย strpos เพื่อรองรับ PHP เก่า
    if (!$ext) {
      if (strpos($mime,'png')  !== false) $ext = 'png';
      elseif (strpos($mime,'webp') !== false) $ext = 'webp';
      elseif (strpos($mime,'gif')  !== false) $ext = 'gif';
      else $ext = 'jpg';
    }
    $dir = __DIR__.'/uploads'; if(!is_dir($dir)) @mkdir($dir,0775,true);
    $name = $o['order_no'].'-slip-'.substr(uniqid('',true),-6).'.'.$ext;
    $path = $dir.'/'.$name;
    if (move_uploaded_file($_FILES['slip']['tmp_name'],$path)) {
      $url  = 'uploads/'.$name;
      $_SESSION['last_order']['slip_url'] = $url;
      $o['slip_url'] = $url;
      $paidMsg = 'รับหลักฐานการชำระเงินแล้ว! ทีมงานจะตรวจสอบให้เร็วที่สุด';
    } else {
      $paidMsg = 'อัปโหลดไม่สำเร็จ ลองใหม่อีกครั้ง';
    }
  }
}
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>สั่งซื้อสำเร็จ (ชำระเงินในหน้านี้)</title>
</head><body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:860px;margin:24px auto;line-height:1.5">
  <h2 style="margin:0 0 8px">สั่งซื้อสำเร็จ</h2>
  <p style="margin:0 0 12px">เลขออเดอร์: <b><?php echo h($o['order_no']); ?></b></p>

  <?php if ($paidMsg) { ?>
    <div style="background:#ecfeff;border:1px solid #a5f3fc;padding:10px 12px;border-radius:8px;margin:10px 0"><?php echo h($paidMsg); ?></div>
  <?php } ?>

  <div style="border:1px solid #e5e7eb;padding:12px;border-radius:8px;margin:12px 0">
    <div><b>ผู้รับ:</b> <?php echo h(isset($ship['name']) ? $ship['name'] : ''); ?></div>
    <div><b>โทร:</b> <?php echo h(isset($ship['phone']) ? $ship['phone'] : ''); ?></div>
    <div><b>ที่อยู่:</b> <?php echo h($shipAddr); ?></div>
    <div><b>วิธีชำระ:</b> <?php echo h(isset($o['payment']) ? $o['payment'] : 'qr'); ?></div>
    <?php if (!empty($o['note'])) { ?><div><b>หมายเหตุ:</b> <?php echo h($o['note']); ?></div><?php } ?>
  </div>

  <div style="border:1px solid #e5e7eb;padding:12px;border-radius:8px;margin:12px 0">
    <b>รายการสินค้า</b>
    <table style="width:100%;border-collapse:collapse;margin-top:8px">
      <tr style="background:#f8fafc">
        <th style="text-align:left;padding:6px;border:1px solid #eee">สินค้า</th>
        <th style="text-align:right;padding:6px;border:1px solid #eee">ราคา</th>
        <th style="text-align:right;padding:6px;border:1px solid #eee">จำนวน</th>
        <th style="text-align:right;padding:6px;border:1px solid #eee">รวม</th>
      </tr>
      <?php foreach (isset($o['items']) ? $o['items'] : array() as $it):
        $nm  = isset($it['name'])  ? $it['name']  : '';
        $pr  = isset($it['price']) ? (float)$it['price'] : 0;
        $qty = isset($it['qty'])   ? (int)$it['qty'] : 1; if ($qty<1) $qty=1;
        $line = $pr * $qty;
      ?>
      <tr>
        <td style="padding:6px;border:1px solid #eee"><?php echo h($nm); ?></td>
        <td style="padding:6px;border:1px solid #eee;text-align:right"><?php echo nf($pr); ?></td>
        <td style="padding:6px;border:1px solid #eee;text-align:right"><?php echo (int)$qty; ?></td>
        <td style="padding:6px;border:1px solid #eee;text-align:right"><?php echo nf($line); ?></td>
      </tr>
      <?php endforeach; ?>
      <tr><td colspan="3" style="padding:6px;border:1px solid #eee;text-align:right">ค่าสินค้า</td><td style="padding:6px;border:1px solid #eee;text-align:right"><b><?php echo nf($o['subtotal']); ?></b></td></tr>
      <tr><td colspan="3" style="padding:6px;border:1px solid #eee;text-align:right">ค่าส่ง</td><td style="padding:6px;border:1px solid #eee;text-align:right"><b><?php echo nf($o['shipping_fee']); ?></b></td></tr>
      <tr><td colspan="3" style="padding:6px;border:1px solid #eee;text-align:right"><b>ยอดสุทธิ</b></td><td style="padding:6px;border:1px solid #eee;text-align:right"><b><?php echo nf($o['grand_total']); ?></b></td></tr>
    </table>
  </div>

  <!-- ชำระเงินในหน้านี้: แสดง QR + ฟอร์มยืนยันการชำระ -->
  <div style="border:1px solid #e5e7eb;padding:12px;border-radius:8px;margin:12px 0">
    <h3 style="margin:6px 0 10px">สแกนชำระผ่าน PromptPay</h3>
    <p style="margin:4px 0">รหัส PromptPay ร้าน: <b><?php echo h($promptpay_id); ?></b></p>
    <p style="margin:4px 0">ยอดชำระ: <b><?php echo nf($o['grand_total']); ?></b> บาท</p>
    <img src="<?php echo h($qrPng); ?>" alt="PromptPay QR" style="max-width:240px;border:1px solid #eee;border-radius:6px">
    <div style="margin-top:6px;color:#64748b;font-size:.92rem">* เปิดแอปธนาคาร → สแกนเพื่อชำระ</div>

    <hr style="margin:14px 0;border:none;border-top:1px dashed #e5e7eb">
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="csrf_token" value="<?php echo h(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''); ?>">
      <label style="font-weight:600">อัปโหลดสลิป:</label>
      <input type="file" name="slip" accept="image/*" required style="padding:8px;border:1px solid #e5e7eb;border-radius:6px">
      <button type="submit" style="background:#16a34a;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer">ยืนยันการชำระเงิน</button>
      <?php if (!empty($o['slip_url'])) { ?>
        <a href="<?php echo h($o['slip_url']); ?>" target="_blank" style="padding:10px 14px;border:1px solid #ddd;border-radius:8px;text-decoration:none">ดูสลิปที่อัปโหลด</a>
      <?php } ?>
    </form>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="index.php" style="padding:10px 14px;border:1px solid #ddd;border-radius:6px;text-decoration:none">กลับหน้าแรก</a>
    <button onclick="window.print()" style="padding:10px 14px;border:1px solid #ddd;border-radius:6px;background:#fff">พิมพ์บิล</button>
  </div>
</body></html>
