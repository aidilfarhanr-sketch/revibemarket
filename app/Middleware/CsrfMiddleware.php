<?php
class CsrfMiddleware { public function handle(): void { if (function_exists('verify_csrf')) verify_csrf(); } }
