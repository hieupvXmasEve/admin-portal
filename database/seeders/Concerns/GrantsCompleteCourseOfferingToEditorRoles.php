<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use App\Models\Permission;
use App\Models\RolePermission;

/**
 * ADR 0013: routine course finalization stays broad — any role that can
 * edit course offerings also gets complete_course_offering by default.
 * Shared by the fresh-install and live-DB permission seeders; idempotent,
 * so re-running never duplicates grants.
 */
trait GrantsCompleteCourseOfferingToEditorRoles
{
    private function grantCompleteCourseOfferingToEditorRoles(): void
    {
        $edit = Permission::where('code', 'edit_course_offering')->first();
        $complete = Permission::where('code', 'complete_course_offering')->first();

        if (! $edit || ! $complete) {
            return;
        }

        $editorRoleIds = RolePermission::where('permission_id', $edit->id)->pluck('role_id');

        foreach ($editorRoleIds as $roleId) {
            RolePermission::firstOrCreate([
                'role_id' => $roleId,
                'permission_id' => $complete->id,
            ]);
        }

        $this->command->info('🔗 Granted complete_course_offering to '.$editorRoleIds->count().' role(s) holding edit_course_offering');
    }
}
