<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');

function upload_profile_photo($file) {
    return revibe_safe_upload($file, 'profile', ['prefix'=>'profile','max_size'=>3*1024*1024]);
}

$user_id=(int)$_SESSION['user_id'];
ensure_seller_profile($conn,$user_id);
$user=mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM users WHERE id=$user_id LIMIT 1"));
$seller=db_table_exists($conn,'sellers')?mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM sellers WHERE user_id=$user_id LIMIT 1")):null;

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $first=trim($_POST['first_name']??'');
    $last=trim($_POST['last_name']??'');
    $phone=trim($_POST['phone']??'');
    $birthdate=trim($_POST['birthdate']??'');
    $bio=trim($_POST['bio']??'');
    $city=trim($_POST['city']??'');
    $address_region=trim($_POST['address_region']??'');
    $street_address=trim($_POST['street_address']??'');
    $address_detail=trim($_POST['address_detail']??'');
    $address_label=trim($_POST['address_label']??'Rumah');
    $is_main_address=isset($_POST['is_main_address']) ? 1 : 0;
    $is_store_address=isset($_POST['is_store_address']) ? 1 : 0;
    $is_return_address=isset($_POST['is_return_address']) ? 1 : 0;
    $latitude=revibe_float_or_null($_POST['latitude']??null);
    $longitude=revibe_float_or_null($_POST['longitude']??null);
    $store=trim($_POST['store_name']??'');
    $desc=trim($_POST['store_description']??'');

    $addressParts = array_filter([$street_address, $address_detail, $address_region], fn($v) => trim((string)$v) !== '');
    $address=trim($_POST['address'] ?? implode(', ', $addressParts));
    if ($city === '' && $address_region !== '') $city = $address_region;

    $new_photo = null;
    if (!empty($_FILES['profile_photo']['name'])) {
        $new_photo = upload_profile_photo($_FILES['profile_photo']);
        if (!$new_photo) { $_SESSION['error']='Foto profil gagal diupload. Gunakan JPG/PNG/WebP maksimal 3MB.'; header('Location: edit_profile.php'); exit; }
    }
    if($first==='' || $last===''){ $_SESSION['error']='Nama depan dan nama belakang wajib diisi.'; header('Location: edit_profile.php'); exit; }
    if(($is_store_address || $is_main_address) && !revibe_valid_coordinate($latitude,$longitude)){
        $_SESSION['error']='Titik lokasi wajib valid untuk alamat utama/toko. Klik Pilih Titik di Peta atau Ambil Titik Lokasi Saya.';
        header('Location: edit_profile.php#alamat'); exit;
    }

    $sets=[]; $types=''; $vals=[];
    foreach(['first_name'=>$first,'last_name'=>$last,'address'=>$address] as $col=>$val){ $sets[]="$col=?"; $types.='s'; $vals[]=$val; }
    $optional = [
        'phone'=>[$phone,'s'],
        'birthdate'=>[$birthdate ?: null,'s'],
        'bio'=>[$bio,'s'],
        'city'=>[$city,'s'],
        'address_region'=>[$address_region,'s'],
        'street_address'=>[$street_address,'s'],
        'address_detail'=>[$address_detail,'s'],
        'address_label'=>[$address_label,'s'],
        'is_main_address'=>[$is_main_address,'i'],
        'is_store_address'=>[$is_store_address,'i'],
        'is_return_address'=>[$is_return_address,'i'],
        'latitude'=>[$latitude,'d'],
        'longitude'=>[$longitude,'d'],
    ];
    foreach($optional as $col=>$pack){ if(db_column_exists($conn,'users',$col)){ $sets[]="$col=?"; $types.=$pack[1]; $vals[]=$pack[0]; } }
    if($new_photo && db_column_exists($conn,'users','profile_photo')){ $sets[]="profile_photo=?"; $types.='s'; $vals[]=$new_photo; }
    $sql="UPDATE users SET ".implode(', ',$sets)." WHERE id=?"; $types.='i'; $vals[]=$user_id;
    $stmt=mysqli_prepare($conn,$sql); mysqli_stmt_bind_param($stmt,$types,...$vals); mysqli_stmt_execute($stmt);

    if(db_table_exists($conn,'sellers')){
        if($store==='') $store=$first.' '.$last.' Store';
        $storeSafe=mysqli_real_escape_string($conn,$store); $descSafe=mysqli_real_escape_string($conn,$desc);
        mysqli_query($conn,"INSERT INTO sellers (user_id, store_name, store_description, verification_status) VALUES ($user_id, '$storeSafe', '$descSafe', 'verified') ON DUPLICATE KEY UPDATE store_name=VALUES(store_name), store_description=VALUES(store_description)");
    }
    $_SESSION['user_name']=$first.' '.$last;
    $_SESSION['success']='Profil dan alamat berhasil diperbarui. Titik koordinat ini akan dipakai untuk jual barang dan checkout.';
    header('Location: profile.php'); exit;
}

$fullAddress = revibe_user_full_address($user);
$addressLabel = revibe_address_label($user);
$lat = $user['latitude'] ?? '';
$lng = $user['longitude'] ?? '';
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Edit Profil & Alamat - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar"><a href="profile.php" class="btn">← Profil</a><a href="../index.php" class="btn">Beranda</a></div>
<div class="page-shell narrow address-page-shell"><div class="page-header address-title"><h1>Ubah Profil & Alamat</h1><p>Alamat dan titik koordinat ini dipakai otomatis untuk checkout, ongkir, lokasi toko, dan lokasi produk yang kamu jual.</p></div>
<?php if(isset($_SESSION['error'])): ?><div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="form-card profile-edit-card address-edit-screen"><?= csrf_field() ?>
    <div class="profile-photo-edit">
        <div class="profile-photo-preview">
            <?php if(!empty($user['profile_photo'])): ?>
                <img src="<?= e(revibe_public_file_url($user['profile_photo'], 'profile')) ?>" alt="Foto Profil">
            <?php else: ?>
                <div class="avatar-letter huge"><?= e(strtoupper(substr($user['first_name'] ?? 'R',0,1))) ?></div>
            <?php endif; ?>
        </div>
        <div>
            <label>Foto Profil</label>
            <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">
            <p class="muted">Setelah upload, foto ini otomatis menjadi foto profil di navbar dan chat.</p>
        </div>
    </div>

    <div class="autofill-card">
        <div class="autofill-icon">✧</div>
        <div class="autofill-body">
            <h3>Tempel dan Isi Otomatis</h3>
            <p>Tempel data alamat lengkap, lalu klik “Isi” untuk mencoba memecah nama, nomor HP, provinsi/kota, jalan, dan detail.</p>
            <textarea id="pasteAddress" rows="3" placeholder="Contoh: Aidil, 0895..., BANTEN KOTA TANGERANG PINANG 15142, Perumahan..., Blok 74 no 3"></textarea>
            <button class="btn secondary" type="button" id="autofillBtn">Isi</button>
        </div>
    </div>

    <section class="address-fields-card" id="alamat">
        <h2>Alamat</h2>
        <div class="form-two"><div><label>Nama Depan</label><input id="firstName" name="first_name" value="<?= e($user['first_name']??'') ?>" required></div><div><label>Nama Belakang</label><input id="lastName" name="last_name" value="<?= e($user['last_name']??'') ?>" required></div></div>
        <div class="form-two"><div><label>Nomor Telepon</label><input id="phoneInput" name="phone" value="<?= e($user['phone']??'') ?>" placeholder="(+62) 895-xxxx-xxxx"></div><div><label>Tanggal Lahir</label><input type="date" name="birthdate" value="<?= e($user['birthdate']??'') ?>"></div></div>
        <label>Provinsi, Kota, Kecamatan, Kode Pos</label>
        <textarea id="regionInput" name="address_region" rows="3" placeholder="Contoh: BANTEN, KOTA TANGERANG, PINANG (PENANG), 15142"><?= e($user['address_region'] ?? ($user['city'] ?? '')) ?></textarea>
        <label>Nama Jalan, Gedung, No. Rumah</label>
        <textarea id="streetInput" name="street_address" rows="3" placeholder="Contoh: Perumahan Banjar Wijaya Cluster Krisan Blok.74.no.3"><?= e($user['street_address'] ?? ($user['address'] ?? '')) ?></textarea>
        <label>Detail Lainnya <span class="muted">(Blok / Unit No., Patokan)</span></label>
        <input id="detailInput" name="address_detail" value="<?= e($user['address_detail']??'') ?>" placeholder="Contoh: Blok 74 no 3, pagar hitam, dekat masjid">
        <input type="hidden" name="address" id="fullAddressHidden" value="<?= e($fullAddress) ?>">
    </section>

    <section class="address-map-card">
        <div class="map-preview" id="mapPreview">
            <div class="map-address-card"><span>Alamat yang Dipilih</span><strong id="selectedAddressText"><?= e($fullAddress ?: 'Alamat belum lengkap') ?></strong></div>
            <div class="map-bubble">Alamatmu di sini</div>
            <div class="map-pin"></div>
            <div class="map-grid-lines"></div>
        </div>
        <div class="coordinate-card profile-coordinate-card">
            <label>Titik Lokasi Alamat/Toko</label>
            <div class="coordinate-name-preview">
                <span>Nama titik dari koordinat</span>
                <strong id="profileCoordName" class="revibe-coord-name" data-lat="<?= e($lat) ?>" data-lng="<?= e($lng) ?>" data-fallback="<?= e($fullAddress ?: 'Titik lokasi belum dipilih') ?>"><?= e($fullAddress ?: 'Titik lokasi belum dipilih') ?></strong>
            </div>
            <input type="hidden" name="latitude" id="profileLat" value="<?= e($lat) ?>">
            <input type="hidden" name="longitude" id="profileLng" value="<?= e($lng) ?>">
            <div class="address-map-actions">
                <button class="btn secondary" type="button" id="profileUseLocation">📍 Ambil Titik Lokasi Saya</button>
                <button class="btn primary" type="button" id="openCoordinatePicker">Pilih Titik di Peta</button>
                <a class="btn secondary" id="openMapLink" href="https://maps.google.com/?q=<?= e(($lat ?: '0').','.($lng ?: '0')) ?>" target="_blank">Buka Maps</a>
            </div>
            <p class="muted">Titik ini dipakai sebagai lokasi penjual saat kamu menjual barang. Saat checkout, titik pembeli juga otomatis diambil dari alamat profil pembeli.</p>
        </div>
    </section>

    <div class="coordinate-picker-modal" id="coordinatePicker" aria-hidden="true">
        <div class="coordinate-picker-topbar">
            <button type="button" class="map-back-btn" id="closeCoordinatePicker">←</button>
            <strong>Pilih Titik Alamat</strong>
            <span></span>
        </div>
        <div class="coordinate-selected-card">
            <span>Alamat yang Dipilih</span>
            <strong id="pickerAddressText"><?= e($fullAddress ?: 'Alamat belum lengkap') ?></strong>
            <small id="pickerCoordText" class="revibe-coord-name" data-lat="<?= e($lat) ?>" data-lng="<?= e($lng) ?>" data-fallback="<?= e($fullAddress ?: 'Titik lokasi belum dipilih') ?>"><?= e($fullAddress ?: 'Titik lokasi belum dipilih') ?></small>
        </div>
        <div id="leafletAddressMap" class="leaflet-address-map">
            <div class="map-fallback-note">Memuat peta interaktif...</div>
        </div>
        <button type="button" class="map-my-location" id="pickerUseLocation" title="Ambil lokasi saya">⌖</button>
        <div class="map-center-badge">Alamatmu di sini</div>
        <div class="map-picker-footer">
            <button type="button" class="btn secondary" id="cancelCoordinatePicker">Batal</button>
            <button type="button" class="btn primary" id="confirmCoordinatePicker">Konfirmasi</button>
        </div>
    </div>

    <section class="address-switch-card">
        <label class="switch-row"><span>Atur sebagai Alamat Utama</span><input type="checkbox" name="is_main_address" <?= !empty($user['is_main_address']) ? 'checked' : 'checked' ?>><i></i></label>
        <label class="switch-row"><span>Atur sebagai Alamat Toko</span><input type="checkbox" name="is_store_address" <?= !empty($user['is_store_address']) ? 'checked' : 'checked' ?>><i></i></label>
        <label class="switch-row"><span>Atur sebagai Alamat Pengembalian</span><input type="checkbox" name="is_return_address" <?= !empty($user['is_return_address']) ? 'checked' : '' ?>><i></i></label>
        <div class="address-label-row"><span>Tandai Sebagai:</span><label><input type="radio" name="address_label" value="Kantor" <?= ($addressLabel==='Kantor')?'checked':'' ?>> Kantor</label><label><input type="radio" name="address_label" value="Rumah" <?= ($addressLabel!=='Kantor')?'checked':'' ?>> Rumah</label></div>
    </section>

    <section class="store-profile-card">
        <h2>Identitas Toko</h2>
        <label>Nama Toko</label><input name="store_name" value="<?= e($seller['store_name']??(($user['first_name']??'ReVibe').' Store')) ?>" placeholder="Nama toko kamu">
        <label>Deskripsi Toko</label><textarea name="store_description" rows="3" placeholder="Ceritakan jenis barang yang biasa kamu jual."><?= e($seller['store_description']??'') ?></textarea>
        <label>Bio Singkat</label><textarea name="bio" rows="3" placeholder="Contoh: Suka jual barang preloved berkualitas dan siap COD area sekitar."><?= e($user['bio']??'') ?></textarea>
        <label>Kota Tampilan</label><input name="city" value="<?= e($user['city']??'') ?>" placeholder="Contoh: Tangerang / Palembang">
    </section>

    <div class="address-bottom-actions"><button class="btn danger" type="button" id="clearAddressBtn">Hapus Alamat</button><button class="btn primary" type="submit">Simpan</button></div>
</form></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/revibe-location.js"></script>
<script>
function buildFullAddress(){
    const region=document.getElementById('regionInput')?.value.trim()||'';
    const street=document.getElementById('streetInput')?.value.trim()||'';
    const detail=document.getElementById('detailInput')?.value.trim()||'';
    const full=[street, detail, region].filter(Boolean).join(', ');
    document.getElementById('fullAddressHidden').value=full;
    document.getElementById('selectedAddressText').textContent=full||'Alamat belum lengkap';
    if(typeof syncPickerAddress==='function') syncPickerAddress();
}
['regionInput','streetInput','detailInput'].forEach(id=>document.getElementById(id)?.addEventListener('input', buildFullAddress));
document.getElementById('autofillBtn')?.addEventListener('click',()=>{
    const raw=(document.getElementById('pasteAddress')?.value||'').replace(/\n+/g, ', ');
    if(!raw.trim()){ alert('Tempel data alamat terlebih dahulu.'); return; }
    const phone=raw.match(/(\+?62|0)\s?\d[\d\s\-]{7,}/);
    if(phone) document.getElementById('phoneInput').value=phone[0].trim();
    const parts=raw.split(',').map(p=>p.trim()).filter(Boolean);
    if(parts[0] && !/\d/.test(parts[0])){
        const names=parts[0].split(/\s+/); document.getElementById('firstName').value=names.shift()||''; document.getElementById('lastName').value=names.join(' ')||document.getElementById('lastName').value;
    }
    const last=parts.slice(-1)[0]||'';
    if(last) document.getElementById('detailInput').value=last;
    const regionPart=parts.find(p=>/PROV|KOTA|KAB|KEC|BANTEN|JAWA|SUMATERA|PALEMBANG|TANGERANG|\d{5}/i.test(p));
    if(regionPart) document.getElementById('regionInput').value=regionPart;
    const streetPart=parts.find(p=>/jalan|jl\.|perum|blok|cluster|rt|rw|no\.?/i.test(p));
    if(streetPart) document.getElementById('streetInput').value=streetPart;
    buildFullAddress();
});
document.getElementById('clearAddressBtn')?.addEventListener('click',()=>{
    ['regionInput','streetInput','detailInput','profileLat','profileLng'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
    buildFullAddress();
});
function updateMapLink(){
    const lat=document.getElementById('profileLat')?.value||'0', lng=document.getElementById('profileLng')?.value||'0';
    const link=document.getElementById('openMapLink'); if(link) link.href='https://maps.google.com/?q='+encodeURIComponent(lat+','+lng);
}
document.getElementById('profileLat')?.addEventListener('input', updateMapLink);
document.getElementById('profileLng')?.addEventListener('input', updateMapLink);
function setProfileCoordinate(lat, lng, syncMainCard=true){
    if(!Number.isFinite(Number(lat)) || !Number.isFinite(Number(lng))) return;
    document.getElementById('profileLat').value = Number(lat).toFixed(7);
    document.getElementById('profileLng').value = Number(lng).toFixed(7);
    updateMapLink();
    resolveAddressFromCoordinate(Number(lat), Number(lng), syncMainCard);
    if(window.revibeMapMarker && window.revibeMap){
        const point=[Number(lat), Number(lng)];
        window.revibeMapMarker.setLatLng(point);
        window.revibeMap.setView(point, Math.max(window.revibeMap.getZoom(), 16));
    }
}
function getProfileCoordinate(){
    const lat=parseFloat(document.getElementById('profileLat')?.value||'');
    const lng=parseFloat(document.getElementById('profileLng')?.value||'');
    if(Number.isFinite(lat) && Number.isFinite(lng)) return [lat,lng];
    return null;
}
let revibeAddressLookupTimer=null;
let revibeAddressLookupToken=0;
let revibeLastResolvedAddress='';

function setAddressFromCoordinateName(name, lat, lng, syncMainCard=false){
    const clean=(name||'').trim() || 'Nama lokasi belum tersedia';
    const picker=document.getElementById('pickerAddressText');
    const pickerSmall=document.getElementById('pickerCoordText');
    const profileName=document.getElementById('profileCoordName');
    if(picker) picker.textContent=clean;
    [pickerSmall, profileName].forEach(el=>{
        if(!el) return;
        el.dataset.lat=Number(lat).toFixed(7);
        el.dataset.lng=Number(lng).toFixed(7);
        el.dataset.fallback=clean;
        el.textContent=clean;
        el.title=Number(lat).toFixed(7)+', '+Number(lng).toFixed(7);
        el.classList.remove('loading-location-name');
    });
    revibeLastResolvedAddress=clean;

    if(syncMainCard){
        const selected=document.getElementById('selectedAddressText');
        const hidden=document.getElementById('fullAddressHidden');
        const region=document.getElementById('regionInput');
        const street=document.getElementById('streetInput');
        const detail=document.getElementById('detailInput');
        const manualFull=[street?.value.trim()||'', detail?.value.trim()||'', region?.value.trim()||''].filter(Boolean).join(', ');
        if(selected) selected.textContent = manualFull || clean;
        if(hidden) hidden.value = manualFull || clean;
        if(region && !region.value.trim()) region.value = clean;
    }
}

function updateCoordinateNameTargets(lat, lng){
    ['pickerCoordText','profileCoordName'].forEach(id=>{
        const el=document.getElementById(id);
        if(!el) return;
        el.dataset.lat=Number(lat).toFixed(7);
        el.dataset.lng=Number(lng).toFixed(7);
        el.textContent='Mencari nama lokasi...';
        el.classList.add('loading-location-name');
    });
    const picker=document.getElementById('pickerAddressText');
    if(picker) picker.textContent='Mencari alamat dari titik yang dipilih...';
}

function resolveAddressFromCoordinate(lat, lng, syncMainCard=false){
    if(!Number.isFinite(Number(lat)) || !Number.isFinite(Number(lng))) return;
    const token=++revibeAddressLookupToken;
    updateCoordinateNameTargets(lat,lng);
    const fallback=document.getElementById('fullAddressHidden')?.value || 'Nama lokasi belum tersedia';
    if(window.ReVibeLocation && typeof window.ReVibeLocation.reverseGeocode === 'function'){
        window.ReVibeLocation.reverseGeocode(lat,lng,fallback).then(name=>{
            if(token!==revibeAddressLookupToken) return;
            setAddressFromCoordinateName(name, lat, lng, syncMainCard);
        });
    } else {
        setAddressFromCoordinateName(fallback, lat, lng, syncMainCard);
    }
}

function scheduleAddressResolve(lat,lng,delay=450){
    clearTimeout(revibeAddressLookupTimer);
    revibeAddressLookupTimer=setTimeout(()=>resolveAddressFromCoordinate(lat,lng,false), delay);
}

function updatePickerCoordinateText(lat, lng){
    resolveAddressFromCoordinate(lat, lng, false);
}
function syncPickerAddress(){
    const text=document.getElementById('selectedAddressText')?.textContent || 'Alamat belum lengkap';
    const picker=document.getElementById('pickerAddressText');
    if(picker) picker.textContent=text;
    ['profileCoordName','pickerCoordText'].forEach(id=>{
        const el=document.getElementById(id);
        if(!el) return;
        el.dataset.fallback = text || 'Titik lokasi belum dipilih';
        if(!document.getElementById('profileLat')?.value || !document.getElementById('profileLng')?.value){
            el.textContent = text || 'Titik lokasi belum dipilih';
        }
    });
}
function haversineKm(a,b){
    const R=6371, toRad=x=>x*Math.PI/180;
    const dLat=toRad(b[0]-a[0]), dLng=toRad(b[1]-a[1]);
    const s=Math.sin(dLat/2)**2 + Math.cos(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.sin(dLng/2)**2;
    return 2*R*Math.atan2(Math.sqrt(s),Math.sqrt(1-s));
}
function requestBrowserLocation(onDone, button){
    if(!navigator.geolocation){ alert('Browser tidak mendukung geolocation. Gunakan tombol Pilih Titik di Peta.'); return; }
    const original = button ? button.textContent : '';
    if(button) button.textContent = 'Mengambil lokasi...';
    navigator.geolocation.getCurrentPosition(pos => {
        if(button) button.textContent = original || '📍 Ambil Titik Lokasi Saya';
        onDone(pos.coords.latitude, pos.coords.longitude);
    }, () => {
        if(button) button.textContent = original || '📍 Ambil Titik Lokasi Saya';
        alert('Lokasi tidak diizinkan. Izinkan location permission di browser, atau geser pin di peta secara manual.');
    }, {enableHighAccuracy:true, timeout:12000, maximumAge:0});
}
document.getElementById('profileUseLocation')?.addEventListener('click', function(){
    requestBrowserLocation((lat,lng)=>setProfileCoordinate(lat,lng), this);
});
function openCoordinatePicker(){
    syncPickerAddress();
    const modal=document.getElementById('coordinatePicker');
    if(!modal) return;
    modal.classList.add('active');
    modal.setAttribute('aria-hidden','false');
    document.body.classList.add('map-picker-open');
    setTimeout(initCoordinateMap, 80);
}
function closeCoordinatePicker(){
    const modal=document.getElementById('coordinatePicker');
    if(!modal) return;
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden','true');
    document.body.classList.remove('map-picker-open');
}
function initCoordinateMap(){
    const existing=getProfileCoordinate();
    const center=existing || [-6.2000000, 106.8166660];
    if(typeof L === 'undefined'){
        document.querySelector('#leafletAddressMap .map-fallback-note')?.classList.add('show');
        if(existing) updatePickerCoordinateText(existing[0], existing[1]);
        return;
    }
    if(!window.revibeMap){
        window.revibeMap=L.map('leafletAddressMap', {zoomControl:false}).setView(center, existing ? 17 : 12);
        L.control.zoom({position:'bottomright'}).addTo(window.revibeMap);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom:19,
            attribution:'&copy; OpenStreetMap'
        }).addTo(window.revibeMap);
        const icon=L.divIcon({className:'revibe-map-pin-icon', html:'<div class="pin-head"></div><div class="pin-stick"></div>', iconSize:[42,64], iconAnchor:[21,54]});
        window.revibeMapMarker=L.marker(center,{draggable:true,icon}).addTo(window.revibeMap);
        window.revibeMap.on('click', e=>{
            window.revibeMapMarker.setLatLng(e.latlng);
            resolveAddressFromCoordinate(e.latlng.lat, e.latlng.lng, false);
        });
        window.revibeMapMarker.on('drag', e=>{
            const p=e.target.getLatLng();
            scheduleAddressResolve(p.lat, p.lng, 650);
        });
        window.revibeMapMarker.on('dragend', e=>{
            const p=e.target.getLatLng();
            resolveAddressFromCoordinate(p.lat, p.lng, false);
        });
    } else {
        window.revibeMap.invalidateSize();
        window.revibeMap.setView(center, existing ? 17 : window.revibeMap.getZoom());
        window.revibeMapMarker.setLatLng(center);
    }
    updatePickerCoordinateText(center[0], center[1]);
    setTimeout(()=>window.revibeMap && window.revibeMap.invalidateSize(), 250);
}
document.getElementById('openCoordinatePicker')?.addEventListener('click', openCoordinatePicker);
document.getElementById('closeCoordinatePicker')?.addEventListener('click', closeCoordinatePicker);
document.getElementById('cancelCoordinatePicker')?.addEventListener('click', closeCoordinatePicker);
document.getElementById('pickerUseLocation')?.addEventListener('click', function(){
    requestBrowserLocation((lat,lng)=>{
        if(window.revibeMap){
            const point=[lat,lng];
            window.revibeMap.setView(point, 17);
            window.revibeMapMarker.setLatLng(point);
            updatePickerCoordinateText(lat,lng);
        } else {
            setProfileCoordinate(lat,lng);
        }
    }, this);
});
document.getElementById('confirmCoordinatePicker')?.addEventListener('click', function(){
    let chosen=null;
    if(window.revibeMapMarker){ const p=window.revibeMapMarker.getLatLng(); chosen=[p.lat,p.lng]; }
    chosen = chosen || getProfileCoordinate();
    if(!chosen){ alert('Pilih titik alamat dulu di peta atau gunakan lokasi saya.'); return; }
    const old=getProfileCoordinate();
    if(old && haversineKm(old, chosen) > 20){
        if(!confirm('Lokasi yang kamu pilih berbeda jauh dari titik sebelumnya. Pastikan pin sudah sesuai alamat kamu. Lanjut simpan titik ini?')) return;
    }
    setProfileCoordinate(chosen[0], chosen[1]);
    closeCoordinatePicker();
});
document.querySelector('.address-edit-screen')?.addEventListener('submit', function(){
    const hidden=document.getElementById('fullAddressHidden');
    const region=document.getElementById('regionInput');
    const street=document.getElementById('streetInput');
    const detail=document.getElementById('detailInput');
    const manualFull=[street?.value.trim()||'', detail?.value.trim()||'', region?.value.trim()||''].filter(Boolean).join(', ');
    if(hidden) hidden.value = manualFull || revibeLastResolvedAddress || hidden.value;
});
buildFullAddress(); updateMapLink(); syncPickerAddress();
</script>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
