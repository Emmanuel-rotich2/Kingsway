/**
 * View Calendar Page Controller
 * Manages academic calendar display and event management
 */
const ViewCalendarController = (() => {
    let events = [];
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];

    const typeColors = {
        academic: 'primary', exam: 'danger', holiday: 'success',
        meeting: 'info', sports: 'warning', extracurricular: 'secondary', other: 'dark'
    };

    async function loadData() {
        try {
            const params = new URLSearchParams({ month: currentMonth + 1, year: currentYear });
            const type = document.getElementById('eventTypeFilter')?.value;
            if (type) params.append('type', type);
            const search = document.getElementById('searchEvents')?.value;
            if (search) params.append('search', search);

            const response = await window.API.apiCall(`/academic/calendar?${params.toString()}`, 'GET');
            const data = response?.data || response || [];
            events = Array.isArray(data) ? data : (data.events || data.data || []);

            renderStats(events);
            renderCalendar();
            renderUpcomingEvents();
        } catch (e) {
            console.error('Load calendar failed:', e);
            events = [];
            renderCalendar();
        }
    }

    function renderStats(data) {
        const now = new Date();
        const thisMonthEvents = data.filter(e => {
            const d = new Date(e.start_date || e.date);
            return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
        });
        const academic = data.filter(e => (e.type || e.event_type || '').toLowerCase() === 'academic');
        const extra = data.filter(e => ['sports', 'extracurricular'].includes((e.type || e.event_type || '').toLowerCase()));

        document.getElementById('totalEvents').textContent = data.length;
        document.getElementById('thisMonthEvents').textContent = thisMonthEvents.length;
        document.getElementById('academicEvents').textContent = academic.length;
        document.getElementById('extraCurricularEvents').textContent = extra.length;
    }

    function renderCalendar() {
        const title = document.getElementById('calendarTitle');
        if (title) title.textContent = `${monthNames[currentMonth]} ${currentYear}`;

        const monthSelect = document.getElementById('monthFilter');
        if (monthSelect) monthSelect.value = currentMonth;

        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const today = new Date();

        const tbody = document.getElementById('calendarBody');
        if (!tbody) return;

        let html = '<tr>';
        for (let i = 0; i < firstDay; i++) {
            html += '<td class="text-muted p-2" style="height:90px; vertical-align:top;"></td>';
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = today.getDate() === day && today.getMonth() === currentMonth && today.getFullYear() === currentYear;
            const dayEvents = events.filter(e => {
                const eDate = (e.start_date || e.date || '').substring(0, 10);
                return eDate === dateStr;
            });

            const cellClass = isToday ? 'bg-light border-primary' : '';
            html += `<td class="p-1 ${cellClass}" style="height:90px; vertical-align:top; cursor:pointer;" onclick="ViewCalendarController.dayClick('${dateStr}')">`;
            html += `<div class="fw-bold small ${isToday ? 'text-primary' : ''}">${day}</div>`;

            dayEvents.slice(0, 3).forEach(evt => {
                const color = typeColors[evt.type || evt.event_type] || 'secondary';
                html += `<div class="badge bg-${color} text-truncate d-block mb-1" style="max-width:100%; font-size:0.65rem;" title="${evt.title || evt.name}">${evt.title || evt.name}</div>`;
            });
            if (dayEvents.length > 3) {
                html += `<small class="text-muted">+${dayEvents.length - 3} more</small>`;
            }
            html += '</td>';

            if ((firstDay + day) % 7 === 0 && day < daysInMonth) {
                html += '</tr><tr>';
            }
        }

        const remainingCells = (7 - (firstDay + daysInMonth) % 7) % 7;
        for (let i = 0; i < remainingCells; i++) {
            html += '<td class="text-muted p-2" style="height:90px; vertical-align:top;"></td>';
        }
        html += '</tr>';
        tbody.innerHTML = html;
    }

    function renderUpcomingEvents() {
        const list = document.getElementById('upcomingEventsList');
        if (!list) return;

        const now = new Date();
        now.setHours(0, 0, 0, 0);
        const upcoming = events
            .filter(e => new Date(e.start_date || e.date) >= now)
            .sort((a, b) => new Date(a.start_date || a.date) - new Date(b.start_date || b.date))
            .slice(0, 10);

        if (!upcoming.length) {
            list.innerHTML = '<div class="list-group-item text-center text-muted py-4">No upcoming events</div>';
            return;
        }

        list.innerHTML = upcoming.map(evt => {
            const color = typeColors[evt.type || evt.event_type] || 'secondary';
            const date = new Date(evt.start_date || evt.date);
            const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            return `
                <div class="list-group-item list-group-item-action" onclick="ViewCalendarController.viewEvent(${evt.id})">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="badge bg-${color} me-2">${evt.type || evt.event_type || 'event'}</span>
                            <strong>${evt.title || evt.name}</strong>
                        </div>
                        <small class="text-muted">${dateStr}</small>
                    </div>
                    ${evt.description ? `<small class="text-muted d-block mt-1">${evt.description.substring(0, 60)}...</small>` : ''}
                </div>
            `;
        }).join('');
    }

    function dayClick(dateStr) {
        openModal(null, dateStr);
    }

    function openModal(event = null, defaultDate = null) {
        document.getElementById('eventId').value = event?.id || '';
        document.getElementById('eventTitle').value = event?.title || event?.name || '';
        document.getElementById('eventStartDate').value = event?.start_date || defaultDate || '';
        document.getElementById('eventEndDate').value = event?.end_date || '';
        document.getElementById('eventType').value = event?.type || event?.event_type || 'academic';
        document.getElementById('eventColor').value = event?.color || 'primary';
        document.getElementById('eventDescription').value = event?.description || '';
        document.getElementById('eventVenue').value = event?.venue || '';
        document.getElementById('eventModalLabel').textContent = event ? 'Edit Event' : 'Add Event';
        new bootstrap.Modal(document.getElementById('eventModal')).show();
    }

    async function save() {
        const id = document.getElementById('eventId').value;
        const payload = {
            title: document.getElementById('eventTitle').value,
            start_date: document.getElementById('eventStartDate').value,
            end_date: document.getElementById('eventEndDate').value || null,
            type: document.getElementById('eventType').value,
            color: document.getElementById('eventColor').value,
            description: document.getElementById('eventDescription').value || null,
            venue: document.getElementById('eventVenue').value || null
        };
        if (!payload.title || !payload.start_date) {
            showNotification('Please fill required fields', 'error');
            return;
        }
        try {
            if (id) {
                await window.API.apiCall(`/academic/calendar/${id}`, 'PUT', payload);
            } else {
                await window.API.apiCall('/academic/calendar', 'POST', payload);
            }
            bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
            showNotification(id ? 'Event updated' : 'Event created', 'success');
            await loadData();
        } catch (e) {
            showNotification(e.message || 'Failed to save event', 'error');
        }
    }

    async function viewEvent(id) {
        try {
            const resp = await window.API.apiCall(`/academic/calendar/${id}`, 'GET');
            const evt = resp?.data || resp;
            openModal(evt);
        } catch (e) {
            showNotification('Failed to load event', 'error');
        }
    }

    function navigateMonth(dir) {
        currentMonth += dir;
        if (currentMonth > 11) { currentMonth = 0; currentYear++; }
        if (currentMonth < 0) { currentMonth = 11; currentYear--; }
        loadData();
    }

    function showNotification(message, type) {
        if (window.API?.showNotification) window.API.showNotification(message, type);
        else alert((type === 'error' ? 'Error: ' : '') + message);
    }

    function initYearDropdown() {
        const select = document.getElementById('yearFilter');
        if (!select) return;
        const now = new Date().getFullYear();
        for (let y = now - 2; y <= now + 3; y++) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            if (y === currentYear) opt.selected = true;
            select.appendChild(opt);
        }
    }

    function attachListeners() {
        document.getElementById('addEventBtn')?.addEventListener('click', () => openModal());
        document.getElementById('saveEventBtn')?.addEventListener('click', () => save());
        document.getElementById('prevMonthBtn')?.addEventListener('click', () => navigateMonth(-1));
        document.getElementById('nextMonthBtn')?.addEventListener('click', () => navigateMonth(1));
        document.getElementById('navigateCalendarBtn')?.addEventListener('click', () => {
            currentMonth = parseInt(document.getElementById('monthFilter').value);
            currentYear = parseInt(document.getElementById('yearFilter').value);
            loadData();
        });
        document.getElementById('eventTypeFilter')?.addEventListener('change', () => loadData());
        document.getElementById('searchEvents')?.addEventListener('keyup', () => {
            clearTimeout(window._calSearchTimeout);
            window._calSearchTimeout = setTimeout(() => loadData(), 300);
        });
        document.getElementById('exportCalendarBtn')?.addEventListener('click', () => {
            window.open((window.APP_BASE || "") + `/api/?route=academic/calendar/export&format=csv&year=${currentYear}`, '_blank');
        });

        // Set default month
        const monthSelect = document.getElementById('monthFilter');
        if (monthSelect) monthSelect.value = currentMonth;
    }

    async function init() {
        initYearDropdown();
        attachListeners();
        await loadData();
    }

    return { init, refresh: loadData, dayClick, viewEvent };
})();

document.addEventListener('DOMContentLoaded', () => ViewCalendarController.init());
