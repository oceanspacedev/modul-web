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
    $mainPhase = $training->mainPhase();
    $mainPhaseLabel = $training->mainPhaseLabel();
    $meetingLabel = $training->meetingPlatformLabel() ?? 'Meeting';
    // Kamera hanya ditampilkan bila memang ada datanya (rekap/pemantauan), supaya tidak muncul kolom berisi "—" saja.
    $showsCamera = $training->isOnline() && ($hasCameraStatus || $canMonitorCamera);
    $hasAttendanceColumn = $training->has_attendance || $showsCamera;
    // Ringkasan dan kolom Evaluasi hanya selama formnya dipakai; isian lama tetap terbaca di tab Evaluasi.
    $showsEvaluation = $training->has_evaluation && $evaluation !== null;
    $participantColumnCount = 2 + (int) $hasAttendanceColumn + (int) $training->has_pretest + (int) $training->has_posttest + (int) $showsEvaluation;
    $quizStats = [];
    if ($training->has_pretest && $training->has_posttest) {
        $quizStats['Pretest'] = ['submitted' => 'pretestSubmitted', 'average' => 'pretestAvg'];
    }
    if ($mainPhase) {
        $quizStats[$mainPhaseLabel] = ['submitted' => 'quizSubmitted', 'average' => 'avgScore'];
    }
    $isClosed = in_array($training->status, [App\Enums\TrainingStatus::Completed, App\Enums\TrainingStatus::Cancelled], true);
    $needsQuizQuestions = ! $isClosed && $training->hasQuiz() && $training->questions->isEmpty();
    $needsTrainingSetup = ! $isClosed && $training->needsSetup();
    $invalidKeyCount = $training->hasQuiz() ? $training->questions->reject->hasValidKey()->count() : 0;
    $failedWaOnly = request('wa') === 'gagal';
    $sectionLinks = [
        'peserta' => 'Peserta',
        'kontrol-sesi' => 'Kontrol sesi',
        'materi' => 'Materi',
        ...($evaluation ? ['hasil-evaluasi' => 'Evaluasi'] : []),
    ];
    $activeSection = request()->string('bagian', strict: true)->toString();
    $activeSection = array_key_exists($activeSection, $sectionLinks) ? $activeSection : 'peserta';
    $rosterQuery = array_filter([
        'peserta' => request('peserta'),
        'wa' => $failedWaOnly ? 'gagal' : null,
        'per_halaman' => $perPage !== \App\Http\Controllers\TrainingController::PARTICIPANTS_PER_PAGE ? $perPage : null,
    ], filled(...));
    $sectionQuery = [...$rosterQuery, ...request()->only('page')];
@endphp

<section class="content-header training-detail-header">
    <div class="container-fluid">
        <a class="training-back-link" href="{{ route('training.index') }}">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Jadwal pelatihan
        </a>
        <div class="training-heading">
            <div>
                <h1 class="training-title">{{ $training->title }}</h1>
                {{-- Satu baris info sesi: jadwal, status, pemateri, dan tempat (link meeting atau tatap muka). --}}
                <p class="training-session-meta">
                    <span>{{ $training->training_date->translatedFormat('j F Y') }} · {{ substr($training->start_time, 0, 5) }}–{{ substr($training->end_time, 0, 5) }} WIB</span>
                    <x-training-status-badge :status="$training->status" />
                    @if($training->trainer)
                        <span>Pemateri <strong>{{ $training->trainer->nama_lengkap }}</strong>@if($training->trainer->divisi) · {{ $training->trainer->divisi->name }}@endif</span>
                    @endif
                    @if($training->meeting_link)
                        <a href="{{ $training->meeting_link }}" target="_blank" rel="noopener">Buka {{ $training->meetingPlatformLabel() }} <i class="fas fa-external-link-alt" aria-hidden="true"></i></a>
                    @else
                        <span>Tatap muka</span>
                    @endif
                </p>
            </div>
            <div class="training-panel-actions">
                @if($training->hasQuiz())
                    <a href="{{ route('training.questions.index', $training) }}" class="btn btn-default">Soal Kuis ({{ $training->questions->count() }})</a>
                @endif
                <div class="dropdown">
                    <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">
                        Opsi pelatihan
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="{{ route('training.edit', $training) }}">Edit informasi</a>
                        <form action="{{ route('training.export', $training) }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item">Ekspor rekap</button>
                        </form>
                        @if($training->hasQuiz() && $stats['quizSubmitted'] + $stats['pretestSubmitted'] > 0)
                            <form action="{{ route('training.regrade', $training) }}" method="POST" class="m-0"
                                onsubmit="return confirm('Hitung ulang nilai semua peserta memakai kunci jawaban saat ini? Jawaban peserta dan nilai esai tidak berubah.')">
                                @csrf
                                <button type="submit" class="dropdown-item">Hitung ulang nilai</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="content training-detail">
    <div class="container-fluid">
        <x-flash />

        @if($invalidKeyCount > 0)
            <div class="alert alert-danger">
                <strong>{{ $invalidKeyCount }} soal pilihan ganda punya kunci jawaban yang tidak valid.</strong>
                Jawaban pada soal tersebut dihitung salah. <a href="{{ route('training.questions.index', $training) }}" class="alert-link">Perbaiki kunci jawaban</a>, lalu pilih <strong>Hitung ulang nilai</strong> di Opsi pelatihan jika sudah ada hasil.
            </div>
        @endif

        @if($needsQuizQuestions || $needsTrainingSetup)
            <div class="training-notice training-notice-warning">
                <span>Persiapan sesi belum lengkap</span>
                <div class="training-panel-actions">
                    @if($needsQuizQuestions)
                        <a href="{{ route('training.questions.index', $training) }}">Siapkan soal kuis</a>
                    @endif
                    @if($needsTrainingSetup)
                        <a href="{{ route('training.setup', $training) }}">Lengkapi pengaturan</a>
                    @endif
                </div>
            </div>
        @endif

        {{-- Ringkasan angka tampil di atas tab, sesuai fitur yang dipakai pelatihan ini. --}}
        @if($training->has_attendance || $quizStats || $showsEvaluation)
            <dl class="training-totals" style="--stat-count: {{ (int) $training->has_attendance + count($quizStats) + (int) $showsEvaluation }}">
                @if($training->has_attendance)
                    <div>
                        <dt>Kehadiran</dt>
                        <dd class="training-total-value">
                            <strong id="statAttended">{{ $stats['attended'] }}</strong>
                            <span aria-hidden="true">/</span><span class="sr-only">hadir dari</span>
                            <span data-training-stat="total">{{ $stats['total'] }}</span>
                        </dd>
                        <dd class="training-total-meta"><span id="statPending">{{ $stats['pending'] }}</span> belum absen · <span id="statAbsent">{{ $stats['absent'] }}</span> tidak hadir</dd>
                    </div>
                @endif
                @foreach($quizStats as $label => $keys)
                    <div>
                        <dt>{{ $label }}</dt>
                        <dd class="training-total-value">
                            <strong data-training-stat="{{ $keys['submitted'] }}">{{ $stats[$keys['submitted']] }}</strong>
                            <span aria-hidden="true">/</span><span class="sr-only">selesai dari</span>
                            <span data-training-stat="total">{{ $stats['total'] }}</span>
                        </dd>
                        {{-- Diperbarui polling live stats: rata-rata baru muncul setelah ada yang mengerjakan. --}}
                        <dd class="training-total-meta" data-training-average="{{ $keys['average'] }}" data-training-count="{{ $keys['submitted'] }}">
                            @if($stats[$keys['submitted']] > 0)
                                Rata-rata <b data-training-stat="{{ $keys['average'] }}">{{ number_format($stats[$keys['average']], 1, ',', '.') }}</b>
                            @else
                                Belum ada yang mengerjakan
                            @endif
                        </dd>
                    </div>
                @endforeach
                @if($showsEvaluation)
                    <div>
                        <dt>Evaluasi</dt>
                        <dd class="training-total-value">
                            <strong>{{ $evaluation['count'] }}</strong>
                            <span aria-hidden="true">/</span><span class="sr-only">mengisi dari</span>
                            <span>{{ $stats['total'] }}</span>
                        </dd>
                        <dd class="training-total-meta">
                            @if($evaluation['average'] !== null)
                                Rata-rata <span class="text-nowrap"><b>{{ number_format($evaluation['average'], 2, ',', '.') }}</b> / {{ \App\Support\TrainingEvaluationForm::SCALE_MAX }}</span>
                            @elseif($evaluation['questionCount'] === 0)
                                Belum ada pertanyaan
                            @else
                                Belum ada yang mengisi
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        @endif

        <nav class="training-sections" aria-label="Bagian pelatihan">
            @foreach($sectionLinks as $section => $label)
                <a href="{{ route('training.show', [$training, ...$sectionQuery, ...($section === 'peserta' ? [] : ['bagian' => $section])]) }}"
                    data-training-section="{{ $section }}" @if($activeSection === $section) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

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

        <!-- MODAL IMPORT PESERTA DARI EXCEL -->
        <div class="modal fade" id="importParticipantsModal" tabindex="-1" role="dialog" aria-labelledby="importParticipantsModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form action="{{ route('training.participants.import', $training) }}" method="POST" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="importParticipantsModalTitle">Import Peserta dari Excel</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <a href="{{ route('training.participants.template', $training) }}" class="btn btn-default btn-block mb-3">
                            <i class="fas fa-download"></i> Unduh Template Excel (.xlsx)
                        </a>
                        <div class="form-group">
                            <label for="importParticipantsFile">File Spreadsheet (.xlsx, .xls, .csv)</label>
                            <input type="file" name="file" id="importParticipantsFile" class="form-control" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="custom-control custom-checkbox mb-3">
                            <input type="checkbox" class="custom-control-input" id="importParticipantsSendWa" name="send_wa" value="1">
                            <label class="custom-control-label font-weight-normal" for="importParticipantsSendWa">Kirim undangan WhatsApp ke peserta yang baru ditambahkan</label>
                        </div>
                        @include('training.partials.participant-import-help')
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Import Peserta</button>
                    </div>
                </form>
            </div>
        </div>

        <section class="training-panel" id="peserta" aria-labelledby="training-roster-title" data-training-panel @if($activeSection !== 'peserta') hidden @endif>
            <header class="training-panel-header training-roster-header">
                <h2 class="training-section-title" id="training-roster-title">
                    {{ $training->hasQuiz() ? 'Daftar Peserta & Nilai' : 'Daftar Peserta' }}
                    <span class="training-section-count" id="statTotal">({{ $stats['total'] }} orang)</span>
                </h2>
                <div class="training-panel-actions">
                    @if($showMeetingActivity)
                        <a href="{{ route('training.meeting.show', $training) }}" class="btn btn-default btn-sm">
                            <i class="fas fa-video mr-1" aria-hidden="true"></i> Aktivitas {{ $meetingLabel }}
                        </a>
                    @endif
                    <a href="{{ route('training.edit', $training) }}#peserta" class="btn btn-primary btn-sm">Tambah peserta</a>
                    <div class="dropdown">
                        <button class="btn btn-default btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">
                            Kelola peserta
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <button type="button" class="dropdown-item" data-toggle="modal" data-target="#importParticipantsModal">Impor Excel</button>
                            <form action="{{ route('training.broadcast-wa', $training) }}" method="POST" class="m-0"
                                onsubmit="return confirm(@js('Kirim undangan WhatsApp ke '.$training->participants_count.' peserta?'))">
                                @csrf
                                <button type="submit" class="dropdown-item">Kirim undangan WhatsApp</button>
                            </form>
                            @if($training->isOnline())
                                <div class="dropdown-divider"></div>
                                <button type="button" class="dropdown-item" data-toggle="modal" data-target="#scanZoomModal">
                                    <i class="fas fa-video"></i> Rekap Kamera
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </header>

            <div class="training-roster-toolbar">
                <form action="{{ route('training.show', $training) }}#peserta" method="GET" class="m-0 training-roster-search" role="search">
                    @foreach(\Illuminate\Support\Arr::except($rosterQuery, 'peserta') as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <x-search-field name="peserta" placeholder="Cari peserta"
                        :clear-url="route('training.show', [$training, ...\Illuminate\Support\Arr::except($rosterQuery, 'peserta')]).'#peserta'" />
                </form>
                @if($waFailedCount > 0 || $failedWaOnly)
                    <a href="{{ route('training.show', [$training, ...\Illuminate\Support\Arr::except($rosterQuery, 'wa'), ...($failedWaOnly ? [] : ['wa' => 'gagal'])]) }}#peserta"
                        class="btn btn-default btn-sm">
                        {{ $failedWaOnly ? 'Tampilkan semua peserta' : 'Undangan gagal ('.$waFailedCount.')' }}
                    </a>
                @endif
                @if($participants->total() > 0)
                    <form action="{{ route('training.show', $training) }}#peserta" method="GET" class="training-roster-pagesize" data-auto-submit>
                        @foreach(\Illuminate\Support\Arr::except($rosterQuery, 'per_halaman') as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endforeach
                        <label for="rosterPerPage" class="mb-0">Tampilkan</label>
                        <select name="per_halaman" id="rosterPerPage" data-native-select class="form-control form-control-sm w-auto">
                            @foreach($perPageOptions as $option)
                                <option value="{{ $option }}" @selected($option === $perPage)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <span>per halaman</span>
                        <noscript><button type="submit" class="btn btn-default btn-sm">Terapkan</button></noscript>
                    </form>
                @endif
            </div>

            <p id="cameraSaveNotice" class="training-notice training-notice-success training-roster-notice d-none" role="status"></p>

            @if($waFailedCount > 0)
                <p class="training-notice training-notice-warning training-roster-notice" role="status">
                    {{ $waFailedCount }} peserta gagal menerima undangan WhatsApp otomatis. Gunakan <strong>Kirim manual</strong> pada baris peserta.
                </p>
            @endif

            <div class="table-responsive training-roster-table">
                <table class="table table-hover mb-0" id="participantsTable" role="table" aria-labelledby="training-roster-title">
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col">Peserta</th>
                            {{-- Tanpa presensi, kolom ini hanya memuat status kamera bila ada datanya; selain itu tidak tampil. --}}
                            @if($training->has_attendance)
                                <th scope="col">Presensi</th>
                            @elseif($showsCamera)
                                <th scope="col">Kamera</th>
                            @endif
                            @foreach($quizStats as $label => $keys)
                                <th scope="col" class="training-participant-score">{{ $label }}</th>
                            @endforeach
                            @if($showsEvaluation)
                                <th scope="col" class="training-participant-score">Evaluasi</th>
                            @endif
                            <th scope="col" class="training-row-actions text-center" id="thActionHeader">Aksi</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
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
                            $waState = $part->waState();
                            $waLinks = \App\Services\WhatsAppService::manualInvitationLinks($training, $part);
                            $waSummary = $part->waSummary();
                            $quiz = $mainPhase ? $part->resultFor($mainPhase) : null;
                            $pretest = $mainPhase === 'posttest' && $training->has_pretest ? $part->pretestResult : null;
                            $quizResults = [];
                            if ($training->has_pretest && $training->has_posttest) {
                                $quizResults['Pretest'] = $pretest;
                            }
                            if ($mainPhase) {
                                $quizResults[$mainPhaseLabel] = $quiz;
                            }
                            $evaluationAverage = $part->evaluation?->average($training->evaluationQuestions);
                            $evaluationScore = $evaluationAverage === null ? null : number_format($evaluationAverage, 1).' / '.\App\Support\TrainingEvaluationForm::SCALE_MAX;
                        @endphp
                        <tr role="row" class="training-participant">
                            <td role="cell" class="training-person">
                                <button type="button" class="training-person-link" data-toggle="modal" data-target="#participantDetails_{{ $part->id }}" aria-label="Rincian {{ $userName }}">{{ $userName }}</button>
                                <span class="cell-sub">{{ $userIdKaryawan }} &middot; {{ $userDivisi }}</span>
                                @if($part->waFailed())
                                    <button type="button" class="training-inline-action status-dot is-danger" data-toggle="modal" data-target="#waLogModal_{{ $part->id }}">
                                        WhatsApp: {{ $waState['label'] }}
                                    </button>
                                @endif
                                @if($part->waFailed() && $waLinks)
                                    <form action="{{ route('training.send-manual-wa', [$training, $part]) }}" method="POST" target="_blank" class="m-0 mt-1">
                                        @csrf
                                        <button type="submit" class="btn btn-default btn-xs">
                                            <i class="fab fa-whatsapp"></i> Kirim manual
                                        </button>
                                    </form>
                                @endif

                                <div class="modal fade text-left" id="waLogModal_{{ $part->id }}" tabindex="-1" role="dialog" aria-labelledby="waLogTitle_{{ $part->id }}" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="waLogTitle_{{ $part->id }}">{{ $part->waLogs->isNotEmpty() ? 'Riwayat Undangan WhatsApp' : 'Status WhatsApp' }}: {{ $userName }}</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                                            </div>
                                            <div class="modal-body text-wrap">
                                                <p class="mb-2">Nomor WhatsApp {{ $userNoWa }} · {{ $waState['label'] }}</p>
                                                @if($waState['detail'])
                                                    <p class="text-muted mb-3">{{ $waState['detail'] }}</p>
                                                @endif
                                                @if($part->waLogs->isNotEmpty())
                                                    <p class="mb-3 text-sm">
                                                        Otomatis: {{ $waSummary['autoSuccess'] }}x berhasil, {{ $waSummary['autoFailed'] }}x gagal
                                                        @if($waSummary['manual'] > 0)
                                                            · Manual: {{ $waSummary['manual'] }}x
                                                        @endif
                                                    </p>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>Waktu</th>
                                                                    <th>Cara</th>
                                                                    <th>Hasil</th>
                                                                    <th>Nomor</th>
                                                                    <th>Keterangan</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($part->waLogs as $waLog)
                                                                    <tr>
                                                                        <td class="text-nowrap">{{ $waLog->created_at->isoFormat('D MMM Y, HH:mm:ss') }}</td>
                                                                        <td class="text-nowrap">{{ $waLog->isManual() ? 'Manual' : 'Otomatis' }}</td>
                                                                        <td class="text-nowrap">
                                                                            <span class="status-dot is-{{ $waLog->isManual() ? 'warning' : ($waLog->success ? 'success' : 'danger') }}">{{ $waLog->resultLabel() }}</span>
                                                                        </td>
                                                                        <td class="text-nowrap">{{ $waLog->phone ?? '-' }}</td>
                                                                        <td>
                                                                            {{ $waLog->detail }}
                                                                            @if($waLog->sender)
                                                                                <span class="text-muted">{{ $waLog->detail ? '·' : '' }} oleh {{ $waLog->sender->nama_lengkap }}</span>
                                                                            @elseif(! $waLog->isManual() && ! $waLog->detail)
                                                                                <span class="text-muted">Broadcast / pengingat</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    @if($waSummary['autoSuccess'] + $waSummary['autoFailed'] + $waSummary['manual'] > $part->waLogs->count())
                                                        <p class="text-muted text-sm mt-3 mb-0">Menampilkan {{ $part->waLogs->count() }} riwayat terbaru.</p>
                                                    @endif
                                                    <p class="text-muted text-sm mt-3 mb-0">"Dibuka manual" berarti teks undangan sudah dibuka di WhatsApp. Status pengiriman hanya terlihat di WhatsApp.</p>
                                                @else
                                                    <p class="text-muted mb-0">Belum ada riwayat undangan.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            @if($hasAttendanceColumn)
                                <td role="cell" class="training-participant-detail">
                                    <span class="training-mobile-label" aria-hidden="true">{{ $training->has_attendance ? 'Presensi' : 'Kamera' }}</span>
                                    @if($training->has_attendance)
                                        @if($part->attendance_status === 'hadir')
                                            <span class="status-dot is-success">Hadir</span>
                                        @elseif($part->attendance_status === 'tidak_hadir')
                                            <span class="status-dot is-danger">Tidak hadir</span>
                                        @else
                                            <span class="status-dot">Belum absen</span>
                                        @endif
                                    @endif
                                    @if($training->isOnline())
                                        <span class="camera-badge-box" id="cameraBadge_{{ $part->id }}">
                                            @if($part->camera_status)
                                                <span class="training-camera is-{{ $part->camera_status->value }}"><i class="fas fa-video mr-1" aria-hidden="true"></i>{{ $part->cameraSummary() }}</span>
                                            @elseif(! $training->has_attendance)
                                                <span class="text-muted" aria-hidden="true">—</span><span class="sr-only">Belum ada data kamera</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                            @endif
                            @foreach($quizResults as $label => $result)
                                <td role="cell" class="training-participant-detail training-participant-score">
                                    <span class="training-mobile-label" aria-hidden="true">{{ $label }}</span>
                                    @if($result)
                                        <span class="training-score">{{ str_replace('.', ',', (string) $result->score) }}</span>
                                        @if($result->is_force_submitted || $result->essay_status === 'pending' || ($result->tab_switch_count ?? 0) > 0)
                                            <button type="button" class="training-score-flag" data-toggle="modal" data-target="#participantDetails_{{ $part->id }}"
                                                aria-label="{{ $label }} {{ $userName }}: {{ $result->is_force_submitted ? 'dikirim otomatis' : ($result->essay_status === 'pending' ? 'esai perlu dinilai' : 'berpindah tab') }}">
                                                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                                            </button>
                                        @endif
                                    @else
                                        <span class="text-muted" aria-hidden="true">—</span><span class="sr-only">Belum mengerjakan</span>
                                    @endif
                                </td>
                            @endforeach
                            @if($showsEvaluation)
                                <td role="cell" class="training-participant-detail training-participant-score">
                                    <span class="training-mobile-label" aria-hidden="true">Evaluasi</span>
                                    @if($part->evaluation)
                                        <span class="training-score">{{ $evaluationAverage === null ? 'Terisi' : number_format($evaluationAverage, 1, ',', '.') }}</span>
                                        @if($evaluationAverage !== null)
                                            <small class="text-muted">/ {{ \App\Support\TrainingEvaluationForm::SCALE_MAX }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted" aria-hidden="true">—</span><span class="sr-only">Belum mengisi</span>
                                    @endif
                                </td>
                            @endif
                            <td role="cell" class="training-row-actions text-center">
                                <!-- DEFAULT ACTION DROPDOWN -->
                                <div class="dropdown default-action-wrapper">
                                    <button class="btn btn-default btn-xs dropdown-toggle" type="button" data-toggle="dropdown" data-boundary="window" aria-expanded="false" aria-label="Aksi untuk {{ $userName }}">
                                        Aksi
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" style="max-height: 280px; overflow-y: auto;">
                                        <button type="button" class="dropdown-item" data-toggle="modal" data-target="#participantDetails_{{ $part->id }}">Rincian peserta</button>
                                        <button type="button" class="dropdown-item" data-toggle="modal" data-target="#waLogModal_{{ $part->id }}">Riwayat WhatsApp</button>
                                        {{-- Lembar jawaban hanya untuk fase yang dipakai dan sudah dikerjakan peserta ini. --}}
                                        @if($pretest && $pretest->answers)
                                            <a class="dropdown-item answer-sheet-link" href="#" data-url="{{ route('training.answer-sheet', [$training, $pretest]) }}">
                                                Lembar Jawaban Pretest{{ $pretest->essay_status === 'pending' ? ' & Review Essay' : '' }}
                                            </a>
                                        @endif
                                        @if($quiz && $quiz->answers)
                                            <a class="dropdown-item answer-sheet-link" href="#" data-url="{{ route('training.answer-sheet', [$training, $quiz]) }}">
                                                Lembar Jawaban {{ $mainPhaseLabel }}{{ $hasEssay && $quiz->essay_status === 'pending' ? ' & Review Essay' : '' }}
                                            </a>
                                        @endif

                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item copy-link-btn" href="javascript:void(0)" data-link="{{ url('/training/portal/' . $part->token) }}">
                                            Salin tautan peserta
                                        </a>

                                        <form action="/training/{{ $training->id }}/send-wa/{{ $part->id }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="dropdown-item">Kirim undangan WhatsApp</button>
                                        </form>
                                        @if($waLinks)
                                            <form action="{{ route('training.send-manual-wa', [$training, $part]) }}" method="POST" target="_blank" class="d-inline">
                                                @csrf
                                                <button type="submit" class="dropdown-item">Kirim Manual via WhatsApp Web</button>
                                            </form>
                                        @else
                                            <span class="dropdown-item text-muted" title="Nomor WhatsApp peserta kosong atau tidak valid. Perbaiki di Pengaturan &gt; User.">Kirim Manual (No. HP tidak valid)</span>
                                        @endif

                                        @if($training->has_attendance)
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAttendanceModal_{{ $part->id }}">Atur Presensi Manual</a>
                                        @endif

                                        {{-- Aksi yang menghapus data dikumpulkan di bawah, dan hanya muncul bila datanya memang ada. --}}
                                        <div class="dropdown-divider"></div>
                                        @if($training->has_attendance && $part->attendance_status !== 'pending')
                                            <form action="/training/{{ $training->id }}/reset-attendance/{{ $part->id }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Kembalikan status presensi '.$userName.' menjadi Belum Absen?'))">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-danger">Reset Presensi (Belum Absen)</button>
                                            </form>
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
                                        <form action="{{ route('training.participants.remove', [$training, $part]) }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Keluarkan '.$userName.' dari pelatihan ini? Link portalnya tidak berlaku lagi, dan hasil kuis, presensi, serta evaluasinya ikut dihapus.'))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                Keluarkan dari Pelatihan
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                {{-- Modal per peserta tetap di dalam sel ini: di luar <td> browser memindahkannya keluar tabel dan memisahkan <form> dari isinya. --}}
                                @include('training.detail.participant-details')

                                <!-- MODAL ATUR PRESENSI MANUAL -->
                                @if($training->has_attendance)
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
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr role="row">
                            <td role="cell" class="training-roster-empty" colspan="{{ $participantColumnCount }}">
                                @if(filled(request('peserta')))
                                    <x-empty-state icon="fa-search" title="Peserta tidak ditemukan" text="Coba kata kunci lain: nama, ID karyawan, No. HP, atau divisi." />
                                @elseif(request('wa') === 'gagal')
                                    <x-empty-state icon="fa-check" title="Tidak ada undangan WhatsApp yang gagal" text="Semua kiriman otomatis terakhir berhasil atau belum dikirim.">
                                        <a href="{{ route('training.show', [$training, ...\Illuminate\Support\Arr::except($rosterQuery, 'wa')]) }}#peserta" class="btn btn-default btn-sm">Tampilkan semua peserta</a>
                                    </x-empty-state>
                                @else
                                    <x-empty-state icon="fa-users" title="Belum ada peserta" />
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($participants->total() > 0)
            <div class="training-roster-footer">
                <span class="text-muted small">
                    Menampilkan {{ $participants->firstItem() }}-{{ $participants->lastItem() }} dari {{ $participants->total() }} peserta{{ filled(request('peserta')) ? ' yang cocok' : '' }}
                </span>
                @if($participants->hasPages())
                    {{ $participants->links('pagination::bootstrap-4') }}
                @endif
            </div>
            @endif
        </section>

        @include('training.detail.session')
        @include('training.detail.materials')
        @if($evaluation)
            @include('training.detail.evaluation')
        @endif

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

{{-- MODAL REKAP KAMERA PESERTA --}}
<div class="modal fade" id="scanZoomModal" tabindex="-1" role="dialog" aria-labelledby="scanZoomModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="scanZoomModalLabel">
                        <i class="fas fa-video text-muted mr-1"></i> Rekap Kamera Peserta
                    </h5>
                    <small class="text-muted">Pilih sumber data. Hasilnya bisa diperiksa dan diubah sebelum disimpan.</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                {{-- PILIH SUMBER DATA --}}
                <div id="zoomStepUpload">
                    <p class="section-title mb-2">Pilih sumber data</p>
                    <p class="text-xs text-muted d-none" id="cameraMergeHint">Data baru digabung ke tabel yang sedang diperiksa.</p>
                    @php($cameraMonitorStep = $hasMeetingCameraData || $canMonitorCamera)
                    @if($cameraMonitorStep)
                        <div class="card mb-3">
                            <div class="card-body d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
                                <div class="flex-grow-1" style="min-width: 240px; flex-basis: 0;">
                                    <span class="section-title d-block mb-1">Dari monitoring {{ $meetingLabel }}
                                        @if($hasMeetingCameraData)<span class="badge bg-success ml-1">Disarankan</span>@endif
                                    </span>
                                    @if($hasMeetingCameraData)
                                        <span class="text-muted text-sm">Status kamera yang terekam selama monitoring: berapa lama kamera tiap peserta menyala selama ikut meeting.</span>
                                    @else
                                        <span class="text-muted text-sm">Belum ada status kamera yang terekam. Jalankan monitoring dari halaman <a href="{{ route('training.meeting.show', $training) }}">Aktivitas {{ $meetingLabel }}</a> selama pelatihan berlangsung, lalu ambil datanya di sini.</span>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-primary btn-sm text-nowrap" id="btnLoadMonitorCamera">
                                    <i class="fas fa-satellite-dish mr-1"></i> Pakai Data Monitoring
                                </button>
                            </div>
                            <div class="alert alert-info mx-3 mb-3 d-none" id="zoomMonitorInfo" role="status"></div>
                        </div>
                    @endif

                    @if($canScanCameraWithAi)
                        <div class="card mb-3">
                            <div class="card-body">
                                <span class="section-title d-block mb-1">Dari screenshot (AI)</span>
                                <span class="text-muted text-sm d-block mb-3">Screenshot tampilan galeri atau panel daftar peserta {{ $meetingLabel }} dari beberapa random check (mis. menit ke-1, ke-5, dan seterusnya). Setiap screenshot dihitung satu pengecekan; AI membaca status kamera setiap peserta dan mencocokkan namanya.</span>
                            <label for="zoomFileInput" class="zoom-drop-area d-block text-center p-3 m-0 w-100" id="zoomDropArea">
                                <span class="stat-tile-icon mb-2"><i class="fas fa-cloud-upload-alt"></i></span>
                                <span class="d-block font-weight-bold mb-1">Klik untuk memilih, atau tarik screenshot / file Word ke sini</span>
                                <span class="text-muted text-sm d-block mb-2">
                                    Hingga 40 gambar (JPG, PNG, WEBP) atau file Word .docx berisi screenshot. Gambar dikecilkan otomatis sebelum dikirim.
                                </span>
                                <span class="btn btn-default btn-sm">
                                    <i class="fas fa-folder-open"></i> Pilih Screenshot / File Word
                                </span>
                            </label>
                            <input type="file" id="zoomFileInput" multiple accept="image/png,image/jpeg,image/webp,.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" style="display: none;">

                            {{-- ALERT ERROR --}}
                            <div class="alert alert-danger mt-3 mb-0 d-none" id="zoomUploadAlert" role="alert"></div>

                            {{-- PREVIEW DAFTAR SCREENSHOT --}}
                            <div class="mt-3 d-none" id="zoomPreviewSection">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="text-sm">
                                        Screenshot terpilih (<span id="zoomFileCount">0</span>/40)
                                    </strong>
                                    <button type="button" class="btn btn-default btn-xs text-danger" id="btnClearFiles">
                                        <i class="fas fa-trash-alt"></i> Hapus Semua
                                    </button>
                                </div>
                                <div class="row" id="zoomThumbGrid" style="row-gap: 12px;"></div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="button" class="btn btn-primary btn-sm" id="btnStartScan" disabled>
                                    <i class="fas fa-magic mr-1"></i> Mulai Pindai dengan AI
                                </button>
                            </div>
                            </div>
                        </div>
                    @endif

                    <div class="card mb-3">
                        <div class="card-body d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
                            <div class="flex-grow-1" style="min-width: 240px; flex-basis: 0;">
                                <span class="section-title d-block mb-1">Isi sendiri</span>
                                <span class="text-muted text-sm">Pilih status kamera tiap peserta langsung dari daftar semua peserta.</span>
                            </div>
                            <button type="button" class="btn btn-default btn-sm text-nowrap" id="btnDirectManualInput">
                                <i class="fas fa-edit mr-1"></i> Isi Sendiri
                            </button>
                        </div>
                    </div>

                    @unless($canScanCameraWithAi)
                        <p class="text-xs text-muted mb-0">Pindai screenshot dengan AI belum tersedia. Hubungi admin untuk mengaktifkannya.@if($hasAdministratorAccess) (Atur di <a href="{{ route('ai-settings.index') }}">Pengaturan AI</a>.)@endif</p>
                    @endunless
                </div>

                {{-- TAHAP 2: PROSES LOADING --}}
                <div id="zoomStepLoading" class="d-none text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <h5 class="section-title mb-2" id="zoomLoadingTitle">
                        Menganalisis Screenshot {{ $meetingLabel }}...
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

                {{-- PERIKSA & SIMPAN --}}
                <div id="zoomStepReview" class="d-none">
                    <p class="text-xs text-muted mb-2 d-none" id="cameraResumeNote">Melanjutkan rekap yang belum disimpan. <a href="#" id="btnResetCameraReview">Mulai ulang</a></p>
                    <h6 class="section-title mb-1" id="reviewSourceTitle">Isi Status Kamera</h6>
                    <p class="text-xs text-muted mb-2" id="cameraRuleText"></p>
                    <input type="search" id="cameraReviewSearch" class="form-control form-control-sm mb-2" placeholder="Cari nama atau ID peserta…" aria-label="Cari peserta">

                    <div class="card mb-3">
                        <div class="card-body p-0 camera-review-scroll">
                            <table class="table mb-0 text-sm" id="zoomReviewTable">
                                <thead>
                                    <tr>
                                        <th>Peserta</th>
                                        <th class="text-nowrap" style="width: 1%;">Status Kamera</th>
                                    </tr>
                                </thead>
                                <tbody id="camGroupCheck">
                                    <tr class="camera-group-row d-none"><th colspan="2" class="text-xs"></th></tr>
                                </tbody>
                                <tbody id="camGroupData">
                                    <tr class="camera-group-row d-none"><th colspan="2" class="text-xs"></th></tr>
                                </tbody>
                                <tbody id="camGroupEmpty">
                                    <tr class="camera-group-row d-none">
                                        <th colspan="2" class="text-xs">
                                            <span class="camera-group-title"></span>
                                            <span class="camera-bulk d-none">
                                                <span class="ml-2">Isi semua yang kosong:</span>
                                                <button type="button" class="btn btn-default btn-xs ml-1" data-bulk-status="on_cam">On Cam</button>
                                                <button type="button" class="btn btn-default btn-xs ml-1" data-bulk-status="partial">Tidak Full On Cam</button>
                                                <button type="button" class="btn btn-default btn-xs ml-1" data-bulk-status="off_cam">Full Off Cam</button>
                                                <span class="d-block text-muted font-weight-normal mt-1">Peserta yang sudah punya rekap sebelumnya tidak diubah.</span>
                                            </span>
                                        </th>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="border-top pt-3 camera-review-footer">
                        <div id="cameraSaveSummary" class="text-sm mb-2" aria-live="polite"></div>
                        @if($training->has_attendance)
                            <div class="custom-control custom-checkbox mb-2 d-none" id="markTidakHadirWrap">
                                <input type="checkbox" class="custom-control-input" id="checkMarkTidakHadir">
                                <label class="custom-control-label text-sm" for="checkMarkTidakHadir" id="markTidakHadirLabel"></label>
                            </div>
                        @endif
                        <div class="alert alert-danger d-none" id="cameraSaveAlert" role="alert"></div>
                        <div class="d-flex justify-content-between flex-wrap" style="gap: 8px;">
                            <button type="button" class="btn btn-default" id="btnBackToUpload">
                                <i class="fas fa-plus mr-1"></i> Ganti atau tambah sumber data
                            </button>
                            <button type="button" class="btn btn-primary" id="btnConfirmSave" disabled></button>
                        </div>
                    </div>
                </div>

                {{-- TIDAK ADA PESERTA TERBACA --}}
                <div id="zoomStepNoneRead" class="d-none">
                    <x-empty-state icon="fa-user-slash" title="Tidak ada peserta yang terbaca" text="AI tidak menemukan kotak atau daftar peserta pada screenshot {{ $meetingLabel }} yang diunggah. Pastikan screenshot menampilkan tampilan galeri atau panel daftar peserta.">
                        <button type="button" class="btn btn-default" id="btnNoneReadBack">
                            <i class="fas fa-arrow-left"></i> Kembali Pilih Sumber Data
                        </button>
                    </x-empty-state>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer')
<script src="{{ asset('template/plugins/jszip/jszip.min.js') }}"></script>
<script>
(function () {
    const links = Array.from(document.querySelectorAll('[data-training-section]'));
    const panels = document.querySelectorAll('[data-training-panel]');

    function sectionFromUrl() {
        const fragment = location.hash.slice(1);
        const section = links.some(link => link.dataset.trainingSection === fragment)
            ? fragment
            : new URLSearchParams(location.search).get('bagian');
        return links.some(link => link.dataset.trainingSection === section) ? section : 'peserta';
    }

    function showSection(section) {
        panels.forEach(panel => { panel.hidden = panel.id !== section; });
        links.forEach(link => {
            if (link.dataset.trainingSection === section) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    links.forEach(link => link.addEventListener('click', function (event) {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        if (location.href !== link.href) history.pushState(null, '', link.href);
        showSection(link.dataset.trainingSection);
    }));

    window.addEventListener('popstate', () => showSection(sectionFromUrl()));
    showSection(sectionFromUrl());

    // Jumlah peserta per halaman langsung diterapkan saat dipilih.
    document.querySelectorAll('form[data-auto-submit] select').forEach(select => {
        select.addEventListener('change', () => select.form.submit());
    });
})();

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
    const averageKeys = { avgScore: true, pretestAvg: true };
    const scoreFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    function updateStat(el, value) {
        // Spasi dari template diabaikan, supaya angka yang sama tidak dianggap berubah dan tidak berkedip.
        if (el && el.textContent.trim() !== String(value)) {
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
                updateStat(document.getElementById('statTotal'), '(' + data.total + ' orang)');
                updateStat(document.getElementById('statAttended'), data.attended);
                updateStat(document.getElementById('statAbsent'), data.absent);
                updateStat(document.getElementById('statPending'), data.pending);

                document.querySelectorAll('[data-training-stat]').forEach(el => {
                    if (!(el.dataset.trainingStat in averageKeys)) updateStat(el, data[el.dataset.trainingStat]);
                });
                // Rata-rata baru ditampilkan setelah ada yang mengerjakan; sebelumnya cukup keterangan singkat.
                document.querySelectorAll('[data-training-average]').forEach(el => {
                    const key = el.dataset.trainingAverage;
                    if (!data[el.dataset.trainingCount]) {
                        updateStat(el, 'Belum ada yang mengerjakan');
                        return;
                    }
                    let value = el.querySelector('[data-training-stat]');
                    if (!value) {
                        el.textContent = 'Rata-rata ';
                        value = document.createElement('b');
                        value.dataset.trainingStat = key;
                        el.appendChild(value);
                    }
                    updateStat(value, scoreFormat.format(data[key]));
                });
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
// REKAP KAMERA PESERTA (MONITORING, PINDAIAN AI, MANUAL) - CLIENT JS
// ===============================================================
(function () {
    const TRAINING_ID = {{ $training->id }};
    const CSRF_TOKEN = '{{ csrf_token() }}';
    const OFFICIAL_PARTICIPANTS = @json($zoomParticipants);
    const MEETING_LABEL = @json($meetingLabel);

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
    const stepNoneRead = document.getElementById('zoomStepNoneRead');

    const reviewTable = document.getElementById('zoomReviewTable');
    const groupCheck = document.getElementById('camGroupCheck');
    const groupData = document.getElementById('camGroupData');
    const groupEmpty = document.getElementById('camGroupEmpty');
    const checkMarkTidakHadir = document.getElementById('checkMarkTidakHadir');
    const btnBackToUpload = document.getElementById('btnBackToUpload');
    const btnConfirmSave = document.getElementById('btnConfirmSave');
    const HAS_MONITOR_DATA = @json($hasMeetingCameraData);

    const MAX_SCREENSHOTS = 40;
    const SCAN_BATCH_SIZE = 10; // the server accepts at most 10 images per request
    const SCAN_BATCH_MAX_BYTES = 600 * 1024; // base64 characters per request, below common 1 MB body limits
    const SCAN_URL = @json(route('training.scan-zoom-ai', $training));
    const SCAN_STATUS_URL = @json(route('training.scan-zoom-ai.status', [$training, '__TOKEN__']));
    const SCAN_POLL_MS = 3000;
    const SCAN_MAX_WAIT_MS = 15 * 60 * 1000; // same as the queue job timeout
    const SAVE_CAMERA_URL = @json(route('training.camera-status.save', $training));
    const MONITOR_CAMERA_URL = @json(route('training.meeting.camera', $training));
    const CAMERA_LABELS = @json($cameraStatusLabels);

    // Sama dengan App\Enums\CameraStatus: lebih dari separuh pengecekan menyala = On Cam, tidak pernah = Full Off Cam.
    function cameraStatusFromChecks(on, total) {
        if (!(total > 0)) return null;
        if (on <= 0) return 'off_cam';
        return on * 2 > total ? 'on_cam' : 'partial';
    }

    function cameraPercent(on, total) {
        const status = cameraStatusFromChecks(on, total);
        if (!status) return null;
        const percent = Math.round(on * 100 / total);
        if (status === 'off_cam') return 0;
        if (status === 'partial') return Math.min(50, Math.max(1, percent));
        return on >= total ? 100 : Math.min(99, Math.max(51, percent));
    }
    const reviewSourceTitle = document.getElementById('reviewSourceTitle');
    const cameraRuleText = document.getElementById('cameraRuleText');
    const REVIEW_TITLES = {
        ai: 'Periksa Hasil Pindaian Screenshot',
        monitor: 'Periksa Hasil Monitoring ' + MEETING_LABEL,
        manual: 'Isi Status Kamera',
        combined: 'Periksa Rekap Kamera Gabungan',
    };
    const RULE_TEXTS = {
        monitor: 'On Cam: kamera menyala lebih dari separuh waktu di meeting. Tidak Full On Cam: pernah menyala, tetapi separuh waktu atau kurang. Full Off Cam: tidak pernah menyala.',
        ai: 'On Cam: kamera menyala di lebih dari separuh screenshot. Tidak Full On Cam: pernah menyala, tetapi di separuh screenshot atau kurang. Full Off Cam: tidak pernah menyala.',
        manual: 'On Cam: kamera lebih sering menyala (lebih dari separuh). Tidak Full On Cam: pernah menyala, tetapi separuh atau kurang. Full Off Cam: tidak pernah menyala.',
    };

    let reviewSources = new Set();

    function setReviewSource(source) {
        reviewSources.add(source);
        const sources = Array.from(reviewSources).filter(src => src !== 'manual');
        const key = sources.length > 1 ? 'combined' : (sources[0] || 'manual');
        if (reviewSourceTitle) reviewSourceTitle.textContent = REVIEW_TITLES[key];
        if (cameraRuleText) cameraRuleText.textContent = RULE_TEXTS[key] || RULE_TEXTS.manual;
        updateGroups();
    }

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
                        <span class="d-block text-truncate font-weight-bold" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</span>
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
            stepNoneRead.classList.add('d-none');

            // Rotating status text
            const loadingMessages = [
                'Mengirim ' + selectedFiles.length + ' screenshot ' + MEETING_LABEL + ' ke Google Gemini Vision...',
                'AI sedang membaca kotak-kotak galeri ' + MEETING_LABEL + '...',
                'Membaca status kamera setiap peserta (video, layar hitam, inisial, avatar)...',
                'Membaca teks nama di setiap kotak peserta...',
                'Mencocokkan nama akun ' + MEETING_LABEL + ' dengan daftar resmi peserta pelatihan...',
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

                let scannedCount = 0;
                for (let b = 0; b < batches.length; b++) {
                    if (titleEl) {
                        titleEl.textContent = batches.length > 1
                            ? `Menganalisis Screenshot ${MEETING_LABEL}... (batch ${b + 1}/${batches.length})`
                            : `Menganalisis Screenshot ${MEETING_LABEL}...`;
                    }

                    try {
                        collected.push(...await scanImages(batches[b]));
                        scannedCount += batches[b].length;
                    } catch (err) {
                        console.error(err);
                        failure = err.message || 'Terjadi kendala saat memproses screenshot dengan AI.';
                        if (batches.length > 1) failure = `Batch ${b + 1}/${batches.length} gagal: ${failure}`;
                        break;
                    }
                }

                clearInterval(loadingInterval);
                stepLoading.classList.add('d-none');
                if (titleEl) titleEl.textContent = `Menganalisis Screenshot ${MEETING_LABEL}...`;

                // Screenshots already counted leave the list, so adding more and scanning again never counts them twice.
                selectedFiles.splice(0, scannedCount);
                renderThumbnails();

                const cameraList = mergeCameraResults(collected);

                // Nothing usable came back: return to the upload step with the error
                if (failure && cameraList.length === 0) {
                    stepUpload.classList.remove('d-none');
                    uploadAlert.textContent = failure;
                    uploadAlert.classList.remove('d-none');
                    return;
                }

                if (cameraList.length === 0 && hasReviewData()) {
                    showReview();
                    if (!failure) showReviewAlert('AI tidak menemukan peserta pada screenshot ini. Tabel yang sedang diperiksa tidak berubah.', 'info');
                } else if (cameraList.length === 0) {
                    stepNoneRead.classList.remove('d-none');
                } else {
                    setReviewSource('ai');
                    cameraList.forEach(item => applySourceItem(item, 'ai'));
                    showReview();
                }

                if (failure) {
                    showReviewAlert(failure + ' Hasil dari batch sebelumnya tetap ditampilkan.');
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
                return (data.data && data.data.camera_participants) || [];
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
        if (status === 404) return 'Alamat tidak ditemukan (HTTP 404). Muat ulang halaman lalu coba lagi.';
        if (status === 502 || status === 503 || status === 504) return `Server terlalu lama merespons (HTTP ${status}). Coba lagi dengan lebih sedikit screenshot.`;
        return `Server mengembalikan error (HTTP ${status}). Coba lagi.`;
    }

    // Combine results from several batches (different screenshots): one row per matched participant or unmatched
    // meeting name, with the screenshot checks added up and the status counted again.
    function mergeCameraResults(list) {
        const byKey = new Map();
        list.forEach(item => {
            const key = item.participant_id
                ? 'p:' + item.participant_id
                : 'z:' + String(item.zoom_name || '').trim().toLowerCase();
            const existing = byKey.get(key);
            if (!existing) {
                byKey.set(key, Object.assign({}, item));
                return;
            }
            addChecks(existing, item);
            if ((item.similarity_score || 0) > (existing.similarity_score || 0)) {
                existing.similarity_score = item.similarity_score;
            }
        });
        return Array.from(byKey.values());
    }

    // Same wording as ZoomAttendanceAiService::screenshotEvidence()
    function screenshotEvidence(on, checks) {
        if (on <= 0) return `Kamera mati di semua ${checks} screenshot`;
        if (on >= checks) return `Kamera menyala di semua ${checks} screenshot`;
        return `Kamera menyala di ${on} dari ${checks} screenshot`;
    }

    function addChecks(target, item) {
        target.checks = (+target.checks || 0) + (+item.checks || 0);
        target.on_cam_checks = (+target.on_cam_checks || 0) + (+item.on_cam_checks || 0);
        target.camera_status = cameraStatusFromChecks(target.on_cam_checks, target.checks);
        target.camera_on_percent = cameraPercent(target.on_cam_checks, target.checks);
        target.visual_evidence = screenshotEvidence(target.on_cam_checks, target.checks);
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function labelWithPercent(status, percent) {
        return CAMERA_LABELS[status] + (percent !== '' && percent !== null && percent !== undefined ? ` ${percent}%` : '');
    }

    // ---------------------------------------------------------------
    // Tabel periksa: satu baris untuk setiap peserta terdaftar
    // ---------------------------------------------------------------
    const participantsById = new Map(OFFICIAL_PARTICIPANTS.map(p => [String(p.id), p]));
    const SOURCE_LABELS = { monitor: 'Monitoring', ai: 'Screenshot' };
    const pendingQueues = new WeakMap(); // participant row -> doubtful matches waiting for "Ya, benar" / "Bukan"
    const unmatchedItems = new WeakMap(); // unknown-name row -> { item, source }
    let rowsBuilt = false;
    let monitorAutoLoaded = false;

    function participantRows() {
        return Array.from(reviewTable.querySelectorAll('tr.camera-row'));
    }

    function rowFor(participantId) {
        return reviewTable.querySelector(`tr.camera-row[data-participant-id="${CSS.escape(String(participantId))}"]`);
    }

    function clearGroups() {
        [groupCheck, groupData, groupEmpty].forEach(group => {
            group.querySelectorAll('tr:not(.camera-group-row)').forEach(tr => tr.remove());
        });
    }

    function buildParticipantRows() {
        clearGroups();
        OFFICIAL_PARTICIPANTS.slice()
            .sort((a, b) => String(a.name).localeCompare(String(b.name), 'id'))
            .forEach(p => groupEmpty.appendChild(createParticipantRow(p)));
        rowsBuilt = true;
        updateGroups();
    }

    // A saved recap starts selected, so the trainer only changes the exceptions; untouched rows are not saved again.
    function createParticipantRow(p) {
        const tr = document.createElement('tr');
        tr.className = 'camera-row';
        const prior = p.camera_status || '';
        const priorPercent = prior && p.camera_on_percent !== null && p.camera_on_percent !== undefined ? String(p.camera_on_percent) : '';
        Object.assign(tr.dataset, {
            participantId: p.id, status: prior, percent: priorPercent, prior, priorPercent,
            computed: '', computedPercent: '', source: '', conflict: '', zoomName: '', confirmedNames: '',
            checks: '', onChecks: '', edited: '', pending: '', evidence: '',
            search: `${p.name} ${p.id_karyawan}`.toLowerCase(),
        });

        tr.innerHTML = `
            <td class="align-middle">
                <div class="font-weight-bold">${escapeHtml(p.name)} <span class="text-xs text-muted font-weight-normal">${escapeHtml(p.id_karyawan)}</span></div>
                <div class="text-xs text-muted camera-row-evidence"></div>
                <div class="camera-row-confirm d-none"></div>
            </td>
            <td class="align-middle">
                <div class="btn-group btn-group-toggle segmented camera-status-picker" role="radiogroup" aria-label="Status kamera ${escapeHtml(p.name)}">
                    <button type="button" class="btn" role="radio" aria-checked="false" data-status="on_cam" title="On Cam">On Cam</button>
                    <button type="button" class="btn" role="radio" aria-checked="false" data-status="partial" title="Tidak Full On Cam">Tidak Full</button>
                    <button type="button" class="btn" role="radio" aria-checked="false" data-status="off_cam" title="Full Off Cam">Full Off</button>
                </div>
                <div class="text-xs camera-row-note"></div>
            </td>
        `;
        renderRow(tr);
        return tr;
    }

    function isUntouchedPrior(tr) {
        return !!tr.dataset.prior && tr.dataset.status === tr.dataset.prior && !tr.dataset.evidence && tr.dataset.edited !== '1';
    }

    // Line 2 under the name, the picker state and the notes under the picker
    function renderRow(tr) {
        const p = participantsById.get(tr.dataset.participantId);
        const evidenceEl = tr.querySelector('.camera-row-evidence');
        let evidence = 'Belum ada data';
        if (tr.dataset.evidence) {
            const differs = tr.dataset.zoomName && p && tr.dataset.zoomName.trim().toLowerCase() !== String(p.name).trim().toLowerCase();
            evidence = (differs ? `di ${MEETING_LABEL}: "${tr.dataset.zoomName}" · ` : '') + tr.dataset.evidence;
        } else if (tr.dataset.prior) {
            evidence = 'Rekap tersimpan: ' + labelWithPercent(tr.dataset.prior, tr.dataset.priorPercent);
        }
        evidenceEl.textContent = evidence;
        evidenceEl.title = evidence;

        tr.querySelectorAll('.camera-status-picker [data-status]').forEach(btn => {
            const active = btn.dataset.status === tr.dataset.status;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-checked', active ? 'true' : 'false');
        });

        const notes = [];
        if (tr.dataset.edited === '1') {
            notes.push(tr.dataset.computed
                ? `<span class="text-muted">Diubah manual (hitungan: ${escapeHtml(labelWithPercent(tr.dataset.computed, tr.dataset.computedPercent))})</span>`
                : '<span class="text-muted">Dipilih manual</span>');
        }
        if (tr.dataset.conflict) {
            notes.push(`<span class="text-warning">${escapeHtml(tr.dataset.conflict)}</span>`);
        }
        if (tr.dataset.prior && tr.dataset.status !== tr.dataset.prior && (tr.dataset.status || tr.dataset.evidence)) {
            notes.push(`<span class="text-warning">Rekap tersimpan: ${escapeHtml(CAMERA_LABELS[tr.dataset.prior])}${tr.dataset.status ? '' : ' (tetap, tidak diubah)'}</span>`);
        }
        tr.querySelector('.camera-row-note').innerHTML = notes.join('<br>');
    }

    // A status clicked by hand is kept even when more data comes in later
    function setRowStatus(tr, status) {
        tr.dataset.status = status;
        if (status && tr.dataset.computed && status === tr.dataset.computed) {
            tr.dataset.edited = '';
            tr.dataset.percent = tr.dataset.computedPercent;
        } else if (status && !tr.dataset.computed && status === tr.dataset.prior && !tr.dataset.evidence) {
            tr.dataset.edited = '';
            tr.dataset.percent = tr.dataset.priorPercent;
        } else {
            tr.dataset.edited = status ? '1' : '';
            tr.dataset.percent = '';
        }
        renderRow(tr);
    }

    function addZoomNames(tr, zoomName) {
        const names = tr.dataset.zoomName ? tr.dataset.zoomName.split(' / ') : [];
        String(zoomName || '').split(' / ').map(n => n.trim()).filter(Boolean).forEach(n => {
            if (!names.includes(n)) names.push(n);
        });
        tr.dataset.zoomName = names.join(' / ');
    }

    function confirmedNames(tr) {
        return tr.dataset.confirmedNames ? tr.dataset.confirmedNames.split('\n') : [];
    }

    function confirmName(tr, zoomName) {
        const names = confirmedNames(tr);
        const key = String(zoomName || '').trim().toLowerCase();
        if (key && !names.includes(key)) names.push(key);
        tr.dataset.confirmedNames = names.join('\n');
    }

    // Put one participant's counted result from a source into their row. Screenshot batches add up; the same source
    // loaded again replaces itself; a different source never silently replaces the result already shown.
    function fillRow(tr, item, source) {
        addZoomNames(tr, item.zoom_name);

        if (tr.dataset.edited === '1') {
            const label = labelWithPercent(item.camera_status, item.camera_on_percent);
            tr.dataset.conflict = `${SOURCE_LABELS[source] || 'Data baru'}: ${label} (status manual tetap dipakai)`;
            renderRow(tr);
            return;
        }

        if (tr.dataset.computed && tr.dataset.source && tr.dataset.source !== source) {
            tr.dataset.conflict = item.camera_status !== tr.dataset.computed
                ? `${SOURCE_LABELS[source]}: ${labelWithPercent(item.camera_status, item.camera_on_percent)}. Klik statusnya bila ini yang benar.`
                : '';
            renderRow(tr);
            return;
        }

        let result = item;
        if (source === 'ai' && tr.dataset.source === 'ai' && tr.dataset.checks && item.checks) {
            result = { checks: +tr.dataset.checks, on_cam_checks: +tr.dataset.onChecks };
            addChecks(result, item);
        }
        tr.dataset.source = source;
        tr.dataset.computed = result.camera_status || '';
        tr.dataset.computedPercent = result.camera_on_percent ?? '';
        tr.dataset.status = tr.dataset.computed;
        tr.dataset.percent = tr.dataset.computedPercent;
        tr.dataset.checks = result.checks || '';
        tr.dataset.onChecks = result.on_cam_checks || '';
        tr.dataset.evidence = result.visual_evidence || '';
        renderRow(tr);
    }

    function placeRow(tr) {
        if (tr.dataset.pending === '1') groupCheck.appendChild(tr);
        else if (tr.dataset.evidence) groupData.appendChild(tr);
        else groupEmpty.appendChild(tr);
    }

    // Doubtful matches do not touch the row until the trainer confirms them
    function showNextPrompt(tr) {
        const queue = pendingQueues.get(tr) || [];
        const confirm = tr.querySelector('.camera-row-confirm');
        if (queue.length === 0) {
            tr.dataset.pending = '';
            tr.classList.remove('table-warning');
            confirm.classList.add('d-none');
            confirm.innerHTML = '';
        } else {
            const { item } = queue[0];
            tr.dataset.pending = '1';
            tr.classList.add('table-warning');
            confirm.innerHTML = `<span class="text-xs">Di ${escapeHtml(MEETING_LABEL)} tertulis "${escapeHtml(item.zoom_name)}" (mirip ${escapeHtml(item.similarity_score)}%): ${escapeHtml(item.visual_evidence || '')}. Benar orang ini?</span>
                <button type="button" class="btn btn-primary btn-xs ml-1 js-confirm-match">Ya, benar</button>
                <button type="button" class="btn btn-default btn-xs js-reject-match">Bukan</button>`;
            confirm.classList.remove('d-none');
        }
        placeRow(tr);
    }

    function applySourceItem(item, source) {
        const tr = item.participant_id ? rowFor(item.participant_id) : null;
        if (!tr) {
            addUnmatchedRow(Object.assign({}, item, { participant_id: null }), source);
            return;
        }

        const score = item.similarity_score ?? 100;
        const name = String(item.zoom_name || '').trim().toLowerCase();
        if (score < 80 && !confirmedNames(tr).includes(name)) {
            const queue = pendingQueues.get(tr) || [];
            queue.push({ item, source });
            pendingQueues.set(tr, queue);
            showNextPrompt(tr);
            return;
        }

        confirmName(tr, item.zoom_name);
        fillRow(tr, item, source);
        placeRow(tr);
    }

    function addUnmatchedRow(item, source) {
        const name = String(item.zoom_name || '').trim();
        if (!name) return;
        const existing = Array.from(groupCheck.querySelectorAll('tr.camera-unmatched-row'))
            .find(r => r.dataset.zoomName.toLowerCase() === name.toLowerCase());
        if (existing) {
            const stored = unmatchedItems.get(existing);
            if (stored.source === 'ai' && source === 'ai' && stored.item.checks && item.checks) {
                addChecks(stored.item, item);
                existing.querySelector('.camera-row-evidence').textContent = stored.item.visual_evidence || '';
            }
            return;
        }

        const tr = document.createElement('tr');
        tr.className = 'camera-unmatched-row table-warning';
        tr.dataset.zoomName = name;
        tr.dataset.search = name.toLowerCase();
        unmatchedItems.set(tr, { item: Object.assign({}, item), source });
        tr.innerHTML = `
            <td class="align-middle">
                <div class="font-weight-bold">"${escapeHtml(name)}" tidak ada di daftar peserta</div>
                <div class="text-xs text-muted camera-row-evidence">${escapeHtml(item.visual_evidence || '')}</div>
            </td>
            <td class="align-middle">
                <select class="form-control form-control-sm camera-pair-select" aria-label="Pasangkan ${escapeHtml(name)}">
                    <option value="">Pasangkan ke peserta…</option>
                </select>
                <button type="button" class="btn btn-link btn-xs text-muted px-0 js-ignore-name">Abaikan nama ini</button>
            </td>
        `;
        groupCheck.appendChild(tr);
    }

    // Options are built when the select is opened: only participants that have no data yet
    function fillPairOptions(select) {
        const current = select.value;
        const free = Array.from(groupEmpty.querySelectorAll('tr.camera-row'))
            .map(tr => participantsById.get(tr.dataset.participantId))
            .filter(Boolean)
            .sort((a, b) => String(a.name).localeCompare(String(b.name), 'id'));
        select.innerHTML = '<option value="">Pasangkan ke peserta…</option>'
            + free.map(p => `<option value="${escapeHtml(p.id)}">${escapeHtml(p.name)} (${escapeHtml(p.id_karyawan)})</option>`).join('');
        select.value = current;
    }

    // Group headers, counts and the bulk control
    function updateGroups() {
        const visibleRows = group => Array.from(group.querySelectorAll('tr:not(.camera-group-row)')).filter(tr => !tr.classList.contains('d-none'));
        const allRows = group => group.querySelectorAll('tr:not(.camera-group-row)').length;
        const onlyManual = Array.from(reviewSources).every(src => src === 'manual');

        const checkHeader = groupCheck.querySelector('.camera-group-row');
        checkHeader.querySelector('th').textContent = `Perlu dicek (${allRows(groupCheck)}): nama di ${MEETING_LABEL} tidak cocok persis dengan peserta terdaftar. Yang belum dicek tidak ikut disimpan.`;
        checkHeader.classList.toggle('d-none', visibleRows(groupCheck).length === 0);

        const dataHeader = groupData.querySelector('.camera-group-row');
        dataHeader.querySelector('th').textContent = `Ada data (${allRows(groupData)})`;
        dataHeader.classList.toggle('d-none', visibleRows(groupData).length === 0);

        const emptyHeader = groupEmpty.querySelector('.camera-group-row');
        emptyHeader.querySelector('.camera-group-title').textContent = onlyManual
            ? `Semua peserta (${allRows(groupEmpty)})`
            : `Belum ada data dari sumber ini (${allRows(groupEmpty)})`;
        emptyHeader.classList.toggle('d-none', visibleRows(groupEmpty).length === 0);
        const bulkTargets = visibleRows(groupEmpty).filter(tr => !tr.dataset.status && !tr.dataset.prior);
        emptyHeader.querySelector('.camera-bulk').classList.toggle('d-none', bulkTargets.length === 0);

        updateCameraSummary();
    }

    function savableRows() {
        return participantRows().filter(tr => tr.dataset.status && !isUntouchedPrior(tr));
    }

    function updateCameraSummary() {
        const rows = savableRows();
        const count = status => rows.filter(tr => tr.dataset.status === status).length;
        const on = count('on_cam'), partial = count('partial'), off = count('off_cam');
        const empty = participantRows().filter(tr => !tr.dataset.status && !tr.dataset.prior).length;
        const unchanged = participantRows().filter(isUntouchedPrior).length;
        const check = groupCheck.querySelectorAll('tr:not(.camera-group-row)').length;
        const replaced = rows.filter(tr => tr.dataset.prior && tr.dataset.prior !== tr.dataset.status).length;
        const offRows = rows.filter(tr => tr.dataset.status === 'off_cam');
        const hadir = offRows.filter(tr => (participantsById.get(tr.dataset.participantId) || {}).attendance_status === 'hadir').length;

        const summary = document.getElementById('cameraSaveSummary');
        const extra = [];
        if (empty) extra.push(`${empty} peserta belum diisi (tidak disimpan)`);
        if (unchanged) extra.push(`${unchanged} rekap tersimpan tidak berubah`);
        if (check) extra.push(`<span class="text-warning">${check} nama perlu dicek</span>`);
        if (replaced) extra.push(`${replaced} mengganti rekap sebelumnya`);
        summary.innerHTML = (rows.length
            ? `Akan disimpan: <strong>${rows.length} peserta</strong> — ${on} On Cam · ${partial} Tidak Full On Cam · ${off} Full Off Cam`
            : 'Belum ada status kamera yang dipilih.')
            + (extra.length ? `<span class="d-block text-xs text-muted">${extra.join(' · ')}</span>` : '');

        if (!btnConfirmSave.dataset.saving) {
            btnConfirmSave.innerHTML = `<i class="fas fa-check-circle mr-1"></i> Simpan Rekap Kamera (${rows.length} peserta)`;
            btnConfirmSave.disabled = rows.length === 0;
        }

        const wrap = document.getElementById('markTidakHadirWrap');
        if (wrap) {
            wrap.classList.toggle('d-none', off === 0);
            if (off === 0 && checkMarkTidakHadir) checkMarkTidakHadir.checked = false;
            document.getElementById('markTidakHadirLabel').innerHTML = `Tandai juga ${off} peserta Full Off Cam sebagai Tidak Hadir di presensi`
                + (hadir ? `<span class="d-block text-danger text-xs">${hadir} di antaranya sudah mengisi presensi Hadir dan akan diganti.</span>` : '');
        }
    }

    // Status picker, match confirmation, pairing and ignore: one delegated handler
    reviewTable.addEventListener('click', e => {
        const statusBtn = e.target.closest('.camera-status-picker [data-status]');
        if (statusBtn) {
            const tr = statusBtn.closest('tr.camera-row');
            setRowStatus(tr, tr.dataset.status === statusBtn.dataset.status ? '' : statusBtn.dataset.status);
            updateGroups();
            return;
        }

        const bulkBtn = e.target.closest('[data-bulk-status]');
        if (bulkBtn) {
            Array.from(groupEmpty.querySelectorAll('tr.camera-row'))
                .filter(tr => !tr.classList.contains('d-none') && !tr.dataset.status && !tr.dataset.prior)
                .forEach(tr => setRowStatus(tr, bulkBtn.dataset.bulkStatus));
            updateGroups();
            return;
        }

        const confirmBtn = e.target.closest('.js-confirm-match');
        const rejectBtn = e.target.closest('.js-reject-match');
        if (confirmBtn || rejectBtn) {
            const tr = (confirmBtn || rejectBtn).closest('tr.camera-row');
            const queue = pendingQueues.get(tr) || [];
            const next = queue.shift();
            if (next && confirmBtn) {
                confirmName(tr, next.item.zoom_name);
                fillRow(tr, next.item, next.source);
            } else if (next) {
                addUnmatchedRow(Object.assign({}, next.item, { participant_id: null }), next.source);
            }
            showNextPrompt(tr);
            updateGroups();
            return;
        }

        const ignoreBtn = e.target.closest('.js-ignore-name');
        if (ignoreBtn) {
            ignoreBtn.closest('tr').remove();
            updateGroups();
        }
    });

    reviewTable.addEventListener('focusin', e => {
        if (e.target.matches('.camera-pair-select')) fillPairOptions(e.target);
    });
    reviewTable.addEventListener('mousedown', e => {
        if (e.target.matches('.camera-pair-select')) fillPairOptions(e.target);
    });

    reviewTable.addEventListener('change', e => {
        const select = e.target.closest('.camera-pair-select');
        if (!select || !select.value) return;
        const unmatched = select.closest('tr');
        const stored = unmatchedItems.get(unmatched);
        const tr = rowFor(select.value);
        if (!stored || !tr) return;
        unmatched.remove();
        confirmName(tr, stored.item.zoom_name);
        fillRow(tr, stored.item, stored.source);
        placeRow(tr);
        updateGroups();
    });

    // Search by name, ID or meeting name
    const searchInput = document.getElementById('cameraReviewSearch');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            reviewTable.querySelectorAll('tr.camera-row, tr.camera-unmatched-row').forEach(tr => {
                const text = tr.dataset.search + ' ' + (tr.dataset.zoomName || '').toLowerCase();
                tr.classList.toggle('d-none', q !== '' && !text.includes(q));
            });
            updateGroups();
        });
    }

    function hasReviewData() {
        return groupCheck.querySelector('tr:not(.camera-group-row)') !== null
            || groupData.querySelector('tr:not(.camera-group-row)') !== null
            || participantRows().some(tr => tr.dataset.status && !isUntouchedPrior(tr));
    }

    function resetCameraReview() {
        reviewSources = new Set();
        if (searchInput) searchInput.value = '';
        if (checkMarkTidakHadir) checkMarkTidakHadir.checked = false;
        hideReviewAlert();
        buildParticipantRows();
    }

    function showStep(step) {
        [stepUpload, stepLoading, stepReview, stepNoneRead].forEach(el => el.classList.toggle('d-none', el !== step));
    }

    function showReview() {
        showStep(stepReview);
        updateGroups();
    }

    function showPicker() {
        document.getElementById('cameraMergeHint').classList.toggle('d-none', !hasReviewData());
        showStep(stepUpload);
    }

    const reviewAlert = document.getElementById('cameraSaveAlert');
    function showReviewAlert(message, kind = 'danger') {
        reviewAlert.className = `alert alert-${kind}`;
        reviewAlert.textContent = message;
    }
    function hideReviewAlert() {
        reviewAlert.className = 'alert alert-danger d-none';
        reviewAlert.textContent = '';
    }

    // Opening the modal: continue an unsaved review, otherwise load monitoring data straight away when there is
    // some, otherwise let the trainer pick a source.
    if (typeof $ !== 'undefined') {
        $('#scanZoomModal').on('show.bs.modal', function (e) {
            if (e.target !== this || !stepLoading.classList.contains('d-none')) return;
            if (!rowsBuilt) buildParticipantRows();
            if (checkMarkTidakHadir) checkMarkTidakHadir.checked = false;
            const info = document.getElementById('zoomMonitorInfo');
            if (info) info.classList.add('d-none');
            document.getElementById('cameraResumeNote').classList.add('d-none');

            if (hasReviewData()) {
                document.getElementById('cameraResumeNote').classList.remove('d-none');
                showReview();
            } else if (HAS_MONITOR_DATA && !monitorAutoLoaded) {
                monitorAutoLoaded = true;
                loadMonitoring(true);
            } else {
                showPicker();
            }
        });
    }

    const btnResetCameraReview = document.getElementById('btnResetCameraReview');
    if (btnResetCameraReview) {
        btnResetCameraReview.addEventListener('click', e => {
            e.preventDefault();
            resetCameraReview();
            document.getElementById('cameraResumeNote').classList.add('d-none');
            showPicker();
        });
    }

    if (btnBackToUpload) btnBackToUpload.addEventListener('click', () => { hideReviewAlert(); showPicker(); });

    const btnNoneReadBack = document.getElementById('btnNoneReadBack');
    if (btnNoneReadBack) btnNoneReadBack.addEventListener('click', showPicker);

    // Save: no alert() and no page reload; the participant list is updated in place
    const saveNotice = document.getElementById('cameraSaveNotice');
    if (btnConfirmSave) {
        btnConfirmSave.addEventListener('click', () => {
            const rows = savableRows();
            if (rows.length === 0) return;
            const items = rows.map(tr => ({
                participant_id: +tr.dataset.participantId,
                zoom_name: tr.dataset.zoomName ? tr.dataset.zoomName.slice(0, 255) : null,
                camera_status: tr.dataset.status,
                camera_on_percent: tr.dataset.percent === '' ? null : +tr.dataset.percent,
            }));
            const markTidakHadir = !!(checkMarkTidakHadir && checkMarkTidakHadir.checked);

            hideReviewAlert();
            btnConfirmSave.dataset.saving = '1';
            btnConfirmSave.disabled = true;
            btnConfirmSave.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Menyimpan…';
            const done = () => { delete btnConfirmSave.dataset.saving; updateCameraSummary(); };

            fetch(SAVE_CAMERA_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                body: JSON.stringify({ items, mark_off_cam_as_tidak_hadir: markTidakHadir }),
            })
            .then(res => res.json().catch(() => ({})).then(data => ({ res, data })))
            .then(({ res, data }) => {
                done();
                if (!res.ok || !data.success) {
                    const message = res.status === 422 && data.message ? data.message : (data.message || describeHttpError(res.status));
                    showReviewAlert(`Gagal menyimpan: ${message} Isian Anda tidak hilang.`);
                    return;
                }

                let markedAbsent = 0;
                items.forEach(item => {
                    const p = participantsById.get(String(item.participant_id));
                    if (p) {
                        p.camera_status = item.camera_status;
                        p.camera_on_percent = item.camera_on_percent;
                    }
                    const absent = markTidakHadir && item.camera_status === 'off_cam';
                    if (absent) {
                        markedAbsent++;
                        if (p) p.attendance_status = 'tidak_hadir';
                    }
                    const badge = document.getElementById(`cameraBadge_${item.participant_id}`);
                    if (!badge) return;
                    badge.innerHTML = `<span class="training-camera is-${item.camera_status}"><i class="fas fa-video mr-1" aria-hidden="true"></i>${escapeHtml(CAMERA_LABELS[item.camera_status])}${item.camera_on_percent !== null ? ` (${item.camera_on_percent}% on cam)` : ''}</span>`;
                    const dot = absent ? badge.closest('td').querySelector('.status-dot') : null;
                    if (dot) {
                        dot.className = 'status-dot is-danger';
                        dot.textContent = 'Tidak hadir';
                    }
                });

                // Kolom Kamera belum tampil (belum ada rekap): muat ulang supaya server merender kolomnya.
                if (!document.querySelector('.camera-badge-box') && items.length) {
                    location.reload();
                    return;
                }

                const count = status => items.filter(i => i.camera_status === status).length;
                saveNotice.textContent = `Rekap kamera tersimpan untuk ${items.length} peserta: ${count('on_cam')} On Cam, ${count('partial')} Tidak Full On Cam, ${count('off_cam')} Full Off Cam.`
                    + (markedAbsent ? ` ${markedAbsent} peserta ditandai Tidak Hadir` + (data.replaced_hadir ? ` (${data.replaced_hadir} sebelumnya tercatat Hadir).` : '.') : '');
                saveNotice.classList.remove('d-none');

                if (typeof $ !== 'undefined') $('#scanZoomModal').modal('hide');
                resetCameraReview();
                saveNotice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            })
            .catch(err => {
                console.error(err);
                done();
                showReviewAlert('Gagal menyimpan karena koneksi terputus. Periksa koneksi lalu klik Simpan lagi; isian Anda tidak hilang.');
            });
        });
    }

    // Isi sendiri: every participant is already a row, so just open the table
    const btnDirectManualInput = document.getElementById('btnDirectManualInput');
    if (btnDirectManualInput) {
        btnDirectManualInput.addEventListener('click', () => {
            setReviewSource('manual');
            showReview();
            if (searchInput) searchInput.focus({ preventScroll: true });
        });
    }

    // Data monitoring meeting (Zoom SDK / laporan platform)
    const btnLoadMonitorCamera = document.getElementById('btnLoadMonitorCamera');
    const monitorInfo = document.getElementById('zoomMonitorInfo');

    async function loadMonitoring(auto) {
        const idleLabel = '<i class="fas fa-satellite-dish mr-1"></i> Pakai Data Monitoring';
        if (monitorInfo) monitorInfo.classList.add('d-none');
        if (auto) {
            document.getElementById('zoomLoadingTitle').textContent = `Mengambil data monitoring ${MEETING_LABEL}…`;
            document.getElementById('zoomLoadingSub').textContent = '';
            showStep(stepLoading);
        } else if (btnLoadMonitorCamera) {
            btnLoadMonitorCamera.disabled = true;
            btnLoadMonitorCamera.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Mengambil data...';
        }

        try {
            const res = await fetch(MONITOR_CAMERA_URL, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error(describeHttpError(res.status));
            const list = (await res.json()).camera_participants || [];

            if (list.length === 0) {
                showPicker();
                if (monitorInfo) {
                    monitorInfo.className = 'alert alert-info mx-3 mb-3';
                    monitorInfo.textContent = `Monitoring ${MEETING_LABEL} belum merekam status kamera peserta.`;
                }
                return;
            }

            setReviewSource('monitor');
            list.forEach(item => applySourceItem(item, 'monitor'));
            showReview();
        } catch (err) {
            console.error(err);
            showPicker();
            if (monitorInfo) {
                monitorInfo.className = 'alert alert-danger mx-3 mb-3';
                monitorInfo.textContent = err.message || 'Data monitoring tidak bisa diambil. Coba lagi.';
            }
        } finally {
            document.getElementById('zoomLoadingTitle').textContent = `Menganalisis Screenshot ${MEETING_LABEL}...`;
            if (btnLoadMonitorCamera) {
                btnLoadMonitorCamera.disabled = false;
                btnLoadMonitorCamera.innerHTML = idleLabel;
            }
        }
    }

    if (btnLoadMonitorCamera) btnLoadMonitorCamera.addEventListener('click', () => loadMonitoring(false));
})();
</script>
@endsection
