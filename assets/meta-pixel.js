/* CUP SEAL – Meta (Facebook) Pixel, csak marketing-hozzájárulás után.
   A suti.js után kell betölteni. Oldalspecifikus esemény: <html data-pixel-esemeny="Lead">. */
(function () {
  var PIXEL_ID = '1638594434599736';
  var betoltve = false;

  function betolt() {
    if (betoltve) { window.fbq('consent', 'grant'); return; }
    betoltve = true;
    /* eslint-disable */
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    /* eslint-enable */
    window.fbq('consent', 'grant');
    window.fbq('init', PIXEL_ID);
    window.fbq('track', 'PageView');
    var esemeny = document.documentElement.getAttribute('data-pixel-esemeny');
    if (esemeny) window.fbq('track', esemeny);
  }

  document.addEventListener('cupseal:suti', function (e) {
    if (e.detail && e.detail.marketing) betolt();
    else if (betoltve) window.fbq('consent', 'revoke');
  });

  if (window.cupsealSuti && window.cupsealSuti.engedelyezett('marketing')) betolt();
})();
