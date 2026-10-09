<?php
declare(strict_types=1);

namespace App\API\Services\catalog;

use App\API\Services\Logger;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use PDO;
use RuntimeException;
use App\API\Services\ReadReplicaService;

/** Unit-level stock identity, scanner events, receipt intake and governed offers. */
final class CatalogStockService
{
    public function __construct(private PDO $db) {}

    public function intakeOptions(): array
    {
        $products=$this->db->query("SELECT p.id,p.title,p.item_id FROM uniform_catalog_products p WHERE p.status<>'archived' ORDER BY p.title")->fetchAll(PDO::FETCH_ASSOC);
        $sizes=$this->db->query("SELECT p.id product_id,NULL variant_id,NULL variant_name,us.id size_id,us.item_id,us.size,us.size_label,us.unit_price FROM " . ReadReplicaService::qualifiedRef("uniform_catalog_products") . " p JOIN " . ReadReplicaService::qualifiedRef("uniform_sizes") . " us ON us.item_id=p.item_id WHERE p.status<>'archived' UNION ALL SELECT v.product_id,v.id,v.name,us.id,us.item_id,us.size,us.size_label,us.unit_price FROM uniform_catalog_variants v JOIN " . ReadReplicaService::qualifiedRef("uniform_sizes") . " us ON us.item_id=v.item_id JOIN " . ReadReplicaService::qualifiedRef("uniform_catalog_products") . " p ON p.id=v.product_id WHERE p.status<>'archived' AND v.status='active' ORDER BY product_id,variant_id,size")->fetchAll(PDO::FETCH_ASSOC);
        foreach($products as &$product)$product['sizes']=array_values(array_filter($sizes,fn(array $size)=>(int)$size['product_id']===(int)$product['id']));
        unset($product);
        return [
            'products'=>$products,
            'suppliers'=>$this->db->query("SELECT id,name FROM suppliers WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
            'purchase_orders'=>$this->db->query("SELECT id,order_number,supplier_id,status FROM purchase_orders WHERE status IN ('approved','ordered','received') ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function sizeLine(int $productId,?int $variantId,int $sizeId,bool $lock=false): array
    {
        $sql="SELECT p.id product_id,p.title,v.id variant_id,v.name variant_name,us.id size_id,us.item_id,us.size,us.size_label,us.unit_price FROM uniform_catalog_products p LEFT JOIN uniform_catalog_variants v ON v.id=? AND v.product_id=p.id JOIN uniform_sizes us ON us.id=? AND us.item_id=COALESCE(v.item_id,p.item_id) WHERE p.id=?".($lock?' FOR UPDATE':'');
        $stmt=$this->db->prepare($sql);$stmt->execute([$variantId,$sizeId,$productId]);$line=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$line)throw new RuntimeException('The selected product, variant and size do not match');
        return $line;
    }

    private function unitCode(): string
    {
        return 'KPSU-'.date('y').'-'.strtoupper(bin2hex(random_bytes(6)));
    }

    /**
     * Loose (+1 weight) availability check for a catalogue line before staff
     * sale registration. Returns [item fields, available qty] or throws.
     */
    public function availableSaleLine(int $productId, ?int $variantId, int $sizeId, int $quantity): array
    {
        $line = $this->sizeLine($productId, $variantId, $sizeId);
        $stmt = $this->db->prepare(
            'SELECT quantity_available, quantity_reserved FROM uniform_sizes WHERE id = ?'
        );
        $stmt->execute([$sizeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $available = (int) ($row['quantity_available'] ?? 0) - (int) ($row['quantity_reserved'] ?? 0);
        if ($available < $quantity) {
            throw new RuntimeException('Selected variant or size is unavailable');
        }
        return $line;
    }

    public function receive(array $data,int $actor): array
    {
        $lines=$data['lines']??[];if(!is_array($lines)||!$lines)throw new RuntimeException('Add at least one received stock line');
        $supplier=(int)($data['supplier_id']??0)?:null;$po=(int)($data['purchase_order_id']??0)?:null;
        if($po){$poStmt=$this->db->prepare("SELECT supplier_id,status FROM purchase_orders WHERE id=? AND status IN ('approved','ordered','received')");$poStmt->execute([$po]);$poRow=$poStmt->fetch(PDO::FETCH_ASSOC);if(!$poRow)throw new RuntimeException('The selected purchase order is not approved for receiving');if($supplier&&(int)$poRow['supplier_id']!==$supplier)throw new RuntimeException('The selected supplier does not match the purchase order');$supplier=$supplier?:(int)$poRow['supplier_id'];}
        $ref=trim((string)($data['receipt_reference']??''))?:'KPS-GRN-'.date('ymd-His').'-'.strtoupper(bin2hex(random_bytes(2)));
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO catalog_stock_receipts(receipt_reference,purchase_order_id,supplier_id,supplier_reference,delivery_note,notes,received_by) VALUES(?,?,?,?,?,?,?)')->execute([$ref,$po,$supplier,trim((string)($data['supplier_reference']??''))?:null,trim((string)($data['delivery_note']??''))?:null,trim((string)($data['notes']??''))?:null,$actor]);
            $receiptId=(int)$this->db->lastInsertId();$units=[];
            foreach($lines as $entry){
                $qty=(int)($entry['quantity']??0);$cost=round((float)($entry['unit_cost']??0),2);
                if($qty<1||$qty>1000)throw new RuntimeException('Each receipt line quantity must be between 1 and 1,000');
                if($cost<0)throw new RuntimeException('Unit cost cannot be negative');
                $line=$this->sizeLine((int)($entry['product_id']??0),(int)($entry['variant_id']??0)?:null,(int)($entry['size_id']??0),true);
                $this->db->prepare('INSERT INTO catalog_stock_receipt_lines(receipt_id,product_id,variant_id,size_id,item_id,quantity,unit_cost,line_cost) VALUES(?,?,?,?,?,?,?,?)')->execute([$receiptId,$line['product_id'],$line['variant_id']?:null,$line['size_id'],$line['item_id'],$qty,$cost,$qty*$cost]);
                $receiptLine=(int)$this->db->lastInsertId();
                $insertUnit=$this->db->prepare("INSERT INTO catalog_stock_units(unit_code,receipt_line_id,product_id,variant_id,size_id,item_id,received_by) VALUES(?,?,?,?,?,?,?)");
                $event=$this->db->prepare("INSERT INTO catalog_stock_unit_events(unit_id,event_type,from_status,to_status,notes,context_json,actor_user_id) VALUES(?,'received',NULL,'in_stock',?,?,?)");
                for($i=0;$i<$qty;$i++){$code=$this->unitCode();$insertUnit->execute([$code,$receiptLine,$line['product_id'],$line['variant_id']?:null,$line['size_id'],$line['item_id'],$actor]);$unitId=(int)$this->db->lastInsertId();$event->execute([$unitId,'Receipt '.$ref,json_encode(['receipt_id'=>$receiptId,'receipt_line_id'=>$receiptLine]),$actor]);$units[]=['id'=>$unitId,'unit_code'=>$code,'product_title'=>$line['title'],'variant_name'=>$line['variant_name'],'size_label'=>$line['size_label']?:$line['size']];}
                $this->db->prepare('UPDATE uniform_sizes SET quantity_available=quantity_available+? WHERE id=?')->execute([$qty,$line['size_id']]);
                $this->db->prepare('UPDATE inventory_items SET current_quantity=current_quantity+? WHERE id=?')->execute([$qty,$line['item_id']]);
                $this->db->prepare("INSERT INTO inventory_transactions(item_id,transaction_type,quantity,transaction_date,reference_type,reference_id,unit_cost,notes) VALUES(?,'in',?,CURDATE(),'purchase',?,?,?)")->execute([$line['item_id'],$qty,$po?:$receiptId,$cost,'Catalogue receipt '.$ref.' received by user '.$actor]);
            }
            if($po)$this->db->prepare("UPDATE purchase_orders SET status='received' WHERE id=? AND status IN ('approved','ordered')")->execute([$po]);
            $this->db->commit();
            Logger::audit('catalog_stock_received','catalog_stock_receipt',$receiptId,'Uniform stock received and unit labels generated',['receipt_reference'=>$ref,'unit_count'=>count($units),'purchase_order_id'=>$po,'supplier_id'=>$supplier]);
            return ['receipt_id'=>$receiptId,'receipt_reference'=>$ref,'units'=>$units,'unit_count'=>count($units)];
        } catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function identifyExistingStock(int $actor): array
    {
        $rows=$this->db->query("SELECT p.id product_id,NULL variant_id,us.id size_id,us.item_id,us.quantity_available,(SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("catalog_stock_units") . " WHERE u.size_id=us.id AND u.status IN ('in_stock','reserved')) identified FROM uniform_catalog_products p JOIN uniform_sizes us ON us.item_id=p.item_id UNION ALL SELECT v.product_id,v.id,us.id,us.item_id,us.quantity_available,(SELECT COUNT(*) FROM catalog_stock_units u WHERE u.size_id=us.id AND u.status IN ('in_stock','reserved')) FROM uniform_catalog_variants v JOIN uniform_sizes us ON us.item_id=v.item_id WHERE v.status='active'")->fetchAll(PDO::FETCH_ASSOC);
        $rows=array_values(array_filter($rows,fn(array $row)=>(int)$row['quantity_available']>(int)$row['identified']));if(!$rows)return ['unit_count'=>0,'units'=>[],'message'=>'All current stock is already identified'];
        $ref='KPS-OPENING-'.date('ymd-His');$this->db->beginTransaction();try{$this->db->prepare("INSERT INTO catalog_stock_receipts(receipt_reference,notes,received_by) VALUES(?,'Opening identification of stock that existed before unit labels',?)")->execute([$ref,$actor]);$receiptId=(int)$this->db->lastInsertId();$units=[];foreach($rows as $row){$qty=(int)$row['quantity_available']-(int)$row['identified'];$line=$this->sizeLine((int)$row['product_id'],(int)$row['variant_id']?:null,(int)$row['size_id'],true);$this->db->prepare('INSERT INTO catalog_stock_receipt_lines(receipt_id,product_id,variant_id,size_id,item_id,quantity,unit_cost,line_cost) VALUES(?,?,?,?,?,?,0,0)')->execute([$receiptId,$line['product_id'],$line['variant_id']?:null,$line['size_id'],$line['item_id'],$qty]);$receiptLine=(int)$this->db->lastInsertId();for($i=0;$i<$qty;$i++){$code=$this->unitCode();$this->db->prepare('INSERT INTO catalog_stock_units(unit_code,receipt_line_id,product_id,variant_id,size_id,item_id,received_by) VALUES(?,?,?,?,?,?,?)')->execute([$code,$receiptLine,$line['product_id'],$line['variant_id']?:null,$line['size_id'],$line['item_id'],$actor]);$unitId=(int)$this->db->lastInsertId();$this->db->prepare("INSERT INTO catalog_stock_unit_events(unit_id,event_type,from_status,to_status,notes,context_json,actor_user_id) VALUES(?,'received',NULL,'in_stock',?,?,?)")->execute([$unitId,'Opening stock identification',json_encode(['opening_balance'=>true,'receipt_id'=>$receiptId]),$actor]);$units[]=['id'=>$unitId,'unit_code'=>$code,'product_title'=>$line['title'],'variant_name'=>$line['variant_name'],'size_label'=>$line['size_label']?:$line['size']];}}$this->db->commit();Logger::audit('catalog_existing_stock_identified','catalog_stock_receipt',$receiptId,'Existing aggregate stock converted to unit identities',['receipt_reference'=>$ref,'unit_count'=>count($units)]);return ['receipt_id'=>$receiptId,'receipt_reference'=>$ref,'unit_count'=>count($units),'units'=>$units];}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function lookup(string $code,int $actor=0,?string $scanner=null,bool $recordScan=true): array
    {
        $code=strtoupper(trim($code));if($code==='')throw new RuntimeException('Scan or enter a unit code');
        $stmt=$this->db->prepare("SELECT u.*,p.title product_title,v.name variant_name,COALESCE(us.size_label,us.size) size_label,us.unit_price,r.receipt_reference,rl.unit_cost,o.order_reference FROM " . ReadReplicaService::qualifiedRef("catalog_stock_units") . " u JOIN " . ReadReplicaService::qualifiedRef("uniform_catalog_products") . " p ON p.id=u.product_id LEFT JOIN uniform_catalog_variants v ON v.id=u.variant_id JOIN " . ReadReplicaService::qualifiedRef("uniform_sizes") . " us ON us.id=u.size_id JOIN catalog_stock_receipt_lines rl ON rl.id=u.receipt_line_id JOIN catalog_stock_receipts r ON r.id=rl.receipt_id LEFT JOIN " . ReadReplicaService::qualifiedRef("catalog_orders") . " o ON o.id=u.reserved_order_id WHERE u.unit_code=?");$stmt->execute([$code]);$unit=$stmt->fetch(PDO::FETCH_ASSOC);if(!$unit)throw new RuntimeException('This QR/barcode is not registered in uniform stock');
        if($recordScan&&$actor)$this->db->prepare("INSERT INTO catalog_stock_unit_events(unit_id,event_type,from_status,to_status,scanner_reference,actor_user_id) VALUES(?,'scanned',?,?,?,?)")->execute([$unit['id'],$unit['status'],$unit['status'],$scanner,$actor]);
        return $unit;
    }

    public function units(array $filters=[]): array
    {
        $where=[];$args=[];if(!empty($filters['status'])){$where[]='u.status=?';$args[]=$filters['status'];}if(!empty($filters['q'])){$where[]='(u.unit_code LIKE ? OR p.title LIKE ? OR r.receipt_reference LIKE ?)';$q='%'.trim((string)$filters['q']).'%';array_push($args,$q,$q,$q);}
        $sql="SELECT u.id,u.unit_code,u.status,u.received_at,u.dispatched_at,p.title product_title,v.name variant_name,COALESCE(us.size_label,us.size) size_label,r.receipt_reference,o.order_reference,CONCAT(pe.first_name,' ',pe.last_name) last_actor FROM " . ReadReplicaService::qualifiedRef("catalog_stock_units") . " u JOIN " . ReadReplicaService::qualifiedRef("uniform_catalog_products") . " p ON p.id=u.product_id LEFT JOIN uniform_catalog_variants v ON v.id=u.variant_id JOIN " . ReadReplicaService::qualifiedRef("uniform_sizes") . " us ON us.id=u.size_id JOIN catalog_stock_receipt_lines rl ON rl.id=u.receipt_line_id JOIN catalog_stock_receipts r ON r.id=rl.receipt_id LEFT JOIN " . ReadReplicaService::qualifiedRef("catalog_orders") . " o ON o.id=u.reserved_order_id LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " pe ON pe.user_id=COALESCE(u.dispatched_by,u.received_by)".($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY u.updated_at DESC LIMIT 500';$stmt=$this->db->prepare($sql);$stmt->execute($args);$units=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $summary=$this->db->query("SELECT COUNT(*) total_units,COALESCE(SUM(status='in_stock'),0) in_stock,COALESCE(SUM(status='reserved'),0) reserved,COALESCE(SUM(status='dispatched'),0) dispatched,COALESCE(SUM(status IN ('damaged','lost','void')),0) exceptions FROM catalog_stock_units")->fetch(PDO::FETCH_ASSOC);
        $variance=$this->db->query("SELECT us.id size_id,p.title,COALESCE(v.name,'Standard') variant_name,COALESCE(us.size_label,us.size) size_label,us.quantity_available aggregate_available,COALESCE(SUM(u.status IN ('in_stock','reserved')),0) identified_available,us.quantity_available-COALESCE(SUM(u.status IN ('in_stock','reserved')),0) variance FROM " . ReadReplicaService::qualifiedRef("uniform_sizes") . " us JOIN " . ReadReplicaService::qualifiedRef("inventory_items") . " i ON i.id=us.item_id LEFT JOIN " . ReadReplicaService::qualifiedRef("uniform_catalog_products") . " p ON p.item_id=i.id LEFT JOIN uniform_catalog_variants v ON v.item_id=i.id LEFT JOIN " . ReadReplicaService::qualifiedRef("catalog_stock_units") . " u ON u.size_id=us.id WHERE p.id IS NOT NULL OR v.id IS NOT NULL GROUP BY us.id,p.title,v.name,us.size_label,us.size,us.quantity_available HAVING us.quantity_available-COALESCE(SUM(u.status IN ('in_stock','reserved')),0)<>0 ORDER BY ABS(us.quantity_available-COALESCE(SUM(u.status IN ('in_stock','reserved')),0)) DESC")->fetchAll(PDO::FETCH_ASSOC);
        return ['units'=>$units,'summary'=>$summary,'variance'=>$variance];
    }

    public function unitEvents(int $unitId): array
    {
        $stmt=$this->db->prepare("SELECT e.*,CONCAT(p.first_name,' ',p.last_name) actor_name,o.order_reference FROM catalog_stock_unit_events e JOIN users u ON u.id=e.actor_user_id JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id=u.person_id LEFT JOIN " . ReadReplicaService::qualifiedRef("catalog_orders") . " o ON o.id=e.order_id WHERE e.unit_id=? ORDER BY e.created_at DESC");$stmt->execute([$unitId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function labels(array $ids): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids))));if(!$ids||count($ids)>1000)throw new RuntimeException('Select between 1 and 1,000 unit labels');$marks=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$this->db->prepare("SELECT u.id,u.unit_code,p.title product_title,v.name variant_name,COALESCE(us.size_label,us.size) size_label FROM " . ReadReplicaService::qualifiedRef("catalog_stock_units") . " u JOIN " . ReadReplicaService::qualifiedRef("uniform_catalog_products") . " p ON p.id=u.product_id LEFT JOIN uniform_catalog_variants v ON v.id=u.variant_id JOIN " . ReadReplicaService::qualifiedRef("uniform_sizes") . " us ON us.id=u.size_id WHERE u.id IN ($marks) ORDER BY u.id");$stmt->execute($ids);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$writer=new SvgWriter();
        foreach($rows as &$row){$qr=@$writer->write(new QrCode((string)$row['unit_code']));$row['qr_data_uri']=$qr->getDataUri();$row['barcode_svg']=$this->code39((string)$row['unit_code']);}unset($row);return $rows;
    }

    /** SVG Code 39 is intentionally local/offline and reads the same value as the QR label. */
    private function code39(string $value): string
    {
        $patterns=['0'=>'nnnwwnwnn','1'=>'wnnwnnnnw','2'=>'nnwwnnnnw','3'=>'wnwwnnnnn','4'=>'nnnwwnnnw','5'=>'wnnwwnnnn','6'=>'nnwwwnnnn','7'=>'nnnwnnwnw','8'=>'wnnwnnwnn','9'=>'nnwwnnwnn','A'=>'wnnnnwnnw','B'=>'nnwnnwnnw','C'=>'wnwnnwnnn','D'=>'nnnnwwnnw','E'=>'wnnnwwnnn','F'=>'nnwnwwnnn','G'=>'nnnnnwwnw','H'=>'wnnnnwwnn','I'=>'nnwnnwwnn','J'=>'nnnnwwwnn','K'=>'wnnnnnnww','L'=>'nnwnnnnww','M'=>'wnwnnnnwn','N'=>'nnnnwnnww','O'=>'wnnnwnnwn','P'=>'nnwnwnnwn','Q'=>'nnnnnnwww','R'=>'wnnnnnwwn','S'=>'nnwnnnwwn','T'=>'nnnnwnwwn','U'=>'wwnnnnnnw','V'=>'nwwnnnnnw','W'=>'wwwnnnnnn','X'=>'nwnnwnnnw','Y'=>'wwnnwnnnn','Z'=>'nwwnwnnnn','-'=>'nwnnnnwnw','.'=>'wwnnnnwnn',' '=>'nwwnnnwnn','*'=>'nwnnwnwnn'];
        $value='*'.strtoupper($value).'*';$x=10;$bars='';foreach(str_split($value) as $char){if(!isset($patterns[$char]))throw new RuntimeException('Unit code cannot be printed as Code 39');foreach(str_split($patterns[$char]) as $i=>$width){$w=$width==='w'?3:1;if($i%2===0)$bars.='<rect x="'.$x.'" y="4" width="'.$w.'" height="44"/>';$x+=$w;}$x+=1;}
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.($x+10).' 68" role="img" aria-label="Barcode '.htmlspecialchars($value,ENT_QUOTES).'"><rect width="100%" height="100%" fill="white"/>'.$bars.'<text x="50%" y="63" text-anchor="middle" font-family="monospace" font-size="10">'.htmlspecialchars(trim($value,'*'),ENT_QUOTES).'</text></svg>';
    }

    public function discounts(): array
    {
        $this->db->exec("UPDATE catalog_discount_campaigns SET status='expired' WHERE status='active' AND ends_at<NOW()");
        $rows=$this->db->query("SELECT d.*,p.title product_title,v.name variant_name,CONCAT(cp.first_name,' ',cp.last_name) creator_name,CONCAT(ap.first_name,' ',ap.last_name) approver_name,(SELECT COUNT(*) FROM catalog_discount_redemptions r WHERE r.campaign_id=d.id) redemptions,(SELECT COALESCE(SUM(r.amount),0) FROM catalog_discount_redemptions r WHERE r.campaign_id=d.id) discount_cost FROM catalog_discount_campaigns d LEFT JOIN " . ReadReplicaService::qualifiedRef("uniform_catalog_products") . " p ON p.id=d.product_id LEFT JOIN uniform_catalog_variants v ON v.id=d.variant_id JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " cp ON cp.user_id=d.created_by LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " ap ON ap.user_id=d.approved_by ORDER BY d.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        return ['campaigns'=>$rows,'products'=>$this->db->query("SELECT id,title FROM uniform_catalog_products WHERE status<>'archived' ORDER BY title")->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function createDiscount(array $data,int $actor): array
    {
        $code=strtoupper(preg_replace('/[^A-Z0-9_-]/','',trim((string)($data['code']??''))));$name=trim((string)($data['name']??''));$type=(string)($data['discount_type']??'percentage');$value=(float)($data['discount_value']??0);$channel=(string)($data['channel']??'all');
        if(strlen($code)<3||$name==='')throw new RuntimeException('Offer code and name are required');if(!in_array($type,['percentage','fixed'],true)||$value<=0||($type==='percentage'&&$value>100))throw new RuntimeException('Enter a valid percentage or fixed discount');if(!in_array($channel,['all','public','internal','physical'],true))throw new RuntimeException('Invalid offer channel');
        $starts=(string)($data['starts_at']??'');$ends=(string)($data['ends_at']??'');if(!$starts||!$ends||strtotime($ends)<=strtotime($starts))throw new RuntimeException('The offer end must be after its start');
        $this->db->prepare("INSERT INTO catalog_discount_campaigns(code,name,description,discount_type,discount_value,maximum_discount,minimum_order,channel,product_id,variant_id,starts_at,ends_at,redemption_limit,per_buyer_limit,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending_approval',?)")->execute([$code,$name,trim((string)($data['description']??''))?:null,$type,$value,(float)($data['maximum_discount']??0)?:null,max(0,(float)($data['minimum_order']??0)),$channel,(int)($data['product_id']??0)?:null,(int)($data['variant_id']??0)?:null,$starts,$ends,(int)($data['redemption_limit']??0)?:null,(int)($data['per_buyer_limit']??0)?:null,$actor]);$id=(int)$this->db->lastInsertId();Logger::audit('catalog_discount_submitted','catalog_discount_campaign',$id,'Catalogue offer submitted for approval',['code'=>$code,'type'=>$type,'value'=>$value,'channel'=>$channel]);return ['id'=>$id,'status'=>'pending_approval'];
    }

    public function decideDiscount(int $id,string $decision,?string $note,int $actor): array
    {
        if(!in_array($decision,['active','rejected','paused'],true))throw new RuntimeException('Invalid offer decision');$stmt=$this->db->prepare('SELECT status,code,ends_at FROM catalog_discount_campaigns WHERE id=? FOR UPDATE');$this->db->beginTransaction();try{$stmt->execute([$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Offer not found');if($decision==='active'&&!in_array($row['status'],['pending_approval','paused'],true))throw new RuntimeException('Only pending or paused offers can be activated');if($decision==='active'&&strtotime((string)$row['ends_at'])<=time())throw new RuntimeException('An offer that has already ended cannot be activated');$this->db->prepare('UPDATE catalog_discount_campaigns SET status=?,approved_by=?,approved_at=NOW(),approval_note=? WHERE id=?')->execute([$decision,$actor,$note,$id]);$this->db->commit();Logger::audit('catalog_discount_decided','catalog_discount_campaign',$id,'Catalogue offer decision recorded',['code'=>$row['code'],'from'=>$row['status'],'to'=>$decision]);return ['id'=>$id,'status'=>$decision];}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function quoteDiscount(?string $code,string $channel,float $subtotal,array $lines,string $buyerType,?int $buyerId): ?array
    {
        $code=strtoupper(trim((string)$code));if($code==='')return null;$stmt=$this->db->prepare("SELECT * FROM catalog_discount_campaigns WHERE code=? AND status='active' AND starts_at<=NOW() AND ends_at>=NOW() AND channel IN ('all',?)");$stmt->execute([$code,$channel]);$offer=$stmt->fetch(PDO::FETCH_ASSOC);if(!$offer)throw new RuntimeException('This offer code is invalid or inactive');if($subtotal<(float)$offer['minimum_order'])throw new RuntimeException('This offer requires a minimum order of KES '.number_format((float)$offer['minimum_order'],2));
        if($offer['redemption_limit']){$s=$this->db->prepare('SELECT COUNT(*) FROM catalog_discount_redemptions WHERE campaign_id=?');$s->execute([$offer['id']]);if((int)$s->fetchColumn()>=(int)$offer['redemption_limit'])throw new RuntimeException('This offer has reached its redemption limit');}
        if($buyerId&&$offer['per_buyer_limit']){$s=$this->db->prepare('SELECT COUNT(*) FROM catalog_discount_redemptions WHERE campaign_id=? AND buyer_type=? AND buyer_id=?');$s->execute([$offer['id'],$buyerType,$buyerId]);if((int)$s->fetchColumn()>=(int)$offer['per_buyer_limit'])throw new RuntimeException('This buyer has already used this offer the maximum number of times');}
        $eligible=$subtotal;if($offer['product_id']){$eligible=0;foreach($lines as $line)if((int)$line['product_id']===(int)$offer['product_id']&&(!$offer['variant_id']||(int)($line['variant_id']??0)===(int)$offer['variant_id']))$eligible+=(float)$line['line_total'];if($eligible<=0)throw new RuntimeException('This offer does not apply to the selected products');}
        $amount=$offer['discount_type']==='percentage'?$eligible*((float)$offer['discount_value']/100):(float)$offer['discount_value'];if($offer['maximum_discount'])$amount=min($amount,(float)$offer['maximum_discount']);$amount=round(min($amount,$subtotal),2);return ['campaign'=>$offer,'amount'=>$amount];
    }

    public function recordDiscount(int $orderId,array $quote,string $buyerType,?int $buyerId,int $actor): void
    {
        $offer=$quote['campaign'];$this->db->prepare('INSERT INTO catalog_order_discounts(order_id,campaign_id,discount_code,description,amount,applied_by) VALUES(?,?,?,?,?,?)')->execute([$orderId,$offer['id'],$offer['code'],$offer['name'],$quote['amount'],$actor?:null]);$this->db->prepare('INSERT INTO catalog_discount_redemptions(campaign_id,order_id,buyer_type,buyer_id,amount) VALUES(?,?,?,?,?)')->execute([$offer['id'],$orderId,$buyerType,$buyerId,$quote['amount']]);
    }
}
