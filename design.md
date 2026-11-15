# SHRCMS Design System

## Design direction

SHRCMS uses a clean, approachable healthcare-administration style: confident navy and blue establish trust, while white surfaces and pale-blue backgrounds keep records and forms easy to scan. The public pages add gradients, translucent surfaces, soft shadows, and rounded cards for a more promotional feel; staff and student workspaces use the same blue foundation in a more compact, task-focused layout.

The supplied clinic reference image follows the same broad direction: a vivid blue brand field, a bright white content canvas, prominent black headings, generous spacing, and rounded service cards. Its exact font and sampled colors cannot be confirmed from the image, so the values below use the website's existing CSS as the source of truth.

## Typography

- **Primary font:** Arial, Helvetica, sans-serif. This is the default across the public pages and most dashboard layouts.
- **Fallback variation:** The signup stylesheet uses Segoe UI, Tahoma, Geneva, Verdana, sans-serif in parts of its form. Prefer the primary stack when applying the system consistently.
- **Hierarchy:** Use bold, large headings for page and section titles; medium-weight labels and compact uppercase table headings for operational details; muted text for supporting descriptions.
- **Suggested scale:** 12–14px for utility text and form labels, 16–20px for card and section headings, and 30px or larger for primary page titles and key metrics.

## Color palette

| Token | Value | Use |
|---|---|---|
| Deep navy | `#081B2D` | Strong headings, dark hero areas, and brand contrast |
| Navy blue | `#102F4F` | Navigation and secondary dark surfaces |
| Brand blue | `#2D6FE8` | Primary actions, links, and active states |
| Bright blue | `#1765E8` | Dashboard actions and emphasis |
| Sky blue | `#66B2FF` | Highlights and gradient accents |
| Pale blue | `#EDF5FF` | Page backgrounds and soft panels |
| Light blue | `#DFEEFF` | Supporting surfaces and icon backgrounds |
| Body text | `#213F67` | Main copy on light surfaces |
| Muted text | `#5C7390` | Secondary labels and descriptions |
| White | `#FFFFFF` | Cards, forms, and primary text on dark surfaces |
| Health green | `#159B68` | Positive status and healthcare accents |
| Warm accent | `#E87927` | Warnings and secondary highlights |

Use the blues as the core palette. Green and orange are supporting status colors, not competing brand colors. Maintain strong contrast for text and interactive controls.

## Surfaces, shape, and spacing

- Use a pale-blue page canvas with white or lightly tinted content surfaces.
- Group related information into cards and panels; use soft, diffuse shadows to create separation.
- Public-facing content can use rounded corners and restrained blue gradients. Keep dashboard forms and tables simpler and denser.
- Favor generous gaps and padding over visual dividers. The project is configured for a **borderless interface**: do not use borders on cards, tables, controls, navigation, or decorative pseudo-elements.
- Preserve visible keyboard focus indicators (such as outlines or focus rings); they are accessibility cues, not decorative borders.

## Components and interaction

- **Primary action:** Solid or gradient brand blue with clear white text; use darker blue on hover.
- **Secondary action:** Pale-blue or white surface with dark-blue text, distinguished by fill and spacing rather than an outline.
- **Status:** Use green for success, orange for warnings, and red for errors, always pairing color with readable text.
- **Tables:** Separate rows with whitespace or subtle background changes rather than horizontal rules.
- **Navigation:** Use navy backgrounds, blue/sky active states, and clear spacing between links.

## Reusable CSS tokens

```css
:root {
  --color-navy: #081b2d;
  --color-navy-blue: #102f4f;
  --color-primary: #2d6fe8;
  --color-primary-bright: #1765e8;
  --color-sky: #66b2ff;
  --color-background: #edf5ff;
  --color-surface-soft: #dfeeff;
  --color-text: #213f67;
  --color-muted: #5c7390;
  --color-surface: #ffffff;
  --color-success: #159b68;
  --color-warning: #e87927;
  --font-sans: Arial, Helvetica, sans-serif;
}
```
