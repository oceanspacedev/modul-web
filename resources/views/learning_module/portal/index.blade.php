@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <h1 class="m-0 mb-1 text-dark font-weight-bold" style="font-size: 1.5rem;">Modul Saya</h1>
        <p class="text-muted small mb-3">Kerjakan pretest, pelajari semua materi, lalu kerjakan posttest. Posttest boleh diulang kapan saja.</p>
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
                @endphp
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 mb-0">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="font-weight-bold mb-0 mr-2">{{ $module->title }}</h5>
                                @if($p->hasPassed())
                                    <span class="badge badge-success px-2 py-1">Lulus</span>
                                @elseif($p->posttests->isNotEmpty())
                                    <span class="badge badge-danger px-2 py-1">Belum Lulus</span>
                                @elseif($stage === 'posttest')
                                    <span class="badge badge-info px-2 py-1">Siap Posttest</span>
                                @elseif($stage === 'materi')
                                    <span class="badge badge-warning text-white px-2 py-1">Pelajari Materi</span>
                                @else
                                    <span class="badge badge-secondary px-2 py-1">Belum Mulai</span>
                                @endif
                            </div>
                            @if($module->description)
                                <p class="text-muted small mb-3">{{ \Illuminate\Support\Str::limit($module->description, 140) }}</p>
                            @endif

                            <div class="small text-muted mb-3 mt-auto">
                                <div>{{ $p->totalItemCount() }} materi &middot; {{ $module->questions_count }} soal &middot; KKM {{ $module->passing_score }}</div>
                                <div class="progress my-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: {{ $p->totalItemCount() ? round($p->completedItemCount() / $p->totalItemCount() * 100) : 0 }}%"></div>
                                </div>
                                <div>
                                    Pretest: <strong class="text-dark">{{ $p->pretest?->score ?? '-' }}</strong>
                                    &middot; Posttest terbaik: <strong class="text-dark">{{ $best?->score ?? '-' }}</strong>
                                </div>
                            </div>

                            <a href="{{ route('learning.show', $module) }}" class="btn btn-primary btn-sm btn-block">
                                {{ $stage === 'pretest' ? 'Mulai Modul' : 'Lanjutkan' }}
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card"><div class="card-body text-center text-muted py-5">
                        Belum ada modul belajar untuk Anda.
                    </div></div>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
