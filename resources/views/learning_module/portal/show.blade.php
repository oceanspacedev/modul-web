@extends('layout.main_template')

@php
    $stage = $progress->stage();
    $best = $progress->bestPosttest();
    $step = 0;
    $limitLabel = fn (?int $remaining) => $remaining === null ? 'boleh diulang tanpa batas' : ($remaining === 0 ? 'batas percobaan habis' : 'sisa '.$remaining.' percobaan');
    $backUrl = $module->isOwned() ? route('document.index') : route('learning.index');
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="mb-3 d-flex align-items-center">
            <a href="{{ $backUrl }}" class="btn btn-default btn-sm mr-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                    {{ $module->isOwned() ? 'Kuis Dokumen: ' : '' }}{{ $module->title }}
                </h1>
                <span class="text-muted small">{{ $module->questions_count }} soal &middot; nilai minimal lulus {{ $module->passing_score }}</span>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')

        @if($progress->needsPretest())
            @include('learning_module.partials.manager-preview')
        @endif

        @if($module->description)
            <p class="text-muted" style="white-space: pre-line;">{{ $module->description }}</p>
        @endif

        {{-- PRETEST --}}
        @if($module->has_pretest)
        <div class="card mb-3 {{ $stage === 'pretest' ? 'border-primary' : '' }}">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center" style="gap: 12px;">
                <div>
                    <span class="badge badge-primary mb-1">Langkah {{ ++$step }}</span>
                    <h5 class="font-weight-bold mb-1">Pretest</h5>
                    @if($progress->pretest)
                        <span class="text-muted small">
                            Nilai awal {{ $progress->pretest->score }} ({{ $progress->pretest->submitted_at->format('d M Y H:i') }})
                            &middot; {{ $limitLabel($progress->remainingAttempts('pretest')) }}
                        </span>
                    @else
                        <span class="text-muted small">
                            Uji pemahaman awal Anda sebelum mempelajari materi.
                            {{ $module->pretest_max_attempts === 1 ? 'Pretest hanya dapat dikerjakan satu kali.' : '' }}
                        </span>
                    @endif
                </div>
                <div class="text-right">
                    @if($progress->pretest)
                        <a href="{{ $module->url('attempt', ['attempt' => $progress->pretests->last()]) }}" class="btn btn-default btn-sm">Lihat hasil</a>
                    @endif
                    @if($progress->canTakePretest())
                        <a href="{{ $module->url('quiz', ['phase' => 'pretest']) }}" class="btn {{ $progress->pretest ? 'btn-outline-primary' : 'btn-primary' }} btn-sm">
                            {{ $progress->pretest ? 'Ulangi Pretest' : 'Mulai Pretest' }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- MATERI --}}
        <div class="card mb-3 {{ $stage === 'materi' ? 'border-primary' : '' }}">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge badge-primary mb-1">Langkah {{ ++$step }}</span>
                    <h5 class="font-weight-bold mb-0">Materi</h5>
                </div>
                <span class="text-muted small">{{ $progress->completedItemCount() }}/{{ $progress->totalItemCount() }} selesai</span>
            </div>
            @if($progress->needsPretest())
                <div class="card-body text-muted small">
                    <i class="fas fa-lock mr-1"></i> Materi terbuka setelah Anda mengerjakan pretest.
                </div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($module->availableItems() as $index => $item)
                        @php $done = $progress->isItemCompleted($item); @endphp
                        <li class="list-group-item d-flex align-items-center flex-wrap" style="gap: 8px;">
                            <i class="fas {{ $done ? 'fa-check-circle text-success' : 'fa-circle text-muted' }}"></i>
                            <i class="fas {{ $item->isVideo() ? 'fa-play-circle text-danger' : 'fa-file-alt text-warning' }}"></i>
                            <span class="flex-grow-1">{{ $index + 1 }}. {{ $item->title() }}</span>
                            @if($item->isVideo())
                                <a href="{{ $module->url('items.show', ['item' => $item]) }}" class="btn btn-sm {{ $done ? 'btn-default' : 'btn-outline-primary' }}">
                                    {{ $done ? 'Tonton Lagi' : 'Tonton Video' }}
                                </a>
                            @else
                                <a href="{{ $module->url('items.open', ['item' => $item]) }}" target="_blank" rel="noopener" class="btn btn-sm {{ $done ? 'btn-default' : 'btn-outline-primary' }} open-document">
                                    {{ $done ? 'Buka Lagi' : 'Buka Dokumen' }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- POSTTEST --}}
        @if($module->has_posttest)
        <div class="card mb-4 {{ $stage === 'posttest' ? 'border-primary' : '' }}">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center" style="gap: 12px;">
                <div>
                    <span class="badge badge-primary mb-1">Langkah {{ ++$step }}</span>
                    <h5 class="font-weight-bold mb-1">Posttest</h5>
                    @if(! $progress->isPosttestUnlocked())
                        <span class="text-muted small"><i class="fas fa-lock mr-1"></i> Terbuka setelah {{ $module->has_pretest ? 'pretest dan ' : '' }}semua materi selesai.</span>
                    @elseif($best)
                        <span class="text-muted small">
                            Nilai terbaik <strong class="text-dark">{{ $best->score }}</strong>
                            @if($progress->hasPassed())
                                &middot; <span class="text-success font-weight-bold">Lulus</span>
                            @else
                                &middot; belum mencapai KKM {{ $module->passing_score }}
                            @endif
                            &middot; {{ $limitLabel($progress->remainingAttempts('posttest')) }}
                        </span>
                    @else
                        <span class="text-muted small">Uji kembali pemahaman Anda setelah mempelajari materi. Semua nilai percobaan tersimpan.</span>
                    @endif
                </div>
                @if($progress->canTakePosttest())
                    <a href="{{ $module->url('quiz', ['phase' => 'posttest']) }}" class="btn btn-primary">
                        {{ $progress->posttests->isEmpty() ? 'Mulai Posttest' : 'Ulangi Posttest' }}
                    </a>
                @endif
            </div>

            @if($progress->posttests->isNotEmpty())
                <div class="table-responsive border-top">
                    <table class="table table-sm mb-0" style="font-size: 0.9rem;">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th class="pl-3">Percobaan</th>
                                <th>Waktu</th>
                                <th class="text-center">Benar</th>
                                <th class="text-center">Nilai</th>
                                <th class="text-center">Perubahan dari Pretest</th>
                                <th class="text-center">Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($progress->posttests->reverse() as $attempt)
                                @php $diff = $progress->pretest ? round($attempt->score - $progress->pretest->score, 2) : null; @endphp
                                <tr>
                                    <td class="pl-3">Posttest ke-{{ $attempt->attempt_number }}</td>
                                    <td>{{ $attempt->submitted_at->format('d M Y H:i') }}</td>
                                    <td class="text-center">{{ $attempt->correct_answers }}/{{ $attempt->total_questions }}</td>
                                    <td class="text-center font-weight-bold">{{ $attempt->score }}</td>
                                    <td class="text-center {{ $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted') }}">
                                        {{ $diff === null ? '-' : ($diff > 0 ? '+' : '').$diff }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $attempt->is_passed ? 'badge-success' : 'badge-danger' }}">{{ $attempt->is_passed ? 'Lulus' : 'Belum Lulus' }}</span>
                                    </td>
                                    <td class="text-right pr-3">
                                        <a href="{{ $module->url('attempt', ['attempt' => $attempt]) }}" class="btn btn-default btn-xs">Lihat</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif
    </div>
</section>
@endsection

@section('footer')
<script>
    // Dokumen dibuka di tab baru; muat ulang halaman ini supaya checklist materi ikut diperbarui.
    $('.open-document').on('click', function () {
        setTimeout(function () { window.location.reload(); }, 1500);
    });
</script>
@endsection
