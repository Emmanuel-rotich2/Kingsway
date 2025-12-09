<!-- API Explorer (Developer Tool) -->
<div class="card shadow-sm mt-3">
    <div class="card-header bg-gradient bg-dark text-white d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0"><i class="bi bi-terminal"></i> API Explorer</h4>
            <small class="text-white-50">Developer-only tool to exercise every API endpoint from the browser</small>
        </div>
        <div class="text-white-50 small">
            <i class="bi bi-shield-lock"></i> Requires authentication; respects backend permissions.
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-warning d-flex align-items-start" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <div>
                <strong>Use carefully.</strong> Calls hit live endpoints and will perform real actions. Provide valid
                payloads where required.
            </div>
        </div>

        <!-- Filters and selection -->
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="apiSearch" class="form-control"
                        placeholder="Filter endpoints (e.g., students, finance.payments)">
                </div>
            </div>
            <div class="col-md-3">
                <select id="namespaceFilter" class="form-select">
                    <option value="">All Namespaces</option>
                </select>
            </div>
            <div class="col-md-3 text-end">
                <button class="btn btn-outline-secondary" id="refreshEndpoints"><i class="bi bi-arrow-clockwise"></i>
                    Refresh</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="border rounded p-2" style="max-height: 480px; overflow-y: auto;">
                    <table class="table table-sm align-middle mb-0" id="apiEndpointsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 55%">Endpoint</th>
                                <th style="width: 25%">Namespace</th>
                                <th style="width: 20%" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="apiEndpointsBody">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">Loading endpoints...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="border rounded p-3 h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <h6 class="mb-0" id="selectedEndpointLabel">Select an endpoint</h6>
                            <small class="text-muted" id="selectedNamespaceLabel"></small>
                        </div>
                        <div id="permissionHint" class="text-muted small"></div>
                    </div>

                    <label class="form-label">Payload (JSON or array of args)</label>
                    <textarea id="apiPayload" class="form-control mb-2" rows="6"
                        placeholder='{} or ["arg1", {"key": "value"}]'></textarea>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">Leave empty for endpoints that require no payload. Arrays are spread
                            as positional arguments.</small>
                        <div class="btn-group">
                            <button class="btn btn-secondary btn-sm" id="loadSample">Load sample</button>
                            <button class="btn btn-primary" id="invokeEndpoint"><i class="bi bi-play-circle"></i>
                                Call</button>
                        </div>
                    </div>

                    <label class="form-label">Result</label>
                    <pre id="apiResult" class="bg-light border rounded p-2 flex-grow-1"
                        style="min-height: 160px; overflow:auto;">Awaiting selection...</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Link to controller -->
<script src="/Kingsway/js/pages/api_explorer.js"></script>