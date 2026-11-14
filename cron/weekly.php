<?php
// التذكير الأسبوعي — يُشغَّل عبر Cron مرة أسبوعيًا
// مثال cPanel: 0 12 * * 5  /usr/bin/php /home/USER/public_html/sajad/cron/weekly.php
require __DIR__.'/../db.php';
$cfg = require __DIR__.'/../config.php';
$pdo = sajad_db();

$q = $pdo->query("SELECT * FROM customers WHERE notify_weekly = 1");
$count = 0;
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $c) {
    send_push($pdo, $c,
        $cfg['shop_name'].' تفتقدك 🌿',
        'سجادتك تستحق الانتعاش — زيارتك القادمة أقرب إلى الغسيل المجاني ('.$c['stamps'].'/'.$cfg['goal_stamps'].')',
        'weekly');
    $count++;
}
echo date('c')." — أُرسل التذكير الأسبوعي إلى $count عميل\n";
