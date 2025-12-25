<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'query_ticket_id',
        'response_id',
        'department_id',
        'form_target_id',
        'from_assignee_user_id',
        'to_assignee_user_id',
        'assigned_by_user_id',
        'action',
        'note',
    ];

    public function queryTicket(): BelongsTo
    {
        return $this->belongsTo(QueryTicket::class, 'query_ticket_id');
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'response_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function formTarget(): BelongsTo
    {
        return $this->belongsTo(FormTarget::class);
    }

    public function fromAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_assignee_user_id');
    }

    public function toAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_assignee_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
