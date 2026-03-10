# Frontend Integration API Reference

All endpoints are prefixed with `/api`. All requests/responses are JSON unless noted.
The `lang` header (or `?lang=en`) sets the active locale on every request.

---

## Authentication

### Admin Panel

```
POST /api/admin/login
Body: { username, password, grecaptcha? }
Returns: { access_token, token_type, expires_in, user }
```

All subsequent admin requests require:
```
Authorization: Bearer <access_token>
```

### Public Users (Members)

```
POST /api/view/user/login        { username, password }
POST /api/view/user/socLogin     { provider, token }     — OAuth social login
POST /api/view/user/logout
POST /api/view/user/memberRegistration
POST /api/view/user/resetForgottenPassword
```

Authenticated member endpoints (require Bearer token):
```
POST /api/member/me                   — get current user profile
POST /api/member/refresh              — refresh JWT token
POST /api/member/updateProfileData
POST /api/member/updatePhone
```

---

## Bootstrap Calls

These two endpoints are the **mandatory first calls** on app init. Call them in parallel.

### 1. `POST /api/view/main/indx`

Returns environment data that does not change by locale. Cache aggressively.

**Optional body:** `{ qr: ["menus","locales",...] }` — pass a list of keys to receive only those fields (response shortener).

**Response shape:**
```json
{
  "locale": "en",
  "locales": { "en": "English", "ge": "Georgian" },
  "server_time": "Mon Mar 10 2026 12:00:00 +0400",
  "siteMenus": { "main": [], "footer": [] },
  "sitePlaceHolders": {},
  "contentTypes": { "news": { "title": "News" }, ... },
  "menus": [ /* full tree with urls, configs, nested children */ ],
  "thinMenu": [ /* flat list: id, title, pid, url, configs, menu_type */ ],
  "static": "https://domain.com/storage",
  "siteSettings": { "key": "value" },
  "smartLayouts": { "Default": {}, "Other": {} },
  "cookies": "HTML string for cookie banner (current locale)",
  "xrates": { ... },
  "exectime": 0.05
}
```

**Key fields for routing:**
- `thinMenu` — use this for client-side route generation. Each item: `{ id, title, pid, url, configs[], menu_type }`.
- `menus` — full tree with nested `children`, `redirect_url`, `fullUrls`, `configs`.
- `locales` — all supported site locales.
- `static` — base URL for all media/file assets.

### 2. `POST /api/view/main/indxTranslatable`

Returns locale-specific data. Re-fetch on locale change.

**Response shape:**
```json
{
  "locale": "en",
  "server_time": "...",
  "home_content": { "home_content": { ... }, "home_id": 14 },
  "widgets": { "widgetName": { ... } },
  "generalPages": { "list": [ /* privacy, terms-condition pages */ ] },
  "smartForms": [ /* form builder forms */ ],
  "cookies": "HTML string"
}
```

---

## Content Endpoints

### Get Page Content by Menu ID

```
POST /api/view/main/getCurrentContent
Body: {
  contentid: 14,         // menu ID (required)
  singleview: 42,        // content item ID → triggers single-item mode
  productview: 99        // product ID → triggers product single mode
}
```

The menu item configuration in the CMS stores `secondary_data` — a list of **smart components** attached to that page. This endpoint resolves all enabled components, fetches their data, and returns them keyed as `{ComponentName}_{unicId}`.

**Response shape:**
```json
{
  "secondary": {
    "NewsList_abc123": {
      "data": { "list": [...], "listCount": 20, "page": 1 },
      "conf": { /* component config */ }
    },
    "MainBanner_xyz": { "data": {...}, "conf": {...} }
  },
  "exectime": 0.12
}
```

**Single-view mode:** Pass `singleview=<contentId>` or `productview=<productId>` alongside `contentid`. Components marked `singleLayout: 1` activate; list-only components are skipped.

---

### Get Paginated Content List

```
POST /api/view/main/getDataList
Body: {
  id: 14,                     // menu ID (required unless contentType provided)
  contentType: "news",        // alternative to id — fetch by type directly
  componentUnicId: "abc123",  // target a specific component's config from the menu
  page: 1,
  perPage: 12,                // alias: limit
  searchText: "query",
  searchDate: ["2024-01-01", "2024-12-31"],
  searchTerms: 93,            // single term ID → auto-detects taxonomy
  searchTerms: [93, 94],      // multiple term IDs
  taxonomies: { "news_category": [1, 5], "timeline_year": [3] },
  taxonomies_or: { ... },     // OR-logic version
  taxonomy: "news_category",  // unfiltered (reset)
  exclude: 42,                // exclude a content ID
  ids: [1, 2, 3],             // fetch specific IDs only
  pageOrder: "asc"|"desc",
  translate: "en"             // override locale
}
```

**Response shape:**
```json
{
  "list": [ { "id": 1, "title": "...", "slug": "...", "date": "...", ... } ],
  "listCount": 84,
  "page": 1,
  "pageCount": 7,
  "exectime": 0.08,
  "componentUnicId": "abc123"
}
```

> **Note:** For product content use `POST /api/view/smartShop/{method}` instead — `getDataList` rejects `content_type=product`.

---

### Full-Text Search

```
POST /api/view/main/search
Body: {
  searchText: "query",          // required
  contentType: ["news","page"], // optional — filter to these types only
  contentFields: ["title","teaser"], // optional — trim response data to these fields
  limit: 10,
  page: 1
}
```

**Response shape:**
```json
{
  "list": [
    {
      "id": 42,
      "title": "...matched excerpt...",
      "url": "/en/news/some-slug",
      "content_type": "news",
      "content_type_title": "News",
      "result_type": "content"|"file",
      "data": { /* full item or filtered by contentFields */ }
    }
  ],
  "listCount": 150,
  "page": 1,
  "pageMatchCount": 10
}
```

Searchable content types (configured via `searchable: 1` in `adminpanel.content_types`):
`page`, `banner`, `news`, `investment_faq`, `investment_strategy`, `shareholder_meetings`, `portfolio_company`, `governance`, `history`, `annual_reports`, `investor_day_presentations`, `financial_statements`, `financial_results`, `view_reports`, `share_trading`, `navHighlights`, `esg_charts`

---

### Get UI String Translations

```
POST /api/view/main/getTranslations
```

Returns all DB-stored UI strings keyed by translation key, grouped by locale.

```json
{ "en": { "submit": "Submit", "loading": "Loading..." }, "ge": { ... } }
```

Cache this response. Invalidate when admin updates strings.

---

### Get Taxonomy Terms

Taxonomy terms are referenced by content items and used for filtering.

```
POST /api/view/main/getAttachedTaxonomies
Body: { contentid: 14 }   // menu ID
```

Returns terms attached to items on that page.

For a full terms list by taxonomy slug:

```
POST /api/view/main/parts/getTerms
Body: { taxonomy: "news_category" }
// or multiple:
Body: { taxonomy: ["news_category", "timeline_year"] }
```

**Available taxonomy slugs** (from `adminpanel.taxonomy`):

| Slug | Select mode | Used by content types |
|---|---|---|
| `portfolio_category` | single | `portfolio_company` |
| `investor_type` | single | — |
| `timeline_year` | single | `news`, `annual_reports`, `investor_day_presentations`, `financial_statements`, `financial_results`, `shareholder_meetings` |
| `history_category` | single | `history` |
| `financial_results_category` | single | `financial_results` |
| `history_year` | multi | `history` |

---

### Submit Online Form

```
POST /api/view/main/saveSubmitedForm
Body: {
  formType: "subscribeForm",   // must match key in adminpanel.onlineForms
  g-recaptcha-response: "...", // required when reCAPTCHA enabled
  // + any form fields
}
```

Configured forms (from `adminpanel.onlineForms`):
- `subscribeForm` — validates `email` uniqueness.

Custom forms not in the config are saved generically after reCAPTCHA validation.

---

### Email Subscription

```
POST /api/subscription/subscribe     { email, name?, ... }
POST /api/subscription/unsubscribe   { email }
GET  /api/subscription/confirm       ?token=xxx
POST /api/subscription/sendManagementLink   { email }
POST /api/subscription/updateData    { token, ... }
GET  /api/subscription/getData       ?token=xxx
```

---

### Utility Endpoints

```
POST /api/view/main/getValidCookiesData
// Returns active cookie consent groups based on browser cookies

POST /api/view/main/getBulkData
Body: { node: "servicesMap" | "getServiceCenters" }
// Returns content of serviceMap or serviceCenters content type

POST /api/view/main/getServiceCenters
// Returns list of serviceCenters content items

POST /api/view/main/getCalendar
Body: { taxonomy: [102, 107] }
// Returns distinct year-month dates for blog content filtered by taxonomy

POST /api/view/main/adwrd
Body: { wrd: "some.key" }
// Registers a new translation key if missing; returns the translation record

POST /api/view/main/uploadfile
Body: FormData { file, type: "image" }
// Uploads a private file (jpeg/jpg/png/pdf/docx, max 10MB)
// Returns: { url, filename, ... }
```

---

## Content Types Reference

Defined in `config/adminpanel.php` → `content_types`. Each type maps to its own set of meta fields.

| Slug | Title | Has taxonomy | Notable fields |
|---|---|---|---|
| `page` | Pages | — | `title`, `content` (multifield2) |
| `banner` | Banners | — | `title`, `teaser`, `url`, `image`, `video`, `portfolio` |
| `news` | News | `timeline_year` | `title`, `teaser`, `image`, `banner_image`, `content` |
| `governance` | Governance | — | `title` (name), `teaser` (position), `image`, `content`, `linkedin`, `soc_x`, `mail` |
| `history` | History | `history_category`, `history_year` | `title`, `content`, `image` |
| `portfolio_company` | Portfolio Company | `portfolio_category` | `title`, `image`, `company_website`, `company_status`, `sector`, `year_of_investment`, `content`, `editor` |
| `annual_reports` | Annual Reports | `timeline_year` | `title`, `image`, `pdf_files`, `xhtml_files` |
| `financial_results` | Financial Results | `timeline_year`, `financial_results_category` | `title`, `quarter` (q1–q4), `file` |
| `financial_statements` | Financial Statements | `timeline_year` | `title`, `period_type`, `file` |
| `investor_day_presentations` | Investor Day Presentations | `timeline_year` | `title`, `file` |
| `shareholder_meetings` | Shareholder Meetings | `timeline_year` | `title`, `content` (editor+file) |
| `credit_ratings` | Credit Ratings | — | `title`, `content` (text+image+file) |
| `navHighlights` | NAV Highlights | — | `title`, `change`, `navHighlights`, `tooltipDiagram` |
| `esg_charts` | ESG Charts | — | various chart data fields |
| `investment_faq` | Investment FAQ | — | `title`, `content` (editor) |
| `investment_strategy` | Investment Strategy | — | `title`, `content` (editor+image+text) |
| `view_reports` | View Reports | — | `title`, `file` |
| `share_trading` | Share Trading | — | `title`, `stats_by_year` (table) |

---

## Smart Components Reference

Smart components are attached to menu items via the CMS. When `getCurrentContent` is called, only enabled components for the current view mode (list/single) are returned.

Each response key is `{ComponentName}_{unicId}` → `{ data: {...}, conf: {...} }`.

| Component | Content type(s) | Notes |
|---|---|---|
| `MainBanner` | `banner` | conf option: `hide-request-meeting-button` |
| `ImageBanner` | `banner` | — |
| `MenuBanner` | `banner` | conf option: `show-breadcrumbs` |
| `AboutUs` | `page` | — |
| `AboutBox` | `page` | — |
| `QuickLinks` | `page` | — |
| `ContactCard` | `page` | — |
| `Documents` | `page` | — |
| `BondsKeyData` | `page` | — |
| `MacroOverview` | `page` | — |
| `OpportunityBox` | `page` | — |
| `OurStrategy` | `page` | — |
| `GcapPortfolioChart` | `page` | conf option: `slot_next_component` |
| `TopShareholders` | `page` | conf option: `slot_next_component` |
| `NewsSlider` | `news` | — |
| `NewsList` | `news` | — |
| `NewsInner` | `news` | — |
| `SimilarNewsSlider` | `news` | — |
| `NavHighlights` | `navHighlights` | — |
| `NavHighlightsStats` | `navHighlights` | conf option: `slot_next_component` |
| `Governance` | `governance` | conf option: `five-in-row` |
| `Portfolio` | `banner` | — |
| `OurHistory` | `history` | — |
| `AnnualReports` | `annual_reports` | — |
| `DayPresentations` | `investor_day_presentations`, `financial_statements` | — |
| `ShareholderMeetings` | `shareholder_meetings` | — |
| `FinancialResults` | `financial_results` | — |
| `CreditRatings` | `credit_ratings` | — |
| `StrategyCard` | `investment_strategy` | — |
| `ManageSubscription` | `banner` | — |

---

## Custom Modules

### LTB Module (public)
```
ANY /api/view/ltb/{method}                  — public
ANY /api/view/ltb/private/{method}          — requires Bearer token
```

### BasisBank Module
```
ANY /api/view/bb/{method}
```

### Shop (public)
```
ANY /api/view/smartShop/{method}
ANY /api/view/smartShop/private/{method}    — requires Bearer token
ANY /api/view/cart/{method}
```

---

## Caching Notes

The backend uses file-based cache. Cache duration is controlled by these `.env` variables:

| Variable | Default | Applies to |
|---|---|---|
| `CACHE_INDX` | 2 min | `/indx`, `/indxTranslatable`, sitemap |
| `CACHE_LIST_VIEW` | 2 min | list content responses |
| `CACHE_SINGLE_VIEW` | 2 min | single content responses |
| `CACHE_STRINGS` | 2 min | translation strings |

Cache keys include the locale and request parameters, so different locales/filters get separate cache entries.

Admin panel has a `POST /api/admin/main/clear-cache` endpoint to flush all caches.

---

## Media / Static Files

All media URLs returned by the API are **relative paths**. Prefix them with the `static` value from `/indx`:

```js
const imageUrl = indx.static + item.image[0].url
// e.g. "https://example.com/storage" + "/media/2024/photo.jpg"
```

---

## Typical Frontend Startup Sequence

```
1. GET locale from URL prefix (e.g. /en/..., /ge/...)
2. Set `lang` header = current locale
3. Parallel:
   a. POST /api/view/main/indx          → routing, menus, settings
   b. POST /api/view/main/indxTranslatable  → home content, widgets
   c. POST /api/view/main/getTranslations   → UI strings
4. Match current URL to thinMenu to find contentid
5. POST /api/view/main/getCurrentContent { contentid }
   → renders smart components for that page
6. On pagination/filter: POST /api/view/main/getDataList
```
