# SpaceGuard

**SpaceGuard** is a GitHub home for Dolibarr add-ons that help keep production instances safe — timers, guardrails, and operational hygiene around debugging and logging.

Maintained by **Amine Labghali** @ **Big Data Consulting**. Components are offered **free to the Dolibarr community**.

---

## Repository layout

| Path | Description |
|------|-------------|
| [`lockdebuglogs/`](lockdebuglogs/) | **Lock Debug Logs** — auto-disable Debug Logs and/or trim the debug log file after a deadline |

Install any module by copying its folder into Dolibarr `htdocs/custom/`, then enabling it under **Home → Setup → Modules**.

Full module documentation: [lockdebuglogs/README.md](lockdebuglogs/README.md).

---

## Lock Debug Logs (summary)

When **Debug Logs** is left on, log files can grow without notice. **LockDebugLogs** adds optional safeguards:

- **Auto-disable** the Debug Logs module after a duration or date/time.
- **Auto-delete** the configured log file (only if the path is under your documents root).

Works on page load, from the setup page, manually, or via the **LockDebugLogsCheck** cron job (every 5 minutes).

**Requirements:** Dolibarr 18+ (tested on 24.0.2), PHP 7.4+, native **Debug Logs** module; **Scheduled jobs** recommended.

```text
htdocs/custom/lockdebuglogs/   ← copy the lockdebuglogs folder from this repo
```

---

## Branches

| Branch | Purpose |
|--------|---------|
| `main` | Stable snapshot aligned with releases |
| `develop` | Active development |

Changes land on `develop` first, then merge into `main`.

---

## Quick install (LockDebugLogs)

1. Clone this repository or download a release archive.
2. Copy `lockdebuglogs/` to `htdocs/custom/lockdebuglogs/` on your Dolibarr instance.
3. Enable **Lock debug logs** in **Setup → Modules**.
4. Open the module setup and configure safeguards; enable **Scheduled jobs** for idle servers.

---

## Contributing

Issues and pull requests are welcome on `develop`. Please keep modules self-contained under their own directory and do not modify Dolibarr core.

---

## License

Module code follows the same licensing model as Dolibarr external modules (GPL-3.0+ as declared in module descriptors). This README and packaging remain free to share with author credit.

**Big Data Consulting** — Dolibarr, data, and integration projects.
