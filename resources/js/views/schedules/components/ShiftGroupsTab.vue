<template>
  <div class="space-y-5 w-full">
    <!-- Header with Branch Filter & Create Group Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card-bg border border-card-border rounded-sm p-4 shadow-2xs">
      <div>
        <h3 class="text-base font-bold text-main-text flex items-center gap-2">
          <Users class="w-5 h-5 text-brand-blue dark:text-brand-yellow" />
          <span>Duty Shift Groups (Group A, Group B, Group C...)</span>
        </h3>
        <p class="text-xs text-main-text/60 mt-0.5">
          Groups are configured separately for each library branch. Staff can belong to one or more groups at a time.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <!-- Branch Filter -->
        <div class="flex items-center gap-2">
          <label class="text-xs font-semibold text-main-text whitespace-nowrap">Filter Branch:</label>
          <select
            v-model="branchFilter"
            class="px-2.5 py-1.5 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs cursor-pointer"
            @change="handleFilterChange"
          >
            <option value="">All Branches</option>
            <option v-for="lib in libraryStore.activeLibraries" :key="lib.id" :value="lib.id">
              {{ lib.name }} ({{ lib.code }})
            </option>
          </select>
        </div>

        <Button
          v-if="canManageGroups"
          variant="primary"
          size="sm"
          :icon="Plus"
          @click="openCreateModal"
        >
          Create Group
        </Button>
      </div>
    </div>

    <!-- Groups Grid / Cards -->
    <div v-if="shiftStore.loading" class="p-8 text-center text-xs text-main-text/50">
      <div class="w-6 h-6 border-2 border-brand-blue border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      Loading shift groups...
    </div>

    <div v-else-if="displayedGroups.length === 0" class="p-8 rounded-sm bg-card-bg border border-card-border text-center text-xs text-main-text/60">
      <Users class="w-10 h-10 stroke-[1.5] text-main-text/30 mx-auto mb-2" />
      <p class="font-bold text-main-text">No Shift Groups for this Branch</p>
      <p class="mt-1">Each library branch has its own independent duty groups (Group A, Group B, Group C...).</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div
        v-for="group in displayedGroups"
        :key="group.id"
        class="bg-card-bg border border-card-border rounded-sm p-4 shadow-2xs space-y-3 flex flex-col justify-between"
      >
        <div class="space-y-2.5">
          <!-- Card Header -->
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="flex items-center gap-2">
                <h4 class="font-bold text-sm text-main-text">{{ group.name }}</h4>
                <span
                  v-if="group.library"
                  class="px-2 py-0.5 rounded-sm text-[10px] font-bold bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20"
                >
                  {{ group.library.code }}
                </span>
              </div>
              <p class="text-[11px] text-main-text/60 mt-0.5 flex items-center gap-1.5 font-medium">
                <Building2 class="w-3 h-3 text-nav-accent" />
                <span>{{ group.library ? group.library.name : 'Branch' }}</span>
              </p>
            </div>

            <div v-if="canManageGroups" class="flex items-center gap-1">
              <button
                type="button"
                class="p-1.5 rounded-sm hover:bg-nav-accent/10 text-main-text/60 hover:text-nav-accent transition cursor-pointer"
                title="Edit Group"
                @click="openEditModal(group)"
              >
                <Pencil class="w-3.5 h-3.5" />
              </button>
              <button
                type="button"
                class="p-1.5 rounded-sm hover:bg-rose-500/10 text-rose-600 transition cursor-pointer"
                title="Delete Group"
                @click="confirmDelete(group)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <p v-if="group.description" class="text-xs text-main-text/70 line-clamp-2">
            {{ group.description }}
          </p>

          <!-- Assigned Members -->
          <div class="pt-2 border-t border-card-border">
            <div class="text-[11px] font-bold text-main-text/60 mb-1.5 flex items-center justify-between">
              <span>Assigned Staff ({{ group.users?.length || 0 }})</span>
              <span class="text-[10px] text-main-text/40 font-normal">Can belong to multiple groups</span>
            </div>
            <div v-if="group.users && group.users.length > 0" class="flex flex-wrap gap-1.5">
              <span
                v-for="u in group.users"
                :key="u.id"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-sm text-[11px] bg-nav-accent/10 text-nav-accent border border-nav-accent/20"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-nav-accent"></span>
                <span>{{ u.name }}</span>
              </span>
            </div>
            <p v-else class="text-[11px] text-main-text/40 italic">
              No staff assigned to this group yet.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Modal
      :show="showModal"
      :title="editingGroup ? 'Edit Shift Group' : 'Create Library Shift Group'"
      description="Each library branch manages its own groups. Attendants in this branch can be assigned to one or more groups."
      :icon="Users"
      size="md"
      @close="showModal = false"
    >
      <form @submit.prevent="handleSaveGroup" class="space-y-4">
        <!-- Target Library Branch (Strictly Required) -->
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Library Branch <span class="text-rose-500">*</span>
          </label>
          <select
            v-model="form.library_id"
            required
            :disabled="isBranchScoped"
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs disabled:opacity-60 cursor-pointer font-medium"
            @change="handleBranchChange"
          >
            <option v-for="lib in libraryStore.activeLibraries" :key="lib.id" :value="lib.id">
              {{ lib.name }} ({{ lib.code }})
            </option>
          </select>
          <p class="text-[11px] text-main-text/50 mt-1">
            Groups are strictly scoped to this library branch.
          </p>
        </div>

        <!-- Group Name -->
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Group Name <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. Group A, Group B, Group C"
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-semibold"
          />
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Description (optional)
          </label>
          <textarea
            v-model="form.description"
            rows="2"
            placeholder="Shift coverage, desk duties..."
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs resize-none"
          ></textarea>
        </div>

        <!-- Staff Assignment Checklist -->
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1 flex items-center justify-between">
            <span>Branch Attendants & Staff (Can be in multiple groups)</span>
            <span class="text-[11px] text-nav-accent font-normal">
              {{ form.user_ids.length }} selected
            </span>
          </label>

          <div class="max-h-48 overflow-y-auto custom-scrollbar border border-input-border rounded-sm p-2 bg-input-bg space-y-1">
            <div
              v-for="staff in branchCompatibleStaff"
              :key="staff.id"
              class="flex items-center justify-between p-1.5 rounded-sm hover:bg-card-bg cursor-pointer transition"
              @click="toggleStaff(staff)"
            >
              <div class="flex items-center gap-2 min-w-0">
                <input
                  type="checkbox"
                  :checked="form.user_ids.includes(staff.id)"
                  class="rounded-xs border-input-border text-nav-accent focus:ring-0 cursor-pointer"
                />
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-main-text truncate">{{ staff.name }}</p>
                  <p class="text-[10px] text-main-text/50 truncate">
                    @{{ staff.username }} • Branch: {{ staff.library ? staff.library.name : 'General Attendant' }}
                  </p>
                </div>
              </div>

              <div>
                <span
                  v-if="form.user_ids.includes(staff.id)"
                  class="px-1.5 py-0.5 rounded-sm text-[9px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20"
                >
                  Assigned
                </span>
              </div>
            </div>

            <p v-if="branchCompatibleStaff.length === 0" class="text-center py-4 text-xs text-main-text/50">
              No staff members found for this branch.
            </p>
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
          :loading="shiftStore.saving"
          @click="handleSaveGroup"
        >
          {{ editingGroup ? 'Update Group' : 'Create Group' }}
        </Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal
      :show="showDelete"
      title="Delete Shift Group"
      size="sm"
      @close="showDelete = false"
    >
      <div class="text-center py-2 space-y-3">
        <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center bg-rose-500/10 text-rose-600 border border-rose-500/20">
          <Trash2 class="w-6 h-6 stroke-[2.2]" />
        </div>
        <p class="text-xs text-main-text">
          Are you sure you want to delete <strong class="text-rose-600">{{ deletingGroup?.name }}</strong>?
        </p>
        <p class="text-[11px] text-main-text/50">
          This will detach assigned attendants.
        </p>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showDelete = false">
          Cancel
        </Button>
        <Button
          variant="danger"
          size="sm"
          :loading="shiftStore.saving"
          @click="handleDeleteGroup"
        >
          Delete Group
        </Button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useShiftStore } from '@/stores/shiftStore';
import { useLibraryStore } from '@/stores/libraryStore';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import { Users, Building2, Plus, Pencil, Trash2 } from 'lucide-vue-next';
import type { ShiftGroup, User } from '@/types';

const shiftStore = useShiftStore();
const libraryStore = useLibraryStore();
const authStore = useAuthStore();
const langStore = useLanguageStore();

const branchFilter = ref<number | ''>('');
const showModal = ref(false);
const showDelete = ref(false);
const editingGroup = ref<ShiftGroup | null>(null);
const deletingGroup = ref<ShiftGroup | null>(null);

const form = reactive<{
  name: string;
  library_id: number;
  description: string;
  user_ids: number[];
}>({
  name: '',
  library_id: 1,
  description: '',
  user_ids: [],
});

const isBranchScoped = computed(() => {
  return !authStore.user?.can_manage_all_libraries && !!authStore.user?.library_id;
});

const canManageGroups = computed(() => {
  return authStore.isAdmin || authStore.canManageLibrary;
});

const displayedGroups = computed(() => {
  const groups = shiftStore.groups || [];
  if (!branchFilter.value) {
    return groups;
  }
  return groups.filter((g) => g.library_id === Number(branchFilter.value));
});

const branchCompatibleStaff = computed(() => {
  const targetLibId = form.library_id;
  return (shiftStore.eligibleStaff || []).filter((s) => {
    return !s.library_id || s.library_id === targetLibId;
  });
});

const handleFilterChange = async () => {
  await shiftStore.fetchGroups(branchFilter.value ? Number(branchFilter.value) : undefined);
};

const toggleStaff = (staff: User) => {
  const idx = form.user_ids.indexOf(staff.id);
  if (idx >= 0) {
    form.user_ids.splice(idx, 1);
  } else {
    form.user_ids.push(staff.id);
  }
};

const handleBranchChange = () => {
  // Prune any attendants not compatible with newly selected branch
  const validIds = branchCompatibleStaff.value.map((s) => s.id);
  form.user_ids = form.user_ids.filter((uid) => validIds.includes(uid));
};

const openCreateModal = () => {
  editingGroup.value = null;
  form.name = '';
  // Default library to branchFilter, or user's branch, or first active library
  const defaultLib = branchFilter.value
    ? Number(branchFilter.value)
    : (authStore.user?.library_id || (libraryStore.activeLibraries[0]?.id ?? 1));
  form.library_id = defaultLib;
  form.description = '';
  form.user_ids = [];
  shiftStore.fetchEligibleStaff(form.library_id);
  showModal.value = true;
};

const openEditModal = (group: ShiftGroup) => {
  editingGroup.value = group;
  form.name = group.name;
  form.library_id = group.library_id || 1;
  form.description = group.description || '';
  form.user_ids = (group.users || []).map((u) => u.id as number);
  shiftStore.fetchEligibleStaff(form.library_id);
  showModal.value = true;
};

const confirmDelete = (group: ShiftGroup) => {
  deletingGroup.value = group;
  showDelete.value = true;
};

const handleSaveGroup = async () => {
  if (!form.name.trim() || !form.library_id) return;

  let success = false;
  if (editingGroup.value) {
    success = await shiftStore.updateGroup(editingGroup.value.id, { ...form });
  } else {
    success = await shiftStore.createGroup({ ...form });
  }

  if (success) {
    showModal.value = false;
  }
};

const handleDeleteGroup = async () => {
  if (!deletingGroup.value) return;
  const success = await shiftStore.deleteGroup(deletingGroup.value.id, branchFilter.value ? Number(branchFilter.value) : undefined);
  if (success) {
    showDelete.value = false;
  }
};

onMounted(async () => {
  if (authStore.user?.library_id) {
    branchFilter.value = authStore.user.library_id;
  }
  await Promise.all([
    libraryStore.fetchLibraries(),
    shiftStore.fetchGroups(branchFilter.value ? Number(branchFilter.value) : undefined),
    shiftStore.fetchEligibleStaff(),
  ]);
});
</script>
