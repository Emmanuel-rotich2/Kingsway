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

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-circle me-2"></i>Sports</h4>
                    <p class="text-muted mb-0">Manage school sports teams, fixtures, and results</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" id="addTeamBtn">
                        <i class="bi bi-plus-lg me-1"></i> New Team
                    </button>
                    <button class="btn btn-outline-primary" id="addFixtureBtn">
                        <i class="bi bi-calendar me-1"></i> Add Fixture
                    </button>
                    <button class="btn btn-outline-success" id="recordResultBtn">
                        <i class="bi bi-trophy me-1"></i> Record Result
                    </button>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#teamsTab">Teams</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#fixturesTab">Fixtures</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#resultsTab">Results</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#standingsTab">Standings</a></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="teamsTab">
            <div class="row" id="teamsGrid">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p>Loading teams...</p>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="fixturesTab">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="fixturesTable">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Home Team</th>
                                    <th scope="col"></th>
                                    <th scope="col">Opponent</th>
                                    <th scope="col">Venue</th>
                                    <th scope="col">Time</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="resultsTab">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="resultsTable">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Team</th>
                                    <th scope="col">Opponent</th>
                                    <th scope="col">Score</th>
                                    <th scope="col">Result</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="standingsTab">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="standingsTable">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Team</th>
                                    <th scope="col">P</th>
                                    <th scope="col">W</th>
                                    <th scope="col">D</th>
                                    <th scope="col">L</th>
                                    <th scope="col">GD</th>
                                    <th scope="col">Pts</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Team Modal -->
    <div class="modal fade" id="addTeamModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="teamForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="teamName" class="form-label">Team Name</label>
                            <input type="text" class="form-control" id="teamName" required>
                        </div>
                        <div class="mb-3">
                            <label for="teamSportType" class="form-label">Sport Type</label>
                            <select class="form-select" id="teamSportType">
                                <option value="">Select sport...</option>
                                <option value="football">Football</option>
                                <option value="basketball">Basketball</option>
                                <option value="volleyball">Volleyball</option>
                                <option value="rugby">Rugby</option>
                                <option value="athletics">Athletics</option>
                                <option value="swimming">Swimming</option>
                                <option value="tennis">Tennis</option>
                                <option value="cricket">Cricket</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="teamCoach" class="form-label">Coach</label>
                            <input type="text" class="form-control" id="teamCoach">
                        </div>
                        <div class="mb-3">
                            <label for="teamSeason" class="form-label">Season</label>
                            <input type="text" class="form-control" id="teamSeason" value="<?= date('Y') ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveTeamBtn">Save Team</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Fixture Modal -->
    <div class="modal fade" id="addFixtureModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>New Fixture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="fixtureForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="fixtureTeamId" class="form-label">Team</label>
                            <select class="form-select" id="fixtureTeamId" required></select>
                        </div>
                        <div class="mb-3">
                            <label for="fixtureOpponent" class="form-label">Opponent</label>
                            <input type="text" class="form-control" id="fixtureOpponent" required>
                        </div>
                        <div class="mb-3">
                            <label for="fixtureDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="fixtureDate" required>
                        </div>
                        <div class="mb-3">
                            <label for="fixtureTime" class="form-label">Time</label>
                            <input type="time" class="form-control" id="fixtureTime">
                        </div>
                        <div class="mb-3">
                            <label for="fixtureVenue" class="form-label">Venue</label>
                            <input type="text" class="form-control" id="fixtureVenue">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveFixtureBtn">Save Fixture</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Record Result Modal -->
    <div class="modal fade" id="recordResultModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-trophy me-2"></i>Record Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="resultForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="resultFixtureId" class="form-label">Fixture</label>
                            <select class="form-select" id="resultFixtureId" required></select>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="resultOurScore" class="form-label">Our Score</label>
                                <input type="number" class="form-control" id="resultOurScore" min="0" required>
                            </div>
                            <div class="col">
                                <label for="resultOpponentScore" class="form-label">Opponent Score</label>
                                <input type="number" class="form-control" id="resultOpponentScore" min="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="resultOutcome" class="form-label">Outcome</label>
                            <select class="form-select" id="resultOutcome" required>
                                <option value="">Select outcome...</option>
                                <option value="win">Win</option>
                                <option value="loss">Loss</option>
                                <option value="draw">Draw</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="saveResultBtn">Save Result</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/sports.js'); ?>
