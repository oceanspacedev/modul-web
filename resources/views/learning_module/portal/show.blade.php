@extends('layout.main_template')

@php
    $stage = $progress->stage();
    $best = $progress->bestPosttest();
    $step = 0;
    $limitLabel = fn (?int $remaining) => $remaining === null ? 'boleh diulang tanpa batas' : ($remaining === 0 ? 'batas percobaan habis' : 'sisa '.$remaining.' percobaan');
    $backUrl = $module->isOwned() ? route('document.index') : route('learning.index');
    $completed = $progress->completedItemCount();
    $total = $progress->totalItemCount();
    $percent = $total ? round($completed / $total * 100) : 0;
    $stepBadge = fn (string $name) => $stage === $name ? 'badge-primary' : 'badge-secondary';
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="($module->isOwned() ? 'Kuis Dokumen: ' : '').$module->title" :subtitle="$module->questions_count.' soal · nilai minimal lulus '.$module->passing_score" :eyebrow="$module->isOwned() ? 'Dokumen' : 'Modul Saya'">
            <x-slot:actions>
                <a href="{{ $backUrl }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid" style="max-width: 1000px;">
        @include('learning_module.partials.alerts')

        @if($progress->needsPretest())
            @include('learning_module.partials.manager-preview')
        @endif

        @if($module->description)
            <p class="text-muted mb-4" style="white-space: pre-line;">{{ $module->description }}</p>
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Langkah Belajar</h3>
                <div class="card-tools d-flex align-items-center gap-2 small text-muted">
                    <span>{{ $completed }}/{{ $total }} materi selesai</span>
                    <div class="progress" style="width: 120px;">
                        <div class="progress-bar" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>

            {{-- PRETEST --}}
            @if($module->has_pretest)
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge {{ $stepBadge('pretest') }}">Langkah {{ ++$step }}</span>
                        <h4 class="section-title">Pretest</h4>
                    </div>
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
                <div class="d-flex flex-wrap gap-2">
                    @if($progress->pretest)
                        <a href="{{ $module->url('attempt', ['attempt' => $progress->pretests->last()]) }}" class="btn btn-default btn-sm">Lihat hasil</a>
                    @endif
                    @if($progress->canTakePretest())
                        <a href="{{ $module->url('quiz', ['phase' => 'pretest']) }}" class="btn {{ $progress->pretest ? 'btn-default' : 'btn-primary' }} btn-sm">
                            {{ $progress->pretest ? 'Ulangi Pretest' : 'Mulai Pretest' }}
                        </a>
                    @endif
                </div>
            </div>
            @endif

            {{-- MATERI --}}
            <div class="{{ $module->has_pretest ? 'border-top' : '' }}">
                <div class="card-body d-flex justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge {{ $stepBadge('materi') }}">Langkah {{ ++$step }}</span>
                        <h4 class="section-title">Materi</h4>
                    </div>
                    <span class="text-muted small">{{ $completed }}/{{ $total }} selesai</span>
                </div>
                @if($progress->needsPretest())
                    <div class="card-body pt-0 text-muted small">
                        <i class="fas fa-lock mr-1"></i> Materi terbuka setelah Anda mengerjakan pretest.
                    </div>
                @else
                    <ul class="list-group list-group-flush border-top">
                        @foreach($module->availableItems() as $index => $item)
                            @php $done = $progress->isItemCompleted($item); @endphp
                            <li class="list-group-item d-flex align-items-center flex-wrap gap-2">
                                <i class="fas {{ $done ? 'fa-check-circle text-success' : 'fa-circle text-muted' }}"></i>
                                <i class="fas {{ $item->isVideo() ? 'fa-play-circle' : 'fa-file-alt' }} text-muted"></i>
                                <span class="flex-grow-1 min-w-0">{{ $index + 1 }}. {{ $item->title() }}</span>
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
            <div class="border-top">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="min-w-0">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge {{ $stepBadge('posttest') }}">Langkah {{ ++$step }}</span>
                            <h4 class="section-title">Posttest</h4>
                        </div>
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
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Percobaan</th>
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
                                        <td>Posttest ke-{{ $attempt->attempt_number }}</td>
                                        <td>{{ $attempt->submitted_at->format('d M Y H:i') }}</td>
                                        <td class="text-center">{{ $attempt->correct_answers }}/{{ $attempt->total_questions }}</td>
                                        <td class="text-center font-weight-bold">{{ $attempt->score }}</td>
                                        <td class="text-center {{ $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted') }}">
                                            {{ $diff === null ? '-' : ($diff > 0 ? '+' : '').$diff }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $attempt->is_passed ? 'badge-success' : 'badge-danger' }}">{{ $attempt->is_passed ? 'Lulus' : 'Belum Lulus' }}</span>
                                        </td>
                                        <td class="text-right">
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
