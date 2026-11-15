<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class UserOption extends Component
{

    public $user;
    public $selected;
    public $pill;
    public $agent;
    public $userID;
    public $additionalText;
    /** A plain option (the name only): for long lists, e.g. a filter of every employee */
    public $plain;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($user, $selected = false, $pill = false, $agent = false, $userID = null, $additionalText = null, $plain = false)
    {
        $this->user = $user;
        $this->selected = $selected;
        $this->pill = $pill;
        $this->agent = $agent;
        $this->userID = $userID;
        $this->additionalText = $additionalText;
        $this->plain = $plain;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return View|string
     */
    /**
     * A plain option as a string, without rendering the component: lists of every
     * employee rendered this component hundreds of times per page (0.5 ms each,
     * ~0.4-0.7 s on Employees and Tasks). Same value, label and selected state.
     */
    public static function plain($user, $selected = false, $userID = null): string
    {
        return '<option value="' . e($userID ?? $user->id) . '"' . ($selected ? ' selected' : '') . '>'
            . e($user->name_salutation) . '</option>';
    }

    public function render()
    {
        return view('components.user-option');
    }

}
