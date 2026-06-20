<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

require_once __DIR__.'/Feature/Finance/Batch/helpers.php';
require_once __DIR__.'/Feature/Academic/ExamResit/helpers.php';
require_once __DIR__.'/Feature/Finance/Operations/exam_resit_due_helpers.php';
