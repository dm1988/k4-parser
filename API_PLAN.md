# Executive Summary
We are adding an automated schedule parsing feature to the app. Instead of processing schedules directly inside the mobile app, your backend server will act as a secure bridge: receiving screenshots or PDFs from mobile users, sending them to the Crew Compass API, and passing back a clean calendar file (.ics) for the user's phone.

## How It Works (The User Experience)
[ Mobile App User ]
       │
       │  1. Uploads roster PDF or screenshots (up to 5 images)
       ▼
[ Your App Backend ]  ◄── (App Creator's Responsibility)
       │
       │  2. Sends files securely via server-to-server API (mTLS)
       ▼
[ Crew Compass API ] ──► [ AI / OCR Processing Engine ]
       │                                  │
       │  3. Processes schedule & returns calendar download link
       ▼
[ Mobile App User ]
       │
       └─► 4. App receives .ics file & prompts user: "Add to Calendar"

# Key Rules & Constraints
Rule                Constraint
Max File Size       12 MB per file
Allowed Files       1 PDF document OR 1–5 Images (.png, .jpg, .webp)
No File Mixing      Do not allow users to upload a PDF and images in the same request
Security            Mutual TLS (mTLS) client certificate + Fixed Server Egress IP required
Authentication      Server-to-server certificate authentication (no API keys or user passwords required)

# API: trusted-client schedule extraction

## Goal and trust boundary

* Add a versioned API for the iPhone app and Ben Napier's Crew Room service to submit schedules and download the generated calendar file.
* Keep the API deny-by-default: only explicitly provisioned clients may connect.
* Treat this as a server-to-server API. The iPhone app must call through an approved backend; a distributed mobile app is not a known host and cannot safely hold a shared client certificate.
* Do not use CORS or the request `Host` header as client authentication. CORS only constrains browsers, and Laravel trusted-host validation protects the destination host rather than proving who sent a request.

## Client authentication and network controls

* Expose the API only over HTTPS through the production reverse proxy or API gateway; prevent direct public access to the application server.
* Require mutual TLS (mTLS). Issue a separate client certificate for each approved integration so it can be identified, rotated, and revoked independently.
* Allowlist each client's fixed egress IP/CIDR at the edge when stable addresses are available; use this as a second control, not as a replacement for mTLS.
* Configure the proxy to validate the client-certificate chain and revocation status, strip any inbound certificate identity headers, and forward a verified client identity to Laravel only over the trusted internal connection.
* Add an application middleware that maps the verified identity to an enabled API client and rejects missing, unknown, disabled, expired, or mismatched clients before controller code runs.
* Store client names, certificate fingerprints/subjects, status, ownership, and last-used timestamps without storing private keys. Keep CA material and certificate secrets in the deployment secret store.
* Give each client an explicit `schedule:extract` capability and apply a named rate limiter keyed by verified client ID plus source IP.
* Apply request-size limits at both the proxy and Laravel layers, and structured audit logging without logging uploaded schedule contents, certificates, or credentials.
* Ensure bootstrap/app.php explicitly configures the application's trusted proxies:
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: [
        '10.0.0.0/8', // Your reverse proxy IP / CIDR
    ]);
})
* If Laravel is not configured to trust only the specific ingress proxy IP, an attacker could bypass mTLS by spoofing internal identity headers directly against the web server.
* Scope: Ensure the idempotency database index is scoped composite: unique(['api_client_id', 'idempotency_key']).
* Payload Hashing: Store a hash of the uploaded payload (or file hashes) alongside the key. If a client re-uses an Idempotency-Key but changes the uploaded schedule file, return 422 Unprocessable Entity or 409 Conflict rather than returning the previous cached job status.
* Retention: Clean up idempotency records on the same schedule as the private disk artifacts.

## API contract

* Register `routes/api.php` in `bootstrap/app.php` and place the contract under `/api/v1`.
* `POST /api/v1/schedule-extractions` accepts `multipart/form-data` with either one PDF or up to five JPEG, PNG, or WebP images. Reject mixed PDF/image batches and multiple PDFs; validate both extension and actual MIME type with the existing 12 MB per-file limit.
* API validation: 
    - 'files' => ['required', 'array', 'min:1', 'max:5'],
    - 'files.*' => ['file', 'max:12288', 'mimes:pdf,jpeg,png,webp', 'mimeTypes:application/pdf,image/jpeg,image/png,image/webp'],
* Accept optional `event_types[]` filters and an `Idempotency-Key` header so client retries cannot create duplicate work.
* Persist uploads to a private disk, create an extraction owned by the authenticated API client, dispatch a queued job, and return `202 Accepted` with the extraction UUID, status, and status URL.
* `GET /api/v1/schedule-extractions/{extraction}` returns only that client's job status (`pending`, `processing`, `completed`, or `failed`). A completed response includes event counts, expiry time, and the calendar download URL; failures expose a stable public error code without internal exception details.
* `GET /api/v1/schedule-extractions/{extraction}/calendar` returns one full-calendar `.ics` file only when the URL signature is valid, has not expired, the extraction belongs to the authenticated client, and the job completed successfully. Per-event downloads are out of scope for v1.
* Use a Form Request, thin controllers, and API Resources to produce a stable snake_case JSON envelope instead of exposing internal DTO shapes. Document `401` for failed client authentication, `403` for ownership/disabled-client failures, `404` for unknown resources, `413` for oversized requests, `422` for invalid uploads or unparseable schedules, and `429` for throttling.
* Even with mTLS and URL signatures, ensure the signed download route doesn't bypass the custom client middleware.
Route::middleware(['api.mtls', 'capability:schedule:extract', 'signed'])
    ->get('/v1/schedule-extractions/{extraction}/calendar', ****::class)
    ->name('api.v1.schedule-extractions.calendar');

## Extraction and storage implementation

* Extract the reusable schedule-extraction workflow from the Livewire/session boundary so the web UI and API call the same validation, parsing, enrichment, logging, and ICS generation services.
* Remove the API path's dependency on `auth()`, `session()`, and `EngineResultCache`; use explicit API-client ownership and a persistent result repository while preserving the existing web cache isolation.
* Do not pass `UploadedFile` instances to the queue. Store inputs first and give the job private storage paths plus the API client and extraction identifiers.
* Extend the extraction record, or add a related API-extraction record, to attribute requests to an API client and track queue status, idempotency key, input paths, result path, expiry, error code, and timestamps. Add indexes for client/idempotency and status/created-at lookups; do not represent service clients as browser users.
* Generate the `.ics` artifact on a private disk. Use a short-lived signed route, aligned with the result retention period, that still requires client authentication and ownership; do not return a public storage URL.
* Delete source uploads and generated downloads after the configured retention period, including failed and abandoned jobs, while retaining non-sensitive request metadata for auditing.
* Make the job idempotent, set an explicit timeout and retry/backoff policy, prevent concurrent processing of the same extraction, and mark terminal failures consistently.

## Verification and rollout

* Add PHPUnit feature tests for valid trusted-proxy identity, missing/spoofed/unknown/revoked client identity, client capability checks, ownership isolation, per-client throttling, route middleware, and JSON content types.
* Test one PDF, one image, multiple images, mixed/unsupported/oversized files, empty extraction results, parser failures, duplicate idempotency keys, and successful retry behavior.
* Test the queued state transitions, private storage and cleanup, audit metadata, expiring signed download URL, tampered/expired signatures, cross-client download attempts, and JSON error shapes.
* Add staging ingress tests for mTLS validation, revoked/expired certificates, spoofed forwarded identity headers, source allowlists, and direct Laravel-origin bypass attempts.
* Add a deployment runbook for issuing, rotating, and revoking each client certificate; emergency client disablement; configuring trusted proxies and firewall rules; and confirming that the Laravel origin cannot be reached directly.
* Roll out to a staging hostname first, provision one client at a time, verify logs and rate limits, then enable production access.
* Run focused API and web-extraction regression tests, then run Pint for changed PHP files before the task is complete.


# Map
[ Client Egress ]
       │  (Fixed IP / mTLS Certificate)
       ▼
[ Edge Proxy / Ingress ] ──(Terminates mTLS, Strips Inbound Identity Headers)
       │  (Injects Internal Verified Header, e.g., X-Verified-Client-ID)
       ▼
[ Laravel Middleware ] ────(Validates Client Enabled Status, Rate Limit, Capability)
       │
       ├─► POST /api/v1/schedule-extractions
       │     └─► Store Files to Private Disk
       │     └─► Save Extraction Record (Client ID + Idempotency Key)
       │     └─► Dispatch Queued Job (Pass Path + Extraction UUID)
       │     └─► Return 202 Accepted
       │
       ├─► GET /api/v1/schedule-extractions/{uuid}
       │     └─► Query Job Status & Error/Result Metadata (Client Scoped)
       │
       └─► GET /api/v1/schedule-extractions/{uuid}/calendar
             └─► Validate Signature + Client Ownership -> Stream .ics Disk Stream


# OpenAPI 3.0 Specification
See API_PLAN.yml

# Developer Integration Guide
## Overview & Architecture Rules
* This API allows authorized integration partners to submit crew schedule documents (PDFs or screenshots) and receive a standardized iCalendar (.ics) file.

[ Your Application Server ] 
         │ 
         │  1. mTLS Connection over HTTPS
         │  2. Fixed Egress IP
         ▼
[ Crew Compass API Gateway ] ────► [ Schedule Processor Queue ]


## Critical Rules
* Server-to-Server Only: Mobile applications must not call this API directly. Requests must be proxied through your secure backend.
* Mutual TLS (mTLS): Every request must present the .crt / .key pair issued specifically to your client identity.
* File Batch Restrictions:
    - Option A: Exactly 1 PDF file.
    - Option B: 1 to 5 Image files (.jpg, .png, .webp).
    - Mixing PDFs and images in a single request will result in a 400 Bad Request.
    - Max file size is 12 MB per file.
## Integration Workflow
Client Server                      API Gateway                      Queue Worker
    │                                  │                                 │
    ├─── POST /schedule-extractions ──►│                                 │
    │    (Files + Idempotency Key)     ├── Save files to disk            │
    │                                  ├── Dispatch async job ──────────►│
    │◄── 202 Accepted (UUID) ──────────┤                                 │ Parse schedule &
    │                                  │                                 │ generate .ics file
    │                                  │                                 │
    ├─── GET /schedule-extractions/{id}│                                 │
    │◄── 200 OK (status: processing) ──┤                                 │
    │                                  │                                 │
    │    ... poll interval ...         │                                 │
    │                                  │                                 │
    ├─── GET /schedule-extractions/{id}│                                 │
    │◄── 200 OK (status: completed) ───┤                                 │
    │    (includes download_url)       │                                 │
    │                                  │                                 │
    ├─── GET {download_url} ──────────►│                                 │
    │◄── 200 OK (text/calendar .ics) ──┴─────────────────────────────────┘


# Code Examples
1. Submit Schedule for Extraction
Always supply an Idempotency-Key header with a unique UUID for each upload attempt.
## cURL
Bash
curl -X POST "https://k4.crewcompass.cc/api/v1/schedule-extractions" \
  --cert client-partner.crt \
  --key client-partner.key \
  -H "Idempotency-Key: e8a26bf0-891a-4638-892a-714cf5e282a1" \
  -F "files[]=@/path/to/roster1.png" \
  -F "files[]=@/path/to/roster2.png" \
  -F "event_types[]=flight" \
  -F "event_types[]=duty"
## Node.js (Axios)
JavaScript
const axios = require('axios');
const fs = require('fs');
const https = require('https');
const FormData = require('form-data');
const crypto = require('crypto');

const httpsAgent = new https.Agent({
  cert: fs.readFileSync('./certs/client.crt'),
  key: fs.readFileSync('./certs/client.key'),
});

async function submitExtraction(filePaths) {
  const form = new FormData();
  filePaths.forEach((filePath) => {
    form.append('files[]', fs.createReadStream(filePath));
  });

  const response = await axios.post('https://k4.crewcompass.cc/api/v1/schedule-extractions', form, {
    httpsAgent,
    headers: {
      ...form.getHeaders(),
      'Idempotency-Key': crypto.randomUUID(),
    },
  });

  return response.data.data; // { id, status, status_url }
}
## Python (Requests)
Python

import requests
import uuid

def submit_extraction(file_paths):
    cert = ('./certs/client.crt', './certs/client.key')
    headers = {'Idempotency-Key': str(uuid.uuid4())}
    
    files = [('files[]', open(path, 'rb')) for path in file_paths]
    
    response = requests.post(
        'https://k4.crewcompass.cc/api/v1/schedule-extractions',
        files=files,
        headers=headers,
        cert=cert
    )
    response.raise_for_status()
    return response.json()['data']
2. Poll Status & Download Calendar

Poll the returned status_url every 2–3 seconds until the status changes to completed or failed.
## Node.js Status & Download
JavaScript
async function pollAndDownload(statusUrl) {
  while (true) {
    const { data } = await axios.get(statusUrl, { httpsAgent });
    const extraction = data.data;

    if (extraction.status === 'completed') {
      console.log(`Extracted ${extraction.metrics.events_extracted} events.`);
      
      // Download the .ics file (must pass httpsAgent for mTLS)
      const calendarResponse = await axios.get(extraction.download_url, {
        httpsAgent,
        responseType: 'arraybuffer',
      });
      
      fs.writeFileSync('./schedule.ics', calendarResponse.data);
      break;
    } 
    
    if (extraction.status === 'failed') {
      throw new Error(`Extraction failed [${extraction.error.code}]: ${extraction.error.message}`);
    }

    // Wait 2.5 seconds before next poll
    await new Promise((resolve) => setTimeout(resolve, 2500));
  }
}
# Error Handling & Status Codes
All non-2xx responses return a structured JSON error body:
JSON
{
  "error": {
    "code": "ERROR_CODE_STRING",
    "message": "Human readable error explanation.",
    "details": null
  }
}
## Error Code Reference
HTTP Status     Error Code                  Description
400             MIXED_FILE_TYPES_REJECTED               Cannot mix PDFs and images in one request.
401             CLIENT_AUTHENTICATION_FAILED            Certificate is missing, expired, or invalid.
403             ACCESS_DENIED                           Client disabled or resource owned by another client.
404             RESOURCE_NOT_FOUND                      Extraction UUID does not exist.
413             FILE_TOO_LARGE                          A file exceeds the 12MB limit.
422             UNPARSEABLE_SCHEDULE_FORMAT             OCR/Parser could not find valid schedule events.429RATE_LIMIT_EXCEEDEDPer-client rate limit exceeded. Retry later.
