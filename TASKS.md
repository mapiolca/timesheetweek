# Pending Maintenance Tasks

## Typographical Fix
- **Issue**: The field definition for `date_validation` in `TimesheetWeek::$fields` uses the translation key `Datevalidation`, which has a lowercase "v" and does not match Dolibarr's existing `DateValidation` string. This causes the interface to display the raw key instead of the translated label.
- **Location**: `class/timesheetweek.class.php`, line 144.
- **Task**: Update the label to the correctly capitalized translation key (and audit similar keys such as `Fkuser`) so the UI shows the proper translated text.

## Bug Fix
- **Issue**: `TimesheetWeekLine` extends `CommonObject` instead of `CommonObjectLine`, so it misses the base behaviours (properties like `$fk_element`, helper methods, etc.) expected for line objects. This can break features such as cascading deletes or generic fetch helpers.
- **Location**: `class/timesheetweekline.class.php`, line 16.
- **Task**: Change the parent class to `CommonObjectLine` and ensure the line class still works after the change.

## Documentation/Comment Correction
- **Issue**: The README instructs users to download Dolibarr from “Dolistore.org” but the hyperlink actually points to `https://www.dolibarr.org`. The mismatch between the link text and URL is confusing.
- **Location**: `README.md`, line 28.
- **Task**: Fix the anchor text so that it accurately reflects the destination URL (or update the URL if the intention was to reference Dolistore).

## Code Comment Cleanup
- **Issue**: The comment describing `$element_for_permission` contains typos ("checkec" and "mymodyle"), which reduces clarity for maintainers.
- **Location**: `class/timesheetweek.class.php`, line 52.
- **Task**: Correct the spelling in the comment so it clearly explains the permission hook.

## Test Improvement
- **Issue**: The helper `formatHours()` in `lib/timesheetweek.lib.php` performs rounding logic that is currently untested. Regressions here would silently affect overtime and totals on the timesheet page.
- **Location**: `lib/timesheetweek.lib.php`, lines 322-326.
- **Task**: Add automated tests (for example, a PHPUnit test case) that cover decimal-to-HH:MM conversions, including edge cases like rounding 59.5 minutes or values above 24 hours.
