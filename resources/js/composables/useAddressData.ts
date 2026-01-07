import { ref, computed } from 'vue';

interface Province {
    code: string;
    name: string;
}

interface Ward {
    code: string;
    name: string;
    province: string;
}

export function useAddressData() {
    const ethnicities = ref<string[]>([]);
    const provinces = ref<Province[]>([]);
    const wards = ref<Ward[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    // Load ethnicities
    const loadEthnicities = async () => {
        if (ethnicities.value.length > 0) return;

        try {
            loading.value = true;
            const response = await fetch('/data/dan-toc.json');
            if (!response.ok) throw new Error('Failed to load ethnicities');
            ethnicities.value = await response.json();
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load ethnicities';
            console.error('Error loading ethnicities:', e);
        } finally {
            loading.value = false;
        }
    };

    // Load provinces
    const loadProvinces = async () => {
        if (provinces.value.length > 0) return;

        try {
            loading.value = true;
            const response = await fetch('/data/cap-tinh.json');
            if (!response.ok) throw new Error('Failed to load provinces');
            provinces.value = await response.json();
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load provinces';
            console.error('Error loading provinces:', e);
        } finally {
            loading.value = false;
        }
    };

    // Load wards
    const loadWards = async () => {
        if (wards.value.length > 0) return;

        try {
            loading.value = true;
            const response = await fetch('/data/cap-xa.json');
            if (!response.ok) throw new Error('Failed to load wards');
            wards.value = await response.json();
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load wards';
            console.error('Error loading wards:', e);
        } finally {
            loading.value = false;
        }
    };

    // Get wards filtered by province
    const getWardsByProvince = computed(() => {
        return (provinceName: string | null | undefined) => {
            if (!provinceName) return [];
            return wards.value.filter((ward) => ward.province === provinceName);
        };
    });

    // Load all data
    const loadAll = async () => {
        await Promise.all([loadEthnicities(), loadProvinces(), loadWards()]);
    };

    return {
        ethnicities,
        provinces,
        wards,
        loading,
        error,
        loadEthnicities,
        loadProvinces,
        loadWards,
        loadAll,
        getWardsByProvince,
    };
}

