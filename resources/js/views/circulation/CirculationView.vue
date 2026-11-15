<template>
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div>
                <h1 class="text-xl font-bold" style="color: var(--text-primary);">Circulation & Loans</h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Issue and return library books. <strong>Only on-duty librarians may issue books.</strong>
                </p>
            </div>
            <button
                @click="openIssueModal"
                :disabled="!canIssue"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :style="canIssue ? 'background: var(--lib-primary-light);' : 'background: #94a3b8;'"
                :title="!canIssue ? dutyBlockMsg : 'Issue a book to a student'">
                <BookPlus :size="15" />
                Issue Book
                <span v-if="!canIssue" class="text-xs opacity-80">(Off Turn)</span>
            </button>
        </div>

        <!-- Duty Gate Warning Banner -->
        <div v-if="!authStore.isAdmin && !authStore.canCirculateBooks"
            class="rounded-xl border p-4 flex items-start gap-3"
            style="background: #fff7ed; border-color: #fed7aa;">
            <Clock :size="18" class="text-orange-600 shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-semibold text-orange-800">Book Issuance Restricted — Not On Duty Today</p>
                <p class="text-xs text-orange-700 mt-0.5">
                    {{ authStore.user?.duty_status?.circulation_reason }}
                </p>
                <p class="text-xs text-orange-600 mt-1" v-if="authStore.user?.duty_status?.next_turn">
                    Your next duty turn: <strong>{{ authStore.user.duty_status.next_turn }}</strong>
                </p>
                <p class="text-xs text-orange-600 mt-1" v-else>
                    No upcoming shifts scheduled. Contact your head librarian.
                </p>
            </div>
        </div>

        <!-- Status Filter Tabs -->
        <div class="flex gap-2 flex-wrap">
            <button v-for="tab in tabs" :key="tab.value"
                @click="statusFilter = tab.value"
                class="px-3 py-1.5 text-xs rounded-full font-medium transition-colors"
                :style="statusFilter === tab.value
                    ? 'background: var(--lib-primary-light); color: white;'
                    : 'background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border);'">
                {{ tab.label }}
            </button>
        </div>

        <!-- Loans Table -->
        <div class="rounded-xl border overflow-hidden" style="background: var(--bg-card); border-color: var(--border);">
            <div v-if="loading" class="p-10 text-center">
                <Loader2 :size="24" class="animate-spin mx-auto" style="color: var(--lib-primary-light);" />
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr style="background: var(--bg-page);">
                        <th v-for="col in ['Book', 'Borrower', 'Issued', 'Due', 'Status', 'Actions']" :key="col"
                            class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide"
                            style="color: var(--text-muted);">{{ col }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loans.length === 0">
                        <td colspan="6" class="py-12 text-center text-sm" style="color: var(--text-muted);">
                            No loans found.
                        </td>
                    </tr>
                    <tr v-for="loan in loans" :key="loan.id"
                        class="border-t hover:bg-slate-50 transition-colors"
                        style="border-color: var(--border);">
                        <td class="px-4 py-3 max-w-[200px]">
                            <p class="font-medium text-xs line-clamp-1" style="color: var(--text-primary);">
                                {{ loan.book?.title }}
                            </p>
                            <p class="text-xs mt-0.5" style="color: var(--text-muted);">{{ loan.book?.author }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs" style="color: var(--text-secondary);">
                            <p class="font-medium" style="color: var(--text-primary);">{{ loan.member?.name }}</p>
                            <p style="color: var(--text-muted);">@{{ loan.member?.username }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono" style="color: var(--text-secondary);">
                            {{ formatDate(loan.issued_date) }}
                        </td>
                        <td class="px-4 py-3 text-xs font-mono" :style="isOverdue(loan) ? 'color: var(--lib-danger);' : 'color: var(--text-secondary);'">
                            {{ formatDate(loan.due_date) }}
                            <span v-if="isOverdue(loan)" class="ml-1 text-xs font-semibold">(Overdue)</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs rounded-full font-medium" :style="loanStatusStyle(loan.status)">
                                {{ loan.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <button v-if="loan.status === 'borrowed' && canIssue"
                                @click="handleReturn(loan.id)"
                                class="flex items-center gap-1 px-2 py-1 rounded text-xs font-medium text-white transition-colors"
                                style="background: var(--lib-success);">
                                <CornerUpLeft :size="12" /> Return
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Issue Book Modal -->
        <AppModal v-model="showIssue" title="Issue Book to Member" size="md">
            <!-- Duty gate reminder inside modal -->
            <div v-if="!canIssue" class="mb-4 p-3 rounded-lg text-xs" style="background: #fef9c3; color: #92400e;">
                <strong>Warning:</strong> You are not on circulation duty today. This action will be blocked by the server.
            </div>
            <form @submit.prevent="handleIssue" class="space-y-4">
                <div>
                    <label class="label">Book ID</label>
                    <input v-model.number="issueForm.book_id" type="number" placeholder="Enter book ID" required class="inp" />
                </div>
                <div>
                    <label class="label">Member (Student) User ID</label>
                    <input v-model.number="issueForm.member_id" type="number" placeholder="Enter member user ID" required class="inp" />
                </div>
                <div>
                    <label class="label">Loan Duration (Days)</label>
                    <input v-model.number="issueForm.due_days" type="number" min="1" max="60" class="inp" />
                </div>
                <div>
                    <label class="label">Notes (optional)</label>
                    <textarea v-model="issueForm.notes" rows="2" class="inp resize-none" placeholder="Loan notes…"></textarea>
                </div>
            </form>
            <template #footer>
                <button @click="showIssue = false" class="px-4 py-2 text-sm rounded-lg border" style="border-color: var(--border);">Cancel</button>
                <button @click="handleIssue" :disabled="submitting"
                    class="px-4 py-2 text-sm rounded-lg text-white font-medium disabled:opacity-50"
                    style="background: var(--lib-primary-light);">
                    {{ submitting ? 'Processing…' : 'Issue Book' }}
                </button>
            </template>
        </AppModal>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useToastStore } from '@/stores/toastStore';
import { circulationApi } from '@/api';
import AppModal from '@/components/ui/AppModal.vue';
import { BookPlus, Clock, Loader2, CornerUpLeft } from '@lucide/vue';
import type { BookLoan } from '@/types';

const authStore = useAuthStore();
const toastStore = useToastStore();

const loading = ref(true);
const submitting = ref(false);
const loans = ref<BookLoan[]>([]);
const statusFilter = ref('');
const showIssue = ref(false);

const issueForm = reactive({ book_id: 0, member_id: 0, due_days: 14, notes: '' });

const canIssue = computed(() => authStore.isAdmin || authStore.canCirculateBooks);
const dutyBlockMsg = computed(() => authStore.user?.duty_status?.circulation_reason ?? 'Not on duty today.');

const tabs = [
    { label: 'All', value: '' },
    { label: 'Borrowed', value: 'borrowed' },
    { label: 'Overdue', value: 'overdue' },
    { label: 'Returned', value: 'returned' },
];

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function isOverdue(loan: BookLoan) {
    return loan.status === 'borrowed' && new Date(loan.due_date) < new Date();
}

function loanStatusStyle(status: string) {
    return {
        borrowed: 'background: #dbeafe; color: #1e40af;',
        returned: 'background: #d1fae5; color: #065f46;',
        overdue: 'background: #fee2e2; color: #991b1b;',
    }[status] ?? 'background: var(--bg-page); color: var(--text-muted);';
}

function openIssueModal() {
    Object.assign(issueForm, { book_id: 0, member_id: 0, due_days: 14, notes: '' });
    showIssue.value = true;
}

async function fetchLoans() {
    loading.value = true;
    try {
        const { data } = await circulationApi.list({ status: statusFilter.value || undefined });
        loans.value = data.data;
    } catch {
        toastStore.error('Failed to load loans.');
    } finally {
        loading.value = false;
    }
}

async function handleIssue() {
    submitting.value = true;
    try {
        await circulationApi.issue({ ...issueForm });
        toastStore.success('Book issued successfully.');
        showIssue.value = false;
        await fetchLoans();
        await authStore.fetchMe();
    } catch (err: any) {
        const data = err.response?.data;
        if (data?.code === 'OUTSIDE_DUTY_TURN') {
            toastStore.error(`Blocked: ${data.message} Next turn: ${data.duty_gate?.next_turn ?? 'None scheduled'}`);
        } else {
            toastStore.error(data?.message || 'Failed to issue book.');
        }
    } finally {
        submitting.value = false;
    }
}

async function handleReturn(loanId: number) {
    try {
        await circulationApi.return(loanId);
        toastStore.success('Book returned successfully.');
        await fetchLoans();
    } catch (err: any) {
        toastStore.error(err.response?.data?.message || 'Return failed.');
    }
}

watch(statusFilter, fetchLoans);

onMounted(fetchLoans);
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
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
