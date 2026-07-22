/**
 * Academic Context Service
 * 
 * Provides centralized access to current academic context information
 * including academic year, term, calendar period, and operational status.
 * 
 * This service maintains the current academic state and provides
 * methods to check if various academic operations are permitted.
 */

(function() {
    'use strict';

    let academicInitPromise = null;
    let changesListenerStarted = false;

    const AcademicContext = {
        // State
        state: {
            currentAcademicYear: null,
            currentTerm: null,
            academicYearId: null,
            termId: null,
            calendarPeriod: null,
            schoolWeek: null,
            isAcademicOperationsOpen: false,
            isGradingOpen: false,
            isTimetableEditingOpen: false,
            isLoading: false,
            lastUpdated: null,
            subscribers: []
        },

        // Cache configuration
        cache: {
            academicYears: { ttl: 24 * 60 * 60 * 1000, data: null, timestamp: null },
            terms: { ttl: 6 * 60 * 60 * 1000, data: null, timestamp: null },
            context: { ttl: 5 * 60 * 1000, data: null, timestamp: null }
        },

        /**
         * Initialize Academic Context
         * Loads current academic context from server
         */
        async init() {
            if (academicInitPromise) return academicInitPromise;
            academicInitPromise = (async () => {
            if (this.state.isLoading) return;
            
            this.state.isLoading = true;
            
            try {
                await this.loadContext();
                this.notifySubscribers('initialized');
            } catch (error) {
                console.error('Failed to initialize Academic Context:', error);
                this.notifySubscribers('error', error);
            } finally {
                this.state.isLoading = false;
            }
            return this.getState();
            })();
            return academicInitPromise;
        },

        /**
         * Load current academic context from server
         */
        async loadContext() {
            try {
                // apiCall() returns the unwrapped payload (response.data), not the
                // {success,data} envelope. getContext() is the method that exists on
                // API.academic (it was previously named getCurrentContext).
                //
                // Retry once with a short backoff: this call fires during dashboard
                // bootstrap, before the service worker has taken control of the page.
                // A transient "NetworkError" in that window must not hard-fail the
                // dashboard — fall back to any cached value via the SW's
                // stale-while-revalidate strategy instead.
                let response;
                try {
                    response = await window.API.academic.getContext();
                } catch (firstErr) {
                    if (/NetworkError|Failed to fetch|network/i.test(String(firstErr && firstErr.message || ''))) {
                        await new Promise(r => setTimeout(r, 600));
                        response = await window.API.academic.getContext();
                    } else {
                        throw firstErr;
                    }
                }

                if (response && (response.current_year || response.academic_year_id || response.term_id)) {
                    this.state.currentAcademicYear = response.current_year;
                    this.state.currentTerm = response.current_term;
                    this.state.academicYearId = response.academic_year_id;
                    this.state.termId = response.term_id;
                    this.state.calendarPeriod = response.calendar_period;
                    this.state.schoolWeek = response.school_week;
                    this.state.isAcademicOperationsOpen = response.operations_open || false;
                    this.state.isGradingOpen = response.grading_open || false;
                    this.state.isTimetableEditingOpen = response.timetable_editing_open || false;
                    this.state.lastUpdated = new Date().toISOString();
                    
                    // Cache the context
                    this.cache.context.data = this.state;
                    this.cache.context.timestamp = Date.now();
                    
                    // Broadcast context change
                    this.broadcastChange('CONTEXT_UPDATED');
                }
            } catch (error) {
                console.error('Error loading academic context:', error);
                const cached = this.cache.context.data;
                if (cached) {
                    Object.assign(this.state, cached, {
                        isLoading: false,
                        subscribers: this.state.subscribers
                    });
                    return;
                }

                this.state.lastUpdated = null;
            }
        },

        /**
         * Get current academic year
         */
        getCurrentAcademicYear() {
            return this.state.currentAcademicYear;
        },

        /**
         * Get current term
         */
        getCurrentTerm() {
            return this.state.currentTerm;
        },

        /**
         * Get academic year ID
         */
        getAcademicYearId() {
            return this.state.academicYearId;
        },

        /**
         * Get term ID
         */
        getTermId() {
            return this.state.termId;
        },

        /**
         * Get calendar period
         */
        getCalendarPeriod() {
            return this.state.calendarPeriod;
        },

        /**
         * Get current school week
         */
        getSchoolWeek() {
            return this.state.schoolWeek;
        },

        /**
         * Check if academic operations are open
         */
        areOperationsOpen() {
            return this.state.isAcademicOperationsOpen;
        },

        /**
         * Check if grading is open
         */
        isGradingOpen() {
            return this.state.isGradingOpen;
        },

        /**
         * Check if timetable editing is open
         */
        isTimetableEditingOpen() {
            return this.state.isTimetableEditingOpen;
        },

        /**
         * Check if specific operation is permitted
         */
        canPerformOperation(operation) {
            const permissions = {
                'grade_entry': this.state.isGradingOpen,
                'marks_entry': this.state.isGradingOpen,
                'timetable_edit': this.state.isTimetableEditingOpen,
                'class_assignment': this.state.isAcademicOperationsOpen,
                'student_promotion': this.state.isAcademicOperationsOpen,
                'report_card_generation': this.state.isGradingOpen
            };
            
            return permissions[operation] || false;
        },

        /**
         * Get all academic years (with caching)
         */
        async getAcademicYears(forceRefresh = false) {
            if (!forceRefresh && this.isCacheValid('academicYears')) {
                return this.cache.academicYears.data;
            }

            try {
                const response = await window.API.students.getAllAcademicYears();
                
                if (response.success && response.data) {
                    this.cache.academicYears.data = response.data;
                    this.cache.academicYears.timestamp = Date.now();
                    return response.data;
                }
            } catch (error) {
                console.error('Error loading academic years:', error);
                throw error;
            }
        },

        /**
         * Get terms for a specific academic year (with caching)
         */
        async getTerms(academicYearId, forceRefresh = false) {
            if (!forceRefresh && this.isCacheValid('terms')) {
                return this.cache.terms.data;
            }

            try {
                const response = await window.API.students.getAcademicYearTerms(academicYearId);
                
                if (response.success && response.data) {
                    this.cache.terms.data = response.data;
                    this.cache.terms.timestamp = Date.now();
                    return response.data;
                }
            } catch (error) {
                console.error('Error loading terms:', error);
                throw error;
            }
        },

        /**
         * Set current academic year
         */
        async setCurrentAcademicYear(yearId) {
            try {
                const response = await window.API.students.setCurrentAcademicYear(yearId);
                
                if (response.success) {
                    // Reload context
                    await this.loadContext();
                    // Invalidate caches
                    this.invalidateCache('context');
                    this.broadcastChange('ACADEMIC_YEAR_CHANGED');
                    this.notifySubscribers('yearChanged', yearId);
                    return true;
                }
            } catch (error) {
                console.error('Error setting current academic year:', error);
                throw error;
            }
        },

        /**
         * Set current term
         */
        async setCurrentTerm(termId) {
            try {
                const response = await window.API.call('/api/academic/terms/' + termId + '/activate', 'POST');
                
                if (response.success) {
                    // Reload context
                    await this.loadContext();
                    // Invalidate caches
                    this.invalidateCache('context');
                    this.broadcastChange('TERM_CHANGED');
                    this.notifySubscribers('termChanged', termId);
                    return true;
                }
            } catch (error) {
                console.error('Error setting current term:', error);
                throw error;
            }
        },

        /**
         * Refresh context from server
         */
        async refresh() {
            await this.loadContext();
            this.notifySubscribers('refreshed');
        },

        /**
         * Check if cache is valid
         */
        isCacheValid(cacheKey) {
            const cache = this.cache[cacheKey];
            if (!cache || !cache.data || !cache.timestamp) return false;
            return (Date.now() - cache.timestamp) < cache.ttl;
        },

        /**
         * Invalidate specific cache
         */
        invalidateCache(cacheKey) {
            if (this.cache[cacheKey]) {
                this.cache[cacheKey].data = null;
                this.cache[cacheKey].timestamp = null;
            }
        },

        /**
         * Invalidate all caches
         */
        invalidateAllCaches() {
            Object.keys(this.cache).forEach(key => {
                this.invalidateCache(key);
            });
        },

        /**
         * Subscribe to context changes
         */
        subscribe(callback) {
            if (typeof callback === 'function') {
                this.state.subscribers.push(callback);
                // Immediately call with current state
                callback(this.state);
            }
        },

        /**
         * Unsubscribe from context changes
         */
        unsubscribe(callback) {
            const index = this.state.subscribers.indexOf(callback);
            if (index > -1) {
                this.state.subscribers.splice(index, 1);
            }
        },

        /**
         * Notify all subscribers
         */
        notifySubscribers(event, data) {
            this.state.subscribers.forEach(callback => {
                try {
                    callback(this.state, event, data);
                } catch (error) {
                    console.error('Error in AcademicContext subscriber:', error);
                }
            });
        },

        /**
         * Broadcast context change via BroadcastChannel
         */
        broadcastChange(eventType) {
            if (typeof BroadcastChannel !== 'undefined') {
                try {
                    const channel = new BroadcastChannel('academic_context');
                    channel.postMessage({
                        type: eventType,
                        timestamp: Date.now(),
                        context: this.state
                    });
                } catch (error) {
                    console.warn('BroadcastChannel not available:', error);
                }
            }
        },

        /**
         * Listen for context changes from other tabs
         */
        listenForChanges() {
            if (changesListenerStarted) return;
            changesListenerStarted = true;
            if (typeof BroadcastChannel !== 'undefined') {
                try {
                    const channel = new BroadcastChannel('academic_context');
                    channel.onmessage = (event) => {
                        if (event.data && event.data.type) {
                            switch (event.data.type) {
                                case 'CONTEXT_UPDATED':
                                case 'ACADEMIC_YEAR_CHANGED':
                                case 'TERM_CHANGED':
                                    // Reload context when other tabs make changes
                                    this.loadContext();
                                    break;
                            }
                        }
                    };
                } catch (error) {
                    console.warn('BroadcastChannel not available:', error);
                }
            }
        },

        /**
         * Get full context state
         */
        getState() {
            return { ...this.state };
        },

        /**
         * Check if context is loaded
         */
        isLoaded() {
            return this.state.currentAcademicYear !== null;
        },

        /**
         * Check if context is loading
         */
        isLoading() {
            return this.state.isLoading;
        }
    };

    // Expose globally
    window.AcademicContext = AcademicContext;

})();
