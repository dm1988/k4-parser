@props([
    'value',
    'type',
    'class',
])

@if ($type === \App\Enums\RouteTokenType::SPEED)
    <span class="{{ $class }}">{{ $value }}</span>
@elseif ($type === \App\Enums\RouteTokenType::AIRWAY)
    <span class="{{ $class }}">{{ $value }}</span>
@elseif ($type === \App\Enums\RouteTokenType::DIRECT)
    <span class="{{ $class }}">{{ $value }}</span>
@else
    <span class="{{ $class }}">{{ $value }}</span>
@endif
