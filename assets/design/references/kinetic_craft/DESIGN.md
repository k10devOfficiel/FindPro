---
name: Kinetic Craft
colors:
  surface: '#f9f9ff'
  surface-dim: '#d3daef'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f1f3ff'
  surface-container: '#e9edff'
  surface-container-high: '#e1e8fd'
  surface-container-highest: '#dce2f7'
  on-surface: '#141b2b'
  on-surface-variant: '#5d3f3d'
  inverse-surface: '#293040'
  inverse-on-surface: '#edf0ff'
  outline: '#916f6c'
  outline-variant: '#e6bdb9'
  surface-tint: '#c0001f'
  primary: '#b8001d'
  on-primary: '#ffffff'
  primary-container: '#e11d2e'
  on-primary-container: '#fff8f7'
  inverse-primary: '#ffb3ae'
  secondary: '#9d4300'
  on-secondary: '#ffffff'
  secondary-container: '#fd761a'
  on-secondary-container: '#5c2400'
  tertiary: '#805000'
  on-tertiary: '#ffffff'
  tertiary-container: '#a16600'
  on-tertiary-container: '#fff9f5'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffdad7'
  primary-fixed-dim: '#ffb3ae'
  on-primary-fixed: '#410004'
  on-primary-fixed-variant: '#930015'
  secondary-fixed: '#ffdbca'
  secondary-fixed-dim: '#ffb690'
  on-secondary-fixed: '#341100'
  on-secondary-fixed-variant: '#783200'
  tertiary-fixed: '#ffddb8'
  tertiary-fixed-dim: '#ffb95f'
  on-tertiary-fixed: '#2a1700'
  on-tertiary-fixed-variant: '#653e00'
  background: '#f9f9ff'
  on-background: '#141b2b'
  surface-variant: '#dce2f7'
typography:
  display-hero:
    fontFamily: Space Grotesk
    fontSize: 56px
    fontWeight: '700'
    lineHeight: 64px
    letterSpacing: -0.02em
  display-hero-mobile:
    fontFamily: Space Grotesk
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Space Grotesk
    fontSize: 40px
    fontWeight: '700'
    lineHeight: 48px
    letterSpacing: -0.015em
  headline-lg-mobile:
    fontFamily: Space Grotesk
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
    letterSpacing: -0.015em
  headline-md:
    fontFamily: Space Grotesk
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Space Grotesk
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  title-md:
    fontFamily: Space Grotesk
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-lg:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.02em
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.04em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-mobile: 1rem
  margin: 2rem
  margin-mobile: 1rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2.5rem
---

## Brand & Style

This design system establishes a high-precision, technical, and dependable marketplace aesthetic. It balances the sharp expertise of digital specialists (engineering, creative editing, cybersecurity) with the immediate clarity required by non-technical clients seeking vetted talent.

The visual style unites **modern technical precision** with **focused minimalism**:
- Clean canvas foundations with surgical 1px borders for structure and trust.
- Energetic linear gradient highlights exclusively reserved for high-leverage user actions and brand markers.
- High typographic contrast pairing an engineered, geometric display voice with an ultra-legible utilitarian UI layer.
- Tangible interactive feedback with clear accessible focus states and purposeful semantic signifiers.

## Colors

The palette balances energy, trust, and functional clarity.

### Color Tokens & Roles
- **Brand Primary (`#E11D2E`)**: Signals core brand presence, primary alerts, and vital emphasis.
- **Brand Secondary (`#F97316`)**: Complements the primary red to form the linear signature gradient (`linear-gradient(135deg, #E11D2E 0%, #F97316 100%)`), restricted to high-conversion CTA buttons, identity marks, and active milestone states.
- **Brand Accent (`#F59E0B`)**: Used for ratings, verified skill indicators, warning alerts, and luminous highlights.
- **Base Canvas (`#F8FAFC`)**: Soft, anti-glare background providing subtle contrast against elevated surfaces.
- **Surfaces (`#FFFFFF`)**: Base for modular cards, sheets, inputs, and flyouts.
- **Borders & Dividers (`#E2E8F0`)**: Structural framing ensuring clear component architecture.
- **Typography - Primary (`#1F2937`)**: Maximum contrast readability for content, headlines, and data values.
- **Typography - Secondary (`#64748B`)**: Contextual subheadings, metadata, placeholders, and inactive labels.
- **Semantic Feedback**:
  - Success: `#15803D` (Completed verification, available status, successful hire).
  - Error: `#B91C1C` (Validation blocks, failed security checks).
  - Warning: `#B45309` (Pending escrow, profile incomplete).

## Typography

The typographic hierarchy implements an engineered dual-type model:
- **Headlines (`Space Grotesk`)**: Provides sharp, angular, tech-forward authority suited for skill taxonomy, expert profile names, and primary impact metrics.
- **Body & UI Controls (`Inter`)**: Delivers supreme readability for high-density service scopes, rate breakdowns, legal agreements, and input fields.

Maintain rigorous line-height tracking to preserve vertical scan rhythm across dense marketplace comparison views.

## Layout & Spacing

A 12-column responsive grid underpins all viewports, locking max content boundaries at 1280px to preserve visual density and control scanning speed:
- **Desktop (>= 1024px)**: 12 columns, 24px (`1.5rem`) gutters, 32px (`2rem`) side margins.
- **Tablet (768px - 1023px)**: 8 columns, 16px (`1rem`) gutters, 24px (`1.5rem`) side margins.
- **Mobile (< 768px)**: 4 columns, 16px (`1rem`) gutters, 16px (`1rem`) side margins. Profile cards, skill tags, and action panels collapse vertically into full-width stacks.

Internal card padding uses `space-lg` (24px) for desktop and scales down to `space-md` (16px) on mobile breakpoints.

## Elevation & Depth

Visual hierarchy uses clean surface nesting framed by 1px solid structural borders (`#E2E8F0`), supplemented by diffuse, cool ambient shadows:

- **Level 0 (Flat / Canvas)**: Pure `#F8FAFC` base with no shadow.
- **Level 1 (Default Card & Table Containers)**: `#FFFFFF` surface, 1px solid border `#E2E8F0`, shadow `0 1px 3px 0 rgba(17, 24, 39, 0.05), 0 1px 2px -1px rgba(17, 24, 39, 0.05)`.
- **Level 2 (Hover States, Interactive Cards, Menus)**: `#FFFFFF` surface, 1px solid border `#CBD5E1`, shadow `0 10px 15px -3px rgba(17, 24, 39, 0.08), 0 4px 6px -4px rgba(17, 24, 39, 0.04)`.
- **Level 3 (Modals, Slide-overs, Escrow drawers)**: `#FFFFFF` surface, shadow `0 20px 25px -5px rgba(17, 24, 39, 0.12), 0 8px 10px -6px rgba(17, 24, 39, 0.06)`.

Interactive states rely on border tone shifts and clean elevation lift rather than exaggerated displacement.

## Shapes

The geometry communicates structural precision:
- **Buttons, inputs, filter chips, and interactive controls**: Standardized strictly at **8px** radius (`rounded-md`).
- **Cards, detail panels, dialogs, and media containers**: Fixed strictly at **12px** radius (`rounded-lg`).
- **Status indicators, avatars, and badge counters**: Full pill geometry (`rounded-full`) to contrast immediately against structural layout cards.

## Components

### Buttons
- **Primary / Brand CTA**: Background is `linear-gradient(135deg, #E11D2E 0%, #F97316 100%)`, text `#FFFFFF`, radius `8px`, height `44px`, font `label-lg`. Focus state: `2px solid #FFFFFF` offset with `2px solid #E11D2E`.
- **Secondary**: Background `#FFFFFF`, border `1px solid #E2E8F0`, text `#1F2937`. Hover: border `#CBD5E1`, background `#F8FAFC`.
- **Ghost / Neutral**: Background transparent, text `#64748B`. Hover: text `#1F2937`, background `#F1F5F9`.

### Input Fields & Controls
- **Inputs & Selects**: Height `44px`, background `#FFFFFF`, border `1px solid #E2E8F0`, radius `8px`, text `body-md` (`#1F2937`). Focus: border `#E11D2E`, shadow ring `0 0 0 3px rgba(225, 29, 46, 0.15)`.
- **Checkboxes & Radios**: Size `18px`, radius `4px` (checkbox) or `full` (radio). Checked state: `#E11D2E` with pure white indicator icon.

### Cards & Profiles
- **Freelance Pro Card**: Background `#FFFFFF`, radius `12px`, border `1px solid #E2E8F0`, elevation Level 1 transitioning to Level 2 on hover. Features top-right availability pill, verified badge, hourly rate callout, and skill tag cluster.

### Badges & Status Chips
- Badges strictly require a 12px semantic icon paired with text for accessibility:
  - **Available**: Surface `#DCFCE7`, text `#15803D`, icon dot.
  - **Busy / Booked**: Surface `#FEE2E2`, text `#B91C1C`.
  - **Skill Tag (Read-only)**: Background `#F1F5F9`, border `1px solid #E2E8F0`, radius `6px`, text `label-md` (`#1F2937`).
  - **Verified Credential**: Surface `rgba(245, 158, 11, 0.1)`, text `#B45309`, border `1px solid rgba(245, 158, 11, 0.3)`.