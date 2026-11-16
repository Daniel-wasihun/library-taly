import { defineStore } from 'pinia';
import { ref } from 'vue';
import { librariesApi } from '@/api';
import { useToastStore } from './toastStore';
import type { Library } from '@/types';

export const useLibraryStore = defineStore('library', () => {
    const libraries = ref<Library[]>([]);
    const selectedLibrary = ref<Library | null>(null);
    const activeBranchFilter = ref<string | number>('all');
    const loading = ref(false);

    const toast = useToastStore();

    async function fetchLibraries(params?: { search?: string; active_only?: boolean }) {
        loading.value = true;
        try {
            const { data } = await librariesApi.list(params);
            libraries.value = data.data || [];
        } catch {
            toast.error('Failed to load library branches.');
        } finally {
            loading.value = false;
        }
    }

    async function createLibrary(payload: Parameters<typeof librariesApi.create>[0]) {
        loading.value = true;
        try {
            const { data } = await librariesApi.create(payload);
            toast.success(data.message || 'Library branch created successfully.');
            await fetchLibraries();
            return true;
        } catch (err: any) {
            const errors = err.response?.data?.errors;
            if (errors) {
                const firstError = Object.values(errors).flat()[0] as string;
                toast.error(firstError);
            } else {
                toast.error(err.response?.data?.message || 'Failed to create library branch.');
            }
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function updateLibrary(id: number, payload: Parameters<typeof librariesApi.update>[1]) {
        loading.value = true;
        try {
            const { data } = await librariesApi.update(id, payload);
            toast.success(data.message || 'Library branch updated successfully.');
            await fetchLibraries();
            return true;
        } catch (err: any) {
            const errors = err.response?.data?.errors;
            if (errors) {
                const firstError = Object.values(errors).flat()[0] as string;
                toast.error(firstError);
            } else {
                toast.error(err.response?.data?.message || 'Failed to update library branch.');
            }
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function deleteLibrary(id: number) {
        loading.value = true;
        try {
            const { data } = await librariesApi.destroy(id);
            toast.success(data.message || 'Library branch deleted successfully.');
            await fetchLibraries();
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete library branch.');
            return false;
        } finally {
            loading.value = false;
        }
    }

    function getLibraryById(id: number | null | undefined): Library | undefined {
        if (!id) return undefined;
        return libraries.value.find((l) => l.id === id);
    }

    return {
        libraries,
        selectedLibrary,
        activeBranchFilter,
        loading,
        fetchLibraries,
        createLibrary,
        updateLibrary,
        deleteLibrary,
        getLibraryById,
    };
});
