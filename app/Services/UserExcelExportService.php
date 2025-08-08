<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserExcelExportService implements WithMultipleSheets
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function exportUsersToExcel(array $filters = []): string
    {
        $this->filters = $filters;

        // Create temporary file
        $fileName = 'users_export_'.now()->format('Y-m-d_H-i-s').'.xlsx';

        // Use Laravel Excel to export to the local disk
        Excel::store($this, 'temp/'.$fileName, 'local');

        // Return the actual file path where it was stored
        $filePath = Storage::disk('local')->path('temp/'.$fileName);

        return $filePath;
    }

    public function sheets(): array
    {
        $users = $this->getUsersWithCampusesAndRoles($this->filters);
        $campusUserRoles = $this->getCampusUserRolesData($this->filters);

        return [
            new UsersSummarySheet($users, $campusUserRoles),
            new DetailedRolesSheet($campusUserRoles),
            new CampusOverviewSheet,
            new RoleDistributionSheet,
        ];
    }

    public function getUsersWithCampusesAndRoles(array $filters = []): Collection
    {
        $query = User::query();
        $this->applyFilters($query, $filters);

        return $query->orderBy('name')->get();
    }

    public function getCampusUserRolesData(array $filters = []): Collection
    {
        $query = CampusUserRole::with(['user', 'campus', 'role']);

        // Apply filters to the user relationship
        if (! empty($filters['search'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('email', 'like', '%'.$filters['search'].'%');
            });
        }

        if (! empty($filters['name'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('name', 'like', '%'.$filters['name'].'%');
            });
        }

        if (! empty($filters['email'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('email', 'like', '%'.$filters['email'].'%');
            });
        }

        return $query->orderBy('user_id')->get();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['campus_id'])) {
            $query->whereHas('campuses', function ($q) use ($filters) {
                $q->whereIn('campus_id', (array) $filters['campus_id']);
            });
        }

        if (! empty($filters['role_id'])) {
            $query->whereHas('campusRoles', function ($q) use ($filters) {
                $q->whereIn('role_id', (array) $filters['role_id']);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('email', 'like', '%'.$filters['search'].'%');
            });
        }

        // Handle specific column filters
        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (! empty($filters['email'])) {
            $query->where('email', 'like', '%'.$filters['email'].'%');
        }
    }
}

class UsersSummarySheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private Collection $users;

    private Collection $campusUserRoles;

    public function __construct(Collection $users, Collection $campusUserRoles)
    {
        $this->users = $users;
        $this->campusUserRoles = $campusUserRoles;
    }

    public function collection()
    {
        return $this->users->map(function ($user) {
            $userRoles = $this->campusUserRoles->where('user_id', $user->id);
            $campusCount = $userRoles->pluck('campus_id')->unique()->count();
            $roleCount = $userRoles->pluck('role_id')->unique()->count();

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->email_verified_at ? 'Yes' : 'No',
                'campus_count' => $campusCount,
                'total_roles' => $roleCount,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'User ID',
            'Name',
            'Email',
            'Email Verified',
            'Campus Count',
            'Total Roles',
            'Created At',
            'Updated At',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 25,
            'C' => 30,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 20,
            'H' => 20,
        ];
    }

    public function title(): string
    {
        return 'Users Summary';
    }
}

class DetailedRolesSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private Collection $campusUserRoles;

    public function __construct(Collection $campusUserRoles)
    {
        $this->campusUserRoles = $campusUserRoles;
    }

    public function collection()
    {
        return $this->campusUserRoles->map(function ($campusUserRole) {
            return [
                'user_id' => $campusUserRole->user->id,
                'user_name' => $campusUserRole->user->name,
                'user_email' => $campusUserRole->user->email,
                'campus_id' => $campusUserRole->campus->id,
                'campus_name' => $campusUserRole->campus->name,
                'campus_code' => $campusUserRole->campus->code,
                'role_id' => $campusUserRole->role->id,
                'role_name' => $campusUserRole->role->name,
                'assigned_at' => $campusUserRole->created_at->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'User ID',
            'User Name',
            'User Email',
            'Campus ID',
            'Campus Name',
            'Campus Code',
            'Role ID',
            'Role Name',
            'Assigned At',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 25,
            'C' => 30,
            'D' => 12,
            'E' => 25,
            'F' => 15,
            'G' => 10,
            'H' => 20,
            'I' => 20,
        ];
    }

    public function title(): string
    {
        return 'User Campus Roles';
    }
}

class CampusOverviewSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function collection()
    {
        $campuses = Campus::withCount('users')->get();

        return $campuses->map(function ($campus) {
            // Get active roles for this campus using a cleaner query
            $activeRoles = DB::table('campus_user_roles')
                ->join('roles', 'campus_user_roles.role_id', '=', 'roles.id')
                ->where('campus_user_roles.campus_id', $campus->id)
                ->distinct()
                ->pluck('roles.name')
                ->implode(', ');

            return [
                'campus_id' => $campus->id,
                'campus_name' => $campus->name,
                'campus_code' => $campus->code,
                'address' => $campus->address,
                'total_users' => $campus->users_count,
                'active_roles' => $activeRoles ?: 'No roles assigned',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Campus ID',
            'Campus Name',
            'Campus Code',
            'Address',
            'Total Users',
            'Active Roles',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 25,
            'C' => 15,
            'D' => 40,
            'E' => 15,
            'F' => 30,
        ];
    }

    public function title(): string
    {
        return 'Campus Overview';
    }
}

class RoleDistributionSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function collection()
    {
        $roles = Role::all();

        return $roles->map(function ($role) {
            // Get total users for this role
            $totalUsers = DB::table('campus_user_roles')
                ->where('role_id', $role->id)
                ->distinct('user_id')
                ->count();

            // Get campuses used for this role
            $campusesUsed = DB::table('campus_user_roles')
                ->where('role_id', $role->id)
                ->distinct('campus_id')
                ->count();

            // Get most common campus for this role
            $mostCommonCampus = DB::table('campus_user_roles')
                ->join('campuses', 'campus_user_roles.campus_id', '=', 'campuses.id')
                ->where('campus_user_roles.role_id', $role->id)
                ->groupBy('campuses.id', 'campuses.name')
                ->orderByRaw('COUNT(*) DESC')
                ->pluck('campuses.name')
                ->first() ?? 'N/A';

            return [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'total_users' => $totalUsers,
                'campuses_used' => $campusesUsed,
                'most_common_campus' => $mostCommonCampus,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Role ID',
            'Role Name',
            'Total Users',
            'Campuses Used',
            'Most Common Campus',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 25,
            'C' => 15,
            'D' => 15,
            'E' => 25,
        ];
    }

    public function title(): string
    {
        return 'Role Distribution';
    }
}
