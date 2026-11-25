<template>
  <div>
    <!-- Floating Bottom PWA Install Banner -->
    <transition
      enter-active-class="transform transition ease-out duration-300"
      enter-from-class="translate-y-full opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transform transition ease-in duration-200"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-full opacity-0"
    >
      <div
        v-if="showBanner && !isStandalone"
        class="fixed bottom-3 left-3 right-3 sm:left-auto sm:right-6 sm:bottom-6 sm:max-w-md z-50 bg-card-bg/95 backdrop-blur-md border border-brand-blue/30 dark:border-brand-blue/50 rounded-xl p-3.5 sm:p-4 shadow-2xl shadow-blue-950/20 text-main-text"
      >
        <div class="flex items-start gap-3">
          <!-- WDU Logo Badge -->
          <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-lg bg-gradient-to-br from-blue-700 to-blue-900 border border-brand-yellow/40 flex items-center justify-center shrink-0 shadow-sm p-1.5">
            <svg viewBox="0 0 512 512" width="100%" height="100%" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="256" cy="140" r="16" fill="#fbbf24"/>
              <path d="M256 110 L256 170 M226 140 L286 140" stroke="#fcd34d" stroke-width="4" stroke-linecap="round"/>
              <path d="M256 205 C196 185 136 210 126 215 L126 315 C136 310 196 285 256 305 Z" fill="#ffffff"/>
              <path d="M256 205 C316 185 376 210 386 215 L386 315 C376 310 316 285 256 305 Z" fill="#f8fafc"/>
              <line x1="256" y1="204" x2="256" y2="307" stroke="#fbbf24" stroke-width="6" stroke-linecap="round"/>
              <rect x="171" y="360" width="170" height="34" rx="8" fill="#fbbf24"/>
              <text x="256" y="384" text-anchor="middle" font-family="system-ui" font-weight="900" font-size="20" fill="#0f172a">WDU</text>
            </svg>
          </div>

          <!-- Description & Title -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-1 mb-0.5">
              <h4 class="text-xs sm:text-sm font-bold text-main-text tracking-tight truncate">
                {{ langStore.isAmharic() ? 'የወልድያ ዩኒቨርሲቲ ቤተ-መጻሕፍት አፕ' : 'Install WDU Library App' }}
              </h4>
              <button
                type="button"
                @click="dismissPrompt"
                class="p-1 -mr-1 text-main-text/40 hover:text-main-text rounded-xs hover:bg-card-hover/40 transition cursor-pointer"
                :title="langStore.isAmharic() ? 'ለጊዜው ይቆይ' : 'Dismiss'"
              >
                <X class="w-4 h-4" />
              </button>
            </div>

            <p class="text-[11px] sm:text-xs text-main-text/70 line-clamp-2 leading-tight mb-3">
              {{ langStore.isAmharic()
                ? 'ያለ ኢንተርኔት ቆጣሪውን በቀላሉ ለመመዝገብ፣ መረጃዎችን በፍጥነት ለማመሳሰል እና ስርዓቱን በቀላሉ ለመጠቀም አፑን ወደ ስልክዎ ይጫኑ።'
                : 'Install our offline-first PWA on your device for instant counter logging, offline sync, and fast portal access.' }}
            </p>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="triggerInstall"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-md bg-brand-blue hover:bg-brand-blue/90 text-white font-bold text-xs shadow-sm transition active:scale-95 cursor-pointer"
              >
                <Download class="w-3.5 h-3.5" />
                <span>{{ langStore.isAmharic() ? 'አፑን ጫን (Install)' : 'Install App' }}</span>
              </button>

              <button
                type="button"
                @click="dismissPrompt"
                class="px-2.5 py-1.5 rounded-md text-xs font-semibold text-main-text/60 hover:text-main-text hover:bg-card-hover/50 transition cursor-pointer"
              >
                {{ langStore.isAmharic() ? 'ለጊዜው ይቆይ' : 'Later' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- iOS Step-by-Step Installation Modal -->
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="showIosModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        @click.self="showIosModal = false"
      >
        <div class="w-full max-w-sm bg-card-bg border border-card-border rounded-xl p-5 shadow-2xl flex flex-col gap-4">
          <div class="flex items-center justify-between pb-2 border-b border-card-border">
            <div class="flex items-center gap-2">
              <Smartphone class="w-5 h-5 text-brand-blue" />
              <h3 class="text-sm font-bold text-main-text">
                {{ langStore.isAmharic() ? 'በ iPhone / iPad ላይ አፑን ለመጫን' : 'Install on iPhone / iPad' }}
              </h3>
            </div>
            <button
              type="button"
              @click="showIosModal = false"
              class="p-1 rounded text-main-text/40 hover:text-main-text transition cursor-pointer"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <div class="space-y-3 text-xs text-main-text/80 leading-relaxed">
            <div class="flex items-start gap-2.5 p-2 rounded-lg bg-main-bg/60 border border-card-border">
              <span class="w-5 h-5 rounded-full bg-brand-blue/10 text-brand-blue font-bold flex items-center justify-center shrink-0 text-[11px]">1</span>
              <div>
                <p class="font-semibold text-main-text">
                  {{ langStore.isAmharic() ? 'በSafari ታች ያለውን የShare ምልክት ይጫኑ' : 'Tap the Share icon in Safari' }}
                </p>
                <p class="text-[10px] text-main-text/50">
                  {{ langStore.isAmharic() ? '(በስክሪኑ ግርጌ የሚገኘውን ሳጥን ከቀስት ጋር)' : '(The box with an arrow pointing upward)' }}
                </p>
              </div>
            </div>

            <div class="flex items-start gap-2.5 p-2 rounded-lg bg-main-bg/60 border border-card-border">
              <span class="w-5 h-5 rounded-full bg-brand-blue/10 text-brand-blue font-bold flex items-center justify-center shrink-0 text-[11px]">2</span>
              <div>
                <p class="font-semibold text-main-text">
                  {{ langStore.isAmharic() ? '"Add to Home Screen" የሚለውን ይምረጡ' : 'Select "Add to Home Screen"' }}
                </p>
                <p class="text-[10px] text-main-text/50">
                  {{ langStore.isAmharic() ? '(ዝቅ ብለው ወደ ታች ሲያሸበርልቁ ያገኙታል)' : '(Scroll down in the action menu to find it)' }}
                </p>
              </div>
            </div>

            <div class="flex items-start gap-2.5 p-2 rounded-lg bg-main-bg/60 border border-card-border">
              <span class="w-5 h-5 rounded-full bg-brand-blue/10 text-brand-blue font-bold flex items-center justify-center shrink-0 text-[11px]">3</span>
              <div>
                <p class="font-semibold text-main-text">
                  {{ langStore.isAmharic() ? 'ከላይ በቀኝ በኩል "Add" የሚለውን ይጫኑ' : 'Tap "Add" in the top right corner' }}
                </p>
                <p class="text-[10px] text-main-text/50">
                  {{ langStore.isAmharic() ? 'አፑ ወዲያውኑ ወደ ስልክዎ ዋና ገጽ ይጫናል' : 'The app icon will immediately appear on your home screen' }}
                </p>
              </div>
            </div>
          </div>

          <button
            type="button"
            @click="showIosModal = false"
            class="w-full py-2 rounded-md bg-brand-blue text-white font-bold text-xs shadow-sm hover:bg-brand-blue/90 transition cursor-pointer"
          >
            {{ langStore.isAmharic() ? 'ተረድቻለሁ / እሺ' : 'Got it' }}
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Download, X, Smartphone } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';

const langStore = useLanguageStore();

const deferredPrompt = ref<any>(null);
const showBanner = ref(false);
const showIosModal = ref(false);
const isInstalled = ref(false);

const isStandalone = computed(() => {
  if (typeof window === 'undefined') return false;
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    (window.navigator as any).standalone === true ||
    document.referrer.includes('android-app://')
  );
});

const isIos = computed(() => {
  if (typeof window === 'undefined') return false;
  const ua = window.navigator.userAgent.toLowerCase();
  return /iphone|ipad|ipod/.test(ua);
});

const checkAndShow = () => {
  if (isStandalone.value) {
    showBanner.value = false;
    return;
  }

  // Check if dismissed in the last 45 minutes
  const lastDismissed = localStorage.getItem('wdu_pwa_dismissed_at');
  if (lastDismissed) {
    const elapsed = Date.now() - Number(lastDismissed);
    if (elapsed < 45 * 60 * 1000) {
      return;
    }
  }

  // Delay slightly so the user sees the page first
  setTimeout(() => {
    if (!isStandalone.value) {
      showBanner.value = true;
    }
  }, 1500);
};

const triggerInstall = async () => {
  if (deferredPrompt.value) {
    try {
      deferredPrompt.value.prompt();
      const choice = await deferredPrompt.value.userChoice;
      if (choice && choice.outcome === 'accepted') {
        showBanner.value = false;
        deferredPrompt.value = null;
        localStorage.setItem('pwa_installed', 'true');
      }
    } catch (err) {
      console.warn('[PWA] Prompt error:', err);
    }
  } else if (isIos.value) {
    showBanner.value = false;
    showIosModal.value = true;
  } else {
    // If browser doesn't expose deferred prompt (e.g. desktop Chrome already showed mini-bar)
    // alert or inform user to use browser install button in address bar
    alert(
      langStore.isAmharic()
        ? 'በብሮውዘርዎ የላይኛው አድራሻ ማስገቢያ (Address Bar) አጠገብ የሚገኘውን የመጫኛ ምልክት (Install icon) ይጫኑ።'
        : 'Please click the Install icon located in your browser address bar to install the app.'
    );
  }
};

const dismissPrompt = () => {
  showBanner.value = false;
  localStorage.setItem('wdu_pwa_dismissed_at', String(Date.now()));
};

onMounted(() => {
  // 1. Capture beforeinstallprompt
  window.addEventListener('beforeinstallprompt', (e: Event) => {
    e.preventDefault();
    deferredPrompt.value = e;
    checkAndShow();
  });

  // 2. Listen for app installation
  window.addEventListener('appinstalled', () => {
    isInstalled.value = true;
    showBanner.value = false;
    deferredPrompt.value = null;
    localStorage.setItem('pwa_installed', 'true');
  });

  // 3. If no standalone and no PWA app, ask to install
  checkAndShow();

  // Expose global trigger for install button in header or sidebar
  (window as any).__wduTriggerPwaInstall = () => {
    if (deferredPrompt.value) {
      triggerInstall();
    } else if (isIos.value) {
      showIosModal.value = true;
    } else {
      showBanner.value = true;
    }
  };
});
</script>
