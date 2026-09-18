# Changelog

All notable changes to the Find My Surgeon plugin are documented in this file.

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
