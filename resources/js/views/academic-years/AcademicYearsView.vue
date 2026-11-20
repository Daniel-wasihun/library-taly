<template>
  <div class="space-y-5 w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <CalendarRange class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.isAmharic() ? 'የአካዳሚክ ዓመታት አስተዳደር' : 'Academic Years Management' }}</span>
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          {{ langStore.isAmharic() ? 'የዩኒቨርሲቲው የአካዳሚክ ዓመታት የቀን ወሰን፣ ንቁ የትምህርት ዘመንና የሪፖርት ማጣሪያዎች' : 'Configure official institutional academic calendar sessions, active operational years, and reporting windows' }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Button
          variant="secondary"
          size="sm"
          :icon="RefreshCw"
          :loading="loading"
          @click="loadData"
        >
          {{ langStore.t('common.refresh', 'Refresh') }}
        </Button>
        <Button
          v-if="canManage"
          variant="primary"
          size="sm"
          :icon="Plus"
          @click="openCreateModal"
        >
          {{ langStore.isAmharic() ? 'አዲስ የአካዳሚክ ዓመት መዝግብ' : 'Add Academic Year' }}
        </Button>
      </div>
    </div>

    <!-- Academic Years DataTable -->
    <DataTable
      :columns="columns"
      :items="academicYears"
      :loading="loading"
      show-search
      v-model:search-value="searchQuery"
      :search-placeholder="langStore.isAmharic() ? 'በስም ወይም በመለያ ኮድ ፈልግ...' : 'Search academic years by name or code...'"
      :total="academicYears.length"
      :per-page="15"
      empty-title="No academic years registered"
      empty-desc="Register an academic year to manage attendance sessions and reporting intervals."
    >
      <!-- Year Identity Column -->
      <template #name="{ item }">
        <div v-if="item" class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-brand-blue/10 dark:bg-brand-yellow/10 text-brand-blue dark:text-brand-yellow border border-brand-blue/20 dark:border-brand-yellow/20 flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">
            <GraduationCap class="w-4 h-4 stroke-[2.2]" />
          </div>
          <div class="min-w-0">
            <div class="font-bold text-main-text flex items-center gap-1.5">
              <span class="truncate">{{ item.name }}</span>
              <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border">
                {{ item.code }}
              </span>
            </div>
            <div v-if="item.description" class="text-[11px] text-main-text/50 truncate max-w-xs mt-0.5">
              {{ item.description }}
            </div>
          </div>
        </div>
      </template>

      <!-- Calendar Date Span Column -->
      <template #date_span="{ item }">
        <div v-if="item" class="space-y-0.5 text-xs font-mono">
          <div class="flex items-center gap-1.5 text-main-text">
            <Calendar class="w-3.5 h-3.5 text-brand-blue shrink-0" />
            <span class="font-semibold">{{ formatDate(item.start_date) }}</span>
            <span class="text-main-text/40">→</span>
            <span class="font-semibold">{{ formatDate(item.end_date) }}</span>
          </div>
        </div>
      </template>

      <!-- Active Status Column -->
      <template #status="{ item }">
        <div v-if="item">
          <span
            v-if="item.is_current"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
          >
            <CheckCircle2 class="w-3.5 h-3.5" />
            {{ langStore.isAmharic() ? 'ንቁ የአካዳሚክ ዓመት' : 'Current Active Year' }}
          </span>
          <span
            v-else
            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium text-main-text/50 bg-main-bg/50 border border-card-border"
          >
            {{ langStore.isAmharic() ? 'ያለፈ / ተዘግቷል' : 'Archived' }}
          </span>
        </div>
      </template>

      <!-- Actions Column -->
      <template #actions="{ item }">
        <div v-if="item && canManage" class="flex items-center justify-end gap-1.5">
          <Button
            v-if="!item.is_current"
            variant="secondary"
            size="sm"
            class="!px-2 !py-1 text-[11px] !h-7 text-brand-blue hover:text-brand-blue hover:bg-brand-blue/10"
            :loading="activatingId === item.id"
            @click="handleSetCurrent(item)"
            :title="langStore.isAmharic() ? 'እንደ ንቁ ዓመት ሰይም' : 'Set as Current Active Year'"
          >
            <Star class="w-3.5 h-3.5 mr-1" />
            {{ langStore.isAmharic() ? 'ንቁ አድርግ' : 'Set Active' }}
          </Button>

          <Button
            variant="secondary"
            size="sm"
            class="!p-1.5 !h-7 !w-7"
            :icon="Pencil"
            @click="openEditModal(item)"
            :title="langStore.t('common.edit', 'Edit')"
          />

          <Button
            variant="danger"
            size="sm"
            class="!p-1.5 !h-7 !w-7"
            :icon="Trash2"
            @click="openDeleteConfirm(item)"
            :title="langStore.t('common.delete', 'Delete')"
          />
        </div>
      </template>
    </DataTable>

    <!-- Create/Edit Modal Dialog -->
    <Modal
      :isOpen="isModalOpen"
      :title="isEditing ? (langStore.isAmharic() ? 'የአካዳሚክ ዓመት አስተካክል' : 'Edit Academic Year') : (langStore.isAmharic() ? 'አዲስ የአካዳሚክ ዓመት መዝግብ' : 'Add Academic Year')"
      @close="closeModal"
    >
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.isAmharic() ? 'የአካዳሚክ ዓመት ስም' : 'Academic Year Name' }} <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. 2019 E.C. (2026/2027)"
            class="w-full h-9 px-3 text-xs rounded-md bg-main-bg border border-card-border text-main-text focus:outline-none focus:ring-1 focus:ring-brand-blue"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.isAmharic() ? 'የመለያ ኮድ' : 'Academic Year Code' }} <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.code"
            type="text"
            required
            placeholder="e.g. AY-2019"
            class="w-full h-9 px-3 text-xs font-mono rounded-md bg-main-bg border border-card-border text-main-text focus:outline-none focus:ring-1 focus:ring-brand-blue uppercase"
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.isAmharic() ? 'የመጀመሪያ ቀን (Gregorian)' : 'Start Date (Gregorian)' }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.start_date"
              type="date"
              required
              class="w-full h-9 px-3 text-xs rounded-md bg-main-bg border border-card-border text-main-text focus:outline-none focus:ring-1 focus:ring-brand-blue"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.isAmharic() ? 'የማብቂያ ቀን (Gregorian)' : 'End Date (Gregorian)' }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.end_date"
              type="date"
              required
              class="w-full h-9 px-3 text-xs rounded-md bg-main-bg border border-card-border text-main-text focus:outline-none focus:ring-1 focus:ring-brand-blue"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.isAmharic() ? 'ዝርዝር ማብራሪያ' : 'Description / Notes' }}
          </label>
          <textarea
            v-model="form.description"
            rows="2"
            placeholder="Optional notes regarding this academic session..."
            class="w-full px-3 py-2 text-xs rounded-md bg-main-bg border border-card-border text-main-text focus:outline-none focus:ring-1 focus:ring-brand-blue"
          ></textarea>
        </div>

        <div class="flex items-center gap-2 pt-1">
          <input
            id="is_current"
            v-model="form.is_current"
            type="checkbox"
            class="w-4 h-4 rounded text-brand-blue focus:ring-brand-blue/30 border-card-border cursor-pointer"
          />
          <label for="is_current" class="text-xs font-medium text-main-text cursor-pointer select-none">
            {{ langStore.isAmharic() ? 'ይህ የአካዳሚክ ዓመት አሁን ንቁ ይሁን' : 'Set as current active academic session' }}
          </label>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-card-border">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            @click="closeModal"
          >
            {{ langStore.t('common.cancel', 'Cancel') }}
          </Button>
          <Button
            type="submit"
            variant="primary"
            size="sm"
            :loading="submitting"
          >
            {{ isEditing ? langStore.t('common.save', 'Save Changes') : (langStore.isAmharic() ? 'መዝግብ' : 'Register Year') }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal
      :isOpen="isDeleteModalOpen"
      :title="langStore.isAmharic() ? 'የአካዳሚክ ዓመት ማረጋገጫ' : 'Confirm Delete Academic Year'"
      @close="isDeleteModalOpen = false"
    >
      <div class="space-y-4">
        <p class="text-xs text-main-text">
          {{ langStore.isAmharic() ? 'እርግጠኛ ነዎት ይህን የአካዳሚክ ዓመት መሰረዝ ይፈልጋሉ?' : 'Are you sure you want to permanently delete this academic year session?' }}
        </p>
        <div v-if="targetItem" class="p-3 rounded bg-rose-500/10 border border-rose-500/20 text-xs font-semibold text-rose-600 dark:text-rose-400">
          {{ targetItem.name }} ({{ targetItem.code }})
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            @click="isDeleteModalOpen = false"
          >
            {{ langStore.t('common.cancel', 'Cancel') }}
          </Button>
          <Button
            type="button"
            variant="danger"
            size="sm"
            :loading="deleting"
            @click="handleDelete"
          >
            {{ langStore.t('common.delete', 'Delete') }}
          </Button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import {
  CalendarRange,
  Calendar,
  GraduationCap,
  RefreshCw,
  Plus,
  Pencil,
  Trash2,
  CheckCircle2,
  Star,
} from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import DataTable from '@/components/ui/DataTable.vue';
import Modal from '@/components/ui/Modal.vue';
import { useLanguageStore } from '@/stores/languageStore';
import { useAuthStore } from '@/stores/authStore';
import { useToastStore } from '@/stores/toastStore';
import { academicYearsApi } from '@/api';
import type { AcademicYear } from '@/types';

const langStore = useLanguageStore();
const authStore = useAuthStore();
const toast = useToastStore();

const canManage = computed(() => {
  return authStore.isAdmin || authStore.canManageLibrary;
});

const academicYears = ref<AcademicYear[]>([]);
const loading = ref(false);
const searchQuery = ref('');
const activatingId = ref<number | null>(null);

const columns = [
  { key: 'name', label: 'Academic Session / Code', sortable: true },
  { key: 'date_span', label: 'Date Interval (Start – End)', sortable: true },
  { key: 'status', label: 'Status', sortable: false },
  { key: 'actions', label: '', sortable: false, class: 'text-right' },
];

const loadData = async () => {
  loading.value = true;
  try {
    const res = await academicYearsApi.list();
    if (res.data?.data) {
      academicYears.value = res.data.data;
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to load academic years');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadData();
});

const formatDate = (dateStr: string) => {
  if (!dateStr) return '';
  return dateStr.split('T')[0];
};

// Modal State
const isModalOpen = ref(false);
const isEditing = ref(false);
const submitting = ref(false);
const editId = ref<number | null>(null);

const form = ref({
  name: '',
  code: '',
  start_date: '',
  end_date: '',
  is_current: false,
  description: '',
});

const openCreateModal = () => {
  isEditing.value = false;
  editId.value = null;
  form.value = {
    name: '',
    code: '',
    start_date: '',
    end_date: '',
    is_current: academicYears.value.length === 0,
    description: '',
  };
  isModalOpen.value = true;
};

const openEditModal = (item: AcademicYear) => {
  isEditing.value = true;
  editId.value = item.id;
  form.value = {
    name: item.name,
    code: item.code,
    start_date: formatDate(item.start_date),
    end_date: formatDate(item.end_date),
    is_current: item.is_current,
    description: item.description || '',
  };
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  isEditing.value = false;
  editId.value = null;
};

const handleSubmit = async () => {
  submitting.value = true;
  try {
    if (isEditing.value && editId.value) {
      const res = await academicYearsApi.update(editId.value, form.value);
      toast.success(res.data?.message || 'Academic year updated successfully');
    } else {
      const res = await academicYearsApi.create(form.value);
      toast.success(res.data?.message || 'Academic year registered successfully');
    }
    closeModal();
    await loadData();
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to save academic year');
  } finally {
    submitting.value = false;
  }
};

const handleSetCurrent = async (item: AcademicYear) => {
  activatingId.value = item.id;
  try {
    const res = await academicYearsApi.setCurrent(item.id);
    toast.success(res.data?.message || `Academic year '${item.name}' is now active`);
    await loadData();
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to activate academic year');
  } finally {
    activatingId.value = null;
  }
};

// Delete state
const isDeleteModalOpen = ref(false);
const deleting = ref(false);
const targetItem = ref<AcademicYear | null>(null);

const openDeleteConfirm = (item: AcademicYear) => {
  targetItem.value = item;
  isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
  if (!targetItem.value) return;
  deleting.value = true;
  try {
    const res = await academicYearsApi.destroy(targetItem.value.id);
    toast.success(res.data?.message || 'Academic year deleted successfully');
    isDeleteModalOpen.value = false;
    targetItem.value = null;
    await loadData();
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to delete academic year');
  } finally {
    deleting.value = false;
  }
};
</script>
