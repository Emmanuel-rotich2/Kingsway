/** Class stream register controller. */
const classStreamsController = (() => {
  const state = { streams: [], classes: [], teachers: [] };
  const getRows = (response) => {
    const payload = response?.data ?? response ?? {};
    return Array.isArray(payload) ? payload : Array.isArray(payload.rows) ? payload.rows : [];
  };
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[character]);
  const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = String(value ?? '—'); };

  async function load() {
    const body = document.querySelector('#dataTable tbody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Loading streams…</td></tr>';
    try {
      const [streamsResponse, classesResponse, teachersResponse] = await Promise.all([
        window.API.apiCall('/academic/streams-list', 'GET', null, {}, { checkPermission: false }),
        window.API.apiCall('/academic/classes-list', 'GET', null, {}, { checkPermission: false }),
        window.API.academic.listTeachers().catch(() => []),
      ]);
      state.streams = getRows(streamsResponse);
      state.classes = getRows(classesResponse);
      state.teachers = getRows(teachersResponse);
      renderOptions();
      render();
    } catch (error) {
      console.error('Class streams failed to load:', error);
      body.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${escapeHtml(error.message || 'Could not load class streams.')}</td></tr>`;
      ['statClasses', 'statStreams', 'statAvg', 'statCapacity'].forEach((id) => setText(id, '—'));
    }
  }

  function visibleStreams() {
    const classId = document.getElementById('filterSelect')?.value || '';
    const status = document.getElementById('statusFilter')?.value || '';
    const search = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    return state.streams.filter((stream) => (!classId || String(stream.class_id) === classId)
      && (!status || String(stream.status || '').toLowerCase() === status)
      && (!search || `${stream.class_name || ''} ${stream.stream_name || ''} ${stream.teacher_name || ''}`.toLowerCase().includes(search)));
  }

  function render() {
    const body = document.querySelector('#dataTable tbody');
    if (!body) return;
    const streams = visibleStreams();
    if (!streams.length) {
      body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No class streams found.</td></tr>';
    } else {
      body.innerHTML = streams.map((stream, index) => {
        const capacity = Number(stream.capacity || 0);
        const count = Number(stream.student_count || 0);
        const utilization = capacity > 0 ? `${Math.round(count * 100 / capacity)}%` : '—';
        const active = String(stream.status || '').toLowerCase() === 'active';
        return `<tr><td>${index + 1}</td><td>${escapeHtml(stream.class_name || stream.level_name || '—')}</td>
          <td>${escapeHtml(stream.stream_name || '—')}</td><td>${escapeHtml(stream.teacher_name || '—')}</td>
          <td>${count}</td><td>${capacity || '—'}</td><td>${utilization}</td>
          <td><span class="badge bg-${active ? 'success' : 'secondary'}">${escapeHtml(stream.status || '—')}</span></td>
          <td><button class="btn btn-sm btn-outline-primary" type="button" title="Edit stream" data-edit-stream="${Number(stream.id)}"><i class="bi bi-pencil"></i></button></td></tr>`;
      }).join('');
      body.querySelectorAll('[data-edit-stream]').forEach((button) => button.addEventListener('click', () => editStream(button.dataset.editStream)));
    }
    const totalCapacity = state.streams.reduce((sum, stream) => sum + Number(stream.capacity || 0), 0);
    const totalStudents = state.streams.reduce((sum, stream) => sum + Number(stream.student_count || 0), 0);
    setText('statClasses', new Set(state.streams.map((stream) => stream.class_id)).size);
    setText('statStreams', state.streams.length);
    setText('statAvg', state.streams.length ? (totalStudents / state.streams.length).toFixed(1) : 0);
    setText('statCapacity', totalCapacity);
  }

  function renderOptions() {
    const classFilter = document.getElementById('filterSelect');
    if (classFilter) classFilter.innerHTML = '<option value="">All Classes</option>' + state.classes.map((item) => `<option value="${Number(item.id)}">${escapeHtml(item.name || item.class_name)}</option>`).join('');
    const recordClass = document.getElementById('recordClass');
    if (recordClass) recordClass.innerHTML = '<option value="">Select class…</option>' + state.classes.map((item) => `<option value="${Number(item.id)}">${escapeHtml(item.name || item.class_name)}</option>`).join('');
    const recordTeacher = document.getElementById('recordTeacher');
    if (recordTeacher) recordTeacher.innerHTML = '<option value="">Not assigned</option>' + state.teachers.map((teacher) => `<option value="${Number(teacher.id)}">${escapeHtml(teacher.name || teacher.full_name || teacher.teacher_name)}</option>`).join('');
  }

  function showAddModal() {
    document.getElementById('recordForm')?.reset();
    const id = document.getElementById('recordId');
    if (id) id.value = '';
    const title = document.getElementById('formModalTitle');
    if (title) title.textContent = 'Add Class Stream';
    const modal = document.getElementById('formModal');
    if (modal && window.bootstrap?.Modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  function editStream(id) {
    const stream = state.streams.find((item) => Number(item.id) === Number(id));
    if (!stream) return;
    document.getElementById('recordId').value = stream.id;
    document.getElementById('recordClass').value = stream.class_id || '';
    document.getElementById('recordName').value = stream.stream_name || '';
    document.getElementById('recordCapacity').value = stream.capacity || 40;
    document.getElementById('recordTeacher').value = stream.teacher_id || '';
    document.getElementById('recordStatus').value = stream.status || 'active';
    const title = document.getElementById('formModalTitle');
    if (title) title.textContent = 'Edit Class Stream';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
  }

  async function saveRecord() {
    const id = document.getElementById('recordId')?.value || '';
    const payload = {
      class_id: Number(document.getElementById('recordClass')?.value || 0),
      stream_name: document.getElementById('recordName')?.value.trim(),
      capacity: Number(document.getElementById('recordCapacity')?.value || 0),
      teacher_id: Number(document.getElementById('recordTeacher')?.value || 0) || null,
      status: document.getElementById('recordStatus')?.value || 'active',
    };
    if (!payload.class_id || !payload.stream_name || !payload.capacity) {
      window.showNotification?.('Class, stream name, and capacity are required.', 'warning');
      return;
    }
    try {
      const path = id ? `/academic/streams/update/${Number(id)}` : '/academic/streams/create';
      await window.API.apiCall(path, id ? 'PUT' : 'POST', payload, {}, { checkPermission: false });
      bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide();
      window.showNotification?.('Class stream saved.', 'success');
      await load();
    } catch (error) {
      window.showNotification?.(error.message || 'Could not save class stream.', 'error');
    }
  }

  function exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport()) return;
    const streams = visibleStreams();
    if (!streams.length) return;
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [['Class', 'Stream', 'Teacher', 'Students', 'Capacity', 'Status'], ...streams.map((stream) => [stream.class_name, stream.stream_name, stream.teacher_name, stream.student_count, stream.capacity, stream.status])]
      .map((row) => row.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'class_streams.csv', 'text/csv;charset=utf-8;');
  }

  function print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint()) return;
    window.print();
  }

  function bind() {
    ['filterSelect', 'statusFilter'].forEach((id) => document.getElementById(id)?.addEventListener('change', render));
    document.getElementById('searchInput')?.addEventListener('input', render);
  }

  async function init() {
    await window.AuthContext?.ready?.();
    bind();
    await load();
  }

  return { init, load, refresh: load, showAddModal, editStream, saveRecord, exportCSV, print };
})();

window.classStreamsController = classStreamsController;
window.ClassStreamsController = classStreamsController;
document.addEventListener('DOMContentLoaded', () => classStreamsController.init());
