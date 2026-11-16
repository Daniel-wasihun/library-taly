import { defineStore } from 'pinia';
import { ref, reactive } from 'vue';
import { attendanceApi } from '@/api';
import { useToastStore } from './toastStore';
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

    const toast = useToastStore();

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

            await fetchStats(true);
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
            });
            stats.value = data;
            if (!hasFilters) {
                statsLoaded.value = true;
            }
        } catch {
            toast.error('Failed to load attendance analytics.');
        } finally {
            statsLoading.value = false;
        }
    }

    async function exportLogs(customFilters?: any) {
        try {
            const params = customFilters || { ...filters };
            const response = await attendanceApi.export(params);

            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `attendance_report_${Date.now()}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            toast.success('Attendance report exported successfully.');
        } catch {
            toast.error('Failed to export attendance report.');
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

            await fetchStats(true);
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
            await fetchStats(true);
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
        updateLogCount,
        deleteLog,
        invalidateCache,
    };
});
