import { defineStore } from 'pinia';
import { ref } from 'vue';
import { schedulesApi, rolesApi } from '@/api';
import { useToastStore } from './toastStore';
import type { DutySchedule, Pagination } from '@/types';

export const useScheduleStore = defineStore('schedules', () => {
    const schedules = ref<DutySchedule[]>([]);
    const myTurns = ref<DutySchedule[]>([]);
    const todayTurns = ref<DutySchedule[]>([]);
    const availableTasks = ref<Record<string, string>>({});
    const isAdmin = ref(false);
    const pagination = ref<Pagination>({ current_page: 1, last_page: 1, total: 0 });
    const loading = ref(false);

    const toast = useToastStore();

    async function fetchSchedules(params?: { user_id?: number; date?: string; task_key?: string; page?: number }) {
        loading.value = true;
        try {
            const { data } = await schedulesApi.list(params);
            schedules.value = data.data;
            availableTasks.value = data.available_tasks ?? {};
            pagination.value = data.pagination;
        } catch {
            toast.error('Failed to load schedules.');
        } finally {
            loading.value = false;
        }
    }

    async function fetchMyTurns() {
        try {
            const { data } = await schedulesApi.myTurns();
            myTurns.value = data.upcoming_turns;
            todayTurns.value = data.today_turns;
            availableTasks.value = data.available_tasks ?? {};
            isAdmin.value = data.is_admin;
        } catch {
            toast.error('Failed to load your turns.');
        }
    }

    async function createSchedule(formData: Parameters<typeof schedulesApi.create>[0]) {
        loading.value = true;
        try {
            await schedulesApi.create(formData);
            toast.success('Duty shift scheduled successfully.');
            await fetchSchedules();
            return true;
        } catch (err: any) {
            const errors = err.response?.data?.errors;
            toast.error(errors ? (Object.values(errors).flat()[0] as string) : (err.response?.data?.message || 'Failed to schedule shift.'));
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function deleteSchedule(id: number) {
        try {
            await schedulesApi.destroy(id);
            toast.success('Duty shift cancelled.');
            await fetchSchedules();
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to cancel shift.');
        }
    }

    return {
        schedules,
        myTurns,
        todayTurns,
        availableTasks,
        isAdmin,
        pagination,
        loading,
        fetchSchedules,
        fetchMyTurns,
        createSchedule,
        deleteSchedule,
    };
});
