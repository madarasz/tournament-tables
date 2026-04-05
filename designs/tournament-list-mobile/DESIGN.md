# Design System Document: Tactical Intelligence & Tournament Operations

## 1. Overview & Creative North Star: "The Digital Command Center"

This design system is engineered to move beyond a standard "gaming dashboard" into a high-stakes, tactical command interface. The Creative North Star is **The Digital Command Center**: an aesthetic that prioritizes rapid data ingestion, mission-critical clarity, and a sense of "active" technology.

Unlike generic tournament platforms that rely on heavy borders and loud gradients, this system utilizes **Tonal Depth** and **Luminous Accents**. We break the "template" look by employing intentional asymmetry in layouts—allowing data-heavy tables to sit alongside wide, breathing hero sections—and by using emerald green not just as a color, but as a light source.

---

## 2. Colors & Atmospheric Depth

The palette is built on a foundation of "Nervous Blacks" and "Deep Charcoals," allowing the Emerald Green (`primary`) to cut through the interface with surgical precision.

### Surface Hierarchy & Nesting
Instead of a flat grid, treat the UI as a series of physical layers.
- **Base Layer:** `surface` (#0e0e0e) – The void. Use this for the overall background.
- **Sectioning:** `surface_container_low` (#131313) – Use this to define large regional blocks.
- **Actionable Cards:** `surface_container` (#1a1a1a) – Nested inside low-tier sections to create a "lift."
- **High-Priority Modals:** `surface_container_highest` (#262626) – The top-most layer for focused interaction.

### The "No-Line" Rule
**Explicit Instruction:** Do not use 1px solid borders to separate sections. Boundaries must be defined solely through background color shifts. For example, a tournament bracket (`surface_container`) should sit on a background of `surface_container_low`. The eye should perceive the edge via the change in value, not a stroke.

### The "Glass & Gradient" Rule
To achieve a "high-tech" feel, use **Glassmorphism** for floating navigation or hovering HUD elements.
- **Token:** `surface_variant` at 60% opacity with a `20px` backdrop-blur.
- **Signature Glow:** For primary CTAs, apply a subtle outer glow using `primary` (#7ff3be) at 15% opacity with a `16px` blur to simulate a powered-on LED.

---

## 3. Typography: The Editorial Edge

We use **Inter** exclusively. Its neutral, geometric construction allows us to push the scale to extremes to create a high-end editorial feel.

- **The Display Scale (`display-lg` to `display-sm`):** Use these for tournament titles or "Winner" announcements. These should be set to `font-weight: 800` (Extra Bold) with a slight tracking decrease (`-0.02em`) to feel like a tactical headline.
- **The Functional Scale (`title-md` to `label-sm`):** These are the workhorses. Labels should always be in `uppercase` with `letter-spacing: 0.05em` to mimic military technical manuals.
- **Visual Hierarchy:** Use `on_surface_variant` (#adaaaa) for secondary metadata to ensure the `primary` green and pure `white` text draw the eye to the most critical data points first.

---

## 4. Elevation & Depth: Tonal Layering

Traditional shadows feel "dirty" in a dark tactical UI. We move away from them in favor of light-based elevation.

- **The Layering Principle:** Stack `surface_container_lowest` (#000000) for deep inset areas (like a terminal or chat log) and `surface_container_high` (#20201f) for elements that need to pop.
- **Ambient Shadows:** If a shadow is required for a floating modal, use a tinted shadow: `rgba(5, 150, 105, 0.08)` (a hint of the emerald primary) with a `40px` blur. This creates an "atmospheric glow" rather than a drop shadow.
- **The "Ghost Border" Fallback:** If accessibility requires a container edge, use `outline_variant` (#484847) at **15% opacity**. It should be felt, not seen.

---

## 5. Component Logic

### Buttons (Tactical Triggers)
- **Primary:** Background: `primary_container`. Text: `on_primary_container`. Shape: `md` (0.375rem). Use a subtle `1px` top-inner-border of `primary` to create a "beveled" tech look.
- **Secondary:** Background: `transparent`. Border: `Ghost Border` (15% opacity). Text: `primary`.
- **Tertiary:** Text: `on_surface_variant`. No background. High-contrast hover state to `white`.

### Cards & Tournament Brackets
- **Forbid Divider Lines.** Use vertical white space (`spacing.6` or `spacing.8`) to separate tournament rounds.
- Individual match cards should use `surface_container` with a `2px` left-accent-border of `primary` only if the match is "Live."

### Input Fields
- **State:** Unfocused inputs should be `surface_container_lowest` with a `0.5px` `outline_variant`. 
- **Active State:** The border glows with `primary` and a `4px` outer blur. Text remains `white`.

### Tactical "HUD" Elements (New Component)
- **Status Pips:** Small circles using `primary` (Online), `error` (Match Issue), or `tertiary` (Paused). These should have a subtle pulse animation.
- **Data Grids:** Use `label-sm` for all headers, colorized in `on_surface_variant`.

---

## 6. Do’s and Don’ts

### Do:
- **Use Asymmetry:** Place a large `display-md` title on the left and a dense data cluster on the right.
- **Embrace the Dark:** Allow large areas of `surface` (#0e0e0e) to exist. Negative space in a dark theme implies premium quality.
- **Use Micro-Interactions:** Buttons should feel "mechanical." A slight `0.98` scale-down on click adds to the tactical feel.

### Don’t:
- **Don’t use Rounded-Full:** Avoid pill-shaped buttons unless they are status chips. Stay within the `sm` to `md` (0.125rem - 0.375rem) range to maintain a "structured" look.
- **Don’t use Grey Shadows:** Shadows must be black or tinted with Emerald Green. Grey shadows will make the UI look "muddy."
- **Don’t Overuse the Primary Green:** If everything is green, nothing is important. Use it strictly for "Active," "Live," or "Primary Action." Metadata should remain neutral.

### Accessibility Note:
While we use low-contrast borders for aesthetics, ensure all text-to-background ratios meet WCAG AA standards by utilizing the high-contrast `on_surface` (White) for all body copy.