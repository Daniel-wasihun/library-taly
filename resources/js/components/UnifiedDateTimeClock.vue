<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Clock, Calendar } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import { formatDT, toEthiopian, amMonths, amDays } from '@/utils/ethiopianDate';

const langStore = useLanguageStore();

// Reactive timestamp state updated every second
const currentTimestamp = ref<number>(Date.now());
let timer: ReturnType<typeof setInterval> | null = null;

// Clock presentation mode: click to toggle between Standard 24h/12h and Ethiopian 12h clock
const timeFormatMode = ref<'ethiopian' | 'standard'>('ethiopian');

function toggleTimeMode() {
  timeFormatMode.value = timeFormatMode.value === 'ethiopian' ? 'standard' : 'ethiopian';
}

function updateClock() {
  currentTimestamp.value = Date.now();
}

onMounted(() => {
  updateClock();
  timer = setInterval(updateClock, 1000);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});

// Computed standardized Date object anchored to Africa/Addis_Ababa offset (+03:00)
const currentDate = computed(() => {
  return new Date(currentTimestamp.value);
});

// Precise, ACID-consistent time calculations anchored strictly to Africa/Addis_Ababa (EAT, UTC+3)
const timeInfo = computed(() => {
  const d = currentDate.value;

  // Format strictly into Africa/Addis_Ababa parts using Intl.DateTimeFormat
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Africa/Addis_Ababa',
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: 'numeric',
    minute: 'numeric',
    second: 'numeric',
    hourCycle: 'h23',
  });

  const parts = formatter.formatToParts(d);
  const map: Record<string, string> = {};
  for (const p of parts) {
    map[p.type] = p.value;
  }

  const h = Number(map.hour === '24' ? '0' : map.hour);
  const m = (map.minute || '00').padStart(2, '0');
  const s = (map.second || '00').padStart(2, '0');
  const gy = Number(map.year);
  const gm = Number(map.month);
  const gd = Number(map.day);

  // Standard 24h & 12h representation
  const std24 = `${String(h).padStart(2, '0')}:${m}:${s}`;
  const period12 = h >= 12 ? 'PM' : 'AM';
  let h12 = h % 12;
  if (h12 === 0) h12 = 12;
  const std12 = `${String(h12).padStart(2, '0')}:${m}:${s} ${period12}`;

  // Ethiopian 12-hour clock (starts at 06:00 AM dawn)
  let etH = h >= 6 ? h - 6 : h + 6;
  if (etH > 12) etH -= 12;
  if (etH === 0) etH = 12;

  let etPeriod = 'ከቀኑ';
  let etPeriodShort = 'ቀን';
  if (h >= 0 && h < 6) {
    etPeriod = 'ከሌሊቱ';
    etPeriodShort = 'ሌሊት';
  } else if (h >= 6 && h < 12) {
    etPeriod = 'ጠዋት';
    etPeriodShort = 'ጠዋት';
  } else if (h >= 12 && h < 18) {
    etPeriod = 'ከቀኑ';
    etPeriodShort = 'ከሰዓት';
  } else {
    etPeriod = 'ከምሽቱ';
    etPeriodShort = 'ምሽት';
  }

  const etTimeFull = `${String(etH).padStart(2, '0')}:${m}:${s} ${etPeriod}`;
  const etTimeShort = `${String(etH).padStart(2, '0')}:${m} ${etPeriodShort}`;

  // Ethiopian Calendar Date
  const [eyear, emonth, edate] = toEthiopian(gy, gm, gd);
  const ethMonthName = amMonths[emonth] || '';
  const dayOfWeekIndex = new Date(gy, gm - 1, gd).getDay();
  const ethDayName = amDays[dayOfWeekIndex] || '';
  const etDateFormatted = `${ethMonthName} ${edate}፣ ${eyear} ዓ.ም`;
  const etDateShort = `${ethMonthName} ${edate}`;

  // Gregorian Date
  const gregMonthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const gregDayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const gregFormatted = `${gregDayNames[dayOfWeekIndex]}, ${gregMonthNames[gm - 1]} ${gd}, ${gy}`;
  const gregShort = `${gregMonthNames[gm - 1]} ${gd}`;

  const isAmharic = langStore.isAmharic();

  return {
    // Primary display for current language & selected mode
    primaryTime: timeFormatMode.value === 'ethiopian' ? (isAmharic ? etTimeFull : `${etTimeFull} (EAT)`) : std12,
    secondaryTime: timeFormatMode.value === 'ethiopian' ? `24h: ${std24}` : `🇪🇹 ${etTimeShort}`,
    primaryDate: isAmharic ? `${ethDayName}፣ ${etDateFormatted}` : gregFormatted,
    secondaryDate: isAmharic ? `(G.C. ${gregFormatted})` : `(E.C. ${etDateFormatted})`,
    mobileTime: timeFormatMode.value === 'ethiopian' ? `${etH}:${m} ${etPeriodShort}` : `${h12}:${m} ${period12}`,
    mobileDate: isAmharic ? `${ethMonthName} ${edate}` : gregShort,
    rawHour: h,
    rawMinute: Number(m),
  };
});
</script>

<template>
  <div
    @click="toggleTimeMode"
    class="flex items-center gap-2 px-2 sm:px-2.5 py-1 rounded-sm border border-card-border bg-main-bg/80 hover:bg-card-hover/40 transition cursor-pointer select-none shadow-2xs group"
    :title="timeFormatMode === 'ethiopian' ? 'Click to switch to Standard 12h/24h Clock' : 'Click to switch to Ethiopian 12h Dawn Clock'"
  >
    <!-- Clock Icon with subtle live pulse -->
    <div class="relative flex items-center justify-center shrink-0 text-brand-blue dark:text-brand-yellow">
      <Clock class="w-3.5 h-3.5 sm:w-4 sm:h-4 group-hover:scale-110 transition-transform" />
      <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping opacity-75"></span>
      <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
    </div>

    <!-- Time & Date Stack: Desktop / Tablet -->
    <div class="hidden sm:flex flex-col text-left leading-none font-mono">
      <div class="flex items-center gap-1.5">
        <span class="text-xs font-bold text-main-text tracking-tight">
          {{ timeInfo.primaryTime }}
        </span>
        <span class="text-[9px] font-sans font-medium px-1 py-0.2 rounded-2xs bg-brand-blue/10 text-brand-blue dark:bg-brand-yellow/15 dark:text-brand-yellow">
          {{ timeFormatMode === 'ethiopian' ? '🇪🇹 ኢትዮ' : 'STD' }}
        </span>
      </div>
      <div class="flex items-center gap-1.5 text-[10px] text-main-text/60 mt-0.5 font-sans">
        <span class="truncate max-w-[170px]">{{ timeInfo.primaryDate }}</span>
        <span class="text-main-text/40">•</span>
        <span class="font-mono text-[9px] text-main-text/50">{{ timeInfo.secondaryTime }}</span>
      </div>
    </div>

    <!-- Compact Minimalist Time: Mobile (Scales cleanly into small phone headers) -->
    <div class="flex sm:hidden flex-col items-end leading-none font-mono">
      <span class="text-[11px] font-bold text-main-text tracking-tight">
        {{ timeInfo.mobileTime }}
      </span>
      <span class="text-[9px] font-sans text-main-text/60 mt-0.5 truncate max-w-[70px]">
        {{ timeInfo.mobileDate }}
      </span>
    </div>
  </div>
</template>
