@php
    $cell = fn ($v) => is_float($v) ? number_format($v, 2) : $v;
    $numeric = fn ($v) => is_int($v) || is_float($v);
@endphp
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
    h1 { font-size: 15px; margin: 0; } .muted { color: #666; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background: #eee; text-align: left; } th, td { border: 1px solid #ccc; padding: 3px 4px; }
    .right { text-align: right; } tr.total td { font-weight: bold; background: #f7f7f7; }
</style></head>
<body>
    <h1>{{ $company?->name ?? config('app.name') }}</h1>
    <h2 style="margin:4px 0">{{ $title }}</h2>
    <div class="muted">
        @foreach ($filters as $label => $value){{ $label }}: {{ $value }} &nbsp;@endforeach
        · Generated {{ now()->format('Y-m-d H:i') }} by {{ $generatedBy }}
    </div>
    <table>
        <thead><tr>@foreach ($headings as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($rows as $row)<tr>@foreach ($row as $v)<td class="{{ $numeric($v) ? 'right' : '' }}">{{ $cell($v) }}</td>@endforeach</tr>@endforeach
            @if ($totals)<tr class="total">@foreach ($totals as $v)<td class="{{ $numeric($v) ? 'right' : '' }}">{{ $cell($v) }}</td>@endforeach</tr>@endif
        </tbody>
    </table>
</body></html>
