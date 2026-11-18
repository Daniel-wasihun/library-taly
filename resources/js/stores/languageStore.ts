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
    'nav.attendance': 'Physical Attendance / Usage Counter',
    'nav.libraries': 'Library Branches',
    'libraries.title': 'Library Branches Management',
    'libraries.subtitle': 'Campus library branches, location profiles, and scoped access control',
    'libraries.add': 'Add Branch',
    'libraries.edit': 'Edit Branch',
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
    'attendance.title': 'Physical Library Attendance & Usage Counter',
    'attendance.subtitle': 'Live patron tracking, gender distribution, and automated periodic reporting',
    'attendance.live_counter': 'Live Counter',
    'attendance.analytics': 'Analytics & Reporting',
    'attendance.select_library': 'Select Library Branch',
    'attendance.all_branches': 'All Campus Branches',
    'attendance.recent_entries': 'Recent Live Entries',
    'attendance.male': 'Male',
    'attendance.female': 'Female',
    'attendance.student': 'Student',
    'attendance.teacher': 'Teacher',
    'attendance.staff': 'Staff',
    'attendance.total_today': 'Total Today',
    'attendance.export_report': 'Export Report',
    'common.male': 'Male',
    'common.female': 'Female',
    'common.student': 'Student',
    'common.teacher': 'Teacher',
    'common.staff': 'Staff',
    'common.count': 'Visitor Count',
    'common.time': 'Time',
    'common.location': 'Location',
    'common.daily_only': 'Daily Report',
    'common.report_only': 'Periodic Reports',
    'common.from': 'From',
    'common.to': 'To',
    'attendance.today_total': "Today's Total Visits",
    'attendance.today_male': 'Male Visitors',
    'attendance.today_female': 'Female Visitors',
    'attendance.branches': 'Library Branches',
    'attendance.active_staff': 'Active Staff',
    'attendance.export_csv': 'Export CSV',
    'attendance.print_report': 'Print Official Report',
    'attendance.print': 'Print',
    'attendance.user_type_demographic': 'User Type Demographic Breakdown',
    'attendance.gender_distribution': 'Gender Ratio & Distribution',
    'attendance.usage_trends': 'Daily Activity Trends in Period',
    'attendance.of_traffic': 'of traffic',
    'attendance.total_label': 'Total Visitors',
    'attendance.all_libraries': 'All Library Branches',
    'attendance.cancel_entry': 'Cancel Entry',
    'attendance.confirm_cancel': 'Are you sure you want to cancel this entry?',
    'attendance.confirm_delete': 'Confirm Deletion',
    'attendance.generating_report': 'Generating official report',
    'attendance.quick_log': 'Quick Log Patron',
    'attendance.batch_increment': 'Batch Increments',
    'attendance.cancel_notice': 'Entries can be cancelled within 2 minutes of logging.',
    'attendance.shift_active': 'Active Shift Duty: Counter Ready',
    'attendance.select_patron_type': 'Patron Type',
    'attendance.quick_log_hint': 'Tap the large button for single visitors or use quick batch numbers (+2 .. +15) for group visits.',
    'attendance.attendant_only_notice': 'Live visitor counting is assigned to desk attendants.',
    'common.batch': 'Batch',
    // Schedules, Shifts & Groups translations
    'schedules.title': 'Daily Shift Schedules & Ethiopian Roster',
    'schedules.subtitle': 'Schedule duty shifts for groups (Group A, B...) across day & night shifts in the Ethiopian calendar.',
    'schedules.tab_calendar': 'Ethiopian Monthly Scheduler',
    'schedules.tab_groups': 'Shift Groups (Group A, B...)',
    'schedules.tab_shifts': 'Shift Definitions (Day / Night)',
    'schedules.tab_turns': 'Individual Duty Turns',
    'schedules.auto_generate': 'Auto-Generate Schedule',
    'schedules.auto_modal_title': 'Auto-Generate Monthly Shift Schedule',
    'schedules.auto_modal_desc': 'Automatically calculate and assign shifts with equal workload across groups in this branch only, excluding official Ethiopian holidays.',
    'schedules.library_branch': 'Library Branch',
    'schedules.ethiopian_year': 'Ethiopian Year',
    'schedules.ethiopian_month': 'Ethiopian Month',
    'schedules.duty_group': 'Duty Group',
    'schedules.shift_name': 'Shift Name',
    'schedules.shift_name_am': 'Amharic Title',
    'schedules.shift_code': 'Shift Code',
    'schedules.start_time': 'Start Time',
    'schedules.end_time': 'End Time',
    'schedules.ethiopian_time': 'Ethiopian Local Time',
    'schedules.standard_time': 'Standard 24h Time',
    'schedules.input_mode': 'Time Input System',
    'schedules.input_ethiopian': 'Ethiopian Clock (12h)',
    'schedules.input_standard': 'Standard International (24h)',
    'schedules.time_windows_desc': 'Time Windows & Automatic Conversion',
    'schedules.converted_preview': 'Real-Time Converted Time Window',
    'schedules.hour': 'Hour',
    'schedules.minute': 'Minute',
    'schedules.period': 'Period',
    'schedules.period_morning': 'Morning',
    'schedules.period_afternoon': 'Afternoon',
    'schedules.period_evening': 'Evening',
    'schedules.period_night': 'Late Night',
    'schedules.detected_holidays': 'Detected Ethiopian Holidays for this Month',
    'schedules.exclude_holidays': 'Exclude Ethiopian Holidays (1 day off per holiday)',
    'schedules.overwrite_existing': 'Overwrite existing schedules for this month and branch',
    'schedules.overlap_warning': 'Staff Multi-Group Overlap Detected',
    'schedules.overlap_desc': 'One person cannot belong to multiple groups for auto-scheduled shifts in a given library branch. Please assign each attendant to a single group.',
    'schedules.odd_days': 'Odd Days (1, 3, 5...)',
    'schedules.even_days': 'Even Days (2, 4, 6...)',
    'schedules.clear_days': 'Clear',
    'schedules.save_schedule': 'Save Shift Schedule',
    'schedules.reset_changes': 'Reset Changes',
    'schedules.create_group': 'Create Group',
    'schedules.edit_group': 'Edit Shift Group',
    'schedules.create_shift': 'Define New Shift',
    'schedules.edit_shift': 'Edit Shift Definition',
    'schedules.shift_status': 'Shift Status',
    'schedules.active_shift': 'Active Shift (Available for Scheduling)',
    'schedules.holiday_badge': 'Ethiopian Holiday (Free/Off)',
    'schedules.next_day': 'Next Day (+1 day)',
    'schedules.holidays_heading': 'Official Ethiopian Holidays for',
    'schedules.holidays_detected_count': 'Holiday(s) Detected',
    'schedules.no_holidays_in_month': 'No Public Holidays this Month',
    'schedules.holidays_rule_desc': 'Official Ethiopian public and religious holidays are canceled in the schedule. Duty shifts cannot be assigned and are automatically removed from duty rosters.',
    'schedules.regular_month_desc': 'All days (1–30) in this Ethiopian month are normal operating duty days.',
    'schedules.shifts_canceled_badge': 'All Duty Shifts Canceled on Holidays',
    'schedules.holiday_legend': 'Ethiopian Holiday (Canceled / No Duty)',
    'schedules.canceled': 'Canceled',
    'schedules.canceled_holiday': 'Canceled (Holiday)',
    'schedules.add_holiday': 'Add Holiday',
    'schedules.add_holiday_title': 'Add Manual Ethiopian Holiday',
    'schedules.add_holiday_subtitle': 'Insert a public or religious holiday for this month. Duty shifts will be automatically canceled on this date.',
    'schedules.holiday_day': 'Ethiopian Day of Month *',
    'schedules.holiday_month_year': 'Month & Year',
    'schedules.holiday_name_en': 'Holiday Name (English) *',
    'schedules.holiday_name_am': 'Amharic Title',
    'schedules.save_holiday': 'Save Holiday',
    'schedules.consecutive_legend': 'Rest Day (No Consecutive Night/Day)',
    'schedules.rest_day': 'Rest Day',
    'schedules.tab_holidays': 'Ethiopian Holidays',
    'schedules.selected': 'Selected',
    'schedules.available_days': 'Available (1–30)',
    'schedules.in_another_shift': 'In Another Shift (Group)',
    'schedules.assigned_other_group': 'Assigned to Other Group',
    'schedules.scope_label': 'Scope',
    'schedules.group_label': 'Group',
    'schedules.all_branches_default': 'All University Library Branches (Default)',
    'schedules.no_members': 'No members',
    'schedules.no_groups_defined': 'No groups defined',
    'schedules.resolve_overlaps_first': 'Resolve Overlaps First',
    'schedules.generate_and_assign': 'Generate & Assign Schedule',
    'attendance.counter_locked_title': 'Usage Counter Inactive (Outside Assigned Shift)',
    'attendance.counter_locked_desc': 'You can only record patron usage counter data during your active assigned shift date and time window.',
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
    'nav.roles': 'ሚናዎችና ፈቃዶች',
    'nav.schedules': 'የተረኛ ፈረቃዎች መርሃ-ግብር',
    'nav.my_turns': 'የእኔ ተረኛ ፈረቃ',
    'nav.attendance': 'የአካላዊ ተጠቃሚዎች ቆጣሪ / ምዝገባ',
    'nav.libraries': 'የቤተ-መጻሕፍት ቅርንጫፎች',
    'libraries.title': 'የቤተ-መጻሕፍት ቅርንጫፎች አስተዳደር',
    'libraries.subtitle': 'የግቢ ቅርንጫፎች፣ የመገኛ አካባቢዎችና የቅርንጫፍ መዳረሻ ቁጥጥር',
    'libraries.add': 'አዲስ ቅርንጫፍ መዝግብ',
    'libraries.edit': 'ቅርንጫፍ አስተካክል',
    'duty.on_duty': 'ዛሬ ተረኛ ነው',
    'duty.off_duty': 'ዛሬ ተረኛ አይደለም',
    'duty.admin_override': 'የአስተዳዳሪ ቀጥተኛ ፈቃድ ንቁ ነው',
    'duty.next_turn': 'ቀጣይ ተረኛ ፈረቃ',
    'users.title': 'የተጠቃሚዎች አስተዳደርና መዳረሻ',
    'users.subtitle': 'የተጠቃሚ ምስክርነቶችን፣ ሚናዎችንና ሁኔታን ያቀናብሩ',
    'users.add': 'አዲስ ተጠቃሚ መዝግብ',
    'roles.title': 'ሚናዎችና የተጠቃሚ ፈቃዶች',
    'roles.subtitle': 'በፈቃዶች ደረጃ የተዋቀረ የተዋረድ ቁጥጥር',
    'roles.create': 'አዲስ ሚና ፍጠር',
    'attendance.title': 'የአካላዊ ቤተ-መጻሕፍት ተጠቃሚዎች ቆጣሪና አጠቃቀም',
    'attendance.subtitle': 'የቀጥታ ተጠቃሚዎች ክትትል፣ የሥርዓተ-ፆታ ስርጭትና ራስ-ሰር ወቅታዊ ሪፖርቶች',
    'attendance.live_counter': 'የቀጥታ ቆጣሪ',
    'attendance.analytics': 'ትንተናና ሪፖርቶች',
    'attendance.select_library': 'የቤተ-መጻሕፍት ቅርንጫፍ ይምረጡ',
    'attendance.all_branches': 'ሁሉም የግቢ ቅርንጫፎች',
    'attendance.recent_entries': 'የቅርብ ጊዜ የቀጥታ ምዝገባዎች',
    'attendance.male': 'ወንድ',
    'attendance.female': 'ሴት',
    'attendance.student': 'ተማሪ',
    'attendance.teacher': 'መምህር',
    'attendance.staff': 'ሠራተኛ',
    'attendance.total_today': 'የዛሬ ድምር',
    'attendance.export_report': 'ሪፖርት አውርድ',
    'common.male': 'ወንድ',
    'common.female': 'ሴት',
    'common.student': 'ተማሪ',
    'common.teacher': 'መምህር',
    'common.staff': 'ሰራተኛ',
    'common.count': 'የጎብኝዎች ብዛት',
    'common.time': 'ሰዓት',
    'common.location': 'ቅርንጫፍ',
    'common.daily_only': 'የዕለቱ ሪፖርት',
    'common.report_only': 'ወቅታዊ ሪፖርቶች',
    'common.from': 'ከ',
    'common.to': 'እስከ',
    'attendance.today_total': 'የዛሬ አጠቃላይ ጎብኝዎች',
    'attendance.today_male': 'ወንድ ጎብኝዎች',
    'attendance.today_female': 'ሴት ጎብኝዎች',
    'attendance.branches': 'የቤተ-መጽሐፍት ቅርንጫፎች',
    'attendance.active_staff': 'ንቁ ሠራተኞች',
    'attendance.export_csv': 'ሪፖርት በፋይል አውርድ',
    'attendance.print_report': 'ይፋዊ ሪፖርት አትም',
    'attendance.print': 'አትም',
    'attendance.user_type_demographic': 'የተጠቃሚዎች ስርጭት ትንተና',
    'attendance.gender_distribution': 'የሥርዓተ-ፆታ ስርጭትና ንጽጽር',
    'attendance.usage_trends': 'የቀናት እንቅስቃሴ አዝማሚያ',
    'attendance.of_traffic': 'የተመዘገበ ድርሻ',
    'attendance.total_label': 'ጠቅላላ ጎብኝዎች',
    'attendance.all_libraries': 'ሁሉም የቤተ-መጻሕፍት ቅርንጫፎች',
    'attendance.cancel_entry': 'ምዝገባን ሰርዝ',
    'attendance.confirm_cancel': 'ይህን የተመዘገበ መረጃ መሰረዝ እንደሚፈልጉ እርግጠኛ ነዎት?',
    'attendance.confirm_delete': 'መሰረዙን አረጋግጥ',
    'attendance.generating_report': 'ይፋዊ ሪፖርት እየተዘጋጀ ነው',
    'attendance.quick_log': 'ፈጣን መዝግብ',
    'attendance.batch_increment': 'በቡድን ማከል',
    'attendance.cancel_notice': 'የተመዘገበው መረጃ በተመዘገበ በ2 ደቂቃ ውስጥ ብቻ መሰረዝ ይችላል።',
    'attendance.shift_active': 'ንቁ የፈረቃ ተረኝነት፡ ቆጣሪው ክፍት ነው',
    'attendance.select_patron_type': 'የተጠቃሚ አይነት',
    'attendance.quick_log_hint': 'ለግለሰብ ጎብኝ ትልቁን ቁልፍ ይጫኑ ወይም ለቡድን ጎብኝዎች ፈጣን ቁጥሮችን (+2 .. +15) ይጠቀሙ።',
    'attendance.attendant_only_notice': 'የቀጥታ ጎብኝዎች ምዝገባ ለተረኛ ረዳቶች ብቻ የተመደበ ነው።',
    'common.batch': 'በቡድን',
    // Schedules, Shifts & Groups translations (Amharic)
    'schedules.title': 'የዕለት ፈረቃዎችና የኢትዮጵያ የቀን መቁጠሪያ መርሃ-ግብር',
    'schedules.subtitle': 'በኢትዮጵያ የቀን አቆጣጠር የፈረቃ ቡድኖችን የቀንና የማታ ፈረቃዎችን ይመድቡና ያስተዳድሩ።',
    'schedules.tab_calendar': 'የኢትዮጵያ ወርሃዊ ፈረቃ አዘጋጅ',
    'schedules.tab_groups': 'የፈረቃ ቡድኖች (ምድብ ሀ፣ ለ...)',
    'schedules.tab_shifts': 'የፈረቃ አይነቶች (ቀን / ማታ)',
    'schedules.tab_turns': 'የግል ተረኛ ፈረቃዎች',
    'schedules.auto_generate': 'መርሃ-ግብር በራስ-ሰር አዘጋጅ',
    'schedules.auto_modal_title': 'ወርሃዊ ፈረቃን በራስ-ሰር አዘጋጅ',
    'schedules.auto_modal_desc': 'በዚህ ቅርንጫፍ ላሉ ቡድኖች እኩል የሥራ ድርሻ በማስላትና የኢትዮጵያ ይፋዊ በዓላትን በማስቀረት ፈረቃዎችን በራስ-ሰር ይመድቡ።',
    'schedules.library_branch': 'የቤተ-መጻሕፍት ቅርንጫፍ',
    'schedules.ethiopian_year': 'የኢትዮጵያ ዓመተ ምሕረት',
    'schedules.ethiopian_month': 'የኢትዮጵያ ወር',
    'schedules.duty_group': 'ተረኛ ቡድን',
    'schedules.shift_name': 'የፈረቃ ስም (በእንግሊዝኛ)',
    'schedules.shift_name_am': 'የፈረቃ ስም (በአማርኛ)',
    'schedules.shift_code': 'የፈረቃ መለያ ኮድ',
    'schedules.start_time': 'የመጀመሪያ ሰዓት',
    'schedules.end_time': 'የማብቂያ ሰዓት',
    'schedules.ethiopian_time': 'የኢትዮጵያ ሰዓት',
    'schedules.standard_time': 'ዓለም አቀፍ 24-ሰዓት',
    'schedules.input_mode': 'የሰዓት ማስገቢያ ሥርዓት',
    'schedules.input_ethiopian': 'የኢትዮጵያ አቆጣጠር (12-ሰዓት)',
    'schedules.input_standard': 'ዓለም አቀፍ አቆጣጠር (24-ሰዓት)',
    'schedules.time_windows_desc': 'የሰዓት መስኮትና ራስ-ሰር ልወጣ',
    'schedules.converted_preview': 'በቀጥታ የተለወጠ የሰዓት መስኮት',
    'schedules.hour': 'ሰዓት',
    'schedules.minute': 'ደቂቃ',
    'schedules.period': 'ክፍለ ጊዜ',
    'schedules.period_morning': 'ጠዋት',
    'schedules.period_afternoon': 'ከቀኑ',
    'schedules.period_evening': 'ከምሽቱ',
    'schedules.period_night': 'ከሌሊቱ (የሌሊት 11 ሰዓት ጨምሮ)',
    'schedules.detected_holidays': 'በዚህ ወር የተገኙ ይፋዊ የኢትዮጵያ በዓላት',
    'schedules.exclude_holidays': 'የኢትዮጵያ በዓላትን ከፈረቃ አስቀር (በዓሉ የዕረፍት ቀን ይሆናል)',
    'schedules.overwrite_existing': 'የነበሩትን የዚህ ወር ፈረቃዎች በሙሉ በድጋሚ ይተኩ',
    'schedules.overlap_warning': 'የሠራተኛ በሁለት ቡድን ውስጥ መገኘት ታይቷል',
    'schedules.overlap_desc': 'በአንድ ቅርንጫፍ ውስጥ አንድ ሰው በአንድ ጊዜ በሁለት ቡድኖች ውስጥ መሆን አይችልም። እባክዎ ለእያንዳንዱ ሠራተኛ አንድ ቡድን ብቻ ይምረጡ።',
    'schedules.odd_days': 'ኢ-ተጋማሽ ቀናት (1፣ 3፣ 5...)',
    'schedules.even_days': 'ተጋማሽ ቀናት (2፣ 4፣ 6...)',
    'schedules.clear_days': 'አጽዳ',
    'schedules.save_schedule': 'የፈረቃ መርሃ-ግብር አስቀምጥ',
    'schedules.reset_changes': 'ለውጦችን መልስ',
    'schedules.create_group': 'አዲስ ቡድን ፍጠር',
    'schedules.edit_group': 'የፈረቃ ቡድን አስተካክል',
    'schedules.create_shift': 'አዲስ ፈረቃ መዝግብ',
    'schedules.edit_shift': 'የፈረቃ ዝርዝር አስተካክል',
    'schedules.shift_status': 'የፈረቃ ሁኔታ',
    'schedules.active_shift': 'ንቁ ፈረቃ (ለመመደብ ዝግጁ)',
    'schedules.holiday_badge': 'የኢትዮጵያ በዓል (የዕረፍት ቀን)',
    'schedules.next_day': 'ቀጣይ ቀን (+1 ቀን)',
    'schedules.holidays_heading': 'የኢትዮጵያ ይፋዊ የበዓላት ቀናት —',
    'schedules.holidays_detected_count': 'በዓላት ተገኝተዋል',
    'schedules.no_holidays_in_month': 'በዚህ ወር ውስጥ ይፋዊ በዓላት የሉም',
    'schedules.holidays_rule_desc': 'በዚህ ወር ያሉ ይፋዊ የኢትዮጵያ በዓላት በፈረቃው ውስጥ ተሰርዘዋል። በእነዚህ ቀናት ፈረቃ አይመደብም እንዲሁም ከመርሐ-ግብሩ ይወገዳሉ።',
    'schedules.regular_month_desc': 'በዚህ የኢትዮጵያ ወር ውስጥ ያሉት ሁሉም 30 ቀናት መደበኛ የሥራ ቀናት ናቸው።',
    'schedules.shifts_canceled_badge': 'በበዓላት ቀናት ሁሉም ፈረቃዎች ተሰርዘዋል',
    'schedules.holiday_legend': 'የኢትዮጵያ በዓል (የተሰረዘ / ዕረፍት)',
    'schedules.canceled': 'ተሰርዟል',
    'schedules.canceled_holiday': 'ተሰርዟል (በዓል)',
    'schedules.add_holiday': 'በዓል ጨምር',
    'schedules.add_holiday_title': 'የኢትዮጵያ በዓል በእጅ መዝግብ',
    'schedules.add_holiday_subtitle': 'ለዚህ ወር ይፋዊ ወይም ሃይማኖታዊ በዓል ይመዝግቡ። በዚያ ቀን የፈረቃ ተረኝነት በራስ-ሰር ይሰረዛል።',
    'schedules.holiday_day': 'የወሩ ቀን *',
    'schedules.holiday_month_year': 'ወር እና ዓ.ም',
    'schedules.holiday_name_en': 'የበዓሉ ስም (እንግሊዝኛ) *',
    'schedules.holiday_name_am': 'የበዓሉ ስም (አማርኛ)',
    'schedules.save_holiday': 'በዓሉን መዝግብ',
    'schedules.consecutive_legend': 'የዕረፍት ቀን (ተከታታይ ማታ/ቀን አይቻልም)',
    'schedules.rest_day': 'ዕረፍት',
    'schedules.tab_holidays': 'የበዓላት መዝገብ',
    'schedules.selected': 'የተመረጠ',
    'schedules.available_days': 'ክፍት ቀናት (1–30)',
    'schedules.in_another_shift': 'በሌላ ፈረቃ ውስጥ (ቡድን)',
    'schedules.assigned_other_group': 'ለሌላ ቡድን ተመድቧል',
    'schedules.scope_label': 'ወሰን',
    'schedules.group_label': 'ቡድን',
    'schedules.all_branches_default': 'ሁሉም የዩኒቨርሲቲ ቤተ-መጻሕፍት ቅርንጫፎች',
    'schedules.no_members': 'ምንም አባላት የሉም',
    'schedules.no_groups_defined': 'የተመዘገበ ቡድን የለም',
    'schedules.resolve_overlaps_first': 'መጀመሪያ ድርብ ምደባዎችን ያስተካክሉ',
    'schedules.generate_and_assign': 'መርሐ-ግብር አዘጋጅና መድብ',
    'attendance.counter_locked_title': 'የጎብኝዎች ቆጣሪ ዝግ ነው (የፈረቃ ሰዓት አይደለም)',
    'attendance.counter_locked_desc': 'የጎብኝዎች መመዝገቢያ ቆጣሪውን መጠቀም የሚችሉት በተመደበልዎት ንቁ የፈረቃ ቀንና ሰዓት ውስጥ ብቻ ነው።',
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
        const defaultDict = lang === 'am' ? DEFAULT_AMHARIC : DEFAULT_ENGLISH;
        try {
            const res = await api.get<{ success: boolean; translations: Record<string, string> }>(`/translations/front/${lang}`);
            if (res.data?.translations) {
                translations.value = { ...defaultDict, ...res.data.translations };
                translationCache.value[lang] = translations.value;
            } else {
                translations.value = defaultDict;
            }
        } catch {
            // fallback to default dictionary
            translations.value = defaultDict;
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

    const isAmharic = () => currentLanguage.value === 'am';

    const localizeBranch = (branch: { name?: string; name_am?: string | null } | null | undefined): string => {
        if (!branch) return '—';
        if (currentLanguage.value === 'am' && branch.name_am) {
            return branch.name_am;
        }
        return branch.name || '—';
    };

    const localizeType = (type: string | null | undefined): string => {
        if (!type) return '—';
        const key = `common.${type.toLowerCase()}`;
        return t(key, type);
    };

    const localizeGender = (gender: string | null | undefined): string => {
        if (!gender) return '—';
        const key = `common.${gender.toLowerCase()}`;
        return t(key, gender);
    };

    const localizeMonth = (m: { name_am?: string; name_en?: string } | null | undefined): string => {
        if (!m) return '—';
        if (currentLanguage.value === 'am' && m.name_am) {
            return m.name_am;
        }
        return m.name_en || '—';
    };

    const localizeShift = (s: { name?: string; name_am?: string | null } | null | undefined): string => {
        if (!s) return '—';
        if (currentLanguage.value === 'am' && s.name_am) {
            return s.name_am;
        }
        return s.name || '—';
    };

    const localizeHoliday = (h: { name?: string; name_am?: string | null } | null | undefined): string => {
        if (!h) return '—';
        if (currentLanguage.value === 'am' && h.name_am) {
            return h.name_am;
        }
        return h.name || '—';
    };

    const formatTimeWindow = (timeStr: string | null | undefined): string => {
        if (!timeStr) return '';
        if (currentLanguage.value === 'am') return timeStr;
        return timeStr
            .replace(/ጠዋት/g, 'Morning')
            .replace(/ከቀኑ/g, 'Afternoon')
            .replace(/ከምሽቱ/g, 'Evening')
            .replace(/ከሌሊቱ/g, 'Night');
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
        isAmharic,
        localizeBranch,
        localizeType,
        localizeGender,
        localizeMonth,
        localizeShift,
        localizeHoliday,
        formatTimeWindow,
    };
});
