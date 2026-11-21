# Signed URL and CORS Lab

A PHP and XAMPP lab for demonstrating temporary signed media URLs and browser CORS behavior.

The project is intentionally small. It lets you observe:

- why a protected endpoint should verify authentication and subscription status;
- how an HMAC signature, expiry time, and optional IP binding affect access;
- why HTTP range requests matter for video seeking;
- how CORS and direct file access are separate concerns.

## Requirements

- PHP 8.0 or later
- Apache 2.4 or later
- XAMPP for Windows, or a compatible PHP/Apache environment
- `curl` for the test scripts

## Setup

1. Copy the project directory into `C:\xampp\htdocs\signed-url-lab`.
2. Copy `config.example.php` to `config.php`.
3. Replace the demo HMAC secret in `config.php` before using it outside local testing.
4. Start Apache from the XAMPP control panel.
5. Open `http://localhost/signed-url-lab/`.

The signed URL system also requires `private/video.mp4` to exist. Replace it with a short local MP4 for your own tests.

## Test Accounts

| Username | Password | Subscription |
|---|---|---|
| `sub` | `sub123` | Enabled |
| `free` | `free123` | Disabled |

These credentials are for the local demo only.

## Browser Test

1. Open `http://localhost/signed-url-lab/`.
2. Sign in with `free` and request a link. The server should return `403`.
3. Sign out, sign in with `sub`, and request a link.
4. Confirm that the video plays and that seeking remains available.
5. Wait for the configured TTL to expire, then try another range request. The server should return `403`.

The default TTL is 60 seconds and can be changed with `ttl` in `config.php`.

After the link is issued, use **Copy URL** to copy it for testing in `curl`, IDM, yt-dlp, or another HTTP client.

## External Tool Test

On Windows:

```powershell
powershell -ExecutionPolicy Bypass -File .\test-link.ps1
```

To wait until the link expires:

```powershell
powershell -ExecutionPolicy Bypass -File .\test-link.ps1 -WaitExpiry
```

Use `-Base "http://localhost:8080/signed-url-lab"` when Apache uses a non-default port or a different project path.

On Linux or macOS:

```bash
./test-link.sh
./test-link.sh http://localhost/signed-url-lab --wait-expiry
```

You can also copy the signed URL shown in the browser and pass it directly to `curl` or `yt-dlp`:

```bash
curl -o test.mp4 "SIGNED_URL"
yt-dlp "SIGNED_URL"
```

The test scripts cover:

| Scenario | Expected status |
|---|---|
| Link request without login | `401` |
| Link request as the `free` account | `403` |
| Download with a valid signed URL and no cookies | `200` |
| Range request for video seeking | `206` |
| Tampered signature | `403` |
| `media.php` without a token | `403` |
| Direct request to `private/video.mp4` | `403` |
| Signed URL after its expiry time | `403` |

## CORS Test

Open `http://127.0.0.1/signed-url-lab/cors-test.html` and send the request.

The response should initially be blocked because `http://127.0.0.1` is not included in `allowed_origins`. Add it to `config.php`, for example:

```php
'allowed_origins' => [
    'http://127.0.0.1',
],
```

Include the port in the origin if Apache does not run on port 80.

CORS controls browser-read access. It does not protect a media file by itself.

## Project Structure

| Path | Purpose |
|---|---|
| `index.php` | Login page and browser video player |
| `login.php` | Demo login handling |
| `logout.php` | Session cleanup |
| `link.php` | Issues temporary signed URLs |
| `media.php` | Validates tokens and serves range requests |
| `lib.php` | Session, HMAC, CORS, and URL helpers |
| `config.example.php` | Safe starting configuration |
| `config.php` | Local configuration; created from the example |
| `cors-test.html` | Browser CORS experiment |
| `ping.php` | Simple CORS test endpoint |
| `test-link.ps1` | Windows HTTP test script |
| `test-link.sh` | Linux/macOS HTTP test script |
| `private/` | Protected media files |

## Security Notes

This project demonstrates access-control concepts and is not intended as a production media platform.

Before adapting it for a real application:

- generate and keep the HMAC secret outside source control;
- move the private media directory outside the Apache document root;
- use HTTPS for login and media traffic;
- hash passwords with `password_hash()` and verify them with `password_verify()`;
- add CSRF protection to authenticated actions;
- consider short token lifetimes and automatic renewal for long videos;
- use HLS/DASH encryption or DRM for valuable content;
- remember that CORS does not replace server-side authorization.

## Laravel Equivalents

| This project | Laravel |
|---|---|
| `link.php` + `make_signed_url()` | `URL::temporarySignedRoute()` |
| `media.php` signature checks | `signed` middleware |
| `apply_cors()` | CORS configuration |
| `private/` | `Storage` with a temporary URL |
