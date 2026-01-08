<?php
/**
 * Sports Page
 * 
 * Purpose: Manage school sports activities
 * Features:
 * - Sports teams management
 * - Fixtures and results
 * - Student participation
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-futbol me-2"></i>Sports</h4>
                    <p class="text-muted mb-0">Manage school sports teams and activities</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                        <i class="fas fa-plus me-1"></i> New Team
                    </button>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addFixtureModal">
                        <i class="fas fa-calendar me-1"></i> Add Fixture
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#teamsTab">Teams</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#fixturesTab">Fixtures</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#resultsTab">Results</a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Teams Tab -->
        <div class="tab-pane fade show active" id="teamsTab">
            <div class="row" id="teamsGrid">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p>Loading teams...</p>
                </div>
            </div>
        </div>

        <!-- Fixtures Tab -->
        <div class="tab-pane fade" id="fixturesTab">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="fixturesTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Sport</th>
                                    <th>Opponent</th>
                                    <th>Venue</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Tab -->
        <div class="tab-pane fade" id="resultsTab">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="resultsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Sport</th>
                                    <th>Match</th>
                                    <th>Score</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/sports.js"></script>