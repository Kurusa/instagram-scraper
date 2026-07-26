# Instagram Scraper

Framework-independent Instagram client used to:

1. resolve a public `@username` to its numeric Instagram profile ID;
2. paginate through a public profile's reels;
3. fetch the full logged-out detail payload for an individual reel;
4. normalize both reel responses into `InstagramReelData`.

The package deliberately contains no Laravel-specific code.

## Data flow

### 1. Username to numeric profile ID

`InstagramScraper::fetchProfileByUsername()` accepts a username with or without `@`.
The persistent browser-compatible Python client calls:

```text
POST https://www.instagram.com/api/graphql
doc_id=36836636079261063
variables={"username":"itsheidiwong"}
friendly_name=PolarisLoggedOutDesktopWWWProfileRootContentQuery
```

The method returns the canonical username and `data.xig_user_by_username.pk`. The
`pk` is the numeric ID expected by the profile reels query. Do not use the response's
separate `id` field: it identifies a different Instagram entity.

```php
$profile = $scraper->fetchProfileByUsername('@itsheidiwong');

// ['id' => '348796639', 'username' => 'itsheidiwong']
```

This profile-root GraphQL response is much smaller than the legacy
`/api/v1/users/web_profile_info/` response and works through the same anonymous
session used for reel details.

### 2. Profile reels

`InstagramScraper::fetchProfileReelsPage()` calls:

```text
POST https://www.instagram.com/graphql/query
doc_id=26909206778772295
```

The request accepts a target Instagram user ID, page size, and optional cursor. It
returns the reel list and pagination information.

`InstagramProfileReelsGraphqlMapper` maps the profile response:

| `InstagramReelData` field | Profile response source                   |
|---------------------------|-------------------------------------------|
| `shortcode`               | `media.code`                              |
| `instagramMediaPk`        | `media.pk`                                |
| `likeCount`               | `media.like_count`                        |
| `commentCount`            | `media.comment_count`                     |
| `thumbnailUrl`            | `media.image_versions2.candidates[0].url` |
| `playCount`               | `media.play_count`                        |
| `takenAt`                 | unavailable                               |
| `captionText`             | unavailable                               |
| `videoUrl`                | unavailable                               |
| `videoDurationSeconds`    | unavailable                               |

The profile query is therefore the source of reel discovery, pagination, and
`play_count`.

### 3. Individual reel details

`InstagramScraper::fetchReel()` accepts the profile/list `InstagramReelData`, delegates
the detail request to a persistent Python process, and returns a complete merged DTO.
`fetchReelByShortcode()` remains available for callers that only have a URL/shortcode.
The Python client uses `curl_cffi` because Instagram only returns the logged-out
detail GraphQL response to requests with a browser-compatible TLS fingerprint.
Ordinary PHP/libcurl receives the Instagram homepage instead of JSON.

For each proxy session, the Python process first creates an anonymous Instagram session:

```text
GET https://www.instagram.com/
```

This supplies anonymous `csrftoken` and `LSD` tokens. Before fetching a reel it checks
whether the media is available:

```text
GET https://www.instagram.com/api/v1/web/get_ruling_for_content/
    ?content_type=MEDIA
    &target_id={media_pk}
```

It then requests the detail payload:

```text
POST https://www.instagram.com/api/graphql
doc_id=27130156389949648
variables={"media_id":"{media_pk}"}
friendly_name=PolarisLoggedOutDesktopWWWPostRootContentQuery
```

The reel data is found at:

```text
data.xig_polaris_media.if_not_gated_logged_out
```

`InstagramReelGraphqlMapper` maps the detail response:

| `InstagramReelData` field | Detail response source              |
|---------------------------|-------------------------------------|
| `shortcode`               | `code`                              |
| `instagramMediaPk`        | `pk`                                |
| `takenAt`                 | `taken_at`                          |
| `captionText`             | `caption.text`                      |
| `likeCount`               | `like_count`                        |
| `commentCount`            | `comment_count`                     |
| `videoUrl`                | `video_versions[0].url`             |
| `thumbnailUrl`            | `image_versions2.candidates[0].url` |
| `videoDurationSeconds`    | parsed from `video_dash_manifest`   |
| `playCount`               | not returned by this detail document |

`InstagramReelDataMerger` retains `playCount` from the profile response while applying
the detail fields. The current logged-out detail document does not contain
`play_count`, `view_count`, or `video_view_count`; consumers do not need to implement
this merge themselves.

## Resolving `playCount`

The persisted Instagram GraphQL documents have fixed selections:

- the profile document returns `play_count`, but not caption/date/video details;
- the detail document returns caption/date/video details, but not `play_count`.

When `fetchReel()` receives a DTO produced by `fetchProfileReelsPage()`, its
`rawData.play_count` marks it as a profile snapshot. No additional profile request is
made; the scraper only fetches the detail document and merges the two DTOs.

When the caller only has a shortcode, or passes a stored DTO without a profile
snapshot, the scraper:

1. fetches the detail document and reads `user.pk`;
2. requests the owner's reels in pages of 12;
3. matches the target by shortcode;
4. merges that profile result with the detail result.

The fallback stops when the reel is found, Instagram reports no next page, or
`profileReelLookupMaxPages` is reached. Although the document accepts `page_size`,
live requests for 50 and 100 items are currently capped by Instagram at 12. Relay's
`after`/`before` values are opaque cursors from a previous response. They cannot be
derived from a shortcode or media PK, and the profile document exposes no single-media
filter.

This still avoids the old per-reel HTML request, whose response was substantially
larger than the detail JSON response.

## Python process and session reuse

`InstagramReelGraphqlClient` starts one long-lived Python child process per PHP client
instance and communicates with it using one-line JSON messages over stdin/stdout.

The Python process:

- keeps one `curl_cffi.requests.Session` per configured proxy identity;
- reuses Instagram cookies and tokens across reel requests;
- refreshes anonymous sessions after the configured TTL;
- retries once with a fresh anonymous session when ruling or GraphQL fails;
- derives a numeric media PK from the shortcode when the caller does not provide one.

For Laravel Horizon or another long-running worker, register `InstagramScraper` as a
singleton so the Python process and its sessions survive across jobs handled by that
worker.

## Proxy behavior

PHP chooses a proxy from `InstagramScraperConfig::$proxies` for each reel request and
sends its host, port, username, and password to the Python process over stdin. Proxy
credentials are not included in the child-process command line.

If sticky proxy credentials are used, keep them stable for at least the anonymous
session TTL. The default TTL is 300 seconds.

## Installation

PHP requirements are installed with Composer:

```bash
composer install
```

Create a Python virtual environment and install the browser-impersonating HTTP client:

```bash
python3 -m venv .venv
.venv/bin/pip install -r python/requirements.txt
```

Pass the virtual environment's Python executable to the configuration:

```php
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;

$config = new InstagramScraperConfig(
    graphqlCsrfToken: '...',
    graphqlAppId: '936619743392459',
    proxies: $proxies,
    requestLogger: $requestLogger,
    pythonExecutable: __DIR__ . '/.venv/bin/python',
);
```

Relevant optional settings:

| Setting | Default | Purpose |
|---|---:|---|
| `pythonClientScriptPath` | bundled script | Override the Python script location |
| `browserImpersonation` | `chrome` | `curl_cffi` browser fingerprint |
| `pythonRequestTimeoutSeconds` | `45` | Timeout for one Instagram request |
| `anonymousSessionTtlSeconds` | `300` | Anonymous cookie/token reuse period |
| `profileReelLookupMaxPages` | `30` | Maximum 12-item pages for shortcode-only play-count lookup |

The PHP runtime must allow `proc_open`.

## Request logging

When a `RequestLogger` is configured, the client logs:

- anonymous-session initialization metadata, without storing the homepage body;
- the profile-resolution GraphQL response;
- the small ruling response;
- the complete detail GraphQL response;
- status, duration, request body, and safe request headers.

Anonymous CSRF and LSD tokens and proxy credentials are excluded from logged headers.
The LSD form value is also redacted. Logging failures remain the responsibility of the
`RequestLogger` implementation and should not interrupt scraping.

## Error behavior

- A missing Python executable, missing script, invalid child-process response, or child
  process timeout raises `RuntimeException`.
- Profile GraphQL transport failures, non-2xx responses, empty responses, and invalid
  JSON raise `RuntimeException` so queue consumers can retry them with backoff. A
  rate-limited response is never mapped to a valid empty/terminal profile page.
- Instagram ruling/GraphQL rejection returns `null` after one fresh-session retry.
- The detail mapper returns `null` when the response does not contain a valid shortcode.
- The service rejects a response whose shortcode does not match the requested shortcode.
- Shortcode-only fetching can return a DTO with `playCount=null` when the reel is not
  found before the configured profile lookup limit.

## Tests

Run the Python tests with the configured virtual environment:

```bash
.venv/bin/python -m unittest discover -s python/tests
```

PHP syntax can be checked with:

```bash
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```
