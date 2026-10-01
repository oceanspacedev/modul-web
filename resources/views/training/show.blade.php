@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-3 align-items-center">
            <div class="col-sm-7">
                <div class="d-flex align-items-center">
                    <a href="/training" class="btn btn-default btn-sm mr-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                            {{ $training->title }}
                        </h1>
                        <span class="text-muted small">
                            Pelaksanaan: {{ \Carbon\Carbon::parse($training->training_date)->format('d F Y') }} &bull; {{ substr($training->start_time, 0, 5) }} - {{ substr($training->end_time, 0, 5) }} WIB
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-sm-5 text-right mt-2 mt-sm-0">
                <div class="btn-group btn-group-sm">
                    <a href="/training/{{ $training->id }}/questions" class="btn btn-default">
                        Soal Kuis ({{ $training->questions->count() }})
                    </a>
                    <a href="/training/{{ $training->id }}/export" class="btn btn-default">
                        Export Nilai
                    </a>
                    <a href="/training/{{ $training->id }}/edit" class="btn btn-default">
                        Edit Pelatihan
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <!-- TRAINING DETAIL OVERVIEW -->
        <div class="card mb-4">
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-lg-8 pr-lg-4 mb-3 mb-lg-0">
                        <div class="d-flex align-items-center mb-3">
                            <span class="text-muted small mr-2">Status:</span>
                            @if($training->status === 'scheduled')
                                <span class="badge badge-warning text-white px-2 py-1 mr-3">Dijadwalkan</span>
                            @elseif($training->status === 'ongoing')
                                <span class="badge badge-success px-2 py-1 mr-3">Berlangsung</span>
                            @elseif($training->status === 'completed')
                                <span class="badge badge-secondary px-2 py-1 mr-3">Selesai</span>
                            @else
                                <span class="badge badge-danger px-2 py-1 mr-3">Dibatalkan</span>
                            @endif

                            <span class="text-muted small mr-2">Akses Kuis:</span>
                            @if($training->is_quiz_active)
                                <span class="badge badge-success px-2 py-1">Terbuka untuk Peserta</span>
                            @else
                                <span class="badge badge-secondary px-2 py-1">Terkunci</span>
                            @endif
                        </div>

                        <div class="row text-sm mb-3">
                            <div class="col-sm-4 mb-2 mb-sm-0">
                                <span class="text-muted small d-block">Pemateri:</span>
                                <strong>{{ $training->trainer->full_name ?? '-' }}</strong>
                            </div>
                            <div class="col-sm-4 mb-2 mb-sm-0">
                                <span class="text-muted small d-block">Tautan Meeting:</span>
                                @if($training->zoom_link)
                                    <a href="{{ $training->zoom_link }}" target="_blank" class="text-primary text-truncate d-block" style="max-width: 200px;">
                                        {{ $training->zoom_link }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                            <div class="col-sm-4">
                                <span class="text-muted small d-block">Waktu:</span>
                                <strong>{{ substr($training->start_time, 0, 5) }} - {{ substr($training->end_time, 0, 5) }} WIB</strong>
                            </div>
                        </div>

                        @if($training->description)
                            <div class="p-3 bg-light rounded text-sm text-muted border mt-2">
                                <strong class="d-block text-dark mb-1">Deskripsi / Silabus:</strong>
                                {{ $training->description }}
                            </div>
                        @endif
                    </div>

                    <!-- CONTROL ACTIONS -->
                    <div class="col-lg-4 pl-lg-4 border-left">
                        <span class="text-muted small font-weight-bold text-uppercase d-block mb-3">Aksi Pelatihan</span>
                        <div class="d-flex flex-column" style="gap: 10px;">
                            <form action="/training/{{ $training->id }}/broadcast-wa" method="POST" onsubmit="return confirm('Kirim notifikasi WhatsApp ke {{ $training->participants->count() }} peserta?')">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm btn-block text-left py-2 font-weight-bold">
                                    <i class="fab fa-whatsapp mr-1"></i> Broadcast WA ke Seluruh Peserta
                                </button>
                            </form>

                            <form action="/training/{{ $training->id }}/toggle-quiz" method="POST">
                                @csrf
                                @if($training->is_quiz_active)
                                    <button type="submit" class="btn btn-outline-danger btn-sm btn-block text-left py-2">
                                        Tutup Akses Kuis
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-outline-primary btn-sm btn-block text-left py-2">
                                        Buka Akses Kuis Peserta
                                    </button>
                                @endif
                            </form>

                            <form action="/training/{{ $training->id }}/status" method="POST" class="d-flex mt-1" style="gap: 6px;">
                                @csrf
                                <select name="status" class="form-control form-control-sm">
                                    <option value="scheduled" {{ $training->status === 'scheduled' ? 'selected' : '' }}>Ubah: Dijadwalkan</option>
                                    <option value="ongoing" {{ $training->status === 'ongoing' ? 'selected' : '' }}>Ubah: Berlangsung</option>
                                    <option value="completed" {{ $training->status === 'completed' ? 'selected' : '' }}>Ubah: Selesai</option>
                                    <option value="cancelled" {{ $training->status === 'cancelled' ? 'selected' : '' }}>Ubah: Dibatalkan</option>
                                </select>
                                <button type="submit" class="btn btn-default btn-sm px-3">Update</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SUMMARY STATS BAR -->
        <div class="row mb-4">
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Total Peserta</span>
                    <strong class="h4 mb-0 text-dark">{{ $stats['total'] }}</strong>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Hadir</span>
                    <strong class="h4 mb-0 text-success">{{ $stats['attended'] }}</strong>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Tidak Hadir</span>
                    <strong class="h4 mb-0 text-danger">{{ $stats['absent'] }}</strong>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Belum Absen</span>
                    <strong class="h4 mb-0 text-warning">{{ $stats['pending'] }}</strong>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Selesai Kuis</span>
                    <strong class="h4 mb-0 text-primary">{{ $stats['quizSubmitted'] }}</strong>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Rata-rata Nilai</span>
                    <strong class="h4 mb-0 text-info">{{ $stats['avgScore'] }}</strong>
                </div>
            </div>
        </div>

        <!-- TABEL REKAP PESERTA & NILAI -->
        <div class="card mb-4">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <h3 class="card-title font-weight-bold">
                    Rekap Absensi & Nilai Peserta
                </h3>
                <div class="card-tools">
                    <input type="text" id="filterTableInput" class="form-control form-control-sm" placeholder="Filter peserta..." style="width: 220px;">
                </div>
            </div>

            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0" id="participantsTable" style="font-size: 0.92rem;">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th class="text-center" style="width: 50px; padding: 14px 16px;">No</th>
                            <th style="padding: 14px 16px;">Nama Peserta</th>
                            <th style="padding: 14px 16px;">Divisi</th>
                            <th style="padding: 14px 16px;">No WhatsApp</th>
                            <th class="text-center" style="padding: 14px 16px;">Notif WA</th>
                            <th class="text-center" style="padding: 14px 16px;">Absensi</th>
                            <th class="text-center" style="padding: 14px 16px;">Status Kuis</th>
                            <th class="text-center" style="padding: 14px 16px;">Nilai</th>
                            <th class="text-center" style="width: 140px; padding: 14px 16px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($training->participants as $index => $part)
                        @php
                            $user = $part->user;
                            $quiz = $part->quizResult;
                        @endphp
                        <tr>
                            <td class="text-center align-middle text-muted" style="padding: 16px;">{{ $index + 1 }}</td>
                            <td class="align-middle" style="padding: 16px;">
                                <strong class="d-block text-dark">{{ $user->full_name }}</strong>
                                <span class="text-muted small">{{ $user->id_karyawan ?? '-' }} &bull; {{ $user->email }}</span>
                            </td>
                            <td class="align-middle text-muted" style="padding: 16px;">
                                {{ $user->divisi->name ?? '-' }}
                            </td>
                            <td class="align-middle text-muted" style="padding: 16px;">
                                {{ $user->no_wa ?? '-' }}
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($part->wa_sent_at)
                                    <span class="badge badge-success px-2 py-1" title="{{ $part->wa_status }} - {{ $part->wa_sent_at }}">
                                        Terkirim
                                    </span>
                                @else
                                    <span class="badge badge-secondary px-2 py-1">Belum</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($part->attendance_status === 'hadir')
                                    <span class="badge badge-success px-2 py-1">Hadir</span>
                                    <span class="d-block text-muted text-xs mt-1">
                                        {{ \Carbon\Carbon::parse($part->attended_at)->format('H:i') }} WIB
                                    </span>
                                @elseif($part->attendance_status === 'tidak_hadir')
                                    <span class="badge badge-danger px-2 py-1">Tidak Hadir</span>
                                    @if($part->attendance_notes)
                                        <span class="d-block text-muted text-xs mt-1">{{ $part->attendance_notes }}</span>
                                    @endif
                                @else
                                    <span class="badge badge-warning text-white px-2 py-1">Belum Absen</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($quiz)
                                    <span class="badge badge-info px-2 py-1">
                                        Selesai ({{ $quiz->correct_answers }}/{{ $quiz->total_questions }})
                                    </span>
                                    @if($training->questions->count() > $quiz->total_questions)
                                        <div class="mt-1">
                                            <span class="badge badge-warning text-white text-xs px-2" title="Pemateri menambah soal baru">
                                                +{{ $training->questions->count() - $quiz->total_questions }} Soal Baru
                                            </span>
                                        </div>
                                    @endif
                                    <span class="d-block text-muted text-xs mt-1">
                                        {{ \Carbon\Carbon::parse($quiz->submitted_at)->format('d/m H:i') }}
                                    </span>
                                @else
                                    <span class="text-muted small">Belum Mengerjakan</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($quiz)
                                    <strong class="{{ $quiz->score >= 75 ? 'text-success' : ($quiz->score >= 50 ? 'text-warning' : 'text-danger') }}" style="font-size: 1.1rem;">
                                        {{ $quiz->score }}
                                    </strong>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                <div class="d-flex justify-content-center align-items-center" style="gap: 6px;">
                                    <!-- COPY LINK -->
                                    <button type="button" class="btn btn-xs btn-default copy-link-btn" 
                                            data-link="{{ url('/training/portal/' . $part->token) }}" title="Salin Tautan Akses">
                                        <i class="fas fa-copy"></i>
                                    </button>

                                    <!-- RESEND WA -->
                                    <form action="/training/{{ $training->id }}/send-wa/{{ $part->id }}" method="POST" class="d-inline m-0">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-default" title="Kirim WA Ulang">
                                            <i class="fab fa-whatsapp text-success"></i>
                                        </button>
                                    </form>

                                    <!-- RESET QUIZ -->
                                    @if($quiz)
                                        <form action="/training/{{ $training->id }}/reset-quiz/{{ $part->id }}" method="POST" class="d-inline m-0" onsubmit="return confirm('Reset kuis untuk {{ $user->full_name }}?')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-default" title="Reset Kuis">
                                                <i class="fas fa-undo text-warning"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <!-- DETAIL MODAL -->
                                    @if($quiz && $quiz->answers)
                                        <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#answerModal_{{ $quiz->id }}" title="Lihat Lembar Jawaban">
                                            <i class="fas fa-eye text-primary"></i>
                                        </button>
                                    @endif
                                </div>

                                <!-- MODAL LEMBAR JAWABAN -->
                                @if($quiz && $quiz->answers)
                                <div class="modal fade text-left" id="answerModal_{{ $quiz->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header py-3">
                                                <h5 class="modal-title font-weight-bold">
                                                    Lembar Jawaban: {{ $user->full_name }}
                                                </h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row mb-4 bg-light p-3 rounded border">
                                                    <div class="col-4">
                                                        <span class="text-muted small d-block">Nilai Akhir:</span>
                                                        <strong class="h4 text-primary mb-0">{{ $quiz->score }} / 100</strong>
                                                    </div>
                                                    <div class="col-4">
                                                        <span class="text-muted small d-block">Hasil:</span>
                                                        <strong class="h5 text-success mb-0">{{ $quiz->correct_answers }} Benar</strong> dari {{ $quiz->total_questions }} Soal
                                                    </div>
                                                    <div class="col-4">
                                                        <span class="text-muted small d-block">Waktu Submit:</span>
                                                        <span class="small font-weight-bold">{{ \Carbon\Carbon::parse($quiz->submitted_at)->format('d/m/Y H:i') }} WIB</span>
                                                    </div>
                                                </div>

                                                <h6 class="font-weight-bold mb-3">Rincian Pertanyaan:</h6>
                                                @foreach($training->questions as $qIndex => $question)
                                                    @php
                                                        $ansData = $quiz->answers[$question->id] ?? null;
                                                        $userAns = $ansData['user_answer'] ?? null;
                                                        $isCorrect = $ansData['is_correct'] ?? false;
                                                    @endphp
                                                    <div class="p-3 mb-3 rounded border {{ $isCorrect ? 'border-success' : 'border-danger' }}" style="background-color: {{ $isCorrect ? '#fafffa' : '#fff8f8' }};">
                                                        <div class="d-flex justify-content-between mb-2">
                                                            <strong>#{{ $qIndex + 1 }}. {{ $question->question }}</strong>
                                                            <span class="badge {{ $isCorrect ? 'badge-success' : 'badge-danger' }} px-2 py-1">
                                                                {{ $isCorrect ? 'Benar' : 'Salah' }}
                                                            </span>
                                                        </div>
                                                        <div class="small">
                                                            <div class="mb-1">Jawaban Peserta: <strong>{{ strtoupper($userAns ?? '-') }}</strong> ({{ $question->{'option_' . $userAns} ?? '-' }})</div>
                                                            <div class="text-success">Kunci Jawaban: <strong>{{ strtoupper($question->correct_answer) }}</strong> ({{ $question->{'option_' . $question->correct_answer} }})</div>
                                                            @if($question->explanation)
                                                                <div class="text-muted mt-2 pt-2 border-top font-italic">Catatan: {{ $question->explanation }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="modal-footer py-2">
                                                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                Belum ada peserta yang terdaftar pada sesi ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
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

    const filterInput = document.getElementById('filterTableInput');
    const table = document.getElementById('participantsTable');
    if (filterInput && table) {
        filterInput.addEventListener('keyup', function() {
            const filter = filterInput.value.toLowerCase();
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const text = rows[i].textContent || rows[i].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        });
    }
});
</script>
@endsection
