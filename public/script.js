$(document).ready(function () {
    $(".select2").select2();
    $subdiv = $(".addusersubdivisi");
    $subdiv.append('<option value="">---Choose Divisi First--</option>');
    $(".adduserdivisi").change(function (e) {
        var $divisiId = $(".adduserdivisi").val();
        if ($divisiId === "") {
            $subdiv.empty();
            $subdiv.append(
                '<option value="">---Choose Divisi First--</option>'
            );
        } else {
            $subdiv.empty();
            $.ajax({
                type: "GET",
                url: "http://modul.sytes.net:8888/subdivisi/get/" + $divisiId,
                success: function (data) {
                    if (data.length > 0) {
                        $.each(data, function (index, value) {
                            $subdiv.append(
                                '<option value="' +
                                    value.id +
                                    '">' +
                                    value.name +
                                    "</option>"
                            );
                        });
                    } else {
                        $subdiv.append('<option value="">-</option>');
                    }
                },
            });
        }
    });

    $(".edituserdivisi").change(function (e) {
        var $divisiId = $(".edituserdivisi").val();
        $subdivedit = $(".editusersubdivisi");
        if ($divisiId === "") {
            $subdivedit.empty();
            $subdivedit.append(
                '<option value="">---Choose Divisi First--</option>'
            );
        } else {
            $subdivedit.empty();
            $.ajax({
                type: "GET",
                url: "http://modul.sytes.net:8888/subdivisi/get/" + $divisiId,
                success: function (data) {
                    if (data.length > 0) {
                        $.each(data, function (index, value) {
                            $subdivedit.append(
                                '<option value="' +
                                    value.id +
                                    '">' +
                                    value.name +
                                    "</option>"
                            );
                        });
                    } else {
                        $subdivedit.append('<option value="">-</option>');
                    }
                },
            });
        }
    });

    $(".viewoption").click(function (e) {
        var $option = $("#options");
        $option.empty();
        var $id = $(this).data("id");
        $.ajax({
            type: "GET",
            url: "http://modul.sytes.net:8888/option/get?id=" + $id,
            success: function (data) {
                $("#modalOptionLabel").empty();
                $("#modalOptionLabel").append(data[0].question.question);
                $.each(data, function (index, value) {
                    $option.append(
                        '<div id="option' +
                            index +
                            '" class="col-12 d-flex justify-content-between"><p>' +
                            value.content +
                            "</p></div>"
                    );
                    if (value.is_true) {
                        $("#option" + index).append(
                            '<div><button class="btn far fa-check-circle" style="color: green;"></button></div>'
                        );
                    }
                });
            },
        });
        $("#modalOption").modal("show");
    });

    $("#modalClose").click(function (e) {
        $("#modalOption").modal("hide");
    });

    $("#tanggal").daterangepicker();

    $("#tanggalExport").daterangepicker({
        parentEl: "#exportAbsent .modal-body",
    });

    $("#bulanChart").datepicker({
        format: "yyyy-mm",
        startView: "months",
        minViewMode: "months",
    });

    $("#tanggalChart").daterangepicker();
});
