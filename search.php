<?php
session_start();
require_once 'config.php'; // ต้องตั้งค่า $pdo ชี้ไปที่ shopdb

// ต้องล็อกอินก่อนถึงจะเข้าได้
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit();
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// ดึงผลลัพธ์จากฐานข้อมูล
$results = array();
$error = '';

try {
    if ($q === '') {
        // ไม่ได้พิมพ์คำค้น: แสดง 24 รายการล่าสุด
        $stmt = $pdo->query("
            SELECT id, sku, name, description, price, image_url
            FROM products
            ORDER BY created_at DESC
            LIMIT 24
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // มีคำค้น: ค้นหาใน name, description และ sku
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare("
            SELECT id, sku, name, description, price, image_url
            FROM products
            WHERE name LIKE ? OR description LIKE ? OR sku LIKE ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute(array($like, $like, $like));
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'ไม่สามารถค้นหาสินค้าได้';
}

// ฟังก์ชันแสดงราคา
function format_price($n) {
    return number_format((float)$n, 0);
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>ผลการค้นหา</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .card img { height: 200px; object-fit: cover; }
  </style>
</head>
<body>
<div class="container mt-4">
  <form class="row g-2 mb-3" method="get" action="">
    <div class="col-sm-10">
      <input type="search" class="form-control" name="q" placeholder="ค้นหาสินค้า..."
             value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-sm-2 d-grid">
      <button class="btn btn-primary" type="submit">ค้นหา</button>
    </div>
  </form>

  <h2 class="mb-3">
    <?php if ($q === ''): ?>
      สินค้าล่าสุด
    <?php else: ?>
      ผลการค้นหาสำหรับ: <?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>
    <?php endif; ?>
  </h2>

  <?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <div class="row">
    <?php if (!empty($results)): ?>
      <?php foreach ($results as $p): ?>
        <?php
          $sku   = isset($p['sku']) ? $p['sku'] : '';
          $name  = isset($p['name']) ? $p['name'] : '';
          $desc  = isset($p['description']) ? $p['description'] : '';
          $price = isset($p['price']) ? $p['price'] : 0;
          $img   = (isset($p['image_url']) && $p['image_url'] !== '') ? $p['image_url'] : 'img/placeholder.png';
        ?>
        <div class="col-md-3 mb-4">
          <div class="card h-100">
            <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>"
                 class="card-img-top"
                 alt="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h5>
              <p class="card-text text-danger fw-bold">฿<?php echo format_price($price); ?></p>
              <p class="text-muted flex-grow-1"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></p>

              <!-- แนะนำ: ส่งเฉพาะ sku ไป orderpd.php แล้วให้ไปดึงราคา/ข้อมูลจาก DB ซ้ำ -->
              <a href="orderpd.php?sku=<?php echo urlencode($sku); ?>"
                 class="btn btn-primary mt-auto">สั่งซื้อ</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-info">ไม่พบสินค้าที่ค้นหา</div>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
