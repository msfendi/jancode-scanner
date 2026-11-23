/*!
 * Modal Penempatan Pallet 3 langkah:
 *   1. Scan QR Lokasi  -> dicek cocok dengan lokasi yang sedang dibuka
 *   2. Scan QR Pallet  -> pallet di-lock ke lokasi tersebut
 *   3. Pilih item hasil scan -> tempatkan karton
 *
 * Kamera memakai QrScanner (BarcodeDetector native / jsQR fallback).
 */
<script>
(function ($) {
    'use strict';

    window.PalletPlacementFlow = {
        step: 0,          // 0 = belum mulai, 1 = lokasi, 2 = pallet, 3 = siap pilih item
        locationCode: null,
        palletCode: null,
        scanner: null,
        onLocked: null,   // callback({ location_code, pallet_code })

        init: function (opts) {
            this.onLocked = opts.onLocked || function () {};
            this.csrf = opts.csrf;
            this.routes = opts.routes || {};

            const self = this;

            $('#btnPlacePallet').on('click', function () { self.open('place'); });
            $('#btnMovePallet').on('click', function () { self.open('move'); });

            $('#btnScanStart').on('click', function () { self.startCamera(); });
            $('#btnScanStop').on('click', function () { self.stopCamera(); });
            $('#btnScanRetry').on('click', function () { self.startCamera(); });

            $('#scanManualLocation').on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); self.submitLocation(); }
            });
            $('#scanManualPallet').on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); self.submitPallet(); }
            });
            $('#btnScanSubmitLocation').on('click', function () { self.submitLocation(); });
            $('#btnScanSubmitPallet').on('click', function () { self.submitPallet(); });

            $('#palletScanModal').on('hidden.bs.modal', function () { self.stopCamera(); });
        },

        open: function (mode) {
            const self = this;
            this.mode = mode;
            this.step = 1;
            this.locationCode = null;
            this.palletCode = null;

            $('#scanModalTitle').text(mode === 'place' ? 'Tempatkan Pallet' : 'Pindah Pallet');
            $('#scanStepLocation').text(mode === 'place'
                ? 'Scan QR lokasi yang sedang dibuka, lalu scan QR pallet.'
                : 'Scan QR pallet yang mau dipindah, lalu scan QR lokasi tujuan.');
            $('#scanStepHint').text(mode === 'place'
                ? 'Pastikan QR lokasi yang discan sama dengan area yang Anda pilih di board.'
                : 'Pastikan QR lokasi tujuan adalah lokasi tempat pallet akan diletakkan.');

            $('#scanLocationCode').val('').removeClass('is-invalid');
            $('#scanPalletCode').val('').removeClass('is-invalid');
            $('#errLocationCode, #errPalletCode').text('');
            $('#scanAlert').addClass('d-none').text('');
            $('#scanCameraBox').removeClass('d-none');
            $('#scanStep1').removeClass('d-none');
            $('#scanStep2').addClass('d-none');
            $('#scanStep3').addClass('d-none');
            $('#scanVideo').addClass('d-none');
            $('#btnScanStart').removeClass('d-none');
            $('#btnScanStop').addClass('d-none');
            $('#btnScanRetry').addClass('d-none');
            $('#scanCamStatus').text('');
            $('#scanManualLocation').focus();

            $('#palletScanModal').one('shown.bs.modal', function () {
                $('#scanManualLocation').focus();
            }).modal('show');
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

            this.scanner = new window.QrScanner(video, {
                onResult: function (value) { self.onDecoded(value); },
                onError: function (msg) {
                    self.camStatus(msg, true);
                    $('#btnScanStart').addClass('d-none');
                    $('#btnScanStop').addClass('d-none');
                    $('#btnScanRetry').removeClass('d-none');
                },
                onStatus: function (msg) { self.camStatus(msg); }
            });

            this.scanner.start().catch(function () { /* pesan sudah tampil via onError */ });
            $('#btnScanStart').addClass('d-none');
            $('#btnScanStop').removeClass('d-none');
        },

        stopCamera: function () {
            if (this.scanner) {
                this.scanner.stop();
                this.scanner = null;
            }
            const video = document.getElementById('scanVideo');
            if (video) { video.classList.add('d-none'); video.srcObject = null; }
            $('#btnScanStart').removeClass('d-none');
            $('#btnScanStop').addClass('d-none');
        },

        camStatus: function (msg, isError) {
            const el = document.getElementById('scanCamStatus');
            el.textContent = msg || '';
            el.classList.toggle('text-danger', !!isError);
            el.classList.toggle('text-muted', !isError);
        },

        onDecoded: function (value) {
            if (!value) return;

            if (this.step === 1) {
                $('#scanLocationCode').val(value);
                this.submitLocation();
            } else if (this.step === 2) {
                $('#scanPalletCode').val(value);
                this.submitPallet();
            }
        },

        submitLocation: function () {
            const self = this;
            const code = $('#scanLocationCode').val().trim();

            if (!code) {
                this.alertBox('Scan QR lokasi dulu.');
                return;
            }

            // Cek lokal: harus sama dengan lokasi yang sedang dibuka di board.
            const expected = this.expectedLocationCode;
            if (expected && code.toUpperCase() !== expected.toUpperCase()) {
                $('#scanLocationCode').addClass('is-invalid');
                $('#errLocationCode').text('QR lokasi ' + code + ' bukan area yang dipilih (' + expected + ').');
                this.alertBox('Area yang discan tidak cocok dengan area yang Anda pilih.');
                return;
            }

            $('#scanLocationCode').removeClass('is-invalid');
            $('#errLocationCode').text('');
            this.alertBox('');
            this.locationCode = code;
            this.step = 2;

            $('#scanStep1').addClass('d-none');
            $('#scanStep2').removeClass('d-none');
            $('#scanManualPallet').focus();
            this.camStatus('Lokasi terverifikasi: ' + code);

            if (this.scanner && this.scanner.running) { this.scanner.stop(); }
            $('#btnScanStart').removeClass('d-none');
            $('#btnScanStop').addClass('d-none');
        },

        submitPallet: function () {
            const self = this;
            const code = $('#scanPalletCode').val().trim();

            if (!code) {
                this.alertBox('Scan QR pallet dulu.');
                return;
            }

            if (!this.locationCode) {
                this.alertBox('Verifikasi QR lokasi terlebih dahulu.');
                return;
            }

            this.camStatus('Memvalidasi pallet…');

            $.ajax({
                url: this.routes.validate,
                type: 'POST',
                dataType: 'json',
                data: {
                    _token: this.csrf,
                    location_code: this.locationCode,
                    pallet_code: code,
                    mode: this.mode || 'place'
                },
                headers: { 'Accept': 'application/json' }
            }).done(function (res) {
                self.palletCode = code;
                self.alertBox('');

                // Kunci pallet ke lokasi (place) atau pindahkan (move).
                const actionUrl = self.mode === 'move' ? self.routes.move : self.routes.place;
                const payload = {
                    _token: self.csrf,
                    location_code: self.locationCode,
                    pallet_code: code
                };

                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: payload,
                    headers: { 'Accept': 'application/json' }
                }).done(function (act) {
                    self.step = 3;

                    $('#scanStep2').addClass('d-none');
                    $('#scanStep3').removeClass('d-none');
                    $('#scanStep3').html(
                        '<div class="alert alert-success py-2 small mb-0">' +
                        '<strong>Pasangan terverifikasi &amp; terkunci.</strong><br>' +
                        'Lokasi <code>' + self.locationCode + '</code> · Pallet <code>' + code + '</code>' +
                        (act.message ? '<br>' + act.message : '') +
                        '</div>'
                    );
                    self.stopCamera();

                    // Unlock form penempatan karton.
                    $('#placementLockedNote').addClass('d-none');
                    $('#formAddCartonToPallet').removeClass('d-none');
                    $('#addCartonPalletId').val(self.expectedAddressId || '');
                    $('#addCartonPalletId').attr('data-location-code', self.locationCode);
                    $('#addCartonPalletId').attr('data-pallet-code', code);

                    if (self.onLocked) {
                        self.onLocked({ location_code: self.locationCode, pallet_code: code });
                    }

                    $('#palletScanModal').modal('hide');

                    // Muat ulang detail lokasi supaya QR pallet, tombol Pindah &
                    // Finish, serta isi karton langsung sesuai setelah penempatan.
                    $('#palletDetailModal').modal('hide');
                    setTimeout(function () {
                        if (typeof window.openPalletModal === 'function' && self.expectedAddressId) {
                            window.openPalletModal(self.expectedAddressId);
                        }
                    }, 350);
                }).fail(function (xhr2) {
                    const j = xhr2.responseJSON || {};
                    const e2 = j.errors || {};
                    if (e2.pallet_code) {
                        $('#scanPalletCode').addClass('is-invalid');
                        $('#errPalletCode').text(e2.pallet_code[0]);
                    }
                    if (e2.location_code) {
                        $('#scanLocationCode').addClass('is-invalid');
                        $('#errLocationCode').text(e2.location_code[0]);
                    }
                    self.alertBox(j.message || 'Pallet gagal dikunci ke lokasi ini.');
                });
            }).fail(function (xhr) {
                const json = xhr.responseJSON || {};
                const errors = json.errors || {};
                if (errors.pallet_code) {
                    $('#scanPalletCode').addClass('is-invalid');
                    $('#errPalletCode').text(errors.pallet_code[0]);
                }
                if (errors.location_code) {
                    $('#scanLocationCode').addClass('is-invalid');
                    $('#errLocationCode').text(errors.location_code[0]);
                }
                self.alertBox(json.message || 'Pallet tidak bisa dipakai untuk lokasi ini.');
            });
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
