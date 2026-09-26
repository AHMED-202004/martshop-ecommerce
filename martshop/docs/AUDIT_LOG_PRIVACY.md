# Audit-log privacy — 2026-09-08

The audit-log index and detail pages now use the strict private-financial response middleware and private layout. Responses are non-cacheable, send no referrer, deny framing and MIME sniffing, and use the restricted Content Security Policy without third-party page assets. The interface explicitly identifies IP addresses, device strings and operation reasons as private administrative data.

## Authorization before record disclosure

Both routes still require `audit-logs.view`. The detail action now performs that permission check before querying the requested audit-log identifier instead of relying on implicit model binding. An unauthorized account therefore receives the same forbidden response for an existing or nonexistent identifier and cannot use the 403/404 difference to enumerate records.

## List-data minimization

The index query selects only the fields required by its table: identifier, actor, action, subject reference, reason and timestamp. It no longer loads `before`, `after`, metadata, IP address or user-agent payloads for every listed row. Those fields are fetched only for an authorized detail request.

Pagination now retains only validated filters. Unknown query parameters are excluded from rendered filter values and following-page links. Existing literal wildcard escaping, date validation and defensive recursive secret-key redaction remain in place.

## Verification

Focused audit-log and dashboard coverage passed: **16 tests, 171 assertions**. The complete suite passed: **206 tests, 2136 assertions**. Blade compilation and PHP syntax checks passed, and all **143 routes** have unique names and method/URI signatures.

Tests use SQLite `:memory:`. No live audit row was created, modified or deleted. Existing audit records remain append-only. Production deployment still requires restricted database/log access, HTTPS and session/proxy configuration, retention policy review, and monitoring of administrative access.
