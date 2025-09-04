<?php
// checkout_nodb.php — เช็คเอาท์จากตะกร้า + จ่าย/อัปสลิปได้ในหน้าเดียว (No-DB)
session_start();

/* ---------- Security headers ---------- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ---------- Polyfills (รองรับ PHP เก่า) ---------- */
if (!function_exists('hash_equals')) {
  function hash_equals($a,$b){
    if (!is_string($a)||!is_string($b)) return false;
    if (strlen($a)!==strlen($b)) return false;
    $res=0; for($i=0,$l=strlen($a);$i<$l;$i++) $res|=ord($a[$i])^ord($b[$i]); return $res===0;
  }
}
if (!function_exists('random_bytes')) {
  function random_bytes($len){
    if (function_exists('openssl_random_pseudo_bytes')) return openssl_random_pseudo_bytes($len);
    $out=''; for($i=0;$i<$len;$i++) $out.=chr(mt_rand(0,255)); return $out;
  }
}

/* ---------- Helpers ---------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function nf0($n){ return number_format((float)$n, 0); }
function nf2($n){ return number_format((float)$n, 2); }
function post($k,$d=''){ return isset($_POST[$k]) ? $_POST[$k] : $d; }

/* ---------- ต้องมีตะกร้า ---------- */
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || empty($_SESSION['cart'])) {
  header('Location: cart.php'); exit;
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];
function valid_csrf($t){ return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$t); }

/* ---------- คำนวณยอดจากตะกร้า ---------- */
$items = array(); $subtotal = 0; $count = 0;
foreach ($_SESSION['cart'] as $sku=>$it) {
  $name  = isset($it['name'])  ? (string)$it['name']  : $sku;
  $price = isset($it['price']) ? (float)$it['price']  : 0;
  $qty   = isset($it['qty'])   ? (int)$it['qty']      : 1; if ($qty<1) $qty=1;
  $line  = $price * $qty;
  $items[] = array('sku'=>$sku,'name'=>$name,'price'=>$price,'qty'=>$qty,'line'=>$line);
  $subtotal += $line; $count += $qty;
}

/* ---------- ตั้งค่าร้าน / PromptPay ---------- */
$promptpay_id = '0653306505'; // <-- เปลี่ยนเป็นของร้านคุณ

/* ---------- สถานะหน้า (form | pay) ---------- */
$stage = 'form';        // เริ่มต้นที่ฟอร์มเช็คเอาท์
$paidMsg = '';          // ข้อความหลังอัปโหลดสลิป
$order = array();       // ข้อมูลออเดอร์ (เก็บใน session เมื่อกดยืนยัน)

/* ---------- กดยืนยันเช็คเอาท์ ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='place_order') {
  if (!valid_csrf(post('csrf_token'))) { http_response_code(400); exit('CSRF invalid'); }

  // รับข้อมูลฟอร์ม
  $ship = array(
    'name'        => trim((string)post('ship_name')),
    'phone'       => trim((string)post('ship_phone')),
    'address'     => trim((string)post('ship_address')),
    'subdistrict' => trim((string)post('ship_subdistrict')),
    'district'    => trim((string)post('ship_district')),
    'province'    => trim((string)post('ship_province')),
    'zipcode'     => trim((string)post('ship_zipcode')),
  );
  $note   = trim((string)post('buyer_note'));
  $pay    = (string)post('payment_method','qr');

  // ค่าขนส่งจากตัวเลือก
  $ship_method = (string)post('shipping_method','standard');
  $ship_fee = 0.0;
  if ($ship_method==='standard') $ship_fee = 40.0;
  elseif ($ship_method==='express') $ship_fee = 80.0;
  elseif ($ship_method==='pickup') $ship_fee = 0.0;

  $grand  = $subtotal + $ship_fee;
  $order_no = 'ORD'.date('Ymd-His').'-'.substr(uniqid('',true),-4);

  $order = array(
    'order_no'     => $order_no,
    'items'        => $items,
    'shipping'     => $ship,
    'shipping_method' => $ship_method,
    'payment'      => $pay,
    'note'         => $note,
    'subtotal'     => $subtotal,
    'shipping_fee' => $ship_fee,
    'grand_total'  => $grand,
  );
  $_SESSION['last_order'] = $order;
  $stage = 'pay';
}

/* ---------- โหลดออเดอร์เมื่ออยู่สเตจชำระ ---------- */
if ($stage==='pay' || (!empty($_SESSION['last_order']) && post('action')!=='place_order')) {
  if (!empty($_SESSION['last_order'])) {
    $order = $_SESSION['last_order'];
    $stage = 'pay';
  }
}

/* ---------- QR PromptPay URL ---------- */
$qrPng = '';
if ($stage==='pay' && !empty($order)) {
  $qrPng = 'https://promptpay.io/'.rawurlencode($promptpay_id).'/'.number_format($order['grand_total'],2,'.','').'.png';
}

/* ---------- อัปโหลดสลิป (อยู่สเตจ pay) ---------- */
if ($stage==='pay' && !empty($_FILES['slip']) && is_uploaded_file($_FILES['slip']['tmp_name'])) {
  if (!valid_csrf(post('csrf_token'))) { http_response_code(400); exit('CSRF invalid'); }
  $okTypes = array('image/jpeg','image/png','image/webp','image/gif');
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $_FILES['slip']['tmp_name']); finfo_close($finfo);

  if (!in_array($mime,$okTypes))         $paidMsg = 'ไฟล์ต้องเป็นรูปภาพ (JPG/PNG/WebP/GIF)';
  elseif ($_FILES['slip']['size']>5*1024*1024) $paidMsg = 'ไฟล์ใหญ่เกิน 5MB';
  else {
    $ext = strtolower(pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION));
    if (!$ext) { // เดาวงเล็บจาก mime สำหรับ PHP เก่า
      if (strpos($mime,'png')!==false) $ext='png';
      elseif (strpos($mime,'webp')!==false) $ext='webp';
      elseif (strpos($mime,'gif')!==false) $ext='gif';
      else $ext='jpg';
    }
    $dir = __DIR__.'/uploads'; if(!is_dir($dir)) @mkdir($dir,0775,true);
    $name = $order['order_no'].'-slip-'.substr(uniqid('',true),-6).'.'.$ext;
    $path = $dir.'/'.$name;
    if (move_uploaded_file($_FILES['slip']['tmp_name'],$path)) {
      $url = 'uploads/'.$name;
      $_SESSION['last_order']['slip_url'] = $url;
      $order['slip_url'] = $url;
      $paidMsg = 'รับหลักฐานการชำระเงินแล้ว! ทีมงานจะตรวจสอบให้เร็วที่สุด';
    } else $paidMsg='อัปโหลดไม่สำเร็จ ลองใหม่อีกครั้ง';
  }
}

/* ---------- Utils แสดงที่อยู่ ---------- */
function fmt_addr($s){
  return trim(
    (isset($s['address'])?$s['address']:'').' '.
    (isset($s['subdistrict'])?$s['subdistrict']:'').' '.
    (isset($s['district'])?$s['district']:'').' '.
    (isset($s['province'])?$s['province']:'').' '.
    (isset($s['zipcode'])?$s['zipcode']:'')
  );
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เช็คเอาท์</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-4" style="max-width:980px">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">เช็คเอาท์ (สินค้า <?php echo (int)$count; ?> ชิ้น)</h1>
    <a href="cart.php" class="btn btn-secondary">ย้อนกลับไปแก้ตะกร้า</a>
  </div>

  <!-- สรุปรายการสินค้า -->
  <div class="card mb-3">
    <div class="card-header bg-white fw-semibold">รายการสินค้า</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>สินค้า</th><th class="text-end">ราคา/ชิ้น</th><th class="text-end">จำนวน</th><th class="text-end">รวม</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($items as $it){ ?>
              <tr>
                <td><?php echo h($it['name']); ?><div class="text-muted small"><?php echo h($it['sku']); ?></div></td>
                <td class="text-end">฿<?php echo nf2($it['price']); ?></td>
                <td class="text-end"><?php echo (int)$it['qty']; ?></td>
                <td class="text-end">฿<?php echo nf2($it['line']); ?></td>
              </tr>
            <?php } ?>
          </tbody>
          <tfoot class="table-light">
            <tr><th colspan="3" class="text-end">ค่าสินค้า</th><th class="text-end">฿<?php echo nf2($subtotal); ?></th></tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <?php if ($stage==='form') { ?>
    <!-- ฟอร์มเช็คเอาท์ -->
    <form method="post" class="card">
      <div class="card-header bg-white fw-semibold">ที่อยู่จัดส่ง & การชำระเงิน</div>
      <div class="card-body">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">ชื่อผู้รับ</label>
            <input class="form-control" name="ship_name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">โทรศัพท์</label>
            <input class="form-control" name="ship_phone" required>
          </div>
          <div class="col-12">
            <label class="form-label">ที่อยู่</label>
            <textarea class="form-control" name="ship_address" rows="2" required></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">ตำบล/แขวง</label>
            <input class="form-control" name="ship_subdistrict">
          </div>
          <div class="col-md-4">
            <label class="form-label">อำเภอ/เขต</label>
            <input class="form-control" name="ship_district">
          </div>
          <div class="col-md-3">
            <label class="form-label">จังหวัด</label>
            <input class="form-control" name="ship_province">
          </div>
          <div class="col-md-1">
            <label class="form-label">รหัสไปรษณีย์</label>
            <input class="form-control" name="ship_zipcode">
          </div>

          <div class="col-md-6">
            <label class="form-label">วิธีจัดส่ง</label>
            <select class="form-select" name="shipping_method">
              <option value="standard">ไปรษณีย์ลงทะเบียน (+฿40)</option>
              <option value="express">ด่วนพิเศษ (+฿80)</option>
              <option value="pickup">มารับเอง (ฟรี)</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">วิธีชำระเงิน</label>
            <select class="form-select" name="payment_method">
              <option value="qr" selected>โอนผ่าน PromptPay (สแกน QR)</option>
              <option value="cod" disabled>เก็บเงินปลายทาง (ปิดชั่วคราว)</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">หมายเหตุถึงร้าน</label>
            <textarea class="form-control" name="buyer_note" rows="2" placeholder="ต้องการนัดรับ/แพ็กของพิเศษ ฯลฯ"></textarea>
          </div>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="fw-semibold">ยอดชำระโดยประมาณหลังเลือกขนส่งจะแสดงในขั้นถัดไป</div>
        <div>
          <button class="btn btn-primary" type="submit" name="action" value="place_order">ยืนยันและไปชำระเงิน</button>
        </div>
      </div>
    </form>
  <?php } else { ?>
    <!-- หน้าชำระเงิน (สแกน QR + อัปสลิป) -->
    <div class="card mb-3">
      <div class="card-header bg-white fw-semibold">ยืนยันการชำระเงิน</div>
      <div class="card-body">
        <p class="mb-1">เลขออเดอร์: <b><?php echo h($order['order_no']); ?></b></p>
        <?php if ($paidMsg) { ?>
          <div class="alert alert-info py-2"><?php echo h($paidMsg); ?></div>
        <?php } ?>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold mb-2">ที่อยู่จัดส่ง</div>
              <div><b>ผู้รับ:</b> <?php echo h($order['shipping']['name']); ?></div>
              <div><b>โทร:</b> <?php echo h($order['shipping']['phone']); ?></div>
              <div><b>ที่อยู่:</b> <?php echo h(fmt_addr($order['shipping'])); ?></div>
              <?php if (!empty($order['note'])) { ?><div><b>หมายเหตุ:</b> <?php echo h($order['note']); ?></div><?php } ?>
            </div>
          </div>
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
             
              <img src="<?php echo h($qrPng); ?>" alt="PromptPay QR" class="img-fluid border rounded mt-2" style="max-width:240px">
              <div class="text-muted small mt-2">* เปิดแอปธนาคาร → สแกนเพื่อชำระ</div>
            </div>
          </div>
        </div>

        <hr>
        <form method="post" enctype="multipart/form-data" class="d-flex align-items-center gap-2 flex-wrap">
          <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
          <label class="fw-semibold me-1">อัปโหลดสลิป:</label>
          <input type="file" name="slip" accept="image/*" required class="form-control" style="max-width:300px">
          <button class="btn btn-success" type="submit">ยืนยันการชำระเงิน</button>
          <?php if (!empty($order['slip_url'])) { ?>
            <a class="btn btn-outline-secondary" target="_blank" href="<?php echo h($order['slip_url']); ?>">ดูสลิปที่อัปโหลด</a>
          <?php } ?>
        </form>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <div>
          
        </div>
        <div>
          <a href="index.php" class="btn btn-outline-secondary">กลับหน้าแรก</a>
          <button onclick="window.print()" class="btn btn-outline-primary">พิมพ์บิล</button>
        </div>
      </div>
    </div>
  <?php } ?>
</div>
</body>
</html>
