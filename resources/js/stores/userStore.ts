import { defineStore } from 'pinia';
import { ref } from 'vue';
import { usersApi, rolesApi } from '@/api';
import { useToastStore } from './toastStore';
import type { User, ApiRole, Pagination } from '@/types';

export const useUserStore = defineStore('users', () => {
    const users = ref<User[]>([]);
    const selectedUser = ref<User | null>(null);
    const roles = ref<ApiRole[]>([]);
    const pagination = ref<Pagination>({ current_page: 1, last_page: 1, total: 0 });
    const loading = ref(false);

    // Pinia Cache Flags
    const isLoaded = ref(false);
    const rolesLoaded = ref(false);

    const toast = useToastStore();

    async function fetchUsers(
        params?: { search?: string; role?: string; status?: string; library_id?: number | string; page?: number },
        force = false
    ) {
        const hasFilters = Boolean(params && (params.search || params.role || params.status || params.library_id || (params.page && params.page > 1)));

        // Return cached users if already loaded and no specific filter params requested
        if (!force && isLoaded.value && !hasFilters && users.value.length > 0) {
            return;
        }

        loading.value = true;
        try {
            const { data } = await usersApi.list(params);
            const rawUsers = data.data || [];
            // Super Admin must not be visible in user management
            users.value = rawUsers.filter((u: any) => !u.roles?.some((r: any) => (r.name || r) === 'Super Admin'));
            pagination.value = data.pagination;
            if (!hasFilters) {
                isLoaded.value = true;
            }
        } catch {
            toast.error('Failed to load users.');
        } finally {
            loading.value = false;
        }
    }

    async function fetchRoles(force = false) {
        if (!force && rolesLoaded.value && roles.value.length > 0) {
            return;
        }

        try {
            const { data } = await rolesApi.list();
            roles.value = data.roles;
            rolesLoaded.value = true;
        } catch {
            toast.error('Failed to load roles.');
        }
    }

    async function createUser(formData: Parameters<typeof usersApi.create>[0]) {
        loading.value = true;
        try {
            await usersApi.create(formData);
            toast.success('User created successfully.');
            await fetchUsers(undefined, true);
            return true;
        } catch (err: any) {
            const errors = err.response?.data?.errors;
            if (errors) {
                const firstError = Object.values(errors).flat()[0] as string;
                toast.error(firstError);
            } else {
                toast.error(err.response?.data?.message || 'Failed to create user.');
            }
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function updateUser(id: number, formData: Parameters<typeof usersApi.update>[1]) {
        loading.value = true;
        try {
            await usersApi.update(id, formData);
            toast.success('User updated successfully.');
            await fetchUsers(undefined, true);
            return true;
        } catch (err: any) {
            const errors = err.response?.data?.errors;
            if (errors) {
                const firstError = Object.values(errors).flat()[0] as string;
                toast.error(firstError);
            } else {
                toast.error(err.response?.data?.message || 'Failed to update user.');
            }
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function deleteUser(id: number) {
        try {
            await usersApi.destroy(id);
            toast.success('User deleted.');
            await fetchUsers(undefined, true);
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete user.');
        }
    }

    async function overridePermissions(userId: number, permissions: string[]) {
        loading.value = true;
        try {
            const { data } = await usersApi.overridePermissions(userId, permissions);
            toast.success(data.message || 'Direct permissions updated successfully.');
            await fetchUsers(undefined, true);
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to update direct permissions.');
            return false;
        } finally {
            loading.value = false;
        }
    }

    function invalidateCache() {
        isLoaded.value = false;
        rolesLoaded.value = false;
    }

    return {
        users,
        selectedUser,
        roles,
        pagination,
        loading,
        isLoaded,
        rolesLoaded,
        fetchUsers,
        fetchRoles,
        createUser,
        updateUser,
        deleteUser,
        overridePermissions,
        invalidateCache,
    };
});
