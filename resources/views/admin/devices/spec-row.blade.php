{{-- One "other spec" row; also used as the template for new rows. --}}
<div class="row g-2 mb-2 spec-row">
    <div class="col-md-5">
        <select name="spec_keys[]" data-combo="create" data-placeholder="Spec name (e.g. Screen)">
            <option value=""></option>
            @foreach(collect($specNames)->when(filled($key) && ! in_array($key, $specNames, true), fn ($c) => $c->prepend($key)) as $name)
                <option value="{{ $name }}" @selected($name === $key)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6"><input name="spec_values[]" value="{{ $value }}" class="form-control" placeholder="Value (e.g. 14&quot; FHD)"></div>
    <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-spec" title="Remove"><i class="bi bi-x"></i></button></div>
</div>
