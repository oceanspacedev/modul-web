@extends('layout.main_template')

@php($hasAny = collect($content)->contains(fn ($v) => $v !== '' && $v !== []))

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$draft->title" :subtitle="$draft->category->label().($draft->user ? ' · '.$draft->user->nama_lengkap : '')"
            :breadcrumbs="['Dokumen Builder' => route('document-builder.index'), 'Pratinjau' => null]">
            @include('document-builder._request-badge')
            <x-slot:actions>
                <a href="{{ route('document-builder.edit', $draft) }}" class="btn btn-default"><i class="fas fa-pen"></i> Edit</a>
                <a href="{{ route('document-builder.word', $draft) }}" class="btn btn-default"><i class="far fa-file-word"></i> Word</a>
                <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Cetak / PDF</button>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        @unless ($hasAny)
            <div class="alert alert-light border no-print">Dokumen masih kosong. <a href="{{ route('document-builder.edit', $draft) }}">Isi sekarang</a> atau <a href="{{ route('document-builder.answers', $draft) }}">susun dengan AI</a>.</div>
        @endunless

        <article class="builder-paper">
            @if ($draft->company)
                <div class="builder-paper-company">{{ $draft->company }}</div>
            @endif
            <div class="builder-paper-kind">{{ $draft->category->name }}</div>
            <h1 class="builder-paper-title">{{ $draft->title }}</h1>

            @if ($identity)
                <table class="builder-paper-identity">
                    @foreach ($identity as $label => $value)
                        <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
                    @endforeach
                </table>
            @endif

            @php($number = 0)
            @foreach ($template['sections'] as $section)
                @php($value = $content[$section['key']])
                @continue($value === '' || $value === [])
                <section class="builder-paper-section">
                    <h2>{{ ++$number }}. {{ $section['label'] }}</h2>
                    @if ($section['type'] === 'text')
                        @foreach (preg_split('/\n{2,}/', $value) as $paragraph)
                            <p>{!! nl2br(e($paragraph)) !!}</p>
                        @endforeach
                    @elseif ($section['type'] === 'list')
                        <ul>
                            @foreach ($value as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    @else
                        <{{ $section['type'] === 'steps' ? 'ol' : 'ul' }} class="builder-paper-items">
                            @foreach ($value as $item)
                                <li>
                                    @if ($item['title'] !== '')<strong>{{ $item['title'] }}</strong>@if ($item['text'] !== ''): @endif @endif
                                    {!! nl2br(e($item['text'])) !!}
                                </li>
                            @endforeach
                        </{{ $section['type'] === 'steps' ? 'ol' : 'ul' }}>
                    @endif
                </section>
            @endforeach
        </article>
    </div>
</section>

<style>
    .builder-paper { background: #fff; max-width: 820px; margin: 0 auto 2rem; padding: 3rem 3.5rem; box-shadow: 0 1px 6px rgba(0, 0, 0, .12); color: #212529; font-size: .95rem; line-height: 1.6; }
    .builder-paper-company { text-align: center; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
    .builder-paper-kind { text-align: center; font-weight: 700; text-transform: uppercase; font-size: 1.05rem; }
    .builder-paper-title { text-align: center; font-size: 1.3rem; font-weight: 700; margin: .25rem 0 1.25rem; }
    .builder-paper-identity { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: .88rem; }
    .builder-paper-identity th, .builder-paper-identity td { border: 1px solid #343a40; padding: .3rem .6rem; vertical-align: top; }
    .builder-paper-identity th { width: 32%; font-weight: 600; }
    .builder-paper-section { margin-bottom: 1.1rem; page-break-inside: auto; }
    .builder-paper-section h2 { font-size: 1.02rem; font-weight: 700; margin: 0 0 .4rem; page-break-after: avoid; }
    .builder-paper-section p { margin: 0 0 .5rem; text-align: justify; }
    .builder-paper-section ul, .builder-paper-section ol { padding-left: 1.4rem; margin-bottom: .5rem; }
    .builder-paper-items li { margin-bottom: .35rem; text-align: justify; }
    @media (max-width: 576px) { .builder-paper { padding: 1.5rem 1.25rem; } }
    @media print {
        @page { size: A4; margin: 18mm 16mm; }
        .main-sidebar, .main-header, .main-footer, .content-header, .no-print, .flash-stack { display: none !important; }
        .content-wrapper { margin-left: 0 !important; background: #fff !important; padding: 0 !important; }
        .builder-paper { box-shadow: none; padding: 0; max-width: none; margin: 0; font-size: 11pt; }
    }
</style>
@endsection
