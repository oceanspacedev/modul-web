@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="container-fluid bg-white">
                            @if ($message = Session::get('chartError'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                            @endif
                            <br>
                            <div class="row float-right">
                                <div class="card-tools d-flex">
                                    <div class="input-group input-group-sm mr-3" style="widows: 400px;">
                                        <form action="/quiz/history" class="d-inline-flex float-right">
                                            <input type="text" class="form-control float-right" value="" name="dateChart"
                                                id="tanggalChart" placeholder="Pilih Bulan .." required>
                                            <select class="custom-select col-4 mx-1" name="joblevel"
                                            required>
                                                @foreach ($joblevels as $joblevel)
                                                    <option value="{{ $joblevel->id }}">{{ $joblevel->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="input-group-append float-right">
                                                <button type="submit" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <br>
                            <br>
                            <div class=" row">
                                <div class="col-6">
                                    <div class="panel">
                                        <div id="chartHighest">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="panel">
                                        <div id="chartLowest">
                                        </div>
                                    </div>
                                </div> 
                            </div> 
                        </div>              
                        <br>
                        <div class="card">
                            <div class="card-header bg-dark">
                                <div class="row d-inline-flex">
                                    <h3 class="card-title">Quiz History</h3>
                                    {{-- <a href="/question/export">
                                        <button class="badge bg-primary mx-3 elevation-0">EXPORT
                                            ALL</button></a> --}}
                                </div>
                                <div class="card-tools d-flex">
                                    <div class="input-group input-group-sm mr-3" style="max-width: 440px;">
                                        <form action="/quiz/history" class="d-inline-flex">
                                            <select class="custom-select col-lg-12 mx-2" name="filterJobLevel"
                                            required>
                                                @foreach ($joblevels as $joblevel)
                                                    <option value="{{ $joblevel->id }}">{{ $joblevel->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="input-group-append float-right">
                                                <button type="submit" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="input-group input-group-sm" style="width: 300px;">
                                        <form action="/quiz/history" class="d-inline-flex">
                                            <input type="text" name="search" class="form-control"
                                                placeholder="Cari">
                                            <div class="input-group-append float-right">
                                                <button type="submit" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if ($message = Session::get('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
                        @if ($message = Session::get('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
                        <!-- /.card-header -->
                        <div class="card-body table-responsive p-0" style="height: 500px;">
                            <table class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Quiz Modul</th>
                                        <th>User</th>
                                        <th>Value</th>
                                        <th>Start</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($historys as $history)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $history->quiz->document->name }}</td>
                                            <td>{{ $history->user->full_name ?? 'Nonactive user'}}</td>
                                            <td>{{ $history->value }}</td>
                                            <td>{{ date('d M Y H:i', $history->created_at / 1000) }}</td>
                                            <td>
                                                <a href={{ '/quiz/history/' . $history->id }} target='_blank'
                                                    data-toggle="tooltip" title="view" class="badge bg-primary"><span><i
                                                            class="fas fa-eye"></i></span></a>
                                                <a href={{ '/quiz/history/delete/' . $history->id }}
                                                    onclick="return confirm('Apalah anda yakin menghapus quiz {{ $history->quiz->document->name }} atas nama {{ $history->user->full_name ?? 'Nonactive user' }}?')"
                                                     data-toggle="tooltip" title="view"
                                                    class="badge bg-danger"><span><i
                                                            class="fas fa-times-circle"></i></span></a>
                                                <a href={{ '/quiz/history/export/' . $history->id }} target='#'
                                                    data-toggle="tooltip" title="export" class="badge bg-success"><span><i
                                                            class="fas fa-download"></i></span></a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>
            </div>
        </section>
    </section>
    <!-- Main content -->
    {{-- <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <!-- WILL ADD ABSENT TABLE HERE -->
                    <div class="card">
                        <div class="card-header bg-dark">
                            <div class="row d-inline-flex">
                                <h3 class="card-title">Value Rank</h3>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0" style="height: 500px;">
                            <table class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>User</th>
                                        <th>Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($totalValue as $history)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $history->user->full_name }}</td>
                                            <td>{{ $history->totalValue }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    </div>
                    <!-- /.card -->
                </div>
            </div>
        </div>
    </section> --}}
@endsection

@section('footer')
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
    Highcharts.chart('chartHighest', {
    chart: {
        type: 'column'
    },
    title: {
        text: 'Highest Total Score'
    },
    subtitle: {
        text: ''
    },
    xAxis: {
        categories: [
            '',
        ],
        crosshair: true
    },
    yAxis: {
        min: 0,
        title: {
            text: 'Total Score'
        }
    },
    tooltip: {
        headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
        pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
            '<td style="padding:0"><b>{point.y:.1f} </b></td></tr>',
        footerFormat: '</table>',
        shared: true,
        useHTML: true
    },
    plotOptions: {
        column: {
            pointPadding: 0.2,
            borderWidth: 0,
            dataLabels: {
                enabled: true
            }
        }
        
    },
    series: [{
        name: {!! json_encode($highestName[0]) !!},
        data: [{!! json_encode($highestScore[0]) !!}],

    },
    {
        name: {!! json_encode($highestName[1]) !!},
        data: [{!! json_encode($highestScore[1]) !!}],
    },
    {
        name: {!! json_encode($highestName[2]) !!},
        data: [{!! json_encode($highestScore[2]) !!}],
    },
]
});

Highcharts.chart('chartLowest', {
    chart: {
        type: 'column'
    },
    title: {
        text: 'Lowest Total Score'
    },
    subtitle: {
        text: ''
    },
    xAxis: {
        categories: [
            '',
        ],
        crosshair: true
    },
    yAxis: {
        min: 0,
        title: {
            text: 'Total Score'
        }
    },
    tooltip: {
        headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
        pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
            '<td style="padding:0"><b>{point.y:.1f} </b></td></tr>',
        footerFormat: '</table>',
        shared: true,
        useHTML: true
    },
    plotOptions: {
        column: {
            pointPadding: 0.2,
            borderWidth: 0,
            dataLabels: {
                enabled: true
            }
        }
    },
    series: [{
        name: {!! json_encode($lowestName[0]) !!},
        data: [{!! json_encode($lowestScore[0]) !!}],

    },
    {
        name: {!! json_encode($lowestName[1]) !!},
        data: [{!! json_encode($lowestScore[1]) !!}],
    },
    {
        name: {!! json_encode($lowestName[2]) !!},
        data: [{!! json_encode($lowestScore[2]) !!}],
    },
]
});
</script>
@endsection
