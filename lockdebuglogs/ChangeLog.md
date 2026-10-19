# Changelog

## 1.1.0

- Performance: one constants query per request, silent early exit while nothing is due.
- Failure handling: max. 3 attempts, 5 minutes apart, then the safeguard switches itself off and reports the reason; unsafe path stops at once.
- Safeguards run independently of each other.
- Run lock against concurrent execution.
- "Execute it now" via POST.
- Translations de_DE and fr_FR; translatable messages.
- Setup page refactored (shared card helper), CLI self-test added.

## 1.0.0

- Initial release: auto-disable Debug Logs module and/or auto-delete log file, setup page with log viewer.
