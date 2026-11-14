<?php
// تهيئة قاعدة بيانات SQLite وإنشاء الجداول تلقائيًا عند أول تشغيل
function sajad_db(): PDO {
    $cfg = require __DIR__.'/config.php';
    $dir = dirname($cfg['db_path']);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $pdo = new PDO('sqlite:'.$cfg['db_path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('CREATE TABLE IF NOT EXISTS customers(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        token TEXT NOT NULL,
        name TEXT DEFAULT "",
        stamps INTEGER DEFAULT 0,
        visits INTEGER DEFAULT 0,
        last_visit TEXT,
        push_token TEXT DEFAULT "",
        notify_weekly INTEGER DEFAULT 1,
        notify_geo INTEGER DEFAULT 1,
        geo_last_sent TEXT,
        created_at TEXT DEFAULT (datetime("now"))
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS stamps_log(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER NOT NULL,
        staff TEXT NOT NULL,
        note TEXT DEFAULT "",
        created_at TEXT DEFAULT (datetime("now"))
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS notifications_log(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER,
        type TEXT,
        title TEXT,
        sent INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime("now"))
    )');
    return $pdo;
}

function new_code(PDO $pdo, string $prefix='SJ'): string {
    do {
        $code = $prefix.'-'.random_int(1000, 9999);
        $q = $pdo->prepare('SELECT 1 FROM customers WHERE code=?');
        $q->execute([$code]);
    } while ($q->fetchColumn());
    return $code;
}

// المسافة بالمتر بين نقطتين (هافرساين)
function distance_m(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R=6371000; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
    $a=sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
    return $R*2*asin(sqrt($a));
}

// إرسال إشعار عبر OneSignal — يعمل فور وضع المفاتيح في config.php
function send_push(PDO $pdo, array $customer, string $title, string $body, string $type='general'): void {
    $cfg = require __DIR__.'/config.php';
    $log = $pdo->prepare('INSERT INTO notifications_log(customer_id,type,title,sent) VALUES(?,?,?,?)');
    if (empty($cfg['onesignal_app_id']) || empty($cfg['onesignal_rest_key']) || empty($customer['push_token'])) {
        $log->execute([$customer['id'] ?? null, $type, $title, 0]);
        return;
    }
    $payload = [
        'app_id' => $cfg['onesignal_app_id'],
        'include_subscription_ids' => [$customer['push_token']],
        'headings' => ['en'=>$title,'ar'=>$title],
        'contents' => ['en'=>$body,'ar'=>$body],
        'url' => '',
    ];
    $ch = curl_init('https://onesignal.com/api/v1/notifications');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Basic '.$cfg['onesignal_rest_key']],
        CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_TIMEOUT=>10,
    ]);
    curl_exec($ch); curl_close($ch);
    $log->execute([$customer['id'], $type, $title, 1]);
}
