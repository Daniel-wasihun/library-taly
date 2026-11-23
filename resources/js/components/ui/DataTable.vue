<script setup lang="ts">
/**
 * DataTable — Reusable, Shadcn-inspired data table matching Gibi-Gubae.
 * Supports searching, sorting, custom column slots (both #[key] and #cell-[key]),
 * skeleton loaders, empty states, and pagination.
 */
import { ref, computed } from 'vue';
import { ArrowUpDown, ChevronLeft, ChevronRight, Search, Inbox } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';

export interface ColumnDef {
  key: string;
  label: string;
  align?: 'left' | 'center' | 'right';
  width?: string;
  sortable?: boolean;
}

interface Props {
  columns: ColumnDef[];
  items?: any[];
  data?: any[];
  loading?: boolean;
  emptyTitle?: string;
  emptyDesc?: string;
  searchPlaceholder?: string;
  searchValue?: string;
  showSearch?: boolean;
  searchable?: boolean;
  currentPage?: number;
  perPage?: number;
  total?: number;
}

const props = withDefaults(defineProps<Props>(), {
  items: undefined,
  data: undefined,
  loading: false,
  emptyTitle: 'No records found',
  emptyDesc: 'Try adjusting your search criteria or filters.',
  searchPlaceholder: 'Search records...',
  searchValue: '',
  showSearch: false,
  searchable: false,
  currentPage: 1,
  perPage: 15,
  total: 0,
});

const emit = defineEmits<{
  (e: 'update:searchValue', val: string): void;
  (e: 'page-change', page: number): void;
  (e: 'sort', key: string): void;
}>();

const langStore = useLanguageStore();
const localSearch = ref('');
const sortKey = ref<string | null>(null);
const sortOrder = ref<'asc' | 'desc'>('asc');

const isSearchVisible = computed(() => props.showSearch || props.searchable);

const rawItems = computed(() => {
  if (Array.isArray(props.items)) return props.items;
  if (Array.isArray(props.data)) return props.data;
  return [];
});

const totalPages = computed(() => {
  const effectiveTotal = props.total > 0 ? props.total : rawItems.value.length;
  return Math.max(1, Math.ceil(effectiveTotal / props.perPage));
});

const handleSearchInput = (e: Event) => {
  const target = e.target as HTMLInputElement;
  localSearch.value = target.value;
  emit('update:searchValue', target.value);
};

const handleSort = (col: ColumnDef) => {
  if (!col.sortable) return;
  if (sortKey.value === col.key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = col.key;
    sortOrder.value = 'asc';
  }
  emit('sort', col.key);
};
</script>

<template>
  <div class="space-y-3.5 w-full">
    <!-- Toolbar: Search Bar & Filters -->
    <div v-if="isSearchVisible || $slots.toolbar" class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div v-if="isSearchVisible" class="relative w-full max-w-sm">
        <Search class="w-4 h-4 text-main-text/40 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          type="text"
          :value="searchValue"
          @input="handleSearchInput"
          :placeholder="searchPlaceholder"
          class="w-full pl-9 pr-4 py-2 text-xs rounded-sm bg-card-bg border border-card-border text-main-text placeholder:text-main-text/40 focus:outline-none focus:border-nav-accent focus:ring-1 focus:ring-nav-accent shadow-2xs transition"
        />
      </div>

      <div v-if="$slots.toolbar" class="flex items-center gap-2">
        <slot name="toolbar" />
      </div>
    </div>

    <!-- Table Container -->
    <div class="bg-card-bg border border-card-border rounded-sm shadow-2xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="border-b border-card-border bg-slate-50/70 dark:bg-slate-800/40 select-none">
              <th
                v-for="col in columns"
                :key="col.key"
                class="px-4 py-3 font-bold text-main-text/60 tracking-wider uppercase text-[10px]"
                :class="[
                  col.align === 'center' ? 'text-center' : (col.align === 'right' ? 'text-right' : 'text-left'),
                  col.sortable ? 'cursor-pointer hover:text-main-text transition' : ''
                ]"
                :style="col.width ? { width: col.width } : {}"
                @click="handleSort(col)"
              >
                <div class="inline-flex items-center gap-1.5" :class="col.align === 'center' ? 'justify-center' : (col.align === 'right' ? 'justify-end' : 'justify-start')">
                  <span>{{ col.label }}</span>
                  <ArrowUpDown v-if="col.sortable" class="w-3 h-3 text-main-text/40" />
                </div>
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-card-border/60">
            <!-- Loading Skeletons -->
            <template v-if="loading">
              <tr v-for="i in 5" :key="`skel-${i}`" class="animate-pulse">
                <td v-for="col in columns" :key="col.key" class="px-4 py-3.5">
                  <div class="h-3.5 bg-slate-200 dark:bg-slate-700/60 rounded-xs w-3/4"></div>
                </td>
              </tr>
            </template>

            <!-- Empty State -->
            <tr v-else-if="!rawItems.length">
              <td :colspan="columns.length" class="px-4 py-12 text-center text-main-text/40">
                <div class="flex flex-col items-center justify-center space-y-2">
                  <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800/60 flex items-center justify-center text-main-text/40">
                    <Inbox class="w-5 h-5 stroke-[1.8]" />
                  </div>
                  <p class="text-xs font-semibold text-main-text/70">{{ emptyTitle }}</p>
                  <p class="text-[11px] text-main-text/40 max-w-sm">{{ emptyDesc }}</p>
                </div>
              </td>
            </tr>

            <!-- Data Rows -->
            <tr
              v-else
              v-for="(item, idx) in rawItems"
              :key="item.id ?? idx"
              class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
            >
              <td
                v-for="col in columns"
                :key="col.key"
                class="px-4 py-3 text-main-text align-middle"
                :class="col.align === 'center' ? 'text-center' : (col.align === 'right' ? 'text-right' : 'text-left')"
              >
                <!-- Allow slot as either #[col.key] or #cell-[col.key] -->
                <slot :name="col.key" :item="item" :value="item[col.key]">
                  <slot :name="'cell-' + col.key" :item="item" :value="item[col.key]">
                    {{ item[col.key] ?? '—' }}
                  </slot>
                </slot>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="total > perPage || rawItems.length > perPage"
        class="px-4 py-3 border-t border-card-border flex items-center justify-between text-xs text-main-text/60 select-none bg-slate-50/40 dark:bg-slate-800/20"
      >
        <span>
          Showing {{ ((currentPage - 1) * perPage) + 1 }} to {{ Math.min(currentPage * perPage, total > 0 ? total : rawItems.length) }} of {{ total > 0 ? total : rawItems.length }} records
        </span>
        <div class="flex items-center gap-1.5">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="emit('page-change', currentPage - 1)"
            class="p-1.5 rounded-sm border border-card-border hover:bg-card-hover disabled:opacity-40 disabled:pointer-events-none cursor-pointer"
          >
            <ChevronLeft class="w-4 h-4 stroke-[2.2]" />
          </button>
          <span class="px-2 font-semibold text-main-text">
            {{ currentPage }} / {{ totalPages }}
          </span>
          <button
            type="button"
            :disabled="currentPage >= totalPages"
            @click="emit('page-change', currentPage + 1)"
            class="p-1.5 rounded-sm border border-card-border hover:bg-card-hover disabled:opacity-40 disabled:pointer-events-none cursor-pointer"
          >
            <ChevronRight class="w-4 h-4 stroke-[2.2]" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
