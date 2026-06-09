(function(){
  function validCoord(lat, lng){
    lat = Number(lat); lng = Number(lng);
    return Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
  }
  function cleanAddressName(data, fallback){
    const a = (data && data.address) ? data.address : {};
    const first = a.suburb || a.village || a.neighbourhood || a.quarter || a.hamlet || a.city_district || a.town || a.city || a.county;
    const second = (first === a.city || first === a.town) ? (a.state_district || a.county) : (a.city || a.town || a.municipality || a.county || a.state_district);
    const third = a.state || a.region;
    const parts = [];
    [first, second, third].forEach(function(part){
      if(part && !parts.includes(part)) parts.push(part);
    });
    if(parts.length) return parts.join(', ');
    if(data && data.display_name){
      return data.display_name.split(',').slice(0, 4).map(function(p){return p.trim();}).filter(Boolean).join(', ');
    }
    return fallback || 'Nama lokasi belum tersedia';
  }
  async function reverseGeocode(lat, lng, fallback){
    if(!validCoord(lat, lng)) return fallback || 'Titik lokasi belum dipilih';
    const fixedLat = Number(lat).toFixed(7);
    const fixedLng = Number(lng).toFixed(7);
    const key = 'revibe_location_name_' + Number(lat).toFixed(4) + '_' + Number(lng).toFixed(4);
    try{
      const cached = localStorage.getItem(key);
      if(cached) return cached;
    }catch(e){}
    try{
      const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(fixedLat) + '&lon=' + encodeURIComponent(fixedLng) + '&zoom=14&addressdetails=1';
      const res = await fetch(url, {headers:{'Accept':'application/json'}});
      if(!res.ok) throw new Error('Reverse geocode gagal');
      const data = await res.json();
      const name = cleanAddressName(data, fallback);
      try{ localStorage.setItem(key, name); }catch(e){}
      return name;
    }catch(e){
      return fallback || 'Nama lokasi belum tersedia';
    }
  }
  function applyCoordinateLabels(root){
    (root || document).querySelectorAll('.revibe-coord-name').forEach(function(el){
      const lat = el.dataset.lat;
      const lng = el.dataset.lng;
      const fallback = el.dataset.fallback || el.textContent || '';
      if(!validCoord(lat, lng)){
        el.textContent = fallback || 'Titik lokasi belum dipilih';
        return;
      }
      el.classList.add('loading-location-name');
      el.textContent = 'Mencari nama lokasi...';
      reverseGeocode(lat, lng, fallback).then(function(name){
        el.textContent = name;
        el.title = Number(lat).toFixed(7) + ', ' + Number(lng).toFixed(7);
        el.classList.remove('loading-location-name');
      });
    });
  }
  window.ReVibeLocation = { validCoord: validCoord, reverseGeocode: reverseGeocode, applyCoordinateLabels: applyCoordinateLabels };
  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){ applyCoordinateLabels(document); });
  else applyCoordinateLabels(document);
})();
