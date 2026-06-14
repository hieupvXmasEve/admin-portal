import { format } from 'date-fns';

export const formatCurrency = (amount: number | string | null | undefined): string => {
    if (amount === null || amount === undefined) {
        return '0 ₫';
    }
    const val = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (isNaN(val)) return '0 ₫';

    // Using Vietnamese locale for currency
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(val);
};

export const formatDate = (date: string | null | undefined): string => {
    if (!date) return '-';
    try {
        return format(new Date(date), 'dd/MM/yyyy HH:mm');
    } catch {
        return date;
    }
};

export const formatDateTime = (date: string | null | undefined): string => {
    if (!date) return '-';
    try {
        return format(new Date(date), 'HH:mm dd/MM/yyyy');
    } catch {
        return date;
    }
};
