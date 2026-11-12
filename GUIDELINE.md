# GUIDELINE

## Drip Engine

Drip templates are compiled to PHP and rendered by the framework. Keep template files in the established locations:

- Module views: `modules/{Module}/Views/{name}.view.php`, rendered with `view('{name}.drip', $data)`.
- Shared layouts: `system/Templates/layouts/{name}.layout.php`.
- Shared components: `system/Templates/components/{name}.component.php`.

### Do

- Put `@extends` at the start of a layout-based view, then define content using matching `@section`/`@endsection` pairs.
- Keep page markup inside a section. Use Drip comments (`{{-- ... --}}`) for template-only comments.
- Use `{{ $value }}` for normal text output. It is escaped by default; keep titles, descriptions, labels, and user-provided data escaped.
- Use context-specific helpers for URLs, attributes, and JavaScript. HTML escaping does not make a value safe in every output context.
- Use `{!! $html !!}` only for trusted or explicitly sanitized HTML. The `/test` feature icons are static SVG strings defined in the controller, so they can be rendered raw; do not apply this to user input.
- Write directives as directives, not inside an echo. For example, put `@class([...])` directly in an HTML tag.
- Match every opening directive with its closing directive, and use only directives documented by Drip.
- Use `@verbatim ... @endverbatim` when a code sample must display Drip syntax literally.
- Put compiled cache under the private `Registry::get('cache')` path. `cache_url` is `null`; compiled PHP must not be exposed through uploads or another public URL.
- Add or update a focused render test when changing compiler behavior, then verify the affected page in WordPress.

### Do Not

- Do not print SVG or other markup with escaped echo. `{{ $feature['icon'] }}` turns `<svg>` into visible text; use raw echo only when the markup is trusted or sanitized.
- Do not use raw echo for request, database, or other untrusted content, such as `{!! $userInput !!}`.
- Do not nest a directive inside escaped echo, such as `{{ @class(['active' => $active]) }}`. Use `<div @class(['active' => $active])>`.
- Do not put HTML output outside the sections of a layout-based view. In particular, avoid raw HTML comments between `@extends(...)` and section directives; use a Drip comment or place the comment inside a section.
- Do not duplicate a layout prefix, for example `@extends('layouts.layouts.test')`. Use `@extends('layouts.test')` or `@extends('test')`.
- Do not assume full Laravel Blade compatibility or use unsupported directives/features without checking Drip's implementation and tests.
- Do not store compiled PHP in the plugin source tree, `uploads`, or any publicly addressable cache directory.

### Recommended layout structure

```php
@extends('layouts.test')

@section('title')
{{ $title }}
@endsection

@section('content')
<h1>{{ $title }}</h1>
@foreach($features as $feature)
    <article @class(['feature-card', 'is-first' => $loop->first])>
        <div class="feature-icon">{!! $feature['icon'] !!}</div>
        <h2>{{ $feature['title'] }}</h2>
        <p>{{ $feature['description'] }}</p>
    </article>
@endforeach
@endsection
```
