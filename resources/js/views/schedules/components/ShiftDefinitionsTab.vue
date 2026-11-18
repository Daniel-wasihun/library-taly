<template>
  <div class="space-y-5 w-full">
    <!-- Header with Create Shift Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card-bg border border-card-border rounded-sm p-4 shadow-2xs">
      <div>
        <h3 class="text-base font-bold text-main-text flex items-center gap-2">
          <Clock class="w-5 h-5 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('schedules.tab_shifts', 'Library Shift Definitions & Ethiopian Local Time') }}</span>
        </h3>
        <p class="text-xs text-main-text/60 mt-0.5">
          {{ langStore.t('schedules.subtitle', 'Configure duty shifts with Ethiopian 12-hour clock (sunrise dawn base) and 24-hour time windows.') }}
        </p>
      </div>

      <Button
        v-if="canManageShifts"
        variant="primary"
        size="sm"
        :icon="Plus"
        @click="openCreateModal"
      >
        {{ langStore.t('schedules.create_shift', 'Define New Shift') }}
      </Button>
    </div>

    <!-- Shifts Grid -->
    <div v-if="shiftStore.loading" class="p-8 text-center text-xs text-main-text/50">
      <div class="w-6 h-6 border-2 border-brand-blue border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      {{ langStore.t('common.loading', 'Loading shifts...') }}
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
                  <span>{{ langStore.isAmharic() ? (shift.name_am || shift.name) : (shift.name || shift.name_am) }}</span>
                </h4>
                <span class="text-[10px] font-mono text-main-text/50 uppercase tracking-wider">
                  {{ langStore.t('schedules.shift_code', 'Code') }}: {{ shift.code }}
                </span>
              </div>
            </div>

            <div v-if="canManageShifts" class="flex items-center gap-1">
              <button
                type="button"
                class="p-1.5 rounded-sm hover:bg-nav-accent/10 text-main-text/60 hover:text-nav-accent transition cursor-pointer"
                :title="langStore.t('common.edit', 'Edit Shift')"
                @click="openEditModal(shift)"
              >
                <Pencil class="w-3.5 h-3.5" />
              </button>
              <button
                type="button"
                class="p-1.5 rounded-sm hover:bg-rose-500/10 text-rose-600 transition cursor-pointer"
                :title="langStore.t('common.delete', 'Delete Shift')"
                @click="confirmDelete(shift)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Time Display (Ethiopian Local Time & 24h) -->
          <div class="p-2.5 rounded-sm bg-input-bg border border-card-border space-y-1.5 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-main-text/60 font-medium">{{ langStore.t('schedules.ethiopian_time', 'Ethiopian Local Time') }}:</span>
              <strong class="font-bold text-nav-accent">
                {{ langStore.formatTimeWindow(shift.ethiopian_start_time) }} – {{ langStore.formatTimeWindow(shift.ethiopian_end_time) }}
              </strong>
            </div>
            <div class="flex items-center justify-between text-[11px] text-main-text/50">
              <span>{{ langStore.t('schedules.standard_time', 'Standard 24h Time') }}:</span>
              <span class="font-mono">
                {{ shift.start_time }} – {{ shift.end_time }}
                <span v-if="isNightCrossOver(shift.start_time, shift.end_time)" class="text-[10px] text-amber-600 font-semibold ml-1">
                  ({{ langStore.t('schedules.next_day', '+1 day') }})
                </span>
              </span>
            </div>
          </div>

          <p v-if="shift.description" class="text-xs text-main-text/70 line-clamp-2">
            {{ shift.description }}
          </p>
        </div>

        <div class="pt-2 border-t border-card-border flex items-center justify-between text-[11px]">
          <span class="text-main-text/50">{{ langStore.t('schedules.shift_status', 'Shift Status') }}:</span>
          <span
            class="px-2 py-0.5 rounded-sm font-bold text-[10px]"
            :class="shift.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/10 text-rose-600'"
          >
            {{ shift.is_active ? langStore.t('common.active', 'Active') : langStore.t('common.inactive', 'Inactive') }}
          </span>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Modal
      :show="showModal"
      :title="editingShift ? langStore.t('schedules.edit_shift', 'Edit Shift Definition') : langStore.t('schedules.create_shift', 'Define New Shift')"
      :description="langStore.t('schedules.auto_modal_desc', 'Set shift titles and time window with automatic live conversion between Ethiopian and standard time.')"
      :icon="Clock"
      size="md"
      @close="showModal = false"
    >
      <form @submit.prevent="handleSaveShift" class="space-y-4">
        <!-- Titles -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('schedules.shift_name', 'Shift Name (English)') }} <span class="text-rose-500">*</span>
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
              {{ langStore.t('schedules.shift_name_am', 'Amharic Title') }}
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
              {{ langStore.t('schedules.shift_code', 'Shift Code') }}
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
              {{ langStore.t('schedules.active_shift', 'Active Shift (Available for Scheduling)') }}
            </label>
          </div>
        </div>

        <!-- Unified Single Time Picker Container with Live Converted Preview -->
        <div class="p-3.5 rounded-sm bg-input-bg border border-input-border space-y-3.5">
          <!-- Time System Toggle (Only One Input System at a time) -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2.5 border-b border-card-border">
            <div>
              <p class="text-xs font-bold text-main-text">
                {{ langStore.t('schedules.input_mode', 'Time Input System') }}
              </p>
              <p class="text-[11px] text-main-text/50">
                Insert time in one format; the other format is converted and shown in front automatically.
              </p>
            </div>

            <!-- Segmented Mode Buttons -->
            <div class="inline-flex rounded-sm p-0.5 bg-card-bg border border-card-border self-start sm:self-auto">
              <button
                type="button"
                class="px-2.5 py-1 text-xs font-bold rounded-xs transition cursor-pointer"
                :class="timeMode === 'ethiopian' ? 'bg-nav-accent text-white shadow-xs' : 'text-main-text/60 hover:text-main-text'"
                @click="switchTimeMode('ethiopian')"
              >
                🇪🇹 {{ langStore.t('schedules.input_ethiopian', 'Ethiopian Clock') }}
              </button>
              <button
                type="button"
                class="px-2.5 py-1 text-xs font-bold rounded-xs transition cursor-pointer"
                :class="timeMode === 'standard' ? 'bg-nav-accent text-white shadow-xs' : 'text-main-text/60 hover:text-main-text'"
                @click="switchTimeMode('standard')"
              >
                🌐 {{ langStore.t('schedules.input_standard', 'Standard 24h') }}
              </button>
            </div>
          </div>

          <!-- 1. OPTION A: Ethiopian Time Picker (Single Unified Input) -->
          <div v-if="timeMode === 'ethiopian'" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Start Time -->
              <div>
                <label class="block text-xs font-semibold text-main-text mb-1">
                  {{ langStore.t('schedules.start_time', 'Start Time') }} ({{ langStore.t('schedules.ethiopian_time', 'Ethiopian Time') }}) <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-3 gap-1.5">
                  <!-- Hour -->
                  <div class="relative">
                    <select
                      v-model.number="ethStart.hour"
                      class="w-full appearance-none pl-2.5 pr-6 py-1.5 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text font-bold hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
                      @change="onEthiopianTimeChanged"
                    >
                      <option v-for="h in [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]" :key="h" :value="h">
                        {{ String(h).padStart(2, '0') }}
                      </option>
                    </select>
                    <div class="absolute right-1.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                      <ChevronDown class="w-3.5 h-3.5" />
                    </div>
                  </div>
                  <!-- Minute -->
                  <div class="relative">
                    <select
                      v-model="ethStart.minute"
                      class="w-full appearance-none pl-2 pr-5 py-1.5 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text font-mono hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
                      @change="onEthiopianTimeChanged"
                    >
                      <option value="00">:00</option>
                      <option value="15">:15</option>
                      <option value="30">:30</option>
                      <option value="45">:45</option>
                    </select>
                    <div class="absolute right-1.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                      <ChevronDown class="w-3.5 h-3.5" />
                    </div>
                  </div>
                  <!-- Period -->
                  <div class="relative">
                    <select
                      v-model="ethStart.period"
                      class="w-full appearance-none pl-2 pr-5 py-1.5 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text font-semibold text-[11px] hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
                      @change="onEthiopianTimeChanged"
                    >
                      <option value="ጠዋት">{{ langStore.isAmharic() ? 'ጠዋት' : 'Morning' }}</option>
                      <option value="ከቀኑ">{{ langStore.isAmharic() ? 'ከቀኑ' : 'Afternoon' }}</option>
                      <option value="ከምሽቱ">{{ langStore.isAmharic() ? 'ከምሽቱ' : 'Evening' }}</option>
                      <option value="ከሌሊቱ">{{ langStore.isAmharic() ? 'ከሌሊቱ' : 'Late Night' }}</option>
                    </select>
                    <div class="absolute right-1.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                      <ChevronDown class="w-3.5 h-3.5" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- End Time (Supports Night 11 o'clock 11:00 ከሌሊቱ / 05:00 AM) -->
              <div>
                <label class="block text-xs font-semibold text-main-text mb-1">
                  {{ langStore.t('schedules.end_time', 'End Time') }} ({{ langStore.t('schedules.ethiopian_time', 'Ethiopian Time') }}) <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-3 gap-1.5">
                  <!-- Hour -->
                  <div class="relative">
                    <select
                      v-model.number="ethEnd.hour"
                      class="w-full appearance-none pl-2.5 pr-6 py-1.5 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text font-bold hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
                      @change="onEthiopianTimeChanged"
                    >
                      <option v-for="h in [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]" :key="h" :value="h">
                        {{ String(h).padStart(2, '0') }}
                      </option>
                    </select>
                    <div class="absolute right-1.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                      <ChevronDown class="w-3.5 h-3.5" />
                    </div>
                  </div>
                  <!-- Minute -->
                  <div class="relative">
                    <select
                      v-model="ethEnd.minute"
                      class="w-full appearance-none pl-2 pr-5 py-1.5 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text font-mono hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
                      @change="onEthiopianTimeChanged"
                    >
                      <option value="00">:00</option>
                      <option value="15">:15</option>
                      <option value="30">:30</option>
                      <option value="45">:45</option>
                    </select>
                    <div class="absolute right-1.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                      <ChevronDown class="w-3.5 h-3.5" />
                    </div>
                  </div>
                  <!-- Period with full night support up to 11:00 ከሌሊቱ -->
                  <div class="relative">
                    <select
                      v-model="ethEnd.period"
                      class="w-full appearance-none pl-2 pr-5 py-1.5 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text font-semibold text-[11px] hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
                      @change="onEthiopianTimeChanged"
                    >
                      <option value="ጠዋት">{{ langStore.isAmharic() ? 'ጠዋት' : 'Morning' }}</option>
                      <option value="ከቀኑ">{{ langStore.isAmharic() ? 'ከቀኑ' : 'Afternoon' }}</option>
                      <option value="ከምሽቱ">{{ langStore.isAmharic() ? 'ከምሽቱ' : 'Evening' }}</option>
                      <option value="ከሌሊቱ">{{ langStore.isAmharic() ? 'ከሌሊቱ (የሌሊት 11 ሰዓት ጨምሮ)' : 'Late Night (up to Night 11)' }}</option>
                    </select>
                    <div class="absolute right-1.5 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                      <ChevronDown class="w-3.5 h-3.5" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 2. OPTION B: Standard 24h Time Picker -->
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-main-text mb-1">
                {{ langStore.t('schedules.start_time', 'Start Time') }} (24h) <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.start_time"
                type="time"
                required
                class="w-full px-3 py-2 text-xs rounded-sm bg-card-bg border border-card-border text-main-text font-mono"
                @change="onStandardTimeChanged"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-main-text mb-1">
                {{ langStore.t('schedules.end_time', 'End Time') }} (24h) <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.end_time"
                type="time"
                required
                class="w-full px-3 py-2 text-xs rounded-sm bg-card-bg border border-card-border text-main-text font-mono"
                @change="onStandardTimeChanged"
              />
            </div>
          </div>

          <!-- LIVE CONVERTED TIME WINDOW PREVIEW (Shown directly in front) -->
          <div class="p-3 rounded-sm bg-card-bg border border-nav-accent/30 text-xs space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-bold text-nav-accent uppercase tracking-wider flex items-center gap-1.5">
                <Clock class="w-3.5 h-3.5" />
                <span>{{ langStore.t('schedules.converted_preview', 'Live Converted Shift Window') }}</span>
              </span>
              <span v-if="isNightCrossOver(form.start_time, form.end_time)" class="px-2 py-0.5 rounded-sm text-[10px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                🌙 {{ langStore.t('schedules.next_day', 'Next Day (+1 day)') }}
              </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
              <div class="p-2 rounded-sm bg-input-bg border border-card-border flex items-center justify-between">
                <span class="text-main-text/60">🇪🇹 {{ langStore.t('schedules.ethiopian_time', 'Ethiopian Local Time') }}:</span>
                <strong class="font-bold text-main-text">{{ langStore.formatTimeWindow(form.ethiopian_start_time) }} – {{ langStore.formatTimeWindow(form.ethiopian_end_time) }}</strong>
              </div>
              <div class="p-2 rounded-sm bg-input-bg border border-card-border flex items-center justify-between">
                <span class="text-main-text/60">🌐 {{ langStore.t('schedules.standard_time', 'Standard 24h Time') }}:</span>
                <strong class="font-mono font-bold text-main-text">{{ form.start_time }} – {{ form.end_time }}</strong>
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.t('common.actions', 'Description') }} (optional)
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
          {{ editingShift ? langStore.t('common.save', 'Update Shift') : langStore.t('schedules.create_shift', 'Create Shift') }}
        </Button>
      </template>
    </Modal>

    <!-- Delete Modal -->
    <Modal
      :show="showDelete"
      :title="langStore.t('common.delete', 'Delete Shift Definition')"
      size="sm"
      @close="showDelete = false"
    >
      <div class="text-center py-2 space-y-3">
        <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center bg-rose-500/10 text-rose-600 border border-rose-500/20">
          <Trash2 class="w-6 h-6 stroke-[2.2]" />
        </div>
        <p class="text-xs text-main-text">
          {{ langStore.t('common.confirm_delete', 'Are you sure you want to delete this shift definition?') }}
          <strong class="text-rose-600 block mt-1">{{ deletingShift?.name }}</strong>
        </p>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showDelete = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button
          variant="danger"
          size="sm"
          :loading="shiftStore.saving"
          @click="handleDeleteShift"
        >
          {{ langStore.t('common.delete', 'Delete Shift') }}
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
import { Clock, Plus, Pencil, Trash2, ChevronDown } from 'lucide-vue-next';
import type { Shift } from '@/types';

const shiftStore = useShiftStore();
const authStore = useAuthStore();
const langStore = useLanguageStore();

const showModal = ref(false);
const showDelete = ref(false);
const editingShift = ref<Shift | null>(null);
const deletingShift = ref<Shift | null>(null);

// Mode: either 'ethiopian' or 'standard'
const timeMode = ref<'ethiopian' | 'standard'>('ethiopian');

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

// Ethiopian Time Picker state
const ethStart = reactive({
  hour: 2,
  minute: '00',
  period: 'ጠዋት' as 'ጠዋት' | 'ከቀኑ' | 'ከምሽቱ' | 'ከሌሊቱ',
});

const ethEnd = reactive({
  hour: 11,
  minute: '00',
  period: 'ከቀኑ' as 'ጠዋት' | 'ከቀኑ' | 'ከምሽቱ' | 'ከሌሊቱ',
});

const canManageShifts = computed(() => {
  return authStore.isAdmin || authStore.canManageLibrary;
});

/**
 * Check if the shift crosses midnight (e.g. 17:00 to 05:00 next morning)
 */
const isNightCrossOver = (startTime: string, endTime: string): boolean => {
  if (!startTime || !endTime) return false;
  const startH = parseInt(startTime.split(':')[0], 10);
  const endH = parseInt(endTime.split(':')[0], 10);
  return endH < startH;
};

/**
 * Convert Ethiopian time to 24h International Time
 */
const ethiopianTo24 = (hEt: number, min: string, period: string): string => {
  let h24 = 0;
  if (period === 'ጠዋት') {
    h24 = hEt === 12 ? 6 : hEt + 6;
  } else if (period === 'ከቀኑ') {
    h24 = hEt === 6 ? 12 : hEt + 6;
  } else if (period === 'ከምሽቱ') {
    h24 = hEt === 12 ? 18 : hEt + 18;
  } else if (period === 'ከሌሊቱ') {
    // 6:00 to 11:00 night = 00:00 to 05:00 early morning!
    h24 = hEt - 6;
    if (h24 < 0) h24 += 24;
  }
  const hStr = String(h24 % 24).padStart(2, '0');
  return `${hStr}:${min}`;
};

/**
 * Convert 24h International Time to Ethiopian time
 */
const intlToEthiopian = (time24: string): { hour: number; minute: string; period: 'ጠዋት' | 'ከቀኑ' | 'ከምሽቱ' | 'ከሌሊቱ'; formatted: string } => {
  if (!time24) return { hour: 12, minute: '00', period: 'ጠዋት', formatted: '12:00 ጠዋት' };
  const parts = time24.split(':');
  const h = parseInt(parts[0], 10) || 0;
  const m = (parts[1] || '00').padStart(2, '0');

  let etH = h >= 6 ? h - 6 : h + 6;
  if (etH > 12) etH -= 12;
  if (etH === 0) etH = 12;

  let period: 'ጠዋት' | 'ከቀኑ' | 'ከምሽቱ' | 'ከሌሊቱ' = 'ከቀኑ';
  if (h >= 0 && h < 6) period = 'ከሌሊቱ';
  else if (h >= 6 && h < 12) period = 'ጠዋት';
  else if (h >= 12 && h < 18) period = 'ከቀኑ';
  else period = 'ከምሽቱ';

  const formattedH = String(etH).padStart(2, '0');
  return {
    hour: etH,
    minute: m,
    period,
    formatted: `${formattedH}:${m} ${period}`,
  };
};

/**
 * Sync from Ethiopian Time inputs to 24h & formatted strings
 */
const onEthiopianTimeChanged = () => {
  form.start_time = ethiopianTo24(ethStart.hour, ethStart.minute, ethStart.period);
  form.end_time = ethiopianTo24(ethEnd.hour, ethEnd.minute, ethEnd.period);

  form.ethiopian_start_time = `${String(ethStart.hour).padStart(2, '0')}:${ethStart.minute} ${ethStart.period}`;
  form.ethiopian_end_time = `${String(ethEnd.hour).padStart(2, '0')}:${ethEnd.minute} ${ethEnd.period}`;
};

/**
 * Sync from Standard 24h Time inputs to Ethiopian time
 */
const onStandardTimeChanged = () => {
  const startEt = intlToEthiopian(form.start_time);
  ethStart.hour = startEt.hour;
  ethStart.minute = startEt.minute;
  ethStart.period = startEt.period;
  form.ethiopian_start_time = startEt.formatted;

  const endEt = intlToEthiopian(form.end_time);
  ethEnd.hour = endEt.hour;
  ethEnd.minute = endEt.minute;
  ethEnd.period = endEt.period;
  form.ethiopian_end_time = endEt.formatted;
};

const switchTimeMode = (mode: 'ethiopian' | 'standard') => {
  timeMode.value = mode;
  if (mode === 'ethiopian') {
    onStandardTimeChanged();
  } else {
    onEthiopianTimeChanged();
  }
};

const openCreateModal = () => {
  editingShift.value = null;
  form.name = '';
  form.name_am = '';
  form.code = '';
  form.start_time = '08:00';
  form.end_time = '17:00';
  form.description = '';
  form.is_active = true;

  timeMode.value = 'ethiopian';
  ethStart.hour = 2;
  ethStart.minute = '00';
  ethStart.period = 'ጠዋት';

  ethEnd.hour = 11;
  ethEnd.minute = '00';
  ethEnd.period = 'ከቀኑ';

  onEthiopianTimeChanged();
  showModal.value = true;
};

const openEditModal = (shift: Shift) => {
  editingShift.value = shift;
  form.name = shift.name;
  form.name_am = shift.name_am || '';
  form.code = shift.code;
  form.start_time = shift.start_time;
  form.end_time = shift.end_time;
  form.description = shift.description || '';
  form.is_active = shift.is_active;

  timeMode.value = 'ethiopian';
  onStandardTimeChanged();
  showModal.value = true;
};

const confirmDelete = (shift: Shift) => {
  deletingShift.value = shift;
  showDelete.value = true;
};

const handleSaveShift = async () => {
  if (!form.name.trim()) return;

  onEthiopianTimeChanged();

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
