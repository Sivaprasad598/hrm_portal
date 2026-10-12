<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\CustomerMeeting;
use App\Models\MeetingOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Add / edit / delete the Meeting Purpose, Meeting Status and Meeting Result dropdown options.
 */
class MeetingOptionController extends AccountBaseController
{
    private const EMOJI_PATTERN = '/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u';

    public function index($type)
    {
        $this->checkType($type);

        return Reply::dataOnly([
            'status' => 'success',
            'options' => MeetingOption::ofType($type)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, $type)
    {
        $this->checkType($type);
        $name = $this->validatedName($request, $type);

        $option = MeetingOption::create([
            'type' => $type,
            'name' => $name,
            'sort_order' => (MeetingOption::where('type', $type)->max('sort_order') ?? 0) + 1,
        ]);

        return Reply::successWithData(MeetingOption::TYPES[$type] . ' added successfully.', [
            'option' => $option->only(['id', 'name']),
        ]);
    }

    public function update(Request $request, $type, $id)
    {
        $this->checkType($type);
        $option = MeetingOption::where('type', $type)->findOrFail($id);
        $name = $this->validatedName($request, $type, $option->id);
        $oldName = $option->name;

        DB::transaction(function () use ($option, $name, $oldName, $type) {
            $option->update(['name' => $name]);

            // Meetings store the text, so keep existing meetings in step with the rename
            $column = MeetingOption::MEETING_COLUMNS[$type];
            CustomerMeeting::where($column, $oldName)->update([$column => $name]);
        });

        return Reply::successWithData(MeetingOption::TYPES[$type] . ' updated successfully.', [
            'option' => $option->only(['id', 'name']),
        ]);
    }

    public function destroy($type, $id)
    {
        $this->checkType($type);
        $option = MeetingOption::where('type', $type)->findOrFail($id);

        if (MeetingOption::where('type', $type)->count() <= 1) {
            return response()->json([
                'status' => 'fail',
                'message' => 'At least one ' . strtolower(MeetingOption::TYPES[$type]) . ' is required.',
            ], 422);
        }

        $option->delete();

        return Reply::success(MeetingOption::TYPES[$type] . ' deleted successfully.');
    }

    private function checkType($type): void
    {
        abort_unless(array_key_exists($type, MeetingOption::TYPES), 404);
    }

    /**
     * Trimmed name, rejecting emojis and case-insensitive duplicates within the same type.
     */
    private function validatedName(Request $request, string $type, ?int $ignoreId = null): string
    {
        $name = preg_replace('/\s+/', ' ', trim((string) $request->name));
        $request->merge(['name' => $name]);
        $label = MeetingOption::TYPES[$type];

        $request->validate([
            'name' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) use ($type, $ignoreId, $label) {
                if (preg_match(self::EMOJI_PATTERN, $value)) {
                    $fail('Emojis are not allowed.');

                    return;
                }

                $exists = MeetingOption::where('type', $type)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                    ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                    ->exists();

                if ($exists) {
                    $fail('"' . $value . '" already exists in ' . $label . '.');
                }
            }],
        ], [
            'name.required' => 'Enter a ' . strtolower($label) . ' name.',
        ]);

        return $name;
    }
}
