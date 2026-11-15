<template>
    <!-- Full-screen auth layout -->
    <div class="min-h-screen flex" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #1d4ed8 100%);">
        <!-- Left Panel – Branding -->
        <div class="hidden lg:flex flex-col justify-between w-1/2 p-12 relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10"
                style="background-image: repeating-linear-gradient(45deg, white 0, white 1px, transparent 0, transparent 50%); background-size: 20px 20px;"></div>

            <!-- Brand Logo -->
            <div class="relative z-10 flex items-center gap-3">
                <div class="w-12 h-12 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center">
                    <BookOpen :size="26" class="text-white" />
                </div>
                <div>
                    <h1 class="text-white font-bold text-xl">UniLibrary</h1>
                    <p class="text-blue-200 text-xs">University Library System</p>
                </div>
            </div>

            <!-- Center Feature List -->
            <div class="relative z-10 space-y-6">
                <div>
                    <h2 class="text-white text-3xl font-bold leading-tight">
                        Knowledge at<br/>Your Fingertips
                    </h2>
                    <p class="text-blue-200 mt-3 text-sm leading-relaxed">
                        Secure, role-based access to the university library catalog with
                        intelligent duty-schedule enforcement for fair task distribution.
                    </p>
                </div>

                <div class="space-y-3">
                    <FeaturePoint v-for="f in features" :key="f.text" :text="f.text" />
                </div>
            </div>

            <!-- Footer -->
            <div class="relative z-10">
                <p class="text-blue-300 text-xs">© 2026 University Library System. All rights reserved.</p>
            </div>
        </div>

        <!-- Right Panel – Login Form -->
        <div class="flex flex-col justify-center items-center flex-1 px-6 py-12">
            <!-- Mobile Brand -->
            <div class="lg:hidden flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <BookOpen :size="20" class="text-white" />
                </div>
                <div>
                    <h1 class="text-white font-bold">UniLibrary</h1>
                    <p class="text-blue-200 text-xs">University Library System</p>
                </div>
            </div>

            <!-- Card -->
            <div class="w-full max-w-sm rounded-2xl shadow-2xl p-8" style="background: var(--bg-card);">
                <div class="mb-6">
                    <h2 class="font-bold text-xl" style="color: var(--text-primary);">Welcome back</h2>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Sign in to your library account</p>
                </div>

                <form @submit.prevent="handleLogin" class="space-y-4">
                    <!-- Login Field -->
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">
                            Username or Email
                        </label>
                        <div class="relative">
                            <User :size="15" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                            <input
                                v-model="form.login"
                                type="text"
                                placeholder="admin or admin@unilib.edu"
                                autocomplete="username"
                                required
                                class="w-full pl-9 pr-3 py-2.5 text-sm rounded-lg border transition-colors"
                                style="background: var(--bg-input); border-color: var(--border); color: var(--text-primary);"
                                :class="{ 'ring-2 ring-red-400 border-transparent': errors.login }"
                            />
                        </div>
                        <p v-if="errors.login" class="text-xs mt-1 text-red-500">{{ errors.login }}</p>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">
                            Password
                        </label>
                        <div class="relative">
                            <Lock :size="15" class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--text-muted);" />
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="password123"
                                autocomplete="current-password"
                                required
                                class="w-full pl-9 pr-10 py-2.5 text-sm rounded-lg border transition-colors"
                                style="background: var(--bg-input); border-color: var(--border); color: var(--text-primary);"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 transition-colors"
                                style="color: var(--text-muted);">
                                <component :is="showPassword ? EyeOff : Eye" :size="15" />
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="authStore.loading"
                        class="w-full py-2.5 rounded-lg text-sm font-semibold text-white transition-all duration-150 disabled:opacity-50"
                        style="background: var(--lib-primary-light);">
                        <span v-if="authStore.loading" class="flex items-center justify-center gap-2">
                            <Loader2 :size="15" class="animate-spin" /> Signing In...
                        </span>
                        <span v-else>Sign In</span>
                    </button>
                </form>

                <!-- Demo Accounts -->
                <div class="mt-6 pt-5 border-t" style="border-color: var(--border);">
                    <p class="text-xs font-medium mb-3" style="color: var(--text-muted);">Demo Accounts</p>
                    <div class="space-y-2">
                        <DemoBtn
                            v-for="d in demoAccounts" :key="d.login"
                            :label="d.label"
                            :badge="d.badge"
                            :badgeStyle="d.badgeStyle"
                            :note="d.note"
                            @click="fillDemo(d.login, d.password)" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { BookOpen, User, Lock, Eye, EyeOff, Loader2 } from '@lucide/vue';

const authStore = useAuthStore();
const router = useRouter();

const form = reactive({ login: '', password: '' });
const errors = reactive({ login: '' });
const showPassword = ref(false);

const features = [
    { text: 'Spatie Role & Permission Management' },
    { text: 'Duty-Schedule Enforced Task Access' },
    { text: 'Book Catalog & Circulation Tracking' },
    { text: 'Laravel Sanctum API Authentication' },
    { text: 'Real-time Duty Turn Verification' },
];

const demoAccounts = [
    { login: 'admin', password: 'password123', label: 'admin', badge: 'Super Admin', badgeStyle: 'background:#1e3a8a; color:#bfdbfe;', note: 'Full override access' },
    { login: 'tigist_duty', password: 'password123', label: 'tigist_duty', badge: 'On Duty ✓', badgeStyle: 'background:#065f46; color:#a7f3d0;', note: 'Can issue books today' },
    { login: 'yohannes_offduty', password: 'password123', label: 'yohannes_offduty', badge: 'Off Duty', badgeStyle: 'background:#7c2d12; color:#fca5a5;', note: 'Blocked from circulation' },
    { login: 'sara_student', password: 'password123', label: 'sara_student', badge: 'Student', badgeStyle: 'background:#374151; color:#d1d5db;', note: 'Catalog read-only' },
];

function fillDemo(login: string, password: string) {
    form.login = login;
    form.password = password;
}

async function handleLogin() {
    errors.login = '';
    const success = await authStore.login({ login: form.login, password: form.password });
    if (success) {
        router.push('/dashboard');
    }
}
</script>

<script lang="ts">
// Inline sub-components for cleanliness
import { defineComponent, h } from 'vue';
import { CheckCircle } from '@lucide/vue';

const FeaturePoint = defineComponent({
    props: { text: String },
    setup(props) {
        return () => h('div', { class: 'flex items-center gap-3 text-blue-100 text-sm' }, [
            h(CheckCircle, { size: 16, class: 'text-blue-300 shrink-0' }),
            h('span', props.text),
        ]);
    },
});

const DemoBtn = defineComponent({
    props: { label: String, badge: String, badgeStyle: String, note: String },
    emits: ['click'],
    setup(props, { emit }) {
        return () => h('button', {
            type: 'button',
            onClick: () => emit('click'),
            class: 'w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg border text-xs transition-colors hover:opacity-80',
            style: 'border-color: var(--border); background: var(--bg-input);',
        }, [
            h('div', { class: 'flex items-center gap-2' }, [
                h('code', { class: 'font-mono', style: 'color: var(--text-primary);' }, props.label),
                h('span', { class: 'text-xs', style: 'color: var(--text-muted);' }, props.note),
            ]),
            h('span', {
                class: 'px-2 py-0.5 rounded-full text-xs font-medium shrink-0',
                style: props.badgeStyle,
            }, props.badge),
        ]);
    },
});

export { FeaturePoint, DemoBtn };
</script>
