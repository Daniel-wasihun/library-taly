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

    const toast = useToastStore();

    async function fetchUsers(params?: { search?: string; role?: string; page?: number }) {
        loading.value = true;
        try {
            const { data } = await usersApi.list(params);
            users.value = data.data;
            pagination.value = data.pagination;
        } catch {
            toast.error('Failed to load users.');
        } finally {
            loading.value = false;
        }
    }

    async function fetchRoles() {
        try {
            const { data } = await rolesApi.list();
            roles.value = data.roles;
        } catch {
            toast.error('Failed to load roles.');
        }
    }

    async function createUser(formData: Parameters<typeof usersApi.create>[0]) {
        loading.value = true;
        try {
            await usersApi.create(formData);
            toast.success('User created successfully.');
            await fetchUsers();
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
            await fetchUsers();
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
            await fetchUsers();
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete user.');
        }
    }

    return {
        users,
        selectedUser,
        roles,
        pagination,
        loading,
        fetchUsers,
        fetchRoles,
        createUser,
        updateUser,
        deleteUser,
    };
});
