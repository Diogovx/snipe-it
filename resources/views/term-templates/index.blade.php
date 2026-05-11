@extends('layouts/default')

@section('title')
    {{ trans('general.terms_of_responsibility') }}
@stop

@section('header_right')
    <a href="{{ route('term-templates.create') }}" class="btn btn-primary pull-right">
        <i class="fa fa-plus"></i> {{ trans('general.new_template') }}
    </a>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">

        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">{{ trans('general.registered_templates') }}</h3>
            </div>
            <div class="box-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{ trans('general.name') }}</th>
                            <th>{{ trans('general.categories') }}</th>
                            <th>{{ trans('general.file') }}</th>
                            <th>{{ trans('general.status') }}</th>
                            <th>{{ trans('general.generated') }}</th>
                            <th>{{ trans('general.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates as $template)
                        <tr>
                            <td>{{ $template->name }}</td>
                            <td>
                                @if($template->allowed_categories)
                                    @foreach($template->allowed_categories as $cat)
                                        <span class="label label-default">{{ $cat }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">{{ trans('general.all') }}</span>
                                @endif
                            </td>
                            <td>
                                <i class="fa-solid fa-file-word text-primary"></i>
                                {{ $template->file_name }}
                            </td>
                            <td>
                                @if($template->active)
                                    <span class="label label-success">{{ trans('general.active') }}</span>
                                @else
                                    <span class="label label-danger">{{ trans('general.inactive') }}</span>
                                @endif
                            </td>
                            <td>{{ $template->logs()->count() }}</td>
                            <td>
                                {{-- dentro do @forelse, na coluna de ações --}}
                                <a href="{{ route('term-templates.logs', $template->id) }}"
                                    class="btn btn-xs btn-info"
                                    title="Ver logs">
                                    <i class="fa fa-history"></i>
                                </a>
                                
                                <a href="{{ route('term-templates.edit', $template->id) }}"
                                   class="btn btn-xs btn-warning">
                                    <i class="fa fa-pencil"></i>
                                </a>
                                
                                <form action="{{ route('term-templates.destroy', $template->id) }}"
                                      method="POST" style="display:inline;"
                                      onsubmit="return confirm('Remover este template?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-danger">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                {{ trans('general.no_templates') }}.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@stop