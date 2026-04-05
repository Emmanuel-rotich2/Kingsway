/**
 * Support Staff Dashboard Controller
 * Populates support staff dashboard with schedule and task data
 */
const supportStaffDashboardController = Object.assign(
    {},
    typeof dashboardBaseController !== "undefined" ? dashboardBaseController : {},
    {
        dashboardName: "Support Staff",
        apiEndpoints: [
            "/api/dashboard/support/schedule",
            "/api/dashboard/support/tasks",
        ],
        config: Object.assign(
            {},
            typeof dashboardBaseController !== "undefined" && dashboardBaseController.config
                ? dashboardBaseController.config
                : {},
            { refreshInterval: 60000 }
        ),

        init: function () {
            console.log("Support Staff Dashboard initializing...");
            if (
                typeof AuthContext !== "undefined" &&
                !AuthContext.isAuthenticated()
            ) {
                window.location.href = "/Kingsway/index.php";
                return;
            }
            this.renderDashboard();
            this.setupAutoRefresh();
        },

        renderDashboard: function () {
            try {
                this.setupEventListeners();
                this.updateRefreshTime();
            } catch (e) {
                console.error("Support Staff dashboard render error:", e);
            }
        },

        setupEventListeners: function () {
        },

        updateRefreshTime: function () {
            const el = document.getElementById("lastRefreshTime");
            if (el) {
                el.textContent = new Date().toLocaleTimeString();
            }
        },
    }
);

document.addEventListener("DOMContentLoaded", function () {
    supportStaffDashboardController.init();
});
