<?php
// واجهة API — JSON + جلسات آمنة + CSRF + حد محاولات الدخول
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')]);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
require __DIR__.'/db.php';
$cfg = require __DIR__.'/config.php';
$pdo = sajad_db();
$act = $_REQUEST['action'] ?? '';

function out($d, int $code=200){ http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function fail($m, $c=400){ out(['ok'=>false,'error'=>$m], $c); }
function is_staff(): bool { return !empty($_SESSION['staff']); }
function check_csrf(): void {
    $t = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) fail('انتهت الجلسة — أعد تحميل الصفحة', 419);
}

// ---------- العميل ----------
if ($act === 'new_customer') {
    $code  = new_code($pdo);
    $token = bin2hex(random_bytes(24));
    $pdo->prepare('INSERT INTO customers(code,token) VALUES(?,?)')->execute([$code,$token]);
    out(['ok'=>true,'code'=>$code,'token'=>$token,'url'=>'index.php?c='.$code.'&t='.$token]);
}

if ($act === 'card') {
    $c = find_customer($pdo, $_REQUEST['code'] ?? '', $_REQUEST['token'] ?? '');
    if (!$c) fail('بطاقة غير موجودة — امسح باركود المغسلة من هذا الجهاز', 404);
    out(['ok'=>true,'card'=>[
        'code'=>$c['code'],'stamps'=>(int)$c['stamps'],'visits'=>(int)$c['visits'],
        'last_visit'=>$c['last_visit'],'notify_weekly'=>(bool)$c['notify_weekly'],
        'notify_geo'=>(bool)$c['notify_geo'],
        'reward_due'=>(int)$c['stamps'] >= (int)$cfg['goal_stamps'],
        'goal'=>(int)$cfg['goal_stamps'],
    ]]);
}

if ($act === 'prefs') {
    $c = find_customer($pdo, $_REQUEST['code'] ?? '', $_REQUEST['token'] ?? '');
    if (!$c) fail('غير مصرح', 403);
    $pdo->prepare('UPDATE customers SET notify_weekly=?, notify_geo=? WHERE id=?')
        ->execute([!empty($_POST['weekly'])?1:0, !empty($_POST['geo'])?1:0, $c['id']]);
    out(['ok'=>true]);
}

if ($act === 'save_push_token') {
    $c = find_customer($pdo, $_REQUEST['code'] ?? '', $_REQUEST['token'] ?? '');
    $pt = substr((string)($_POST['push_token'] ?? ''), 0, 80);
    if ($c && $pt) $pdo->prepare('UPDATE customers SET push_token=? WHERE id=?')->execute([$pt,$c['id']]);
    out(['ok'=>true]);
}

if ($act === 'geo_ping') {
    $c = find_customer($pdo, $_REQUEST['code'] ?? '', $_REQUEST['token'] ?? '');
    if (!$c) fail('غير مصرح', 403);
    $lat=(float)($_POST['lat']??0); $lng=(float)($_POST['lng']??0);
    $d = distance_m($lat,$lng,(float)$cfg['geo_lat'],(float)$cfg['geo_lng']);
    $inside = $d <= (float)$cfg['geo_radius_m'];
    $sent = false;
    if ($inside && $c['notify_geo']) {
        $cool = (int)$cfg['geo_cooldown'];
        $fresh = $c['geo_last_sent'] && (time()-strtotime($c['geo_last_sent'])) < $cool*3600;
        if (!$fresh) {
            send_push($pdo,$c,'أنت قريب من '.$cfg['shop_name'].'! 🌿',
                'مرّر الآن واحصل على ختمًا جديدًا في بطاقتك — تبقّى قليل للغسيل المجاني','geo');
            $pdo->prepare('UPDATE customers SET geo_last_sent=datetime("now") WHERE id=?')->execute([$c['id']]);
            $sent = true;
        }
    }
    out(['ok'=>true,'distance_m'=>round($d),'inside'=>$inside,'notified'=>$sent]);
}

// ---------- الموظف ----------
if ($act === 'staff_login') {
    if (!login_allowed($pdo)) fail('محاولات كثيرة — حاول بعد 10 دقائق', 429);
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['pin'] ?? '');
    $q = $pdo->prepare('SELECT * FROM staff WHERE username=?');
    $q->execute([$u]); $s = $q->fetch(PDO::FETCH_ASSOC);
    if (!$s || !password_verify($p, $s['pin_hash'])) {
        login_failed($pdo);
        fail('بيانات الدخول غير صحيحة', 401);
    }
    login_succeeded($pdo);
    session_regenerate_id(true);
    $_SESSION['staff'] = $s['username'];
    $_SESSION['csrf']  = bin2hex(random_bytes(16));
    out(['ok'=>true,'csrf'=>$_SESSION['csrf'],'username'=>$s['username']]);
}
if ($act === 'staff_logout') { session_destroy(); out(['ok'=>true]); }
if (!is_staff() && in_array($act,['staff_scan','add_stamp','customers','stats','export_csv'],true))
    fail('يجب تسجيل دخول الموظف', 401);
if (in_array($act,['add_stamp'],true)) check_csrf();

if ($act === 'staff_scan') {
    $code = preg_replace('/[^A-Z0-9\-]/i','', (string)($_REQUEST['code'] ?? ''));
    $q=$pdo->prepare('SELECT * FROM customers WHERE code=?');
    $q->execute([strtoupper($code)]); $c=$q->fetch(PDO::FETCH_ASSOC);
    if (!$c) fail('الباركود غير مسجل — تأكد من الرقم', 404);
    $logs=$pdo->prepare('SELECT staff,note,created_at FROM stamps_log WHERE customer_id=? ORDER BY id DESC LIMIT 8');
    $logs->execute([$c['id']]);
    out(['ok'=>true,'customer'=>[
        'code'=>$c['code'],'stamps'=>(int)$c['stamps'],'visits'=>(int)$c['visits'],
        'last_visit'=>$c['last_visit'],'created_at'=>$c['created_at'],
        'reward_due'=>(int)$c['stamps']>=(int)$cfg['goal_stamps'],'goal'=>(int)$cfg['goal_stamps'],
        'recent'=>$logs->fetchAll(PDO::FETCH_ASSOC),
    ]]);
}

if ($act === 'add_stamp') {
    $code = preg_replace('/[^A-Z0-9\-]/i','', (string)($_POST['code'] ?? ''));
    $q=$pdo->prepare('SELECT * FROM customers WHERE code=?');
    $q->execute([strtoupper($code)]); $c=$q->fetch(PDO::FETCH_ASSOC);
    if (!$c) fail('العميل غير موجود', 404);
    $stamps=(int)$c['stamps']+1; $reward=false;
    if ($stamps >= (int)$cfg['goal_stamps']) { $stamps=0; $reward=true; }
    $pdo->prepare('UPDATE customers SET stamps=?, visits=visits+1, last_visit=date("now") WHERE id=?')->execute([$stamps,$c['id']]);
    $pdo->prepare('INSERT INTO stamps_log(customer_id,staff,note) VALUES(?,?,?)')
        ->execute([$c['id'], $_SESSION['staff'], $reward?'صرف مكافأة':'ختم زيارة']);
    if ($reward) {
        send_push($pdo,$c,'مبروك! حصلت على '.$cfg['reward_text'].' 🎉',
            'اكتملت بطاقتك في '.$cfg['shop_name'].' — أرِها الموظف عند زيارتك القادمة','reward');
    }
    out(['ok'=>true,'stamps'=>$stamps,'reward_redeemed'=>$reward,'goal'=>(int)$cfg['goal_stamps']]);
}

if ($act === 'customers') {
    $q=$pdo->query('SELECT code,stamps,visits,last_visit,created_at FROM customers ORDER BY id DESC LIMIT 200');
    out(['ok'=>true,'customers'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($act === 'stats') {
    $t = [];
    $t['customers'] = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $t['stamps_today'] = (int)$pdo->query("SELECT COUNT(*) FROM stamps_log WHERE created_at >= datetime('now','start of day')")->fetchColumn();
    $t['rewards'] = (int)$pdo->query("SELECT COUNT(*) FROM stamps_log WHERE note='صرف مكافأة'")->fetchColumn();
    $t['visits_total'] = (int)$pdo->query('SELECT COALESCE(SUM(visits),0) FROM customers')->fetchColumn();
    $t['recent'] = $pdo->query("SELECT staff,note,created_at FROM stamps_log ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    out(['ok'=>true,'stats'=>$t,'csrf'=>$_SESSION['csrf']]);
}

if ($act === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sajad-customers-'.date('Ymd').'.csv"');
    echo "\xEF\xBB\xBF"; // BOM لعرض العربية في Excel
    $out = fopen('php://output','w');
    fputcsv($out, ['الرقم','الأختام','الزيارات','آخر زيارة','تاريخ الإنشاء']);
    foreach ($pdo->query('SELECT code,stamps,visits,last_visit,created_at FROM customers ORDER BY id') as $r)
        fputcsv($out, [$r['code'],$r['stamps'],$r['visits'],$r['last_visit'],$r['created_at']]);
    exit;
}

fail('إجراء غير معروف', 404);
