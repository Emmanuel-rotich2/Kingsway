/**
 * js/pages/student_card_verification.js
 *
 * Page controller for student_portal.php (QR ID-card verification).
 * Fetches GET /api/public/student-verification/{studentId}?scope=... and
 * renders the sections the server authorized. No SQL, no scraping of PHP —
 * everything arrives as JSON over the API; the server owns visibility.
 */
(function () {
    'use strict';

    var params = new URLSearchParams(window.location.search);
    var studentId = parseInt(params.get('student_id') || params.get('id') || '0', 10);

    var esc = function (value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };
    var row = function (label, value) {
        return '<div class="info-row"><div class="info-label">' + esc(label) + '</div>'
            + '<div class="info-value">' + esc(value == null || value === '' ? 'N/A' : value) + '</div></div>';
    };
    var card = function (icon, title, innerHtml) {
        return '<div class="info-card"><h4><i class="bi bi-' + icon + '"></i> ' + esc(title) + '</h4>' + innerHtml + '</div>';
    };
    var empty = function (text) { return '<p class="text-muted">' + esc(text) + '</p>'; };

    function renderHeader(payload) {
        var s = payload.student;
        var isPublic = payload.viewing_as === 'public';
        var photo = s.photo_url && s.photo_url !== '' ? s.photo_url : 'uploads/students/avatar.jpg';
        document.getElementById('header').innerHTML =
            '<div class="row align-items-center w-100 m-0">'
            + '<div class="col-md-2 text-center"><img src="' + esc(photo) + '" alt="Learner Photo" class="student-photo" onerror="this.onerror=null;this.src=\'images/official_school_logo.png\';"></div>'
            + '<div class="col-md-10">'
            + '<div class="mb-1 text-white-50 small"><i class="bi bi-shield-check me-1"></i>Kingsway Preparatory School</div>'
            + '<h2 class="fw-bold mb-0">' + esc(s.first_name + ' ' + s.last_name) + '</h2>'
            + '<p class="mb-2"><strong>Class:</strong> ' + esc((s.class_name || 'N/A') + ' - ' + (s.stream_name || ''))
            + (!isPublic && s.admission_no ? ' | <strong>Admission No:</strong> ' + esc(s.admission_no) : '') + '</p>'
            + '<p class="mb-0">' + (isPublic
                ? '<span class="role-badge"><i class="bi bi-qr-code"></i> Scanned from ID card — <a href="index.php" style="color:#fff;text-decoration:underline;">staff sign-in for role-based details</a></span>'
                : '<span class="role-badge"><i class="bi bi-person-shield"></i> Viewing as: ' + esc(payload.viewing_as) + (params.get('scope') ? ' <span class="ms-1 small">(scope: ' + esc(params.get('scope')) + ')</span>' : '') + '</span>')
            + '</p></div></div>';
        document.title = 'ID Card Verification - ' + s.first_name + ' ' + s.last_name + ' | Kingsway Preparatory School';
    }

    function renderSections(payload) {
        var s = payload.student;
        var d = payload.data || {};
        var allowed = payload.sections_allowed || [];
        var has = function (section) { return allowed.indexOf('all') !== -1 || allowed.indexOf(section) !== -1; };
        var out = '';

        out += card('person', 'Basic Information',
            row('Full Name', s.first_name + ' ' + s.last_name)
            + row('Class', (s.class_name || 'N/A') + (s.stream_name ? ' - ' + s.stream_name : ''))
            + (payload.viewing_as !== 'public'
                ? row('Admission No', s.admission_no) + row('Gender', s.gender) + row('Date of Birth', s.date_of_birth)
                + '<div class="info-row"><div class="info-label">Status:</div><div class="info-value"><span class="badge bg-' + (s.status === 'active' ? 'success' : 'warning') + '">' + esc(s.status ? s.status.charAt(0).toUpperCase() + s.status.slice(1) : '') + '</span></div></div>'
                : ''));

        if (has('academic')) {
            var perf = (d.academic || []).map(function (p) { return row((p.term || '') + ' ' + (p.academic_year || ''), p.grade || 'N/A'); }).join('');
            out += card('mortarboard', 'Academic Information',
                row('Year Joined', s.year_joined) + row('Expected Graduation', s.expected_graduation_year)
                + (d.academic && d.academic.length ? '<h5 class="mt-3">Recent Performance</h5>' + perf : ''));
        }

        if (has('financial')) {
            var fees = (d.fees || []).map(function (f) {
                return '<div class="info-row"><div class="info-label">' + esc(f.term + ' ' + f.academic_year) + ':</div>'
                    + '<div class="info-value">' + esc(f.amount || '0') + ' - <span class="badge bg-' + (f.status === 'paid' ? 'success' : 'warning') + '">' + esc(f.status || 'pending') + '</span></div></div>';
            }).join('');
            out += card('cash-wave', 'Financial Information', fees || empty('No financial records found.'));
        }

        if (has('transportation')) {
            if (d.transport) {
                var t = d.transport;
                var elig = t.eligibility || {};
                out += card('bus-front', 'Transportation - Ride Check',
                    '<div class="alert alert-' + (elig.allowed ? 'success' : 'danger') + ' py-2 px-3"><strong><i class="bi bi-' + (elig.allowed ? 'check-circle' : 'x-circle') + '"></i> ' + esc(elig.label || '') + '</strong><div class="small mt-1">' + esc(elig.reason || '') + '</div></div>'
                    + row('Route', (t.route_name || 'N/A') + (t.route_code ? ' (' + t.route_code + ')' : ''))
                    + row('Vehicle', (t.vehicle_number || 'N/A') + (t.vehicle_type ? ' - ' + t.vehicle_type : ''))
                    + row('Driver', t.driver_name)
                    + row('Pickup Point', (t.pickup_point || 'N/A') + (t.pickup_time ? ' at ' + String(t.pickup_time).slice(0, 5) : ''))
                    + row('Drop-off Point', (t.dropoff_point || 'N/A') + (t.dropoff_time ? ' at ' + String(t.dropoff_time).slice(0, 5) : ''))
                    + (d.transport_bill ? row('Monthly Bill', d.transport_bill.billing_month + ' - KES ' + Number(d.transport_bill.amount_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' (' + (d.transport_bill.payment_status || 'N/A') + ')') : ''));
            } else {
                out += card('bus-front', 'Transportation - Ride Check',
                    '<div class="alert alert-danger py-2 px-3 mb-0"><strong><i class="bi bi-x-circle"></i> Not Subscribed</strong><div class="small mt-1">This learner has no transport subscription and should NOT board the school bus.</div></div>');
            }
        }

        if (has('authorization')) {
            var c = d.id_card;
            out += card('person-badge', 'Authorization Information', c
                ? row('Card Number', c.card_number) + row('Issue Date', c.issue_date) + row('Expiry Date', c.expiry_date)
                    + '<div class="info-row"><div class="info-label">Status:</div><div class="info-value"><span class="badge bg-success">' + esc(c.status || 'N/A') + '</span></div></div>'
                : empty('No active ID card found.'));
        }

        if (has('medical')) {
            out += card('heartbeat', 'Medical Information',
                (d.medical || []).map(function (m) { return row(m.date, m.notes); }).join('') || empty('No medical records found.'));
        }
        if (has('sports')) {
            out += card('person-walking', 'Sports Information',
                (d.sports || []).map(function (sp) { return row('Sport', sp.sport_name) + row('Position', sp.position); }).join('') || empty('No sports records found.'));
        }
        if (has('library')) {
            out += card('book', 'Library Information',
                (d.library || []).map(function (b) { return row('Book', b.book_title) + row('Due Date', b.due_date); }).join('') || empty('No library records found.'));
        }
        if (has('guidance')) {
            out += card('chats', 'Guidance & Counseling',
                (d.guidance || []).map(function (g) { return row(g.date, g.notes); }).join('') || empty('No guidance records found.'));
        }

        document.getElementById('sections').innerHTML = out;
        document.getElementById('footer').hidden = false;
    }

    function fail(message) {
        document.getElementById('header').innerHTML =
            '<div class="alert alert-warning w-100 mb-0"><i class="bi bi-exclamation-triangle"></i> ' + esc(message) + '</div>';
    }

    if (!studentId) { fail('No learner identifier in the link. Scan a valid ID card.'); return; }

    var url = '/public/student-verification/' + studentId + (params.get('scope') ? '?scope=' + encodeURIComponent(params.get('scope')) : '');
    var request = (window.API && window.API.call) ? window.API.call(url, 'GET') : null;
    (request || fetch('api' + url /* public endpoint; session cookie widens sections server-side */, { credentials: 'include' }).then(function (r) { return r.json(); }))
        .then(function (res) {
            var payload = res && (res.data || res);
            if (!payload || !payload.student) { fail('Learner not found.'); return; }
            renderHeader(payload);
            renderSections(payload);
        })
        .catch(function () { fail('Verification is temporarily unavailable. Please try again.'); });
})();
