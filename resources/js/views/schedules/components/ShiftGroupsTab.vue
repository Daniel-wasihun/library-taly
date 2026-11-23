<template>
  <div class="space-y-5 w-full">
    <!-- Header with Explanation & Create Group Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card-bg border border-card-border rounded-sm p-4 shadow-2xs">
      <div>
        <h3 class="text-base font-bold text-main-text flex items-center gap-2">
          <Users class="w-5 h-5 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('schedules.groups_title', 'University Shift Groups (Group A, Group B, Group C...)') }}</span>
        </h3>
        <p class="text-xs text-main-text/60 mt-0.5">
          {{ langStore.t('schedules.groups_unified_desc', 'Shift groups belong to all library branches by default. Staff belong to their respective branch and cannot belong to more than one group at a time.') }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <Button
          v-if="canManageGroups"
          variant="primary"
          size="sm"
          :icon="Plus"
          @click="openCreateModal"
        >
          {{ langStore.t('schedules.create_group', 'Create Group') }}
        </Button>
      </div>
    </div>

    <!-- Groups Grid Skeleton -->
    <div v-if="shiftStore.loading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 animate-pulse">
      <div v-for="i in 4" :key="i" class="bg-card-bg border border-card-border rounded-sm p-4 space-y-3">
        <div class="flex items-start justify-between">
          <div class="space-y-1.5 w-2/3">
            <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4"></div>
            <div class="h-2.5 bg-slate-200 dark:bg-slate-700/60 rounded w-1/2"></div>
          </div>
          <div class="h-5 w-16 bg-slate-200 dark:bg-slate-700/60 rounded"></div>
        </div>
        <div class="space-y-1.5 pt-2">
          <div v-for="j in 3" :key="j" class="h-6 bg-slate-200 dark:bg-slate-700/40 rounded"></div>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t border-card-border">
          <div class="h-7 w-16 bg-slate-200 dark:bg-slate-700/50 rounded"></div>
          <div class="h-7 w-16 bg-slate-200 dark:bg-slate-700/50 rounded"></div>
        </div>
      </div>
    </div>

    <div v-else-if="shiftStore.groups.length === 0" class="p-8 rounded-sm bg-card-bg border border-card-border text-center text-xs text-main-text/60">
      <Users class="w-10 h-10 stroke-[1.5] text-main-text/30 mx-auto mb-2" />
      <p class="font-bold text-main-text">No Shift Groups Defined</p>
      <p class="mt-1">Create unified shift groups (Group A, Group B, Group C...) to schedule shifts across all branches.</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div
        v-for="group in shiftStore.groups"
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
                  class="px-2 py-0.5 rounded-sm text-[10px] font-bold border"
                  :class="group.library
                    ? 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/20'
                    : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20'"
                >
                  {{ group.library ? group.library.code : (langStore.isAmharic() ? 'ሁሉም ቅርንጫፎች' : 'All Branches') }}
                </span>
              </div>
              <p class="text-[11px] text-main-text/60 mt-0.5 flex items-center gap-1.5 font-medium">
                <Building2 class="w-3 h-3 text-nav-accent" />
                <span>{{ group.library ? langStore.localizeBranch(group.library) : (langStore.isAmharic() ? 'ሁሉም የዩኒቨርሲቲ ቤተ-መጻሕፍት ቅርንጫፎች' : 'All University Library Branches') }}</span>
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

          <!-- Assigned Members with their Library Branch -->
          <div class="pt-2 border-t border-card-border">
            <div class="text-[11px] font-bold text-main-text/60 mb-1.5 flex items-center justify-between">
              <span>Assigned Attendants ({{ group.users?.length || 0 }})</span>
              <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">1 Group Per Librarian</span>
            </div>
            <div v-if="group.users && group.users.length > 0" class="flex flex-wrap gap-1.5">
              <span
                v-for="u in group.users"
                :key="u.id"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-sm text-[11px] bg-card-bg text-main-text border border-card-border shadow-3xs"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-nav-accent"></span>
                <span class="font-semibold">{{ u.name }}</span>
                <span v-if="u.library" class="text-[9px] px-1 rounded-xs bg-nav-accent/10 text-nav-accent font-bold">
                  {{ u.library.code }}
                </span>
              </span>
            </div>
            <p v-else class="text-[11px] text-main-text/40 italic">
              No staff members assigned yet.
            </p>
          </div>
        </div>

        <div class="pt-2 border-t border-card-border flex items-center justify-between text-[11px]">
          <span class="text-main-text/50">Status:</span>
          <span
            class="px-2 py-0.5 rounded-sm font-bold text-[10px]"
            :class="group.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/10 text-rose-600'"
          >
            {{ group.is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Modal
      :show="showModal"
      :title="editingGroup ? 'Edit Shift Group' : 'Create Unified Shift Group'"
      description="Define group name and assign librarians. Each librarian belongs to their own library branch and can only belong to one group at a time."
      :icon="Users"
      size="md"
      @close="showModal = false"
    >
      <form @submit.prevent="handleSaveGroup" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Group Name *
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. Group A, Group B, Group C..."
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-semibold"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Target Scope / Branch
          </label>
          <div class="relative">
            <select
              v-model="form.library_id"
              class="w-full h-9 appearance-none pl-3 pr-8 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text shadow-2xs hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent transition cursor-pointer font-medium"
            >
              <option :value="null">🌐 {{ langStore.isAmharic() ? 'ሁሉም የዩኒቨርሲቲ ቅርንጫፎች (የጋራ ምድብ)' : 'All University Branches (Default Scope)' }}</option>
              <option v-for="lib in libraryStore.activeLibraries" :key="lib.id" :value="lib.id">
                [{{ lib.code }}] {{ langStore.localizeBranch(lib) }}
              </option>
            </select>
            <div class="absolute right-2.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
              <ChevronDown class="w-3.5 h-3.5" />
            </div>
          </div>
          <p class="text-[11px] text-main-text/50 mt-1">
            Leave as "All University Branches" so the group applies to all branches.
          </p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Description (optional)
          </label>
          <textarea
            v-model="form.description"
            rows="2"
            placeholder="Shift group purpose, responsibilities..."
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs resize-none"
          ></textarea>
        </div>

        <!-- Attendant Multi-Select with 1 Group Constraint -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-semibold text-main-text">
              Assign Librarians & Attendants ({{ form.user_ids.length }} selected)
            </label>
            <span class="text-[10px] text-nav-accent font-semibold">
              Strictly 1 group per librarian
            </span>
          </div>

          <div class="max-h-56 overflow-y-auto border border-card-border rounded-sm p-2 bg-input-bg space-y-1 custom-scrollbar">
            <div
              v-for="staff in availableStaffList"
              :key="staff.id"
              class="flex items-center justify-between p-2 rounded-xs border text-xs transition select-none"
              :class="[
                form.user_ids.includes(staff.id)
                  ? 'bg-nav-accent/10 border-nav-accent/30 text-nav-accent font-bold'
                  : (!staff.is_available
                      ? 'bg-card-bg/40 border-card-border/40 text-main-text/40 cursor-not-allowed opacity-60'
                      : 'bg-card-bg border-card-border text-main-text hover:bg-card-bg/80 cursor-pointer')
              ]"
              @click="staff.is_available && toggleStaff(staff)"
            >
              <div class="flex items-center gap-2">
                <input
                  type="checkbox"
                  :checked="form.user_ids.includes(staff.id)"
                  :disabled="!staff.is_available"
                  class="rounded-xs border-input-border text-nav-accent pointer-events-none"
                />
                <div>
                  <span class="font-semibold">{{ staff.name }}</span>
                  <span v-if="staff.library" class="text-[10px] ml-1.5 px-1.5 py-0.2 rounded-xs bg-card-bg border border-card-border font-medium text-main-text/70">
                    {{ staff.library.name }} ({{ staff.library.code }})
                  </span>
                </div>
              </div>

              <div>
                <span
                  v-if="!staff.is_available"
                  class="text-[10px] px-2 py-0.5 rounded-sm bg-amber-500/10 text-amber-600 font-semibold"
                >
                  Already in {{ staff.current_group_name }}
                </span>
                <span
                  v-else-if="form.user_ids.includes(staff.id)"
                  class="text-[10px] text-emerald-600 font-bold"
                >
                  ✓ Selected
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-card-border">
          <Button variant="secondary" size="sm" type="button" @click="showModal = false">
            {{ langStore.t('common.cancel', 'Cancel') }}
          </Button>
          <Button
            variant="primary"
            size="sm"
            type="submit"
            :loading="shiftStore.saving"
          >
            {{ editingGroup ? 'Update Group' : 'Create Group' }}
          </Button>
        </div>
      </form>
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
          This will detach assigned attendants and clean group schedules.
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
import { Users, Building2, Plus, Pencil, Trash2, ChevronDown } from 'lucide-vue-next';
import type { ShiftGroup, User } from '@/types';

const shiftStore = useShiftStore();
const libraryStore = useLibraryStore();
const authStore = useAuthStore();
const langStore = useLanguageStore();

const showModal = ref(false);
const showDelete = ref(false);
const editingGroup = ref<ShiftGroup | null>(null);
const deletingGroup = ref<ShiftGroup | null>(null);

const form = reactive<{
  name: string;
  library_id: number | null;
  description: string;
  user_ids: number[];
}>({
  name: '',
  library_id: null,
  description: '',
  user_ids: [],
});

const canManageGroups = computed(() => {
  return authStore.canManageSchedules;
});

const availableStaffList = computed(() => {
  return shiftStore.eligibleStaff || [];
});

const toggleStaff = (staff: any) => {
  const idx = form.user_ids.indexOf(staff.id);
  if (idx >= 0) {
    form.user_ids.splice(idx, 1);
  } else {
    form.user_ids.push(staff.id);
  }
};

const openCreateModal = async () => {
  editingGroup.value = null;
  form.name = '';
  form.library_id = null; // null = all branches
  form.description = '';
  form.user_ids = [];
  await shiftStore.fetchEligibleStaff();
  showModal.value = true;
};

const openEditModal = async (group: ShiftGroup) => {
  editingGroup.value = group;
  form.name = group.name;
  form.library_id = group.library_id || null;
  form.description = group.description || '';
  form.user_ids = (group.users || []).map((u) => u.id as number);
  await shiftStore.fetchEligibleStaff(group.id);
  showModal.value = true;
};

const confirmDelete = (group: ShiftGroup) => {
  deletingGroup.value = group;
  showDelete.value = true;
};

const handleSaveGroup = async () => {
  if (!form.name.trim()) return;

  let success = false;
  if (editingGroup.value) {
    success = await shiftStore.updateGroup(editingGroup.value.id, { ...form });
  } else {
    success = await shiftStore.createGroup({ ...form });
  }

  if (success) {
    showModal.value = false;
    await shiftStore.fetchGroups();
  }
};

const handleDeleteGroup = async () => {
  if (!deletingGroup.value) return;
  const success = await shiftStore.deleteGroup(deletingGroup.value.id);
  if (success) {
    showDelete.value = false;
    await shiftStore.fetchGroups();
  }
};

onMounted(async () => {
  await Promise.all([
    libraryStore.fetchLibraries(),
    shiftStore.fetchGroups(),
    shiftStore.fetchEligibleStaff(),
  ]);
});
</script>
