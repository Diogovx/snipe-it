<div style="padding: 15px;">

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($templates->isEmpty())
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            {{ trans('general.no_templates') }}
            <strong>{{ $asset->model?->category?->name ?? 'deste ativo' }}</strong>.
        </div>
    @else
        {{-- Info do usuário --}}
        <div class="callout callout-info" style="margin-bottom: 15px;">
            <p style="margin: 0;">
                <i class="fa-solid fa-user fa-fw"></i>
                <strong>{{ trans('general.user') }}:</strong>
                {{ $assignedUser
                    ? $assignedUser->first_name . ' ' . $assignedUser->last_name
                    : 'Nenhum usuário vinculado' }}
            </p>
            <p style="margin: 4px 0 0;">
                <i class="fa-solid fa-tag fa-fw"></i>
                <strong>{{ trans('general.asset') }}:</strong> {{ $asset->asset_tag }}
                — {{ $asset->model?->name ?? '' }}
            </p>
        </div>

        <form action="{{ route('terms.generate', $asset->id) }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="term_template_id">
                    <i class="fa-solid fa-file-word fa-fw"></i>
                    {{ trans('general.template_type') }}
                </label>
                <select name="term_template_id" id="term_template_id" class="form-control" style="cursor: pointer;">
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}" style="cursor: pointer;">{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="modal-footer" style="padding: 10px 0 0; margin: 0; border-top: 1px solid #e5e5e5;">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    {{ trans('general.cancel') }}
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-download"></i> {{ trans('general.gen_download') }}
                </button>
            </div>
        </form>
    @endif

</div>