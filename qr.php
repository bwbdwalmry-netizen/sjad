<?php
// صفحة باركود المغسلة — للطباعة وتعليقها عند الكاشير
$cfg = require __DIR__.'/config.php';
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
$url = $proto.'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/new.php';
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>باركود المغسلة — <?=htmlspecialchars($cfg['app_name'])?></title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Tajawal',sans-serif;background:#f4faf6;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.sheet{background:#fff;border-radius:20px;max-width:420px;width:100%;padding:36px 28px;text-align:center;border:2px solid #0e8a4d;box-shadow:0 10px 40px rgba(14,138,77,.12)}
.logo{width:64px;height:64px;border-radius:16px;background:linear-gradient(145deg,#0e8a4d,#0a6b3b);color:#fff;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;margin:0 auto 12px}
h1{margin:0 0 4px;font-size:24px;color:#12241b}
p{color:#5c7267;margin:4px 0}
#qrcode{width:260px;height:260px;margin:20px auto}
#qrcode img,#qrcode canvas{margin:0 auto}
.steps{text-align:right;background:#e7f6ee;border-radius:12px;padding:14px 16px;margin-top:16px;font-size:14px;color:#07522c}
.steps li{margin:6px 0}
.print-btn{margin-top:18px;background:#0e8a4d;color:#fff;border:none;border-radius:12px;padding:14px 30px;font-family:inherit;font-size:16px;font-weight:700;cursor:pointer}
@media print{.print-btn{display:none}body{background:#fff}}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
<div class="sheet">
  <div class="logo">س</div>
  <h1><?=htmlspecialchars($cfg['shop_name'])?></h1>
  <p>امسح الباركود بكاميرا هاتفك واستلم بطاقة ولاءك فورًا</p>
  <div id="qrcode"></div>
  <p style="font-size:12px;word-break:break-all;color:#9fb3a8"><?=htmlspecialchars($url)?></p>
  <ul class="steps">
    <li>وجّه كاميرا الهاتف نحو الباركود</li>
    <li>افتح الرابط — تُنشأ بطاقتك تلقائيًا بدون تسجيل</li>
    <li>اعرض الباركود لموظفنا عند كل زيارة لجمع الأختام</li>
  </ul>
  <button class="print-btn" onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
</div>
<script>
new QRCode(document.getElementById('qrcode'), {
  text: <?=json_encode($url)?>,
  width: 260, height: 260,
  colorDark: '#07522c', colorLight: '#ffffff',
  correctLevel: QRCode.CorrectLevel.M
});
</script>
</body>
</html>
