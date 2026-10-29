/**
 * Teste de navegador da Hero da Home: grava quadros (screencast) de 0 a 1500 ms depois da pintura da Hero e falha se ela
 * trocar de aparência de forma brusca (Hero antiga → nova), ou se o título mudar de lugar.
 *
 * Não roda no `npm run test:js` (precisa de navegador). Uso, com o Playwright instalado e o app no ar:
 *   HERO_URL=http://localhost:8080/ node tests/JavaScript/browser/heroFrames.mjs
 * Perfis: desktop 1440, Pixel 5 e Pixel 5 em rede/CPU lentas; cache frio e quente. Sai com código 1 se algum falhar.
 */
import { checkNoAbruptSwap } from '../support/frameDiff.mjs';

// HERO_PLAYWRIGHT: caminho/URL do módulo do Playwright quando ele não está instalado no projeto.
const { chromium, devices } = await import(process.env.HERO_PLAYWRIGHT || 'playwright');

const URL = process.env.HERO_URL || 'http://localhost:8080/';
const MAX_TOTAL = Number(process.env.HERO_MAX_TOTAL || 0.09); // quadro estático × quadro final (a cena de vidro acrescenta os cortes)
const MAX_STEP = Number(process.env.HERO_MAX_STEP || 0.07); // entre quadros consecutivos (a troca da Hero antiga pela nova dava degraus de ~0,13)
const WINDOW_MS = 1500;

const profiles = [
    ['desktop 1440', { viewport: { width: 1440, height: 900 } }, false],
    ['pixel 5', { ...devices['Pixel 5'] }, false],
    ['pixel 5 lento', { ...devices['Pixel 5'] }, true],
];

// Roda dentro da página: reduz cada PNG para ~240 px de largura e devolve os pixels RGBA da região da Hero.
const decode = async ({ dataUrls, clip }) => {
    const out = [];
    for (const url of dataUrls) {
        const img = new Image();
        img.src = url;
        await img.decode();
        const scale = 200 / clip.width;
        const c = document.createElement('canvas');
        c.width = Math.round(clip.width * scale);
        c.height = Math.round(clip.height * scale);
        const ctx = c.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(img, clip.x, clip.y, clip.width, clip.height, 0, 0, c.width, c.height);
        out.push(Array.from(ctx.getImageData(0, 0, c.width, c.height).data));
    }
    return out;
};

const browser = await chromium.launch({ args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'] });
const helper = await (await browser.newContext()).newPage();
let failed = false;

for (const [name, opts, slow] of profiles.filter(([n]) => !process.env.HERO_ONLY || n === process.env.HERO_ONLY)) {
    for (const warm of [false, true]) {
        const ctx = await browser.newContext(opts);
        if (warm) {
            // Aquece o cache e fecha a aba (uma aba com WebGL aberta competiria pela CPU na medição).
            const warmup = await ctx.newPage();
            await warmup.goto(URL, { waitUntil: 'load' }).catch(() => {});
            await warmup.waitForTimeout(1500);
            await warmup.close();
        }
        const page = await ctx.newPage();
        const cdp = await ctx.newCDPSession(page);
        if (slow) {
            await cdp.send('Network.enable');
            await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 });
            await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
        }
        // Instantes (relógio de parede, ms) em que a Hero ficou pronta, o canvas começou a entrar e assentou.
        await page.addInitScript(() => {
            window.__hero = {};
            const mark = () => {
                const el = document.querySelector('.hero-glass');
                if (!el) return;
                for (const k of ['ready', 'on', 'settled']) {
                    if (!window.__hero[k] && el.classList.contains(`hero-glass--${k}`)) window.__hero[k] = performance.timeOrigin + performance.now();
                }
            };
            new MutationObserver(mark).observe(document, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
        });
        const frames = [];
        cdp.on('Page.screencastFrame', (f) => {
            frames.push({ t: f.metadata.timestamp * 1000, data: f.data });
            cdp.send('Page.screencastFrameAck', { sessionId: f.sessionId }).catch(() => {});
        });
        await cdp.send('Page.startScreencast', { format: 'png', everyNthFrame: 1 });
        await page.goto(URL, { waitUntil: 'commit' });
        await page.waitForSelector('.hero-glass--ready', { timeout: 60000 });
        // título: posição na 1ª pintura × depois que o canvas assentou (ou 6 s, sem WebGL)
        const first = await page.evaluate(() => {
            const b = document.querySelector('.hero-glass .hg-headline').getBoundingClientRect();
            return { x: b.x, y: b.y, w: b.width };
        });
        await page.waitForSelector('.hero-glass--settled', { timeout: slow ? 30000 : 15000 }).catch(() => {});
        await page.waitForTimeout(800);
        await cdp.send('Page.stopScreencast');
        const times = await page.evaluate(() => window.__hero);
        const readyAt = times.ready; // a foto carregou e entra com um fade de 0,25 s (texto e painel já estavam na 1ª pintura)
        const last = await page.evaluate(() => {
            const b = document.querySelector('.hero-glass .hg-headline').getBoundingClientRect();
            return { x: b.x, y: b.y, w: b.width, h1: document.querySelectorAll('h1').length };
        });
        const clip = await page.evaluate(() => {
            const r = document.querySelector('.hero-glass').getBoundingClientRect();
            return { x: 0, y: Math.max(r.y, 0), width: innerWidth, height: Math.min(r.height, innerHeight - Math.max(r.y, 0)) };
        });
        const shots = await helper.evaluate(decode, { dataUrls: frames.map((f) => `data:image/png;base64,${f.data}`), clip }).catch(() => null);
        await ctx.close();

        // Quadro estático = 1º quadro depois do fade de entrada da Hero (já com foto); janela de 1500 ms a partir dele; quadro final = o último.
        const all = frames.map((f, i) => ({ t: f.t, data: shots?.[i] })).filter((f) => f.data && f.t >= readyAt + 350);
        // Sem quadro novo depois da entrada da Hero = a tela ficou estável (nada trocou): passa.
        const result = { ok: true, problems: [], total: 0, worstStep: 0 };
        if (all.length >= 2) {
            const t0 = all[0].t;
            const windowed = all.filter((f) => f.t - t0 <= WINDOW_MS);
            const lastFrame = all[all.length - 1];
            const sel = (windowed.includes(lastFrame) ? windowed : [...windowed, lastFrame]).map((f) => ({ t: f.t - t0, data: Uint8ClampedArray.from(f.data) }));
            Object.assign(result, checkNoAbruptSwap(sel, { maxTotal: MAX_TOTAL, maxStep: MAX_STEP }));
            if (process.env.HERO_DEBUG) console.log('  quadros (ms desde o 1º; entrada/canvas/assentado):', sel.map((f) => Math.round(f.t)).join(' '), JSON.stringify(times));
        }
        const usable = all;
        // No toque o vidro deriva sozinho (inclinação suave): tolera uns pixels; uma troca de Hero deslocaria dezenas.
        const moved = Math.hypot(first.x - last.x, first.y - last.y) > 3 || Math.abs(first.w - last.w) > 3;
        if (moved) result.problems.push(`o título mudou de lugar: ${JSON.stringify(first)} → ${JSON.stringify(last)}`);
        if (last.h1 !== 1) result.problems.push(`h1 na página: ${last.h1}`);
        const ok = result.problems.length === 0;
        failed ||= !ok;
        console.log(`${ok ? 'OK  ' : 'FALHA'} ${name} · ${warm ? 'cache quente' : 'cache frio'} · quadros ${usable.length} · total ${result.total?.toFixed(4)} · pior passo ${result.worstStep?.toFixed(4)}${ok ? '' : ' · ' + result.problems.join('; ')}`);
    }
}
await browser.close();
process.exit(failed ? 1 : 0);
