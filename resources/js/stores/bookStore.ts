import { defineStore } from 'pinia';
import { ref } from 'vue';
import { booksApi } from '@/api';
import { useToastStore } from './toastStore';
import type { Book, Pagination } from '@/types';

export const useBookStore = defineStore('books', () => {
    const books = ref<Book[]>([]);
    const categories = ref<string[]>([]);
    const pagination = ref<Pagination>({ current_page: 1, last_page: 1, total: 0 });
    const loading = ref(false);

    const toast = useToastStore();

    async function fetchBooks(params?: { search?: string; category?: string; available?: boolean; page?: number }) {
        loading.value = true;
        try {
            const { data } = await booksApi.list(params);
            books.value = data.data;
            categories.value = data.categories ?? [];
            pagination.value = data.pagination;
        } catch {
            toast.error('Failed to load catalog.');
        } finally {
            loading.value = false;
        }
    }

    async function createBook(formData: any) {
        loading.value = true;
        try {
            await booksApi.create(formData);
            toast.success('Book added to catalog.');
            await fetchBooks();
            return true;
        } catch (err: any) {
            const errors = err.response?.data?.errors;
            toast.error(errors ? (Object.values(errors).flat()[0] as string) : (err.response?.data?.message || 'Failed to add book.'));
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function updateBook(id: number, formData: Partial<Book>) {
        loading.value = true;
        try {
            await booksApi.update(id, formData);
            toast.success('Book updated.');
            await fetchBooks();
            return true;
        } catch (err: any) {
            const errors = err.response?.data?.errors;
            toast.error(errors ? (Object.values(errors).flat()[0] as string) : (err.response?.data?.message || 'Failed to update book.'));
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function deleteBook(id: number) {
        try {
            await booksApi.destroy(id);
            toast.success('Book removed from catalog.');
            await fetchBooks();
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete book.');
        }
    }

    return { books, categories, pagination, loading, fetchBooks, createBook, updateBook, deleteBook };
});
