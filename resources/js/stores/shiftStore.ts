import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/api/client';
import { useToastStore } from './toastStore';
import type {
    Shift,
    ShiftGroup,
    GroupShiftSchedule,
    ShiftMatrixResponse,
    User,
} from '@/types';

export const useShiftStore = defineStore('shift', () => {
    const toast = useToastStore();

    const loading = ref(false);
    const saving = ref(false);

    // Shifts
    const shifts = ref<Shift[]>([]);
    // Groups
    const groups = ref<ShiftGroup[]>([]);
    // Eligible staff for group assignment
    const eligibleStaff = ref<(User & { is_compatible: boolean })[]>([]);

    // Matrix & Ethiopian Scheduling State
    const matrixData = ref<ShiftMatrixResponse | null>(null);

    /**
     * Fetch all shift definitions
     */
    const fetchShifts = async () => {
        try {
            loading.value = true;
            const res = await api.get('/shifts');
            shifts.value = res.data.data || [];
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to load shift definitions');
        } finally {
            loading.value = false;
        }
    };

    /**
     * Create a shift definition
     */
    const createShift = async (payload: Partial<Shift>) => {
        try {
            saving.value = true;
            const res = await api.post('/shifts', payload);
            toast.success(res.data.message || 'Shift created successfully');
            await fetchShifts();
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to create shift');
            return false;
        } finally {
            saving.value = false;
        }
    };

    /**
     * Update a shift definition
     */
    const updateShift = async (id: number, payload: Partial<Shift>) => {
        try {
            saving.value = true;
            const res = await api.put(`/shifts/${id}`, payload);
            toast.success(res.data.message || 'Shift updated successfully');
            await fetchShifts();
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to update shift');
            return false;
        } finally {
            saving.value = false;
        }
    };

    /**
     * Delete a shift definition
     */
    const deleteShift = async (id: number) => {
        try {
            saving.value = true;
            const res = await api.delete(`/shifts/${id}`);
            toast.success(res.data.message || 'Shift deleted successfully');
            await fetchShifts();
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete shift');
            return false;
        } finally {
            saving.value = false;
        }
    };

    /**
     * Fetch shift groups, filterable by libraryId
     */
    const fetchGroups = async (libraryId?: number) => {
        try {
            loading.value = true;
            const res = await api.get('/shift-groups', {
                params: { library_id: libraryId },
            });
            groups.value = res.data.data || [];
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to load shift groups');
        } finally {
            loading.value = false;
        }
    };

    /**
     * Fetch staff eligible for group assignment
     */
    const fetchEligibleStaff = async (currentGroupId?: number) => {
        try {
            const res = await api.get('/shift-groups/eligible-staff', {
                params: { current_group_id: currentGroupId },
            });
            eligibleStaff.value = res.data.data || [];
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to load staff list');
        }
    };

    /**
     * Create a shift group
     */
    const createGroup = async (payload: {
        name: string;
        library_id: number | null;
        description?: string;
        user_ids?: number[];
    }) => {
        try {
            saving.value = true;
            const res = await api.post('/shift-groups', payload);
            toast.success(res.data.message || 'Shift group created successfully');
            await fetchGroups(payload.library_id ?? undefined);
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to create shift group');
            return false;
        } finally {
            saving.value = false;
        }
    };

    /**
     * Update a shift group
     */
    const updateGroup = async (
        id: number,
        payload: {
            name: string;
            library_id: number | null;
            description?: string;
            user_ids?: number[];
        }
    ) => {
        try {
            saving.value = true;
            const res = await api.put(`/shift-groups/${id}`, payload);
            toast.success(res.data.message || 'Shift group updated successfully');
            await fetchGroups(payload.library_id ?? undefined);
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to update shift group');
            return false;
        } finally {
            saving.value = false;
        }
    };

    /**
     * Delete a shift group
     */
    const deleteGroup = async (id: number, libraryId?: number) => {
        try {
            saving.value = true;
            const res = await api.delete(`/shift-groups/${id}`);
            toast.success(res.data.message || 'Shift group deleted');
            await fetchGroups(libraryId);
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete shift group');
            return false;
        } finally {
            saving.value = false;
        }
    };

    /**
     * Fetch monthly Ethiopian shift matrix & conflict maps
     */
    const fetchMatrix = async (params: {
        library_id?: number;
        ethiopian_year?: number;
        ethiopian_month?: number;
    }) => {
        try {
            loading.value = true;
            const res = await api.get('/shift-schedules/matrix', { params });
            matrixData.value = res.data;
            return res.data as ShiftMatrixResponse;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to load shift schedule matrix');
            return null;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Assign monthly Ethiopian schedule for a group across all library branches
     */
    const assignSchedule = async (payload: {
        library_id?: number;
        shift_group_id: number;
        ethiopian_year: number;
        ethiopian_month: number;
        shifts: { shift_id: number; days: number[] }[];
        notes?: string;
    }) => {
        try {
            saving.value = true;
            const res = await api.post('/shift-schedules/assign', payload);
            toast.success(res.data.message || 'Schedule assigned successfully across all branches');
            // Refresh matrix
            await fetchMatrix({
                library_id: payload.library_id,
                ethiopian_year: payload.ethiopian_year,
                ethiopian_month: payload.ethiopian_month,
            });
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to assign shift schedule');
            return false;
        } finally {
            saving.value = false;
        }
    };

    /**
     * Auto-generate balanced shift schedule with holiday exclusion & overlap check
     */
    const autoGenerateSchedule = async (payload: {
        library_id?: number;
        ethiopian_year: number;
        ethiopian_month: number;
        exclude_holidays?: boolean;
        overwrite_existing?: boolean;
    }) => {
        try {
            saving.value = true;
            const res = await api.post('/shift-schedules/auto-generate', payload);
            toast.success(res.data.message || 'Schedule auto-generated successfully with equal workload');
            await fetchMatrix({
                library_id: payload.library_id,
                ethiopian_year: payload.ethiopian_year,
                ethiopian_month: payload.ethiopian_month,
            });
            return res.data;
        } catch (err: any) {
            const errorMsg =
                err.response?.data?.errors?.staff_overlap?.[0] ||
                err.response?.data?.errors?.groups?.[0] ||
                err.response?.data?.message ||
                'Failed to auto-generate schedule';
            toast.error(errorMsg);
            return null;
        } finally {
            saving.value = false;
        }
    };

    /**
     * Check if any staff member is in multiple groups
     */
    const checkStaffOverlap = async (libraryId?: number) => {
        try {
            const res = await api.get('/shift-schedules/check-overlap', {
                params: { library_id: libraryId },
            });
            return res.data as {
                has_overlap: boolean;
                overlaps: { user_id: number; user_name: string; username: string; groups: string[] }[];
            };
        } catch {
            return { has_overlap: false, overlaps: [] };
        }
    };

    /**
     * Delete group schedule entry
     */
    const deleteGroupSchedule = async (
        id: number,
        refreshParams?: { library_id: number; ethiopian_year: number; ethiopian_month: number }
    ) => {
        try {
            saving.value = true;
            const res = await api.delete(`/shift-schedules/${id}`);
            toast.success(res.data.message || 'Schedule cleared');
            if (refreshParams) {
                await fetchMatrix(refreshParams);
            }
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to clear schedule');
            return false;
        } finally {
            saving.value = false;
        }
    };

    /**
     * Add manual user-inserted Ethiopian Holiday
     */
    const addHoliday = async (payload: {
        ethiopian_year: number;
        ethiopian_month: number;
        ethiopian_day: number;
        name: string;
        name_am?: string;
        description?: string;
        library_id?: number | null;
    }) => {
        try {
            saving.value = true;
            const res = await api.post('/shift-schedules/holidays', payload);
            toast.success(res.data.message || 'Holiday successfully saved');
            return res.data;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to save holiday');
            return null;
        } finally {
            saving.value = false;
        }
    };

    /**
     * Delete manual user-inserted Ethiopian Holiday
     */
    const deleteHoliday = async (id: number) => {
        try {
            saving.value = true;
            const res = await api.delete(`/shift-schedules/holidays/${id}`);
            toast.success(res.data.message || 'Holiday deleted');
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete holiday');
            return false;
        } finally {
            saving.value = false;
        }
    };

    return {
        loading,
        saving,
        shifts,
        groups,
        eligibleStaff,
        matrixData,
        fetchShifts,
        createShift,
        updateShift,
        deleteShift,
        fetchGroups,
        fetchEligibleStaff,
        createGroup,
        updateGroup,
        deleteGroup,
        fetchMatrix,
        assignSchedule,
        autoGenerateSchedule,
        checkStaffOverlap,
        deleteGroupSchedule,
        addHoliday,
        deleteHoliday,
    };
});
