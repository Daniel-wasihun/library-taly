<template>
  <div class="space-y-5 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <BookMarked class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('circulation.title', 'Circulation & Book Loans') }}</span>
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          Issue and return library books. <strong>Only on-duty librarians may issue books.</strong>
        </p>
      </div>

      <Button
        variant="primary"
        size="sm"
        :icon="BookPlus"
        :disabled="!canIssue"
        @click="openIssueModal"
        :title="!canIssue ? dutyBlockMsg : 'Issue a book'"
      >
        <span>{{ langStore.t('circulation.issue', 'Issue Book') }}</span>
        <span v-if="!canIssue" class="text-[10px] opacity-75 font-normal ml-1">({{ langStore.t('duty.off_duty', 'Off Turn') }})</span>
      </Button>
    </div>

    <!-- Duty Gate Warning Banner -->
    <div
      v-if="!authStore.isAdmin && !authStore.canCirculateBooks"
      class="rounded-xl border p-4 flex items-start gap-3 bg-amber-500/10 dark:bg-amber-950/40 border-amber-500/30 text-amber-800 dark:text-amber-200"
    >
      <Clock class="w-5 h-5 text-amber-600 dark:text-brand-yellow shrink-0 mt-0.5" />
      <div>
        <p class="text-sm font-bold">{{ langStore.t('duty.restricted_notice', 'Book Issuance Restricted — Not On Duty Today') }}</p>
        <p class="text-xs mt-0.5 text-main-text/70">
          {{ authStore.user?.duty_status?.circulation_reason }}
        </p>
        <p class="text-xs mt-1 font-semibold text-amber-700 dark:text-brand-yellow" v-if="authStore.user?.duty_status?.next_turn">
          {{ langStore.t('duty.next_turn', 'Your next turn') }}: <span class="font-mono underline">{{ authStore.user.duty_status.next_turn }}</span>
        </p>
      </div>
    </div>

    <!-- Status Tabs -->
    <div class="flex gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        @click="statusFilter = tab.value"
        class="px-3 py-1.5 text-xs rounded-xl font-semibold transition cursor-pointer select-none"
        :class="statusFilter === tab.value
          ? 'bg-nav-accent text-white dark:text-slate-900 shadow-2xs'
          : 'bg-card-bg border border-card-border text-main-text/70 hover:bg-main-text/5'"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- DataTable -->
    <DataTable
      :columns="columns"
      :items="loans"
      :loading="loading"
    >
      <template #book="{ item }">
        <div class="min-w-0">
          <p class="font-bold text-main-text truncate">{{ item.book?.title }}</p>
          <p class="text-[11px] text-main-text/50 truncate">{{ item.book?.author }} · ISBN: {{ item.book?.isbn }}</p>
        </div>
      </template>

      <template #member="{ item }">
        <div class="min-w-0">
          <p class="font-bold text-main-text truncate">{{ item.member?.name }}</p>
          <p class="text-[11px] text-main-text/50 font-mono truncate">@{{ item.member?.username }}</p>
        </div>
      </template>

      <template #issued_date="{ item }">
        <span class="text-xs font-mono text-main-text/70">{{ formatDate(item.issued_date) }}</span>
      </template>

      <template #due_date="{ item }">
        <span
          class="text-xs font-mono font-semibold"
          :class="isOverdue(item) ? 'text-rose-600 dark:text-rose-400' : 'text-main-text/70'"
        >
          {{ formatDate(item.due_date) }}
          <span v-if="isOverdue(item)" class="ml-1 text-[10px] text-rose-500 font-bold uppercase">(Overdue)</span>
        </span>
      </template>

      <template #status="{ item }">
        <StatusBadge :status="item.status" />
      </template>

      <template #actions="{ item }">
        <div class="flex items-center justify-end">
          <Button
            v-if="item.status === 'borrowed' && canIssue"
            variant="soft-success"
            size="sm"
            @click="handleReturn(item.id)"
          >
            {{ langStore.t('circulation.return', 'Return') }}
          </Button>
          <span v-else class="text-main-text/30">—</span>
        </div>
      </template>
    </DataTable>

    <!-- Issue Book Modal -->
    <Modal
      :show="showIssue"
      :title="langStore.t('circulation.issue', 'Issue Book to Student Member')"
      description="Record a new book loan. Verified against duty schedule turn."
      :icon="BookPlus"
      size="md"
      @close="showIssue = false"
    >
      <div v-if="!canIssue" class="mb-4 p-3 rounded-lg text-xs bg-amber-500/10 text-amber-700 dark:text-brand-yellow border border-amber-500/25">
        <strong>Warning:</strong> You are not scheduled on circulation duty today. Attempting to issue will be rejected by the duty gate.
      </div>

      <form @submit.prevent="handleIssue" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Book ID <span class="text-rose-500">*</span>
          </label>
          <input
            v-model.number="issueForm.book_id"
            type="number"
            min="1"
            required
            placeholder="Book ID"
            class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Borrower User ID (Student) <span class="text-rose-500">*</span>
          </label>
          <input
            v-model.number="issueForm.member_id"
            type="number"
            min="1"
            required
            placeholder="Student Member ID"
            class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Loan Period (Days)
          </label>
          <input
            v-model.number="issueForm.due_days"
            type="number"
            min="1"
            max="60"
            class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Notes (optional)
          </label>
          <textarea
            v-model="issueForm.notes"
            rows="2"
            placeholder="Condition notes, library pass, etc."
            class="w-full px-3 py-2 text-xs rounded-xl bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs resize-none"
          ></textarea>
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showIssue = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button
          variant="primary"
          size="sm"
          :loading="submitting"
          @click="handleIssue"
        >
          {{ langStore.t('circulation.issue', 'Issue Book') }}
        </Button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import { useToastStore } from '@/stores/toastStore';
import { circulationApi } from '@/api';
import Button from '@/components/ui/Button.vue';
import DataTable, { ColumnDef } from '@/components/ui/DataTable.vue';
import Modal from '@/components/ui/Modal.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { BookMarked, BookPlus, Clock } from 'lucide-vue-next';
import type { BookLoan } from '@/types';

const authStore = useAuthStore();
const langStore = useLanguageStore();
const toast = useToastStore();

const loading = ref(true);
const submitting = ref(false);
const loans = ref<BookLoan[]>([]);
const statusFilter = ref('');
const showIssue = ref(false);

const canIssue = computed(() => authStore.isAdmin || authStore.canCirculateBooks);
const dutyBlockMsg = computed(() => authStore.user?.duty_status?.circulation_reason || 'Not on duty today.');

const issueForm = reactive({
  book_id: 1,
  member_id: 4,
  due_days: 14,
  notes: '',
});

const columns = computed<ColumnDef[]>(() => [
  { key: 'book', label: langStore.t('books.book_title', 'Book') },
  { key: 'member', label: langStore.t('circulation.borrower', 'Borrower (Student)') },
  { key: 'issued_date', label: langStore.t('circulation.issued_date', 'Issued Date') },
  { key: 'due_date', label: langStore.t('circulation.due_date', 'Due Date') },
  { key: 'status', label: langStore.t('common.status', 'Status') },
  { key: 'actions', label: langStore.t('common.actions', 'Actions'), align: 'right' },
]);

const tabs = computed(() => [
  { label: langStore.t('common.all', 'All'), value: '' },
  { label: langStore.t('circulation.status_borrowed', 'Borrowed'), value: 'borrowed' },
  { label: langStore.t('circulation.status_overdue', 'Overdue'), value: 'overdue' },
  { label: langStore.t('circulation.status_returned', 'Returned'), value: 'returned' },
]);

const formatDate = (d: string) => {
  if (!d) return '—';
  return new Date(d).toLocaleDateString(langStore.currentLanguage === 'am' ? 'am-ET' : 'en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const isOverdue = (loan: BookLoan) => {
  return loan.status === 'borrowed' && new Date(loan.due_date) < new Date();
};

const openIssueModal = () => {
  issueForm.book_id = 1;
  issueForm.member_id = 4;
  issueForm.due_days = 14;
  issueForm.notes = '';
  showIssue.value = true;
};

const fetchLoans = async () => {
  loading.value = true;
  try {
    const { data } = await circulationApi.list({ status: statusFilter.value || undefined });
    loans.value = data.data;
  } catch {
    toast.error('Failed to load loans.');
  } finally {
    loading.value = false;
  }
};

const handleIssue = async () => {
  submitting.value = true;
  try {
    await circulationApi.issue({ ...issueForm });
    toast.success('Book issued successfully.');
    showIssue.value = false;
    await fetchLoans();
    await authStore.fetchMe();
  } catch (err: any) {
    const data = err.response?.data;
    if (data?.code === 'OUTSIDE_DUTY_TURN') {
      toast.error(`Blocked: ${data.message} Next turn: ${data.duty_gate?.next_turn || 'None scheduled'}`);
    } else {
      toast.error(data?.message || 'Failed to issue book.');
    }
  } finally {
    submitting.value = false;
  }
};

const handleReturn = async (id: number) => {
  try {
    await circulationApi.return(id);
    toast.success('Book returned successfully.');
    await fetchLoans();
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Return failed.');
  }
};

watch(statusFilter, fetchLoans);
onMounted(fetchLoans);
</script>
