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


// Sidebar ciut (layar >= 992px): sidebar tidak melebar saat di-hover (lihat app.css). Nama menu yang di-hover
// atau difokus tampil di samping ikonnya, lengkap dengan submenunya kalau ada, supaya submenu tetap bisa dibuka.
// Pilihan ciut/lebar dari tombol menu juga diingat untuk halaman berikutnya (dipasang lagi di header.blade.php).
$(function () {
    var $sidebar = $(".main-sidebar");
    if (!$sidebar.length) {
        return;
    }

    var wide = window.matchMedia("(min-width: 992px)");
    var $flyout = $('<div class="sidebar-flyout" role="tooltip" hidden></div>').appendTo("body");
    var hideTimer = null;
    var current = null;

    function isCollapsed() {
        return wide.matches && $("body").hasClass("sidebar-collapse");
    }

    function hide(delay) {
        clearTimeout(hideTimer);
        var run = function () {
            $flyout.prop("hidden", true);
            current = null;
        };
        if (delay) {
            hideTimer = setTimeout(run, delay);
        } else {
            run();
        }
    }

    function show(link) {
        clearTimeout(hideTimer);
        if (!isCollapsed()) {
            return hide();
        }
        if (current === link && !$flyout.prop("hidden")) {
            return;
        }
        current = link;

        // Nama menu tanpa panah submenu dan badge di dalam <p>.
        var $label = $(link).children("p").clone();
        $label.find(".right").remove();
        $flyout.empty().append($('<div class="sidebar-flyout-title"></div>').text($.trim($label.text())));

        var $children = $(link).parent().children(".nav-treeview").find("> .nav-item > .nav-link");
        $flyout.toggleClass("has-menu", $children.length > 0);
        if ($children.length) {
            var $menu = $('<div class="sidebar-flyout-menu"></div>').appendTo($flyout);
            $children.each(function () {
                $('<a class="sidebar-flyout-link"></a>')
                    .attr("href", this.getAttribute("href"))
                    .toggleClass("active", $(this).hasClass("active"))
                    .text($.trim($(this).text()))
                    .appendTo($menu);
            });
        }

        // Baris judul sejajar dengan baris menu yang di-hover, tetap di dalam layar.
        var rect = link.getBoundingClientRect();
        $flyout.prop("hidden", false).css({ left: $sidebar[0].getBoundingClientRect().right + 8, top: 0 });
        var top = Math.min(rect.top, window.innerHeight - $flyout.outerHeight() - 8);
        $flyout.css("top", Math.max(8, top));
    }

    $sidebar
        .on("mouseenter focusin", ".nav-sidebar > .nav-item > .nav-link", function () {
            show(this);
        })
        .on("mouseleave focusout", ".nav-sidebar > .nav-item > .nav-link", function () {
            hide(150);
        });

    // Jeda 150ms di atas memberi waktu kursor pindah dari ikon ke submenu di flyout.
    $flyout
        .on("mouseenter focusin", function () {
            clearTimeout(hideTimer);
        })
        .on("mouseleave focusout", function () {
            hide(150);
        });

    $sidebar[0].addEventListener("scroll", function () {
        hide();
    }, true);
    $(window).on("resize", function () {
        hide();
    });

    $(document).on("collapsed.lte.pushmenu shown.lte.pushmenu", function (e) {
        hide();
        // Di layar kecil tombol menu membuka sidebar sebagai panel, dan AdminLTE menciutkannya sendiri saat jendela
        // diperkecil; keduanya bukan pilihan yang perlu diingat.
        if (!wide.matches) {
            return;
        }
        try {
            localStorage.setItem("sidebar-collapsed", e.type === "collapsed" ? "1" : "0");
        } catch (err) {}
    });
});
