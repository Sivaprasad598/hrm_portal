<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeRelation extends BaseModel
{

    protected $fillable = [
        'user_id',
        'serial_no',
        'name',
        'relation',
        'contact_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
