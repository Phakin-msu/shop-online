<?php

ob_start(); session_start();
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit(); }
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: SAMEORIGIN'); header('Referrer-Policy: strict-origin-when-cross-origin');

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(function_exists('random_bytes')? random_bytes(32) : openssl_random_pseudo_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

function h($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function getv($k,$d){ return isset($_GET[$k])? $_GET[$k] : $d; }

$id    = getv('id','SKU-XXXX');
$name  = getv('name','สินค้าเดโม่');
$price = floatval(getv('price', 999.00));

$img = array(
 'SKU-WATCH-001'=>'img/h.png','SKU-GLASS-001'=>'img/q.png','SKU-BAG-001'=>'img/a.png',
 'SKU-SHOE-001'=>'img/b.png','SKU-HP-001'=>'img/headphone.png','SKU-CAM-001'=>'img/photo.png',
 'SKU-CUP-001'=>'img/t.png','SKU-NB-001'=>'img/notebook.png'
);
$img = isset($img[$id])? $img[$id] : 'img/photo.png';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>สั่งซื้อสินค้า - Checkout</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body{background:#f7f9fb}.container-wrap{max-width:1100px;margin:24px auto}.card{border-radius:14px}
  .section-title{font-weight:700}.addr-card{background:#fff;border:1px dashed #cfd5e1;border-radius:12px;padding:16px}
  .badge-default{background:#eef2ff;color:#2b3dbd}.product-img{width:88px;height:88px;object-fit:cover;border-radius:10px;border:1px solid #eee}
  .price{font-weight:700}.sticky-summary{position:sticky;top:16px}.muted{color:#6c757d}.xsmall{font-size:.85rem}
  .table>:not(caption)>*>*{vertical-align:middle}
</style>
</head>
<body>
<!-- Top bar -->
<div class="bg-white border-bottom">
  <div class="container-wrap d-flex align-items-center justify-content-between py-2">
    <div class="d-flex align-items-center gap-2">
      <img src="img/logomsu.png" style="height:36px;border-radius:8px" alt=""><div class="fw-semibold">เช็คเอาต์การสั่งซื้อ</div>
    </div>
    <div class="xsmall text-muted">เข้าสู่ระบบ: <?php echo h(isset($_SESSION['user_email'])? $_SESSION['user_email'] : 'guest'); ?></div>
  </div>
</div>

<div class="container-wrap">
<form id="checkoutForm" method="post" action="order_submit.php" class="row g-3">
<input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">

<!-- ซ้าย -->
<div class="col-lg-8">
  <!-- ที่อยู่ -->
  <div class="card shadow-sm mb-3"><div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="section-title">ที่อยู่จัดส่ง</div>
      <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#addrModal"><i class="bi bi-geo-alt me-1"></i> เพิ่ม/แก้ไขที่อยู่</button>
    </div>
    <div id="addrView" class="addr-card d-flex align-items-center justify-content-between">
      <div>
        <div class="fw-semibold" id="addr_name">ยังไม่มีชื่อผู้รับ</div>
        <div class="text-break" id="addr_full">กรุณาเพิ่มที่อยู่จัดส่ง</div>
        <div class="muted" id="addr_phone">โทร: -</div>
      </div>
      <span class="badge badge-default">เริ่มต้น</span>
    </div>
    <?php
      $fields=['name','address','phone','province','district','subdistrict','zipcode'];
      foreach($fields as $f) echo '<input type="hidden" name="shipping['.$f.']" id="f_'.$f.'">';
    ?>
    <div class="form-text mt-2">* ต้องระบุที่อยู่ก่อนยืนยันคำสั่งซื้อ</div>
  </div></div>

  <!-- รายการ -->
  <div class="card shadow-sm mb-3"><div class="card-body">
    <div class="section-title mb-3">รายการสินค้า</div>
    <div class="table-responsive"><table class="table">
      <thead class="table-light"><tr><th>สินค้า</th><th class="text-end" style="width:120px">ราคา/ชิ้น</th><th class="text-end" style="width:130px">จำนวน</th><th class="text-end" style="width:140px">รวม</th></tr></thead>
      <tbody><tr>
        <td>
          <div class="d-flex gap-3">
            <img src="<?php echo h($img); ?>" class="product-img" alt="product">
            <div><div class="fw-semibold"><?php echo h($name); ?></div><div class="xsmall text-muted">รหัส: <?php echo h($id); ?></div></div>
          </div>
          <input type="hidden" name="items[0][product_id]" value="<?php echo h($id); ?>">
          <input type="hidden" name="items[0][name]" value="<?php echo h($name); ?>">
          <input type="hidden" id="price" name="items[0][price]" value="<?php echo number_format($price,2,'.',''); ?>">
        </td>
        <td class="text-end"><span id="price_text" class="price"><?php echo number_format($price,2); ?></span> ฿</td>
        <td class="text-end"><input type="number" class="form-control form-control-sm text-center ms-auto" style="max-width:130px" min="1" step="1" id="qty" name="items[0][qty]" value="1"></td>
        <td class="text-end"><span id="line_total" class="fw-semibold"><?php echo number_format($price,2); ?></span> ฿</td>
      </tr></tbody>
    </table></div>
  </div></div>

  <!-- ขนส่ง -->
  <div class="card shadow-sm mb-3"><div class="card-body">
    <div class="section-title mb-2">วิธีขนส่ง</div>
    <div class="row g-2">
      <?php
        $ships=[['standard','มาตรฐาน (2-4 วัน)',40,true],['express','ด่วน (1-2 วัน)',80,false],['pickup','รับที่สาขา',0,false]];
        foreach($ships as $i=>$s){
          echo '<div class="col-md-4"><label class="form-check border rounded p-3 w-100 mb-0"><input class="form-check-input ship me-2" type="radio" name="shipping_method" value="'.$s[0].'" data-fee="'.$s[2].'" '.($s[3]?'checked':'').'>'.$s[1].'<div class="muted">'.($s[2]?'ค่าส่ง ':'').number_format($s[2]).' ฿</div></label></div>';
        }
      ?>
    </div>
    <input type="hidden" id="shipping_fee" name="shipping_fee" value="40.00">
  </div></div>

  <!-- ชำระเงิน -->
  <div class="card shadow-sm mb-3"><div class="card-body">
    <div class="section-title mb-2">วิธีชำระเงิน</div>
    <div class="border rounded p-3 d-flex align-items-start gap-2">
      <div class="fs-5"><i class="bi bi-qr-code"></i></div>
      <div><div class="fw-semibold">สแกนคิวอาร์ (PromptPay)</div><div class="muted">กด “ยืนยันคำสั่งซื้อ” เพื่อแสดง QR</div></div>
    </div>
    <input type="hidden" name="payment_method" value="qr">
    <div class="mt-3"><label class="form-label">หมายเหตุถึงร้าน (ถ้ามี)</label><textarea class="form-control" name="buyer_note" rows="2" placeholder="ระบุความต้องการเพิ่มเติม…"></textarea></div>
  </div></div>
</div>


<div class="col-lg-4">
  <div class="card shadow-sm sticky-summary"><div class="card-body">
    <div class="section-title mb-3">สรุปคำสั่งซื้อ</div>
    <div class="d-flex justify-content-between mb-2"><div class="muted">ค่าสินค้า</div><div><span id="sub_total"><?php echo number_format($price,2); ?></span> ฿</div></div>
    <div class="d-flex justify-content-between mb-2"><div class="muted">ค่าส่ง</div><div><span id="ship_fee">40.00</span> ฿</div></div>
    <hr>
    <div class="d-flex justify-content-between align-items-center">
      <div class="fw-semibold">ยอดรวมสุทธิ</div>
      <div class="fs-5 fw-bold"><span id="grand_total"><?php echo number_format($price+40,2); ?></span> ฿</div>
    </div>
    <input type="hidden" id="h_sub_total" name="summary[subtotal]" value="<?php echo number_format($price,2,'.',''); ?>">
    <input type="hidden" id="h_grand" name="summary[grand_total]" value="<?php echo number_format($price+40,2,'.',''); ?>">
    <button type="submit" class="btn btn-primary w-100 mt-3">ยืนยันคำสั่งซื้อ</button>
    <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">กลับไปเลือกสินค้า</a>
  </div></div>
</div>
</form>
</div>

<!-- Modal Address -->
<div class="modal fade" id="addrModal" tabindex="-1"><div class="modal-dialog">
  <form class="modal-content" id="addrForm" onsubmit="return false;">
    <div class="modal-header"><h5 class="modal-title">ที่อยู่จัดส่ง</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <?php
        $rows=[
          ['ชื่อผู้รับ','m_name','text'],['เบอร์โทร','m_phone','tel']
        ];
        foreach($rows as $r) echo '<div class="mb-2"><label class="form-label">'.$r[0].'</label><input type="'.$r[2].'" class="form-control" id="'.$r[1].'" required></div>';
      ?>
      <div class="mb-2"><label class="form-label">ที่อยู่</label><textarea class="form-control" id="m_address" rows="2" required></textarea></div>
      <div class="row g-2">
        <?php
          $addr2=[['จังหวัด','m_province'],['อำเภอ/เขต','m_district'],['ตำบล/แขวง','m_subdistrict'],['รหัสไปรษณีย์','m_zipcode']];
          foreach($addr2 as $i=>$a) echo '<div class="col-md-6"><label class="form-label">'.$a[0].'</label><input type="text" class="form-control" id="'.$a[1].'" required></div>';
        ?>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button><button class="btn btn-primary" id="saveAddress">บันทึกที่อยู่</button></div>
  </form>
</div></div>

<script>
  
  const nf=n=>Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
  const toNum=v=>Number((v||'0').toString().replace(/,/g,''));

  const qtyEl = document.getElementById('qty'),
        priceEl=document.getElementById('price'),
        lineTotalEl=document.getElementById('line_total'),
        subTotalEl=document.getElementById('sub_total'),
        shipFeeEl=document.getElementById('ship_fee'),
        grandEl=document.getElementById('grand_total'),
        hSub=document.getElementById('h_sub_total'),
        hGrand=document.getElementById('h_grand'),
        shipInputs=document.querySelectorAll('.ship'),
        shippingFeeHidden=document.getElementById('shipping_fee');

  qtyEl.addEventListener('input',()=>{ if(toNum(qtyEl.value)<1) qtyEl.value=1; recalc(); });
  shipInputs.forEach(r=>r.addEventListener('change',function(){
    const fee=Number(this.getAttribute('data-fee')||0);
    shipFeeEl.textContent=nf(fee); shippingFeeHidden.value=fee.toFixed(2); recalc();
  }));

  function recalc(){
    const qty=toNum(qtyEl.value), price=toNum(priceEl.value), ship=toNum(shipFeeEl.textContent),
          line=qty*price, sub=line, grand=sub+ship;
    lineTotalEl.textContent=nf(line); subTotalEl.textContent=nf(sub); grandEl.textContent=nf(grand);
    hSub.value=sub.toFixed(2); hGrand.value=grand.toFixed(2);
  }
  recalc();

  document.getElementById('saveAddress').addEventListener('click',()=>{
    const v=id=>document.getElementById(id).value.trim();
    const name=v('m_name'), phone=v('m_phone'), addr=v('m_address'), pv=v('m_province'), dt=v('m_district'), sd=v('m_subdistrict'), zip=v('m_zipcode');
    if(!name||!phone||!addr||!pv||!dt||!sd||!zip){ alert('กรุณากรอกที่อยู่ให้ครบ'); return; }
    document.getElementById('addr_name').textContent=name;
    document.getElementById('addr_full').textContent=`${addr} ${sd} ${dt} ${pv} ${zip}`;
    document.getElementById('addr_phone').textContent='โทร: '+phone;
    [['f_name',name],['f_phone',phone],['f_address',addr],['f_province',pv],['f_district',dt],['f_subdistrict',sd],['f_zipcode',zip]]
      .forEach(([id,val])=>document.getElementById(id).value=val);
    bootstrap.Modal.getInstance(document.getElementById('addrModal')).hide();
  });

  document.getElementById('checkoutForm').addEventListener('submit',e=>{
    if(!document.getElementById('f_name').value){ e.preventDefault(); alert('กรุณากรอกที่อยู่จัดส่งก่อนยืนยันคำสั่งซื้อ'); }
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
