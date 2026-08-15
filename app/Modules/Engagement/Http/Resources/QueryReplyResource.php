<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueryReplyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The `uploadRecord` "relation" is never a real Eloquent relation on
     * QueryReply — it is a UploadRecordSummary|null the caller injects via
     * setRelation() from App\Shared\Contracts\Upload\UploadRecordReader
     * before building this resource. A caller that forgets to inject it
     * looks identical to a reply with no attachment (both read as null),
     * so this class cannot itself distinguish "genuinely no attachment"
     * from "caller forgot" — that gap is covered by presence tests at each
     * call site (see tests/Feature/Api/V1/Student/QueryTicketApiTest.php),
     * not by anything in this class.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => $this->resolveAuthor(),
            'message' => $this->message,
            'is_official_answer' => $this->is_official_answer,
            'attachment' => $this->when(
                $this->uploadRecord !== null,
                fn () => $this->uploadRecord->toArray()
            ),
            'created_at' => $this->created_at,
        ];
    }

    protected function resolveAuthor(): array
    {
        if ($this->author_student_id) {
            return [
                'type' => 'student',
                'id' => $this->author_student_id,
                'name' => $this->whenLoaded('authorStudent', fn () => $this->authorStudent->full_name),
                'student_id' => $this->whenLoaded('authorStudent', fn () => $this->authorStudent->student_id),
            ];
        }

        return [
            'type' => 'staff',
            'id' => $this->author_user_id,
            'name' => $this->whenLoaded('author', fn () => $this->author->name),
        ];
    }
}
