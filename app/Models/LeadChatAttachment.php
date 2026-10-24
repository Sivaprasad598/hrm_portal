<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A file sent in Team Chat. Stored in storage/app/lead-chat/<lead id>/ (the lead_chat disk, outside the web root); downloaded only through LeadChatController,
 * which checks the person is on the lead's team.
 */
class LeadChatAttachment extends Model
{

    protected $guarded = ['id'];

    /**
     * Approved upload categories. A file is accepted only when its name names one of them, matched without case and
     * with _ - . & treated as spaces: "Deliverables - GIS map.pdf", "Survey data.kml", "Engineering_Requirements.pdf", "Capacity plan.docx",
     * "Assumptions v2.pdf", "Dependencies list.xlsx". The patterns run on the normalised name (lowercase, words separated by spaces).
     */
    // Each starts at the beginning of a word, so "CapacityPlan" and "Deliverables2026" match but "Incapacity" does not
    public const CATEGORIES = [
        'Deliverables' => '\bdeliverable',
        'Survey' => '\bsurvey',
        'Engineering Requirements' => '\bengineering ?requirement',
        'Capacity' => '\bcapacity',
        'Assumptions' => '\bassumption',
        'Dependencies' => '\bdependenc',
    ];

    /** The approved category a file name belongs to, or null when it names none. */
    public static function categoryFor(string $filename): ?string
    {
        // "CapacityPlan" -> "Capacity Plan", then everything but letters and digits becomes a space
        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', pathinfo($filename, PATHINFO_FILENAME));
        $name = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $name)));

        foreach (self::CATEGORIES as $category => $pattern) {
            if (preg_match('/' . $pattern . '/', $name)) {
                return $category;
            }
        }

        return null;
    }

    public function message()
    {
        return $this->belongsTo(LeadChatMessage::class, 'message_id');
    }

    public function sizeLabel(): string
    {
        $kb = $this->size / 1024;

        return $kb >= 1024 ? round($kb / 1024, 1) . ' MB' : max(1, round($kb)) . ' KB';
    }

}
