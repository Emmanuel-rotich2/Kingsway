<?php
/** Internal staff shopping catalogue. This page never exposes management controls. */


?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/pages/product-catalog.css?v=<?= asset_version('css/pages/product-catalog.css') ?>">

<div class="staff-catalog-page">
  <section class="staff-catalog-cover">
    <div class="staff-catalog-cover-copy">
      <h1>Products<br><em>Catalogue</em></h1>
      <p>Uniforms and staff-only Kingsway merchandise, presented as one curated school collection.</p>
      <a href="#staffCatalogueCollection">Explore the collection</a>
    </div>
    <div class="staff-catalog-cover-art" aria-hidden="true">
      <div class="staff-catalog-book book-back"><small>Kingsway Collection</small><strong>2026</strong></div>
      <div class="staff-catalog-book book-front"><img src="<?= htmlspecialchars($appBase) ?>/uploads/school_assets/official_school_logo.png" alt=""><small>Official internal edition</small><strong>PRODUCTS<br>CATALOGUE</strong><span>In God We Soar</span></div>
    </div>
  </section>

  <section class="staff-catalog-collection" id="staffCatalogueCollection">
    <header class="staff-catalog-heading">
      <div><h2>Kingsway collection</h2><p>Public uniforms plus merchandise reserved for the internal school community.</p></div>
      <div><a class="btn btn-outline-success mb-2" href="<?= htmlspecialchars($appBase) ?>/home.php?route=internal_catalog_account"><i class="bi bi-bag me-1" aria-hidden="true"></i>My cart, wishlist &amp; orders</a><div class="catalog-search"><i class="bi bi-search" aria-hidden="true"></i><label class="visually-hidden" for="sicSearch">Search the collection</label><input type="search" id="sicSearch" placeholder="Search the collection…" autocomplete="off"><span id="sicCount">0 items</span></div></div>
    </header>
    <nav class="catalog-tabs" id="sicCategories" aria-label="Catalogue collections"></nav>
    <div id="sicError" class="alert alert-danger d-none"></div>
    <div class="staff-catalog-spread" id="sicProducts"><div class="catalog-loading"><div class="spinner-border spinner-border-sm me-2"></div>Opening catalogue…</div></div>
  </section>
</div>

<?php asset_script($appBase, 'js/pages/internal_products_catalog.js'); ?>
