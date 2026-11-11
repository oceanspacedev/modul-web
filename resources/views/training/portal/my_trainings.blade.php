@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Pelatihan Saya" subtitle="Sesi pelatihan yang Anda ikuti beserta kehadiran dan nilai kuis." eyebrow="Pelatihan / Pelatihan Saya" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />
        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">No</th>
                            <th>Topik Pelatihan</th>
                            <th>Pemateri</th>
                            <th>Jadwal Pelaksanaan</th>
                            <th class="text-center">Kehadiran</th>
                            <th class="text-center">Nilai Kuis</th>
                            <th>Langkah Anda</th>
                            <th class="text-right" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($participations as $index => $part)
                        @php
                            $t = $part->training;
                            $quiz = $part->quizResult;
                            $pretest = $part->pretestResult;
                            [$todo, $todoClass, $needsAction] = match (true) {
                                $t->status === App\Enums\TrainingStatus::Cancelled => ['Dibatalkan', 'text-muted', false],
                                $quiz !== null => ['Selesai', 'text-success', false],
                                $t->isPhaseActive('pretest') && $pretest === null => ['Kerjakan pretest', 'text-primary font-weight-bold', true],
                                $t->is_attendance_active && $part->attendance_status === 'pending' => ['Isi presensi', 'text-primary font-weight-bold', true],
                                $t->isPhaseActive('posttest') => [$t->has_pretest ? 'Kerjakan posttest' : 'Kerjakan kuis', 'text-primary font-weight-bold', true],
                                $t->status === App\Enums\TrainingStatus::Completed => ['Selesai', 'text-muted', false],
                                default => ['Menunggu pemateri', 'text-muted', false],
                            };
                        @endphp
                        <tr>
                            <td class="text-center text-muted">
                                {{ $participations->firstItem() + $index }}
                            </td>
                            <td>
                                <strong class="d-block">{{ $t->title }}</strong>
                                @if($t->description)
                                    <span class="text-muted small d-block text-truncate" style="max-width: 280px;">
                                        {{ $t->description }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-muted">
                                {{ $t->trainer->nama_lengkap ?? '-' }}
                            </td>
                            <td>
                                <span class="d-block">{{ \Carbon\Carbon::parse($t->training_date)->format('d M Y') }}</span>
                                <span class="text-muted small">{{ substr($t->start_time, 0, 5) }} - {{ substr($t->end_time, 0, 5) }} WIB</span>
                            </td>
                            <td class="text-center">
                                @if($part->attendance_status === 'hadir')
                                    <span class="badge badge-success">Hadir</span>
                                @elseif($part->attendance_status === 'tidak_hadir')
                                    <span class="badge badge-danger">Tidak Hadir</span>
                                @else
                                    <span class="badge badge-warning">Belum Konfirmasi</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($quiz)
                                    <strong>{{ $quiz->score }}</strong>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                                @if($pretest)
                                    <span class="cell-sub d-block">Pretest {{ $pretest->score }}</span>
                                @endif
                            </td>
                            <td class="small {{ $todoClass }}">{{ $todo }}</td>
                            <td class="text-right">
                                <a href="/training/portal/{{ $part->token }}" target="_blank" rel="noopener" class="btn {{ $needsAction ? 'btn-primary' : 'btn-default' }} btn-xs" title="Dibuka di tab baru">
                                    Buka Sesi <i class="fas fa-external-link-alt"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="fa-chalkboard-teacher" title="Belum ada pelatihan" text="Anda belum terdaftar di pelatihan mana pun." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($participations->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $participations->links() }}
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
