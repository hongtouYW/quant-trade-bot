@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('question.edit_question'))
@section('header-title', __('question.edit_question'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('question.edit_question') }}: {{ $question->question_id }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.question.update', $question->question_id) }}" method="POST" class="p-4" enctype="multipart/form-data">
                    @csrf
                    @method('PUT') {{-- Use PUT method for update --}}

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="title" class="form-label">{{ __('question.title') }}</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $question->title) }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="question_desc" class="form-label">{{ __('question.question_desc') }}</label>
                        <textarea class="form-control @error('question_desc') is-invalid @enderror" id="question_desc" name="question_desc" maxlength="10000" style="height: 100px;">{{ old('question_desc', $question->question_desc) }}</textarea>
                        @error('question_desc')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="question_type" class="form-label">{{ __('question.select') }}</label>
                        <select class="form-control @error('question_type') is-invalid @enderror" id="question_type" name="question_type">
                            <option value="">{{ __('question.select') }}</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" {{ $type == $question->question_type ? 'selected' : '' }}>
                                    {{ __('question.'.$type) }}
                                </option>
                            @endforeach
                        </select>
                        @error('question_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="picture" class="form-label">{{ __('question.picture') }}</label>
                            <input type="file" class="form-control @error('picture') is-invalid @enderror" id="picture" name="picture" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @if ($question->picture)
                                <p class="text-sm mt-2"><img src="{{ asset('storage/' . $question->picture) }}" alt="{{ $question->title }}" class="img-fluid" style="max-width: 100px;"></p>
                            @endif
                            @error('picture')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('question.selectrelated') }}</label>
                        <div id="questionrelated" name="questionrelated">

                            @foreach ($relateds as $index => $related)
                                <div class="card p-3 mb-3 border">
                                    <input type="hidden" name="related[{{ $index }}][questionrelated_id]" value="{{ $related->questionrelated_id }}">
                                    @php
                                        $titleId = "related_title_existing_$index";
                                        $textareaId = "related_question_desc_existing_$index";
                                        $fileId = "related_picture_existing_$index";
                                    @endphp
                                    <div class="mb-2">
                                        <label class="form-label" for="{{ $titleId }}">{{ __('question.title') }}</label>
                                        <input
                                            type="text"
                                            name="related[{{ $index }}][title]"
                                            class="form-control"
                                            maxlength="200"
                                            value="{{ old('related.$index.title', $related->title) }}"
                                        >
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label" for="{{ $textareaId }}">{{ __('question.question_desc') }}</label>
                                        <textarea
                                            name="related[{{ $index }}][question_desc]"
                                            class="form-control"
                                            maxlength="10000"
                                            style="height: 100px;"
                                        >{{ old("related.$index.question_desc", $related->question_desc) }}</textarea>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label" for="{{ $fileId }}">{{ __('question.picture') }}</label>
                                        <input
                                            type="file"
                                            name="related[{{ $index }}][picture]"
                                            class="form-control"
                                            accept="image/*"
                                        >

                                        @if ($related->picture)
                                            <img
                                                src="{{ asset('storage/'.$related->picture) }}"
                                                class="img-fluid mt-2"
                                                style="max-width:100px"
                                            >
                                        @endif
                                    </div>

                                    <div class="mb-2">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger"
                                            onclick="this.closest('.card').remove()"
                                        >
                                            {{ __('messages.delete') ?? 'Remove' }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-success mt-2" onclick="addRelatedQuestion()">
                            + {{ __('messages.add') }}
                        </button>
                    </div>
                    <div class="mb-3">
                        <label for="language" class="form-label">{{ __('messages.selectlanguage') }}</label>
                        <select class="form-control @error('lang') is-invalid @enderror" id="language" name="language">
                            <option value="">{{ __('messages.selectlanguage') }}</option>
                            @foreach ($langs as $lang)
                                <option value="{{ $lang }}" {{ $question->lang == $lang ? 'selected' : '' }}>
                                    {{ __('messages.'.$lang) }}
                                </option>
                            @endforeach
                        </select>
                        @error('lang')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $question->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('question.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('question.edit_question') }}</button>
                    <a href="{{ route('admin.question.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@section('js')
<script>
    let relatedIndex = {{ count($relateds) }};

    function addRelatedQuestion() {
        const container = document.getElementById('questionrelated');

        const row = document.createElement('div');
        row.classList.add('card', 'p-3', 'mb-3', 'border');
        const titleId = `related_title_${relatedIndex}`;
        const textareaId = `related_question_desc_${relatedIndex}`;
        const fileId = `related_picture_${relatedIndex}`;
        row.innerHTML = `
            <div class="mb-2">
                <label class="form-label" for="${titleId}">{{ __('question.title') }}</label>
                <input
                    type="text"
                    name="related[${relatedIndex}][title]"
                    class="form-control"
                    maxlength="200"
                >
            </div>

            <div class="mb-2">
                <label class="form-label" for="${textareaId}">{{ __('question.question_desc') }}</label>
                <textarea
                    name="related[${relatedIndex}][question_desc]"
                    class="form-control"
                    maxlength="10000"
                    style="height: 100px;"
                ></textarea>
            </div>

            <div class="mb-2">
                <label class="form-label" for="${fileId}">{{ __('question.picture') }}</label>
                <input
                    type="file"
                    name="related[${relatedIndex}][picture]"
                    class="form-control"
                    accept="image/*"
                >
            </div>
            <div class="mb-2">
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.card').remove()">
                    {{ __('messages.delete') ?? 'Remove' }}
                </button>
            </div>
        `;

        container.appendChild(row);
        relatedIndex++;
    }
</script>
@endsection
