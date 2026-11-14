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
    $hasAttendanceColumn = $training->has_attendance || $training->isOnline();
    $participantColumnCount = 2 + (int) $hasAttendanceColumn + (int) $training->has_pretest + (int) $training->has_posttest + (int) $training->has_evaluation;
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
    $sectionQuery = request()->only(['peserta', 'wa', 'page']);
@endphp

<section class="content-header training-detail-header">
    <div class="container-fluid">
        <a class="training-back-link" href="{{ route('training.index') }}">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Jadwal pelatihan
        </a>
        <div class="training-heading">
            <div>
                <h1 class="training-title">{{ $training->title }}</h1>
                <p class="training-session-meta">
                    <span>{{ $training->training_date->translatedFormat('j F Y') }} · {{ substr($training->start_time, 0, 5) }}–{{ substr($training->end_time, 0, 5) }} WIB</span>
                    <x-training-status-badge :status="$training->status" />
                </p>
                <p class="training-session-meta">
                    @if($training->trainer)
                        <span>Pemateri <strong>{{ $training->trainer->nama_lengkap }}</strong>@if($training->trainer->divisi) · {{ $training->trainer->divisi->name }}@endif</span>
                    @endif
                    @if($training->zoom_link)
                        <a href="{{ $training->zoom_link }}" target="_blank" rel="noopener">Buka Zoom <i class="fas fa-external-link-alt" aria-hidden="true"></i></a>
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
                        <ul class="small text-muted pl-3 mb-0">
                            <li>Kolom dibaca dari judulnya di baris pertama; hanya sheet pertama yang dibaca. Kolom lain (mis. RSC, Detail Cabang) diabaikan.</li>
                            <li><b>No HP wajib</b> di setiap baris, karena undangan dan link portal dikirim lewat WhatsApp.</li>
                            <li>Peserta dicocokkan ke master User lewat <b>Employee ID</b>, lalu username, lalu No HP. Karyawan yang belum ada di master User tidak dibuat; tambahkan dulu lewat Pengaturan &gt; User atau sinkronisasi DnD.</li>
                            <li>Nama, Job Position, Divisi, dan No HP di master User ikut diperbarui bila Anda berwenang atas user itu. No HP adalah login WhatsApp, jadi hanya admin yang berwenang yang dapat mengubahnya.</li>
                            <li>Peserta yang sudah terdaftar tidak didobel. Baris yang ditolak dan catatannya dikabarkan lewat Notifikasi.</li>
                        </ul>
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
                                    <i class="fas fa-video-slash"></i> Scan Off Cam
                                </button>
                                <button type="button" class="dropdown-item" id="btnToggleManualOffCamMode">Input Off Cam manual</button>
                            @endif
                        </div>
                    </div>
                </div>
            </header>

            @if($training->has_attendance || $quizStats || $training->has_evaluation)
                <dl class="training-totals">
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
                            <dd class="training-total-meta">
                                Rata-rata <b data-training-stat="{{ $keys['average'] }}">{{ $stats[$keys['submitted']] > 0 ? number_format($stats[$keys['average']], 1, ',', '.') : '—' }}</b>
                            </dd>
                        </div>
                    @endforeach
                    @if($training->has_evaluation)
                        <div>
                            <dt>Evaluasi</dt>
                            <dd class="training-total-value">
                                <strong>{{ $evaluation['count'] }}</strong>
                                <span aria-hidden="true">/</span><span class="sr-only">mengisi dari</span>
                                <span>{{ $stats['total'] }}</span>
                            </dd>
                            @if($evaluation['average'] !== null)
                                <dd class="training-total-meta">Rata-rata <span class="text-nowrap"><b>{{ number_format($evaluation['average'], 2, ',', '.') }}</b> / {{ \App\Support\TrainingEvaluationForm::SCALE_MAX }}</span></dd>
                            @endif
                        </div>
                    @endif
                </dl>
            @endif

            <div class="training-roster-toolbar">
                <form action="{{ route('training.show', $training) }}#peserta" method="GET" class="m-0 training-roster-search" role="search">
                    @if($failedWaOnly)
                        <input type="hidden" name="wa" value="gagal">
                    @endif
                    <x-search-field name="peserta" placeholder="Cari peserta"
                        :clear-url="route('training.show', $failedWaOnly ? [$training, 'wa' => 'gagal'] : $training).'#peserta'" />
                </form>
                @if($waFailedCount > 0 || $failedWaOnly)
                    <a href="{{ route('training.show', [$training, ...request()->only('peserta'), ...($failedWaOnly ? [] : ['wa' => 'gagal'])]) }}#peserta"
                        class="btn btn-default btn-sm">
                        {{ $failedWaOnly ? 'Tampilkan semua peserta' : 'Undangan gagal ('.$waFailedCount.')' }}
                    </a>
                @endif
            </div>

            <div id="bannerManualOffCamMode" class="training-notice training-notice-warning training-roster-notice d-none">
                <div>
                    <strong>Mode Off Cam aktif</strong>
                    <span class="d-block">Pilih peserta dan gunakan + atau − untuk mengubah hitungan.</span>
                </div>
                <button type="button" class="btn btn-default btn-sm" id="btnExitManualOffCamMode">Selesai mencatat</button>
            </div>

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
                            {{-- Tanpa presensi, kolom ini hanya memuat catatan kamera (pelatihan daring) atau tidak tampil. --}}
                            @if($training->has_attendance)
                                <th scope="col">Presensi</th>
                            @elseif($training->isOnline())
                                <th scope="col">Kamera</th>
                            @endif
                            @foreach($quizStats as $label => $keys)
                                <th scope="col" class="training-participant-score">{{ $label }}</th>
                            @endforeach
                            @if($training->has_evaluation)
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
                                    <span class="offcam-badge-box" id="offcamBadge_{{ $part->id }}">
                                        @if($part->is_off_cam && ($part->off_cam_count ?? 1) > 0)
                                            <span class="training-offcam">Off Cam {{ $part->off_cam_count ?: 1 }}×</span>
                                        @elseif(! $training->has_attendance)
                                            <span class="text-muted">—</span>
                                        @endif
                                    </span>
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
                            @if($training->has_evaluation)
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
                                        <div class="dropdown-divider"></div>
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
                                            Salin tautan peserta
                                        </a>

                                        <form action="/training/{{ $training->id }}/send-wa/{{ $part->id }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="dropdown-item">
                                                Kirim undangan WhatsApp
                                            </button>
                                        </form>
                                        @if($waLinks)
                                            <form action="{{ route('training.send-manual-wa', [$training, $part]) }}" method="POST" target="_blank" class="d-inline">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    Kirim Manual via WhatsApp Web
                                                </button>
                                            </form>
                                        @else
                                            <span class="dropdown-item text-muted" title="Nomor WhatsApp peserta kosong atau tidak valid. Perbaiki di Pengaturan &gt; User.">Kirim Manual (No. HP tidak valid)</span>
                                        @endif

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

                                        <div class="dropdown-divider"></div>
                                        <form action="{{ route('training.participants.remove', [$training, $part]) }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Keluarkan '.$userName.' dari pelatihan ini? Link portalnya tidak berlaku lagi, dan hasil kuis, presensi, serta evaluasinya ikut dihapus.'))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                Keluarkan dari Pelatihan
                                            </button>
                                        </form>
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
                                @include('training.detail.participant-details')

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
                        <tr role="row">
                            <td role="cell" class="training-roster-empty" colspan="{{ $participantColumnCount }}">
                                @if(filled(request('peserta')))
                                    <x-empty-state icon="fa-search" title="Peserta tidak ditemukan" text="Coba kata kunci lain: nama, ID karyawan, No. HP, atau divisi." />
                                @elseif(request('wa') === 'gagal')
                                    <x-empty-state icon="fa-check" title="Tidak ada undangan WhatsApp yang gagal" text="Semua kiriman otomatis terakhir berhasil atau belum dikirim.">
                                        <a href="{{ route('training.show', $training) }}#peserta" class="btn btn-default btn-sm">Tampilkan semua peserta</a>
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
                {{ $participants->links('pagination::bootstrap-4') }}
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
    const statFields = document.querySelectorAll('[data-training-stat]');
    const scoreFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    function updateStat(el, value) {
        if (el && el.textContent !== String(value)) {
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

                const values = {
                    ...data,
                    avgScore: data.quizSubmitted ? scoreFormat.format(data.avgScore) : '—',
                    pretestAvg: data.pretestSubmitted ? scoreFormat.format(data.pretestAvg) : '—',
                };
                statFields.forEach(el => updateStat(el, values[el.dataset.trainingStat]));
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
                btnToggle.textContent = 'Selesai mencatat Off Cam';
            }
            if (thAction) {
                thAction.textContent = 'Catat Off Cam';
            }
        } else {
            defaultWrappers.forEach(el => el.classList.remove('d-none'));
            quickWrappers.forEach(el => el.classList.add('d-none'));
            if (banner) {
                banner.classList.add('d-none');
                banner.classList.remove('d-flex');
            }
            if (btnToggle) {
                btnToggle.textContent = 'Input Off Cam manual';
            }
            if (thAction) {
                thAction.textContent = 'Aksi';
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
                                <span class="training-offcam">Off Cam ${count}×</span>
                            `;
                        } else {
                            badgeBox.textContent = @js($training->has_attendance ? '' : '—');
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
