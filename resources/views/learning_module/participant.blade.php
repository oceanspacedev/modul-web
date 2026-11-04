@extends('layout.main_template')

@php
    $user = $progress->user;
    $attempts = $progress->pretests->merge($progress->posttests)->reverse();
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="mb-3 d-flex align-items-center">
            <a href="{{ route('learning-module.show', $module) }}" class="btn btn-default btn-sm mr-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">{{ $user->nama_lengkap }}</h1>
                <span class="text-muted small">
                    {{ $user->divisi->name ?? '-' }} &middot; {{ $user->jabatan->name ?? '-' }} &middot; {{ $module->title }}
                </span>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-6 mb-3">
                <div class="card h-100 mb-0"><div class="card-body py-3">
                    <span class="text-muted small d-block">Pretest</span>
                    <strong style="font-size: 1.4rem;">{{ $progress->pretest?->score ?? '-' }}</strong>
                </div></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card h-100 mb-0"><div class="card-body py-3">
                    <span class="text-muted small d-block">Posttest terbaik ({{ $progress->posttests->count() }}x mencoba)</span>
                    <strong style="font-size: 1.4rem;">{{ $progress->bestPosttest()?->score ?? '-' }}</strong>
                </div></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card h-100 mb-0"><div class="card-body py-3">
                    <span class="text-muted small d-block">Materi selesai</span>
                    <strong style="font-size: 1.4rem;">{{ $progress->completedItemCount() }}/{{ $progress->totalItemCount() }}</strong>
                </div></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card h-100 mb-0"><div class="card-body py-3">
                    <span class="text-muted small d-block">Status</span>
                    <strong style="font-size: 1.4rem;">{{ $progress->statusLabel() }}</strong>
                </div></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header py-3">
                <h3 class="card-title font-weight-bold m-0">Riwayat Percobaan</h3>
            </div>
            <div class="card-body">
                @forelse($attempts as $attempt)
                    <div class="border rounded mb-3">
                        <a class="d-flex justify-content-between align-items-center p-3 text-dark" data-toggle="collapse" href="#attempt_{{ $attempt->id }}" role="button">
                            <span>
                                <strong>{{ ($attempt->isPretest() ? 'Pretest' : 'Posttest').' ke-'.$attempt->attempt_number }}</strong>
                                <span class="text-muted small ml-2">{{ $attempt->submitted_at->format('d M Y H:i') }}</span>
                            </span>
                            <span>
                                <span class="mr-2">{{ $attempt->correct_answers }}/{{ $attempt->total_questions }} benar</span>
                                <span class="badge {{ $attempt->is_passed ? 'badge-success' : 'badge-danger' }} px-2 py-1" style="font-size: 0.9rem;">{{ $attempt->score }}</span>
                            </span>
                        </a>
                        <div class="collapse border-top p-3" id="attempt_{{ $attempt->id }}">
                            @include('learning_module.partials.attempt-review', ['attempt' => $attempt, 'showKey' => true])
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Peserta belum mengerjakan pretest/posttest.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection
