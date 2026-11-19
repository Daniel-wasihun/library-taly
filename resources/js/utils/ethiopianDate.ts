/**
 * ethiopianDate.ts — Mathematically Exact Gregorian <-> Ethiopian Calendar (EC) & 12-hour Time Utility
 * Uses Astronomical Julian Day Number (JDN) algorithm with 100% bidirectional invertibility.
 */

export const amMonths = [
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

export const amDays = ['እሑድ', 'ሰኞ', 'ማክሰኞ', 'ረቡዕ', 'ሐሙስ', 'ዓርብ', 'ቅዳሜ'];

// Epoch of the Ethiopian Calendar (Meskerem 1, 1 EE corresponds to JDN 1724221)
const ETHIOPIC_EPOCH = 1724221;

/**
 * Convert Gregorian Calendar (year, month, day) to Julian Day Number (JDN).
 */
export const gregorianToJdn = (year: number, month: number, day: number): number => {
    const a = Math.floor((14 - month) / 12);
    const y = year + 4800 - a;
    const m = month + 12 * a - 3;
    return (
        day +
        Math.floor((153 * m + 2) / 5) +
        365 * y +
        Math.floor(y / 4) -
        Math.floor(y / 100) +
        Math.floor(y / 400) -
        32045
    );
};

/**
 * Convert Julian Day Number (JDN) to Gregorian Calendar [year, month, day].
 */
export const jdnToGregorian = (jdn: number): [number, number, number] => {
    const a = jdn + 32044;
    const b = Math.floor((4 * a + 3) / 146097);
    const c = a - Math.floor((146097 * b) / 4);
    const d = Math.floor((4 * c + 3) / 1461);
    const e = c - Math.floor((1461 * d) / 4);
    const m = Math.floor((5 * e + 2) / 153);
    const day = e - Math.floor((153 * m + 2) / 5) + 1;
    const month = m + 3 - 12 * Math.floor(m / 10);
    const year = 100 * b + d - 4800 + Math.floor(m / 10);
    return [year, month, day];
};

/**
 * Convert Ethiopian Calendar (year, month, day) to Julian Day Number (JDN).
 */
export const ethiopianToJdn = (year: number, month: number, day: number): number => {
    return (
        ETHIOPIC_EPOCH +
        (year - 1) * 365 +
        Math.floor(year / 4) +
        (month - 1) * 30 +
        day -
        1
    );
};

/**
 * Convert Julian Day Number (JDN) to Ethiopian Calendar [year, month, day].
 */
export const jdnToEthiopian = (jdn: number): [number, number, number] => {
    const daysSinceEpoch = jdn - ETHIOPIC_EPOCH;
    const cycle = Math.floor(daysSinceEpoch / 1461);
    const rem = daysSinceEpoch % 1461;

    let yInCycle = 0;
    let dayInYear = 0;

    if (rem < 365) {
        yInCycle = 0;
        dayInYear = rem;
    } else if (rem < 730) {
        yInCycle = 1;
        dayInYear = rem - 365;
    } else if (rem < 1096) {
        yInCycle = 2;
        dayInYear = rem - 730;
    } else {
        yInCycle = 3;
        dayInYear = rem - 1096;
    }

    const year = cycle * 4 + yInCycle + 1;
    const month = Math.floor(dayInYear / 30) + 1;
    const day = (dayInYear % 30) + 1;

    return [year, month, day];
};

/**
 * Convert Gregorian Calendar [Y, M, D] to Ethiopian Calendar [Y, M, D].
 * 100% Mathematically exact across all years.
 */
export const toEthiopian = (year: number, month: number, date: number): [number, number, number] => {
    const jdn = gregorianToJdn(year, month, date);
    return jdnToEthiopian(jdn);
};

/**
 * Convert Ethiopian Calendar [Y, M, D] to Gregorian Calendar [Y, M, D].
 * 100% Mathematically exact across all years.
 */
export const toGregorian = (year: number, month: number, date: number): [number, number, number] => {
    const jdn = ethiopianToJdn(year, month, date);
    return jdnToGregorian(jdn);
};

/**
 * Format date and time in 12-hour format, converting to Ethiopian Calendar if lang is 'am'.
 * All inputs are parsed from ISO string, YYYY-MM-DD date-only string, or Date object.
 */
export const formatDT = (dateInput: Date | string | null | undefined, isAmharic = false) => {
    if (!dateInput) return { time: '—', date: '—', full: '—' };

    // Handle date-only strings (YYYY-MM-DD) safely without UTC timezone shift
    if (typeof dateInput === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(dateInput.trim())) {
        const [gy, gm, gd] = dateInput.trim().split('-').map(Number);
        const dayOfWeekIndex = new Date(gy, gm - 1, gd).getDay();

        if (!isAmharic) {
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const dateStr = `${monthNames[gm - 1]} ${gd}, ${gy}`;
            const fullStr = `${dayNames[dayOfWeekIndex]}, ${dateStr}`;
            return { time: '', date: dateStr, full: fullStr };
        } else {
            const [ey, em, ed] = toEthiopian(gy, gm, gd);
            const etDate = `${amMonths[em]} ${ed}, ${ey}`;
            const etFull = `${amDays[dayOfWeekIndex]}፣ ${amMonths[em]} ${ed} ቀን ${ey} ዓ.ም`;
            return { time: '', date: etDate, full: etFull };
        }
    }

    let date: Date;
    if (typeof dateInput === 'string') {
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

    // 12-hour Gregorian format
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

    // 12-hour Ethiopian Calendar and Dawn-based Clock
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
