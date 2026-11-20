<template>
  <div class="relative inline-block text-left" ref="containerRef" :class="{ 'w-full': block }">
    <!-- Trigger Button -->
    <button
      type="button"
      :disabled="disabled"
      @click="toggleDropdown"
      class="inline-flex items-center justify-between gap-2 transition-all cursor-pointer select-none outline-none group"
      :class="[
        sizeClasses,
        variantClasses,
        block ? 'w-full' : '',
        disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '',
        isOpen ? 'border-brand-blue ring-2 ring-brand-blue/20' : '',
      ]"
      :aria-expanded="isOpen"
      :aria-haspopup="true"
    >
      <span class="flex items-center gap-1.5 truncate">
        <component
          :is="triggerIcon"
          v-if="triggerIcon"
          class="shrink-0 transition-colors"
          :class="size === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4'"
        />
        <span class="truncate font-semibold tracking-tight">
          {{ displayLabel }}
        </span>
      </span>

      <ChevronDown
        class="shrink-0 text-main-text/40 transition-transform duration-200 group-hover:text-main-text/70"
        :class="[
          size === 'sm' ? 'w-3 h-3' : 'w-3.5 h-3.5',
          { 'rotate-180 text-brand-blue dark:text-brand-yellow': isOpen }
        ]"
      />
    </button>

    <!-- Dropdown Menu Popup -->
    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 translate-y-1 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-1 scale-95"
    >
      <div
        v-if="isOpen"
        class="absolute z-50 mt-1.5 rounded-md shadow-xl bg-card-bg border border-card-border ring-1 ring-black/5 py-1 overflow-y-auto custom-scrollbar"
        :class="[
          align === 'right' ? 'right-0' : 'left-0',
          menuWidth || 'min-w-[180px] w-max max-w-xs',
          maxHeight || 'max-h-64',
        ]"
        tabindex="-1"
        @keydown.esc="closeDropdown"
      >
        <div v-if="normalizedOptions.length === 0" class="px-3 py-2 text-xs text-main-text/50 italic text-center">
          {{ emptyText || 'No options available' }}
        </div>

        <button
          v-for="opt in normalizedOptions"
          :key="String(opt.value)"
          type="button"
          :disabled="opt.disabled"
          @click="selectOption(opt)"
          class="w-full text-left px-3 py-2 text-xs flex items-center justify-between gap-2.5 transition-colors cursor-pointer select-none"
          :class="[
            isSelected(opt.value)
              ? 'bg-brand-blue/10 text-brand-blue dark:text-brand-yellow font-bold'
              : 'text-main-text hover:bg-main-text/5 hover:text-brand-blue dark:hover:text-brand-yellow',
            opt.disabled ? 'opacity-40 cursor-not-allowed pointer-events-none' : '',
          ]"
        >
          <div class="flex items-center gap-2 min-w-0">
            <component
              :is="opt.icon"
              v-if="opt.icon"
              class="w-3.5 h-3.5 shrink-0 opacity-70"
            />
            <div class="truncate">
              <div class="truncate" :class="{ 'font-bold': isSelected(opt.value) }">
                {{ opt.label }}
              </div>
              <div v-if="opt.description" class="text-[10px] text-main-text/50 font-normal truncate">
                {{ opt.description }}
              </div>
            </div>
          </div>

          <div class="flex items-center gap-1.5 shrink-0 ml-2">
            <span
              v-if="opt.badge"
              class="px-1.5 py-0.5 rounded text-[9.5px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border"
            >
              {{ opt.badge }}
            </span>
            <Check
              v-if="isSelected(opt.value)"
              class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow shrink-0 stroke-[2.5]"
            />
          </div>
        </button>
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, type Component } from 'vue';
import { ChevronDown, Check } from 'lucide-vue-next';

export interface DropdownOption {
  value: any;
  label: string;
  icon?: Component;
  badge?: string;
  description?: string;
  disabled?: boolean;
}

interface Props {
  modelValue?: any;
  options: (DropdownOption | string | number)[];
  placeholder?: string;
  size?: 'sm' | 'md' | 'lg';
  variant?: 'button' | 'input' | 'ghost' | 'primary';
  align?: 'left' | 'right';
  icon?: Component;
  disabled?: boolean;
  block?: boolean;
  menuWidth?: string;
  maxHeight?: string;
  emptyText?: string;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  options: () => [],
  placeholder: 'Select option...',
  size: 'md',
  variant: 'button',
  align: 'left',
  disabled: false,
  block: false,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void;
  (e: 'change', value: any): void;
}>();

const isOpen = ref(false);
const containerRef = ref<HTMLElement | null>(null);

const toggleDropdown = () => {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
};

const closeDropdown = () => {
  isOpen.value = false;
};

const normalizedOptions = computed<DropdownOption[]>(() => {
  return props.options.map((opt) => {
    if (typeof opt === 'object' && opt !== null && 'value' in opt) {
      return opt as DropdownOption;
    }
    return {
      value: opt,
      label: String(opt),
    };
  });
});

const selectedOption = computed(() => {
  return normalizedOptions.value.find((opt) => String(opt.value) === String(props.modelValue)) || null;
});

const displayLabel = computed(() => {
  if (selectedOption.value) {
    return selectedOption.value.label;
  }
  return props.placeholder;
});

const triggerIcon = computed(() => {
  if (selectedOption.value?.icon) {
    return selectedOption.value.icon;
  }
  return props.icon || null;
});

const isSelected = (val: any) => {
  return String(props.modelValue) === String(val);
};

const selectOption = (opt: DropdownOption) => {
  if (opt.disabled) return;
  emit('update:modelValue', opt.value);
  emit('change', opt.value);
  closeDropdown();
};

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'h-8 px-2.5 text-[11px] rounded-md';
    case 'lg':
      return 'h-10 px-4 text-sm rounded-md';
    case 'md':
    default:
      return 'h-9 px-3 text-xs rounded-md';
  }
});

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'input':
      return 'bg-card-bg text-main-text border border-card-border/90 shadow-2xs hover:border-brand-blue/50 focus:border-brand-blue';
    case 'ghost':
      return 'bg-transparent text-main-text hover:bg-main-text/5 border border-transparent';
    case 'primary':
      return 'bg-brand-blue text-white border border-transparent shadow-2xs hover:bg-brand-blue-dark';
    case 'button':
    default:
      return 'bg-card-bg text-main-text border border-card-border/80 shadow-2xs hover:bg-main-text/5 hover:border-card-border';
  }
});

const handleClickOutside = (e: MouseEvent) => {
  if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
    closeDropdown();
  }
};

onMounted(() => {
  window.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  window.removeEventListener('click', handleClickOutside);
});
</script>
