<?php
/**
 * View Teaching Materials Page
 * Purpose: Intern-specific read-only view of teaching materials
 * Features: Browse and download teaching materials (read-only)
 * Block 4: Teaching Delivery
 * Role: Intern (9)
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-secondary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-folder2-open"></i> Teaching Materials
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="ViewTeachingMaterialsController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Materials</h6>
                        <h3 class="text-primary mb-0" id="totalMaterialsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Worksheets</h6>
                        <h3 class="text-success mb-0" id="worksheetsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Presentations</h6>
                        <h3 class="text-info mb-0" id="presentationsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Other Resources</h6>
                        <h3 class="text-warning mb-0" id="othersCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Search materials...">
            </div>
            <div class="col-md-3">
                <select id="subjectFilter" class="form-select">
                    <option value="">All Subjects</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="typeFilter" class="form-select">
                    <option value="">All Types</option>
                    <option value="Worksheet">Worksheet</option>
                    <option value="Notes">Notes</option>
                    <option value="Presentation">Presentation</option>
                    <option value="Video">Video</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" onclick="ViewTeachingMaterialsController.filter()">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </div>

        <!-- Materials Grid -->
        <div id="materialsGrid">
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-2">Loading teaching materials...</p>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/view_teaching_materials.js"></script>
