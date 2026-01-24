# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Duplicator is an Electron desktop application for print shop collectives (duplicopieurs/photocopieurs). It provides accounting management, document processing (PDF imposition, color separation for Riso printers), and direct printing capabilities.

The app exists in two versions:
- **Electron + Caddy** (main, `main-caddy.js`): Uses Caddy as reverse proxy + PHP built-in server
- **Electron + PHP** (legacy, `main.js`): Direct PHP server

## Architecture

```
Electron (main-caddy.js)
    ├── Caddy (reverse proxy, port 8000)
    │       └── PHP built-in server (port 8001)
    │               └── app/public/index.php (frontend router)
    ├── Print Engine (src/print-engine/)
    │       ├── Windows: win32-printer.cc (N-API addon)
    │       └── Linux/macOS: cups-printer.js (CUPS commands)
    └── preload.js (IPC bridge to frontend)
```

### Key Components

- **main-caddy.js**: Electron main process, manages Caddy/PHP processes, printer monitoring, auto-updates
- **app/public/index.php**: PHP frontend router (MVC pattern)
- **app/view/*.html.php**: PHP views (templates)
- **app/controler/**: Business logic and functions
- **app/api/**: AJAX endpoints (PDF conversion, print jobs, session management)
- **src/print-engine/**: Cross-platform printing module (Win32 API / CUPS)
- **utils/**: Electron utilities (printer-monitor.js, spool-analyzer.js, admin-checker.js)

### Database

SQLite database stored in userData (preserved across updates):
- Windows: `%APPDATA%\Duplicator\duplinew.sqlite`
- Linux: `~/.config/Duplicator/duplinew.sqlite`

Error log: `%TEMP%\duplicator_errors.log`

## Development Commands

```bash
# Run the app in development
npm start                    # Uses main-caddy.js (Caddy + PHP)
npm run start:php           # Uses main.js (direct PHP)
npm run dev                 # Development mode with NODE_ENV=development

# Build
npm run build               # Build for current platform
npm run build:caddy         # Build with Caddy configuration

# Download runtime dependencies
npm run download-caddy      # Download Caddy binary
npm run download-php        # Download PHP binary
npm run download-all        # Download both

# Rebuild native print engine (Windows)
npm run rebuild:print-engine   # Runs node-gyp rebuild in src/print-engine/windows
```

## Testing

```bash
npm test                    # Run all tests
npm run test:unit          # Unit tests only
npm run test:integration   # Integration tests (Caddy + PHP)
npm run test:e2e           # End-to-end Electron tests
npm run test:watch         # Watch mode
npm run test:coverage      # Coverage report

# Run specific test patterns
npm test -- print-engine   # Tests matching "print-engine"
```

Jest is configured with 30s timeout. Tests mock Electron, child_process, and fs modules.

## Print Engine

The native print module (`src/print-engine/`) provides cross-platform printing:

**API:**
```javascript
const printEngine = require('./src/print-engine');
await printEngine.getPrinters();
await printEngine.getPrinterCapabilities(printerName);
await printEngine.printJob(pdfPath, { printer, copies, inputSlot, pageSize, colorMode, duplex });
```

**Windows**: Requires Visual Studio Build Tools. Rebuild with `npm run rebuild:print-engine`.

**Linux**: Uses CUPS commands (lpstat, lpoptions, lp).

## IPC Communication

Frontend accesses Electron features via `window.electronAPI` (defined in preload.js):
- File operations: `openFile`, `cleanupTmpFiles`, `showOpenDialog`
- Printing: `getPrinters`, `getPrinterCapabilities`, `printJob`
- Updates: `checkForUpdates`, `downloadUpdate`, `installUpdate`
- PHP monitoring: `onPhpLog`, `onPhpFatal`, `onPhpStatus`, `restartPhp`
- Admin: `checkAdminStatus`, `restartAsAdmin`

## Important Paths

- Caddyfile: Project root (copied to resources on build)
- PHP config: `app/php.ini`
- Ghostscript: `ghostscript/` directory
- Translations: `app/controler/functions/i18n.php`

## Compatibility Notes

- Must maintain compatibility with standalone PHP server version
- Manual print mode (`tirage_multimachines`) must always work
- Supports Windows (primary), Linux (AppImage), macOS
- ASAR is disabled in builds (files in `resources/app/` not `resources/app.asar.unpacked/`)

## Publishing Updates

1. Update version in `package.json`
2. Commit and tag: `git tag v1.x.x && git push origin main --tags`
3. Build: `npm run build:caddy`
4. Create GitHub Release with `.exe`, `.yml` files from `dist/`
5. Users receive automatic updates via electron-updater
