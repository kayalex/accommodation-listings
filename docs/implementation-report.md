# Accommodation Listings System — Implementation Report

Author: Stayvista Team

Date: 2025-09-12

Abstract
The system is a web application enabling landlords to list off-campus properties and students to discover them, with admin oversight. It is implemented in PHP atop Supabase services (Auth, PostgREST, Storage). This report covers architecture, data model, APIs, security, performance, observability, and operations, with code references.

Keywords: PHP, Supabase, PostgREST, Storage, RLS, Tailwind, Student Housing

1. Introduction
Objectives
- Provide a discoverable catalogue of student accommodations with rich details.
- Support landlord CRUD over listings with file uploads.
- Allow students to report problematic listings for admin review.
- Ensure role-based access and data security with minimal backend complexity.
Scope and constraints
- Server-rendered PHP with includes; no framework.
- Supabase as the backend: Auth, PostgREST, Storage; PostgreSQL managed by Supabase.
- Tailwind via CDN for styling; Leaflet for maps.
Stakeholders and roles
- Students, Landlords, Administrators (see RBAC in [`includes/header.php`](includes/header.php)).

2. Methodology
- Incremental development with PHP includes ([`includes/header.php`](includes/header.php), [`includes/footer.php`](includes/footer.php)).
- Service classes encapsulate remote calls: [`Auth`](api/auth.php:8), [`PropertyListings`](api/fetch_listings.php:6), [`Reports`](api/reports.php:5).
- Supabase used as BaaS to minimize custom backend code.

3. System Overview
User journeys
- Student: browse ([`pages/listings.php`](pages/listings.php)), view details ([`pages/listing_detail.php`](pages/listing_detail.php)), report ([`pages/report.php`](pages/report.php)).
- Landlord: sign up ([`pages/sign-up.php`](pages/sign-up.php)), add/edit/delete listings ([`pages/add-property.php`](pages/add-property.php), [`pages/edit-property.php`](pages/edit-property.php), [`pages/delete-property.php`](pages/delete-property.php)), dashboard ([`pages/dashboard.php`](pages/dashboard.php)).
- Admin: users, properties, verifications, reports ([`pages/admin.php`](pages/admin.php)).
Site shell
- Navigation and session state in [`includes/header.php`](includes/header.php); footer in [`includes/footer.php`](includes/footer.php).

4. Architecture
Components
- PHP pages (view/controller blend), service classes (Auth, Listings, Reports).
- Supabase services: Auth, PostgREST (REST over Postgres), Storage buckets.
Configuration
- Application and Supabase keys in [`config/config.php`](config/config.php).
Core classes and key methods
- [`Auth::register()`](api/auth.php:62), [`Auth::confirmEmail()`](api/auth.php:114), [`Auth::login()`](api/auth.php:136), [`Auth::updateProfile()`](api/auth.php:215), [`Auth::updatePassword()`](api/auth.php:270).
- [`PropertyListings::getAllProperties()`](api/fetch_listings.php:32), [`PropertyListings::getPropertyById()`](api/fetch_listings.php:80), [`PropertyListings::getPropertiesByLandlord()`](api/fetch_listings.php:186).
- [`Reports::submitReport()`](api/reports.php:22), [`Reports::getReports()`](api/reports.php:82), [`Reports::updateReportStatus()`](api/reports.php:105).
Data flow (high level)
- Browser -> PHP page -> Service class -> Supabase API -> JSON -> Rendered HTML.

5. Supabase Configuration and RLS Policies
Auth
- Email/password signup with optional email verification via [`Auth::confirmEmail()`](api/auth.php:114).
PostgREST access
- Server-side calls use either anon key or user access token. Writes that require user identity should use access token (e.g., Storage and row inserts).
Storage buckets
- properties: public assets for listings (created if absent by [`pages/add-property.php`](pages/add-property.php)).
- verification: landlord NRC documents (accessed for admin review).
Recommended RLS patterns (to be configured in Supabase)
- properties: enable RLS; insert/update/delete where auth.uid() = landlord_id; select is public or constrained by use-case.
- property_images: RLS referencing property ownership.
- property_amenities: RLS referencing property ownership.
- profiles: select self or admin; landlords visible to students for listing cards; updates limited to self; is_verified only by admin.
- reports: insert by authenticated users; select by admin; reporter can select own.
Storage policies
- Bucket properties: allow upload and delete where folder prefix contains auth.uid(); public read; forbid listing root.
- Bucket verification: only owner upload; admin read; public links avoided or time-limited.
Keys management
- Move secrets out of [`config/config.php`](config/config.php) into environment variables; never commit service role keys.

6. Database Design and Schema
ER model (inferred)
- profiles (1) — (M) properties
- properties (1) — (M) property_images
- properties (M) — (M) amenities via property_amenities
- profiles (1) — (M) reports (as reporter); profiles (1) — (M) reports (as landlord)
Table specifications (inferred from code)
- profiles: id uuid, name text, role text, email text, phone text, unique_id text, verification_document text, is_verified int, created_at timestamptz. See usage in [`Auth::getUserProfile()`](api/auth.php:25).
- properties: id int, title text, description text, price numeric, latitude float8, longitude float8, address text, landlord_id uuid, target_university text, is_published int, created_at timestamptz. See [`PropertyListings::getAllProperties()`](api/fetch_listings.php:32).
- property_images: id int, property_id int, storage_path text, is_primary bool. See [`PropertyListings::attachPrimaryImages()`](api/fetch_listings.php:126).
- amenities: id int, name text. See [`pages/add-property.php`](pages/add-property.php).
- property_amenities: id int, property_id int, amenity_id int. See [`pages/edit-property.php`](pages/edit-property.php).
- reports: id int, listing_id int, landlord_id uuid, reported_by uuid, reason text, status text, created_at timestamptz. See [`Reports::submitReport()`](api/reports.php:22) and [`api/submit_report.php`](api/submit_report.php).
Note: Types are inferred; confirm with Supabase schema.

7. Authentication and Authorization
Registration flow
- [`Auth::register()`](api/auth.php:62) calls Supabase /auth/v1/signup, then inserts the profile row with role and [`Auth::generateUniqueId()`](api/auth.php:58).
Email verification
- [`Auth::confirmEmail()`](api/auth.php:114) verifies tokens from Supabase email links.
Login and session
- [`Auth::login()`](api/auth.php:136) exchanges credentials for access token and composes $_SESSION['user'] with auth and profile via [`Auth::getUserProfile()`](api/auth.php:25).
Role checks
- Pages gate access via [`Auth::isAuthenticated()`](api/auth.php:186) and [`Auth::getUserRole()`](api/auth.php:204), e.g., [`pages/admin.php`](pages/admin.php), [`pages/add-property.php`](pages/add-property.php).
Password management
- [`Auth::updatePassword()`](api/auth.php:270) verifies current password by re-login then updates via Supabase /auth/v1/user.

8. API Layer and Business Logic
Listings retrieval
- [`PropertyListings::getAllProperties()`](api/fetch_listings.php:32) composes query params (filters: university, type, price range), pulls profile fields, and orders by created_at; then hydrates image_url via [`PropertyListings::attachPrimaryImages()`](api/fetch_listings.php:126).
- [`PropertyListings::getPropertyById()`](api/fetch_listings.php:80) fetches property with profiles, images, amenities; converts storage paths to public URLs via [`PropertyListings::getPublicUrl()`](api/fetch_listings.php:173).
Landlord listings
- [`PropertyListings::getPropertiesByLandlord()`](api/fetch_listings.php:186) filters by landlord_id for dashboard views.
Reporting flow
- Modal UI in [`pages/report.php`](pages/report.php) posts to [`api/submit_report.php`](api/submit_report.php), which validates session and calls [`Reports::submitReport()`](api/reports.php:22).
Admin workflows
- Reports moderation in [`pages/admin.php`](pages/admin.php) with [`Reports::updateReportStatus()`](api/reports.php:105).
- Property deletion cascades images and amenities before removing the property in [`pages/admin.php`](pages/admin.php) and [`pages/delete-property.php`](pages/delete-property.php).

9. User Interface and Flows
Shell and navigation
- [`includes/header.php`](includes/header.php) initializes Auth, exposes $isLoggedIn/$userRole, renders menus; [`includes/footer.php`](includes/footer.php) provides footer and scripts.
Browsing
- Home showcases latest listings ([`pages/index.php`](pages/index.php)); filters applied in [`pages/listings.php`](pages/listings.php) with sanitized inputs and price validation.
Details
- [`pages/listing_detail.php`](pages/listing_detail.php) renders images, amenities, map (Leaflet), and landlord contacts with verified badge.
Landlord CRUD
- Add: [`pages/add-property.php`](pages/add-property.php) validates inputs, uploads images to Storage with user JWT, inserts images and amenities rows.
- Edit: [`pages/edit-property.php`](pages/edit-property.php) updates property and re-syncs amenities.
- Delete: [`pages/delete-property.php`](pages/delete-property.php) ownership verified before delete.
Profile and verification
- [`pages/edit-profile.php`](pages/edit-profile.php) updates name/phone, uploads verification doc to verification bucket, sets is_verified state.
Admin
- [`pages/admin.php`](pages/admin.php) tabs: users (filters), properties (filters), verifications (approve/reject with doc links), reports (status chips).

10. Algorithms and Implementation Techniques
Unique short IDs
- [`Auth::generateUniqueId()`](api/auth.php:58) creates a 5-digit string; backfill helper in [`scripts/update_user_ids.php`](scripts/update_user_ids.php).
Image handling lifecycle
- File validations (MIME, extension, size), unique naming, per-user foldering, upload via Storage REST with Authorization: Bearer user access token in [`pages/add-property.php`](pages/add-property.php).
Join hydration and URL building
- Primary image selection in [`PropertyListings::attachPrimaryImages()`](api/fetch_listings.php:126); public URL build in [`PropertyListings::getPublicUrl()`](api/fetch_listings.php:173).
Consistency on edits
- Amenity set re-sync (delete-then-insert) in [`pages/edit-property.php`](pages/edit-property.php).

11. Security and Privacy Posture
Strengths
- Server-side session; widespread output encoding via htmlspecialchars; RLS-ready structure; role-gated pages; minimal exposed endpoints.
Risks and improvements
- Secrets in repo: move keys from [`config/config.php`](config/config.php) to env vars; never commit service role keys.
- Mixed token usage: standardize on user token for user-owned writes; use server-side secret only where RLS demands and code runs on trusted server.
- CSRF: add tokens to forms (login, sign-up, add/edit/delete property, report submission, admin forms).
- Rate limiting: apply per-IP throttle to report submission and auth flows.
- Input validation: centralize sanitization; enforce server-side validation for all numeric and enum fields.
- Storage access: restrict verification bucket reads to admin; avoid public URLs for sensitive docs.
- .htaccess: enforce security headers and disable directory listing.

12. Performance and Scalability
Query efficiency
- Use pagination (limit/offset or range headers) for listings and admin tables; currently fetches all.
- Prefer selecting only necessary fields; existing joins are selective but can be trimmed.
N+1 avoidance
- [`PropertyListings::attachPrimaryImages()`](api/fetch_listings.php:126) minimizes per-property lookups by batching with IN(); validate for large sets.
Caching
- Page-level caching for anonymous listing pages; HTTP caching for public images.
Asset delivery
- Tailwind CDN configured in [`includes/header.php`](includes/header.php); consider compiling static CSS and using SRI to pin versions.

13. Error Handling and Observability
Current approach
- Scattered error_log() calls; explicit debug surfaces in [`api/reports.php`](api/reports.php:54) and [`api/submit_report.php`](api/submit_report.php:56).
Recommendations
- Central logging with channels (app, auth, storage); JSON logs to files under logs/ (e.g., logs/report_errors.log).
- User-friendly error pages and suppression of notices/warnings in production.
- Add request IDs to correlate logs across layers.

14. DevOps and Operational Runbook
Configuration and secrets
- Export SUPABASE_URL and SUPABASE_KEY via environment; read in [`config/config.php`](config/config.php).
- Separate anon and service keys; lock down usage to server-only contexts; rotate keys on schedule.
Local development
- XAMPP stack; configure Apache vhost to /accommodation-listings; ensure PHP cURL enabled.
Deployment
- Sync code; configure environment; set file permissions; enable HTTPS; set security headers in .htaccess.
Storage buckets
- Pre-create properties and verification buckets with policies; define lifecycle rules if needed.
Operations
- Account verification process and SLAs; report triage steps; data retention policies.

15. Testing Strategy
Unit and integration
- Mock Supabase endpoints for [`Auth`](api/auth.php:8), [`PropertyListings`](api/fetch_listings.php:6), [`Reports`](api/reports.php:5).
End-to-end
- Critical paths: sign-up, login, add property, browse, detail, report, admin review.
Security tests
- CSRF, XSS in inputs rendered in [`pages/listings.php`](pages/listings.php) and [`pages/listing_detail.php`](pages/listing_detail.php); Storage upload abuse; RLS enforcement checks.
Performance tests
- Listing pagination; image CDN delivery; large landlord portfolios.

16. Risk Register (selected)
- Secret leakage in repo: High likelihood/impact — Mitigation: env vars, key rotation, secret scanning.
- RLS misconfiguration: Medium likelihood, High impact — Mitigation: policy review, integration tests.
- Public access to verification docs: Low likelihood, High impact — Mitigation: private bucket, signed URLs, admin-only policies.
- Missing CSRF: Medium likelihood, Medium impact — Mitigation: add tokens across forms.

17. Limitations and Future Work
- Not yet implemented: favorites, messaging, reviews/ratings, availability scheduling, password reset.
- Technical: empty [`.htaccess`](.htaccess) and reliance on CDN without SRI.
- Planned: SSR+SPA hybrid, improved admin analytics, audit logs, notifications.

18. Conclusion
The system demonstrates a pragmatic architecture that leverages Supabase to deliver core marketplace features with minimal infrastructure. Implementing the recommended security and operational practices will harden the platform for production use and support feature growth.

19. Appendices
A. Example API calls
- Fetch listings (with filters) similar to [`PropertyListings::getAllProperties()`](api/fetch_listings.php:32).
- Submit report via [`api/submit_report.php`](api/submit_report.php).
B. Selected code listings (pointers)
- [`Auth::login()`](api/auth.php:136), [`Auth::updateProfile()`](api/auth.php:215)
- [`PropertyListings::getPropertyById()`](api/fetch_listings.php:80)
- [`Reports::updateReportStatus()`](api/reports.php:105)

End of document.