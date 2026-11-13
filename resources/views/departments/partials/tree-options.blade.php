{{-- Department <option>s in tree order: each division in bold, its sub divisions
     indented under it with the division's name beside them (so the several "IT"s
     can be told apart). Typing in the search finds a sub division by its division too.
     Pass $departments (the ones the person may pick); $selected is optional. --}}
@foreach (\App\Models\Team::treeOptions($departments) as $dept)
    @php
        $deptHtml = $dept->depth === 0
            ? '<span class="dept-opt dept-opt--top">' . e($dept->name) . '</span>'
            : '<span class="dept-opt" style="padding-left:' . (14 * $dept->depth) . 'px">' . e($dept->name)
                . ' <small class="dept-opt__of">' . e($dept->division) . '</small></span>';
    @endphp
    <option value="{{ $dept->id }}" data-tokens="{{ $dept->path }}" data-content="{{ $deptHtml }}"
        @selected(isset($selected) && (string) $selected === (string) $dept->id)>{{ $dept->path }}</option>
@endforeach
{{-- (their look: .dept-opt in public/css/epic/theme.css, so it also works in pop-ups) --}}
