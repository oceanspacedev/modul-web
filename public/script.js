$(document).ready(function () {
    $(".select2").select2();
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
    
    // Fix Bootstrap table-responsive clipping dropdowns dynamically
    $(document).on('show.bs.dropdown', '.table-responsive', function () {
        $(this).css('overflow', 'inherit');
    });
    $(document).on('hide.bs.dropdown', '.table-responsive', function () {
        $(this).css('overflow', '');
    });
});

