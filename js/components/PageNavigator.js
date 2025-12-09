/**
 * PageNavigator Component
 * Handles in-page navigation for complex workflows without full page reload
 * Use cases:
 * - Class → Students → Profile drill-down
 * - Multi-step forms (admissions, exams)
 * - Tabbed interfaces (messaging, settings)
 * - Detail pages that need full UI (not just modals)
 */

class PageNavigator {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.container = document.getElementById(containerId);
        this.history = [];
        this.currentPage = null;
        this.pages = new Map();
        this.breadcrumbContainer = options.breadcrumbContainer || null;
        this.onNavigate = options.onNavigate || null;
        
        if (!this.container) {
            throw new Error(`Container with ID '${containerId}' not found`);
        }
        
        this.initializeBreadcrumbs();
    }

    /**
     * Register a page component
     * @param {string} pageId - Unique identifier for the page
     * @param {Object} config - Page configuration
     */
    registerPage(pageId, config) {
        this.pages.set(pageId, {
            title: config.title,
            render: config.render,           // Function that returns HTML
            onLoad: config.onLoad || null,   // Called after page is rendered
            onLeave: config.onLeave || null, // Called before leaving page
            parent: config.parent || null,   // For breadcrumb navigation
            data: config.data || {}          // Initial data
        });
    }

    /**
     * Navigate to a specific page
     * @param {string} pageId - ID of the page to navigate to
     * @param {Object} data - Data to pass to the page
     * @param {boolean} addToHistory - Whether to add to navigation history
     */
    async navigateTo(pageId, data = {}, addToHistory = true) {
        const page = this.pages.get(pageId);
        
        if (!page) {
            console.error(`Page '${pageId}' not found`);
            return;
        }

        // Call onLeave for current page
        if (this.currentPage && this.pages.get(this.currentPage)?.onLeave) {
            await this.pages.get(this.currentPage).onLeave();
        }

        // Add to history
        if (addToHistory && this.currentPage) {
            this.history.push({
                pageId: this.currentPage,
                data: this.pages.get(this.currentPage).data
            });
        }

        // Update current page data
        page.data = { ...page.data, ...data };

        // Render the page
        const html = await page.render(data);
        this.container.innerHTML = html;

        // Update state
        this.currentPage = pageId;

        // Update breadcrumbs
        this.updateBreadcrumbs(pageId);

        // Call onLoad
        if (page.onLoad) {
            await page.onLoad(data);
        }

        // Callback
        if (this.onNavigate) {
            this.onNavigate(pageId, data);
        }
    }

    /**
     * Navigate back to previous page
     */
    async goBack() {
        if (this.history.length === 0) {
            console.warn('No page history available');
            return;
        }

        const previous = this.history.pop();
        await this.navigateTo(previous.pageId, previous.data, false);
    }

    /**
     * Navigate to home/root page
     */
    async goHome() {
        this.history = [];
        const firstPageId = this.pages.keys().next().value;
        if (firstPageId) {
            await this.navigateTo(firstPageId, {}, false);
        }
    }

    /**
     * Clear navigation history
     */
    clearHistory() {
        this.history = [];
    }

    /**
     * Initialize breadcrumb container
     */
    initializeBreadcrumbs() {
        if (this.breadcrumbContainer) {
            const container = document.getElementById(this.breadcrumbContainer);
            if (container) {
                container.innerHTML = '<nav aria-label="breadcrumb"><ol class="breadcrumb" id="pageBreadcrumb"></ol></nav>';
            }
        }
    }

    /**
     * Update breadcrumb navigation
     */
    updateBreadcrumbs(currentPageId) {
        const breadcrumbEl = document.getElementById('pageBreadcrumb');
        if (!breadcrumbEl) return;

        const breadcrumbs = this.buildBreadcrumbs(currentPageId);
        breadcrumbEl.innerHTML = breadcrumbs.map((crumb, index) => {
            const isLast = index === breadcrumbs.length - 1;
            if (isLast) {
                return `<li class="breadcrumb-item active">${crumb.title}</li>`;
            } else {
                return `
                    <li class="breadcrumb-item">
                        <a href="#" data-navigate="${crumb.pageId}">${crumb.title}</a>
                    </li>
                `;
            }
        }).join('');

        // Attach click handlers
        breadcrumbEl.querySelectorAll('[data-navigate]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetPageId = e.target.dataset.navigate;
                
                // Remove history items after this page
                const targetIndex = this.history.findIndex(h => h.pageId === targetPageId);
                if (targetIndex !== -1) {
                    this.history = this.history.slice(0, targetIndex);
                }
                
                this.navigateTo(targetPageId, {}, false);
            });
        });
    }

    /**
     * Build breadcrumb trail
     */
    buildBreadcrumbs(pageId) {
        const breadcrumbs = [];
        let current = pageId;

        while (current) {
            const page = this.pages.get(current);
            if (page) {
                breadcrumbs.unshift({
                    pageId: current,
                    title: page.title
                });
                current = page.parent;
            } else {
                break;
            }
        }

        return breadcrumbs;
    }

    /**
     * Get current page ID
     */
    getCurrentPage() {
        return this.currentPage;
    }

    /**
     * Get current page data
     */
    getCurrentData() {
        return this.pages.get(this.currentPage)?.data || {};
    }

    /**
     * Update current page data
     */
    updateData(data) {
        if (this.currentPage) {
            const page = this.pages.get(this.currentPage);
            if (page) {
                page.data = { ...page.data, ...data };
            }
        }
    }
}

/**
 * TabNavigator Component
 * Handles tabbed navigation within a page
 * Use cases:
 * - Messaging (Inbox, Sent, Drafts, Compose)
 * - Settings (General, Security, Notifications)
 * - Student Profile (Bio, Academics, Attendance, Finance)
 */
class TabNavigator {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.container = document.getElementById(containerId);
        this.tabs = new Map();
        this.activeTab = null;
        this.tabsContainer = options.tabsContainer || `${containerId}Tabs`;
        this.contentContainer = options.contentContainer || `${containerId}Content`;
        this.onTabChange = options.onTabChange || null;
        
        if (!this.container) {
            throw new Error(`Container with ID '${containerId}' not found`);
        }

        this.initialize();
    }

    /**
     * Initialize tab structure
     */
    initialize() {
        this.container.innerHTML = `
            <ul class="nav nav-tabs mb-3" id="${this.tabsContainer}" role="tablist"></ul>
            <div class="tab-content" id="${this.contentContainer}"></div>
        `;
    }

    /**
     * Register a tab
     * @param {string} tabId - Unique tab identifier
     * @param {Object} config - Tab configuration
     */
    registerTab(tabId, config) {
        this.tabs.set(tabId, {
            label: config.label,
            icon: config.icon || null,
            badge: config.badge || null,
            render: config.render,           // Function that returns HTML
            onActivate: config.onActivate || null,
            onDeactivate: config.onDeactivate || null,
            permission: config.permission || null
        });
    }

    /**
     * Render all tabs
     */
    renderTabs() {
        const tabsEl = document.getElementById(this.tabsContainer);
        const contentEl = document.getElementById(this.contentContainer);
        
        if (!tabsEl || !contentEl) return;

        // Clear existing
        tabsEl.innerHTML = '';
        contentEl.innerHTML = '';

        let firstTab = null;

        // Render tab headers
        this.tabs.forEach((tab, tabId) => {
            // Check permission
            if (tab.permission && !AuthContext.hasPermission(tab.permission)) {
                return;
            }

            if (!firstTab) firstTab = tabId;

            const tabHeader = document.createElement('li');
            tabHeader.className = 'nav-item';
            tabHeader.role = 'presentation';
            
            let badgeHtml = '';
            if (tab.badge) {
                badgeHtml = `<span class="badge bg-danger ms-2">${tab.badge}</span>`;
            }

            let iconHtml = '';
            if (tab.icon) {
                iconHtml = `<i class="bi ${tab.icon} me-2"></i>`;
            }

            tabHeader.innerHTML = `
                <button class="nav-link ${tabId === this.activeTab ? 'active' : ''}" 
                        id="${tabId}-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#${tabId}-content" 
                        type="button" 
                        role="tab">
                    ${iconHtml}${tab.label}${badgeHtml}
                </button>
            `;

            tabsEl.appendChild(tabHeader);

            // Render tab content
            const tabContent = document.createElement('div');
            tabContent.className = `tab-pane fade ${tabId === this.activeTab ? 'show active' : ''}`;
            tabContent.id = `${tabId}-content`;
            tabContent.role = 'tabpanel';
            
            contentEl.appendChild(tabContent);

            // Attach event listener
            const button = tabHeader.querySelector('button');
            button.addEventListener('shown.bs.tab', () => {
                this.activateTab(tabId);
            });
        });

        // Activate first tab if none active
        if (!this.activeTab && firstTab) {
            this.activateTab(firstTab);
        }
    }

    /**
     * Activate a specific tab
     */
    async activateTab(tabId) {
        const tab = this.tabs.get(tabId);
        if (!tab) return;

        // Deactivate current tab
        if (this.activeTab && this.activeTab !== tabId) {
            const currentTab = this.tabs.get(this.activeTab);
            if (currentTab?.onDeactivate) {
                await currentTab.onDeactivate();
            }
        }

        // Render content
        const contentEl = document.getElementById(`${tabId}-content`);
        if (contentEl) {
            const html = await tab.render();
            contentEl.innerHTML = html;
        }

        // Update state
        this.activeTab = tabId;

        // Call onActivate
        if (tab.onActivate) {
            await tab.onActivate();
        }

        // Callback
        if (this.onTabChange) {
            this.onTabChange(tabId);
        }
    }

    /**
     * Update tab badge
     */
    updateBadge(tabId, value) {
        const tab = this.tabs.get(tabId);
        if (tab) {
            tab.badge = value;
            this.renderTabs();
        }
    }

    /**
     * Get active tab ID
     */
    getActiveTab() {
        return this.activeTab;
    }

    /**
     * Refresh current tab
     */
    async refresh() {
        if (this.activeTab) {
            await this.activateTab(this.activeTab);
        }
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PageNavigator, TabNavigator };
}
