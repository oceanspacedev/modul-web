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
        <div class="mb-3 d-flex align-items-center">
            <a href="{{ $module->url('show') }}" class="btn btn-default btn-sm mr-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">{{ $video->title }}</h1>
                <span class="text-muted small">{{ $module->title }} &middot; materi {{ $position + 1 }} dari {{ $items->count() }}</span>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid" style="max-width: 1000px;">
        @include('learning_module.partials.video-player', [
            'video' => $video,
            'completeUrl' => $module->url('items.complete', ['item' => $item]),
            'done' => $done,
        ])

        <div class="d-flex justify-content-end mb-3" style="gap: 8px;">
            <span class="text-success font-weight-bold align-self-center d-none" id="posttestOpenNote">Semua materi selesai, posttest sudah terbuka.</span>
            @if($next)
                <a href="{{ $next->isVideo() ? $module->url('items.show', ['item' => $next]) : $module->url('show') }}" class="btn btn-default">Materi berikutnya</a>
            @endif
            <a href="{{ $module->url('show') }}" class="btn btn-primary {{ $done ? '' : 'd-none' }}" id="btnBack">Kembali ke Modul</a>
        </div>

        @if($video->description)
            <div class="card mb-5"><div class="card-body text-muted" style="white-space: pre-line;">{{ $video->description }}</div></div>
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
