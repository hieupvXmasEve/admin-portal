import { useTeachingAssignmentsStore } from '@/stores/teachingAssignments';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface SyncOptions {
    enabled?: boolean;
    interval?: number; // in milliseconds
    onConflict?: (localData: any, serverData: any) => 'local' | 'server' | 'merge';
    onError?: (error: Error) => void;
}

/**
 * Composable for real-time data synchronization
 * Handles automatic refresh, conflict resolution, and optimistic updates
 */
export function useRealTimeSync(options: SyncOptions = {}) {
    const {
        enabled = true,
        interval = 30000, // 30 seconds
        onConflict = () => 'server' as const,
        onError = console.error,
    } = options;

    const store = useTeachingAssignmentsStore();

    // State
    const isOnline = ref(navigator.onLine);
    const lastSyncTime = ref<Date | null>(null);
    const syncError = ref<string | null>(null);
    const pendingChanges = ref<any[]>([]);
    const isSyncing = ref(false);

    // Sync interval reference
    let syncInterval: NodeJS.Timeout | null = null;

    // Methods
    const performSync = async () => {
        if (!enabled || !isOnline.value || isSyncing.value) {
            return;
        }

        isSyncing.value = true;
        syncError.value = null;

        try {
            // Refresh assignments data
            await store.refreshAssignments();

            // Process any pending changes
            await processPendingChanges();

            lastSyncTime.value = new Date();
        } catch (error) {
            syncError.value = error instanceof Error ? error.message : 'Sync failed';
            onError(error instanceof Error ? error : new Error('Sync failed'));
        } finally {
            isSyncing.value = false;
        }
    };

    const processPendingChanges = async () => {
        if (pendingChanges.value.length === 0) {
            return;
        }

        const changes = [...pendingChanges.value];
        pendingChanges.value = [];

        for (const change of changes) {
            try {
                await applyChange(change);
            } catch (error) {
                // Re-add failed changes to pending list
                pendingChanges.value.push(change);
                throw error;
            }
        }
    };

    const applyChange = async (change: any) => {
        switch (change.type) {
            case 'assign':
                await store.assignLecturer(change.data);
                break;
            case 'unassign':
                await store.unassignLecturer(change.data.courseOfferingId);
                break;
            default:
                console.warn('Unknown change type:', change.type);
        }
    };

    const addPendingChange = (type: string, data: any) => {
        pendingChanges.value.push({
            id: Date.now() + Math.random(),
            type,
            data,
            timestamp: new Date(),
        });
    };

    const startSync = () => {
        if (syncInterval) {
            clearInterval(syncInterval);
        }

        if (enabled && interval > 0) {
            syncInterval = setInterval(performSync, interval);
            // Perform initial sync
            performSync();
        }
    };

    const stopSync = () => {
        if (syncInterval) {
            clearInterval(syncInterval);
            syncInterval = null;
        }
    };

    const forceSync = () => {
        return performSync();
    };

    // Optimistic updates
    const optimisticAssign = async (request: any) => {
        // Add to pending changes
        addPendingChange('assign', request);

        // Perform optimistic update to local state
        // This would update the UI immediately while the request is processed
        try {
            return await store.assignLecturer(request);
        } catch (error) {
            // Remove from pending if it failed immediately
            pendingChanges.value = pendingChanges.value.filter((change) => !(change.type === 'assign' && change.data === request));
            throw error;
        }
    };

    const optimisticUnassign = async (courseOfferingId: number) => {
        // Add to pending changes
        addPendingChange('unassign', { courseOfferingId });

        try {
            return await store.unassignLecturer(courseOfferingId);
        } catch (error) {
            // Remove from pending if it failed immediately
            pendingChanges.value = pendingChanges.value.filter(
                (change) => !(change.type === 'unassign' && change.data.courseOfferingId === courseOfferingId),
            );
            throw error;
        }
    };

    // Network status handlers
    const handleOnline = () => {
        isOnline.value = true;
        syncError.value = null;

        // Resume syncing when back online
        if (enabled) {
            performSync();
        }
    };

    const handleOffline = () => {
        isOnline.value = false;
        stopSync();
    };

    // Conflict resolution
    const resolveConflict = (localData: any, serverData: any) => {
        const resolution = onConflict(localData, serverData);

        switch (resolution) {
            case 'local':
                return localData;
            case 'server':
                return serverData;
            case 'merge':
                // Simple merge strategy - can be enhanced based on needs
                return { ...serverData, ...localData };
            default:
                return serverData;
        }
    };

    // Visibility change handler (pause sync when tab is hidden)
    const handleVisibilityChange = () => {
        if (document.hidden) {
            stopSync();
        } else if (enabled) {
            startSync();
        }
    };

    // Lifecycle
    onMounted(() => {
        // Set up event listeners
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        document.addEventListener('visibilitychange', handleVisibilityChange);

        // Start syncing
        if (enabled) {
            startSync();
        }
    });

    onUnmounted(() => {
        // Clean up
        stopSync();
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    });

    return {
        // State
        isOnline: isOnline,
        lastSyncTime: lastSyncTime,
        syncError: syncError,
        pendingChanges: pendingChanges,
        isSyncing: isSyncing,

        // Methods
        performSync,
        startSync,
        stopSync,
        forceSync,
        optimisticAssign,
        optimisticUnassign,
        addPendingChange,
        resolveConflict,

        // Computed
        hasPendingChanges: computed(() => pendingChanges.value.length > 0),
        isConnected: computed(() => isOnline.value && !syncError.value),
        syncStatus: computed(() => {
            if (!isOnline.value) return 'offline';
            if (syncError.value) return 'error';
            if (isSyncing.value) return 'syncing';
            if (pendingChanges.value.length > 0) return 'pending';
            return 'synced';
        }),
    };
}
