@extends('layout.main_template')

@section('content')
<style>
    .zoom-drop-area {
        border: 1px dashed var(--gray-300);
        border-radius: var(--radius-xl);
        background: var(--gray-50);
        cursor: pointer;
        transition: border-color 0.15s ease, background-color 0.15s ease;
    }
    .zoom-drop-area:hover,
    .zoom-drop-area.is-dragover {
        border-color: var(--primary-500);
        background: var(--primary-50);
    }
</style>

@php
    $headerSubtitle = \Carbon\Carbon::parse($training->training_date)->format('d F Y')
        . ' · ' . substr($training->start_time, 0, 5) . ' - ' . substr($training->end_time, 0, 5) . ' WIB'
        . ($training->zoom_link ? ' · Online via Zoom' : '');
@endphp

<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$training->title" :subtitle="$headerSubtitle" :breadcrumbs="['Pelatihan' => null, 'Jadwal Pelatihan' => route('training.index'), $training->title => null]">
            <div class="mt-2">
                <x-training-status-badge :status="$training->status" />
            </div>
            <x-slot:actions>
                <a href="{{ route('training.index') }}" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                {{-- Kamera hanya relevan untuk pelatihan daring. --}}
                @if($training->isOnline())
                    <button type="button" class="btn btn-default" data-toggle="modal" data-target="#scanZoomModal">
                        <i class="fas fa-video-slash"></i> Scan Off Cam
                    </button>
                @endif
                <div class="dropdown d-inline-block">
                    <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Opsi
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="/training/{{ $training->id }}/edit">
                            <i class="fas fa-edit mr-2 text-muted"></i> Edit Informasi Pelatihan
                        </a>
                        <form action="{{ route('training.export', $training) }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="fas fa-file-excel mr-2 text-muted"></i> Export Rekap Nilai
                            </button>
                        </form>
                        @if($training->hasQuiz() && $stats['quizSubmitted'] + $stats['pretestSubmitted'] > 0)
                            <form action="{{ route('training.regrade', $training) }}" method="POST" class="m-0"
                                onsubmit="return confirm('Hitung ulang nilai semua peserta memakai kunci jawaban saat ini? Jawaban peserta dan nilai essay tidak berubah.')">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="fas fa-calculator mr-2 text-muted"></i> Hitung Ulang Nilai
                                </button>
                            </form>
                        @endif
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#uploadTrainingVideoModal">
                            <i class="fas fa-video mr-2 text-muted"></i> Upload Video Materi
                        </a>
                    </div>
                </div>
                @if($training->hasQuiz())
                    <a href="/training/{{ $training->id }}/questions" class="btn btn-primary">
                        Soal Kuis ({{ $training->questions->count() }})
                    </a>
                @endif
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        {{-- Kunci jawaban rusak (mis. tersimpan bukan huruf pilihan): nilai peserta salah sampai diperbaiki. --}}
        @php $invalidKeyCount = $training->hasQuiz() ? $training->questions->reject->hasValidKey()->count() : 0; @endphp
        @if($invalidKeyCount > 0)
            <div class="alert alert-danger">
                <strong>{{ $invalidKeyCount }} soal pilihan ganda tidak punya kunci jawaban yang valid.</strong>
                Jawaban peserta pada soal itu selalu dihitung salah.
                <a href="{{ route('training.questions.index', $training) }}" class="alert-link">Perbaiki di Soal Kuis</a>, lalu tekan <strong>Hitung Ulang Nilai</strong> bila sudah ada peserta yang mengerjakan.
            </div>
        @endif

        {{-- LANGKAH PELATIHAN --}}
        @php
            $questionCount = $training->questions->count();
            $pretestQuestionCount = $training->questionsFor('pretest')->count();
            $participantCount = $training->participants_count;
            $isClosed = in_array($training->status, [App\Enums\TrainingStatus::Completed, App\Enums\TrainingStatus::Cancelled], true);
            $sessionSteps = [
                'pretest' => [
                    'label' => 'Pretest',
                    'hint' => 'buka pretest untuk peserta.',
                    'done' => $training->isPhaseActive('pretest') || $training->phaseOpensAt('pretest') || $training->pretestResults()->exists(),
                    // Dialog waktu yang sama dengan tombol Buka Pretest di Kontrol Sesi.
                    'action' => 'Buka Pretest', 'modal' => 'phaseTimeModal', 'modalData' => [
                        'url' => route('training.toggle-pretest', $training), 'phase' => 'pretest', 'open' => '1',
                        'title' => 'Buka Pretest', 'submit' => 'Buka Pretest', 'minutes' => $training->durationMinutes('pretest'),
                    ],
                ],
                'attendance' => [
                    'label' => 'Presensi',
                    'hint' => 'buka presensi untuk peserta.',
                    'done' => $training->isAttendanceOpenOrScheduled() || $stats['attended'] > 0,
                    // Dialog waktu yang sama dengan tombol Buka Presensi di Kontrol Sesi.
                    'action' => 'Buka Presensi', 'modal' => 'phaseTimeModal', 'modalData' => [
                        'url' => route('training.toggle-attendance', $training), 'phase' => 'attendance', 'open' => '1',
                        'title' => 'Buka Presensi', 'submit' => 'Buka Presensi',
                        'intro' => 'Jam buka dan jam tutup berlaku sama untuk semua peserta. Setelah jam tutup lewat, presensi tertutup sendiri dan peserta yang belum mengisi tercatat Belum Absen.',
                    ],
                ],
                'evaluation' => [
                    'label' => 'Evaluasi',
                    'hint' => 'buka form evaluasi saat peserta boleh mengisinya.',
                    'done' => $training->isPhaseActive('evaluation') || $training->phaseOpensAt('evaluation') || ($evaluation['count'] ?? 0) > 0,
                    // Dialog waktu yang sama dengan tombol Buka Evaluasi di Kontrol Sesi.
                    'action' => 'Buka Evaluasi', 'modal' => 'phaseTimeModal', 'modalData' => [
                        'url' => route('training.toggle-evaluation-access', $training), 'phase' => 'evaluation', 'open' => '1',
                        'title' => 'Buka Evaluasi', 'submit' => 'Buka Evaluasi',
                        'intro' => 'Jam buka dan jam tutup berlaku sama untuk semua peserta. Setelah jam tutup lewat, form evaluasi tidak bisa diisi lagi.',
                    ],
                ],
                'posttest' => [
                    'label' => $training->has_pretest ? 'Posttest' : 'Kuis',
                    'hint' => $training->has_pretest ? 'buka posttest setelah materi selesai.' : 'buka kuis setelah materi selesai.',
                    'done' => $training->isPhaseActive('posttest') || $training->phaseOpensAt('posttest') || $stats['quizSubmitted'] > 0,
                    'action' => $training->has_pretest ? 'Buka Posttest' : 'Buka Kuis', 'modal' => 'phaseTimeModal', 'modalData' => [
                        'url' => route('training.toggle-quiz', $training), 'phase' => 'posttest', 'open' => '1',
                        'title' => 'Buka Kuis', 'submit' => 'Buka Kuis', 'minutes' => $training->durationMinutes('posttest'),
                    ],
                ],
            ];
            $trainingSteps = [
                [
                    'label' => 'Peserta', 'meta' => $participantCount.' peserta',
                    'hint' => 'daftarkan peserta pelatihan.',
                    'done' => $participantCount > 0,
                    'action' => 'Tambah Peserta', 'url' => route('training.edit', $training).'#peserta',
                ],
                ...($training->hasQuiz() ? [[
                    'label' => 'Soal', 'meta' => $questionCount.' soal',
                    'hint' => 'siapkan soal kuis.',
                    'done' => $questionCount > 0,
                    'action' => 'Siapkan Soal', 'url' => route('training.questions.index', $training),
                ]] : []),
                [
                    'label' => 'Pengaturan',
                    'hint' => 'jawab pertanyaan pengaturan presensi.',
                    'done' => ! $training->needsSetup(),
                    'action' => 'Atur', 'url' => route('training.setup', $training),
                ],
                // Langkah saat sesi (pretest, presensi, evaluasi, posttest) mengikuti urutan langkah peserta pelatihan ini.
                ...collect($training->portalSteps())->map(fn (string $step) => $sessionSteps[$step] ?? null)->filter()->values()->all(),
                [
                    'label' => 'Rekap',
                    'hint' => 'ubah status jadi Selesai, lalu export rekap nilai.',
                    'done' => false,
                    'action' => 'Export Rekap', 'post' => route('training.export', $training),
                ],
            ];
        @endphp
        @unless($isClosed)
            <x-setup-steps title="Langkah menjalankan pelatihan" :steps="$trainingSteps" />
        @endunless

        {{-- RINGKASAN --}}
        @php
            $attendanceRate = $stats['total'] > 0 ? round(($stats['attended'] / $stats['total']) * 100) : 0;
            $quizRate = $stats['total'] > 0 ? round(($stats['quizSubmitted'] / $stats['total']) * 100) : 0;
            // Kuis yang nilainya ditampilkan sebagai nilai pelatihan: posttest, atau pretest bila pelatihan hanya punya itu.
            $mainPhase = $training->mainPhase();
            $mainPhaseLabel = $training->mainPhaseLabel();
        @endphp
        <div class="row">
            <div class="col-lg-3 col-6 mb-3">
                <div class="stat-tile h-100">
                    <div class="min-w-0">
                        <p class="stat-tile-label">Peserta</p>
                        <p class="stat-tile-value" id="statTotal">{{ $stats['total'] }}</p>
                        @if($training->has_attendance)
                            <p class="stat-tile-hint"><span id="statAttended">{{ $stats['attended'] }}</span> hadir &middot; <span id="statPending">{{ $stats['pending'] }}</span> belum absen</p>
                        @else
                            <p class="stat-tile-hint">tanpa presensi</p>
                        @endif
                    </div>
                </div>
            </div>
            @if($training->has_attendance)
            <div class="col-lg-3 col-6 mb-3">
                <div class="stat-tile h-100">
                    <div class="min-w-0">
                        <p class="stat-tile-label">Kehadiran</p>
                        <p class="stat-tile-value">{{ $attendanceRate }}%</p>
                        <p class="stat-tile-hint"><span id="statAbsent">{{ $stats['absent'] }}</span> tidak hadir</p>
                    </div>
                </div>
            </div>
            @endif
            {{-- Dua kotak terakhir mengikuti isi pelatihan: kuis utamanya (posttest, atau pretest bila hanya itu), form
                 evaluasi bila tanpa kuis, atau tidak tampil bila pelatihan hanya sesi dan presensi. --}}
            @if($mainPhase)
                <div class="col-lg-3 col-6 mb-3">
                    <div class="stat-tile h-100">
                        <div class="min-w-0">
                            <p class="stat-tile-label">{{ $mainPhaseLabel }} selesai</p>
                            <p class="stat-tile-value"><span id="statQuiz">{{ $stats['quizSubmitted'] }}</span><span class="text-muted text-sm font-weight-normal"> / {{ $stats['total'] }}</span></p>
                            <p class="stat-tile-hint">{{ $quizRate }}% peserta</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6 mb-3">
                    <div class="stat-tile h-100">
                        <div class="min-w-0">
                            <p class="stat-tile-label">Rata-rata nilai{{ $training->has_pretest && $training->has_posttest ? ' posttest' : '' }}</p>
                            <p class="stat-tile-value" id="statAvg">{{ $stats['avgScore'] }}</p>
                            @if($training->has_pretest && $training->has_posttest)
                                <p class="stat-tile-hint">pretest <span id="statPretestAvg">{{ $stats['pretestAvg'] }}</span> dari <span id="statPretest">{{ $stats['pretestSubmitted'] }}</span> peserta</p>
                            @else
                                <p class="stat-tile-hint">dari yang sudah mengerjakan</p>
                            @endif
                        </div>
                    </div>
                </div>
            @elseif($training->has_evaluation && $evaluation)
                <div class="col-lg-3 col-6 mb-3">
                    <div class="stat-tile h-100">
                        <div class="min-w-0">
                            <p class="stat-tile-label">Evaluasi terisi</p>
                            <p class="stat-tile-value">{{ $evaluation['count'] }}<span class="text-muted text-sm font-weight-normal"> / {{ $stats['total'] }}</span></p>
                            <p class="stat-tile-hint">{{ $stats['total'] > 0 ? round(($evaluation['count'] / $stats['total']) * 100) : 0 }}% peserta</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6 mb-3">
                    <div class="stat-tile h-100">
                        <div class="min-w-0">
                            <p class="stat-tile-label">Rata-rata evaluasi</p>
                            <p class="stat-tile-value">{{ $evaluation['average'] === null ? '-' : number_format($evaluation['average'], 1) }}<span class="text-muted text-sm font-weight-normal"> / {{ \App\Support\TrainingEvaluationForm::SCALE_MAX }}</span></p>
                            <p class="stat-tile-hint">dari yang sudah mengisi</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- TRAINING DETAIL OVERVIEW & SESSION CONTROL -->
        <div class="row">
            <!-- LEFT COLUMN: DETAIL & MATERI -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Detail Pelatihan</h3>
                    </div>
                    <div class="card-body">
                        <!-- METADATA GRID -->
                        <div class="row">
                            <div class="col-sm-4 mb-3 mb-sm-0">
                                <div class="text-muted text-xs mb-1">Pemateri</div>
                                <div class="font-weight-bold">{{ $training->trainer->nama_lengkap ?? '-' }}</div>
                                <div class="text-muted text-xs">{{ optional($training->trainer)->divisi->name ?? 'Divisi Umum' }}</div>
                            </div>
                            <div class="col-sm-4 mb-3 mb-sm-0">
                                <div class="text-muted text-xs mb-1">Tautan Meeting</div>
                                @if($training->zoom_link)
                                    <a href="{{ $training->zoom_link }}" target="_blank" class="d-inline-block text-truncate" style="max-width: 220px;">
                                        Buka Tautan Zoom
                                    </a>
                                @else
                                    <span class="text-muted">Tatap muka (tanpa tautan)</span>
                                @endif
                            </div>
                            <div class="col-sm-4">
                                <div class="text-muted text-xs mb-1">Jadwal Sesi</div>
                                <div class="font-weight-bold">{{ substr($training->start_time, 0, 5) }} - {{ substr($training->end_time, 0, 5) }} WIB</div>
                            </div>
                        </div>

                        @if($training->description)
                            <div class="border-top mt-3 pt-3">
                                <div class="text-muted text-xs mb-1">Deskripsi & Materi Pelatihan</div>
                                <div class="text-sm">{{ $training->description }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- SECTION MATERI & DOKUMEN PELATIHAN -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Materi & Dokumen Pelatihan <span class="text-muted font-weight-normal">({{ $training->documents->count() }})</span>
                        </h3>
                        <div class="card-tools d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#attachDocumentModal">
                                Lampirkan Dokumen
                            </button>
                            @if($training->hasQuiz())
                                <a href="/training/{{ $training->id }}/questions?open_ai=1" class="btn btn-xs btn-default">
                                    Buat Soal AI
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Rekaman Video -->
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 mb-3 bg-light rounded border">
                            <div class="d-flex align-items-center min-w-0">
                                <span class="stat-tile-icon mr-3"><i class="fas fa-play"></i></span>
                                <div class="min-w-0">
                                    <div class="font-weight-bold text-sm text-truncate">
                                        {{ $training->video ? $training->video->title : 'Rekaman Video Materi' }}
                                    </div>
                                    <div class="text-muted text-xs">
                                        @if($training->video)
                                            {{ $training->video->views_count }} dilihat &bull; Diunggah {{ $training->video->created_at->format('d M Y') }}
                                        @else
                                            Belum ada video rekaman yang diunggah.
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                @if($training->video)
                                    <a href="{{ route('video.show', $training->video->id) }}" class="btn btn-xs btn-default">
                                        Tonton Video
                                    </a>
                                    <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#uploadTrainingVideoModal">
                                        Ganti Video
                                    </button>
                                @else
                                    <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#uploadTrainingVideoModal">
                                        Upload Video Materi
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Dokumen Terlampir -->
                        @if($training->documents->isEmpty())
                            <div class="text-center py-2 text-muted text-sm">
                                Belum ada dokumen materi yang dilampirkan.
                            </div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($training->documents as $doc)
                                    @php
                                        $latestVer = $doc->versions->first();
                                        $fileUrl = $latestVer ? $latestVer->file_url : $doc->file_url;
                                    @endphp
                                    <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
                                        <div class="mr-2 min-w-0">
                                            <a href="{{ $fileUrl }}" target="_blank" class="font-weight-bold text-dark text-sm text-truncate d-block" title="{{ $doc->name }}">
                                                {{ $doc->name }}
                                            </a>
                                            <span class="text-xs text-muted">
                                                {{ $doc->dokumentype->name ?? 'Dokumen' }} &bull; v{{ $doc->version ?? ($latestVer->version_number ?? 1) }}
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-end flex-wrap gap-1">
                                            @if($canImportDocumentQuestions && $doc->quizModule?->hasQuiz() && $doc->quizModule->questions_count > 0)
                                                <a href="{{ route('training.questions.index', ['training' => $training, 'quiz_document' => $doc->id]) }}" class="btn btn-xs btn-default">
                                                    Ambil Soal
                                                </a>
                                            @endif
                                            <a href="{{ $fileUrl }}" target="_blank" class="btn btn-xs btn-default">
                                                Buka
                                            </a>
                                            <form action="/training/{{ $training->id }}/detach-document/{{ $doc->id }}" method="POST" class="d-inline m-0" onsubmit="return confirm(@js('Lepas dokumen '.$doc->name.' dari pelatihan ini?'))">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-default text-danger">
                                                    Lepas
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- HASIL FORM EVALUASI -->
                @if($evaluation)
                @php $scaleMax = \App\Support\TrainingEvaluationForm::SCALE_MAX; @endphp
                <div class="card" id="hasil-evaluasi">
                    <div class="card-header">
                        <h3 class="card-title">
                            Hasil Evaluasi Peserta
                            <span class="text-muted text-sm font-weight-normal">({{ $evaluation['count'] }} dari {{ $stats['total'] }} mengisi)</span>
                        </h3>
                        <div class="card-tools d-flex align-items-center gap-1">
                            @unless($training->has_evaluation)
                                <span class="badge badge-secondary">Nonaktif</span>
                            @endunless
                            <a href="{{ route('training.evaluation.index', $training) }}" class="btn btn-xs btn-default">Kelola Pertanyaan</a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($evaluation['questionCount'] === 0)
                            <div class="text-center py-2 text-muted text-sm">
                                Form evaluasi belum punya pertanyaan.
                            </div>
                        @elseif($evaluation['count'] === 0)
                            <div class="text-center py-2 text-muted text-sm">
                                Belum ada peserta yang mengisi form evaluasi ({{ $evaluation['questionCount'] }} pertanyaan).
                            </div>
                        @else
                            @if($evaluation['average'] !== null)
                                <div class="d-flex align-items-baseline gap-2 mb-3">
                                    <span class="score-value" style="font-size: 1.75rem;">{{ number_format($evaluation['average'], 2) }}</span>
                                    <span class="text-muted text-sm">rata-rata dari skala {{ $scaleMax }}</span>
                                </div>
                            @endif
                            @foreach($evaluation['questions'] as $item)
                                @php $q = $item['question']; @endphp
                                <div class="{{ $loop->last ? '' : 'mb-3' }}">
                                    @if($q->isScale())
                                        <div class="d-flex justify-content-between align-items-baseline gap-2 text-sm">
                                            <span>{{ $q->question }}</span>
                                            <strong class="text-nowrap">{{ $item['average'] === null ? '-' : number_format($item['average'], 2) }}</strong>
                                        </div>
                                        <div class="rating-bar mt-1"><span style="width: {{ round(($item['average'] ?? 0) / $scaleMax * 100) }}%"></span></div>
                                    @elseif($q->type === \App\Models\TrainingEvaluationQuestion::TYPE_CHOICE)
                                        <div class="text-sm mb-1">{{ $q->question }} <span class="text-muted text-xs">({{ $item['answered'] }} jawaban)</span></div>
                                        {{-- Daftar panjang (mis. nama toko): hanya opsi yang dipilih yang ditampilkan. --}}
                                        @php
                                            $options = $q->hasLongOptionList() ? array_filter($item['options'], fn ($option) => $option['count'] > 0) : $item['options'];
                                            $unchosen = count($item['options']) - count($options);
                                        @endphp
                                        @foreach($options as $option)
                                            @php $share = $item['answered'] > 0 ? round($option['count'] / $item['answered'] * 100) : 0; @endphp
                                            <div class="d-flex justify-content-between align-items-baseline gap-2 text-xs text-muted">
                                                <span>{{ $option['label'] }}{{ $option['removed'] ? ' (opsi lama)' : '' }}</span>
                                                <span class="text-nowrap">{{ $option['count'] }} &middot; {{ $share }}%</span>
                                            </div>
                                            <div class="rating-bar mb-1"><span style="width: {{ $share }}%"></span></div>
                                        @endforeach
                                        @if($unchosen > 0)
                                            <div class="text-xs text-muted">{{ $unchosen }} pilihan lain belum dipilih.</div>
                                        @endif
                                    @else
                                        <div class="text-sm mb-1">
                                            {{ $q->question }}
                                            <span class="text-muted text-xs">
                                                ({{ $item['answered'] }} jawaban{{ $item['answered'] > count($item['entries']) ? ', '.count($item['entries']).' terbaru; selengkapnya di Export Rekap' : '' }})
                                            </span>
                                        </div>
                                        @forelse($item['entries'] as $entry)
                                            <div class="text-sm border-left pl-2 mb-2">
                                                <strong>{{ $entry['name'] }}</strong>
                                                <span class="text-muted text-xs">&middot; {{ $entry['at']->format('d M Y H:i') }}</span>
                                                <div style="white-space: pre-line;">{{ $entry['text'] }}</div>
                                            </div>
                                        @empty
                                            <div class="text-muted text-xs">Belum ada jawaban.</div>
                                        @endforelse
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
                @endif
            </div>

            <!-- RIGHT COLUMN: KONTROL SESI -->
            <div class="col-lg-4">
                <div class="card" id="kontrol-sesi">
                    <div class="card-header">
                        <h3 class="card-title">Kontrol Sesi Pelatihan</h3>
                    </div>

                    <!-- STATUS SESI -->
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted text-xs">Status Pelatihan</span>
                            <x-training-status-badge :status="$training->status" />
                        </div>
                        <form action="{{ route('training.status', $training) }}" method="POST" id="formStatusPelatihan">
                            @csrf
                            <select name="status" class="form-control form-control-sm" onchange="document.getElementById('formStatusPelatihan').submit();">
                                @foreach (App\Enums\TrainingStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($training->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>

                    <!-- PENGATURAN AKSES & FITUR -->
                    <div class="list-group list-group-flush border-top">
                        {{-- Kontrol tiap langkah, dalam Urutan Langkah Peserta. Langkah sesi tidak punya kontrol di sini (lihat Status Sesi). --}}
                        @foreach($training->portalStepOrder() as $controlStep)
                            @includeIf('training.controls.'.$controlStep)
                        @endforeach

                        <!-- URUTAN LANGKAH PESERTA -->
                        <div class="list-group-item d-flex align-items-start justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-weight-bold">Urutan Langkah Peserta</div>
                                <div class="text-muted text-xs">
                                    {{ implode(' → ', array_map(fn (string $step) => \App\Models\Training::PORTAL_STEPS[$step], $training->portalSteps())) }}
                                </div>
                            </div>
                            <button type="button" class="btn btn-default btn-xs text-nowrap" data-toggle="modal" data-target="#portalStepsModal">Ubah</button>
                        </div>

                        <!-- ATURAN KUIS -->
                        <div class="list-group-item d-flex align-items-start justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-weight-bold">Aturan Kuis</div>
                                @forelse(array_filter(\App\Models\Training::PHASES, fn ($phase) => $training->hasPhase($phase)) as $phase)
                                    <div class="text-muted text-xs">
                                        {{ \App\Models\Training::phaseLabel($phase) }}: {{ mb_strtolower(\App\Support\QuizRules::attemptsLabel($training->maxAttempts($phase))) }}
                                        &middot; hasil {{ mb_strtolower(\App\Support\QuizRules::answersLabel($training->showsAnswers($phase))) }}
                                        &middot; {{ $training->durationMinutes($phase) ? 'terbuka '.$training->durationMinutes($phase).' menit' : 'tanpa batas waktu' }}
                                        &middot; mode {{ $training->quizMode($phase) === 'game' ? 'game' : 'formal' }}
                                    </div>
                                @empty
                                    <div class="text-muted text-xs">Tanpa kuis</div>
                                @endforelse
                                @if($training->has_posttest && $training->allowsQuizRetake() && $training->show_quiz_answers)
                                    <div class="text-warning text-xs">Kunci jawaban terlihat, peserta bisa menyontek saat mengulang</div>
                                @endif
                            </div>
                            <button type="button" class="btn btn-default btn-xs text-nowrap" data-toggle="modal" data-target="#quizRulesModal">Ubah</button>
                        </div>
                    </div>

                    <!-- TOMBOL BROADCAST WHATSAPP -->
                    <div class="card-footer">
                        <form action="/training/{{ $training->id }}/broadcast-wa" method="POST" class="m-0" onsubmit="return confirm(@js('Kirim notifikasi WhatsApp ke '.$training->participants_count.' peserta?'))">
                            @csrf
                            <button type="submit" class="btn btn-default btn-sm btn-block">
                                <i class="fab fa-whatsapp"></i> Broadcast WA ke Peserta
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL ATURAN KUIS (blok yang sama dengan form video, dokumen, dan modul) -->
        <div class="modal fade" id="quizRulesModal" tabindex="-1" role="dialog" aria-labelledby="quizRulesModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form action="{{ route('training.quiz-rules', $training) }}" method="POST" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="quizRulesModalTitle">Ubah Aturan Kuis</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @include('learning_module.partials.quiz-rules-fields', ['trainingRules' => $training])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Aturan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL URUTAN LANGKAH PESERTA -->
        <div class="modal fade" id="portalStepsModal" tabindex="-1" role="dialog" aria-labelledby="portalStepsModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form action="{{ route('training.portal-steps', $training) }}" method="POST" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="portalStepsModalTitle">Urutan Langkah Peserta</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">
                            Susun langkah sesuai jalannya sesi Anda. Urutan ini menentukan susunan kartu dan nomor "Langkah" di halaman
                            peserta, juga susunan baris di Kontrol Sesi. Tiap langkah tetap Anda buka sendiri dari Kontrol Sesi.
                        </p>
                        @php
                            $usedSteps = $training->portalSteps();
                            // Langkah yang dimatikan tetap bisa diurutkan, supaya posisinya sudah benar saat dinyalakan.
                            $stepNotes = [
                                'pretest' => 'dimatikan di Aturan Kuis',
                                'attendance' => 'presensi tidak dipakai',
                                'evaluation' => $training->has_evaluation ? 'belum ada pertanyaan' : 'form evaluasi nonaktif',
                                'posttest' => 'dimatikan di Aturan Kuis',
                            ];
                        @endphp
                        <ol class="list-group mb-3" id="portalStepsList">
                            @foreach($training->portalStepOrder() as $step)
                                <li class="list-group-item d-flex align-items-center justify-content-between gap-2 py-2" data-step="{{ $step }}">
                                    <input type="hidden" name="steps[]" value="{{ $step }}">
                                    <span class="min-w-0">
                                        <span class="badge badge-secondary mr-1" data-step-number></span>
                                        <span class="text-sm font-weight-bold">{{ $step === 'posttest' && ! $training->has_pretest ? 'Kuis' : \App\Models\Training::PORTAL_STEPS[$step] }}</span>
                                        @unless(in_array($step, $usedSteps, true))
                                            <span class="text-muted text-xs">({{ $stepNotes[$step] ?? 'tidak dipakai' }})</span>
                                        @endunless
                                    </span>
                                    <span class="d-inline-flex gap-1 flex-shrink-0">
                                        <button type="button" class="btn btn-default btn-xs" data-step-move="-1" title="Naikkan" aria-label="Naikkan {{ \App\Models\Training::PORTAL_STEPS[$step] }}"><i class="fas fa-arrow-up"></i></button>
                                        <button type="button" class="btn btn-default btn-xs" data-step-move="1" title="Turunkan" aria-label="Turunkan {{ \App\Models\Training::PORTAL_STEPS[$step] }}"><i class="fas fa-arrow-down"></i></button>
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                        <p class="small mb-0" id="portalStepsEvaluationNote"
                            data-before="Form evaluasi di atas posttest: selama form terbuka, peserta wajib mengisinya sebelum mengerjakan posttest. Buka form lewat baris Form Evaluasi sebelum posttest, supaya waktu posttest tidak terpotong."
                            data-after="Form evaluasi di bawah posttest: peserta bisa langsung mengerjakan posttest. Form dibuka sendiri lewat baris Form Evaluasi."></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Urutan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL BUKA / ATUR WAKTU PRETEST, POSTTEST, PRESENSI & FORM EVALUASI (isinya diatur dari tombol pemicunya) -->
        <div class="modal fade" id="phaseTimeModal" tabindex="-1" role="dialog" aria-labelledby="phaseTimeModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form action="" method="POST" class="modal-content">
                    @csrf
                    <input type="hidden" name="phase" value="">
                    <input type="hidden" name="open" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="phaseTimeModalTitle">Atur waktu</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @php $phaseScheduleDate = $training->scheduleDate(); @endphp
                        <p class="text-muted small" id="phaseTimeModalIntro"
                            data-default="Jam buka dan jam tutup berlaku sama untuk semua peserta. Setelah jam tutup lewat, kuis terkunci sendiri dan peserta yang belum mengerjakan tidak mendapat nilai.">
                            Jam buka dan jam tutup berlaku sama untuk semua peserta. Setelah jam tutup lewat, kuis terkunci
                            sendiri dan peserta yang belum mengerjakan tidak mendapat nilai.
                        </p>

                        <div id="phaseOpenChoice">
                            <div class="text-sm font-weight-bold mb-2">Dibuka</div>
                            <div class="custom-control custom-radio mb-2">
                                <input type="radio" class="custom-control-input" id="openModeNow" name="open_mode" value="now">
                                <label class="custom-control-label" for="openModeNow">Sekarang</label>
                            </div>
                            <div class="custom-control custom-radio mb-3">
                                <input type="radio" class="custom-control-input" id="openModeClock" name="open_mode" value="clock">
                                <label class="custom-control-label" for="openModeClock">Otomatis pada pukul</label>
                                <input type="time" class="form-control form-control-sm d-inline-block w-auto ml-2 align-middle" name="open_time" aria-label="Jam buka" data-pick="open_mode:clock">
                                <span class="text-muted small ml-1">WIB, {{ $phaseScheduleDate->isToday() ? 'hari ini' : $phaseScheduleDate->translatedFormat('j M Y') }}</span>
                            </div>
                            <div class="text-sm font-weight-bold mb-2">Ditutup</div>
                        </div>

                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" class="custom-control-input" id="closeModeClock" name="close_mode" value="clock">
                            <label class="custom-control-label" for="closeModeClock">Otomatis pada pukul</label>
                            <input type="time" class="form-control form-control-sm d-inline-block w-auto ml-2 align-middle" name="close_time" aria-label="Jam tutup" data-pick="close_mode:clock">
                            <span class="text-muted small ml-1">WIB</span>
                        </div>
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" class="custom-control-input" id="closeModeMinutes" name="close_mode" value="minutes">
                            <label class="custom-control-label" for="closeModeMinutes">Otomatis setelah</label>
                            <input type="number" min="1" max="{{ \App\Support\QuizRules::MAX_DURATION_MINUTES }}" class="form-control form-control-sm d-inline-block ml-2 align-middle" style="width: 5rem;" name="close_minutes" aria-label="Lama terbuka dalam menit" data-pick="close_mode:minutes">
                            <span class="small ml-1" id="phaseMinutesHint">menit</span>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" class="custom-control-input" id="closeModeNone" name="close_mode" value="none">
                            <label class="custom-control-label" for="closeModeNone" id="phaseNoneLabel" data-default="Tanpa batas waktu, saya kunci sendiri">Tanpa batas waktu, saya kunci sendiri</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="phaseTimeModalSubmit">Simpan Waktu</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TABEL REKAP PESERTA & NILAI -->
        <div class="card" id="peserta">
            <div class="card-header">
                <h3 class="card-title">
                    {{ $training->hasQuiz() ? 'Daftar Peserta & Nilai' : 'Daftar Peserta' }}
                    <span class="text-muted text-sm font-weight-normal">({{ $training->participants_count }} orang)</span>
                </h3>
                <div class="card-tools d-flex align-items-center flex-wrap gap-2">
                    <a href="{{ route('training.edit', $training) }}#peserta" class="btn btn-default btn-sm">
                        <i class="fas fa-user-plus"></i> Tambah / Ubah Peserta
                    </a>
                    @if($training->isOnline())
                        <button type="button" class="btn btn-default btn-sm" id="btnToggleManualOffCamMode">
                            <i class="fas fa-user-edit"></i> Input Off Cam Manual
                        </button>
                    @endif
                    {{-- Pencarian dijalankan server: tabel hanya memuat satu halaman peserta. --}}
                    <form action="{{ route('training.show', $training) }}#peserta" method="GET" class="m-0" role="search" style="width: 220px; max-width: 100%;">
                        <x-search-field name="peserta" placeholder="Cari peserta..."
                            :clear-url="route('training.show', $training).'#peserta'" />
                    </form>
                </div>
            </div>

            <!-- BANNER NOTIFIKASI MODE MANUAL OFF CAM (MUNCUL KETIKA MODE AKTIF) -->
            <div id="bannerManualOffCamMode" class="alert alert-danger mx-3 mt-3 mb-0 d-none align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-start">
                    <i class="fas fa-video-slash mr-2 mt-1"></i>
                    <div>
                        <strong class="d-block">Mode cepat Off Cam aktif</strong>
                        <span class="text-sm">Klik <strong>"Tandai Off Cam"</strong> untuk mencatat. Gunakan tombol <strong>[+]</strong> untuk menambah frekuensi atau <strong>[-]</strong> untuk mengurangi hitungan jika salah.</span>
                    </div>
                </div>
                <button type="button" class="btn btn-default btn-sm text-nowrap" id="btnExitManualOffCamMode">
                    <i class="fas fa-check"></i> Selesai Mode Manual
                </button>
            </div>

            <div class="card-body table-responsive p-0" style="min-height: 260px;">
                <table class="table table-hover mb-0" id="participantsTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px;">No</th>
                            <th>Peserta</th>
                            <th>WhatsApp</th>
                            {{-- Tanpa presensi, kolom ini hanya memuat catatan kamera (pelatihan daring) atau tidak tampil. --}}
                            @if($training->has_attendance)
                                <th>Presensi</th>
                            @elseif($training->isOnline())
                                <th>Kamera</th>
                            @endif
                            @if($mainPhase)
                                <th>{{ $mainPhaseLabel }}</th>
                                <th class="text-center">Nilai</th>
                            @elseif($training->has_evaluation)
                                <th>Evaluasi</th>
                            @endif
                            <th class="text-center" style="width: 110px;" id="thActionHeader">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $hasEssay = $mainPhase !== null && $training->questionsFor($mainPhase)->contains('type', 'essay');
                        @endphp
                        @forelse($participants as $part)
                        @php
                            $user = $part->user;
                            $userName = $user->nama_lengkap ?? ($part->name ?? 'Peserta');
                            $userDivisi = optional(optional($user)->divisi)->name ?? '-';
                            $userEmail = optional($user)->email ?? '-';
                            $userIdKaryawan = optional($user)->employee_id ?? '-';
                            $userNoWa = optional($user)->no_hp ?? '-';
                            // Kolom kuis memuat fase utama; pretest tampil sebagai baris kecil hanya bila ada posttest juga.
                            $quiz = $mainPhase ? $part->resultFor($mainPhase) : null;
                            $pretest = $mainPhase === 'posttest' && $training->has_pretest ? $part->pretestResult : null;
                        @endphp
                        <tr>
                            <td class="text-center align-middle text-muted">
                                {{ $participants->firstItem() + $loop->index }}
                            </td>
                            <td class="align-middle">
                                <span class="d-block font-weight-bold text-gray-950" title="{{ $userEmail }}">{{ $userName }}</span>
                                <span class="cell-sub">{{ $userIdKaryawan }} &middot; {{ $userDivisi }}</span>
                            </td>
                            <td class="align-middle text-nowrap">
                                <span class="d-block">{{ $userNoWa }}</span>
                                <span class="cell-sub">{{ $part->wa_sent_at ? 'WA terkirim' : 'Belum dikirim' }}</span>
                            </td>
                            @if($training->has_attendance || $training->isOnline())
                            <td class="align-middle text-nowrap">
                                @if(! $training->has_attendance)
                                    {{-- Presensi tidak dipakai. --}}
                                @elseif($part->attendance_status === 'hadir')
                                    <span class="status-dot is-success">Hadir</span>
                                    <span class="cell-sub">
                                        {{ \Carbon\Carbon::parse($part->attended_at)->format('H:i') }} WIB
                                        @if($part->attendance_proof)
                                            &middot; <a href="javascript:void(0)" data-toggle="modal" data-target="#proofModal_{{ $part->id }}">Foto</a>
                                        @endif
                                    </span>
                                @elseif($part->attendance_status === 'tidak_hadir')
                                    <span class="status-dot is-danger">Tidak hadir</span>
                                    @if($part->attendance_notes)
                                        <span class="cell-sub text-truncate" style="max-width: 160px;" title="{{ $part->attendance_notes }}">{{ $part->attendance_notes }}</span>
                                    @endif
                                @else
                                    <span class="status-dot">Belum absen</span>
                                @endif

                                <div class="offcam-badge-box" id="offcamBadge_{{ $part->id }}">
                                    @if($part->is_off_cam && ($part->off_cam_count ?? 1) > 0)
                                        <span class="cell-flag is-danger" title="Terdeteksi Off Cam di Zoom{{ $part->zoom_display_name ? ': '.$part->zoom_display_name : '' }}">
                                            <i class="fas fa-video-slash"></i> Off cam {{ $part->off_cam_count ?: 1 }}x
                                        </span>
                                    @endif
                                </div>
                            </td>
                            @endif
                            @if($mainPhase || $training->has_evaluation)
                            <td class="align-middle text-nowrap">
                                @if(! $mainPhase)
                                    {{-- Pelatihan tanpa kuis: kolom ini hanya berisi form evaluasi. --}}
                                @elseif($quiz)
                                    <span class="d-block">Selesai</span>
                                    <span class="cell-sub">
                                        {{ $quiz->correct_answers }} PG benar
                                        @if($quiz->essay_status === 'graded')
                                            &middot; Essay {{ $quiz->essay_score }}
                                        @endif
                                        &middot; {{ \Carbon\Carbon::parse($quiz->submitted_at)->format('d/m H:i') }}
                                    </span>
                                    @if($quiz->essay_status === 'pending')
                                        <span class="cell-flag is-warning"><i class="fas fa-hourglass-half"></i> Essay perlu dinilai</span>
                                    @endif
                                @else
                                    <span class="text-muted">Belum mengerjakan</span>
                                @endif
                                @if($training->has_evaluation || $part->evaluation)
                                    @php
                                        $evaluationAverage = $part->evaluation?->average($training->evaluationQuestions);
                                        $evaluationScore = $evaluationAverage === null ? null : number_format($evaluationAverage, 1).' / '.\App\Support\TrainingEvaluationForm::SCALE_MAX;
                                    @endphp
                                    @if($mainPhase)
                                        <span class="cell-sub">Evaluasi: {{ $part->evaluation ? ($evaluationScore ?? 'terisi') : 'belum' }}</span>
                                    @elseif($part->evaluation)
                                        <span class="d-block">Terisi</span>
                                        <span class="cell-sub">{{ $evaluationScore ? $evaluationScore.' · ' : '' }}{{ $part->evaluation->submitted_at?->format('d/m H:i') }}</span>
                                    @else
                                        <span class="text-muted">Belum mengisi</span>
                                    @endif
                                @endif
                                @if($mainPhase === 'posttest' && $training->has_pretest)
                                    <span class="cell-sub">Pretest: {{ $pretest ? $pretest->score.(($pretest->attempt_count ?? 1) > 1 ? ' (tertinggi dari '.$pretest->attempt_count.'x)' : '') : 'belum' }}</span>
                                    @if($pretest?->essay_status === 'pending')
                                        <span class="cell-flag is-warning"><i class="fas fa-hourglass-half"></i> Essay pretest perlu dinilai</span>
                                    @endif
                                @endif
                            </td>
                            @endif
                            @if($mainPhase)
                            <td class="text-center align-middle text-nowrap">
                                @if($quiz)
                                    <span class="score-value {{ $quiz->score < 50 ? 'is-danger' : ($quiz->score < 75 ? 'is-warning' : '') }}">{{ $quiz->score }}</span>
                                    @if($quiz->essay_status === 'pending')
                                        <span class="cell-sub">skor PG</span>
                                    @endif
                                    @if(($quiz->attempt_count ?? 1) > 1)
                                        <span class="cell-sub" title="Nilai tertinggi dari {{ $quiz->attempt_count }} percobaan">tertinggi dari {{ $quiz->attempt_count }}x</span>
                                    @endif
                                    @if($quiz->is_force_submitted)
                                        <span class="cell-flag is-danger" title="Dikirim otomatis karena kecurangan"><i class="fas fa-exclamation-triangle"></i> Curang (Auto-Submit)</span>
                                    @elseif(($quiz->tab_switch_count ?? 0) > 0)
                                        <span class="cell-flag is-warning" title="Peserta berpindah tab saat mengerjakan"><i class="fas fa-exchange-alt"></i> Pindah tab {{ $quiz->tab_switch_count }}x</span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            @endif
                            <td class="text-center align-middle">
                                <!-- DEFAULT ACTION DROPDOWN -->
                                <div class="dropdown default-action-wrapper">
                                    <button class="btn btn-default btn-xs dropdown-toggle" type="button" data-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false">
                                        Aksi
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" style="max-height: 280px; overflow-y: auto;">
                                        @if($quiz && $quiz->answers)
                                            <a class="dropdown-item answer-sheet-link" href="#" data-url="{{ route('training.answer-sheet', [$training, $quiz]) }}">
                                                Lembar Jawaban {{ $pretest ? 'Posttest' : '' }} {{ $hasEssay && $mainPhase === 'posttest' ? '& Review Essay' : '' }}
                                            </a>
                                        @endif
                                        @if($pretest && $pretest->answers)
                                            <a class="dropdown-item answer-sheet-link" href="#" data-url="{{ route('training.answer-sheet', [$training, $pretest]) }}">
                                                Lembar Jawaban Pretest {{ $pretest->essay_status === 'pending' ? '& Review Essay' : '' }}
                                            </a>
                                        @endif
                                        @if(($quiz && $quiz->answers) || ($pretest && $pretest->answers))
                                            <div class="dropdown-divider"></div>
                                        @endif

                                        <a class="dropdown-item copy-link-btn" href="javascript:void(0)" data-link="{{ url('/training/portal/' . $part->token) }}">
                                            Salin Link Peserta
                                        </a>

                                        <form action="/training/{{ $training->id }}/send-wa/{{ $part->id }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="dropdown-item">
                                                Kirim Notifikasi WA
                                            </button>
                                        </form>

                                        @if($training->has_attendance)
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAttendanceModal_{{ $part->id }}">
                                            Atur Presensi Manual
                                        </a>

                                        @if($part->attendance_status !== 'pending')
                                            <form action="/training/{{ $training->id }}/reset-attendance/{{ $part->id }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Kembalikan status presensi '.$userName.' menjadi Belum Absen?'))">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-danger">
                                                    Reset Presensi (Belum Absen)
                                                </button>
                                            </form>
                                        @endif
                                        @endif

                                        @if($quiz || $pretest || $part->evaluation)
                                            <div class="dropdown-divider"></div>
                                        @endif
                                        @if($quiz || $pretest)
                                            <form action="/training/{{ $training->id }}/reset-quiz/{{ $part->id }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Reset hasil kuis untuk '.$userName.'?'))">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-danger">
                                                    Reset Kuis Peserta
                                                </button>
                                            </form>
                                        @endif
                                        @if($part->evaluation)
                                            <form action="{{ route('training.reset-evaluation', [$training, $part]) }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Reset evaluasi '.$userName.'? Jawaban evaluasinya dihapus dan peserta bisa mengisi lagi.'))">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-danger">
                                                    Reset Evaluasi Peserta
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <!-- TOMBOL TANDAI OFF CAM (STEPPER COUNTER 1-KLIK) -->
                                <div class="quick-offcam-action-wrapper d-none" id="offcamActionWrapper_{{ $part->id }}">
                                    @if(!$part->is_off_cam || ($part->off_cam_count ?? 0) <= 0)
                                        <button type="button" class="btn btn-default btn-xs text-danger text-nowrap btn-offcam-step" data-id="{{ $part->id }}" data-action="increment" data-name="{{ $userName }}" title="Tandai peserta ini Off Cam">
                                            <i class="fas fa-video-slash"></i> Tandai Off Cam
                                        </button>
                                    @else
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-default btn-offcam-step" data-id="{{ $part->id }}" data-action="decrement" data-name="{{ $userName }}" title="Kurangi frekuensi (-1)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <span class="btn btn-default text-danger text-nowrap" style="pointer-events: none;">
                                                <i class="fas fa-video-slash"></i> <span id="offcamCountText_{{ $part->id }}">{{ $part->off_cam_count ?: 1 }}x</span> Off
                                            </span>
                                            <button type="button" class="btn btn-default btn-offcam-step" data-id="{{ $part->id }}" data-action="increment" data-name="{{ $userName }}" title="Tambah frekuensi (+1)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                {{-- Modal per peserta tetap di dalam sel ini: di luar <td> browser memindahkannya keluar tabel dan memisahkan <form> dari isinya. --}}
                                <!-- MODAL BUKTI SCREENSHOT -->
                                @if($part->attendance_proof)
                                <div class="modal fade text-left" id="proofModal_{{ $part->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Bukti Kehadiran Peserta</h5>
                                                    <small class="text-muted">{{ $userName }} &bull; {{ $userDivisi }}</small>
                                                </div>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body text-center">
                                                <div class="mb-2 text-left text-sm text-muted">
                                                    Waktu Presensi: <strong>{{ \Carbon\Carbon::parse($part->attended_at)->format('d F Y, H:i') }} WIB</strong>
                                                </div>
                                                <div class="bg-light p-2 rounded border">
                                                    <a href="{{ $part->attendance_proof_url }}" target="_blank" title="Buka gambar ukuran asli">
                                                        <img src="{{ $part->attendance_proof_url }}" alt="Screenshot Bukti" class="img-fluid rounded" style="max-height: 380px; object-fit: contain;">
                                                    </a>
                                                </div>
                                                <small class="text-muted mt-2 d-block">
                                                    Klik foto di atas untuk membuka ukuran penuh di tab baru.
                                                </small>
                                            </div>
                                            <div class="modal-footer">
                                                <a href="{{ $part->attendance_proof_url }}" target="_blank" download class="btn btn-default">
                                                    Unduh Foto
                                                </a>
                                                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- MODAL ATUR PRESENSI MANUAL -->
                                <div class="modal fade text-left" id="editAttendanceModal_{{ $part->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    Atur Presensi Peserta
                                                </h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <form action="/training/{{ $training->id }}/update-attendance/{{ $part->id }}" method="POST">
                                                @csrf
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <strong class="d-block text-sm">{{ $userName }}</strong>
                                                        <span class="text-muted text-xs">{{ $userIdKaryawan }} &bull; {{ $userDivisi }}</span>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label class="mb-1">Status Kehadiran</label>
                                                        <select name="attendance_status" class="form-control form-control-sm">
                                                            <option value="pending" {{ $part->attendance_status === 'pending' ? 'selected' : '' }}>Belum Absen</option>
                                                            <option value="hadir" {{ $part->attendance_status === 'hadir' ? 'selected' : '' }}>Hadir</option>
                                                            <option value="tidak_hadir" {{ $part->attendance_status === 'tidak_hadir' ? 'selected' : '' }}>Tidak Hadir</option>
                                                        </select>
                                                        <small class="text-muted d-block mt-1">Pilih 'Belum Absen' untuk mengizinkan peserta absen kembali.</small>
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <label class="mb-1">Catatan / Alasan</label>
                                                        <input type="text" name="attendance_notes" class="form-control form-control-sm" value="{{ $part->attendance_notes }}" placeholder="Keterangan izin/sakit/hadir...">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8">
                                @if(filled(request('peserta')))
                                    <x-empty-state icon="fa-search" title="Peserta tidak ditemukan" text="Coba kata kunci lain: nama, ID karyawan, No. HP, atau divisi." />
                                @else
                                    <x-empty-state icon="fa-users" title="Belum ada peserta" text="Daftarkan peserta agar mereka bisa presensi dan mengerjakan kuis pelatihan ini.">
                                        <a href="{{ route('training.edit', $training) }}#peserta" class="btn btn-primary btn-sm">Tambah Peserta</a>
                                    </x-empty-state>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($participants->total() > 0)
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">
                    Menampilkan {{ $participants->firstItem() }}-{{ $participants->lastItem() }} dari {{ $participants->total() }} peserta{{ filled(request('peserta')) ? ' yang cocok' : '' }}
                </span>
                {{ $participants->links('pagination::bootstrap-4') }}
            </div>
            @endif
        </div>

        {{-- MODAL LEMBAR JAWABAN: satu modal untuk semua peserta, isinya dimuat saat dibuka (lihat .answer-sheet-link). --}}
        <div class="modal fade text-left" id="answerSheetModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content" id="answerSheetContent"></div>
            </div>
        </div>
    </div>
</section>

{{-- MODAL UPLOAD VIDEO MATERI PELATIHAN --}}
<div class="modal fade" id="uploadTrainingVideoModal" tabindex="-1" role="dialog" aria-labelledby="uploadTrainingVideoModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="uploadTrainingVideoModalLabel">Upload Video Materi Pelatihan</h5>
                    <small class="text-muted">Video akan otomatis masuk ke Galeri Video Materi dengan judul pelatihan ini.</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('video.store') }}" method="POST" enctype="multipart/form-data" id="uploadTrainingVideoForm">
                @csrf
                <input type="hidden" name="training_id" value="{{ $training->id }}">
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

                <div class="modal-body">
                    {{-- Judul Video Otomatis --}}
                    <div class="form-group mb-3">
                        <label>
                            Judul Video Materi <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" class="form-control" value="{{ $training->title }}" required placeholder="Contoh: {{ $training->title }}">
                        <small class="text-muted">Terisi otomatis sesuai judul sesi pelatihan saat ini.</small>
                    </div>

                    {{-- Pilihan Tipe Video: Upload File vs Tautan Link --}}
                    <div class="form-group mb-3">
                        <label class="d-block">Sumber Video <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="showTypeFile" name="video_type" value="file" class="custom-control-input" checked onchange="toggleVideoTypeInShow('file')">
                                <label class="custom-control-label" for="showTypeFile">
                                    Upload File Video
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="showTypeLink" name="video_type" value="link" class="custom-control-input" onchange="toggleVideoTypeInShow('link')">
                                <label class="custom-control-label" for="showTypeLink">
                                    Tautan / Link (YouTube / Drive / Zoom)
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Group 1: File Input --}}
                    <div class="form-group mb-3" id="showFileInputGroup">
                        <label>Pilih File Video Rekaman <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="video_file" id="showVideoFileInput" class="custom-file-input" accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-matroska" required onchange="updateShowFileName(this)">
                            <label class="custom-file-label" id="showVideoFileLabel" for="showVideoFileInput">Pilih file video (MP4, MKV, WEBM, MOV)...</label>
                        </div>
                        <small class="text-muted">Maksimal ukuran file video: 2 GB.</small>
                    </div>

                    {{-- Group 2: Link Input --}}
                    <div class="form-group mb-3" id="showLinkInputGroup" style="display: none;">
                        <label>URL / Tautan Video <span class="text-danger">*</span></label>
                        <input type="url" name="video_link" id="showVideoLinkInput" class="form-control" placeholder="https://www.youtube.com/watch?v=... atau https://drive.google.com/file/d/...">
                        <small class="text-muted">Mendukung link YouTube, Zoom Cloud Recording, atau Google Drive.</small>
                    </div>

                    {{-- Deskripsi Otomatis --}}
                    <div class="form-group mb-3">
                        <label>Deskripsi & Catatan Sesi (Opsional)</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Rangkuman materi atau topik yang dibahas...">Rekaman video materi sesi pelatihan {{ $training->title }} yang dilaksanakan pada {{ \Carbon\Carbon::parse($training->training_date)->format('d F Y') }}.</textarea>
                    </div>

                    {{-- Thumbnail Cover (Opsional) --}}
                    <div class="form-group mb-0">
                        <label>Cover / Thumbnail Video (Opsional)</label>
                        <div class="custom-file">
                            <input type="file" name="thumbnail" id="showThumbInput" class="custom-file-input" accept="image/png,image/jpeg,image/webp" onchange="updateShowThumbLabel(this)">
                            <label class="custom-file-label" id="showThumbLabel" for="showThumbInput">Pilih gambar thumbnail (JPG, PNG, WEBP)...</label>
                        </div>
                        <small class="text-muted">Biarkan kosong jika ingin menggunakan cover video bawaan sistem.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitVideo">
                        Simpan ke Video Materi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL ATTACH DOKUMEN MATERI PELATIHAN --}}
<div class="modal fade" id="attachDocumentModal" tabindex="-1" role="dialog" aria-labelledby="attachDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attachDocumentModalLabel">Lampirkan Dokumen Materi</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="/training/{{ $training->id }}/attach-document" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group mb-0">
                        <label>Pilih Dokumen <span class="text-danger">*</span></label>
                        <select name="document_ids[]" id="modalDocSelect" class="form-control select2" multiple="multiple" style="width: 100%;" required data-placeholder="-- Pilih dokumen untuk dilampirkan --">
                            @foreach($allDocuments as $d)
                                @php
                                    $alreadyAttached = $training->documents->contains('id', $d->id);
                                @endphp
                                <option value="{{ $d->id }}" {{ $alreadyAttached ? 'selected' : '' }}>
                                    {{ $d->name }} ({{ $d->dokumentype->name ?? 'Dokumen' }}) {{ $alreadyAttached ? '[Sudah Terlampir]' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Simpan Lampiran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL SCAN PRESENSI ZOOM (DETEKSI OFF CAM) --}}
<div class="modal fade" id="scanZoomModal" tabindex="-1" role="dialog" aria-labelledby="scanZoomModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            {{-- MODAL HEADER --}}
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="scanZoomModalLabel">
                        <i class="fas fa-camera-retro text-muted mr-1"></i> Pindai Presensi Zoom: Deteksi Off Cam
                    </h5>
                    <small class="text-muted">
                        Menganalisis screenshot Zoom (hingga 40 gambar) dengan Vision AI untuk mendeteksi peserta yang mematikan kamera dan mencocokkan nama secara otomatis.
                    </small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                {{-- TAHAP 1: UPLOAD & KOMPRESI (CLIENT-SIDE SOLUSI 1) --}}
                <div id="zoomStepUpload">
                    <div class="card mb-0">
                        <div class="card-body">
                            <label for="zoomFileInput" class="zoom-drop-area d-block text-center p-4 m-0 w-100" id="zoomDropArea">
                                <span class="stat-tile-icon mb-3"><i class="fas fa-cloud-upload-alt"></i></span>
                                <span class="section-title d-block mb-1">
                                    Klik untuk Memilih atau Tarik Screenshot / File Word ke Sini
                                </span>
                                <p class="text-muted text-sm mb-2">
                                    Hingga <strong>40 gambar</strong> (JPG, PNG, WEBP) atau file Word <strong>.docx</strong> berisi screenshot.
                                </p>
                                <span class="badge badge-secondary mb-2">
                                    <i class="fas fa-bolt"></i> Solusi 1: Kompresi Otomatis di Browser (~200KB/file, proses super cepat & hemat kuota)
                                </span>
                                <div class="mt-2">
                                    <span class="btn btn-default btn-sm">
                                        <i class="fas fa-folder-open"></i> Pilih Screenshot / File Word
                                    </span>
                                </div>
                            </label>
                            <input type="file" id="zoomFileInput" multiple accept="image/png,image/jpeg,image/webp,.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" style="display: none;">

                            <div class="mt-3 text-center p-2 rounded bg-light border">
                                <span class="text-muted text-sm">Tidak punya screenshot? Ingin mendata langsung?</span>
                                <button type="button" class="btn btn-link btn-sm p-0 ml-1" id="btnDirectManualInput">
                                    <i class="fas fa-edit"></i> Catat Peserta Off Cam Secara Manual &rarr;
                                </button>
                            </div>

                            {{-- ALERT ERROR --}}
                            <div class="alert alert-danger mt-3 d-none" id="zoomUploadAlert"></div>

                            {{-- PREVIEW DAFTAR SCREENSHOT --}}
                            <div class="mt-4 d-none" id="zoomPreviewSection">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="text-sm">
                                        Screenshot Terpilih (<span id="zoomFileCount">0</span>/40):
                                    </strong>
                                    <button type="button" class="btn btn-default btn-xs text-danger" id="btnClearFiles">
                                        <i class="fas fa-trash-alt"></i> Hapus Semua
                                    </button>
                                </div>
                                <div class="row" id="zoomThumbGrid" style="row-gap: 12px;"></div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-default" data-dismiss="modal">
                                Batal
                            </button>
                            <button type="button" class="btn btn-primary" id="btnStartScan" disabled>
                                <i class="fas fa-magic mr-1"></i> Mulai Pindai dengan AI
                            </button>
                        </div>
                    </div>
                </div>

                {{-- TAHAP 2: PROSES LOADING --}}
                <div id="zoomStepLoading" class="d-none text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <h5 class="section-title mb-2" id="zoomLoadingTitle">
                        Menganalisis Screenshot Zoom...
                    </h5>
                    <p class="text-muted text-sm mb-3" id="zoomLoadingSub">
                        Memproses gambar dan mendeteksi kotak video peserta...
                    </p>
                    <div class="progress mx-auto" style="max-width: 450px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                    </div>
                    <div class="mt-3 text-muted text-xs font-italic">
                        Mohon tunggu, AI Vision sedang memindai seluruh tile dan mencocokkan kemiripan nama dengan database peserta kelas ini.
                    </div>
                </div>

                {{-- TAHAP 3: REVIEW & VERIFIKASI MANUAL PENGAWAS --}}
                <div id="zoomStepReview" class="d-none">
                    <div class="alert alert-warning d-flex align-items-start mb-3">
                        <i class="fas fa-user-clock mr-3 mt-1"></i>
                        <div>
                            <strong class="d-block mb-1">
                                Verifikasi Hasil Pindaian AI (<span id="countReviewOffCam">0</span> Peserta Off Cam Terdeteksi)
                            </strong>
                            <span class="text-sm">
                                Hanya peserta yang <strong>mematikan kamera (Off Cam)</strong> yang dicatat. Periksa dan sesuaikan data di bawah sebelum disimpan.
                            </span>
                        </div>
                    </div>

                    {{-- ACTION BAR KONTROL --}}
                    <div class="card mb-3">
                        <div class="card-body py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center flex-wrap gap-3">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="checkSelectAll" checked>
                                    <label class="custom-control-label text-sm" for="checkSelectAll">
                                        Pilih Semua (<span id="textSelectedCount">0</span> terpilih)
                                    </label>
                                </div>
                                <div class="border-left pl-3">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="checkMarkTidakHadir">
                                        <label class="custom-control-label text-muted text-sm" for="checkMarkTidakHadir">
                                            Ubah juga status kehadiran menjadi <strong>"Tidak Hadir"</strong>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-default btn-sm" id="btnAddManualRow">
                                    <i class="fas fa-plus"></i> Tambah Peserta Manual
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- TABEL VERIFIKASI OFF CAM --}}
                    <div class="card mb-3">
                        <div class="card-body table-responsive p-0" style="max-height: 420px;">
                            <table class="table table-hover mb-0 text-sm" id="zoomReviewTable">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 45px;">Pilih</th>
                                        <th class="text-center" style="width: 40px;">No</th>
                                        <th style="min-width: 170px;">Nama di Zoom</th>
                                        <th style="min-width: 250px;">Peserta Terdaftar (Database)</th>
                                        <th class="text-center" style="width: 140px;">Kemiripan AI</th>
                                        <th>Alasan / Bukti Visual</th>
                                        <th class="text-center" style="width: 60px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="zoomReviewTbody">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- FOOTER REVIEW --}}
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2">
                        <button type="button" class="btn btn-default" id="btnBackToUpload">
                            <i class="fas fa-arrow-left"></i> Scan Ulang / Ganti Screenshot
                        </button>
                        <button type="button" class="btn btn-primary" id="btnConfirmSave">
                            <i class="fas fa-check-circle mr-1"></i> Konfirmasi & Simpan Catatan Off Cam
                        </button>
                    </div>
                </div>

                {{-- TAHAP 4: SEMUA ON CAM --}}
                <div id="zoomStepAllOnCam" class="d-none">
                    <x-empty-state icon="fa-check-circle" title="Semua peserta terdeteksi On Cam" text="Tidak ditemukan peserta yang mematikan kamera pada seluruh screenshot Zoom yang diunggah. Semua peserta tampak aktif menyalakan kamera.">
                        <button type="button" class="btn btn-default" data-dismiss="modal">
                            Tutup
                        </button>
                    </x-empty-state>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL QUICK OFF CAM UNTUK SATU PESERTA --}}
<div class="modal fade text-left" id="singleQuickOffCamModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Tandai Off Cam (Manual)
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="quickParticipantId">
                <div class="mb-3">
                    <span class="text-muted text-xs d-block">Nama Peserta</span>
                    <strong class="d-block" id="quickParticipantName">-</strong>
                </div>
                <div class="form-group mb-3">
                    <label class="mb-1">Nama di Zoom / Catatan Sesi</label>
                    <input type="text" id="quickZoomName" class="form-control form-control-sm" placeholder="Contoh: M. Rizky (Off Cam sesi 1)">
                    <small class="text-muted text-xs">Kosongkan jika ingin otomatis memakai nama peserta.</small>
                </div>
                <div class="custom-control custom-checkbox mb-0">
                    <input type="checkbox" class="custom-control-input" id="quickMarkTidakHadir">
                    <label class="custom-control-label text-sm text-muted" for="quickMarkTidakHadir">
                        Ubah status kehadiran menjadi <strong>"Tidak Hadir"</strong>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSaveQuickOffCam">
                    Simpan Off Cam
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer')
<script src="{{ asset('template/plugins/jszip/jszip.min.js') }}"></script>
<script>
function toggleVideoTypeInShow(type) {
    var fileGroup = document.getElementById('showFileInputGroup');
    var linkGroup = document.getElementById('showLinkInputGroup');
    var fileInput = document.getElementById('showVideoFileInput');
    var linkInput = document.getElementById('showVideoLinkInput');

    if (type === 'file') {
        fileGroup.style.display = 'block';
        linkGroup.style.display = 'none';
        fileInput.setAttribute('required', 'required');
        linkInput.removeAttribute('required');
    } else {
        fileGroup.style.display = 'none';
        linkGroup.style.display = 'block';
        fileInput.removeAttribute('required');
        linkInput.setAttribute('required', 'required');
    }
}

function updateShowFileName(input) {
    if (input.files && input.files[0]) {
        document.getElementById('showVideoFileLabel').innerText = input.files[0].name;
    }
}

function updateShowThumbLabel(input) {
    if (input.files && input.files[0]) {
        document.getElementById('showThumbLabel').innerText = input.files[0].name;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const copyButtons = document.querySelectorAll('.copy-link-btn');
    copyButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const link = this.getAttribute('data-link');
            navigator.clipboard.writeText(link).then(() => {
                alert('Tautan akses peserta berhasil disalin:\n' + link);
            }).catch(err => {
                prompt('Salin tautan:', link);
            });
        });
    });

    // Lembar jawaban dimuat saat dibuka, supaya halaman tidak merender semua soal untuk setiap peserta.
    const answerSheetContent = document.getElementById('answerSheetContent');
    document.addEventListener('click', function (e) {
        const link = e.target.closest('.answer-sheet-link');
        if (!link || !answerSheetContent) return;
        e.preventDefault();

        const closeButton = '<button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>';
        answerSheetContent.innerHTML = '<div class="modal-header"><h5 class="modal-title">Lembar Jawaban</h5>' + closeButton + '</div>' +
            '<div class="modal-body text-center text-muted py-5"><span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Memuat lembar jawaban...</div>';
        $('#answerSheetModal').modal('show');

        fetch(link.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                answerSheetContent.innerHTML = html;
            })
            .catch(function () {
                answerSheetContent.innerHTML = '<div class="modal-header"><h5 class="modal-title">Lembar Jawaban</h5>' + closeButton + '</div>' +
                    '<div class="modal-body"><div class="alert alert-danger mb-0">Lembar jawaban gagal dimuat. Muat ulang halaman lalu coba lagi.</div></div>';
            });
    });

    const videoForm = document.getElementById('uploadTrainingVideoForm');
    if (videoForm) {
        let isSubmitting = false;
        videoForm.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
            const submitBtn = document.getElementById('btnSubmitVideo');
            if (submitBtn) {
                submitBtn.style.pointerEvents = 'none';
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Mengunggah Video, mohon tunggu...';
                setTimeout(function() {
                    submitBtn.disabled = true;
                }, 50);
            }
            videoForm.style.pointerEvents = 'none';
        });
    }
});

// ---------------------------------------------------------------
// LIVE STATS POLLING — update counter admin setiap 10 detik
// ---------------------------------------------------------------
(function () {
    const STATS_URL = @js(route('training.live-stats', $training));

    function updateStat(id, value) {
        const el = document.getElementById(id);
        if (el && el.textContent != value) {
            el.textContent = value;
            el.style.transition = 'color 0.3s';
            const orig = el.style.color;
            el.style.color = 'var(--primary-600)';
            setTimeout(function() { el.style.color = orig; }, 600);
        }
    }

    function pollStats() {
        fetch(STATS_URL, { headers: { 'Accept': 'application/json' } })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                updateStat('statTotal',    data.total);
                updateStat('statAttended', data.attended);
                updateStat('statAbsent',   data.absent);
                updateStat('statPending',  data.pending);
                updateStat('statQuiz',     data.quizSubmitted);
                updateStat('statAvg',      data.avgScore);
                updateStat('statPretest',    data.pretestSubmitted);
                updateStat('statPretestAvg', data.pretestAvg);
            })
            .catch(function() { /* silent fail */ });
    }

    setInterval(pollStats, 10000);
})();

// ---------------------------------------------------------------
// HITUNG MUNDUR pretest/posttest berbatas waktu di Kontrol Sesi. Saat habis, barisnya berganti ke "waktu habis"
// dan tombolnya kembali menjadi "Buka"; server sudah menganggap fase itu tertutup.
// ---------------------------------------------------------------
document.querySelectorAll('[data-phase-timer]').forEach(function (row) {
    const endsAt = Date.now() + Number(row.dataset.phaseTimer) * 1000;
    const countdown = row.querySelector('[data-phase-countdown]');

    const timer = setInterval(function () {
        const left = Math.max(0, Math.round((endsAt - Date.now()) / 1000));
        if (left > 0) {
            countdown.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
            return;
        }

        clearInterval(timer);
        row.classList.remove('flex-wrap');
        row.querySelector('[data-phase-status]').textContent = row.dataset.timeoutText;
        row.querySelector('[data-phase-open-controls]').classList.replace('d-flex', 'd-none');
        row.querySelector('[data-phase-closed-controls]').classList.replace('d-none', 'd-flex');
    }, 1000);
});

// Fase terjadwal: saat jam bukanya tiba, statusnya berganti ke "aktif" (server sudah membukanya untuk peserta).
document.querySelectorAll('[data-phase-starts]').forEach(function (row) {
    setTimeout(function () {
        row.querySelector('[data-phase-status]').textContent = row.dataset.openedText;
        row.querySelector('[data-phase-lock]').textContent = row.dataset.lockLabel;
    }, Number(row.dataset.phaseStarts) * 1000);
});

// ---------------------------------------------------------------
// DIALOG URUTAN LANGKAH PESERTA: tombol naik/turun memindahkan baris; urutannya terkirim lewat input steps[].
// ---------------------------------------------------------------
(function () {
    const list = document.getElementById('portalStepsList');
    if (!list) return;
    const note = document.getElementById('portalStepsEvaluationNote');

    function refresh() {
        const items = Array.from(list.children);
        items.forEach(function (item, index) {
            item.querySelector('[data-step-number]').textContent = index + 1;
            item.querySelector('[data-step-move="-1"]').disabled = index === 0;
            item.querySelector('[data-step-move="1"]').disabled = index === items.length - 1;
        });
        const order = items.map(function (item) { return item.dataset.step; });
        note.textContent = order.indexOf('evaluation') < order.indexOf('posttest') ? note.dataset.before : note.dataset.after;
    }

    list.addEventListener('click', function (event) {
        const button = event.target.closest('[data-step-move]');
        if (!button) return;
        const item = button.closest('li');
        if (button.dataset.stepMove === '-1' && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
        } else if (button.dataset.stepMove === '1' && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
        }
        refresh();
        button.focus();
    });

    refresh();
})();

// ---------------------------------------------------------------
// DIALOG BUKA / JADWAL / ATUR WAKTU: satu dialog untuk pretest dan posttest. Tombol pemicunya membawa alamat tujuan,
// fase, dan isian awal (jadwal yang sedang berlaku, atau batas waktu bawaan dari Aturan Kuis).
// ---------------------------------------------------------------
if (typeof $ !== 'undefined') {
    $('#phaseTimeModal').on('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const form = this.querySelector('form');
        if (!trigger) return;

        const pick = function (name, value) {
            form.querySelector('input[name="' + name + '"][value="' + value + '"]').checked = true;
        };
        // "Atur waktu" pada fase yang sudah terbuka hanya mengganti jam tutup, jadi pilihan jam buka disembunyikan.
        const opening = Boolean(trigger.dataset.open);

        form.action = trigger.dataset.url;
        form.elements.phase.value = trigger.dataset.phase;
        form.elements.open.value = trigger.dataset.open || '';
        form.elements.open.disabled = !opening;
        document.getElementById('phaseTimeModalTitle').textContent = trigger.dataset.title;
        document.getElementById('phaseTimeModalSubmit').textContent = trigger.dataset.submit;
        const intro = document.getElementById('phaseTimeModalIntro');
        intro.textContent = trigger.dataset.intro || intro.dataset.default;
        const noneLabel = document.getElementById('phaseNoneLabel');
        noneLabel.textContent = trigger.dataset.noneLabel || noneLabel.dataset.default;

        document.getElementById('phaseOpenChoice').classList.toggle('d-none', !opening);
        form.querySelectorAll('[name="open_mode"], [name="open_time"]').forEach(function (field) { field.disabled = !opening; });
        form.elements.open_time.value = trigger.dataset.openTime || '';
        pick('open_mode', trigger.dataset.openTime ? 'clock' : 'now');
        document.getElementById('phaseMinutesHint').textContent = opening ? 'menit sejak dibuka' : 'menit dari sekarang';

        form.elements.close_time.value = trigger.dataset.time || '';
        form.elements.close_minutes.value = trigger.dataset.minutes || '';
        pick('close_mode', trigger.dataset.time ? 'clock' : (trigger.dataset.minutes ? 'minutes' : 'none'));
    });

    // Mengisi jam atau menit sekaligus memilih opsinya.
    $('#phaseTimeModal [data-pick]').on('focus input', function () {
        const choice = this.dataset.pick.split(':');
        this.form.querySelector('input[name="' + choice[0] + '"][value="' + choice[1] + '"]').checked = true;
    });
}

if (typeof $ !== 'undefined') {
    $(function() {
        if ($.fn.select2) {
            $('#modalDocSelect').select2({
                dropdownParent: $('#attachDocumentModal'),
                placeholder: '-- Pilih dokumen untuk dilampirkan --',
                width: '100%'
            });
        }
    });
}

// ===============================================================
// ZOOM ATTENDANCE AI SCANNER (OFF-CAM DETECTOR) - CLIENT JS
// ===============================================================
(function () {
    const TRAINING_ID = {{ $training->id }};
    const CSRF_TOKEN = '{{ csrf_token() }}';
    const OFFICIAL_PARTICIPANTS = @json($zoomParticipants);

    // DOM Elements
    const dropArea = document.getElementById('zoomDropArea');
    const fileInput = document.getElementById('zoomFileInput');
    const previewSection = document.getElementById('zoomPreviewSection');
    const thumbGrid = document.getElementById('zoomThumbGrid');
    const fileCountEl = document.getElementById('zoomFileCount');
    const btnClearFiles = document.getElementById('btnClearFiles');
    const btnStartScan = document.getElementById('btnStartScan');
    const uploadAlert = document.getElementById('zoomUploadAlert');

    const stepUpload = document.getElementById('zoomStepUpload');
    const stepLoading = document.getElementById('zoomStepLoading');
    const stepReview = document.getElementById('zoomStepReview');
    const stepAllOnCam = document.getElementById('zoomStepAllOnCam');

    const reviewTbody = document.getElementById('zoomReviewTbody');
    const countReviewOffCam = document.getElementById('countReviewOffCam');
    const textSelectedCount = document.getElementById('textSelectedCount');
    const checkSelectAll = document.getElementById('checkSelectAll');
    const checkMarkTidakHadir = document.getElementById('checkMarkTidakHadir');
    const btnAddManualRow = document.getElementById('btnAddManualRow');
    const btnBackToUpload = document.getElementById('btnBackToUpload');
    const btnConfirmSave = document.getElementById('btnConfirmSave');

    const MAX_SCREENSHOTS = 40;
    const SCAN_BATCH_SIZE = 10; // the server accepts at most 10 images per request
    const SCAN_BATCH_MAX_BYTES = 600 * 1024; // base64 characters per request, below common 1 MB body limits
    const SCAN_URL = @json(route('training.scan-zoom-ai', $training));
    const SCAN_STATUS_URL = @json(route('training.scan-zoom-ai.status', [$training, '__TOKEN__']));
    const SCAN_POLL_MS = 3000;
    const SCAN_MAX_WAIT_MS = 15 * 60 * 1000; // same as the queue job timeout
    const SAVE_OFF_CAM_URL = @json(route('training.save-zoom-off-cam', $training));
    const UPDATE_OFF_CAM_COUNT_URL = @json(route('training.update-offcam-count', [$training, '__PARTICIPANT__']));

    let selectedFiles = []; // array of { file, base64, origSize, compSize }
    let loadingInterval = null;

    // Helper: Format bytes to KB/MB
    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    // Helper: Client-side Canvas Image Compression (Solusi 1)
    function compressImage(file, maxWidth = 1600, quality = 0.8) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = e => {
                const img = new Image();
                img.src = e.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    let w = img.width;
                    let h = img.height;
                    if (w > maxWidth) {
                        h = Math.round((h * maxWidth) / w);
                        w = maxWidth;
                    }
                    canvas.width = w;
                    canvas.height = h;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, w, h);
                    const base64 = canvas.toDataURL('image/jpeg', quality);
                    const compSize = Math.round((base64.length - 'data:image/jpeg;base64,'.length) * 0.75);
                    resolve({
                        file: file,
                        name: file.name,
                        origSize: file.size,
                        compSize: compSize,
                        base64: base64
                    });
                };
                img.onerror = err => reject(err);
            };
            reader.onerror = err => reject(err);
        });
    }

    // Drag and Drop Events
    if (dropArea && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
                dropArea.classList.add('is-dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
                dropArea.classList.remove('is-dragover');
            }, false);
        });

        dropArea.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length) {
                handleFilesSelected(dt.files);
            }
        });

        fileInput.addEventListener('change', e => {
            if (e.target.files && e.target.files.length) {
                handleFilesSelected(e.target.files);
            }
        });
    }

    function isDocx(file) {
        return /\.docx$/i.test(file.name);
    }

    // Read the image size so tiny pictures (icons, logos) inside a Word file can be skipped
    function imageWidth(file) {
        return new Promise(resolve => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => { URL.revokeObjectURL(url); resolve(img.width); };
            img.onerror = () => { URL.revokeObjectURL(url); resolve(0); };
            img.src = url;
        });
    }

    // Pull the screenshots out of a .docx, in the order they appear in the document
    async function extractDocxImages(docxFile) {
        if (typeof JSZip === 'undefined') {
            throw new Error('Pembaca file Word belum termuat. Muat ulang halaman lalu coba lagi.');
        }

        const zip = await JSZip.loadAsync(docxFile);
        const mimeByExt = { png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', webp: 'image/webp' };
        const isImagePath = path => /^word\/media\/[^/]+\.(png|jpe?g|webp)$/i.test(path);

        let orderedPaths = [];
        const docXml = zip.file('word/document.xml');
        const relsXml = zip.file('word/_rels/document.xml.rels');
        if (docXml && relsXml) {
            const [doc, rels] = await Promise.all([docXml.async('string'), relsXml.async('string')]);
            const targetById = {};
            for (const m of rels.matchAll(/<Relationship\b[^>]*>/g)) {
                const id = (m[0].match(/\bId="([^"]+)"/) || [])[1];
                const target = (m[0].match(/\bTarget="([^"]+)"/) || [])[1];
                if (id && target) targetById[id] = 'word/' + target.replace(/^\.?\//, '');
            }
            for (const m of doc.matchAll(/r:embed="([^"]+)"/g)) {
                const path = targetById[m[1]];
                if (path && isImagePath(path) && !orderedPaths.includes(path)) orderedPaths.push(path);
            }
        }

        // Fallback: any media file not referenced above, sorted naturally (image2 before image10)
        const remaining = Object.keys(zip.files)
            .filter(path => isImagePath(path) && !orderedPaths.includes(path))
            .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
        orderedPaths = orderedPaths.concat(remaining);

        const baseName = docxFile.name.replace(/\.docx$/i, '');
        const images = [];
        for (const path of orderedPaths) {
            const ext = path.split('.').pop().toLowerCase();
            const blob = await zip.file(path).async('blob');
            const file = new File([blob], `${baseName} #${images.length + 1}.${ext}`, { type: mimeByExt[ext] });
            if (await imageWidth(file) < 200) continue;
            images.push(file);
        }

        return images;
    }

    // Process selected files (images and/or Word documents)
    async function handleFilesSelected(files) {
        uploadAlert.classList.add('d-none');
        uploadAlert.textContent = '';

        const remainingSlots = MAX_SCREENSHOTS - selectedFiles.length;
        if (remainingSlots <= 0) {
            uploadAlert.textContent = `Batas maksimal ${MAX_SCREENSHOTS} screenshot sudah tercapai.`;
            uploadAlert.classList.remove('d-none');
            return;
        }

        btnStartScan.disabled = true;
        btnStartScan.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Membaca file...';

        const validFiles = [];
        const problems = [];
        for (const f of Array.from(files)) {
            if (f.type.startsWith('image/')) {
                validFiles.push(f);
            } else if (isDocx(f)) {
                try {
                    const images = await extractDocxImages(f);
                    if (images.length === 0) {
                        problems.push(`Tidak ada screenshot di dalam "${f.name}".`);
                    }
                    validFiles.push(...images);
                } catch (err) {
                    console.error('Docx read error:', err);
                    problems.push(err.message && err.message.startsWith('Pembaca') ? err.message : `File "${f.name}" tidak bisa dibaca.`);
                }
            }
        }

        if (validFiles.length === 0) {
            uploadAlert.textContent = problems.length
                ? problems.join(' ')
                : 'Harap pilih screenshot (JPG, PNG, WEBP) atau file Word (.docx).';
            uploadAlert.classList.remove('d-none');
            renderThumbnails();
            return;
        }

        const filesToProcess = validFiles.slice(0, remainingSlots);
        if (validFiles.length > remainingSlots) {
            problems.push(`Hanya ${remainingSlots} dari ${validFiles.length} gambar yang dimasukkan (maks. ${MAX_SCREENSHOTS}).`);
        }
        if (problems.length) {
            uploadAlert.textContent = problems.join(' ');
            uploadAlert.classList.remove('d-none');
        }

        btnStartScan.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Mengompresi screenshot...';

        for (const f of filesToProcess) {
            try {
                const compressed = await compressImage(f, 1600, 0.82);
                selectedFiles.push(compressed);
            } catch (err) {
                console.error('Compression error:', err);
            }
        }

        if (fileInput) fileInput.value = '';
        renderThumbnails();
    }

    // Render file thumbnails with compression savings
    function renderThumbnails() {
        if (!previewSection || !thumbGrid) return;

        thumbGrid.innerHTML = '';
        fileCountEl.textContent = selectedFiles.length;

        if (selectedFiles.length === 0) {
            previewSection.classList.add('d-none');
            btnStartScan.disabled = true;
            btnStartScan.innerHTML = '<i class="fas fa-magic mr-1"></i> Mulai Pindai dengan AI';
            return;
        }

        previewSection.classList.remove('d-none');
        btnStartScan.disabled = false;
        btnStartScan.innerHTML = `<i class="fas fa-magic mr-1"></i> Mulai Pindai ${selectedFiles.length} Screenshot dengan AI`;

        selectedFiles.forEach((item, idx) => {
            const savingsPercent = Math.round(((item.origSize - item.compSize) / item.origSize) * 100);
            const col = document.createElement('div');
            col.className = 'col-md-3 col-sm-6';
            col.innerHTML = `
                <div class="card h-100 mb-0 overflow-hidden">
                    <div class="position-relative bg-light" style="height: 110px;">
                        <img src="${item.base64}" alt="Screenshot ${idx + 1}" style="width: 100%; height: 100%; object-fit: cover;">
                        <span class="badge badge-dark position-absolute" style="top: 6px; left: 6px;">
                            #${idx + 1}
                        </span>
                        <button type="button" class="btn btn-default btn-xs text-danger position-absolute remove-thumb-btn" data-index="${idx}" style="top: 6px; right: 6px;" title="Hapus screenshot">
                            &times;
                        </button>
                    </div>
                    <div class="p-2 text-xs">
                        <span class="d-block text-truncate font-weight-bold" title="${item.name}">${item.name}</span>
                        <div class="d-flex justify-content-between align-items-center text-muted mt-1">
                            <span><del>${formatBytes(item.origSize)}</del> &rarr; <strong>${formatBytes(item.compSize)}</strong></span>
                            <span class="badge badge-success">-${savingsPercent > 0 ? savingsPercent : 0}%</span>
                        </div>
                    </div>
                </div>
            `;
            thumbGrid.appendChild(col);
        });

        // Attach remove events
        thumbGrid.querySelectorAll('.remove-thumb-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                const index = parseInt(btn.getAttribute('data-index'), 10);
                selectedFiles.splice(index, 1);
                renderThumbnails();
            });
        });
    }

    // Clear all files
    if (btnClearFiles) {
        btnClearFiles.addEventListener('click', () => {
            selectedFiles = [];
            if (fileInput) fileInput.value = '';
            renderThumbnails();
        });
    }

    // Start AI Scan Action
    if (btnStartScan) {
        btnStartScan.addEventListener('click', () => {
            if (selectedFiles.length === 0) return;

            // Switch to loading step
            stepUpload.classList.add('d-none');
            stepLoading.classList.remove('d-none');
            stepReview.classList.add('d-none');
            stepAllOnCam.classList.add('d-none');

            // Rotating status text
            const loadingMessages = [
                'Mengirim ' + selectedFiles.length + ' screenshot Zoom ke Google Gemini Vision...',
                'AI sedang membaca kotak-kotak galeri Zoom...',
                'Mendeteksi peserta yang mematikan kamera (layar hitam, inisial, avatar)...',
                'Membaca teks nama di setiap kotak yang Off Cam...',
                'Mencocokkan nama akun Zoom dengan daftar resmi peserta pelatihan...',
                'Menghitung skor kemiripan dan menyusun hasil verifikasi...'
            ];
            let msgIdx = 0;
            const subEl = document.getElementById('zoomLoadingSub');
            if (subEl) subEl.textContent = loadingMessages[0];

            loadingInterval = setInterval(() => {
                msgIdx = (msgIdx + 1) % loadingMessages.length;
                if (subEl) subEl.textContent = loadingMessages[msgIdx];
            }, 3000);

            // Send the screenshots in small batches, one after another
            const batches = buildScanBatches(selectedFiles.map(item => item.base64));
            const titleEl = document.getElementById('zoomLoadingTitle');

            (async () => {
                const collected = [];
                let failure = null;

                for (let b = 0; b < batches.length; b++) {
                    if (titleEl) {
                        titleEl.textContent = batches.length > 1
                            ? `Menganalisis Screenshot Zoom... (batch ${b + 1}/${batches.length})`
                            : 'Menganalisis Screenshot Zoom...';
                    }

                    try {
                        collected.push(...await scanImages(batches[b]));
                    } catch (err) {
                        console.error(err);
                        failure = err.message || 'Terjadi kendala saat memproses screenshot dengan AI.';
                        if (batches.length > 1) failure = `Batch ${b + 1}/${batches.length} gagal: ${failure}`;
                        break;
                    }
                }

                clearInterval(loadingInterval);
                stepLoading.classList.add('d-none');
                if (titleEl) titleEl.textContent = 'Menganalisis Screenshot Zoom...';

                const offCamList = mergeOffCamResults(collected);

                // Nothing usable came back: return to the upload step with the error
                if (failure && offCamList.length === 0) {
                    stepUpload.classList.remove('d-none');
                    uploadAlert.textContent = failure;
                    uploadAlert.classList.remove('d-none');
                    return;
                }

                if (offCamList.length === 0) {
                    stepAllOnCam.classList.remove('d-none');
                } else {
                    renderReviewTable(offCamList);
                    stepReview.classList.remove('d-none');
                }

                if (failure) {
                    alert(failure + '\n\nHasil dari batch sebelumnya tetap ditampilkan.');
                }
            })();
        });
    }

    // Group images so each request stays small: web servers often reject bodies above ~1 MB
    function buildScanBatches(images) {
        const batches = [];
        let current = [];
        let currentBytes = 0;
        images.forEach(img => {
            if (current.length && (current.length >= SCAN_BATCH_SIZE || currentBytes + img.length > SCAN_BATCH_MAX_BYTES)) {
                batches.push(current);
                current = [];
                currentBytes = 0;
            }
            current.push(img);
            currentBytes += img.length;
        });
        if (current.length) batches.push(current);
        return batches;
    }

    // Scan one batch. If the server says the request is too large, split it in half and retry.
    async function scanImages(images) {
        let res;
        try {
            res = await fetch(SCAN_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ images })
            });
        } catch (err) {
            throw new Error('Tidak bisa terhubung ke server. Periksa koneksi internet lalu coba lagi.');
        }

        if (res.status === 413 && images.length > 1) {
            const half = Math.ceil(images.length / 2);
            const first = await scanImages(images.slice(0, half));
            const second = await scanImages(images.slice(half));
            return first.concat(second);
        }

        let data = null;
        try {
            data = await res.json();
        } catch (err) {
            data = null;
        }

        if (res.ok && data && data.success && data.token) {
            return await waitForScanResult(data.token);
        }

        if (data && data.message) throw new Error(data.message);
        throw new Error(describeHttpError(res.status));
    }

    // The scan runs on the server queue (the AI can take minutes): poll until this batch is finished.
    async function waitForScanResult(token) {
        const statusUrl = SCAN_STATUS_URL.replace('__TOKEN__', encodeURIComponent(token));
        const deadline = Date.now() + SCAN_MAX_WAIT_MS;

        while (Date.now() < deadline) {
            await new Promise(resolve => setTimeout(resolve, SCAN_POLL_MS));

            let data = null;
            try {
                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                data = await res.json();
            } catch (err) {
                continue; // brief network hiccup: keep waiting
            }

            if (data.status === 'done') {
                return (data.data && data.data.off_cam_participants) || [];
            }
            if (data.status === 'failed') {
                throw new Error(data.message || 'Scan screenshot gagal diproses. Coba lagi.');
            }
        }

        throw new Error('Scan belum selesai setelah 15 menit. Pastikan antrean (Horizon) berjalan, lalu coba lagi.');
    }

    function describeHttpError(status) {
        if (status === 413) return 'Ukuran screenshot terlalu besar untuk server (HTTP 413). Minta admin server menaikkan batas upload.';
        if (status === 419) return 'Sesi sudah habis (HTTP 419). Muat ulang halaman lalu coba lagi.';
        if (status === 401 || status === 403) return `Akses ditolak (HTTP ${status}). Muat ulang halaman dan pastikan masih login.`;
        if (status === 404) return 'Alamat pemindai tidak ditemukan (HTTP 404).';
        if (status === 502 || status === 503 || status === 504) return `Server terlalu lama merespons (HTTP ${status}). Coba lagi dengan lebih sedikit screenshot.`;
        return `Server mengembalikan error (HTTP ${status}). Coba lagi.`;
    }

    // Combine results from several batches: one row per matched participant (highest score wins),
    // unmatched Zoom names are de-duplicated by name.
    function mergeOffCamResults(list) {
        const byKey = new Map();
        list.forEach(item => {
            const key = item.participant_id
                ? 'p:' + item.participant_id
                : 'z:' + String(item.zoom_name || '').trim().toLowerCase();
            const existing = byKey.get(key);
            if (!existing || (item.similarity_score || 0) > (existing.similarity_score || 0)) {
                byKey.set(key, item);
            }
        });
        return Array.from(byKey.values());
    }

    // Render Review Table
    function renderReviewTable(detectedList) {
        if (!reviewTbody) return;
        reviewTbody.innerHTML = '';
        countReviewOffCam.textContent = detectedList.length;

        detectedList.forEach((item, idx) => {
            const tr = createReviewRow(idx + 1, item);
            reviewTbody.appendChild(tr);
        });

        updateSelectedCount();
    }

    // Create a row for the review table
    function createReviewRow(rowNo, item) {
        const tr = document.createElement('tr');
        tr.className = 'review-item-row align-middle';

        // Check if item is matched to a participant
        const isMatched = !!item.participant_id;
        const isChecked = isMatched;

        // Build select options
        let optionsHtml = '<option value="">-- Bukan Peserta Pelatihan (Abaikan) --</option>';
        OFFICIAL_PARTICIPANTS.forEach(p => {
            const selected = (p.id == item.participant_id) ? 'selected' : '';
            optionsHtml += `<option value="${p.id}" ${selected}>${p.name} (${p.id_karyawan}) - ${p.divisi}</option>`;
        });

        // Similarity badge styling
        const score = item.similarity_score || 0;
        let scoreBadgeClass = 'badge-secondary';
        let scoreLabel = 'Rendah';
        if (score >= 80) {
            scoreBadgeClass = 'badge-success';
            scoreLabel = 'Sangat Mirip';
        } else if (score >= 65) {
            scoreBadgeClass = 'badge-warning';
            scoreLabel = 'Mirip';
        }

        tr.innerHTML = `
            <td class="text-center align-middle">
                <input type="checkbox" class="row-checkbox" ${isChecked ? 'checked' : ''} style="cursor: pointer;">
            </td>
            <td class="text-center align-middle text-muted row-number">
                ${rowNo}
            </td>
            <td class="align-middle">
                <input type="text" class="form-control form-control-sm zoom-name-input" value="${escapeHtml(item.zoom_name || '')}" placeholder="Nama di Zoom...">
            </td>
            <td class="align-middle">
                <select class="form-control form-control-sm participant-select">
                    ${optionsHtml}
                </select>
            </td>
            <td class="text-center align-middle">
                <span class="badge ${scoreBadgeClass} similarity-badge" title="${escapeHtml(item.match_reason || '')}">
                    ${score}% &bull; ${scoreLabel}
                </span>
            </td>
            <td class="align-middle text-xs text-muted">
                <div class="evidence-text text-dark">${escapeHtml(item.visual_evidence || 'Layar mati/avatar')}</div>
                <div class="reason-text text-muted">${escapeHtml(item.match_reason || '-')}</div>
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-default btn-xs text-danger btn-delete-row" title="Hapus baris">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;

        // Event: when participant select changes
        const selectEl = tr.querySelector('.participant-select');
        const checkEl = tr.querySelector('.row-checkbox');
        const badgeEl = tr.querySelector('.similarity-badge');

        selectEl.addEventListener('change', () => {
            selectEl.classList.remove('is-invalid');
            if (selectEl.value) {
                checkEl.checked = true;
                if (!item.participant_id || selectEl.value != item.participant_id) {
                    badgeEl.className = 'badge badge-primary similarity-badge';
                    badgeEl.innerHTML = '100% &bull; Manual';
                }
            } else {
                checkEl.checked = false;
            }
            updateSelectedCount();
        });

        checkEl.addEventListener('change', () => {
            if (!checkEl.checked) selectEl.classList.remove('is-invalid');
            updateSelectedCount();
        });

        tr.querySelector('.btn-delete-row').addEventListener('click', () => {
            tr.remove();
            renumberReviewRows();
            updateSelectedCount();
        });

        return tr;
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Renumber rows after delete
    function renumberReviewRows() {
        if (!reviewTbody) return;
        const rows = reviewTbody.querySelectorAll('.review-item-row');
        rows.forEach((r, i) => {
            const numEl = r.querySelector('.row-number');
            if (numEl) numEl.textContent = i + 1;
        });
        countReviewOffCam.textContent = rows.length;
    }

    // Update selected count indicator
    function updateSelectedCount() {
        if (!reviewTbody) return;
        const checkedBoxes = reviewTbody.querySelectorAll('.row-checkbox:checked');
        const totalRows = reviewTbody.querySelectorAll('.review-item-row').length;
        textSelectedCount.textContent = checkedBoxes.length;

        if (checkSelectAll) {
            checkSelectAll.checked = (totalRows > 0 && checkedBoxes.length === totalRows);
        }

        if (btnConfirmSave) {
            btnConfirmSave.disabled = (checkedBoxes.length === 0);
        }
    }

    // Master checkbox toggle
    if (checkSelectAll) {
        checkSelectAll.addEventListener('change', e => {
            const isChecked = e.target.checked;
            reviewTbody.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = isChecked;
            });
            updateSelectedCount();
        });
    }

    // Add manual row button
    if (btnAddManualRow) {
        btnAddManualRow.addEventListener('click', () => {
            const currentTotal = reviewTbody.querySelectorAll('.review-item-row').length;
            const newTr = createReviewRow(currentTotal + 1, {
                zoom_name: 'Input Manual',
                participant_id: null,
                similarity_score: 100,
                match_reason: 'Ditambahkan manual oleh pengawas',
                visual_evidence: 'Pengamatan pengawas',
                confidence: 'high'
            });
            reviewTbody.appendChild(newTr);
            renumberReviewRows();
            updateSelectedCount();
            newTr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

    // Back to upload step
    if (btnBackToUpload) {
        btnBackToUpload.addEventListener('click', () => {
            stepReview.classList.add('d-none');
            stepUpload.classList.remove('d-none');
        });
    }

    // Confirm and Save action
    if (btnConfirmSave) {
        btnConfirmSave.addEventListener('click', () => {
            const rows = reviewTbody.querySelectorAll('.review-item-row');
            const itemsToSave = [];
            const unmatchedSelects = [];

            rows.forEach(r => {
                const isChecked = r.querySelector('.row-checkbox').checked;
                const selectEl = r.querySelector('.participant-select');
                const participantId = selectEl.value;
                const zoomName = r.querySelector('.zoom-name-input').value;
                const reason = r.querySelector('.reason-text').textContent;

                selectEl.classList.remove('is-invalid');
                if (!isChecked) return;

                if (!participantId) {
                    selectEl.classList.add('is-invalid');
                    unmatchedSelects.push(selectEl);
                    return;
                }

                itemsToSave.push({
                    participant_id: participantId,
                    zoom_name: zoomName || '-',
                    reason: reason || 'Terdeteksi Off Cam di Zoom'
                });
            });

            // Checked rows that are not linked to a participant cannot be saved: say so instead of skipping them
            if (unmatchedSelects.length > 0) {
                const n = unmatchedSelects.length;
                alert(itemsToSave.length === 0 && n === rows.length
                    ? `Semua ${n} nama belum dipasangkan ke peserta pelatihan ini.\n\nPilih nama pesertanya di kolom "Peserta Terdaftar" (kotak merah). Kalau orangnya memang bukan peserta pelatihan ini, hapus centangnya.`
                    : `${n} baris yang dicentang belum dipasangkan ke peserta.\n\nPilih nama pesertanya di kolom "Peserta Terdaftar" (kotak merah), atau hapus centangnya.`);
                unmatchedSelects[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                unmatchedSelects[0].focus();
                return;
            }

            if (itemsToSave.length === 0) {
                alert('Belum ada baris yang dicentang.');
                return;
            }

            const markTidakHadir = checkMarkTidakHadir ? checkMarkTidakHadir.checked : false;

            btnConfirmSave.disabled = true;
            btnConfirmSave.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Menyimpan data presensi...';

            fetch(SAVE_OFF_CAM_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    off_cam_items: itemsToSave,
                    mark_as_tidak_hadir: markTidakHadir
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Catatan Off Cam berhasil disimpan!');
                    window.location.reload();
                } else {
                    alert('Gagal menyimpan: ' + (data.message || 'Terjadi kesalahan.'));
                    btnConfirmSave.disabled = false;
                    btnConfirmSave.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Konfirmasi & Simpan Catatan Off Cam';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Gagal terhubung ke server saat menyimpan.');
                btnConfirmSave.disabled = false;
                btnConfirmSave.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Konfirmasi & Simpan Catatan Off Cam';
            });
        });
    }

    // Direct manual input mode function
    function openManualInputMode() {
        stepUpload.classList.add('d-none');
        stepLoading.classList.add('d-none');
        stepAllOnCam.classList.add('d-none');
        stepReview.classList.remove('d-none');

        // If table is currently empty, add an initial manual row
        if (reviewTbody.querySelectorAll('.review-item-row').length === 0) {
            const newTr = createReviewRow(1, {
                zoom_name: '',
                participant_id: null,
                similarity_score: 100,
                match_reason: 'Dicatat manual oleh pengawas',
                visual_evidence: 'Pengamatan langsung pengawas',
                confidence: 'high'
            });
            reviewTbody.appendChild(newTr);
            renumberReviewRows();
            updateSelectedCount();
        }
    }

    const btnDirectManualInput = document.getElementById('btnDirectManualInput');
    if (btnDirectManualInput) {
        btnDirectManualInput.addEventListener('click', openManualInputMode);
    }

    const btnOpenManualTable = document.getElementById('btnOpenManualTable');
    if (btnOpenManualTable) {
        btnOpenManualTable.addEventListener('click', () => {
            if (typeof $ !== 'undefined') {
                $('#scanZoomModal').modal('show');
            }
            openManualInputMode();
        });
    }

    // ===============================================================
    // MODE CEPAT INPUT OFF CAM MANUAL (1-KLIK DI TABEL PESERTA)
    // ===============================================================
    let isManualOffCamModeActive = false;
    // Diingat per pelatihan supaya mode tetap aktif saat pindah halaman daftar peserta.
    const MANUAL_OFF_CAM_KEY = 'training.manualOffCam.' + TRAINING_ID;

    function setManualOffCamMode(active) {
        isManualOffCamModeActive = active;
        try {
            active ? sessionStorage.setItem(MANUAL_OFF_CAM_KEY, '1') : sessionStorage.removeItem(MANUAL_OFF_CAM_KEY);
        } catch (e) {}
        const defaultWrappers = document.querySelectorAll('.default-action-wrapper');
        const quickWrappers = document.querySelectorAll('.quick-offcam-action-wrapper');
        const banner = document.getElementById('bannerManualOffCamMode');
        const btnToggle = document.getElementById('btnToggleManualOffCamMode');
        const thAction = document.getElementById('thActionHeader');

        if (active) {
            defaultWrappers.forEach(el => el.classList.add('d-none'));
            quickWrappers.forEach(el => el.classList.remove('d-none'));
            if (banner) {
                banner.classList.remove('d-none');
                banner.classList.add('d-flex');
            }
            if (btnToggle) {
                btnToggle.className = 'btn btn-outline-danger btn-sm';
                btnToggle.innerHTML = '<i class="fas fa-times"></i> Selesai Mode Manual';
            }
            if (thAction) {
                thAction.textContent = 'Status Kamera (Klik)';
                thAction.style.width = '160px';
            }
        } else {
            defaultWrappers.forEach(el => el.classList.remove('d-none'));
            quickWrappers.forEach(el => el.classList.add('d-none'));
            if (banner) {
                banner.classList.add('d-none');
                banner.classList.remove('d-flex');
            }
            if (btnToggle) {
                btnToggle.className = 'btn btn-default btn-sm';
                btnToggle.innerHTML = '<i class="fas fa-user-edit"></i> Input Off Cam Manual';
            }
            if (thAction) {
                thAction.textContent = 'Aksi';
                thAction.style.width = '110px';
            }
        }
    }

    try {
        if (sessionStorage.getItem(MANUAL_OFF_CAM_KEY) === '1') setManualOffCamMode(true);
    } catch (e) {}

    document.addEventListener('click', function (e) {
        if (e.target.closest('#btnToggleManualOffCamMode')) {
            e.preventDefault();
            setManualOffCamMode(!isManualOffCamModeActive);
        } else if (e.target.closest('#btnExitManualOffCamMode')) {
            e.preventDefault();
            setManualOffCamMode(false);
        }
    });

    // 1-Click Stepper Listener on Participants Table
    const participantsTable = document.getElementById('participantsTable');
    if (participantsTable) {
        participantsTable.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-offcam-step');
            if (!btn) return;

            const partId = btn.getAttribute('data-id');
            const partName = btn.getAttribute('data-name') || 'Peserta';
            const action = btn.getAttribute('data-action') || 'increment';

            if (!partId) return;

            const wrapper = document.getElementById(`offcamActionWrapper_${partId}`);
            if (btn.disabled) return;
            btn.disabled = true;

            fetch(UPDATE_OFF_CAM_COUNT_URL.replace('__PARTICIPANT__', encodeURIComponent(partId)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ action: action })
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    const count = data.off_cam_count || 0;

                    // Update action stepper UI in participant row
                    if (wrapper) {
                        if (count <= 0) {
                            wrapper.innerHTML = `
                                <button type="button" class="btn btn-default btn-xs text-danger text-nowrap btn-offcam-step" data-id="${partId}" data-action="increment" data-name="${partName}" title="Tandai peserta ini Off Cam">
                                    <i class="fas fa-video-slash"></i> Tandai Off Cam
                                </button>
                            `;
                        } else {
                            wrapper.innerHTML = `
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-default btn-offcam-step" data-id="${partId}" data-action="decrement" data-name="${partName}" title="Kurangi frekuensi (-1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="btn btn-default text-danger text-nowrap" style="pointer-events: none;">
                                        <i class="fas fa-video-slash"></i> <span id="offcamCountText_${partId}">${count}x</span> Off
                                    </span>
                                    <button type="button" class="btn btn-default btn-offcam-step" data-id="${partId}" data-action="increment" data-name="${partName}" title="Tambah frekuensi (+1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            `;
                        }
                    }

                    // Update badge di kolom Presensi
                    const badgeBox = document.getElementById(`offcamBadge_${partId}`);
                    if (badgeBox) {
                        if (count > 0) {
                            badgeBox.innerHTML = `
                                <span class="cell-flag is-danger" title="Terdeteksi Off Cam di Zoom">
                                    <i class="fas fa-video-slash"></i> Off cam ${count}x
                                </span>
                            `;
                        } else {
                            badgeBox.innerHTML = '';
                        }
                    }
                } else {
                    alert('Gagal mengupdate frekuensi Off Cam: ' + (data.message || 'Terjadi kesalahan'));
                }
            })
            .catch(err => {
                console.error(err);
                btn.disabled = false;
                alert('Gagal menghubungi server.');
            });
        });
    }
})();
</script>
@endsection
