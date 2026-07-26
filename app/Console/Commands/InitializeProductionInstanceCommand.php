<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InitializeProductionInstanceCommand extends Command
{
    protected $signature = 'instance:initialize
        {--institution= : The institution name}
        {--campus-code= : The initial campus code}
        {--campus-address= : The initial campus address}
        {--admin-name= : The super-admin display name}
        {--admin-email= : The super-admin email address}
        {--admin-password= : The super-admin password; prefer the hidden prompt}
        {--force : Confirm initialization of an otherwise empty instance}';

    protected $description = 'Initialize an empty production instance with roles, one campus, and one super-admin';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Pass --force to confirm initialization of this instance.');

            return self::FAILURE;
        }

        if ($this->hasExistingInstanceData()) {
            $this->error('Initialization stopped: roles, permissions, campuses, or users already exist.');

            return self::FAILURE;
        }

        $data = $this->validatedInput();

        if ($data === null) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($data): void {
            $roleAndPermissionSeeder = app(RoleAndPermissionSeeder::class);
            $roleAndPermissionSeeder->setCommand($this);
            $roleAndPermissionSeeder->run();

            $campus = Campus::query()->create([
                'name' => $data['institution'],
                'code' => $data['campus_code'],
                'address' => $data['campus_address'],
            ]);

            $administrator = User::query()->create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'email_verified_at' => now(),
            ]);

            $superAdminRole = Role::query()->where('code', 'super_admin')->sole();

            CampusUserRole::query()->create([
                'user_id' => $administrator->id,
                'campus_id' => $campus->id,
                'role_id' => $superAdminRole->id,
            ]);
        });

        $this->info('Instance initialized with one campus and one super-admin.');

        return self::SUCCESS;
    }

    /** @return array{institution: string, campus_code: string, campus_address: string, admin_name: string, admin_email: string, admin_password: string}|null */
    private function validatedInput(): ?array
    {
        $password = $this->option('admin-password');

        if (! is_string($password) || $password === '') {
            $password = $this->secret('Super-admin password');
        }

        if (! is_string($password) || $password === '') {
            $this->error('A super-admin password is required.');

            return null;
        }

        $input = [
            'institution' => $this->option('institution') ?: $this->ask('Institution name'),
            'campus_code' => $this->option('campus-code') ?: $this->ask('Initial campus code'),
            'campus_address' => $this->option('campus-address') ?: $this->ask('Initial campus address'),
            'admin_name' => $this->option('admin-name') ?: $this->ask('Super-admin name'),
            'admin_email' => $this->option('admin-email') ?: $this->ask('Super-admin email'),
            'admin_password' => $password,
        ];

        $validator = Validator::make($input, [
            'institution' => ['required', 'string', 'max:255'],
            'campus_code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'campus_address' => ['required', 'string', 'max:1000'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return null;
        }

        /** @var array{institution: string, campus_code: string, campus_address: string, admin_name: string, admin_email: string, admin_password: string} $validated */
        $validated = $validator->validated();

        return $validated;
    }

    private function hasExistingInstanceData(): bool
    {
        return Campus::query()->exists()
            || CampusUserRole::query()->exists()
            || Permission::query()->exists()
            || Role::query()->exists()
            || User::query()->exists();
    }
}
