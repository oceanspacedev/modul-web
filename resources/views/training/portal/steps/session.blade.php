{{--
    Kartu langkah Ikuti Sesi di portal peserta: rincian pelatihan, tautan meeting, dan dokumen materi. Urutan dan nomor langkahnya diatur pemateri (Training::portalSteps()); variabel
    lain berasal dari training/portal/index.blade.php.
--}}
<div class="content-card">
    <div class="content-card-header">
        <span><span class="badge badge-primary mr-1">Langkah {{ $stepNumber['session'] }}</span> Ikuti Sesi</span>
        <x-training-status-badge :status="$training->status" />
    </div>

    <h1 class="page-title mb-2" style="font-size: 1.375rem;">
        {{ $training->title }}
    </h1>

    <div class="row mb-3">
        <div class="col-sm-6 mb-2">
            <span class="text-muted small d-block">Pemateri</span>
            <strong>{{ $training->trainer->nama_lengkap ?? '-' }}</strong>
        </div>
        <div class="col-sm-6 mb-2">
            <span class="text-muted small d-block">Jadwal</span>
            <strong>{{ \Carbon\Carbon::parse($training->training_date)->format('d F Y') }}</strong>
            <span class="small d-block text-muted">{{ substr($training->start_time, 0, 5) }} - {{ substr($training->end_time, 0, 5) }} WIB</span>
        </div>
    </div>

    @if($training->description)
        <div class="p-3 bg-light rounded text-muted mb-4 small border">
            <strong class="text-dark d-block mb-1">Deskripsi Materi:</strong>
            {{ $training->description }}
        </div>
    @endif

    @if($training->status === App\Enums\TrainingStatus::Cancelled)
        <div class="alert alert-danger mb-0">
            <i class="fas fa-ban mr-1"></i> Pelatihan ini dibatalkan.{{ $training->zoom_link ? ' Tautan Zoom tidak lagi tersedia.' : '' }}
        </div>
    @elseif($training->zoom_link)
        <div>
            <a href="{{ $training->zoom_link }}" target="_blank" rel="noopener" class="btn btn-primary btn-block">
                <i class="fas fa-video mr-1"></i> Buka Tautan Zoom
            </a>
        </div>
    @else
        {{-- Pelatihan tatap muka: tidak ada tautan meeting. --}}
        <div class="alert alert-light border mb-0 small">
            <i class="fas fa-map-marker-alt mr-1"></i> Pelatihan ini diadakan tatap muka, tanpa tautan meeting.
        </div>
    @endif

    @if($training->documents->isNotEmpty())
        <div class="mt-3 pt-3 border-top">
            <strong class="d-block text-dark small mb-2">Dokumen Materi Pelatihan</strong>
            <div class="list-group list-group-flush border rounded">
                @foreach($training->documents as $pdoc)
                    @php
                        $pver = $pdoc->versions->first();
                        // Dokumen ber-pretest/posttest dibuka lewat halaman kuisnya (perlu login).
                        $pquiz = $pdoc->activeQuiz();
                        $purl = $pquiz ? $pquiz->url('show') : ($pver ? $pver->file_url : $pdoc->file_url);
                    @endphp
                    <a href="{{ $purl }}" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-2">
                        <div class="text-truncate mr-2">
                            <span class="font-weight-bold text-dark small text-truncate d-block">{{ $pdoc->name }}</span>
                            <span class="text-xs text-muted">{{ $pdoc->dokumentype->name ?? 'Dokumen' }} &bull; Versi {{ $pdoc->version ?? 1 }}</span>
                        </div>
                        <span class="badge badge-secondary">Buka</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
