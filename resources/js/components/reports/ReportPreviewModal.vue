<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  Printer,
  Download,
  X,
  FileSpreadsheet,
  FileText,
  Building2,
  Calendar,
  Clock,
  Users,
  CheckCircle2,
} from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import { useAuthStore } from '@/stores/authStore';
import { useAttendanceStore } from '@/stores/attendanceStore';
import { useLibraryStore } from '@/stores/libraryStore';
import { formatDT } from '@/utils/ethiopianDate';
import Modal from '@/components/ui/Modal.vue';
import Button from '@/components/ui/Button.vue';

const props = defineProps<{
  show: boolean;
  scope?: 'full' | 'patron_types' | 'branches' | 'day_of_week' | 'intra_month';
  dateFrom?: string;
  dateTo?: string;
  libraryId?: number | null;
  reportStats?: any;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const langStore = useLanguageStore();
const authStore = useAuthStore();
const attendanceStore = useAttendanceStore();
const libraryStore = useLibraryStore();

const isPrinting = ref(false);

const activeScope = computed(() => props.scope || 'full');

const effectiveStats = computed(() => {
  return props.reportStats || attendanceStore.stats || {};
});

const libraryName = computed(() => {
  if (!props.libraryId) {
    return langStore.isAmharic() ? 'ሁሉም የዩኒቨርሲቲው ቅርንጫፎች' : 'All University Library Branches';
  }
  const lib = libraryStore.libraries.find((l) => Number(l.id) === Number(props.libraryId));
  if (!lib) return langStore.isAmharic() ? 'ቅርንጫፍ #' + props.libraryId : 'Branch #' + props.libraryId;
  return langStore.isAmharic() && lib.name_am ? lib.name_am : lib.name;
});

const todayDisplay = computed(() => {
  return formatDT(new Date(), langStore.isAmharic());
});

const reportingWindowDisplay = computed(() => {
  const from = props.dateFrom;
  const to = props.dateTo;
  if (!from && !to) return todayDisplay.value.full;
  if (from === to) {
    return formatDT(from, langStore.isAmharic()).full;
  }
  const dFrom = formatDT(from, langStore.isAmharic()).date;
  const dTo = formatDT(to, langStore.isAmharic()).date;
  return `${dFrom} – ${dTo}`;
});

const totalUsers = computed(() => Number(effectiveStats.value.total_users || 0));
const totalMale = computed(() => Number(effectiveStats.value.total_male || 0));
const totalFemale = computed(() => Number(effectiveStats.value.total_female || 0));

const malePercent = computed(() => {
  return totalUsers.value > 0 ? Math.round((totalMale.value / totalUsers.value) * 100) : 0;
});

const femalePercent = computed(() => {
  return totalUsers.value > 0 ? Math.round((totalFemale.value / totalUsers.value) * 100) : 0;
});

const demographics = computed(() => {
  const dist = effectiveStats.value.library_distribution || [];
  const res = {
    student: { male: 0, female: 0, total: 0, percent: 0 },
    teacher: { male: 0, female: 0, total: 0, percent: 0 },
    staff: { male: 0, female: 0, total: 0, percent: 0 },
  };

  dist.forEach((d: any) => {
    const type = (d.user_type || '').toLowerCase();
    const count = parseInt(String(d.count || 0), 10) || 0;
    const gender = (d.gender || '').toLowerCase();

    if (type === 'student' || type === 'teacher' || type === 'staff') {
      if (gender === 'male') res[type].male += count;
      else if (gender === 'female') res[type].female += count;
      res[type].total += count;
    }
  });

  const grandTotal = totalUsers.value || 1;
  res.student.percent = Math.round((res.student.total / grandTotal) * 100);
  res.teacher.percent = Math.round((res.teacher.total / grandTotal) * 100);
  res.staff.percent = Math.round((res.staff.total / grandTotal) * 100);

  return res;
});

const branchList = computed(() => {
  const branches = effectiveStats.value.branch_comparison || [];
  if (branches.length > 0) return branches;

  return libraryStore.libraries.map((lib) => {
    return {
      name: langStore.isAmharic() && lib.name_am ? lib.name_am : lib.name,
      code: lib.code || 'LIB',
      students: 0,
      teachers: 0,
      staff: 0,
      total: 0,
      percent: 0,
    };
  });
});

const handlePrint = () => {
  isPrinting.value = true;
  setTimeout(() => {
    window.print();
    isPrinting.value = false;
  }, 150);
};

const handleSavePdf = async () => {
  await attendanceStore.downloadFormalReport('pdf', {
    date_from: props.dateFrom,
    date_to: props.dateTo,
    library_id: props.libraryId || undefined,
  }, activeScope.value);
};

const handleSaveExcel = async () => {
  await attendanceStore.downloadFormalReport('xlsx', {
    date_from: props.dateFrom,
    date_to: props.dateTo,
    library_id: props.libraryId || undefined,
  }, activeScope.value);
};

const handleSaveCsv = async () => {
  await attendanceStore.downloadFormalReport('csv', {
    date_from: props.dateFrom,
    date_to: props.dateTo,
    library_id: props.libraryId || undefined,
  }, activeScope.value);
};
</script>

<template>
  <Modal
    :show="show"
    max-width="5xl"
    :closeable="true"
    @close="emit('close')"
  >
    <!-- Modal Control Header (Sticky on Screen, Hidden during Print) -->
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 w-full pr-6 print:hidden">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded bg-brand-blue text-white flex items-center justify-center font-bold text-xs shadow-xs">
            WDU
          </div>
          <div>
            <h3 class="text-sm sm:text-base font-bold text-main-text leading-tight">
              {{ langStore.isAmharic() ? 'ይፋዊ የሪፖርት ቅድመ-እይታ' : 'Official Report Document Preview' }}
            </h3>
            <p class="text-[11px] text-main-text/60">
              {{ libraryName }} · {{ todayDisplay.date }}
            </p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
          <!-- Print Button -->
          <Button
            variant="primary"
            size="sm"
            :icon="Printer"
            @click="handlePrint"
            class="!bg-brand-blue text-white shadow-2xs text-xs"
          >
            {{ langStore.isAmharic() ? 'አትም (Print)' : 'Print' }}
          </Button>

          <!-- Save / Download PDF -->
          <Button
            variant="secondary"
            size="sm"
            :icon="Download"
            :loading="attendanceStore.isDownloadingReport && attendanceStore.downloadingReportFormat === 'pdf'"
            :disabled="attendanceStore.isDownloadingReport"
            @click="handleSavePdf"
            class="text-xs"
          >
            PDF
          </Button>

          <!-- Save Excel -->
          <Button
            variant="secondary"
            size="sm"
            :icon="FileSpreadsheet"
            :loading="attendanceStore.isDownloadingReport && attendanceStore.downloadingReportFormat === 'xlsx'"
            :disabled="attendanceStore.isDownloadingReport"
            @click="handleSaveExcel"
            class="text-xs hidden md:inline-flex"
          >
            Excel
          </Button>

          <!-- Save CSV -->
          <Button
            variant="secondary"
            size="sm"
            :icon="FileText"
            :loading="attendanceStore.isDownloadingReport && attendanceStore.downloadingReportFormat === 'csv'"
            :disabled="attendanceStore.isDownloadingReport"
            @click="handleSaveCsv"
            class="text-xs hidden sm:inline-flex"
          >
            CSV
          </Button>
        </div>
      </div>
    </template>

    <!-- Document Paper Area (A4 Styled Printable Sheet) -->
    <div class="p-2 sm:p-6 bg-slate-100 dark:bg-slate-900 overflow-y-auto max-h-[75vh]">
      <div id="unilib-report-printable-sheet" class="max-w-[850px] mx-auto bg-white text-slate-900 border border-slate-300 rounded shadow-sm p-4 sm:p-8 font-sans text-xs leading-relaxed print:p-0 print:border-none print:shadow-none print:max-w-none">
        
        <!-- Institutional Header Table -->
        <div class="border-b-2 border-[#0b529c] pb-3 mb-4 flex items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded bg-[#0b529c] text-white flex items-center justify-center font-bold text-lg font-mono tracking-wider shrink-0">
              WDU
            </div>
            <div>
              <h1 class="text-sm sm:text-base font-extrabold text-[#0b529c] tracking-tight uppercase">
                {{ langStore.isAmharic() ? 'ወልድያ ዩኒቨርሲቲ' : 'WOLDIA UNIVERSITY' }}
              </h1>
              <p class="text-[11px] font-semibold text-slate-700">
                {{ langStore.isAmharic() ? 'የዩኒቨርሲቲ ቤተ-መጻሕፍት እና መረጃ አገልግሎት ዳይሬክቶሬት' : 'Directorate of University Library & Information Services' }}
              </p>
              <p class="text-[10px] text-slate-500 font-medium">
                {{ langStore.isAmharic() ? 'ይፋዊ የአጠቃቀም ቆጣሪና የስነ-ሕዝብ ትንተና ሪፖርት' : 'Official Library Attendance & Demographic Analytics Report' }}
              </p>
            </div>
          </div>
          <div class="text-right shrink-0">
            <span class="inline-block px-2.5 py-1 rounded text-[9.5px] font-bold uppercase bg-blue-50 text-[#0b529c] border border-blue-200">
              {{ langStore.isAmharic() ? 'ይፋዊ ሪፖርት' : 'Official Report' }}
            </span>
          </div>
        </div>

        <!-- Document Metadata Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 p-2.5 rounded bg-slate-50 border border-slate-200 mb-5 text-[11px]">
          <div>
            <span class="text-slate-500 block text-[9.5px] uppercase font-bold">{{ langStore.isAmharic() ? 'የተዘጋጀበት ቀን' : 'Issuance Date' }}</span>
            <span class="font-semibold text-slate-800">{{ todayDisplay.date }} · {{ todayDisplay.time }}</span>
          </div>
          <div>
            <span class="text-slate-500 block text-[9.5px] uppercase font-bold">{{ langStore.isAmharic() ? 'የሽፋን ጊዜ' : 'Reporting Window' }}</span>
            <span class="font-semibold text-slate-800">{{ reportingWindowDisplay }}</span>
          </div>
          <div>
            <span class="text-slate-500 block text-[9.5px] uppercase font-bold">{{ langStore.isAmharic() ? 'ቅርንጫፍ' : 'Facility Scope' }}</span>
            <span class="font-semibold text-slate-800">{{ libraryName }}</span>
          </div>
          <div>
            <span class="text-slate-500 block text-[9.5px] uppercase font-bold">{{ langStore.isAmharic() ? 'ያዘጋጀው' : 'Officer' }}</span>
            <span class="font-semibold text-slate-800">{{ authStore.user?.name || 'Attendant' }}</span>
          </div>
        </div>

        <!-- 1. Key Performance Indicators (General & Relevant Only) -->
        <div class="mb-5">
          <h2 class="text-xs font-bold text-[#0b529c] uppercase tracking-wider border-b border-[#0b529c]/40 pb-1 mb-2.5">
            {{ langStore.isAmharic() ? '1. ዋና ዋና የአፈጻጸም ጠቋሚዎች (KPIs)' : '1. Key Performance Indicators (KPIs)' }}
          </h2>
          <div class="grid grid-cols-3 gap-2.5">
            <div class="p-3 rounded border border-slate-200 bg-slate-50/70 text-center">
              <span class="text-[10px] uppercase font-bold text-slate-500 block">{{ langStore.isAmharic() ? 'ጠቅላላ ተጠቃሚዎች' : 'Total Patron Traffic' }}</span>
              <span class="text-xl sm:text-2xl font-black text-[#0b529c] font-mono leading-tight">{{ totalUsers.toLocaleString() }}</span>
              <span class="text-[9.5px] text-slate-500 block mt-0.5">{{ langStore.isAmharic() ? 'በጊዜ ወሰኑ የተመዘገበ' : 'Recorded Visits' }}</span>
            </div>
            <div class="p-3 rounded border border-slate-200 bg-white text-center">
              <span class="text-[10px] uppercase font-bold text-[#0b529c] block">{{ langStore.localizeGender('male') }}</span>
              <span class="text-lg sm:text-xl font-bold text-[#0b529c] font-mono leading-tight">{{ totalMale.toLocaleString() }}</span>
              <span class="text-[9.5px] text-slate-500 block mt-0.5">{{ malePercent }}% {{ langStore.isAmharic() ? 'ድርሻ' : 'of total' }}</span>
            </div>
            <div class="p-3 rounded border border-slate-200 bg-white text-center">
              <span class="text-[10px] uppercase font-bold text-amber-600 block">{{ langStore.localizeGender('female') }}</span>
              <span class="text-lg sm:text-xl font-bold text-amber-600 font-mono leading-tight">{{ totalFemale.toLocaleString() }}</span>
              <span class="text-[9.5px] text-slate-500 block mt-0.5">{{ femalePercent }}% {{ langStore.isAmharic() ? 'ድርሻ' : 'of total' }}</span>
            </div>
          </div>
        </div>

        <!-- 2. Patron Demographic Categorization Table -->
        <div class="mb-5">
          <h2 class="text-xs font-bold text-[#0b529c] uppercase tracking-wider border-b border-[#0b529c]/40 pb-1 mb-2.5">
            {{ langStore.isAmharic() ? '2. የተጠቃሚዎች ምድብና ስነ-ሕዝብ ዝርዝር' : '2. Patron Demographic Categorization' }}
          </h2>
          <table class="w-full border-collapse border border-slate-300 text-[11px]">
            <thead>
              <tr class="bg-slate-100 text-slate-800">
                <th class="border border-slate-300 py-1.5 px-2.5 text-left font-bold uppercase text-[10px]">{{ langStore.isAmharic() ? 'የተጠቃሚ ምድብ' : 'User Type' }}</th>
                <th class="border border-slate-300 py-1.5 px-2.5 text-right font-bold uppercase text-[10px]">{{ langStore.localizeGender('male') }}</th>
                <th class="border border-slate-300 py-1.5 px-2.5 text-right font-bold uppercase text-[10px]">{{ langStore.localizeGender('female') }}</th>
                <th class="border border-slate-300 py-1.5 px-2.5 text-right font-bold uppercase text-[10px]">{{ langStore.isAmharic() ? 'ጠቅላላ' : 'Total' }}</th>
                <th class="border border-slate-300 py-1.5 px-2.5 text-right font-bold uppercase text-[10px]">{{ langStore.isAmharic() ? 'ድርሻ (%)' : 'Share (%)' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="border border-slate-300 py-1.5 px-2.5 font-semibold">{{ langStore.isAmharic() ? 'ተማሪ' : 'Student' }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ demographics.student.male.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ demographics.student.female.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono font-bold">{{ demographics.student.total.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ demographics.student.percent }}%</td>
              </tr>
              <tr class="bg-slate-50/50">
                <td class="border border-slate-300 py-1.5 px-2.5 font-semibold">{{ langStore.isAmharic() ? 'መምህር' : 'Teacher' }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ demographics.teacher.male.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ demographics.teacher.female.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono font-bold">{{ demographics.teacher.total.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ demographics.teacher.percent }}%</td>
              </tr>
              <tr>
                <td class="border border-slate-300 py-1.5 px-2.5 font-semibold">{{ langStore.isAmharic() ? 'ሠራተኛ' : 'Staff' }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ demographics.staff.male.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ demographics.staff.female.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono font-bold">{{ demographics.staff.total.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ demographics.staff.percent }}%</td>
              </tr>
              <tr class="bg-slate-100 font-bold text-[#0b529c]">
                <td class="border border-slate-300 py-1.5 px-2.5 uppercase">{{ langStore.isAmharic() ? 'ጠቅላላ ድምር' : 'Total Traffic' }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ totalMale.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ totalFemale.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono text-xs">{{ totalUsers.toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">100.0%</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 3. Campus Facility Distribution Table -->
        <div v-if="activeScope === 'full' || activeScope === 'branches'" class="mb-5">
          <h2 class="text-xs font-bold text-[#0b529c] uppercase tracking-wider border-b border-[#0b529c]/40 pb-1 mb-2.5">
            {{ langStore.isAmharic() ? '3. የቅርንጫፍ ቤተ-መጻሕፍት ንጽጽር' : '3. Library Branch Facility Distribution' }}
          </h2>
          <table class="w-full border-collapse border border-slate-300 text-[11px]">
            <thead>
              <tr class="bg-slate-100 text-slate-800">
                <th class="border border-slate-300 py-1.5 px-2.5 text-left font-bold uppercase text-[10px]">{{ langStore.isAmharic() ? 'ቅርንጫፍ' : 'Branch' }}</th>
                <th class="border border-slate-300 py-1.5 px-2.5 text-left font-bold uppercase text-[10px]">{{ langStore.isAmharic() ? 'ኮድ' : 'Code' }}</th>
                <th class="border border-slate-300 py-1.5 px-2.5 text-right font-bold uppercase text-[10px]">{{ langStore.isAmharic() ? 'ተማሪ' : 'Students' }}</th>
                <th class="border border-slate-300 py-1.5 px-2.5 text-right font-bold uppercase text-[10px]">{{ langStore.isAmharic() ? 'መምህር' : 'Teachers' }}</th>
                <th class="border border-slate-300 py-1.5 px-2.5 text-right font-bold uppercase text-[10px]">{{ langStore.isAmharic() ? 'ሠራተኛ' : 'Staff' }}</th>
                <th class="border border-slate-300 py-1.5 px-2.5 text-right font-bold uppercase text-[10px]">{{ langStore.isAmharic() ? 'ጠቅላላ' : 'Total' }}</th>
                <th class="border border-slate-300 py-1.5 px-2.5 text-right font-bold uppercase text-[10px]">{{ langStore.isAmharic() ? 'ድርሻ' : 'Share' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in branchList" :key="b.code" class="hover:bg-slate-50/50">
                <td class="border border-slate-300 py-1.5 px-2.5 font-semibold">{{ b.name }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 font-mono text-[10px]">{{ b.code }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ Number(b.students || 0).toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ Number(b.teachers || 0).toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ Number(b.staff || 0).toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono font-bold">{{ Number(b.total || 0).toLocaleString() }}</td>
                <td class="border border-slate-300 py-1.5 px-2.5 text-right font-mono">{{ b.percent || 0 }}%</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Report Footer / Exporter Information Only -->
        <div class="mt-8 pt-4 border-t border-slate-300 flex items-center justify-between text-slate-600 text-[11.5px]">
          <div class="flex items-center gap-1.5">
            <span class="font-bold text-slate-800">{{ langStore.isAmharic() ? 'ሪፖርቱን ያመነጨው/ያዘጋጀው፡' : 'Generated by:' }}</span>
            <span class="font-semibold text-[#0b529c]">{{ authStore.user?.name || 'Staff' }}</span>
            <span v-if="authStore.user?.role" class="text-slate-400 text-[10px]">
              ({{ authStore.user.role }})
            </span>
          </div>
          <div class="font-mono text-slate-500 text-[10.5px]">
            {{ todayDisplay.date }} · {{ todayDisplay.time }}
          </div>
        </div>

      </div>
    </div>
  </Modal>
</template>

<style scoped>
@media print {
  body * {
    visibility: hidden;
  }
  #unilib-report-printable-sheet,
  #unilib-report-printable-sheet * {
    visibility: visible;
  }
  #unilib-report-printable-sheet {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 0;
    border: none;
    box-shadow: none;
  }
}
</style>
