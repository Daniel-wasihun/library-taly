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
    const exportHistory = ref<Array<{
        id: string;
        name: string;
        format: 'xlsx' | 'doc' | 'csv' | 'pdf';
        date: string;
        timestamp: string;
        scope: string;
        size: string;
    }>>([
        {
            id: 'EXP-101',
            name: 'WDU_Official_Attendance_Executive_Audit',
            format: 'xlsx',
            date: 'Today',
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            scope: 'Academic Campus Facilities',
            size: '46 KB',
        },
        {
            id: 'EXP-102',
            name: 'WDU_Library_Demographic_Analysis_Memo',
            format: 'doc',
            date: 'Today',
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            scope: 'Academic Campus Facilities',
            size: '34 KB',
        },
        {
            id: 'EXP-103',
            name: 'WDU_Physical_Gate_Raw_Audit_Log',
            format: 'csv',
            date: 'Today',
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            scope: 'Academic Campus Facilities',
            size: '18 KB',
        },
    ]);

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

    async function downloadFormalReport(format: 'xlsx' | 'doc' | 'csv' | 'pdf', customFilters?: any) {
        if (!authStore.canManageReporting && !authStore.hasPermission('attendance.view') && !authStore.hasPermission('attendance.record')) {
            toast.error(langStore.isAmharic() ? 'ይህንን ሪፖርት የማውረድ ፈቃድ የለዎትም።' : 'You do not have permission to download this report.');
            return;
        }

        const params = {
            format,
            lang: langStore.currentLanguage,
            ...(customFilters || filters),
        };

        if (format === 'pdf') {
            const token = authStore.token || localStorage.getItem('auth_token') || '';
            const libParam = params.library_id ? `&library_id=${params.library_id}` : '';
            const dateFrom = params.date_from ? `&date_from=${params.date_from}` : '';
            const dateTo = params.date_to ? `&date_to=${params.date_to}` : '';
            const url = `/api/physical/attendance/report/print?lang=${params.lang}&token=${encodeURIComponent(token)}${libParam}${dateFrom}${dateTo}&auto_print=1`;
            window.open(url, '_blank');
            return;
        }

        try {
            const response = await attendanceApi.downloadReport(params);
            const blob = new Blob([response.data]);
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            const ext = format === 'xlsx' ? 'xlsx' : (format === 'doc' ? 'doc' : 'csv');
            const filename = `WDU_Library_Report_${Date.now()}.${ext}`;
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

            const estSize = Math.max(12, Math.round(blob.size / 1024)) + ' KB';
            exportHistory.value.unshift({
                id: `EXP-${Math.floor(100 + Math.random() * 900)}`,
                name: filename.replace(`.${ext}`, ''),
                format,
                date: 'Today',
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                scope: params.library_id ? 'Assigned Branch Facility' : 'All Campus Facilities',
                size: estSize,
            });

            toast.success(
                langStore.isAmharic()
                    ? `ይፋዊ የ${format.toUpperCase()} ሪፖርት በተሳካ ሁኔታ ወርዷል።`
                    : `Official ${format.toUpperCase()} report downloaded successfully.`
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
