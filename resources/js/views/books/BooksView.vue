<template>
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div>
                <h1 class="text-xl font-bold" style="color: var(--text-primary);">Book Catalog</h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    {{ bookStore.pagination.total }} books in the university library.
                </p>
            </div>
            <button v-if="authStore.isAdmin || authStore.hasRole('Head Librarian')"
                @click="openCreate"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white"
                style="background: var(--lib-primary-light);">
                <Plus :size="15" /> Add Book
            </button>
        </div>

        <!-- Search + Category Filter -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                <input v-model="search" type="search" placeholder="Search by title, author or ISBN…"
                    class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border"
                    style="background: var(--bg-card); border-color: var(--border); color: var(--text-primary);" />
            </div>
            <select v-model="categoryFilter"
                class="px-3 py-2 text-sm rounded-lg border"
                style="background: var(--bg-card); border-color: var(--border); color: var(--text-primary);">
                <option value="">All Categories</option>
                <option v-for="c in bookStore.categories" :key="c" :value="c">{{ c }}</option>
            </select>
            <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-secondary);">
                <input v-model="availableOnly" type="checkbox" class="rounded" />
                Available only
            </label>
        </div>

        <!-- Books Grid -->
        <div v-if="bookStore.loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-for="i in 8" :key="i" class="rounded-xl h-44 animate-pulse" style="background: var(--border);"></div>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-for="book in bookStore.books" :key="book.id"
                class="rounded-xl border p-4 flex flex-col gap-3 transition-shadow hover:shadow-md"
                style="background: var(--bg-card); border-color: var(--border);">
                <!-- Book spine visual -->
                <div class="flex items-start gap-3">
                    <div class="w-10 h-14 rounded flex items-center justify-center shrink-0"
                        :style="getCategoryStyle(book.category)">
                        <BookOpen :size="18" class="text-white" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold leading-snug line-clamp-2" style="color: var(--text-primary);">
                            {{ book.title }}
                        </p>
                        <p class="text-xs mt-0.5 truncate" style="color: var(--text-secondary);">{{ book.author }}</p>
                    </div>
                </div>

                <!-- Metadata -->
                <div class="space-y-1 text-xs" style="color: var(--text-muted);">
                    <div class="flex items-center gap-1.5">
                        <Tag :size="11" />
                        <span>{{ book.category }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <MapPin :size="11" />
                        <span class="truncate">{{ book.shelf_location ?? 'Not assigned' }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <Hash :size="11" />
                        <code class="text-xs">{{ book.isbn }}</code>
                    </div>
                </div>

                <!-- Availability -->
                <div class="flex items-center justify-between mt-auto">
                    <div class="text-xs" style="color: var(--text-muted);">
                        <span class="font-semibold" :style="book.available_copies > 0 ? 'color: var(--lib-success);' : 'color: var(--lib-danger);'">
                            {{ book.available_copies }}
                        </span>
                        / {{ book.total_copies }} available
                    </div>
                    <div class="flex items-center gap-1">
                        <button v-if="authStore.isAdmin || authStore.hasRole('Head Librarian')"
                            @click="openEdit(book)" class="p-1.5 rounded hover:bg-blue-50 transition-colors">
                            <Pencil :size="13" style="color: var(--lib-primary-light);" />
                        </button>
                        <button v-if="authStore.isAdmin"
                            @click="confirmDelete(book)" class="p-1.5 rounded hover:bg-red-50 transition-colors">
                            <Trash2 :size="13" style="color: var(--lib-danger);" />
                        </button>
                    </div>
                </div>

                <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--border);">
                    <div class="h-full rounded-full transition-all"
                        :style="`width: ${availabilityPct(book)}%; background: ${book.available_copies > 2 ? 'var(--lib-success)' : book.available_copies > 0 ? '#d97706' : 'var(--lib-danger)'};`"></div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <AppModal v-model="showModal" :title="editingBook ? 'Edit Book' : 'Add Book to Catalog'" size="md">
            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div>
                    <label class="label">Title</label>
                    <input v-model="form.title" type="text" placeholder="Introduction to Algorithms" required class="inp" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Author(s)</label>
                        <input v-model="form.author" type="text" required class="inp" />
                    </div>
                    <div>
                        <label class="label">ISBN</label>
                        <input v-model="form.isbn" type="text" required class="inp" />
                    </div>
                    <div>
                        <label class="label">Category</label>
                        <input v-model="form.category" type="text" list="categories-list" required class="inp" />
                        <datalist id="categories-list">
                            <option v-for="c in bookStore.categories" :key="c" :value="c" />
                        </datalist>
                    </div>
                    <div>
                        <label class="label">Shelf Location</label>
                        <input v-model="form.shelf_location" type="text" placeholder="Floor 2 - Stacks CS-101" class="inp" />
                    </div>
                    <div>
                        <label class="label">Total Copies</label>
                        <input v-model.number="form.total_copies" type="number" min="1" required class="inp" />
                    </div>
                    <div v-if="editingBook">
                        <label class="label">Available Copies</label>
                        <input v-model.number="form.available_copies" type="number" min="0" required class="inp" />
                    </div>
                </div>
            </form>
            <template #footer>
                <button @click="showModal = false" class="px-4 py-2 text-sm rounded-lg border"
                    style="border-color: var(--border);">Cancel</button>
                <button @click="handleSubmit" :disabled="bookStore.loading"
                    class="px-4 py-2 text-sm rounded-lg text-white font-medium disabled:opacity-50"
                    style="background: var(--lib-primary-light);">
                    {{ bookStore.loading ? 'Saving…' : editingBook ? 'Update' : 'Add Book' }}
                </button>
            </template>
        </AppModal>

        <!-- Delete Modal -->
        <AppModal v-model="showDelete" title="Remove Book" size="sm">
            <div class="text-center space-y-3 py-2">
                <Trash2 :size="28" class="mx-auto" style="color: var(--lib-danger);" />
                <p class="text-sm" style="color: var(--text-secondary);">
                    Remove <strong>{{ deletingBook?.title }}</strong> from catalog?
                </p>
            </div>
            <template #footer>
                <button @click="showDelete = false" class="px-4 py-2 text-sm rounded-lg border" style="border-color: var(--border);">Cancel</button>
                <button @click="handleDelete" class="px-4 py-2 text-sm rounded-lg text-white" style="background: var(--lib-danger);">Remove</button>
            </template>
        </AppModal>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue';
import { useBookStore } from '@/stores/bookStore';
import { useAuthStore } from '@/stores/authStore';
import AppModal from '@/components/ui/AppModal.vue';
import { Plus, Search, BookOpen, Pencil, Trash2, Tag, MapPin, Hash } from '@lucide/vue';
import type { Book } from '@/types';

const bookStore = useBookStore();
const authStore = useAuthStore();
const search = ref('');
const categoryFilter = ref('');
const availableOnly = ref(false);
const showModal = ref(false);
const showDelete = ref(false);
const editingBook = ref<Book | null>(null);
const deletingBook = ref<Book | null>(null);

const form = reactive({
    title: '', author: '', isbn: '', category: '', shelf_location: '', total_copies: 1, available_copies: 1,
});

const categoryColors: Record<string, string> = {
    'Computer Science': '#1e3a8a',
    'Medicine': '#065f46',
    'Law': '#5b21b6',
    'Engineering': '#92400e',
};

function getCategoryStyle(cat: string) {
    const color = categoryColors[cat] ?? '#374151';
    return `background: ${color};`;
}

function availabilityPct(book: Book) {
    if (book.total_copies === 0) return 0;
    return Math.round((book.available_copies / book.total_copies) * 100);
}

function openCreate() {
    editingBook.value = null;
    Object.assign(form, { title: '', author: '', isbn: '', category: '', shelf_location: '', total_copies: 1, available_copies: 1 });
    showModal.value = true;
}

function openEdit(b: Book) {
    editingBook.value = b;
    Object.assign(form, { ...b });
    showModal.value = true;
}

function confirmDelete(b: Book) {
    deletingBook.value = b;
    showDelete.value = true;
}

async function handleSubmit() {
    const success = editingBook.value
        ? await bookStore.updateBook(editingBook.value.id, { ...form })
        : await bookStore.createBook({ ...form });
    if (success) showModal.value = false;
}

async function handleDelete() {
    if (deletingBook.value) {
        await bookStore.deleteBook(deletingBook.value.id);
        showDelete.value = false;
    }
}

let searchTimer: ReturnType<typeof setTimeout>;
watch([search, categoryFilter, availableOnly], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        bookStore.fetchBooks({ search: search.value, category: categoryFilter.value, available: availableOnly.value });
    }, 400);
});

onMounted(() => bookStore.fetchBooks());
</script>

<style scoped>
.label {
    display: block;
    font-size: 0.75rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
    color: var(--text-secondary);
}
.inp {
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border);
    background: var(--bg-input);
    color: var(--text-primary);
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
