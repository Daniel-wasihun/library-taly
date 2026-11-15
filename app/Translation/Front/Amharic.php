<?php

namespace App\Translation\Front;

class Amharic
{
    public static function get(): array
    {
        return [
            // App & General
            'app.name' => 'የዩኒቨርሲቲ ቤተ-መጻሕፍት ፖርታል',
            'app.subtitle' => 'የዩኒቨርሲቲ ቤተ-መጻሕፍትና ተረኛ ፈረቃ አስተዳደር ሥርዓት',
            'app.tagline' => 'የትምህርት ጥረትና በፈረቃ የተመደበ የተግባራት ቁጥጥር',
            'app.welcome' => 'እንኳን ወደ ዩኒቨርሲቲ ቤተ-መጻሕፍት ፖርታል በደህና መጡ',
            'common.actions' => 'ተግባራት',
            'common.save' => 'አስቀምጥ',
            'common.cancel' => 'ሰርዝ',
            'common.delete' => 'አጥፋ',
            'common.edit' => 'አስተካክል',
            'common.view' => 'ተመልከት',
            'common.search' => 'መዝገቦችን ፈልግ...',
            'common.filter' => 'አጣራ',
            'common.status' => 'ሁኔታ',
            'common.all' => 'ሁሉም',
            'common.active' => 'ንቁ',
            'common.inactive' => 'ቦዘኔ',
            'common.suspended' => 'የታገደ',
            'common.loading' => 'በመጫን ላይ...',
            'common.refresh' => 'አድስ',
            'common.confirm_delete' => 'እርግጠኛ ነዎት ይህንን መዝገብ መሰረዝ ይፈልጋሉ? ይህ ድርጊት አይመለስም።',
            'common.no_records' => 'ምንም የተገኘ መረጃ የለም።',
            'common.success' => 'ተሳክቷል',
            'common.error' => 'ስህተት',
            'common.close' => 'ዝጋ',
            'common.create' => 'አዲስ ፍጠር',
            'common.update' => 'አሻሽል',

            // Navigation
            'nav.dashboard' => 'ዋና ዳሽቦርድ',
            'nav.group_general' => 'አጠቃላይ',
            'nav.group_library' => 'የቤተ-መጻሕፍት ሥራዎች',
            'nav.group_admin' => 'አስተዳደርና ደህንነት',
            'nav.users' => 'የተጠቃሚዎች አስተዳደር',
            'nav.roles' => 'ሚናዎችና ፈቃዶች (RBAC)',
            'nav.schedules' => 'የተረኛ ፈረቃዎች መርሃ-ግብር',
            'nav.my_turns' => 'የእኔ ተረኛ ፈረቃ',
            'nav.books' => 'የመጻሕፍት ማውጫ',
            'nav.circulation' => 'የመጻሕፍት ብድርና ዝውውር',

            // Duty Status
            'duty.on_duty' => 'ዛሬ ተረኛ ነው',
            'duty.off_duty' => 'ዛሬ ተረኛ አይደለም',
            'duty.admin_override' => 'የአስተዳዳሪ ቀጥተኛ ፈቃድ ንቁ ነው',
            'duty.restricted_notice' => 'ተግባሩ በተረኛ ፈረቃ የተገደበ ነው',
            'duty.next_turn' => 'ቀጣይ ተረኛ ፈረቃ',
            'duty.active_shift' => 'ንቁ የሥራ ፈረቃ',
            'duty.shifts_today' => 'የዛሬ ተረኛ ፈረቃዎች',
            'duty.task_circulation' => 'የመጻሕፍት ብድርና ዝውውር',
            'duty.task_audit' => 'የመጻሕፍት ክምችት ኦዲትና ባርኮድ ቅኝት',
            'duty.task_fine' => 'የቅጣት ክፍያ ማስተላለፍና ምህረት',
            'duty.task_archive' => 'የተከለከሉ ጥንታዊ ሰነዶች ክፍል',

            // Users
            'users.title' => 'የተጠቃሚዎች አስተዳደርና መዳረሻ',
            'users.subtitle' => 'የተጠቃሚ ምስክርነቶችን፣ የSpatie ሚናዎችንና ሁኔታን ያቀናብሩ',
            'users.add' => 'አዲስ ተጠቃሚ መዝግብ',
            'users.edit' => 'ተጠቃሚ አስተካክል',
            'users.name' => 'ሙሉ ስም',
            'users.username' => 'ልዩ የተጠቃሚ ስም',
            'users.email' => 'የኢሜይል አድራሻ',
            'users.role' => 'የተመደበ ሚና',
            'users.password' => 'የይለፍ ቃል',
            'users.password_hint' => 'የነበረውን ይለፍ ቃል ለመጠበቅ ባዶ ይተዉት',
            'users.phone' => 'ስልክ ቁጥር',
            'users.created' => 'ተጠቃሚው በተሳካ ሁኔታ ተፈጥሯል።',
            'users.updated' => 'ተጠቃሚው በተሳካ ሁኔታ ተሻሽሏል።',
            'users.deleted' => 'ተጠቃሚው በተሳካ ሁኔታ ተሰርዟል።',

            // Roles
            'roles.title' => 'ሚናዎችና የተጠቃሚ ፈቃዶች',
            'roles.subtitle' => 'በSpatie Laravel Permission የተዋቀረ የተዋረድ ቁጥጥር',
            'roles.create' => 'አዲስ ሚና ፍጠር',
            'roles.edit' => 'ሚና አስተካክል',
            'roles.name' => 'የሚና ስም',
            'roles.permissions' => 'ፈቃዶች',
            'roles.catalog' => 'የሚናዎች ዝርዝር',
            'roles.matrix' => 'የፈቃዶች ማትሪክስ',
            'roles.created' => 'ሚናው በተሳካ ሁኔታ ተፈጥሯል።',
            'roles.updated' => 'ሚናው በተሳካ ሁኔታ ተሻሽሏል።',

            // Books & Circulation
            'books.title' => 'የዩኒቨርሲቲ ቤተ-መጻሕፍት ማውጫ',
            'books.add' => 'መጽሐፍ መዝግብ',
            'books.isbn' => 'ISBN ቁጥር',
            'books.book_title' => 'የመጽሐፉ ርዕስ',
            'books.author' => 'ደራሲ',
            'books.category' => 'ዘርፍ / የትምህርት መስክ',
            'books.location' => 'የመደርደሪያ ቦታ',
            'books.copies' => 'ያሉ ቅጂዎች',
            'circulation.title' => 'የመጻሕፍት ብድርና ዝውውር',
            'circulation.issue' => 'መጽሐፍ አበድር',
            'circulation.return' => 'የተበደረውን መጽሐፍ ተቀበል',
            'circulation.borrower' => 'ተበዳሪ (ተማሪ)',
            'circulation.due_date' => 'የመመለሻ ቀን',
            'circulation.issued_date' => 'የተበደረበት ቀን',
            'circulation.status_borrowed' => 'በብድር ላይ',
            'circulation.status_returned' => 'ተመልሷል',
            'circulation.status_overdue' => 'ቀኑ ያለፈበት',
        ];
    }
}
