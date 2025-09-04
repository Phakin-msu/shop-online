<?php
session_start();
require_once 'config.php'; // ต้องมี $pdo และ dbname=shopdb

// ต้องล็อกอินก่อนถึงจะเข้าได้
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit();
}

// HTTP Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// นับจำนวนสินค้าในตะกร้า (สำหรับ badge)
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cart_count += isset($it['qty']) ? (int)$it['qty'] : 0;
    }
}

// ดึงสินค้า 6 ชิ้นล่าสุดจากฐานข้อมูล (แสดงทุกชิ้น ไม่กรอง stock)
$products = array();
$fetch_error = '';
try {
    $stmt = $pdo->query("
        SELECT id, sku, name, description, price, image_url, stock
        FROM products
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $fetch_error = 'ไม่สามารถดึงข้อมูลสินค้าได้';
}

// ฟังก์ชันช่วยแปลงตัวเลขราคาแสดงผล
function format_price($price) {
    return number_format((float)$price, 0);
}
// escape
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>หน้าหลัก - ระบบจัดซื้อสินค้า</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <style>
    .logo { height: 40px; margin-right: 10px; border-radius: 6px; }
    .menu-bar {
      display: flex; align-items: center; justify-content: space-between;
      background: #fff; border-bottom: 2px solid #eee; padding: 10px 20px;
    }
    .menu-items a { margin-right: 20px; text-decoration: none; font-weight: 500; color: #333; }
    .menu-items a:hover { color: #007bff; }
    .products-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; margin-top: 2rem; }
    .product-card {
      position: relative;
      background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 8px 25px rgba(0,0,0,.1);
      transition: transform .3s ease, box-shadow .3s ease; border:1px solid #f0f0f0;
    }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,.15); }
    .product-card img { width:100%; height:250px; object-fit:cover; border-bottom:1px solid #f0f0f0; }
    .product-info { padding:1.5rem; }
    .product-info h3 { margin:0 0 .5rem 0; font-size:1.25rem; font-weight:600; }
    .price { color:#e74c3c; font-size:1.4rem; font-weight:700; margin:.25rem 0 .5rem; }
    .description { color:#666; font-size:.95rem; margin:.25rem 0 1rem; line-height:1.5; }
    .stock { font-size:.95rem; font-weight:600; }
    .stock.ok { color:#2e7d32; }       /* เขียว */
    .stock.low { color:#ef6c00; }      /* ส้ม */
    .stock.out { color:#b00020; }      /* แดง */

    .btn-add-cart {
      width:100%; background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
      color:#fff; border:none; padding:.75rem 1.25rem; border-radius:8px; font-size:1rem; font-weight:600; cursor:pointer;
      transition: transform .2s ease, box-shadow .2s ease;
    }
    .btn-add-cart:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,.4); }
    .btn-to-cart { width:100%; border-radius:8px; font-weight:600; }

    /* ป้ายสินค้าหมด */
    .ribbon-out {
      position:absolute; top:12px; left:-40px; transform:rotate(-45deg);
      background:#b00020; color:#fff; padding:6px 60px; font-weight:700; box-shadow:0 6px 12px rgba(0,0,0,.15);
      letter-spacing:.5px;
    }
    .disabled-btn {
      pointer-events: none; opacity: .6;
    }
  </style>
</head>
<body>

<!-- โลโก้ + ปุ่มออกจากระบบ -->
<div class="d-flex justify-content-between align-items-center bg-dark text-white p-2">
  <div class="d-flex align-items-center">
    <img src="img/logomsu.png" class="logo" alt="Logo">
    <span class="fw-bold">ระบบจัดซื้อสินค้า</span>
  </div>
  <a href="logout.php" class="btn btn-danger btn-sm">ออกจากระบบ</a>
</div>

<!-- แถบเมนู + ค้นหา -->
<div class="menu-bar">
  <div class="menu-items">
    <a href="index.php"><i class="bi bi-house-door me-1"></i> หน้าแรก</a>
    <a href="contact.php"><i class="bi bi-telephone me-1"></i> ติดต่อเรา</a>
    <a href="howto.php"><i class="bi bi-bag-check me-1"></i> วิธีสั่งซื้อ & ชำระเงิน</a>
    <a href="faq.php"><i class="bi bi-question-circle me-1"></i> คำถามที่พบบ่อย</a>
    <a href="quote.php" class="position-relative">
  <i class="bi bi-file-earmark-text me-1"></i> เสนอราคา
</a>

    <a href="cart.php" class="position-relative">
      <i class="bi bi-cart me-1"></i> ตะกร้า
      <span id="cart-count" class="badge bg-danger ms-1"><?php echo (int)$cart_count; ?></span>
    </a>
  </div>

  <!-- ฟอร์มค้นหา ชิดขวา -->
  <form class="d-flex ms-auto" role="search" action="search.php" method="get" style="max-width:400px; width:100%;">
    <input class="form-control me-2" type="search" placeholder="ค้นหาสินค้า..." name="q">
    <button class="btn btn-outline-primary" type="submit">ค้นหา</button>
  </form>
</div>

<div class="container-fluid mt-4">
  <div class="content mt-3">
    <h2 class="text-center">สินค้าแนะนำ</h2>

    <?php if ($fetch_error !== ''): ?>
      <div class="alert alert-danger text-center my-4"><?php echo h($fetch_error); ?></div>
    <?php endif; ?>

    <div class="products-grid">
      <?php if (!empty($products)): ?>
        <?php foreach ($products as $p): ?>
          <?php
            $sku   = isset($p['sku']) ? $p['sku'] : '';
            $name  = isset($p['name']) ? $p['name'] : '';
            $desc  = isset($p['description']) ? $p['description'] : '';
            $price = isset($p['price']) ? $p['price'] : 0;
            $img   = (isset($p['image_url']) && $p['image_url'] !== '') ? $p['image_url'] : 'img/placeholder.png';
            $stock = isset($p['stock']) ? (int)$p['stock'] : 0;

            // คลาสสีสถานะสต็อก
            $stockClass = 'out';
            if ($stock > 10) $stockClass = 'ok';
            else if ($stock > 0) $stockClass = 'low';

            $isOut = ($stock <= 0);
          ?>
          <div class="product-card">
            <?php if ($isOut): ?>
              <div class="ribbon-out">สินค้าหมด</div>
            <?php endif; ?>

            <img src="<?php echo h($img); ?>" alt="<?php echo h($name); ?>">
            <div class="product-info">
              <h3><?php echo h($name); ?></h3>
              <p class="price">฿<?php echo format_price($price); ?></p>
              <p class="stock <?php echo $stockClass; ?>">
                สต็อก: <?php echo max(0, $stock); ?> ชิ้น
              </p>
              <p class="description"><?php echo h($desc); ?></p>

              <!-- ปุ่มสั่งซื้อ: ส่งไป orderpd.php -->
              <button class="btn-add-cart <?php echo $isOut ? 'disabled-btn' : ''; ?>"
                      data-id="<?php echo h($sku); ?>"
                      data-name="<?php echo h($name); ?>"
                      data-price="<?php echo (float)$price; ?>"
                      <?php echo $isOut ? 'disabled aria-disabled="true"' : ''; ?>>
                สั่งซื้อ
              </button>

              <!-- ปุ่มเพิ่มเข้าตะกร้า: POST ไป cart_add.php -->
              <button class="btn btn-outline-success btn-to-cart mt-2 add-to-cart <?php echo $isOut ? 'disabled-btn' : ''; ?>"
                      data-id="<?php echo h($sku); ?>"
                      data-name="<?php echo h($name); ?>"
                      data-price="<?php echo (float)$price; ?>"
                      <?php echo $isOut ? 'disabled aria-disabled="true"' : ''; ?>>
                เพิ่มเข้าตะกร้า
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="alert alert-info text-center">ยังไม่มีสินค้าในระบบ หรือยังไม่ได้เพิ่มสินค้า</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  // ปุ่ม "สั่งซื้อ" -> ไป orderpd.php พร้อม query string (เฉพาะสินค้าที่มีสต็อก)
  var buyButtons = document.querySelectorAll('.btn-add-cart');
  for (var i = 0; i < buyButtons.length; i++) {
    buyButtons[i].addEventListener('click', function () {
      if (this.hasAttribute('disabled')) return;
      var id = this.getAttribute('data-id') || '';
      var name = this.getAttribute('data-name') || '';
      var price = this.getAttribute('data-price') || '';
      var params = new URLSearchParams({ id: id, name: name, price: price });
      window.location.href = 'orderpd.php?' + params.toString();
    });
  }

  // ปุ่ม "เพิ่มเข้าตะกร้า" -> POST ไป cart_add.php แล้วอัปเดต badge (เฉพาะสินค้าที่มีสต็อก)
  var addButtons = document.querySelectorAll('.add-to-cart');
  for (var j = 0; j < addButtons.length; j++) {
    addButtons[j].addEventListener('click', function () {
      if (this.hasAttribute('disabled')) return;
      var id = this.getAttribute('data-id') || '';
      var name = this.getAttribute('data-name') || '';
      var price = this.getAttribute('data-price') || '';

      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'cart_add.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
          try {
            var res = JSON.parse(xhr.responseText || '{}');
            if (xhr.status === 200 && res.ok) {
              var badge = document.getElementById('cart-count');
              if (badge) { badge.textContent = res.count; }
              alert('เพิ่ม "' + name + '" เข้าตะกร้าแล้ว');
            } else {
              alert('เพิ่มตะกร้าไม่สำเร็จ');
            }
          } catch (e) {
            alert('เกิดข้อผิดพลาดในการประมวลผล');
          }
        }
      };

      var body = 'id=' + encodeURIComponent(id)
               + '&name=' + encodeURIComponent(name)
               + '&price=' + encodeURIComponent(price);

      xhr.send(body);
    });
  }
</script>

</body>
</html>
