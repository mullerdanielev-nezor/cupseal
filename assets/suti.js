/* CUP SEAL – süti- és hozzájárulás-kezelő.
   A választást a böngésző localStorage-ában tároljuk ("cupseal-suti"), 12 hónapig.
   Statisztikai vagy marketingeszközt csak hozzájárulás után szabad betölteni:
     if (window.cupsealSuti.engedelyezett('statisztika')) { ... }
     document.addEventListener('cupseal:suti', e => { e.detail.statisztika ... });
*/
(function () {
  var KULCS = 'cupseal-suti', VERZIO = 1, ERVENYES_NAP = 365;
  var KATEGORIAK = [
    { id: 'szukseges', nev: 'Szükséges', leiras: 'Az oldal működéséhez és a süti-választásod megjegyzéséhez kell. Nem kapcsolható ki.', kotelezo: true },
    { id: 'statisztika', nev: 'Statisztika', leiras: 'Névtelen látogatottsági mérés. Jelenleg nem használunk ilyet; ha bevezetjük, csak a hozzájárulásoddal fut.' },
    { id: 'marketing', nev: 'Marketing', leiras: 'Hirdetések mérése és személyre szabása. Jelenleg nem használunk ilyet; ha bevezetjük, csak a hozzájárulásoddal fut.' }
  ];

  function olvas() {
    try {
      var v = JSON.parse(localStorage.getItem(KULCS));
      if (!v || v.v !== VERZIO) return null;
      if (Date.now() - v.ido > ERVENYES_NAP * 864e5) return null;
      return v;
    } catch (e) { return null; }
  }
  function ment(valasztas) {
    var v = { v: VERZIO, ido: Date.now(), statisztika: !!valasztas.statisztika, marketing: !!valasztas.marketing };
    try { localStorage.setItem(KULCS, JSON.stringify(v)); } catch (e) {}
    allapot = v;
    document.dispatchEvent(new CustomEvent('cupseal:suti', { detail: v }));
    bezar();
  }

  var allapot = olvas(), sav = null;

  var CSS = '' +
    '.suti{position:fixed;z-index:60;left:12px;right:12px;bottom:12px;max-width:560px;margin-left:auto;background:#fff;color:#101014;border:2px solid #101014;border-radius:24px;padding:20px;box-shadow:6px 6px 0 #101014;font-family:Archivo,system-ui,sans-serif;font-size:15px;line-height:1.45}' +
    '.suti h2{font-family:Anton,Archivo,sans-serif;font-weight:400;text-transform:uppercase;font-size:28px;line-height:1.1;margin:0 0 8px;display:flex;align-items:center;gap:10px}' +
    '.suti h2::before{content:"";width:18px;height:18px;border-radius:50%;background:#FF8A00;box-shadow:10px 0 0 -2px #F0540A}' +
    '.suti p{margin:0 0 14px}.suti a{color:inherit;font-weight:700}' +
    '.suti-gombok{display:flex;flex-wrap:wrap;gap:8px}' +
    '.suti button{font:inherit;font-weight:800;font-size:15px;min-height:46px;padding:8px 18px;border:2px solid #101014;border-radius:99px;background:#fff;color:#101014;cursor:pointer;flex:1 1 auto}' +
    '.suti button.fo{background:#FF8A00}.suti button:focus-visible{outline:4px solid #FF8A00;outline-offset:2px}' +
    '.suti-kat{display:none;margin:0 0 14px;border-top:2px solid #E4E4E8}.suti.nyitott .suti-kat{display:block}' +
    '.suti-kat label{display:grid;grid-template-columns:1fr auto;gap:2px 14px;align-items:center;padding:10px 0;border-bottom:2px solid #E4E4E8;cursor:pointer}' +
    '.suti-kat b{font-weight:800}.suti-kat small{grid-column:1;color:#55555c;font-size:13px}' +
    '.suti-kat input{grid-row:1/3;grid-column:2;width:22px;height:22px;accent-color:#F0540A}' +
    '.suti .csak-nyitott{display:none}.suti.nyitott .csak-nyitott{display:inline-flex;justify-content:center}.suti.nyitott .csak-zart{display:none}' +
    '@media (min-width:700px){.suti{left:auto;right:24px;bottom:24px}}';

  function stilus() {
    if (document.getElementById('suti-css')) return;
    var s = document.createElement('style'); s.id = 'suti-css'; s.textContent = CSS;
    document.head.appendChild(s);
  }

  function megnyit(reszletes) {
    stilus();
    if (sav) bezar();
    var link = document.documentElement.getAttribute('data-adatkezeles') || 'adatkezeles.html';
    sav = document.createElement('section');
    sav.className = 'suti' + (reszletes ? ' nyitott' : '');
    sav.setAttribute('role', 'dialog');
    sav.setAttribute('aria-labelledby', 'suti-cim');
    var kat = KATEGORIAK.map(function (k) {
      var be = k.kotelezo || (allapot && allapot[k.id]);
      return '<label><b>' + k.nev + '</b><input type="checkbox" name="' + k.id + '"' + (be ? ' checked' : '') + (k.kotelezo ? ' disabled' : '') + '><small>' + k.leiras + '</small></label>';
    }).join('');
    sav.innerHTML =
      '<h2 id="suti-cim">Sütik</h2>' +
      '<p>Az oldal működéséhez szükséges tárolást használjuk. Statisztikai vagy marketingsütit csak akkor kapcsolunk be, ha hozzájárulsz. Részletek az <a href="' + link + '#sutik">adatkezelési tájékoztatóban</a>.</p>' +
      '<div class="suti-kat">' + kat + '</div>' +
      '<div class="suti-gombok">' +
        '<button type="button" data-a="mind" class="fo">Elfogadom mind</button>' +
        '<button type="button" data-a="szukseges">Csak a szükségesek</button>' +
        '<button type="button" data-a="beallitas" class="csak-zart">Beállítások</button>' +
        '<button type="button" data-a="mentes" class="csak-nyitott">Választás mentése</button>' +
      '</div>';
    sav.addEventListener('click', function (e) {
      var a = e.target.getAttribute && e.target.getAttribute('data-a');
      if (!a) return;
      if (a === 'mind') ment({ statisztika: true, marketing: true });
      else if (a === 'szukseges') ment({});
      else if (a === 'beallitas') sav.classList.add('nyitott');
      else if (a === 'mentes') ment({
        statisztika: sav.querySelector('[name=statisztika]').checked,
        marketing: sav.querySelector('[name=marketing]').checked
      });
    });
    document.body.appendChild(sav);
    var elso = sav.querySelector('button'); if (elso) elso.focus({ preventScroll: true });
  }
  function bezar() { if (sav) { sav.remove(); sav = null; } }

  window.cupsealSuti = {
    engedelyezett: function (kat) { return kat === 'szukseges' || !!(allapot && allapot[kat]); },
    beallitasok: function () { megnyit(true); }
  };

  document.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('[data-suti-beallitas]');
    if (t) { e.preventDefault(); megnyit(true); }
  });

  function indul() { if (!allapot) megnyit(false); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', indul); else indul();
})();
