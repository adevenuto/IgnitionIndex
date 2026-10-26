# Ignition Index — design system

**Read this first.** This project is the rebrand and redesign of **CarTrak**, which is now
**Ignition Index**. If you are an agent working in the CarTrak codebase: the existing design
system is being *replaced*, not extended. Do not preserve the old red-on-white, rounded-card,
soft-shadow look. Do not carry over `CarTrak` as a product name, the red `#c9152b`-family
accent, the pill/rounded-`--radius-lg` geometry, or the white floating cards. Where old styles
conflict with this document, this document wins.

> **2026-10 revision: softened blueprint.** The steel accent and the gauge stay. The geometry,
> type and chrome have changed:
> - the `+` corner marks are retired
> - cards, controls and chips are rounded, with one soft elevation
> - type is Inter
> - the primary button is near-black
> - icon tiles may carry one of four tile hues
> - the chrome is light: the navy top bar, rail and checker strip are retired (§1, §5)
> - default buttons and inputs are 48px
>
> §1–§5 have been rewritten to match. The reference artboards below predate this revision; take
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
  transparency. Every ground is light since 2026-10, so it is always used as-is; the paper
  variant (`ignition-index-logo-light.png`) is retired with the navy chrome. Never put the mark on
  a filled chip or box.
- **Lockup:** logo + the wordmark in two uppercase lines, `IGNITION` over `INDEX`, the heading
  face bold at 18px/1.05, letter-spacing `0.01em`, in ink.
- **Checker motif:** *retired 2026-10.* The 10px checkered strip under the navy header went with
  the navy chrome; the flag lives on in the mark.

## 2. Color

Take everything from the Industry tokens. Two roles are **additions** this product needs and the
token sheet does not carry; if you formalize the system, add them as named variables rather than
re-typing the hexes.

| Role | Value | Use |
| --- | --- | --- |
| `--color-bg` | `#f2f2f3` | Content ground. Also the paper ink of the light logo variant. |
| `--color-neutral-100` | `#f5f5f8` | Card and plate fills on the content ground. |
| `--color-text` | `#1d1f20` | Body and heading ink. |
| `--color-accent` | `#2f6bed` | Brand blue *(2026-10; was steel `#5980a6`)*. Primary button, active nav, links, focus, progress, "on interval" gauges. |
| `--primary` | `var(--color-accent)` | The primary button and selected pills. |
| `--color-accent-700` | `#2253c4` | Accent at paragraph size: links, eyebrows, on-interval text. |
| `--color-accent-900` | `#183570` | Deepest brand step. |
| `--color-divider` | `#1d1f20` @ 16% | Every hairline. |
| **`--chrome-*`** *(2026-10)* | near-white + ink | Top bar, rail, drawer, homepage header and footer. Replaces `--shell-navy`. |
| **`--status-due`** *(new)* | `#c08a2e` | Amber. Gauge ≥ 75% of interval. |
| **`--status-overdue`** *(new)* | `#b4442f` | Rust. Gauge ≥ 100%. Also notification badges. |

**Bright values are for ink you can see, not ink you have to read.** The bright accent steps and
amber `--status-due` clear only ~3:1 against the content ground — fine for gauge arcs, borders,
fills, icons and type at 20px+, but they fail at small sizes. Any text under ~14px uses the deep
step instead: on interval → `--color-accent-700` (#2253c4), due soon → `#8a6a2e`, overdue →
`#b4442f` (already dark enough). The status *tag* keeps the bright color on its **border** and
takes the deep step for its **text**, so the color coding survives intact.

The two status colors are functional: they encode service state and nothing else. Do not
introduce a green "all good" state; on interval is the brand blue.

**Themes** *(2026-10)*. Colour is themable. Each person picks a theme in Settings →
Appearance; the default is **Volt** (charcoal with an electric lime). A theme is a block of token
overrides in `resources/css/ignition/themes.css`, keyed on `<html data-theme>`, with what every
dark theme shares on `data-scheme='dark'`. Dark themes invert the neutral and accent ramps (100 is
a deep tint to sit on, 700 a light ink to read), lift the status and lane inks, and use a deeper
elevation. Themes: Volt, Indigo Night, Lagoon, Neon (dark), Daylight, Sandstone (light). The
values below are **Daylight's**; read every role as "the theme's" rather than a fixed hex.

**Colour pass** *(2026-10)*. The palette widened so the light design carries colour. Each role
below has one job; none of them stands in for status.

- **Brand: vivid blue.** `--color-accent` is `#2f6bed` (ramp 100–900 in `product.css`, which
  overrides Industry's steel). It is the primary button, the active nav pill, the selected
  state, links, focus, progress and the on-interval gauge. White on it is ~4.7:1. Photos still
  wash into the old steel (`--duotone-ink`), because vivid blue would turn them electric.
- **Lane hues: one per kind of entry.** Fuel `--lane-fuel` (orange, `#ea6c1f`), Service
  `--lane-visit` (violet), Expense `--lane-expense` (emerald), Miles `--lane-odometer` (sky).
  Each has a `-bg` tint and an `-ink` deep step (4.5:1 on the tint) for text and icons. They
  identify an entry's type, on the Log entry tabs, History tiles and the review card's top edge,
  and nothing else. Fuel's orange is deliberately redder and brighter than the due amber. The
  lookup lives in `resources/js/lib/lanes.ts`.
- **Vehicle colour: the owner's paint colour, as identity.** A round swatch beside the name
  (ringed, so white and silver still show) and a tint on its tiles and empty photo well. No
  coloured bands or edges on cards and chips. Passed as `--vehicle`.
- **Selected states are tints, actions are solid.** The active nav item, settings item and
  vehicle chip are a light brand tint (`accent-100`, brand ink or a brand outline); solid blue is
  for buttons. **Log entry** alone is ink (`variant="ink"`): the foundational action, set apart
  from the blue so it stands out on every screen.
- **Tile hues:** `--tile-brand` for the product's own actions, `--tile-slate` for neutral ones.
  (Violet and teal tiles are retired: violet now means Service.)
- **Tints and glows:** the content ground is a breath cooler (`#f3f5f9`). Large, low-alpha
  radial washes (`.ii-hero-glow`, `.ii-soft-glow`) are allowed on grounds — the homepage hero,
  empty states, the sign-in screens — and tinted section bands on the homepage. They never sit
  behind text at low contrast and never animate.

In the chrome, text is `--chrome-ink` (ink) for primary and `--chrome-ink-secondary`
(`--color-neutral-700`) for inactive nav items, eyebrows and metadata.

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

*(Rewritten 2026-10: the chrome is light.)*

```
┌──────────────────────────────────────────────── 66px top bar (near-white) ──┐
│ [logo] IGNITION/INDEX   [■ Log entry]  [search]  ···  [bell] [user ▾]        │
├──── 1px hairline ───────────────────────────────────────────────────────────┤
│ 216px  │                                                                    │
│ light  │   content ground (--color-bg), 26px / 30px padding                 │
│ rail   │                                                                    │
└────────┴────────────────────────────────────────────────────────────────────┘
```

- **Top bar** (`--chrome-bar`, 66px, hairline under it): brand lockup at far left in a 194px
  column; then the **Log entry** button in the near-black primary (this is the app's one primary
  action and it lives in the chrome, not floating in the content); then a search field as an
  outlined box. Right side: notification bell with an overdue-rust count pill, a hairline
  divider, then the user block — a 34px round steel avatar with initials, name, and a scope line beneath it
  ("Personal garage" / the fleet or group name). The user block is the disclosure for
  **Settings**, **Billing** and **Log out**; there is no Settings item in the rail.
- **Left rail** (`--chrome-rail`, 216px, hairline to its right): an eyebrow label, then
  **Garage** (with a vehicle count), **History**, **Insights**. The active item is a near-black
  pill with paper ink — the same mark as the log sheet's selected tab. Below it a **Due next** list (three
  items, status dot + name + percent). *(2026-10: the **Upgrade to Fleet** cell that was
  pinned to the bottom is removed, along with every Fleet mention in the product and on the
  landing page, until Fleet is a real initiative.)* Hover on an inactive item is a faint ink
  wash (`--chrome-hover`). The mobile drawer is the same rail on the same light ground.
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
The active chip inverts to the near-black primary with paper ink. A dashed 54px square at the end adds a
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
- [ ] Light chrome (bar and rail edged with hairlines) + paper content ground; no navy, no checker strip
- [ ] Logo: the dark mark, as-is — never on a filled chip
- [ ] Every card is a frame (`BlueprintFrame`): rounded `--radius-lg`, hairline, `--shadow-card`, no corner marks
- [ ] Radii from the scale (8 / 12 / 16 / full); shadows only on frames and overlays
- [ ] Photographs wrapped in `duotone`
- [ ] Only steel, amber and rust, plus tile hues on icon tiles; amber/rust only ever mean service state
- [ ] Lucide icons at stroke-width 1.5
- [ ] Gauge percent counts up toward due, basis printed, order fixed
- [ ] Focus rings are `2px solid var(--color-accent)` with `2px` offset — never browser default
- [ ] 10–13px text sits at `--color-neutral-700` or darker (neutral-600 fails 4.5:1 on the card fill)
