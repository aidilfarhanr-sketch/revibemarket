<?php
class ProductService {
    private $conn;
    public function __construct($conn = null) { $this->conn = $conn; }
    public function latestPublic(int $limit = 12): array {
        if (!$this->conn) return [];
        $limit = max(1, min(48, $limit));
        $cache = class_exists('CacheService') ? new CacheService() : null;
        return $cache ? $cache->remember('public:products:latest:'.$limit, (int)(function_exists('revibe_env') ? revibe_env('CACHE_TTL_PUBLIC_SECONDS',300) : 300), fn() => $this->queryLatest($limit)) : $this->queryLatest($limit);
    }
    private function queryLatest(int $limit): array {
        $rows=[]; $q=mysqli_query($this->conn,"SELECT p.*,u.first_name,u.last_name FROM products p LEFT JOIN users u ON u.id=p.user_id WHERE COALESCE(p.product_status,'approved')='approved' AND COALESCE(p.is_active,1)=1 ORDER BY p.id DESC LIMIT {$limit}");
        while($q && ($r=mysqli_fetch_assoc($q))) $rows[]=$r;
        return $rows;
    }
    public function categories(): array {
        if (!$this->conn) return [];
        $cache = class_exists('CacheService') ? new CacheService() : null;
        $cb = function(){ $rows=[]; $q=mysqli_query($this->conn,"SELECT category,COUNT(*) total FROM products WHERE COALESCE(product_status,'approved')='approved' GROUP BY category ORDER BY total DESC"); while($q && ($r=mysqli_fetch_assoc($q))) $rows[]=$r; return $rows; };
        return $cache ? $cache->remember('public:categories', 600, $cb) : $cb();
    }
    public function invalidatePublicCache(): void { if (class_exists('CacheService')) (new CacheService())->invalidatePublicProductCache(); }
    public function validateProductInput(array $data): array {
        $name = trim((string)($data['name'] ?? '')); $price = (int)($data['price'] ?? 0);
        if ($name === '' || $price <= 0) return ['success'=>false,'message'=>'Nama produk dan harga wajib valid.','error_code'=>'INVALID_PRODUCT'];
        return ['success'=>true,'message'=>'OK','data'=>['name'=>$name,'price'=>$price]];
    }
}
