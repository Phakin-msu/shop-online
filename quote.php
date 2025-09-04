<?php
session_start();
require_once 'config.php'; 


if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit();
}


header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function format_price($n){ return number_format((float)$n, 0); }

function gen_quote_code(){
    $rand = function_exists('random_bytes') ? strtoupper(bin2hex(random_bytes(3))) : strtoupper(substr(md5(uniqid('', true)), 0, 6));
    return 'QT'.date('YmdHis').$rand;
}


if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];
function valid_csrf($t){ return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$t); }

// โหลดสินค้าจริงจาก DB (เพื่อ sync ชื่อ/ราคา/เช็คว่ามีอยู่จริง)
$skus = array_keys($_SESSION['cart']);
$productMap = array();
if (!empty($skus)) {
    $ph = implode(',', array_fill(0, count($skus), '?'));
    try{
        $stmt = $pdo->prepare("SELECT sku, name, price, stock, image_url FROM products WHERE sku IN ($ph)");
        $stmt->execute($skus);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $r){ $productMap[$r['sku']] = $r; }
    }catch(PDOException $e){}
}


foreach ($skus as $sku){
    if (!isset($productMap[$sku])) { unset($_SESSION['cart'][$sku]); continue; }
    $_SESSION['cart'][$sku]['name']  = isset($productMap[$sku]['name']) ? $productMap[$sku]['name'] : (isset($_SESSION['cart'][$sku]['name'])?$_SESSION['cart'][$sku]['name']:'');
    $_SESSION['cart'][$sku]['price'] = isset($productMap[$sku]['price']) ? (float)$productMap[$sku]['price'] : 0;
    // ไม่บังคับตาม stock (เพราะเป็นการ “ขอใบเสนอราคา”)
}

// นับรวม
$total = 0; $count = 0;
foreach ($_SESSION['cart'] as $pid => $it){
    $price = isset($it['price']) ? (float)$it['price'] : 0;
    $qty   = isset($it['qty']) ? (int)$it['qty'] : 0;
    $total += $price * $qty;
    $count += $qty;
}

$ok_msg = ''; $err_msg = '';
$prefill = array('name'=>'','email'=>'','phone'=>'','company'=>'','note'=>'');

// ส่งคำขอใบเสนอราคา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='submit_quote'){
    if (!valid_csrf(isset($_POST['csrf_token'])?$_POST['csrf_token']:'') ){
        $err_msg = 'โทเค็นไม่ถูกต้อง (CSRF)';
    } elseif (empty($_SESSION['cart'])) {
        $err_msg = 'ไม่มีสินค้าในตะกร้า';
    } else {
        // อ่านข้อมูลฟอร์ม
        $prefill['name']    = isset($_POST['name']) ? trim($_POST['name']) : '';
        $prefill['email']   = isset($_POST['email']) ? trim($_POST['email']) : '';
        $prefill['phone']   = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $prefill['company'] = isset($_POST['company']) ? trim($_POST['company']) : '';
        $prefill['note']    = isset($_POST['note']) ? trim($_POST['note']) : '';

        $errs = array();
        if ($prefill['name'] === '')  $errs[] = 'กรุณากรอกชื่อผู้ติดต่อ';
        if ($prefill['email'] === '') $errs[] = 'กรุณากรอกอีเมล';

        if (!empty($errs)){
            $err_msg = implode(' | ', $errs);
        } else {
            try{
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->beginTransaction();

                $quote_code = gen_quote_code();
                $buyer_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

                // บันทึกหัวใบเสนอราคา
                $insQ = $pdo->prepare("
                    INSERT INTO quotes (quote_code, buyer_id, name, email, phone, company, note, total_amount, status, created_at)
                    VALUES (:code, :buyer, :name, :email, :phone, :company, :note, :total, 'submitted', NOW())
                ");
                $insQ->bindValue(':code', $quote_code, PDO::PARAM_STR);
                if ($buyer_id === null) $insQ->bindValue(':buyer', null, PDO::PARAM_NULL);
                else                    $insQ->bindValue(':buyer', $buyer_id, PDO::PARAM_INT);
                $insQ->bindValue(':name', $prefill['name'], PDO::PARAM_STR);
                $insQ->bindValue(':email', $prefill['email'], PDO::PARAM_STR);
                $insQ->bindValue(':phone', $prefill['phone'], PDO::PARAM_STR);
                $insQ->bindValue(':company', $prefill['company'], PDO::PARAM_STR);
                $insQ->bindValue(':note', $prefill['note'], PDO::PARAM_STR);
                $insQ->bindValue(':total', (float)$total);
                $insQ->execute();

                $qid = (int)$pdo->lastInsertId();

                // รายการสินค้าใบเสนอราคา
                $insI = $pdo->prepare("INSERT INTO quote_items (quote_id, sku, name, price, qty) VALUES (:qid, :sku, :name, :price, :qty)");
                foreach ($_SESSION['cart'] as $sku => $it){
                    $name  = isset($it['name']) ? $it['name'] : '';
                    $price = isset($it['price']) ? (float)$it['price'] : 0;
                    $qty   = isset($it['qty']) ? (int)$it['qty'] : 0;
                    if ($qty <= 0) continue;

                    $insI->execute(array(
                        ':qid'   => $qid,
                        ':sku'   => $sku,
                        ':name'  => $name,
                        ':price' => $price,
                        ':qty'   => $qty
                    ));
                }

                $pdo->commit();

              
                $ok_msg = 'ส่งคำขอใบเสนอราคาเรียบร้อย! รหัส: '.$quote_code;
                
            }catch(Exception $ex){
                if ($pdo->inTransaction()) $pdo->rollBack();
                $err_msg = 'บันทึกไม่สำเร็จ: '.$ex->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>ขอใบเสนอราคา</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 980px;">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">ขอใบเสนอราคา</h1>
    <div>
      <a href="index.php" class="btn btn-secondary">กลับหน้าหลัก</a>
    </div>
  </div>

  <?php if ($ok_msg !== ''): ?>
    <div class="alert alert-success"><?php echo h($ok_msg); ?></div>
  <?php endif; ?>
  <?php if ($err_msg !== ''): ?>
    <div class="alert alert-danger"><?php echo h($err_msg); ?></div>
  <?php endif; ?>

  <?php if (empty($_SESSION['cart'])): ?>
    <div class="alert alert-info">ยังไม่มีสินค้าในตะกร้า กรุณาเลือกสินค้าแล้วกด “เพิ่มเข้าตะกร้า” ก่อน</div>
  <?php else: ?>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header bg-light fw-semibold">รายการสินค้า</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>สินค้า</th>
                    <th class="text-end">ราคา/ชิ้น</th>
                    <th class="text-center">จำนวน</th>
                    <th class="text-end">รวม</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($_SESSION['cart'] as $sku => $it): 
                    $name  = isset($it['name']) ? $it['name'] : '';
                    $price = isset($it['price']) ? (float)$it['price'] : 0;
                    $qty   = isset($it['qty']) ? (int)$it['qty'] : 0;
                ?>
                  <tr>
                    <td><?php echo h($name); ?><div class="text-muted small"><?php echo h($sku); ?></div></td>
                    <td class="text-end">฿<?php echo format_price($price); ?></td>
                    <td class="text-center"><?php echo (int)$qty; ?></td>
                    <td class="text-end">฿<?php echo format_price($price * $qty); ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <th colspan="3" class="text-end">ยอดรวมโดยประมาณ</th>
                    <th class="text-end">฿<?php echo format_price($total); ?></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <form method="post" class="card">
          <div class="card-header bg-light fw-semibold">ข้อมูลผู้ติดต่อ</div>
          <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="action" value="submit_quote">

            <div class="mb-3">
              <label class="form-label">ชื่อผู้ติดต่อ *</label>
              <input type="text" class="form-control" name="name" required value="<?php echo h($prefill['name']); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">อีเมล *</label>
              <input type="email" class="form-control" name="email" required value="<?php echo h($prefill['email']); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">โทรศัพท์</label>
              <input type="text" class="form-control" name="phone" value="<?php echo h($prefill['phone']); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">บริษัท/หน่วยงาน</label>
              <input type="text" class="form-control" name="company" value="<?php echo h($prefill['company']); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">หมายเหตุเพิ่มเติม</label>
              <textarea class="form-control" name="note" rows="3"><?php echo h($prefill['note']); ?></textarea>
            </div>

            <button class="btn btn-primary w-100" type="submit">ส่งคำขอใบเสนอราคา</button>
            <div class="form-text mt-2">* ใบเสนอราคาเป็นราคาประมาณการ อาจมีการปรับเปลี่ยนตามจำนวน/เงื่อนไขเพิ่มเติม</div>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
