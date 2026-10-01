$(document).ready(function () {
    $(".select2").select2();
    $subdiv = $(".addusersubdivisi");
    if ($subdiv.find("option").length === 0) {
        $subdiv.append('<option value="">---Choose Divisi First--</option>');
    }
    $(".adduserdivisi").change(function (e) {
        var $divisiId = $(this).val();
        var $currentSubdiv = $(this).closest("form").find(".addusersubdivisi").length
            ? $(this).closest("form").find(".addusersubdivisi")
            : $(".addusersubdivisi");

        if (!$divisiId) {
            $currentSubdiv.empty();
            $currentSubdiv.append(
                '<option value="">---Choose Divisi First--</option>'
            );
        } else {
            $currentSubdiv.empty();
            $currentSubdiv.append('<option value="">Loading...</option>');
            $.ajax({
                type: "GET",
                url: "/subdivisi/get/" + $divisiId,
                success: function (data) {
                    $currentSubdiv.empty();
                    if (data && data.length > 0) {
                        $currentSubdiv.append('<option value="">--Choose Sub Divisi--</option>');
                        $.each(data, function (index, value) {
                            $currentSubdiv.append(
                                '<option value="' +
                                    value.id +
                                    '">' +
                                    value.name +
                                    "</option>"
                            );
                        });
                    } else {
                        $currentSubdiv.append('<option value="">- Tidak ada sub divisi -</option>');
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Gagal memuat sub divisi:", error);
                    $currentSubdiv.empty();
                    $currentSubdiv.append('<option value="">- Gagal memuat sub divisi -</option>');
                }
            });
        }
    });

    $(".edituserdivisi").change(function (e) {
        var $divisiId = $(this).val();
        var $subdivedit = $(this).closest("form").find(".editusersubdivisi").length
            ? $(this).closest("form").find(".editusersubdivisi")
            : $(".editusersubdivisi");

        if (!$divisiId) {
            $subdivedit.empty();
            $subdivedit.append(
                '<option value="">---Choose Divisi First--</option>'
            );
        } else {
            $subdivedit.empty();
            $subdivedit.append('<option value="">Loading...</option>');
            $.ajax({
                type: "GET",
                url: "/subdivisi/get/" + $divisiId,
                success: function (data) {
                    $subdivedit.empty();
                    if (data && data.length > 0) {
                        $subdivedit.append('<option value="">--Choose Sub Divisi--</option>');
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
                        $subdivedit.append('<option value="">- Tidak ada sub divisi -</option>');
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Gagal memuat sub divisi:", error);
                    $subdivedit.empty();
                    $subdivedit.append('<option value="">- Gagal memuat sub divisi -</option>');
                }
            });
        }
    });

    $(".viewoption").click(function (e) {
        var $option = $("#options");
        $option.empty();
        var $id = $(this).data("id");
        $.ajax({
            type: "GET",
            url: "/option/get?id=" + $id,
            success: function (data) {
                $("#modalOptionLabel").empty();
                if (data && data.length > 0) {
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
                }
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
