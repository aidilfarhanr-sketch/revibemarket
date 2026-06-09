(function(){
  'use strict';
  const loader = document.getElementById('rv-page-loader');
  if(!loader) return;
  let hideTimer = null;
  const hide = () => {
    if(loader.classList.contains('is-hidden')) return;
    loader.classList.add('is-hiding');
    window.setTimeout(() => { loader.classList.add('is-hidden'); loader.setAttribute('aria-hidden','true'); }, 430);
  };
  const show = (text) => {
    const p = loader.querySelector('p');
    if(text && p) p.textContent = text;
    loader.classList.remove('is-hidden','is-hiding');
    loader.removeAttribute('aria-hidden');
    clearTimeout(hideTimer);
    hideTimer = window.setTimeout(hide, 5000);
  };
  window.ReVibeLoader = {show, hide};
  window.addEventListener('load', () => window.setTimeout(hide, 250));
  window.addEventListener('pageshow', () => window.setTimeout(hide, 120));
  window.setTimeout(hide, 5000);

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if(form && form.matches('form') && !form.hasAttribute('data-no-loader')) show('Memproses permintaan ReVibe...');
  }, true);

  document.addEventListener('click', (e) => {
    const a = e.target.closest && e.target.closest('a[href]');
    if(!a || a.hasAttribute('data-no-loader')) return;
    const href = a.getAttribute('href') || '';
    if(href.startsWith('#') || href.startsWith('javascript:') || a.target === '_blank') return;
    if(/checkout|payment|seller|admin|order|withdraw|login|register|cart/i.test(href)) show('Loading ReVibe Market...');
  }, true);

  if(window.fetch){
    const originalFetch = window.fetch;
    window.fetch = function(){
      show('Sinkronisasi data ReVibe...');
      return originalFetch.apply(this, arguments).finally(() => window.setTimeout(hide, 200));
    };
  }
})();

(function(){
  'use strict';
  let touchReady = false;
  function markTouch(){
    if(touchReady) return;
    touchReady = true;
    document.documentElement.classList.add('rv-touch-ready');
  }
  window.addEventListener('touchstart', markTouch, {passive:true, once:true});

  function refreshLeafletMap(){
    window.setTimeout(function(){
      try{
        if(window.revibeMap && typeof window.revibeMap.invalidateSize === 'function'){
          window.revibeMap.invalidateSize(true);
        }
      }catch(e){}
    }, 120);
    window.setTimeout(function(){
      try{
        if(window.revibeMap && typeof window.revibeMap.invalidateSize === 'function'){
          window.revibeMap.invalidateSize(true);
        }
      }catch(e){}
    }, 420);
  }

  document.addEventListener('click', function(e){
    const target = e.target && e.target.closest ? e.target.closest('#openCoordinatePicker,.open-coordinate-picker,[data-open-coordinate-picker],[data-open-map],[data-map-modal-open]') : null;
    if(target) refreshLeafletMap();
  }, true);

  function observeMapModal(){
    if(!window.MutationObserver) return;
    const observer = new MutationObserver(function(mutations){
      for(const mutation of mutations){
        if(mutation.type === 'attributes' && mutation.target && mutation.target.classList && mutation.target.classList.contains('coordinate-picker-modal') && mutation.target.classList.contains('active')){
          refreshLeafletMap();
        }
      }
    });
    document.querySelectorAll('.coordinate-picker-modal').forEach(function(modal){
      observer.observe(modal, {attributes:true, attributeFilter:['class','style','aria-hidden']});
    });
  }

  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', observeMapModal);
  else observeMapModal();

  window.addEventListener('resize', refreshLeafletMap, {passive:true});
  window.addEventListener('orientationchange', function(){ window.setTimeout(refreshLeafletMap, 360); }, {passive:true});
})();

