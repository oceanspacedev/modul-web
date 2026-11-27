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

    if ($.fn.daterangepicker) {
        $("#tanggal").daterangepicker();

        $("#tanggalExport").daterangepicker({
            parentEl: "#exportAbsent .modal-body",
        });

        $("#tanggalChart").daterangepicker();
    }

    if ($.fn.datepicker) {
        $("#bulanChart").datepicker({
            format: "yyyy-mm",
            startView: "months",
            minViewMode: "months",
        });
    }
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


// Topbar sticky border saat halaman digulir.
$(function () {
    var $header = $(".main-header");
    if (!$header.length) {
        return;
    }

    function syncScrolled() {
        $header.toggleClass("is-scrolled", window.scrollY > 4);
    }

    syncScrolled();
    window.addEventListener("scroll", syncScrolled, { passive: true });
});


// Sidebar ciut (layar >= 992px): sidebar tidak melebar saat di-hover (lihat app.css). Nama menu yang di-hover
// atau difokus tampil di samping ikonnya, lengkap dengan submenunya kalau ada, supaya submenu tetap bisa dibuka.
// Di layar < 992px sidebar berperan sebagai drawer geser (sinkron memakai matchMedia agar tidak terpengaruh
// lebar scrollbar di sekitar 992px). Pilihan ciut/lebar diingat untuk halaman berikutnya (header.blade.php).
$(function () {
    var $sidebar = $(".main-sidebar");
    if (!$sidebar.length) {
        return;
    }

    var wide = window.matchMedia("(min-width: 992px)");
    var $flyout = $('<div class="sidebar-flyout" role="tooltip" hidden></div>').appendTo("body");
    var hideTimer = null;
    var current = null;
    var openedAt = 0;

    function isCollapsed() {
        return wide.matches && $("body").hasClass("sidebar-collapse");
    }

    function hide(delay) {
        clearTimeout(hideTimer);
        var run = function () {
            $flyout.prop("hidden", true);
            current = null;
            openedAt = 0;
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
        openedAt = Date.now();

        // Nama menu tanpa panah submenu dan badge di dalam <p>; badge notifikasi dipindah ke judul flyout bila aktif.
        var $label = $(link).children("p").clone();
        $label.find(".right").remove();
        var $badge = $(link).find("[data-notification-count]").first();
        var $title = $('<div class="sidebar-flyout-title"></div>').text($.trim($label.text()));
        if ($badge.length && !$badge.prop("hidden")) {
            $title.append(" ").append($('<span class="badge badge-primary ml-2"></span>').text($.trim($badge.text())));
        }
        $flyout.empty().append($title);

        var $children = $(link).parent().children(".nav-treeview").find("> .nav-item > .nav-link");
        $flyout.toggleClass("has-menu", $children.length > 0);
        if ($children.length) {
            var $menu = $('<div class="sidebar-flyout-menu"></div>').appendTo($flyout);
            $children.each(function () {
                var $item = $('<a class="sidebar-flyout-link"></a>')
                    .attr("href", this.getAttribute("href"))
                    .toggleClass("active", $(this).hasClass("active"))
                    .text($.trim($(this).text()));
                if (this.hasAttribute("target")) {
                    $item.attr("target", this.getAttribute("target"));
                }
                if (this.hasAttribute("rel")) {
                    $item.attr("rel", this.getAttribute("rel"));
                }
                $item.appendTo($menu);
            });
        }

        // Baris judul sejajar dengan baris menu yang di-hover, tetap di dalam layar.
        var rect = link.getBoundingClientRect();
        $flyout.prop("hidden", false).css({ left: $sidebar[0].getBoundingClientRect().right + 8, top: 0 });
        var top = Math.min(rect.top, window.innerHeight - $flyout.outerHeight() - 8);
        $flyout.css("top", Math.max(8, top));
    }

    function syncViewport() {
        hide();
        var $body = $("body");
        if (wide.matches) {
            var rememberCollapsed = false;
            try {
                rememberCollapsed = localStorage.getItem("sidebar-collapsed") === "1";
            } catch (err) {}
            $body.removeClass("sidebar-open sidebar-closed").toggleClass("sidebar-collapse", rememberCollapsed);
        } else {
            $body.removeClass("sidebar-open").addClass("sidebar-closed sidebar-collapse");
        }
    }

    $sidebar
        .on("mouseenter focusin", ".nav-sidebar > .nav-item > .nav-link", function () {
            show(this);
        })
        .on("mouseleave focusout", ".nav-sidebar > .nav-item > .nav-link", function () {
            hide(150);
        })
        .on("click", ".nav-sidebar > .nav-item > .nav-link", function (e) {
            if (!isCollapsed()) {
                return;
            }
            if ($(this).parent().children(".nav-treeview").length) {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (current === this && !$flyout.prop("hidden")) {
                    if (Date.now() - openedAt > 400) {
                        hide();
                    }
                } else {
                    show(this);
                }
            }
        });

    // Jeda 150ms di atas memberi waktu kursor pindah dari ikon ke submenu di flyout.
    $flyout
        .on("mouseenter focusin", function () {
            clearTimeout(hideTimer);
        })
        .on("mouseleave focusout", function () {
            hide(150);
        });

    document.addEventListener("pointerdown", function (e) {
        if (!$flyout.prop("hidden") && e.target && e.target.closest && !e.target.closest(".main-sidebar, .sidebar-flyout")) {
            hide();
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") {
            return;
        }
        hide();
        if (!wide.matches && $("body").hasClass("sidebar-open")) {
            var $toggle = $('[data-widget="pushmenu"]').first();
            if ($toggle.length && $.fn.PushMenu) {
                $toggle.PushMenu("collapse");
            } else {
                $("body").removeClass("sidebar-open").addClass("sidebar-closed sidebar-collapse");
            }
        }
    });

    $sidebar[0].addEventListener("scroll", function () {
        hide();
    }, true);
    $(window).on("resize", function () {
        hide();
    });

    if (wide.addEventListener) {
        wide.addEventListener("change", syncViewport);
    } else if (wide.addListener) {
        wide.addListener(syncViewport);
    }

    $(document).on("collapsed.lte.pushmenu shown.lte.pushmenu", function (e) {
        hide();
        var $body = $("body");
        if (!wide.matches) {
            if (e.type === "shown") {
                $body.addClass("sidebar-open").removeClass("sidebar-closed sidebar-collapse");
            } else {
                $body.removeClass("sidebar-open").addClass("sidebar-closed sidebar-collapse");
            }
            return;
        }
        $body.removeClass("sidebar-open sidebar-closed");
        try {
            localStorage.setItem("sidebar-collapsed", e.type === "collapsed" ? "1" : "0");
        } catch (err) {}
    });
});

// Kotak unggah file (x-file-dropzone): klik atau seret file ke kotak, cek tipe dan ukurannya,
// tampilkan baris file terpilih, dan ubah barisnya jadi "Mengunggah…" saat form dikirim.
(function () {
    function formatBytes(bytes) {
        var units = ["B", "KB", "MB", "GB"];
        var i = 0;
        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        return bytes.toLocaleString("id-ID", { maximumFractionDigits: i === 0 ? 0 : 1 }) + " " + units[i];
    }

    function setup(zone) {
        var input = zone.querySelector(".file-dropzone-input");
        var item = zone.querySelector(".file-dropzone-item");
        if (!input || !item) return;

        var extensions = (zone.dataset.extensions || "").split(",").filter(Boolean);
        var maxBytes = parseInt(zone.dataset.maxBytes, 10) || 0;
        var nameEl = item.querySelector(".file-dropzone-name");
        var extEl = item.querySelector(".file-dropzone-ext");
        var sizeEl = item.querySelector(".file-dropzone-size");
        var stateEl = item.querySelector(".file-dropzone-state");

        function show(file, state, error) {
            var ext = (file.name.split(".").pop() || "").toLowerCase();
            nameEl.textContent = file.name;
            nameEl.title = file.name;
            extEl.textContent = ext.slice(0, 4).toUpperCase();
            sizeEl.textContent = formatBytes(file.size);
            stateEl.textContent = state;
            item.classList.toggle("is-error", !!error);
            item.classList.toggle("is-ready", !error);
            item.classList.remove("is-uploading");
            item.hidden = false;
        }

        function clear() {
            input.value = "";
            item.hidden = true;
            item.classList.remove("is-error", "is-ready", "is-uploading");
        }

        function check(file) {
            var ext = (file.name.split(".").pop() || "").toLowerCase();
            if (extensions.length && extensions.indexOf(ext) === -1) {
                return "Tipe file tidak didukung";
            }
            if (maxBytes && file.size > maxBytes) {
                return "Melebihi " + zone.dataset.maxLabel;
            }
            return null;
        }

        function handle() {
            var file = input.files && input.files[0];
            if (!file) {
                clear();
                return;
            }
            zone.classList.remove("is-invalid");
            var error = check(file);
            if (error) {
                // File ditolak tetap ditampilkan supaya pengguna tahu alasannya, tapi tidak ikut terkirim.
                show(file, error, true);
                input.value = "";
                return;
            }
            show(file, "Siap diunggah", false);
        }

        input.addEventListener("change", handle);

        item.querySelector(".file-dropzone-remove").addEventListener("click", function () {
            clear();
            input.focus();
        });

        ["dragenter", "dragover"].forEach(function (type) {
            zone.addEventListener(type, function (e) {
                if (!e.dataTransfer || Array.prototype.indexOf.call(e.dataTransfer.types, "Files") === -1) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = "copy";
                zone.classList.add("is-dragover");
            });
        });

        zone.addEventListener("dragleave", function (e) {
            if (!zone.contains(e.relatedTarget)) {
                zone.classList.remove("is-dragover");
            }
        });

        zone.addEventListener("drop", function (e) {
            zone.classList.remove("is-dragover");
            if (!e.dataTransfer || !e.dataTransfer.files.length) return;
            e.preventDefault();
            try {
                var transfer = new DataTransfer();
                transfer.items.add(e.dataTransfer.files[0]);
                input.files = transfer.files;
            } catch (err) {
                input.files = e.dataTransfer.files;
            }
            handle();
        });

        if (input.form) {
            input.form.addEventListener("submit", function (e) {
                if (e.defaultPrevented || !input.files || !input.files.length) return;
                item.classList.add("is-uploading");
                item.classList.remove("is-ready");
                stateEl.textContent = "Mengunggah…";
            });
        }

        // Kembali ke halaman ini lewat tombol Back: browser bisa mengosongkan input, jadi baris ikut disamakan.
        window.addEventListener("pageshow", handle);
    }

    document.querySelectorAll(".file-dropzone").forEach(setup);
})();
