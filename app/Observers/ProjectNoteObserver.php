<?php

namespace App\Observers;

use App\Events\ProjectNoteEvent;
use App\Events\ProjectNoteMentionEvent;
use App\Events\ProjectNoteUpdateEvent;
use App\Models\ProjectUserNote;
use App\Models\ProjectNote;
use App\Models\User;

// use function GuzzleHttp\json_decode;

class ProjectNoteObserver
{

    /**
     * @param ProjectNote $ProjectNote
     */
    public function saving(ProjectNote $ProjectNote)
    {
        if (!isRunningInConsoleOrSeeding()) {
            $ProjectNote->last_updated_by = user()->id;
        }
    }

    public function creating(ProjectNote $ProjectNote)
    {
        if (!isRunningInConsoleOrSeeding()) {
            $ProjectNote->added_by = user()->id;
        }
    }

    public function created(ProjectNote $projectNote)
    {
        $project = $projectNote->project;

        if (request()->mention_user_id != null && request()->mention_user_id != '') {

            $projectNote->mentionUser()->sync(request()->mention_user_id);

            $projectUsers = json_decode($project->projectMembers->pluck('id'));

            $mentionIds = json_decode($projectNote->mentionNote->pluck('user_id'));

            $mentionUserId = array_intersect($mentionIds, $projectUsers);

            if ($mentionUserId != null && $mentionUserId != '') {

                event(new ProjectNoteMentionEvent($project, $projectNote->created_at, $mentionUserId));

            }

            $unmentionIds = array_diff($projectUsers, $mentionIds);

            if ($unmentionIds != null && $unmentionIds != '') {

                $projectNoteUsers = User::whereIn('id', $unmentionIds)->get();
                event(new ProjectNoteEvent($project, $projectNote->created_at, $projectNoteUsers));

            }

        }
        else {

            if ($projectNote->type == 0) {
                event(new ProjectNoteEvent($project, $projectNote->created_at, $projectNote->project->projectMembers));
            } else {
                $projectNoteUsers = User::whereIn('id', request()?->user_id)->get();
                event(new ProjectNoteEvent($project, $projectNote->created_at, $projectNoteUsers));
            }

        }

    }

    public function updating(ProjectNote $projectNote)
    {
        $mentionedUser = ProjectUserNote::where('project_note_id', $projectNote->id)->pluck('user_id')->map(fn($id) => (string) $id)->toArray();
        $requestUserId = request()->user_id ?? [];
        $newMention = array_diff($requestUserId, $mentionedUser);
        $project = $projectNote->project;

        if (!empty($newMention) && $projectNote->type == '1') {
            event(new ProjectNoteMentionEvent($project, $projectNote->created_at, $newMention));
        }

        // Check for title or details changes
        $changes = [];

        if ($projectNote->isDirty('title')) {
            $changes['title'] = [
                'old' => $projectNote->getOriginal('title'),
                'new' => $projectNote->title
            ];
        }

        if ($projectNote->isDirty('details')) {
            $changes['details'] = [
                'old' => $projectNote->getOriginal('details'),
                'new' => $projectNote->details
            ];
        }

        // If there are changes in title or details, send notification
        if (!empty($changes)) {
            $notifyUsers = collect();

            if ($projectNote->type == 0) {
                // Public note - notify all project members
                $notifyUsers = $project->projectMembers;
            } else {
                // Private note - notify only assigned users
                $notifyUsers = User::whereIn('id', $requestUserId)->get();
            }

            if ($notifyUsers->isNotEmpty()) {
                event(new ProjectNoteUpdateEvent($project, $projectNote, $notifyUsers));
            }
        }
    }

}
