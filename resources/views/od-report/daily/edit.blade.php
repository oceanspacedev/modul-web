@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Ubah Aktivitas" :subtitle="$activity->user->nama_lengkap.' · '.$activity->date->format('d/m/Y')"
            :breadcrumbs="['Daily Report' => route('od-report.daily.index'), 'Ubah' => null]" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="row">
            <div class="col-lg-8">
                <form action="{{ route('od-report.daily.update', $activity) }}" method="POST" class="card">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="back" value="{{ request('back') }}">
                    <div class="card-body pb-0">
                        @include('od-report.daily._fields')
                    </div>
                    <div class="card-footer d-flex flex-wrap justify-content-between gap-2">
                        <a href="{{ request('back') === 'weekly' ? route('od-report.weekly') : route('od-report.daily.index') }}" class="btn btn-default">Kembali</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
