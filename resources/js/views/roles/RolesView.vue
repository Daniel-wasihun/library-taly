<template>
  <div class="space-y-6 w-full">
    <!-- Page Header matching Gibi-Gubae -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <ShieldCheck class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('roles.title', 'Roles & RBAC Matrix') }}</span>
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          {{ langStore.t('roles.subtitle', 'Hierarchical authorization powered by Spatie Laravel Permission') }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <!-- View switcher for catalog -->
        <div v-if="activeTab === 'catalog'" class="flex items-center p-0.5 rounded-sm border border-card-border bg-slate-100 dark:bg-slate-800">
          <button
            type="button"
            @click="viewMode = 'table'"
            class="px-2.5 py-1 rounded-xs text-xs font-semibold transition cursor-pointer"
            :class="viewMode === 'table' ? 'bg-card-bg text-main-text shadow-2xs' : 'text-main-text/50 hover:text-main-text'"
          >
            Table
          </button>
          <button
            type="button"
            @click="viewMode = 'cards'"
            class="px-2.5 py-1 rounded-xs text-xs font-semibold transition cursor-pointer"
            :class="viewMode === 'cards' ? 'bg-card-bg text-main-text shadow-2xs' : 'text-main-text/50 hover:text-main-text'"
          >
            Cards
          </button>
        </div>

        <Button
          variant="secondary"
          size="sm"
          :icon="RefreshCw"
          :loading="roleStore.loading"
          @click="roleStore.fetchRoles(true)"
        >
          {{ langStore.t('common.refresh', 'Refresh') }}
        </Button>
        <Button
          v-if="authStore.canManageRoles || authStore.hasPermission('roles.create')"
          variant="primary"
          size="sm"
          :icon="Plus"
          @click="openCreateModal"
        >
          {{ langStore.t('roles.create', 'Create Role') }}
        </Button>
      </div>
    </div>

    <!-- Navigation Tabs: Matches Gibi-Gubae styling -->
    <div class="flex items-center gap-2 p-1 rounded-sm border border-card-border bg-card-bg/60 shadow-2xs w-fit text-xs font-semibold">
      <button
        type="button"
        @click="activeTab = 'catalog'"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-xs transition cursor-pointer select-none"
        :class="activeTab === 'catalog'
          ? 'bg-nav-accent text-white dark:text-slate-900 shadow-2xs'
          : 'text-main-text/70 hover:bg-main-text/5 hover:text-main-text'"
      >
        <Shield class="w-3.5 h-3.5" />
        <span>{{ langStore.t('roles.catalog', 'Roles Catalog') }}</span>
      </button>

      <button
        type="button"
        @click="activeTab = 'matrix'"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-xs transition cursor-pointer select-none"
        :class="activeTab === 'matrix'
          ? 'bg-nav-accent text-white dark:text-slate-900 shadow-2xs'
          : 'text-main-text/70 hover:bg-main-text/5 hover:text-main-text'"
      >
        <Grid class="w-3.5 h-3.5" />
        <span>{{ langStore.t('roles.matrix', 'Permission Matrix') }}</span>
      </button>
    </div>

    <!-- TAB 1: Roles Catalog -->
    <div v-if="activeTab === 'catalog'">
      <!-- Table Mode -->
      <div v-if="viewMode === 'table'" class="bg-card-bg border border-card-border rounded-sm shadow-2xs overflow-hidden">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="border-b border-card-border bg-slate-50/60 dark:bg-slate-800/40 select-none">
              <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">Role</th>
              <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">Assigned Users</th>
              <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">Permissions</th>
              <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px] text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-card-border/60">
            <tr v-for="role in roles" :key="role.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
              <td class="px-4 py-3 font-bold text-main-text">
                <RoleBadge :role="role" />
              </td>
              <td class="px-4 py-3 text-main-text/70">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 border border-card-border">
                  <Users class="w-3 h-3 text-nav-accent" />
                  {{ role.users_count ?? 0 }} members
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1 max-w-md">
                  <span
                    v-for="perm in (role.permissions || []).slice(0, 4)"
                    :key="perm"
                    class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20"
                  >
                    {{ perm }}
                  </span>
                  <span v-if="(role.permissions?.length || 0) > 4" class="text-[10px] text-main-text/50 font-bold self-center">
                    +{{ role.permissions.length - 4 }} more
                  </span>
                  <span v-if="!role.permissions?.length" class="text-[10px] text-main-text/40 italic">None</span>
                </div>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="authStore.canManageRoles || authStore.hasPermission('roles.update')"
                    type="button"
                    class="table-action-btn text-brand-blue hover:bg-brand-blue/10 transition cursor-pointer"
                    title="Edit Role"
                    @click="openEditModal(role)"
                  >
                    <Pencil class="w-4 h-4 stroke-[2.2]" />
                  </button>
                  <button
                    v-if="role.name !== 'Super Admin' && (authStore.canManageRoles || authStore.hasPermission('roles.delete'))"
                    type="button"
                    class="table-action-btn text-rose-600 hover:bg-rose-500/10 transition cursor-pointer"
                    title="Delete Role"
                    @click="confirmDelete(role)"
                  >
                    <Trash2 class="w-4 h-4 stroke-[2.2]" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Cards Mode -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="role in roles"
          :key="role.id"
          class="bg-card-bg border border-card-border rounded-sm p-5 shadow-2xs hover:border-nav-accent/40 transition-colors flex flex-col justify-between"
        >
          <div>
            <div class="flex items-center justify-between gap-2 mb-3">
              <RoleBadge :role="role" />
              <span class="text-[10px] font-semibold text-main-text/50 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full border border-card-border">
                {{ role.users_count ?? 0 }} users
              </span>
            </div>

            <div class="space-y-1.5 my-3">
              <p class="text-[11px] font-bold text-main-text/50 uppercase tracking-wider">Granted Permissions</p>
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="perm in (role.permissions || []).slice(0, 6)"
                  :key="perm"
                  class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20"
                >
                  {{ perm }}
                </span>
                <span v-if="(role.permissions?.length || 0) > 6" class="text-[10px] text-main-text/50 font-bold self-center">
                  +{{ role.permissions.length - 6 }} more
                </span>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-4 border-t border-card-border mt-3">
            <Button
              v-if="authStore.canManageRoles || authStore.hasPermission('roles.update')"
              variant="secondary"
              size="sm"
              :icon="Pencil"
              @click="openEditModal(role)"
            >
              Edit
            </Button>
            <Button
              v-if="role.name !== 'Super Admin' && (authStore.canManageRoles || authStore.hasPermission('roles.delete'))"
              variant="danger"
              size="sm"
              :icon="Trash2"
              @click="confirmDelete(role)"
            >
              Delete
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: Permissions Matrix -->
    <div v-else class="bg-card-bg border border-card-border rounded-xl shadow-2xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="border-b border-card-border bg-slate-50/60 dark:bg-slate-800/40 select-none">
              <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">Permission Name</th>
              <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">Group</th>
              <th
                v-for="role in roles"
                :key="role.id"
                class="px-4 py-3 font-bold text-center text-main-text uppercase text-[10px]"
              >
                {{ role.name }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-card-border/60">
            <tr
              v-for="perm in permissions"
              :key="perm.id"
              class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
            >
              <td class="px-4 py-2.5 font-mono font-semibold text-main-text">
                {{ perm.name }}
              </td>
              <td class="px-4 py-2.5">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-main-text/60 border border-card-border">
                  {{ perm.group }}
                </span>
              </td>
              <td
                v-for="role in roles"
                :key="role.id"
                class="px-4 py-2.5 text-center"
              >
                <span
                  v-if="role.name === 'Super Admin' || role.permissions?.includes(perm.name)"
                  class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold"
                >
                  ✓
                </span>
                <span v-else class="text-main-text/20">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create / Edit Role Modal -->
    <Modal
      :show="showModal"
      :title="editingRole ? langStore.t('roles.edit', 'Edit Role') : langStore.t('roles.create', 'Create Role')"
      :description="langStore.t('roles.subtitle', 'Configure role identity and assign granular permissions')"
      :icon="ShieldCheck"
      size="xl"
      @close="showModal = false"
    >
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.t('roles.name', 'Role Name') }} <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. Assistant Librarian"
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
          />
        </div>

        <div class="space-y-3 pt-2">
          <label class="block text-xs font-bold text-main-text uppercase tracking-wider">
            {{ langStore.t('roles.permissions', 'Permissions') }}
          </label>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-72 overflow-y-auto p-1">
            <div
              v-for="perm in permissions"
              :key="perm.id"
              class="flex items-center gap-2.5 p-2.5 rounded-sm border border-card-border hover:bg-slate-50 dark:hover:bg-slate-800/40 cursor-pointer"
              @click="togglePermission(perm.name)"
            >
              <input
                type="checkbox"
                :checked="form.permissions.includes(perm.name)"
                class="rounded border-card-border text-nav-accent focus:ring-nav-accent"
              />
              <div class="min-w-0">
                <p class="text-xs font-mono font-semibold text-main-text truncate">{{ perm.name }}</p>
                <p class="text-[10px] text-main-text/40 capitalize">{{ perm.group }}</p>
              </div>
            </div>
          </div>
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showModal = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button variant="primary" size="sm" :loading="loading" @click="handleSubmit">
          {{ editingRole ? langStore.t('common.update', 'Update Role') : langStore.t('common.save', 'Save Role') }}
        </Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal
      :show="showDelete"
      title="Delete Role"
      size="sm"
      @close="showDelete = false"
    >
      <div class="text-center py-2 space-y-3">
        <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center bg-rose-500/10 text-rose-600 border border-rose-500/20">
          <Trash2 class="w-6 h-6 stroke-[2.2]" />
        </div>
        <p class="text-xs text-main-text">
          Are you sure you want to delete role <strong>{{ deletingRole?.name }}</strong>?
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
import { ref, reactive, computed, onMounted } from 'vue';
import { useLanguageStore } from '@/stores/languageStore';
import { useRoleStore } from '@/stores/roleStore';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import RoleBadge from '@/components/ui/RoleBadge.vue';
import { ShieldCheck, Shield, Grid, Plus, Pencil, Trash2, Users, RefreshCw } from 'lucide-vue-next';

const langStore = useLanguageStore();
const roleStore = useRoleStore();

const activeTab = ref<'catalog' | 'matrix'>('catalog');
const viewMode = ref<'table' | 'cards'>('table');

const roles = computed(() => roleStore.roles);
const permissions = computed(() => roleStore.permissions);

const showModal = ref(false);
const showDelete = ref(false);
const editingRole = ref<any>(null);
const deletingRole = ref<any>(null);

const form = reactive({
  name: '',
  permissions: [] as string[],
});

const openCreateModal = () => {
  editingRole.value = null;
  form.name = '';
  form.permissions = [];
  showModal.value = true;
};

const openEditModal = (role: any) => {
  editingRole.value = role;
  form.name = role.name;
  form.permissions = [...(role.permissions || [])];
  showModal.value = true;
};

const togglePermission = (name: string) => {
  const idx = form.permissions.indexOf(name);
  if (idx > -1) {
    form.permissions.splice(idx, 1);
  } else {
    form.permissions.push(name);
  }
};

const confirmDelete = (role: any) => {
  deletingRole.value = role;
  showDelete.value = true;
};

const handleSubmit = async () => {
  if (!form.name.trim()) return;
  const success = editingRole.value
    ? await roleStore.updateRole(editingRole.value.id, form)
    : await roleStore.createRole(form);

  if (success) {
    showModal.value = false;
  }
};

const handleDelete = async () => {
  if (!deletingRole.value) return;
  const success = await roleStore.deleteRole(deletingRole.value.id);
  if (success) {
    showDelete.value = false;
  }
};

onMounted(() => {
  roleStore.fetchRoles(false);
});
</script>
