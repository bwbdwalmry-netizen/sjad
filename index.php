<?php
$cfg = require __DIR__.'/config.php';
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0e8a4d">
<title><?=htmlspecialchars($cfg['app_name'])?> — بطاقة الولاء</title>
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="%230e8a4d"/><text x="32" y="42" font-size="30" text-anchor="middle" fill="white" font-family="sans-serif">س</text></svg>'>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<link rel="stylesheet" href="assets/css/style.css">
<script>
window.SAJAD = {
  shop: <?=json_encode($cfg['shop_name'])?>,
  onesignal_app_id: <?=json_encode($cfg['onesignal_app_id'])?>
};
window.OneSignalDeferred = window.OneSignalDeferred || [];
OneSignalDeferred.push(async function(OneSignal){
  if (!window.SAJAD.onesignal_app_id) return;
  await OneSignal.init({ appId: window.SAJAD.onesignal_app_id, allowLocalhostAsSecureOrigin: true });
});
</script>
</head>
<body class="customer">
<main class="app">
  <header class="brand">
    <div class="logo">س</div>
    <div>
      <h1><?=htmlspecialchars($cfg['app_name'])?></h1>
      <p class="muted"><?=htmlspecialchars($cfg['shop_name'])?> — <?=htmlspecialchars($cfg['tagline'])?></p>
    </div>
  </header>

  <!-- حالة: لا توجد بطاقة على هذا الجهاز -->
  <section id="noCard" class="card center hidden">
    <div class="big-mark">🌿</div>
    <h2>أهلًا بك في <?=htmlspecialchars($cfg['app_name'])?></h2>
    <p class="muted">امسح باركود المغسلة المعلّق عند الكاشير لأول مرة فقط — سيتم إنشاء بطاقتك تلقائيًا على هذا الجهاز بدون تسجيل أو رقم هاتف.</p>
    <p class="muted small">إذا مسحتَ الباركود سابقًا من جهاز آخر، امسحه من هذا الجهاز لتربط البطاقة هنا.</p>
  </section>

  <!-- بطاقة العميل -->
  <section id="cardView" class="hidden">
    <div class="card hero" id="stampCard">
      <div class="hero-top">
        <div>
          <h2>بطاقة الولاء</h2>
          <p class="muted">امسح الباركود عند كل زيارة لجمع الأختام</p>
        </div>
        <div class="stamps-count"><span id="stampsNum">0</span><small>/<?=$cfg['goal_stamps']?></small></div>
      </div>
      <div class="stamps" id="stamps"></div>
      <div id="rewardBanner" class="reward-banner hidden">
        🎉 مبروك! استحققت <?=htmlspecialchars($cfg['reward_text'])?> — أرِ البطاقة للموظف ليصرفها
      </div>
      <div id="progressMsg" class="muted center-text"></div>
    </div>

    <div class="card">
      <h3>باركود البطاقة</h3>
      <p class="muted">يصوّره الموظف عند كل زيارة — لا حاجة لتسجيل الدخول أبدًا</p>
      <div class="barcode-wrap"><svg id="barcode"></svg></div>
      <div class="code-num" id="codeNum"></div>
    </div>

    <div class="card">
      <h3>التنبيهات</h3>
      <label class="switch-row">
        <span>تذكير عند الاقتراب من المغسلة (500م)</span>
        <input type="checkbox" id="geoToggle" checked><i></i>
      </label>
      <label class="switch-row">
        <span>تذكير أسبوعي للغسيل</span>
        <input type="checkbox" id="weeklyToggle" checked><i></i>
      </label>
      <p class="muted small" id="geoStatus"></p>
    </div>

    <div class="card stats">
      <div><b id="visitsNum">0</b><span>زيارة</span></div>
      <div><b id="lastVisit">—</b><span>آخر زيارة</span></div>
    </div>
  </section>
</main>
<script src="assets/js/customer.js"></script>
</body>
</html>
