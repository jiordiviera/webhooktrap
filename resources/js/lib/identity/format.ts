/*
 * Times on the Identity screens are always UTC and always text (A11Y §9):
 * "9 Nov 2026", "11 Nov 2026, 13:52 UTC", "Today, 13:52 UTC", "14:58 UTC".
 * Formatting is manual so the server render and the browser agree.
 */

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

const pad = (n: number): string => String(n).padStart(2, '0');

function parse(iso: string | Date): Date {
    return iso instanceof Date ? iso : new Date(iso);
}

/** "9 Nov 2026" */
export function formatDay(iso: string | Date): string {
    const d = parse(iso);

    return `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
}

/** "14:58 UTC" */
export function formatTime(iso: string | Date): string {
    const d = parse(iso);

    return `${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())} UTC`;
}

/** "11 Nov 2026, 13:52 UTC" */
export function formatFull(iso: string | Date): string {
    return `${formatDay(iso)}, ${formatTime(iso)}`;
}

/** "Today, 13:52 UTC" when the day is today (UTC), otherwise the date. */
export function formatLastUsed(iso: string | Date, now = new Date()): string {
    const d = parse(iso);

    if (formatDay(d) === formatDay(now)) {
        return `Today, ${formatTime(d)}`;
    }

    return formatDay(d);
}

/**
 * The throttle answer (S-02 rev. 4): the server's retry time rounded up to
 * the minute, printed as "14:58 UTC". Nothing else is said about duration.
 */
export function retryLabel(iso: string): string {
    const d = parse(iso);
    const rounded = new Date(Math.ceil(d.getTime() / 60_000) * 60_000);

    return formatTime(rounded);
}

/** "2 days 19 hours left" (S-29 #5); never a live countdown. */
export function timeLeft(iso: string, now = new Date()): string {
    const ms = Math.max(0, parse(iso).getTime() - now.getTime());
    const hours = Math.floor(ms / 3_600_000);
    const days = Math.floor(hours / 24);
    const rest = hours % 24;
    const unit = (n: number, word: string) =>
        `${n} ${word}${n === 1 ? '' : 's'}`;

    if (days > 0) {
        return `${unit(days, 'day')} ${unit(rest, 'hour')} left`;
    }

    return `${unit(Math.max(rest, 1), 'hour')} left`;
}

/** Guest meter time: "23 h 49 m". */
export function meterLeft(iso: string, now = new Date()): string {
    const minutes = Math.max(
        0,
        Math.floor((parse(iso).getTime() - now.getTime()) / 60_000),
    );

    return `${Math.floor(minutes / 60)} h ${minutes % 60} m`;
}
