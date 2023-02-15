# PMD 3.0 Importer

Imports PMD 3.0 data.

## Changelog

### 1.1.0

- Donations import.

### 1.0.3

- Checking for `$transDeptObj` when attempting to set `$trans_dept_id` in stores.php.

### 1.0.2

- BUGFIX: Requiring `pmd_import_attachment()` inside `transportation-departments.php`.
- BUGFIX: Referencing `$trans_dept` instead of `$org` inside `transportation-departments.php`.

### 1.0.1

- Don't attempt to add post_thumbnail on dry runs.

### 1.0.0

- Initial release.