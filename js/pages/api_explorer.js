// Developer API Explorer - dynamically exposes every API endpoint with a test harness
(function () {
    const state = {
        endpoints: [],
        filtered: [],
        selected: null,
        permissionMap: window.ENDPOINT_PERMISSIONS || {}
    };

    const els = {};

    function initElements() {
        els.search = document.getElementById('apiSearch');
        els.namespaceFilter = document.getElementById('namespaceFilter');
        els.refreshBtn = document.getElementById('refreshEndpoints');
        els.tableBody = document.getElementById('apiEndpointsBody');
        els.selectedEndpointLabel = document.getElementById('selectedEndpointLabel');
        els.selectedNamespaceLabel = document.getElementById('selectedNamespaceLabel');
        els.permissionHint = document.getElementById('permissionHint');
        els.payload = document.getElementById('apiPayload');
        els.result = document.getElementById('apiResult');
        els.invokeBtn = document.getElementById('invokeEndpoint');
        els.loadSample = document.getElementById('loadSample');
    }

    function collectEndpoints() {
        const api = window.API || {};
        const endpoints = [];
        Object.keys(api).forEach(ns => {
            const bucket = api[ns];
            if (!bucket || typeof bucket !== 'object') return;
            Object.keys(bucket).forEach(method => {
                if (typeof bucket[method] === 'function') {
                    endpoints.push({ ns, method, key: `${ns}.${method}` });
                }
            });
        });
        state.endpoints = endpoints.sort((a, b) => a.key.localeCompare(b.key));
        state.filtered = state.endpoints;
        renderNamespaceOptions();
        renderTable();
    }

    function renderNamespaceOptions() {
        const namespaces = [...new Set(state.endpoints.map(e => e.ns))].sort();
        els.namespaceFilter.innerHTML = '<option value="">All Namespaces</option>' +
            namespaces.map(ns => `<option value="${ns}">${ns}</option>`).join('');
    }

    function renderTable() {
        const rows = state.filtered.map(ep => `
            <tr>
                <td>${ep.method}</td>
                <td>${ep.ns}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" data-key="${ep.key}">
                        Test
                    </button>
                </td>
            </tr>
        `);
        els.tableBody.innerHTML = rows.join('') || '<tr><td colspan="3" class="text-center text-muted py-3">No endpoints found</td></tr>';
        els.tableBody.querySelectorAll('button[data-key]').forEach(btn => {
            btn.addEventListener('click', () => selectEndpoint(btn.dataset.key));
        });
    }

    function filterEndpoints() {
        const term = (els.search.value || '').toLowerCase();
        const nsFilter = els.namespaceFilter.value;
        state.filtered = state.endpoints.filter(ep => {
            const matchTerm = !term || ep.key.toLowerCase().includes(term);
            const matchNs = !nsFilter || ep.ns === nsFilter;
            return matchTerm && matchNs;
        });
        renderTable();
    }

    function selectEndpoint(key) {
        state.selected = state.endpoints.find(e => e.key === key) || null;
        if (!state.selected) return;
        els.selectedEndpointLabel.textContent = state.selected.method;
        els.selectedNamespaceLabel.textContent = state.selected.ns;
        const perm = lookupPermission(state.selected);
        els.permissionHint.textContent = perm ? `Requires permission: ${perm}` : '';
        els.payload.value = '';
        els.result.textContent = 'Ready to call...';
    }

    function lookupPermission(ep) {
        const direct = state.permissionMap[`/${ep.ns}/${ep.method}`];
        return typeof direct === 'string' ? direct : null;
    }

    async function invokeSelected() {
        if (!state.selected) return;
        const { ns, method } = state.selected;
        const fn = window.API?.[ns]?.[method];
        if (typeof fn !== 'function') {
            els.result.textContent = 'Selected endpoint is not callable.';
            return;
        }
        let args = [];
        const raw = els.payload.value.trim();
        if (raw) {
            try {
                const parsed = JSON.parse(raw);
                args = Array.isArray(parsed) ? parsed : [parsed];
            } catch (e) {
                els.result.textContent = 'Invalid JSON payload';
                return;
            }
        }
        els.result.textContent = 'Calling...';
        try {
            const res = await fn(...args);
            els.result.textContent = JSON.stringify(res, null, 2);
        } catch (err) {
            els.result.textContent = `Error: ${err.message || err}`;
        }
    }

    function loadSample() {
        if (!state.selected) return;
        // Provide a basic sample based on method naming conventions
        const { method } = state.selected;
        if (method.startsWith('get') || method === 'index') {
            els.payload.value = '';
        } else if (method.startsWith('update') || method.startsWith('create') || method.startsWith('save')) {
            els.payload.value = JSON.stringify({ example: true }, null, 2);
        } else {
            els.payload.value = JSON.stringify({}, null, 2);
        }
    }

    function bindEvents() {
        els.search.addEventListener('input', filterEndpoints);
        els.namespaceFilter.addEventListener('change', filterEndpoints);
        els.refreshBtn.addEventListener('click', () => {
            collectEndpoints();
            filterEndpoints();
        });
        els.invokeBtn.addEventListener('click', invokeSelected);
        els.loadSample.addEventListener('click', loadSample);
    }

    document.addEventListener('DOMContentLoaded', () => {
        initElements();
        collectEndpoints();
        bindEvents();
    });
})();
