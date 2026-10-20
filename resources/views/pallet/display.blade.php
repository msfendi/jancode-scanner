<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pallet Display — Live</title>
    <style>
        :root {
            --green: #22c55e;
            --yellow: #f59e0b;
            --red: #ef4444;
            --bg: #0a0f1e;
            --card: #141a2e;
            --line: #26304d;
            --text: #e6ecff;
            --muted: #8b96b5;
            --accent: #60a5fa;
        }

        * { box-sizing: border-box; }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }

        body {
            display: flex;
            flex-direction: column;
        }

        header {
            flex: 0 0 48px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px;
            background: #0d1526;
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
            overflow: hidden;
        }

        .brand {
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 0.08em;
            color: #fff;
        }

        .brand small {
            display: block;
            font-size: 9px;
            letter-spacing: 0.18em;
            color: var(--muted);
            font-weight: 600;
        }

        .stat {
            display: inline-flex;
            align-items: baseline;
            gap: 5px;
            font-size: 11px;
            color: var(--muted);
            background: #101a30;
            border: 1px solid var(--line);
            border-radius: 5px;
            padding: 3px 8px;
        }

        .stat strong {
            font-size: 14px;
            color: #fff;
        }

        .stat.green strong { color: var(--green); }
        .stat.yellow strong { color: var(--yellow); }
        .stat.red strong { color: var(--red); }

        .spacer { flex: 1; }

        .live {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
        }

        .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #64748b;
            box-shadow: 0 0 0 3px rgba(100, 116, 139, 0.18);
        }

        .dot.on {
            background: var(--green);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.22);
            animation: blink 1.6s infinite;
        }

        @keyframes blink {
            50% { opacity: 0.35; }
        }

        #clock {
            font-size: 11px;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        .btn {
            appearance: none;
            border: 1px solid var(--line);
            background: #182140;
            color: var(--text);
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn:hover { background: #223059; }

        #board {
            flex: 1;
            min-height: 0;
            display: flex;
            gap: 5px;
            padding: 5px;
        }

        .row-card {
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 4px;
            overflow: hidden;
        }

        .row-title {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4px;
            padding: 0 1px;
            min-width: 0;
        }

        .row-label {
            font-size: 13px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }

        .row-sub {
            font-size: 8px;
            font-weight: 700;
            color: var(--muted);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-width: 0;
            text-align: right;
        }

        .half {
            flex: 1 1 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .half-title {
            flex: 0 0 auto;
            font-size: 8px;
            letter-spacing: 0.08em;
            color: var(--muted);
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
        }

        .positions {
            flex: 1 1 0;
            min-height: 0;
            display: grid;
            gap: 1px;
        }

        .pos-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            min-height: 0;
        }

        .box {
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 2px;
            overflow: hidden;
            font-weight: 800;
            font-size: clamp(6px, 0.62vw, 10px);
            line-height: 1;
            color: #05210f;
            background: var(--green);
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
            user-select: none;
        }

        .box.vacant { background: var(--green); color: #05210f; }
        .box.partial { background: var(--yellow); color: #2b1a00; }
        .box.full { background: var(--red); color: #fff; }

        .box.multi {
            box-shadow: inset 0 0 0 2px var(--accent);
        }

        .box.flash {
            box-shadow: 0 0 0 2px #fff, 0 0 12px rgba(255, 255, 255, 0.65);
        }

        .legend {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 10px;
            color: var(--muted);
        }

        .legend i {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 2px;
            margin-right: 4px;
            vertical-align: -1px;
        }

        .legend .lg-green { background: var(--green); }
        .legend .lg-yellow { background: var(--yellow); }
        .legend .lg-red { background: var(--red); }
        .legend .lg-multi { background: transparent; box-shadow: inset 0 0 0 2px var(--accent); }

        @media (max-width: 900px) {
            .stat, .legend { display: none; }
            #board { gap: 2px; padding: 2px; }
            .row-card { padding: 2px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="brand">
            PALLET DISPLAY
            <small>LIVE MONITOR</small>
        </div>

        <span class="stat"><strong id="stTotal">0</strong> pallet</span>
        <span class="stat green"><strong id="stVacant">0</strong> kosong</span>
        <span class="stat yellow"><strong id="stPartial">0</strong> terisi</span>
        <span class="stat red"><strong id="stFull">0</strong> penuh</span>
        <span class="stat"><strong id="stCartons">0</strong> karton</span>

        <div class="spacer"></div>

        <div class="legend">
            <span><i class="lg-green"></i>Kosong</span>
            <span><i class="lg-yellow"></i>Belum penuh</span>
            <span><i class="lg-red"></i>Penuh</span>
            <span><i class="lg-multi"></i>Multi-negara</span>
        </div>

        <span class="live"><span class="dot" id="liveDot"></span><span id="liveText">Menghubungkan…</span></span>
        <span id="clock"></span>

        <button class="btn" type="button" id="btnFullscreen">
            <span id="fsLabel">Fullscreen</span>
        </button>
    </header>

    <main id="board"></main>

    <script>
        (function () {
            'use strict';

            const ROWS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
            const STREAM_URL = @json(route('pallet.public.stream'));
            const boxMap = new Map();
            let es = null;
            let reconnectTimer = null;
            let lastSeenIds = new Set();

            function statusClass(cartons, max) {
                const c = parseInt(cartons, 10) || 0;
                const m = parseInt(max, 10) || 42;
                if (c <= 0) return 'vacant';
                return c >= m ? 'full' : 'partial';
            }

            function buildBoard() {
                const board = document.getElementById('board');
                board.innerHTML = '';

                ROWS.forEach(function (row) {
                    const card = document.createElement('section');
                    card.className = 'row-card';
                    card.setAttribute('data-row', row);

                    const title = document.createElement('div');
                    title.className = 'row-title';
                    title.innerHTML = '<span class="row-label">ROW ' + row + '</span><span class="row-sub" data-row-sub>—</span>';
                    card.appendChild(title);

                    const halves = [
                        { label: 'DEPAN · a | b', cols: ['a', 'b'], count: 14 },
                        { label: 'BELAKANG · c | d', cols: ['c', 'd'], count: 16 },
                    ];

                    halves.forEach(function (half) {
                        const wrap = document.createElement('div');
                        wrap.className = 'half';

                        const h = document.createElement('div');
                        h.className = 'half-title';
                        h.textContent = half.label;
                        wrap.appendChild(h);

                        const grid = document.createElement('div');
                        grid.className = 'positions';
                        grid.style.gridTemplateRows = 'repeat(' + half.count + ', minmax(0, 1fr))';

                        for (let p = 1; p <= half.count; p++) {
                            const line = document.createElement('div');
                            line.className = 'pos-row';

                            half.cols.forEach(function (col) {
                                const box = document.createElement('div');
                                box.className = 'box vacant';
                                box.setAttribute('data-key', row + ':' + col + ':' + p);
                                line.appendChild(box);
                                boxMap.set(row + ':' + col + ':' + p, box);
                            });

                            grid.appendChild(line);
                        }

                        wrap.appendChild(grid);
                        card.appendChild(wrap);
                    });

                    board.appendChild(card);
                });
            }

            function setLive(on, text) {
                const dot = document.getElementById('liveDot');
                const label = document.getElementById('liveText');
                dot.classList.toggle('on', !!on);
                label.textContent = text;
            }

            function applyPayload(data) {
                if (!data || !Array.isArray(data.pallets)) return;

                let vacant = 0, partial = 0, full = 0, cartons = 0;
                const seen = new Set();

                data.pallets.forEach(function (p) {
                    const key = p.row + ':' + p.col + ':' + p.pos;
                    const box = boxMap.get(key);
                    const c = parseInt(p.cartons, 10) || 0;
                    const m = parseInt(p.max_carton, 10) || 42;

                    seen.add(key);
                    cartons += c;
                    if (c <= 0) vacant++;
                    else if (c >= m) full++;
                    else partial++;

                    if (!box) return;

                    const next = statusClass(c, m);
                    box.classList.remove('vacant', 'partial', 'full');
                    box.classList.add(next);
                    box.classList.toggle('multi', p.country_mode === 'multi');
                    box.textContent = c > 0 ? c : '';
                    box.title = p.code + ' — ' + (c > 0
                        ? (c + '/' + m + ' karton · ' + (p.style || '-') + ' · ' + (p.country || '-'))
                        : 'Kosong') + (p.country_mode === 'multi' ? ' · multi-negara' : '');

                    if (c > 0 && !lastSeenIds.has(p.code)) {
                        box.classList.add('flash');
                        setTimeout(function () { box.classList.remove('flash'); }, 1200);
                    }
                });

                lastSeenIds = new Set(data.pallets.filter(function (p) {
                    return (parseInt(p.cartons, 10) || 0) > 0;
                }).map(function (p) { return p.code; }));

                document.getElementById('stTotal').textContent = data.pallets.length;
                document.getElementById('stVacant').textContent = vacant;
                document.getElementById('stPartial').textContent = partial;
                document.getElementById('stFull').textContent = full;
                document.getElementById('stCartons').textContent = cartons.toLocaleString('id-ID');

                ROWS.forEach(function (row) {
                    const el = document.querySelector('[data-row="' + row + '"] [data-row-sub]');
                    if (!el) return;
                    const r = data.rows ? data.rows[row] : null;
                    if (r && r.is_locked) {
                        el.textContent = (r.country || '-') + ' · ' + (r.total_cartons || 0) + ' ctn';
                        el.title = (r.style || '-') + ' · ' + (r.country || '-');
                    } else {
                        el.textContent = 'kosong';
                        el.title = 'Belum terisi';
                    }
                });
            }

            function scheduleReconnect() {
                if (reconnectTimer) return;
                reconnectTimer = setTimeout(function () {
                    reconnectTimer = null;
                    connect();
                }, 2000);
            }

            function connect() {
                if (es) {
                    es.close();
                    es = null;
                }

                setLive(false, 'Menghubungkan…');

                es = new EventSource(STREAM_URL);

                es.addEventListener('open', function () {
                    setLive(true, 'LIVE');
                });

                es.addEventListener('pallet', function (ev) {
                    let data = null;
                    try { data = JSON.parse(ev.data); } catch (e) { return; }
                    applyPayload(data);
                    setLive(true, 'LIVE');
                });

                es.addEventListener('done', function () {
                    setLive(false, 'Menyambung ulang…');
                    es.close();
                    es = null;
                    scheduleReconnect();
                });

                es.addEventListener('error', function () {
                    setLive(false, 'Koneksi terputus…');
                    if (es) {
                        es.close();
                        es = null;
                    }
                    scheduleReconnect();
                });
            }

            function tickClock() {
                const now = new Date();
                document.getElementById('clock').textContent = now.toLocaleTimeString('id-ID', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });
            }

            document.getElementById('btnFullscreen').addEventListener('click', function () {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(function () {});
                } else {
                    document.exitFullscreen().catch(function () {});
                }
            });

            document.addEventListener('fullscreenchange', function () {
                document.getElementById('fsLabel').textContent = document.fullscreenElement ? 'Keluar' : 'Fullscreen';
            });

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden && !es) {
                    connect();
                }
            });

            buildBoard();
            setInterval(tickClock, 1000);
            tickClock();

            if (window.EventSource) {
                connect();
            } else {
                setLive(false, 'Browser tidak mendukung live update');
            }
        })();
    </script>
</body>
</html>
