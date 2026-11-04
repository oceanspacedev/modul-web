@extends('layout.main_template')

@php
    $diff = ! $attempt->isPretest() && $progress->pretest ? round($attempt->score - $progress->pretest->score, 2) : null;
    $backUrl = $module->participantUrl();
    $backLabel = $module->isOwnedByVideo() ? 'Video' : ($module->isOwned() ? 'Dokumen' : 'Modul');
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
                    Hasil {{ $attempt->isPretest() ? 'Pretest' : 'Posttest' }} ke-{{ $attempt->attempt_number }}
                </h1>
                <span class="text-muted small">{{ $module->title }} &middot; {{ $attempt->submitted_at->format('d M Y H:i') }}</span>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid" style="max-width: 900px;">
        @include('learning_module.partials.alerts')

        <div class="card mb-3">
            <div class="card-body text-center py-4">
                <span class="text-muted d-block mb-1">Nilai Anda</span>
                <strong class="d-block" style="font-size: 3rem; line-height: 1;">{{ $attempt->score }}</strong>
                <span class="text-muted d-block mt-2">{{ $attempt->correct_answers }} dari {{ $attempt->total_questions }} soal benar</span>

                @unless($attempt->isPretest())
                    <div class="mt-3">
                        @if($attempt->is_passed)
                            <span class="badge badge-success px-3 py-2" style="font-size: 0.95rem;">Lulus (KKM {{ $module->passing_score }})</span>
                        @else
                            <span class="badge badge-danger px-3 py-2" style="font-size: 0.95rem;">Belum mencapai KKM {{ $module->passing_score }}</span>
                        @endif
                    </div>
                    @if($diff !== null)
                        <p class="mt-3 mb-0 {{ $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted') }}">
                            {{ $diff > 0 ? 'Naik '.$diff : ($diff < 0 ? 'Turun '.abs($diff) : 'Sama') }} poin dibanding pretest ({{ $progress->pretest->score }})
                        </p>
                    @endif
                @endunless
            </div>
            <div class="card-footer bg-white text-center">
                @if($attempt->isPretest())
                    <a href="{{ $backUrl }}" class="btn btn-primary">{{ $module->isOwnedByVideo() ? 'Lanjut Tonton Video' : 'Lanjut Pelajari Materi' }}</a>
                @else
                    <a href="{{ $backUrl }}" class="btn btn-default mr-1">Kembali ke {{ $backLabel }}</a>
                    @if($progress->canTakePosttest())
                        <a href="{{ route('learning.quiz', [$module, 'posttest']) }}" class="btn btn-primary">Ulangi Posttest</a>
                    @endif
                @endif
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-header py-3">
                <h3 class="card-title font-weight-bold m-0">Rincian Jawaban</h3>
            </div>
            <div class="card-body">
                @include('learning_module.partials.attempt-review', ['attempt' => $attempt, 'showKey' => false])
            </div>
        </div>
    </div>
</section>
@endsection
