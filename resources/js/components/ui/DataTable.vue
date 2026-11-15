<script setup lang="ts">
/**
 * DataTable — Shadcn-inspired reusable data table matching Gibi-Gubae.
 */
import { ref, computed } from 'vue';
import { ChevronLeft, ChevronRight, Search } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';

export interface ColumnDef {
  key: string;
  label: string;
  align?: 'left' | 'center' | 'right';
  width?: string;
}

interface Props {
  columns: ColumnDef[];
  items?: any[];
  loading?: boolean;
  emptyTitle?: string;
  searchPlaceholder?: string;
  searchValue?: string;
  showSearch?: boolean;
  currentPage?: number;
  perPage?: number;
  total?: number;
}

const props = withDefaults(defineProps<Props>(), {
  items: () => [],
  loading: false,
  emptyTitle: 'No records found',
  searchPlaceholder: 'Search records...',
  searchValue: '',
  showSearch: false,
  currentPage: 1,
  perPage: 15,
  total: 0,
});

const emit = defineEmits<{
  (e: 'update:searchValue', val: string): void;
  (e: 'page-change', page: number): void;
}>();

const langStore = useLanguageStore();

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(props.total / props.perPage));
});

const handleSearchInput = (e: Event) => {
  const target = e.target as HTMLInputElement;
  emit('update:searchValue', target.value);
};
</script>

<template>
  <div class="space-y-3.5">
    <!-- Toolbar: Search Bar -->
    <div v-if="showSearch" class="flex items-center justify-between gap-3">
      <div class="relative w-full max-w-sm">
        <Search class="w-4 h-4 text-main-text/40 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          type="text"
          :value="searchValue"
          @input="handleSearchInput"
          :placeholder="searchPlaceholder"
          class="w-full pl-9 pr-4 py-2 text-xs rounded-xl bg-card-bg border border-card-border text-main-text placeholder:text-main-text/40 focus:outline-none focus:border-nav-accent focus:ring-1 focus:ring-nav-accent shadow-2xs transition"
        />
      </div>
      <slot name="toolbar" />
    </div>

    <!-- Table Container -->
    <div class="bg-card-bg border border-card-border rounded-xl shadow-2xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="border-b border-card-border bg-slate-50/60 dark:bg-slate-800/40 select-none">
              <th
                v-for="col in columns"
                :key="col.key"
                class="px-4 py-3 font-bold text-main-text/60 tracking-wider uppercase text-[10px]"
                :class="col.align === 'center' ? 'text-center' : (col.align === 'right' ? 'text-right' : 'text-left')"
                :style="col.width ? { width: col.width } : {}"
              >
                {{ col.label }}
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-card-border/60">
            <!-- Loading Skeletons -->
            <tr v-if="loading" v-for="i in 5" :key="`skel-${i}`" class="animate-pulse">
              <td :colspan="columns.length" class="px-4 py-3">
                <div class="h-4 bg-slate-200 dark:bg-slate-700/60 rounded w-full"></div>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-else-if="!items.length">
              <td :colspan="columns.length" class="px-4 py-12 text-center text-main-text/40">
                <p class="text-xs font-semibold">{{ emptyTitle }}</p>
              </td>
            </tr>

            <!-- Data Rows -->
            <tr
              v-else
              v-for="(item, idx) in items"
              :key="item.id ?? idx"
              class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
            >
              <td
                v-for="col in columns"
                :key="col.key"
                class="px-4 py-3 text-main-text"
                :class="col.align === 'center' ? 'text-center' : (col.align === 'right' ? 'text-right' : 'text-left')"
              >
                <slot :name="col.key" :item="item" :value="item[col.key]">
                  {{ item[col.key] ?? '—' }}
                </slot>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="total > perPage"
        class="px-4 py-3 border-t border-card-border flex items-center justify-between text-xs text-main-text/60 select-none bg-slate-50/40 dark:bg-slate-800/20"
      >
        <span>
          Showing {{ ((currentPage - 1) * perPage) + 1 }} to {{ Math.min(currentPage * perPage, total) }} of {{ total }} records
        </span>
        <div class="flex items-center gap-1.5">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="emit('page-change', currentPage - 1)"
            class="p-1.5 rounded-lg border border-card-border hover:bg-card-hover disabled:opacity-40 disabled:pointer-events-none cursor-pointer"
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
            class="p-1.5 rounded-lg border border-card-border hover:bg-card-hover disabled:opacity-40 disabled:pointer-events-none cursor-pointer"
          >
            <ChevronRight class="w-4 h-4 stroke-[2.2]" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
