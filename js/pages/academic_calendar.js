/** Read-only academic calendar page backed by the shared calendar component. */
const academicCalendarController = {
  async init() {
    await window.AuthContext?.ready?.();
    if (window.AuthContext && !window.AuthContext.isAuthenticated()) {
      window.location.href = `${window.APP_BASE || ''}/index.php`;
      return;
    }

    const table = window.AcademicEventsTable?.create?.({
      containerId: 'calendarEventsTable',
      onReady: (instance) => this.renderUpcoming(instance.state.allEvents),
    });
    if (!table) {
      this.showError('The calendar view could not be initialized. Refresh the page to try again.');
      return;
    }
    document.getElementById('printCalendar')?.addEventListener('click', () => window.print());
  },

  renderUpcoming(events) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const upcoming = (Array.isArray(events) ? events : []).filter((event) => {
      const date = new Date(`${event.start_date || event.date || event.event_date || ''}T00:00:00`);
      return !Number.isNaN(date.getTime()) && date >= today;
    });
    window.UpcomingEventsWidget?.render?.(
      document.getElementById('upcomingEvents'),
      upcoming,
      { max: 8, emptyText: 'No upcoming events for this academic year.' },
    );
  },

  showError(message) {
    const upcoming = document.getElementById('upcomingEvents');
    if (upcoming) {
      const item = document.createElement('li');
      item.className = 'list-group-item text-danger text-center py-4';
      item.textContent = String(message ?? 'Could not load upcoming events.');
      upcoming.replaceChildren(item);
    }
    window.showNotification?.(message, 'error');
  },
};

window.academicCalendarController = academicCalendarController;
document.addEventListener('DOMContentLoaded', () => academicCalendarController.init().catch((error) => {
  console.error('Academic Calendar initialization failed:', error);
  academicCalendarController.showError('Could not load the academic calendar. Refresh to try again.');
}));
