import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

/*
 * LOCAL PATCH — restyled to the Ignition Index design system.
 *
 * Every shadcn variant NAME is preserved so no consuming page changes; each maps
 * onto an Industry .btn variant instead (styles.css lines 140-162):
 *
 *   default   -> .btn-primary    (steel fill, paper ink)
 *   outline   -> .btn-secondary  (hairline border, ink wash on hover)
 *   secondary -> .btn-secondary  (same; the ink-filled variant is gone)
 *   ghost     -> .btn-ghost      (steel text, steel wash on hover)
 *   surface   -> card-filled icon button (added; no Industry equivalent)
 *   destructive -> outlined rust (see below)
 *   link      -> no Industry equivalent; left as shadcn shipped it
 *
 * Buttons are set in the heading role at 14px/600, sentence case. (Before the
 * 2026-10 softening this was condensed uppercase; Inter carries no condensed
 * face, and uppercase Inter at button size shouts.)
 *
 * Geometry per design doc section 4 (softened 2026-10): the control radius,
 * hairline, no shadow, and no press scale. Nothing in the system deforms, so
 * the press feedback is a background step instead, which is what Industry's
 * own .btn:active does.
 *
 * The primary fill is near-black ink since 2026-10, not steel: steel remains
 * the brand on nav, links, focus and gauges, and the one "do this" button
 * reads as the action on any ground.
 *
 * destructive is an OUTLINE rather than a fill on purpose. Rust is a service
 * status colour (section 2), and filling a button with it puts a status colour
 * somewhere that is not a service state. Outlined, it still reads as danger
 * without claiming a large area.
 *
 * cursor-pointer is on the base rather than left to the element: `as-child` is
 * used throughout, so a Button is as often a <label> (the photo picker) or an
 * <a> as it is a <button>, and only the shared class reaches all three. Disabled
 * buttons are unaffected — they already carry pointer-events-none.
 *
 * A future `npx shadcn-vue add button` will revert this file.
 */
export const buttonVariants = cva(
  "inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md border font-display text-[14px]/[1.2] font-semibold transition-colors duration-[var(--dur-fast)] ease-standard disabled:pointer-events-none disabled:opacity-45 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        // Ink lifts toward slate on hover and press: a step you can see on a
        // near-black fill, where darkening further would not register.
        default:
          "border-primary bg-primary text-primary-foreground hover:border-[color-mix(in_oklab,var(--primary)_84%,white)] hover:bg-[color-mix(in_oklab,var(--primary)_84%,white)] active:bg-[color-mix(in_oklab,var(--primary)_74%,white)]",
        destructive:
          "border-destructive bg-transparent text-destructive hover:bg-destructive/10 active:bg-destructive/18 focus-visible:ring-destructive/20",
        outline:
          "border-border bg-transparent text-foreground hover:bg-hover-surface active:bg-foreground/14",
        secondary:
          "border-border bg-transparent text-foreground hover:bg-hover-surface active:bg-foreground/14",
        ghost:
          "border-transparent px-(--space-1) text-accent hover:bg-accent/10 active:bg-accent/18",
        surface:
          "border-border bg-card text-foreground hover:bg-hover-surface",
        link: "border-transparent text-accent underline-offset-4 hover:underline",
      },
      size: {
        "default": "h-11 px-[22px] has-[>svg]:px-4",
        "sm": "h-9 gap-1.5 px-4 text-sm has-[>svg]:px-3",
        "lg": "h-14 px-[26px] text-h3 has-[>svg]:px-5",
        "icon": "size-9",
        "icon-sm": "size-8",
        "icon-lg": "size-10",
        "icon-tap": "size-11",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
