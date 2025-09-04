<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit();
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>คำถามที่พบบ่อย (FAQ)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h2 class="mb-3">คำถามที่พบบ่อย (FAQ)</h2>

  <div class="accordion" id="faqAccordion">

    <div class="accordion-item">
      <h2 class="accordion-header" id="q1">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#a1">
          สินค้าส่งกี่วันถึง?
        </button>
      </h2>
      <div id="a1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          จัดส่งแบบมาตรฐาน 2–4 วันทำการ / แบบด่วน 1–2 วัน (ไม่รวมวันหยุด)
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header" id="q2">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#a2">
          มีค่าจัดส่งเท่าไร?
        </button>
      </h2>
      <div id="a2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          ส่งมาตรฐาน 40 บาท / ส่งด่วน 80 บาท / รับเองที่สาขาฟรี
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header" id="q3">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#a3">
          สามารถขอคืนสินค้าได้หรือไม่?
        </button>
      </h2>
      <div id="a3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          สินค้ามีปัญหา เคลมได้ภายใน 7 วันนับจากวันที่ได้รับสินค้า (ตามเงื่อนไขร้าน)
        </div>
      </div>
    </div>

  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
