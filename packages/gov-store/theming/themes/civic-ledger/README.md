# Civic Ledger

Calm, legible, bilingual by default. A quiet green-on-paper theme for the people who use the register
every day: area ICT officers, office administrators and front-line staff.

## Intent

- **Paper, not chrome.** A warm off-white ground (`#F5F6F3`), white surfaces and a white neutral header
  keep attention on the work. Deep civic green (`#0B5E45`) is used for primary action only.
- **Status is lightness, not just hue.** The four asset statuses and three document states sit at different
  OKLCH lightness levels, so they stay distinct in greyscale and for colour-blind users. The only
  same-lightness pairs left are blue/red and blue/amber, which colour-vision deficiencies keep apart.
- **Bangla as a first language.** Noto Sans and Noto Sans Bengali are matched on x-height. Regular density,
  generous `lg` radius and 44 px touch targets on coarse pointers.
- **Focus is blue.** The focus ring (`#1D4F91` light, `#8DB4EE` dark) contrasts with the green primary, so
  keyboard focus is never mistaken for a selected state.

## Variants

`header: neutral` · `sidebar: dark` · `density: regular` · `table: rules` · `surfaces: card` ·
`controls: outlined` · `radius: lg` · `status_style: tinted`.

## Review

- Theme Lab: `/gov/theme-lab/civic-ledger` (light and dark side by side).
- `php artisan gs-theme:validate civic-ledger`
- `php artisan gs-theme:previews civic-ledger`
- Status is `draft` until reviewed in production's Lab; switch to `published` in a later release.
