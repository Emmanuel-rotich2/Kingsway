/**
 * Academic Data Offline Storage Service
 * Provides offline support for academic data using IndexedDB
 * Enables caching and synchronization of academic context, classes, subjects, etc.
 */

const AcademicOfflineService = (() => {
    const DB_NAME = 'KingswayAcademyOffline';
    const DB_VERSION = 1;
    const STORES = {
        academicContext: 'academic_context',
        academicYears: 'academic_years',
        academicTerms: 'academic_terms',
        classes: 'classes',
        subjects: 'subjects',
        learningAreas: 'learning_areas',
        schemesOfWork: 'schemes_of_work',
        lessonPlans: 'lesson_plans',
        assessments: 'assessments',
        results: 'results',
        syncQueue: 'sync_queue'
    };

    let db = null;
    let isOnline = navigator.onLine;
    let syncInProgress = false;

    /**
     * Initialize IndexedDB database
     */
    async function initDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                db = request.result;
                resolve(db);
            };

            request.onupgradeneeded = (event) => {
                const database = event.target.result;

                // Create object stores for each data type
                Object.values(STORES).forEach(storeName => {
                    if (!database.objectStoreNames.contains(storeName)) {
                        const store = database.createObjectStore(storeName, { keyPath: 'id', autoIncrement: true });
                        store.createIndex('updatedAt', 'updatedAt', { unique: false });
                    }
                });

                // Create additional indexes for performance
                const contextStore = database.objectStore(STORES.academicContext);
                contextStore.createIndex('isCurrent', 'isCurrent', { unique: false });

                const classesStore = database.objectStore(STORES.classes);
                classesStore.createIndex('class_name', 'class_name', { unique: false });

                const subjectsStore = database.objectStore(STORES.subjects);
                subjectsStore.createIndex('subject_name', 'subject_name', { unique: false });
            };
        });
    }

    /**
     * Store data in IndexedDB
     */
    async function storeData(storeName, data) {
        if (!db) await initDB();
        
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            
            // Add timestamp for sync purposes
            const dataWithTimestamp = Array.isArray(data) 
                ? data.map(item => ({ ...item, updatedAt: new Date().toISOString() }))
                : { ...data, updatedAt: new Date().toISOString() };

            const request = Array.isArray(dataWithTimestamp) 
                ? store.clear().then(() => {
                    dataWithTimestamp.forEach(item => store.put(item));
                })
                : store.put(dataWithTimestamp);

            transaction.oncomplete = () => resolve(dataWithTimestamp);
            transaction.onerror = () => reject(transaction.error);
        });
    }

    /**
     * Retrieve data from IndexedDB
     */
    async function getData(storeName) {
        if (!db) await initDB();
        
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get specific item by ID
     */
    async function getItem(storeName, id) {
        if (!db) await initDB();
        
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(id);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Cache academic context
     */
    async function cacheAcademicContext(context) {
        await storeData(STORES.academicContext, context);
        console.log('Academic context cached offline');
    }

    /**
     * Get cached academic context
     */
    async function getCachedAcademicContext() {
        try {
            const contexts = await getData(STORES.academicContext);
            return contexts.find(c => c.isCurrent) || contexts[0];
        } catch (error) {
            console.error('Failed to get cached context:', error);
            return null;
        }
    }

    /**
     * Cache academic years
     */
    async function cacheAcademicYears(years) {
        await storeData(STORES.academicYears, years);
        console.log('Academic years cached offline');
    }

    /**
     * Get cached academic years
     */
    async function getCachedAcademicYears() {
        try {
            return await getData(STORES.academicYears);
        } catch (error) {
            console.error('Failed to get cached years:', error);
            return [];
        }
    }

    /**
     * Cache academic terms
     */
    async function cacheAcademicTerms(terms) {
        await storeData(STORES.academicTerms, terms);
        console.log('Academic terms cached offline');
    }

    /**
     * Get cached academic terms
     */
    async function getCachedAcademicTerms() {
        try {
            return await getData(STORES.academicTerms);
        } catch (error) {
            console.error('Failed to get cached terms:', error);
            return [];
        }
    }

    /**
     * Cache classes
     */
    async function cacheClasses(classes) {
        await storeData(STORES.classes, classes);
        console.log('Classes cached offline');
    }

    /**
     * Get cached classes
     */
    async function getCachedClasses() {
        try {
            return await getData(STORES.classes);
        } catch (error) {
            console.error('Failed to get cached classes:', error);
            return [];
        }
    }

    /**
     * Cache subjects
     */
    async function cacheSubjects(subjects) {
        await storeData(STORES.subjects, subjects);
        console.log('Subjects cached offline');
    }

    /**
     * Get cached subjects
     */
    async function getCachedSubjects() {
        try {
            return await getData(STORES.subjects);
        } catch (error) {
            console.error('Failed to get cached subjects:', error);
            return [];
        }
    }

    /**
     * Add operation to sync queue
     */
    async function addToSyncQueue(operation) {
        if (!db) await initDB();
        
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([STORES.syncQueue], 'readwrite');
            const store = transaction.objectStore(STORES.syncQueue);
            
            const syncItem = {
                ...operation,
                createdAt: new Date().toISOString(),
                synced: false
            };

            const request = store.add(syncItem);
            request.onsuccess = () => resolve(syncItem);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get unsynced operations
     */
    async function getUnsyncedOperations() {
        if (!db) await initDB();
        
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([STORES.syncQueue], 'readonly');
            const store = transaction.objectStore(STORES.syncQueue);
            const index = store.index('synced');
            const request = index.getAll(false);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Mark operation as synced
     */
    async function markAsSynced(operationId) {
        if (!db) await initDB();
        
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([STORES.syncQueue], 'readwrite');
            const store = transaction.objectStore(STORES.syncQueue);
            
            const getRequest = store.get(operationId);
            getRequest.onsuccess = () => {
                const data = getRequest.result;
                if (data) {
                    data.synced = true;
                    data.syncedAt = new Date().toISOString();
                    store.put(data);
                }
            };

            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);
        });
    }

    /**
     * Synchronize offline changes with server
     */
    async function syncWithServer() {
        if (syncInProgress || !isOnline) return;

        syncInProgress = true;
        console.log('Starting offline sync...');

        try {
            const unsyncedOperations = await getUnsyncedOperations();
            
            for (const operation of unsyncedOperations) {
                try {
                    await executeSyncOperation(operation);
                    await markAsSynced(operation.id);
                    console.log(`Synced operation: ${operation.type}`);
                } catch (error) {
                    console.error(`Failed to sync operation ${operation.id}:`, error);
                }
            }

            // Refresh cached data from server
            await refreshCachedData();
            
            console.log('Offline sync completed');
        } catch (error) {
            console.error('Sync failed:', error);
        } finally {
            syncInProgress = false;
        }
    }

    /**
     * Execute a single sync operation
     */
    async function executeSyncOperation(operation) {
        const { type, endpoint, method, data } = operation;
        
        try {
            const response = await window.API.apiCall(endpoint, method, data);
            return response;
        } catch (error) {
            throw new Error(`Sync operation failed: ${error.message}`);
        }
    }

    /**
     * Refresh cached data from server
     */
    async function refreshCachedData() {
        try {
            // Refresh academic context
            const contextResponse = await window.API.apiCall('academic/context', 'GET');
            if (contextResponse.success) {
                await cacheAcademicContext(contextResponse.data);
            }

            // Refresh years
            const yearsResponse = await window.API.apiCall('academic/years-list', 'GET');
            if (yearsResponse.data) {
                await cacheAcademicYears(yearsResponse.data);
            }

            // Refresh terms
            const termsResponse = await window.API.apiCall('academic/terms-list', 'GET');
            if (termsResponse.data) {
                await cacheAcademicTerms(termsResponse.data);
            }

            // Refresh classes
            const classesResponse = await window.API.apiCall('academic/classes-list', 'GET');
            if (classesResponse.data) {
                await cacheClasses(classesResponse.data);
            }

            // Refresh subjects
            const subjectsResponse = await window.API.apiCall('academic/subjects-list', 'GET');
            if (subjectsResponse.data) {
                await cacheSubjects(subjectsResponse.data);
            }

            console.log('Cached data refreshed from server');
        } catch (error) {
            console.error('Failed to refresh cached data:', error);
        }
    }

    /**
     * Clear all cached data
     */
    async function clearCache() {
        if (!db) await initDB();
        
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(Object.values(STORES), 'readwrite');
            
            Object.values(STORES).forEach(storeName => {
                const store = transaction.objectStore(storeName);
                store.clear();
            });

            transaction.oncomplete = () => {
                console.log('Cache cleared');
                resolve();
            };
            transaction.onerror = () => reject(transaction.error);
        });
    }

    /**
     * Initialize offline support
     */
    async function init() {
        try {
            await initDB();
            
            // Set up online/offline event listeners
            window.addEventListener('online', handleOnline);
            window.addEventListener('offline', handleOffline);
            
            // Initial sync if online
            if (isOnline) {
                await refreshCachedData();
            }
            
            console.log('Academic offline service initialized');
        } catch (error) {
            console.error('Failed to initialize offline service:', error);
        }
    }

    function handleOnline() {
        isOnline = true;
        console.log('Connection restored - syncing offline changes');
        syncWithServer();
        
        // Notify user
        if (window.showNotification) {
            window.showNotification('Connection restored', 'success');
        }
    }

    function handleOffline() {
        isOnline = false;
        console.log('Connection lost - working in offline mode');
        
        // Notify user
        if (window.showNotification) {
            window.showNotification('Working offline - changes will sync when connected', 'warning');
        }
    }

    /**
     * Check if currently online
     */
    function isCurrentlyOnline() {
        return isOnline;
    }

    /**
     * Get storage usage statistics
     */
    async function getStorageStats() {
        if (!db) await initDB();
        
        const stats = {};
        
        for (const storeName of Object.values(STORES)) {
            try {
                const data = await getData(storeName);
                stats[storeName] = {
                    count: data.length,
                    size: JSON.stringify(data).length
                };
            } catch (error) {
                stats[storeName] = { count: 0, size: 0, error: error.message };
            }
        }
        
        return stats;
    }

    return {
        init,
        isCurrentlyOnline,
        cacheAcademicContext,
        getCachedAcademicContext,
        cacheAcademicYears,
        getCachedAcademicYears,
        cacheAcademicTerms,
        getCachedAcademicTerms,
        cacheClasses,
        getCachedClasses,
        cacheSubjects,
        getCachedSubjects,
        addToSyncQueue,
        syncWithServer,
        clearCache,
        getStorageStats
    };
})();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', AcademicOfflineService.init);
} else {
    AcademicOfflineService.init();
}
