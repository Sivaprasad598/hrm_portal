<?php

namespace App\Console\Commands;

use App\Helper\Common;
use App\Helper\Files;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ConvertTaskBase64Images extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:convert-base64-images {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert base64 images in task descriptions and task comments to uploaded files';

    const FILE_PATH = 'quill-images';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in DRY-RUN mode. No changes will be made.');
        }

        $this->info('Starting to process tasks and task comments...');

        $processedTasksCount = 0;
        $updatedTasksCount = 0;
        $processedCommentsCount = 0;
        $updatedCommentsCount = 0;
        $errorCount = 0;

        // Process tasks in chunks to avoid memory issues
        $this->info("\n--- Processing Tasks ---");
        Task::whereNotNull('description')
            ->where('description', 'like', '%data:image%')
            ->chunk(50, function ($tasks) use (&$processedTasksCount, &$updatedTasksCount, &$errorCount, $dryRun) {
                foreach ($tasks as $task) {
                    $processedTasksCount++;

                    try {
                        $updated = $this->processTask($task, $dryRun);

                        if ($updated) {
                            $updatedTasksCount++;
                            $this->info("Processed task ID: {$task->id} - Updated");
                        } else {
                            $this->line("Processed task ID: {$task->id} - No changes needed");
                        }
                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->error("Error processing task ID: {$task->id} - {$e->getMessage()}");
                    }
                }
            });

        // Process task comments in chunks
        $this->info("\n--- Processing Task Comments ---");
        TaskComment::whereNotNull('comment')
            ->where('comment', 'like', '%data:image%')
            ->with('task') // Load task relationship to get company_id
            ->chunk(50, function ($comments) use (&$processedCommentsCount, &$updatedCommentsCount, &$errorCount, $dryRun) {
                foreach ($comments as $comment) {
                    $processedCommentsCount++;

                    try {
                        $updated = $this->processTaskComment($comment, $dryRun);

                        if ($updated) {
                            $updatedCommentsCount++;
                            $this->info("Processed comment ID: {$comment->id} - Updated");
                        } else {
                            $this->line("Processed comment ID: {$comment->id} - No changes needed");
                        }
                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->error("Error processing comment ID: {$comment->id} - {$e->getMessage()}");
                    }
                }
            });

        $this->info("\n=== Summary ===");
        $this->info("Tasks processed: {$processedTasksCount}");
        $this->info("Tasks updated: {$updatedTasksCount}");
        $this->info("Comments processed: {$processedCommentsCount}");
        $this->info("Comments updated: {$updatedCommentsCount}");
        $this->info("Total errors: {$errorCount}");

        if ($dryRun) {
            $this->warn("\nThis was a DRY-RUN. No changes were made.");
        }

        return Command::SUCCESS;
    }

    /**
     * Process a single task
     *
     * @param Task $task
     * @param bool $dryRun
     * @return bool
     */
    protected function processTask(Task $task, bool $dryRun): bool
    {
        $description = $task->description;

        if (empty($description) || strpos($description, 'data:image') === false) {
            return false;
        }

        // Use regex to find all base64 images in the description
        // This is more reliable than DOMDocument for potentially malformed HTML
        $pattern = '/<img[^>]+src=["\'](data:image\/(\w+);base64,([^"\']+))["\'][^>]*>/i';
        $updated = false;
        $modifiedDescription = $description;

        preg_match_all($pattern, $description, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if (empty($matches)) {
            return false;
        }

        // Process matches in reverse order to maintain offsets
        $matches = array_reverse($matches);

        foreach ($matches as $match) {
            $fullMatch = $match[0][0];
            $fullSrc = $match[1][0];
            $imageType = $match[2][0];
            $base64Data = $match[3][0];

            $this->line("  Found base64 image (type: {$imageType}) in task ID: {$task->id}");

            if (!$dryRun) {
                // Decode base64 data
                $imageData = base64_decode($base64Data, true);

                if ($imageData === false) {
                    $this->warn("  Failed to decode base64 data for task ID: {$task->id}");
                    continue;
                }

                // Generate filename
                $extension = $imageType === 'jpeg' ? 'jpg' : $imageType;
                $tempFilename = "temp_image_" . uniqid() . ".{$extension}";

                // Create temp directory if it doesn't exist
                $tempDir = public_path(Files::UPLOAD_FOLDER . '/temp');
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0775, true);
                }

                // Save to temp file
                $tempPath = $tempDir . '/' . $tempFilename;
                file_put_contents($tempPath, $imageData);

                // Create UploadedFile instance to match ImageController pattern
                $uploadedFile = new UploadedFile(
                    $tempPath,
                    "image.{$extension}",
                    "image/{$imageType}",
                    null,
                    true // test mode
                );

                // Use the same upload method as ImageController
                $filename = Files::uploadLocalOrS3($uploadedFile, self::FILE_PATH);

                // Clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }

                // Update file storage record with company_id if available
                if ($task->company_id) {
                    $fileStorage = \App\Models\FileStorage::where('filename', $filename)->first();
                    if ($fileStorage) {
                        $fileStorage->company_id = $task->company_id;
                        $fileStorage->save();
                    }
                }

                // Generate encrypted filename for URL (same as ImageController)
                $encrypted = Common::encryptDecrypt($filename);
                $newUrl = route('image.getImage', $encrypted);

                // Replace the full img tag's src attribute
                $newImgTag = preg_replace(
                    '/src=["\']data:image\/\w+;base64,[^"\']+["\']/i',
                    'src="' . $newUrl . '"',
                    $fullMatch
                );

                // Replace in description
                $modifiedDescription = str_replace($fullMatch, $newImgTag, $modifiedDescription);

                $this->line("  Uploaded image: {$filename}");
            } else {
                $this->line("  [DRY-RUN] Would upload base64 image");
            }

            $updated = true;
        }

        // Update task description if changes were made
        if ($updated && !$dryRun && $modifiedDescription !== $description) {
            $task->description = $modifiedDescription;
            $task->save();
        }

        return $updated;
    }

    /**
     * Process a single task comment
     *
     * @param TaskComment $comment
     * @param bool $dryRun
     * @return bool
     */
    protected function processTaskComment(TaskComment $comment, bool $dryRun): bool
    {
        $commentText = $comment->comment;

        if (empty($commentText) || strpos($commentText, 'data:image') === false) {
            return false;
        }

        // Use regex to find all base64 images in the comment
        $pattern = '/<img[^>]+src=["\'](data:image\/(\w+);base64,([^"\']+))["\'][^>]*>/i';
        $updated = false;
        $modifiedComment = $commentText;

        preg_match_all($pattern, $commentText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if (empty($matches)) {
            return false;
        }

        // Process matches in reverse order to maintain offsets
        $matches = array_reverse($matches);

        // Get company_id from related task
        $companyId = $comment->task ? $comment->task->company_id : null;

        foreach ($matches as $match) {
            $fullMatch = $match[0][0];
            $fullSrc = $match[1][0];
            $imageType = $match[2][0];
            $base64Data = $match[3][0];

            $this->line("  Found base64 image (type: {$imageType}) in comment ID: {$comment->id}");

            if (!$dryRun) {
                // Decode base64 data
                $imageData = base64_decode($base64Data, true);

                if ($imageData === false) {
                    $this->warn("  Failed to decode base64 data for comment ID: {$comment->id}");
                    continue;
                }

                // Generate filename
                $extension = $imageType === 'jpeg' ? 'jpg' : $imageType;
                $tempFilename = "temp_image_" . uniqid() . ".{$extension}";

                // Create temp directory if it doesn't exist
                $tempDir = public_path(Files::UPLOAD_FOLDER . '/temp');
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0775, true);
                }

                // Save to temp file
                $tempPath = $tempDir . '/' . $tempFilename;
                file_put_contents($tempPath, $imageData);

                // Create UploadedFile instance to match ImageController pattern
                $uploadedFile = new UploadedFile(
                    $tempPath,
                    "image.{$extension}",
                    "image/{$imageType}",
                    null,
                    true // test mode
                );

                // Use the same upload method as ImageController
                $filename = Files::uploadLocalOrS3($uploadedFile, self::FILE_PATH);

                // Clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }

                // Update file storage record with company_id if available
                if ($companyId) {
                    $fileStorage = \App\Models\FileStorage::where('filename', $filename)->first();
                    if ($fileStorage) {
                        $fileStorage->company_id = $companyId;
                        $fileStorage->save();
                    }
                }

                // Generate encrypted filename for URL (same as ImageController)
                $encrypted = Common::encryptDecrypt($filename);
                $newUrl = route('image.getImage', $encrypted);

                // Replace the full img tag's src attribute
                $newImgTag = preg_replace(
                    '/src=["\']data:image\/\w+;base64,[^"\']+["\']/i',
                    'src="' . $newUrl . '"',
                    $fullMatch
                );

                // Replace in comment
                $modifiedComment = str_replace($fullMatch, $newImgTag, $modifiedComment);

                $this->line("  Uploaded image: {$filename}");
            } else {
                $this->line("  [DRY-RUN] Would upload base64 image");
            }

            $updated = true;
        }

        // Update comment if changes were made
        if ($updated && !$dryRun && $modifiedComment !== $commentText) {
            $comment->comment = $modifiedComment;
            $comment->save();
        }

        return $updated;
    }
}
