<?php
/**
 * Welcome Index View (Drip Template)
 * File: index.view.php
 *
 * Demonstrates Drip template engine features:
 * - Layout inheritance (@extends, @section, @yield)
 * - Components (<x-alert>)
 * - Stacks (@push, @stack)
 * - Built-in directives (@csrf, @auth, @can, @trans)
 * - Loop metadata ($loop)
 * - Conditional attributes (@class)
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
@extends('layouts.base')

@section('title', '{{ esc_html__('Welcome to RadykFW', 'radykfw') }}')

@push('styles')
<style>
    .welcome-hero { min-height: 80vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 60px 24px; }
    .welcome-badge { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50px; font-size: 14px; font-weight: 600; color: white; margin-bottom: 24px; }
    .welcome h1 { font-size: clamp(32px, 5vw, 56px); font-weight: 800; line-height: 1.1; margin-bottom: 16px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .welcome p { font-size: clamp(16px, 2vw, 20px); color: var(--text-muted, #64748b); margin-bottom: 32px; max-width: 600px; margin-left: auto; margin-right: auto; }
    .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-top: 40px; }
    .feature-card { background: var(--card-bg, #fff); border: 1px solid var(--border, #e2e8f0); border-radius: 16px; padding: 32px; transition: all 0.3s ease; }
    .feature-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
    .feature-icon { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
    .feature-icon svg { width: 24px; height: 24px; stroke: white; }
    .feature-card h3 { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
    .feature-card p { color: var(--text-muted, #64748b); font-size: 14px; line-height: 1.6; }
    .radyk-alert { padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 12px; }
    .radyk-alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
    .radyk-alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .radyk-alert-content { flex: 1; }
    .demo-section { margin-top: 40px; padding-top: 40px; border-top: 1px solid var(--border, #e2e8f0); }
    .demo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
    .demo-card { background: var(--card-bg, #fff); border: 1px solid var(--border, #e2e8f0); border-radius: 12px; padding: 24px; }
    .demo-card h4 { font-size: 16px; font-weight: 600; margin-bottom: 12px; }
    .code-block { background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 13px; overflow-x: auto; margin: 12px 0; }
    .code-block .keyword { color: #569cd6; }
    .code-block .string { color: #ce9178; }
    .code-block .comment { color: #6a9955; }
</style>
@endpush

@section('content')
<div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 24px;">
    <section class="welcome-hero" aria-labelledby="welcome-title">
        <div class="welcome-badge">RadykFW HMVC Framework</div>
        <h1 id="welcome-title">{{ esc_html__('Welcome to RadykFW', 'radykfw') }}</h1>
        <p>{{ esc_html__('A powerful Hierarchical MVC framework for WordPress with custom routing and Drip template engine', 'radykfw') }}</p>
        
        {{-- Demo: @auth / @guest directives --}}
        @auth
        <x-alert type="success">{{ esc_html__('You are logged in!', 'radykfw') }}</x-alert>
        @elseauth
        <x-alert type="info">{{ esc_html__('Welcome, guest! Log in to see more features.', 'radykfw') }}</x-alert>
        @endauth
    </section>

    <section class="features" id="features" aria-labelledby="features-title">
        <div class="section-header" style="text-align: center; margin-bottom: 40px;">
            <span class="section-tag" style="display: inline-block; padding: 8px 16px; background: var(--accent, #eef2ff); color: #667eea; border-radius: 50px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px;">{{ esc_html__('Features', 'radykfw') }}</span>
            <h2 id="features-title" style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 700; margin-bottom: 16px;">{{ esc_html__('Everything you need to build', 'radykfw') }}</h2>
            <p style="font-size: 18px; color: var(--text-muted, #64748b); max-width: 600px; margin: 0 auto;">{{ esc_html__('Powerful features for modern WordPress development', 'radykfw') }}</p>
        </div>

        <div class="features-grid">
            {{-- Demo: @foreach with $loop metadata --}}
            @foreach($features as $feature)
            <article class="feature-card" {{ @class(['feature-card', 'is-first' => $loop->first, 'is-last' => $loop->last]) }}>
                <div class="feature-icon">
                    {{ $feature['icon'] }}
                </div>
                <h3>{{ $feature['title'] }}</h3>
                <p>{{ $feature['description'] }}</p>
                {{-- Demo: $loop metadata display --}}
                <small style="color: var(--text-muted, #64748b); font-size: 12px;">
                    Item {{ $loop->iteration }} of {{ $loop->count }}{{ $loop->first ? ' (first)' : '' }}{{ $loop->last ? ' (last)' : '' }}
                </small>
            </article>
            @endforeach
        </div>
    </section>

    <section class="demo-section" aria-labelledby="demo-title">
        <h3 id="demo-title" style="font-size: 24px; font-weight: 700; margin-bottom: 24px; text-align: center;">{{ esc_html__('Drip Engine Demo', 'radykfw') }}</h3>
        <div class="demo-grid">
            <div class="demo-card">
                <h4>{{ esc_html__('Layout Inheritance', 'radykfw') }}</h4>
                <p>{{ esc_html__('This page extends <code>layouts.base</code> via <code>@extends</code> and defines sections with <code>@section</code>.', 'radykfw') }}</p>
                <div class="code-block">
                    <span class="keyword">@extends</span>('layouts.base')<br>
                    <span class="keyword">@section</span>('content')<br>
                    &nbsp;&nbsp;... content ...<br>
                    <span class="keyword">@endsection</span>
                </div>
            </div>

            <div class="demo-card">
                <h4>{{ esc_html__('Components & Slots', 'radykfw') }}</h4>
                <p>{{ esc_html__('Reusable <code><x-alert></code> component from <code>system/Templates/components/</code> with slot content.', 'radykfw') }}</p>
                <div class="code-block">
                    <span class="keyword"><x-alert</span> type="info"<span class="keyword">></span><br>
                    &nbsp;&nbsp;Message here<br>
                    <span class="keyword"></x-alert></span>
                </div>
            </div>

            <div class="demo-card">
                <h4>{{ esc_html__('Stacks', 'radykfw') }}</h4>
                <p>{{ esc_html__('Push styles/scripts to named stacks rendered in layout via <code>@stack</code>.', 'radykfw') }}</p>
                <div class="code-block">
                    <span class="keyword">@push</span>('styles')<br>
                    &nbsp;&nbsp;<span class="keyword"><style></span>...<span class="keyword"></style></span><br>
                    <span class="keyword">@endpush</span>
                </div>
            </div>

            <div class="demo-card">
                <h4>{{ esc_html__('Built-in WP Directives', 'radykfw') }}</h4>
                <p>{{ esc_html__('<code>@csrf</code>, <code>@auth</code>, <code>@can</code>, <code>@trans</code>, <code>@role</code> map to WP APIs.', 'radykfw') }}</p>
                <div class="code-block">
                    <span class="keyword">@csrf</span><br>
                    <span class="keyword">@auth</span> ... <span class="keyword">@endauth</span><br>
                    <span class="keyword">@can</span>('manage_options') ... <span class="keyword">@endcan</span><br>
                    <span class="keyword">@trans</span>('key', ['param' => $val])
                </div>
            </div>

            <div class="demo-card">
                <h4>{{ esc_html__('Loop Metadata', 'radykfw') }}</h4>
                <p>{{ esc_html__('<code>$loop</code> provides index, iteration, first, last, count, depth, parent.', 'radykfw') }}</p>
                <div class="code-block">
                    <span class="keyword">@foreach</span>($items as $item)<br>
                    &nbsp;&nbsp;{{ $loop->iteration }} / {{ $loop->count }}<br>
                    <span class="keyword">@endforeach</span>
                </div>
            </div>

            <div class="demo-card">
                <h4>{{ esc_html__('Conditional Attributes', 'radykfw') }}</h4>
                <p>{{ esc_html__('<code>@class</code>, <code>@style</code>, <code>@checked</code>, <code>@selected</code> for dynamic attributes.', 'radykfw') }}</p>
                <div class="code-block">
                    <span class="keyword"><div</span> {{ @class(['base', 'active' => $cond]) }} <span class="keyword">></span>
                </div>
            </div>
        </div>
    </section>

    {{-- Demo: @once directive --}}
    @once
    <script>
        console.log('RadykFW Drip template loaded once per render cycle');
    </script>
    @endonce
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dismissible alert handler
        document.querySelectorAll('.radyk-alert-dismiss').forEach(function(btn) {
            btn.addEventListener('click', function() {
                this.closest('.radyk-alert').style.display = 'none';
            });
        });
    });
</script>
@endpush