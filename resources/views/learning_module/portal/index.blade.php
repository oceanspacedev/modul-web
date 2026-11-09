@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Modul Saya" subtitle="Kerjakan pretest, pelajari semua materi, lalu kerjakan posttest. Posttest boleh diulang kapan saja." eyebrow="Modul Belajar" />
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
                                <div>{{ $p->totalItemCount() }} materi &middot; {{ $module->questions_count }} soal &middot; KKM {{ $module->passing_score }}</div>
                                <div class="d-flex align-items-center gap-2 my-2">
                                    <div class="progress flex-grow-1">
                                        <div class="progress-bar" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <span>{{ $percent }}%</span>
                                </div>
                                <div>
                                    Pretest: <strong class="text-dark">{{ $p->pretest?->score ?? '-' }}</strong>
                                    &middot; Posttest terbaik: <strong class="text-dark">{{ $best?->score ?? '-' }}</strong>
                                </div>
                            </div>

                            <a href="{{ route('learning.show', $module) }}" class="btn {{ $p->hasPassed() ? 'btn-default' : 'btn-primary' }} btn-block">
                                {{ $stage === 'pretest' ? 'Mulai Modul' : 'Lanjutkan' }}
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card">
                        <x-empty-state icon="fa-book-reader" title="Belum ada modul belajar" text="Belum ada modul belajar untuk Anda. Modul baru akan muncul di sini setelah dipublikasikan." />
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
