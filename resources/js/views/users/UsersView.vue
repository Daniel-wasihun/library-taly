<template>
  <div class="space-y-5 max-w-7xl mx-auto">
    <!-- Header matching Gibi-Gubae -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <Users class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('users.title', 'User Accounts & Access') }}</span>
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          {{ langStore.t('users.subtitle', 'Manage user authentication, Spatie role assignments, and duty status') }}
        </p>
      </div>
      <Button
        variant="primary"
        size="sm"
        :icon="Plus"
        @click="openCreateModal"
      >
        {{ langStore.t('users.add', 'Add New User') }}
      </Button>
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

      <!-- Duty Status Column -->
      <template #duty_status="{ item }">
        <span v-if="item.is_admin" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20">
          <Shield class="w-3 h-3 text-blue-600 dark:text-blue-400" />
          {{ langStore.t('duty.admin_override', 'Override') }}
        </span>
        <span v-else-if="item.duty_status?.has_active_shift_today" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          {{ langStore.t('duty.on_duty', 'On Duty') }}
        </span>
        <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20">
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
          <button
            type="button"
            class="table-action-btn text-brand-blue hover:bg-brand-blue/10 transition cursor-pointer"
            :title="langStore.t('common.edit', 'Edit User')"
            @click="openEditModal(item)"
          >
            <Pencil class="w-4 h-4 stroke-[2.2]" />
          </button>
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
      :title="editingUser ? langStore.t('users.edit', 'Edit User') : langStore.t('users.add', 'Add New User')"
      :description="langStore.t('users.subtitle', 'Configure user credentials, email, and role assignment')"
      :icon="editingUser ? Pencil : UserPlus"
      size="md"
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
            placeholder="Dr. Abebe Bekele"
            class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
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
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
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
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
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
              :placeholder="editingUser ? 'Leave blank to retain' : '••••••••'"
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
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
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <!-- Role Assignment -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('users.role', 'Assigned Role') }} <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.role"
              required
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
            >
              <option value="">Select a role...</option>
              <option v-for="r in userStore.roles" :key="r.id" :value="r.name">
                {{ r.name }}
              </option>
            </select>
          </div>

          <!-- Account Status -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('common.status', 'Status') }}
            </label>
            <select
              v-model="form.status"
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs capitalize"
            >
              <option value="active">{{ langStore.t('common.active', 'Active') }}</option>
              <option value="inactive">{{ langStore.t('common.inactive', 'Inactive') }}</option>
              <option value="suspended">{{ langStore.t('common.suspended', 'Suspended') }}</option>
            </select>
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
import { useLanguageStore } from '@/stores/languageStore';
import Button from '@/components/ui/Button.vue';
import DataTable, { ColumnDef } from '@/components/ui/DataTable.vue';
import Modal from '@/components/ui/Modal.vue';
import RoleBadge from '@/components/ui/RoleBadge.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { Users, Plus, Pencil, Trash2, UserPlus, Shield } from 'lucide-vue-next';
import type { User } from '@/types';

const userStore = useUserStore();
const langStore = useLanguageStore();

const searchQuery = ref('');
const showModal = ref(false);
const showDelete = ref(false);
const editingUser = ref<User | null>(null);
const deletingUser = ref<User | null>(null);

const userColumns = computed<ColumnDef[]>(() => [
  { key: 'user', label: langStore.t('users.name', 'User') },
  { key: 'roles', label: langStore.t('users.role', 'Roles') },
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
  status: 'active',
});

const openCreateModal = () => {
  editingUser.value = null;
  Object.assign(form, {
    name: '',
    username: '',
    email: '',
    password: '',
    phone: '',
    role: userStore.roles[0]?.name || '',
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
    status: user.status,
  });
  showModal.value = true;
};

const confirmDelete = (user: User) => {
  deletingUser.value = user;
  showDelete.value = true;
};

const handleSubmit = async () => {
  const payload: any = { ...form };
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

const handlePageChange = (page: number) => {
  userStore.fetchUsers({ search: searchQuery.value, page });
};

let searchTimer: ReturnType<typeof setTimeout>;
watch(searchQuery, (query) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    userStore.fetchUsers({ search: query });
  }, 350);
});

onMounted(async () => {
  await Promise.all([userStore.fetchUsers(), userStore.fetchRoles()]);
});
</script>
