/**
 * Conteúdo impresso no vidro (canvas da página) e medição de texto — compartilhado pelo motor WebGL e
 * pela composição em CSS, para que o texto real do DOM e o impresso coincidam.
 */

export const FONT = 'Figtree, ui-sans-serif, system-ui, sans-serif';

// Tokens do site: tailwind.config.js (talents-*), slate-600 e o fundo do .app-shell.
export const PALETTE = {
    ink: '#2a1042',
    inkSoft: '#4a2070',
    eyebrow: '#632a7e',
    subtitle: '#475569',
    primary: '#632a7e',
    primaryHover: '#7b4fa2',
    ring: '#7b4fa2',
    page: '#faf8fc',
};

export const fontOf = (size, weight) => `${weight} ${size}px ${FONT}`;

export const makeMeasure = () => {
    const ctx = document.createElement('canvas').getContext('2d');
    const hasLS = 'letterSpacing' in ctx;
    const measure = (text, size, weight, tracking) => {
        ctx.font = fontOf(size, weight);
        if (hasLS) ctx.letterSpacing = `${tracking}px`;
        const w = ctx.measureText(text).width;
        return hasLS ? w : w + tracking * text.length;
    };
    return { measure, hasLS };
};

const fillTracked = (ctx, text, x, y, tracking, hasLS) => {
    if (hasLS) {
        ctx.letterSpacing = `${tracking}px`;
        ctx.fillText(text, x, y);
        ctx.letterSpacing = '0px';
        return;
    }
    let cx = x;
    [...text].forEach((ch) => {
        ctx.fillText(ch, cx, y);
        cx += ctx.measureText(ch).width + tracking;
    });
};

const baselineOf = (ctx, top, lh) => {
    const m = ctx.measureText('Hg');
    const fa = m.fontBoundingBoxAscent ?? parseFloat(ctx.font) * 0.8;
    const fd = m.fontBoundingBoxDescent ?? parseFloat(ctx.font) * 0.2;
    return top + (lh - (fa + fd)) / 2 + fa;
};

const drawImageCover = (ctx, img, b, fx, fy) => {
    const s = Math.max(b.w / img.width, b.h / img.height);
    const dw = img.width * s;
    const dh = img.height * s;
    ctx.drawImage(img, b.x - (dw - b.w) * fx, b.y - (dh - b.h) * fy, dw, dh);
};

/**
 * Desenha a página do painel no canvas `cv` (DPR ≥ 2). `printImage=false` deixa a foto de fora
 * (direção B: a foto fica atrás do vidro, como camada própria).
 */
export const drawPage = (cv, { g, layout: L, heroImg, printImage = true, hover = -1, focus = -1, dpr = 2, hasLS = true }) => {
    const w = Math.round(g.pw * dpr);
    const h = Math.round(g.ph * dpr);
    if (cv.width !== w || cv.height !== h) {
        cv.width = w;
        cv.height = h;
    }
    const ctx = cv.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, g.pw, g.ph);
    ctx.textBaseline = 'alphabetic';

    if (heroImg && printImage) {
        const t = document.createElement('canvas');
        t.width = Math.round(L.image.w * dpr);
        t.height = Math.round(L.image.h * dpr);
        const tc = t.getContext('2d');
        tc.scale(dpr, dpr);
        drawImageCover(tc, heroImg, { x: 0, y: 0, w: L.image.w, h: L.image.h }, 0.78, 0.08);
        tc.globalCompositeOperation = 'source-atop';
        const veil = tc.createLinearGradient(0, 0, L.image.w, L.image.h);
        veil.addColorStop(0, 'rgba(255,255,255,.18)');
        veil.addColorStop(0.45, 'rgba(255,255,255,.02)');
        veil.addColorStop(1, 'rgba(255,255,255,.08)');
        tc.fillStyle = veil;
        tc.fillRect(0, 0, L.image.w, L.image.h);
        tc.globalCompositeOperation = 'destination-in';
        const fade = g.portrait
            ? tc.createLinearGradient(0, 0, 0, L.image.h * 0.35)
            : tc.createLinearGradient(0, 0, L.image.w * 0.35, 0);
        fade.addColorStop(0, 'rgba(0,0,0,0)');
        fade.addColorStop(1, 'rgba(0,0,0,1)');
        tc.fillStyle = fade;
        tc.fillRect(0, 0, L.image.w, L.image.h);
        ctx.drawImage(t, L.image.x, L.image.y, L.image.w, L.image.h);
    }

    const e = L.eyebrow;
    ctx.font = fontOf(e.size, 600);
    ctx.fillStyle = PALETTE.eyebrow;
    e.lines.forEach((line, i) => fillTracked(ctx, line, e.x, baselineOf(ctx, e.y + i * e.lh, e.lh), e.tracking, hasLS));

    const hd = L.headline;
    ctx.font = fontOf(hd.size, 600);
    const grad = ctx.createLinearGradient(0, hd.y, 0, hd.y + hd.lines.length * hd.lh);
    grad.addColorStop(0, PALETTE.ink);
    grad.addColorStop(1, PALETTE.inkSoft);
    ctx.fillStyle = grad;
    hd.lines.forEach((line, i) => fillTracked(ctx, line, hd.x, baselineOf(ctx, hd.y + i * hd.lh, hd.lh), hd.tracking, hasLS));

    const sb = L.subtitle;
    ctx.font = fontOf(sb.size, 400);
    ctx.fillStyle = PALETTE.subtitle;
    sb.lines.forEach((line, i) => ctx.fillText(line, sb.x, baselineOf(ctx, sb.y + i * sb.lh, sb.lh)));

    L.ctas.forEach((c, i) => {
        const hot = hover === i;
        const base = baselineOf(ctx, c.y, c.h);
        if (c.kind === 'primary') {
            ctx.save();
            ctx.shadowColor = 'rgba(74,32,112,.34)';
            ctx.shadowBlur = hot ? 14 : 8;
            ctx.shadowOffsetY = hot ? 6 : 3;
            ctx.fillStyle = hot ? PALETTE.primaryHover : PALETTE.primary;
            ctx.beginPath();
            ctx.roundRect(c.x, c.y, c.w, c.h, c.h / 2);
            ctx.fill();
            ctx.restore();
            if (hot) {
                // micromomento: reflexo diagonal discreto atravessando a pílula
                ctx.save();
                ctx.beginPath();
                ctx.roundRect(c.x, c.y, c.w, c.h, c.h / 2);
                ctx.clip();
                const sheen = ctx.createLinearGradient(c.x, c.y, c.x + c.w, c.y + c.h);
                sheen.addColorStop(0.25, 'rgba(255,255,255,0)');
                sheen.addColorStop(0.45, 'rgba(255,255,255,.16)');
                sheen.addColorStop(0.6, 'rgba(255,255,255,0)');
                ctx.fillStyle = sheen;
                ctx.fillRect(c.x, c.y, c.w, c.h);
                ctx.restore();
            }
            ctx.font = fontOf(c.size, 600);
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.fillText(c.label, c.x + c.w / 2, base);
            ctx.textAlign = 'left';
        } else {
            ctx.font = fontOf(c.size, 500);
            ctx.fillStyle = hot ? PALETTE.ink : PALETTE.inkSoft;
            ctx.fillText(c.label, c.x, base);
            if (hot) ctx.fillRect(c.x, base + 3, c.w, 1.4);
        }
        if (focus === i) {
            // foco: anel duplo (branco + roxo) para ler sobre qualquer fundo
            const r = c.kind === 'primary' ? c.h / 2 + 5 : 10;
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#ffffff';
            ctx.beginPath();
            ctx.roundRect(c.x - 5, c.y - 5, c.w + 10, c.h + 10, r);
            ctx.stroke();
            ctx.lineWidth = 2;
            ctx.strokeStyle = PALETTE.ring;
            ctx.stroke();
        }
    });
};

/** Fundo claro: degradê branco → #faf8fc com manchas da marca (sem faixa escura). */
export const drawBackdrop = (cv, W, H, { blobs = 1 } = {}) => {
    cv.width = Math.max(1, Math.round(W));
    cv.height = Math.max(1, Math.round(H));
    const ctx = cv.getContext('2d');
    const lin = ctx.createLinearGradient(0, 0, 0, H);
    lin.addColorStop(0, '#ffffff');
    lin.addColorStop(0.5, '#f8f4fc');
    lin.addColorStop(1, PALETTE.page);
    ctx.fillStyle = lin;
    ctx.fillRect(0, 0, W, H);
    const blob = (fx, fy, fr, rgb, a) => {
        const r = fr * Math.max(W, H * 1.4);
        const rg = ctx.createRadialGradient(fx * W, fy * H, 0, fx * W, fy * H, r);
        rg.addColorStop(0, `rgba(${rgb},${a * blobs})`);
        rg.addColorStop(1, `rgba(${rgb},0)`);
        ctx.fillStyle = rg;
        ctx.fillRect(0, 0, W, H);
    };
    blob(0.16, 0.34, 0.3, '179,136,217', 0.5);
    blob(0.52, 0.72, 0.26, '123,79,162', 0.3);
    blob(0.86, 0.26, 0.28, '212,184,228', 0.75);
    blob(0.38, 0.5, 0.2, '99,42,126', 0.16);
    const top = ctx.createLinearGradient(0, 0, 0, H * 0.14);
    top.addColorStop(0, 'rgba(255,255,255,1)');
    top.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = top;
    ctx.fillRect(0, 0, W, H * 0.14);
    const bot = ctx.createLinearGradient(0, H * 0.78, 0, H);
    bot.addColorStop(0, 'rgba(250,248,252,0)');
    bot.addColorStop(1, 'rgba(250,248,252,1)');
    ctx.fillStyle = bot;
    ctx.fillRect(0, H * 0.78, W, H * 0.22);
};
