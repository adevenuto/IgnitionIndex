# Ignition Index — design system

**Read this first.** This project is the rebrand and redesign of **CarTrak**, which is now
**Ignition Index**. If you are an agent working in the CarTrak codebase: the existing design
system is being *replaced*, not extended. Do not preserve the old red-on-white, rounded-card,
soft-shadow look. Do not carry over `CarTrak` as a product name, the red `#c9152b`-family
accent, the pill/rounded-`--radius-lg` geometry, or the white floating cards. Where old styles
conflict with this document, this document wins.

> **2026-10 revision: softened blueprint.** The steel accent, the navy shell, the checker strip
> and the gauge stay. The geometry and type have changed:
> - the `+` corner marks are retired
> - cards, controls and chips are rounded, with one soft elevation
> - type is Inter
> - the primary button is near-black
> - icon tiles may carry one of four tile hues
>
> §2–§4 have been rewritten to match. The reference artboards below predate this revision; take
> their layout, not their geometry.

Reference designs in this project:

- `Ignition Index.dc.html` — the vehicle-detail screen and the canonical app shell.
- `Ignition Index Landing.dc.html` — the marketing page, same language applied to a public site.

Both are built on the **Industry** design system, which is the token source of truth and lives at
`_ds/industry-073689bb-ad62-4044-9f1d-c9beedebbcc6/styles.css`. Link that stylesheet and use its
CSS variables. Never hard-code a value the tokens already carry.

---

## 1. Brand

- **Name:** Ignition Index. Never "CarTrak", never "Ignition-Index", never "II" in UI copy.
- **Mark:** `assets/ignition-index-logo.png` — a checkered-flag `i` inside a gear, navy ink on
  transparency. On light grounds use it as-is. On the navy shell use
  `assets/ignition-index-logo-light.png`, a derived variant in which the navy ink is lifted to
  paper (`#f2f2f3`) and the light fills drop out, so the mark reads as a paper line drawing. Never
  put the mark on a filled chip or box.
- **Lockup:** logo chip + the wordmark set in two condensed uppercase lines, `IGNITION` over
  `INDEX`, `var(--font-heading)` at 20px/1.05, letter-spacing `0.02em`.
- **Checker motif:** the flag pattern is reused as a 10px-tall texture strip separating the navy
  header from the content below. It is decoration, used exactly once per page.
  ```css
  height: 10px;
  background: #101a2b;
  background-image: conic-gradient(rgba(242,242,243,.16) 90deg, transparent 0 180deg,
                                   rgba(242,242,243,.16) 0 270deg, transparent 0);
  background-size: 10px 10px;
  ```

## 2. Color

Take everything from the Industry tokens. Two roles are **additions** this product needs and the
token sheet does not carry; if you formalize the system, add them as named variables rather than
re-typing the hexes.

| Role | Value | Use |
| --- | --- | --- |
| `--color-bg` | `#f2f2f3` | Content ground. Also the paper ink of the light logo variant. |
| `--color-neutral-100` | `#f5f5f8` | Card and plate fills on the content ground. |
| `--color-text` | `#1d1f20` | Body and heading ink. |
| `--color-accent` | `#5980a6` | Steel. Active nav, links, focus, progress, "on interval" gauges. |
| `--primary` | `#1d1f20` | Near-black. The primary button and the selected segment of a tab control. |
| `--color-accent-700` | `#416180` | Accent used at paragraph size (the base accent is only 3:1). |
| `--color-accent-900` | `#1d2d3d` | Full reversed field (the landing's Fleet section). |
| `--color-divider` | `#1d1f20` @ 16% | Every hairline. |
| **`--shell-navy`** *(new)* | `#16233a` | Top bar, footer, active vehicle chip, Fleet price card. |
| **`--shell-navy-deep`** *(new)* | `#101a2b` | Left rail, checker strip ground. |
| **`--status-due`** *(new)* | `#c08a2e` | Amber. Gauge ≥ 75% of interval. |
| **`--status-overdue`** *(new)* | `#b4442f` | Rust. Gauge ≥ 100%. Also notification badges. |

**Bright values are for ink you can see, not ink you have to read.** Steel `--color-accent` and
amber `--status-due` clear only ~3:1 against the content ground — fine for gauge arcs, borders,
fills, icons and type at 20px+, but they fail at small sizes. Any text under ~14px uses the deep
step instead: on interval → `--color-accent-700` (#416180), due soon → `#8a6a2e`, overdue →
`#b4442f` (already dark enough). The status *tag* keeps the bright color on its **border** and
takes the deep step for its **text**, so the color coding survives intact.

The two status colors are the **only** colors outside the steel accent, and they are functional —
they encode service state and nothing else. Do not introduce a green "all good" state; on
interval is steel. Do not tint charts, avatars or categories.

**Tile hues** *(2026-10)*. Icon tiles, and only icon tiles, may take one of four hues, so that
a row of methods or features can be told apart at a glance: `--tile-steel`, `--tile-violet`,
`--tile-teal` and `--tile-slate`. Each is a pair: a light fill (`--tile-*-bg`) and an icon ink
(`--tile-*-ink`) that clears 3:1 on it. A tile hue never colours text, a border, a chart or a
status, and amber and rust are never tile hues: they still mean service state and nothing else.

On the navy shell, text is `#f2f2f3` at full opacity for primary, `rgba(242,242,243,.72)` for
secondary nav items, `rgba(242,242,243,.38–.55)` for eyebrow labels and metadata only.

## 3. Type

`var(--font-heading)` and `var(--font-body)` are both **Inter** *(2026-10; was Barlow Condensed
and Barlow)*. Headings are told apart by weight and size, not by a condensed face, so most of
the uppercase below is now reserved for eyebrows. Headings are sentence case with a slight
negative tracking.

| Use | Spec |
| --- | --- |
| Page H1 (landing) | heading, `clamp(46px,6vw,76px)`, `line-height:.98`, `letter-spacing:-.02em`, uppercase |
| Section H2 | heading, `clamp(32px,3.4vw,46px)`, uppercase |
| Vehicle name | heading, 38px, sentence case (it is a pet name, not a label) |
| Card / gauge title | heading, 19–24px, sentence case |
| Big number (odometer, %) | heading, 26–52px, tabular feel, unit in body font at ~50% the size |
| Eyebrow / kicker | body 700, 10px, `letter-spacing:.14em`, uppercase, `--color-neutral-700` |
| Body | body 400, 14–18px, `line-height:1.55`, `text-wrap:pretty` |
| Metadata | body 400, 11–13px, `--color-neutral-700` |

Uppercase condensed is for *chrome and headlines*. Never uppercase a vehicle name, a service name,
or body copy.

## 4. Geometry

*(Rewritten 2026-10.)* Rounded, from one scale: `--radius-sm` 8px for chips, tags and icon tiles
inside a control; `--radius-md` 12px for buttons, inputs and selects; `--radius-lg` 16px for
cards, panels and dialogs; `--radius-full` for pill tabs, badges and avatars.

Every card, figure and framed region is a **frame** (`BlueprintFrame`): a hairline border, a
near-white fill and `--shadow-card`, a soft single-step elevation. The `+` registration marks
are retired. Dialogs and sheets take `--shadow-overlay`. Nothing else casts a shadow: no shadows
on buttons, inputs or rows.

Photographs still go through `class="duotone"` so a wall of user-uploaded car pictures does not
fight the interface. They take the frame's radius.

Spacing comes from `--space-1 … --space-8` (3.4 / 6.8 / 10.2 / 13.6 / 20.4 / 27.2px — the 0.85×
density is already baked in). Section rhythm on the landing page is 96px between sections, 78px
inside a hero.

Icons: **Lucide, stroke-width 1.5**, sized 14–19px in chrome. No filled icons, no icon fonts.

## 5. App shell

```
┌──────────────────────────────────────────────── 66px navy top bar ──┐
│ [logo chip] IGNITION/INDEX   [Log entry ▸]  [search]  ···  [bell] [user ▾] │
├──── 10px checker strip ─────────────────────────────────────────────┤
│ 216px  │                                                            │
│ navy   │   content ground (--color-bg), 26px / 30px padding         │
│ rail   │                                                            │
└────────┴────────────────────────────────────────────────────────────┘
```

- **Top bar** (`--shell-navy`, 66px): brand lockup at far left in a 194px column; then the
  **Log entry** primary button (this is the app's one primary action and it lives in the chrome,
  not floating in the content); then a search field as an outlined box on navy. Right side:
  notification bell with an overdue-rust count badge, a hairline divider, then the user block —
  34px square accent avatar with initials, name, and a scope line beneath it
  ("Personal garage" / the fleet or group name). The user block is the disclosure for
  **Settings**, **Billing** and **Log out**; there is no Settings item in the rail.
- **Left rail** (`--shell-navy-deep`, 216px): an eyebrow label, then **Garage** (with a vehicle
  count), **History**, **Insights**. The active item is a solid `--color-accent` block with
  `#0d1626` ink — square, full-width, no indicator bar. Below it a **Due next** list (three
  items, status dot + name + percent). *(2026-10: the **Upgrade to Fleet** cell that was
  pinned to the bottom is removed, along with every Fleet mention in the product and on the
  landing page, until Fleet is a real initiative.)* Hover on an inactive item is `rgba(89,128,166,.22)`.
- **Content**: breadcrumb → vehicle switcher row → vehicle header card → gauges.

## 6. The gauge

The gauge is the product's one distinctive object; get it right before anything else.

- **Semantics:** the percentage is *how much of the interval has elapsed*, so it counts **up**
  toward due. 0% = just serviced, 100% = due now, >100% = overdue. Never invert this into
  "life remaining".
- **Basis:** each service's interval is configured per vehicle as a mileage figure **or** a
  calendar period (and, later, whichever comes first). The basis is printed in the card's top-left
  eyebrow (`5,000 mi`, `12 mo`) so the number is never unexplained. Configuration happens in a
  modal reached from **Intervals** on the vehicle header.
- **Form:** a 240° analogue dial — tick marks, a tapered needle, a hub, and a thick progress arc
  in the status color. Geometry, on a `0 0 120 104` viewBox:
  - arc path `M21.9 84 A44 44 0 1 1 98.1 84`, arc length **184.3**, stroke 6–9px
  - track stroke `rgba(29,31,32,.14)`; progress `stroke-dasharray: (pct/100 × 184.3) 400`
  - 11 ticks from `rotate(-120 60 62)` to `rotate(120 60 62)` in 24° steps
  - needle `rotate(-120 + pct/100 × 240, 60, 62)`, clamped at 100% so an overdue needle pegs
  - the viewBox is cropped to `0 0 120 92` so there is no dead space under the arc
  - the percentage is **not** inside the dial and **not** an SVG `<text>` element — the mouth
    between the hub and the arc is too shallow to hold type at readout size, and a `{{ }}` hole
    inside `<text>` renders an SVG-namespaced `<span>` that paints nothing. It is an ordinary
    HTML `<div>` in `var(--font-heading)` sitting directly **below** the dial, at roughly 1.5×
    the service-name size (34px on a 150px dial, 52px on a 300px one). Name and remaining
    distance/time follow beneath it
- **Status:** `< 75%` steel `On interval` · `≥ 75%` amber `Due soon` · `≥ 100%` rust `Overdue`.
  The status word renders as a `.tag .tag-outline` in the status color in the card's top-right.
- **Ordering:** the gallery order is **fixed** (user-defined). Overdue items are *not* reordered
  to the front — they change color and pick up the warning tag in place. The rail's **Due next**
  list is where urgency gets sorted.
- **The gallery** (decided): three user-**pinned** dials at large size, followed by the remaining
  services as a two-column list of miniature dials + name + percent. "Pinned" is a user choice,
  not urgency, which is what keeps it consistent with the fixed-order rule. Keep the list's item
  count **even** so the two-column grid never ends on a half-empty row; draw its rules as `gap:1px`
  over a divider-colored grid background rather than per-row borders, so the last row does not
  double up against the card's own border.

## 7. Vehicle header

A single blueprint card split `420px | 1fr`: duotone photograph left, data right. The data side
carries the vehicle name (38px, with a small square color chip — the user's chosen identity color,
the one place a non-system color is allowed, and only as a 10px square), the year/make/model line,
**Edit** and **Intervals** as `.btn .btn-secondary`, a hairline, then a four-cell metric row —
**Odometer**, **Last reading**, **Avg / month**, **Services due** (rust when non-zero) — and the
VIN as the last line in 11px letterspaced metadata.

Above it, the **vehicle switcher**: a horizontal row of 150px-wide blueprint chips, one per
vehicle, each showing the identity square, the pet name in condensed 17px, and the model beneath.
The active chip inverts to `--shell-navy` with paper ink. A dashed 54px square at the end adds a
vehicle. This row is the scale seam — see below.

## 8. Fleet (paid tier)

Fleet is a paid tier that should not require a new visual language. Design for it now:

- **The switcher row is the seam.** Four chips fit; forty do not. At fleet scale the same row
  becomes a filter bar — group selector, search, status filter — over a vehicle *table*
  (`.table` from Industry) whose rows carry miniature gauges. The vehicle-detail screen itself is
  unchanged.
- **Scope lives in the user block.** The line under the user's name ("Personal garage") becomes
  the active group ("Northside yard · 24 vehicles"), and switching scope happens there.
- **Roles:** drivers get the Log-entry button and their assigned vehicle only; managers get
  intervals, costs and the roll-up. Same shell, fewer items in the rail.
- **Roll-up screens** (fleet-wide overdue, cost per mile, upcoming week) belong under Insights and
  reuse the gauge as their unit.
- **Upsell surfaces** are the rail's bottom cell and the landing page's Fleet section. Keep them
  to one each; the free tier should not feel nagged.

## 9. Copy

Plain, mechanical, no exclamation. Say the number and the unit. "1,450 mi to go", not "You're
doing great!". Service names are sentence case and specific ("Brake pads, front", not "Brakes").
Vehicle names are whatever the owner typed. The product's own vocabulary: **garage**, **vehicle**,
**service**, **interval**, **gauge**, **reading**, **entry**, **fleet**, **group**.

## 10. Checklist before you ship a screen

- [ ] Industry stylesheet linked; no hard-coded hex, font name or spacing px that a token carries
- [ ] Navy shell + checker strip + paper content ground
- [ ] Logo: dark variant on light grounds, `-light` variant on navy — never on a filled chip
- [ ] Every card is a frame (`BlueprintFrame`): rounded `--radius-lg`, hairline, `--shadow-card`, no corner marks
- [ ] Radii from the scale (8 / 12 / 16 / full); shadows only on frames and overlays
- [ ] Photographs wrapped in `duotone`
- [ ] Only steel, amber and rust, plus tile hues on icon tiles; amber/rust only ever mean service state
- [ ] Lucide icons at stroke-width 1.5
- [ ] Gauge percent counts up toward due, basis printed, order fixed
- [ ] Focus rings are `2px solid var(--color-accent)` with `2px` offset — never browser default
- [ ] 10–13px text sits at `--color-neutral-700` or darker (neutral-600 fails 4.5:1 on the card fill)
