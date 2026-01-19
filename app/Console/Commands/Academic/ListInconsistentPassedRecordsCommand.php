<?php

namespace App\Console\Commands\Academic;

use App\Models\AcademicRecord;
use Illuminate\Console\Command;

class ListInconsistentPassedRecordsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academic:list-inconsistent-passed {--update : Cập nhật is_passed = 0 cho các record không khớp}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liệt kê sinh viên và tên môn học có record is_passed = 1 nhưng final_percentage < min_grade_threshold';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $records = AcademicRecord::query()
            ->join('course_offerings', 'academic_records.course_offering_id', '=', 'course_offerings.id')
            ->join('syllabus_templates', 'course_offerings.syllabus_template_id', '=', 'syllabus_templates.id')
            ->where('academic_records.is_passed', true)
            ->whereColumn('academic_records.final_percentage', '<', 'syllabus_templates.min_grade_threshold')
            ->select('academic_records.*', 'syllabus_templates.min_grade_threshold as threshold')
            ->with(['student', 'unit'])
            ->get();

        if ($records->isEmpty()) {
            $this->info('Không tìm thấy record nào không khớp.');
            return;
        }

        $headers = ['Student ID', 'Student Name', 'Unit Code', 'Unit Name', 'Percentage', 'Threshold', 'Letter Grade'];
        $data = $records->map(function ($record) {
            return [
                $record->student->student_id ?? 'N/A',
                $record->student->full_name ?? 'N/A',
                $record->unit->code ?? 'N/A',
                $record->unit->name ?? 'N/A',
                $record->final_percentage,
                $record->threshold,
                $record->final_letter_grade,
            ];
        });

        $this->table($headers, $data);
        $this->info("Tổng cộng: " . $records->count() . " records.");

        if ($this->option('update')) {
            if ($this->confirm('Bạn có chắc chắn muốn cập nhật is_passed = 0 cho tất cả các record trên?')) {
                $count = 0;
                foreach ($records as $record) {
                    $record->update(['is_passed' => false]);
                    $count++;
                }
                $this->info("Đã cập nhật {$count} records về is_passed = 0.");
            }
        }
    }
}
