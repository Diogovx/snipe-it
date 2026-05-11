@extends('layouts/default')

@section('title') {{ trans('general.new_template') }} @stop

@section('content')
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">{{ trans('general.new_template') }}</h3>
            </div>

            <form action="{{ route('term-templates.store') }}"
                  method="POST" enctype="multipart/form-data">
                @csrf
                <div class="box-body">

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="form-group {{ $errors->has('name') ? 'has-error' : '' }}">
                        <label>{{ trans('general.template_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               value="{{ old('name') }}"
                               placeholder="Ex: Termo de Responsabilidade">
                    </div>

                    <div class="form-group {{ $errors->has('template') ? 'has-error' : '' }}">
                        <label>{{ trans('general.file') }} .docx <span class="text-danger">*</span></label>
                        <input type="file" name="template" accept=".docx">
                        <p class="help-block">
                            {{ trans('general.use') }} 
                            <code>${ {{ trans('general.var') }} }</code> 
                            {{ trans('general.in_document_fields') }}.
                            {{ trans('general.see_var_below') }}.
                        </p>
                    </div>

                    <div class="form-group">
                        {{-- Opção "Todas" --}}
    <div class="checkbox" style="margin-bottom: 8px;">
        <label>
            <input type="checkbox" id="all_categories">
            <strong>{{ trans('general.all_categories') }}</strong>
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
                       {{ isset($termTemplate) && $termTemplate->allowed_categories && in_array($category->name, $termTemplate->allowed_categories) ? 'checked' : '' }}>
                {{ $category->name }}
            </label>
        </div>
        @endforeach
    </div>
<div class="form-group {{ $errors->has('term_type') ? 'has-error' : '' }}">
    <label>{{ trans('general.document_type') }} <span class="text-danger">*</span></label>
    <select name="term_type" class="form-control" required>
        <option value="">{{ trans('general.select_document_purpose') }}</option>
        <option value="checkout" {{ old('term_type') == 'checkout' ? 'selected' : '' }}>
            {{ trans('general.terms_responsibility') }} (Checkout)
        </option>
        <option value="checkin" {{ old('term_type') == 'checkin' ? 'selected' : '' }}>
            {{ trans('general.return_term') }} (Checkin)
        </option>
    </select>
    <p class="help-block">{{ trans('general.defines_when_generated') }}</p>
</div>

    <div class="form-group {{ $errors->has('depends_on') ? 'has-error' : '' }}">
                        <label>{{trans('general.it_depends_on_optional')}}</label>
                        <select name="depends_on" class="form-control">
                            <option value="">{{ trans('general.no_dependency') }}</option>
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

    <p class="help-block">{{ trans('general.leave_uncheck_category') }}.</p>
                    </div>

                    {{-- Variáveis disponíveis --}}
                    <div class="box box-info collapsed-box">
                        <div class="box-header with-border" data-widget="collapse" style="cursor:pointer;">
                            <h3 class="box-title">
                                <i class="fa fa-info-circle"></i>
                                {{ trans('general.var_available') }}
                            </h3>
                            <div class="box-tools pull-right">
                                <button type="button" class="btn btn-box-tool">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>{{ trans('general.user') }}</strong>
                                    <ul class="list-unstyled" style="font-family: monospace; font-size: 12px; margin-top: 6px;">
                                        <li><code>${user_name}</code></li>
                                        <li><code>${user_email}</code></li>
                                        <li><code>${user_employee_num}</code></li>
                                        <li><code>${user_department}</code></li>
                                        <li><code>${user_location}</code></li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <strong>{{ trans('general.asset') }}</strong>
                                    <ul class="list-unstyled" style="font-family: monospace; font-size: 12px; margin-top: 6px;">
                                        <li><code>${asset_tag}</code></li>
                                        <li><code>${asset_name}</code></li>
                                        <li><code>${asset_serial}</code></li>
                                        <li><code>${asset_model}</code></li>
                                        <li><code>${asset_category}</code></li>
                                        <li><code>${asset_manufacturer}</code></li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <strong>Data / Listas</strong>
                                    <ul class="list-unstyled" style="font-family: monospace; font-size: 12px; margin-top: 6px;">
                                        <li><code>${date_today}</code></li>
                                        <li><code>${date_today_long}</code></li>
                                        <li><code>${accessories}</code></li>
                                        <li><code>${acc_name}</code></li>
                                        <li><code>${acc_serial}</code></li>
                                        <li><code>${acc_category}</code></li>
                                        <li><code>${acc_qty}</code></li>
                                        <li><code>${/accessories}</code></li>
                                        <li><code>${components}</code></li>
                                        <li><code>${comp_name}</code></li>
                                        <li><code>${comp_serial}</code></li>
                                        <li><code>${comp_category}</code></li>
                                        <li><code>${comp_qty}</code></li>
                                        <li><code>${/components}</code></li>
                                        <li><code>${user_assets}</code></li>
                                        <li><code>${ua_tag}</code></li>
                                        <li><code>${ua_serial}</code></li>
                                        <li><code>${ua_model}</code></li>
                                        <li><code>${ua_category}</code></li>
                                        <li><code>${/user_assets}</code></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

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