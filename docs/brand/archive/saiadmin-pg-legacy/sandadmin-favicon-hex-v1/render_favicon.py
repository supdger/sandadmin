from __future__ import annotations

import argparse
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont


ROOT = Path(__file__).resolve().parent
BASE = 512
SS = 4
CANVAS = BASE * SS


def scale_points(points: list[tuple[float, float]]) -> list[tuple[int, int]]:
    return [(round(x * SS), round(y * SS)) for x, y in points]


def cubic(
    p0: tuple[float, float],
    p1: tuple[float, float],
    p2: tuple[float, float],
    p3: tuple[float, float],
    steps: int = 120,
) -> list[tuple[float, float]]:
    result = []
    for index in range(steps + 1):
        t = index / steps
        u = 1 - t
        x = u**3 * p0[0] + 3 * u**2 * t * p1[0] + 3 * u * t**2 * p2[0] + t**3 * p3[0]
        y = u**3 * p0[1] + 3 * u**2 * t * p1[1] + 3 * u * t**2 * p2[1] + t**3 * p3[1]
        result.append((x, y))
    return result


def interpolate(stops: list[tuple[float, tuple[int, int, int]]], t: float) -> tuple[int, int, int]:
    if t <= stops[0][0]:
        return stops[0][1]
    if t >= stops[-1][0]:
        return stops[-1][1]
    for (left_t, left), (right_t, right) in zip(stops, stops[1:]):
        if left_t <= t <= right_t:
            ratio = (t - left_t) / (right_t - left_t)
            return tuple(round(a + (b - a) * ratio) for a, b in zip(left, right))
    return stops[-1][1]


def gradient(stops: list[tuple[float, tuple[int, int, int]]]) -> Image.Image:
    image = Image.new("RGBA", (CANVAS, CANVAS))
    draw = ImageDraw.Draw(image)
    for y in range(CANVAS):
        color = interpolate(stops, y / (CANVAS - 1))
        draw.line((0, y, CANVAS, y), fill=(*color, 255))
    return image


def stroke_mask(points: list[tuple[float, float]], width: float, closed: bool = False) -> Image.Image:
    mask = Image.new("L", (CANVAS, CANVAS), 0)
    draw = ImageDraw.Draw(mask)
    scaled = scale_points(points)
    if closed:
        scaled.append(scaled[0])
    draw.line(scaled, fill=255, width=round(width * SS), joint="curve")
    if not closed:
        radius = width * SS / 2
        for x, y in (scaled[0], scaled[-1]):
            draw.ellipse((round(x - radius), round(y - radius), round(x + radius), round(y + radius)), fill=255)
    return mask


def color_layer(color: tuple[int, int, int, int], mask: Image.Image) -> Image.Image:
    layer = Image.new("RGBA", (CANVAS, CANVAS), color)
    layer.putalpha(mask.point(lambda value: value * color[3] // 255))
    return layer


def gradient_layer(stops: list[tuple[float, tuple[int, int, int]]], mask: Image.Image) -> Image.Image:
    layer = gradient(stops)
    layer.putalpha(mask)
    return layer


def make_master() -> Image.Image:
    icon = Image.new("RGBA", (CANVAS, CANVAS), (0, 0, 0, 0))

    hex_points = [(256, 27), (450, 139), (450, 373), (256, 485), (62, 373), (62, 139)]
    hex_outer = stroke_mask(hex_points, 58, closed=True)
    hex_inner = stroke_mask(hex_points, 38, closed=True)
    icon = Image.alpha_composite(icon, color_layer((7, 23, 37, 235), hex_outer))
    hex_glow = color_layer((25, 205, 245, 125), hex_inner.filter(ImageFilter.GaussianBlur(8 * SS)))
    icon = Image.alpha_composite(icon, hex_glow)
    icon = Image.alpha_composite(
        icon,
        gradient_layer(
            [(0.0, (115, 247, 241)), (0.48, (33, 200, 246)), (1.0, (22, 139, 231))],
            hex_inner,
        ),
    )

    segment_a = cubic((356, 132), (292, 84), (154, 103), (151, 184))
    segment_b = cubic((151, 184), (147, 250), (350, 246), (354, 329))
    segment_c = cubic((354, 329), (358, 411), (228, 440), (150, 389))
    s_points = segment_a + segment_b[1:] + segment_c[1:]
    s_outer = stroke_mask(s_points, 132)
    s_inner = stroke_mask(s_points, 104)
    icon = Image.alpha_composite(icon, color_layer((8, 21, 37, 235), s_outer))
    s_glow = color_layer((205, 128, 228, 105), s_inner.filter(ImageFilter.GaussianBlur(10 * SS)))
    icon = Image.alpha_composite(icon, s_glow)
    icon = Image.alpha_composite(
        icon,
        gradient_layer(
            [
                (0.0, (115, 238, 228)),
                (0.22, (246, 198, 92)),
                (0.52, (199, 123, 234)),
                (0.78, (240, 168, 63)),
                (1.0, (255, 216, 117)),
            ],
            s_inner,
        ),
    )

    return icon.resize((BASE, BASE), Image.Resampling.LANCZOS)


def make_preview(images: dict[int, Image.Image]) -> Image.Image:
    width, height = 1600, 1030
    preview = Image.new("RGBA", (width, height), "#E9EEF4")
    draw = ImageDraw.Draw(preview)
    font = ImageFont.load_default(size=24)
    small_font = ImageFont.load_default(size=14)

    panels = [(60, 60, 740, 700, "#F7F9FC"), (860, 60, 1540, 700, "#07111F")]
    for left, top, right, bottom, color in panels:
        draw.rounded_rectangle((left, top, right, bottom), radius=36, fill=color)
        preview.alpha_composite(images[512], (left + 84, top + 64))

    draw.text((60, 24), "SandAdmin favicon - light background", fill="#142235", font=font)
    draw.text((860, 24), "SandAdmin favicon - dark background", fill="#142235", font=font)

    sizes = [16, 32, 48, 60]
    start_x = 70
    for index, size in enumerate(sizes):
        x = start_x + index * 380
        draw.rounded_rectangle((x, 760, x + 320, 1000), radius=24, fill="#FFFFFF")
        actual_x = x + 24
        actual_y = 810
        preview.alpha_composite(images[size], (actual_x, actual_y))
        zoom = images[size].resize((size * 3, size * 3), Image.Resampling.NEAREST)
        preview.alpha_composite(zoom, (x + 108, 785))
        draw.text((x + 22, 725), f"{size} x {size} px", fill="#142235", font=small_font)
        draw.text((x + 20, 970), "actual size", fill="#526170", font=small_font)
        draw.text((x + 160, 970), "3x pixel view", fill="#526170", font=small_font)

    return preview.convert("RGB")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--package-only",
        action="store_true",
        help="Package an existing browser-rendered sandadmin-favicon-master.png.",
    )
    args = parser.parse_args()

    if args.package_only:
        master = Image.open(ROOT / "sandadmin-favicon-master.png").convert("RGBA")
    else:
        master = make_master()
        master.save(ROOT / "sandadmin-favicon-master.png", optimize=True)

    sizes = [16, 32, 48, 60, 64, 128, 170, 256, 512]
    images = {size: master.resize((size, size), Image.Resampling.LANCZOS) for size in sizes}
    for size, image in images.items():
        image.save(ROOT / f"sandadmin-favicon-{size}.png", optimize=True)

    master.save(
        ROOT / "sandadmin-favicon.ico",
        format="ICO",
        sizes=[(16, 16), (32, 32), (48, 48), (60, 60), (64, 64), (128, 128), (256, 256)],
    )
    make_preview(images).save(ROOT / "sandadmin-favicon-preview.png", optimize=True)


if __name__ == "__main__":
    main()
