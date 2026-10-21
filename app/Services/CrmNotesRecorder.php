<?php

namespace App\Services;

use App\Models\CustomerMeeting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * CRM Notes extension recordings: the extension uploads the call audio in ~5-minute segments.
 * Each segment is transcribed as soon as it arrives (the audio is then deleted, only text is kept),
 * and when the call ends the segment transcripts are joined and turned into meeting notes.
 *
 * Files: storage/app/crm-notes/{meeting id}/{segment}.txt
 */
class CrmNotesRecorder
{
    // A recording with no new segment for this long is treated as ended (browser closed mid-call)
    public const STALE_MINUTES = 15;

    public static function directory(CustomerMeeting $meeting): string
    {
        return storage_path('app/crm-notes/' . $meeting->id);
    }

    public static function addSegment(CustomerMeeting $meeting, int $index, UploadedFile $audio, ?int $userId): void
    {
        $dir = self::directory($meeting);
        File::ensureDirectoryExists($dir);

        // New recording of a meeting that already has AI notes (e.g. the call was joined again): start over
        if ($index === 0 && $meeting->notes_recording_status !== 'recording') {
            File::cleanDirectory($dir);
        }

        $meeting->forceFill([
            'notes_recording_status' => 'recording',
            'notes_recorded_by' => $userId,
            'notes_last_upload_at' => now(),
        ])->save();

        $extension = $audio->getClientOriginalExtension() ?: 'webm';
        $audioPath = $dir . '/' . $index . '.' . $extension;
        $audio->move($dir, $index . '.' . $extension);

        try {
            $text = OpenAiMeetingNotes::transcribe($audioPath, "segment-{$index}.{$extension}");
            File::put($dir . '/' . str_pad((string) $index, 4, '0', STR_PAD_LEFT) . '.txt', $text);
        } finally {
            // Keep only the text
            File::delete($audioPath);
        }
    }

    /**
     * Join the segment transcripts and write the meeting notes. Returns false if there was nothing to use.
     */
    public static function finish(CustomerMeeting $meeting): bool
    {
        $meeting->loadMissing(['customer', 'lead', 'client']);
        $files = glob(self::directory($meeting) . '/*.txt') ?: [];
        sort($files);

        $transcript = trim(implode("\n", array_map(fn ($file) => trim(File::get($file)), $files)));

        // Speech-to-text invents a few repeated words ("you you you") from silent audio: that is not speech
        $words = array_unique(str_word_count(strtolower($transcript), 1));

        if ($transcript === '' || count($words) < 3) {
            $meeting->forceFill(['notes_recording_status' => 'failed'])->save();
            Log::warning('CRM Notes: no speech was transcribed for meeting ' . $meeting->id);

            return false;
        }

        $meeting->forceFill(['notes_recording_status' => 'processing', 'meet_transcript' => $transcript])->save();

        try {
            $notes = OpenAiMeetingNotes::notes($meeting, $transcript);
        } catch (\Throwable $e) {
            $meeting->forceFill(['notes_recording_status' => 'failed'])->save();
            Log::error('CRM Notes: notes failed for meeting ' . $meeting->id . ': ' . $e->getMessage());

            return false;
        }

        $meeting->forceFill([
            'meet_notes' => $notes,
            'meet_notes_source' => 'ai',
            'meet_notes_fetched_at' => now(),
            'notes_recording_status' => 'done',
        ])->save();

        return true;
    }

    /**
     * Recordings whose browser stopped sending without "finish" (closed tab, lost connection).
     */
    public static function finishStale(): int
    {
        $done = 0;

        CustomerMeeting::where('notes_recording_status', 'recording')
            ->where('notes_last_upload_at', '<', now()->subMinutes(self::STALE_MINUTES))
            ->get()
            ->each(function (CustomerMeeting $meeting) use (&$done) {
                if (self::finish($meeting)) {
                    $done++;
                }
            });

        return $done;
    }
}
