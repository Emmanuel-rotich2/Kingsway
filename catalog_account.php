<?php
$appBase=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'')),'/');if($appBase==='.')$appBase='';
$pageTitle='My Uniform Store Account';$activePage='uniforms';$pageScript='catalog_account';
require_once __DIR__.'/public/layout/public_data.php';
?>
<?php include __DIR__.'/public/layout/header.php'; ?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/pages/product-catalog.css?v=<?= asset_version('css/pages/product-catalog.css') ?>">
<section class="section"><div class="container-fluid px-lg-5"><div class="catalog-account-page p-0">
 <header class="catalog-account-head"><div><small>Parent uniform store</small><h1>Cart, wishlist &amp; orders</h1><p class="mb-0">Choose products for your learner and track collection from one place.</p></div><a class="btn btn-light" href="<?= htmlspecialchars($appBase) ?>/index.php?route=r39d07ccbcf8a">Continue shopping</a></header>
 <div id="pcaAuth" class="alert alert-warning d-none mt-3">Sign in to the Parent Portal before using your shopping account. <a href="<?= htmlspecialchars($appBase) ?>/parent_portal.php">Parent sign in</a></div><div id="pcaMessage" class="mt-3"></div>
 <nav class="catalog-account-tabs"><button class="active" data-tab="cart">Cart <span id="pcaCartBadge">0</span></button><button data-tab="wishlist">Wishlist</button><button data-tab="orders">Orders &amp; reviews</button></nav>
 <section id="pcaCart" class="catalog-account-panel"></section><section id="pcaWishlist" class="catalog-account-panel d-none"></section><section id="pcaOrders" class="catalog-account-panel d-none"></section>
</div></div></section>
<?php include __DIR__.'/public/layout/footer.php'; ?>
