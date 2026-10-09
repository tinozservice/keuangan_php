/* Pencatat Keuangan — skrip antarmuka kecil (tanpa dependensi). */
(function () {
    'use strict';

    /* --- Toggle menu navigasi seluler --- */
    var toggle = document.querySelector('[data-nav-toggle]');
    var menu = document.getElementById('nav-menu');

    if (toggle && menu) {
        var label = toggle.querySelector('.sr-only');

        var setOpen = function (open) {
            menu.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (label) {
                label.textContent = open ? 'Tutup menu' : 'Buka menu';
            }
        };

        toggle.addEventListener('click', function () {
            setOpen(!menu.classList.contains('is-open'));
        });

        menu.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                setOpen(false);
            }
        });
    }

    /* --- Dropdown avatar (Pengaturan Akun / Keluar) --- */
    var avatarToggle = document.querySelector('[data-avatar-toggle]');
    var avatarMenu = avatarToggle ? avatarToggle.closest('.avatar-menu') : null;

    if (avatarToggle && avatarMenu) {
        var setAvatarOpen = function (open) {
            avatarMenu.classList.toggle('is-open', open);
            avatarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        avatarToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            setAvatarOpen(!avatarMenu.classList.contains('is-open'));
        });

        document.addEventListener('click', function (event) {
            if (avatarMenu.classList.contains('is-open') && !avatarMenu.contains(event.target)) {
                setAvatarOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setAvatarOpen(false);
            }
        });
    }

    /* --- Input transaksi via AI: rekam suara / foto struk (FR-022–FR-026) --- */
    var aiPanel = document.querySelector('[data-ai-extract]');

    if (aiPanel) {
        var aiEndpoint = aiPanel.getAttribute('data-endpoint') || '';
        var aiRecord = aiPanel.querySelector('[data-ai-record]');
        var aiRecordLabel = aiPanel.querySelector('[data-ai-record-label]');
        var aiPhoto = aiPanel.querySelector('[data-ai-photo]');
        var aiFile = aiPanel.querySelector('[data-ai-file]');
        var aiStatus = aiPanel.querySelector('[data-ai-status]');
        var aiForm = aiPanel.closest('form');
        var aiToken = aiForm ? aiForm.querySelector('input[name="csrf_token"]') : null;
        var aiRecorder = null;
        var aiChunks = [];
        var aiBusy = false;

        var aiSetStatus = function (message, kind) {
            if (!aiStatus) { return; }
            aiStatus.textContent = message;
            aiStatus.classList.remove('is-error', 'is-ok');
            if (kind) { aiStatus.classList.add(kind === 'error' ? 'is-error' : 'is-ok'); }
        };

        var aiSetBusy = function (state) {
            aiBusy = state;
            if (aiRecord) { aiRecord.disabled = state; }
            if (aiPhoto) { aiPhoto.disabled = state; }
        };

        var aiFill = function (fields) {
            if (!fields) { return; }
            var dateInput = document.getElementById('tx_date');
            var typeInput = document.getElementById('type');
            var accountInput = document.getElementById('account_id');
            var amountInput = document.getElementById('amount');
            var descInput = document.getElementById('description');
            if (fields.tanggal && dateInput) { dateInput.value = fields.tanggal; }
            if (fields.jenis && typeInput) { typeInput.value = fields.jenis; }
            if (fields.rekening_id && accountInput) { accountInput.value = String(fields.rekening_id); }
            if (fields.nominal && amountInput) { amountInput.value = fields.nominal; }
            if (fields.deskripsi && descInput) { descInput.value = fields.deskripsi; }
        };

        var aiUpload = function (blob, filename, kind) {
            if (!aiEndpoint || !aiToken) {
                aiSetStatus('Formulir tidak siap untuk mengirim berkas.', 'error');
                return;
            }
            var data = new FormData();
            data.append('csrf_token', aiToken.value);
            data.append('jenis', kind);
            data.append('berkas', blob, filename);
            aiSetBusy(true);
            aiSetStatus(kind === 'suara' ? 'Mengirim rekaman…' : 'Mengirim foto…');
            fetch(aiEndpoint, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (res) {
                    return res.json().catch(function () {
                        return { ok: false, message: 'Respons server tidak valid (HTTP ' + res.status + ').' };
                    });
                })
                .then(function (payload) {
                    if (payload && payload.ok) {
                        aiFill(payload.fields);
                        var filled = payload.fields ? ['tanggal', 'jenis', 'nominal', 'deskripsi', 'rekening_id'].filter(function (key) {
                            return payload.fields[key] !== null && payload.fields[key] !== undefined && payload.fields[key] !== '';
                        }).length : 0;
                        if (filled === 0) {
                            aiSetStatus('Model tidak mengisi isian apa pun — coba lagi dengan rekaman/foto lebih jelas, atau isi manual.', 'error');
                            return;
                        }
                        var extra = payload.transcript ? ' Terdengar: "' + payload.transcript + '".' : '';
                        aiSetStatus('Berhasil diproses oleh model ' + (payload.model || '-') + '.' + extra + ' Periksa isian lalu simpan.', 'ok');
                    } else {
                        aiSetStatus((payload && payload.message) ? payload.message : 'Gagal memproses berkas.', 'error');
                    }
                })
                .catch(function () {
                    aiSetStatus('Koneksi gagal saat mengirim berkas.', 'error');
                })
                .then(function () { aiSetBusy(false); });
        };

        var aiCompress = function (file, done) {
            var reader = new FileReader();
            reader.onload = function () {
                var img = new Image();
                img.onload = function () {
                    var maxSide = 1600;
                    var scale = Math.min(1, maxSide / Math.max(img.width, img.height));
                    var width = Math.max(1, Math.round(img.width * scale));
                    var height = Math.max(1, Math.round(img.height * scale));
                    var canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                    canvas.toBlob(function (blob) {
                        done(blob && blob.size > 0 ? blob : file);
                    }, 'image/jpeg', 0.82);
                };
                img.onerror = function () { done(file); };
                img.src = reader.result;
            };
            reader.onerror = function () { done(file); };
            reader.readAsDataURL(file);
        };

        var aiToWav = function (blob, done) {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) { done(blob, 'webm'); return; }
            var reader = new FileReader();
            reader.onload = function () {
                var ctx = new Ctx();
                ctx.decodeAudioData(reader.result, function (buffer) {
                    var samples = buffer.getChannelData(0);
                    var rate = buffer.sampleRate;
                    var length = samples.length;
                    var out = new ArrayBuffer(44 + length * 2);
                    var view = new DataView(out);
                    var writeStr = function (offset, text) {
                        for (var i = 0; i < text.length; i++) { view.setUint8(offset + i, text.charCodeAt(i)); }
                    };
                    writeStr(0, 'RIFF');
                    view.setUint32(4, 36 + length * 2, true);
                    writeStr(8, 'WAVE');
                    writeStr(12, 'fmt ');
                    view.setUint32(16, 16, true);
                    view.setUint16(20, 1, true);
                    view.setUint16(22, 1, true);
                    view.setUint32(24, rate, true);
                    view.setUint32(28, rate * 2, true);
                    view.setUint16(32, 2, true);
                    view.setUint16(34, 16, true);
                    writeStr(36, 'data');
                    view.setUint32(40, length * 2, true);
                    var offset = 44;
                    for (var i = 0; i < length; i++) {
                        var s = Math.max(-1, Math.min(1, samples[i]));
                        view.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
                        offset += 2;
                    }
                    ctx.close();
                    done(new Blob([out], { type: 'audio/wav' }), 'wav');
                }, function () {
                    ctx.close();
                    done(blob, 'webm');
                });
            };
            reader.onerror = function () { done(blob, 'webm'); };
            reader.readAsArrayBuffer(blob);
        };

        if (aiRecord) {
            aiRecord.addEventListener('click', function () {
                if (aiBusy) { return; }
                if (aiRecorder && aiRecorder.state === 'recording') { aiRecorder.stop(); return; }
                if (!navigator.mediaDevices || !window.MediaRecorder) {
                    aiSetStatus('Peramban ini tidak mendukung rekaman suara.', 'error');
                    return;
                }
                navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                    aiChunks = [];
                    aiRecorder = new MediaRecorder(stream);
                    aiRecorder.addEventListener('dataavailable', function (event) {
                        if (event.data && event.data.size > 0) { aiChunks.push(event.data); }
                    });
                    aiRecorder.addEventListener('stop', function () {
                        stream.getTracks().forEach(function (track) { track.stop(); });
                        if (aiRecordLabel) { aiRecordLabel.textContent = 'Rekam suara'; }
                        aiRecord.setAttribute('aria-pressed', 'false');
                        aiRecord.classList.remove('btn-primary');
                        aiRecord.classList.add('btn-ghost');
                        if (aiChunks.length === 0) {
                            aiSetStatus('Tidak ada audio yang terekam.', 'error');
                            return;
                        }
                        var blob = new Blob(aiChunks, { type: aiRecorder.mimeType || 'audio/webm' });
                        aiToWav(blob, function (wavBlob, ext) {
                            aiUpload(wavBlob, 'rekaman.' + ext, 'suara');
                        });
                    });
                    aiRecorder.start();
                    aiRecord.setAttribute('aria-pressed', 'true');
                    if (aiRecordLabel) { aiRecordLabel.textContent = 'Stop rekaman'; }
                    aiRecord.classList.remove('btn-ghost');
                    aiRecord.classList.add('btn-primary');
                    aiSetStatus('Sedang merekam… tekan tombol lagi untuk berhenti.', 'ok');
                }).catch(function () {
                    aiSetStatus('Izin mikrofon ditolak atau tidak tersedia.', 'error');
                });
            });
        }

        if (aiPhoto && aiFile) {
            aiPhoto.addEventListener('click', function () { if (!aiBusy) { aiFile.click(); } });
            aiFile.addEventListener('change', function () {
                var file = aiFile.files && aiFile.files[0] ? aiFile.files[0] : null;
                aiFile.value = '';
                if (!file) { return; }
                if (!/^image\//.test(file.type)) {
                    aiSetStatus('Berkas harus berupa gambar.', 'error');
                    return;
                }
                aiCompress(file, function (blob) { aiUpload(blob, 'foto-struk.jpg', 'foto'); });
            });
        }
    }

    /* --- Pencarian bahasa alami: prompt teks & suara (FR-027–FR-031) --- */
    var aiSearch = document.querySelector('[data-ai-search]');

    if (aiSearch) {
        var searchEndpoint = aiSearch.getAttribute('data-endpoint') || '';
        var searchInput = document.getElementById('ai_cari');
        var searchGo = aiSearch.querySelector('[data-ai-search-go]');
        var searchMic = aiSearch.querySelector('[data-ai-search-mic]');
        var searchMicLabel = aiSearch.querySelector('[data-ai-search-mic-label]');
        var searchStatus = aiSearch.querySelector('[data-ai-search-status]');
        var searchToken = document.querySelector('input[name="csrf_token"]');
        var searchWsId = '';
        var searchQIdx = searchEndpoint.indexOf('?');
        if (searchQIdx >= 0) { searchWsId = new URLSearchParams(searchEndpoint.slice(searchQIdx + 1)).get('id') || ''; }
        var searchRecorder = null;
        var searchChunks = [];
        var searchBusy = false;

        var searchSetStatus = function (message, kind) {
            if (!searchStatus) { return; }
            searchStatus.textContent = message;
            searchStatus.classList.remove('is-error', 'is-ok');
            if (kind) { searchStatus.classList.add(kind === 'error' ? 'is-error' : 'is-ok'); }
        };
        var searchSetBusy = function (state) {
            searchBusy = state;
            if (searchGo) { searchGo.disabled = state; }
            if (searchMic) { searchMic.disabled = state; }
        };
        var searchApply = function (payload) {
            var filter = payload.filter || {};
            var parts = [];
            if (searchWsId) { parts.push('id=' + encodeURIComponent(searchWsId)); }
            if (filter.dari) { parts.push('dari=' + encodeURIComponent(filter.dari)); }
            if (filter.sampai) { parts.push('sampai=' + encodeURIComponent(filter.sampai)); }
            if (filter.q) { parts.push('q=' + encodeURIComponent(filter.q)); }
            if (filter.rekening_id) { parts.push('rekening=' + encodeURIComponent(filter.rekening_id)); }
            var extra = payload.transcript ? ' (Terdengar: "' + payload.transcript + '")' : '';
            searchSetStatus('Filter ditemukan' + extra + ' — memuat daftar transaksi…', 'ok');
            var base = searchEndpoint.split('?')[0];
            window.location.href = base.replace('transaksi-cari-ai.php', 'transaksi.php') + '?' + parts.join('&');
        };
        var searchPost = function (data) {
            if (!searchEndpoint || !searchToken) {
                searchSetStatus('Formulir pencarian tidak siap.', 'error');
                return;
            }
            data.append('csrf_token', searchToken.value);
            searchSetBusy(true);
            searchSetStatus('Memproses permintaan…');
            fetch(searchEndpoint, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (res) {
                    return res.json().catch(function () {
                        return { ok: false, message: 'Respons server tidak valid (HTTP ' + res.status + ').' };
                    });
                })
                .then(function (payload) {
                    if (payload && payload.ok) {
                        searchApply(payload);
                    } else {
                        searchSetStatus((payload && payload.message) ? payload.message : 'Gagal memproses permintaan.', 'error');
                    }
                })
                .catch(function () { searchSetStatus('Koneksi gagal.', 'error'); })
                .then(function () { searchSetBusy(false); });
        };

        if (searchGo) {
            searchGo.addEventListener('click', function () {
                if (searchBusy) { return; }
                var text = searchInput ? searchInput.value.trim() : '';
                if (text === '') { searchSetStatus('Tulis permintaan pencarian dulu.', 'error'); return; }
                var data = new FormData();
                data.append('jenis', 'teks');
                data.append('teks', text);
                searchPost(data);
            });
        }

        if (searchMic) {
            searchMic.addEventListener('click', function () {
                if (searchBusy) { return; }
                if (searchRecorder && searchRecorder.state === 'recording') { searchRecorder.stop(); return; }
                if (!navigator.mediaDevices || !window.MediaRecorder) {
                    searchSetStatus('Peramban ini tidak mendukung rekaman suara.', 'error');
                    return;
                }
                navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                    searchChunks = [];
                    searchRecorder = new MediaRecorder(stream);
                    searchRecorder.addEventListener('dataavailable', function (event) {
                        if (event.data && event.data.size > 0) { searchChunks.push(event.data); }
                    });
                    searchRecorder.addEventListener('stop', function () {
                        stream.getTracks().forEach(function (track) { track.stop(); });
                        if (searchMicLabel) { searchMicLabel.textContent = 'Cari dengan suara'; }
                        searchMic.setAttribute('aria-pressed', 'false');
                        searchMic.classList.remove('btn-primary');
                        searchMic.classList.add('btn-ghost');
                        if (searchChunks.length === 0) { searchSetStatus('Tidak ada audio terekam.', 'error'); return; }
                        var blob = new Blob(searchChunks, { type: searchRecorder.mimeType || 'audio/webm' });
                        aiToWav(blob, function (wavBlob, ext) {
                            var data = new FormData();
                            data.append('jenis', 'suara');
                            data.append('berkas', wavBlob, 'cari.' + ext);
                            searchPost(data);
                        });
                    });
                    searchRecorder.start();
                    searchMic.setAttribute('aria-pressed', 'true');
                    if (searchMicLabel) { searchMicLabel.textContent = 'Stop rekaman'; }
                    searchMic.classList.remove('btn-ghost');
                    searchMic.classList.add('btn-primary');
                    searchSetStatus('Sedang merekam… tekan tombol lagi untuk berhenti.', 'ok');
                }).catch(function () { searchSetStatus('Izin mikrofon ditolak atau tidak tersedia.', 'error'); });
            });
        }
    }
})();
