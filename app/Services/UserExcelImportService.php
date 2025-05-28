<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Campus;
use App\Models\Role;
use App\Models\CampusUserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserExcelImportService
{
    private array $importResults = [];
    private array $errors = [];
    private array $warnings = [];
    private int $processedRows = 0;
    private int $successfulRows = 0;
    private int $failedRows = 0;
    private int $skippedRows = 0;

    public function importUsersFromExcel(string $filePath, array $options = []): array
    {
        $this->resetCounters();

        try {
            // Validate file
            $this->validateImportFile($filePath);

            // Load spreadsheet
            $spreadsheet = IOFactory::load($filePath);
            $format = $this->detectFormat($spreadsheet);

            // Process based on format
            switch ($format) {
                case 'simple':
                    $this->processSimpleFormat($spreadsheet, $options);
                    break;
                case 'detailed':
                    $this->processDetailedFormat($spreadsheet, $options);
                    break;
                case 'relationship':
                    $this->processRelationshipFormat($spreadsheet, $options);
                    break;
                default:
                    throw new \Exception('Unable to detect import format');
            }

            return $this->generateImportReport();
        } catch (\Exception $e) {
            Log::error('Import failed: ' . $e->getMessage(), [
                'file' => $filePath,
                'options' => $options
            ]);

            throw $e;
        }
    }

    public function validateImportFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Import file not found');
        }

        $fileSize = filesize($filePath);
        $maxSize = config('import.max_file_size', '10MB');
        $maxSizeBytes = $this->convertToBytes($maxSize);

        if ($fileSize > $maxSizeBytes) {
            throw new \Exception("File size exceeds maximum allowed size of {$maxSize}");
        }

        $allowedExtensions = config('import.allowed_extensions', ['xlsx', 'xls']);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception('Invalid file format. Allowed formats: ' . implode(', ', $allowedExtensions));
        }

        return true;
    }

    public function previewImportData(string $filePath, int $previewRows = 10): array
    {
        $this->validateImportFile($filePath);

        $spreadsheet = IOFactory::load($filePath);
        $format = $this->detectFormat($spreadsheet);

        $preview = [
            'format' => $format,
            'sheets' => [],
            'estimated_users' => 0
        ];

        foreach ($spreadsheet->getAllSheets() as $index => $worksheet) {
            $sheetData = $this->getSheetPreview($worksheet, $previewRows);
            $preview['sheets'][] = [
                'name' => $worksheet->getTitle(),
                'headers' => $sheetData['headers'],
                'data' => $sheetData['data'],
                'total_rows' => $sheetData['total_rows']
            ];
        }

        // Estimate number of users based on format
        $preview['estimated_users'] = $this->estimateUserCount($preview, $format);

        return $preview;
    }

    private function processSimpleFormat($spreadsheet, array $options): void
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $this->worksheetToArray($worksheet);

        if (empty($data)) {
            throw new \Exception('No data found in the worksheet');
        }

        $headers = array_shift($data); // Remove header row
        $this->validateSimpleFormatHeaders($headers);

        DB::beginTransaction();

        try {
            foreach ($data as $rowIndex => $row) {
                $this->processedRows++;
                $actualRowNumber = $rowIndex + 2; // +2 because we removed headers and Excel is 1-indexed

                try {
                    $userData = $this->mapSimpleFormatRow($headers, $row);
                    $this->processUserRow($userData, $actualRowNumber, $options);
                    $this->successfulRows++;
                } catch (\Exception $e) {
                    $this->failedRows++;
                    $this->errors[] = [
                        'row' => $actualRowNumber,
                        'error' => $e->getMessage(),
                        'data' => $row
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function processDetailedFormat($spreadsheet, array $options): void
    {
        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $sheets[$worksheet->getTitle()] = $this->worksheetToArray($worksheet);
        }

        if (!isset($sheets['Users']) || !isset($sheets['User Campus Roles'])) {
            throw new \Exception('Required sheets not found. Expected: Users, User Campus Roles');
        }

        DB::beginTransaction();

        try {
            // First, process users
            $this->processUsersSheet($sheets['Users'], $options);

            // Then, process relationships
            $this->processRelationshipsSheet($sheets['User Campus Roles'], $options);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function processRelationshipFormat($spreadsheet, array $options): void
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $this->worksheetToArray($worksheet);

        if (empty($data)) {
            throw new \Exception('No data found in the worksheet');
        }

        $headers = array_shift($data);
        $this->validateRelationshipFormatHeaders($headers);

        DB::beginTransaction();

        try {
            $processedUsers = [];

            foreach ($data as $rowIndex => $row) {
                $this->processedRows++;
                $actualRowNumber = $rowIndex + 2;

                try {
                    $relationshipData = $this->mapRelationshipFormatRow($headers, $row);

                    // Create user if not already processed
                    $userKey = strtolower($relationshipData['email']);
                    if (!isset($processedUsers[$userKey])) {
                        $user = $this->createOrUpdateUser($relationshipData, $actualRowNumber, $options);
                        $processedUsers[$userKey] = $user;
                    } else {
                        $user = $processedUsers[$userKey];
                    }

                    // Create relationship
                    $this->assignUserToCampusWithRole(
                        $user,
                        $relationshipData['campus_code'],
                        $relationshipData['role_code'],
                        $actualRowNumber
                    );

                    $this->successfulRows++;
                } catch (\Exception $e) {
                    $this->failedRows++;
                    $this->errors[] = [
                        'row' => $actualRowNumber,
                        'error' => $e->getMessage(),
                        'data' => $row
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function createOrUpdateUser(array $userData, int $rowNumber, array $options): User
    {
        // Validate user data
        $validator = Validator::make($userData, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            throw new \Exception('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        // Check if user exists
        $existingUser = User::where('email', $userData['email'])->first();

        if ($existingUser) {
            $duplicateHandling = $options['duplicate_handling'] ?? config('import.duplicate_handling', 'update');

            switch ($duplicateHandling) {
                case 'skip':
                    $this->warnings[] = [
                        'row' => $rowNumber,
                        'message' => 'User already exists, skipped'
                    ];
                    return $existingUser;

                case 'error':
                    throw new \Exception('User with email already exists');

                case 'update':
                default:
                    $existingUser->update([
                        'name' => $userData['name'],
                        'phone' => $userData['phone'] ?? null,
                        'address' => $userData['address'] ?? null,
                    ]);

                    $this->warnings[] = [
                        'row' => $rowNumber,
                        'message' => 'User already exists, updated information'
                    ];

                    return $existingUser;
            }
        }

        // Create new user
        $password = $userData['password'] ?? config('import.default_password', 'TempPassword123!');

        return User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($password),
            'phone' => $userData['phone'] ?? null,
            'address' => $userData['address'] ?? null,
            'email_verified_at' => now(), // Auto-verify imported users
        ]);
    }

    private function assignUserToCampusWithRole(User $user, string $campusCode, string $roleCode, int $rowNumber): void
    {
        // Find campus
        $campus = Campus::where('code', $campusCode)->first();
        if (!$campus) {
            throw new \Exception("Campus with code '{$campusCode}' not found");
        }

        // Find role
        $role = Role::where('code', $roleCode)->first();
        if (!$role) {
            throw new \Exception("Role with code '{$roleCode}' not found");
        }

        // Check if relationship already exists
        $existingRelation = CampusUserRole::where([
            'user_id' => $user->id,
            'campus_id' => $campus->id,
            'role_id' => $role->id
        ])->first();

        if ($existingRelation) {
            $this->warnings[] = [
                'row' => $rowNumber,
                'message' => "User already assigned to {$campus->name} with role {$role->name}"
            ];
            return;
        }

        // Create relationship
        CampusUserRole::create([
            'user_id' => $user->id,
            'campus_id' => $campus->id,
            'role_id' => $role->id
        ]);
    }

    private function detectFormat($spreadsheet): string
    {
        $sheetNames = [];
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $sheetNames[] = $worksheet->getTitle();
        }

        // Check for detailed format (multiple specific sheets)
        if (in_array('Users', $sheetNames) && in_array('User Campus Roles', $sheetNames)) {
            return 'detailed';
        }

        // Check first sheet headers to determine format
        $firstSheet = $spreadsheet->getActiveSheet();
        $headers = $this->getSheetHeaders($firstSheet);

        // Simple format: has concatenated campus/role columns
        if (in_array('Campus Codes', $headers) || in_array('Role Codes', $headers)) {
            return 'simple';
        }

        // Relationship format: has individual relationship columns
        if (in_array('Campus Code', $headers) && in_array('Role Code', $headers)) {
            return 'relationship';
        }

        return 'unknown';
    }

    private function worksheetToArray(Worksheet $worksheet): array
    {
        return $worksheet->toArray(null, true, true, true);
    }

    private function getSheetHeaders(Worksheet $worksheet): array
    {
        $highestColumn = $worksheet->getHighestColumn();
        $headers = [];

        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $headers[] = $worksheet->getCell($col . '1')->getValue();
        }

        return array_filter($headers); // Remove empty headers
    }

    private function getSheetPreview(Worksheet $worksheet, int $previewRows): array
    {
        $data = $this->worksheetToArray($worksheet);
        $headers = !empty($data) ? array_shift($data) : [];

        return [
            'headers' => $headers,
            'data' => array_slice($data, 0, $previewRows),
            'total_rows' => count($data)
        ];
    }

    private function estimateUserCount(array $preview, string $format): int
    {
        switch ($format) {
            case 'simple':
                return $preview['sheets'][0]['total_rows'] ?? 0;

            case 'detailed':
                foreach ($preview['sheets'] as $sheet) {
                    if ($sheet['name'] === 'Users') {
                        return $sheet['total_rows'];
                    }
                }
                return 0;

            case 'relationship':
                // Count unique emails
                $emails = [];
                foreach ($preview['sheets'][0]['data'] as $row) {
                    if (!empty($row[1])) { // Assuming email is in second column
                        $emails[strtolower($row[1])] = true;
                    }
                }
                return count($emails);

            default:
                return 0;
        }
    }

    private function validateSimpleFormatHeaders(array $headers): void
    {
        $required = ['Name', 'Email'];

        // Clean headers by removing asterisks and trimming whitespace
        $cleanHeaders = array_map(function ($header) {
            return trim(str_replace('*', '', $header));
        }, $headers);

        $missing = array_diff($required, $cleanHeaders);

        if (!empty($missing)) {
            throw new \Exception('Missing required headers: ' . implode(', ', $missing));
        }
    }

    private function validateRelationshipFormatHeaders(array $headers): void
    {
        $required = ['User Name', 'User Email', 'Campus Code', 'Role Code'];

        // Clean headers by removing asterisks and trimming whitespace
        $cleanHeaders = array_map(function ($header) {
            return trim(str_replace('*', '', $header));
        }, $headers);

        $missing = array_diff($required, $cleanHeaders);

        if (!empty($missing)) {
            throw new \Exception('Missing required headers: ' . implode(', ', $missing));
        }
    }

    private function mapSimpleFormatRow(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            // Clean header by removing asterisks and other formatting characters
            $cleanHeader = trim(str_replace(['*', '(required)', '(optional)'], '', $header));
            $key = strtolower(str_replace(' ', '_', $cleanHeader));
            $mapped[$key] = $row[$index] ?? null;
        }
        return $mapped;
    }

    private function mapRelationshipFormatRow(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            // Clean header by removing asterisks and other formatting characters
            $cleanHeader = trim(str_replace(['*', '(required)', '(optional)'], '', $header));
            $key = strtolower(str_replace(' ', '_', $cleanHeader));
            $mapped[$key] = $row[$index] ?? null;
        }

        return [
            'name' => $mapped['user_name'],
            'email' => $mapped['user_email'],
            'password' => $mapped['password'] ?? null,
            'phone' => $mapped['phone'] ?? null,
            'address' => $mapped['address'] ?? null,
            'campus_code' => $mapped['campus_code'],
            'role_code' => $mapped['role_code']
        ];
    }

    private function processUserRow(array $userData, int $rowNumber, array $options): void
    {
        // Create or update user
        $user = $this->createOrUpdateUser($userData, $rowNumber, $options);

        // Process campus codes and roles if present
        if (!empty($userData['campus_codes']) && !empty($userData['role_codes'])) {
            $campusCodes = array_map('trim', explode(',', $userData['campus_codes']));
            $roleCodes = array_map('trim', explode(',', $userData['role_codes']));

            // Assign each campus-role combination
            foreach ($campusCodes as $campusIndex => $campusCode) {
                foreach ($roleCodes as $roleIndex => $roleCode) {
                    $this->assignUserToCampusWithRole($user, $campusCode, $roleCode, $rowNumber);
                }
            }
        }
    }

    private function processUsersSheet(array $data, array $options): void
    {
        if (empty($data)) return;

        $headers = array_shift($data);

        foreach ($data as $rowIndex => $row) {
            $this->processedRows++;
            $actualRowNumber = $rowIndex + 2;

            try {
                $userData = $this->mapSimpleFormatRow($headers, $row);
                $this->createOrUpdateUser($userData, $actualRowNumber, $options);
                $this->successfulRows++;
            } catch (\Exception $e) {
                $this->failedRows++;
                $this->errors[] = [
                    'row' => $actualRowNumber,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
            }
        }
    }

    private function processRelationshipsSheet(array $data, array $options): void
    {
        if (empty($data)) return;

        $headers = array_shift($data);

        foreach ($data as $rowIndex => $row) {
            $actualRowNumber = $rowIndex + 2;

            try {
                $relationshipData = $this->mapSimpleFormatRow($headers, $row);

                $user = User::where('email', $relationshipData['user_email'])->first();
                if (!$user) {
                    throw new \Exception('User not found with email: ' . $relationshipData['user_email']);
                }

                $this->assignUserToCampusWithRole(
                    $user,
                    $relationshipData['campus_code'],
                    $relationshipData['role_code'],
                    $actualRowNumber
                );
            } catch (\Exception $e) {
                $this->errors[] = [
                    'row' => $actualRowNumber,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
            }
        }
    }

    private function generateImportReport(): array
    {
        return [
            'summary' => [
                'total_rows' => $this->processedRows,
                'successful' => $this->successfulRows,
                'failed' => $this->failedRows,
                'skipped' => $this->skippedRows,
                'processing_time' => '0 seconds' // Will be calculated by controller
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }

    private function resetCounters(): void
    {
        $this->importResults = [];
        $this->errors = [];
        $this->warnings = [];
        $this->processedRows = 0;
        $this->successfulRows = 0;
        $this->failedRows = 0;
        $this->skippedRows = 0;
    }

    private function convertToBytes(string $size): int
    {
        $unit = strtoupper(substr($size, -2));
        $value = (int)substr($size, 0, -2);

        switch ($unit) {
            case 'KB':
                return $value * 1024;
            case 'MB':
                return $value * 1024 * 1024;
            case 'GB':
                return $value * 1024 * 1024 * 1024;
            default:
                return (int)$size;
        }
    }
}
