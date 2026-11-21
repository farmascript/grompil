# GUI style guide

Use these shared colors when adding application forms, dialogs, and suggestion
lists. The aim is to give controls a consistent background and keep white input
fields easy to distinguish.

## Neutral surfaces and controls

| Use | Color | Current example |
| --- | --- | --- |
| Main application page | `#f6f8f2` | Shared page background (`--paper` in `assets/app.css`) |
| White surface | `#ffffff` | Cards, modal headers, and form controls |
| Muted form/content surface | `#f1f5f3` | Address suggestions and the patient add/edit modal body |
| Muted surface border | `#cbd8d2` | Border around the address suggestion list |
| Muted surface hover/active | `#dfeae5` | Hovered or keyboard-selected address suggestion |
| Text on active muted surface | `#183b32` | Address suggestion hover/active text |
| Deceased record surface | `#f4eeee` | Patient/customer suggestions when marked deceased |
| Deceased record border | `#dfcaca` | Border around a deceased suggestion |
| Deceased record text | `#533b3b` | Text on a deceased suggestion |

For data-entry dialogs, keep the header white and use `#f1f5f3` behind the
form. Leave inputs white with their normal control border. This contrast helps
users see where to enter data without making the form background too strong.

For suggestions, use `#f1f5f3` for the list and its rows, `#cbd8d2` for the
outer border, and `#dfeae5` with `#183b32` text for hover and keyboard selection.
Do not use the hover color as the resting background.

The current shared implementation is in `assets/app.css` (`.address-suggestions`)
and the dispensing modal rule is in `assets/dispensing.css` (`#patientModal .modal-body`).
Keep future screens aligned with these colors and roles.

Mark deceased patient/customer suggestions with the muted rose surface and text
colors above. This is a resting state distinct from the green-gray active and
hover surfaces; keep the deceased marker and date readable in both states.
