<template>
  <div class="min-h-screen flex flex-col justify-between relative bg-main-bg text-main-text select-none">
    <!-- Top Bar with Language and Theme Switcher -->
    <header class="px-6 py-4 flex items-center justify-between z-20">
      <div class="flex items-center gap-2.5">
        <WduLogo :size="36" />
        <div class="flex flex-col">
          <span class="font-bold text-sm tracking-tight text-main-text leading-tight">
            {{ langStore.isAmharic() ? 'ወልድያ ዩኒቨርሲቲ' : 'Woldia University' }}
          </span>
          <span class="text-[9.5px] text-brand-blue dark:text-brand-yellow font-bold uppercase tracking-wider">
            {{ langStore.isAmharic() ? 'ቤተ-መጻሕፍት ፖርታል' : 'Library Portal' }}
          </span>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <ThemeToggle />
        <LanguageSwitcher />
      </div>
    </header>

    <!-- Main Login Card Area -->
    <main class="flex-1 flex items-center justify-center p-4">
      <div class="w-full max-w-md bg-card-bg border border-card-border rounded-sm sm:rounded shadow-xl p-6 sm:p-8 space-y-6">
        <!-- Title & WDU Emblem -->
        <div class="text-center space-y-2">
          <div class="flex justify-center mb-1">
            <WduLogo :size="64" />
          </div>
          <h2 class="text-xl font-extrabold text-main-text tracking-tight">
            {{ langStore.isAmharic() ? 'ወልድያ ዩኒቨርሲቲ ቤተ-መጻሕፍት' : 'Woldia University Library' }}
          </h2>
          <p class="text-xs text-brand-blue dark:text-brand-yellow font-semibold tracking-wide">
            {{ langStore.isAmharic() ? 'የመገኘትና የፈረቃ አስተዳደር ሥርዓት' : 'Library Attendance & Shift Duty System' }}
          </p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="space-y-4">
          <!-- Username / Email -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1.5">
              {{ langStore.t('users.username', 'Username') }} / {{ langStore.t('users.email', 'Email Address') }}
            </label>
            <div class="relative">
              <User class="w-4 h-4 text-main-text/40 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="form.login"
                type="text"
                required
                autocomplete="username"
                placeholder="admin or admin@unilib.edu"
                class="w-full pl-9 pr-3 py-2.5 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
              />
            </div>
          </div>

          <!-- Password -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1.5">
              {{ langStore.t('users.password', 'Password') }}
            </label>
            <div class="relative">
              <Lock class="w-4 h-4 text-main-text/40 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="password123"
                class="w-full pl-9 pr-10 py-2.5 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-main-text/40 hover:text-main-text cursor-pointer"
              >
                <component :is="showPassword ? EyeOff : Eye" class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Submit Button -->
          <Button
            type="submit"
            variant="primary"
            block
            :loading="authStore.loading"
            class="mt-2"
          >
            {{ langStore.t('app.welcome', 'Sign In') }}
          </Button>
        </form>

        <!-- Quick Demo Accounts matching Gibi-Gubae credentials -->
        <div class="pt-4 border-t border-card-border/70 space-y-2">
          <p class="text-[11px] font-bold text-main-text/50 uppercase tracking-wider text-center">
            Demo Credentials (Click to fill)
          </p>

          <div class="grid grid-cols-2 gap-2 text-xs">
            <button
              type="button"
              @click="fillDemo('admin', 'password123')"
              class="p-2.5 rounded-lg border border-card-border hover:bg-slate-50 dark:hover:bg-slate-800/40 text-left transition cursor-pointer"
            >
              <div class="font-bold text-brand-blue dark:text-brand-yellow font-mono text-[11px]">admin</div>
              <div class="text-[10px] text-main-text/50">Super Admin (Override)</div>
            </button>

            <button
              type="button"
              @click="fillDemo('tigist_duty', 'password123')"
              class="p-2.5 rounded-lg border border-card-border hover:bg-slate-50 dark:hover:bg-slate-800/40 text-left transition cursor-pointer"
            >
              <div class="font-bold text-emerald-600 dark:text-emerald-400 font-mono text-[11px]">tigist_duty</div>
              <div class="text-[10px] text-main-text/50">Librarian (On Duty ✓)</div>
            </button>

            <button
              type="button"
              @click="fillDemo('yohannes_offduty', 'password123')"
              class="p-2.5 rounded-lg border border-card-border hover:bg-slate-50 dark:hover:bg-slate-800/40 text-left transition cursor-pointer"
            >
              <div class="font-bold text-amber-600 dark:text-amber-400 font-mono text-[11px]">yohannes_offduty</div>
              <div class="text-[10px] text-main-text/50">Librarian (Off Duty ✕)</div>
            </button>

            <button
              type="button"
              @click="fillDemo('sara_student', 'password123')"
              class="p-2.5 rounded-lg border border-card-border hover:bg-slate-50 dark:hover:bg-slate-800/40 text-left transition cursor-pointer"
            >
              <div class="font-bold text-slate-700 dark:text-slate-300 font-mono text-[11px]">sara_student</div>
              <div class="text-[10px] text-main-text/50">Student Member</div>
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 text-center text-xs text-main-text/40">
      {{ langStore.t('app.name', 'UniLibrary Portal') }} · {{ langStore.t('app.tagline', 'Academic Excellence & Automated Duty Enforcement') }}
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import ThemeToggle from '@/components/ThemeToggle.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import WduLogo from '@/components/WduLogo.vue';
import Button from '@/components/ui/Button.vue';
import { BookOpen, User, Lock, Eye, EyeOff } from 'lucide-vue-next';

const authStore = useAuthStore();
const langStore = useLanguageStore();
const router = useRouter();

const showPassword = ref(false);
const form = reactive({ login: '', password: '' });

const fillDemo = (login: string, pass: string) => {
  form.login = login;
  form.password = pass;
};

const handleLogin = async () => {
  const success = await authStore.login({ login: form.login, password: form.password });
  if (success) {
    const savedRoute = localStorage.getItem('unilib_pwa_last_route');
    if (savedRoute && savedRoute !== '/' && savedRoute !== '/login' && !savedRoute.startsWith('/login')) {
      router.push(savedRoute);
      return;
    }
    if (authStore.canViewDashboard) {
      router.push('/dashboard');
    } else if (authStore.hasPermission('attendance.record') || authStore.hasPermission('attendance.view')) {
      router.push('/attendance');
    } else {
      router.push('/my-turns');
    }
  }
};
</script>
