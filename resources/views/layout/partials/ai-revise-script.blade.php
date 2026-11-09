{{--
    Dipasang sekali di halaman kelola soal (pelatihan & modul).
    "Ubah dengan AI" untuk satu soal (kartu di layar tinjauan soal AI, atau form edit soal tersimpan): AI membaca ulang
    materi lalu menulis ulang pertanyaan saja, jawaban saja, atau semuanya, dan mengecek pertanyaannya belum ada di soal
    lain. Gambar soal/pilihan tidak disentuh.
    Pemakaian di JS:
      aiReviseButton()             -> tombol untuk header kartu
      aiRevisePanel(key, essay)    -> panel pilihan + instruksi, taruh di awal badan kartu
      mountAiRevise(form, key)     -> pasang tombol + panel di awal .modal-body form edit
      bindAiRevise(container, { url, cardSelector, read(card), write(card, q, part), extra(card), statusUrl(token), collectOthers })
        read          : nilai soal saat ini dari kartu {type?, question, option_a..e, correct_answer, explanation}
        write         : isi kartu dengan soal hasil AI (juga dipakai untuk "Kembalikan", dengan part 'all')
        extra         : field tambahan untuk request: materi acuan (document_ids / item_ids), question_id, api_key
        statusUrl     : (opsional) bila server menjawab {token}, hasil diambil dengan polling ke alamat ini
        collectOthers : (default true) kirim pertanyaan kartu lain sebagai other_questions supaya AI tidak membuat
                        soal yang sama; form edit memakai false karena server sudah membandingkan dengan soal tersimpan.
--}}
<script>
(function () {
    var csrf = @json(csrf_token());

    window.aiReviseButton = function () {
        return '<button type="button" class="btn btn-default btn-xs ai-revise-toggle" title="Ubah soal ini dengan AI">' +
            '<i class="fas fa-magic mr-1"></i>Ubah dengan AI' +
        '</button>';
    };

    window.aiRevisePanel = function (index, isEssay) {
        var parts = [
            ['question', 'Pertanyaan saja'],
            ['answers', isEssay ? 'Kunci jawaban saja' : 'Jawaban saja'],
            ['all', 'Semuanya']
        ];
        var radios = parts.map(function (part, i) {
            var id = 'ai_revise_' + index + '_' + part[0];
            return '<div class="custom-control custom-radio custom-control-inline">' +
                '<input type="radio" id="' + id + '" name="ai_revise_part_' + index + '" value="' + part[0] + '" class="custom-control-input ai-revise-part"' + (i === 0 ? ' checked' : '') + '>' +
                '<label class="custom-control-label" for="' + id + '">' + part[1] + '</label>' +
            '</div>';
        }).join('');

        return '<div class="ai-revise-panel border rounded p-2 mb-3 bg-light d-none">' +
            '<div class="small font-weight-bold mb-1">Bagian yang diubah AI</div>' +
            '<div class="mb-2">' + radios + '</div>' +
            '<textarea class="form-control form-control-sm mb-2 ai-revise-instruction" rows="2" maxlength="500" placeholder="Instruksi (opsional), mis. buat lebih sulit, perjelas istilahnya"></textarea>' +
            '<div class="d-flex align-items-center flex-wrap gap-2">' +
                '<button type="button" class="btn btn-primary btn-xs ai-revise-run"><i class="fas fa-magic mr-1"></i>Ubah</button>' +
                '<button type="button" class="btn btn-default btn-xs ai-revise-undo d-none">Kembalikan sebelum diubah</button>' +
                '<span class="small text-muted ai-revise-status"></span>' +
            '</div>' +
            '<div class="small text-warning mt-1 ai-revise-warning d-none"></div>' +
            '<div class="small text-danger mt-1 ai-revise-error d-none"></div>' +
        '</div>';
    };

    window.mountAiRevise = function (form, key) {
        var body = form.querySelector('.modal-body');
        body.insertAdjacentHTML('afterbegin',
            '<div class="d-flex justify-content-end mb-2">' + window.aiReviseButton() + '</div>' + window.aiRevisePanel(key, false));
    };

    window.bindAiRevise = function (container, options) {
        function panelOf(card) {
            return card.querySelector('.ai-revise-panel');
        }

        function setStatus(panel, text, error, warning) {
            panel.querySelector('.ai-revise-status').textContent = error ? '' : (text || '');
            [['.ai-revise-error', error], ['.ai-revise-warning', warning]].forEach(function (pair) {
                var el = panel.querySelector(pair[0]);
                el.textContent = pair[1] || '';
                el.classList.toggle('d-none', !pair[1]);
            });
        }

        function postJson(body) {
            return fetch(options.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body)
            }).then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            }).then(function (result) {
                if (!result.ok || !result.data.success) {
                    var errors = result.data && result.data.errors ? Object.values(result.data.errors)[0][0] : null;
                    throw new Error(errors || (result.data && result.data.message) || 'Gagal mengubah soal dengan AI.');
                }
                return result.data.token && options.statusUrl ? poll(result.data.token) : result.data;
            });
        }

        // Ubah soal modul berjalan di queue (video bisa beberapa menit): tunggu sampai selesai.
        function poll(token) {
            return new Promise(function (resolve, reject) {
                function check(delay) {
                    setTimeout(function () {
                        fetch(options.statusUrl(token), { headers: { 'Accept': 'application/json' } })
                            .then(function (res) { return res.json(); })
                            .then(function (data) {
                                if (data.status === 'done') {
                                    resolve(data);
                                } else if (data.status === 'failed') {
                                    reject(new Error(data.message || 'Gagal mengubah soal dengan AI.'));
                                } else {
                                    check(3000);
                                }
                            })
                            .catch(function () { check(6000); });
                    }, delay);
                }
                check(1500);
            });
        }

        function otherQuestions(card) {
            return Array.prototype.filter.call(container.querySelectorAll(options.cardSelector), function (other) {
                return other !== card;
            }).map(function (other) {
                return String(options.read(other).question || '').trim();
            }).filter(Boolean);
        }

        container.addEventListener('click', function (e) {
            // Satu halaman bisa punya beberapa pengikatan (kartu tinjauan & form edit): tangani milik sendiri saja.
            var card = e.target.closest(options.cardSelector);
            if (!card || !container.contains(card)) {
                return;
            }

            var toggle = e.target.closest('.ai-revise-toggle');
            if (toggle) {
                var panel = panelOf(card);
                panel.classList.toggle('d-none');
                if (!panel.classList.contains('d-none')) {
                    panel.querySelector('.ai-revise-instruction').focus();
                }
                return;
            }

            var undo = e.target.closest('.ai-revise-undo');
            if (undo) {
                if (card._aiReviseBefore) {
                    options.write(card, card._aiReviseBefore, 'all');
                    card._aiReviseBefore = null;
                }
                undo.classList.add('d-none');
                setStatus(panelOf(card), 'Soal dikembalikan seperti sebelum diubah.');
                return;
            }

            var run = e.target.closest('.ai-revise-run');
            if (!run) {
                return;
            }

            var runPanel = panelOf(card);
            var question = options.read(card);
            if (!question.question || !String(question.question).trim()) {
                setStatus(runPanel, '', 'Isi pertanyaan dulu sebelum diubah dengan AI.');
                return;
            }

            var checkedPart = runPanel.querySelector('.ai-revise-part:checked');
            var part = checkedPart ? checkedPart.value : 'all';
            var body = Object.assign({
                question: question,
                part: part,
                instruction: runPanel.querySelector('.ai-revise-instruction').value.trim() || null,
                other_questions: options.collectOthers === false ? [] : otherQuestions(card)
            }, options.extra ? options.extra(card) : {});

            var originalHtml = run.innerHTML;
            var seconds = 0;
            var busyText = function () { return 'AI sedang membaca ulang materi dan mengecek soal lain... ' + seconds + ' detik'; };
            run.disabled = true;
            run.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span>Mengubah...';
            setStatus(runPanel, busyText());
            var timer = setInterval(function () { seconds++; setStatus(runPanel, busyText()); }, 1000);

            postJson(body).then(function (data) {
                // Simpan isi kartu tepat sebelum ditimpa, untuk tombol "Kembalikan".
                card._aiReviseBefore = options.read(card);
                options.write(card, data.question, part);
                runPanel.querySelector('.ai-revise-undo').classList.remove('d-none');
                clearInterval(timer);
                setStatus(runPanel, 'Soal sudah diubah AI. Periksa lagi sebelum disimpan.', null, data.similar_to
                    ? 'Pertanyaan ini masih mirip dengan soal yang sudah ada: "' + data.similar_to + '". Coba ubah lagi dengan pilihan "Semuanya" atau sunting manual.'
                    : null);
            }).catch(function (err) {
                clearInterval(timer);
                setStatus(runPanel, '', err.message);
            }).finally(function () {
                run.disabled = false;
                run.innerHTML = originalHtml;
            });
        });
    };
})();
</script>
