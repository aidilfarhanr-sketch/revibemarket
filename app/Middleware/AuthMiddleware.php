<?php
class AuthMiddleware { public function requireLogin(): void { if (function_exists('require_login')) require_login('../index.php'); } }
