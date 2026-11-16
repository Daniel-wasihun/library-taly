/**
 * ethiopianDate.ts — Professional Gregorian <-> Ethiopian Calendar (EC) & 12-hour Time Utility
 * Inspired by ethiopian-date standard library and Ethiopian dawn-based 12h clock.
 */

const amMonths = [
    '',
    'መስከረም',
    'ጥቅምት',
    'ኅዳር',
    'ታኅሣሥ',
    'ጥር',
    'የካቲት',
    'መጋቢት',
    'ሚያዝያ',
    'ግንቦት',
    'ሰኔ',
    'ሐምሌ',
    'ነሐሴ',
    'ጳጉሜን',
];

const amDays = ['እሁድ', 'ሰኞ', 'ማክሰኞ', 'ረቡዕ', 'ሐሙስ', 'አርብ', 'ቅዳሜ'];

const startDayOfEthiopian = (year: number) => {
    const newYearDay = Math.floor(year / 100) - Math.floor(year / 400) - 4;
    return ((year - 1) % 4 === 3) ? newYearDay + 1 : newYearDay;
};

export const toEthiopian = (year: number, month: number, date: number): [number, number, number] => {
    const gregorianMonths = [0.0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const ethiopianMonths = [0.0, 30, 30, 30, 30, 30, 30, 30, 30, 30, 5, 30, 30, 30, 30];

    if ((year % 4 === 0 && year % 100 !== 0) || year % 400 === 0) {
        gregorianMonths[2] = 29;
    }

    let ethiopianYear = year - 8;
    if (ethiopianYear % 4 === 3) {
        ethiopianMonths[10] = 6;
    }

    const newYearDay = startDayOfEthiopian(year - 8);

    let until = 0;
    for (let i = 1; i < month; i++) {
        until += gregorianMonths[i];
    }
    until += date;

    let tahissas = (ethiopianYear % 4) === 0 ? 26 : 25;
    if (year < 1582) {
        ethiopianMonths[1] = 0;
        ethiopianMonths[2] = tahissas;
    } else if (until <= 277 && year === 1582) {
        ethiopianMonths[1] = 0;
        ethiopianMonths[2] = tahissas;
    } else {
        tahissas = newYearDay - 3;
        ethiopianMonths[1] = tahissas;
    }

    let m = 1;
    let ethiopianDate = 1;
    for (m = 1; m < ethiopianMonths.length; m++) {
        if (until <= ethiopianMonths[m]) {
            ethiopianDate = (m === 1 || ethiopianMonths[m] === 0) ? until + (30 - tahissas) : until;
            break;
        } else {
            until -= ethiopianMonths[m];
        }
    }

    if (m > 10) {
        ethiopianYear += 1;
    }

    const order = [0, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 1, 2, 3, 4];
    const ethiopianMonth = order[m];
    return [ethiopianYear, ethiopianMonth, ethiopianDate];
};

export const toGregorian = (year: number, month: number, date: number): [number, number, number] => {
    const newYearDay = startDayOfEthiopian(year);
    let gregorianYear = year + 7;
    let gregorianMonths = [0.0, 30, 31, 30, 31, 31, 28, 31, 30, 31, 30, 31, 31, 30];

    const nextYear = gregorianYear + 1;
    if ((nextYear % 4 === 0 && nextYear % 100 !== 0) || nextYear % 400 === 0) {
        gregorianMonths[6] = 29;
    }

    let until = ((month - 1) * 30.0) + date;
    if (until <= 37 && year <= 1575) {
        until += 28;
        gregorianMonths[0] = 31;
    } else {
        until += newYearDay - 1;
    }

    if ((year - 1) % 4 === 3) {
        until += 1;
    }

    let m = 0;
    let gregorianDate = 1;
    for (let i = 0; i < gregorianMonths.length; i++) {
        if (until <= gregorianMonths[i]) {
            m = i;
            gregorianDate = until;
            break;
        } else {
            m = i;
            until -= gregorianMonths[i];
        }
    }

    if (m > 4) {
        gregorianYear += 1;
    }

    const order = [8, 9, 10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8, 9];
    const gregorianMonth = order[m];
    return [gregorianYear, gregorianMonth, gregorianDate];
};

/**
 * Format date and time in 12-hour format, converting to Ethiopian Calendar if lang is 'am'.
 * All inputs are parsed from ISO or Date object.
 */
export const formatDT = (dateInput: Date | string | null | undefined, isAmharic = false) => {
    if (!dateInput) return { time: '—', date: '—', full: '—' };

    let date: Date;
    if (typeof dateInput === 'string') {
        // If string without timezone, replace space with T for valid Date parsing
        const cleaned = dateInput.replace(' ', 'T');
        date = new Date(cleaned);
    } else {
        date = dateInput;
    }

    if (isNaN(date.getTime())) {
        return { time: '—', date: '—', full: '—' };
    }

    const h = date.getHours();
    const m = date.getMinutes().toString().padStart(2, '0');

    // 12-hour format calculations
    if (!isAmharic) {
        const period = h >= 12 ? 'PM' : 'AM';
        let h12 = h % 12;
        if (h12 === 0) h12 = 12;
        const time12 = `${h12.toString().padStart(2, '0')}:${m} ${period}`;

        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        const dateStr = `${monthNames[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        const fullStr = `${dayNames[date.getDay()]}, ${dateStr}`;

        return {
            time: time12,
            date: dateStr,
            full: fullStr,
        };
    }

    // Ethiopian Calendar
    const [eyear, emonth, edate] = toEthiopian(
        date.getFullYear(),
        date.getMonth() + 1,
        date.getDate()
    );

    // Ethiopian 12-hour clock (starts at dawn 6:00 AM)
    let etH = h >= 6 ? h - 6 : h + 6;
    if (etH > 12) etH -= 12;
    if (etH === 0) etH = 12;

    let period = 'ከቀኑ';
    if (h >= 0 && h < 6) period = 'ከሌሊቱ';
    else if (h >= 6 && h < 12) period = 'ጠዋት';
    else if (h >= 12 && h < 18) period = 'ከቀኑ';
    else period = 'ከምሽቱ';

    const etTime = `${etH}:${m} ${period}`;
    const etDate = `${amMonths[emonth]} ${edate}, ${eyear}`;
    const etFull = `${amDays[date.getDay()]}፣ ${amMonths[emonth]} ${edate} ቀን ${eyear} ዓ.ም`;

    return {
        time: etTime,
        date: etDate,
        full: etFull,
    };
};
