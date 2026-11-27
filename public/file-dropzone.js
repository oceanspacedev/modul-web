// Kotak unggah file (x-file-dropzone): klik atau seret file ke kotak, cek tipe, ukuran dan jumlahnya,
// tampilkan baris tiap file, dan ubah barisnya jadi "Mengunggah…" saat form dikirim.
// Semua lewat event delegation, jadi kotak yang ditambahkan belakangan (template, modal) ikut jalan.
(function () {
    if (window.FileDropzone) return;

    var KINDS = {
        pdf: ["pdf"],
        word: ["doc", "docx"],
        excel: ["xls", "xlsx", "csv"],
        ppt: ["ppt", "pptx"],
        visio: ["vsd", "vsdx"],
        image: ["jpg", "jpeg", "png", "webp", "gif"],
        video: ["mp4", "webm", "ogg", "mov", "mkv"],
    };
    var ICONS = {
        pdf: "fa-file-pdf",
        word: "fa-file-word",
        excel: "fa-file-excel",
        ppt: "fa-file-powerpoint",
        visio: "fa-file-alt",
        image: "fa-file-image",
        video: "fa-file-video",
        other: "fa-file-alt",
    };

    function extOf(file) {
        var parts = file.name.split(".");
        return parts.length > 1 ? parts.pop().toLowerCase() : "";
    }

    function kindOf(ext) {
        for (var kind in KINDS) {
            if (KINDS[kind].indexOf(ext) !== -1) return kind;
        }
        return "other";
    }

    function formatBytes(bytes) {
        var units = ["B", "KB", "MB", "GB"];
        var i = 0;
        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        return bytes.toLocaleString("id-ID", { maximumFractionDigits: i === 0 ? 0 : 1 }) + " " + units[i];
    }

    function zoneOf(el) {
        return el && el.closest ? el.closest(".file-dropzone") : null;
    }

    function inputOf(zone) {
        return zone.querySelector(".file-dropzone-input");
    }

    function setFiles(input, files) {
        try {
            var transfer = new DataTransfer();
            files.forEach(function (file) { transfer.items.add(file); });
            input.files = transfer.files;
            return true;
        } catch (err) {
            return false;
        }
    }

    function revokePreviews(zone) {
        (zone._previewUrls || []).forEach(function (url) { URL.revokeObjectURL(url); });
        zone._previewUrls = [];
    }

    function render(zone, uploading) {
        var input = inputOf(zone);
        var list = zone.querySelector(".file-dropzone-list");
        var tpl = zone.querySelector(".file-dropzone-template");
        if (!input || !list || !tpl) return;

        revokePreviews(zone);
        list.innerHTML = "";

        var accepted = Array.prototype.slice.call(input.files || []);
        var rejected = zone._rejected || [];
        var rows = accepted.map(function (file, i) { return { file: file, index: i } })
            .concat(rejected.map(function (item, i) { return { file: item.file, error: item.error, rejectedIndex: i } }));

        rows.forEach(function (row) {
            var item = tpl.content.firstElementChild.cloneNode(true);
            var ext = extOf(row.file);
            var kind = kindOf(ext);
            var icon = item.querySelector(".file-dropzone-file-icon");

            icon.classList.add("is-" + kind);
            icon.querySelector("i").className = "fas " + ICONS[kind];
            item.querySelector(".file-dropzone-ext").textContent = ext.slice(0, 4).toUpperCase();
            if (kind === "image" && !row.error && window.URL && URL.createObjectURL) {
                var url = URL.createObjectURL(row.file);
                zone._previewUrls.push(url);
                var img = document.createElement("img");
                img.src = url;
                img.alt = "";
                icon.classList.add("has-preview");
                icon.appendChild(img);
            }

            var nameEl = item.querySelector(".file-dropzone-name");
            nameEl.textContent = row.file.name;
            nameEl.title = row.file.name;
            item.querySelector(".file-dropzone-size").textContent = formatBytes(row.file.size);

            var state = item.querySelector(".file-dropzone-state");
            var remove = item.querySelector(".file-dropzone-remove");
            if (row.error) {
                item.classList.add("is-error");
                state.textContent = row.error;
                remove.dataset.rejected = row.rejectedIndex;
            } else if (uploading) {
                item.classList.add("is-uploading");
                state.textContent = "Mengunggah…";
            } else {
                item.classList.add("is-ready");
                state.textContent = "Siap diunggah";
                remove.dataset.index = row.index;
            }
            remove.setAttribute("aria-label", "Hapus " + row.file.name);
            list.appendChild(item);
        });

        var announce = zone.querySelector("[data-dropzone-announce]");
        if (announce) {
            announce.textContent = uploading ? "Mengunggah " + accepted.length + " file"
                : rows.length ? accepted.length + " file siap, " + rejected.length + " ditolak" : "";
        }
    }

    // Pisahkan file yang lolos dari yang ditolak; yang ditolak tetap tampil dengan alasannya tapi tidak ikut terkirim.
    function check(zone) {
        var input = inputOf(zone);
        var files = Array.prototype.slice.call(input.files || []);
        var extensions = (zone.dataset.extensions || "").split(",").filter(Boolean);
        var maxBytes = parseInt(zone.dataset.maxBytes, 10) || 0;
        var maxFiles = parseInt(zone.dataset.maxFiles, 10) || 0;
        var ok = [];
        var rejected = [];

        files.forEach(function (file) {
            var error = null;
            if (extensions.length && extensions.indexOf(extOf(file)) === -1) {
                error = "Tipe file tidak didukung";
            } else if (maxBytes && file.size > maxBytes) {
                error = "Melebihi " + zone.dataset.maxLabel;
            } else if (maxFiles && ok.length >= maxFiles) {
                error = "Melebihi " + maxFiles + " file";
            }
            if (error) {
                rejected.push({ file: file, error: error });
            } else {
                ok.push(file);
            }
        });

        if (rejected.length && !setFiles(input, ok)) {
            input.value = "";
        }
        zone._rejected = rejected;
        zone.classList.remove("is-invalid");
        render(zone, false);
    }

    document.addEventListener("change", function (e) {
        if (!e.target.matches || !e.target.matches(".file-dropzone-input")) return;
        var zone = zoneOf(e.target);
        if (zone) check(zone);
    }, true);

    document.addEventListener("click", function (e) {
        var button = e.target.closest ? e.target.closest(".file-dropzone-remove") : null;
        var zone = zoneOf(button);
        if (!zone) return;
        var input = inputOf(zone);

        if (button.dataset.rejected !== undefined) {
            zone._rejected.splice(parseInt(button.dataset.rejected, 10), 1);
            render(zone, false);
            input.focus();
            return;
        }

        var index = parseInt(button.dataset.index, 10);
        var keep = Array.prototype.slice.call(input.files || []).filter(function (file, i) { return i !== index; });
        if (!setFiles(input, keep)) input.value = "";
        input.focus();
        // Beri tahu skrip halaman (pratinjau, hitung lampiran) bahwa pilihan file berubah.
        input.dispatchEvent(new Event("change", { bubbles: true }));
    });

    function hasFiles(e) {
        return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], "Files") !== -1;
    }

    ["dragenter", "dragover"].forEach(function (type) {
        document.addEventListener(type, function (e) {
            var zone = zoneOf(e.target);
            if (!zone || !hasFiles(e) || inputOf(zone).disabled) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = "copy";
            zone.classList.add("is-dragover");
        });
    });

    document.addEventListener("dragleave", function (e) {
        var zone = zoneOf(e.target);
        if (zone && !zone.contains(e.relatedTarget)) {
            zone.classList.remove("is-dragover");
        }
    });

    document.addEventListener("drop", function (e) {
        var zone = zoneOf(e.target);
        if (!zone) return;
        zone.classList.remove("is-dragover");
        var input = inputOf(zone);
        if (!hasFiles(e) || input.disabled || !e.dataTransfer.files.length) return;
        e.preventDefault();

        var files = Array.prototype.slice.call(e.dataTransfer.files);
        if (!input.multiple) files = files.slice(0, 1);
        if (!setFiles(input, files)) {
            input.files = e.dataTransfer.files;
        }
        input.dispatchEvent(new Event("change", { bubbles: true }));
    });

    // Bubble di document: handler form yang membatalkan kirim (validasi, AJAX) sudah jalan lebih dulu.
    document.addEventListener("submit", function (e) {
        if (e.defaultPrevented) return;
        e.target.querySelectorAll(".file-dropzone").forEach(function (zone) {
            var input = inputOf(zone);
            if (input && !input.disabled && input.files && input.files.length) {
                render(zone, true);
            }
        });
    });

    // Kembali lewat tombol Back: browser bisa mengosongkan atau mempertahankan input, jadi baris disamakan.
    window.addEventListener("pageshow", function () {
        document.querySelectorAll(".file-dropzone").forEach(function (zone) {
            zone._rejected = [];
            render(zone, false);
        });
    });

    window.FileDropzone = { refresh: render };
})();
