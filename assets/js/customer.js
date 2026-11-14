// بطاقة العميل — لا تسجيل دخول: البطاقة تُفتح بالرابط المحفوظ على الجهاز
(function(){
const params = new URLSearchParams(location.search);
const code  = params.get('c');
let   token = params.get('t') || '';

// حفظ التوكن على الجهاز لأول مرة — بعدها لا حاجة للرابط الكامل
if (code && token) localStorage.setItem('sajad_t_'+code, token);
else if (code) token = localStorage.getItem('sajad_t_'+code) || '';

const $ = id => document.getElementById(id);

if (!code || !token) { $('noCard').classList.remove('hidden'); return; }

async function api(action, extra={}){
  const body = new URLSearchParams({code, token, ...extra});
  const r = await fetch('api.php?action='+action, {method: extra._get?'GET':'POST', body: extra._get?null:body});
  return r.json();
}

function renderStamps(n, goal){
  $('stamps').innerHTML = Array.from({length: goal}, (_,i)=>
    '<div class="stamp'+(i<n?' f':'')+'">'+(i<n?'ختم':'')+'</div>').join('');
  $('stampsNum').textContent = n;
}

async function load(){
  const d = await api('card', {_get:1});
  if (!d.ok) { $('noCard').classList.remove('hidden'); return; }
  const c = d.card;
  renderStamps(c.stamps, c.goal);
  $('rewardBanner').classList.toggle('hidden', !c.reward_due);
  $('progressMsg').textContent = c.reward_due ? '' :
    `تبقّى ${c.goal - c.stamps} ${c.goal - c.stamps === 1 ? 'ختم' : 'أختام'} للحصول على المكافأة`;
  $('visitsNum').textContent = c.visits;
  $('lastVisit').textContent = c.last_visit || '—';
  $('codeNum').textContent = c.code;
  $('geoToggle').checked = c.notify_geo;
  $('weeklyToggle').checked = c.notify_weekly;
  $('cardView').classList.remove('hidden');
  try { JsBarcode('#barcode', c.code, {format:'CODE128', displayValue:false, margin:0, height:64, lineColor:'#12241b'}); }
  catch(e){ $('barcode').outerHTML = '<div class="code-num" style="font-size:18px">'+c.code+'</div>'; }
}

// حفظ تفضيلات الإشعارات
async function savePrefs(){
  await api('prefs', {weekly: $('weeklyToggle').checked ? 1 : 0, geo: $('geoToggle').checked ? 1 : 0});
}
$('geoToggle').addEventListener('change', savePrefs);
$('weeklyToggle').addEventListener('change', savePrefs);

// تسجيل توكن الإشعارات (OneSignal) وربطه بالبطاقة
async function registerPush(){
  if (!window.OneSignalDeferred || !window.SAJAD.onesignal_app_id) return;
  OneSignalDeferred.push(async OS => {
    try {
      await OS.login(code); // external_id = رقم البطاقة
      const sub = await OS.User.PushSubscription;
      const id = sub && sub.id;
      if (id) await api('save_push_token', {push_token: id});
    } catch(e){}
  });
}

// فحص الموقع لحظيًا عند فتح البطاقة — داخل نطاق 500م يُرسل إشعار (بفاصل زمني من الخادم)
function geoCheck(){
  if (!navigator.geolocation) return;
  $('geoStatus').textContent = 'جارٍ فحص الموقع للتذكير عند الاقتراب من المغسلة…';
  navigator.geolocation.getCurrentPosition(async pos => {
    const d = await api('geo_ping', {lat: pos.coords.latitude, lng: pos.coords.longitude});
    if (d.ok) {
      $('geoStatus').textContent = d.inside
        ? 'أنت الآن داخل نطاق المغسلة (500م) ✓' + (d.notified ? ' — أُرسل لك إشعار الترحيب' : '')
        : `أنت على بعد ${(d.distance_m/1000).toFixed(1)} كم من المغسلة`;
    }
  }, () => { $('geoStatus').textContent = 'فعّل الموقع من إعدادات المتصفح ليصلك تذكير الاقتراب.'; },
     {timeout: 8000, maximumAge: 600000});
}

if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(()=>{});
load().then(() => { registerPush(); if ($('geoToggle').checked) geoCheck(); });
})();
