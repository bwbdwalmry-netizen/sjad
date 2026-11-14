<?php
// واجهة API — جميع الطلبات ترجع JSON
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/db.php';
$cfg  = require __DIR__.'/config.php';
$pdo  = sajad_db();
$act  = $_REQUEST['action'] ?? '';

function out($d, int $code=200){ http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function fail($m, $c=400){ out(['ok'=>false,'error'=>$m], $c); }
function is_staff(): bool { return !empty($_SESSION['staff']); }

// ---------- العميل ----------
if ($act === 'new_customer') {
    // يُستدعى من new.php عند أول مسح لباركود المغسلة
    $code  = new_code($pdo);
    $token = bin2hex(random_bytes(24));
    $pdo->prepare('INSERT INTO customers(code,token) VALUES(?,?)')->execute([$code,$token]);
    out(['ok'=>true,'code'=>$code,'token'=>$token,'url'=>'index.php?c='.$code.'&t='.$token]);
}

if ($act === 'card') {
    $code = $_REQUEST['code'] ?? ''; $token = $_REQUEST['token'] ?? '';
    $q = $pdo->prepare('SELECT * FROM customers WHERE code=? AND token=?');
    $q->execute([$code,$token]); $c = $q->fetch(PDO::FETCH_ASSOC);
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
    $code=$_REQUEST['code']??''; $token=$_REQUEST['token']??'';
    $q=$pdo->prepare('SELECT id FROM customers WHERE code=? AND token=?');
    $q->execute([$code,$token]); $id=$q->fetchColumn();
    if (!$id) fail('غير مصرح', 403);
    $pdo->prepare('UPDATE customers SET notify_weekly=?, notify_geo=? WHERE id=?')
        ->execute([!empty($_POST['weekly'])?1:0, !empty($_POST['geo'])?1:0, $id]);
    out(['ok'=>true]);
}

if ($act === 'save_push_token') {
    $code=$_REQUEST['code']??''; $token=$_REQUEST['token']??''; $pt=$_POST['push_token']??'';
    $q=$pdo->prepare('SELECT id FROM customers WHERE code=? AND token=?');
    $q->execute([$code,$token]); $id=$q->fetchColumn();
    if ($id && $pt) { $pdo->prepare('UPDATE customers SET push_token=? WHERE id=?')->execute([$pt,$id]); }
    out(['ok'=>true]);
}

// فحص الموقع: إذا كان العميل داخل نطاق 500م أرسل إشعارًا (بفاصل زمني)
if ($act === 'geo_ping') {
    $code=$_REQUEST['code']??''; $token=$_REQUEST['token']??'';
    $lat=(float)($_POST['lat']??0); $lng=(float)($_POST['lng']??0);
    $q=$pdo->prepare('SELECT * FROM customers WHERE code=? AND token=?');
    $q->execute([$code,$token]); $c=$q->fetch(PDO::FETCH_ASSOC);
    if (!$c) fail('غير مصرح', 403);
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
    $u=$_POST['username']??''; $p=$_POST['pin']??'';
    foreach ($cfg['staff'] as $s) if ($s['username']===$u && $s['pin']===$p) {
        session_regenerate_id(true);
        $_SESSION['staff']=$u;
        out(['ok'=>true]);
    }
    fail('بيانات الدخول غير صحيحة', 401);
}
if ($act === 'staff_logout') { session_destroy(); out(['ok'=>true]); }
if (!is_staff() && in_array($act,['staff_scan','add_stamp','customers'],true)) fail('يجب تسجيل دخول الموظف', 401);

if ($act === 'staff_scan') {
    $code = trim($_REQUEST['code']??'');
    $q=$pdo->prepare('SELECT * FROM customers WHERE code=?');
    $q->execute([$code]); $c=$q->fetch(PDO::FETCH_ASSOC);
    if (!$c) fail('الباركود غير مسجل — تأكد من الرقم', 404);
    $logs=$pdo->prepare('SELECT staff,created_at FROM stamps_log WHERE customer_id=? ORDER BY id DESC LIMIT 8');
    $logs->execute([$c['id']]);
    out(['ok'=>true,'customer'=>[
        'code'=>$c['code'],'stamps'=>(int)$c['stamps'],'visits'=>(int)$c['visits'],
        'last_visit'=>$c['last_visit'],'created_at'=>$c['created_at'],
        'reward_due'=>(int)$c['stamps']>=(int)$cfg['goal_stamps'],'goal'=>(int)$cfg['goal_stamps'],
        'recent'=>$logs->fetchAll(PDO::FETCH_ASSOC),
    ]]);
}

if ($act === 'add_stamp') {
    $code = trim($_POST['code']??'');
    $q=$pdo->prepare('SELECT * FROM customers WHERE code=?');
    $q->execute([$code]); $c=$q->fetch(PDO::FETCH_ASSOC);
    if (!$c) fail('العميل غير موجود', 404);
    $stamps=(int)$c['stamps']+1; $reward=false;
    if ($stamps >= (int)$cfg['goal_stamps']) { $stamps=0; $reward=true; } // صرف المكافأة وبدء بطاقة جديدة
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

fail('إجراء غير معروف', 404);
