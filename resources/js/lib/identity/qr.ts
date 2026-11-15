/*
 * Minimal QR Code encoder for the authenticator setup URI (S-17B `totp`):
 * byte mode, error correction level M, versions 1 to 40. Follows ISO/IEC
 * 18004 as laid out in Project Nayuki's reference generator (MIT). Written
 * locally so no package is added for one image; the setup key next to the
 * QR stays the fallback.
 */

const ECC_PER_BLOCK_M = [
    -1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26,
    26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28,
    28, 28, 28,
];

const BLOCKS_M = [
    -1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17,
    17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49,
];

const FORMAT_M = 0; // ECC level M in the format bits.

function rawDataModules(ver: number): number {
    let result = (16 * ver + 128) * ver + 64;

    if (ver >= 2) {
        const align = Math.floor(ver / 7) + 2;
        result -= (25 * align - 10) * align - 55;

        if (ver >= 7) {
            result -= 36;
        }
    }

    return result;
}

function dataCodewords(ver: number): number {
    return (
        Math.floor(rawDataModules(ver) / 8) -
        ECC_PER_BLOCK_M[ver] * BLOCKS_M[ver]
    );
}

function gfMul(x: number, y: number): number {
    let z = 0;

    for (let i = 7; i >= 0; i--) {
        z = (z << 1) ^ ((z >>> 7) * 0x11d);
        z ^= ((y >>> i) & 1) * x;
    }

    return z & 0xff;
}

export function rsDivisor(degree: number): number[] {
    const result: number[] = Array.from({ length: degree - 1 }, () => 0);
    result.push(1);
    let root = 1;

    for (let i = 0; i < degree; i++) {
        for (let j = 0; j < result.length; j++) {
            result[j] = gfMul(result[j], root);

            if (j + 1 < result.length) {
                result[j] ^= result[j + 1];
            }
        }
        root = gfMul(root, 0x02);
    }

    return result;
}

export function rsRemainder(data: number[], divisor: number[]): number[] {
    const result: number[] = divisor.map(() => 0);

    for (const b of data) {
        const factor = b ^ (result.shift() as number);
        result.push(0);
        divisor.forEach((coef, i) => {
            result[i] ^= gfMul(coef, factor);
        });
    }

    return result;
}

const bit = (x: number, i: number): boolean => ((x >>> i) & 1) !== 0;

function alignmentPositions(ver: number): number[] {
    if (ver === 1) {
        return [];
    }
    const size = ver * 4 + 17;
    const count = Math.floor(ver / 7) + 2;
    const step = Math.floor((ver * 8 + count * 3 + 5) / (count * 4 - 4)) * 2;
    const result = [6];

    for (let pos = size - 7; result.length < count; pos -= step) {
        result.splice(1, 0, pos);
    }

    return result;
}

/** Encodes text as a QR matrix (`true` = dark), with no quiet zone. */
export function encodeQr(text: string): boolean[][] {
    const bytes = Array.from(new TextEncoder().encode(text));

    let ver = 1;

    for (; ver <= 40; ver++) {
        const countBits = ver <= 9 ? 8 : 16;

        if (4 + countBits + bytes.length * 8 <= dataCodewords(ver) * 8) {
            break;
        }
    }

    if (ver > 40) {
        throw new Error('Text too long for a QR code');
    }

    // Data bits: byte mode, length, payload, terminator, padding.
    const bits: number[] = [];
    const push = (value: number, len: number) => {
        for (let i = len - 1; i >= 0; i--) {
            bits.push((value >>> i) & 1);
        }
    };
    push(0b0100, 4);
    push(bytes.length, ver <= 9 ? 8 : 16);
    bytes.forEach((b) => push(b, 8));
    const capacity = dataCodewords(ver) * 8;
    push(0, Math.min(4, capacity - bits.length));
    push(0, (8 - (bits.length % 8)) % 8);

    for (let pad = 0xec; bits.length < capacity; pad ^= 0xec ^ 0x11) {
        push(pad, 8);
    }

    const data: number[] = [];

    for (let i = 0; i < bits.length; i += 8) {
        data.push(parseInt(bits.slice(i, i + 8).join(''), 2));
    }

    // Error correction and interleaving.
    const numBlocks = BLOCKS_M[ver];
    const eccLen = ECC_PER_BLOCK_M[ver];
    const rawCodewords = Math.floor(rawDataModules(ver) / 8);
    const numShort = numBlocks - (rawCodewords % numBlocks);
    const shortLen = Math.floor(rawCodewords / numBlocks);
    const divisor = rsDivisor(eccLen);
    const blocks: number[][] = [];

    for (let i = 0, k = 0; i < numBlocks; i++) {
        const dat = data.slice(
            k,
            k + shortLen - eccLen + (i < numShort ? 0 : 1),
        );
        k += dat.length;
        const ecc = rsRemainder(dat, divisor);

        if (i < numShort) {
            dat.push(0);
        }
        blocks.push(dat.concat(ecc));
    }

    const codewords: number[] = [];

    for (let i = 0; i < blocks[0].length; i++) {
        blocks.forEach((block, j) => {
            if (i !== shortLen - eccLen || j >= numShort) {
                codewords.push(block[i]);
            }
        });
    }

    // Function patterns.
    const size = ver * 4 + 17;
    const modules: boolean[][] = Array.from({ length: size }, () =>
        Array.from({ length: size }, () => false),
    );
    const isFunction: boolean[][] = Array.from({ length: size }, () =>
        Array.from({ length: size }, () => false),
    );
    const set = (x: number, y: number, dark: boolean) => {
        modules[y][x] = dark;
        isFunction[y][x] = true;
    };

    for (let i = 0; i < size; i++) {
        set(6, i, i % 2 === 0);
        set(i, 6, i % 2 === 0);
    }

    const finder = (x: number, y: number) => {
        for (let dy = -4; dy <= 4; dy++) {
            for (let dx = -4; dx <= 4; dx++) {
                const dist = Math.max(Math.abs(dx), Math.abs(dy));
                const xx = x + dx;
                const yy = y + dy;

                if (xx >= 0 && xx < size && yy >= 0 && yy < size) {
                    set(xx, yy, dist !== 2 && dist !== 4);
                }
            }
        }
    };
    finder(3, 3);
    finder(size - 4, 3);
    finder(3, size - 4);

    const align = alignmentPositions(ver);
    const last = align.length - 1;

    align.forEach((ay, i) => {
        align.forEach((ax, j) => {
            if (
                (i === 0 && j === 0) ||
                (i === 0 && j === last) ||
                (i === last && j === 0)
            ) {
                return;
            }

            for (let dy = -2; dy <= 2; dy++) {
                for (let dx = -2; dx <= 2; dx++) {
                    set(
                        ax + dx,
                        ay + dy,
                        Math.max(Math.abs(dx), Math.abs(dy)) !== 1,
                    );
                }
            }
        });
    });

    const drawFormat = (mask: number) => {
        const value = (FORMAT_M << 3) | mask;
        let rem = value;

        for (let i = 0; i < 10; i++) {
            rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
        }
        const fbits = ((value << 10) | rem) ^ 0x5412;

        for (let i = 0; i <= 5; i++) {
            set(8, i, bit(fbits, i));
        }
        set(8, 7, bit(fbits, 6));
        set(8, 8, bit(fbits, 7));
        set(7, 8, bit(fbits, 8));

        for (let i = 9; i < 15; i++) {
            set(14 - i, 8, bit(fbits, i));
        }

        for (let i = 0; i < 8; i++) {
            set(size - 1 - i, 8, bit(fbits, i));
        }

        for (let i = 8; i < 15; i++) {
            set(8, size - 15 + i, bit(fbits, i));
        }
        set(8, size - 8, true);
    };
    drawFormat(0);

    if (ver >= 7) {
        let rem = ver;

        for (let i = 0; i < 12; i++) {
            rem = (rem << 1) ^ ((rem >>> 11) * 0x1f25);
        }
        const vbits = (ver << 12) | rem;

        for (let i = 0; i < 18; i++) {
            const dark = bit(vbits, i);
            const a = size - 11 + (i % 3);
            const b = Math.floor(i / 3);
            set(a, b, dark);
            set(b, a, dark);
        }
    }

    // Codewords in the zigzag order.
    let index = 0;

    for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) {
            right = 5;
        }

        for (let vert = 0; vert < size; vert++) {
            for (let j = 0; j < 2; j++) {
                const x = right - j;
                const upward = ((right + 1) & 2) === 0;
                const y = upward ? size - 1 - vert : vert;

                if (!isFunction[y][x] && index < codewords.length * 8) {
                    modules[y][x] = bit(
                        codewords[index >>> 3],
                        7 - (index & 7),
                    );
                    index++;
                }
            }
        }
    }

    // Masks: pick the lowest penalty.
    const masked = (mask: number): boolean[][] =>
        modules.map((row, y) =>
            row.map((dark, x) => {
                if (isFunction[y][x]) {
                    return dark;
                }
                let invert: boolean;

                switch (mask) {
                    case 0:
                        invert = (x + y) % 2 === 0;
                        break;
                    case 1:
                        invert = y % 2 === 0;
                        break;
                    case 2:
                        invert = x % 3 === 0;
                        break;
                    case 3:
                        invert = (x + y) % 3 === 0;
                        break;
                    case 4:
                        invert =
                            (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0;
                        break;
                    case 5:
                        invert = ((x * y) % 2) + ((x * y) % 3) === 0;
                        break;
                    case 6:
                        invert = (((x * y) % 2) + ((x * y) % 3)) % 2 === 0;
                        break;
                    default:
                        invert = (((x + y) % 2) + ((x * y) % 3)) % 2 === 0;
                }

                return dark !== invert;
            }),
        );

    let best: boolean[][] = modules;
    let bestPenalty = Infinity;

    for (let mask = 0; mask < 8; mask++) {
        drawFormat(mask);
        const candidate = masked(mask);
        const score = penalty(candidate);

        if (score < bestPenalty) {
            bestPenalty = score;
            best = candidate;
        }
    }

    return best;
}

/** Penalty rules 1, 2 and 4 of the standard (rule 3 only tunes the choice). */
function penalty(m: boolean[][]): number {
    const size = m.length;
    let score = 0;
    let dark = 0;

    for (let y = 0; y < size; y++) {
        let runX = 1;
        let runY = 1;

        for (let x = 0; x < size; x++) {
            if (m[y][x]) {
                dark++;
            }

            if (x > 0) {
                if (m[y][x] === m[y][x - 1]) {
                    runX++;
                } else {
                    score += runX >= 5 ? runX - 2 : 0;
                    runX = 1;
                }

                if (m[x][y] === m[x - 1][y]) {
                    runY++;
                } else {
                    score += runY >= 5 ? runY - 2 : 0;
                    runY = 1;
                }
            }

            if (
                x > 0 &&
                y > 0 &&
                m[y][x] === m[y][x - 1] &&
                m[y][x] === m[y - 1][x] &&
                m[y][x] === m[y - 1][x - 1]
            ) {
                score += 3;
            }
        }
        score += runX >= 5 ? runX - 2 : 0;
        score += runY >= 5 ? runY - 2 : 0;
    }

    const total = size * size;
    const k = Math.ceil(Math.abs(dark * 20 - total * 10) / total) - 1;

    return score + Math.max(0, k) * 10;
}
