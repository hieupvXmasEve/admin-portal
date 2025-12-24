import { format } from 'date-fns';

export const formatDate = (date: string) => {
    return format(new Date(date), 'dd/MM/yyyy');
};

export const formatTime = (time: string) => {
    return format(new Date(time), 'HH:mm');
};

export const formatDateTime = (datetime: string) => {
    return format(new Date(datetime), 'HH:mm dd/MM/yyyy');
};
// format date to 01 Jun 2025
export const formatDateToShort = (date: string) => {
    return format(new Date(date), 'dd/MM/yyyy');
};
// time "2025-10-06T04:00:00.000000Z". format to DD/MM/YYYY HH:mm
export const formatDateTimeToShort = (date: string) => {
    return format(new Date(date), 'HH:mm dd/MM/yyyy');
};

// format date to full date and time
export const formatDateTimeToFull = (date: string) => {
    return format(new Date(date), 'EEE, dd/MM/yyyy hh:mm a');
};
