<div class="modal fade text-left" id="participantDetails_{{ $part->id }}" tabindex="-1" role="dialog"
    aria-labelledby="participantDetailsTitle_{{ $part->id }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="participantDetailsTitle_{{ $part->id }}">{{ $userName }}</h5>
                    <p class="participant-detail-identity">{{ $userIdKaryawan }} &middot; {{ $userDivisi }}</p>
                    @if(filled($userEmail) && $userEmail !== '-')
                        <p class="participant-detail-identity text-break">{{ $userEmail }}</p>
                    @endif
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body participant-detail">
                @if($training->has_attendance)
                    @php
                        [$attendanceLabel, $attendanceTone] = match ($part->attendance_status) {
                            'hadir' => ['Hadir', 'success'],
                            'tidak_hadir' => ['Tidak hadir', 'danger'],
                            default => ['Belum absen', 'muted'],
                        };
                    @endphp
                    <section class="participant-detail-row">
                        <div class="participant-detail-main">
                            <h6 class="participant-detail-label">Presensi</h6>
                            @if($part->attended_at)
                                <p class="participant-detail-meta">{{ $part->attended_at->translatedFormat('d M Y, H:i') }} WIB</p>
                            @endif
                            @if($part->attendance_notes)
                                <p class="participant-detail-meta text-break">{{ $part->attendance_notes }}</p>
                            @endif
                            @if($part->attendance_proof)
                                <div class="participant-detail-links">
                                    <a href="{{ $part->attendance_proof_url }}" target="_blank" rel="noopener"><i class="fas fa-image"></i> Lihat bukti</a>
                                    <a href="{{ $part->attendance_proof_url }}" target="_blank" rel="noopener" download><i class="fas fa-download"></i> Unduh</a>
                                </div>
                            @endif
                        </div>
                        <span class="participant-detail-pill is-{{ $attendanceTone }}">{{ $attendanceLabel }}</span>
                    </section>
                @endif

                @foreach($quizResults as $phaseLabel => $result)
                    <section class="participant-detail-row">
                        <div class="participant-detail-main">
                            <h6 class="participant-detail-label">{{ $phaseLabel }}</h6>
                            @if($result)
                                <p class="participant-detail-meta">
                                    {{ $result->correct_answers }} PG benar
                                    @if($result->essay_status === 'graded')
                                        &middot; Nilai esai {{ $result->essay_score }}
                                    @endif
                                    @if(($result->attempt_count ?? 1) > 1)
                                        &middot; tertinggi dari {{ $result->attempt_count }}x
                                    @endif
                                </p>
                                @if($result->submitted_at)
                                    <p class="participant-detail-meta">{{ $result->submitted_at->translatedFormat('d M Y, H:i') }} WIB</p>
                                @endif
                                @if($result->essay_status === 'pending')
                                    <p class="participant-detail-meta">Nilai saat ini baru dari pilihan ganda.</p>
                                @endif
                                @if($result->is_force_submitted || $result->essay_status === 'pending' || ($result->tab_switch_count ?? 0) > 0)
                                    <div class="participant-detail-flags">
                                        @if($result->essay_status === 'pending')
                                            <span class="participant-detail-pill is-warning">Esai perlu dinilai</span>
                                        @endif
                                        @if($result->is_force_submitted)
                                            <span class="participant-detail-pill is-danger">Curang (Auto-Submit)</span>
                                        @endif
                                        @if(($result->tab_switch_count ?? 0) > 0)
                                            <span class="participant-detail-pill is-warning">Pindah tab {{ $result->tab_switch_count }}x</span>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </div>
                        @if($result)
                            <span class="participant-detail-value">{{ $result->score }}</span>
                        @else
                            <span class="participant-detail-pill is-muted">Belum mengerjakan</span>
                        @endif
                    </section>
                @endforeach

                @if($training->has_evaluation || $part->evaluation)
                    <section class="participant-detail-row">
                        <div class="participant-detail-main">
                            <h6 class="participant-detail-label">Evaluasi</h6>
                            @if($part->evaluation?->submitted_at)
                                <p class="participant-detail-meta">{{ $part->evaluation->submitted_at->translatedFormat('d M Y, H:i') }} WIB</p>
                            @endif
                        </div>
                        @if($evaluationScore)
                            <span class="participant-detail-value">{{ $evaluationScore }}</span>
                        @elseif($part->evaluation)
                            <span class="participant-detail-pill is-success">Terisi</span>
                        @else
                            <span class="participant-detail-pill is-muted">Belum mengisi</span>
                        @endif
                    </section>
                @endif
            </div>
        </div>
    </div>
</div>
