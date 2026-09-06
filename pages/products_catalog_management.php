<?php
/**
 * Products Catalogue Management — School Administrator / Uniform Store Manager.
 * Route key: products_catalog_management
 *
 * This is operational inventory UI, not a buyer catalogue.
 */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/pages/product-catalog.css?v=<?= asset_version('css/pages/product-catalog.css') ?>">

<div class="container-fluid px-4 py-4 internal-catalog-shell">

    <!-- Page Header -->
    <div class="internal-catalog-hero mb-4">
        <div>
            <span class="internal-catalog-kicker">Uniform store workspace</span>
            <h1 id="ipcPageTitle">Products Catalogue Management</h1>
            <p>Manage product visibility, presentation, variants, images, sizes, pricing and incoming stock.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary ipc-manage-only d-none" id="ipcAddProductBtn">
                <i class="bi bi-plus-lg me-1"></i>Add Product
            </button>
            <button class="btn btn-outline-success" id="ipcRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4 ipc-manager-only d-none" id="ipcManagementSummary">
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-blue">
                <div class="dash-stat-value" id="ipcStatTotal">0</div>
                <div class="dash-stat-label">Catalogue Products</div>
                <div class="dash-stat-sub" id="ipcStatTotalSub">All statuses</div>
                <i class="bi bi-collection dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="ipcStatLive">0</div>
                <div class="dash-stat-label">Live</div>
                <div class="dash-stat-sub">Active &amp; published</div>
                <i class="bi bi-store dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value" id="ipcStatDraft">0</div>
                <div class="dash-stat-label">Private / Draft</div>
                <div class="dash-stat-sub">Internal-only or being prepared</div>
                <i class="bi bi-pencil-square dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-red">
                <div class="dash-stat-value" id="ipcStatArchived">0</div>
                <div class="dash-stat-label">Archived</div>
                <div class="dash-stat-sub">Hidden from catalogue</div>
                <i class="bi bi-archive dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-6 col-lg-5">
                    <input type="search" class="form-control" id="ipcSearch" placeholder="Search by title, code or description…">
                </div>
                <div class="col-md-3 col-lg-3 ipc-manager-only d-none">
                    <select class="form-select" id="ipcStatusFilter">
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                <div class="col-md-3 col-lg-4 text-md-end">
                    <span class="text-muted small" id="ipcCount">0 items shown</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Image-led catalogue management grid -->
    <div class="internal-products-grid" id="ipcProductsBody">
        <div class="catalog-loading"><div class="spinner-border spinner-border-sm me-2"></div>Loading collection…</div>
    </div>
    <div class="catalog-management-note">
        <div class="p-3 border-top text-muted small d-none ipc-manage-only" id="ipcHelpRow">
            You can manage this catalogue (create, publish, upload images, and record internal sales) because you hold an inventory management permission.
        </div>
    </div>

</div>

<!-- Product modal (add / edit) -->
<div class="modal fade" id="ipcProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="ipcProductForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="ipcProductModalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="ipcProductItem">Inventory item <span class="text-danger">*</span></label>
                        <select class="form-select" id="ipcProductItem" required>
                            <option value="">Select inventory item…</option>
                        </select>
                        <input type="hidden" id="ipcProductExistingId" value="0">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label" for="ipcProductCategory">Catalogue category</label><select class="form-select" id="ipcProductCategory"><option value="formal-uniform">Formal uniforms</option><option value="sportswear">Games &amp; sportswear</option><option value="bags">School bags</option><option value="branded-merchandise">Branded merchandise</option><option value="drinkware">Cups, mugs &amp; bottles</option><option value="stationery">Stationery</option><option value="accessories">Accessories</option></select></div>
                        <div class="col-md-6"><label class="form-label" for="ipcProductType">Product type</label><input class="form-control" id="ipcProductType" placeholder="e.g. blazer, sweater, tracksuit"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-4 mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="ipcCustomName"><label class="form-check-label" for="ipcCustomName">Optional printed name</label></div><div class="form-check"><input class="form-check-input" type="checkbox" id="ipcCustomNumber"><label class="form-check-label" for="ipcCustomNumber">Optional player number</label></div></div>
                    <div class="mb-3">
                        <label class="form-label" for="ipcProductTitle">Catalogue title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ipcProductTitle" maxlength="150" required
                               placeholder="e.g. Boys navy blazer (Spec 1)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ipcProductDesc">Description</label>
                        <textarea class="form-control" id="ipcProductDesc" rows="3" maxlength="500"
                                  placeholder="Visible to buyers in the public storefront."></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="ipcProductStatus">Status</label>
                            <select class="form-select" id="ipcProductStatus">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="ipcProductPublished">
                                <label class="form-check-label" for="ipcProductPublished">Show in public catalogue</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        Active products always appear in the internal staff catalogue. Enable public visibility only for products that parents and guests should see.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="ipcProductSaveBtn">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Variant, sizes, prices and stock manager -->
<div class="modal fade" id="ipcOptionsModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h5 class="modal-title">Variants, sizes, prices &amp; stock</h5><p class="small text-muted mb-0" id="ipcOptionsProductInfo"></p></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" id="ipcOptionsProductId"><input type="hidden" id="ipcOptionsBaseItemId">
        <div class="row g-4"><div class="col-lg-6"><h6>Colour / design variant</h6><form id="ipcVariantForm" class="card card-body bg-light border-0"><input type="hidden" id="ipcVariantId"><div class="row g-2"><div class="col-md-6"><label class="form-label">Name</label><input class="form-control" id="ipcVariantName" required placeholder="Deep Green"></div><div class="col-md-6"><label class="form-label">Code</label><input class="form-control" id="ipcVariantCode" required placeholder="deep-green"></div><div class="col-md-6"><label class="form-label">Colour name</label><input class="form-control" id="ipcVariantColor"></div><div class="col-md-3"><label class="form-label">Swatch</label><input type="color" class="form-control form-control-color w-100" id="ipcVariantSwatch" value="#0b5d3b"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" id="ipcVariantStatus"><option value="draft">Draft</option><option value="active">Active</option><option value="archived">Archived</option></select></div><div class="col-12"><label class="form-label">Variant stock item <span class="text-muted fw-normal">(optional)</span></label><select class="form-select" id="ipcVariantItem"><option value="">Share the product's base stock</option></select><div class="form-text">Choose a separate inventory SKU when this colour has independent sizes, price and stock.</div></div></div><button class="btn btn-primary mt-3" type="submit">Save variant</button></form><div class="mt-3" id="ipcVariantsList"></div></div>
        <div class="col-lg-6"><h6>Size, price and stock</h6><form id="ipcSizeForm" class="card card-body bg-light border-0"><div class="row g-2"><div class="col-md-6"><label class="form-label">Inventory item</label><select class="form-select" id="ipcSizeItem" required></select></div><div class="col-md-3"><label class="form-label">Size</label><input class="form-control" id="ipcSizeCode" required placeholder="M"></div><div class="col-md-3"><label class="form-label">Type</label><select class="form-select" id="ipcSizeType"><option value="clothing">Clothing</option><option value="waist">Waist</option><option value="shoe">Shoe</option><option value="one_size">One size</option></select></div><div class="col-md-4"><label class="form-label">Price (KES)</label><input type="number" min="0" step="0.01" class="form-control" id="ipcSizePrice" required></div><div class="col-md-4"><label class="form-label">Stock available</label><input type="number" min="0" class="form-control" id="ipcSizeStock" required></div><div class="col-md-4"><label class="form-label">Reorder level</label><input type="number" min="0" class="form-control" id="ipcSizeReorder" value="0"></div></div><button class="btn btn-success mt-3" type="submit">Save size &amp; stock</button></form><div class="mt-3" id="ipcSizesList"></div></div></div>
    </div></div></div></div>

<!-- Product image modal -->
<div class="modal fade" id="ipcImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="ipcImageForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Upload Product Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ipcImageProductId" value="0">
                    <p class="text-muted small mb-3" id="ipcImageProductInfo">—</p>
                    <div class="mb-3">
                        <label class="form-label" for="ipcImageFile">Image file <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="ipcImageFile" accept="image/*" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ipcImageAlt">Alt text</label>
                        <input type="text" class="form-control" id="ipcImageAlt" maxlength="150"
                               placeholder="Short description for accessibility">
                    </div>
                    <div class="row g-3 mb-3"><div class="col-md-7"><label class="form-label" for="ipcImageVariant">Variant</label><select class="form-select" id="ipcImageVariant"><option value="">General product image</option></select></div><div class="col-md-5"><label class="form-label" for="ipcImageView">Image view</label><select class="form-select" id="ipcImageView"><option value="catalog">Catalogue</option><option value="front">Front</option><option value="back">Back</option><option value="detail">Detail</option><option value="lifestyle">Lifestyle</option><option value="size_guide">Size guide</option></select></div></div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="ipcImagePrimary" checked>
                        <label class="form-check-label" for="ipcImagePrimary">Use as the primary product image</label>
                    </div>
                    <div class="mt-4">
                        <div class="small fw-semibold mb-2">Current images</div>
                        <div class="d-flex flex-wrap gap-2" id="ipcCurrentImages"><span class="text-muted small">No images uploaded.</span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="ipcImageSaveBtn">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/products_catalog_management.js'); ?>
