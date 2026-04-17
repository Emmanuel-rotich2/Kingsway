/**
 * Balances By Class Controller
 * Page: balances_by_class.php
 * Show fee collection and balances aggregated by class,
 * with per-student drill-down and full billing history modal.
 */
const BalancesByClassController = {
  state: {
    classBalances: [],
    classes: [],
    academicYears: [],
    totals: { expected: 0, collected: 0, pending: 0 },
    charts: {},
    selectedClassId: null,
    selectedClassName: '',
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await this.loadFilterOptions();
    this.bindEvents();
    await this.loadData();
  },

  async loadFilterOptions() {
    try {
      const [classesRes, yearsRes] = await Promise.all([
        window.API.academic.listClasses(),
        window.API.academic.listYears(),
      ]);

      const classes = classesRes?.success ? classesRes.data || [] : (classesRes?.data || []);
      const years = yearsRes?.success ? yearsRes.data || [] : (yearsRes?.data || []);

      this.state.classes = Array.isArray(classes) ? classes : [];
      this.state.academicYears = Array.isArray(years) ? years : [];

      const classSelect = document.getElementById('classSelect');
      if (classSelect) {
        this.state.classes.forEach(function(c) {
          const opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = c.name || c.class_name || '';
          classSelect.appendChild(opt);
        });
      }

      const yearSelect = document.getElementById('academicYearSelect');
      if (yearSelect) {
        this.state.academicYears.forEach(function(y) {
          const opt = document.createElement('option');
          opt.value = y.id;
          opt.textContent = y.year || y.name || '';
          if (y.is_current == 1 || y.is_current === '1') opt.selected = true;
          yearSelect.appendChild(opt);
        });
      }
    } catch (err) {
      console.error('Failed to load filter options:', err);
    }
  },

  bindEvents() {
    const exportBtn = document.getElementById('exportReport');
    if (exportBtn) exportBtn.addEventListener('click', () => this.exportCSV());

    const refreshBtn = document.getElementById('refreshData');
    if (refreshBtn) refreshBtn.addEventListener('click', () => this.loadData());

    const applyBtn = document.getElementById('applyFiltersBtn');
    if (applyBtn) applyBtn.addEventListener('click', () => this.onFiltersChange());

    const classSelect = document.getElementById('classSelect');
    if (classSelect) classSelect.addEventListener('change', () => this.onFiltersChange());

    const yearSelect = document.getElementById('academicYearSelect');
    if (yearSelect) yearSelect.addEventListener('change', () => this.loadData());

    const termSelect = document.getElementById('termSelect');
    if (termSelect) termSelect.addEventListener('change', () => this.onFiltersChange());
  },

  onFiltersChange() {
    const classSelect = document.getElementById('classSelect');
    const classId = classSelect ? classSelect.value : '';

    if (classId) {
      const selectedOpt = classSelect.options[classSelect.selectedIndex];
      this.state.selectedClassId = classId;
      this.state.selectedClassName = selectedOpt ? selectedOpt.textContent : '';
      this.loadClassBillingReport();
    } else {
      this.state.selectedClassId = null;
      this.state.selectedClassName = '';
      const tbody = document.getElementById('tbody_class_billing');
      if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-3">Select a class to view billing report</td></tr>';
      const nameEl = document.getElementById('selectedClassName');
      if (nameEl) nameEl.textContent = 'Select a class above';
    }

    this.loadData();
  },

  async loadData() {
    try {
      this.showTableLoading();

      const yearSelect = document.getElementById('academicYearSelect');
      const academicYearId = yearSelect ? yearSelect.value : '';

      const params = {};
      if (academicYearId) params.academic_year_id = academicYearId;

      const [classesRes, paymentRes] = await Promise.all([
        window.API.academic.listClasses(),
        window.API.finance.getStudentPaymentStatusList
          ? window.API.finance.getStudentPaymentStatusList(params)
          : window.API.academic.getCustom({ action: 'class-balances' }),
      ]);

      const classes = classesRes?.success ? classesRes.data || [] : (classesRes?.data || []);
      const paymentPayload = paymentRes?.success ? paymentRes.data || [] : (paymentRes?.data || paymentRes || []);
      const payments = Array.isArray(paymentPayload)
        ? paymentPayload
        : (paymentPayload?.items || paymentPayload?.data || []);

      // Aggregate by class
      const classMap = {};
      (Array.isArray(classes) ? classes : []).forEach(function(c) {
        classMap[c.id] = {
          id: c.id,
          name: c.name || c.class_name,
          students: 0,
          expected: 0,
          collected: 0,
          pending: 0,
        };
      });

      (Array.isArray(payments) ? payments : []).forEach(function(p) {
        const cid = p.class_id;
        if (classMap[cid]) {
          classMap[cid].students++;
          classMap[cid].expected += parseFloat(p.total_due || p.total_fee || p.expected || 0);
          classMap[cid].collected += parseFloat(p.total_paid || p.amount_paid || p.collected || 0);
          classMap[cid].pending += parseFloat(p.current_balance || p.balance || p.pending || 0);
        } else if (p.class_name) {
          if (!classMap[p.class_name]) {
            classMap[p.class_name] = {
              id: p.class_name,
              name: p.class_name,
              students: 0,
              expected: 0,
              collected: 0,
              pending: 0,
            };
          }
          classMap[p.class_name].students++;
          classMap[p.class_name].expected += parseFloat(p.total_due || p.total_fee || p.expected || 0);
          classMap[p.class_name].collected += parseFloat(p.total_paid || p.amount_paid || p.collected || 0);
          classMap[p.class_name].pending += parseFloat(p.current_balance || p.balance || p.pending || 0);
        }
      });

      this.state.classBalances = Object.values(classMap).filter(function(c) { return c.students > 0; });
      this.state.totals = {
        expected: this.state.classBalances.reduce(function(s, c) { return s + c.expected; }, 0),
        collected: this.state.classBalances.reduce(function(s, c) { return s + c.collected; }, 0),
        pending: this.state.classBalances.reduce(function(s, c) { return s + c.pending; }, 0),
        students: this.state.classBalances.reduce(function(s, c) { return s + c.students; }, 0),
      };

      this.updateStats();
      this.renderTable();
      this.renderCharts();
    } catch (error) {
      console.error('Error loading class balances:', error);
      this.showLocalNotification('Error loading data', 'error');
    }
  },

  loadClassBillingReport: function() {
    var classId = document.getElementById('classSelect')?.value;
    var academicYearId = document.getElementById('academicYearSelect')?.value;
    var termId = document.getElementById('termSelect')?.value;

    var nameEl = document.getElementById('selectedClassName');
    var subtitleEl = document.getElementById('studentBillingSubtitle');
    if (nameEl) nameEl.textContent = this.state.selectedClassName || 'Selected Class';
    if (subtitleEl) subtitleEl.textContent = termId ? 'Term ' + termId : 'All Terms';

    if (!classId) {
      var tbody = document.getElementById('tbody_class_billing');
      if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-3">Select a class to view billing report</td></tr>';
      return;
    }

    var tbody = document.getElementById('tbody_class_billing');
    if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>';

    var url = '/finance/class-billing-report/' + classId;
    var queryParts = [];
    if (academicYearId) queryParts.push('academic_year_id=' + encodeURIComponent(academicYearId));
    if (termId) queryParts.push('term_id=' + encodeURIComponent(termId));
    if (queryParts.length) url += '?' + queryParts.join('&');

    window.API.apiCall(url, 'GET').then(function(resp) {
      var data = resp.data || resp;
      var students = data.students || data || [];
      var agg = data.aggregate || {};

      // Update summary cards
      var totalStudentsEl = document.getElementById('totalStudents');
      var totalBilledEl = document.getElementById('totalExpected');
      var totalCollectedEl = document.getElementById('totalCollected');
      var collectionRateEl = document.getElementById('collectionRate');

      if (totalStudentsEl) totalStudentsEl.textContent = agg.total_students || (Array.isArray(students) ? students.length : 0);
      if (totalBilledEl) totalBilledEl.textContent = 'KES ' + Number(agg.total_billed_class || 0).toLocaleString();
      if (totalCollectedEl) totalCollectedEl.textContent = 'KES ' + Number(agg.total_collected_class || 0).toLocaleString();
      if (collectionRateEl) collectionRateEl.textContent = (agg.collection_rate || 0).toFixed(1) + '%';

      // Render student billing table
      var tbody = document.getElementById('tbody_class_billing');
      if (!tbody) return;

      if (!Array.isArray(students) || !students.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-3">No students found for selected filters</td></tr>';
        return;
      }

      tbody.innerHTML = students.map(function(s, i) {
        var statusClass = s.payment_status === 'paid' ? 'success' : (parseFloat(s.balance || 0) > 0 ? 'danger' : 'secondary');
        var safeName = ((s.first_name || '') + ' ' + (s.last_name || '')).trim().replace(/'/g, "\\'");
        return '<tr>' +
          '<td>' + (i + 1) + '</td>' +
          '<td>' + (s.first_name || '') + ' ' + (s.last_name || '') + '</td>' +
          '<td>' + (s.admission_no || '—') + '</td>' +
          '<td><span class="badge bg-secondary">' + (s.student_type || '') + '</span></td>' +
          '<td>KES ' + Number(s.total_billed || 0).toLocaleString() + '</td>' +
          '<td>KES ' + Number(s.total_paid || 0).toLocaleString() + '</td>' +
          '<td><strong>KES ' + Number(s.balance || 0).toLocaleString() + '</strong></td>' +
          '<td><span class="badge bg-' + statusClass + '">' + (s.payment_status || 'pending') + '</span></td>' +
          '<td>' + (s.last_payment_date ? String(s.last_payment_date).substring(0, 10) : '—') + '</td>' +
          '<td><button class="btn btn-sm btn-outline-primary" onclick="loadStudentHistory(' + s.id + ',\'' + safeName + '\')"><i class="fas fa-eye"></i></button></td>' +
          '</tr>';
      }).join('');
    }).catch(function(err) {
      console.error('Failed to load class billing report:', err);
      var tbody = document.getElementById('tbody_class_billing');
      if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-3">Failed to load student billing data.</td></tr>';
    });
  },

  updateStats() {
    const fmt = (n) =>
      new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        minimumFractionDigits: 0,
      }).format(n);
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };

    // Only update these summary cards if no specific class is selected
    if (!this.state.selectedClassId) {
      el('totalExpected', fmt(this.state.totals.expected));
      el('totalCollected', fmt(this.state.totals.collected));
      el('totalPending', fmt(this.state.totals.pending));
      el('totalStudents', this.state.totals.students || 0);

      const rate =
        this.state.totals.expected > 0
          ? ((this.state.totals.collected / this.state.totals.expected) * 100).toFixed(1) + '%'
          : '0%';
      el('collectionRate', rate);
    }
  },

  renderTable() {
    const tbody = document.querySelector('#classBalancesTable tbody');
    if (!tbody) return;

    if (this.state.classBalances.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-4">No class balance data available</td></tr>';
      return;
    }

    const fmt = (n) => new Intl.NumberFormat('en-KE').format(n);
    const self = this;

    tbody.innerHTML = this.state.classBalances
      .map((c, i) => {
        const rate =
          c.expected > 0 ? ((c.collected / c.expected) * 100).toFixed(1) : 0;
        const barColor =
          rate >= 80 ? 'success' : rate >= 50 ? 'warning' : 'danger';
        return `
            <tr>
                <td>${i + 1}</td>
                <td><strong>${self.esc(c.name)}</strong></td>
                <td>${c.students}</td>
                <td class="text-end">KES ${fmt(c.expected)}</td>
                <td class="text-end text-success">KES ${fmt(c.collected)}</td>
                <td class="text-end text-danger">KES ${fmt(c.pending)}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="progress flex-fill me-2" style="height: 8px;">
                            <div class="progress-bar bg-${barColor}" style="width: ${rate}%"></div>
                        </div>
                        <small class="fw-bold">${rate}%</small>
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="BalancesByClassController.drillDownClass('${c.id}', '${self.esc(c.name)}')">
                        <i class="fas fa-users me-1"></i>View Students
                    </button>
                </td>
            </tr>`;
      })
      .join('');
  },

  drillDownClass(classId, className) {
    const classSelect = document.getElementById('classSelect');
    if (classSelect) {
      classSelect.value = classId;
    }
    this.state.selectedClassId = classId;
    this.state.selectedClassName = className;
    this.loadClassBillingReport();
    // Scroll to student billing section
    const section = document.getElementById('studentBillingSection');
    if (section) section.scrollIntoView({ behavior: 'smooth' });
  },

  renderCharts() {
    if (typeof Chart === 'undefined') return;

    // Class Collection Chart (bar)
    const collCtx = document.getElementById('classCollectionChart')?.getContext('2d');
    if (collCtx) {
      if (this.state.charts.collection) this.state.charts.collection.destroy();
      const labels = this.state.classBalances.map((c) => c.name);
      this.state.charts.collection = new Chart(collCtx, {
        type: 'bar',
        data: {
          labels,
          datasets: [
            {
              label: 'Collected',
              data: this.state.classBalances.map((c) => c.collected),
              backgroundColor: 'rgba(40, 167, 69, 0.7)',
            },
            {
              label: 'Pending',
              data: this.state.classBalances.map((c) => c.pending),
              backgroundColor: 'rgba(220, 53, 69, 0.7)',
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'top' } },
          scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true },
          },
        },
      });
    }

    // Collection Rate Chart (doughnut)
    const rateCtx = document.getElementById('collectionRateChart')?.getContext('2d');
    if (rateCtx) {
      if (this.state.charts.rate) this.state.charts.rate.destroy();
      this.state.charts.rate = new Chart(rateCtx, {
        type: 'doughnut',
        data: {
          labels: ['Collected', 'Pending'],
          datasets: [
            {
              data: [this.state.totals.collected, this.state.totals.pending],
              backgroundColor: ['rgba(40, 167, 69, 0.8)', 'rgba(220, 53, 69, 0.8)'],
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'bottom' } },
        },
      });
    }
  },

  exportCSV() {
    if (this.state.classBalances.length === 0) {
      this.showLocalNotification('No data to export', 'warning');
      return;
    }
    const headers = ['#', 'Class', 'Students', 'Expected (KES)', 'Collected (KES)', 'Pending (KES)', 'Rate (%)'];
    const rows = this.state.classBalances.map((c, i) => {
      const rate = c.expected > 0 ? ((c.collected / c.expected) * 100).toFixed(1) : 0;
      return [i + 1, c.name, c.students, c.expected, c.collected, c.pending, rate];
    });
    const csv = [headers, ...rows]
      .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(','))
      .join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'balances_by_class.csv';
    a.click();
    URL.revokeObjectURL(url);
  },

  showTableLoading() {
    const tbody = document.querySelector('#classBalancesTable tbody');
    if (tbody)
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading balances...</td></tr>';
  },

  esc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  },

  showLocalNotification(msg, type = 'info') {
    if (typeof showNotification === 'function') {
      showNotification(msg, type);
      return;
    }
    const alert = document.createElement('div');
    alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = '9999';
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },
};

/**
 * Global helper called by the student billing table's inline onclick handlers.
 * Opens the full billing history modal.
 */
function loadStudentHistory(studentId, studentName) {
  var nameEl = document.getElementById('historyStudentName');
  var contentEl = document.getElementById('billingHistoryContent');
  if (!nameEl || !contentEl) return;

  nameEl.textContent = studentName;
  contentEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

  var modalEl = document.getElementById('studentBillingHistoryModal');
  if (!modalEl) return;
  var modal = new bootstrap.Modal(modalEl);
  modal.show();

  window.API.apiCall('/finance/students-billing-history/' + studentId, 'GET')
    .then(function(resp) {
      var data = resp.data || resp;
      renderBillingHistoryModal(data);
    })
    .catch(function() {
      contentEl.innerHTML = '<div class="alert alert-danger">Failed to load billing history.</div>';
    });
}

/**
 * Renders billing history data into the modal body.
 */
function renderBillingHistoryModal(data) {
  var contentEl = document.getElementById('billingHistoryContent');
  if (!contentEl) return;

  var years = data.academic_years || data || [];
  if (!Array.isArray(years) || !years.length) {
    contentEl.innerHTML = '<div class="alert alert-info">No billing history found.</div>';
    return;
  }

  var html = '';
  years.forEach(function(yr) {
    html += '<div class="card mb-3">';
    html += '<div class="card-header fw-bold bg-light">Academic Year ' + yr.year + '</div>';
    html += '<div class="card-body p-0">';

    html += '<ul class="nav nav-tabs px-3 pt-2">';
    (yr.terms || []).forEach(function(term, i) {
      html += '<li class="nav-item"><a class="nav-link' + (i === 0 ? ' active' : '') + '" data-bs-toggle="tab" href="#bterm-' + yr.year + '-' + term.term_id + '">' + term.term_name + '</a></li>';
    });
    html += '</ul>';

    html += '<div class="tab-content p-3">';
    (yr.terms || []).forEach(function(term, i) {
      html += '<div class="tab-pane fade' + (i === 0 ? ' show active' : '') + '" id="bterm-' + yr.year + '-' + term.term_id + '">';

      html += '<h6 class="text-muted mb-2">Fee Obligations</h6>';
      html += '<table class="table table-sm table-bordered mb-3"><thead class="table-light"><tr><th>Fee Type</th><th>Amount Due</th><th>Paid</th><th>Waived</th><th>Balance</th><th>Status</th></tr></thead><tbody>';
      (term.obligations || []).forEach(function(o) {
        var sc = o.payment_status === 'paid' ? 'success' : o.payment_status === 'partial' ? 'warning' : 'danger';
        html += '<tr><td>' + (o.fee_type_name || '') + '</td>' +
          '<td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td>' +
          '<td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td>' +
          '<td>KES ' + Number(o.amount_waived || 0).toLocaleString() + '</td>' +
          '<td><strong>KES ' + Number(o.balance || 0).toLocaleString() + '</strong></td>' +
          '<td><span class="badge bg-' + sc + '">' + (o.payment_status || 'pending') + '</span></td></tr>';
      });
      html += '<tr class="table-light fw-bold"><td>TOTAL</td>' +
        '<td>KES ' + Number(term.total_due || 0).toLocaleString() + '</td>' +
        '<td>KES ' + Number(term.total_paid || 0).toLocaleString() + '</td>' +
        '<td>—</td>' +
        '<td>KES ' + Number(term.balance || 0).toLocaleString() + '</td>' +
        '<td></td></tr>';
      html += '</tbody></table>';

      if ((term.payments || []).length > 0) {
        html += '<h6 class="text-muted mb-2">Payments Received</h6>';
        html += '<table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th><th>Reference</th></tr></thead><tbody>';
        (term.payments || []).forEach(function(p) {
          html += '<tr>' +
            '<td>' + (p.payment_date || '').substring(0, 10) + '</td>' +
            '<td>' + (p.payment_method || '') + '</td>' +
            '<td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td>' +
            '<td>' + (p.receipt_no || '—') + '</td>' +
            '<td>' + (p.reference_no || '—') + '</td></tr>';
        });
        html += '</tbody></table>';
      }

      html += '</div>'; // tab-pane
    });
    html += '</div></div></div>';
  });

  contentEl.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', () => BalancesByClassController.init());
