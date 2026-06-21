<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use Illuminate\Support\Str;

class BusinessGlossary
{
    /**
     * @return array<string, list<string>>
     */
    public function metricPhrases(): array
    {
        return [
            'finance_collection_summary' => [
                'nợ học phí',
                'outstanding tuition',
                'outstanding',
                'collection',
                'collection progress',
                'học phí đã thu',
            ],
            'academic_defer_count' => [
                'defer',
                'bảo lưu',
                'tạm hoãn học',
                'ngưng học tạm thời',
            ],
            'academic_student_status_count' => [
                'student status',
                'trạng thái sinh viên',
                'active deferred pending dropout',
            ],
            'finance_fee_monitor_summary' => [
                'fee monitor',
                'thiếu phí',
                'generated fee',
                'blocked fee',
            ],
            'finance_dng_lifecycle_attention' => [
                'dng lifecycle',
                'payment lifecycle',
                'attention bucket',
                'dng cần xử lý',
            ],
        ];
    }

    public function metricForPhrase(string $phrase): ?string
    {
        $normalized = $this->normalize($phrase);

        foreach ($this->metricPhrases() as $metric => $phrases) {
            foreach ($phrases as $candidate) {
                if (str_contains($normalized, $this->normalize($candidate))) {
                    return $metric;
                }
            }
        }

        return null;
    }

    /**
     * @return array{filter: string, value: string}|null
     */
    public function filterValueForPhrase(string $phrase): ?array
    {
        $normalized = $this->normalize($phrase);

        foreach (['kỳ hiện tại', 'term hiện tại', 'semester hiện tại', 'current term', 'current semester'] as $candidate) {
            if (str_contains($normalized, $this->normalize($candidate))) {
                return [
                    'filter' => 'semester',
                    'value' => 'current',
                ];
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->toString();
    }
}
