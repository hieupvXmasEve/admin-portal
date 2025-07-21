<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { GpaData, GpaRecord } from '@/types/models';
import {
    AlertTriangle,
    Award,
    BarChart3,
    Calendar,
    Filter,
    GraduationCap,
    Search,
    TrendingDown,
    TrendingUp,
    Trophy,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    gpa: GpaData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

// Reactive filters
const searchQuery = ref('');
const selectedYear = ref<string>('');

// Computed filtered data
const filteredGpaRecords = computed(() => {
    let filtered = props.gpa.data;

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(record => 
            record.semester.toLowerCase().includes(query) ||
            record.semester_code.toLowerCase().includes(query) ||
            record.academic_year.toLowerCase().includes(query)
        );
    }

    // Year filter
    if (selectedYear.value) {
        filtered = filtered.filter(record => record.academic_year === selectedYear.value);
    }

    return filtered;
});

// GPA styling
const getGpaColor = (gpa: number) => {
    if (gpa >= 3.5) return 'text-green-600';
    if (gpa >= 3.0) return 'text-blue-600';
    if (gpa >= 2.5) return 'text-yellow-600';
    return 'text-red-600';
};

const getGpaBadgeVariant = (gpa: number) => {
    if (gpa >= 3.5) return 'default';
    if (gpa >= 3.0) return 'secondary';
    if (gpa >= 2.5) return 'outline';
    return 'destructive';
};

const getAcademicStandingColor = (standing: string) => {
    const colors: Record<string, string> = {
        excellent: 'text-green-600',
        good_standing: 'text-green-600',
        satisfactory: 'text-blue-600',
        warning: 'text-yellow-600',
        probation: 'text-red-600',
        suspension: 'text-red-600',
    };
    return colors[standing] || 'text-gray-600';
};

const getAcademicStandingBadge = (standing: string) => {
    const variants: Record<string, string> = {
        excellent: 'default',
        good_standing: 'default',
        satisfactory: 'secondary',
        warning: 'outline',
        probation: 'destructive',
        suspension: 'destructive',
    };
    return variants[standing] || 'outline';
};

const getTrendIcon = (trend: string) => {
    const icons: Record<string, any> = {
        improving: TrendingUp,
        declining: TrendingDown,
        stable: BarChart3,
        insufficient_data: BarChart3,
    };
    return icons[trend] || BarChart3;
};

const getTrendColor = (trend: string) => {
    const colors: Record<string, string> = {
        improving: 'text-green-600',
        declining: 'text-red-600',
        stable: 'text-blue-600',
        insufficient_data: 'text-gray-600',
    };
    return colors[trend] || 'text-gray-600';
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatGpa = (gpa: number) => {
    return gpa > 0 ? gpa.toFixed(2) : 'N/A';
};

const formatStatus = (status: string) => {
    return status.replace(/_/g, ' ').toUpperCase();
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedYear.value = '';
};

const hasActiveFilters = computed(() => {
    return searchQuery.value || selectedYear.value;
});

// Get unique academic years for filter dropdown
const availableYears = computed(() => {
    const years = new Set(props.gpa.data.map(record => record.academic_year));
    return Array.from(years).sort().reverse();
});

// Check for academic concerns
const hasAcademicConcerns = computed(() => {
    const { current_academic_standing, probation_semesters, warning_semesters } = props.gpa.summary;
    return ['warning', 'probation', 'suspension'].includes(current_academic_standing) || 
           probation_semesters > 0 || warning_semesters > 0;
});

// Calculate GPA progression for simple chart
const gpaProgression = computed(() => {
    return props.gpa.data.slice().reverse().map((record, index) => ({
        semester: record.semester_code,
        gpa: record.semester_gpa,
        cumulative: record.cumulative_gpa,
        index: index + 1,
    }));
});
</script>

<template>
    <div class="space-y-6">
        <!-- Loading State -->
        <div v-if="loading" class="space-y-4">
            <div class="animate-pulse">
                <div class="h-8 bg-gray-200 rounded w-1/3 mb-4"></div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div v-for="i in 4" :key="i" class="h-24 bg-gray-200 rounded"></div>
                </div>
                <div class="h-64 bg-gray-200 rounded mb-6"></div>
                <div class="h-96 bg-gray-200 rounded"></div>
            </div>
        </div>

        <!-- Content -->
        <div v-else>
            <!-- Academic Concerns Alert -->
            <Alert v-if="hasAcademicConcerns" variant="destructive" class="mb-6">
                <AlertTriangle class="h-4 w-4" />
                <AlertDescription>
                    <strong>Academic Standing Alert:</strong> 
                    Current standing: {{ formatStatus(gpa.summary.current_academic_standing) }}.
                    <span v-if="gpa.summary.probation_semesters > 0">
                        {{ gpa.summary.probation_semesters }} probation semester(s).
                    </span>
                    <span v-if="gpa.summary.warning_semesters > 0">
                        {{ gpa.summary.warning_semesters }} warning semester(s).
                    </span>
                    Please review academic performance.
                </AlertDescription>
            </Alert>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Current GPA</p>
                                <p 
                                    class="text-2xl font-bold"
                                    :class="getGpaColor(gpa.summary.current_semester_gpa)"
                                >
                                    {{ formatGpa(gpa.summary.current_semester_gpa) }}
                                </p>
                            </div>
                            <GraduationCap class="h-8 w-8 text-blue-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Cumulative GPA</p>
                                <p 
                                    class="text-2xl font-bold"
                                    :class="getGpaColor(gpa.summary.current_cumulative_gpa)"
                                >
                                    {{ formatGpa(gpa.summary.current_cumulative_gpa) }}
                                </p>
                            </div>
                            <BarChart3 class="h-8 w-8 text-purple-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Credits Earned</p>
                                <p class="text-2xl font-bold text-green-600">{{ gpa.summary.total_credit_hours_earned }}</p>
                            </div>
                            <Award class="h-8 w-8 text-green-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Dean's List</p>
                                <p class="text-2xl font-bold text-yellow-600">{{ gpa.summary.dean_list_semesters }}</p>
                            </div>
                            <Trophy class="h-8 w-8 text-yellow-600" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- GPA Trend Overview -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <component 
                            :is="getTrendIcon(gpa.summary.gpa_trend)"
                            :class="getTrendColor(gpa.summary.gpa_trend)"
                            class="h-5 w-5"
                        />
                        GPA Trend Analysis
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Trend Summary -->
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-muted-foreground">Overall Trend</p>
                                <div class="flex items-center gap-2">
                                    <component 
                                        :is="getTrendIcon(gpa.summary.gpa_trend)"
                                        :class="getTrendColor(gpa.summary.gpa_trend)"
                                        class="h-5 w-5"
                                    />
                                    <Badge :variant="gpa.summary.gpa_trend === 'improving' ? 'default' : 
                                                   gpa.summary.gpa_trend === 'declining' ? 'destructive' : 'secondary'">
                                        {{ formatStatus(gpa.summary.gpa_trend) }}
                                    </Badge>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Academic Standing</p>
                                <Badge 
                                    :variant="getAcademicStandingBadge(gpa.summary.current_academic_standing) as any"
                                    :class="getAcademicStandingColor(gpa.summary.current_academic_standing)"
                                >
                                    {{ formatStatus(gpa.summary.current_academic_standing) }}
                                </Badge>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Quality Points</p>
                                <p class="font-medium">{{ gpa.summary.total_quality_points.toFixed(2) }}</p>
                            </div>
                        </div>

                        <!-- Simple GPA Chart -->
                        <div class="md:col-span-2">
                            <div class="space-y-2">
                                <p class="text-sm font-medium">GPA Progression (Last 8 Semesters)</p>
                                <div class="h-32 flex items-end justify-between gap-1 p-4 bg-muted rounded-lg">
                                    <div 
                                        v-for="(point, index) in gpaProgression.slice(-8)" 
                                        :key="index"
                                        class="flex flex-col items-center gap-1 flex-1"
                                    >
                                        <div class="text-xs text-muted-foreground">{{ formatGpa(point.gpa) }}</div>
                                        <div 
                                            class="w-full rounded-t transition-all duration-300"
                                            :class="getGpaColor(point.gpa).replace('text-', 'bg-')"
                                            :style="{ height: `${Math.max((point.gpa / 4.0) * 80, 8)}px` }"
                                        ></div>
                                        <div class="text-xs text-muted-foreground transform -rotate-45 origin-center">
                                            {{ point.semester }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Filters -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <Filter class="h-5 w-5" />
                            Filters
                        </span>
                        <Button 
                            v-if="hasActiveFilters" 
                            variant="outline" 
                            size="sm" 
                            @click="clearFilters"
                            class="flex items-center gap-2"
                        >
                            <X class="h-4 w-4" />
                            Clear
                        </Button>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Search -->
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search semesters..."
                                class="pl-10"
                            />
                        </div>

                        <!-- Year Filter -->
                        <Select v-model="selectedYear">
                            <SelectTrigger>
                                <SelectValue placeholder="All Years" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">All Years</SelectItem>
                                <SelectItem 
                                    v-for="year in availableYears" 
                                    :key="year" 
                                    :value="year"
                                >
                                    {{ year }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <!-- Spacer -->
                        <div></div>

                        <!-- Results Count -->
                        <div class="flex items-center text-sm text-muted-foreground">
                            {{ filteredGpaRecords.length }} of {{ gpa.data.length }} semesters
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- GPA Transcript Table -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BarChart3 class="h-5 w-5" />
                        Academic Transcript
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Semester</TableHead>
                                    <TableHead>Academic Year</TableHead>
                                    <TableHead>Semester GPA</TableHead>
                                    <TableHead>Cumulative GPA</TableHead>
                                    <TableHead>Credits Attempted</TableHead>
                                    <TableHead>Credits Earned</TableHead>
                                    <TableHead>Quality Points</TableHead>
                                    <TableHead>Academic Standing</TableHead>
                                    <TableHead>Honors</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="filteredGpaRecords.length === 0">
                                    <TableCell colspan="9" class="text-center py-8 text-muted-foreground">
                                        No GPA records found matching your criteria.
                                    </TableCell>
                                </TableRow>
                                <TableRow 
                                    v-for="record in filteredGpaRecords" 
                                    :key="record.id"
                                    class="hover:bg-muted/50"
                                >
                                    <TableCell>
                                        <div>
                                            <p class="font-medium">{{ record.semester }}</p>
                                            <p class="text-sm text-muted-foreground">{{ record.semester_code }}</p>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span class="font-medium">{{ record.academic_year }}</span>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getGpaBadgeVariant(record.semester_gpa) as any">
                                            {{ formatGpa(record.semester_gpa) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getGpaBadgeVariant(record.cumulative_gpa) as any">
                                            {{ formatGpa(record.cumulative_gpa) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <span class="font-medium">{{ record.credit_hours_attempted }}</span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="font-medium text-green-600">{{ record.credit_hours_earned }}</span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="font-medium">{{ record.quality_points.toFixed(2) }}</span>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getAcademicStandingBadge(record.academic_standing) as any">
                                            {{ formatStatus(record.academic_standing) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex gap-1">
                                            <Badge v-if="record.dean_list_eligible" variant="secondary" class="text-xs">
                                                Dean's List
                                            </Badge>
                                            <Badge v-if="record.honors_eligible" variant="secondary" class="text-xs">
                                                Honors
                                            </Badge>
                                            <Badge v-if="record.probation_status" variant="destructive" class="text-xs">
                                                Probation
                                            </Badge>
                                            <Badge v-if="record.warning_status" variant="outline" class="text-xs">
                                                Warning
                                            </Badge>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
