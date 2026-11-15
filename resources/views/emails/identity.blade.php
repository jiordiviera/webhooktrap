{{-- Placeholder copy: A03 owns the wording of every template (RD SECURITY copy). --}}
{{ config('app.name') }} · {{ $template }}

@foreach ($params as $key => $value)
{{ $key }}: {{ $value }}
@endforeach
@if ($actionUrl)

{{ $actionUrl }}
@endif
