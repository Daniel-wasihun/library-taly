<template>
  <div class="space-y-5 w-full">
    <!-- Header with Create Shift Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h3 class="text-base font-bold text-main-text">
          Library Shift Definitions & Ethiopian Local Time
        </h3>
        <p class="text-xs text-main-text/60 mt-0.5">
          Configure duty shifts with Ethiopian 12-hour clock (sunrise dawn base) and 24-hour time windows.
        </p>
      </div>

      <Button
        v-if="canManageShifts"
        variant="primary"
        size="sm"
        :icon="Plus"
        @click="openCreateModal"
      >
        Define New Shift
      </Button>
    </div>

    <!-- Shifts Grid -->
    <div v-if="shiftStore.loading" class="p-8 text-center text-xs text-main-text/50">
      <div class="w-6 h-6 border-2 border-brand-blue border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      Loading shifts...
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="shift in shiftStore.shifts"
        :key="shift.id"
        class="bg-card-bg border border-card-border rounded-sm p-4 shadow-2xs space-y-3 flex flex-col justify-between"
      >
        <div class="space-y-2.5">
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2.5">
              <div
                class="w-9 h-9 rounded-sm flex items-center justify-center text-white font-bold text-sm shadow-xs"
                :class="shift.code === 'night' ? 'bg-indigo-600' : 'bg-amber-600'"
              >
                {{ shift.code === 'night' ? '🌙' : '☀️' }}
              </div>
              <div>
                <h4 class="font-bold text-sm text-main-text flex items-center gap-1.5">
                  <span>{{ shift.name }}</span>
                  <span v-if="shift.name_am" class="text-xs text-main-text/50 font-normal">
                    ({{ shift.name_am }})
                  </span>
                </h4>
                <span class="text-[10px] font-mono text-main-text/50 uppercase tracking-wider">
                  Code: {{ shift.code }}
                </span>
              </div>
            </div>

            <div v-if="canManageShifts" class="flex items-center gap-1">
              <button
                type="button"
                class="p-1.5 rounded-sm hover:bg-nav-accent/10 text-main-text/60 hover:text-nav-accent transition cursor-pointer"
                title="Edit Shift"
                @click="openEditModal(shift)"
              >
                <Pencil class="w-3.5 h-3.5" />
              </button>
              <button
                type="button"
                class="p-1.5 rounded-sm hover:bg-rose-500/10 text-rose-600 transition cursor-pointer"
                title="Delete Shift"
                @click="confirmDelete(shift)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Time Display (Ethiopian Local Time & 24h) -->
          <div class="p-2.5 rounded-sm bg-card-bg/60 border border-card-border space-y-1.5 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-main-text/60 font-medium">Ethiopian Local Time:</span>
              <strong class="font-bold text-nav-accent">
                {{ shift.ethiopian_start_time }} – {{ shift.ethiopian_end_time }}
              </strong>
            </div>
            <div class="flex items-center justify-between text-[11px] text-main-text/50">
              <span>Standard 24-Hour Time:</span>
              <span class="font-mono">{{ shift.start_time }} – {{ shift.end_time }}</span>
            </div>
          </div>

          <p v-if="shift.description" class="text-xs text-main-text/70 line-clamp-2">
            {{ shift.description }}
          </p>
        </div>

        <div class="pt-2 border-t border-card-border flex items-center justify-between text-[11px]">
          <span class="text-main-text/50">Shift Status:</span>
          <span
            class="px-2 py-0.5 rounded-sm font-bold text-[10px]"
            :class="shift.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/10 text-rose-600'"
          >
            {{ shift.is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Modal
      :show="showModal"
      :title="editingShift ? 'Edit Shift Definition' : 'Define New Library Shift'"
      description="Set shift title, Ethiopian local time (12h cycle) and international 24h window."
      :icon="Clock"
      size="md"
      @close="showModal = false"
    >
      <form @submit.prevent="handleSaveShift" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              Shift Name (English) <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="e.g. Day Shift, Night Shift"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-semibold"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              Amharic Title (የፈረቃ ስም)
            </label>
            <input
              v-model="form.name_am"
              type="text"
              placeholder="e.g. የቀን ፈረቃ, የማታ ፈረቃ"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-medium"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              Shift Code / Identifier
            </label>
            <input
              v-model="form.code"
              type="text"
              placeholder="e.g. day, night, morning"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
          <div class="flex items-center gap-2 pt-6">
            <input
              v-model="form.is_active"
              type="checkbox"
              id="is_active_check"
              class="rounded-xs border-input-border text-nav-accent focus:ring-0 cursor-pointer"
            />
            <label for="is_active_check" class="text-xs font-semibold text-main-text cursor-pointer">
              Active Shift (Available for Scheduling)
            </label>
          </div>
        </div>

        <!-- Time Settings -->
        <div class="p-3 rounded-sm bg-card-bg/60 border border-card-border space-y-3">
          <p class="text-[11px] font-bold text-main-text/75 uppercase tracking-wider">
            Time Windows & Ethiopian Local Time
          </p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-main-text mb-1">
                24-Hour Start Time <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.start_time"
                type="time"
                required
                class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
                @change="updateComputedEthiopianTimes"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-main-text mb-1">
                24-Hour End Time <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.end_time"
                type="time"
                required
                class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
                @change="updateComputedEthiopianTimes"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-card-border/60">
            <div>
              <label class="block text-xs font-semibold text-main-text mb-1">
                Ethiopian Start Time (የኢትዮጵያ ሰዓት)
              </label>
              <input
                v-model="form.ethiopian_start_time"
                type="text"
                placeholder="e.g. 02:00 ጠዋት"
                class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-main-text mb-1">
                Ethiopian End Time (የኢትዮጵያ ሰዓት)
              </label>
              <input
                v-model="form.ethiopian_end_time"
                type="text"
                placeholder="e.g. 11:00 ከቀኑ"
                class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
              />
            </div>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Description (optional)
          </label>
          <textarea
            v-model="form.description"
            rows="2"
            placeholder="Operational coverage details, handover guidelines..."
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs resize-none"
          ></textarea>
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
          @click="handleSaveShift"
        >
          {{ editingShift ? 'Update Shift' : 'Create Shift' }}
        </Button>
      </template>
    </Modal>

    <!-- Delete Modal -->
    <Modal
      :show="showDelete"
      title="Delete Shift Definition"
      size="sm"
      @close="showDelete = false"
    >
      <div class="text-center py-2 space-y-3">
        <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center bg-rose-500/10 text-rose-600 border border-rose-500/20">
          <Trash2 class="w-6 h-6 stroke-[2.2]" />
        </div>
        <p class="text-xs text-main-text">
          Delete shift definition <strong class="text-rose-600">{{ deletingShift?.name }}</strong>?
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
          @click="handleDeleteShift"
        >
          Delete Shift
        </Button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useShiftStore } from '@/stores/shiftStore';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import { Clock, Plus, Pencil, Trash2 } from 'lucide-vue-next';
import type { Shift } from '@/types';

const shiftStore = useShiftStore();
const authStore = useAuthStore();
const langStore = useLanguageStore();

const showModal = ref(false);
const showDelete = ref(false);
const editingShift = ref<Shift | null>(null);
const deletingShift = ref<Shift | null>(null);

const form = reactive({
  name: '',
  name_am: '',
  code: '',
  start_time: '08:00',
  end_time: '17:00',
  ethiopian_start_time: '02:00 ጠዋት',
  ethiopian_end_time: '11:00 ከቀኑ',
  description: '',
  is_active: true,
});

const canManageShifts = computed(() => {
  return authStore.isAdmin || authStore.canManageLibrary;
});

const convertToEthiopianLocalTime = (time24: string): string => {
  if (!time24) return '';
  const parts = time24.split(':');
  const h = parseInt(parts[0], 10) || 0;
  const m = (parts[1] || '00').padStart(2, '0');

  let etH = h >= 6 ? h - 6 : h + 6;
  if (etH > 12) etH -= 12;
  if (etH === 0) etH = 12;

  let period = 'ከቀኑ';
  if (h >= 0 && h < 6) period = 'ከሌሊቱ';
  else if (h >= 6 && h < 12) period = 'ጠዋት';
  else if (h >= 12 && h < 18) period = 'ከቀኑ';
  else period = 'ከምሽቱ';

  const formattedH = String(etH).padStart(2, '0');
  return `${formattedH}:${m} ${period}`;
};

const updateComputedEthiopianTimes = () => {
  if (form.start_time) {
    form.ethiopian_start_time = convertToEthiopianLocalTime(form.start_time);
  }
  if (form.end_time) {
    form.ethiopian_end_time = convertToEthiopianLocalTime(form.end_time);
  }
};

const openCreateModal = () => {
  editingShift.value = null;
  form.name = '';
  form.name_am = '';
  form.code = '';
  form.start_time = '08:00';
  form.end_time = '17:00';
  form.ethiopian_start_time = '02:00 ጠዋት';
  form.ethiopian_end_time = '11:00 ከቀኑ';
  form.description = '';
  form.is_active = true;
  showModal.value = true;
};

const openEditModal = (shift: Shift) => {
  editingShift.value = shift;
  form.name = shift.name;
  form.name_am = shift.name_am || '';
  form.code = shift.code;
  form.start_time = shift.start_time;
  form.end_time = shift.end_time;
  form.ethiopian_start_time = shift.ethiopian_start_time || convertToEthiopianLocalTime(shift.start_time);
  form.ethiopian_end_time = shift.ethiopian_end_time || convertToEthiopianLocalTime(shift.end_time);
  form.description = shift.description || '';
  form.is_active = shift.is_active;
  showModal.value = true;
};

const confirmDelete = (shift: Shift) => {
  deletingShift.value = shift;
  showDelete.value = true;
};

const handleSaveShift = async () => {
  if (!form.name.trim()) return;

  let success = false;
  if (editingShift.value) {
    success = await shiftStore.updateShift(editingShift.value.id, { ...form });
  } else {
    success = await shiftStore.createShift({ ...form });
  }

  if (success) {
    showModal.value = false;
  }
};

const handleDeleteShift = async () => {
  if (!deletingShift.value) return;
  const success = await shiftStore.deleteShift(deletingShift.value.id);
  if (success) {
    showDelete.value = false;
  }
};

onMounted(() => {
  shiftStore.fetchShifts();
});
</script>
