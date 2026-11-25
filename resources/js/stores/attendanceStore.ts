import { defineStore } from 'pinia';
import { ref, reactive } from 'vue';
import { attendanceApi } from '@/api';
import { useToastStore } from './toastStore';
import { useAuthStore } from './authStore';
import { useLanguageStore } from './languageStore';
import { useLibraryStore } from './libraryStore';
import type { Attendance, AttendanceStats } from '@/types';
import { offlineDb, type OfflineAttendanceRecord } from '@/services/offlineStorage';
import { isOnline, updatePendingCount, syncOfflineAttendance } from '@/services/syncService';
import { saveFileWithPickerOrFallback } from '@/utils/fileDownloader';

export const useAttendanceStore = defineStore('attendance', () => {
    const logs = ref<Attendance[]>([]);
    const loading = ref(false);
    const logging = ref(false);
    const statsLoading = ref(false);
    const stats = ref<AttendanceStats | null>(null);

    // Pinia Cache Flags
    const isLoaded = ref(false);
    const statsLoaded = ref(false);
    const isDownloadingReport = ref(false);
    const downloadingReportFormat = ref<string | null>(null);

    // Universal Document Preview Modal State
    const isPdfPreviewOpen = ref(false);
    const pdfPreviewUrl = ref<string | null>(null);
    const pdfPreviewBlob = ref<Blob | null>(null);
    const pdfPreviewFilename = ref<string>('');

    const openPdfPreview = (blob: Blob, filename: string) => {
        if (pdfPreviewUrl.value) {
            try { window.URL.revokeObjectURL(pdfPreviewUrl.value); } catch {}
        }
        pdfPreviewBlob.value = blob;
        pdfPreviewUrl.value = window.URL.createObjectURL(blob);
        pdfPreviewFilename.value = filename;
        isPdfPreviewOpen.value = true;
    };

    const closePdfPreview = () => {
        isPdfPreviewOpen.value = false;
        if (pdfPreviewUrl.value) {
            const oldUrl = pdfPreviewUrl.value;
            setTimeout(() => {
                try { window.URL.revokeObjectURL(oldUrl); } catch {}
            }, 60000);
            pdfPreviewUrl.value = null;
        }
        pdfPreviewBlob.value = null;
    };

    const loadSavedHistory = () => {
        try {
            const raw = sessionStorage.getItem('wdu_attendance_exports');
            return raw ? JSON.parse(raw) : [];
        } catch {
            return [];
        }
    };

    const exportHistory = ref<Array<{
        id: string;
        name: string;
        format: 'xlsx' | 'doc' | 'csv' | 'pdf';
        date: string;
        timestamp: string;
        scope: string;
        size: string;
    }>>(loadSavedHistory());

    const saveExportHistory = () => {
        try {
            sessionStorage.setItem('wdu_attendance_exports', JSON.stringify(exportHistory.value));
        } catch {
            // ignore
        }
    };

    const toast = useToastStore();
    const authStore = useAuthStore();
    const langStore = useLanguageStore();

    const pagination = reactive({
        currentPage: 1,
        lastPage: 1,
        perPage: 25,
        total: 0,
    });

    const filters = reactive({
        library_id: '' as string | number,
        user_type: '',
        gender: '',
        date_from: '',
        date_to: '',
    });

    async function fetchLogs(page = 1, perPage?: number, force = false) {
        if (perPage) {
            pagination.perPage = perPage;
        }

        const hasFilters = Boolean(filters.library_id || filters.user_type || filters.gender || filters.date_from || filters.date_to);

        // Use cache if already loaded on page 1 with no extra filters
        if (!force && isLoaded.value && page === 1 && !hasFilters && logs.value.length > 0) {
            return;
        }

        loading.value = true;
        try {
            const { data } = await attendanceApi.list({
                page,
                per_page: pagination.perPage,
                ...filters,
            });

            logs.value = data.data || [];
            pagination.currentPage = Number(data.current_page || 1);
            pagination.lastPage = Number(data.last_page || 1);
            pagination.total = Number(data.total || 0);

            if (page === 1 && !hasFilters) {
                isLoaded.value = true;
            }
        } catch {
            if (typeof navigator !== 'undefined' && navigator.onLine) {
                toast.error('Failed to fetch attendance records.');
            }
        } finally {
            loading.value = false;
        }
    }

    async function logEntry(libraryId: number, userType: string, gender: string, count = 1) {
        logging.value = true;
        const now = new Date();
        const clientUuid = 'att_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
        const recordedAt = now.toISOString();

        // ── OFFLINE-FIRST LOGIC ──────────────────────────────────────────────
        // If device is offline, record to IndexedDB immediately and silently
        if (!isOnline.value || (typeof navigator !== 'undefined' && !navigator.onLine)) {
            try {
                const libStore = useLibraryStore();
                const lib = libStore.libraries.find(l => Number(l.id) === Number(libraryId));

                const offlineRecord: OfflineAttendanceRecord = {
                    client_uuid: clientUuid,
                    library_id: Number(libraryId),
                    user_type: userType,
                    gender: gender as 'male' | 'female',
                    count: count,
                    recorded_at: recordedAt,
                    sync_status: 'pending',
                    library_name: lib?.name || 'Library #' + libraryId,
                    staff_name: authStore.user?.name || 'Attendant',
                    staff_id: authStore.user?.id,
                    created_timestamp: now.getTime(),
                };

                await offlineDb.addRecord(offlineRecord);
                await updatePendingCount();

                // Optimistically add to UI list for immediate feedback
                const mockItem: Attendance = {
                    id: -Math.floor(Math.random() * 100000),
                    slug: clientUuid,
                    library_id: Number(libraryId),
                    user_type: userType,
                    gender: gender,
                    visitor_count: count,
                    staff_id: authStore.user?.id || 1,
                    entry_at: recordedAt,
                    library: lib ? { id: lib.id, name: lib.name, code: lib.code } : undefined,
                    staff: authStore.user ? { id: authStore.user.id, name: authStore.user.name, email: authStore.user.email } : undefined,
                };

                logs.value.unshift(mockItem);
                pagination.total += count;

                // Handled silently - no annoying toast on counter entry
                return { success: true, data: mockItem, offline: true };
            } catch (err: any) {
                console.error('Failed to save offline log:', err);
                throw err;
            } finally {
                logging.value = false;
            }
        }

        // ── ONLINE WITH OFFLINE FALLBACK ─────────────────────────────────────
        try {
            const { data } = await attendanceApi.log({
                library_id: libraryId,
                user_type: userType,
                gender: gender,
                count: count,
            });

            // Optimistic update of local logs list for instant feedback
            if (pagination.currentPage === 1 && data.data) {
                logs.value.unshift(data.data);
                if (logs.value.length > pagination.perPage) {
                    logs.value.pop();
                }
                pagination.total += count;
            } else {
                await fetchLogs(1, undefined, true);
            }

            if (authStore.canManageReporting) {
                await fetchStats(true);
            }
            return data;
        } catch (err: any) {
            // If request failed due to network interruption or timeout, fallback to offline IndexedDB silently
            const isNetworkError = !err.response || err.code === 'ECONNABORTED' || err.message?.includes('Network');
            if (isNetworkError) {
                try {
                    const libStore = useLibraryStore();
                    const lib = libStore.libraries.find(l => Number(l.id) === Number(libraryId));

                    const offlineRecord: OfflineAttendanceRecord = {
                        client_uuid: clientUuid,
                        library_id: Number(libraryId),
                        user_type: userType,
                        gender: gender as 'male' | 'female',
                        count: count,
                        recorded_at: recordedAt,
                        sync_status: 'pending',
                        library_name: lib?.name || 'Library #' + libraryId,
                        staff_name: authStore.user?.name || 'Attendant',
                        staff_id: authStore.user?.id,
                        created_timestamp: now.getTime(),
                    };

                    await offlineDb.addRecord(offlineRecord);
                    await updatePendingCount();

                    // Handled silently
                    return { success: true, offline: true };
                } catch (storeErr: any) {
                    console.error('Failed to queue offline record:', storeErr);
                }
            }

            const msg = err.response?.data?.message || 'Failed to log attendance.';
            toast.error(msg);
            throw err;
        } finally {
            logging.value = false;
        }
    }

    async function fetchStats(force = false) {
        // Enforce reporting authorization: never make unpermitted stats requests
        if (!authStore.canManageReporting) {
            return;
        }

        const hasFilters = Boolean(filters.library_id || filters.date_from || filters.date_to);
        if (!force && statsLoaded.value && !hasFilters && stats.value) {
            return;
        }

        statsLoading.value = true;
        try {
            const { data } = await attendanceApi.stats({
                library_id: filters.library_id || undefined,
                date_from: filters.date_from || undefined,
                date_to: filters.date_to || undefined,
                lang: langStore.currentLanguage,
            });
            stats.value = data;
            if (!hasFilters) {
                statsLoaded.value = true;
            }
        } catch (err: any) {
            if (err?.response?.status === 403 || err?.response?.status === 401) {
                return;
            }
            toast.error('Failed to load attendance analytics.');
        } finally {
            statsLoading.value = false;
        }
    }

    async function exportLogs(customFilters?: any) {
        return downloadFormalReport('csv', customFilters);
    }

    async function downloadFormalReport(
        format: 'xlsx' | 'doc' | 'csv' | 'pdf',
        customFilters?: any,
        scope: 'full' | 'patron_types' | 'branches' | 'day_of_week' | 'intra_month' = 'full'
    ) {
        if (!authStore.canManageReporting && !authStore.hasPermission('attendance.view') && !authStore.hasPermission('attendance.record')) {
            toast.error(langStore.isAmharic() ? 'ይህንን ሪፖርት የማውረድ ፈቃድ የለዎትም።' : 'You do not have permission to download this report.');
            return;
        }

        const params: any = {
            format,
            scope,
            lang: langStore.currentLanguage,
            ...(customFilters || filters),
        };

        const scopeNames: Record<string, string> = {
            patron_types: 'Patron_Demographics',
            branches: 'Campus_Facilities_Comparison',
            day_of_week: 'Day_Of_Week_Influx_Profile',
            intra_month: 'Intra_Month_Cycles',
            full: 'Executive_Attendance_Audit',
        };
        const scopeSlug = scopeNames[scope] || 'Attendance_Audit';

        let facilitySlug = '';
        if (params.library_id) {
            const libraryStore = useLibraryStore();
            const lib = libraryStore.libraries.find(l => Number(l.id) === Number(params.library_id));
            if (lib) {
                const clean = lib.name.replace(/[^A-Za-z0-9]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
                if (clean) facilitySlug = clean + '_';
            }
        } else if (scope === 'full' || scope === 'patron_types') {
            facilitySlug = 'All_Libraries_';
        }

        const dateFrom = params.date_from || new Date().toISOString().split('T')[0];
        const dateTo = params.date_to || dateFrom;
        const dateSuffix = (dateFrom === dateTo) ? dateFrom : `${dateFrom}_to_${dateTo}`;

        const ext = format;
        const filename = `WDU_${facilitySlug}${scopeSlug}_${dateSuffix}.${ext}`;
        const auditId = `WDU-${scope.toUpperCase().substring(0, 4)}-${dateSuffix.replace(/-/g, '')}`;

        const mimeMap: Record<string, string> = {
            pdf: 'application/pdf',
            xlsx: 'application/vnd.ms-excel',
            doc: 'application/msword',
            csv: 'text/csv',
        };

        isDownloadingReport.value = true;
        downloadingReportFormat.value = format;

        toast.info(
            langStore.isAmharic()
                ? `የ${format.toUpperCase()} ይፋዊ ሰነድ በመዘጋጀት ላይ ነው፤ እባክዎ ጥቂት ይጠብቁ...`
                : `Generating official ${format.toUpperCase()} report, please wait...`
        );

        try {
            const response = await attendanceApi.downloadReport(params);
            const mimeType = mimeMap[format] || 'application/octet-stream';
            const blob = new Blob([response.data], { type: mimeType });

            // If PDF format, open in the window default PDF preview modal with save/print/share
            if (format === 'pdf') {
                openPdfPreview(blob, filename);
            } else {
                // For CSV, XLSX, DOC: prompt directory save picker or download to selected folder
                await saveFileWithPickerOrFallback({
                    blob,
                    filename,
                    mimeType,
                    title: filename,
                });
            }

            const estSize = Math.max(12, Math.round(blob.size / 1024)) + ' KB';
            
            const scopeLabelsAm: Record<string, string> = {
                patron_types: 'የተጠቃሚዎች ምድብ (Usage Counter)',
                branches: 'የቅርንጫፍ ንጽጽር (Branch Comparison)',
                day_of_week: 'የቀናት ፍሰት (Day Influx)',
                intra_month: 'የወሩ ሳምንታትና ቀናት (Intra-Month)',
                full: 'ሙሉ ይፋዊ ኦዲት (Full Audit)',
            };
            const scopeLabelsEn: Record<string, string> = {
                patron_types: 'Patron Demographics Only',
                branches: 'Branch Comparison Only',
                day_of_week: 'Day of Week Influx Only',
                intra_month: 'Intra-Month Cycles Only',
                full: 'Comprehensive Executive Audit',
            };

            const scopeLabel = langStore.isAmharic() 
                ? (scopeLabelsAm[scope] || scope) 
                : (scopeLabelsEn[scope] || scope);

            exportHistory.value.unshift({
                id: auditId,
                name: filename.replace(`.${ext}`, ''),
                format,
                date: langStore.isAmharic() ? 'ዛሬ' : 'Today',
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                scope: scopeLabel,
                size: estSize,
            });
            saveExportHistory();

            if (format === 'pdf') {
                toast.success(
                    langStore.isAmharic()
                        ? `ይፋዊ የ${format.toUpperCase()} ሪፖርት ቅድመ-እይታ ተከፍቷል፤ ወደ ፈለጉት ማህደር ማስቀመጥ ወይም ማተም ይችላሉ።`
                        : `Official ${format.toUpperCase()} report preview ready. You can save to any folder or print.`
                );
            } else {
                toast.success(
                    langStore.isAmharic()
                        ? `ይፋዊ የ${format.toUpperCase()} ሪፖርት በተሳካ ሁኔታ ተቀምጧል።`
                        : `Official ${format.toUpperCase()} report (${scopeLabel}) saved successfully.`
                );
            }
        } catch {
            toast.error(
                langStore.isAmharic()
                    ? 'ሪፖርቱን ማውረድ አልተቻለም። እባክዎ እንደገና ይሞክሩ።'
                    : 'Failed to generate official report download. Please retry.'
            );
        } finally {
            isDownloadingReport.value = false;
            downloadingReportFormat.value = null;
        }
    }

    async function updateLogCount(slug: string, count: number) {
        logging.value = true;
        try {
            const { data } = await attendanceApi.updateCount(slug, count);

            const idx = logs.value.findIndex((l) => l.slug === slug);
            if (idx !== -1 && data.data) {
                logs.value[idx] = data.data;
            }

            if (authStore.canManageReporting) {
                await fetchStats(true);
            }
            return data;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to update attendance count.');
            throw err;
        } finally {
            logging.value = false;
        }
    }

    async function deleteLog(slug: string) {
        loading.value = true;
        try {
            await attendanceApi.destroy(slug);
            logs.value = logs.value.filter((l) => l.slug !== slug);
            pagination.total = Math.max(0, pagination.total - 1);
            if (authStore.canManageReporting) {
                await fetchStats(true);
            }
            toast.success('Attendance entry cancelled.');
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Cannot delete entry: outside allowable time window.');
            return false;
        } finally {
            loading.value = false;
        }
    }

    function invalidateCache() {
        isLoaded.value = false;
        statsLoaded.value = false;
    }

    return {
        logs,
        loading,
        logging,
        statsLoading,
        stats,
        pagination,
        filters,
        isLoaded,
        statsLoaded,
        isDownloadingReport,
        downloadingReportFormat,
        fetchLogs,
        logEntry,
        fetchStats,
        exportLogs,
        downloadFormalReport,
        exportHistory,
        updateLogCount,
        deleteLog,
        invalidateCache,
        isPdfPreviewOpen,
        pdfPreviewUrl,
        pdfPreviewBlob,
        pdfPreviewFilename,
        openPdfPreview,
        closePdfPreview,
    };
});
