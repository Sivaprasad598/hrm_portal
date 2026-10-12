<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingOption extends Model
{
    public const TYPES = [
        'purpose' => 'Meeting Purpose',
        'status' => 'Meeting Status',
        'result' => 'Meeting Result',
    ];

    // customer_meetings column that stores each type's chosen name
    public const MEETING_COLUMNS = [
        'purpose' => 'purpose',
        'status' => 'status',
        'result' => 'outcome',
    ];

    protected $table = 'meeting_options';

    protected $guarded = ['id'];

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type)->orderBy('sort_order')->orderBy('id');
    }
}
