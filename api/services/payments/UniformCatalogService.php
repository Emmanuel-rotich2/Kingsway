<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use App\API\Services\UploadService;
use PDO;
use RuntimeException;
use App\API\Services\ReadReplicaService;

/** Uniform store catalogue and parent checkout preparation. */
final class UniformCatalogService
{
    private PDO $db;
    private UploadService $uploads;

    private function publicImageUrl(?string $stored): ?string
    {
        return $this->uploads->publicUrl($stored);
    }
    public function __construct(PDO $db) { $this->db = $db; $this->uploads = new UploadService(); }

    public function list(array $filters = []): array
    {
        $where = "p.status='active' AND p.published=1";
        $args = [];
        if (!empty($filters['staff'])) $where = "1=1"; // management workspace includes every lifecycle state
        elseif (!empty($filters['internal'])) $where = "p.status='active'"; // staff shop includes public and internal-only products
        if (!empty($filters['q'])) { $where .= ' AND (p.title LIKE ? OR i.description LIKE ?)'; $q='%'.trim((string)$filters['q']).'%'; $args[]=$q; $args[]=$q; }
        if (!empty($filters['category'])) { $where .= ' AND p.category_slug=?'; $args[]=(string)$filters['category']; }
        $s=$this->db->prepare("SELECT p.id,p.item_id,p.slug,p.title,p.description,p.category_slug,p.product_type,p.customizable_name,p.customizable_number,p.status,p.published,i.code,COALESCE((SELECT im.url FROM uniform_catalog_variants dv JOIN " . ReadReplicaService::qualifiedRef("uniform_catalog_images") . " im ON im.variant_id=dv.id WHERE dv.product_id=p.id AND dv.status='active' ORDER BY dv.is_default DESC,dv.display_order,im.is_primary DESC,im.display_order,im.id LIMIT 1),(SELECT im.url FROM " . ReadReplicaService::qualifiedRef("uniform_catalog_images") . " im WHERE im.product_id=p.id AND im.variant_id IS NULL ORDER BY im.is_primary DESC,im.display_order,im.id LIMIT 1)) AS image_url FROM " . ReadReplicaService::qualifiedRef("uniform_catalog_products") . " p JOIN " . ReadReplicaService::qualifiedRef("inventory_items") . " i ON i.id=p.item_id WHERE {$where} ORDER BY p.category_slug,p.title");
        $s->execute($args); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
        $sizes=$this->db->query("SELECT p.id AS product_id,NULL AS variant_id,us.id AS size_id,us.size,us.size_label,us.size_type,us.unit_price,us.quantity_available-us.quantity_reserved AS available FROM " . ReadReplicaService::qualifiedRef("uniform_catalog_products") . " JOIN " . ReadReplicaService::qualifiedRef("uniform_sizes") . " us ON us.item_id=p.item_id UNION ALL SELECT v.product_id,v.id AS variant_id,us.id,us.size,us.size_label,us.size_type,us.unit_price,us.quantity_available-us.quantity_reserved FROM uniform_catalog_variants v JOIN " . ReadReplicaService::qualifiedRef("uniform_sizes") . " us ON us.item_id=v.item_id WHERE v.item_id IS NOT NULL ORDER BY product_id,variant_id,size")->fetchAll(PDO::FETCH_ASSOC);
        $variantWhere = !empty($filters['staff']) ? '1=1' : "v.status='active'";
        $variants=$this->db->query("SELECT v.id,v.product_id,v.item_id,v.code,v.name,v.color_name,v.swatch_hex,v.status,v.is_default,v.display_order,(SELECT im.url FROM " . ReadReplicaService::qualifiedRef("uniform_catalog_images") . " im WHERE im.variant_id=v.id ORDER BY im.is_primary DESC,im.display_order,im.id LIMIT 1) image_url FROM uniform_catalog_variants v WHERE {$variantWhere} ORDER BY v.product_id,v.display_order,v.id")->fetchAll(PDO::FETCH_ASSOC);
        $images=$this->db->query("SELECT id,product_id,variant_id,url,alt_text,view_type,is_primary,display_order FROM uniform_catalog_images ORDER BY product_id,variant_id,is_primary DESC,display_order,id")->fetchAll(PDO::FETCH_ASSOC);
        $by=[];$variantBy=[];$imageBy=[]; foreach($sizes as $size)$by[(int)$size['product_id']][]=$size; foreach($variants as $variant){$variant['image_url']=$this->publicImageUrl($variant['image_url']??null);$variantBy[(int)$variant['product_id']][]=$variant;} foreach($images as $image){$image['url']=$this->publicImageUrl($image['url']??null);$imageBy[(int)$image['product_id']][]=$image;}
        foreach($rows as &$row){$row['image_url']=$this->publicImageUrl($row['image_url']??null);$row['sizes']=$by[(int)$row['id']]??[];$row['variants']=$variantBy[(int)$row['id']]??[];$row['images']=$imageBy[(int)$row['id']]??[];} unset($row); return $rows;
    }

    public function saveProduct(array $data, int $userId): array
    {
        $itemId=(int)($data['item_id']??0); $title=trim((string)($data['title']??''));
        if(!$itemId||$title==='')throw new RuntimeException('item_id and title are required');
        $slug=trim((string)($data['slug']??'')) ?: strtolower(preg_replace('/[^a-z0-9]+/i','-', $title)).'-'.$itemId;
        $s=$this->db->prepare("INSERT INTO uniform_catalog_products(item_id,slug,title,description,category_slug,product_type,customizable_name,customizable_number,published,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),category_slug=VALUES(category_slug),product_type=VALUES(product_type),customizable_name=VALUES(customizable_name),customizable_number=VALUES(customizable_number),published=VALUES(published),status=VALUES(status)");
        $s->execute([$itemId,$slug,$title,$data['description']??null,$data['category_slug']??'formal-uniform',$data['product_type']??null,!empty($data['customizable_name'])?1:0,!empty($data['customizable_number'])?1:0,!empty($data['published'])?1:0,$data['status']??'draft',$userId]);
        $id=(int)$this->db->lastInsertId(); if(!$id){$q=$this->db->prepare('SELECT id FROM uniform_catalog_products WHERE item_id=?');$q->execute([$itemId]);$id=(int)$q->fetchColumn();} return ['id'=>$id,'product'=>$this->get($id)];
    }

    public function addImage(int $productId, string $url, ?string $alt=null, bool $primary=false, ?int $variantId=null, string $viewType='catalog'): array
    { if(!$productId||$url==='')throw new RuntimeException('product_id and image URL are required');if(!in_array($viewType,['catalog','front','back','detail','lifestyle','size_guide'],true))throw new RuntimeException('Invalid image view type'); $canonical=$this->uploads->canonicalStoredReference($url); $this->db->beginTransaction(); try { if($primary){$sql=$variantId?'UPDATE uniform_catalog_images SET is_primary=0 WHERE product_id=? AND variant_id=?':'UPDATE uniform_catalog_images SET is_primary=0 WHERE product_id=? AND variant_id IS NULL';$params=$variantId?[$productId,$variantId]:[$productId];$this->db->prepare($sql)->execute($params);}$s=$this->db->prepare('INSERT INTO uniform_catalog_images(product_id,variant_id,url,alt_text,view_type,is_primary) VALUES(?,?,?,?,?,?)');$s->execute([$productId,$variantId?:null,$canonical,$alt,$viewType,$primary?1:0]);$this->db->commit();return ['id'=>(int)$this->db->lastInsertId(),'url'=>$this->publicImageUrl($canonical)]; }catch(\Throwable $e){$this->db->rollBack();throw $e;} }

    public function get(int $id): array { $s=$this->db->prepare('SELECT * FROM uniform_catalog_products WHERE id=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC)?:[]; }

    /** Images for one product, display-ordered. */
    public function imagesForProduct(int $productId): array
    { $s=$this->db->prepare('SELECT id,variant_id,url,alt_text,view_type,is_primary,display_order FROM uniform_catalog_images WHERE product_id=? ORDER BY is_primary DESC,display_order,id');$s->execute([$productId]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }

    /** Active variants for one product, display-ordered. */
    public function variantsForProduct(int $productId): array
    { $s=$this->db->prepare("SELECT id,item_id,code,name,color_name,swatch_hex,is_default,display_order FROM uniform_catalog_variants WHERE product_id=? AND status='active' ORDER BY display_order,id");$s->execute([$productId]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }

    /** In-stock sizes (base + variant unions) for one product. */
    public function sizesForProduct(int $itemId, int $productId): array
    { $s=$this->db->prepare("SELECT us.id size_id,NULL variant_id,us.size,us.size_label,us.size_type,us.unit_price,us.quantity_available-us.quantity_reserved AS available FROM " . ReadReplicaService::qualifiedRef("uniform_sizes") . " WHERE us.item_id=? AND us.quantity_available>us.quantity_reserved UNION ALL SELECT us.id,v.id,us.size,us.size_label,us.size_type,us.unit_price,us.quantity_available-us.quantity_reserved AS available FROM uniform_catalog_variants v JOIN " . ReadReplicaService::qualifiedRef("uniform_sizes") . " us ON us.item_id=v.item_id WHERE v.product_id=? AND v.status='active' AND us.quantity_available>us.quantity_reserved ORDER BY variant_id,unit_price,size");$s->execute([$itemId,$productId]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }

    public function saveVariant(array $data): array
    { $productId=(int)($data['product_id']??0);$id=(int)($data['id']??0);$code=trim((string)($data['code']??''));$name=trim((string)($data['name']??''));if(!$productId||$code===''||$name==='')throw new RuntimeException('product_id, variant code and name are required');$itemId=(int)($data['item_id']??0)?:null;$hex=trim((string)($data['swatch_hex']??''))?:null;if($hex!==null&&!preg_match('/^#[0-9A-Fa-f]{6}$/',$hex))throw new RuntimeException('Swatch must be a six-digit hex colour');if(!empty($data['is_default']))$this->db->prepare('UPDATE uniform_catalog_variants SET is_default=0 WHERE product_id=?')->execute([$productId]);if($id){$s=$this->db->prepare('UPDATE uniform_catalog_variants SET item_id=?,code=?,name=?,color_name=?,swatch_hex=?,status=?,is_default=?,display_order=? WHERE id=? AND product_id=?');$s->execute([$itemId,$code,$name,$data['color_name']??null,$hex,$data['status']??'draft',!empty($data['is_default'])?1:0,(int)($data['display_order']??0),$id,$productId]);}else{$s=$this->db->prepare('INSERT INTO uniform_catalog_variants(product_id,item_id,code,name,color_name,swatch_hex,status,is_default,display_order) VALUES(?,?,?,?,?,?,?,?,?)');$s->execute([$productId,$itemId,$code,$name,$data['color_name']??null,$hex,$data['status']??'draft',!empty($data['is_default'])?1:0,(int)($data['display_order']??0)]);$id=(int)$this->db->lastInsertId();}return ['id'=>$id]; }

    public function saveSize(array $data): array
    { $itemId=(int)($data['item_id']??0);$size=trim((string)($data['size']??''));if(!$itemId||$size==='')throw new RuntimeException('Inventory item and size are required');$price=(float)($data['unit_price']??0);$available=(int)($data['quantity_available']??0);$reserved=(int)($data['quantity_reserved']??0);if($price<0||$available<0||$reserved<0||$reserved>$available)throw new RuntimeException('Price and stock values are invalid');$s=$this->db->prepare('INSERT INTO uniform_sizes(item_id,size,size_label,size_type,quantity_available,quantity_reserved,unit_price,reorder_level) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE size_label=VALUES(size_label),size_type=VALUES(size_type),quantity_available=VALUES(quantity_available),quantity_reserved=VALUES(quantity_reserved),unit_price=VALUES(unit_price),reorder_level=VALUES(reorder_level)');$s->execute([$itemId,$size,$data['size_label']??$size,$data['size_type']??'clothing',$available,$reserved,$price,(int)($data['reorder_level']??0)]);return ['item_id'=>$itemId,'size'=>$size]; }

    public function deleteImage(int $imageId): void
    { if(!$imageId)throw new RuntimeException('Image ID is required');$this->db->prepare('DELETE FROM uniform_catalog_images WHERE id=?')->execute([$imageId]); }

    public function wishlist(int $parentId, int $productId): array
    { if(!$parentId||!$productId)throw new RuntimeException('parent_id and product_id are required');$this->db->prepare('INSERT INTO uniform_catalog_wishlists(parent_id,product_id) VALUES(?,?) ON DUPLICATE KEY UPDATE created_at=created_at')->execute([$parentId,$productId]);return ['parent_id'=>$parentId,'product_id'=>$productId]; }

    public function addToCart(int $parentId, int $productId, int $sizeId, int $quantity, ?int $variantId=null): array
    { if(!$parentId||!$productId||!$sizeId||$quantity<1)throw new RuntimeException('product, size and positive quantity are required');$check=$this->db->prepare("SELECT p.id FROM " . ReadReplicaService::qualifiedRef("uniform_catalog_products") . " p LEFT JOIN uniform_catalog_variants v ON v.id=? AND v.product_id=p.id JOIN " . ReadReplicaService::qualifiedRef("uniform_sizes") . " s ON s.item_id=COALESCE(v.item_id,p.item_id) WHERE p.id=? AND s.id=? AND p.status='active' AND p.published=1 AND (v.id IS NULL OR v.status='active') AND s.quantity_available-s.quantity_reserved>=?");$check->execute([$variantId,$productId,$sizeId,$quantity]);if(!$check->fetchColumn())throw new RuntimeException('Selected variant or size is unavailable');$c=$this->db->prepare("SELECT id FROM uniform_catalog_carts WHERE parent_id=? AND status='open' ORDER BY id DESC LIMIT 1");$c->execute([$parentId]);$cart=(int)$c->fetchColumn();if(!$cart){$this->db->prepare("INSERT INTO uniform_catalog_carts(parent_id) VALUES(?)")->execute([$parentId]);$cart=(int)$this->db->lastInsertId();}$this->db->prepare('INSERT INTO uniform_catalog_cart_items(cart_id,product_id,variant_id,size_id,quantity) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity),updated_at=NOW()')->execute([$cart,$productId,$variantId,$sizeId,$quantity]);return $this->cart($parentId); }

    public function cart(int $parentId): array
    { $c=$this->db->prepare("SELECT id FROM uniform_catalog_carts WHERE parent_id=? AND status='open' ORDER BY id DESC LIMIT 1");$c->execute([$parentId]);$id=(int)$c->fetchColumn();if(!$id)return ['cart_id'=>null,'items'=>[],'total'=>0.0];$s=$this->db->prepare('SELECT ci.*,p.title,v.name variant_name,v.color_name,s.size,s.size_label,s.unit_price,(ci.quantity*s.unit_price) line_total FROM uniform_catalog_cart_items ci JOIN ' . ReadReplicaService::qualifiedRef('uniform_catalog_products') . ' p ON p.id=ci.product_id LEFT JOIN uniform_catalog_variants v ON v.id=ci.variant_id JOIN ' . ReadReplicaService::qualifiedRef('uniform_sizes') . ' s ON s.id=ci.size_id WHERE ci.cart_id=?');$s->execute([$id]);$items=$s->fetchAll(PDO::FETCH_ASSOC);$total=0.0;foreach($items as $x)$total+=(float)$x['line_total'];return ['cart_id'=>$id,'items'=>$items,'total'=>$total]; }
}
