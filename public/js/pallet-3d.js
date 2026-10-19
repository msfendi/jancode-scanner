/* =============================================================================
 * pallet-3d.js — Visualisasi 3D layout pallet (Three.js)
 * -----------------------------------------------------------------------------
 * - Membangun gudang 480 posisi (Row B–I, kolom a–d) dari data /pallet/3d-data
 * - Handlift (hand pallet truck) yang berjalan di lorong & mengangkut karton
 * - Animasi karton masuk (in) / keluar (out) dari tiap pallet
 * - Auto-sync: perubahan data nyata (scan / penempatan / karton keluar) dianimasikan
 * ========================================================================== */
window.Pallet3D = (function () {
    'use strict';

    /* ------------------------------- Konfigurasi ---------------------------- */
    const ROWS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
    const COLS = ['a', 'b', 'c', 'd'];

    const BAY = 1.32;                 // jarak antar posisi (sumbu X)
    const LANE = 1.32;                // jarak antar kolom dalam satu blok (sumbu Z)
    const AISLE = 3.4;                // lebar lorong antar baris
    // Blok DEPAN (a & b) dan blok BELAKANG (c & d) dipisah jarak (1 kotak pallet).
    const COL_GAP = LANE;             // jarak kosong antara b dan c  (1 kotak ≈ 1,3 m)
    const COL_OFFSET = [0, LANE, LANE * 2 + COL_GAP, LANE * 3 + COL_GAP];
    const ROW_DEPTH = LANE * 3 + COL_GAP;   // kedalaman total 1 baris (a..d)
    const ROW_PITCH = ROW_DEPTH + AISLE;

    const PALLET_S = 1.15;
    const PALLET_H = 0.14;
    const CARTON_W = 0.34, CARTON_D = 0.5, CARTON_H = 0.33;
    const PER_LAYER = 6;
    const MAX_LAYERS = 7;

    const CORRIDOR_X = -2.6;          // lorong depan (sumbu Z)
    const FRONT_Z = -4.2;             // lorong utama depan (sumbu X)
    const DOCK = { x: -7.5, z: -4.2 };      // area masuk
    // area keluar mengikuti lebar layout (layout.dispatchX)

    const STYLE_COLORS = [
        0xe07a5f, 0x81b29a, 0x6c8ae4, 0xf2cc8f, 0xb07dcc,
        0x57a773, 0xe9c46a, 0x4ea8de, 0xef8354, 0x9b5de5,
        0x00b4d8, 0xc98474, 0x8ab17d, 0xd68fd7, 0x7f9cf5
    ];

    /* --------------------------- State modul (privat) ----------------------- */
    let cfg = { dataUrl: '' };

    let scene, camera, renderer, raycaster, pointerNdc, clock;
    let baseGroup, cartonRoot, labelGroup, markGroup;
    let floor, selRing, hoverRing;
    let truck = null, truckWheels = [], truckPayload = null;

    const records = {};               // code -> { data, base, group, cartons[], label }
    const baseMeshes = [];

    const updaters = new Set();
    let running = false;
    let queue = Promise.resolve();

    const state = {
        labels: true,
        autoRotate: false,
        live: false,
        busy: false,
        autoDemo: false,
        speed: 1
    };

    const serverQty = {};             // qty terakhir versi server (untuk diff)
    const styleColorMap = {};
    let layout = { maxX: 0, maxZ: 0, minZ: 0 };
    let hoverCode = null;
    let selectedCode = null;
    let pointerInside = false;
    let liveTimer = null;

    const orbit = {
        target: new THREE.Vector3(0, 1.2, 0),
        theta: -0.75,
        phi: 0.92,
        radius: 46,
        minRadius: 6,
        maxRadius: 160
    };

    /* ------------------------------- Utilitas ------------------------------- */
    const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
    const easeInOut = t => (t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2);
    const easeOut = t => 1 - Math.pow(1 - t, 3);

    function lerpAngle(a, b, t) {
        let d = ((b - a + Math.PI) % (Math.PI * 2)) - Math.PI;
        if (d < -Math.PI) d += Math.PI * 2;
        return a + d * t;
    }

    function colorForStyle(style) {
        const key = style || '(tanpa style)';
        if (!styleColorMap[key]) {
            const idx = Object.keys(styleColorMap).length % STYLE_COLORS.length;
            styleColorMap[key] = STYLE_COLORS[idx];
        }
        return styleColorMap[key];
    }

    function palletPos(row, col, posNo) {
        const ri = ROWS.indexOf(row);
        const ci = COLS.indexOf(col);
        return new THREE.Vector3((posNo - 1) * BAY, 0, ri * ROW_PITCH + COL_OFFSET[ci]);
    }

    function aisleZForRow(row) {
        const ri = ROWS.indexOf(row);
        if (ri <= 0) return FRONT_Z;
        return ri * ROW_PITCH - AISLE * 0.5 - 0.35;
    }

    /* --------------------- Animasi: tween & update loop --------------------- */
    function animate(duration, onUpdate, ease) {
        return new Promise(resolve => {
            let t = 0;
            const fn = dt => {
                t += dt * state.speed;
                const k = clamp(t / duration, 0, 1);
                onUpdate(ease ? ease(k) : k, dt);
                if (k >= 1) { updaters.delete(fn); resolve(); }
            };
            updaters.add(fn);
        });
    }

    function wait(ms) {
        return new Promise(resolve => {
            let acc = 0;
            const fn = dt => {
                acc += dt * 1000 * state.speed;
                if (acc >= ms) { updaters.delete(fn); resolve(); }
            };
            updaters.add(fn);
        });
    }

    function enqueue(task) {
        queue = queue.then(task).catch(err => console.warn('[3D]', err));
        return queue;
    }

    /* ------------------------------- Inisialisasi --------------------------- */
    function init() {
        const canvas = document.getElementById('threeCanvas');
        const wrap = document.getElementById('threeWrap');

        renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setSize(wrap.clientWidth, wrap.clientHeight, false);
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        scene = new THREE.Scene();
        scene.background = new THREE.Color(0x10131f);
        scene.fog = new THREE.Fog(0x10131f, 95, 260);

        clock = new THREE.Clock();
        raycaster = new THREE.Raycaster();
        pointerNdc = new THREE.Vector2();

        camera = new THREE.PerspectiveCamera(48, wrap.clientWidth / wrap.clientHeight, 0.1, 600);

        // Cahaya
        scene.add(new THREE.HemisphereLight(0xdfe8ff, 0x2a2e3d, 1.05));
        const amb = new THREE.AmbientLight(0xffffff, 0.3);
        scene.add(amb);

        const sun = new THREE.DirectionalLight(0xfff3d6, 1.15);
        sun.position.set(18, 32, -20);
        sun.castShadow = true;
        sun.shadow.mapSize.set(1024, 1024);
        sun.shadow.camera.near = 1;
        sun.shadow.camera.far = 90;
        sun.shadow.camera.left = -14;
        sun.shadow.camera.right = 14;
        sun.shadow.camera.top = 14;
        sun.shadow.camera.bottom = -14;
        scene.add(sun);
        scene.add(sun.target);

        baseGroup = new THREE.Group();
        cartonRoot = new THREE.Group();
        labelGroup = new THREE.Group();
        markGroup = new THREE.Group();
        scene.add(baseGroup, cartonRoot, labelGroup, markGroup);

        // Ring sorot
        const ringGeo = new THREE.RingGeometry(0.72, 0.86, 4);
        selRing = new THREE.Mesh(ringGeo, new THREE.MeshBasicMaterial({
            color: 0xffd166, transparent: true, opacity: 0.95, side: THREE.DoubleSide
        }));
        selRing.rotation.x = -Math.PI / 2;
        selRing.visible = false;
        scene.add(selRing);

        hoverRing = new THREE.Mesh(ringGeo.clone(), new THREE.MeshBasicMaterial({
            color: 0xffffff, transparent: true, opacity: 0.5, side: THREE.DoubleSide
        }));
        hoverRing.rotation.x = -Math.PI / 2;
        hoverRing.visible = false;
        scene.add(hoverRing);

        truck = buildTruck();
        truck.position.set(DOCK.x, 0, DOCK.z);
        truck.rotation.y = Math.PI / 2;
        truckWheels = truck.userData.wheels;
        truckPayload = truck.userData.payload;
        scene.add(truck);

        bindUI();
        bindPointer(canvas);
        window.addEventListener('resize', onResize);
        onResize();
    }

    function onResize() {
        const wrap = document.getElementById('threeWrap');
        if (!wrap || !renderer) return;
        const w = wrap.clientWidth, h = wrap.clientHeight;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }

    /* -------------------------------- Lantai -------------------------------- */
    function buildFloor(minX, maxX, minZ, maxZ) {
        if (floor) { scene.remove(floor); floor.geometry.dispose(); }
        const padX = 12, padZ = 9;
        const w = (maxX - minX) + padX * 2;
        const d = (maxZ - minZ) + padZ * 2;
        const cx = (minX + maxX) / 2;
        const cz = (minZ + maxZ) / 2;

        const geo = new THREE.PlaneGeometry(w, d);
        floor = new THREE.Mesh(geo, new THREE.MeshStandardMaterial({
            color: 0x2c3145, roughness: 0.95, metalness: 0.02
        }));
        floor.rotation.x = -Math.PI / 2;
        floor.position.set(cx, 0, cz);
        floor.receiveShadow = true;
        scene.add(floor);

        // Grid lantai
        const grid = new THREE.GridHelper(Math.max(w, d), Math.round(Math.max(w, d) / 2), 0x39405c, 0x2a2f45);
        grid.position.set(cx, 0.012, cz);
        scene.add(grid);

        // Zona dock & dispatch
        addZone('AREA MASUK', DOCK.x, DOCK.z, 0x2f9e6f);
        addZone('AREA KELUAR', layout.dispatchX, DOCK.z, 0xc0612a);
    }

    function addZone(text, x, z, color) {
        const pad = new THREE.Mesh(
            new THREE.PlaneGeometry(5.2, 4.4),
            new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.16, side: THREE.DoubleSide })
        );
        pad.rotation.x = -Math.PI / 2;
        pad.position.set(x, 0.02, z);
        scene.add(pad);

        const sprite = makeTextSprite(text, { bg: 'rgba(0,0,0,0)', color: '#' + color.toString(16).padStart(6, '0'), font: 'bold 34px Nunito, sans-serif' });
        sprite.position.set(x, 1.6, z);
        sprite.scale.set(4.4, 1.1, 1);
        scene.add(sprite);
    }

    /* ------------------------------ Text sprite ----------------------------- */
    function makeTextSprite(text, opts) {
        opts = opts || {};
        return makeSprite([{ t: text, size: opts.font || 'bold 30px Nunito, sans-serif', color: opts.color || '#ffffff' }], opts);
    }

    function makeSprite(lines, opts) {
        opts = opts || {};
        const pad = 10;
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const fonts = lines.map(l => l.size);
        const lineH = 34;
        canvas.width = 320;
        canvas.height = Math.max(48, lines.length * lineH + pad * 2);
        ctx.font = fonts[0];
        let maxW = 0;
        lines.forEach((l, i) => {
            ctx.font = l.size;
            maxW = Math.max(maxW, ctx.measureText(l.t).width);
        });
        canvas.width = Math.min(1024, Math.ceil(maxW + pad * 2));
        const c2 = canvas.getContext('2d');
        c2.clearRect(0, 0, canvas.width, canvas.height);
        if (opts.bg) {
            c2.fillStyle = opts.bg;
            roundRect(c2, 0, 0, canvas.width, canvas.height, 10);
            c2.fill();
        }
        lines.forEach((l, i) => {
            c2.font = l.size;
            c2.fillStyle = l.color;
            c2.textAlign = 'center';
            c2.textBaseline = 'top';
            c2.fillText(l.t, canvas.width / 2, pad + i * lineH + 4);
        });
        const tex = new THREE.CanvasTexture(canvas);
        tex.minFilter = THREE.LinearFilter;
        const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthWrite: false });
        const sprite = new THREE.Sprite(mat);
        const scale = opts.scale || 1;
        sprite.scale.set((canvas.width / 100) * scale, (canvas.height / 100) * scale, 1);
        sprite.userData.text = lines.map(l => l.t).join(' | ');
        return sprite;
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    /* -------------------------------- Handlift ------------------------------ */
    function buildTruck() {
        const g = new THREE.Group();
        const steel = new THREE.MeshStandardMaterial({ color: 0xf0a500, metalness: 0.45, roughness: 0.45 });
        const dark = new THREE.MeshStandardMaterial({ color: 0x3a3f4d, metalness: 0.5, roughness: 0.55 });
        const rubber = new THREE.MeshStandardMaterial({ color: 0x15171d, roughness: 0.95 });

        [-0.26, 0.26].forEach(sx => {
            const fork = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.07, 1.05), steel);
            fork.position.set(sx, 0.1, 0.52);
            fork.castShadow = true;
            g.add(fork);
            const tip = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.05, 0.14), dark);
            tip.position.set(sx, 0.05, 1.06);
            g.add(tip);
        });

        const chassis = new THREE.Mesh(new THREE.BoxGeometry(0.7, 0.42, 0.56), steel);
        chassis.position.set(0, 0.3, -0.34);
        chassis.castShadow = true;
        g.add(chassis);

        const mast = new THREE.Mesh(new THREE.BoxGeometry(0.15, 1.1, 0.15), dark);
        mast.position.set(0, 0.78, -0.5);
        mast.castShadow = true;
        g.add(mast);

        const handle = new THREE.Mesh(new THREE.BoxGeometry(0.54, 0.08, 0.08), dark);
        handle.position.set(0, 1.26, -0.6);
        handle.rotation.x = 0.32;
        g.add(handle);

        const wheels = [];
        const addWheel = (r, wdt, x, z) => {
            const holder = new THREE.Group();
            holder.position.set(x, r, z);
            const wheel = new THREE.Mesh(new THREE.CylinderGeometry(r, r, wdt, 16), rubber);
            wheel.rotation.z = Math.PI / 2;
            wheel.castShadow = true;
            holder.add(wheel);
            g.add(holder);
            wheels.push(holder);
        };
        addWheel(0.13, 0.1, -0.31, -0.5);
        addWheel(0.13, 0.1, 0.31, -0.5);
        addWheel(0.085, 0.08, -0.26, 0.92);
        addWheel(0.085, 0.08, 0.26, 0.92);

        const payload = new THREE.Group();
        payload.position.set(0, 0.13, 0.55);
        g.add(payload);

        g.userData = { wheels, payload };
        return g;
    }

    /* --------------------------------- Pallets ------------------------------ */
    function buildPallet(rec) {
        const { x, z } = rec.pos3;

        const base = new THREE.Mesh(
            new THREE.BoxGeometry(PALLET_S, PALLET_H, PALLET_S),
            new THREE.MeshStandardMaterial({ color: 0x9a7b4f, roughness: 0.9 })
        );
        base.position.set(x, PALLET_H / 2, z);
        base.receiveShadow = true;
        base.userData.code = rec.data.code;
        baseGroup.add(base);
        baseMeshes.push(base);

        // Kaki pallet (kesan wooden pallet)
        const feet = new THREE.Mesh(
            new THREE.BoxGeometry(PALLET_S * 0.92, 0.05, PALLET_S * 0.92),
            new THREE.MeshStandardMaterial({ color: 0x6f5735, roughness: 0.95 })
        );
        feet.position.set(x, 0.025, z);
        baseGroup.add(feet);

        const group = new THREE.Group();
        group.position.set(x, 0, z);
        cartonRoot.add(group);

        rec.base = base;
        rec.group = group;
        rec.cartons = [];
    }

    function cartonSlot(i, y0) {
        const layer = Math.floor(i / PER_LAYER);
        const k = i % PER_LAYER;
        const cx = (k % 3) - 1;
        const cz = Math.floor(k / 3);
        return new THREE.Vector3(
            cx * CARTON_W,
            (y0 || PALLET_H) + CARTON_H / 2 + layer * CARTON_H,
            (cz - 0.5) * CARTON_D
        );
    }

    function cartonMaterial(style) {
        const c = colorForStyle(style);
        return new THREE.MeshStandardMaterial({ color: c, roughness: 0.78, metalness: 0.04 });
    }

    function addCarton(rec, index) {
        const mesh = new THREE.Mesh(
            new THREE.BoxGeometry(CARTON_W * 0.96, CARTON_H * 0.96, CARTON_D * 0.96),
            cartonMaterial(rec.data.style)
        );
        mesh.position.copy(cartonSlot(index));
        mesh.castShadow = false;
        mesh.userData.code = rec.data.code;
        rec.group.add(mesh);
        rec.cartons.push(mesh);
        return mesh;
    }

    function setPalletQty(rec, qty) {
        qty = Math.max(0, Math.min(qty, MAX_LAYERS * PER_LAYER));
        while (rec.cartons.length > qty) {
            const m = rec.cartons.pop();
            rec.group.remove(m);
            m.geometry.dispose();
        }
        while (rec.cartons.length < qty) {
            addCarton(rec, rec.cartons.length);
        }
        if (rec.data) rec.data.qty = rec.cartons.length;
        updateLabel(rec);
    }

    function updateLabel(rec) {
        if (rec.label) {
            labelGroup.remove(rec.label);
            rec.label.material.map.dispose();
            rec.label.material.dispose();
            rec.label = null;
        }
        if (!state.labels || rec.data.qty <= 0) return;

        const ls = Math.max(0, rec.cartons.length - 1);
        const y = PALLET_H + (ls + 1) * CARTON_H + 0.42;
        const sprite = makeSprite([
            { t: rec.data.code, size: 'bold 26px Nunito, sans-serif', color: '#ffffff' },
            { t: rec.data.qty + ' karton', size: '20px Nunito, sans-serif', color: '#ffd166' }
        ], { bg: 'rgba(12,15,26,0.82)', scale: 0.62 });
        sprite.position.set(rec.pos3.x, y, rec.pos3.z);
        labelGroup.add(sprite);
        rec.label = sprite;
    }

    /* --------------------------- Muat & sinkron data ------------------------ */
    function fetchData() {
        return fetch(cfg.dataUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(r => r.json());
    }

    function boot(options) {
        cfg.dataUrl = (options && options.dataUrl) || '/pallet/3d-data';
        init();

        fetchData().then(data => {
            buildAll(data);
            refreshStats(data.stats);
            document.getElementById('threeLoading').style.display = 'none';
            startLoop();
            fitCameraToContent(true);
        }).catch(err => {
            console.error(err);
            const el = document.getElementById('threeLoading');
            if (el) el.innerHTML = '<div class="text-danger">Gagal memuat data: ' + err.message + '</div>';
        });
    }

    function buildAll(data) {
        // Bersihkan
        baseMeshes.length = 0;
        Object.keys(records).forEach(k => delete records[k]);
        [baseGroup, cartonRoot, labelGroup, markGroup].forEach(g => {
            while (g.children.length) g.remove(g.children[0]);
        });

        let maxX = 0, maxZ = 0, minZ = Infinity;

        data.pallets.forEach(p => {
            const pos3 = palletPos(p.row, p.col, p.pos);
            const rec = { data: p, pos3, cartons: [], label: null };
            records[p.code] = rec;
            buildPallet(rec);
            maxX = Math.max(maxX, pos3.x);
            maxZ = Math.max(maxZ, pos3.z);
            minZ = Math.min(minZ, pos3.z);
        });

        Object.keys(records).forEach(code => {
            const rec = records[code];
            setPalletQty(rec, rec.data.qty);
            serverQty[code] = rec.data.qty;
        });

        layout = { maxX, maxZ, minZ, dispatchX: maxX + 6.5 };
        floor && null;
        buildFloor(-1, maxX, minZ, maxZ);

        orbit.target.set(maxX / 2, 1.2, (minZ + maxZ) / 2);

        // Legenda
        buildLegend();

        // Baris label + penanda blok DEPAN (a,b) / BELAKANG (c,d)
        const stripeW = (layout.maxX || BAY * 15) + BAY * 1.4;
        const stripeX = (layout.maxX || 0) / 2;
        ROWS.forEach((r, i) => {
            const zRow = i * ROW_PITCH;

            const sprite = makeTextSprite('ROW ' + r, { color: '#8fa0c8', font: 'bold 30px Nunito, sans-serif', scale: 0.7 });
            sprite.position.set(-2.6, 2.2, zRow + ROW_DEPTH / 2);
            markGroup.add(sprite);

            // garis pemisah jarak antara blok depan (a,b) dan belakang (c,d)
            const stripe = new THREE.Mesh(
                new THREE.PlaneGeometry(stripeW, COL_GAP * 0.9),
                new THREE.MeshBasicMaterial({ color: 0x7f8dbb, transparent: true, opacity: 0.13, side: THREE.DoubleSide })
            );
            stripe.rotation.x = -Math.PI / 2;
            stripe.position.set(stripeX, 0.022, zRow + (COL_OFFSET[1] + COL_OFFSET[2]) / 2);
            markGroup.add(stripe);

            if (i === 0) {
                const front = makeTextSprite('DEPAN (a,b)', { color: '#7fd1b0', font: 'bold 28px Nunito, sans-serif', scale: 0.62 });
                front.position.set(-2.6, 1.1, zRow + (COL_OFFSET[0] + COL_OFFSET[1]) / 2);
                markGroup.add(front);

                const back = makeTextSprite('BELAKANG (c,d)', { color: '#d3b24a', font: 'bold 28px Nunito, sans-serif', scale: 0.62 });
                back.position.set(-2.6, 1.1, zRow + (COL_OFFSET[2] + COL_OFFSET[3]) / 2);
                markGroup.add(back);
            }
        });
    }

    function buildLegend() {
        const body = document.getElementById('legendBody');
        const styles = {};
        Object.values(records).forEach(rec => {
            if (rec.data.qty <= 0) return;
            const key = rec.data.style || '(tanpa style)';
            if (!styles[key]) styles[key] = { qty: 0, pallets: 0 };
            styles[key].qty += rec.data.qty;
            styles[key].pallets++;
        });
        const keys = Object.keys(styles);
        if (!keys.length) {
            body.innerHTML = '<span class="text-muted small">Belum ada pallet terisi.</span>';
            return;
        }
        body.innerHTML = keys.map(k => {
            const col = '#' + colorForStyle(k).toString(16).padStart(6, '0');
            return `<div class="lg-item"><span class="sw" style="background:${col}"></span>
                    <span class="lbl" title="${k}">${k} &middot; ${styles[k].qty} ktn / ${styles[k].pallets} plt</span></div>`;
        }).join('');
    }

    function refreshStats(stats) {
        if (!stats) return;
        document.getElementById('stCartons').textContent = stats.cartons;
        document.getElementById('stOccupied').textContent = stats.occupied;
        document.getElementById('stVacant').textContent = stats.vacant;
        document.getElementById('stPositions').textContent = stats.positions;
        document.getElementById('stCapacity').textContent = stats.capacity;
    }

    /* ------------------------------ Auto-sync ------------------------------- */
    function syncNow(animateChanges) {
        return fetchData().then(data => {
            refreshStats(data.stats);
            document.getElementById('syncTs').textContent = 'Terakhir sync: ' + (data.updated_at || nowText());

            const changes = [];
            data.pallets.forEach(p => {
                const prev = serverQty[p.code];
                if (prev === undefined) { serverQty[p.code] = p.qty; return; }
                if (prev !== p.qty) { changes.push({ code: p.code, from: prev, to: p.qty }); }
                serverQty[p.code] = p.qty;
                const rec = records[p.code];
                if (rec) { rec.data = p; }
            });

            buildLegend();

            if (!changes.length) return;

            if (!animateChanges) {
                changes.forEach(ch => {
                    const rec = records[ch.code];
                    if (rec) setPalletQty(rec, ch.to);
                });
                return;
            }

            changes.forEach(ch => {
                enqueue(() => animateChange(ch));
            });
        }).catch(err => console.warn('[3D sync]', err));
    }

    function animateChange(ch) {
        const rec = records[ch.code];
        if (!rec) return;
        const delta = ch.to - ch.from;
        if (delta < 0) {
            return runOut(rec, Math.abs(delta), { label: 'Karton keluar: ' + rec.data.code });
        }
        return runIn(rec, delta, { label: 'Karton masuk: ' + rec.data.code });
    }

    /* ------------------------- Urutan animasi handlift ----------------------- */
    function truckBadge(text) {
        const el = document.getElementById('truckBadge');
        if (!text) { el.style.display = 'none'; return; }
        el.textContent = text;
        el.style.display = 'block';
    }

    function driveTo(x, z, speed) {
        speed = speed || 6.5;
        return new Promise(resolve => {
            const fn = dt => {
                const dx = x - truck.position.x;
                const dz = z - truck.position.z;
                const dist = Math.hypot(dx, dz);
                const step = speed * dt * state.speed;
                if (dist > 0.0001) {
                    const targetRot = Math.atan2(dx, dz);
                    truck.rotation.y = lerpAngle(truck.rotation.y, targetRot, Math.min(1, dt * 7));
                }
                if (dist <= step) {
                    truck.position.x = x;
                    truck.position.z = z;
                    updaters.delete(fn);
                    resolve();
                    return;
                }
                const mx = (dx / dist) * step;
                const mz = (dz / dist) * step;
                truck.position.x += mx;
                truck.position.z += mz;
                truckWheels.forEach((w, i) => { w.rotation.x += (i < 2 ? 1 : 0.6) * (mz + mx) * 1.6; });
                truck.rotation.y = lerpAngle(truck.rotation.y, Math.atan2(dx, dz), Math.min(1, dt * 7));
            };
            updaters.add(fn);
        });
    }

    function faceTo(angle, ms) {
        return animate((ms || 520) / 1000, k => {
            truck.rotation.y = lerpAngle(truck.rotation.y, angle, k);
        }, t => t);
    }

    function routeToPallet(rec, speed) {
        const aisleZ = aisleZForRow(rec.data.row);
        return driveTo(CORRIDOR_X, truck.position.z, speed)
            .then(() => driveTo(CORRIDOR_X, aisleZ, speed))
            .then(() => driveTo(rec.pos3.x, aisleZ, speed));
    }

    function routeToArea(x, z, speed) {
        return driveTo(CORRIDOR_X, truck.position.z, speed)
            .then(() => driveTo(CORRIDOR_X, z, speed))
            .then(() => driveTo(x, z, speed));
    }

    /* Karton dari pallet → atas handlift */
    function loadFromPallet(rec, count) {
        const take = Math.min(count, rec.cartons.length);
        if (!take) return Promise.resolve(0);
        const jobs = [];
        for (let i = 0; i < take; i++) {
            const mesh = rec.cartons[rec.cartons.length - 1];
            rec.cartons.pop();
            reparentKeepLocal(mesh, truckPayload);
            const target = payloadSlot(i);
            const from = mesh.position.clone();
            const startDelay = i * 80;
            jobs.push(wait(startDelay).then(() => animate(0.45, k => {
                mesh.position.lerpVectors(from, target, k);
                mesh.position.y += Math.sin(Math.PI * k) * (0.8 + i * 0.05);
                mesh.rotation.y = k * Math.PI * 2;
            }, easeInOut)).then(() => { mesh.position.copy(target); mesh.rotation.set(0, 0, 0); }));
        }
        return Promise.all(jobs).then(() => take);
    }

    /* Karton baru dari area masuk (dock) → atas handlift */
    function loadFromDock(count, style) {
        if (count <= 0) return Promise.resolve(0);
        truck.updateMatrixWorld(true);
        const jobs = [];
        for (let i = 0; i < count; i++) {
            const mesh = new THREE.Mesh(
                new THREE.BoxGeometry(CARTON_W * 0.96, CARTON_H * 0.96, CARTON_D * 0.96),
                cartonMaterial(style)
            );
            const dockPos = new THREE.Vector3(
                DOCK.x - 1.4 + (i % 4) * 0.42,
                0.17 + Math.floor(i / 8) * CARTON_H,
                DOCK.z + 2.4 + Math.floor((i % 8) / 4) * 0.55
            );
            mesh.position.copy(dockPos);
            scene.add(mesh);

            const targetLocal = payloadSlot(i);
            const targetWorld = truckPayload.localToWorld(targetLocal.clone());
            const from = dockPos.clone();
            jobs.push(wait(i * 70).then(() => animate(0.45, k => {
                mesh.position.lerpVectors(from, targetWorld, k);
                mesh.position.y += Math.sin(Math.PI * k) * 1.1;
                mesh.rotation.y = k * Math.PI * 2;
            }, easeInOut)).then(() => {
                reparentKeepLocal(mesh, truckPayload);
                mesh.position.copy(targetLocal);
                mesh.rotation.set(0, 0, 0);
            }));
        }
        return Promise.all(jobs).then(() => count);
    }

    /* Karton dari handlift → pallet */
    function unloadToPallet(rec, count) {
        const take = Math.min(count, truckPayload.children.length);
        if (!take) return Promise.resolve(0);
        const jobs = [];
        for (let i = 0; i < take; i++) {
            const mesh = truckPayload.children[truckPayload.children.length - 1];
            const idx = rec.cartons.length;
            reparentKeepLocal(mesh, rec.group);
            const target = cartonSlot(idx);
            const from = mesh.position.clone();
            rec.cartons.push(mesh);
            jobs.push(wait(i * 80).then(() => animate(0.45, k => {
                mesh.position.lerpVectors(from, target, k);
                mesh.position.y += Math.sin(Math.PI * k) * 0.9;
                mesh.rotation.y = k * Math.PI * 2;
            }, easeInOut)).then(() => { mesh.position.copy(target); mesh.rotation.set(0, 0, 0); }));
        }
        return Promise.all(jobs).then(() => { setPalletQty(rec, rec.cartons.length); return take; });
    }

    /* Karton dari handlift → area keluar, lalu hilang (karton keluar gudang) */
    function unloadToDispatch(count) {
        const take = Math.min(count, truckPayload.children.length);
        if (!take) return Promise.resolve(0);
        truck.updateMatrixWorld(true);
        const jobs = [];
        for (let i = 0; i < take; i++) {
            const mesh = truckPayload.children[truckPayload.children.length - 1];
            const world = new THREE.Vector3();
            mesh.getWorldPosition(world);
            scene.add(mesh);
            mesh.position.copy(world);

            const to = new THREE.Vector3(
                layout.dispatchX - 2.0 + (i % 5) * 0.5,
                0.18 + Math.floor(i / 10) * CARTON_H,
                DOCK.z + 1.6 + Math.floor((i % 10) / 5) * 0.55
            );
            const from = world.clone();
            jobs.push(wait(i * 60).then(() => animate(0.4, k => {
                mesh.position.lerpVectors(from, to, k);
                mesh.position.y += Math.sin(Math.PI * k) * 0.9;
            }, easeInOut)).then(() => animate(0.5, k => {
                if (mesh.material) {
                    mesh.material.transparent = true;
                    mesh.material.opacity = 1 - k;
                }
                mesh.position.y = to.y + k * 0.35;
            }, t => t)).then(() => {
                scene.remove(mesh);
                mesh.geometry.dispose();
                if (mesh.material) mesh.material.dispose();
            }));
        }
        return Promise.all(jobs).then(() => take);
    }

    function payloadSlot(i) {
        const layer = Math.floor(i / PER_LAYER);
        const k = i % PER_LAYER;
        const cx = (k % 3) - 1;
        const cz = Math.floor(k / 3);
        return new THREE.Vector3(cx * CARTON_W, CARTON_H / 2 + layer * CARTON_H, (cz - 0.5) * CARTON_D);
    }

    function reparentKeepLocal(obj, newParent) {
        const world = new THREE.Vector3();
        obj.getWorldPosition(world);
        newParent.add(obj);
        newParent.updateWorldMatrix(true, false);
        newParent.worldToLocal(world);
        obj.position.copy(world);
        obj.rotation.set(0, 0, 0);
    }

    /* Satu siklus: karton KELUAR dari pallet → dibawa ke area keluar */
    function runOut(rec, qty, opts) {
        opts = opts || {};
        if (!rec || !rec.cartons.length) return Promise.resolve();
        qty = Math.min(qty || rec.cartons.length, rec.cartons.length);

        truckBadge(opts.label || ('Mengambil karton dari ' + rec.data.code));
        const aisleZ = aisleZForRow(rec.data.row);

        return routeToPallet(rec, 7.5)
            .then(() => faceTo(0, 520))                       // hadap +Z (ke pallet)
            .then(() => loadFromPallet(rec, qty))
            .then(() => setPalletQty(rec, rec.cartons.length))
            .then(() => faceTo(Math.PI / 2, 420))
            .then(() => driveTo(rec.pos3.x, aisleZ, 6))
            .then(() => routeToArea(layout.dispatchX, DOCK.z, 7.5))
            .then(() => faceTo(0, 420))
            .then(() => unloadToDispatch(999))
            .then(() => {
                if (rec.data) rec.data.qty = rec.cartons.length;
                truckBadge(null);
            });
    }

    /* Satu siklus: karton MASUK ke pallet dari area masuk */
    function runIn(rec, qty, opts) {
        opts = opts || {};
        if (!rec) return Promise.resolve();
        qty = qty || PER_LAYER;

        truckBadge(opts.label || ('Menempatkan karton ke ' + rec.data.code));
        const aisleZ = aisleZForRow(rec.data.row);

        return driveTo(DOCK.x, DOCK.z, 7.5)
            .then(() => faceTo(0, 400))
            .then(() => loadFromDock(qty, rec.data.style))
            .then(() => faceTo(Math.PI / 2, 400))
            .then(() => routeToPallet(rec, 7.5))
            .then(() => faceTo(0, 520))
            .then(() => unloadToPallet(rec, 999))
            .then(() => {
                if (rec.data) rec.data.qty = rec.cartons.length;
                truckBadge(null);
            });
    }

    function occupiedRecords(limit) {
        return Object.values(records).filter(r => r.cartons.length > 0).slice(0, limit || 4);
    }

    function emptyRecords(limit) {
        return Object.values(records).filter(r => r.cartons.length === 0).slice(0, limit || 3);
    }

    function chainRun(list) {
        let p = Promise.resolve();
        list.forEach(item => { p = p.then(item); });
        return p;
    }

    /* Demo: karton keluar dari pallet → area keluar, lalu tampilan dikembalikan */
    function dispatchDemoOut() {
        if (state.busy) return;
        const targets = occupiedRecords(4);
        if (!targets.length) {
            toast('Tidak ada pallet terisi untuk diangkut.');
            return;
        }

        const plan = targets.map(rec => ({ rec, qty: rec.cartons.length }));
        state.busy = true;

        enqueue(() => {
            return chainRun(plan.map(t => () => runOut(t.rec, t.qty, { label: 'Ambil karton dari ' + t.rec.data.code })))
                .then(() => { toast('Karton keluar selesai — mengembalikan tampilan…'); return wait(1400); })
                .then(() => chainRun(plan.map(t => () => runIn(t.rec, t.qty, { label: 'Kembalikan karton ke ' + t.rec.data.code }))))
                .then(() => { state.busy = false; toast('Simulasi karton keluar selesai.'); });
        });
    }

    /* Demo: karton masuk ke pallet kosong */
    function dispatchDemoIn() {
        if (state.busy) return;
        const targets = emptyRecords(3);
        if (!targets.length) {
            toast('Tidak ada pallet kosong untuk ditempati.');
            return;
        }
        const plan = targets.map(rec => ({ rec, qty: PER_LAYER }));
        state.busy = true;

        enqueue(() => {
            return chainRun(plan.map(t => () => runIn(t.rec, t.qty, { label: 'Karton masuk ke ' + t.rec.data.code })))
                .then(() => { state.busy = false; toast('Simulasi karton masuk selesai.'); });
        });
    }

    /* Putar simulasi otomatis (keluar → masuk, berulang) */
    function dispatchDemoAuto() {
        state.autoDemo = !state.autoDemo;
        const btn = document.getElementById('btnDemoAuto');
        btn.innerHTML = '<i class="fas fa-play mr-1"></i> ' + (state.autoDemo ? 'Stop Simulasi Otomatis' : 'Putar Simulasi Otomatis');
        btn.classList.toggle('active', state.autoDemo);
        if (state.autoDemo) runAutoCycle();
    }

    function runAutoCycle() {
        if (!state.autoDemo) return;
        if (state.busy) { setTimeout(runAutoCycle, 1600); return; }

        const targets = occupiedRecords(3);
        if (!targets.length) {
            const empties = emptyRecords(3);
            if (!empties.length) { state.autoDemo = false; dispatchDemoAuto(); return; }
            state.busy = true;
            enqueue(() => chainRun(empties.map(rec => () => runIn(rec, PER_LAYER, { label: 'Karton masuk ke ' + rec.data.code })))
                .then(() => { state.busy = false; setTimeout(runAutoCycle, 1200); }));
            return;
        }

        const plan = targets.map(rec => ({ rec, qty: rec.cartons.length }));
        state.busy = true;
        enqueue(() => chainRun(plan.map(t => () => runOut(t.rec, t.qty, { label: 'Ambil karton dari ' + t.rec.data.code })))
            .then(() => wait(900))
            .then(() => chainRun(plan.map(t => () => runIn(t.rec, t.qty, { label: 'Kembalikan karton ke ' + t.rec.data.code }))))
            .then(() => { state.busy = false; setTimeout(runAutoCycle, 1200); }));
    }

    function toast(msg) {
        if (window.Swal && Swal.fire) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: msg, showConfirmButton: false, timer: 2600, background: '#171b2b', color: '#e7ebf5' });
        }
    }

    function nowText() {
        const d = new Date();
        return d.toLocaleTimeString('id-ID');
    }

    /* -------------------------------- Kamera -------------------------------- */
    function updateCamera() {
        orbit.phi = clamp(orbit.phi, 0.12, Math.PI / 2 - 0.03);
        orbit.radius = clamp(orbit.radius, orbit.minRadius, orbit.maxRadius);
        const x = orbit.target.x + orbit.radius * Math.cos(orbit.phi) * Math.sin(orbit.theta);
        const y = orbit.target.y + orbit.radius * Math.sin(orbit.phi);
        const z = orbit.target.z + orbit.radius * Math.cos(orbit.phi) * Math.cos(orbit.theta);
        camera.position.set(x, y, z);
        camera.lookAt(orbit.target);
    }

    /* Hitung jarak kamera agar seluruh layout + zona masuk/keluar masuk frame */
    function fitRadius(pad) {
        const spanX = (layout.maxX || 1) + 17;
        const spanZ = (layout.maxZ - layout.minZ) + 17;
        const sphereR = 0.5 * Math.sqrt(spanX * spanX + spanZ * spanZ);
        const vFov = camera.fov * Math.PI / 180;
        const hFov = 2 * Math.atan(Math.tan(vFov / 2) * camera.aspect);
        const dist = Math.max(sphereR / Math.sin(vFov / 2), sphereR / Math.sin(hFov / 2));
        return Math.max(40, dist * (pad || 1.0));
    }

    function setView(kind) {
        if (kind === 'iso') {
            orbit.theta = -0.72; orbit.phi = 0.84;
            orbit.radius = fitRadius(1.0);
        } else if (kind === 'top') {
            orbit.theta = 0; orbit.phi = 1.45;
            orbit.radius = fitRadius(1.02);
        } else if (kind === 'front') {
            orbit.theta = 0; orbit.phi = 0.34;
            orbit.radius = fitRadius(0.86);
        }
    }

    function fitCameraToContent(initial) {
        orbit.target.set(layout.maxX / 2, 1.0, (layout.minZ + layout.maxZ) / 2);
        if (initial) setView('iso');
        updateCamera();
    }

    function focusFirstOccupied() {
        const rec = Object.values(records).find(r => r.cartons.length > 0);
        if (!rec) { toast('Belum ada pallet terisi.'); return; }
        orbit.target.set(rec.pos3.x, 1.4, rec.pos3.z);
        orbit.radius = Math.max(12, orbit.radius * 0.55);
        selectPallet(rec.data.code);
    }

    /* --------------------------- Interaksi pointer --------------------------- */
    function bindPointer(canvas) {
        let dragging = false, btn = 0, lastX = 0, lastY = 0, moved = 0;

        canvas.addEventListener('contextmenu', e => e.preventDefault());
        canvas.addEventListener('pointerenter', () => { pointerInside = true; });
        canvas.addEventListener('pointerleave', () => { pointerInside = false; hoverRing.visible = false; hoverCode = null; });

        canvas.addEventListener('pointerdown', e => {
            dragging = true; btn = e.button; lastX = e.clientX; lastY = e.clientY; moved = 0;
            canvas.setPointerCapture(e.pointerId);
        });

        canvas.addEventListener('pointermove', e => {
            const rect = canvas.getBoundingClientRect();
            pointerNdc.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            pointerNdc.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

            if (!dragging) { return; }
            const dx = e.clientX - lastX, dy = e.clientY - lastY;
            lastX = e.clientX; lastY = e.clientY;
            moved += Math.abs(dx) + Math.abs(dy);

            if (btn === 2 || e.shiftKey) {
                // pan
                const panScale = orbit.radius * 0.0016;
                const right = new THREE.Vector3().setFromMatrixColumn(camera.matrix, 0);
                const up = new THREE.Vector3().setFromMatrixColumn(camera.matrix, 1);
                orbit.target.addScaledVector(right, -dx * panScale);
                orbit.target.addScaledVector(up, dy * panScale);
            } else {
                orbit.theta -= dx * 0.005;
                orbit.phi -= dy * 0.005;
            }
            updateCamera();
        });

        canvas.addEventListener('pointerup', e => {
            dragging = false;
            canvas.releasePointerCapture && canvas.releasePointerCapture(e.pointerId);
            if (moved < 6) pickAt();
        });

        canvas.addEventListener('wheel', e => {
            e.preventDefault();
            orbit.radius *= (1 + Math.sign(e.deltaY) * 0.09);
            updateCamera();
        }, { passive: false });
    }

    function pickAt() {
        raycaster.setFromCamera(pointerNdc, camera);
        const hits = raycaster.intersectObjects(baseMeshes, false);
        if (!hits.length) { return; }
        selectPallet(hits[0].object.userData.code);
    }

    function hoverLoop() {
        if (!pointerInside) return;
        raycaster.setFromCamera(pointerNdc, camera);
        const hits = raycaster.intersectObjects(baseMeshes, false);
        const code = hits.length ? hits[0].object.userData.code : null;
        if (code === hoverCode) return;
        hoverCode = code;
        const rec = code ? records[code] : null;
        if (rec) {
            hoverRing.position.set(rec.pos3.x, 0.05, rec.pos3.z);
            hoverRing.visible = true;
        } else {
            hoverRing.visible = false;
        }
    }

    function selectPallet(code) {
        const rec = records[code];
        if (!rec) return;
        selectedCode = code;
        selRing.position.set(rec.pos3.x, 0.06, rec.pos3.z);
        selRing.visible = true;

        const s = rec.data;
        document.getElementById('infoCode').textContent = s.code;
        document.getElementById('infoMeta').textContent =
            [s.qty + ' / ' + s.capacity + ' karton', s.style ? 'Style ' + s.style : null,
             s.country ? 'Dest ' + s.country : null, s.ocf ? 'OCF ' + s.ocf : null]
                .filter(Boolean).join(' • ');

        const rows = (s.items || []).map(it =>
            `<tr><td>${it.jancode || '-'}</td><td>${it.color || '-'}</td><td>${it.size || '-'}</td><td class="text-right"><b>${it.qty}</b></td></tr>`
        ).join('');
        document.getElementById('infoTable').innerHTML =
            (rows || '<tr><td colspan="4" class="text-muted">Pallet kosong.</td></tr>');
        document.getElementById('hudInfo').style.display = 'block';
    }

    /* --------------------------------- UI HUD -------------------------------- */
    function bindUI() {
        const $ = id => document.getElementById(id);

        $('btnDemoOut').addEventListener('click', dispatchDemoOut);
        $('btnDemoIn').addEventListener('click', dispatchDemoIn);
        $('btnDemoAuto').addEventListener('click', dispatchDemoAuto);

        $('btnViewIso').addEventListener('click', () => { setView('iso'); updateCamera(); });
        $('btnViewTop').addEventListener('click', () => { setView('top'); updateCamera(); });
        $('btnViewFront').addEventListener('click', () => { setView('front'); updateCamera(); });

        $('btnAutoRotate').addEventListener('click', function () {
            state.autoRotate = !state.autoRotate;
            this.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Putar kamera: ' + (state.autoRotate ? 'nyala' : 'mati');
            this.classList.toggle('active', state.autoRotate);
        });

        $('btnLabels').addEventListener('click', function () {
            state.labels = !state.labels;
            this.innerHTML = '<i class="fas fa-tag mr-1"></i> Label: ' + (state.labels ? 'tampil' : 'sembunyi');
            Object.values(records).forEach(updateLabel);
        });

        $('btnFocus').addEventListener('click', focusFirstOccupied);

        $('btnSync').addEventListener('click', () => {
            if (state.busy) return;
            state.busy = true;
            syncNow(false).then(() => {
                Object.values(records).forEach(rec => setPalletQty(rec, rec.data.qty));
                state.busy = false;
                toast('Tampilan disamakan dengan data terbaru.');
            });
        });

        $('btnLive').addEventListener('click', function () {
            state.live = !state.live;
            this.innerHTML = '<i class="fas fa-broadcast-tower mr-1"></i> Auto-sync: ' + (state.live ? 'nyala' : 'mati');
            this.classList.toggle('active', state.live);
            document.getElementById('liveDot').classList.toggle('on', state.live);
            document.getElementById('liveText').textContent = 'Live: ' + (state.live ? 'menyala (5s)' : 'mati');
            if (liveTimer) { clearInterval(liveTimer); liveTimer = null; }
            if (state.live) {
                liveTimer = setInterval(() => { if (!state.busy) syncNow(true); }, 5000);
            }
        });

        $('infoClose').addEventListener('click', () => {
            document.getElementById('hudInfo').style.display = 'none';
            selRing.visible = false;
            selectedCode = null;
        });
    }

    /* -------------------------------- Loop ---------------------------------- */
    function startLoop() {
        if (running) return;
        running = true;
        renderer.setAnimationLoop(() => {
            const dt = Math.min(clock.getDelta(), 0.05);

            updaters.forEach(fn => fn(dt));

            if (state.autoRotate) {
                orbit.theta += dt * 0.12;
                updateCamera();
            }

            truckBadgeTick(dt);
            hoverLoop();

            renderer.render(scene, camera);
        });
    }

    let badgeT = 0;
    function truckBadgeTick(dt) {
        const el = document.getElementById('truckBadge');
        if (el.style.display === 'block') {
            badgeT += dt;
            el.style.opacity = 0.72 + 0.28 * Math.sin(badgeT * 5);
        }
    }

    /* --------------------------------- API ---------------------------------- */
    return {
        boot,
        syncNow,
        demoOut: dispatchDemoOut,
        demoIn: dispatchDemoIn,
        demoAuto: dispatchDemoAuto,
        _debug: () => ({
            truck: truck ? { x: +truck.position.x.toFixed(2), y: +truck.position.y.toFixed(2), z: +truck.position.z.toFixed(2), ry: +truck.rotation.y.toFixed(2) } : null,
            busy: state.busy,
            autoDemo: state.autoDemo,
            occupied: Object.values(records).filter(r => r.cartons.length > 0).length,
            visualCartons: Object.values(records).reduce((s, r) => s + r.cartons.length, 0),
            payload: truckPayload ? truckPayload.children.length : 0,
            labels: labelGroup ? labelGroup.children.length : 0,
            marks: markGroup ? markGroup.children.map(c => c.userData.text + '@' + c.position.z.toFixed(2)) : []
        }),
        _records: records
    };
})();
