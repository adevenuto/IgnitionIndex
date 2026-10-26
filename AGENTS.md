## ⚠ Design: read `docs/design_system/IGNITION-INDEX-DESIGN-SYSTEM.md` before any UI work

> This product was **CarTrak** and is now **Ignition Index**. It is mid-rebrand: the name, the
> visual system, and the design tokens have all been replaced.
>
> `docs/design_system/IGNITION-INDEX-DESIGN-SYSTEM.md` is the **binding** spec for anything with a
> visual surface — components, screens, emails, marketing pages. Read it in full before writing
> UI, and treat it as authoritative where it conflicts with existing code. Existing components
> that predate it are legacy, not precedent: do not copy their patterns forward.
>
> **Do not** reintroduce, extend, or pattern-match against the old CarTrak system: the CarTrak
> name, the red accent, or filled icons.
>
> **2026-10 softening.** The blueprint geometry has been softened: the `+` corner marks are
> retired, and cards, controls and chips now use a radius scale and one soft elevation. Type is
> Outfit, the primary button is near-black, and icon tiles may take one of the four tile hues.
> Pill tabs and pill badges are allowed. The chrome is light — the navy top bar, rail and
> checker strip are retired — and default buttons and inputs are 48px. A colour pass followed:
> the brand is vivid blue (also the primary button), each entry type has a lane hue, vehicles
> show their paint colour, and soft glows are allowed on grounds — see the doc's §2.
>
> **Themes.** Colour now comes from a theme the user picks in Settings → Appearance
> (`App\Enums\Theme`, `resources/css/ignition/themes.css`, rendered on `<html data-theme
data-scheme>`). **Volt** (charcoal + lime) is the default; Indigo Night, Lagoon, Neon, Daylight
> and Sandstone are the others. Never hard-code a colour a theme should own: use the tokens, and
> check new UI in at least one dark and one light theme. The doc's §2–§4 describe the current rules; where an
> older comment in the code cites the square, mark-framed geometry, the doc wins.
>
> **Do** take every color, font, space and radius from the CSS variables in `styles.css`
> (`var(--color-*)`, `var(--font-*)`, `var(--space-*)`). Never hard-code a hex, a font name, or a
> px value the tokens already carry. The design doc lists the two roles the token sheet does not
> carry (the light chrome, service-status amber/rust) and exactly where each is allowed.
>
> If a design decision is not covered by the doc, ask rather than inventing one.
