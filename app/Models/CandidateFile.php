<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file kept for a candidate: the resume, a document, or the signed offer.
 * Stored under public/user-uploads/candidate-files/{candidate id}/ - the same
 * way employee documents are.
 */
class CandidateFile extends BaseModel
{

    use HasCompany;

    public const FILE_PATH = 'candidate-files';

    public const CATEGORIES = ['resume' => 'Resume', 'document' => 'Document', 'signed_offer' => 'Signed offer'];

    protected $guarded = ['id'];

    protected $appends = ['url'];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function getUrlAttribute(): string
    {
        return asset_url_local_s3(self::FILE_PATH . '/' . $this->candidate_id . '/' . $this->hashname);
    }

    /** Where the file sits on the local disk (the default disk is public/user-uploads). */
    public function localPath(): string
    {
        return public_path(\App\Helper\Files::UPLOAD_FOLDER . '/' . self::FILE_PATH . '/' . $this->candidate_id . '/' . $this->hashname);
    }

    public function isPdf(): bool
    {
        return str_contains((string) $this->mime, 'pdf') || str_ends_with(strtolower($this->filename), '.pdf');
    }

}
