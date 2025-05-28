<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Campus;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserExcelExportService
{
    public function exportUsersToExcel(array $filters = []): string
    {
        $users = $this->getUsersWithCampusesAndRoles($filters);

        $spreadsheet = new Spreadsheet();

        // Remove default worksheet
        $spreadsheet->removeSheetByIndex(0);

        // Create worksheets
        $this->createUsersSummarySheet($spreadsheet, $users);
        $this->createDetailedRolesSheet($spreadsheet, $users);
        $this->createCampusOverviewSheet($spreadsheet);
        $this->createRoleDistributionSheet($spreadsheet);

        // Set active sheet to first one
        $spreadsheet->setActiveSheetIndex(0);

        // Save to temporary file
        $fileName = 'users_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $filePath = storage_path('app/temp/' . $fileName);

        // Ensure temp directory exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    public function getUsersWithCampusesAndRoles(array $filters = []): Collection
    {
        $query = User::with([
            'campusRoles.campus:id,name,code,address',
            'campusRoles.role:id,name'
        ]);

        $this->applyFilters($query, $filters);

        return $query->orderBy('name')->get();
    }

    private function createUsersSummarySheet(Spreadsheet $spreadsheet, Collection $users): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Users Summary');

        // Headers
        $headers = [
            'User ID',
            'Name',
            'Email',
            'Email Verified',
            'Campus Count',
            'Total Roles',
            'Created At',
            'Updated At'
        ];

        $worksheet->fromArray($headers, null, 'A1');

        // Apply header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        $worksheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // Data rows
        $row = 2;
        foreach ($users as $user) {
            $campusCount = $user->campusRoles->pluck('campus_id')->unique()->count();
            $roleCount = $user->campusRoles->pluck('role_id')->unique()->count();

            $worksheet->fromArray([
                $user->id,
                $user->name,
                $user->email,
                $user->email_verified_at ? 'Yes' : 'No',
                $campusCount,
                $roleCount,
                $user->created_at->format('Y-m-d H:i:s'),
                $user->updated_at->format('Y-m-d H:i:s')
            ], null, "A{$row}");

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'H') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row
        $worksheet->freezePane('A2');

        // Add auto-filter
        $worksheet->setAutoFilter('A1:H' . ($row - 1));
    }

    private function createDetailedRolesSheet(Spreadsheet $spreadsheet, Collection $users): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('User Campus Roles');

        // Headers
        $headers = [
            'User ID',
            'User Name',
            'User Email',
            'Campus ID',
            'Campus Name',
            'Campus Code',
            'Role ID',
            'Role Name',
            'Assigned At'
        ];

        $worksheet->fromArray($headers, null, 'A1');

        // Apply header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        $worksheet->getStyle('A1:I1')->applyFromArray($headerStyle);

        // Data rows
        $row = 2;
        foreach ($users as $user) {
            foreach ($user->campusRoles as $campusRole) {
                $worksheet->fromArray([
                    $user->id,
                    $user->name,
                    $user->email,
                    $campusRole->campus->id,
                    $campusRole->campus->name,
                    $campusRole->campus->code,
                    $campusRole->role->id,
                    $campusRole->role->name,
                    $campusRole->created_at->format('Y-m-d H:i:s')
                ], null, "A{$row}");

                $row++;
            }
        }

        // Auto-size columns
        foreach (range('A', 'I') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row
        $worksheet->freezePane('A2');

        // Add auto-filter
        $worksheet->setAutoFilter('A1:I' . ($row - 1));
    }

    private function createCampusOverviewSheet(Spreadsheet $spreadsheet): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Campus Overview');

        // Headers
        $headers = [
            'Campus ID',
            'Campus Name',
            'Campus Code',
            'Address',
            'Total Users',
            'Active Roles'
        ];

        $worksheet->fromArray($headers, null, 'A1');

        // Apply header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        $worksheet->getStyle('A1:F1')->applyFromArray($headerStyle);

        // Get campus data with statistics
        $campuses = Campus::withCount('users')->get();

        $row = 2;
        foreach ($campuses as $campus) {
            // Get active roles for this campus using a cleaner query
            $activeRoles = DB::table('campus_user_roles')
                ->join('roles', 'campus_user_roles.role_id', '=', 'roles.id')
                ->where('campus_user_roles.campus_id', $campus->id)
                ->distinct()
                ->pluck('roles.name')
                ->implode(', ');

            $worksheet->fromArray([
                $campus->id,
                $campus->name,
                $campus->code,
                $campus->address,
                $campus->users_count,
                $activeRoles ?: 'No roles assigned'
            ], null, "A{$row}");

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'F') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row
        $worksheet->freezePane('A2');

        // Add auto-filter
        $worksheet->setAutoFilter('A1:F' . ($row - 1));
    }

    private function createRoleDistributionSheet(Spreadsheet $spreadsheet): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Role Distribution');

        // Headers
        $headers = [
            'Role ID',
            'Role Name',
            'Total Users',
            'Campuses Used',
            'Most Common Campus'
        ];

        $worksheet->fromArray($headers, null, 'A1');

        // Apply header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        $worksheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        // Get role statistics
        $roles = Role::all();

        $row = 2;
        foreach ($roles as $role) {
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

            $worksheet->fromArray([
                $role->id,
                $role->name,
                $totalUsers,
                $campusesUsed,
                $mostCommonCampus
            ], null, "A{$row}");

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'E') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row
        $worksheet->freezePane('A2');

        // Add auto-filter
        $worksheet->setAutoFilter('A1:E' . ($row - 1));
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['campus_id'])) {
            $query->whereHas('campuses', function ($q) use ($filters) {
                $q->whereIn('campus_id', (array) $filters['campus_id']);
            });
        }

        if (!empty($filters['role_id'])) {
            $query->whereHas('campusRoles', function ($q) use ($filters) {
                $q->whereIn('role_id', (array) $filters['role_id']);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }
    }
}
