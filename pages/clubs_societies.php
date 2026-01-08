<?php
/**
 * Clubs & Societies Page
 * 
 * Purpose: Manage student clubs and societies
 * Features:
 * - Club registration and management
 * - Member enrollment
 * - Activity scheduling
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-users-cog me-2"></i>Clubs & Societies</h4>
                    <p class="text-muted mb-0">Manage school clubs, societies, and student participation</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClubModal">
                    <i class="fas fa-plus me-1"></i> New Club
                </button>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 id="totalClubs">--</h2>
                    <p class="mb-0">Active Clubs</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="totalMembers">--</h2>
                    <p class="mb-0">Total Members</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="upcomingEvents">--</h2>
                    <p class="mb-0">Upcoming Events</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Clubs Grid -->
    <div class="row" id="clubsGrid">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p>Loading clubs...</p>
        </div>
    </div>
</div>

<!-- Add Club Modal -->
<div class="modal fade" id="addClubModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Club</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addClubForm">
                    <div class="mb-3">
                        <label class="form-label">Club Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category" required>
                            <option value="">Select Category</option>
                            <option value="academic">Academic</option>
                            <option value="sports">Sports</option>
                            <option value="arts">Arts & Culture</option>
                            <option value="service">Community Service</option>
                            <option value="religious">Religious</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Patron (Staff)</label>
                        <select class="form-select" name="patron_id">
                            <option value="">Select Patron</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meeting Day</label>
                        <select class="form-select" name="meeting_day">
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addClubForm" class="btn btn-primary">Create Club</button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/clubs_societies.js"></script>