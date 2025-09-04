<?php
session_start();
require_once 'config.php'; // ต้องมี $pdo ชี้ไปที่ shopdb

// ===== Security headers =====
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ===== ต้องล็อกอินก่อน =====
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit();
}

// ===== Helper =====
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function format_price($n) { return number_format((float)$n, 0); } // แสดง 0 ทศนิยม
function gen_order_code() {
    // สร้างรหัสออร์เดอร์อ่านง่าย/ไม่ชนกัน
    $rand = function_exists('random_bytes') ? strtoupper(bin2hex(random_bytes(3))) : strtoupper(substr(md5(uniqid('', true)), 0, 6));
    return 'ORD'.date('YmdHis').$rand;
}

// ===== เตรียมตะกร้าใน session =====
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// ===== CSRF token =====
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];
function valid_csrf($t) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$t);
}

// ===== รับ action =====
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$id     = isset($_POST['id']) ? (string)$_POST['id'] : '';
$token  = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

$order_success = '';
$order_error   = '';

// ต้องใช้ token สำหรับคำสั่งแก้ไขตะกร้า/ชำระเงิน
$need_token = in_array($action, array('inc','dec','remove','clear','checkout'), true);

// ===== จัดการ action ตะกร้า (inc/dec/remove/clear) =====
if ($action !== '' && $action !== 'checkout') {
    if ($need_token && !valid_csrf($token)) {
        // ไม่ผ่านโทเค็น — ไม่ทำอะไร
    } else {
        if ($action === 'inc' && isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['qty'] = (int)$_SESSION['cart'][$id]['qty'] + 1;
        }
        if ($action === 'dec' && isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['qty'] = (int)$_SESSION['cart'][$id]['qty'] - 1;
            if ((int)$_SESSION['cart'][$id]['qty'] <= 0) {
                unset($_SESSION['cart'][$id]);
            }
        }
        if ($action === 'remove') {
            unset($_SESSION['cart'][$id]);
        }
        if ($action === 'clear') {
            $_SESSION['cart'] = array();
        }
    }
}

// ===== ดึงข้อมูลสินค้าจริงจาก DB เพื่อทับชื่อ/ราคา/เช็คสต็อก =====
$skus = array_keys($_SESSION['cart']);
$productMap = array(); // sku => row จาก DB

if (!empty($skus)) {
    $placeholders = implode(',', array_fill(0, count($skus), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT sku, name, price, image_url, stock
            FROM products
            WHERE sku IN ($placeholders)
        ");
        $stmt->execute($skus);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $productMap[$r['sku']] = $r;
        }
    } catch (PDOException $e) {
        // ถ้าดึงไม่ได้ จะใช้ค่าจาก session ชั่วคราว (ไม่แนะนำ)
    }
}

// อัปเดตค่าตะกร้าจาก DB (ชื่อ/ราคา/จำกัดจำนวนด้วย stock)
foreach ($skus as $sku) {
    if (!isset($productMap[$sku])) {
        unset($_SESSION['cart'][$sku]); // สินค้าหายจากระบบ
        continue;
    }
    $row = $productMap[$sku];

    $_SESSION['cart'][$sku]['name']  = isset($row['name']) ? $row['name'] : (isset($_SESSION['cart'][$sku]['name']) ? $_SESSION['cart'][$sku]['name'] : '');
    $_SESSION['cart'][$sku]['price'] = isset($row['price']) ? (float)$row['price'] : 0;

    $stock = isset($row['stock']) ? (int)$row['stock'] : 0;
    $qty   = isset($_SESSION['cart'][$sku]['qty']) ? (int)$_SESSION['cart'][$sku]['qty'] : 0;
    if ($stock <= 0) {
        unset($_SESSION['cart'][$sku]); // ของหมด
        continue;
    }
    if ($qty > $stock) {
        $_SESSION['cart'][$sku]['qty'] = $stock;
    } elseif ($qty <= 0) {
        $_SESSION['cart'][$sku]['qty'] = 1;
    }
}

// คำนวณผลรวมใหม่
$total = 0; $count = 0;
foreach ($_SESSION['cart'] as $pid => $it) {
    $price = isset($it['price']) ? (float)$it['price'] : 0;
    $qty   = isset($it['qty']) ? (int)$it['qty'] : 0;
    $total += $price * $qty;
    $count += $qty;
}

// ======= ยืนยันคำสั่งซื้อ: บันทึกลง DB (orders + order_items) =======
if ($action === 'checkout') {
    if ($need_token && !valid_csrf($token)) {
        $order_error = 'โทเค็นไม่ถูกต้อง (CSRF)';
    } elseif (empty($_SESSION['cart'])) {
        $order_error = 'ตะกร้าสินค้าว่าง';
    } else {
        try {
            // เปิดโหมด Exception จะช่วยให้ catch error ได้ชัด
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $pdo->beginTransaction();

            // ล็อกแถวสินค้าและตรวจสต็อกอีกครั้ง (กันแข่ง)
            $items = array(); // เก็บรายการซื้อจริงจาก DB
            $grand = 0;

            foreach ($_SESSION['cart'] as $sku => $it) {
                $qty = isset($it['qty']) ? (int)$it['qty'] : 0;
                if ($qty <= 0) { continue; }

                // ล็อกด้วย FOR UPDATE
                $stm = $pdo->prepare("SELECT sku, name, price, stock FROM products WHERE sku = ? FOR UPDATE");
                $stm->execute(array($sku));
                $p = $stm->fetch(PDO::FETCH_ASSOC);
                if (!$p) {
                    throw new Exception('ไม่พบสินค้า SKU: '.$sku);
                }
                if ((int)$p['stock'] < $qty) {
                    throw new Exception('สต็อกไม่พอสำหรับ SKU: '.$sku);
                }

                $price = (float)$p['price'];
                $name  = (string)$p['name'];

                $items[] = array(
                    'sku' => $sku,
                    'name'=> $name,
                    'price'=> $price,
                    'qty' => $qty
                );
                $grand += $price * $qty;
            }

            if (empty($items)) {
                throw new Exception('ไม่มีรายการสำหรับออกออร์เดอร์');
            }

            // ทำออร์เดอร์
            $order_code = gen_order_code();
            $buyer_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

            $insOrder = $pdo->prepare("
                INSERT INTO orders (order_code, buyer_id, total_amount, status, created_at)
                VALUES (:code, :buyer, :total, 'pending', NOW())
            ");
            $insOrder->bindValue(':code', $order_code, PDO::PARAM_STR);
            if ($buyer_id === null) {
                $insOrder->bindValue(':buyer', null, PDO::PARAM_NULL);
            } else {
                $insOrder->bindValue(':buyer', $buyer_id, PDO::PARAM_INT);
            }
            $insOrder->bindValue(':total', (float)$grand);
            $insOrder->execute();

            $order_id = (int)$pdo->lastInsertId();

            // รายการสินค้า + ตัดสต็อก
            $insItem = $pdo->prepare("
                INSERT INTO order_items (order_id, sku, name, price, qty)
                VALUES (:oid, :sku, :name, :price, :qty)
            ");
            $updStock = $pdo->prepare("
                UPDATE products SET stock = stock - :qty WHERE sku = :sku
            ");

            foreach ($items as $it) {
                $insItem->execute(array(
                    ':oid'   => $order_id,
                    ':sku'   => $it['sku'],
                    ':name'  => $it['name'],
                    ':price' => (float)$it['price'],
                    ':qty'   => (int)$it['qty']
                ));
                $updStock->execute(array(
                    ':qty' => (int)$it['qty'],
                    ':sku' => $it['sku']
                ));
            }

            $pdo->commit();

            // ล้างตะกร้า และโชว์ข้อความสำเร็จ
            $_SESSION['cart'] = array();
            $count = 0; $total = 0;
            $order_success = 'สั่งซื้อสำเร็จ! รหัสคำสั่งซื้อ: '.$order_code;

        } catch (Exception $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $order_error = 'ไม่สามารถทำรายการได้: '.$ex->getMessage();
        }
    }
}

?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>ตะกร้าสินค้า</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">ตะกร้าสินค้า (<?php echo (int)$count; ?>)</h1>
    <div>
      <a href="index.php" class="btn btn-secondary">เลือกซื้อสินค้าต่อ</a>
    </div>
  </div>

  <?php if ($order_success !== ''): ?>
    <div class="alert alert-success"><?php echo h($order_success); ?></div>
  <?php endif; ?>
  <?php if ($order_error !== ''): ?>
    <div class="alert alert-danger"><?php echo h($order_error); ?></div>
  <?php endif; ?>

  <?php if (empty($_SESSION['cart'])): ?>
    <div class="alert alert-info">ยังไม่มีสินค้าในตะกร้า</div>
  <?php else: ?>
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>สินค้า</th>
                <th class="text-end">ราคา/ชิ้น</th>
                <th class="text-center">จำนวน</th>
                <th class="text-end">รวม</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($_SESSION['cart'] as $pid => $it):
                $name  = isset($it['name']) ? $it['name'] : '';
                $price = isset($it['price']) ? (float)$it['price'] : 0;
                $qty   = isset($it['qty']) ? (int)$it['qty'] : 0;
            ?>
              <tr>
                <td><?php echo h($name); ?><div class="text-muted small"><?php echo h($pid); ?></div></td>
                <td class="text-end">฿<?php echo format_price($price); ?></td>
                <td class="text-center">
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="id" value="<?php echo h($pid); ?>">
                    <input type="hidden" name="action" value="dec">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">-</button>
                  </form>
                  <span class="mx-2"><?php echo (int)$qty; ?></span>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="id" value="<?php echo h($pid); ?>">
                    <input type="hidden" name="action" value="inc">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">+</button>
                  </form>
                </td>
                <td class="text-end">฿<?php echo format_price($price * $qty); ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="id" value="<?php echo h($pid); ?>">
                    <input type="hidden" name="action" value="remove">
                    <button class="btn btn-outline-danger btn-sm" type="submit">ลบ</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
              <tr>
                <th colspan="3" class="text-end">ยอดรวม</th>
                <th class="text-end">฿<?php echo format_price($total); ?></th>
                <th></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="clear">
        <button class="btn btn-outline-danger" type="submit">ล้างตะกร้า</button>
      </form>

      <!-- ปุ่มยืนยันคำสั่งซื้อ: POST -> checkout (บันทึก DB) -->
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="checkout">
        <button class="btn btn-primary" type="submit">ยืนยันคำสั่งซื้อ</button>
      </form>
    </div>
  <?php endif; ?>
</div>
</body>
</html>

<?php
/* ===========================================================
   DDL ตัวอย่าง (รันครั้งเดียวพอ) ถ้ายังไม่มีตาราง orders/order_items

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_code VARCHAR(32) NOT NULL UNIQUE,
  buyer_id INT NULL,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  sku VARCHAR(100) NOT NULL,
  name VARCHAR(255) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  qty INT NOT NULL,
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

   หมายเหตุ:
   - ใช้สินค้าคีย์เป็น sku ตามโค้ดตะกร้า
   - ถ้าตาราง products มีคอลัมน์ vendor_id หรือฟิลด์อื่น ๆ
     ไม่กระทบส่วนออกออร์เดอร์ (ยกเว้นคุณต้องการผูก order กับผู้ขาย)
   =========================================================== */
