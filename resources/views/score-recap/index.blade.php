@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Rekap Nilai" subtitle="Export nilai pelatihan dan pretest/posttest dari banyak kegiatan sekaligus dalam satu file Excel." />

            <x-flash />

            <div class="row">
                <div class="col-lg-7">
                    <form action="{{ route('score-recap.export') }}" method="POST" class="card">
                        @csrf
                        <div class="card-header">
                            <h3 class="card-title">Filter</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group col-sm-6">
                                    <label for="from">Dari tanggal <span class="text-danger">*</span></label>
                                    <input type="date" id="from" name="from" class="form-control"
                                        value="{{ old('from', now()->startOfMonth()->toDateString()) }}" required>
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="to">Sampai tanggal <span class="text-danger">*</span></label>
                                    <input type="date" id="to" name="to" class="form-control"
                                        value="{{ old('to', now()->toDateString()) }}" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="divisi_id">Divisi</label>
                                <select id="divisi_id" name="divisi_id" class="form-control">
                                    <option value="">Semua divisi</option>
                                    @foreach ($divisis as $divisi)
                                        <option value="{{ $divisi->id }}" @selected(old('divisi_id') == $divisi->id)>{{ $divisi->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group mb-0">
                                <label>Jenis nilai <span class="text-danger">*</span></label>
                                @foreach ($sections as $key => $label)
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="section_{{ $key }}" name="sections[]" value="{{ $key }}"
                                            @checked(in_array($key, old('sections', array_keys($sections)), true))>
                                        <label class="custom-control-label" for="section_{{ $key }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                        </div>
                    </form>
                </div>

                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Isi file</h3>
                        </div>
                        <div class="card-body">
                            <ul class="pl-3 mb-3">
                                <li><b>Sheet Pelatihan</b>: satu baris per peserta per pelatihan yang tanggalnya masuk rentang. Kolomnya mengikuti pengaturan pelatihannya: hanya langkah yang dipakai (presensi, pretest, form evaluasi, posttest) yang muncul, dalam Urutan Langkah Peserta.</li>
                                <li class="mt-2"><b>Sheet Modul &amp; Kuis Materi</b>: satu baris per peserta per modul/kuis video/kuis dokumen yang terakhir dikerjakan di dalam rentang. Berisi nilai pretest, posttest terbaik dan terakhir, kenaikan nilai, dan status lulus.</li>
                            </ul>
                            <p class="text-muted mb-0">File dibuat di belakang layar. Begitu selesai, file bisa diunduh dari menu <a href="{{ route('notifications.index') }}">Notifikasi</a>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
