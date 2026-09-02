<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import LookupRowActions from '@/components/finance/lookup/LookupRowActions.vue';
import SendToBatchBar from '@/components/finance/lookup/SendToBatchBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDataTable } from '@/composables/useDataTable';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import { useLookupSelection } from '@/composables/useLookupSelection';
import { usePermission } from '@/composables/usePermission';
import type { PaginatedResponse } from '@/types';
import { formatCurrency, getChargeStatusBadgeClass, getChargeStatusLabel, getChargeTypeBadgeClass, getChargeTypeLabel, type ChargeStatus, type ChargeType, type Semester } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronsUpDown, Plus, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface ChargeRow {
    id: number;
    student_id: number;
    student?: { id: number; full_name: string; student_id: string };
    charge_type: string;
    description: string;
    amount: number;
    status: string;
    semester?: { name: string };
}

interface ChargeFilters {
    search: string;
    student_id?: number | null;
    semester_id?: number | null;
    charge_type: string;
    status: string;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
    per_page: number;
}

const props = defineProps<{
    charges: PaginatedResponse<ChargeRow>;
    semesters: Semester[];
    chargeTypes: { value: string; label: string }[];
    student?: { id: number; full_name: string; student_id: string } | null;
    filters: ChargeFilters;
}>();

const permission = usePermission();
const sel = useLookupSelection();
const { selectedLabel } = useFinanceSemester();

const chargesIndexRoute = computed(() => (props.student ? route('finance.students.charges', props.student.id) : financeRoutes.lookup.chargeLedger()));

const createChargeRoute = computed(() => (props.student ? route('finance.charges.create', { student_id: props.student.id }) : route('finance.charges.create')));

const pageDescription = computed(() => (props.student ? `Quản lý các khoản phí và tín dụng của ${props.student.full_name} (${props.student.student_id})` : 'Tra cứu charge ledger toàn campus — sort, filter, chọn nhiều dòng để đẩy Batch Studio.'));

const { filters, setFilter, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useDataTable<ChargeFilters>({
    baseUrl: chargesIndexRoute.value,
    initialFilters: {
        search: props.filters.search ?? '',
        student_id: props.filters.student_id ?? null,
        charge_type: props.filters.charge_type ?? 'all',
        status: props.filters.status ?? 'all',
        sort: props.filters.sort ?? null,
        direction: props.filters.direction ?? null,
        per_page: props.filters.per_page ?? 20,
    },
    defaultValues: {
        charge_type: 'all',
        status: 'all',
        per_page: 20,
        sort: null,
        direction: null,
    },
    only: ['charges', 'filters'],
});

const canDng = computed(() => permission.can('create_finance_payments'));

const allRows = computed(() => props.charges.data.filter((charge) => charge.student_id).map((charge) => ({ key: charge.id, studentId: charge.student_id })));

function sortBy(column: string): void {
    handleSortChange(column, currentSort.value === column && currentDirection.value === 'asc' ? 'desc' : 'asc');
}

function openStudent(row: ChargeRow): void {
    router.visit(financeRoutes.students.overview(row.student_id, `charge:${row.id}`));
}
</script>

<template>
    <Head title="Finance Charges" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Finance Charges</h1>
                <p class="text-muted-foreground mt-1">{{ pageDescription }} · {{ selectedLabel }}</p>
            </div>
            <Link :href="createChargeRoute">
                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    Tạo khoản phí mới
                </Button>
            </Link>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-72">
                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <Input :model-value="filters.search" placeholder="Tìm mô tả / tên / mã SV…" class="pl-9" @update:model-value="handleSearch" />
            </div>
            <Select :model-value="filters.status" @update:model-value="(value) => setFilter('status', String(value))">
                <SelectTrigger class="w-44">
                    <SelectValue placeholder="Trạng thái" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Mọi trạng thái</SelectItem>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="void">Void</SelectItem>
                </SelectContent>
            </Select>
            <Select :model-value="filters.charge_type" @update:model-value="(value) => setFilter('charge_type', String(value))">
                <SelectTrigger class="w-44">
                    <SelectValue placeholder="Loại phí" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Mọi loại phí</SelectItem>
                    <SelectItem v-for="type in chargeTypes" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="bg-background sticky top-0 z-10 w-8">
                            <Checkbox :model-value="allRows.length > 0 && sel.count.value === allRows.length" @update:model-value="(value) => sel.toggleAll(allRows, !!value)" />
                        </TableHead>
                        <TableHead class="bg-background sticky top-0 z-10">Sinh viên</TableHead>
                        <TableHead class="bg-background sticky top-0 z-10 cursor-pointer" @click="sortBy('charge_type')">
                            Loại phí
                            <ChevronsUpDown class="inline h-3 w-3" />
                        </TableHead>
                        <TableHead class="bg-background sticky top-0 z-10">Mô tả</TableHead>
                        <TableHead class="bg-background sticky top-0 z-10">Học kỳ</TableHead>
                        <TableHead class="bg-background sticky top-0 z-10 cursor-pointer text-right" @click="sortBy('amount')">
                            Số tiền
                            <ChevronsUpDown class="inline h-3 w-3" />
                        </TableHead>
                        <TableHead class="bg-background sticky top-0 z-10 cursor-pointer" @click="sortBy('status')">
                            Trạng thái
                            <ChevronsUpDown class="inline h-3 w-3" />
                        </TableHead>
                        <TableHead class="bg-background sticky top-0 z-10 text-right">Thao tác</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="charge in charges.data" :key="charge.id" class="hover:bg-muted/50 cursor-pointer" @click="openStudent(charge)">
                        <TableCell @click.stop>
                            <Checkbox :model-value="sel.isSelected(charge.id)" @update:model-value="() => sel.toggle({ key: charge.id, studentId: charge.student_id })" />
                        </TableCell>
                        <TableCell>
                            <div class="font-medium">{{ charge.student?.full_name ?? 'N/A' }}</div>
                            <div class="text-muted-foreground text-xs tabular-nums">{{ charge.student?.student_id }}</div>
                        </TableCell>
                        <TableCell>
                            <Badge :class="getChargeTypeBadgeClass(charge.charge_type as ChargeType)">
                                {{ getChargeTypeLabel(charge.charge_type as ChargeType) }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-muted-foreground max-w-[220px] truncate">{{ charge.description }}</TableCell>
                        <TableCell>{{ charge.semester?.name ?? '-' }}</TableCell>
                        <TableCell class="text-right tabular-nums" :class="charge.amount < 0 ? 'text-emerald-600' : ''">
                            {{ formatCurrency(charge.amount) }}
                        </TableCell>
                        <TableCell>
                            <Badge :class="getChargeStatusBadgeClass(charge.status as ChargeStatus)">
                                {{ getChargeStatusLabel(charge.status as ChargeStatus) }}
                            </Badge>
                        </TableCell>
                        <TableCell @click.stop>
                            <LookupRowActions :student-id="charge.student_id" :focus="`charge:${charge.id}`" :detail-url="financeRoutes.lookup.chargeDetail(charge.id)" />
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="charges.data.length === 0" :colspan="8"> Không có khoản phí nào khớp bộ lọc. </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <DataPagination :pagination-data="charges" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
        <SendToBatchBar :count="sel.count.value" :can-dng="canDng" @dng="sel.sendToBatch()" @clear="sel.clear" />
    </div>
</template>
