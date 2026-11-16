import { defineStore } from 'pinia';
import { ref } from 'vue';
import { rolesApi } from '@/api';
import { useToastStore } from './toastStore';
import type { ApiRole } from '@/types';

export const useRoleStore = defineStore('roles', () => {
    const roles = ref<any[]>([]);
    const permissions = ref<{ id: number; name: string; group?: string }[]>([]);
    const loading = ref(false);
    const isLoaded = ref(false);

    const toast = useToastStore();

    async function fetchRoles(force = false) {
        if (!force && isLoaded.value && roles.value.length > 0) {
            return;
        }

        loading.value = true;
        try {
            const { data } = await rolesApi.list();
            roles.value = data.roles || [];
            permissions.value = data.permissions || [];
            isLoaded.value = true;
        } catch {
            toast.error('Failed to load roles and permissions.');
        } finally {
            loading.value = false;
        }
    }

    async function createRole(data: { name: string; permissions?: string[] }) {
        loading.value = true;
        try {
            await rolesApi.create(data);
            toast.success('Role created successfully.');
            await fetchRoles(true);
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to create role.');
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function updateRole(id: number, data: { name: string; permissions?: string[] }) {
        loading.value = true;
        try {
            await rolesApi.update(id, data);
            toast.success('Role updated successfully.');
            await fetchRoles(true);
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to update role.');
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function deleteRole(id: number) {
        loading.value = true;
        try {
            await rolesApi.destroy(id);
            toast.success('Role deleted successfully.');
            await fetchRoles(true);
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete role.');
            return false;
        } finally {
            loading.value = false;
        }
    }

    function invalidateCache() {
        isLoaded.value = false;
    }

    return {
        roles,
        permissions,
        loading,
        isLoaded,
        fetchRoles,
        createRole,
        updateRole,
        deleteRole,
        invalidateCache,
    };
});
