/*!
 * QR Scanner kamera untuk halaman penempatan pallet.
 *
 * Pakai BarcodeDetector native bila tersedia (Chrome/Android), fallback jsQR
 * bila tidak. Keduanya membaca dari <video> + canvas.
 *
 * CATATAN: getUserMedia hanya bisa jalan di secure context (https:// atau
 * localhost). Kalau halaman dibuka lewat http://IP-LAN, kamera tidak akan
 * tersedia dan scanner akan menolak dengan pesan jelas.
 */
(function (global) {
    'use strict';

    var VideoCanvas = global.VideoCanvas;

    function QrScanner(videoElement, options) {
        options = options || {};
        this.video = videoElement;
        this.canvas = document.createElement('canvas');
        this.canvasContext = this.canvas.getContext('2d', { willReadFrequently: true });
        this.stream = null;
        this.running = false;
        this.frameId = null;
        this.detector = null;
        this.useNative = false;
        this.onResult = options.onResult || function () {};
        this.onError = options.onError || function () {};
        this.onStatus = options.onStatus || function () {};
        this._tick = this._tick.bind(this);
    }

    QrScanner.prototype.isSecure = function () {
        return global.isSecureContext === true
            || global.location.protocol === 'https:'
            || global.location.hostname === 'localhost'
            || global.location.hostname === '127.0.0.1';
    };

    QrScanner.prototype.supported = function () {
        return !!(global.navigator && global.navigator.mediaDevices && global.navigator.mediaDevices.getUserMedia);
    };

    QrScanner.prototype.start = function () {
        var self = this;

        if (!this.supported()) {
            this.onError('Browser tidak mendukung akses kamera.');
            return Promise.reject(new Error('unsupported'));
        }

        if (!this.isSecure()) {
            this.onError('Kamera butuh koneksi HTTPS (atau localhost).');
            return Promise.reject(new Error('insecure-context'));
        }

        return global.navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false
        }).then(function (stream) {
            self.stream = stream;
            self.video.srcObject = stream;
            self.video.setAttribute('playsinline', 'true');
            self.video.muted = true;

            return new Promise(function (resolve) {
                if (self.video.readyState >= 2) {
                    resolve();
                } else {
                    self.video.onloadeddata = function () { resolve(); };
                }
            });
        }).then(function () {
            // Autoplay untuk <video muted> tidak selalu jalan (terutama saat
            // elemen baru ditampilkan). Panggil play() eksplisit supaya frame
            // benar-benar mengalir; tanpa ini yang digambar ke canvas kosong.
            var playResult = self.video.play();
            if (playResult && typeof playResult.then === 'function') {
                return playResult.catch(function () { /* dibiarkan: tick tetap jalan */ });
            }
        }).then(function () {
            // Pilih decoder.
            //
            // Di Chrome Android `BarcodeDetector` tersedia dan
            // `getSupportedFormats()` mengembalikan **Promise**. Meneruskan
            // Promise itu sebagai `formats` memicu:
            //   "Failed to read the 'formats' property ... must have a
            //    callable @@iterator property."
            // Jadi hasilnya di-await dulu, dan hanya array yang dipakai.
            //
            // Apa pun yang gagal (format tidak didukung / konstruktor melempar
            // "Failed to construct 'BarcodeDetector'") jatuh ke jsQR — kamera
            // tidak boleh mati hanya karena decoder native bermasalah.
            self.useNative = false;
            self.detector = null;

            var hasNative = global.BarcodeDetector && typeof global.BarcodeDetector === 'function';

            if (!hasNative) {
                if (!global.jsQR) throw new Error('no-decoder');
                self.running = true;
                self.onStatus('Kamera aktif. Arahkan ke QR.');
                self._tick();
                return;
            }

            return Promise.resolve()
                .then(function () {
                    if (typeof global.BarcodeDetector.getSupportedFormats !== 'function') {
                        return ['qr_code'];
                    }
                    return global.BarcodeDetector.getSupportedFormats();
                })
                .then(function (supported) {
                    var formats = Array.isArray(supported) ? supported : ['qr_code'];

                    if (formats.indexOf('qr_code') === -1) {
                        throw new Error('qr_code tidak didukung');
                    }

                    self.detector = new global.BarcodeDetector({ formats: formats });
                    self.useNative = true;
                })
                .catch(function () {
                    self.detector = null;
                    self.useNative = false;

                    if (!global.jsQR) throw new Error('no-decoder');
                })
                .then(function () {
                    self.running = true;
                    self.onStatus('Kamera aktif. Arahkan ke QR.');
                    self._tick();
                });
        }).catch(function (err) {
            if (err && err.name === 'NotAllowedError') {
                self.onError('Izin kamera ditolak.');
            } else if (err && err.name === 'NotFoundError') {
                self.onError('Kamera tidak ditemukan di perangkat ini.');
            } else if (err && err.message === 'no-decoder') {
                self.onError('Pustaka pemindai QR tidak tersedia.');
            } else {
                self.onError('Kamera gagal dibuka: ' + (err && err.message ? err.message : 'tidak diketahui'));
            }
            throw err;
        });
    };

    QrScanner.prototype._tick = function () {
        var self = this;

        if (!this.running) return;

        var w = this.video.videoWidth;
        var h = this.video.videoHeight;

        // Frame pertama sering 0x0 (metadata video belum siap). Jangan
        // diumpankan ke decoder: jsQR melempar "Malformed data passed to
        // binarizer" dan loop pemindaian ikut mati.
        if (!w || !h) {
            this.frameId = global.requestAnimationFrame(this._tick);
            return;
        }

        if (this.useNative) {
            this.detector.detect(this.video)
                .then(function (codes) {
                    if (codes && codes.length && codes[0].rawValue) {
                        self._hit(codes[0].rawValue);
                        return;
                    }
                    self.frameId = global.requestAnimationFrame(self._tick);
                })
                .catch(function () {
                    self.frameId = global.requestAnimationFrame(self._tick);
                });
            return;
        }

        this.canvas.width = w;
        this.canvas.height = h;
        this.canvasContext.drawImage(this.video, 0, 0, w, h);

        try {
            var imageData = this.canvasContext.getImageData(0, 0, w, h);
            // jsQR(data, width, height, options) — width & height wajib.
            var result = global.jsQR(
                imageData.data,
                imageData.width,
                imageData.height,
                { inversionAttempts: 'attemptBoth' }
            );

            if (result && result.data) {
                this._hit(result.data);
                return;
            }
        } catch (e) {
            // Frame buram/terlalu kecil: abaikan, lanjut frame berikutnya.
        }

        this.frameId = global.requestAnimationFrame(this._tick);
    };

    QrScanner.prototype._hit = function (value) {
        this.stop();
        this.onResult(String(value).trim());
    };

    QrScanner.prototype.stop = function () {
        this.running = false;

        if (this.frameId) {
            global.cancelAnimationFrame(this.frameId);
            this.frameId = null;
        }

        if (this.stream) {
            this.stream.getTracks().forEach(function (t) { t.stop(); });
            this.stream = null;
        }

        if (this.video) {
            this.video.srcObject = null;
        }
    };

    global.QrScanner = QrScanner;
}(window));
