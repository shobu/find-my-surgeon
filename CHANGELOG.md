# Changelog

All notable changes to the Find My Surgeon plugin are documented in this file.

## [2.3] - 2026-09-18

### Added
- Completed French (fr) and Dutch (nl) translations in `includes/translations.php`,
  replacing the English placeholders introduced in v2.1. Three Dutch strings
  (title, subtitle, select_label) were provided by Vakol directly and kept as-is;
  the rest (country, city, search, download_strong, download_text, no_cities,
  ajax_error) and all French strings were translated by Claude, pending native
  speaker review.
- `questions-fr.pdf` / `questions-nl.pdf` are still not present under
  `assets/pdf/`; the download link keeps falling back to the English PDF for
  these two languages until those files are added.

## [2.2] - 2026-09-18

### Security
- `includes/meta-doctor-details.php`: `fms_save_doctor_details()` had no nonce
  verification and no explicit capability check, relying only on WordPress's
  implicit `save_post` protections. Added a nonce field to the metabox
  (`fms_doctor_details_nonce`), verified on save, plus explicit
  `current_user_can('edit_post', $post_id)` and post-type checks so the
  handler only runs for the `doctor` CPT with a valid submission.
- `includes/import-doctors.php`: `fms_import_doctors_page()` processed the ZIP
  upload step with only a nonce check (`check_admin_referer`), no explicit
  capability check (the Excel import step already had one, further down).
  The page was already gated by `manage_options` via `add_submenu_page`, but
  added an explicit `current_user_can('manage_options')` check at the top of
  the function as defense in depth for a page that handles file uploads.

## [2.1] - 2026-09-18

### Fixed
- GA4/GTM click tracking for doctor profile links was never firing because the jQuery
  delegated selector in `assets/js/filter.js` targeted `.fms-doctor-card a.fms-doctor-link`,
  a class that does not exist in the rendered markup. Corrected to `.fms-doctor a.fms-doctor-link`.
  The internal click-logging system (`assets/js/logging.js`, `fms_click_log` DB table,
  admin "FMS Click Logs" dashboard) was not affected by this bug, it uses a different,
  attribute-based selector and was tracking events correctly the whole time.

### Added
- French (`fr`) and Dutch (`nl`) added to the supported-languages list across the plugin:
  - `includes/translations.php`: URL language detection regex, `$translations` array (all keys),
    JS string translations (`no_cities`, `ajax_error`).
  - `includes/class-fms-click-logger.php`: `get_supported_languages()` (used by the admin
    reporting filters and language attribution).
  - `templates/filter-template.php`: duplicate URL language detection regex, PDF URL map
    (`fms_get_pdf_url()`).
  - FR/NL string values are currently English placeholders. Real translations to be supplied
    and applied on top of these placeholders.
  - No `questions-fr.pdf` / `questions-nl.pdf` exist yet under `assets/pdf/`; until they are
    added, the download link automatically falls back to the English PDF for these two
    languages (existing fallback behavior, unchanged).

## [2.0] - baseline
- Version prior to this changelog being introduced. Existing functionality at this point:
  Doctor CPT + hierarchical Location taxonomy (Country/City), AJAX country->city->doctors
  filter, custom click-logging analytics system with admin reporting dashboard and CSV
  export, Excel + ZIP bulk doctor import tool, EN/DE/IT/GR interface translations.
