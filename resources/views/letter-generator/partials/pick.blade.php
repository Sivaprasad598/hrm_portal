{{-- A searchable drop-down for the generator (designation, location, department): the app's own
     picker. A value from an employee that is not in the list is kept as its own option.
     $name, $options (list of strings), $value, $title --}}
@php $value = (string) ($value ?? ''); @endphp
<select name="{{ $name }}" class="form-control select-picker gen-pick" data-live-search="true" data-size="8" title="{{ $title }}">
    <option value="">--</option>
    @if ($value !== '' && !collect($options)->contains($value))
        <option value="{{ $value }}" selected>{{ $value }}</option>
    @endif
    @foreach ($options as $option)
        <option value="{{ $option }}" @selected($option === $value)>{{ $option }}</option>
    @endforeach
</select>
