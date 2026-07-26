/**
 * Boarding Master Dashboard Controller
 * Composes existing BoardingController statistics, occupancy, roll call and
 * permission/exeat endpoints.
 */
(() => {
    const unwrap = (response) => {
        let value = response;
        for (let depth = 0; depth < 4; depth += 1) {
            if (value && typeof value === 'object' && !Array.isArray(value)
                && Object.prototype.hasOwnProperty.call(value, 'data')) {
                value = value.data;
                continue;
            }
            break;
        }
        return value;
    };

    const today = () => new Date().toISOString().slice(0, 10);

    const controller = DashboardBaseController.create({
        controllerName: 'BoardingMasterDashboardController',
        rootId: 'boardingDashboard',
        refreshButtonId: 'boardingDashboardRefresh',
        stateId: 'boardingDashboardState',
        scopeId: 'boardingDashboardScope',
        lastUpdatedId: 'boardingDashboardLastUpdated',

        async apiMethod() {
            const [statsResponse, occupancyResponse, rollCallResponse, exeatResponse] = await Promise.all([
                window.API.boarding.getStats(),
                window.API.boarding.getOccupancy(),
                window.API.boarding.getRollCalls({ date: today() }),
                window.API.boarding.getExeats({})
            ]);

            const stats = unwrap(statsResponse) || {};
            const occupancy = unwrap(occupancyResponse);
            const rollCalls = unwrap(rollCallResponse);
            const exeats = unwrap(exeatResponse);
            const occupancyRows = Array.isArray(occupancy) ? occupancy : [];
            const rollCallRows = Array.isArray(rollCalls) ? rollCalls : [];
            const exeatRows = Array.isArray(exeats) ? exeats : [];

            const rollTotals = rollCallRows.reduce((totals, row) => {
                totals.present += Number(row.present_count || 0);
                totals.absent += Number(row.absent_count || 0);
                totals.permission += Number(row.permission_count || 0);
                totals.sickBay += Number(row.sick_bay_count || 0);
                return totals;
            }, { present: 0, absent: 0, permission: 0, sickBay: 0 });

            return {
                meta: { scope_label: 'Boarding' },
                cards: {
                    active_boarders: Number(stats.total_boarders || 0),
                    occupancy_rate: Number(stats.occupancy_rate || 0),
                    absent_or_unknown: Number(stats.absent_or_unknown || 0),
                    urgent_notes: Number(stats.urgent_notes || 0)
                },
                charts: {
                    occupancy: {
                        labels: occupancyRows.map((row) => row.dormitory_name || row.name || 'Dormitory'),
                        datasets: [
                            {
                                label: 'Occupied',
                                data: occupancyRows.map((row) => Number(row.occupied || 0)),
                                borderWidth: 2
                            },
                            {
                                label: 'Available',
                                data: occupancyRows.map((row) => Number(row.available || 0)),
                                borderWidth: 2
                            }
                        ]
                    },
                    roll_call: {
                        labels: ['Present', 'Absent', 'Permission', 'Sick bay'],
                        data: [
                            rollTotals.present,
                            rollTotals.absent,
                            rollTotals.permission,
                            rollTotals.sickBay
                        ]
                    }
                },
                tables: {
                    roll_calls: rollCallRows,
                    exeats: exeatRows
                }
            };
        },

        cards: [
            { id: 'brdBoarders', path: 'cards.active_boarders', subtitleId: 'brdBoardersSub', subtitle: 'Students assigned to dormitories' },
            { id: 'brdOccupancy', path: 'cards.occupancy_rate', format: 'percent', subtitleId: 'brdOccupancySub', subtitle: 'Across active dormitories' },
            { id: 'brdAbsent', path: 'cards.absent_or_unknown', subtitleId: 'brdAbsentSub', subtitle: 'Today’s latest roll call' },
            { id: 'brdNotes', path: 'cards.urgent_notes', subtitleId: 'brdNotesSub', subtitle: 'Unresolved high-priority notes' }
        ],
        chartDefinitions: [
            { id: 'brdOccupancyChart', path: 'charts.occupancy', label: 'Beds', type: 'bar', showLegend: true },
            { id: 'brdRollCallChart', path: 'charts.roll_call', label: 'Students', type: 'doughnut', showLegend: true }
        ],
        tableDefinitions: [
            {
                bodyId: 'brdRollCallsBody',
                path: 'tables.roll_calls',
                emptyText: 'No roll-call summary for today.',
                columns: [
                    { key: 'dormitory_name' },
                    { key: 'session_name' },
                    { key: 'present_count', format: 'number' },
                    { key: 'absent_count', format: 'number' },
                    { key: 'attendance_percentage', format: 'percent' }
                ]
            },
            {
                bodyId: 'brdNotesBody',
                path: 'tables.exeats',
                emptyText: 'No current permissions or exeats.',
                columns: [
                    { key: 'student_name' },
                    { value: (row) => row.permission_type_name || row.type || 'Permission' },
                    {
                        value: (row) => [row.start_date, row.end_date]
                            .filter(Boolean)
                            .join(' – ')
                    },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value, {
                            pending: 'warning', approved: 'success', rejected: 'danger',
                            completed: 'secondary', cancelled: 'secondary'
                        })
                    }
                ]
            }
        ]
    });

    window.BoardingMasterDashboardController = controller;
    window.boardingDashboardController = controller;
    DashboardBaseController.boot(controller, 'BoardingMasterDashboardController');
})();
