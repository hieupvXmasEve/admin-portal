<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Course Finalize
    |--------------------------------------------------------------------------
    */

    'finalize' => [

        // Attendance-threshold gate on finalize (ACAD-RET-001). Temporarily
        // disabled by default; flip ACADEMIC_FINALIZE_ATTENDANCE_GATE=true in
        // .env to restore attendance-based failure without a code change.
        'attendance_gate_enabled' => env('ACADEMIC_FINALIZE_ATTENDANCE_GATE', false),

    ],

];
