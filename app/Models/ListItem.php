<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $list_id
 * @property string $title
 * @property bool $done
 * @property CarbonImmutable|null $done_at
 * @property int|null $assigned_member_id
 * @property CarbonImmutable|null $due_on
 * @property-read TodoList $list
 * @property-read Member|null $assignee
 */
class ListItem extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['done' => false];

    protected $fillable = ['list_id', 'title', 'done', 'done_at', 'assigned_member_id', 'due_on', 'created_by'];

    protected function casts(): array
    {
        return [
            'done' => 'boolean',
            'done_at' => 'immutable_datetime',
            'due_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<TodoList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TodoList::class, 'list_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_member_id');
    }
}
