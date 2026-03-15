# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Text Share** is a simple, lightweight web application for sharing text with short URLs. Built with Laravel 12, it allows users to create and share text snippets with optional password protection and automatic expiration.

**Key Features:**
- ✅ Share text via short URLs (`/s/{hash}`)
- ✅ Optional password protection
- ✅ Optional user authentication (login/register)
- ✅ Browser-based history tracking (using fingerprinting)
- ✅ Multiple expiration options (1 day, 1 week, 1 month, 1 year)
- ✅ Permanent shares for logged-in users
- ✅ Auto-cleanup of expired shares
- ✅ Clean, modern UI with dark mode
- ✅ Multiple format support (JSON, XML, Markdown, HTML, Base64)
- ✅ Video Merge API (external integration)

**Tech Stack:**
- Laravel 12
- MySQL database
- Vanilla JavaScript (no frontend framework)
- Marked.js (Markdown rendering)
- Pako.js & LZ-String (compression)

## Development Commands

### Initial Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Running Development Server
```bash
php artisan serve
# App available at: http://localhost:8000
```

### Database Operations
```bash
php artisan migrate           # Run migrations
php artisan migrate:fresh     # Reset and rebuild database
```

### Cleanup Command
```bash
php artisan text-share:cleanup  # Manually cleanup expired shares
# Auto-runs daily at midnight via Laravel scheduler
```

### Code Quality
```bash
php artisan pint             # Format code
php artisan test             # Run tests
```

## Architecture Overview

### Simple Web Application
This is a public web app with no authentication required. Users can:
- Create text shares from the home page (`/`)
- View shares via short URLs (`/s/{hash_id}`)
- Browse their history (tracked by browser fingerprint)

### Data Model

**text_shares table:**
- `id` - Auto-increment primary key
- `hash_id` - Unique 10-character identifier for URL
- `browser_id` - Browser fingerprint (for history tracking)
- `content` - Compressed text content
- `format` - Format type (json, xml, markdown, etc.)
- `password` - Optional hashed password
- `expires_at` - Expiration timestamp
- `created_at`, `updated_at` - Timestamps

### Browser Fingerprinting
Uses a simple fingerprint based on:
- User agent
- Language
- Screen resolution
- Color depth
- Timezone
- Canvas fingerprint

Stored in localStorage as `ts_browser_id` for persistence.

### Password Protection
- Optional password per share
- Password hashed with `password_hash()` (bcrypt)
- Session-based verification (remember on device via session + cookie)
- Minimum password length: 1 character

## Routes

### Web Routes (`routes/web.php`)
- `GET /` - Home page (create new share)
- `GET /s/{hashId}` - View a specific share

### API Routes (`routes/api.php`)

#### Authentication API
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | Login user |
| POST | `/api/auth/logout` | Logout user |
| GET | `/api/auth/me` | Get current user info |

#### Text Share API
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/text-share` | Create a new share |
| POST | `/api/text-share/{hashId}/verify` | Verify password |
| POST | `/api/text-share/history` | Get history by browser_id |

- Rate limited: 10 requests/minute
- Uses `web` middleware for session support

#### Video Merge API (External)
Requires `X-API-Key` header for authentication.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/video/merge` | Merge video with TTS audio |
| POST | `/api/video/cleanup` | Delete merged video file |
| GET | `/api/video/status` | Check system status |

**Headers:**
```
X-API-Key: your-api-key
Content-Type: application/json
```

**POST /api/video/merge**
```json
{
  "video_url": "https://example.com/video.mp4",
  "audio_url": "https://example.com/audio.mp3",
  "job_id": "unique-job-id"
}
```

Response:
```json
{
  "success": true,
  "output_url": "https://your-domain.com/merged_videos/job-id_final.mp4",
  "job_id": "unique-job-id",
  "file_size": 12345678
}
```

**POST /api/video/cleanup**
```json
{
  "job_id": "unique-job-id"
}
```

**GET /api/video/status**
```json
{
  "success": true,
  "ffmpeg_installed": true,
  "ffmpeg_path": "/usr/bin/ffmpeg",
  "ffmpeg_version": "ffmpeg version 4.4.2",
  "disk_free_gb": 50.25,
  "disk_total_gb": 100.00,
  "pending_files": 3
}
```

## Key Implementation Details

### Text Compression
Three compression methods (auto-selects smallest):
- Raw: URL encode (prefix: `R`)
- LZ-String: LZW compression (prefix: `L`)
- Pako: Deflate compression (prefix: `Z`)

### History Tracking
- Client-side browser fingerprinting
- Stored in localStorage
- API fetches shares by `browser_id`
- Sidebar UI shows last 50 shares

### Auto-Cleanup
- Command: `CleanupExpiredTextShares`
- Scheduled: Daily at midnight
- Deletes shares where `expires_at < now()`

### Views
- `resources/views/text-share/index.blade.php` - Home page
- `resources/views/text-share/show.blade.php` - View share

Both pages include:
- Editor with live preview
- Format selector and formatter
- Save controls (password, expiration)
- History sidebar
- Dark mode toggle

## Environment Variables

Required in `.env`:
```
APP_NAME="Text Share"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_DATABASE=moneys
DB_USERNAME=root
DB_PASSWORD=

# API Key (for external API access via X-API-Key header)
API_KEY=your-secure-api-key-here

# FFmpeg path (for video merge)
FFMPEG_PATH=/usr/bin/ffmpeg
```

## Database Migrations

Current migrations (in order):
1. `create_cache_table` - Laravel cache
2. `create_jobs_table` - Laravel queue jobs
3. `create_text_shares_table` - Main text_shares table
4. `add_password_to_text_shares_table` - Password protection
5. `add_browser_id_to_text_shares_table` - History tracking
6. `drop_old_feature_tables` - Cleanup from previous version

## Project Structure

```
app/
├── Console/Commands/
│   └── CleanupExpiredTextShares.php
├── Http/Controllers/
│   ├── Controller.php (Laravel base)
│   ├── TextShareController.php
│   ├── VideoMergeController.php
│   └── Auth/
│       └── AuthController.php
├── Models/
│   ├── TextShare.php
│   └── User.php
└── Providers/
    └── AppServiceProvider.php

routes/
├── api.php (all API endpoints)
├── web.php (/, /s/{hash})
└── console.php (cleanup schedule)

resources/views/text-share/
├── index.blade.php (home page)
├── show.blade.php (view share)
└── partials/
    └── auth-modal.blade.php (login/register modal)

public/
├── css/text-share.css
├── js/
│   ├── auth.js
│   ├── auth-patch.js
│   └── text-share/app.js
└── merged_videos/ (output directory for video merge)

database/migrations/
└── (text_shares, users related migrations)
```

## Testing

PHPUnit configured for Laravel 12. Test files go in:
- `tests/Feature/` - Integration tests
- `tests/Unit/` - Unit tests

## Notes

- **Optional authentication**: Users can register/login for permanent shares, or use as guest
- **Guest users**: Browser-based tracking with expiration (1 day to 1 year)
- **Logged-in users**: Permanent shares, no expiration
- **Privacy-focused**: Browser fingerprint stored locally
- **Automatic cleanup**: Expired guest shares deleted daily
- **Lightweight**: No heavy dependencies (no Sanctum, Filament, Swagger)
- **Video Merge API**: External API for merging videos with TTS audio (requires API key)
