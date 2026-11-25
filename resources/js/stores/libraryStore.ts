import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { librariesApi } from '@/api';
import { useToastStore } from './toastStore';
import type { Library } from '@/types';

export const DEFAULT_OFFLINE_LIBRARIES: Library[] = [
    {
        id: 1,
        name: 'Natural Science Library',
        code: 'NATURAL',
        campus: 'Main Campus',
        is_active: true,
        description: 'Natural sciences, computing, and mathematics reference branch',
    } as any,
    {
        id: 2,
        name: 'Social Science Library',
        code: 'SOCIAL',
        campus: 'Main Campus',
        is_active: true,
        description: 'Humanities, language studies, and social sciences collection',
    } as any,
    {
        id: 3,
        name: 'Technology Campus Library',
        code: 'TECH',
        campus: 'Technology Campus',
        is_active: true,
        description: 'Engineering, architecture, and technology disciplines',
    } as any,
    {
        id: 4,
        name: 'Health Science Library',
        code: 'HEALTH',
        campus: 'Health Science Campus',
        is_active: true,
        description: 'Medicine, nursing, public health, and pharmacy library',
    } as any,
];

function loadLocalCachedLibraries(): Library[] {
    try {
        const raw = localStorage.getItem('unilib_cached_libraries');
        if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed) && parsed.length > 0) return parsed;
        }
    } catch {
        // ignore
    }
    return DEFAULT_OFFLINE_LIBRARIES;
}

export const useLibraryStore = defineStore('library', () => {
    const libraries = ref<Library[]>(loadLocalCachedLibraries());
    const selectedLibrary = ref<Library | null>(null);
    const activeBranchFilter = ref<string | number>('all');
    const loading = ref(false);
    const isLoaded = ref(false);

    const toast = useToastStore();

    async function fetchLibraries(params?: { search?: string; active_only?: boolean }, force = false) {
        const hasFilters = Boolean(params && (params.search || params.active_only));

        // Return cached libraries if already loaded and no filter requested
        if (!force && isLoaded.value && !hasFilters && libraries.value.length > 0) {
            return;
        }

        loading.value = true;
        try {
            const { data } = await librariesApi.list(params);
            const fetched = data.data || [];
            if (fetched.length > 0) {
                libraries.value = fetched;
                try {
                    localStorage.setItem('unilib_cached_libraries', JSON.stringify(fetched));
                } catch {
                    // ignore
                }
            } else if (libraries.value.length === 0) {
                libraries.value = DEFAULT_OFFLINE_LIBRARIES;
            }
            if (!hasFilters) {
                isLoaded.value = true;
            }
        } catch {
            // Silently fallback to cached or default libraries when offline
            if (libraries.value.length === 0) {
                libraries.value = loadLocalCachedLibraries();
            }
            // Do NOT throw annoying error toast when offline or initial load
            if (typeof navigator !== 'undefined' && navigator.onLine) {
                toast.error('Failed to refresh library branches from server.');
            }
        } finally {
            loading.value = false;
        }
    }

    async function createLibrary(payload: Parameters<typeof librariesApi.create>[0]) {
        loading.value = true;
        try {
            const { data } = await librariesApi.create(payload);
            toast.success(data.message || 'Library branch created successfully.');
            await fetchLibraries(undefined, true);
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
            await fetchLibraries(undefined, true);
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
            await fetchLibraries(undefined, true);
            return true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete library branch.');
            return false;
        } finally {
            loading.value = false;
        }
    }

    function invalidateCache() {
        isLoaded.value = false;
    }

    const activeLibraries = computed(() => {
        return (libraries.value || []).filter((l) => l.is_active);
    });

    return {
        libraries,
        activeLibraries,
        selectedLibrary,
        activeBranchFilter,
        loading,
        isLoaded,
        fetchLibraries,
        createLibrary,
        updateLibrary,
        deleteLibrary,
        invalidateCache,
    };
});
