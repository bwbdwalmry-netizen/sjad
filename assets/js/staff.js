// لوحة الموظف — مسح الباركود + أختام + إحصاءات (مع CSRF)
(function(){
const $ = id => document.getElementById(id);
let scanner = null, currentCode = null, csrf = '';

async function post(action, data={}, isGet=false){
  let url = 'api.php?action='+action;
  if (isGet) url += '&' + new URLSearchParams({csrf, ...data}).toString();
  const r = await fetch(url, {
    method: isGet?'GET':'POST',
    body: isGet?null:new URLSearchParams({csrf, ...data})
  });
  return r.json();
}

$('loginBtn').onclick = async () => {
  $('loginErr').textContent = '';
  const d = await post('staff_login', {username: $('user').value.trim(), pin: $('pin').value.trim()});
  if (d.ok) {
    csrf = d.csrf;
    $('loginBox').classList.add('hidden');
    $('panel').classList.remove('hidden');
    startScanner(); loadCustomers(); loadStats();
  } else $('loginErr').textContent = d.error || 'بيانات الدخول غير صحيحة';
};
$('pin').addEventListener('keydown', e => { if (e.key === 'Enter') $('loginBtn').click(); });
$('logoutBtn').onclick = async () => { await post('staff_logout'); location.reload(); };

function extractCode(text){
  const m = text.match(/SJ-\d{4}/i);
  return m ? m[0].toUpperCase() : text.trim().toUpperCase();
}
async function onScan(text){
  beep(); stopScanner();
  await lookup(extractCode(text));
}
function beep(){
  try {
    const ctx = new (window.AudioContext||window.webkitAudioContext)();
    const o = ctx.createOscillator(), g = ctx.createGain();
    o.connect(g); g.connect(ctx.destination);
    o.frequency.value = 880; g.gain.value = 0.06;
    o.start(); o.stop(ctx.currentTime + 0.12);
  } catch(e){}
}
async function startScanner(){
  if (typeof Html5Qrcode === 'undefined') return;
  scanner = new Html5Qrcode('scanner');
  try {
    await scanner.start({facingMode:'environment'}, {fps:10, qrbox:{width:220, height:120}}, onScan, ()=>{});
  } catch(e){
    $('scanner').innerHTML = '<p style="color:#9fd4b6;text-align:center;padding:30px 10px">تعذر فتح الكاميرا — أدخل رقم البطاقة يدويًا بالأسفل</p>';
  }
}
function stopScanner(){ if (scanner){ scanner.stop().catch(()=>{}).then(()=>scanner.clear()); scanner = null; } }

$('manualBtn').onclick = () => { const v = $('manualCode').value.trim(); if (v) lookup(extractCode(v)); };
$('manualCode').addEventListener('keydown', e => { if (e.key === 'Enter') $('manualBtn').click(); });

async function lookup(code){
  $('stampMsg').textContent = '';
  const d = await post('staff_scan', {code}, true);
  if (!d.ok) { $('custCard').classList.add('hidden'); alert(d.error || 'غير موجود'); startScanner(); return; }
  const c = d.customer;
  currentCode = c.code;
  $('custCard').classList.remove('hidden');
  $('custCode').textContent = c.code;
  $('cStamps').textContent = c.stamps + ' / ' + c.goal;
  $('cVisits').textContent = c.visits;
  $('cLast').textContent = c.last_visit || '—';
  $('rewardBadge').classList.toggle('hidden', !c.reward_due);
  $('cStampGrid').innerHTML = Array.from({length: c.goal}, (_,i)=>
    '<div class="stamp'+(i<c.stamps?' f':'')+'">'+(i<c.stamps?'ختم':'')+'</div>').join('');
  $('cLog').innerHTML = c.recent.length
    ? c.recent.map(l => '<li><span>'+(l.note||'ختم')+' — '+l.staff+'</span><span class="dim">'+l.created_at+'</span></li>').join('')
    : '<li><span class="dim">لا عمليات بعد</span></li>';
  $('custCard').scrollIntoView({behavior:'smooth'});
}

$('stampBtn').onclick = async () => {
  if (!currentCode) return;
  const btn = $('stampBtn'); btn.disabled = true;
  const d = await post('add_stamp', {code: currentCode});
  btn.disabled = false;
  if (d.ok) {
    $('stampMsg').textContent = d.reward_redeemed
      ? 'اكتملت البطاقة — صُرفت المكافأة وبدأت بطاقة جديدة 🎉'
      : 'تمت إضافة الختم ✓ ('+d.stamps+'/'+d.goal+')';
    setTimeout(() => { if (currentCode) lookup(currentCode); startScanner(); loadStats(); }, 900);
  } else alert(d.error || 'خطأ');
};

async function loadStats(){
  const d = await post('stats', {}, true);
  if (!d.ok) return;
  csrf = d.csrf || csrf;
  $('sCustomers').textContent = d.stats.customers;
  $('sToday').textContent = d.stats.stamps_today;
  $('sRewards').textContent = d.stats.rewards;
  $('sVisits').textContent = d.stats.visits_total;
}
async function loadCustomers(){
  const d = await post('customers', {}, true);
  if (d.ok) $('custList').innerHTML = d.customers.map(c =>
    '<li><span>'+c.code+'</span><span class="dim">'+c.stamps+' ختم • '+c.visits+' زيارة</span></li>').join('')
    || '<li><span class="dim">لا بطاقات بعد</span></li>';
}
})();
