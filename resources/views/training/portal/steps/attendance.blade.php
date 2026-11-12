{{--
    Kartu langkah Presensi Kehadiran di portal peserta. Urutan dan nomor langkahnya diatur pemateri (Training::portalSteps()); variabel
    lain berasal dari training/portal/index.blade.php.
--}}
<!-- KONFIRMASI KEHADIRAN (tidak tampil bila pelatihan tanpa presensi) -->
@if($training->has_attendance)
<div class="content-card" id="attendanceCard">
    <div class="content-card-header">
        <span><span class="badge badge-primary mr-1">Langkah {{ $stepNumber['attendance'] }}</span> Presensi Kehadiran</span>
        <div class="d-flex align-items-center gap-2">
            @if($participant->attendance_status === 'hadir')
                <span class="badge badge-success" id="attendanceBadge">Hadir</span>
            @elseif($participant->attendance_status === 'tidak_hadir')
                <span class="badge badge-danger" id="attendanceBadge">Tidak Hadir</span>
            @else
                <span class="badge badge-warning" id="attendanceBadge">Belum Konfirmasi</span>
            @endif
            <span id="liveIndicator" class="d-flex align-items-center gap-1" title="Halaman ini memperbarui status secara otomatis">
                <span class="live-dot"></span>
                <span class="text-muted text-xs">Live</span>
            </span>
        </div>
    </div>

    @if($participant->attendance_status === 'hadir')
        <div class="alert alert-success mb-0" id="attendanceBody">
            <div class="d-flex align-items-center mb-1">
                <i class="fas fa-check-circle fa-lg mr-2"></i>
                <strong>Kehadiran Anda Telah Terverifikasi</strong>
            </div>
            <p class="small mb-0">
                Dicatat pada {{ \Carbon\Carbon::parse($participant->attended_at)->format('d F Y, H:i') }} WIB.
            </p>

            @if($participant->attendance_proof)
                <div class="mt-3 p-3 bg-white rounded border">
                    <span class="small font-weight-bold text-dark d-block mb-2">
                        <i class="fas fa-camera mr-1 text-primary"></i> {{ $proofText['shown'] }}:
                    </span>
                    <div class="text-center">
                        <a href="{{ $participant->attendance_proof_url }}" target="_blank" title="Klik untuk perbesar">
                            <img src="{{ $participant->attendance_proof_url }}" alt="Bukti Kehadiran" class="img-fluid rounded border shadow-sm" style="max-height: 220px; object-fit: contain;">
                        </a>
                        <small class="text-muted d-block mt-1 font-italic">
                            <i class="fas fa-external-link-alt mr-1"></i> Klik gambar untuk melihat ukuran penuh
                        </small>
                    </div>
                </div>
            @endif
        </div>
    @elseif($participant->attendance_status === 'tidak_hadir')
        <div class="alert alert-danger mb-0" id="attendanceBody">
            <div class="d-flex align-items-center mb-1">
                <i class="fas fa-times-circle fa-lg mr-2"></i>
                <strong>Konfirmasi Tidak Hadir Telah Dicatat</strong>
            </div>
            @if($participant->attendance_notes)
                <div class="small mt-1">Keterangan: {{ $participant->attendance_notes }}</div>
            @endif
        </div>
    @else
        {{-- BELUM ABSEN (STATUS: PENDING) --}}
        {{-- Batas waktu presensi: selalu ada (tersembunyi bila tertutup atau tanpa batas waktu), seperti pretest/posttest. --}}
        @include('training.partials.portal-deadline', ['phase' => 'attendance'])

        @if(! $training->isAttendanceOpen())
            <div class="text-center py-4" id="attendanceBody" data-state="locked">
                <span class="empty-state-icon"><i class="fas fa-user-clock"></i></span>
                <h6 class="mb-1" data-attendance-locked-title>{{ $training->phaseTimedOut('attendance') ? $attendanceLockedText['closedTitle'] : $attendanceLockedText['title'] }}</h6>
                <p class="text-muted small mb-0" style="max-width: 480px; margin: 0 auto;" data-locked-text="attendance"
                    data-default="{{ $attendanceLockedText['default'] }}" data-scheduled="{{ $attendanceLockedText['scheduled'] }}" data-timed-out="{{ $attendanceLockedText['timedOut'] }}">
                    @if($attendanceOpensAt)
                        {{ $attendanceLockedText['scheduled'] }}{{ \App\Models\Training::timeLabel($attendanceOpensAt) }} WIB.
                    @elseif($training->phaseTimedOut('attendance'))
                        {{ $attendanceLockedText['timedOut'] }}
                    @else
                        {{ $attendanceLockedText['default'] }}
                    @endif
                </p>
            </div>
        @else
            <div id="attendanceBody" data-state="open">
            <p class="text-muted small mb-3">
                Sesi presensi telah dibuka! Silakan lakukan konfirmasi kehadiran Anda untuk pelatihan ini:
            </p>

            @if($training->require_attendance_proof)
                <div class="alert alert-info py-2 px-3 small mb-3" id="proofInfoBox">
                    <i class="fas fa-info-circle mr-1"></i> <strong>{{ $proofText['requiredTitle'] }}:</strong> {{ $proofText['required'] }}
                </div>
            @else
                <div class="d-none" id="proofInfoBox"></div>
            @endif

            <form action="/training/portal/{{ $participant->token }}/attendance" method="POST" enctype="multipart/form-data" id="attendanceForm">
                @csrf
                <input type="hidden" name="status" value="hadir">

                <div id="proofFieldWrapper" class="{{ $training->require_attendance_proof ? '' : 'd-none' }}">
                    <div class="form-group mb-3">
                        <label class="small font-weight-bold text-dark mb-1" for="attendanceProofInput">
                            {{ $proofText['upload'] }} <span class="text-danger">*</span>
                        </label>
                        <div class="custom-file">
                            <input type="file" name="attendance_proof" id="attendanceProofInput" class="custom-file-input" accept="image/png, image/jpeg, image/jpg, image/webp" {{ $training->require_attendance_proof ? 'required' : '' }} onchange="previewAttendanceProof(this)">
                            <label class="custom-file-label text-muted text-truncate" for="attendanceProofInput" id="attendanceProofLabel">
                                {{ $proofText['pick'] }}
                            </label>
                        </div>
                        <small class="form-text text-muted">Format: JPG, PNG, atau WEBP. Maksimal 5MB. {{ $proofText['hint'] }}</small>

                        <div id="proofPreviewWrapper" class="mt-2 text-center d-none">
                            <img id="proofPreviewImg" src="#" alt="Preview Screenshot" class="img-fluid rounded border shadow-sm" style="max-height: 180px; object-fit: contain;">
                            <div class="mt-1">
                                <button type="button" class="btn btn-link btn-xs text-danger" onclick="clearProofInput()">
                                    <i class="fas fa-trash-alt mr-1"></i> {{ $proofText['replace'] }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-check-circle mr-1"></i> Saya Hadir
                    </button>

                    <button type="button" class="btn btn-default px-4" data-toggle="collapse" data-target="#notAttendBox">
                        Tidak Hadir
                    </button>
                </div>
            </form>

            <div class="collapse mt-3" id="notAttendBox">
                <form action="/training/portal/{{ $participant->token }}/attendance" method="POST" class="p-3 border rounded bg-light">
                    @csrf
                    <input type="hidden" name="status" value="tidak_hadir">
                    <div class="form-group mb-3">
                        <label class="small font-weight-bold mb-1">Alasan Tidak Hadir:</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Tuliskan keterangan..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm px-3">
                        Simpan Keterangan
                    </button>
                </form>
            </div>
            </div>
        @endif
    @endif
</div>
@endif
