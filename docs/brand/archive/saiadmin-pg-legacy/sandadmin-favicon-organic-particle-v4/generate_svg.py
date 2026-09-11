from __future__ import annotations

import math
import random
from pathlib import Path


ROOT = Path(__file__).resolve().parent
RNG = random.Random(20260815)

CONTROL_POINTS = [
    (374.0, 105.0),
    (337.0, 125.0),
    (268.0, 151.0),
    (208.0, 178.0),
    (169.0, 215.0),
    (181.0, 246.0),
    (236.0, 263.0),
    (302.0, 281.0),
    (345.0, 315.0),
    (347.0, 352.0),
    (315.0, 382.0),
    (257.0, 403.0),
    (178.0, 414.0),
    (139.0, 417.0),
]

COLOR_STOPS = [
    (0.00, (91, 239, 230)),
    (0.13, (255, 211, 106)),
    (0.34, (230, 154, 206)),
    (0.55, (183, 101, 229)),
    (0.78, (242, 165, 66)),
    (1.00, (255, 218, 112)),
]

ACCENTS = [
    ((255, 203, 88), 0.27),
    ((202, 112, 235), 0.19),
    ((91, 235, 226), 0.09),
]


def mix(left: tuple[int, int, int], right: tuple[int, int, int], amount: float) -> tuple[int, int, int]:
    return tuple(round(a + (b - a) * amount) for a, b in zip(left, right))


def color_at(t: float) -> tuple[int, int, int]:
    for (left_t, left), (right_t, right) in zip(COLOR_STOPS, COLOR_STOPS[1:]):
        if left_t <= t <= right_t:
            return mix(left, right, (t - left_t) / (right_t - left_t))
    return COLOR_STOPS[-1][1]


def catmull_rom(p0, p1, p2, p3, t: float) -> tuple[float, float]:
    t2 = t * t
    t3 = t2 * t
    x = 0.5 * (
        2 * p1[0]
        + (-p0[0] + p2[0]) * t
        + (2 * p0[0] - 5 * p1[0] + 4 * p2[0] - p3[0]) * t2
        + (-p0[0] + 3 * p1[0] - 3 * p2[0] + p3[0]) * t3
    )
    y = 0.5 * (
        2 * p1[1]
        + (-p0[1] + p2[1]) * t
        + (2 * p0[1] - 5 * p1[1] + 4 * p2[1] - p3[1]) * t2
        + (-p0[1] + 3 * p1[1] - 3 * p2[1] + p3[1]) * t3
    )
    return x, y


def point_at(t: float) -> tuple[float, float]:
    count = len(CONTROL_POINTS) - 1
    scaled = min(max(t, 0.0), 0.999999) * count
    segment = int(scaled)
    local = scaled - segment
    p1 = CONTROL_POINTS[segment]
    p2 = CONTROL_POINTS[min(segment + 1, count)]
    p0 = CONTROL_POINTS[max(segment - 1, 0)]
    p3 = CONTROL_POINTS[min(segment + 2, count)]
    return catmull_rom(p0, p1, p2, p3, local)


def frame_at(t: float) -> tuple[float, float, float, float]:
    delta = 0.0008
    x, y = point_at(t)
    before = point_at(max(0.0, t - delta))
    after = point_at(min(0.999999, t + delta))
    dx = after[0] - before[0]
    dy = after[1] - before[1]
    length = math.hypot(dx, dy) or 1.0
    return x, y, -dy / length, dx / length


def width_at(t: float) -> float:
    body = 31 * math.sin(math.pi * t) ** 0.72
    upper_lobe = 10 * math.exp(-((t - 0.28) / 0.16) ** 2)
    lower_lobe = 12 * math.exp(-((t - 0.72) / 0.16) ** 2)
    return 9 + body + upper_lobe + lower_lobe


def particle(t: float, offset_factor: float, wisp: bool = False) -> str:
    x, y, nx, ny = frame_at(t)
    width = width_at(t)
    offset = offset_factor * width
    along = RNG.gauss(0, 1.35 if not wisp else 2.4)

    delta = 0.0008
    before = point_at(max(0.0, t - delta))
    after = point_at(min(0.999999, t + delta))
    dx = after[0] - before[0]
    dy = after[1] - before[1]
    length = math.hypot(dx, dy) or 1.0
    tx, ty = dx / length, dy / length

    px = x + nx * offset + tx * along
    py = y + ny * offset + ty * along

    base = color_at(t)
    selector = RNG.random()
    cumulative = 0.0
    for accent, chance in ACCENTS:
        cumulative += chance
        if selector < cumulative:
            base = mix(base, accent, RNG.uniform(0.30, 0.68))
            break

    if wisp:
        radius = RNG.uniform(0.38, 1.05)
        opacity = RNG.uniform(0.16, 0.55)
    else:
        radius = 0.55 + RNG.random() ** 1.7 * 1.15
        opacity = RNG.uniform(0.70, 0.99)

    color = f"#{base[0]:02X}{base[1]:02X}{base[2]:02X}"
    return f'<circle cx="{px:.2f}" cy="{py:.2f}" r="{radius:.2f}" fill="{color}" opacity="{opacity:.2f}"/>'


def generate_particles() -> list[str]:
    circles: list[str] = []

    for _ in range(5200):
        t = RNG.random()
        offset = max(-1.0, min(1.0, RNG.gauss(0, 0.44)))
        circles.append(particle(t, offset))

    # Two loose color lanes keep the flow layered instead of typographic.
    for lane, spread, count in ((0.42, 0.18, 600), (-0.38, 0.20, 600)):
        for _ in range(count):
            t = RNG.uniform(0.04, 0.96)
            circles.append(particle(t, RNG.gauss(lane, spread)))

    # Sparse dust outside the main ribbon creates the organic sand edge.
    for _ in range(750):
        t = RNG.random()
        side = -1 if RNG.random() < 0.5 else 1
        offset = side * RNG.uniform(0.92, 1.55)
        circles.append(particle(t, offset, wisp=True))

    # Bright, slightly irregular heads at both ends.
    for t, color in ((0.002, (103, 247, 236)), (0.998, (255, 209, 94))):
        x, y, _, _ = frame_at(t)
        for _ in range(100):
            angle = RNG.random() * math.tau
            distance = abs(RNG.gauss(0, 5.2))
            px = x + math.cos(angle) * distance
            py = y + math.sin(angle) * distance
            radius = RNG.uniform(0.55, 2.0)
            tint = mix(color, (255, 255, 255), RNG.uniform(0.0, 0.35))
            circles.append(
                f'<circle cx="{px:.2f}" cy="{py:.2f}" r="{radius:.2f}" '
                f'fill="#{tint[0]:02X}{tint[1]:02X}{tint[2]:02X}" opacity="{RNG.uniform(0.55, 1):.2f}"/>'
            )

    return circles


def main() -> None:
    particles = "\n      ".join(generate_particles())
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512" role="img" aria-labelledby="title desc">
  <title id="title">SandAdmin organic particle favicon</title>
  <desc id="desc">A naturally flowing particle S inside a cyan hexagon on a transparent background.</desc>
  <defs>
    <linearGradient id="hexGradient" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#7CFAF3"/>
      <stop offset="0.48" stop-color="#22D0F7"/>
      <stop offset="1" stop-color="#168DE8"/>
    </linearGradient>
    <filter id="hexGlow" x="-20%" y="-20%" width="140%" height="140%" color-interpolation-filters="sRGB">
      <feGaussianBlur stdDeviation="7" result="blur"/>
      <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
    <filter id="particleGlow" x="-100%" y="-100%" width="300%" height="300%" color-interpolation-filters="sRGB">
      <feGaussianBlur stdDeviation="8"/>
    </filter>
    <g id="particleCloud">
      {particles}
    </g>
  </defs>

  <path d="M256 29 448 140 448 372 256 483 64 372 64 140Z"
        fill="none" stroke="url(#hexGradient)" stroke-width="36"
        stroke-linejoin="round" filter="url(#hexGlow)"/>
  <circle cx="374" cy="105" r="15" fill="#71F0E7" opacity="0.38" filter="url(#particleGlow)"/>
  <circle cx="139" cy="417" r="16" fill="#FFD269" opacity="0.42" filter="url(#particleGlow)"/>
  <use href="#particleCloud"/>
</svg>
'''
    (ROOT / "sandadmin-favicon-master.svg").write_text(svg, encoding="utf-8")


if __name__ == "__main__":
    main()
