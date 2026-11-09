// Semua dropdown (<select>) bisa diketik untuk mencari (Select2), termasuk
// yang ditambahkan lewat JS belakangan. Untuk tetap memakai dropdown bawaan
// browser, beri <select> (atau pembungkusnya) atribut data-native-select.
// Di dalam modal Bootstrap, daftar pilihannya ditempel ke modal itu supaya
// kolom pencariannya tetap bisa diketik (focus trap modal).
var SEARCHABLE_SELECT = "select";
var NATIVE_SELECT_AREA = "[data-native-select], .phpdebugbar";

window.initSearchableSelects = function (scope) {
    if (!$.fn.select2) {
        return;
    }
    var selects = $(scope || document)
        .find(SEARCHABLE_SELECT)
        .addBack(SEARCHABLE_SELECT)
        .filter(function () {
            // Yang sudah dipasang (mis. oleh skrip halaman) tidak diulang.
            return !$(this).hasClass("select2-hidden-accessible") && !$(this).closest(NATIVE_SELECT_AREA).length;
        })
        .get();
    var batch = 50;

    function init(select) {
        var $select = $(select);
        if ($select.hasClass("select2-hidden-accessible") || !document.body.contains(select)) {
            return;
        }
        var $modal = $select.closest(".modal");
        var options = {
            width: select.style.width || "100%",
            dropdownParent: $modal.length ? $modal : $(document.body),
        };
        // Pilihan ganda mencari lewat kotaknya sendiri; tanpa placeholder kotak
        // itu terlihat seperti isian kosong. (Atur sendiri lewat data-placeholder.)
        if (select.multiple && !$select.data("placeholder")) {
            options.placeholder = "Ketik untuk mencari...";
        }
        $select.select2(options);
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

// Select2 mengabarkan perubahan pilihan lewat jQuery .trigger("change"), yang
// tidak sampai ke listener addEventListener("change") biasa. Untuk dropdown
// Select2, kirim event change native: listener jQuery, native, dan atribut
// onchange semuanya tetap jalan, masing-masing sekali.
$.event.special.change = $.extend({}, $.event.special.change, {
    trigger: function (event) {
        if (
            this.nodeName === "SELECT" &&
            $(this).hasClass("select2-hidden-accessible") &&
            !event.namespace &&
            arguments.length === 1
        ) {
            this.dispatchEvent(new Event("change", { bubbles: true }));
            return false;
        }
    },
});

$(document).ready(function () {
    initSearchableSelects();

    // Dropdown yang muncul belakangan (baris tabel, form dari JS) ikut dipasang.
    if (window.MutationObserver) {
        new MutationObserver(function (mutations) {
            var added = [];
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1 && (node.nodeName === "SELECT" || node.querySelector("select"))) {
                        added.push(node);
                    }
                });
            });
            if (added.length) {
                initSearchableSelects(added);
            }
        }).observe(document.body, { childList: true, subtree: true });
    }
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


// Badge notifikasi (lonceng topbar & menu sidebar) diperbarui tiap menit, supaya hasil import/export yang
// selesai di belakang layar langsung terlihat tanpa memuat ulang halaman.
$(function () {
    var $badges = $("[data-notification-count]");
    if (!$badges.length) {
        return;
    }

    function refresh() {
        if (document.hidden) {
            return;
        }
        $.getJSON("/notifikasi/unread-count").done(function (data) {
            var count = data.count || 0;
            $badges.text(count > 99 ? "99+" : count).prop("hidden", count === 0);
        });
    }

    setInterval(refresh, 60000);
    document.addEventListener("visibilitychange", refresh);
});
