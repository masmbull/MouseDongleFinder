# Security Policy

MouseDongleFinder is a **local, single-user Windows desktop tool**. It is not a multi-user web application and does not implement authentication or authorization.

## Supported versions

Only the latest release on the `main` branch receives security updates.

## Reporting a vulnerability

If you discover a security issue, please report it by opening a GitHub issue or contacting the repository owner directly. Do **not** disclose the vulnerability publicly until it has been addressed.

## Security design principles

- **No remote code execution**: Downloaded files (drivers, utilities) are never executed. Executable extensions (`.exe`, `.msi`) require explicit user confirmation before download.
- **PowerShell safety**: USB scanning uses `powershell.exe -File` with an argument array (never `Invoke-Expression` or shell string interpolation). The PowerShell script lives in `resources/ps/`.
- **Environment isolation**: Laravel `.env` variables are temporarily removed from `getenv()`, `$_SERVER`, and `$_ENV` while the PowerShell child process runs, then restored immediately. This avoids PowerShell 5.1 failure (error 0x8009001d).
- **HTTPS only**: All downloads are HTTPS-only, size-capped at 50 MB, with redirect-target re-validation and SHA-256 verification.
- **No secrets in code**: Never commit `.env`, passwords, API keys, or credentials. `.env` is gitignored.
- **No production access**: Never access a production VPS or credentials belonging to another project.
- **No scraping**: Web research uses a keyless Wikipedia MediaWiki API or a configured search API. No Google/Bing scraping.
- **SQLite local**: All data lives locally in `database/database.sqlite`. No cloud sync.

## What is out of scope

- Authentication / authorization (single-user local tool)
- Production server hardening
- Credential management
- Cross-user data isolation

## Credits

Security research and decisions are documented in `RESEARCH.md` §4 (License & security review summary).