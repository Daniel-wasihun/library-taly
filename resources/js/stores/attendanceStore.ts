import { defineStore } from 'pinia';
import { ref, reactive } from 'vue';
import { attendanceApi } from '@/api';
import { useToastStore } from './toastStore';
import { useAuthStore } from './authStore';
import { useLanguageStore } from './languageStore';
import type { Attendance, AttendanceStats } from '@/types';

export const useAttendanceStore = defineStore('attendance', () => {
    const logs = ref<Attendance[]>([]);
    const loading = ref(false);
    const logging = ref(false);
    const statsLoading = ref(false);
    const stats = ref<AttendanceStats | null>(null);

    // Pinia Cache Flags
    const isLoaded = ref(false);
    const statsLoaded = ref(false);

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
            toast.error('Failed to fetch attendance records.');
        } finally {
            loading.value = false;
        }
    }

    async function logEntry(libraryId: number, userType: string, gender: string, count = 1) {
        logging.value = true;
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
            branches: 'Branch_Comparison',
            day_of_week: 'Day_Influx_Profile',
            intra_month: 'IntraMonth_Cycles',
            full: 'Official_Attendance_Executive_Audit',
        };
        const scopePrefix = scopeNames[scope] || 'Attendance_Audit';
        const ext = format;
        const filename = `WDU_${scopePrefix}_${Date.now()}.${ext}`;

        const mimeMap: Record<string, string> = {
            pdf: 'application/pdf',
            xlsx: 'application/vnd.ms-excel',
            doc: 'application/msword',
            csv: 'text/csv',
        };

        try {
            const response = await attendanceApi.downloadReport(params);
            const blob = new Blob([response.data], { type: mimeMap[format] || 'application/octet-stream' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

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
                id: `EXP-${Math.floor(100 + Math.random() * 900)}`,
                name: filename.replace(`.${ext}`, ''),
                format,
                date: langStore.isAmharic() ? 'ዛሬ' : 'Today',
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                scope: scopeLabel,
                size: estSize,
            });
            saveExportHistory();

            toast.success(
                langStore.isAmharic()
                    ? `ይፋዊ የ${format.toUpperCase()} ሪፖርት (${scopeLabel}) በተሳካ ሁኔታ ወርዷል።`
                    : `Official ${format.toUpperCase()} report (${scopeLabel}) downloaded successfully.`
            );
        } catch {
            toast.error(
                langStore.isAmharic()
                    ? 'ሪፖርቱን ማውረድ አልተቻለም። እባክዎ እንደገና ይሞክሩ።'
                    : 'Failed to generate official report download. Please retry.'
            );
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
        fetchLogs,
        logEntry,
        fetchStats,
        exportLogs,
        downloadFormalReport,
        exportHistory,
        updateLogCount,
        deleteLog,
        invalidateCache,
    };
});
