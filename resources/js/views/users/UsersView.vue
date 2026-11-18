<template>
  <div class="space-y-5 w-full">
    <!-- Header matching Gibi-Gubae -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <Users class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('users.title', 'User Accounts & Access') }}</span>
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          {{ langStore.t('users.subtitle', 'Manage user authentication, campus branch assignments, Spatie roles, and granular permission overrides') }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="secondary"
          size="sm"
          :icon="RefreshCw"
          :loading="userStore.loading"
          @click="loadData(true)"
        >
          {{ langStore.t('common.refresh', 'Refresh') }}
        </Button>
        <Button
          variant="primary"
          size="sm"
          :icon="Plus"
          @click="openCreateModal"
        >
          {{ langStore.t('users.add', 'Add New User') }}
        </Button>
      </div>
    </div>

    <!-- Users DataTable matching Gibi-Gubae -->
    <DataTable
      :columns="userColumns"
      :items="userStore.users"
      :loading="userStore.loading"
      show-search
      v-model:search-value="searchQuery"
      :search-placeholder="langStore.t('common.search', 'Search users by name, username, email...')"
      :total="userStore.pagination.total"
      :per-page="15"
      :current-page="userStore.pagination.current_page"
      @page-change="handlePageChange"
    >
      <!-- Filter Toolbar Slot -->
      <template #toolbar>
        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
          <!-- Library Branch Filter -->
          <div class="relative">
            <select
              v-model="selectedLibraryFilter"
              @change="handleFilterChange"
              class="h-9 appearance-none pl-3 pr-8 text-xs font-semibold rounded-md bg-card-bg border border-card-border/80 text-main-text hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
            >
              <option value="all">{{ langStore.t('attendance.all_branches', 'All Branches') }}</option>
              <option value="general">{{ langStore.isAmharic() ? 'አጠቃላይ (ሁሉም ቅርንጫፎች)' : 'General (All Branches)' }}</option>
              <option v-for="lib in libraryStore.libraries" :key="lib.id" :value="lib.id">
                [{{ lib.code }}] {{ langStore.localizeBranch(lib) }}
              </option>
            </select>
            <div class="absolute right-2.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
              <ChevronDown class="w-3.5 h-3.5" />
            </div>
          </div>

          <!-- Role Filter -->
          <div class="relative">
            <select
              v-model="selectedRoleFilter"
              @change="handleFilterChange"
              class="h-9 appearance-none pl-3 pr-8 text-xs font-semibold rounded-md bg-card-bg border border-card-border/80 text-main-text hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
            >
              <option value="all">{{ langStore.t('common.all', 'All') }} {{ langStore.t('roles.title', 'Roles') }}</option>
              <option v-for="r in roleStore.roles.filter(r => r.name !== 'Super Admin')" :key="r.id" :value="r.name">
                {{ r.name }}
              </option>
            </select>
            <div class="absolute right-2.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
              <ChevronDown class="w-3.5 h-3.5" />
            </div>
          </div>

          <!-- Status Filter -->
          <div class="relative">
            <select
              v-model="selectedStatusFilter"
              @change="handleFilterChange"
              class="h-9 appearance-none pl-3 pr-8 text-xs font-semibold rounded-md bg-card-bg border border-card-border/80 text-main-text hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer capitalize"
            >
              <option value="all">{{ langStore.t('common.all', 'All') }} {{ langStore.t('common.status', 'Statuses') }}</option>
              <option value="active">{{ langStore.t('common.active', 'Active') }}</option>
              <option value="inactive">{{ langStore.t('common.inactive', 'Inactive') }}</option>
              <option value="suspended">{{ langStore.t('common.suspended', 'Suspended') }}</option>
            </select>
            <div class="absolute right-2.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
              <ChevronDown class="w-3.5 h-3.5" />
            </div>
          </div>
        </div>
      </template>

      <!-- User Details Column -->
      <template #user="{ item }">
        <div v-if="item" class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-lg bg-nav-accent/10 text-nav-accent border border-nav-accent/25 font-bold flex items-center justify-center text-xs shrink-0 shadow-2xs">
            {{ (item.name || '?').charAt(0).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <div class="font-bold text-main-text flex items-center gap-1.5">
              <span class="truncate">{{ item.name }}</span>
              <span v-if="item.is_admin" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-nav-accent/10 text-nav-accent border border-nav-accent/25 uppercase tracking-wide">
                Admin
              </span>
            </div>
            <div class="text-[11px] text-main-text/50 font-mono truncate">
              @{{ item.username }} · {{ item.email }}
            </div>
          </div>
        </div>
      </template>

      <!-- Library Branch Assignment Column -->
      <template #library="{ item }">
        <div v-if="item?.library" class="flex items-center gap-1.5" :title="item.library.name">
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-main-text border border-card-border select-none shadow-2xs">
            <Building2 class="w-3 h-3 text-brand-blue dark:text-brand-yellow" />
            <span class="font-mono font-bold text-nav-accent">[{{ item.library.code }}]</span>
            <span class="truncate max-w-[120px]">{{ item.library.name }}</span>
          </span>
        </div>
        <div v-else class="flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20 select-none shadow-2xs">
            <Shield class="w-3 h-3 text-purple-600 dark:text-purple-400" />
            <span>All Branches (System-wide)</span>
          </span>
        </div>
      </template>

      <!-- Roles Column -->
      <template #roles="{ item }">
        <div v-if="item" class="flex flex-wrap gap-1.5">
          <RoleBadge
            v-for="role in (item.roles || [])"
            :key="role.id ?? role"
            :role="role"
          />
          <span v-if="!item.roles?.length" class="text-main-text/40 italic text-[11px]">No role</span>
        </div>
      </template>

      <!-- Direct Permission Overrides Column -->
      <template #direct_permissions="{ item }">
        <div v-if="item?.direct_permissions?.length" class="flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/25 select-none shadow-2xs">
            <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
            {{ item.direct_permissions.length }} Overrides
          </span>
        </div>
        <span v-else class="text-main-text/40 text-[11px] italic">Role default</span>
      </template>

      <!-- Duty Status Column -->
      <template #duty_status="{ item }">
        <span v-if="item.is_admin" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20">
          <Shield class="w-3 h-3 text-blue-600 dark:text-blue-400" />
          {{ langStore.t('duty.admin_override', 'Override') }}
        </span>
        <span v-else-if="item.duty_status?.has_active_shift_today" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20" :title="item.duty_status?.today_shifts?.[0]?.task_name || 'Active Turn'">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          {{ langStore.t('duty.on_duty', 'On Duty') }}
        </span>
        <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20" :title="item.duty_status?.next_turn ? `Next turn: ${item.duty_status.next_turn}` : 'No turn scheduled'">
          {{ langStore.t('duty.off_duty', 'Off Duty') }}
        </span>
      </template>

      <!-- Status Column -->
      <template #status="{ item }">
        <StatusBadge :status="item.status" />
      </template>

      <!-- Actions Column -->
      <template #actions="{ item }">
        <div class="flex items-center justify-end gap-1">
          <!-- Direct Permission Overrides Button -->
          <button
            type="button"
            class="table-action-btn text-purple-600 hover:bg-purple-500/10 transition cursor-pointer"
            title="Direct Permission Overrides"
            @click="openOverridesModal(item)"
          >
            <Zap class="w-4 h-4 stroke-[2.2]" />
          </button>

          <!-- Edit User Button -->
          <button
            type="button"
            class="table-action-btn text-brand-blue hover:bg-brand-blue/10 transition cursor-pointer"
            :title="langStore.t('common.edit', 'Edit User')"
            @click="openEditModal(item)"
          >
            <Pencil class="w-4 h-4 stroke-[2.2]" />
          </button>

          <!-- Delete User Button -->
          <button
            type="button"
            class="table-action-btn text-rose-600 hover:bg-rose-500/10 transition cursor-pointer"
            :title="langStore.t('common.delete', 'Delete User')"
            @click="confirmDelete(item)"
          >
            <Trash2 class="w-4 h-4 stroke-[2.2]" />
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Create / Edit User Modal matching Gibi-Gubae -->
    <Modal
      :show="showModal"
      :title="editingUser ? langStore.t('users.edit', 'Edit User Account') : langStore.t('users.add', 'Register New User')"
      description="Configure user identity, campus library branch assignment, credentials, and Spatie roles"
      :icon="editingUser ? Pencil : UserPlus"
      size="lg"
      @close="showModal = false"
    >
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Full Name -->
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.t('users.name', 'Full Name') }} <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. Dr. Abebe Bekele"
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <!-- Username -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('users.username', 'Username (unique)') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.username"
              type="text"
              required
              placeholder="abebe_bekele"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>

          <!-- Email -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('users.email', 'Email Address') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.email"
              type="email"
              required
              placeholder="abebe@unilib.edu"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <!-- Password -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('users.password', 'Password') }}
              <span v-if="!editingUser" class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.password"
              type="password"
              :required="!editingUser"
              :placeholder="editingUser ? 'Leave blank to retain current' : '••••••••'"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
            />
          </div>

          <!-- Phone -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('users.phone', 'Phone Number') }}
            </label>
            <input
              v-model="form.phone"
              type="tel"
              placeholder="+251 911 234567"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
        </div>

        <!-- Library Branch Assignment (LMS-style) -->
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.t('libraries.title', 'Library Branch Assignment') }}
          </label>
          <div class="relative">
            <select
              v-model="form.library_id"
              class="w-full h-9 appearance-none pl-3 pr-8 text-xs font-semibold rounded-md bg-card-bg border border-card-border/80 text-main-text hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
            >
              <option value="">{{ langStore.isAmharic() ? 'አጠቃላይ / ሁሉም ቅርንጫፎች (የስርዓት አስተዳዳሪ)' : 'General / All Libraries (System-wide Management)' }}</option>
              <option v-for="lib in libraryStore.libraries" :key="lib.id" :value="lib.id">
                [{{ lib.code }}] {{ langStore.localizeBranch(lib) }}
              </option>
            </select>
            <div class="absolute right-2.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
              <ChevronDown class="w-3.5 h-3.5" />
            </div>
          </div>
          <p class="text-[10px] text-main-text/40 mt-1">
            {{ langStore.isAmharic() ? 'ለተወሰነ ቅርንጫፍ የተመደቡ ሠራተኞች ለዚያ ቅርንጫፍ ብቻ የፈረቃና የቆጣሪ ሥራዎችን ያከናውናሉ።' : 'Staff assigned to a specific branch are scoped to that facility for attendance counter and duty shifts.' }}
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <!-- Role Assignment -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('users.role', 'Assigned Role') }} <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <select
                v-model="form.role"
                required
                class="w-full h-9 appearance-none pl-3 pr-8 text-xs font-semibold rounded-md bg-card-bg border border-card-border/80 text-main-text hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
              >
                <option value="">{{ langStore.isAmharic() ? 'ሚና ይምረጡ...' : 'Select a role...' }}</option>
                <option v-for="r in roleStore.roles.filter(r => r.name !== 'Super Admin')" :key="r.id" :value="r.name">
                  {{ r.name }}
                </option>
              </select>
              <div class="absolute right-2.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                <ChevronDown class="w-3.5 h-3.5" />
              </div>
            </div>
          </div>

          <!-- Account Status -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('common.status', 'Status') }}
            </label>
            <div class="relative">
              <select
                v-model="form.status"
                class="w-full h-9 appearance-none pl-3 pr-8 text-xs font-semibold rounded-md bg-card-bg border border-card-border/80 text-main-text hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer capitalize"
              >
                <option value="active">{{ langStore.t('common.active', 'Active') }}</option>
                <option value="inactive">{{ langStore.t('common.inactive', 'Inactive') }}</option>
                <option value="suspended">{{ langStore.t('common.suspended', 'Suspended') }}</option>
              </select>
              <div class="absolute right-2.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                <ChevronDown class="w-3.5 h-3.5" />
              </div>
            </div>
          </div>
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showModal = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button
          variant="primary"
          size="sm"
          :loading="userStore.loading"
          @click="handleSubmit"
        >
          {{ editingUser ? langStore.t('common.update', 'Update User') : langStore.t('common.save', 'Save User') }}
        </Button>
      </template>
    </Modal>

    <!-- Direct Permission Overrides Modal -->
    <Modal
      :show="showOverridesModal"
      :title="`Direct Permission Overrides: ${overridesUser?.name}`"
      description="Explicit user abilities in model_has_permissions that augment or override base role permissions"
      :icon="Zap"
      size="xl"
      @close="showOverridesModal = false"
    >
      <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-1">
        <div v-for="(perms, moduleName) in groupedPermissions" :key="moduleName" class="space-y-2">
          <h4 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-1.5 pb-1 border-b border-card-border/60">
            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
            <span>{{ moduleName }}</span>
          </h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <label
              v-for="perm in perms"
              :key="perm.name"
              class="flex items-center gap-2 p-2 rounded-sm border border-card-border hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer transition select-none text-xs"
              :class="userDirectPerms.includes(perm.name) ? 'border-purple-500/50 bg-purple-500/5 dark:bg-purple-500/10' : ''"
            >
              <input
                type="checkbox"
                :value="perm.name"
                v-model="userDirectPerms"
                class="rounded text-purple-600 focus:ring-purple-500/20"
              />
              <span class="font-mono text-[11px] text-main-text truncate">{{ perm.name }}</span>
            </label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showOverridesModal = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button
          variant="primary"
          size="sm"
          :loading="userStore.loading"
          @click="saveDirectPermissions"
        >
          Save Overrides
        </Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal
      :show="showDelete"
      :title="langStore.t('common.delete', 'Delete User')"
      size="sm"
      @close="showDelete = false"
    >
      <div class="text-center py-2 space-y-3">
        <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center bg-rose-500/10 text-rose-600 border border-rose-500/20">
          <Trash2 class="w-6 h-6 stroke-[2.2]" />
        </div>
        <p class="text-xs text-main-text">
          {{ langStore.t('common.confirm_delete', 'Are you sure you want to delete this record?') }}
        </p>
        <p class="text-xs font-bold text-main-text/70 font-mono">
          {{ deletingUser?.name }} (@{{ deletingUser?.username }})
        </p>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showDelete = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button variant="danger" size="sm" @click="handleDelete">
          {{ langStore.t('common.delete', 'Delete') }}
        </Button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useUserStore } from '@/stores/userStore';
import { useLibraryStore } from '@/stores/libraryStore';
import { useRoleStore } from '@/stores/roleStore';
import { useLanguageStore } from '@/stores/languageStore';
import { rolesApi } from '@/api';
import Button from '@/components/ui/Button.vue';
import DataTable, { ColumnDef } from '@/components/ui/DataTable.vue';
import Modal from '@/components/ui/Modal.vue';
import RoleBadge from '@/components/ui/RoleBadge.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { Users, Plus, Pencil, Trash2, UserPlus, Shield, Zap, RefreshCw, Building2, ChevronDown } from 'lucide-vue-next';
import type { User } from '@/types';

const userStore = useUserStore();
const libraryStore = useLibraryStore();
const roleStore = useRoleStore();
const langStore = useLanguageStore();

const searchQuery = ref('');
const selectedLibraryFilter = ref('all');
const selectedRoleFilter = ref('all');
const selectedStatusFilter = ref('all');

const showModal = ref(false);
const showDelete = ref(false);
const showOverridesModal = ref(false);

const editingUser = ref<User | null>(null);
const deletingUser = ref<User | null>(null);
const overridesUser = ref<User | null>(null);

const userDirectPerms = ref<string[]>([]);

const userColumns = computed<ColumnDef[]>(() => [
  { key: 'user', label: langStore.t('users.name', 'User') },
  { key: 'library', label: 'Branch Assignment' },
  { key: 'roles', label: langStore.t('users.role', 'Roles') },
  { key: 'direct_permissions', label: 'Direct Overrides' },
  { key: 'duty_status', label: langStore.t('duty.active_shift', 'Duty Turn') },
  { key: 'status', label: langStore.t('common.status', 'Status') },
  { key: 'actions', label: langStore.t('common.actions', 'Actions'), align: 'right' },
]);

const form = reactive({
  name: '',
  username: '',
  email: '',
  password: '',
  phone: '',
  role: '',
  library_id: '' as string | number,
  status: 'active',
});

const groupedPermissions = computed(() => {
  const groups: Record<string, { id: number; name: string }[]> = {};
  for (const p of roleStore.permissions) {
    const mod = p.name.split('.')[0] || 'general';
    if (!groups[mod]) groups[mod] = [];
    groups[mod].push(p);
  }
  return groups;
});

const loadData = async (force = false) => {
  await Promise.all([
    userStore.fetchUsers({
      search: searchQuery.value,
      library_id: selectedLibraryFilter.value !== 'all' ? selectedLibraryFilter.value : undefined,
      role: selectedRoleFilter.value !== 'all' ? selectedRoleFilter.value : undefined,
      status: selectedStatusFilter.value !== 'all' ? selectedStatusFilter.value : undefined,
    }, force),
    roleStore.fetchRoles(force),
    libraryStore.fetchLibraries(undefined, force),
  ]);
};

const openCreateModal = () => {
  editingUser.value = null;
  Object.assign(form, {
    name: '',
    username: '',
    email: '',
    password: '',
    phone: '',
    role: roleStore.roles.find(r => r.name !== 'Super Admin')?.name || '',
    library_id: '',
    status: 'active',
  });
  showModal.value = true;
};

const openEditModal = (user: User) => {
  editingUser.value = user;
  const roleName = (user as any).roles?.[0]?.name ?? user.roles?.[0] ?? '';
  Object.assign(form, {
    name: user.name,
    username: user.username,
    email: user.email,
    password: '',
    phone: user.phone || '',
    role: roleName,
    library_id: user.library_id ?? '',
    status: user.status,
  });
  showModal.value = true;
};

const openOverridesModal = (user: User) => {
  overridesUser.value = user;
  userDirectPerms.value = [...(user.direct_permissions || [])];
  showOverridesModal.value = true;
};

const saveDirectPermissions = async () => {
  if (!overridesUser.value) return;
  const success = await userStore.overridePermissions(overridesUser.value.id, userDirectPerms.value);
  if (success) {
    showOverridesModal.value = false;
  }
};

const confirmDelete = (user: User) => {
  deletingUser.value = user;
  showDelete.value = true;
};

const handleSubmit = async () => {
  const payload: any = {
    ...form,
    library_id: form.library_id ? Number(form.library_id) : null,
  };
  if (!payload.password) delete payload.password;

  const success = editingUser.value
    ? await userStore.updateUser(editingUser.value.id, payload)
    : await userStore.createUser(payload);

  if (success) {
    showModal.value = false;
  }
};

const handleDelete = async () => {
  if (deletingUser.value) {
    await userStore.deleteUser(deletingUser.value.id);
    showDelete.value = false;
  }
};

const handleFilterChange = () => {
  userStore.fetchUsers({
    search: searchQuery.value,
    library_id: selectedLibraryFilter.value !== 'all' ? selectedLibraryFilter.value : undefined,
    role: selectedRoleFilter.value !== 'all' ? selectedRoleFilter.value : undefined,
    status: selectedStatusFilter.value !== 'all' ? selectedStatusFilter.value : undefined,
  });
};

const handlePageChange = (page: number) => {
  userStore.fetchUsers({
    search: searchQuery.value,
    library_id: selectedLibraryFilter.value !== 'all' ? selectedLibraryFilter.value : undefined,
    role: selectedRoleFilter.value !== 'all' ? selectedRoleFilter.value : undefined,
    status: selectedStatusFilter.value !== 'all' ? selectedStatusFilter.value : undefined,
    page,
  });
};

let searchTimer: ReturnType<typeof setTimeout>;
watch(searchQuery, (query) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    handleFilterChange();
  }, 350);
});

onMounted(async () => {
  await loadData();
});
</script>
