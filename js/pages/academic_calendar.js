/** Read-only academic calendar page backed by the shared calendar component. */
(function (window, document) {
    'use strict';

    const academicCalendarController = {
        table: null,
        yearId: null,
        year: null,
        initialized: false,

        async init() {
            if (this.initialized) return;
            this.initialized = true;

            await window.AuthContext?.ready?.();
            if (window.AuthContext && !window.AuthContext.isAuthenticated()) {
                window.location.href = `${window.APP_BASE || ''}/index.php`;
                return;
            }

            // The year filter loads the table, so the table does not load on its own.
            this.table = window.AcademicEventsTable?.create?.({
                containerId: 'calendarEventsTable',
                defaultScope: 'year',
                autoLoad: false,
                onReady: (instance) => this.renderUpcoming(instance.state.allEvents),
                onError: () => this.renderUpcomingError(),
            });
            if (!this.table) {
                this.showError('The calendar view could not be initialized. Refresh the page to try again.');
                return;
            }

            // Keep an explicitly selected year even if the shared year-list
            // request fails. The table can still load that year's events by ID.
            const requestedYearId = Number(new URLSearchParams(window.location.search).get('academic_year_id'));
            if (Number.isInteger(requestedYearId) && requestedYearId > 0) {
                this.yearId = requestedYearId;
                this.table.state.yearId = requestedYearId;
            }

            document.getElementById('printCalendar')?.addEventListener('click', () => this.print());
            document.getElementById('exportCalendarCsv')?.addEventListener('click', () => this.exportCsv());
            document.getElementById('calendarPdf')?.addEventListener('click', () => this.downloadPdf());

            const select = document.getElementById('academicYearFilter');
            if (window.AcademicYearFilter && select) {
                // Calls back once with the URL-selected year (when present), then
                // on every change. setYear deliberately opens the whole year.
                const mounted = await window.AcademicYearFilter.mount(select, {
                    onChange: (id, year) => {
                        this.yearId = id || null;
                        this.year = year || null;
                        this.table.setYear(this.yearId);
                    },
                });
                if (!mounted) {
                    // A missing year list must not leave the event table in its
                    // loading state forever.
                    await this.table.load();
                }
            } else {
                await this.table.load();
            }
        },

        renderUpcoming(events) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const upcoming = (Array.isArray(events) ? events : [])
                .filter((event) => {
                    const date = new Date(`${event.start_date || event.date || event.event_date || ''}T00:00:00`);
                    return !Number.isNaN(date.getTime()) && date >= today;
                })
                .sort((a, b) => {
                    const dateCompare = String(a.start_date || a.date || '').localeCompare(String(b.start_date || b.date || ''));
                    if (dateCompare !== 0) return dateCompare;
                    return String(a.start_time || '').localeCompare(String(b.start_time || ''));
                });

            window.UpcomingEventsWidget?.render?.(
                document.getElementById('upcomingEvents'),
                upcoming,
                { max: 8, emptyText: 'No upcoming events for this academic year.' },
            );
        },

        renderUpcomingError() {
            const container = document.getElementById('upcomingEvents');
            if (!container) return;

            const item = document.createElement('li');
            item.className = 'list-group-item text-center text-danger py-4';
            item.setAttribute('role', 'alert');

            const message = document.createElement('p');
            message.className = 'mb-2';
            message.textContent = 'Upcoming events could not be loaded.';
            item.appendChild(message);

            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'btn btn-sm btn-outline-secondary';
            retry.textContent = 'Try again';
            retry.addEventListener('click', () => this.table?.load());
            item.appendChild(retry);

            container.replaceChildren(item);
        },

        yearLabel() {
            const label = this.year?.year_code
                || this.year?.year_name
                || (this.yearId ? `ID-${this.yearId}` : 'current');
            return String(label).replace(/[^0-9A-Za-z-]+/g, '-');
        },

        print() {
            this.updatePrintContext();
            window.print();
        },

        updatePrintContext() {
            const context = document.getElementById('academicCalendarPrintContext');
            if (!context) return;

            const yearSelect = document.getElementById('academicYearFilter');
            const selectedYearText = yearSelect?.selectedOptions?.[0]?.textContent?.trim() || '';
            const yearLabel = this.year
                ? (selectedYearText || this.year.year_name || this.year.year_code || `ID ${this.yearId}`)
                : (this.yearId ? `ID ${this.yearId}` : (selectedYearText || 'Selected year'));
            const root = document.getElementById('calendarEventsTable');
            const scopeLabels = {
                current_term: 'Current Term',
                year: 'Whole Year',
                upcoming: 'Upcoming',
            };
            const scope = scopeLabels[this.table?.state?.scope] || 'Whole Year';
            const period = root?.querySelector("[data-academic-role='term']")?.selectedOptions?.[0]?.textContent?.trim() || 'Whole year';
            const eventType = root?.querySelector("[data-academic-role='type']")?.selectedOptions?.[0]?.textContent?.trim() || 'All Types';
            const search = root?.querySelector("[data-academic-role='search']")?.value?.trim();

            context.textContent = [
                `Academic year: ${yearLabel}`,
                `View: ${scope}`,
                `Period: ${period}`,
                `Event type: ${eventType}`,
                search ? `Search: ${search}` : '',
            ].filter(Boolean).join(' · ');
        },

        /** CSV of the rows the table is showing (its filters applied). */
        async exportCsv() {
            const rows = this.table?.state?.events || [];
            if (!rows.length) {
                this.notify('There are no events to export.', 'info');
                return;
            }

            const header = [
                'Period',
                'Week',
                'Event',
                'Description',
                'Type',
                'Start Date',
                'End Date',
                'Time',
                'Location',
                'Status',
                'Status Context',
            ];
            const lines = [header.map((value) => this.csvCell(value)).join(',')];
            rows.forEach((event) => {
                lines.push([
                    event.period_name || event.term_name || '',
                    event.week_number ?? '',
                    event.title || event.name || event.event_name || '',
                    event.description || '',
                    event.type || event.event_type || '',
                    event.start_date || event.date || event.event_date || '',
                    event.end_date || event.start_date || event.date || '',
                    window.AcademicEventsTable?.eventTimeLabel?.(event) || '',
                    event.location || event.venue || '',
                    event.status || '',
                    window.AcademicEventsTable?.statusContext?.(event) || '',
                ].map((value) => this.csvCell(value)).join(','));
            });

            try {
                await window.KingswayFileLifecycle.exportText(
                    `\ufeff${lines.join('\r\n')}`,
                    `academic_calendar_${this.yearLabel()}.csv`,
                    'text/csv;charset=utf-8',
                );
            } catch (error) {
                console.error('Academic calendar CSV export failed:', error);
                this.notify('The academic calendar could not be exported.', 'error');
            }
        },

        /** The official term-by-term calendar PDF for the selected year. */
        async downloadPdf() {
            if (!window.PrintManager?.printAcademicCalendar) {
                this.notify('Calendar PDF generation is unavailable.', 'warning');
                return;
            }
            const options = { filename: `academic_calendar_${this.yearLabel()}` };
            if (this.yearId) options.academicYear = this.yearId;
            try {
                await window.PrintManager.printAcademicCalendar(options);
            } catch (error) {
                console.error('Academic calendar PDF generation failed:', error);
                this.notify('The academic calendar PDF could not be generated.', 'error');
            }
        },

        csvCell(value) {
            let text = String(value ?? '');
            if (/^[=+\-@]/.test(text) && !/^-?\d+(?:\.\d+)?$/.test(text)) {
                text = `'${text}`;
            }
            return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
        },

        notify(message, type) {
            if (typeof window.showNotification === 'function') {
                window.showNotification(message, type || 'info');
            } else if (typeof window.API?.showNotification === 'function') {
                window.API.showNotification(message, type || 'info');
            }
        },

        showError(message) {
            const upcoming = document.getElementById('upcomingEvents');
            if (upcoming) {
                const item = document.createElement('li');
                item.className = 'list-group-item text-danger text-center py-4';
                item.textContent = String(message ?? 'Could not load upcoming events.');
                upcoming.replaceChildren(item);
            }
            this.notify(message, 'error');
        },
    };

    window.academicCalendarController = academicCalendarController;
    document.addEventListener('DOMContentLoaded', () => {
        academicCalendarController.init().catch((error) => {
            console.error('Academic Calendar initialization failed:', error);
            academicCalendarController.table?.renderError();
            academicCalendarController.showError('Could not load the academic calendar. Refresh to try again.');
        });
    }, { once: true });
})(window, document);
