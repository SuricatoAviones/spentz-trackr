/**
 * Genera los iconos de marca de la app: favicon, apple-touch-icon e iconos PWA.
 *
 *   node scripts/generate-icons.mjs
 *
 * ¿Por qué un script y no unos PNG sueltos en el repo? Porque son seis binarios
 * derivados de la MISMA marca, y sin esto nadie puede reproducirlos si cambia el
 * color o la forma. El diseño vive aquí y en `public/favicon.svg`, que dibuja lo
 * mismo en vectores.
 *
 * Sin dependencias a propósito: el proyecto no tiene rasterizador, así que se
 * pintan los píxeles a mano (supermuestreo 4x para el antialias) y se escriben
 * los bytes de PNG e ICO. `zlib` y `Buffer` vienen con Node.
 *
 * Tres variantes, que NO son la misma imagen escalada:
 *
 *   - favicon: por debajo de 24 px el anillo va continuo y más grueso. Con el
 *     trazo fino del diseño original los cuatro huecos desaparecen y la marca se
 *     empasta con el fondo.
 *   - maskable (Android): fondo a sangre y marca al 68 %. Android recorta estos
 *     iconos a un círculo usando solo el 80 % central; con el logo completo le
 *     cortaba el texto.
 *   - apple-touch-icon: fondo a sangre y sin esquinas redondeadas propias, que
 *     iOS aplica las suyas encima.
 */
// Importado explícitamente: eslint corre con globals de navegador y `Buffer`
// no existe ahí.
import { Buffer } from 'node:buffer';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { deflateSync } from 'node:zlib';

const BG = [0x0b, 0x12, 0x20]; // #0B1220
const MARK = [0x10, 0xb9, 0x81]; // #10B981

const SS = 4; // supermuestreo por eje
const VIEW = 64; // el diseño está pensado en un lienzo de 64 unidades

const publicDir = resolve(dirname(fileURLToPath(import.meta.url)), '../public');

/* ------------------------------------------------------------------ */
/* Geometría                                                           */
/* ------------------------------------------------------------------ */

/** ¿El punto cae dentro de un rectángulo redondeado centrado en (cx, cy)? */
function inRoundedRect(x, y, cx, cy, halfW, halfH, radius) {
    const dx = Math.abs(x - cx);
    const dy = Math.abs(y - cy);

    if (dx > halfW || dy > halfH) {
        return false;
    }

    const ix = dx - (halfW - radius);
    const iy = dy - (halfH - radius);

    if (ix <= 0 || iy <= 0) {
        return true;
    }

    return Math.hypot(ix, iy) <= radius;
}

/** ¿El punto cae en el anillo? `solid` lo dibuja entero, sin los cuatro huecos. */
function inRing(x, y, cx, cy, radius, halfStroke, solid) {
    const dx = x - cx;
    const dy = y - cy;
    const dist = Math.hypot(dx, dy);
    const onStroke = Math.abs(dist - radius) <= halfStroke;

    if (solid) {
        return onStroke;
    }

    const arc = 65.9; // 4 arcos de 65.9° + 4 huecos de 24.1° = 360°
    const gap = 24.1;
    const rotation = -45;

    for (let i = 0; i < 4; i++) {
        const start = rotation + i * (arc + gap);

        if (onStroke) {
            const angle = (Math.atan2(dy, dx) * 180) / Math.PI;
            const delta = (((angle - start) % 360) + 360) % 360;

            if (delta <= arc) {
                return true;
            }
        }

        // Tapas redondeadas en los extremos de cada arco.
        for (const capAngle of [start, start + arc]) {
            const rad = (capAngle * Math.PI) / 180;

            if (
                Math.hypot(
                    x - (cx + radius * Math.cos(rad)),
                    y - (cy + radius * Math.sin(rad)),
                ) <= halfStroke
            ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Dibuja la marca y devuelve un buffer RGBA de `size` x `size`.
 *
 * @param {object} options
 * @param {boolean} options.rounded  Esquinas redondeadas propias (false = a sangre).
 * @param {number}  options.scale    Tamaño de la marca respecto al lienzo.
 */
function render(size, { rounded = true, scale = 1 } = {}) {
    const px = Buffer.alloc(size * size * 4);
    const unit = size / VIEW;
    const c = VIEW / 2;

    // Por debajo de 24 px el detalle fino no sobrevive al escalado.
    const tiny = size < 24;
    const ringRadius = 20 * scale;
    const halfStroke = (tiny ? 5 : 3) * scale;
    const coreHalf = (tiny ? 7 : 6) * scale;
    const coreRadius = 3.5 * scale;
    const bgRadius = rounded ? (tiny ? 10 : 14) : 0;

    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            let bgHits = 0;
            let markHits = 0;

            for (let sy = 0; sy < SS; sy++) {
                for (let sx = 0; sx < SS; sx++) {
                    const vx = (x + (sx + 0.5) / SS) / unit;
                    const vy = (y + (sy + 0.5) / SS) / unit;

                    if (!inRoundedRect(vx, vy, c, c, c, c, bgRadius)) {
                        continue;
                    }

                    bgHits++;

                    if (
                        inRing(vx, vy, c, c, ringRadius, halfStroke, tiny) ||
                        inRoundedRect(
                            vx,
                            vy,
                            c,
                            c,
                            coreHalf,
                            coreHalf,
                            coreRadius,
                        )
                    ) {
                        markHits++;
                    }
                }
            }

            const samples = SS * SS;
            const markRatio = bgHits === 0 ? 0 : markHits / bgHits;
            const i = (y * size + x) * 4;

            for (let ch = 0; ch < 3; ch++) {
                px[i + ch] = Math.round(
                    BG[ch] * (1 - markRatio) + MARK[ch] * markRatio,
                );
            }

            px[i + 3] = Math.round((bgHits / samples) * 255);
        }
    }

    return px;
}

/* ------------------------------------------------------------------ */
/* PNG                                                                 */
/* ------------------------------------------------------------------ */

const CRC_TABLE = (() => {
    const table = new Uint32Array(256);

    for (let n = 0; n < 256; n++) {
        let c = n;

        for (let k = 0; k < 8; k++) {
            c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        }

        table[n] = c >>> 0;
    }

    return table;
})();

function crc32(buf) {
    let crc = 0xffffffff;

    for (const byte of buf) {
        crc = CRC_TABLE[(crc ^ byte) & 0xff] ^ (crc >>> 8);
    }

    return (crc ^ 0xffffffff) >>> 0;
}

function pngChunk(type, data) {
    const length = Buffer.alloc(4);
    length.writeUInt32BE(data.length, 0);

    const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(body), 0);

    return Buffer.concat([length, body, crc]);
}

function toPng(rgba, size) {
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(size, 0);
    ihdr.writeUInt32BE(size, 4);
    ihdr[8] = 8; // bits por canal
    ihdr[9] = 6; // RGBA

    const stride = size * 4 + 1; // +1 por el byte de filtro de cada línea
    const raw = Buffer.alloc(size * stride);

    for (let y = 0; y < size; y++) {
        raw[y * stride] = 0; // filtro "none"
        rgba.copy(raw, y * stride + 1, y * size * 4, (y + 1) * size * 4);
    }

    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        pngChunk('IHDR', ihdr),
        pngChunk('IDAT', deflateSync(raw, { level: 9 })),
        pngChunk('IEND', Buffer.alloc(0)),
    ]);
}

/* ------------------------------------------------------------------ */
/* ICO                                                                 */
/* ------------------------------------------------------------------ */

/** Bloque BMP de 32 bits tal y como lo espera un ICO. */
function bmpBlock(rgba, size) {
    const header = Buffer.alloc(40);
    header.writeUInt32LE(40, 0);
    header.writeInt32LE(size, 4);
    header.writeInt32LE(size * 2, 8); // alto doble: mapa XOR + máscara AND
    header.writeUInt16LE(1, 12);
    header.writeUInt16LE(32, 14);
    header.writeUInt32LE(size * size * 4, 20);

    // BGRA y de abajo a arriba.
    const xor = Buffer.alloc(size * size * 4);

    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            const src = (y * size + x) * 4;
            const dst = ((size - 1 - y) * size + x) * 4;

            xor[dst] = rgba[src + 2];
            xor[dst + 1] = rgba[src + 1];
            xor[dst + 2] = rgba[src];
            xor[dst + 3] = rgba[src + 3];
        }
    }

    // Máscara AND a ceros: la transparencia la lleva el canal alfa.
    const mask = Buffer.alloc(Math.ceil(size / 32) * 4 * size);

    return Buffer.concat([header, xor, mask]);
}

function toIco(sizes) {
    const blocks = sizes.map((size) => bmpBlock(render(size), size));

    const header = Buffer.alloc(6);
    header.writeUInt16LE(1, 2); // tipo: icono
    header.writeUInt16LE(sizes.length, 4);

    let offset = 6 + sizes.length * 16;

    const entries = sizes.map((size, i) => {
        const entry = Buffer.alloc(16);
        entry[0] = size === 256 ? 0 : size;
        entry[1] = size === 256 ? 0 : size;
        entry.writeUInt16LE(1, 4); // planos
        entry.writeUInt16LE(32, 6); // bits por píxel
        entry.writeUInt32LE(blocks[i].length, 8);
        entry.writeUInt32LE(offset, 12);
        offset += blocks[i].length;

        return entry;
    });

    return Buffer.concat([header, ...entries, ...blocks]);
}

/* ------------------------------------------------------------------ */

function write(relativePath, contents) {
    const target = resolve(publicDir, relativePath);
    mkdirSync(dirname(target), { recursive: true });
    writeFileSync(target, contents);
    console.log(`  ${relativePath.padEnd(28)} ${contents.length} bytes`);
}

console.log('Generando iconos en public/');

write('favicon.ico', toIco([16, 32, 48]));

// iOS aplica su propia máscara redondeada: el icono va a sangre.
write(
    'apple-touch-icon.png',
    toPng(render(180, { rounded: false, scale: 0.85 }), 180),
);

// PWA, propósito "any": se muestra tal cual, con sus esquinas.
for (const size of [192, 512]) {
    write(`icons/icon-${size}.png`, toPng(render(size), size));
}

// PWA, propósito "maskable": Android recorta al 80 % central.
for (const size of [192, 512]) {
    write(
        `icons/maskable-${size}.png`,
        toPng(render(size, { rounded: false, scale: 0.68 }), size),
    );
}

console.log('Listo.');
