@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$draft->title" :subtitle="$draft->category->label()"
            :breadcrumbs="['Dokumen Builder' => route('document-builder.index'), 'Edit' => null]">
            @include('document-builder._request-badge')
            <x-slot:actions>
                <a href="{{ route('document-builder.answers', $draft) }}" class="btn btn-default"><i class="fas fa-magic"></i> Susun Ulang</a>
                <a href="{{ route('document-builder.show', $draft) }}" class="btn btn-default"><i class="far fa-eye"></i> Pratinjau</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <form action="{{ route('document-builder.update', $draft) }}" method="POST" id="draftForm">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-lg-8">
                    @foreach ($template['sections'] as $number => $section)
                        @php($key = $section['key'])
                        @php($value = old('content.'.$key, $content[$key]))
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ $number + 1 }}. {{ $section['label'] }}</h3>
                                <div class="card-tools small text-muted d-none d-md-block">{{ $section['hint'] }}</div>
                            </div>
                            <div class="card-body">
                                @if ($section['type'] === 'text')
                                    <textarea class="form-control" name="content[{{ $key }}]" rows="4" maxlength="{{ App\Support\DocumentBuilderContent::MAX_TEXT }}" data-autosize>{{ $value }}</textarea>
                                @elseif ($section['type'] === 'list')
                                    <div data-rows>
                                        @foreach ((array) $value ?: [''] as $point)
                                            @include('document-builder._row-point', ['key' => $key, 'point' => $point])
                                        @endforeach
                                    </div>
                                    <template data-row-template>@include('document-builder._row-point', ['key' => $key, 'point' => ''])</template>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-add-row><i class="fas fa-plus"></i> Tambah Poin</button>
                                @else
                                    @php($labels = $section['item_labels'] ?? ['Judul', 'Uraian'])
                                    <div data-rows data-numbered="{{ $section['type'] === 'steps' ? 1 : 0 }}">
                                        @foreach ((array) $value ?: [['title' => '', 'text' => '']] as $i => $item)
                                            @include('document-builder._row-item', ['key' => $key, 'index' => $i, 'item' => $item, 'labels' => $labels])
                                        @endforeach
                                    </div>
                                    <template data-row-template>@include('document-builder._row-item', ['key' => $key, 'index' => '__INDEX__', 'item' => ['title' => '', 'text' => ''], 'labels' => $labels])</template>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-add-row><i class="fas fa-plus"></i> Tambah {{ $labels[0] }}</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="col-lg-4">
                    <div class="card builder-sticky">
                        <div class="card-header"><h3 class="card-title">Info Dokumen</h3></div>
                        <div class="card-body">
                            @foreach ([
                                ['title', 'Judul', 250, true],
                                ['company', 'Badan Usaha', 150, false],
                                ['department', 'Departemen', 150, false],
                                ['document_number', 'Nomor Dokumen', 60, false],
                                ['revision', 'Revisi', 20, false],
                                ['approved_by', 'Disahkan oleh', 150, false],
                            ] as [$field, $label, $max, $isRequired])
                                <div class="form-group mb-2">
                                    <label for="{{ $field }}" class="small font-weight-bold mb-1">{{ $label }}</label>
                                    <input type="text" class="form-control form-control-sm @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}"
                                        maxlength="{{ $max }}" value="{{ old($field, $draft->{$field}) }}" @required($isRequired)>
                                </div>
                            @endforeach
                            <div class="form-group">
                                <label for="effective_date" class="small font-weight-bold mb-1">Tanggal Berlaku</label>
                                <input type="date" class="form-control form-control-sm @error('effective_date') is-invalid @enderror" id="effective_date" name="effective_date"
                                    value="{{ old('effective_date', $draft->effective_date?->format('Y-m-d')) }}">
                            </div>

                            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Simpan</button>
                            <button type="submit" name="after" value="show" class="btn btn-default btn-block">Simpan &amp; Pratinjau</button>
                            <p class="small text-muted mt-2 mb-0" data-dirty-note hidden>Ada perubahan yang belum disimpan.</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<style>
    .builder-row { display: flex; gap: .5rem; align-items: flex-start; margin-bottom: .5rem; }
    .builder-row-num { flex: 0 0 26px; height: 26px; margin-top: 4px; border-radius: 50%; background: #e9ecef; font-size: .75rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; }
    .builder-row-tools { display: flex; flex-direction: column; gap: 2px; }
    .builder-row-tools .btn { padding: 0 .4rem; line-height: 1.4; }
    [data-numbered="0"] .builder-row-num { display: none; }
    @media (min-width: 992px) { .builder-sticky { position: sticky; top: 1rem; } }
</style>
@endsection

@section('footer')
<script>
    (function () {
        const form = document.getElementById('draftForm');
        let dirty = false;

        function autosize(el) {
            el.style.height = 'auto';
            el.style.height = (el.scrollHeight + 2) + 'px';
        }

        function renumber(rows) {
            [...rows.children].forEach((row, i) => {
                const num = row.querySelector('[data-row-num]');
                if (num) num.textContent = i + 1;
                row.querySelector('[data-move="up"]').disabled = i === 0;
                row.querySelector('[data-move="down"]').disabled = i === rows.children.length - 1;
            });
        }

        document.querySelectorAll('[data-rows]').forEach(renumber);
        document.querySelectorAll('[data-autosize]').forEach(autosize);

        form.addEventListener('input', event => {
            if (event.target.matches('[data-autosize]')) autosize(event.target);
            if (!dirty) { dirty = true; document.querySelector('[data-dirty-note]').hidden = false; }
        });

        form.addEventListener('click', event => {
            const button = event.target.closest('button[type="button"]');
            if (!button) return;
            const body = button.closest('.card-body');

            if (button.hasAttribute('data-add-row')) {
                const rows = body.querySelector('[data-rows]');
                const html = body.querySelector('[data-row-template]').innerHTML.replaceAll('__INDEX__', 'n' + Date.now());
                rows.insertAdjacentHTML('beforeend', html);
                renumber(rows);
                rows.lastElementChild.querySelectorAll('[data-autosize]').forEach(autosize);
                rows.lastElementChild.querySelector('input, textarea').focus();
            } else {
                const row = button.closest('[data-row]');
                const rows = row.parentNode;
                if (button.dataset.move === 'up' && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
                if (button.dataset.move === 'down' && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
                if (button.hasAttribute('data-remove')) row.remove();
                renumber(rows);
            }
            if (!dirty) { dirty = true; document.querySelector('[data-dirty-note]').hidden = false; }
        });

        form.addEventListener('submit', () => { dirty = false; });
        window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    })();
</script>
@endsection
