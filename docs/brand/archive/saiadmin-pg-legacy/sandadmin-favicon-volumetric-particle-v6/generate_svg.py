from __future__ import annotations

import math
import random
from pathlib import Path


ROOT = Path(__file__).resolve().parent
RNG = random.Random(20260815)

CONTROL_POINTS = [
    (397.0, 69.0),
    (352.0, 108.0),
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
    (160.0, 419.0),
    (110.0, 440.0),
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

LANE_GOLD = (246, 178, 70)
LANE_VIOLET = (183, 91, 226)
LANE_CYAN = (72, 222, 216)
CORE_GOLD = (255, 207, 92)


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
    body = 34 * math.sin(math.pi * t) ** 0.72
    upper_lobe = 12 * math.exp(-((t - 0.28) / 0.16) ** 2)
    lower_lobe = 13 * math.exp(-((t - 0.72) / 0.16) ** 2)
    return 10 + body + upper_lobe + lower_lobe


def shade_color(color: tuple[int, int, int], depth: float) -> tuple[int, int, int]:
    normalized = max(-1.0, min(1.0, depth))
    if normalized >= 0:
        return mix(color, (255, 247, 221), 0.22 * normalized)
    factor = 0.68 + 0.32 * (normalized + 1.0)
    return tuple(round(channel * factor) for channel in color)


def lane_color(t: float, across: float, lane: str | None) -> tuple[int, int, int]:
    base = color_at(t)
    if lane == "core":
        return mix(base, CORE_GOLD, 0.66)
    if lane == "gold":
        return mix(base, LANE_GOLD, 0.72)
    if lane == "violet":
        return mix(base, LANE_VIOLET, 0.72)
    if lane == "cyan":
        return mix(base, LANE_CYAN, 0.72)
    if across < -0.32:
        return mix(base, LANE_VIOLET, 0.48)
    if across > 0.48:
        return mix(base, LANE_CYAN, 0.44)
    return mix(base, LANE_GOLD, 0.38)


def particle(
    t: float,
    across: float,
    thickness: float,
    lane: str | None = None,
    wisp: bool = False,
) -> tuple[float, str]:
    x, y, nx, ny = frame_at(t)
    width = width_at(t)
    along = RNG.gauss(0, 1.35 if not wisp else 2.4)

    delta = 0.0008
    before = point_at(max(0.0, t - delta))
    after = point_at(min(0.999999, t + delta))
    dx = after[0] - before[0]
    dy = after[1] - before[1]
    length = math.hypot(dx, dy) or 1.0
    tx, ty = dx / length, dy / length

    # Rotate a thick ribbon cross-section along the S. When the ribbon turns
    # edge-on its apparent width narrows, then expands again with the color
    # lanes swapped, producing the volumetric twist visible in the reference.
    twist = -0.40 * math.pi + 1.60 * math.pi * t + 0.08 * math.sin(4 * math.pi * t)
    tube_depth = 11 + 7 * math.sin(math.pi * t) ** 0.8
    u = across * width
    v = thickness * tube_depth
    cross_screen = u * math.cos(twist) - v * math.sin(twist)
    depth = u * math.sin(twist) + v * math.cos(twist)
    depth_normalized = depth / max(width, 1)

    px = x + nx * cross_screen + 0.24 * depth + tx * along
    py = y + ny * cross_screen - 0.15 * depth + ty * along

    base = lane_color(t, across, lane)
    selector = RNG.random()
    cumulative = 0.0
    for accent, chance in ACCENTS:
        cumulative += chance
        if selector < cumulative:
            base = mix(base, accent, RNG.uniform(0.30, 0.68))
            break
    base = shade_color(base, depth_normalized)

    if wisp:
        radius = RNG.uniform(0.32, 0.92) * (0.88 + 0.18 * max(-1, min(1, depth_normalized)))
        opacity = RNG.uniform(0.14, 0.46)
    else:
        perspective = 0.82 + 0.32 * ((max(-1, min(1, depth_normalized)) + 1) / 2)
        radius = (0.48 + RNG.random() ** 1.65 * 1.32) * perspective
        opacity = RNG.uniform(0.66, 0.99) * (0.82 + 0.18 * perspective)

    color = f"#{base[0]:02X}{base[1]:02X}{base[2]:02X}"
    circle = f'<circle cx="{px:.2f}" cy="{py:.2f}" r="{radius:.2f}" fill="{color}" opacity="{opacity:.2f}"/>'
    return depth, circle


def generate_particles() -> list[str]:
    particles: list[tuple[float, str]] = []

    for _ in range(7200):
        t = RNG.random()
        if RNG.random() < 0.55:
            across = RNG.uniform(-1.0, 1.0)
        else:
            across = max(-1.0, min(1.0, RNG.gauss(0, 0.54)))
        particles.append(particle(t, across, max(-1.0, min(1.0, RNG.gauss(0, 0.48)))))

    # Three colored lanes rotate with the ribbon instead of sitting flat on it.
    lanes = (
        ("core", 0.00, 0.09, 1500),
        ("gold", -0.10, 0.14, 1250),
        ("violet", -0.55, 0.15, 1000),
        ("cyan", 0.58, 0.14, 800),
    )
    for lane, center, spread, count in lanes:
        for _ in range(count):
            t = RNG.uniform(0.025, 0.975)
            across = max(-1.0, min(1.0, RNG.gauss(center, spread)))
            particles.append(particle(t, across, RNG.gauss(0, 0.22), lane=lane))

    # Sparse depth dust makes the outer silhouette soft without flattening it.
    for _ in range(1050):
        t = RNG.random()
        side = -1 if RNG.random() < 0.5 else 1
        across = side * RNG.uniform(0.95, 1.55)
        particles.append(particle(t, across, RNG.gauss(0, 0.78), wisp=True))

    # Bright spherical heads extend just beyond the hexagon at both ends.
    for t, color in ((0.002, (103, 247, 236)), (0.998, (255, 209, 94))):
        x, y, _, _ = frame_at(t)
        for _ in range(150):
            angle = RNG.random() * math.tau
            distance = abs(RNG.gauss(0, 6.0))
            px = x + math.cos(angle) * distance
            py = y + math.sin(angle) * distance
            depth = RNG.uniform(-1, 1)
            radius = RNG.uniform(0.50, 2.15) * (0.88 + 0.20 * (depth + 1) / 2)
            tint = mix(color, (255, 255, 255), RNG.uniform(0.0, 0.35))
            particles.append(
                (
                    depth * 30,
                    f'<circle cx="{px:.2f}" cy="{py:.2f}" r="{radius:.2f}" '
                    f'fill="#{tint[0]:02X}{tint[1]:02X}{tint[2]:02X}" opacity="{RNG.uniform(0.62, 1):.2f}"/>',
                )
            )

    particles.sort(key=lambda item: item[0])
    return [circle for _, circle in particles]


def main() -> None:
    particles = "\n      ".join(generate_particles())
    glow_points = " ".join(
        f"{x:.2f},{y:.2f}" for x, y in (point_at(index / 140) for index in range(141))
    )
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512" role="img" aria-labelledby="title desc">
  <title id="title">SandAdmin volumetric particle favicon</title>
  <desc id="desc">A twisted volumetric particle S crossing a cyan hexagon on a transparent background.</desc>
  <defs>
    <linearGradient id="hexGradient" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#7CFAF3"/>
      <stop offset="0.48" stop-color="#22D0F7"/>
      <stop offset="1" stop-color="#168DE8"/>
    </linearGradient>
    <linearGradient id="sGlowGradient" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#68EDE4"/>
      <stop offset="0.22" stop-color="#FFD16B"/>
      <stop offset="0.55" stop-color="#B765E5"/>
      <stop offset="0.80" stop-color="#F2A542"/>
      <stop offset="1" stop-color="#FFD86F"/>
    </linearGradient>
    <filter id="hexGlow" x="-20%" y="-20%" width="140%" height="140%" color-interpolation-filters="sRGB">
      <feGaussianBlur stdDeviation="6" result="blur"/>
      <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
    <filter id="particleGlow" x="-100%" y="-100%" width="300%" height="300%" color-interpolation-filters="sRGB">
      <feGaussianBlur stdDeviation="7"/>
    </filter>
    <g id="particleCloud">
      {particles}
    </g>
  </defs>

  <path d="M256 29 448 140 448 372 256 483 64 372 64 140Z"
        fill="none" stroke="url(#hexGradient)" stroke-width="34"
        stroke-linejoin="round" filter="url(#hexGlow)"/>
  <polyline points="{glow_points}" fill="none" stroke="url(#sGlowGradient)" stroke-width="38"
            stroke-linecap="round" stroke-linejoin="round" opacity="0.11" filter="url(#particleGlow)"/>
  <circle cx="397" cy="69" r="10" fill="#71F0E7" opacity="0.30" filter="url(#particleGlow)"/>
  <circle cx="110" cy="440" r="11" fill="#FFD269" opacity="0.34" filter="url(#particleGlow)"/>
  <use href="#particleCloud"/>
</svg>
'''
    (ROOT / "sandadmin-favicon-master.svg").write_text(svg, encoding="utf-8")


if __name__ == "__main__":
    main()
