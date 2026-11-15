<template>
    <div class="space-y-5">
        <!-- Header + Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div>
                <h1 class="text-xl font-bold" style="color: var(--text-primary);">User Management</h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage library staff, librarians and student members.
                </p>
            </div>
            <button @click="openCreate"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors"
                style="background: var(--lib-primary-light);">
                <UserPlus :size="15" />
                Add User
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                <input v-model="search" type="search" placeholder="Search users by name, username or email…"
                    class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border"
                    style="background: var(--bg-card); border-color: var(--border); color: var(--text-primary);" />
            </div>
            <select v-model="roleFilter" class="px-3 py-2 text-sm rounded-lg border"
                style="background: var(--bg-card); border-color: var(--border); color: var(--text-primary);">
                <option value="">All Roles</option>
                <option v-for="r in userStore.roles" :key="r.id" :value="r.name">{{ r.name }}</option>
            </select>
        </div>

        <!-- Table -->
        <div class="rounded-xl border overflow-hidden" style="background: var(--bg-card); border-color: var(--border);">
            <div v-if="userStore.loading" class="p-10 text-center">
                <Loader2 :size="24" class="animate-spin mx-auto" style="color: var(--lib-primary-light);" />
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr style="background: var(--bg-page);">
                        <th v-for="col in columns" :key="col"
                            class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide"
                            style="color: var(--text-muted);">{{ col }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="userStore.users.length === 0">
                        <td :colspan="columns.length" class="py-12 text-center text-sm" style="color: var(--text-muted);">
                            No users found.
                        </td>
                    </tr>
                    <tr v-for="u in userStore.users" :key="u.id"
                        class="border-t transition-colors hover:bg-slate-50"
                        style="border-color: var(--border);">
                        <!-- User Info -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                    style="background: var(--lib-primary-light);">
                                    {{ initials(u.name) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium truncate" style="color: var(--text-primary);">{{ u.name }}</p>
                                    <p class="text-xs truncate" style="color: var(--text-muted);">@{{ u.username }}</p>
                                </div>
                            </div>
                        </td>
                        <!-- Email -->
                        <td class="px-4 py-3 text-xs" style="color: var(--text-secondary);">{{ u.email }}</td>
                        <!-- Role -->
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full"
                                :style="roleStyle((u as any).roles?.[0]?.name ?? u.roles?.[0])">
                                {{ (u as any).roles?.[0]?.name ?? u.roles?.[0] ?? '—' }}
                            </span>
                        </td>
                        <!-- Status -->
                        <td class="px-4 py-3">
                            <span class="flex items-center gap-1.5 text-xs font-medium"
                                :style="u.status === 'active' ? 'color: var(--lib-success);' : 'color: var(--text-muted);'">
                                <div class="w-1.5 h-1.5 rounded-full"
                                    :style="u.status === 'active' ? 'background: var(--lib-success);' : 'background: var(--text-muted);'"></div>
                                {{ u.status }}
                            </span>
                        </td>
                        <!-- Actions -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <button @click="openEdit(u)"
                                    class="p-1.5 rounded-md hover:bg-blue-50 transition-colors" title="Edit">
                                    <Pencil :size="14" style="color: var(--lib-primary-light);" />
                                </button>
                                <button @click="confirmDelete(u)"
                                    class="p-1.5 rounded-md hover:bg-red-50 transition-colors" title="Delete">
                                    <Trash2 :size="14" style="color: var(--lib-danger);" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="userStore.pagination.last_page > 1"
                class="flex items-center justify-between px-4 py-3 border-t text-xs"
                style="border-color: var(--border); color: var(--text-muted);">
                <span>Total {{ userStore.pagination.total }} users</span>
                <div class="flex gap-1">
                    <button v-for="p in pages" :key="p"
                        @click="goPage(p)"
                        class="w-7 h-7 rounded flex items-center justify-center transition-colors"
                        :style="p === currentPage ? 'background: var(--lib-primary-light); color: white;' : 'hover:background: var(--bg-page);'">
                        {{ p }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <AppModal v-model="showModal" :title="editingUser ? 'Edit User' : 'Add New User'" size="md">
            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="label">Full Name</label>
                        <input v-model="form.name" type="text" placeholder="Dr. Abebe Bekele" required class="inp" />
                    </div>
                    <div>
                        <label class="label">Username</label>
                        <input v-model="form.username" type="text" placeholder="abebe_bekele" required class="inp" />
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input v-model="form.email" type="email" placeholder="abebe@unilib.edu" required class="inp" />
                    </div>
                    <div>
                        <label class="label">Password {{ editingUser ? '(leave blank to keep)' : '' }}</label>
                        <input v-model="form.password" type="password" placeholder="••••••" :required="!editingUser" class="inp" />
                    </div>
                    <div>
                        <label class="label">Phone</label>
                        <input v-model="form.phone" type="tel" placeholder="+251 911 123456" class="inp" />
                    </div>
                    <div>
                        <label class="label">Role</label>
                        <select v-model="form.role" required class="inp">
                            <option value="">Select role…</option>
                            <option v-for="r in userStore.roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select v-model="form.status" class="inp">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
            </form>
            <template #footer>
                <button @click="showModal = false" class="px-4 py-2 text-sm rounded-lg border transition-colors"
                    style="border-color: var(--border); color: var(--text-secondary);">Cancel</button>
                <button @click="handleSubmit" :disabled="userStore.loading"
                    class="px-4 py-2 text-sm rounded-lg text-white font-medium transition-colors disabled:opacity-50"
                    style="background: var(--lib-primary-light);">
                    <span v-if="userStore.loading"><Loader2 :size="14" class="animate-spin inline mr-1" />Saving…</span>
                    <span v-else>{{ editingUser ? 'Update User' : 'Create User' }}</span>
                </button>
            </template>
        </AppModal>

        <!-- Delete Confirm Modal -->
        <AppModal v-model="showDelete" title="Delete User" size="sm">
            <div class="text-center space-y-3 py-2">
                <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center" style="background: var(--lib-danger-light);">
                    <Trash2 :size="20" style="color: var(--lib-danger);" />
                </div>
                <p class="text-sm" style="color: var(--text-secondary);">
                    Delete <strong>{{ deletingUser?.name }}</strong>? This cannot be undone.
                </p>
            </div>
            <template #footer>
                <button @click="showDelete = false" class="px-4 py-2 text-sm rounded-lg border"
                    style="border-color: var(--border);">Cancel</button>
                <button @click="handleDelete" class="px-4 py-2 text-sm rounded-lg text-white font-medium"
                    style="background: var(--lib-danger);">Delete</button>
            </template>
        </AppModal>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useUserStore } from '@/stores/userStore';
import AppModal from '@/components/ui/AppModal.vue';
import { UserPlus, Search, Pencil, Trash2, Loader2 } from '@lucide/vue';
import type { User } from '@/types';

const userStore = useUserStore();

const columns = ['User', 'Email', 'Role', 'Status', 'Actions'];
const search = ref('');
const roleFilter = ref('');
const currentPage = ref(1);
const showModal = ref(false);
const showDelete = ref(false);
const editingUser = ref<User | null>(null);
const deletingUser = ref<User | null>(null);

const form = reactive({
    name: '', username: '', email: '', password: '',
    phone: '', role: '', status: 'active',
});

const pages = computed(() => {
    const total = userStore.pagination.last_page;
    return Array.from({ length: total }, (_, i) => i + 1);
});

function initials(name: string) {
    return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function roleStyle(role: string) {
    return {
        'Super Admin': 'background: #dbeafe; color: #1e40af;',
        'Head Librarian': 'background: #ede9fe; color: #5b21b6;',
        'Desk Librarian': 'background: #d1fae5; color: #065f46;',
        'Student Member': 'background: #fef9c3; color: #92400e;',
    }[role] ?? 'background: var(--bg-page); color: var(--text-secondary);';
}

function openCreate() {
    editingUser.value = null;
    Object.assign(form, { name: '', username: '', email: '', password: '', phone: '', role: '', status: 'active' });
    showModal.value = true;
}

function openEdit(u: User) {
    editingUser.value = u;
    const roleName = (u as any).roles?.[0]?.name ?? u.roles?.[0] ?? '';
    Object.assign(form, { name: u.name, username: u.username, email: u.email, password: '', phone: u.phone ?? '', role: roleName, status: u.status });
    showModal.value = true;
}

function confirmDelete(u: User) {
    deletingUser.value = u;
    showDelete.value = true;
}

async function handleSubmit() {
    const payload: any = { ...form };
    if (!payload.password) delete payload.password;
    const success = editingUser.value
        ? await userStore.updateUser(editingUser.value.id, payload)
        : await userStore.createUser(payload);
    if (success) showModal.value = false;
}

async function handleDelete() {
    if (deletingUser.value) {
        await userStore.deleteUser(deletingUser.value.id);
        showDelete.value = false;
    }
}

function goPage(p: number) {
    currentPage.value = p;
    userStore.fetchUsers({ search: search.value, role: roleFilter.value, page: p });
}

let searchTimeout: ReturnType<typeof setTimeout>;
watch([search, roleFilter], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        userStore.fetchUsers({ search: search.value, role: roleFilter.value });
    }, 400);
});

onMounted(async () => {
    await Promise.all([userStore.fetchUsers(), userStore.fetchRoles()]);
});
</script>

<style scoped>
.label {
    display: block;
    font-size: 0.75rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
    color: var(--text-secondary);
}
.inp {
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border);
    background: var(--bg-input);
    color: var(--text-primary);
}
</style>
