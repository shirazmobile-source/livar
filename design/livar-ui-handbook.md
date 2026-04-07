# LiVAR CRM Design and CSS Extraction

This file was extracted from the project codebase. Its purpose is to let a designer or frontend developer recreate the UX/UI appearance with high fidelity.

## 1) Visual language summary

From an aesthetic standpoint, this project has the following visual DNA:

- Minimal and clean, with no visual clutter
- Card-based, with relatively generous corner rounding
- Color accent used only for the brand orange
- Bright, neutral surfaces in light mode
- Glassy / semi-transparent dark mode with high contrast
- Sticky top navigation with blur, similar to iOS/macOS
- Short, smooth interactions with a micro-motion feel
- Mobile-first behavior on operational pages, with a bottom-stuck CTA

## 2) Core design tokens

### Colors
- Brand: `#FF5500`
- Text: `#111111`
- Muted text: `#666666`
- Border: `#E9E9E9`
- Surface: `#FFFFFF`
- Surface 2: `#FBFBFB`
- Surface 3: `#F6F6F6`

Dark theme:
- Background: `#050506`
- Surface: `rgba(255,255,255,.06)`
- Border: `rgba(255,255,255,.14)`
- Text: `rgba(255,255,255,.92)`

### Corner radius
- Card radius: `18px`
- Input/Button radius: `14px`
- Table radius: `16px`
- Pill radius: `999px`

### Shadows
- Small: `0 6px 18px rgba(0,0,0,.06)`
- Medium: `0 10px 30px rgba(0,0,0,.08)`
- Large: `0 16px 46px rgba(0,0,0,.10)`

In dark mode, shadows become deeper.

### Typography
- Font: system stack
- H1: `18px`
- H2: `16px`
- Body: `15px`
- Label/Small text: `12px` to `13px`
- Dominant font weight for CTAs and KPIs: `900`

## 3) Layout rules

### Container
- Main width: `1100px`
- Desktop padding: `18px`
- Mobile padding: `12px`

### Grid
- Base spacing between items: `10px` to `12px`
- Data-heavy pages are built around grid and card patterns, not heavy section blocks.

### Responsive behavior
- Main breakpoint: `899px`
- Desktop: horizontal top navigation, multi-column KPI grid, full table
- Mobile: card list, compact tabs, accordion, sticky action bar

## 4) Navigation pattern

### Topbar
- sticky
- White or black translucent background with blur
- Thin bottom border
- Logo/brand on the left
- Navigation pill in the center
- User pill on the right

### Navigation item
- Pill shape
- Very subtle hover
- Active state with orange border and a faint orange background

## 5) Card pattern

All pages are built around a card system:

- Thin light border
- `18px` corners
- Soft shadow
- Very subtle hover lift
- Separate padding for header and body

Cards are used for:
- Forms
- KPIs
- Tables
- Item lists
- Accordions
- Alerts

## 6) Button pattern

### Primary button
- height: `44px`
- background: brand orange
- text: black
- font-weight: `900`
- border: black or very dark

### Secondary button
- White or the current surface color
- Light border
- No heavy shadow

### Ghost button
- No meaningful border
- Hover uses a very faint tint

## 7) Form pattern

- input/select/textarea with `14px` corners
- Thin light border
- Simple, flat background
- Very subtle orange focus ring
- Clear, undecorated typography

From a UX perspective, the forms prioritize clarity and speed more than decorative effects.

## 8) Badge / Tab / Status

- All are pill-shaped
- Statuses are shown with a very faint color tint
- Orange is used for the main / brand state
- Green, red, and blue are used for status states
- Active tabs are indicated with a soft orange fill

## 9) Tables

- Rounded container
- Slightly differentiated header background
- Soft borders
- Very subtle row hover
- Date and numeric columns use tabular numerals

## 10) Mobile

On mobile, the project follows this pattern:

- Dense but organized card lists
- Two-column KPIs
- Compact, pill-like tabs
- Accordion usage to reduce clutter
- Sticky bottom action bar for quick actions
- Overflow guards for long text

## 11) Dashboard

The dashboard has two important modes:

### Desktop
- Multi-column KPI tiles
- Analytical cards with an accent line at the top
- High information density, but still organized
- Micro stat boxes under charts

### Mobile
- KPI cards in a two-column grid
- Now bar / status bar
- Collapsible sections
- Quick and obvious CTAs

## 12) What the designer must follow precisely

1. Do not use exaggerated gradients or glassmorphism.
2. Everything should feel flat-modern, not skeuomorphic.
3. Accent should remain limited to the brand orange; the palette should stay neutral.
4. Radius should be generous but controlled, neither too sharp nor too bubble-like.
5. Shadows should be soft and shallow.
6. Whitespace should be controlled, neither sparse nor unreadably dense.
7. Mobile should feel more functional than desktop.
8. Black, white, and grays should dominate, and orange should act only as a guide.

## 13) Ready-to-use prompt for a designer

Design a modern, minimal CRM with the following characteristics:

- Overall style: modern minimal, premium utility UI
- Visual tone: clean, high-clarity, slightly iOS-like
- Brand color: `#FF5500`
- Base palette: white, black, very soft grays
- Forms and cards: rounded, soft-shadow, subtle border
- Navigation: sticky topbar with blur, pill-shaped items
- Primary button: orange with black text and font weight 900
- Badges and tabs: pill shapes with very light tinted backgrounds
- Desktop: card grid + KPI tiles + clean tables
- Mobile: stacked cards + accordion + sticky bottom actions
- Dark mode: near-black background with translucent panels
- Avoid heavy gradients, neon, heavy glass effects, and multiple accent colors
- The final look should feel like a professional, fast, and trustworthy CRM

## 14) Accuracy limitation

This output was extracted from source code, not from final rendered screenshots of every page. For final pixel-perfect work, it is better to add one more step and capture snapshots of the key screens. But for visual redesign and design-system creation, this handoff is fully sufficient and accurate.
