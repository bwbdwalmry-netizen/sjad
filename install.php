<?php
// معالج التثبيت بالمتصفح — بدون تعديل ملفات يدويًا
$lock = __DIR__.'/data/installed.lock';
$err = ''; $done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop   = trim($_POST['shop_name'] ?? '');
    $lat    = (float)($_POST['lat'] ?? 0);
    $lng    = (float)($_POST['lng'] ?? 0);
    $radius = max(50, min(5000, (int)($_POST['radius'] ?? 500)));
    $goal   = max(3, min(30, (int)($_POST['goal'] ?? 8)));
    $user   = trim($_POST['admin_user'] ?? '');
    $pin    = (string)($_POST['admin_pin'] ?? '');
    $osid   = trim($_POST['onesignal_app_id'] ?? '');
    $oskey  = trim($_POST['onesignal_rest_key'] ?? '');

    if ($shop === '' || $user === '' || !preg_match('/^\d{4,8}$/', $pin) || ($lat == 0.0 && $lng == 0.0)) {
        $err = 'أكمل كل الحقول — والرمز السري أرقام فقط (4–8)، والإحدائيات مطلوبة.';
    } elseif (file_exists($lock)) {
        $err = 'التطبيق مثبّت مسبقًا — احذف data/installed.lock لإعادة التثبيت.';
    } else {
        if (!is_dir(__DIR__.'/data')) mkdir(__DIR__.'/data', 0775, true);
        $cfg = [
            'app_name'=>'سجاد','shop_name'=>$shop,
            'tagline'=>'بطاقة الولاء الرقمية — اجمع الأختام واكسب الغسيل المجاني',
            'goal_stamps'=>$goal,'reward_text'=>'غسيل مجاني',
            'geo_lat'=>$lat,'geo_lng'=>$lng,'geo_radius_m'=>$radius,'geo_cooldown'=>24,
            'onesignal_app_id'=>$osid,'onesignal_rest_key'=>$oskey,
        ];
        $export = var_export($cfg, true);
        file_put_contents(__DIR__.'/config.php', "<?php\n// أُنشئ بواسطة معالج التثبيت — عدّل القيم من هنا عند الحاجة\nreturn $export;\n");
        require __DIR__.'/db.php';
        $pdo = sajad_db();
        $pdo->prepare('INSERT OR REPLACE INTO staff(username,pin_hash) VALUES(?,?)')
            ->execute([$user, password_hash($pin, PASSWORD_DEFAULT)]);
        file_put_contents($lock, date('c'));
        $done = true;
    }
}
$cfg0 = is_file(__DIR__.'/config.php') ? require __DIR__.'/config.php' : [];
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0e8a4d">
<title>تثبيت تطبيق سجاد</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="app" style="max-width:520px">
  <header class="brand"><div class="logo">س</div>
    <div><h1>تثبيت سجاد</h1><p class="muted"><?= $done ? 'اكتمل التثبيت' : 'ثلاث دقائق وينطلق تطبيقك' ?></p></div>
  </header>

  <?php if ($done): ?>
  <section class="card center">
    <div class="big-mark">🌿</div>
    <h2>التطبيق جاهز!</h2>
    <p class="muted">خطوتك الأخيرة: افتح صفحة باركود المغسلة واطبعه:</p>
    <p style="margin:12px 0"><a class="btn primary" style="display:inline-block;text-decoration:none" href="qr.php">طباعة باركود المغسلة 🖨️</a></p>
    <p class="muted small">بطاقة العميل: <b>index.php</b> • لوحة الموظف: <b>staff.php</b></p>
    <p class="muted small">للأمان: احذف ملف <b>install.php</b> الآن.</p>
  </section>

  <?php else: ?>
  <section class="card">
    <h2>بيانات المغسلة</h2>
    <?php if ($err): ?><p class="error"><?=htmlspecialchars($err)?></p><?php endif; ?>
    <form method="post">
      <div class="field"><label>اسم المغسلة</label><input name="shop_name" required value="<?=htmlspecialchars($_POST['shop_name'] ?? '')?>" placeholder="مغسلة سجاد"></div>
      <div class="field"><label>خط عرض الموقع (Latitude) — من خرائط جوجل</label><input name="lat" required inputmode="decimal" value="<?=htmlspecialchars($_POST['lat'] ?? '')?>" placeholder="24.7136"></div>
      <div class="field"><label>خط طول الموقع (Longitude)</label><input name="lng" required inputmode="decimal" value="<?=htmlspecialchars($_POST['lng'] ?? '')?>" placeholder="46.6753"></div>
      <div class="row">
        <div class="field" style="flex:1"><label>نطاق التنبيه (متر)</label><input name="radius" type="number" value="500" min="50" max="5000"></div>
        <div class="field" style="flex:1"><label>الأختام للمكافأة</label><input name="goal" type="number" value="8" min="3" max="30"></div>
      </div>
      <h2 style="margin-top:8px">حساب الموظف المشرف</h2>
      <div class="field"><label>اسم المستخدم</label><input name="admin_user" required value="<?=htmlspecialchars($_POST['admin_user'] ?? '')?>" placeholder="admin"></div>
      <div class="field"><label>الرمز السري (أرقام 4–8)</label><input name="admin_pin" required inputmode="numeric" pattern="\d{4,8}" placeholder="مثال 7391"></div>
      <h2 style="margin-top:8px">الإشعارات (اختياري — OneSignal)</h2>
      <div class="field"><label>App ID</label><input name="onesignal_app_id" value="<?=htmlspecialchars($_POST['onesignal_app_id'] ?? '')?>"></div>
      <div class="field"><label>REST API Key</label><input name="onesignal_rest_key" value="<?=htmlspecialchars($_POST['onesignal_rest_key'] ?? '')?>"></div>
      <button class="btn primary big-btn">تثبيت التطبيق 🚀</button>
    </form>
  </section>
  <p class="muted small center">يمكنك ترك حقول OneSignal فارغة وتفعيلها لاحقًا من config.php</p>
  <?php endif; ?>
</main>
</body>
</html>
