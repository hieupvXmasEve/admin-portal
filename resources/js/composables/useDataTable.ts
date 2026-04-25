import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref, readonly, nextTick, type Ref } from 'vue';

// Core type definitions
export type FilterValue = string | number | boolean | string[] | null;
export type SortDirection = 'asc' | 'desc' | null;

export interface DataTablePagination {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
}

export interface DataTableSort {
    field: string | null;
    direction: SortDirection;
}

export interface DataTableValidation {
    errors: Record<string, string[]>;
    isValid: boolean;
    validating: Record<string, boolean>;
}

export interface DataTableDependencies {
    resolved: Record<string, any>;
    loading: Record<string, boolean>;
}

export interface DataTableState<T = any> {
    data: T[];
    pagination: DataTablePagination;
    filters: Record<string, FilterValue>;
    sort: DataTableSort;
    loading: boolean;
    error: string | null;
    validation: DataTableValidation;
    dependencies: DataTableDependencies;
}

export interface ValidationRule {
    validate: (value: FilterValue, allFilters: Record<string, FilterValue>) => string | null | Promise<string | null>;
    message: string;
    async?: boolean;
}

export interface FilterDependency {
    dependsOn: string[];
    resolve: (dependencyValues: any[]) => Promise<FilterValue>;
    loading?: boolean;
}

export interface DataTableConfig<T = any> {
    /**
     * Base URL for navigation
     */
    baseUrl: string;
    
    /**
     * Initial filter values
     */
    initialFilters?: Record<string, FilterValue>;
    
    /**
     * Default values that should not appear in URL
     */
    defaultValues?: Partial<Record<string, FilterValue>>;
    
    /**
     * Initial pagination settings
     */
    initialPagination?: Partial<DataTablePagination>;
    
    /**
     * Global debounce delay in ms
     */
    debounce?: number;
    
    /**
     * Per-field debounce configuration
     */
    fieldDebounce?: Record<string, number>;
    
    /**
     * Fields that should not be debounced (immediate navigation)
     */
    immediateFields?: string[];
    
    /**
     * Inertia partial reload keys
     */
    only?: string[];
    
    /**
     * Preserve scroll position on navigation
     */
    preserveScroll?: boolean;
    
    /**
     * Error callback for navigation failures
     */
    onError?: (error: any) => void;
    
    /**
     * Success callback for navigation completion
     */
    onSuccess?: () => void;
}

export interface DataTableInstance<T = any> {
    // State
    state: Readonly<DataTableState<T>>;
    
    // Core methods
    apply: (filters?: Partial<Record<string, FilterValue>>) => void;
    setFilter: <K extends string>(key: K, value: FilterValue, options?: { skipValidation?: boolean }) => Promise<void>;
    clearFilter: (key: string) => void;
    clearAllFilters: () => void;
    
    // Navigation methods
    setSort: (field: string, direction: SortDirection) => void;
    clearSort: () => void;
    setPage: (page: number) => void;
    setPerPage: (perPage: number) => void;
    refresh: () => void;
    
    // Validation methods
    addValidationRule: (filterKey: string, rule: ValidationRule) => void;
    validateFilter: (key: string, value: FilterValue) => string[];
    validateAll: () => Record<string, string[]>;
    
    // Dependency methods
    addDependency: (filterKey: string, dependency: FilterDependency) => void;
    resolveDependencies: (changedFilter?: string) => Promise<void>;
    
    // Computed properties
    hasActiveFilters: Ref<boolean>;
    currentPage: Ref<number>;
    totalPages: Ref<number>;
    isFirstPage: Ref<boolean>;
    isLastPage: Ref<boolean>;
    
    // Loading states
    isLoading: Ref<boolean>;
    hasError: Ref<boolean>;
    errorMessage: Ref<string | null>;
    
    // Validation computed
    hasValidationErrors: Ref<boolean>;
    validationErrors: Ref<Record<string, string[]>>;
    isValidating: Ref<boolean>;
    fieldValidating: Ref<Record<string, boolean>>;
    
    // Dependency computed
    isResolvingDependencies: Ref<boolean>;
    
    // Performance metrics
    performanceMetrics: Readonly<{
        requestCount: number;
        lastRequestTime: number;
        cachedRequests: number;
        debouncedFunctions: number;
    }>;
}

/**
 * Filter Validator class for handling validation logic
 */
class FilterValidator {
    private rules: Map<string, ValidationRule[]> = new Map();
    private validationPromises: Map<string, Promise<void>> = new Map();

    constructor(private state: DataTableState) {}

    addRule(filterKey: string, rule: ValidationRule): void {
        if (!this.rules.has(filterKey)) {
            this.rules.set(filterKey, []);
        }
        this.rules.get(filterKey)!.push(rule);
    }

    async validateFilter(key: string, value: FilterValue): Promise<string[]> {
        // Cancel any existing validation for this field
        if (this.validationPromises.has(key)) {
            // Note: We don't actually cancel promises, but we'll ignore their results
            this.validationPromises.delete(key);
        }

        const rules = this.rules.get(key) || [];
        const errors: string[] = [];
        const asyncRules: ValidationRule[] = [];

        // Clear previous errors and set validating state
        delete this.state.validation.errors[key];
        this.state.validation.validating[key] = true;

        // Run sync validations first
        for (const rule of rules) {
            if (rule.async) {
                asyncRules.push(rule);
                continue;
            }

            try {
                const error = rule.validate(value, this.state.filters);
                if (error) {
                    errors.push(error);
                }
            } catch (err) {
                console.error(`Sync validation error for field ${key}:`, err);
                errors.push('Validation error occurred');
            }
        }

        // Update state with sync validation results
        if (errors.length > 0) {
            this.state.validation.errors[key] = errors;
        }
        this.state.validation.isValid = Object.keys(this.state.validation.errors).length === 0;

        // Run async validations if any
        if (asyncRules.length > 0) {
            const validationPromise = this.runAsyncValidations(key, value, asyncRules, errors);
            this.validationPromises.set(key, validationPromise);
            
            try {
                await validationPromise;
            } catch (err) {
                // Error already handled in runAsyncValidations
            } finally {
                this.validationPromises.delete(key);
                this.state.validation.validating[key] = false;
            }
        } else {
            this.state.validation.validating[key] = false;
        }

        return errors;
    }

    private async runAsyncValidations(
        key: string, 
        value: FilterValue, 
        asyncRules: ValidationRule[], 
        existingErrors: string[]
    ): Promise<void> {
        const asyncErrors: string[] = [];

        for (const rule of asyncRules) {
            try {
                const error = await rule.validate(value, this.state.filters);
                if (error) {
                    asyncErrors.push(error);
                }
            } catch (err) {
                console.error(`Async validation error for field ${key}:`, err);
                asyncErrors.push('Validation error occurred');
            }
        }

        // Update final validation state
        const allErrors = [...existingErrors, ...asyncErrors];
        if (allErrors.length > 0) {
            this.state.validation.errors[key] = allErrors;
        } else {
            delete this.state.validation.errors[key];
        }
        this.state.validation.isValid = Object.keys(this.state.validation.errors).length === 0;
    }

    async validateAll(): Promise<Record<string, string[]>> {
        const allErrors: Record<string, string[]> = {};
        const validationPromises: Promise<void>[] = [];

        for (const [key, value] of Object.entries(this.state.filters)) {
            const validationPromise = this.validateFilter(key, value).then(errors => {
                if (errors.length > 0) {
                    allErrors[key] = errors;
                }
            });
            validationPromises.push(validationPromise);
        }

        await Promise.all(validationPromises);
        return allErrors;
    }

    isValidating(): boolean {
        return Object.values(this.state.validation.validating).some(validating => validating);
    }

    getFieldValidating(): Record<string, boolean> {
        return { ...this.state.validation.validating };
    }

    getMethods() {
        return {
            addValidationRule: this.addRule.bind(this),
            validateFilter: this.validateFilter.bind(this),
            validateAll: this.validateAll.bind(this),
            isValidating: this.isValidating.bind(this),
            getFieldValidating: this.getFieldValidating.bind(this)
        };
    }
}

/**
 * Dependency Manager class for handling filter dependencies
 */
class DependencyManager {
    private dependencies: Map<string, FilterDependency> = new Map();

    constructor(
        private state: DataTableState,
        private debouncedLoad: () => void
    ) {}

    addDependency(filterKey: string, dependency: FilterDependency): void {
        this.dependencies.set(filterKey, dependency);
    }

    async resolveDependencies(changedFilter?: string): Promise<void> {
        const dependenciesToResolve = changedFilter
            ? Array.from(this.dependencies.entries()).filter(([_, dep]) =>
                dep.dependsOn.includes(changedFilter)
            )
            : Array.from(this.dependencies.entries());

        for (const [filterKey, dependency] of dependenciesToResolve) {
            const dependencyValues = dependency.dependsOn.map(dep => 
                this.state.filters[dep]
            );

            // Skip if any dependency is null/undefined
            if (dependencyValues.some(v => v === null || v === undefined)) {
                continue;
            }

            try {
                this.state.dependencies.loading[filterKey] = true;
                const resolvedValue = await dependency.resolve(dependencyValues);
                this.state.dependencies.resolved[filterKey] = resolvedValue;
                
                // Update filter with resolved value
                this.state.filters[filterKey] = resolvedValue;
            } catch (error) {
                console.error(`Failed to resolve dependency for ${filterKey}:`, error);
            } finally {
                this.state.dependencies.loading[filterKey] = false;
            }
        }

        // Trigger data reload if dependencies were resolved
        if (dependenciesToResolve.length > 0) {
            this.debouncedLoad();
        }
    }

    getMethods() {
        return {
            addDependency: this.addDependency.bind(this),
            resolveDependencies: this.resolveDependencies.bind(this)
        };
    }
}

/**
 * State Manager class for efficient state updates
 */
class StateManager {
    private pendingUpdates: Map<string, any> = new Map();
    private updateScheduled: boolean = false;

    constructor(private state: DataTableState) {}

    batchUpdate(updates: Record<string, any>): void {
        // Queue all updates
        for (const [key, value] of Object.entries(updates)) {
            this.pendingUpdates.set(key, value);
        }

        // Schedule update if not already scheduled
        if (!this.updateScheduled) {
            this.updateScheduled = true;
            // Use nextTick to batch updates in the same Vue update cycle
            nextTick(() => {
                this.flushUpdates();
            });
        }
    }

    private flushUpdates(): void {
        // Apply all pending updates at once
        for (const [key, value] of this.pendingUpdates) {
            this.setNestedProperty(this.state, key, value);
        }

        // Clear pending updates
        this.pendingUpdates.clear();
        this.updateScheduled = false;
    }

    private setNestedProperty(obj: any, path: string, value: any): void {
        const keys = path.split('.');
        let current = obj;

        for (let i = 0; i < keys.length - 1; i++) {
            if (!(keys[i] in current)) {
                current[keys[i]] = {};
            }
            current = current[keys[i]];
        }

        current[keys[keys.length - 1]] = value;
    }

    updateFilters(filters: Partial<Record<string, FilterValue>>): void {
        const updates: Record<string, any> = {};
        
        for (const [key, value] of Object.entries(filters)) {
            updates[`filters.${key}`] = value;
        }

        this.batchUpdate(updates);
    }

    updatePagination(pagination: Partial<DataTablePagination>): void {
        const updates: Record<string, any> = {};
        
        for (const [key, value] of Object.entries(pagination)) {
            updates[`pagination.${key}`] = value;
        }

        this.batchUpdate(updates);
    }

    updateSort(sort: Partial<DataTableSort>): void {
        const updates: Record<string, any> = {};
        
        for (const [key, value] of Object.entries(sort)) {
            updates[`sort.${key}`] = value;
        }

        this.batchUpdate(updates);
    }
}

/**
 * Performance Manager class for handling debouncing and optimizations
 */
class PerformanceManager {
    private debouncedFunctions: Map<string, ReturnType<typeof useDebounceFn>> = new Map();
    private requestCache: Map<string, Promise<void>> = new Map();
    private lastRequestTime: number = 0;
    private requestCount: number = 0;

    constructor(
        private config: DataTableConfig,
        private loadFunction: () => Promise<void>
    ) {}

    getDebouncedLoad(field?: string): () => void {
        // If field is specified and should be immediate, return direct load
        if (field && this.config.immediateFields?.includes(field)) {
            return () => this.loadFunction();
        }

        // Determine debounce delay
        let delay = this.config.debounce || 300;
        if (field && this.config.fieldDebounce?.[field]) {
            delay = this.config.fieldDebounce[field];
        }

        // Create or get cached debounced function
        const cacheKey = field ? `field:${field}:${delay}` : `global:${delay}`;
        
        if (!this.debouncedFunctions.has(cacheKey)) {
            const debouncedFn = useDebounceFn(() => {
                this.loadFunction();
            }, delay);
            this.debouncedFunctions.set(cacheKey, debouncedFn);
        }

        return this.debouncedFunctions.get(cacheKey)!;
    }

    async deduplicatedLoad(params: Record<string, any>): Promise<void> {
        const cacheKey = JSON.stringify(params);
        const now = Date.now();

        // If same request is in progress, return it
        if (this.requestCache.has(cacheKey)) {
            return this.requestCache.get(cacheKey)!;
        }

        // Create new request
        const requestPromise = this.loadFunction();
        this.requestCache.set(cacheKey, requestPromise);

        // Clean up old cache entries (keep last 10)
        if (this.requestCache.size > 10) {
            const oldestKey = this.requestCache.keys().next().value;
            this.requestCache.delete(oldestKey);
        }

        try {
            await requestPromise;
        } finally {
            this.requestCache.delete(cacheKey);
            this.lastRequestTime = now;
            this.requestCount++;
        }
    }

    getMetrics() {
        return {
            requestCount: this.requestCount,
            lastRequestTime: this.lastRequestTime,
            cachedRequests: this.requestCache.size,
            debouncedFunctions: this.debouncedFunctions.size
        };
    }

    clearCache(): void {
        this.requestCache.clear();
        this.debouncedFunctions.clear();
    }
}

/**
 * Core Methods class for handling table operations
 */
class CoreMethods<T = any> {
    constructor(
        private state: DataTableState<T>,
        private config: DataTableConfig<T>,
        private performanceManager: PerformanceManager,
        private validator: FilterValidator,
        private dependencyManager: DependencyManager,
        private stateManager: StateManager
    ) {}

    async load(): Promise<void> {
        this.state.loading = true;
        this.state.error = null;

        try {
            // Validate all filters before loading
            const errors = await this.validator.validateAll();
            if (Object.keys(errors).length > 0) {
                this.state.loading = false;
                return;
            }

            // Build query parameters
            const params = this.buildQueryParams();

            // Use deduplicated load through performance manager
            await this.performanceManager.deduplicatedLoad(params);

            // Make navigation request
            await router.get(this.config.baseUrl, params, {
                only: this.config.only,
                preserveScroll: this.config.preserveScroll ?? true,
                onSuccess: () => {
                    this.state.loading = false;
                    this.config.onSuccess?.();
                },
                onError: (errors) => {
                    this.state.error = typeof errors === 'string' ? errors : 'Navigation failed';
                    this.state.loading = false;
                    this.config.onError?.(errors);
                }
            });
        } catch (error) {
            this.state.error = error.message;
            this.state.loading = false;
            this.config.onError?.(error);
        }
    }

    apply(filters?: Partial<Record<string, FilterValue>>): void {
        const updates: Record<string, any> = {};
        
        if (filters) {
            this.stateManager.updateFilters(filters);
        }
        
        this.stateManager.updatePagination({ page: 1 });
        this.performanceManager.getDebouncedLoad()();
    }

    async setFilter(key: string, value: FilterValue, options: { skipValidation?: boolean } = {}): Promise<void> {
        // Validate filter if not skipped
        if (!options.skipValidation) {
            const errors = await this.validator.validateFilter(key, value);
            if (errors.length > 0) {
                return; // Don't set filter if validation fails
            }
        }

        // Update filter and reset page using state manager
        this.stateManager.updateFilters({ [key]: value });
        this.stateManager.updatePagination({ page: 1 });

        // Resolve dependencies
        await this.dependencyManager.resolveDependencies(key);

        // Reload data (debounced)
        this.performanceManager.getDebouncedLoad(key)();
    }

    clearFilter(key: string): void {
        this.setFilter(key, null);
    }

    clearAllFilters(): void {
        this.state.filters = {};
        this.state.pagination.page = 1;
        this.state.validation.errors = {};
        this.state.validation.isValid = true;
        this.load();
    }

    setSort(field: string, direction: SortDirection): void {
        this.stateManager.updateSort({ field, direction });
        this.stateManager.updatePagination({ page: 1 });
        this.load();
    }

    clearSort(): void {
        this.stateManager.updateSort({ field: null, direction: null });
        this.stateManager.updatePagination({ page: 1 });
        this.load();
    }

    setPage(page: number): void {
        this.stateManager.updatePagination({ page });
        this.load();
    }

    setPerPage(perPage: number): void {
        this.stateManager.updatePagination({ perPage, page: 1 });
        this.load();
    }

    refresh(): void {
        this.load();
    }

    private buildQueryParams(): Record<string, any> {
        const params: Record<string, any> = {
            page: this.state.pagination.page,
            per_page: this.state.pagination.perPage,
        };

        // Add sort parameter
        if (this.state.sort.field) {
            params.sort = `${this.state.sort.field}:${this.state.sort.direction}`;
        }

        // Add filters (excluding defaults and null/empty values)
        const defaults = this.config.defaultValues || {};
        for (const [key, value] of Object.entries(this.state.filters)) {
            if (value !== null && value !== '' && value !== defaults[key]) {
                params[key] = value;
            }
        }

        return params;
    }
}

/**
 * Main useDataTable composable
 */
export function useDataTable<T = any>(config: DataTableConfig<T>): DataTableInstance<T> {
    // Create reactive state
    const state = reactive<DataTableState<T>>({
        data: [],
        pagination: {
            page: 1,
            perPage: 15,
            total: 0,
            lastPage: 1,
            ...config.initialPagination
        },
        filters: { ...config.initialFilters },
        sort: { field: null, direction: null },
        loading: false,
        error: null,
        validation: { errors: {}, isValid: true, validating: {} },
        dependencies: { resolved: {}, loading: {} }
    });

    // Initialize helper classes
    const validator = new FilterValidator(state);
    const stateManager = new StateManager(state);
    
    // Create a temporary load function that will be replaced
    let loadFunction: () => Promise<void> = async () => {};
    
    const dependencyManager = new DependencyManager(state, async () => {});
    const performanceManager = new PerformanceManager(config, () => loadFunction());
    const core = new CoreMethods(state, config, performanceManager, validator, dependencyManager, stateManager);
    
    // Now set the real load function
    loadFunction = () => core.load();

    // Computed properties
    const hasActiveFilters = computed(() => {
        const defaults = config.defaultValues || {};
        return Object.entries(state.filters).some(([key, value]) => 
            value !== null && value !== '' && value !== defaults[key]
        );
    });

    const currentPage = computed(() => state.pagination.page);
    const totalPages = computed(() => state.pagination.lastPage);
    const isFirstPage = computed(() => state.pagination.page === 1);
    const isLastPage = computed(() => state.pagination.page >= state.pagination.lastPage);

    const isLoading = computed(() => state.loading);
    const hasError = computed(() => !!state.error);
    const errorMessage = computed(() => state.error);

    const hasValidationErrors = computed(() => !state.validation.isValid);
    const validationErrors = computed(() => state.validation.errors);
    const isValidating = computed(() => validator.isValidating());
    const fieldValidating = computed(() => validator.getFieldValidating());

    const isResolvingDependencies = computed(() => 
        Object.values(state.dependencies.loading).some(loading => loading)
    );

    return {
        // State
        state: readonly(state),
        
        // Core methods
        apply: core.apply.bind(core),
        setFilter: core.setFilter.bind(core),
        clearFilter: (key: string) => core.setFilter(key, null),
        clearAllFilters: core.clearAllFilters.bind(core),
        
        // Navigation methods
        setSort: core.setSort.bind(core),
        clearSort: core.clearSort.bind(core),
        setPage: core.setPage.bind(core),
        setPerPage: core.setPerPage.bind(core),
        refresh: core.refresh.bind(core),
        
        // Validation methods
        addValidationRule: validator.addRule.bind(validator),
        validateFilter: validator.validateFilter.bind(validator),
        validateAll: validator.validateAll.bind(validator),
        
        // Dependency methods
        addDependency: dependencyManager.addDependency.bind(dependencyManager),
        resolveDependencies: dependencyManager.resolveDependencies.bind(dependencyManager),
        
        // Computed properties
        hasActiveFilters,
        currentPage,
        totalPages,
        isFirstPage,
        isLastPage,
        
        // Loading states
        isLoading,
        hasError,
        errorMessage,
        
        // Validation computed
        hasValidationErrors,
        validationErrors,
        isValidating,
        fieldValidating,
        
        // Dependency computed
        isResolvingDependencies,
        
        // Performance metrics
        performanceMetrics: computed(() => performanceManager.getMetrics()),
    };
}