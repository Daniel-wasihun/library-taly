<template>
  <transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="opacity-0 scale-95"
    enter-to-class="opacity-100 scale-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="opacity-100 scale-100"
    leave-to-class="opacity-0 scale-95"
  >
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 md:p-6 bg-black/75 backdrop-blur-sm"
      @click.self="handleClose"
    >
      <div class="w-full h-full max-w-5xl bg-card-bg border border-card-border rounded-xl shadow-2xl flex flex-col overflow-hidden">
        <!-- Preview Modal Top Bar -->
        <div class="px-3 py-2.5 sm:px-5 sm:py-3 border-b border-card-border bg-card-hover/20 flex flex-wrap items-center justify-between gap-2 shrink-0">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-md bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 flex items-center justify-center shrink-0">
              <FileText class="w-4 h-4" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h3 class="text-xs sm:text-sm font-bold text-main-text truncate">
                  {{ filename || 'WDU_Attendance_Report.pdf' }}
                </h3>
                <span class="hidden sm:inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-brand-blue/10 text-brand-blue dark:bg-brand-blue/20 dark:text-blue-200">
                  {{ langStore.isAmharic() ? 'ይፋዊ ፒዲኤፍ ቅድመ-እይታ' : 'PDF Document Preview' }}
                </span>
              </div>
              <p class="text-[10px] text-main-text/60 truncate">
                {{ langStore.isAmharic() ? 'በማንኛውም ማህደር ውስጥ ለማስቀመጥ "አስቀምጥ" የሚለውን ይጫኑ' : 'Save to any selected directory or print directly' }}
              </p>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center gap-1.5 sm:gap-2 shrink-0 ml-auto">
            <!-- Save File As... -->
            <button
              type="button"
              @click="handleSave"
              :disabled="saving"
              class="inline-flex items-center gap-1.5 px-2.5 py-1.5 sm:px-3 sm:py-1.5 rounded-md bg-brand-blue hover:bg-brand-blue/90 text-white font-bold text-xs shadow-sm transition active:scale-95 disabled:opacity-50 cursor-pointer"
              :title="langStore.isAmharic() ? 'ፋይሉን ወደ ፈለጉት ማህደር አስቀምጥ' : 'Save to your selected folder or Downloads'"
            >
              <Download class="w-3.5 h-3.5" />
              <span>{{ langStore.isAmharic() ? 'አስቀምጥ (Save)' : 'Save File' }}</span>
            </button>

            <!-- Direct Print -->
            <button
              type="button"
              @click="handlePrint"
              class="inline-flex items-center gap-1.5 px-2.5 py-1.5 sm:px-3 sm:py-1.5 rounded-md border border-card-border hover:bg-card-hover/40 text-main-text font-semibold text-xs transition active:scale-95 cursor-pointer"
              :title="langStore.isAmharic() ? 'በቀጥታ አትም' : 'Print PDF'"
            >
              <Printer class="w-3.5 h-3.5" />
              <span class="hidden sm:inline">{{ langStore.isAmharic() ? 'አትም' : 'Print' }}</span>
            </button>

            <!-- Open in New Window -->
            <button
              type="button"
              @click="handleOpenExternal"
              class="inline-flex items-center gap-1.5 px-2 sm:px-2.5 py-1.5 rounded-md border border-card-border hover:bg-card-hover/40 text-main-text/70 hover:text-main-text font-semibold text-xs transition active:scale-95 cursor-pointer"
              :title="langStore.isAmharic() ? 'በብሮውዘሩ ነባሪ መስኮት ክፈት' : 'Open in browser window'"
            >
              <ExternalLink class="w-3.5 h-3.5" />
            </button>

            <div class="h-4 w-px bg-card-border mx-0.5"></div>

            <!-- Close Modal -->
            <button
              type="button"
              @click="handleClose"
              class="p-1.5 rounded-md text-main-text/50 hover:text-main-text hover:bg-card-hover/50 transition cursor-pointer"
              :title="langStore.isAmharic() ? 'ዝጋ' : 'Close'"
            >
              <X class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Embedded Native / Default PDF Preview -->
        <div class="flex-1 w-full bg-slate-100 dark:bg-slate-900 relative">
          <iframe
            v-if="pdfUrl"
            ref="iframeRef"
            :src="pdfUrl"
            class="w-full h-full border-0"
            title="PDF Document Preview"
          ></iframe>
          <div v-else class="flex items-center justify-center h-full text-xs text-main-text/50">
            {{ langStore.isAmharic() ? 'ሰነዱ እየተጫነ ነው...' : 'Loading document preview...' }}
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { FileText, Download, Printer, ExternalLink, X } from 'lucide-vue-next';
import { useAttendanceStore } from '@/stores/attendanceStore';
import { useLanguageStore } from '@/stores/languageStore';
import { useToastStore } from '@/stores/toastStore';
import { saveFileWithPickerOrFallback } from '@/utils/fileDownloader';

const attendanceStore = useAttendanceStore();
const langStore = useLanguageStore();
const toast = useToastStore();

const iframeRef = ref<HTMLIFrameElement | null>(null);
const saving = ref(false);

const isOpen = computed(() => attendanceStore.isPdfPreviewOpen);
const pdfUrl = computed(() => attendanceStore.pdfPreviewUrl);
const filename = computed(() => attendanceStore.pdfPreviewFilename);

const handleClose = () => {
  attendanceStore.closePdfPreview();
};

const handleSave = async () => {
  if (!attendanceStore.pdfPreviewBlob) return;
  saving.value = true;
  try {
    const result = await saveFileWithPickerOrFallback({
      blob: attendanceStore.pdfPreviewBlob,
      filename: filename.value || 'WDU_Attendance_Report.pdf',
      mimeType: 'application/pdf',
      title: filename.value,
    });

    if (result.success) {
      toast.success(
        langStore.isAmharic()
          ? 'የፒዲኤፍ ሰነዱ በተመረጠው ማህደር ወይም በDownloads ውስጥ ተቀምጧል።'
          : 'PDF saved successfully to your selected location / Downloads.'
      );
    }
  } catch (err) {
    toast.error('Failed to save file.');
  } finally {
    saving.value = false;
  }
};

const handlePrint = () => {
  if (iframeRef.value?.contentWindow) {
    try {
      iframeRef.value.contentWindow.focus();
      iframeRef.value.contentWindow.print();
      return;
    } catch {}
  }
  if (pdfUrl.value) {
    window.open(pdfUrl.value, '_blank');
  }
};

const handleOpenExternal = () => {
  if (pdfUrl.value) {
    window.open(pdfUrl.value, '_blank');
  }
};
</script>
