<script>
/*!
 * Modal Penempatan / Pemindahan Pallet — alur 2 langkah scan QR.
 *
 * TEMPATKAN (mode place), urutan:
 *   1. QR Lokasi  -> wajib sama dengan area yang sedang dibuka di board
 *   2. QR Pallet  -> pallet dikunci ke lokasi tersebut
 *
 * PINDAH (mode move), urutan:
 *   1. QR Pallet  -> pallet yang mau dipindah (bebas, bukan area yang dibuka)
 *   2. QR Lokasi  -> lokasi TUJUAN, harus kosong
 *
 * Setelah pasangan valid, form penempatan karton dibuka.
 */
(function ($) {
    'use strict';

    window.PalletPlacementFlow = {
        mode: 'place',
        step: 1,
        locationCode: null,
        palletCode: null,
        scanner: null,
        expectedLocationCode: null,   // hanya dipakai mode place
        expectedAddressId: null,
        onLocked: null,

        /** Urutan field tiap langkah, per mode. */
        orders: {
            place: ['location', 'pallet'],
            move: ['pallet', 'location'],
            swap: ['pallet_from', 'pallet_to'],
            verify: ['location', 'pallet']
        },

        labels: {
            place: {
                location: { label: 'QR Lokasi', placeholder: 'mis. B-a-15', button: 'Verifikasi' },
                pallet:   { label: 'QR Pallet', placeholder: 'mis. PLT-0042', button: 'Kunci Pallet' }
            },
            move: {
                pallet:   { label: 'QR Pallet Asal', placeholder: 'mis. PLT-0042', button: 'Verifikasi' },
                location: { label: 'QR Lokasi Tujuan', placeholder: 'mis. B-b-07', button: 'Pindahkan' }
            },
            swap: {
                pallet_from: { label: 'QR Pallet Lama', placeholder: 'mis. PLT-0001', button: 'Verifikasi' },
                pallet_to:   { label: 'QR Pallet Baru', placeholder: 'mis. PLT-0007', button: 'Tukar Pallet' }
            },
            verify: {
                location: { label: 'QR Lokasi', placeholder: 'mis. B-a-15', button: 'Verifikasi' },
                pallet:   { label: 'QR Pallet', placeholder: 'mis. PLT-0042', button: 'Buka Penempatan' }
            }
        },

        init: function (opts) {
            this.onLocked = opts.onLocked || function () {};
            this.onVerified = opts.onVerified || function () {};
            this.csrf = opts.csrf;
            this.routes = opts.routes || {};

            const self = this;

            $('#btnPlacePallet').on('click', function () { self.open('place'); });
            $('#btnMovePallet').on('click', function () { self.open('move'); });
            $('#btnSwapPallet').on('click', function () { self.open('swap'); });

            $('#btnScanStart').on('click', function () { self.startCamera(); });
            $('#btnScanStop').on('click', function () { self.stopCamera(); });
            $('#btnScanRetry').on('click', function () { self.startCamera(); });

            $('#scanStep1Input').on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); self.submitStep1(); }
            });
            $('#scanStep2Input').on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); self.submitStep2(); }
            });
            $('#btnScanStep1').on('click', function () { self.submitStep1(); });
            $('#btnScanStep2').on('click', function () { self.submitStep2(); });

            $('#palletScanModal').on('hidden.bs.modal', function () { self.stopCamera(); });
        },

        /** Jenis field pada langkah ke-n (1/2) untuk mode aktif. */
        fieldOf: function (step) {
            const order = this.orders[this.mode] || this.orders.place;
            return order[step - 1];
        },

        open: function (mode) {
            this.mode = mode;
            this.step = 1;
            this.locationCode = null;
            this.palletCode = null;
            this.oldPalletCode = null;
            this.newPalletCode = null;

            const titles = { place: 'Tempatkan Pallet', move: 'Pindah Pallet', swap: 'Tukar Pallet', verify: 'Verifikasi Pallet & Lokasi' };
            const hints = {
                place: 'Scan QR lokasi yang sedang dibuka, lalu QR pallet yang mau ditempatkan.',
                move: 'Scan QR pallet yang mau dipindah, lalu QR lokasi tujuan yang masih kosong.',
                swap: 'Scan QR pallet lama yang berdiri di lokasi ini, lalu QR pallet baru penggantinya. Isi karton ikut pindah, lokasi tidak berubah.',
                verify: 'Scan QR lokasi yang sedang dibuka, lalu QR pallet yang berdiri di lokasi itu. Setelah cocok, penempatan karton dibuka.'
            };

            $('#scanModalTitle').text(titles[mode] || titles.place);
            $('#scanStepHint').text(hints[mode] || hints.place);

            this.renderSteps();

            $('#scanCameraBox').removeClass('d-none');
            $('#scanStep1').removeClass('d-none');
            $('#scanStep2').addClass('d-none');
            $('#scanStep3').addClass('d-none');
            $('#scanVideo').addClass('d-none');
            $('#scanPlaceholder').removeClass('d-none');
            $('#scanViewfinder').addClass('d-none').removeClass('qr-hit');
            $('#scanGuide').text('Arahkan QR ke dalam kotak. Kode terbaca otomatis.');
            $('#btnScanStart').removeClass('d-none');
            $('#btnScanStop').addClass('d-none');
            $('#btnScanRetry').addClass('d-none');
            $('#btnScanTorch').addClass('d-none').removeClass('btn-dark').addClass('btn-outline-dark');
            $('#scanCamStatus').text('');
            $('#scanAlert').addClass('d-none').text('');

            $('#palletScanModal').one('shown.bs.modal', function () {
                $('#scanStep1Input').focus();
            }).modal('show');
        },

        /** Setel label, placeholder, dan tombol sesuai mode. */
        renderSteps: function () {
            const conf = this.labels[this.mode] || this.labels.place;

            [1, 2].forEach(function (step) {
                const field = window.PalletPlacementFlow.fieldOf(step);
                const c = conf[field];

                $('#scanStep' + step + 'Label').text(c.label);
                $('#scanStep' + step + 'Input').val('')
                    .attr('placeholder', c.placeholder)
                    .removeClass('is-invalid');
                $('#btnScanStep' + step).text(c.button);
                $('#scanStep' + step + 'Error').text('');
            });
        },

        startCamera: function () {
            const self = this;

            if (!window.QrScanner) {
                this.camStatus('Pustaka pemindai QR tidak termuat.', true);
                return;
            }

            this.stopCamera();
            this.camStatus('Meminta izin kamera…');

            const video = document.getElementById('scanVideo');
            video.classList.remove('d-none');
            $('#scanPlaceholder').addClass('d-none');
            $('#scanViewfinder').removeClass('d-none qr-hit');
            $('#scanGuide').text('Arahkan QR ke dalam kotak. Kode terbaca otomatis.');

            this.scanner = new window.QrScanner(video, {
                // Hanya pindai isi kotak bidik, supaya QR tetangga di luar kotak
                // tidak ikut terbaca (mis. PLT-0006 di bawah PLT-0005).
                scanRegion: function () {
                    const vf = document.getElementById('scanViewfinder');
                    const vp = document.querySelector('.qr-viewport');

                    if (!vf || !vp || vf.classList.contains('d-none')) {
                        return null;
                    }

                    const a = vf.getBoundingClientRect();
                    const b = vp.getBoundingClientRect();

                    return { x: a.left - b.left, y: a.top - b.top, w: a.width, h: a.height };
                },
                // Wajib terbaca 2 frame berturut-turut sebelum diterima.
                requiredHits: 2,
                onCandidate: function (value, hits, need) {
                    if (hits < need) {
                        self.camStatus('Membaca ' + value + '… (' + hits + '/' + need + ')');
                    }
                },
                onResult: function (value) { self.onDecoded(value); },
                onError: function (msg) {
                    self.camStatus(msg, true);
                    $('#btnScanStart').addClass('d-none');
                    $('#btnScanStop').addClass('d-none');
                    $('#btnScanRetry').removeClass('d-none');
                    $('#scanViewfinder').addClass('d-none');
                },
                onStatus: function (msg) { self.camStatus(msg); }
            });

            this.scanner.start().then(function () {
                self.torchButton();
            }).catch(function () { /* pesan sudah tampil via onError */ });

            $('#btnScanStart').addClass('d-none');
            $('#btnScanStop').removeClass('d-none');
        },

        /** Tombol senter hanya muncul bila kamera mendukung torch. */
        torchButton: function () {
            const track = this.scanner && this.scanner.stream
                ? this.scanner.stream.getVideoTracks()[0]
                : null;

            if (!track || !track.getCapabilities) return;

            let caps = {};
            try { caps = track.getCapabilities() || {}; } catch (e) { return; }
            if (!caps.torch) return;

            const $btn = $('#btnScanTorch').removeClass('d-none');
            let on = false;

            $btn.off('click').on('click', function () {
                on = !on;
                track.applyConstraints({ advanced: [{ torch: on }] }).catch(function () {});
                $btn.toggleClass('btn-dark', on).toggleClass('btn-outline-dark', !on);
            });
        },

        stopCamera: function () {
            if (this.scanner) {
                this.scanner.stop();
                this.scanner = null;
            }
            const video = document.getElementById('scanVideo');
            if (video) { video.classList.add('d-none'); video.srcObject = null; }
            $('#scanPlaceholder').removeClass('d-none');
            $('#scanViewfinder').addClass('d-none').removeClass('qr-hit');
            $('#btnScanStart').removeClass('d-none');
            $('#btnScanStop').addClass('d-none');
            $('#btnScanTorch').addClass('d-none').removeClass('btn-dark').addClass('btn-outline-dark');
        },

        camStatus: function (msg, isError) {
            const el = document.getElementById('scanCamStatus');
            el.textContent = msg || '';
            el.classList.toggle('text-danger', !!isError);
            el.classList.toggle('text-muted', !isError);
        },

        onDecoded: function (value) {
            if (!value) return;

            // Umpan balik cepat: bingkai hijau + getar singkat.
            $('#scanViewfinder').addClass('qr-hit');
            this.camStatus('Terbaca: ' + value);
            if (navigator.vibrate) { try { navigator.vibrate(60); } catch (e) {} }

            const self = this;
            setTimeout(function () {
                $('#scanStep' + self.step + 'Input').val(value);
                if (self.step === 1) {
                    self.submitStep1();
                } else {
                    self.submitStep2();
                }
            }, 250);
        },

        // ================= LANGKAH 1 =================
        submitStep1: function () {
            const field = this.fieldOf(1);
            const code = $('#scanStep1Input').val().trim();

            if (!code) {
                this.alertBox('Scan QR dulu.');
                return;
            }

            if (field === 'location') {
                // Mode place: QR lokasi wajib sama dengan area yang dibuka.
                const expected = this.expectedLocationCode;

                if (expected && code.toUpperCase() !== expected.toUpperCase()) {
                    $('#scanStep1Input').addClass('is-invalid');
                    $('#scanStep1Error').text('QR lokasi ' + code + ' bukan area yang dipilih (' + expected + ').');
                    this.alertBox('Area yang discan tidak cocok dengan area yang Anda pilih.');
                    return;
                }

                this.locationCode = code;
                this.camStatus('Lokasi terverifikasi: ' + code);
            } else if (field === 'pallet_from') {
                // Mode tukar: langkah pertama = pallet lama.
                this.oldPalletCode = code;
                this.camStatus('Pallet lama terbaca: ' + code);
            } else {
                // Mode pindah: yang discan lebih dulu adalah pallet asal.
                this.palletCode = code;
                this.camStatus('Pallet terbaca: ' + code);
            }

            $('#scanStep1Input').removeClass('is-invalid');
            $('#scanStep1Error').text('');
            this.alertBox('');
            this.step = 2;

            $('#scanStep1').addClass('d-none');
            $('#scanStep2').removeClass('d-none');
            $('#scanStep2Input').focus();

            if (this.scanner && this.scanner.running) { this.scanner.stop(); }
            $('#btnScanStart').removeClass('d-none');
            $('#btnScanStop').addClass('d-none');
        },

        // ================= LANGKAH 2 =================
        submitStep2: function () {
            const self = this;
            const field = this.fieldOf(2);
            const code = $('#scanStep2Input').val().trim();

            if (!code) {
                this.alertBox('Scan QR dulu.');
                return;
            }

            if (field === 'pallet') {
                this.palletCode = code;

                // Mode verifikasi: pallet yang discan harus pallet yang terikat
                // di lokasi ini, bukan pallet lain.
                if (this.mode === 'verify' && this.expectedPalletCode
                    && code.toUpperCase() !== this.expectedPalletCode.toUpperCase()) {
                    $('#scanStep2Input').addClass('is-invalid');
                    $('#scanStep2Error').text('QR pallet ' + code + ' bukan pallet di lokasi ini (' + this.expectedPalletCode + ').');
                    this.alertBox('QR pallet tidak cocok dengan pallet yang terikat di lokasi ini.');
                    return;
                }
            } else if (field === 'pallet_to') {
                this.newPalletCode = code;
            } else {
                this.locationCode = code;
            }

            // Susun payload + validasi kelengkapan sesuai mode.
            const payload = { _token: this.csrf, mode: this.mode };

            if (this.mode === 'swap') {
                if (!this.oldPalletCode || !this.newPalletCode) {
                    this.alertBox('Kedua QR pallet harus discan dulu.');
                    return;
                }
                payload.old_pallet_code = this.oldPalletCode;
                payload.new_pallet_code = this.newPalletCode;

                // Lokasi harus sama dengan area yang sedang dibuka.
                if (this.expectedLocationCode) {
                    payload.expected_location_code = this.expectedLocationCode;
                }
            } else {
                if (!this.locationCode || !this.palletCode) {
                    this.alertBox('Kedua QR harus discan dulu.');
                    return;
                }
                payload.location_code = this.locationCode;
                payload.pallet_code = this.palletCode;
            }

            this.camStatus('Memvalidasi pasangan…');

            // Validasi dulu (tanpa menyimpan), baru jalankan aksinya.
            $.ajax({
                url: this.routes.validate,
                type: 'POST',
                dataType: 'json',
                data: payload,
                headers: { 'Accept': 'application/json' }
            }).done(function () {
                self.alertBox('');

                // Mode verifikasi: tidak ada aksi yang ditulis ke database.
                // Cukup cocokkan QR, lalu buka penempatan karton.
                if (self.mode === 'verify') {
                    self.step = 3;
                    self.stopCamera();

                    $('#scanStep2').addClass('d-none');
                    $('#scanStep1Error, #scanStep2Error').text('');
                    $('#scanStep1Input, #scanStep2Input').removeClass('is-invalid');
                    $('#scanStep3').removeClass('d-none').html(
                        '<div class="alert alert-success py-2 small mb-0">' +
                        '<strong>Terverifikasi.</strong><br>' +
                        'Lokasi <code>' + self.locationCode + '</code> · Pallet <code>' + self.palletCode + '</code>' +
                        '<br>Penempatan karton dibuka.' +
                        '</div>'
                    );

                    if (self.onVerified) {
                        self.onVerified({
                            location_code: self.locationCode,
                            pallet_code: self.palletCode
                        });
                    }

                    $('#palletScanModal').modal('hide');
                    $('#palletDetailModal').modal('show');
                    return;
                }

                // place: pallet -> lokasi | move: pallet ke lokasi tujuan | swap: tukar pallet
                const actionUrl = self.mode === 'move'
                    ? self.routes.move
                    : (self.mode === 'swap' ? self.routes.swap : self.routes.place);

                // Payload aksi: buang 'mode' (hanya dipakai endpoint validasi).
                const actionData = Object.assign({}, payload);
                delete actionData.mode;

                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: actionData,
                    headers: { 'Accept': 'application/json' }
                }).done(function (act) {
                    self.step = 3;
                    self.stopCamera();

                    const headlines = {
                        place: 'Pallet terkunci.',
                        move: 'Pallet dipindah.',
                        swap: 'Pallet ditukar.'
                    };

                    $('#scanStep2').addClass('d-none');
                    $('#scanStep1Error, #scanStep2Error').text('');
                    $('#scanStep1Input, #scanStep2Input').removeClass('is-invalid');
                    $('#scanStep3').removeClass('d-none').html(
                        '<div class="alert alert-success py-2 small mb-0">' +
                        '<strong>' + (headlines[self.mode] || headlines.place) + '</strong><br>' +
                        (act.message ? act.message : '') +
                        '</div>'
                    );

                    // Buka form penempatan karton pada lokasi ini.
                    $('#placementLockedNote').addClass('d-none');
                    $('#formAddCartonToPallet').removeClass('d-none');
                    $('#addCartonPalletId').val(self.expectedAddressId || '');
                    $('#addCartonPalletId').attr('data-location-code', self.expectedLocationCode || self.locationCode);
                    $('#addCartonPalletId').attr('data-pallet-code', self.mode === 'swap' ? self.newPalletCode : self.palletCode);

                    if (self.onLocked) {
                        self.onLocked({
                            location_code: self.expectedLocationCode || self.locationCode,
                            pallet_code: self.mode === 'swap' ? self.newPalletCode : self.palletCode
                        });
                    }

                    $('#palletScanModal').modal('hide');

                    // Muat ulang detail lokasi supaya QR & tombol ikut terbarui.
                    $('#palletDetailModal').modal('hide');
                    setTimeout(function () {
                        if (typeof window.openPalletModal === 'function' && self.expectedAddressId) {
                            window.openPalletModal(self.expectedAddressId);
                        }
                    }, 350);
                }).fail(function (xhr2) {
                    self.showErrors(xhr2, 2);
                });
            }).fail(function (xhr) {
                self.showErrors(xhr, 2);
            });
        },

        /** Tampilkan error per-field dari response 422. */
        showErrors: function (xhr, step) {
            const json = (xhr && xhr.responseJSON) || {};
            const errors = json.errors || {};
            const locMsg = errors.location_code ? errors.location_code[0] : null;
            const palletMsg = errors.pallet_code ? errors.pallet_code[0] : null;
            const oldMsg = errors.old_pallet_code ? errors.old_pallet_code[0] : null;
            const newMsg = errors.new_pallet_code ? errors.new_pallet_code[0] : null;

            // Petakan pesan ke field langkah yang sesuai mode.
            const map = {
                location:    locMsg,
                pallet:      palletMsg,
                pallet_from: oldMsg,
                pallet_to:   newMsg
            };

            [1, 2].forEach(function (n) {
                const msg = map[window.PalletPlacementFlow.fieldOf(n)];

                if (msg) {
                    $('#scanStep' + n + 'Input').addClass('is-invalid');
                    $('#scanStep' + n + 'Error').text(msg);
                }
            });

            if (errors.expected_location_code) {
                this.alertBox(errors.expected_location_code[0]);
                return;
            }

            this.alertBox(json.message || 'Pasangan QR tidak valid.');
        },

        alertBox: function (msg) {
            if (msg) {
                $('#scanAlert').removeClass('d-none').text(msg);
            } else {
                $('#scanAlert').addClass('d-none').text('');
            }
        }
    };
}(jQuery));
</script>
