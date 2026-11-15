<template>
  <div class="space-y-5 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <BookOpen class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('books.title', 'University Library Catalog') }}</span>
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          Browse, search, and manage cataloged books across academic departments
        </p>
      </div>

      <Button
        v-if="authStore.isAdmin || authStore.hasRole('Head Librarian')"
        variant="primary"
        size="sm"
        :icon="Plus"
        @click="openCreateModal"
      >
        {{ langStore.t('books.add', 'Add Book') }}
      </Button>
    </div>

    <!-- DataTable matching Gibi-Gubae -->
    <DataTable
      :columns="columns"
      :items="bookStore.books"
      :loading="bookStore.loading"
      show-search
      v-model:search-value="searchQuery"
      :search-placeholder="langStore.t('common.search', 'Search by title, author, or ISBN...')"
      :total="bookStore.pagination.total"
      :per-page="15"
      :current-page="bookStore.pagination.current_page"
      @page-change="handlePageChange"
    >
      <!-- Title & Author Column -->
      <template #title="{ item }">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-lg bg-nav-accent/10 text-nav-accent border border-nav-accent/25 flex items-center justify-center font-bold text-xs shrink-0">
            📖
          </div>
          <div class="min-w-0">
            <p class="font-bold text-main-text truncate">{{ item.title }}</p>
            <p class="text-[11px] text-main-text/50 truncate">{{ item.author }}</p>
          </div>
        </div>
      </template>

      <!-- ISBN Column -->
      <template #isbn="{ item }">
        <code class="text-xs font-mono text-main-text/70 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded border border-card-border">
          {{ item.isbn }}
        </code>
      </template>

      <!-- Category Column -->
      <template #category="{ item }">
        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/25">
          {{ item.category }}
        </span>
      </template>

      <!-- Location Column -->
      <template #shelf_location="{ item }">
        <span class="text-xs text-main-text/70 font-mono">{{ item.shelf_location || 'General Stacks' }}</span>
      </template>

      <!-- Availability Column -->
      <template #availability="{ item }">
        <div class="flex items-center gap-2">
          <span
            class="font-bold text-xs"
            :class="item.available_copies > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
          >
            {{ item.available_copies }}
          </span>
          <span class="text-[11px] text-main-text/40">/ {{ item.total_copies }}</span>
        </div>
      </template>

      <!-- Actions Column -->
      <template #actions="{ item }">
        <div v-if="authStore.isAdmin || authStore.hasRole('Head Librarian')" class="flex items-center justify-end gap-1">
          <button
            type="button"
            class="table-action-btn text-brand-blue hover:bg-brand-blue/10 transition cursor-pointer"
            :title="langStore.t('common.edit', 'Edit Book')"
            @click="openEditModal(item)"
          >
            <Pencil class="w-4 h-4 stroke-[2.2]" />
          </button>
          <button
            v-if="authStore.isAdmin"
            type="button"
            class="table-action-btn text-rose-600 hover:bg-rose-500/10 transition cursor-pointer"
            :title="langStore.t('common.delete', 'Delete Book')"
            @click="confirmDelete(item)"
          >
            <Trash2 class="w-4 h-4 stroke-[2.2]" />
          </button>
        </div>
        <span v-else class="text-main-text/30">—</span>
      </template>
    </DataTable>

    <!-- Create / Edit Modal -->
    <Modal
      :show="showModal"
      :title="editingBook ? langStore.t('common.edit', 'Edit Book') : langStore.t('books.add', 'Add Book')"
      :description="langStore.t('books.title', 'Fill catalog details and copy counts')"
      :icon="BookOpen"
      size="md"
      @close="showModal = false"
    >
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.t('books.book_title', 'Book Title') }} <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.title"
            type="text"
            required
            placeholder="Introduction to Algorithms"
            class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('books.author', 'Author(s)') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.author"
              type="text"
              required
              placeholder="Thomas H. Cormen"
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('books.isbn', 'ISBN') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.isbn"
              type="text"
              required
              placeholder="978-0262046305"
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('books.category', 'Category') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.category"
              type="text"
              required
              placeholder="Computer Science"
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('books.location', 'Shelf Location') }}
            </label>
            <input
              v-model="form.shelf_location"
              type="text"
              placeholder="Floor 2 - Stacks CS-101"
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              Total Copies <span class="text-rose-500">*</span>
            </label>
            <input
              v-model.number="form.total_copies"
              type="number"
              min="1"
              required
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>

          <div v-if="editingBook">
            <label class="block text-xs font-semibold text-main-text mb-1">
              Available Copies <span class="text-rose-500">*</span>
            </label>
            <input
              v-model.number="form.available_copies"
              type="number"
              min="0"
              required
              class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showModal = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button variant="primary" size="sm" :loading="bookStore.loading" @click="handleSubmit">
          {{ editingBook ? langStore.t('common.update', 'Update Book') : langStore.t('common.save', 'Save Book') }}
        </Button>
      </template>
    </Modal>

    <!-- Delete Modal -->
    <Modal
      :show="showDelete"
      :title="langStore.t('common.delete', 'Delete Book')"
      size="sm"
      @close="showDelete = false"
    >
      <div class="text-center py-2 space-y-3">
        <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center bg-rose-500/10 text-rose-600 border border-rose-500/20">
          <Trash2 class="w-6 h-6 stroke-[2.2]" />
        </div>
        <p class="text-xs text-main-text">
          Remove <strong>{{ deletingBook?.title }}</strong> from library catalog?
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
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useBookStore } from '@/stores/bookStore';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import Button from '@/components/ui/Button.vue';
import DataTable, { ColumnDef } from '@/components/ui/DataTable.vue';
import Modal from '@/components/ui/Modal.vue';
import { BookOpen, Plus, Pencil, Trash2 } from 'lucide-vue-next';
import type { Book } from '@/types';

const bookStore = useBookStore();
const authStore = useAuthStore();
const langStore = useLanguageStore();

const searchQuery = ref('');
const showModal = ref(false);
const showDelete = ref(false);
const editingBook = ref<Book | null>(null);
const deletingBook = ref<Book | null>(null);

const columns = computed<ColumnDef[]>(() => [
  { key: 'title', label: langStore.t('books.book_title', 'Book & Author') },
  { key: 'isbn', label: langStore.t('books.isbn', 'ISBN') },
  { key: 'category', label: langStore.t('books.category', 'Category') },
  { key: 'shelf_location', label: langStore.t('books.location', 'Location') },
  { key: 'availability', label: langStore.t('books.copies', 'Availability') },
  { key: 'actions', label: langStore.t('common.actions', 'Actions'), align: 'right' },
]);

const form = reactive({
  title: '',
  author: '',
  isbn: '',
  category: '',
  shelf_location: '',
  total_copies: 1,
  available_copies: 1,
});

const openCreateModal = () => {
  editingBook.value = null;
  Object.assign(form, {
    title: '',
    author: '',
    isbn: '',
    category: '',
    shelf_location: '',
    total_copies: 1,
    available_copies: 1,
  });
  showModal.value = true;
};

const openEditModal = (book: Book) => {
  editingBook.value = book;
  Object.assign(form, { ...book });
  showModal.value = true;
};

const confirmDelete = (book: Book) => {
  deletingBook.value = book;
  showDelete.value = true;
};

const handleSubmit = async () => {
  const success = editingBook.value
    ? await bookStore.updateBook(editingBook.value.id, { ...form })
    : await bookStore.createBook({ ...form });

  if (success) {
    showModal.value = false;
  }
};

const handleDelete = async () => {
  if (deletingBook.value) {
    await bookStore.deleteBook(deletingBook.value.id);
    showDelete.value = false;
  }
};

const handlePageChange = (page: number) => {
  bookStore.fetchBooks({ search: searchQuery.value, page });
};

let searchTimer: ReturnType<typeof setTimeout>;
watch(searchQuery, (query) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    bookStore.fetchBooks({ search: query });
  }, 350);
});

onMounted(() => bookStore.fetchBooks());
</script>
