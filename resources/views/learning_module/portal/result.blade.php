@extends('layout.main_template')

@php
    $diff = ! $attempt->isPretest() && $progress->pretest ? round($attempt->score - $progress->pretest->score, 2) : null;
    $backUrl = $module->participantUrl();
    $backLabel = $module->isOwnedByVideo() ? 'Video' : ($module->isOwned() ? 'Dokumen' : 'Modul');
    $percent = $attempt->total_questions ? round($attempt->correct_answers / $attempt->total_questions * 100) : 0;
@endphp

@section('content')
<style>
    .result-score {
        font-family: var(--font-heading);
        font-size: 3rem;
        font-weight: 600;
        line-height: 1;
        color: var(--gray-950);
    }
</style>

<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="'Hasil '.($attempt->isPretest() ? 'Pretest' : 'Posttest').' ke-'.$attempt->attempt_number" :subtitle="$module->title.' · '.$attempt->submitted_at->format('d M Y H:i')" eyebrow="Modul Saya / Hasil">
            <x-slot:actions>
                <a href="{{ $backUrl }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid" style="max-width: 900px;">
        @include('learning_module.partials.alerts')

        <div class="card">
            <div class="card-body text-center py-5">
                <p class="eyebrow mb-2">Nilai Anda</p>
                <div class="result-score">{{ $attempt->score }}</div>
                <p class="text-muted mt-2 mb-0">{{ $attempt->correct_answers }} dari {{ $attempt->total_questions }} soal benar</p>
                <div class="progress mx-auto mt-3" style="max-width: 240px;">
                    <div class="progress-bar" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                @unless($attempt->isPretest())
                    <div class="mt-3">
                        @if($attempt->is_passed)
                            <span class="badge badge-success">Lulus (KKM {{ $module->passing_score }})</span>
                        @else
                            <span class="badge badge-danger">Belum mencapai KKM {{ $module->passing_score }}</span>
                        @endif
                    </div>
                    @if($diff !== null)
                        <p class="small mt-2 mb-0 {{ $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted') }}">
                            {{ $diff > 0 ? 'Naik '.$diff : ($diff < 0 ? 'Turun '.abs($diff) : 'Sama') }} poin dibanding pretest ({{ $progress->pretest->score }})
                        </p>
                    @endif
                @endunless
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-center gap-2">
                @if($attempt->isPretest())
                    <a href="{{ $backUrl }}" class="btn btn-primary">{{ $module->isOwnedByVideo() ? 'Lanjut Tonton Video' : 'Lanjut Pelajari Materi' }}</a>
                @else
                    <a href="{{ $backUrl }}" class="btn btn-default">Kembali ke {{ $backLabel }}</a>
                    @if($progress->canTakePosttest())
                        <a href="{{ $module->url('quiz', ['phase' => 'posttest']) }}" class="btn btn-primary">Ulangi Posttest</a>
                    @endif
                @endif
            </div>
        </div>

        @if($module->showsAnswers($attempt->phase))
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Rincian Jawaban</h3>
                </div>
                <div class="card-body">
                    @include('learning_module.partials.attempt-review', ['attempt' => $attempt, 'showKey' => true])
                </div>
            </div>
        @else
            <p class="text-muted small text-center">Rincian jawaban tidak ditampilkan untuk {{ $attempt->isPretest() ? 'pretest' : 'posttest' }} ini.</p>
        @endif
    </div>
</section>
@endsection
