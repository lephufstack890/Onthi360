/**
 * Sinh public/css/legacy-compat.css — "lớp vá" cho trình duyệt cũ (Chrome ≤ 110, Win7/8.1 kẹt ở
 * Chrome 109, Cốc Cốc/Edge bản cũ, Safari < 15.4…).
 *
 * VÌ SAO CẦN: Tailwind v4 xuất CSS dùng tính năng mới:
 *   · màu bảng màu viết bằng oklch()                     → Chrome < 111 coi là sai → MẤT MÀU
 *   · gradient "linear-gradient(to right in oklab, …)"   → Chrome < 111 bỏ cả nền → NÚT TRẮNG
 *     (nút Đăng nhập / Đăng ký chữ trắng trên nền trắng = "biến mất")
 *   · đơn vị dvh                                          → Chrome < 108 bỏ max-height
 * Tệp này đọc CSS đã build, tìm đúng các khai báo đó và viết lại bằng cú pháp cũ (hex, gradient
 * không "in oklab", vh), bọc trong @supports not (...) — trình duyệt mới KHÔNG bị ảnh hưởng.
 * Giữ nguyên @layer / @media gốc để thứ tự ưu tiên y như Tailwind.
 *
 * Chạy tự động sau "npm run build" (script postbuild trong package.json). Chạy tay:
 *   node scripts/legacy-css.mjs
 */
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'public/build/manifest.json'), 'utf8'));
const cssFiles = [...new Set(Object.values(manifest).flatMap((e) => [e.file, ...(e.css || [])]).filter((f) => f && f.endsWith('.css')))];

/* ── oklch → sRGB hex (Björn Ottosson), ngoài gam màu thì cắt về [0,1] ── */
export function oklchToHex(input) {
    const m = input.trim().match(/^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)(?:deg)?\s*(?:\/\s*([\d.]+)(%?))?\s*\)$/i);
    if (!m) { return null; }
    const L = m[2] ? parseFloat(m[1]) / 100 : parseFloat(m[1]);
    const C = parseFloat(m[3]);
    const h = (parseFloat(m[4]) * Math.PI) / 180;
    const a = C * Math.cos(h), b = C * Math.sin(h);
    const l_ = L + 0.3963377774 * a + 0.2158037573 * b;
    const m_ = L - 0.1055613458 * a - 0.0638541728 * b;
    const s_ = L - 0.0894841775 * a - 1.2914855480 * b;
    const l = l_ ** 3, mm = m_ ** 3, s = s_ ** 3;
    const lin = [
        4.0767416621 * l - 3.3077115913 * mm + 0.2309699292 * s,
        -1.2684380046 * l + 2.6097574011 * mm - 0.3413193965 * s,
        -0.0041960863 * l - 0.7034186147 * mm + 1.7076147010 * s,
    ];
    const gamma = (x) => (x <= 0.0031308 ? 12.92 * x : 1.055 * x ** (1 / 2.4) - 0.055);
    const hex = lin.map((x) => Math.round(Math.min(1, Math.max(0, gamma(x))) * 255).toString(16).padStart(2, '0')).join('');
    let alpha = '';
    if (m[5] !== undefined) {
        const av = m[6] ? parseFloat(m[5]) / 100 : parseFloat(m[5]);
        if (av < 1) { alpha = Math.round(av * 255).toString(16).padStart(2, '0'); }
    }
    return '#' + hex + alpha;
}

/* ── bộ đọc CSS tối giản (đã minify): cây at-rule / rule, tôn trọng chuỗi, ngoặc, chú thích ── */
function parse(css) {
    let i = 0;
    const n = css.length;
    function skipComment() { if (css[i] === '/' && css[i + 1] === '*') { const e = css.indexOf('*/', i + 2); i = e < 0 ? n : e + 2; return true; } return false; }
    function readUntil(stops) {
        let out = '', depth = 0, q = null;
        while (i < n) {
            const ch = css[i];
            if (q) { out += ch; if (ch === '\\') { out += css[i + 1] || ''; i += 2; continue; } if (ch === q) { q = null; } i++; continue; }
            if (ch === '"' || ch === "'") { q = ch; out += ch; i++; continue; }
            if (ch === '/' && css[i + 1] === '*') { skipComment(); continue; }
            if (ch === '(' ) { depth++; } else if (ch === ')') { depth--; }
            if (depth === 0 && stops.includes(ch)) { break; }
            out += ch; i++;
        }
        return out;
    }
    function block() {
        const nodes = [];
        while (i < n) {
            while (i < n && /\s/.test(css[i])) { i++; }
            if (skipComment()) { continue; }
            if (css[i] === '}') { i++; break; }
            const prelude = readUntil(['{', ';', '}']).trim();
            if (css[i] === ';') { i++; nodes.push({ type: 'stmt', prelude }); continue; }
            if (css[i] === '}') { if (prelude) { nodes.push({ type: 'stmt', prelude }); } continue; }
            i++; // '{'
            if (prelude.startsWith('@')) {
                const name = prelude.slice(1).split(/[\s(]/)[0].toLowerCase();
                if (['media', 'supports', 'layer', 'container', 'scope', 'document'].includes(name)) {
                    nodes.push({ type: 'at', prelude, children: block() });
                } else {
                    // @font-face, @keyframes, @property…: bỏ qua nội dung
                    let depth = 1; while (i < n && depth) { if (css[i] === '{') depth++; else if (css[i] === '}') depth--; i++; }
                }
            } else {
                const body = readUntil(['}']); i++;
                // khối lồng (CSS nesting) hiếm gặp sau minify — nếu có thì bỏ qua phần lồng
                const decls = [];
                let k = 0; const parts = []; let cur = '', d = 0, q = null;
                for (; k < body.length; k++) {
                    const ch = body[k];
                    if (q) { cur += ch; if (ch === q) q = null; continue; }
                    if (ch === '"' || ch === "'") { q = ch; cur += ch; continue; }
                    if (ch === '(') d++; else if (ch === ')') d--;
                    if (ch === ';' && d === 0) { parts.push(cur); cur = ''; continue; }
                    cur += ch;
                }
                if (cur.trim()) parts.push(cur);
                for (const p of parts) { const c = p.indexOf(':'); if (c > 0) decls.push([p.slice(0, c).trim(), p.slice(c + 1).trim()]); }
                nodes.push({ type: 'rule', selector: prelude, decls });
            }
        }
        return nodes;
    }
    return block();
}

const OKLCH = /oklch\([^()]*\)/gi;
const fixes = { oklch: [], gradient: [], vh: [] };

function walk(nodes, ctx) {
    for (const node of nodes) {
        if (node.type === 'at') {
            // Bỏ các khối @supports của chính Tailwind (đã có bản dự phòng sẵn) — không vá trong đó.
            if (/^@supports/i.test(node.prelude)) { continue; }
            walk(node.children, [...ctx, node.prelude]);
        } else if (node.type === 'rule') {
            const o = [], g = [], v = [];
            for (const [prop, val] of node.decls) {
                if (/oklch\(/i.test(val)) {
                    let ok = true;
                    const fixed = val.replace(OKLCH, (m) => { const h = oklchToHex(m); if (!h) ok = false; return h || m; });
                    if (ok) { o.push([prop, fixed]); }
                }
                if (/\s+in\s+ok(lab|lch)/i.test(val) && /gradient/i.test(prop + val)) {
                    g.push([prop, val.replace(/\s+in\s+ok(lab|lch)(\s+(shorter|longer|increasing|decreasing)\s+hue)?/gi, '')]);
                }
                if (/\d(dvh|svh|lvh|dvw|svw|lvw)\b/.test(val)) {
                    v.push([prop, val.replace(/(\d)(d|s|l)(vh|vw)\b/g, '$1$3')]);
                }
            }
            if (o.length) fixes.oklch.push({ ctx, selector: node.selector, decls: o });
            if (g.length) fixes.gradient.push({ ctx, selector: node.selector, decls: g });
            if (v.length) fixes.vh.push({ ctx, selector: node.selector, decls: v });
        }
    }
}

for (const f of cssFiles) { walk(parse(fs.readFileSync(path.join(root, 'public/build', f), 'utf8')), []); }

function emit(list) {
    return list.map(({ ctx, selector, decls }) => {
        let s = selector + '{' + decls.map(([p, v]) => p + ':' + v).join(';') + '}';
        // Cú pháp "(width>=40rem)" chỉ chạy từ Chrome 104 — viết lại kiểu cũ "(min-width:40rem)".
        for (let j = ctx.length - 1; j >= 0; j--) { s = ctx[j].replace(/\(\s*width\s*>=\s*([^)]+?)\s*\)/g, '(min-width:$1)') + '{' + s + '}'; }
        return s;
    }).join('\n');
}

const out = [
    '/* TỰ SINH bởi scripts/legacy-css.mjs từ ' + cssFiles.join(', ') + ' — đừng sửa tay, chạy lại "npm run build". */',
    '/* Vá cho trình duyệt cũ: chỉ có tác dụng khi trình duyệt KHÔNG hiểu oklch / gradient "in oklab" / dvh. */',
    '@supports not (color: oklch(50% 0.1 200)) {\n' + emit(fixes.oklch) + '\n}',
    '@supports not (background-image: linear-gradient(in oklab, red, blue)) {\n' + emit(fixes.gradient) + '\n}',
    '@supports not (height: 1dvh) {\n' + emit(fixes.vh) + '\n}',
    '',
].join('\n');

fs.mkdirSync(path.join(root, 'public/css'), { recursive: true });
fs.writeFileSync(path.join(root, 'public/css/legacy-compat.css'), out);
console.log(`legacy-compat.css: ${fixes.oklch.length} rule màu, ${fixes.gradient.length} rule gradient, ${fixes.vh.length} rule dvh → public/css/legacy-compat.css (${out.length} byte)`);
