@php
$content = "<div class='d-flex align-items-center text-left'>
    <div class='taskEmployeeImg border-0 d-inline-block mr-1'>
        <img class='rounded-circle' src='".$user->image_url."'>
    </div>
    <div>". htmlentities($user->userBadge());

        if (isset($additionalText) && !is_null($additionalText)) {
        $content .= "<div class='f-10 font-weight-light my-1'>".$additionalText."</div>";
        }

        $content.="</div>";

    if($agent){
        $content .= ' ['.$user->email.'] ';
    }

    if($pill){
        $content = "<span class='badge badge-pill badge-light border abc'>".$content."</span>";
    }

    if ($user->status == 'deactive') {
        $content .= "<span class='badge badge-pill badge-danger border align-center ml-2 px-2'>Inactive</span>";
    } 

    // one line: the markup is repeated in every option of every people dropdown
    $content = preg_replace('/\s+/', ' ', trim($content));

    @endphp

    {{-- plain: no picture or markup per option (a list of hundreds of people stays light) --}}
    <option @selected($selected) @unless($plain) data-content="{!! $content !!}" @endunless value="{{ $userID ?? $user->id }}">
        {{ $user->name_salutation }}
    </option>
