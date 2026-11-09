@extends('layout.main_template')

@php
    $user = $progress->user;
    $attempts = $progress->pretests->merge($progress->posttests)->reverse();
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$user->nama_lengkap" :subtitle="($user->divisi->name ?? '-').' · '.($user->jabatan->name ?? '-').' · '.$module->title" eyebrow="Modul Belajar / Riwayat Peserta">
            <x-slot:actions>
                <a href="{{ $module->url('results') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-6 mb-4">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-clipboard-list"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Pretest</p>
                        <p class="stat-tile-value">{{ $progress->pretest?->score ?? '-' }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-chart-line"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Posttest terbaik ({{ $progress->posttests->count() }}x mencoba)</p>
                        <p class="stat-tile-value">{{ $progress->bestPosttest()?->score ?? '-' }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-photo-video"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Materi selesai</p>
                        <p class="stat-tile-value">{{ $progress->completedItemCount() }}/{{ $progress->totalItemCount() }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-flag"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Status</p>
                        <p class="stat-tile-value">{{ $progress->statusLabel() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Riwayat Percobaan</h3>
            </div>
            <div class="card-body">
                @forelse($attempts as $attempt)
                    <div class="border rounded mb-3">
                        <a class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 text-dark" data-toggle="collapse" href="#attempt_{{ $attempt->id }}" role="button">
                            <span>
                                <strong>{{ ($attempt->isPretest() ? 'Pretest' : 'Posttest').' ke-'.$attempt->attempt_number }}</strong>
                                <span class="text-muted small ml-2">{{ $attempt->submitted_at->format('d M Y H:i') }}</span>
                            </span>
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="text-muted small">{{ $attempt->correct_answers }}/{{ $attempt->total_questions }} benar</span>
                                <span class="badge {{ $attempt->is_passed ? 'badge-success' : 'badge-danger' }}">{{ $attempt->score }}</span>
                                <i class="fas fa-chevron-down text-muted small"></i>
                            </span>
                        </a>
                        <div class="collapse border-top p-3" id="attempt_{{ $attempt->id }}">
                            @include('learning_module.partials.attempt-review', ['attempt' => $attempt, 'showKey' => true])
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="fa-clipboard-list" title="Belum ada percobaan" text="Peserta belum mengerjakan pretest/posttest." class="py-4" />
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection
