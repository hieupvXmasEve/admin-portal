## Why

The current codebase has two competing table filter composables - `useInertiaFilters` (53 files, auto-sync magic) and `useServerTableQuery` (31 files, manual control). This creates inconsistency, maintenance overhead, and architectural confusion. We need a single, well-designed composable that provides explicit control, extensibility, and long-term maintainability.

## What Changes

- **NEW**: Create `useDataTable` composable with explicit control and built-in validation
- **NEW**: Add comprehensive TypeScript support and smart debouncing optimizations
- **NEW**: Implement dependent filter support for common scenarios (campus → programs)
- **REPLACE**: Gradually migrate all `useInertiaFilters` and `useServerTableQuery` usage
- **UPDATE**: Documentation and development guidelines to use new pattern
- **REMOVE**: Deprecate and eventually remove old composables

## Capabilities

### New Capabilities
- `data-table-core`: Core state management and navigation for server-side tables
- `data-table-validation`: Built-in filter validation and business rule enforcement
- `data-table-dependencies`: Dependent filter relationships (campus → programs)
- `data-table-performance`: Smart debouncing and basic performance optimizations

### Modified Capabilities
- `frontend-filtering`: Update frontend filtering guidelines to use new composable

## Impact

- **Frontend**: All table pages will use the new composable (84 files affected)
- **Documentation**: Update filtering guidelines and component examples
- **Developer Experience**: Unified, predictable API for all table operations
- **Performance**: Improved debouncing and efficient state management
- **Maintainability**: Single source of truth for table functionality