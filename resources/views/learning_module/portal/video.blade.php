@extends('layout.main_template')

@php
    $done = $progress->isItemCompleted($item);
    $items = $module->availableItems();
    $position = $items->search(fn ($i) => $i->id === $item->id);
    $next = $position !== false ? $items->get($position + 1) : null;
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$video->title" :subtitle="$module->title.' · materi '.($position + 1).' dari '.$items->count()" eyebrow="Modul Saya / Materi">
            <x-slot:actions>
                <a href="{{ $module->url('show') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.video-player', [
            'video' => $video,
            'completeUrl' => $module->url('items.complete', ['item' => $item]),
            'done' => $done,
        ])

        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-4">
            <span class="text-success small font-weight-bold mr-auto d-none" id="posttestOpenNote"><i class="fas fa-check-circle mr-1"></i> Semua materi selesai, posttest sudah terbuka.</span>
            @if($next)
                <a href="{{ $next->isVideo() ? $module->url('items.show', ['item' => $next]) : $module->url('show') }}" class="btn btn-default">Materi berikutnya <i class="fas fa-arrow-right"></i></a>
            @endif
            <a href="{{ $module->url('show') }}" class="btn btn-primary {{ $done ? '' : 'd-none' }}" id="btnBack">Kembali ke Modul</a>
        </div>

        @if($video->description)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Deskripsi</h3>
                </div>
                <div class="card-body text-muted" style="white-space: pre-line;">{{ $video->description }}</div>
            </div>
        @endif
    </div>
</section>
@endsection

@section('footer')
<script>
document.addEventListener('material-completed', function (event) {
    document.getElementById('btnBack').classList.remove('d-none');
    if (event.detail.posttest_open) {
        document.getElementById('posttestOpenNote').classList.remove('d-none');
    }
});
</script>
@endsection
