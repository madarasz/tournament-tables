# Design System Document

## 1. Overview & Creative North Star: "The Tactical Command"
This design system moves away from the cluttered, "hobbyist" aesthetic often found in tabletop gaming apps. Instead, it adopts the persona of a **high-end military command interface**—clean, authoritative, and built for rapid data ingestion under pressure.

**The Creative North Star: The Tactical Command**
We treat the tournament floor as a theater of operations. The UI is not just a list of names; it is a professional venue display. We break the "template" look by using **intentional asymmetry** (e.g., heavy left-aligned typography contrasted with floating right-aligned data points) and **tonal depth** rather than structural lines. The interface should feel like a series of glowing tactical overlays resting on a deep, infinite void.

---

## 2. Colors & Surface Philosophy
The palette is rooted in deep naval foundations with high-performance green accents. It emphasizes legibility in low-light tournament environments.

### The Palette (Material Design 3 Logic)
*   **Background (The Void):** `#0b1326` (surface-dim). This is the absolute base.
*   **Primary (The Command):** `#8bd6b6` (primary) / `#065f46` (primary-container). Used for high-level navigation and state.
*   **Secondary (The Pulse):** `#4de082` (secondary). Reserved for action, success, and active round status.
*   **Neutral (The Intel):** `#dae2fd` (on-surface). Pure data, high contrast against the dark background.

### The "No-Line" Rule
**Explicit Instruction:** Do not use 1px solid borders to section content. Boundaries must be defined solely through background color shifts. To separate a list of matches, move from `surface-container-low` to `surface-container-highest`. A 1px line is a failure of tonal planning.

### Surface Hierarchy & Nesting
Treat the UI as a series of physical layers. Use the following tiers to create "nested" depth:
1.  **Level 0 (App Base):** `surface` (#0b1326)
2.  **Level 1 (Section Wrappers):** `surface-container-low` (#131b2e)
3.  **Level 2 (Active Cards/Rows):** `surface-container-high` (#222a3d)
4.  **Level 3 (Floating Overlays):** `surface-container-highest` (#2d3449)

### The "Glass & Gradient" Rule
To elevate the "Tactical Command" feel, use **Glassmorphism** for floating elements like Round Selectors. Apply `surface-variant` with a 60% opacity and a `20px` backdrop-blur. 
*   **Signature Texture:** Primary CTAs should use a subtle linear gradient from `primary` (#8bd6b6) to `primary-container` (#065f46) at a 135-degree angle to provide a "metallic" sheen.

---

## 3. Typography: Professional Venue Display
We use **Inter** exclusively. The goal is "Editorial Minimalism"—massive headlines paired with compact, utilitarian data labels.

*   **Display (The Round Marker):** `display-lg` (3.5rem). Use for "ROUND 4" or "TOP 8". Tracking: -2%.
*   **Headline (The Table Number):** `headline-lg` (2rem). Bold, authoritative.
*   **Title (The Player Name):** `title-md` (1.125rem). Medium weight.
*   **Label (The Faction/Stats):** `label-md` (0.75rem). All-caps with +5% letter spacing for a "technical readout" feel.

**Editorial Tension:** Create hierarchy by placing a `label-sm` (Faction) directly above a `title-lg` (Player Name). The extreme difference in scale establishes a premium, bespoke look.

---

## 4. Elevation & Depth: Tonal Layering
Traditional shadows are prohibited. Depth is achieved via light, not darkness.

*   **The Layering Principle:** Place a `surface-container-lowest` card on a `surface-container-low` section to create a soft "recessed" look.
*   **Ambient Shadows:** For floating modals, use a shadow color tinted with `on-surface` (#dae2fd) at 4% opacity. Blur radius must be at least `24px`.
*   **The "Ghost Border" Fallback:** If a divider is mandatory for accessibility, use the `outline-variant` (#3f4944) at **15% opacity**. It should be felt, not seen.

---

## 5. Components: Tactical Primitives

### Compact List Rows (The Matchup)
*   **Structure:** No dividers. Use a `2px` vertical accent of `primary` on the far left of the "Active Table" row.
*   **Spacing:** Use `spacing-3` (0.6rem) for internal padding to keep the venue display dense and professional.
*   **Background:** Use `surface-container-low` for even rows and `surface-container-lowest` for odd rows to create a "Zebra" effect without lines.

### Faction Pills (The Identifier)
*   **Style:** `round-full`. 
*   **Color:** Background `tertiary-container` (#465467) with `on-tertiary-container` text. 
*   **Interaction:** On hover, transition to `secondary-container` (#00b55d) to signal readiness.

### Round Selectors (The Timekeeper)
*   **Style:** Circular, floating glass elements.
*   **Visuals:** `surface-bright` (#31394d) with 40% opacity and a `1px` Ghost Border.
*   **Active State:** The active round should glow with a `secondary` (#4de082) outer-glow (blur 8px, opacity 30%).

### Input Fields & Selectors
*   **Style:** Minimalist underline. No box. 
*   **Focus State:** The underline transitions from `outline` to `secondary`.
*   **Error State:** Use `error` (#ffb4ab) only for the label and underline; never fill the box with red.

---

## 6. Do's and Don'ts

### Do
*   **Use Asymmetry:** Place the "Table Number" in a large display font on the left, and the "Time Remaining" in a small label font on the far right.
*   **Embrace Negative Space:** Use `spacing-10` (2.25rem) between major content blocks to allow the "Tactical" elements to breathe.
*   **Subtle Animation:** When a score changes, use a quick fade-in/scale-up (0.2s) to make the data feel "live."

### Don't
*   **Don't use 100% White:** Always use `on-surface` (#dae2fd). Pure white (#FFFFFF) will "bloom" too much against the deep navy background.
*   **Don't use Card Shadows:** Use background color steps (Surface Tiers) to define containers.
*   **Don't use Rounded Corners on Everything:** Use `round-sm` (0.125rem) for most containers to keep the "Technical/Industrial" feel. Reserve `round-full` only for status pills and action buttons.