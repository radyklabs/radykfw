<?php
/**
 * Alert Component
 * File: system/Templates/components/alert.drip
 *
 * Usage: <x-alert type="info" dismissible="true">Message</x-alert>
 * Attributes: type (info|success|warning|error), dismissible (bool)
 * Slot: $slot (string)
 */
if (!defined('ABSPATH')) {
    exit;
}

$type = $type ?? 'info';
$dismissible = $dismissible ?? false;
$classes = ['radyk-alert', "radyk-alert-{$type}"];
if ($dismissible) {
    $classes[] = 'radyk-alert-dismissible';
}
?>
<div class="{{ implode(' ', $classes) }}" role="alert">
    <div class="radyk-alert-content">
        {{ $slot }}
    </div>
    @if($dismissible)
    <button type="button" class="radyk-alert-dismiss" aria-label="{{ esc_attr__('Dismiss', 'radykfw') }}">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>
    @endif
</div>