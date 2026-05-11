@extends('layouts/default')

@section('title') {{ trans('general.edit_Template') }}: {{ $termTemplate->name }} @stop

@section('content')
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">{{ trans('general.Edit') }}: {{ $termTemplate->name }}</h3>
            </div>

            <form action="{{ route('term-templates.update', $termTemplate->id) }}"
                  method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="box-body">

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <div class="form-group">
                        <label>{{ trans('general.template_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               value="{{ old('name', $termTemplate->name) }}">
                    </div>

                    <div class="form-group">
                        <label>{{ trans('general.change_file') }} .docx</label>
                        <input type="file" name="template" accept=".docx">
                        <p class="help-block">
                            {{ trans('general.current_file') }}: <code>{{ $termTemplate->file_name }}</code>.
                            {{ trans('general.leave_it_empty_to_preserve') }}.
                        </p>
                    </div>

                    <div class="form-group">
                        <label>{{ trans('general.allowed_categories') }}</label>

                        {{-- Opção "Todas" --}}
                        <div class="checkbox" style="margin-bottom: 8px;">
                            <label>
                                <input type="checkbox" id="all_categories">
                                <strong>{{trans('general.all_categories')}}</strong>
                            </label>
                        </div>

                        <div id="categories_list" class="well well-sm" style="max-height: 200px; overflow-y: auto;">
                            @foreach($categories as $category)
                            <div class="checkbox" style="margin: 2px 0;">
                                <label>
                                    <input type="checkbox"
                                        name="allowed_categories[]"
                                        value="{{ $category->name }}"
                                        class="category-checkbox"
                                    {{-- Para o edit, marca as já salvas --}}
                                    {{ isset($termTemplate) && $termTemplate->allowed_categories && in_array($category->name, $termTemplate->allowed_categories) ? 'checked' : '' }}>
                                    {{ $category->name }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <p class="help-block">{{ trans('general.leave_uncheck_category') }}.</p>

                    </div>
                    <div class="form-group {{ $errors->has('term_type') ? 'has-error' : '' }}">
                        <label>{{ trans('general.document_type') }} <span class="text-danger">*</span></label>
                        <select name="term_type" class="form-control" required>
                            <option value="">{{ trans('general.select_document_purpose') }}</option>
                            <option value="checkout" {{ old('term_type', $termTemplate->term_type) == 'checkout' ? 'selected' : '' }}>{{ trans('general.terms_responsibility') }} (Checkout)</option>
                            <option value="checkin" {{ old('term_type', $termTemplate->term_type) == 'checkin' ? 'selected' : '' }}>{{ trans('general.return_term') }} (Checkin)</option>
                        </select>
                        <p class="help-block">{{ trans('general.defines_when_generated') }}</p>
                    </div>

                    <div class="form-group {{ $errors->has('depends_on') ? 'has-error' : '' }}">
                        <label>{{ trans('general.it_depends_on_optional') }}</label>
                        <select name="depends_on" class="form-control">
                            <option value="">{{ trans('no_dependency') }}</option>
                                @foreach(\App\Models\TermTemplate::where('active', true)
                                    ->whereNull('depends_on')  {{-- evita dependências circulares --}}
                                    ->where('id', '!=', isset($termTemplate) ? $termTemplate->id : 0)
                                    ->orderBy('term_type')->orderBy('name')
                                    ->get() as $dep)
                            <option value="{{ $dep->term_type }}"
                                {{ old('depends_on', isset($termTemplate) ? $termTemplate->depends_on : '') == $dep->term_type ? 'selected' : '' }}>
                                [{{ $dep->term_type }}] {{ $dep->name }}
                            </option>
                                @endforeach
                        </select>
                        <p class="help-block">
                            {{ trans('general.if_filled_term_generated_after_the_selected_type_has_been_generated') }}
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ trans('general.status') }}</label>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="active" value="1"
                                    {{ old('active', $termTemplate->active) ? 'checked' : '' }}>
                                {{ trans('general.active_template') }}
                            </label>
                        </div>
                    </div>

                    {{-- Histórico de uso --}}
                    @if($termTemplate->logs()->count() > 0)
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title">
                                {{ trans('general.last_generations') }} ({{ $termTemplate->logs()->count() }} {{ trans('general.total') }})
                            </h3>
                        </div>
                        <div class="box-body no-padding">
                            <table class="table table-condensed">
                                <thead>
                                    <tr>
                                        <th>{{ trans('general.date') }}</th>
                                        <th>{{ trans('general.asset') }}</th>
                                        <th>{{ trans('general.username') }}</th>
                                        <th>{{ trans('general.generated_by') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($termTemplate->logs()->with(['asset','assignedUser','generatedBy'])->latest()->take(10)->get() as $log)
                                    <tr>
                                        <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            @if($log->asset)
                                                <a href="{{ route('hardware.show', $log->asset->id) }}">
                                                    {{ $log->asset->asset_tag }}
                                                </a>
                                            @endif
                                        </td>
                                        <td>{{ $log->assignedUser?->first_name }} {{ $log->assignedUser?->last_name }}</td>
                                        <td>{{ $log->generatedBy?->first_name }} {{ $log->generatedBy?->last_name }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                </div>
                <div class="box-footer">
                    <a href="{{ route('term-templates.index') }}" class="btn btn-default">{{ trans('general.cancel') }}</a>
                    <button type="submit" class="btn btn-primary pull-right">
                        <i class="fa fa-save"></i> {{ trans('general.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Botão "Todas" marca/desmarca todas
document.getElementById('all_categories').addEventListener('change', function() {
    document.querySelectorAll('.category-checkbox').forEach(cb => {
        cb.checked = this.checked;
    });
});

// Se todas estiverem marcadas, marca o "Todas" também
document.querySelectorAll('.category-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        const all = document.querySelectorAll('.category-checkbox');
        const allChecked = [...all].every(c => c.checked);
        document.getElementById('all_categories').checked = allChecked;
    });
});
</script>
@stop