<?php foreach (['success'=>'success','error'=>'error','warning'=>'warning','info'=>'info'] as $key=>$class): if(isset($_SESSION[$key])): ?>
<div class="rv-toast <?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$_SESSION[$key], ENT_QUOTES, 'UTF-8'); unset($_SESSION[$key]); ?><button type="button" onclick="this.parentElement.remove()">✕</button></div>
<?php endif; endforeach; ?>
