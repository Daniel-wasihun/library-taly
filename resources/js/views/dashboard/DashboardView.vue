<template>
  <div class="space-y-6 w-full max-w-7xl mx-auto pb-10">
    <!-- Institutional Command Center Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-card-bg border border-card-border rounded-md p-4 sm:p-5 shadow-2xs">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg sm:text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
            <LayoutDashboard class="w-5 h-5 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('nav.dashboard', 'Dashboard') }}</span>
          </h2>
          <span class="text-[10px] font-mono px-2 py-0.5 rounded font-bold uppercase bg-brand-blue/10 text-brand-blue border border-brand-blue/20">
            {{ langStore.isAmharic() ? 'ወልድያ ዩኒቨርሲቲ' : 'Woldia University' }}
          </span>
        </div>
        <p class="text-xs text-main-text/60 mt-1 flex items-center gap-2 flex-wrap">
          <span class="font-mono font-medium text-brand-blue dark:text-brand-yellow">{{ todayDate }}</span>
          <span>·</span>
          <span>{{ langStore.t('app.tagline', 'Academic Excellence & Automated Duty Enforcement') }}</span>
        </p>
      </div>

      <!-- Quick Action Navigation Hub -->
      <div class="flex items-center gap-2 flex-wrap">
        <router-link
          v-if="authStore.canRecordAttendance"
          to="/attendance"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold bg-brand-blue text-white hover:bg-blue-900 shadow-2xs transition active:scale-95 cursor-pointer"
        >
          <Activity class="w-3.5 h-3.5" />
          <span>{{ langStore.t('attendance.live_desk', 'Live Counter') }}</span>
        </router-link>

        <router-link
          v-if="authStore.canManageAttendanceAnalytics"
          to="/attendance"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold border border-card-border bg-card-bg hover:bg-nav-accent/10 hover:border-nav-accent text-main-text shadow-2xs transition active:scale-95 cursor-pointer"
        >
          <TrendingUp class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('attendance.analytics', 'Analytics & Reports') }}</span>
        </router-link>

        <router-link
          to="/my-turns"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold border border-card-border bg-card-bg hover:bg-nav-accent/10 hover:border-nav-accent text-main-text shadow-2xs transition active:scale-95 cursor-pointer"
        >
          <Clock class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('nav.my_turns', 'My Shifts') }}</span>
        </router-link>
      </div>
    </div>

    <!-- CRITICAL SHIFT INACTIVITY ALERT BANNER (> 30 Mins Inactive) -->
    <div
      v-if="stats?.inactivity_alerts?.has_active_incidents && (authStore.isAdmin || authStore.canManageAttendanceAnalytics)"
      class="rounded-md border p-4 sm:p-5 bg-rose-500/10 dark:bg-rose-950/30 border-rose-500/40 text-rose-950 dark:text-rose-100 shadow-sm space-y-4"
    >
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-rose-500/20 pb-3">
        <div class="flex items-start gap-3">
          <div class="p-2 rounded-md bg-rose-500/20 text-rose-700 dark:text-rose-400 shrink-0 mt-0.5">
            <AlertOctagon class="w-6 h-6 animate-pulse" />
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="text-sm sm:text-base font-extrabold text-rose-800 dark:text-rose-300 tracking-tight flex items-center gap-1.5">
                <span>{{ langStore.isAmharic() ? 'የፈረቃ ሠራተኞች የሥራ ማቆም ክትትል ማስጠንቀቂያ (>30 ደቂቃ)' : 'Critical Compliance Alert: Attendant Shift Inactivity (>30 Mins)' }}</span>
              </h3>
              <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-600 text-white shadow-2xs">
                {{ stats.inactivity_alerts.incident_count }} {{ langStore.isAmharic() ? 'ቅርንጫፎች ተቋርጠዋል' : 'Branches Inactive' }}
              </span>
            </div>
            <p class="text-xs text-rose-700/90 dark:text-rose-300/80 mt-1">
              <strong>{{ stats.inactivity_alerts.active_shift?.name }}</strong> ({{ stats.inactivity_alerts.active_shift?.time_window }} / {{ stats.inactivity_alerts.active_shift?.time_window_ethiopian }}) · {{ stats.inactivity_alerts.active_shift?.group_name }} —
              {{ langStore.isAmharic() ? 'በቤተ-መጻሕፍት ደንብ መሠረት፣ በቅርንጫፍ ከተመደቡት ሠራተኞች መካከል ቢያንስ አንዱ በ30 ደቂቃ ውስጥ ሲቆጥር ቅርንጫፉ ንቁ እንደሆነ ይቆጠራል። ከዚህ በታች የተዘረዘሩት ቅርንጫፎች የተጠቆሙት ማናቸውም በፈረቃው የተመደቡ ሠራተኞች ላለፉት ከ 30 ደቂቃ በላይ ምንም ዓይነት የተጠቃሚ ቆጠራ ስላላከናወኑ ብቻ ነው።' : 'Per library policy, if ANY attendant on duty records visitor counts within 30 minutes, the branch is compliant. The branches below are currently flagged because NONE of their scheduled attendants have recorded any counts for over 30 consecutive minutes.' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            @click="openInactivityModal"
            type="button"
            class="px-3 py-1.5 rounded-md text-xs font-bold bg-rose-700 hover:bg-rose-800 text-white shadow-2xs transition flex items-center gap-1.5 cursor-pointer active:scale-95"
          >
            <FileText class="w-3.5 h-3.5" />
            <span>{{ langStore.isAmharic() ? 'ይፋዊ የኦዲት ሪፖርት' : 'Inactivity Audit Report' }}</span>
          </button>
          <a
            :href="printAuditUrl"
            target="_blank"
            class="px-3 py-1.5 rounded-md text-xs font-bold border border-rose-500/40 bg-card-bg hover:bg-rose-500/10 text-rose-800 dark:text-rose-200 shadow-2xs transition flex items-center gap-1.5 cursor-pointer active:scale-95"
          >
            <Printer class="w-3.5 h-3.5" />
            <span>{{ langStore.isAmharic() ? 'አትም' : 'Print Document' }}</span>
          </a>
        </div>
      </div>

      <!-- Inactive Branches Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <div
          v-for="alert in stats.inactivity_alerts.alerts"
          :key="alert.branch_id"
          class="p-3.5 rounded-md bg-card-bg border border-rose-500/30 dark:bg-card-bg/80 space-y-2.5 shadow-2xs"
        >
          <div class="flex items-center justify-between gap-2 border-b border-card-border pb-2">
            <div>
              <h4 class="font-bold text-xs text-main-text flex items-center gap-1">
                <Building2 class="w-3.5 h-3.5 text-brand-blue" />
                <span>{{ langStore.isAmharic() ? alert.branch_name_am : alert.branch_name }}</span>
              </h4>
              <span class="text-[9px] font-mono text-main-text/50">Code: [{{ alert.branch_code }}]</span>
            </div>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
              ⏱️ {{ alert.inactive_duration_human }} inactive
            </span>
          </div>

          <div class="text-[11px] space-y-1 bg-main-bg/50 p-2 rounded border border-card-border/60">
            <div class="flex items-center justify-between text-[10px] text-main-text/70">
              <span>{{ langStore.isAmharic() ? 'የማቆም ክፍተት (የፈረንጅ)' : 'Inactive Interval (EAT)' }}:</span>
              <strong class="font-mono text-rose-700 dark:text-rose-300">{{ alert.interval_formatted }}</strong>
            </div>
            <div class="flex items-center justify-between text-[10px] text-main-text/70">
              <span>{{ langStore.isAmharic() ? 'የማቆም ክፍተት (የኢትዮጵያ)' : 'Inactive Interval (Eth)' }}:</span>
              <strong class="text-emerald-700 dark:text-emerald-400">{{ alert.interval_ethiopian }}</strong>
            </div>
            <div class="flex items-center justify-between text-[10px] text-main-text/70">
              <span>{{ langStore.isAmharic() ? 'የተመዘገበ ተጠቃሚ' : 'Logged this shift' }}:</span>
              <strong class="font-mono">{{ alert.total_entries_recorded }}</strong>
            </div>
          </div>

          <!-- On-Duty Attendants who are Not Working -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">
              <span>{{ langStore.isAmharic() ? 'በፈረቃው የተመደቡ ሠራተኞች' : 'Scheduled Attendants On Duty' }} ({{ alert.attendants.length }}):</span>
              <span class="text-[9px] font-semibold text-rose-600 dark:text-rose-400">🔴 {{ langStore.isAmharic() ? 'አልሰሩም' : 'Not Counting' }}</span>
            </div>
            <div class="grid grid-cols-1 gap-1">
              <div
                v-for="att in alert.attendants"
                :key="att.id"
                class="flex items-center justify-between p-1.5 rounded text-[11px] bg-rose-500/8 dark:bg-rose-950/20 border border-rose-500/20"
              >
                <div class="flex items-center gap-1.5 truncate">
                  <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0"></span>
                  <span class="font-bold text-main-text truncate">{{ att.name }}</span>
                  <span class="text-[10px] text-main-text/50 font-mono">(@{{ att.username }})</span>
                </div>
                <span class="text-[9px] text-main-text/60 font-mono truncate max-w-[120px]">{{ att.phone && att.phone !== '—' ? att.phone : att.email }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ACTIVE ON-DUTY BRANCHES & WORKING ATTENDANTS (GREEN COMPLIANCE CARD) -->
    <div
      v-if="stats?.inactivity_alerts?.has_active_branches && (authStore.isAdmin || authStore.canManageAttendanceAnalytics || authStore.canViewDashboard)"
      class="rounded-md border p-4 sm:p-5 bg-emerald-500/10 dark:bg-emerald-950/30 border-emerald-500/40 text-emerald-950 dark:text-emerald-100 shadow-sm space-y-4"
    >
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-emerald-500/20 pb-3">
        <div class="flex items-start gap-3">
          <div class="p-2 rounded-md bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 shrink-0 mt-0.5">
            <CheckCircle2 class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="text-sm sm:text-base font-extrabold text-emerald-800 dark:text-emerald-300 tracking-tight flex items-center gap-1.5">
                <span>{{ langStore.isAmharic() ? 'ንቁ እና ተጠቃሚዎችን በመመዝገብ ላይ ያሉ ቅርንጫፎች' : 'Active On-Duty Branches & Attendant Duty Execution' }}</span>
              </h3>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-600 text-white shadow-2xs">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
                </span>
                <span>{{ stats.inactivity_alerts.active_branches_count }} {{ langStore.isAmharic() ? 'ቅርንጫፍ ንቁ ነው' : 'Branches Active & Counting' }}</span>
              </span>
            </div>
            <p class="text-xs text-emerald-850 dark:text-emerald-300/80 mt-1">
              <strong>{{ stats.inactivity_alerts.active_shift?.name }}</strong> ({{ stats.inactivity_alerts.active_shift?.time_window }} / {{ stats.inactivity_alerts.active_shift?.time_window_ethiopian }}) · {{ stats.inactivity_alerts.active_shift?.group_name }} —
              {{ langStore.isAmharic() ? 'የተመደቡት ሠራተኞች የተጠቃሚዎችን ቆጠራ በወቅቱ እያከናወኑ ያሉባቸው ቅርንጫፎች ዝርዝር እና አሁን እየመዘገቡ ያሉ ሠራተኞች።' : 'Branches where assigned attendants are actively recording patron usage within the 30-minute threshold. The attendants currently operating the counter are listed below.' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <router-link
            to="/attendance"
            class="px-3 py-1.5 rounded-md text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white shadow-2xs transition flex items-center gap-1.5 cursor-pointer active:scale-95"
          >
            <Activity class="w-3.5 h-3.5" />
            <span>{{ langStore.isAmharic() ? 'ቀጥታ ቆጣሪ' : 'Live Counter' }}</span>
          </router-link>
        </div>
      </div>

      <!-- Active Branches Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <div
          v-for="branch in stats.inactivity_alerts.active_branches"
          :key="branch.branch_id"
          class="p-3.5 rounded-md bg-card-bg border border-emerald-500/30 dark:bg-card-bg/80 space-y-3 shadow-2xs"
        >
          <!-- Branch Header & Live Status -->
          <div class="flex items-center justify-between gap-2 border-b border-card-border pb-2">
            <div>
              <h4 class="font-bold text-xs text-main-text flex items-center gap-1.5">
                <Building2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                <span>{{ langStore.isAmharic() ? branch.branch_name_am : branch.branch_name }}</span>
              </h4>
              <span class="text-[9px] font-mono text-main-text/50">Code: [{{ branch.branch_code }}]</span>
            </div>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              <span>{{ branch.last_entry_minutes_ago === 0 ? (langStore.isAmharic() ? 'አሁን' : 'Just now') : `${branch.last_entry_minutes_ago}m ago` }}</span>
            </span>
          </div>

          <!-- Activity Metrics Banner -->
          <div class="text-[11px] space-y-1.5 bg-main-bg/50 p-2.5 rounded border border-card-border/60">
            <div class="flex items-center justify-between text-[10px] text-main-text/70">
              <span class="flex items-center gap-1">
                <Clock class="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                <span>{{ langStore.isAmharic() ? 'የመጨረሻ ቆጠራ (የፈረንጅ/ኢት)' : 'Last Recorded (EAT / Eth)' }}:</span>
              </span>
              <strong class="font-mono text-emerald-700 dark:text-emerald-300">
                {{ branch.last_entry_formatted }} ({{ branch.last_entry_ethiopian }})
              </strong>
            </div>
            <div class="flex items-center justify-between text-[10px] text-main-text/70">
              <span class="flex items-center gap-1">
                <Users class="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                <span>{{ langStore.isAmharic() ? 'በዚህ ፈረቃ የተቆጠሩ ተጠቃሚዎች' : 'Patrons Counted this Shift' }}:</span>
              </span>
              <strong class="font-mono text-main-text bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/20">
                {{ branch.total_entries_recorded }} {{ langStore.isAmharic() ? 'ሰዎች' : 'patrons' }} ({{ branch.total_batches_count }} {{ langStore.isAmharic() ? 'ጊዜ' : 'batches' }})
              </strong>
            </div>
          </div>

          <!-- Active Working Attendant(s) - The Person(s) Currently Doing the Counting -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
              <span class="flex items-center gap-1">
                <UserCheck class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                <span>{{ langStore.isAmharic() ? 'አሁን እየመዘገቡ ያሉ ሠራተኞች' : 'Attendant(s) Actively Logging' }} ({{ branch.working_attendants.length }}):</span>
              </span>
              <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                🟢 {{ langStore.isAmharic() ? 'ንቁ ሠራተኛ' : 'Active Operator' }}
              </span>
            </div>

            <div class="space-y-1.5">
              <div
                v-for="att in branch.working_attendants"
                :key="att.id"
                class="p-2 rounded text-[11px] bg-emerald-500/10 dark:bg-emerald-950/25 border border-emerald-500/25 space-y-1"
              >
                <div class="flex items-center justify-between gap-1.5">
                  <div class="flex items-center gap-1.5 truncate">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                    <span class="font-extrabold text-main-text truncate">{{ att.name }}</span>
                    <span class="text-[10px] text-main-text/60 font-mono">(@{{ att.username }})</span>
                  </div>
                  <span class="text-[9px] font-bold font-mono px-1.5 py-0.5 rounded bg-emerald-600 text-white shrink-0">
                    {{ att.counts_logged }} {{ langStore.isAmharic() ? 'ተጠቃሚ' : 'patrons' }}
                  </span>
                </div>

                <div class="flex items-center justify-between text-[10px] text-main-text/60 pt-0.5 border-t border-emerald-500/15">
                  <span class="font-mono truncate max-w-[140px]">{{ att.phone && att.phone !== '—' ? att.phone : att.email }}</span>
                  <span class="text-emerald-700 dark:text-emerald-400 font-medium">
                    {{ langStore.isAmharic() ? 'የመጨረሻ' : 'Last' }}: {{ att.last_entry_formatted }} ({{ att.last_entry_minutes_ago }}m ago)
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Other Scheduled Attendants on Duty in this Branch -->
          <div v-if="branch.other_scheduled_attendants?.length" class="pt-1.5 border-t border-card-border/60">
            <div class="text-[9px] text-main-text/50 flex items-center justify-between">
              <span>{{ langStore.isAmharic() ? 'ሌሎች በፈረቃው የተመደቡ ሠራተኞች' : 'Other Scheduled on Duty' }} ({{ branch.other_scheduled_attendants.length }}):</span>
              <span class="text-emerald-600 dark:text-emerald-400 font-semibold text-[8.5px]">✓ {{ langStore.isAmharic() ? 'ቅርንጫፉ ንቁ ነው' : 'Branch Compliant' }}</span>
            </div>
            <div class="flex flex-wrap gap-1 mt-1">
              <span
                v-for="otherAtt in branch.other_scheduled_attendants"
                :key="otherAtt.id"
                class="px-1.5 py-0.5 rounded text-[9px] bg-main-bg border border-card-border/80 text-main-text/70 font-mono truncate"
                :title="`${otherAtt.name} (@${otherAtt.username}) - ${otherAtt.phone}`"
              >
                {{ otherAtt.name }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Duty Turn Notification Banner (For Non-Admin Attendants when Off-Duty) -->
    <div
      v-if="!authStore.isAdmin && !authStore.canLogAttendance"
      class="rounded-md border p-4 flex items-start gap-3 bg-amber-500/10 dark:bg-amber-950/40 border-amber-500/30 text-amber-800 dark:text-amber-200"
    >
      <AlertTriangle class="w-5 h-5 text-amber-600 dark:text-brand-yellow shrink-0 mt-0.5" />
      <div class="text-xs">
        <p class="font-bold text-sm">{{ langStore.t('duty.restricted_notice', 'Desk Logging Restricted — Off Duty Today') }}</p>
        <p class="mt-0.5 opacity-80" v-if="stats?.current_user_duty?.reason">
          {{ stats.current_user_duty.reason }}
        </p>
        <p class="mt-1 font-semibold text-amber-700 dark:text-brand-yellow" v-if="stats?.current_user_duty?.next_turn">
          {{ langStore.t('duty.next_turn', 'Your next turn') }}: <span class="font-mono underline">{{ stats.current_user_duty.next_turn }}</span>
        </p>
      </div>
    </div>

    <!-- Metric Insight Cards (6 Core Performance Indicators) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <!-- Total Patrons Today -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-main-text/50 uppercase tracking-wider">
          <span>{{ langStore.t('attendance.today_total', 'Today Patrons') }}</span>
          <Activity class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
        </div>
        <div class="text-2xl font-bold text-brand-blue dark:text-brand-yellow font-mono">
          {{ loading ? '—' : (stats?.stats?.today_total ?? 0).toLocaleString() }}
        </div>
        <div class="text-[10px] text-main-text/50 flex items-center gap-1 font-mono">
          <span>7-day:</span>
          <strong class="text-main-text">{{ loading ? '—' : (stats?.stats?.week_total ?? 0).toLocaleString() }}</strong>
        </div>
      </div>

      <!-- Students -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
          <span>{{ langStore.localizeType('student') }}</span>
          <GraduationCap class="w-3.5 h-3.5" />
        </div>
        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">
          {{ loading ? '—' : (stats?.stats?.today_student ?? 0).toLocaleString() }}
        </div>
        <div class="text-[10px] text-emerald-700/70 dark:text-emerald-300/70 font-mono">
          {{ stats?.stats?.student_percent ?? 0 }}% {{ langStore.t('attendance.of_traffic', 'of total') }}
        </div>
      </div>

      <!-- Faculty / Researchers -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
          <span>{{ langStore.localizeType('teacher') }}</span>
          <BookOpen class="w-3.5 h-3.5" />
        </div>
        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 font-mono">
          {{ loading ? '—' : (stats?.stats?.today_teacher ?? 0).toLocaleString() }}
        </div>
        <div class="text-[10px] text-blue-700/70 dark:text-blue-300/70 font-mono">
          {{ stats?.stats?.teacher_percent ?? 0 }}% {{ langStore.t('attendance.of_traffic', 'of total') }}
        </div>
      </div>

      <!-- Administrative Staff -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider">
          <span>{{ langStore.localizeType('staff') }}</span>
          <Briefcase class="w-3.5 h-3.5" />
        </div>
        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 font-mono">
          {{ loading ? '—' : (stats?.stats?.today_staff ?? 0).toLocaleString() }}
        </div>
        <div class="text-[10px] text-purple-700/70 dark:text-purple-300/70 font-mono">
          {{ stats?.stats?.staff_percent ?? 0 }}% {{ langStore.t('attendance.of_traffic', 'of total') }}
        </div>
      </div>

      <!-- Campus Branches -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-main-text/50 uppercase tracking-wider">
          <span>{{ langStore.t('attendance.branches', 'Branches') }}</span>
          <Building2 class="w-3.5 h-3.5 text-brand-blue" />
        </div>
        <div class="text-2xl font-bold text-main-text font-mono">
          {{ loading ? '—' : (stats?.stats?.total_branches ?? 0) }}
        </div>
        <div class="text-[10px] text-main-text/50 truncate">
          {{ langStore.isAmharic() ? 'ከፍተኛ:' : 'Top:' }} <span class="font-bold text-brand-blue truncate">{{ stats?.stats?.busiest_branch ?? 'Natural' }}</span>
        </div>
      </div>

      <!-- Active Staff On Shift Today -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
          <span>{{ langStore.t('duty.shifts_today', 'On Duty Staff') }}</span>
          <ShieldCheck class="w-3.5 h-3.5 text-emerald-500" />
        </div>
        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">
          {{ loading ? '—' : (stats?.stats?.current_shift_staff_count ?? stats?.stats?.on_duty_staff_today?.length ?? 0) }}
        </div>
        <div class="text-[10px] text-emerald-700/70 dark:text-emerald-300/70 font-mono">
          {{ langStore.isAmharic() ? `${stats?.stats?.current_shift_staff_count ?? 0} አሁን በስራ ላይ (${stats?.stats?.total_staff_on_duty_today ?? 0} ዛሬ)` : `${stats?.stats?.current_shift_staff_count ?? 0} active now (${stats?.stats?.total_staff_on_duty_today ?? 0} today)` }}
        </div>
      </div>
    </div>

    <!-- Mid-Tier Analytics: Demographic Split & Branch Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Demographic Composition & Gender Proportions -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between border-b border-card-border pb-3">
          <h3 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <BarChart3 class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.demographic_breakdown', 'Patron Demographic Segmentation') }}</span>
          </h3>
          <span class="text-[10px] font-mono text-main-text/50">
            {{ langStore.isAmharic() ? 'የዛሬ የቀጥታ ምጣኔ' : "Today's Live Proportions" }}
          </span>
        </div>

        <!-- Gender Proportional Split Bar -->
        <div class="space-y-1.5">
          <div class="flex justify-between text-[11px] font-bold">
            <span class="text-brand-blue">
              {{ langStore.localizeGender('male') }}: {{ stats?.stats?.today_male ?? 0 }} ({{ stats?.stats?.male_percent ?? 50 }}%)
            </span>
            <span class="text-amber-600 dark:text-brand-yellow">
              {{ langStore.localizeGender('female') }}: {{ stats?.stats?.today_female ?? 0 }} ({{ stats?.stats?.female_percent ?? 50 }}%)
            </span>
          </div>
          <div class="w-full h-2.5 rounded-full overflow-hidden flex bg-slate-100 dark:bg-slate-800 shadow-inner">
            <div
              class="bg-brand-blue h-full transition-all duration-700"
              :style="{ width: `${stats?.stats?.male_percent ?? 50}%` }"
            ></div>
            <div
              class="bg-brand-yellow h-full transition-all duration-700"
              :style="{ width: `${stats?.stats?.female_percent ?? 50}%` }"
            ></div>
          </div>
        </div>

        <!-- Patron Type Distribution Progress Bars -->
        <div class="space-y-3 pt-2">
          <!-- Students -->
          <div class="space-y-1">
            <div class="flex justify-between text-xs">
              <span class="font-bold text-main-text flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                {{ langStore.localizeType('student') }}
              </span>
              <span class="font-mono text-main-text/70">
                <strong>{{ (stats?.stats?.today_student ?? 0).toLocaleString() }}</strong> ({{ stats?.stats?.student_percent ?? 0 }}%)
              </span>
            </div>
            <div class="w-full h-1.5 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              <div
                class="bg-emerald-500 h-full rounded-full transition-all duration-500"
                :style="{ width: `${stats?.stats?.student_percent ?? 0}%` }"
              ></div>
            </div>
          </div>

          <!-- Teachers / Faculty -->
          <div class="space-y-1">
            <div class="flex justify-between text-xs">
              <span class="font-bold text-main-text flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                {{ langStore.localizeType('teacher') }}
              </span>
              <span class="font-mono text-main-text/70">
                <strong>{{ (stats?.stats?.today_teacher ?? 0).toLocaleString() }}</strong> ({{ stats?.stats?.teacher_percent ?? 0 }}%)
              </span>
            </div>
            <div class="w-full h-1.5 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              <div
                class="bg-blue-500 h-full rounded-full transition-all duration-500"
                :style="{ width: `${stats?.stats?.teacher_percent ?? 0}%` }"
              ></div>
            </div>
          </div>

          <!-- Staff -->
          <div class="space-y-1">
            <div class="flex justify-between text-xs">
              <span class="font-bold text-main-text flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                {{ langStore.localizeType('staff') }}
              </span>
              <span class="font-mono text-main-text/70">
                <strong>{{ (stats?.stats?.today_staff ?? 0).toLocaleString() }}</strong> ({{ stats?.stats?.staff_percent ?? 0 }}%)
              </span>
            </div>
            <div class="w-full h-1.5 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              <div
                class="bg-purple-500 h-full rounded-full transition-all duration-500"
                :style="{ width: `${stats?.stats?.staff_percent ?? 0}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Campus Library Facilities Foot-Traffic Breakdown -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between border-b border-card-border pb-3">
          <h3 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <Building2 class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.branch_comparison', 'Campus Facilities Foot-Traffic') }}</span>
          </h3>
          <router-link
            to="/libraries"
            class="text-[11px] font-semibold text-brand-blue dark:text-brand-yellow hover:underline"
          >
            {{ langStore.isAmharic() ? 'ሁሉንም ቅርንጫፎች ይመልከቱ →' : 'Manage Branches →' }}
          </router-link>
        </div>

        <div v-if="loading" class="space-y-3">
          <div v-for="i in 3" :key="i" class="h-10 bg-slate-100 dark:bg-slate-800/60 rounded animate-pulse"></div>
        </div>

        <div v-else-if="!stats?.stats?.branches_traffic?.length" class="py-8 text-center text-xs text-main-text/40">
          {{ langStore.t('common.no_data', 'No branch traffic logged yet today.') }}
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="branch in stats?.stats?.branches_traffic"
            :key="branch.id"
            class="p-2.5 rounded border border-card-border/60 hover:bg-main-bg/50 transition space-y-1.5"
          >
            <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-2 min-w-0">
                <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-brand-blue/10 text-brand-blue border border-brand-blue/20 shrink-0">
                  {{ branch.code }}
                </span>
                <span class="font-bold text-main-text truncate">
                  {{ langStore.isAmharic() ? (branch.name_am || branch.name) : branch.name }}
                </span>
              </div>
              <div class="font-mono text-xs text-right shrink-0">
                <strong class="text-brand-blue dark:text-brand-yellow">{{ branch.total }}</strong>
                <span class="text-main-text/50 ml-1">({{ branch.percent }}%)</span>
              </div>
            </div>
            <div class="w-full h-1.5 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              <div
                class="bg-brand-blue dark:bg-brand-yellow h-full rounded-full transition-all duration-500"
                :style="{ width: `${branch.percent}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Tier: Live Gate Activity Feed & Today's Duty Deployment -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Recent Patron Usage Feed (2 Cols) -->
      <div class="lg:col-span-2 bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between border-b border-card-border pb-3">
          <h3 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <Activity class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.recent_entries', 'Recent Usage Entries') }}</span>
          </h3>
          <router-link
            to="/attendance"
            class="text-xs font-semibold text-brand-blue dark:text-brand-yellow hover:underline"
          >
            {{ langStore.t('common.view_all', 'Open Live Counter →') }}
          </router-link>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 5" :key="i" class="h-12 bg-slate-100 dark:bg-slate-800/60 rounded animate-pulse" />
        </div>

        <div v-else-if="!stats?.recent_entries?.length" class="py-10 text-center text-xs text-main-text/40">
          {{ langStore.t('common.no_records', 'No attendance entries logged yet today.') }}
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="entry in stats?.recent_entries"
            :key="entry.id"
            class="flex items-center justify-between gap-3 p-2.5 rounded border border-card-border/60 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition text-xs"
          >
            <div class="flex items-center gap-3 min-w-0">
              <span
                class="w-8 h-8 rounded flex items-center justify-center font-bold text-xs shrink-0 font-mono shadow-2xs"
                :class="entry.gender === 'male'
                  ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                  : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'"
              >
                +{{ entry.visitor_count }}
              </span>

              <div class="min-w-0">
                <p class="font-bold text-main-text truncate flex items-center gap-2">
                  <span class="capitalize">{{ langStore.localizeGender(entry.gender) }}</span>
                  <span class="text-main-text/40 font-normal">·</span>
                  <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border">
                    {{ langStore.localizeType(entry.user_type) }}
                  </span>
                  <span v-if="entry.library" class="text-[11px] text-main-text/60 truncate">
                    @ {{ langStore.localizeBranch(entry.library) }}
                  </span>
                </p>
                <p class="text-[10px] text-main-text/50 truncate mt-0.5">
                  {{ langStore.t('attendance.logged_by', 'Logged by') }} {{ entry.staff?.name ?? 'Desk Staff' }} · {{ formatTime12(entry.entry_at) }}
                </p>
              </div>
            </div>

            <span class="text-[11px] text-main-text/50 font-mono shrink-0">
              {{ formatDate(entry.entry_at) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Today's Duty Shifts & Group Deployments (1 Col) -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between border-b border-card-border pb-3">
          <div class="flex items-center gap-2">
            <Users class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
            <h3 class="text-xs font-bold text-main-text uppercase tracking-wider">
              {{ langStore.t('duty.shifts_today', "Today's Duty Shifts & Groups") }}
            </h3>
          </div>
          <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
            {{ stats?.stats?.current_shift_staff_count ?? 0 }} Active Now ({{ stats?.stats?.total_staff_on_duty_today ?? 0 }} Total)
          </span>
        </div>

        <div v-if="loading" class="space-y-3">
          <div v-for="i in 2" :key="i" class="h-24 bg-slate-100 dark:bg-slate-800/60 rounded animate-pulse" />
        </div>

        <div v-else-if="!stats?.stats?.today_shift_groups?.length && !stats?.stats?.on_duty_staff_today?.length" class="py-10 text-center space-y-2">
          <CalendarOff class="w-8 h-8 text-main-text/30 mx-auto" />
          <p class="text-xs text-main-text/40">
            {{ langStore.isAmharic() ? 'ለዛሬ የተመደበ የቤተ-መጻሕፍት ተረኛ ፈረቃ የለም።' : 'No shift groups scheduled on duty today.' }}
          </p>
        </div>

        <!-- Shift Groups Cards -->
        <div v-else class="space-y-3">
          <div
            v-for="shiftGroup in stats?.stats?.today_shift_groups"
            :key="`${shiftGroup.shift_id}-${shiftGroup.group_id}`"
            class="rounded-md border transition-all duration-200 overflow-hidden cursor-pointer"
            :class="shiftGroup.is_current_shift
              ? 'border-emerald-500/40 bg-emerald-500/5 dark:bg-emerald-950/20 shadow-2xs'
              : 'border-card-border bg-card-bg/60 hover:border-card-border/90'"
            @click="toggleShiftExpand(shiftGroup.shift_id)"
          >
            <!-- Card Header -->
            <div class="p-3 space-y-2">
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="text-sm font-bold text-main-text">
                    {{ langStore.isAmharic() ? shiftGroup.shift_name_am : shiftGroup.shift_name }}
                  </span>
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-brand-blue/10 text-brand-blue dark:bg-brand-yellow/15 dark:text-brand-yellow border border-brand-blue/20">
                    {{ shiftGroup.group_name }}
                  </span>
                </div>
                <span
                  class="px-2 py-0.5 rounded text-[9.5px] font-bold uppercase flex items-center gap-1 shrink-0"
                  :class="shiftGroup.is_current_shift
                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30'
                    : 'bg-slate-500/10 text-main-text/60 border border-card-border'"
                >
                  <span v-if="shiftGroup.is_current_shift" class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  {{ shiftGroup.is_current_shift ? (langStore.isAmharic() ? 'አሁን በስራ ላይ' : 'Active Now') : (langStore.isAmharic() ? 'የቀጣይ ፈረቃ' : 'Later Today') }}
                </span>
              </div>

              <div class="flex items-center justify-between text-[11px] text-main-text/60 font-mono">
                <span>🕒 {{ shiftGroup.time_window }} ({{ shiftGroup.ethiopian_time_window }})</span>
                <span class="font-bold text-main-text">{{ shiftGroup.total_staff_count }} {{ langStore.isAmharic() ? 'ሠራተኞች' : 'staff' }}</span>
              </div>

              <!-- Click prompt -->
              <div class="flex items-center justify-between pt-1 border-t border-card-border/60 text-[10px] text-brand-blue dark:text-brand-yellow font-semibold">
                <span>{{ expandedShiftId === shiftGroup.shift_id ? (langStore.isAmharic() ? 'ዝርዝሩን ደብቅ ▲' : 'Hide Staff ▲') : (langStore.isAmharic() ? 'በስራ ላይ ያሉትን ሠራተኞች ተመልከት ▼' : 'Click to view attendants on duty ▼') }}</span>
                <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': expandedShiftId === shiftGroup.shift_id }" />
              </div>
            </div>

            <!-- Expanded Attendants List by Branch -->
            <div
              v-if="expandedShiftId === shiftGroup.shift_id"
              class="border-t border-card-border/80 bg-main-bg/50 p-3 space-y-3 text-xs"
              @click.stop
            >
              <div
                v-for="branch in shiftGroup.branches"
                :key="branch.branch_id"
                class="space-y-1.5"
              >
                <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-main-text/70 border-b border-card-border pb-1">
                  <span class="flex items-center gap-1">
                    <Building2 class="w-3 h-3 text-brand-blue" />
                    {{ langStore.isAmharic() ? branch.branch_name_am : branch.branch_name }} [{{ branch.branch_code }}]
                  </span>
                  <span>{{ branch.attendants.length }} {{ langStore.isAmharic() ? 'ተረኞች' : 'on duty' }}</span>
                </div>

                <div class="grid grid-cols-1 gap-1">
                  <div
                    v-for="att in branch.attendants"
                    :key="att.user_id"
                    class="flex items-center justify-between p-1.5 rounded text-[11px] bg-card-bg border border-card-border/80 hover:border-brand-blue/30 transition"
                  >
                    <div class="flex items-center gap-1.5 truncate">
                      <div class="w-5 h-5 rounded-full bg-brand-blue/10 text-brand-blue dark:text-brand-yellow font-bold flex items-center justify-center text-[9px] shrink-0">
                        {{ att.user_name.charAt(0) }}
                      </div>
                      <span class="font-bold text-main-text truncate">{{ att.user_name }}</span>
                      <span class="text-[10px] text-main-text/50 font-mono">(@{{ att.user_username }})</span>
                    </div>
                    <span class="text-[9px] text-main-text/50 font-mono truncate max-w-[100px]">{{ att.phone !== '—' ? att.phone : att.email }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Shift Inactivity Compliance Audit Report Modal -->
    <Modal
      :show="showInactivityModal"
      :title="langStore.isAmharic() ? 'የፈረቃ ሠራተኞች የሥራ ማቆም ኦዲት ሪፖርት' : 'Attendant Shift Inactivity & Compliance Audit Report'"
      size="xl"
      @close="showInactivityModal = false"
    >
      <div class="space-y-4 text-xs">
        <!-- Filter bar -->
        <div class="flex flex-wrap items-center justify-between gap-3 bg-main-bg p-3 rounded-md border border-card-border">
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5">
              <span class="font-bold text-main-text/70">{{ langStore.isAmharic() ? 'ቀን' : 'Date' }}:</span>
              <input
                type="date"
                v-model="auditFilterDate"
                @change="fetchInactivityReport"
                class="px-2 py-1 rounded border border-card-border bg-card-bg text-main-text font-mono text-xs focus:ring-1 focus:ring-brand-blue"
              />
            </div>
            <button
              @click="fetchInactivityReport"
              class="px-2.5 py-1 rounded bg-brand-blue text-white font-bold hover:bg-blue-900 transition cursor-pointer"
            >
              {{ langStore.t('common.refresh', 'Refresh') }}
            </button>
          </div>

          <div class="flex items-center gap-2">
            <a
              :href="printAuditUrl"
              target="_blank"
              class="px-3 py-1.5 rounded border border-card-border bg-card-bg font-bold text-main-text hover:bg-nav-accent/10 transition flex items-center gap-1.5 cursor-pointer"
            >
              <Printer class="w-3.5 h-3.5" />
              <span>{{ langStore.isAmharic() ? 'ይፋዊ ሰነድ አትም' : 'Print Official Document' }}</span>
            </a>
            <a
              :href="exportCsvUrl"
              target="_blank"
              class="px-3 py-1.5 rounded bg-brand-blue font-bold text-white hover:bg-blue-900 transition flex items-center gap-1.5 cursor-pointer"
            >
              <Download class="w-3.5 h-3.5" />
              <span>{{ langStore.isAmharic() ? 'CSV ላክ' : 'Export CSV' }}</span>
            </a>
          </div>
        </div>

        <div v-if="loadingAudit" class="py-12 text-center space-y-2">
          <div class="w-6 h-6 border-2 border-brand-blue border-t-transparent rounded-full animate-spin mx-auto" />
          <p class="text-main-text/60 font-medium">{{ langStore.isAmharic() ? 'የኦዲት መረጃዎችን በማዘጋጀት ላይ...' : 'Compiling shift compliance audit data...' }}</p>
        </div>

        <template v-else>
          <!-- Summary KPIs -->
          <div v-if="auditReport?.summary" class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <div class="p-2.5 rounded border border-card-border bg-card-bg text-center">
              <div class="text-base font-extrabold font-mono text-main-text">{{ auditReport.summary.total_monitored_shifts }}</div>
              <div class="text-[9px] uppercase tracking-wider text-main-text/50 font-bold">{{ langStore.isAmharic() ? 'የተመረመሩ ፈረቃዎች' : 'Monitored Shifts' }}</div>
            </div>
            <div class="p-2.5 rounded border border-rose-500/30 bg-rose-500/10 text-center">
              <div class="text-base font-extrabold font-mono text-rose-700 dark:text-rose-400">{{ auditReport.summary.total_incidents }}</div>
              <div class="text-[9px] uppercase tracking-wider text-rose-600 font-bold">{{ langStore.isAmharic() ? 'የማቆም ክስተቶች (>30ደ)' : 'Inactivity Incidents (>30m)' }}</div>
            </div>
            <div class="p-2.5 rounded border border-amber-500/30 bg-amber-500/10 text-center">
              <div class="text-base font-extrabold font-mono text-amber-700 dark:text-amber-400">{{ auditReport.summary.total_inactive_hours }}h</div>
              <div class="text-[9px] uppercase tracking-wider text-amber-600 font-bold">{{ langStore.isAmharic() ? 'የባከነ ሰዓት' : 'Total Inactive Hours' }}</div>
            </div>
            <div class="p-2.5 rounded border border-card-border bg-card-bg text-center">
              <div class="text-base font-extrabold font-mono text-brand-blue dark:text-brand-yellow">{{ auditReport.summary.compliance_rate }}%</div>
              <div class="text-[9px] uppercase tracking-wider text-main-text/50 font-bold">{{ langStore.isAmharic() ? 'የፈረቃ ተገዢነት' : 'Duty Compliance' }}</div>
            </div>
          </div>

          <!-- Incidents Table -->
          <div class="overflow-x-auto rounded border border-card-border max-h-[420px] overflow-y-auto">
            <table class="w-full border-collapse text-left">
              <thead class="sticky top-0 bg-main-bg border-b border-card-border z-10">
                <tr class="text-[10px] uppercase font-bold text-main-text/60">
                  <th class="p-2">ID</th>
                  <th class="p-2">{{ langStore.isAmharic() ? 'ቅርንጫፍ' : 'Branch' }}</th>
                  <th class="p-2">{{ langStore.isAmharic() ? 'ፈረቃና ቡድን' : 'Shift & Group' }}</th>
                  <th class="p-2">{{ langStore.isAmharic() ? 'የተመደቡ ሠራተኞች' : 'Scheduled Attendants' }}</th>
                  <th class="p-2">{{ langStore.isAmharic() ? 'የማቆም ክፍተት' : 'Inactive Interval' }}</th>
                  <th class="p-2">{{ langStore.isAmharic() ? 'ቆይታ' : 'Duration' }}</th>
                  <th class="p-2">{{ langStore.isAmharic() ? 'ሁኔታ' : 'Status' }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-card-border/60">
                <tr
                  v-for="inc in auditReport?.incidents || []"
                  :key="inc.incident_id"
                  class="hover:bg-main-bg/50 text-[11px]"
                >
                  <td class="p-2 font-mono font-bold text-main-text/60">{{ inc.incident_id }}</td>
                  <td class="p-2 font-bold text-main-text">{{ langStore.isAmharic() ? inc.branch_name_am : inc.branch_name }}</td>
                  <td class="p-2">
                    <div class="font-bold text-brand-blue">{{ langStore.isAmharic() ? inc.shift_name_am : inc.shift_name }}</div>
                    <div class="text-[10px] text-main-text/50">{{ inc.shift_group_name }}</div>
                  </td>
                  <td class="p-2">
                    <div class="space-y-0.5">
                      <div v-for="att in inc.attendants" :key="att.id" class="text-[10px]">
                        <strong>{{ att.name }}</strong> <span class="text-main-text/50">(@{{ att.username }})</span>
                      </div>
                    </div>
                  </td>
                  <td class="p-2">
                    <div class="font-bold font-mono">{{ inc.interval_formatted }}</div>
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400">{{ inc.interval_ethiopian }}</div>
                  </td>
                  <td class="p-2 font-bold font-mono text-rose-700 dark:text-rose-400">{{ inc.duration_human }}</td>
                  <td class="p-2">
                    <span
                      class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase"
                      :class="inc.is_ongoing ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30'"
                    >
                      {{ inc.is_ongoing ? (langStore.isAmharic() ? '🔴 አሁን አልሰሩም' : '🔴 Active Non-Working') : (langStore.isAmharic() ? '⚠️ ክፍተት' : '⚠️ Past Gap') }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!auditReport?.incidents?.length">
                  <td colspan="7" class="p-6 text-center text-emerald-600 dark:text-emerald-400 font-bold">
                    ✓ {{ langStore.isAmharic() ? 'በተመረጠው ቀን ምንም የሥራ ማቆም ክፍተት አልተገኘም። 100% የፈረቃ ተገዢነት።' : 'No inactivity incidents detected for this period. 100% duty compliance.' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import { dashboardApi, shiftInactivityApi } from '@/api';
import { formatDT } from '@/utils/ethiopianDate';
import Modal from '@/components/ui/Modal.vue';
import {
  LayoutDashboard,
  AlertTriangle,
  AlertOctagon,
  Activity,
  Building2,
  Users,
  ShieldCheck,
  CheckCircle2,
  UserCheck,
  Clock,
  CalendarOff,
  GraduationCap,
  BookOpen,
  Briefcase,
  TrendingUp,
  BarChart3,
  FileText,
  Printer,
  Download,
  ChevronDown,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const langStore = useLanguageStore();

const loading = ref(true);
const stats = ref<any>(null);

// Shift Group Accordion in Today's Duty Shifts
const expandedShiftId = ref<number | null>(null);
const toggleShiftExpand = (shiftId: number) => {
  expandedShiftId.value = expandedShiftId.value === shiftId ? null : shiftId;
};

// Inactivity Audit Modal & Data
const showInactivityModal = ref(false);
const loadingAudit = ref(false);
const auditReport = ref<any>(null);
const auditFilterDate = ref(new Date().toISOString().split('T')[0]);

const printAuditUrl = computed(() => {
  return shiftInactivityApi.exportUrl({
    date: auditFilterDate.value,
    format: 'print',
    lang: langStore.isAmharic() ? 'am' : 'en',
  });
});

const exportCsvUrl = computed(() => {
  return shiftInactivityApi.exportUrl({
    date: auditFilterDate.value,
    format: 'csv',
    lang: langStore.isAmharic() ? 'am' : 'en',
  });
});

const openInactivityModal = () => {
  showInactivityModal.value = true;
  fetchInactivityReport();
};

const fetchInactivityReport = async () => {
  try {
    loadingAudit.value = true;
    const { data } = await shiftInactivityApi.report({
      date: auditFilterDate.value,
    });
    auditReport.value = data?.data || null;
  } catch (err) {
    console.error('Failed to load shift inactivity report:', err);
  } finally {
    loadingAudit.value = false;
  }
};

const todayDate = computed(() => {
  return formatDT(new Date(), langStore.isAmharic()).full;
});

const formatDate = (d: string) => {
  if (!d) return '—';
  return formatDT(d, langStore.isAmharic()).date;
};

const formatTime12 = (d: string) => {
  if (!d) return '—';
  return formatDT(d, langStore.isAmharic()).time;
};

const formatTimeStr12 = (timeStr: string) => {
  if (!timeStr) return '—';
  const parts = timeStr.split(':');
  if (parts.length >= 2) {
    const today = new Date();
    today.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), 0);
    return formatDT(today, langStore.isAmharic()).time;
  }
  return timeStr;
};

onMounted(async () => {
  try {
    const { data } = await dashboardApi.stats();
    stats.value = data;
    // Auto-expand current active shift
    const active = data?.stats?.today_shift_groups?.find((s: any) => s.is_current_shift);
    if (active) {
      expandedShiftId.value = active.shift_id;
    } else if (data?.stats?.today_shift_groups?.length) {
      expandedShiftId.value = data.stats.today_shift_groups[0].shift_id;
    }
  } finally {
    loading.value = false;
  }
});
</script>
