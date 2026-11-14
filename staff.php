<?php
$cfg = require __DIR__.'/config.php';
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0e8a4d">
<title><?=htmlspecialchars($cfg['app_name'])?> — لوحة الموظف</title>
<link rel="icon" href='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="%230e8a4d"/><text x="32" y="42" font-size="30" text-anchor="middle" fill="white" font-family="sans-serif">س</text></svg>'>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="staff">
<main class="app">
  <header class="brand">
    <div class="logo">س</div>
    <div>
      <h1>لوحة الموظف</h1>
      <p class="muted"><?=htmlspecialchars($cfg['shop_name'])?></p>
    </div>
  </header>

  <!-- تسجيل الدخول -->
  <section id="loginBox" class="card">
    <h2>دخول الموظفين</h2>
    <p class="muted">هذه الصفحة للموظفين فقط</p>
    <div class="field"><label>اسم المستخدم</label><input id="user" autocomplete="username"></div>
    <div class="field"><label>الرمز السري</label><input id="pin" type="password" inputmode="numeric" autocomplete="current-password"></div>
    <button class="btn primary" id="loginBtn">دخول</button>
    <p class="error" id="loginErr"></p>
  </section>

  <!-- لوحة التحكم -->
  <section id="panel" class="hidden">
    <div class="card">
      <div class="row-between">
        <h2>تصوير باركود العميل</h2>
        <button class="btn ghost small" id="logoutBtn">خروج</button>
      </div>
      <div id="scanner" class="scanner"></div>
      <div class="row">
        <input id="manualCode" placeholder="أو أدخل الرقم يدويًا (مثال SJ-4821)">
        <button class="btn primary" id="manualBtn">بحث</button>
      </div>
      <p class="muted small">وجّه الكاميرا نحو باركود بطاقة العميل</p>
    </div>

    <div class="card hidden" id="custCard">
      <div class="row-between">
        <h2 id="custCode"></h2>
        <span id="rewardBadge" class="badge hidden">مكافأة مستحقة 🎉</span>
      </div>
      <div class="stats">
        <div><b id="cStamps"></b><span>ختم</span></div>
        <div><b id="cVisits"></b><span>زيارة</span></div>
        <div><b id="cLast"></b><span>آخر زيارة</span></div>
      </div>
      <div class="stamps" id="cStampGrid"></div>
      <button class="btn primary big-btn" id="stampBtn">إضافة ختم للزيارة</button>
      <p class="muted small" id="stampMsg"></p>
      <h3 class="mt">آخر العمليات</h3>
      <ul class="log" id="cLog"></ul>
    </div>

    <div class="card">
      <h2>أحدث البطاقات</h2>
      <ul class="log" id="custList"></ul>
    </div>
  </section>
</main>
<script src="assets/js/staff.js"></script>
</body>
</html>
