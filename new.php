<?php
// الصفحة التي يشير إليها باركود المغسلة الثابت
// أول مسح: ينشئ حسابًا تلقائيًا ويحفظه على الجهاز — لا تسجيل ولا كلمة مرور
require __DIR__.'/db.php';
$pdo = sajad_db();
$code  = new_code($pdo);
$token = bin2hex(random_bytes(24));
$pdo->prepare('INSERT INTO customers(code,token) VALUES(?,?)')->execute([$code,$token]);
header('Location: index.php?c='.urlencode($code).'&t='.$token);
exit;
