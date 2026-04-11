<div class='row filter'>
    @foreach ($filters as $key => $filter)
        <div class='col-lg-2 filter-input'>
            @if (gettype($filter['type'] ?? '') == 'array')
                <select class="datatable-search-field select2-init" name="{{ $key }}">
                    <option value=""> 选择{{ $filter['name'] ?? '' }}
                    </option>
                    @foreach ($filter['type'] as $optionKey => $optionValue)
                        <option value="{{ $optionKey }}">
                            {{ $optionValue }}
                        </option>
                    @endforeach
                </select>
            @else
                @switch($filter['type'])
                    @case('date2')
                        <label for="start">选择开始{{ $filter['name'] ?? '' }} : </label>
                        <input class="datatable-search-field" type="date" name="{{ $key }}_from">
                </div>
                <div class='col-lg-2 filter-input'>
                    <label for="start">选择结束{{ $filter['name'] ?? '' }} : </label>
                    <input class="datatable-search-field" type="date" name="{{ $key }}_to">
                @break

                @case('date')
                    <label for="start">选择{{ $filter['name'] ?? '' }} : </label>
                    <input class="datatable-search-field" type="date" name="{{ $key }}">
                @break

                @case('select')
                    <select class="datatable-search-field" id="search_{{ $key }}" name="{{ $key }}">
                        <option value=""> 选择{{ $filter['name'] ?? '' }}
                    </select>
                    <script>
                        $(document).ready(function() {
                            $('#search_{{$key}}').select2({
                                ajax: {
                                    url: '{{($filter['route'] ?? "")}}',
                                    dataType: 'json',
                                    data: function(params) {
                                        return {
                                            q: params.term || '',
                                            page: params.page || 1,
                                            pre: '{{json_encode($filter['init'] ?? [])}}'
                                        }
                                    },
                                    cache: true
                                }
                            });
                       });
                    </script>
                @break

                @case('text')
                    <input class="datatable-search-field search-text-field" placeholder="选择{{ $filter['name'] ?? '' }}" type="text" name="{{ $key }}">
                @break
            @endswitch
    @endif
</div>
@endforeach
@if (!empty($filters))
    <div class="col-lg-12" style="text-align: right;">
        <button class="btn btn-sm search-reset-btn">
            重置
        </button>
    </div>
@endif
</div>
@if (!empty($filters))
    <hr>
@endif
