/**
 * Student Card Verification Controller
 * Verifies student ID cards.
 */
const studentCardVerificationController = {
  state: { referenceCache: null, verification: null, student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "id-cards");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Card Verification Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load(studentId) {
    try {
      const [verification, student] = await Promise.all([
        this.API('GET', `/api/students/${studentId}/card-verification`),
        this.API('GET', `/api/students/${studentId}`),
      ]);
      this.state.verification = verification?.data || verification || {};
      this.state.student = student?.data || student || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  render() {
    const v = this.state.verification;
    const student = this.state.student;
    { const el = document.getElementById('scvStudentName'); if (el) el.textContent = student?.name || '—'; }
    { const el = document.getElementById('scvAdmissionNo'); if (el) el.textContent = student?.admission_number || '—'; }
    { const el = document.getElementById('scvCardStatus'); if (el) el.textContent = v?.status || '—'; }
    { const el = document.getElementById('scvCardNumber'); if (el) el.textContent = v?.card_number || '—'; }
    { const el = document.getElementById('scvExpiryDate'); if (el) el.textContent = this.fmtDate(v?.expiry_date); }
    { const el = document.getElementById('scvVerified'); if (el) el.textContent = v?.verified ? 'Yes' : 'No'; }
    { const el = document.getElementById('scvVerifiedBy'); if (el) el.textContent = v?.verified_by || '—'; }
    { const el = document.getElementById('scvVerifiedAt'); if (el) el.textContent = this.fmtDate(v?.verified_at); }
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.loadReferencesComposed();
    await this.load(studentId);
    window.APIRealtime?.register?.("student_card_verification", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(studentId); }
    }, ["students"]);
  },
};

window.studentCardVerificationController = studentCardVerificationController;
document.addEventListener('DOMContentLoaded', () => studentCardVerificationController.init().catch(() => {}));