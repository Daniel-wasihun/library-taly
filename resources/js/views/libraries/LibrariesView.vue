<template>
  <div class="space-y-5 w-full">
    <!-- Header matching Gibi-Gubae -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <Landmark class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('libraries.title', 'Library Branches & Facilities') }}</span>
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          {{ langStore.t('libraries.subtitle', 'Manage university multi-campus library facilities, department branches, and resource allocations') }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Button
          variant="secondary"
          size="sm"
          :icon="RefreshCw"
          :loading="libraryStore.loading"
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
          {{ langStore.t('libraries.add', 'Add Library Branch') }}
        </Button>
      </div>
    </div>

    <!-- Library Branches DataTable -->
    <DataTable
      :columns="libraryColumns"
      :items="libraryStore.libraries"
      :loading="libraryStore.loading"
      show-search
      v-model:search-value="searchQuery"
      :search-placeholder="langStore.t('common.search', 'Search branches by name, code, or location...')"
      :total="libraryStore.libraries.length"
      :per-page="15"
      empty-title="No library branches found"
      empty-desc="Add a new university campus branch to get started."
    >
      <!-- Branch Info Column -->
      <template #branch="{ item }">
        <div v-if="item" class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-brand-blue/10 dark:bg-brand-yellow/10 text-brand-blue dark:text-brand-yellow border border-brand-blue/20 dark:border-brand-yellow/20 flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">
            <Building2 class="w-4 h-4 stroke-[2.2]" />
          </div>
          <div class="min-w-0">
            <div class="font-bold text-main-text flex items-center gap-1.5">
              <span class="truncate">{{ item.name }}</span>
              <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border">
                {{ item.code }}
              </span>
            </div>
            <div v-if="item.name_am" class="text-[11px] text-main-text/50 truncate">
              {{ item.name_am }}
            </div>
          </div>
        </div>
      </template>

      <!-- Location Column -->
      <template #location="{ item }">
        <div v-if="item?.location" class="flex items-center gap-1.5 text-xs text-main-text/70">
          <MapPin class="w-3.5 h-3.5 text-main-text/40 shrink-0" />
          <span class="truncate">{{ item.location }}</span>
        </div>
        <span v-else class="text-main-text/30 italic text-xs">Not specified</span>
      </template>

      <!-- Contact Column -->
      <template #contact="{ item }">
        <div class="space-y-0.5 text-[11px]">
          <div v-if="item?.phone" class="flex items-center gap-1 text-main-text/70 font-mono">
            <Phone class="w-3 h-3 text-main-text/40" />
            <span>{{ item.phone }}</span>
          </div>
          <div v-if="item?.email" class="flex items-center gap-1 text-main-text/50 truncate">
            <Mail class="w-3 h-3 text-main-text/40" />
            <span class="truncate">{{ item.email }}</span>
          </div>
          <span v-if="!item?.phone && !item?.email" class="text-main-text/30 italic">No contact</span>
        </div>
      </template>

      <!-- Branch Metrics Column -->
      <template #metrics="{ item }">
        <div class="flex items-center gap-2 text-[11px]">
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20" title="Assigned Staff">
            <Users class="w-3 h-3" />
            {{ item.users_count ?? 0 }} Staff
          </span>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20" title="Cataloged Books">
            <BookOpen class="w-3 h-3" />
            {{ item.books_count ?? 0 }} Books
          </span>
          <span v-if="(item.active_loans_count ?? 0) > 0" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20" title="Active Borrowed Loans">
            {{ item.active_loans_count }} Active Loans
          </span>
        </div>
      </template>

      <!-- Status Column -->
      <template #status="{ item }">
        <span
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border"
          :class="item.is_active
            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20'
            : 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20'"
        >
          <span class="w-1.5 h-1.5 rounded-full" :class="item.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
          {{ item.is_active ? 'Operational' : 'Inactive' }}
        </span>
      </template>

      <!-- Actions Column -->
      <template #actions="{ item }">
        <div class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="table-action-btn text-brand-blue hover:bg-brand-blue/10 transition cursor-pointer"
            title="Edit Branch"
            @click="openEditModal(item)"
          >
            <Pencil class="w-4 h-4 stroke-[2.2]" />
          </button>
          <button
            type="button"
            class="table-action-btn text-rose-600 hover:bg-rose-500/10 transition cursor-pointer"
            title="Delete Branch"
            @click="confirmDelete(item)"
          >
            <Trash2 class="w-4 h-4 stroke-[2.2]" />
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Create / Edit Library Branch Modal -->
    <Modal
      :show="showModal"
      :title="editingLibrary ? 'Edit Library Branch' : 'Add University Library Branch'"
      description="Configure physical campus library facility details and code"
      :icon="editingLibrary ? Pencil : Building2"
      size="lg"
      @close="showModal = false"
    >
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <!-- English Name -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              Branch Name (English) <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="e.g. Science & Technology Library"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
            />
          </div>

          <!-- Amharic Name -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              የቅርንጫፉ ስም (አማርኛ)
            </label>
            <input
              v-model="form.name_am"
              type="text"
              placeholder="ምሳሌ፡ የሳይንስና ቴክኖሎጂ ቤተ-መጻሕፍት"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <!-- Code -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              Branch Code (Unique) <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.code"
              type="text"
              required
              maxlength="10"
              placeholder="SCITECH"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono uppercase"
            />
          </div>

          <!-- Location -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              Campus / Building Location
            </label>
            <input
              v-model="form.location"
              type="text"
              placeholder="e.g. Technology Campus, Block C"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <!-- Phone -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              Desk Phone
            </label>
            <input
              v-model="form.phone"
              type="tel"
              placeholder="+251 111 234502"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>

          <!-- Email -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              Desk Email
            </label>
            <input
              v-model="form.email"
              type="email"
              placeholder="scitech@unilib.edu"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
            />
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Description & Stack Focus
          </label>
          <textarea
            v-model="form.description"
            rows="2"
            placeholder="Specialized library stack and faculty coverage..."
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
          ></textarea>
        </div>

        <!-- Active Status Toggle -->
        <div class="flex items-center gap-2 pt-1">
          <input
            id="lib_active"
            v-model="form.is_active"
            type="checkbox"
            class="rounded border-card-border text-nav-accent focus:ring-nav-accent"
          />
          <label for="lib_active" class="text-xs font-medium text-main-text cursor-pointer select-none">
            Branch is active and open for book circulation & patron services
          </label>
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showModal = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button
          variant="primary"
          size="sm"
          :loading="libraryStore.loading"
          @click="handleSubmit"
        >
          {{ editingLibrary ? langStore.t('common.update', 'Update Branch') : langStore.t('common.save', 'Save Branch') }}
        </Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal
      :show="showDelete"
      title="Delete Library Branch"
      size="sm"
      @close="showDelete = false"
    >
      <div class="text-center py-2 space-y-3">
        <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center bg-rose-500/10 text-rose-600 border border-rose-500/20">
          <Trash2 class="w-6 h-6 stroke-[2.2]" />
        </div>
        <p class="text-xs text-main-text">
          Are you sure you want to delete this library branch?
        </p>
        <p class="text-xs font-bold text-main-text/70 font-mono">
          {{ deletingLibrary?.name }} [{{ deletingLibrary?.code }}]
        </p>
        <p class="text-[11px] text-rose-500">
          Note: Branches with active borrowed book loans cannot be deleted.
        </p>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showDelete = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button variant="danger" size="sm" @click="handleDelete">
          {{ langStore.t('common.delete', 'Delete Branch') }}
        </Button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useLibraryStore } from '@/stores/libraryStore';
import { useLanguageStore } from '@/stores/languageStore';
import Button from '@/components/ui/Button.vue';
import DataTable, { ColumnDef } from '@/components/ui/DataTable.vue';
import Modal from '@/components/ui/Modal.vue';
import { Landmark, Building2, Plus, Pencil, Trash2, MapPin, Phone, Mail, Users, BookOpen, RefreshCw } from 'lucide-vue-next';
import type { Library } from '@/types';

const libraryStore = useLibraryStore();
const langStore = useLanguageStore();

const searchQuery = ref('');
const showModal = ref(false);
const showDelete = ref(false);

const editingLibrary = ref<Library | null>(null);
const deletingLibrary = ref<Library | null>(null);

const libraryColumns = computed<ColumnDef[]>(() => [
  { key: 'branch', label: 'Library Branch' },
  { key: 'location', label: 'Campus Location' },
  { key: 'contact', label: 'Contact' },
  { key: 'metrics', label: 'Allocations & Metrics' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Actions', align: 'right' },
]);

const form = reactive({
  name: '',
  name_am: '',
  code: '',
  location: '',
  phone: '',
  email: '',
  description: '',
  is_active: true,
});

const loadData = async (force = false) => {
  await libraryStore.fetchLibraries({ search: searchQuery.value }, force);
};

const openCreateModal = () => {
  editingLibrary.value = null;
  Object.assign(form, {
    name: '',
    name_am: '',
    code: '',
    location: '',
    phone: '',
    email: '',
    description: '',
    is_active: true,
  });
  showModal.value = true;
};

const openEditModal = (lib: Library) => {
  editingLibrary.value = lib;
  Object.assign(form, {
    name: lib.name,
    name_am: lib.name_am || '',
    code: lib.code,
    location: lib.location || '',
    phone: lib.phone || '',
    email: lib.email || '',
    description: lib.description || '',
    is_active: lib.is_active,
  });
  showModal.value = true;
};

const confirmDelete = (lib: Library) => {
  deletingLibrary.value = lib;
  showDelete.value = true;
};

const handleSubmit = async () => {
  const payload = { ...form };
  const success = editingLibrary.value
    ? await libraryStore.updateLibrary(editingLibrary.value.id, payload)
    : await libraryStore.createLibrary(payload);

  if (success) {
    showModal.value = false;
  }
};

const handleDelete = async () => {
  if (deletingLibrary.value) {
    await libraryStore.deleteLibrary(deletingLibrary.value.id);
    showDelete.value = false;
  }
};

let searchTimer: ReturnType<typeof setTimeout>;
watch(searchQuery, (query) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    libraryStore.fetchLibraries({ search: query });
  }, 350);
});

onMounted(async () => {
  await loadData();
});
</script>
