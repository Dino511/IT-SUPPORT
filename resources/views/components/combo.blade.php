{{--
    Searchable master-data dropdown. Admins pick from $options; with :create="true"
    the Super Admin may also type a new value. A current value that is no longer
    in the list (deactivated option) is kept so editing doesn't drop it.
--}}
@props(['name', 'options' => [], 'value' => null, 'create' => true, 'required' => false, 'placeholder' => null])
@php
    $value = old($name, $value);
    $options = collect($options)->when(filled($value) && ! in_array($value, $options, true), fn ($c) => $c->prepend($value));
@endphp
<select name="{{ $name }}" {{ $attributes }} data-combo="{{ $create ? 'create' : '' }}" @required($required) @if($placeholder) data-placeholder="{{ $placeholder }}" @endif>
    <option value=""></option>
    @foreach($options as $opt)
        <option value="{{ $opt }}" @selected($opt === $value)>{{ $opt }}</option>
    @endforeach
</select>
