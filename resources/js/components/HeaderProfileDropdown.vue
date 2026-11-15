<template>
  <div ref="dropdownRef" class="relative">
    <!-- Trigger Button: User Avatar + Name + Chevron -->
    <button
      type="button"
      @click="isOpen = !isOpen"
      class="flex items-center gap-2.5 p-1.5 rounded-lg hover:bg-main-text/5 transition-colors cursor-pointer border border-transparent hover:border-card-border"
      :aria-expanded="isOpen"
      aria-label="User Profile Menu"
    >
      <!-- Initials Avatar -->
      <div class="w-7 h-7 rounded-md bg-nav-accent/10 text-nav-accent border border-nav-accent/25 flex items-center justify-center text-xs font-bold shadow-2xs shrink-0">
        {{ userInitials }}
      </div>

      <div class="hidden sm:flex flex-col text-left">
        <span class="text-xs font-semibold text-main-text leading-tight truncate max-w-[130px]">
          {{ authStore.user?.name || 'User' }}
        </span>
        <span class="text-[10px] text-nav-accent font-medium leading-none mt-0.5">
          {{ userRoleLabel }}
        </span>
      </div>

      <ChevronDown class="w-3.5 h-3.5 text-main-text/40 transition-transform duration-200" :class="{ 'rotate-180': isOpen }" />
    </button>

    <!-- Dropdown Menu -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="transform scale-95 opacity-0"
      enter-to-class="transform scale-100 opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="transform scale-100 opacity-100"
      leave-to-class="transform scale-95 opacity-0"
    >
      <div
        v-if="isOpen"
        class="absolute right-0 mt-2 w-72 bg-card-bg/95 backdrop-blur-md rounded-xl shadow-xl border border-card-border p-2 z-50 animate-in fade-in"
      >
        <!-- User Info Header -->
        <div class="p-2.5 rounded-lg bg-card-bg border border-card-border/80 shadow-2xs mb-2">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-md bg-nav-accent/10 text-nav-accent border border-nav-accent/25 flex items-center justify-center font-bold text-xs shadow-2xs shrink-0">
              {{ userInitials }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-xs font-bold text-main-text truncate">
                {{ authStore.user?.name }}
              </p>
              <p class="text-[11px] text-main-text/50 truncate font-mono">
                @{{ authStore.user?.username }} · {{ authStore.user?.email }}
              </p>
              <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-nav-accent/10 text-nav-accent border border-nav-accent/20">
                {{ userRoleLabel }}
              </span>
            </div>
          </div>
        </div>

        <!-- Duty Status Banner in dropdown -->
        <div class="px-2.5 py-2 mb-2 rounded-lg text-xs"
             :class="authStore.isAdmin ? 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-500/20' : (authStore.isOnDutyToday ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20')">
          <p class="font-bold flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full" :class="authStore.isAdmin ? 'bg-blue-500' : (authStore.isOnDutyToday ? 'bg-emerald-500' : 'bg-amber-500')"></span>
            {{ authStore.isAdmin ? 'Admin Override Active' : (authStore.isOnDutyToday ? 'On Duty Today' : 'Off Duty') }}
          </p>
          <p v-if="!authStore.isAdmin && authStore.user?.duty_status?.next_turn" class="text-[11px] mt-0.5 opacity-80">
            Next turn: {{ authStore.user.duty_status.next_turn }}
          </p>
        </div>

        <!-- Menu Action -->
        <div class="border-t border-card-border/60 pt-1">
          <button
            type="button"
            @click="handleLogout"
            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-md text-xs font-medium text-rose-600 hover:bg-rose-500/10 transition-colors cursor-pointer"
          >
            <LogOut class="w-4 h-4 shrink-0 stroke-[2.2]" />
            <span>Sign Out</span>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { ChevronDown, LogOut } from 'lucide-vue-next';

const authStore = useAuthStore();
const router = useRouter();

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const userInitials = computed(() => {
  const name = authStore.user?.name || '';
  return name.split(' ').slice(0, 2).map((w: string) => w[0]).join('').toUpperCase() || 'U';
});

const userRoleLabel = computed(() => {
  return authStore.user?.roles?.[0] || 'Member';
});

const handleLogout = async () => {
  isOpen.value = false;
  await authStore.logout();
  router.push('/login');
};

const handleClickOutside = (e: MouseEvent) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>
