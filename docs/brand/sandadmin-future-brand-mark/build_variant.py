from __future__ import annotations

import base64
import random
from io import BytesIO
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parent
SOURCE = ROOT.parent / "sandadmin-future-brand-mark-v1" / "sandadmin-brand-mark-master.png"
RNG = random.Random(20260817)


def particle_color(rgb: tuple[int, int, int], brighten: float) -> tuple[int, int, int]:
    return tuple(round(channel + (255 - channel) * brighten) for channel in rgb)


def build_master() -> tuple[Image.Image, int]:
    source = Image.open(SOURCE).convert("RGBA")
    data = np.asarray(source)
    red = data[:, :, 0].astype(np.int16)
    green = data[:, :, 1].astype(np.int16)
    blue = data[:, :, 2].astype(np.int16)
    alpha = data[:, :, 3]

    gold = (red > 110) & (green > 50) & (red > green * 1.04) & (blue < red * 0.90)
    magenta = (red > 90) & (blue > 65) & ((red + blue) > green * 2.18)
    warm_light = (red > 185) & (green > 125) & (blue < 175) & (red > blue * 1.12)
    sand_color = gold | magenta | warm_light

    corridor = Image.new("L", source.size, 0)
    corridor_draw = ImageDraw.Draw(corridor)
    centerline = [
        (624, 370),
        (552, 378),
        (470, 400),
        (426, 445),
        (448, 500),
        (520, 552),
        (596, 612),
        (610, 674),
        (568, 723),
        (488, 753),
        (392, 760),
    ]
    corridor_draw.line(centerline, fill=255, width=230, joint="curve")
    corridor_mask = np.asarray(corridor) > 0
    mask = sand_color & (alpha > 18) & corridor_mask
    anchors = np.argwhere(mask)
    if len(anchors) == 0:
        raise RuntimeError("No sand particles were detected in the transparent source.")

    expansion = Image.new("RGBA", source.size, (0, 0, 0, 0))
    expansion_draw = ImageDraw.Draw(expansion, "RGBA")
    density = Image.new("RGBA", source.size, (0, 0, 0, 0))
    density_draw = ImageDraw.Draw(density, "RGBA")
    core = Image.new("RGBA", source.size, (0, 0, 0, 0))
    core_draw = ImageDraw.Draw(core, "RGBA")

    # Outer particles widen the ribbon while keeping a loose sand boundary.
    for _ in range(40000):
        y, x = anchors[RNG.randrange(len(anchors))]
        angle = RNG.random() * 6.283185307179586
        distance = min(38.0, abs(RNG.gauss(0, 15.0)))
        px = x + np.cos(angle) * distance
        py = y + np.sin(angle) * distance
        radius = 0.50 + RNG.random() ** 1.7 * 2.00
        base = tuple(int(value) for value in data[y, x, :3])
        color = particle_color(base, RNG.uniform(0.0, 0.20))
        opacity = round(int(alpha[y, x]) * RNG.uniform(0.20, 0.62))
        expansion_draw.ellipse(
            (px - radius, py - radius, px + radius, py + radius),
            fill=(*color, opacity),
        )

    # A denser inner particle layer gives the sand band more visual weight.
    for _ in range(36000):
        y, x = anchors[RNG.randrange(len(anchors))]
        px = x + RNG.gauss(0, 5.0)
        py = y + RNG.gauss(0, 5.0)
        radius = 0.60 + RNG.random() ** 1.8 * 2.10
        base = tuple(int(value) for value in data[y, x, :3])
        color = particle_color(base, RNG.uniform(0.02, 0.24))
        opacity = round(int(alpha[y, x]) * RNG.uniform(0.32, 0.78))
        density_draw.ellipse(
            (px - radius, py - radius, px + radius, py + radius),
            fill=(*color, opacity),
        )

    # A tighter luminous core improves the S silhouette on both light and dark
    # surfaces without turning the particle ribbon into a flat solid stroke.
    for _ in range(18000):
        y, x = anchors[RNG.randrange(len(anchors))]
        px = x + RNG.gauss(0, 2.4)
        py = y + RNG.gauss(0, 2.4)
        radius = 0.55 + RNG.random() ** 1.9 * 1.75
        base = tuple(int(value) for value in data[y, x, :3])
        color = particle_color(base, RNG.uniform(0.12, 0.32))
        opacity = round(int(alpha[y, x]) * RNG.uniform(0.34, 0.74))
        core_draw.ellipse(
            (px - radius, py - radius, px + radius, py + radius),
            fill=(*color, opacity),
        )

    # Keep the original flattened layer on top so the cube edge ordering and
    # luminous highlights remain unchanged.
    master = Image.alpha_composite(expansion, density)
    master = Image.alpha_composite(master, core)
    master = Image.alpha_composite(master, source)
    return master, len(anchors)


def write_svg(master: Image.Image) -> None:
    buffer = BytesIO()
    master.save(buffer, format="PNG", optimize=True)
    encoded = base64.b64encode(buffer.getvalue()).decode("ascii")
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024" role="img" aria-label="SandAdmin future brand mark v3">
  <image width="1024" height="1024" preserveAspectRatio="xMidYMid meet" href="data:image/png;base64,{encoded}"/>
</svg>
'''
    (ROOT / "sandadmin-brand-mark-master.svg").write_text(svg, encoding="utf-8")


def write_preview(master: Image.Image) -> None:
    preview = Image.new("RGB", (2048, 1024), "#F7F3EA")
    draw = ImageDraw.Draw(preview)
    preview.paste(master, (0, 0), master)
    deep = Image.new("RGBA", (1024, 1024), "#08111F")
    deep.alpha_composite(master)
    preview.paste(deep.convert("RGB"), (1024, 0))
    font = ImageFont.load_default(size=18)
    draw.text((40, 36), "LIGHT", fill="#111827", font=font)
    draw.text((1064, 36), "DEEP", fill="#F3F4F6", font=font)
    preview.save(ROOT / "sandadmin-brand-mark-preview.png", optimize=True)


def main() -> None:
    master, anchors = build_master()
    master.save(ROOT / "sandadmin-brand-mark-master.png", optimize=True)
    master.save(ROOT / "sandadmin-brand-mark-1024.png", optimize=True)

    for size in (512, 170):
        image = master.resize((size, size), Image.Resampling.LANCZOS)
        image.save(ROOT / f"sandadmin-brand-mark-{size}.png", optimize=True)
        if size == 170:
            image.save(
                ROOT / "sandadmin-brand-mark-170.webp",
                format="WEBP",
                lossless=True,
                quality=100,
                method=6,
            )

    write_svg(master)
    write_preview(master)
    print(f"detected_sand_anchor_pixels={anchors}")


if __name__ == "__main__":
    main()
