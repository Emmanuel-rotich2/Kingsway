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
    document.getElementById('scvStudentName')?.textContent = student?.name || '—';
    document.getElementById('scvAdmissionNo')?.textContent = student?.admission_number || '—';
    document.getElementById('scvCardStatus')?.textContent = v?.status || '—';
    document.getElementById('scvCardNumber')?.textContent = v?.card_number || '—';
    document.getElementById('scvExpiryDate')?.textContent = this.fmtDate(v?.expiry_date);
    document.getElementById('scvVerified')?.textContent = v?.verified ? 'Yes' : 'No';
    document.getElementById('scvVerifiedBy')?.textContent = v?.verified_by || '—';
    document.getElementById('scvVerifiedAt')?.textContent = this.fmtDate(v?.verified_at);
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