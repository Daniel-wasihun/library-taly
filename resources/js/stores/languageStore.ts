import { defineStore } from 'pinia';
import { ref, watch } from 'vue';
import Cookies from 'js-cookie';
import api from '@/api/client';

export interface LanguageOption {
    key: string;
    name: string;
    label: string;
    icon: string;
}

// Built-in offline fallback dictionaries so UI never flashes empty keys
const DEFAULT_ENGLISH: Record<string, string> = {
    'app.name': 'UniLibrary Portal',
    'app.subtitle': 'University Library Management System',
    'common.actions': 'Actions',
    'common.save': 'Save Changes',
    'common.cancel': 'Cancel',
    'common.delete': 'Delete',
    'common.edit': 'Edit',
    'common.view': 'View',
    'common.search': 'Search records...',
    'common.filter': 'Filter',
    'common.status': 'Status',
    'common.all': 'All',
    'common.active': 'Active',
    'common.inactive': 'Inactive',
    'common.suspended': 'Suspended',
    'common.loading': 'Loading...',
    'common.refresh': 'Refresh',
    'common.confirm_delete': 'Are you sure you want to delete this record?',
    'common.no_records': 'No records found.',
    'common.success': 'Success',
    'common.error': 'Error',
    'common.close': 'Close',
    'nav.dashboard': 'Overview Dashboard',
    'nav.group_general': 'General',
    'nav.group_library': 'Library Operations',
    'nav.group_admin': 'Administration & Security',
    'nav.users': 'User Management',
    'nav.roles': 'Roles & RBAC Matrix',
    'nav.schedules': 'Duty Shift Schedules',
    'nav.my_turns': 'My Duty Turns',
    'nav.books': 'Book Catalog',
    'nav.circulation': 'Circulation & Loans',
    'duty.on_duty': 'On Duty Today',
    'duty.off_duty': 'Off Duty',
    'duty.admin_override': 'Admin Override Active',
    'duty.next_turn': 'Next Scheduled Turn',
    'users.title': 'User Accounts & Access',
    'users.subtitle': 'Manage user authentication, Spatie role assignments, and status',
    'users.add': 'Add New User',
    'roles.title': 'Roles & RBAC Matrix',
    'roles.subtitle': 'Hierarchical authorization powered by Spatie Laravel Permission',
    'roles.create': 'Create Role',
    'books.title': 'University Library Catalog',
    'books.add': 'Add Book',
    'circulation.title': 'Circulation & Book Loans',
    'circulation.issue': 'Issue Book',
    'circulation.return': 'Return Book',
};

const DEFAULT_AMHARIC: Record<string, string> = {
    'app.name': 'የዩኒቨርሲቲ ቤተ-መጻሕፍት ፖርታል',
    'app.subtitle': 'የዩኒቨርሲቲ ቤተ-መጻሕፍትና ተረኛ ፈረቃ አስተዳደር ሥርዓት',
    'common.actions': 'ተግባራት',
    'common.save': 'አስቀምጥ',
    'common.cancel': 'ሰርዝ',
    'common.delete': 'አጥፋ',
    'common.edit': 'አስተካክል',
    'common.view': 'ተመልከት',
    'common.search' : 'መዝገቦችን ፈልግ...',
    'common.filter': 'አጣራ',
    'common.status': 'ሁኔታ',
    'common.all': 'ሁሉም',
    'common.active': 'ንቁ',
    'common.inactive': 'ቦዘኔ',
    'common.suspended': 'የታገደ',
    'common.loading': 'በመጫን ላይ...',
    'common.refresh': 'አድስ',
    'common.confirm_delete': 'እርግጠኛ ነዎት ይህንን መዝገብ መሰረዝ ይፈልጋሉ?',
    'common.no_records': 'ምንም የተገኘ መረጃ የለም።',
    'common.success': 'ተሳክቷል',
    'common.error': 'ስህተት',
    'common.close': 'ዝጋ',
    'nav.dashboard': 'ዋና ዳሽቦርድ',
    'nav.group_general': 'አጠቃላይ',
    'nav.group_library': 'የቤተ-መጻሕፍት ሥራዎች',
    'nav.group_admin': 'አስተዳደርና ደህንነት',
    'nav.users': 'የተጠቃሚዎች አስተዳደር',
    'nav.roles': 'ሚናዎችና ፈቃዶች (RBAC)',
    'nav.schedules': 'የተረኛ ፈረቃዎች መርሃ-ግብር',
    'nav.my_turns': 'የእኔ ተረኛ ፈረቃ',
    'nav.books': 'የመጻሕፍት ማውጫ',
    'nav.circulation': 'የመጻሕፍት ብድርና ዝውውር',
    'duty.on_duty': 'ዛሬ ተረኛ ነው',
    'duty.off_duty': 'ዛሬ ተረኛ አይደለም',
    'duty.admin_override': 'የአስተዳዳሪ ቀጥተኛ ፈቃድ ንቁ ነው',
    'duty.next_turn': 'ቀጣይ ተረኛ ፈረቃ',
    'users.title': 'የተጠቃሚዎች አስተዳደርና መዳረሻ',
    'users.subtitle': 'የተጠቃሚ ምስክርነቶችን፣ የSpatie ሚናዎችንና ሁኔታን ያቀናብሩ',
    'users.add': 'አዲስ ተጠቃሚ መዝግብ',
    'roles.title': 'ሚናዎችና የተጠቃሚ ፈቃዶች',
    'roles.subtitle': 'በSpatie Laravel Permission የተዋቀረ የተዋረድ ቁጥጥር',
    'roles.create': 'አዲስ ሚና ፍጠር',
    'books.title': 'የዩኒቨርሲቲ ቤተ-መጻሕፍት ማውጫ',
    'books.add': 'መጽሐፍ መዝግብ',
    'circulation.title': 'የመጻሕፍት ብድርና ዝውውር',
    'circulation.issue': 'መጽሐፍ አበድር',
    'circulation.return': 'የተበደረውን መጽሐፍ ተቀበል',
};

export const useLanguageStore = defineStore('language', () => {
    const savedLang = Cookies.get('lang') || localStorage.getItem('app_locale') || 'en';
    const currentLanguage = ref<string>(savedLang);
    const availableLanguages = ref<LanguageOption[]>([
        { key: 'en', name: 'English', label: 'English', icon: '🇬🇧' },
        { key: 'am', name: 'Amharic', label: 'አማርኛ', icon: '🇪🇹' },
    ]);
    const translations = ref<Record<string, string>>(savedLang === 'am' ? DEFAULT_AMHARIC : DEFAULT_ENGLISH);
    const translationCache = ref<Record<string, Record<string, string>>>({
        en: DEFAULT_ENGLISH,
        am: DEFAULT_AMHARIC,
    });
    const isLoading = ref<boolean>(false);

    // Sync HTML lang attribute
    watch(
        currentLanguage,
        (newLang) => {
            Cookies.set('lang', newLang, { expires: 365 });
            localStorage.setItem('app_locale', newLang);
            if (typeof document !== 'undefined') {
                document.documentElement.setAttribute('lang', newLang);
            }
        },
        { immediate: true }
    );

    const loadTranslations = async (lang: string = currentLanguage.value) => {
        if (translationCache.value[lang] && Object.keys(translationCache.value[lang]).length > 10) {
            translations.value = translationCache.value[lang];
        }

        isLoading.value = true;
        try {
            const res = await api.get<{ success: boolean; translations: Record<string, string> }>(`/translations/front/${lang}`);
            if (res.data?.translations) {
                translations.value = res.data.translations;
                translationCache.value[lang] = res.data.translations;
            }
        } catch {
            // fallback to default dictionary
            translations.value = lang === 'am' ? DEFAULT_AMHARIC : DEFAULT_ENGLISH;
        } finally {
            isLoading.value = false;
        }
    };

    const setLanguage = async (lang: string) => {
        if (currentLanguage.value === lang && Object.keys(translations.value).length > 0) return;
        currentLanguage.value = lang;
        translations.value = lang === 'am' ? DEFAULT_AMHARIC : DEFAULT_ENGLISH;
        await loadTranslations(lang);
    };

    const t = (key: string, fallback?: string): string => {
        return translations.value[key] || fallback || key;
    };

    return {
        currentLanguage,
        availableLanguages,
        translations,
        isLoading,
        loadTranslations,
        setLanguage,
        t,
        tr: t,
    };
});
