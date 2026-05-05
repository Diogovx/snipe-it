@extends('layouts/default')

@section('title')
    Logs: {{ $termTemplate->name }}
@stop

@section('header_right')
    <a href="{{ route('term-templates.index') }}" class="btn btn-default pull-right">
        <i class="fa fa-arrow-left"></i> {{ trans('general.back') }}
    </a>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">

        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-history"></i>
                    {{ trans('general.generation_history') }} - {{ $termTemplate->name }}
                </h3>
                <div class="box-tools pull-right">
                    <span class="label label-default">
                        {{ $logs->total() }} {{ trans('general.total') }}
                    </span>
                </div>
            </div>

            <div class="box-body no-padding">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{ trans('general.date') }}</th>
                            <th>{{ trans('general.asset') }}</th>
                            <th>{{ trans('general.user') }}</th>
                            <th>{{ trans('general.generated_by') }}</th>
                            <th>{{ trans('general.file') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if($log->asset)
                                    <a href="{{ route('hardware.show', $log->asset->id) }}">
                                        {{ $log->asset->asset_tag }}
                                    </a>
                                    <br>
                                    <small class="text-muted">{{ $log->asset->name }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($log->assignedUser)
                                    {{ $log->assignedUser->first_name }}
                                    {{ $log->assignedUser->last_name }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($log->generatedBy)
                                    {{ $log->generatedBy->first_name }}
                                    {{ $log->generatedBy->last_name }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">
                                    <i class="fa fa-file-word-o"></i>
                                    {{ $log->generated_file_name }}
                                </small>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                {{ trans('general.no_generation_template') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
            <div class="box-footer">
                {{ $logs->links() }}
            </div>
            @endif
        </div>

    </div>
</div>
@stop