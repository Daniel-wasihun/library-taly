/**
 * IndexedDB offline storage for University Library Attendance Counter
 * Provides robust offline-first caching, queuing, and background synchronization.
 */

export interface OfflineAttendanceRecord {
    client_uuid: string;
    library_id: number;
    user_type: 'student' | 'teacher' | 'staff' | string;
    gender: 'male' | 'female';
    count: number;
    recorded_at: string; // ISO 8601 string
    sync_status: 'pending' | 'syncing' | 'synced' | 'rejected';
    sync_error?: string;
    library_name?: string;
    staff_name?: string;
    staff_id?: number;
    created_timestamp: number;
}

export interface CachedDutyStatus {
    allowed: boolean;
    reason: string;
    code?: string;
    task?: string;
    task_name?: string;
    library_id?: number;
    shift?: {
        name?: string;
        code?: string;
        start_time?: string;
        end_time?: string;
    } | null;
    shift_date?: string;
    next_turn?: string | null;
    cached_at: number;
}

const DB_NAME = 'unilib_offline_db';
const DB_VERSION = 1;
const STORE_ATTENDANCE = 'offline_attendance';
const STORE_DUTY = 'cached_duty_status';
const STORE_SETTINGS = 'offline_settings';

class OfflineAttendanceDB {
    private db: IDBDatabase | null = null;
    private initPromise: Promise<IDBDatabase> | null = null;

    /**
     * Open or initialize IndexedDB instance
     */
    public async getDB(): Promise<IDBDatabase> {
        if (this.db) return this.db;
        if (this.initPromise) return this.initPromise;

        this.initPromise = new Promise((resolve, reject) => {
            if (typeof window === 'undefined' || !window.indexedDB) {
                return reject(new Error('IndexedDB is not supported in this environment.'));
            }

            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = (event: IDBVersionChangeEvent) => {
                const db = (event.target as IDBOpenDBRequest).result;

                // 1. Attendance Queue Store
                if (!db.objectStoreNames.contains(STORE_ATTENDANCE)) {
                    const store = db.createObjectStore(STORE_ATTENDANCE, { keyPath: 'client_uuid' });
                    store.createIndex('sync_status', 'sync_status', { unique: false });
                    store.createIndex('recorded_at', 'recorded_at', { unique: false });
                    store.createIndex('library_id', 'library_id', { unique: false });
                }

                // 2. Cached Duty & Schedule Store
                if (!db.objectStoreNames.contains(STORE_DUTY)) {
                    db.createObjectStore(STORE_DUTY, { keyPath: 'library_id' });
                }

                // 3. Settings / Meta Store
                if (!db.objectStoreNames.contains(STORE_SETTINGS)) {
                    db.createObjectStore(STORE_SETTINGS, { keyPath: 'key' });
                }
            };

            request.onsuccess = () => {
                this.db = request.result;
                resolve(request.result);
            };

            request.onerror = () => {
                reject(request.error);
            };
        });

        return this.initPromise;
    }

    /**
     * Add an offline attendance record to IndexedDB
     */
    public async addRecord(record: OfflineAttendanceRecord): Promise<void> {
        const db = await this.getDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_ATTENDANCE, 'readwrite');
            const store = tx.objectStore(STORE_ATTENDANCE);
            const req = store.put(record);

            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Get all pending records to be synced
     */
    public async getPendingRecords(): Promise<OfflineAttendanceRecord[]> {
        const db = await this.getDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_ATTENDANCE, 'readonly');
            const store = tx.objectStore(STORE_ATTENDANCE);
            const index = store.index('sync_status');
            const req = index.getAll('pending');

            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Get all offline records (for offline feed display)
     */
    public async getAllRecords(limit = 100): Promise<OfflineAttendanceRecord[]> {
        const db = await this.getDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_ATTENDANCE, 'readonly');
            const store = tx.objectStore(STORE_ATTENDANCE);
            const req = store.getAll();

            req.onsuccess = () => {
                const list: OfflineAttendanceRecord[] = req.result || [];
                // Sort descending by recorded_at
                list.sort((a, b) => b.created_timestamp - a.created_timestamp);
                resolve(list.slice(0, limit));
            };
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Mark records as synced
     */
    public async markAsSynced(clientUuids: string[]): Promise<void> {
        const db = await this.getDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_ATTENDANCE, 'readwrite');
            const store = tx.objectStore(STORE_ATTENDANCE);

            let remaining = clientUuids.length;
            if (remaining === 0) return resolve();

            clientUuids.forEach(uuid => {
                const req = store.get(uuid);
                req.onsuccess = () => {
                    const record = req.result as OfflineAttendanceRecord;
                    if (record) {
                        record.sync_status = 'synced';
                        store.put(record);
                    }
                    remaining--;
                    if (remaining === 0) resolve();
                };
                req.onerror = () => {
                    remaining--;
                    if (remaining === 0) resolve();
                };
            });
        });
    }

    /**
     * Mark or delete rejected/pruned records (records outside scheduled window)
     */
    public async removeRecords(clientUuids: string[]): Promise<void> {
        const db = await this.getDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_ATTENDANCE, 'readwrite');
            const store = tx.objectStore(STORE_ATTENDANCE);

            let remaining = clientUuids.length;
            if (remaining === 0) return resolve();

            clientUuids.forEach(uuid => {
                const req = store.delete(uuid);
                req.onsuccess = () => {
                    remaining--;
                    if (remaining === 0) resolve();
                };
                req.onerror = () => {
                    remaining--;
                    if (remaining === 0) resolve();
                };
            });
        });
    }

    /**
     * Cache active duty status for offline checking
     */
    public async setCachedDuty(libraryId: number, duty: CachedDutyStatus): Promise<void> {
        const db = await this.getDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_DUTY, 'readwrite');
            const store = tx.objectStore(STORE_DUTY);
            const req = store.put({ library_id: libraryId, ...duty });
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Retrieve cached duty status
     */
    public async getCachedDuty(libraryId: number): Promise<CachedDutyStatus | null> {
        const db = await this.getDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_DUTY, 'readonly');
            const store = tx.objectStore(STORE_DUTY);
            const req = store.get(libraryId);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
    }
}

export const offlineDb = new OfflineAttendanceDB();
