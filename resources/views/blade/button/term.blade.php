@props([
    'item' => null,
     'route',
     'wide' => false,
])

@if($item->assigned_to && str_contains((string) $item->assigned_type, 'User'))
    <a href="{{ $route }}"
        class="btn btn-sm bg-primary hidden-print"
        data-toggle="modal"
        data-target="#termModal"
        data-tooltip="true" 
        data-placement="top" 
        data-title={{ trans('general.generate_term') }}
        style="color: white;">
        <i class="fa-solid fa-file-contract"></i>
        @if ($wide == 'true')
            {{ trans('general.generate_term') }}
        @endif                
    </a>
@endif