import { ref } from 'vue';
import { offlineDb, type OfflineAttendanceRecord, type CachedDutyStatus } from './offlineStorage';
import { attendanceApi } from '@/api';
import { useToastStore } from '@/stores/toastStore';
import { useAuthStore } from '@/stores/authStore';

// Reactive network connectivity state
export const isOnline = ref<boolean>(typeof navigator !== 'undefined' ? navigator.onLine : true);
export const isSyncing = ref<boolean>(false);
export const pendingSyncCount = ref<number>(0);
export const lastSyncTime = ref<string | null>(null);

let syncInterval: any = null;
let networkListenersInitialized = false;

/**
 * Update pending counter count from IndexedDB
 */
export async function updatePendingCount(): Promise<number> {
    try {
        const pending = await offlineDb.getPendingRecords();
        pendingSyncCount.value = pending.length;
        return pending.length;
    } catch {
        pendingSyncCount.value = 0;
        return 0;
    }
}

/**
 * Prune and validate cached offline logs before syncing:
 * 1. Checks if each log was captured within the attendant's scheduled shift window
 * 2. Prunes/removes logs recorded on days/times outside the authorized schedule
 * 3. Returns only valid records to sync with the server
 */
export async function validateAndPruneOfflineRecords(records: OfflineAttendanceRecord[]): Promise<{
    valid: OfflineAttendanceRecord[];
    pruned: OfflineAttendanceRecord[];
}> {
    const valid: OfflineAttendanceRecord[] = [];
    const pruned: OfflineAttendanceRecord[] = [];

    // Retrieve cached duty status or schedule windows
    for (const record of records) {
        const recordDate = new Date(record.created_timestamp);
        const recordIsoDate = record.recorded_at.slice(0, 10);
        const cachedDuty = await offlineDb.getCachedDuty(record.library_id);

        let isValid = true;
        let pruneReason = '';

        if (cachedDuty) {
            // If cached duty says not allowed, verify if user had shift
            if (!cachedDuty.allowed) {
                isValid = false;
                pruneReason = cachedDuty.reason || 'Outside assigned duty schedule';
            } else if (cachedDuty.shift?.start_time && cachedDuty.shift?.end_time) {
                // Validate recorded time against shift hours
                const recHours = recordDate.getHours();
                const recMinutes = recordDate.getMinutes();
                const recTimeStr = `${String(recHours).padStart(2, '0')}:${String(recMinutes).padStart(2, '0')}`;

                const [startH, startM] = cachedDuty.shift.start_time.split(':').map(Number);
                const [endH, endM] = cachedDuty.shift.end_time.split(':').map(Number);

                const recTotal = recHours * 60 + recMinutes;
                const startTotal = startH * 60 + startM;
                const endTotal = endH * 60 + endM;

                if (startTotal <= endTotal) {
                    // Regular daytime shift
                    if (recTotal < startTotal || recTotal > endTotal) {
                        isValid = false;
                        pruneReason = `Recorded time (${recTimeStr}) is outside shift hours (${cachedDuty.shift.start_time} - ${cachedDuty.shift.end_time})`;
                    }
                } else {
                    // Overnight night shift (e.g. 17:00 to 05:00)
                    if (recTotal < startTotal && recTotal > endTotal) {
                        isValid = false;
                        pruneReason = `Recorded time (${recTimeStr}) is outside overnight shift hours (${cachedDuty.shift.start_time} - ${cachedDuty.shift.end_time})`;
                    }
                }
            }

            // Also check if record date matches duty schedule date if strict date was set
            if (cachedDuty.shift_date && cachedDuty.shift_date !== recordIsoDate) {
                isValid = false;
                pruneReason = `Recorded date (${recordIsoDate}) does not match assigned schedule date (${cachedDuty.shift_date})`;
            }
        }

        if (isValid) {
            valid.push(record);
        } else {
            record.sync_error = pruneReason;
            pruned.push(record);
        }
    }

    // Automatically remove pruned/invalid records from IndexedDB so they don't pollute storage
    if (pruned.length > 0) {
        const prunedUuids = pruned.map(r => r.client_uuid);
        await offlineDb.removeRecords(prunedUuids);
    }

    return { valid, pruned };
}

/**
 * Execute synchronization of pending offline attendance records
 */
export async function syncOfflineAttendance(force = false): Promise<{
    syncedCount: number;
    prunedCount: number;
    errors: string[];
}> {
    if (isSyncing.value) {
        return { syncedCount: 0, prunedCount: 0, errors: ['Sync already in progress'] };
    }

    if (!isOnline.value && !force) {
        return { syncedCount: 0, prunedCount: 0, errors: ['Device is currently offline'] };
    }

    const pending = await offlineDb.getPendingRecords();
    if (pending.length === 0) {
        pendingSyncCount.value = 0;
        return { syncedCount: 0, prunedCount: 0, errors: [] };
    }

    isSyncing.value = true;
    const toast = useToastStore();
    const errors: string[] = [];
    let syncedTotal = 0;
    let prunedTotal = 0;

    try {
        // Step 1: Validate and prune out-of-schedule records locally
        const { valid, pruned } = await validateAndPruneOfflineRecords(pending);
        prunedTotal = pruned.length;

        if (prunedTotal > 0) {
            console.log(`[Sync] Pruned ${prunedTotal} offline record(s) recorded outside active shift schedules.`);
        }

        if (valid.length === 0) {
            await updatePendingCount();
            return { syncedCount: 0, prunedCount: prunedTotal, errors: [] };
        }

        // Step 2: Send valid records in batches of 50 to server
        const BATCH_SIZE = 50;
        for (let i = 0; i < valid.length; i += BATCH_SIZE) {
            const batch = valid.slice(i, i + BATCH_SIZE);
            const payload = {
                records: batch.map(r => ({
                    client_uuid: r.client_uuid,
                    library_id: r.library_id,
                    user_type: r.user_type,
                    gender: r.gender,
                    count: r.count,
                    recorded_at: r.recorded_at,
                })),
            };

            try {
                const res = await (attendanceApi as any).batchSync(payload);
                const data = res.data;

                if (data.success) {
                    const syncedUuids = (data.synced || []).map((s: any) => s.client_uuid);
                    if (syncedUuids.length > 0) {
                        await offlineDb.markAsSynced(syncedUuids);
                        syncedTotal += syncedUuids.length;
                    }

                    // Handle server-side rejected items (out of schedule / unauthorized)
                    if (data.rejected && data.rejected.length > 0) {
                        const rejectedUuids = data.rejected.map((r: any) => r.client_uuid);
                        await offlineDb.removeRecords(rejectedUuids);
                        prunedTotal += rejectedUuids.length;
                    }
                }
            } catch (err: any) {
                const msg = err?.response?.data?.message || err.message || 'Batch sync failed';
                errors.push(msg);
            }
        }

        lastSyncTime.value = new Date().toLocaleTimeString();
        await updatePendingCount();

        if (syncedTotal > 0) {
            // Exactly one clean green success toast upon successful sync
            toast.success(`Online: Synced ${syncedTotal} attendance counter record(s) successfully.`);
        }

    } catch (e: any) {
        errors.push(e.message || 'Sync error occurred');
    } finally {
        isSyncing.value = false;
    }

    return { syncedCount: syncedTotal, prunedCount: prunedTotal, errors };
}

/**
 * Initialize offline network listeners & automatic sync background queue
 */
export function initOfflineSyncService(): void {
    if (typeof window === 'undefined' || networkListenersInitialized) return;
    networkListenersInitialized = true;

    // Listen for online / offline browser events
    window.addEventListener('online', async () => {
        const wasOffline = !isOnline.value;
        isOnline.value = true;

        if (wasOffline) {
            const toast = useToastStore();
            const pending = await updatePendingCount();
            if (pending > 0) {
                // syncOfflineAttendance() will fire the single clean green toast upon completion
                await syncOfflineAttendance();
            } else {
                // Single green toast
                toast.success('Online: Network connection restored.');
            }
        }
    });

    window.addEventListener('offline', () => {
        if (isOnline.value) {
            isOnline.value = false;
            // Exactly one clean yellow warning toast when switching to offline mode
            const toast = useToastStore();
            toast.warning('Offline Mode: Attendance counter active. Data saved locally.');
        }
    });

    // Check initial pending records
    updatePendingCount();

    // Background sync cycle every 30 seconds if online
    if (!syncInterval) {
        syncInterval = setInterval(() => {
            if (isOnline.value && pendingSyncCount.value > 0 && !isSyncing.value) {
                syncOfflineAttendance();
            }
        }, 30000);
    }
}
