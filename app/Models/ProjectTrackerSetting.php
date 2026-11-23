<?php

namespace App\Models;

/** The project tracker's company settings: the working hours a day used for capacity (8, or 7). */
class ProjectTrackerSetting extends BaseModel
{

    protected $guarded = ['id'];

    protected $casts = ['workday_hours' => 'float'];

    public static function forCompany(?int $companyId = null): self
    {
        $companyId = $companyId ?? company()->id;

        return self::firstOrCreate(['company_id' => $companyId], ['workday_hours' => 8]);
    }

}
