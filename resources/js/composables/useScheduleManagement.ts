import type { ScheduleMatrix, ScheduleSession, TimeSlot, WeeklyGridData } from '@/types/schedule';
import { useUrlSearchParams } from '@vueuse/core';
import { addDays, endOfWeek, format, isValid, isWithinInterval, parse, parseISO, startOfWeek } from 'date-fns';
import { computed, ref, watch } from 'vue';

export function useScheduleManagement() {
    const params = useUrlSearchParams('history');

    // Initialize from URL param or default to today
    const getInitialDate = () => {
        if (params.date && typeof params.date === 'string') {
            const parsed = parse(params.date, 'yyyy-MM-dd', new Date());
            if (isValid(parsed)) {
                return parsed;
            }
        }
        return new Date();
    };

    const selectedWeek = ref(getInitialDate());

    // Sync state to URL
    watch(selectedWeek, (newDate) => {
        params.date = format(newDate, 'yyyy-MM-dd');
    });

    // Time slots for the grid (8:00 AM to 8:00 PM)
    const timeSlots = computed<TimeSlot[]>(() => {
        const slots: TimeSlot[] = [];
        for (let hour = 7; hour <= 20; hour++) {
            slots.push({
                hour,
                displayTime: format(new Date().setHours(hour, 0, 0, 0), 'HH:mm'),
            });
        }
        return slots;
    });

    // Get the current week's date range
    const currentWeekRange = computed(() => {
        const start = startOfWeek(selectedWeek.value, { weekStartsOn: 1 });
        const end = endOfWeek(selectedWeek.value, { weekStartsOn: 1 });

        return {
            start,
            end,
            formatted: {
                start: format(start, 'yyyy-MM-dd'),
                end: format(end, 'yyyy-MM-dd'),
            },
        };
    });

    // Get all days of the current week
    const weekDays = computed(() => {
        const startDate = currentWeekRange.value.start;
        const days = [];

        for (let i = 0; i < 7; i++) {
            const date = addDays(startDate, i);
            days.push({
                date,
                dateString: format(date, 'yyyy-MM-dd'),
                displayDate: format(date, 'EEE dd'),
                fullDate: format(date, 'EEEE, MMMM dd'),
                dayName: format(date, 'EEEE'),
                isToday: format(date, 'yyyy-MM-dd') === format(new Date(), 'yyyy-MM-dd'),
            });
        }

        return days;
    });

    // Build a matrix of sessions for O(1) lookup: matrix[date][hour] -> sessions[]
    const buildScheduleMatrix = (sessions: ScheduleSession[]): ScheduleMatrix => {
        const matrix: ScheduleMatrix = {};

        sessions.forEach((session) => {
            const date = session.date;
            if (!matrix[date]) {
                matrix[date] = {};
            }

            try {
                const startHour = parseInt(session.startTime.split(':')[0]);
                const endHour = parseInt(session.endTime.split(':')[0]);

                // Iterate through all hours covered by this session
                // Logic matches getSessionsForSlot: sessionStart <= hour && sessionEnd > hour
                for (let h = startHour; h < endHour; h++) {
                    if (!matrix[date][h]) {
                        matrix[date][h] = [];
                    }
                    matrix[date][h].push(session);
                }
            } catch (e) {
                console.error('Error parsing session time for matrix', session, e);
            }
        });

        return matrix;
    };

    // Filter sessions for the current week
    const filterSessionsForWeek = (sessions: ScheduleSession[]) => {
        const { start, end } = currentWeekRange.value;

        return sessions.filter((session) => {
            try {
                const sessionDate = parseISO(session.date);
                return isWithinInterval(sessionDate, { start, end });
            } catch {
                return false;
            }
        });
    };

    // Group sessions by date
    const groupSessionsByDate = (sessions: ScheduleSession[]): WeeklyGridData => {
        const grouped: WeeklyGridData = {};

        sessions.forEach((session) => {
            const date = session.date;
            if (!grouped[date]) {
                grouped[date] = [];
            }
            grouped[date].push(session);
        });

        return grouped;
    };

    // Get sessions for a specific date and time slot (Legacy O(N) approach)
    const getSessionsForSlot = (sessionsByDate: WeeklyGridData, dateString: string, hour: number): ScheduleSession[] => {
        const sessions = sessionsByDate[dateString] || [];

        return sessions.filter((session) => {
            try {
                const sessionStart = parseInt(session.startTime.split(':')[0]);
                const sessionEnd = parseInt(session.endTime.split(':')[0]);

                // Check if the time slot overlaps with the session
                return sessionStart <= hour && sessionEnd > hour;
            } catch {
                return false;
            }
        });
    };

    // Get sessions from matrix (O(1) approach)
    const getSessionsFromMatrix = (matrix: ScheduleMatrix, dateString: string, hour: number): ScheduleSession[] => {
        return matrix[dateString]?.[hour] || [];
    };

    // Check if multiple sessions overlap in the same time slot
    const hasOverlappingSessions = (sessions: ScheduleSession[], hour: number): boolean => {
        // If passing array directly (from matrix)
        return sessions.length > 1;
    };

    // Get overlapping sessions count for a time slot
    // Optimized to take the sessions array directly
    const getOverlappingSessionsCount = (sessionsInSlot: ScheduleSession[]): number => {
        // Count unique sessions (by ID) to avoid counting spanning sessions multiple times
        // Note: Matrix building ensures we don't duplicate session in the SAME slot list usually,
        // but defensive coding is good. However, for performance, we might assume uniqueness if buildScheduleMatrix is correct.
        // In buildScheduleMatrix, we iterate sessions and push. A session is pushed once per hour.
        // So sessionsInSlot should be unique.
        return sessionsInSlot.length;
    };

    // Check if we should show overlap indicator (more than 2 sessions)
    const shouldShowOverlapIndicator = (sessionsInSlot: ScheduleSession[]): boolean => {
        return sessionsInSlot.length > 0;
    };

    // Get visual state for a session to determine width and position
    const getSessionVisualState = (session: ScheduleSession, matrix: ScheduleMatrix): { colSpan: number; colStart: number; totalCols: number } => {
        try {
            const date = session.date;
            const startHour = parseInt(session.startTime.split(':')[0]);
            const span = Math.ceil(getSessionSpan(session));
            const endHour = startHour + span;

            // Find all unique intersecting sessions
            const overlappingSessions = new Set<number>();
            overlappingSessions.add(session.id);

            let maxConcurrency = 1;

            for (let h = startHour; h < endHour; h++) {
                const sessionsInSlot = matrix[date]?.[h] || [];
                if (sessionsInSlot.length > maxConcurrency) {
                    maxConcurrency = sessionsInSlot.length;
                }
                sessionsInSlot.forEach((s) => overlappingSessions.add(s.id));
            }

            // If concurrency > 2, we can't show properly in split view (handled by modal logic usually)
            // But if we are here, we might be forcing a 2-split or similar.
            // Based on requirement "visual split width for 2 sessions", we treat max 2.
            // If maxConcurrency > 2, the grid usually shows the "Cluster" icon for that slot,
            // but the session card might still try to render.
            // We should probably default to full width or hidden if it's too complex,
            // but let's stick to the 2-column logic for <= 2.

            if (maxConcurrency === 1) {
                return { colSpan: 2, colStart: 1, totalCols: 2 }; // Full width (2/2)
            }

            // Get array of overlapping sessions to determine order
            // We need to fetch the actual session objects to sort them
            // Since we only have IDs in the Set, and the matrix has the objects...
            // We can grab them from the first slot they appear in or similar.
            // Efficient way: iterate the Set, find objects.
            // Actually, we can just collect objects during the loop.

            const uniqueSessionsMap = new Map<number, ScheduleSession>();
            uniqueSessionsMap.set(session.id, session);

            for (let h = startHour; h < endHour; h++) {
                const sessionsInSlot = matrix[date]?.[h] || [];
                sessionsInSlot.forEach((s) => uniqueSessionsMap.set(s.id, s));
            }

            const sortedSessions = Array.from(uniqueSessionsMap.values()).sort((a, b) => {
                // Sort by start time, then ID
                const startA = parseInt(a.startTime.split(':')[0]) * 60 + parseInt(a.startTime.split(':')[1]);
                const startB = parseInt(b.startTime.split(':')[0]) * 60 + parseInt(b.startTime.split(':')[1]);

                if (startA !== startB) return startA - startB;
                return a.id - b.id;
            });

            const index = sortedSessions.findIndex((s) => s.id === session.id);

            // 2 columns system
            // Index 0 -> Left (Start 1)
            // Index 1 -> Right (Start 2)
            // If index >= 2, effectively hidden or piled (handled by Z-index in CSS usually)

            return {
                colSpan: 1,
                colStart: (index % 2) + 1,
                totalCols: 2,
            };
        } catch (e) {
            console.error('Error calculating visual state', e);
            return { colSpan: 2, colStart: 1, totalCols: 2 };
        }
    };

    // Calculate how many time slots a session spans
    const getSessionSpan = (session: ScheduleSession): number => {
        try {
            const [startHour, startMinute] = session.startTime.split(':').map(Number);
            const [endHour, endMinute] = session.endTime.split(':').map(Number);

            // Calculate span in hours (minimum 1 hour)
            let span = endHour - startHour;
            if (endMinute > startMinute) span += 0.5;
            if (endMinute < startMinute) span -= 0.5;

            return Math.max(1, span);
        } catch {
            return 1;
        }
    };

    // Check if a session starts at a specific hour
    const sessionStartsAtSlot = (session: ScheduleSession, hour: number): boolean => {
        try {
            const sessionStart = parseInt(session.startTime.split(':')[0]);
            return sessionStart === hour;
        } catch {
            return false;
        }
    };

    // Navigation functions
    const navigateWeek = (direction: 'prev' | 'next') => {
        const newDate = new Date(selectedWeek.value);
        const daysToAdd = direction === 'next' ? 7 : -7;
        newDate.setDate(newDate.getDate() + daysToAdd);
        selectedWeek.value = newDate;
    };

    const goToToday = () => {
        selectedWeek.value = new Date();
    };

    const setWeek = (date: Date) => {
        selectedWeek.value = new Date(date);
    };

    // Format utilities
    const formatSessionTime = (startTime: string, endTime: string): string => {
        try {
            return `${startTime} - ${endTime}`;
        } catch {
            return 'Invalid time';
        }
    };

    const formatSessionDuration = (startTime: string, endTime: string): string => {
        try {
            const [startHour, startMinute] = startTime.split(':').map(Number);
            const [endHour, endMinute] = endTime.split(':').map(Number);

            const durationMinutes = endHour * 60 + endMinute - (startHour * 60 + startMinute);
            const hours = Math.floor(durationMinutes / 60);
            const minutes = durationMinutes % 60;

            if (hours > 0 && minutes > 0) {
                return `${hours}h ${minutes}m`;
            } else if (hours > 0) {
                return `${hours}h`;
            } else {
                return `${minutes}m`;
            }
        } catch {
            return 'Unknown duration';
        }
    };

    // Session status utilities
    const getSessionStatusColor = (status: string) => {
        switch (status) {
            case 'scheduled':
                return 'blue';
            case 'in_progress':
                return 'yellow';
            case 'completed':
                return 'green';
            case 'cancelled':
                return 'red';
            default:
                return 'gray';
        }
    };

    const getSessionStatusBadgeVariant = (status: string) => {
        switch (status) {
            case 'scheduled':
                return 'default';
            case 'in_progress':
                return 'warning';
            case 'completed':
                return 'success';
            case 'cancelled':
                return 'destructive';
            default:
                return 'secondary';
        }
    };

    return {
        // State
        selectedWeek,

        // Computed
        timeSlots,
        currentWeekRange,
        weekDays,

        // Methods
        buildScheduleMatrix,
        filterSessionsForWeek,
        groupSessionsByDate,
        getSessionsForSlot,
        getSessionsFromMatrix,
        hasOverlappingSessions,
        getOverlappingSessionsCount,
        shouldShowOverlapIndicator,
        getSessionVisualState,
        getSessionSpan,
        sessionStartsAtSlot,
        navigateWeek,
        goToToday,
        setWeek,
        formatSessionTime,
        formatSessionDuration,
        getSessionStatusColor,
        getSessionStatusBadgeVariant,
    };
}
