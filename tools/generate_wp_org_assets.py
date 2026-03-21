#!/usr/bin/env python3
import os
import struct
import zlib


FONT = {
    "A": ["01110", "10001", "10001", "11111", "10001", "10001", "10001"],
    "B": ["11110", "10001", "10001", "11110", "10001", "10001", "11110"],
    "C": ["01111", "10000", "10000", "10000", "10000", "10000", "01111"],
    "D": ["11110", "10001", "10001", "10001", "10001", "10001", "11110"],
    "E": ["11111", "10000", "10000", "11110", "10000", "10000", "11111"],
    "F": ["11111", "10000", "10000", "11110", "10000", "10000", "10000"],
    "G": ["01111", "10000", "10000", "10111", "10001", "10001", "01111"],
    "H": ["10001", "10001", "10001", "11111", "10001", "10001", "10001"],
    "I": ["11111", "00100", "00100", "00100", "00100", "00100", "11111"],
    "J": ["00001", "00001", "00001", "00001", "10001", "10001", "01110"],
    "K": ["10001", "10010", "10100", "11000", "10100", "10010", "10001"],
    "L": ["10000", "10000", "10000", "10000", "10000", "10000", "11111"],
    "M": ["10001", "11011", "10101", "10101", "10001", "10001", "10001"],
    "N": ["10001", "10001", "11001", "10101", "10011", "10001", "10001"],
    "O": ["01110", "10001", "10001", "10001", "10001", "10001", "01110"],
    "P": ["11110", "10001", "10001", "11110", "10000", "10000", "10000"],
    "Q": ["01110", "10001", "10001", "10001", "10101", "10010", "01101"],
    "R": ["11110", "10001", "10001", "11110", "10100", "10010", "10001"],
    "S": ["01111", "10000", "10000", "01110", "00001", "00001", "11110"],
    "T": ["11111", "00100", "00100", "00100", "00100", "00100", "00100"],
    "U": ["10001", "10001", "10001", "10001", "10001", "10001", "01110"],
    "V": ["10001", "10001", "10001", "10001", "10001", "01010", "00100"],
    "W": ["10001", "10001", "10001", "10101", "10101", "10101", "01010"],
    "X": ["10001", "10001", "01010", "00100", "01010", "10001", "10001"],
    "Y": ["10001", "10001", "01010", "00100", "00100", "00100", "00100"],
    "Z": ["11111", "00001", "00010", "00100", "01000", "10000", "11111"],
    "0": ["01110", "10001", "10011", "10101", "11001", "10001", "01110"],
    "1": ["00100", "01100", "00100", "00100", "00100", "00100", "01110"],
    "2": ["01110", "10001", "00001", "00010", "00100", "01000", "11111"],
    "3": ["11110", "00001", "00001", "01110", "00001", "00001", "11110"],
    "4": ["00010", "00110", "01010", "10010", "11111", "00010", "00010"],
    "5": ["11111", "10000", "10000", "11110", "00001", "00001", "11110"],
    "6": ["01110", "10000", "10000", "11110", "10001", "10001", "01110"],
    "7": ["11111", "00001", "00010", "00100", "01000", "01000", "01000"],
    "8": ["01110", "10001", "10001", "01110", "10001", "10001", "01110"],
    "9": ["01110", "10001", "10001", "01111", "00001", "00001", "01110"],
    "%": ["11001", "11010", "00100", "00100", "01011", "10011", "00000"],
    "-": ["00000", "00000", "00000", "11111", "00000", "00000", "00000"],
    ">": ["10000", "01000", "00100", "00010", "00100", "01000", "10000"],
    ".": ["00000", "00000", "00000", "00000", "00000", "01100", "01100"],
    ":": ["00000", "01100", "01100", "00000", "01100", "01100", "00000"],
    "/": ["00001", "00010", "00100", "01000", "10000", "00000", "00000"],
    " ": ["00000", "00000", "00000", "00000", "00000", "00000", "00000"],
}


def chunk(tag, data):
    return (
        struct.pack("!I", len(data))
        + tag
        + data
        + struct.pack("!I", zlib.crc32(tag + data) & 0xFFFFFFFF)
    )


def save_png(path, width, height, pixels):
    raw = bytearray()
    for y in range(height):
        raw.append(0)
        for x in range(width):
            raw.extend(pixels[y][x])
    png = bytearray(b"\x89PNG\r\n\x1a\n")
    png.extend(chunk(b"IHDR", struct.pack("!IIBBBBB", width, height, 8, 2, 0, 0, 0)))
    png.extend(chunk(b"IDAT", zlib.compress(bytes(raw), 9)))
    png.extend(chunk(b"IEND", b""))
    with open(path, "wb") as handle:
        handle.write(png)


def canvas(width, height, color):
    return [[list(color) for _ in range(width)] for _ in range(height)]


def fill_gradient(pixels, top, bottom):
    height = len(pixels)
    width = len(pixels[0])
    for y in range(height):
        ratio = y / max(height - 1, 1)
        color = [
            int(top[i] * (1 - ratio) + bottom[i] * ratio)
            for i in range(3)
        ]
        for x in range(width):
            pixels[y][x] = color[:]


def rect(pixels, x, y, w, h, color):
    height = len(pixels)
    width = len(pixels[0])
    for yy in range(max(0, y), min(height, y + h)):
        row = pixels[yy]
        for xx in range(max(0, x), min(width, x + w)):
            row[xx] = list(color)


def round_rect(pixels, x, y, w, h, radius, color):
    r2 = radius * radius
    for yy in range(y, y + h):
        for xx in range(x, x + w):
            dx = 0
            dy = 0
            if xx < x + radius:
                dx = x + radius - xx
            elif xx >= x + w - radius:
                dx = xx - (x + w - radius - 1)
            if yy < y + radius:
                dy = y + radius - yy
            elif yy >= y + h - radius:
                dy = yy - (y + h - radius - 1)
            if dx * dx + dy * dy <= r2:
                if 0 <= yy < len(pixels) and 0 <= xx < len(pixels[0]):
                    pixels[yy][xx] = list(color)


def circle(pixels, cx, cy, r, color):
    r2 = r * r
    for yy in range(cy - r, cy + r + 1):
        for xx in range(cx - r, cx + r + 1):
            if 0 <= yy < len(pixels) and 0 <= xx < len(pixels[0]):
                dx = xx - cx
                dy = yy - cy
                if dx * dx + dy * dy <= r2:
                    pixels[yy][xx] = list(color)


def draw_char(pixels, x, y, char, scale, color):
    glyph = FONT.get(char.upper(), FONT[" "])
    for row_index, row in enumerate(glyph):
        for col_index, bit in enumerate(row):
            if bit == "1":
                rect(
                    pixels,
                    x + col_index * scale,
                    y + row_index * scale,
                    scale,
                    scale,
                    color,
                )


def draw_text(pixels, x, y, text, scale, color, spacing=1):
    cursor = x
    for char in text:
        draw_char(pixels, cursor, y, char, scale, color)
        cursor += (5 + spacing) * scale


def icon(path, size):
    pixels = canvas(size, size, (244, 238, 226))
    fill_gradient(pixels, (18, 33, 57), (28, 72, 93))
    circle(pixels, size // 2, size // 2, int(size * 0.34), (245, 242, 232))
    circle(pixels, size // 2, int(size * 0.35), int(size * 0.09), (18, 33, 57))
    round_rect(
        pixels,
        int(size * 0.28),
        int(size * 0.48),
        int(size * 0.44),
        int(size * 0.11),
        max(2, int(size * 0.04)),
        (199, 109, 74),
    )
    round_rect(
        pixels,
        int(size * 0.32),
        int(size * 0.66),
        int(size * 0.36),
        int(size * 0.08),
        max(2, int(size * 0.04)),
        (18, 33, 57),
    )
    save_png(path, size, size, pixels)


def banner(path, width, height):
    pixels = canvas(width, height, (248, 244, 236))
    fill_gradient(pixels, (247, 241, 229), (222, 231, 236))
    round_rect(pixels, 56, 44, 520, height - 88, 28, (18, 33, 57))
    round_rect(pixels, 610, 56, width - 666, height - 112, 28, (255, 255, 255))
    circle(pixels, 168, height // 2 - 4, 72, (245, 242, 232))
    circle(pixels, 168, height // 2 - 38, 18, (18, 33, 57))
    round_rect(pixels, 114, height // 2 + 18, 108, 22, 10, (199, 109, 74))
    round_rect(pixels, 126, height // 2 + 60, 84, 16, 8, (245, 242, 232))
    draw_text(pixels, 282, 94, "SCROLL MAGNET", 6, (245, 242, 232))
    draw_text(pixels, 284, 162, "SMART CTA TRIGGERS", 3, (245, 242, 232))
    draw_text(pixels, 284, 196, "FOR ENGAGED VISITORS", 3, (245, 242, 232))
    round_rect(pixels, 682, 96, 210, 98, 18, (245, 241, 232))
    round_rect(pixels, 910, 96, 210, 98, 18, (243, 248, 249))
    round_rect(pixels, 1138, 96, 210, 98, 18, (250, 239, 233))
    draw_text(pixels, 720, 120, "50% OFFER", 3, (18, 33, 57))
    draw_text(pixels, 948, 120, "70% BUTTON", 3, (18, 33, 57))
    draw_text(pixels, 1168, 120, "90% POPUP", 3, (18, 33, 57))
    round_rect(pixels, 722, 150, 130, 18, 9, (199, 109, 74))
    round_rect(pixels, 950, 150, 130, 18, 9, (18, 33, 57))
    round_rect(pixels, 1178, 150, 130, 18, 9, (199, 109, 74))
    draw_text(pixels, 690, 232, "BEHAVIOR BASED", 3, (18, 33, 57))
    draw_text(pixels, 690, 266, "CONVERSION BOOSTER", 3, (18, 33, 57))
    save_png(path, width, height, pixels)


def screenshot(path, width, height):
    pixels = canvas(width, height, (240, 244, 247))
    fill_gradient(pixels, (241, 244, 247), (231, 236, 240))
    round_rect(pixels, 40, 36, width - 80, height - 72, 22, (255, 255, 255))
    rect(pixels, 40, 36, width - 80, 70, (18, 33, 57))
    draw_text(pixels, 76, 58, "SCROLL MAGNET SETTINGS", 4, (245, 242, 232))

    card_y = 146
    for idx, label in enumerate(["GLOBAL SETTINGS", "TRIGGER 1", "TRIGGER 2", "TRIGGER 3", "MVP STATS"]):
        y = card_y + idx * 150
        round_rect(pixels, 84, y, width - 168, 120, 18, (248, 244, 236))
        draw_text(pixels, 114, y + 18, label, 3, (18, 33, 57))
        rect(pixels, 114, y + 56, 220, 12, (199, 109, 74))
        rect(pixels, 114, y + 80, 320, 10, (202, 212, 220))
        rect(pixels, 462, y + 56, 170, 12, (18, 33, 57))
        rect(pixels, 462, y + 80, 240, 10, (202, 212, 220))
        rect(pixels, 730, y + 56, 120, 42, (18, 33, 57))
        rect(pixels, 874, y + 56, 120, 42, (199, 109, 74))

    save_png(path, width, height, pixels)


def main():
    base = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    out_dir = os.path.join(base, "wordpress-org-assets")
    os.makedirs(out_dir, exist_ok=True)

    icon(os.path.join(out_dir, "icon-128x128.png"), 128)
    icon(os.path.join(out_dir, "icon-256x256.png"), 256)
    banner(os.path.join(out_dir, "banner-772x250.png"), 772, 250)
    banner(os.path.join(out_dir, "banner-1544x500.png"), 1544, 500)
    screenshot(os.path.join(out_dir, "screenshot-1.png"), 1280, 960)


if __name__ == "__main__":
    main()
