// Service Worker — يتيح إشعارات الدفع ويعمل كتطبيق مثبّت (PWA)
self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(clients.claim()));
// OneSignal يتولى إظهار الإشعارات تلقائيًا عبر واجهته — لا حاجة لمعالج push هنا
self.addEventListener('fetch', e => {
  e.respondWith(fetch(e.request).catch(() => caches.match(e.request)));
});
