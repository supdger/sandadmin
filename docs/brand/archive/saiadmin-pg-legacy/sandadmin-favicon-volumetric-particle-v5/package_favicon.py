from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parent


def make_preview(images: dict[int, Image.Image]) -> Image.Image:
    preview = Image.new("RGBA", (1600, 1030), "#E9EEF4")
    draw = ImageDraw.Draw(preview)
    title_font = ImageFont.load_default(size=24)
    label_font = ImageFont.load_default(size=14)

    panels = [(60, 60, 740, 700, "#F7F9FC"), (860, 60, 1540, 700, "#07111F")]
    for left, top, right, bottom, color in panels:
        draw.rounded_rectangle((left, top, right, bottom), radius=36, fill=color)
        preview.alpha_composite(images[512], (left + 84, top + 64))

    draw.text((60, 24), "Volumetric twisted particle S - light", fill="#142235", font=title_font)
    draw.text((860, 24), "Volumetric twisted particle S - dark", fill="#142235", font=title_font)

    for index, size in enumerate((16, 32, 48, 60)):
        x = 70 + index * 380
        draw.rounded_rectangle((x, 760, x + 320, 1000), radius=24, fill="#FFFFFF")
        preview.alpha_composite(images[size], (x + 24, 810))
        zoom = images[size].resize((size * 3, size * 3), Image.Resampling.NEAREST)
        preview.alpha_composite(zoom, (x + 108, 785))
        draw.text((x + 22, 725), f"{size} x {size} px", fill="#142235", font=label_font)
        draw.text((x + 20, 970), "actual size", fill="#526170", font=label_font)
        draw.text((x + 160, 970), "3x pixel view", fill="#526170", font=label_font)

    return preview.convert("RGB")


def main() -> None:
    master = Image.open(ROOT / "sandadmin-favicon-master.png").convert("RGBA")
    sizes = (16, 32, 48, 60, 64, 128, 170, 256, 512)
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
