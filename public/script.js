// Dropdown yang bisa diketik untuk mencari: beri <select> class "select2".
// Di dalam modal Bootstrap, daftar pilihannya ditempel ke modal itu supaya
// kolom pencariannya tetap bisa diketik (focus trap modal).
window.initSearchableSelects = function (scope) {
    if (!$.fn.select2) {
        return;
    }
    var selects = $(scope || document).find("select.select2").get();
    var batch = 50;

    function init(select) {
        var $select = $(select);
        var $modal = $select.closest(".modal");
        $select.select2({
            dropdownParent: $modal.length ? $modal : $(document.body),
        });
    }

    // Batch pertama langsung, sisanya bertahap supaya halaman dengan
    // ratusan dropdown (mis. satu per baris tabel) tidak macet saat dibuka.
    (function next(start) {
        selects.slice(start, start + batch).forEach(init);
        if (start + batch < selects.length) {
            setTimeout(function () {
                next(start + batch);
            }, 0);
        }
    })(0);
};

// Select2 4.0.13 + jQuery 3.6 tidak memindahkan kursor ke kolom pencarian
// saat dropdown dibuka, jadi user harus klik dulu sebelum bisa mengetik.
$(document).on("select2:open", function (e) {
    var select2 = $(e.target).data("select2");
    var field = select2 && select2.dropdown && select2.dropdown.$search;
    if (field && field.length) {
        setTimeout(function () {
            field[0].focus();
        }, 0);
    }
});

$(document).ready(function () {
    initSearchableSelects();
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

