@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Modul Saya" subtitle="Ikuti langkah tiap modul: kuis awal bila ada, materi, lalu kuis akhir bila ada." eyebrow="Modul Belajar / Modul Saya" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')

        <div class="row">
            @forelse($modules as $module)
                @php
                    $p = $progress[$module->id];
                    $stage = $p->stage();
                    $best = $p->bestPosttest();
                    $percent = $p->totalItemCount() ? round($p->completedItemCount() / $p->totalItemCount() * 100) : 0;
                @endphp
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 mb-0">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <h3 class="section-title min-w-0">{{ $module->title }}</h3>
                                @if($p->hasPassed())
                                    <span class="badge badge-success">Lulus</span>
                                @elseif($p->posttests->isNotEmpty())
                                    <span class="badge badge-danger">Belum Lulus</span>
                                @elseif($stage === 'posttest')
                                    <span class="badge badge-primary">Siap Posttest</span>
                                @elseif($stage === 'selesai')
                                    <span class="badge badge-success">Selesai</span>
                                @elseif($stage === 'materi')
                                    <span class="badge badge-warning">Pelajari Materi</span>
                                @else
                                    <span class="badge badge-secondary">Belum Mulai</span>
                                @endif
                            </div>
                            @if($module->description)
                                <p class="text-muted small mb-3">{{ \Illuminate\Support\Str::limit($module->description, 140) }}</p>
                            @endif

                            <div class="small text-muted mb-3 mt-auto">
                                <div>
                                    {{ $p->totalItemCount() }} materi
                                    @if($module->hasQuiz())
                                        &middot; {{ $module->questions_count }} soal &middot; KKM {{ $module->passing_score }}
                                    @else
                                        &middot; tanpa kuis
                                    @endif
                                </div>
                                <div class="d-flex align-items-center gap-2 my-2">
                                    <div class="progress flex-grow-1">
                                        <div class="progress-bar" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <span>{{ $percent }}%</span>
                                </div>
                                @if($module->hasQuiz())
                                    <div>
                                        @if($module->has_pretest)
                                            Pretest: <strong class="text-dark">{{ $p->pretest?->score ?? '-' }}</strong>
                                        @endif
                                        @if($module->has_pretest && $module->has_posttest) &middot; @endif
                                        @if($module->has_posttest)
                                            Posttest terbaik: <strong class="text-dark">{{ $best?->score ?? '-' }}</strong>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <a href="{{ route('learning.show', $module) }}" class="btn {{ $p->hasPassed() || $stage === 'selesai' ? 'btn-default' : 'btn-primary' }} btn-block">
                                {{ $stage === 'pretest' ? 'Mulai Modul' : 'Lanjutkan' }}
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card">
                        <x-empty-state icon="fa-book-reader" title="Belum ada modul belajar" text="Modul muncul di sini setelah dipublikasikan untuk divisi dan jabatan Anda." />
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
