/**
 * Driver Dashboard Controller
 * Uses the existing TransportAPI and DriverManager assignment chain.
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

    const weekdayLabels = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    const controller = DashboardBaseController.create({
        controllerName: 'DriverDashboardController',
        rootId: 'driverDashboard',
        refreshButtonId: 'driverDashboardRefresh',
        stateId: 'driverDashboardState',
        scopeId: 'driverDashboardScope',
        lastUpdatedId: 'driverDashboardLastUpdated',

        async apiMethod() {
            const [routeResponse, vehicleResponse] = await Promise.all([
                window.API.transport.getMyRoute(),
                window.API.transport.getMyVehicle()
            ]);

            const route = unwrap(routeResponse) || null;
            const vehicle = unwrap(vehicleResponse) || null;
            let manifest = [];

            if (route?.id) {
                const manifestResponse = await window.API.transport.getRouteManifest(Number(route.id));
                const manifestValue = unwrap(manifestResponse);
                manifest = Array.isArray(manifestValue)
                    ? manifestValue
                    : Array.isArray(manifestValue?.manifest)
                        ? manifestValue.manifest
                        : [];
            }

            const schedules = Array.isArray(route?.schedules) ? route.schedules : [];
            const incidents = Array.isArray(route?.recent_incidents) ? route.recent_incidents : [];
            const dayTotals = weekdayLabels.reduce((totals, day) => {
                totals[day] = 0;
                return totals;
            }, {});

            schedules.forEach((row) => {
                if (!row.date) {
                    return;
                }
                const date = new Date(`${row.date}T00:00:00`);
                if (!Number.isNaN(date.getTime())) {
                    dayTotals[weekdayLabels[date.getDay()]] += 1;
                }
            });

            const stopTotals = manifest.reduce((totals, row) => {
                const stop = row.pickup_stop || row.stop_name || 'Unassigned';
                totals[stop] = (totals[stop] || 0) + 1;
                return totals;
            }, {});

            return {
                meta: { scope_label: route?.name || route?.route_name || 'Transport assignment' },
                cards: {
                    vehicle_registration: vehicle?.registration_number || route?.registration_number || '—',
                    vehicle_status: vehicle?.status || route?.vehicle_status || 'No vehicle assigned',
                    route_name: route?.name || route?.route_name || '—',
                    route_code: route?.code || route?.route_code || 'No active route',
                    passengers: Number(route?.passenger_count || manifest.length || 0),
                    recent_incidents: incidents.length
                },
                charts: {
                    passengers_by_stop: {
                        labels: Object.keys(stopTotals),
                        data: Object.values(stopTotals)
                    },
                    weekly_trips: {
                        labels: weekdayLabels.slice(1).concat(weekdayLabels[0]),
                        data: weekdayLabels.slice(1).concat(weekdayLabels[0]).map((day) => dayTotals[day])
                    }
                },
                tables: {
                    schedule: schedules.map((row) => ({
                        ...row,
                        route_name: route?.name || route?.route_name || '—',
                        registration_number: vehicle?.registration_number || route?.registration_number || '—'
                    })),
                    manifest
                }
            };
        },

        cards: [
            { id: 'drvVehicle', value: (data) => data.cards?.vehicle_registration || '—', format: 'text', subtitleId: 'drvVehicleSub', subtitle: (data) => data.cards?.vehicle_status || 'No vehicle assigned' },
            { id: 'drvRoute', value: (data) => data.cards?.route_name || '—', format: 'text', subtitleId: 'drvRouteSub', subtitle: (data) => data.cards?.route_code || 'No active route' },
            { id: 'drvPassengers', path: 'cards.passengers', subtitleId: 'drvPassengersSub', subtitle: 'Active route assignments' },
            { id: 'drvIncidents', path: 'cards.recent_incidents', subtitleId: 'drvIncidentsSub', subtitle: 'Most recent route or vehicle incidents' }
        ],
        chartDefinitions: [
            { id: 'drvManifestChart', path: 'charts.passengers_by_stop', label: 'Passengers', type: 'doughnut', showLegend: true },
            { id: 'drvScheduleChart', path: 'charts.weekly_trips', label: 'Trips', type: 'bar' }
        ],
        tableDefinitions: [
            {
                bodyId: 'drvScheduleBody',
                path: 'tables.schedule',
                emptyText: 'No upcoming trips assigned.',
                columns: [
                    { value: (row) => row.date || 'Recurring', format: 'date' },
                    { key: 'pickup_time', format: 'time' },
                    { key: 'route_name' },
                    { key: 'registration_number' }
                ]
            },
            {
                bodyId: 'drvManifestBody',
                path: 'tables.manifest',
                emptyText: 'No active passengers assigned.',
                columns: [
                    { value: (row) => row.admission_no || row.student_admission_no || '—' },
                    { value: (row) => row.student_name || [row.first_name, row.last_name].filter(Boolean).join(' ') || '—' },
                    { value: (row) => row.pickup_stop || row.pickup_stop_name || row.stop_name || '—' },
                    { value: (row) => row.dropoff_stop || row.dropoff_stop_name || row.stop_name || '—' }
                ]
            }
        ]
    });

    window.DriverDashboardController = controller;
    window.driverDashboardController = controller;
    DashboardBaseController.boot(controller, 'DriverDashboardController');
})();
