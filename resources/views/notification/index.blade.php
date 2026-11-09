@extends('layout.main_template')

@php
    $statusStyles = [
        'success' => ['fa-check-circle', 'notification-icon-success', 'Berhasil'],
        'partial' => ['fa-exclamation-triangle', 'notification-icon-warning', 'Sebagian gagal'],
        'failed' => ['fa-times-circle', 'notification-icon-danger', 'Gagal'],
    ];
@endphp

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Notifikasi" subtitle="Hasil import, export, dan proses lain yang berjalan di belakang layar." />

            <x-flash />

            <div class="card">
                <div class="card-body p-0">
                    @forelse ($notifications as $notification)
                        @php
                            $data = $notification->data;
                            [$icon, $iconClass, $statusLabel] = $statusStyles[$data['status'] ?? 'success'] ?? $statusStyles['success'];
                            $details = $data['errors'] ?? [];
                        @endphp
                        <div class="notification-row {{ $notification->read_at ? '' : 'is-unread' }}">
                            <span class="notification-icon {{ $iconClass }}" title="{{ $statusLabel }}"><i class="fas {{ $icon }}"></i></span>
                            <div class="notification-body">
                                <div class="notification-title">
                                    {{ $data['title'] ?? 'Notifikasi' }}
                                    @unless ($notification->read_at)
                                        <span class="badge badge-primary ml-1">Baru</span>
                                    @endunless
                                </div>
                                <p class="notification-message">{{ $data['message'] ?? '' }}</p>
                                <div class="notification-meta" title="{{ $notification->created_at->isoFormat('D MMMM Y, HH:mm') }}">
                                    {{ $notification->created_at->diffForHumans() }}
                                </div>

                                @if ($details !== [])
                                    <details class="notification-details">
                                        <summary>Lihat rincian ({{ count($details) }} baris)</summary>
                                        <ul>
                                            @foreach ($details as $line)
                                                <li>{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </div>
                            <div class="notification-actions">
                                @if (! empty($data['download_path']))
                                    <a href="{{ route('notifications.download', $notification->id) }}" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download"></i> Unduh
                                    </a>
                                @endif
                                <form action="{{ route('notifications.destroy', $notification->id) }}" method="POST" class="m-0" onsubmit="return confirm('Hapus notifikasi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-default btn-sm text-danger" aria-label="Hapus notifikasi">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <x-empty-state icon="fa-bell" title="Belum ada notifikasi" text="Hasil import dan export yang diproses di belakang layar akan muncul di sini." />
                    @endforelse
                </div>
            </div>

            {{ $notifications->links('pagination::bootstrap-4') }}
        </div>
    </section>
@endsection
