# Lock Debug Logs (LockDebugLogs)

**External Dolibarr module** — scheduled safeguards for the native **Debug Logs** feature so debug tracing cannot stay on (or log files grow) by accident.

| | |
|---|---|
| **Technical name** | `lockdebuglogs` |
| **Module number** | `500001` |
| **Version** | `1.1.0` |
| **Author** | **Amine Labghali** @ **Big Data Consulting** |
| **Availability** | Open and **free for the Dolibarr community** (use, share, deploy) |
| **Dolibarr** | 18.0 and later (tested on 24.0.2) |
| **PHP** | 7.4+ |
| **Core changes** | None — install under `htdocs/custom/` only |

---

## What it does

When you enable **Debug Logs** in Dolibarr for troubleshooting, the application can write a lot of data to a log file. If you forget to turn debugging off, the file may grow for a long time.

**Lock Debug Logs** lets you define one or two optional timers:

1. **Auto-disable Debug Logs** — turns off the Debug Logs module at the chosen time (same as disabling it in *Home → Setup → Modules*).
2. **Auto-delete log file** — deletes the file configured in Debug Logs setup (`SYSLOG_FILE`), only if its path is inside your documents directory (`DOL_DATA_ROOT`).

Both options are **off by default**. You choose duration (e.g. 30 minutes) or an exact date and time. When the deadline is reached, the action runs automatically while someone uses Dolibarr, or via the scheduled job if cron is configured.

The setup page also shows the **last lines of your Dolibarr log file** and a button to refresh them.

---

## Requirements

| Module | Role |
|--------|------|
| **Debug Logs** (`modSyslog`) | **Required** — Lock Debug Logs depends on it. |
| **Scheduled jobs** (`modCron`) | **Recommended** — runs the safeguard every 5 minutes even when nobody is logged in. |

Without scheduled jobs, deadlines still run when any user opens a Dolibarr page after the deadline (silent check on each request).

---

## Installation

### 1. Copy the module

Copy the whole `lockdebuglogs` folder into your Dolibarr **`htdocs/custom/`** directory:

```text
htdocs/custom/lockdebuglogs/
```

The folder must contain at least `core/modules/modLockDebugLogs.class.php`.

### 2. Enable in Dolibarr

1. Log in as an **administrator**.
2. Go to **Home → Setup → Modules / Applications**.
3. Search for **Lock debug logs** (or `LockDebugLogs`).
4. Switch the module **ON**.

If **Debug Logs** is not enabled, Dolibarr will ask you to enable it first (module dependency).

### 3. Open setup

**Home → Setup → Modules → Lock debug logs →** click the setup icon,  
or open **Lock Debug Logs setup** from the module card.

### 4. (Recommended) Scheduled jobs

1. Enable the **Scheduled jobs** module.
2. In **Home → Tools → Scheduled jobs**, find **LockDebugLogsCheck** and ensure it is **enabled** (runs every 5 minutes when cron executes).
3. On your server, run Dolibarr’s cron regularly, for example:

   ```bash
   php /path/to/dolibarr/htdocs/cron/cron_run_jobs.php login password
   ```

   Replace `login` and `password` with a Dolibarr user allowed to run jobs (often an admin). On Windows, use Task Scheduler with the same command.

The setup page status card shows whether Debug Logs and Scheduled jobs are active.

---

## Daily use

1. Enable **Debug Logs** only when you need traces (native setup: **Home → Setup → Other → Debug logs**).
2. On **Lock Debug Logs setup**, enable one or both safeguards and set **after duration** or **at date and time**.
3. Click **Save**.
4. The **Scheduled deadline** badge shows when the action will run (green while waiting, red if overdue until the next check).

**Execute it now** runs the same checks immediately (useful for testing).

When a safeguard has run, its checkbox is turned off but your form choices (duration, date, etc.) stay saved so you can adjust and save again.

---

## Uninstall / disable module

Disabling or removing **Lock Debug Logs** deletes all module settings (`LOCKDEBUGLOGS_*` constants) and unregisters the cron job. It does **not** change Debug Logs configuration or uninstall Debug Logs.

---

## Security notes

- Only **administrators** can open the setup page and run manual checks.
- Log file deletion is allowed only when the resolved path is under **`DOL_DATA_ROOT`**.
- The module does not patch Dolibarr core files; updates to Dolibarr do not overwrite this module if it stays in `custom/`.

---

## Packaging and deployment

To deploy on another server:

1. Zip the `lockdebuglogs` directory (include `README.md`, `langs/`, `core/`, `admin/`, `class/`, `lib/`, `css/`).
2. Extract into `htdocs/custom/` on the target instance.
3. Enable the module and configure as above.

No database script is required beyond what Dolibarr runs on module activation (constants and cron registration).

---

## Troubleshooting

| Issue | What to check |
|-------|----------------|
| Deadline never fires while idle | Enable **Scheduled jobs** and run `cron_run_jobs.php` every few minutes. |
| Deadline fires late | Normal if nobody uses Dolibarr and cron is off; open any page or use **Execute it now**. |
| Log viewer empty | Path shown is `SYSLOG_FILE`; enable Debug Logs and a sufficient log level so the file exists and has content. |
| “No log file at this path” | Create/trigger logging once, or fix the path in native Debug Logs setup. |
| Countdown looked wrong by ~1–2 h | Save the deadline again after upgrade (date/time uses your user timezone). |

If a safeguard fails it is retried up to 3 times (5 minutes apart) and then switched off; the reason is shown under **Last automatic action**.

Module messages appear in the standard Dolibarr log with the prefix `LockDebugLogs:` when Debug Logs is active.

---

## File layout (overview)

```text
lockdebuglogs/
├── admin/          Setup page and log refresh (AJAX)
├── class/          Business logic, cron, hooks
├── core/modules/   Module descriptor (activation)
├── css/            Setup page styling
├── langs/          en_US, de_DE, fr_FR labels
├── tests/          CLI self-test (run as the web server user)
└── lib/            Shared helpers
```

---

## Changelog

### 1.1.0

- Performance: all module constants are read with one query per request; while no deadline is due the page-load check costs one query and writes nothing to the log (before: about 35 queries and several log lines per page).
- A failing safeguard no longer retries on every page load: up to 3 attempts, 5 minutes apart, then it switches itself off and reports the reason in "Last automatic action". An unsafe log path stops at once.
- The two safeguards run independently; an error in one no longer blocks the other.
- A run lock (`documents/admin/temp/lockdebuglogs.lock`) prevents two simultaneous requests or cron from executing the same action twice.
- "Execute it now" is a POST form instead of a GET link; it now also reports "No action due".
- Translations: `de_DE` and `fr_FR` added; the module's own messages are translatable.
- Setup page: the two safeguard cards are generated by one helper (less duplicated code).
- New CLI self-test `tests/cli_selftest.php`.

### 1.0.0

- Auto-disable Debug Logs module and/or auto-delete log file (duration or datetime).
- Checks on page load, setup, manual run, and scheduled job every 5 minutes.
- Setup UI: status card, dual safeguard cards, log viewer with live reload.
- Cleanup of all module constants on disable.

---

## Author

Developed by **Amine Labghali** at **Big Data Consulting**.

This module is offered **free of charge to the Dolibarr community**. You may install it on any instance, share the package, and adapt it for your organisation. No license fee — please keep this README and author credit when you redistribute the zip.

Questions or professional Dolibarr / data projects: **Big Data Consulting**.
