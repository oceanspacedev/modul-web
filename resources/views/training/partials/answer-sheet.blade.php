{{--
    Isi modal "Lembar Jawaban" satu peserta di halaman detail pelatihan. Dimuat lewat AJAX saat modal dibuka
    (TrainingController::answerSheet), supaya halaman detail tidak merender semua soal untuk setiap peserta.
--}}
<div class="modal-header">
    <h5 class="modal-title">
        Lembar Jawaban{{ $training->has_pretest && $training->has_posttest ? ' '.\App\Models\Training::phaseLabel($quiz->phase) : '' }}: {{ $userName }}
    </h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<div class="modal-body">
    <!-- STATS SUMMARY -->
    <div class="row no-gutters mb-4 border rounded text-center">
        <div class="col-3 p-3 border-right">
            <p class="stat-tile-label">Nilai Akhir</p>
            <p class="stat-tile-value">{{ $quiz->score }}</p>
        </div>
        <div class="col-3 p-3 border-right">
            <p class="stat-tile-label">Nilai PG ({{ $quiz->correct_answers }} Benar)</p>
            <p class="stat-tile-value">{{ $quiz->mc_score ?? $quiz->score }}</p>
        </div>
        <div class="col-3 p-3 border-right">
            <p class="stat-tile-label">Nilai Essay</p>
            @if($quiz->essay_status === 'graded')
                <p class="stat-tile-value">{{ $quiz->essay_score }}</p>
            @else
                <p class="text-sm text-muted mb-0 mt-1">Belum Dinilai</p>
            @endif
        </div>
        <div class="col-3 p-3">
            <p class="stat-tile-label">Waktu Submit</p>
            <p class="text-sm font-weight-bold mb-0 mt-1">{{ \Carbon\Carbon::parse($quiz->submitted_at)->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    <!-- CATATAN ANTI-CURANG AUDIT -->
    @if(($quiz->tab_switch_count ?? 0) > 0 || $quiz->is_force_submitted)
    <div class="alert alert-{{ $quiz->is_force_submitted ? 'danger' : 'warning' }} mb-4 text-left">
        <div>
            <strong class="d-block mb-1">
                Catatan Integritas: {{ $quiz->is_force_submitted ? 'Kuis Dikunci Otomatis (Melebihi Toleransi Pindah Tab)' : 'Terdeteksi Keluar dari Halaman Kuis' }}
            </strong>
            <div class="text-sm">
                Peserta terdeteksi keluar dari halaman kuis / membuka aplikasi lain sebanyak <strong>{{ $quiz->tab_switch_count }} kali</strong>.
                @if(!empty($quiz->violation_logs) && is_array($quiz->violation_logs))
                    <div class="mt-2 pt-2 border-top">
                        <span class="font-weight-bold d-block mb-1 text-xs">Riwayat Deteksi Pelanggaran:</span>
                        <ul class="mb-0 pl-3 text-xs">
                            @foreach($quiz->violation_logs as $log)
                                <li>
                                    <strong>{{ $log['type'] ?? 'Pindah Tab Browser' }}</strong>
                                    (Pelanggaran ke-{{ $log['count'] ?? '1' }}) pada
                                    {{ isset($log['time']) ? \Carbon\Carbon::parse($log['time'])->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') . ' WIB' : '-' }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- FORM PENILAIAN ESSAY OLEH PEMATERI -->
    @if($hasEssay)
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">Penilaian Soal Essay oleh Pemateri</h3>
        </div>
        <div class="card-body">
            <form action="/training/{{ $training->id }}/grade-essay/{{ $quiz->id }}" method="POST">
                @csrf
                <div class="row align-items-end">
                    <div class="col-md-3 mb-2 mb-md-0">
                        <label class="text-xs mb-1">Nilai Essay (0-100)</label>
                        <input type="number" name="essay_score" class="form-control form-control-sm" min="0" max="100" step="1" value="{{ $quiz->essay_score !== null ? round($quiz->essay_score) : '' }}" placeholder="Contoh: 90" required>
                    </div>
                    <div class="col-md-6 mb-2 mb-md-0">
                        <label class="text-xs mb-1">Catatan Evaluasi (Opsional)</label>
                        <input type="text" name="essay_feedback" class="form-control form-control-sm" value="{{ $quiz->essay_feedback ?? '' }}" placeholder="Contoh: Pemahaman konsep materi baik.">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm btn-block">
                            Simpan Nilai
                        </button>
                    </div>
                </div>
                @if($quiz->essay_status === 'graded')
                    <div class="text-xs text-success mt-2">
                        Nilai essay tersimpan {{ $quiz->essay_score }}/100. Nilai akhir total dihitung gabungan PG dan Essay.
                    </div>
                @else
                    <div class="text-xs text-muted mt-2">
                        Nilai essay akan otomatis dikombinasikan dengan nilai PG secara proporsional.
                    </div>
                @endif
            </form>
        </div>
    </div>
    @endif

    <h6 class="section-title mb-3">Rincian Pertanyaan & Jawaban Peserta</h6>
    {{-- Hanya soal yang dipakai fase ini (pretest dan posttest bisa memakai soal berbeda). --}}
    @foreach($training->questionsFor($quiz->phase) as $qIndex => $question)
        @php
            $ansData = $quiz->answers[$question->id] ?? null;
            $userAns = $ansData['user_answer'] ?? null;
            $isEssay = ($question->type === 'essay');
            $isCorrect = $ansData['is_correct'] ?? false;
        @endphp
        <div class="p-3 mb-3 rounded border">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <span class="badge badge-secondary mr-1">
                        #{{ $qIndex + 1 }} {{ $isEssay ? '(Essay)' : '(PG)' }}
                    </span>
                    <strong>{{ $question->question }}</strong>
                    @if($question->imageUrl())
                        <img src="{{ $question->imageUrl() }}" alt="Gambar soal" class="quiz-img quiz-opt-img mt-2">
                    @endif
                </div>
                <div>
                    @if($isEssay)
                        <span class="badge badge-primary">Tersimpan</span>
                    @else
                        <span class="badge {{ $isCorrect ? 'badge-success' : 'badge-danger' }}">
                            {{ $isCorrect ? 'Benar' : 'Salah' }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="text-sm mt-2 pt-2 border-top">
                @if($isEssay)
                    <div class="mb-2">
                        <span class="text-muted d-block">Jawaban Essay Peserta:</span>
                        <div class="p-2 bg-light rounded border mt-1" style="white-space: pre-wrap;">{{ $userAns ?: '(Tidak ada jawaban)' }}</div>
                    </div>
                    @if($question->correct_answer)
                        <div class="text-muted"><strong>Pedoman Acuan Jawaban:</strong> {{ $question->correct_answer }}</div>
                    @endif
                @else
                    <div class="mb-1">
                        Jawaban Peserta: <strong>{{ strtoupper($userAns ?? '-') }}</strong> ({{ $question->{'option_' . $userAns} ?? '-' }})
                        @if($userAns && $question->optionImageUrl($userAns))
                            <img src="{{ $question->optionImageUrl($userAns) }}" alt="Gambar jawaban peserta" class="quiz-img quiz-img-thumb mt-1">
                        @endif
                    </div>
                    @if($question->correct_answer)
                        <div class="text-success">
                            Kunci Jawaban: <strong>{{ strtoupper($question->correct_answer) }}</strong> ({{ $question->{'option_' . $question->correct_answer} ?? '-' }})
                            @if($question->optionImageUrl($question->correct_answer))
                                <img src="{{ $question->optionImageUrl($question->correct_answer) }}" alt="Gambar kunci jawaban" class="quiz-img quiz-img-thumb mt-1">
                            @endif
                        </div>
                    @endif
                @endif

                @if($question->explanation)
                    <div class="text-muted mt-2 pt-2 border-top font-italic">Catatan: {{ $question->explanation }}</div>
                @endif
            </div>
        </div>
    @endforeach
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
</div>
