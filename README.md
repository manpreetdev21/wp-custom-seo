# WP Custom SEO

Modern SEO management for WordPress. Developed by Manpreet Singh.

Foundation, on-page SEO, the entity and schema graph layer, the aggregated
schema API, sitemaps, breadcrumbs, social metadata, the redirect manager with
its 404 monitor, the internal link graph, the bulk editor, Local SEO, the brand
entity, WooCommerce, the AI assistant, the site audit, AI internal linking and
FAQ generation, CSV import/export, migration from four other SEO plugins, WP-CLI
commands, a configurable score model, Search Console, Analytics, email reports,
AI crawler controls and the Abilities API.

Nothing here fabricates results it cannot produce. Where an external service is
not connected, the feature says so and shows nothing, rather than showing
zeroes or a placeholder.

Recommendations are recommendations. Nothing in this plugin is presented as a
guaranteed ranking factor, and no score here represents any search engine's
algorithm.

## Requirements

- PHP 8.1+ (tested on 8.4)
- WordPress 6.5+

## Install

Drop the folder in `wp-content/plugins/` and activate. Composer is optional —
without `vendor/`, a small PSR-4 fallback autoloader takes over.

For development:

```
composer install
composer test   # php tests/self-check.php
composer cs     # phpcs against WordPress standards
composer i18n   # regenerate languages/wp-custom-seo.pot (needs WP-CLI)
```

## Lifecycle

**Activation** creates the tables, adds the capability to administrators and
editors, and records the version. **Deactivation is non-destructive**: it
deletes nothing, and clears every scheduled event, because an event for a hook
nothing listens to any more is a row that sits in the cron option for good.

The list of those events is built from the constants the modules themselves
declare rather than repeated as literal strings — it had already drifted once,
and a hook that is renamed in one place and left scheduled here is invisible
until someone reads the cron option.

**Uninstall clears the scheduled events whatever your settings say** — they are
instructions to run code that is about to stop existing, not data. Everything
else is removed only if you ticked *Delete data on uninstall*: the tables, the
prefixed post, term and user meta, the prefixed options and transients, and the
capability. Data belonging to other plugins is never touched, including anything
this plugin imported *from* — a Yoast install is left exactly as it was.

## Translations

Text domain `wp-custom-seo`, template at `languages/wp-custom-seo.pot`, loaded
on `init` — WordPress warns when a domain is loaded earlier, and nothing here
needs a translated string before then.

## Architecture

| Path | Role |
| --- | --- |
| `wp-custom-seo.php` | Header, constants, autoloader selection, lifecycle hooks |
| `src/Core/Plugin.php` | Boots modules on `plugins_loaded`, applies pending migrations |
| `src/Core/Autoloader.php` | PSR-4 fallback for `WPCustomSeo\` when Composer is absent |
| `src/Core/Activator.php` | Activation (multisite-aware), non-destructive deactivation |
| `src/Core/Capabilities.php` | The `wpcseo_manage_seo` capability |
| `src/Core/Settings.php` | Schema-driven settings on one option, via the Settings API |
| `src/Database/Migrator.php` | Versioned, filterable migrations |
| `src/Admin/Menu.php` | SEO menu, filterable page registry, page-scoped assets |
| `src/Admin/MetaBox.php` | Editor SEO panel: fields, nonce, save |
| `src/SEO/Meta.php` | Meta key registration and access |
| `src/SEO/Templates.php` | `%%variable%%` expansion and truncation |
| `src/SEO/Frontend.php` | Title, description, canonical, robots output |
| `src/SEO/Analyzer.php` | On-page analysis and scoring |
| `src/SEO/Weights.php` | The score model: what each check is worth |
| `src/Entities/Registry.php` | Site entities and their stable `@id`s |
| `src/Entities/Authors.php` | Author profile fields |
| `src/Schema/Graph/Graph.php` | Node collection, merge-by-`@id` |
| `src/Schema/Graph/Pieces.php` | Request and post graph builders |
| `src/Schema/Validator.php` | Structural and referential validation |
| `src/Schema/Faq.php` | Detection of a visible FAQ, and its nodes |
| `src/Schema/Conflicts.php` | Detection of other schema emitters |
| `src/Schema/Output.php` | Front-end JSON-LD |
| `src/Schema/Aggregator.php` | Paginated site-wide schema |
| `src/Schema/Cache.php` | Version-keyed aggregation cache |
| `src/Admin/SchemaPage.php` | SEO → Schema |
| `src/Admin/ToolsPage.php` | SEO → Tools |
| `src/Database/Tables.php` | The two custom tables |
| `src/Redirects/Redirects.php` | Rule storage, validation, loop detection |
| `src/Redirects/Engine.php` | Request matching and dispatch |
| `src/Redirects/NotFound.php` | 404 logging and pruning |
| `src/Audit/Auditor.php` | The site audit |
| `src/Audit/Finding.php` | One finding, with its evidence |
| `src/Audit/Cannibalization.php` | Pages competing for one keyphrase |
| `src/Audit/Decay.php` | Pages worth re-reading |
| `src/Admin/AuditPage.php` | SEO → Site Audit |
| `src/Transfer/Sources.php` | Other SEO plugins' field maps and dialects |
| `src/Transfer/Import.php` | Batched copy from another plugin |
| `src/Transfer/Csv.php` | Spreadsheet export and import |
| `src/CLI/Command.php` | The `wp seo` commands |
| `src/Crawlers/AiCrawlers.php` | AI crawler controls in robots.txt |
| `src/Abilities/Abilities.php` | The `wp-custom-seo/` abilities |
| `src/Reports/Report.php` | What a periodic report contains |
| `src/Reports/Mailer.php` | Subject, body and delivery |
| `src/Reports/Schedule.php` | Cron reconciliation |
| `src/Analytics/Client.php` | GA4 Data API client |
| `src/Analytics/Engagement.php` | Organic landing pages and totals |
| `src/SearchConsole/Account.php` | Service account key validation and storage |
| `src/SearchConsole/Token.php` | Signed assertion and access tokens |
| `src/SearchConsole/Client.php` | The API client, cached and error-translated |
| `src/SearchConsole/Performance.php` | Report shaping |
| `src/Admin/SearchConsolePage.php` | SEO → Search Performance |
| `src/AI/ProviderInterface.php` | The provider contract |
| `src/AI/AbstractProvider.php` | HTTP transport and error translation |
| `src/AI/{Anthropic,OpenAI,Gemini}Provider.php` | The three shipped providers |
| `src/AI/Credentials.php` | Encrypted API key storage |
| `src/AI/Manager.php` | Provider selection, throttling, logging |
| `src/AI/Prompts/` | One class per prompt |
| `src/AI/Json.php` | Tolerant reader for structured model output |
| `src/Admin/BriefPage.php` | SEO → Content Brief |
| `src/AI/UsageLog.php` | Metadata-only usage log |
| `src/Admin/AIPage.php` | SEO → AI |
| `src/API/AIRoutes.php` | Generation endpoints |
| `src/Local/Locations.php` | Location post type, fields, shortcode |
| `src/Local/Schema.php` | LocalBusiness structured data |
| `src/WooCommerce/Integration.php` | Guard and WooCommerce hooks |
| `src/WooCommerce/Product.php` | Product structured data |
| `src/Links/Links.php` | Link graph storage and queries |
| `src/Links/Scanner.php` | Link extraction and batched rebuild |
| `src/Links/Candidates.php` | Real pages a post could link to |
| `src/Admin/RedirectsPage.php` | SEO → Redirects |
| `src/Admin/NotFoundPage.php` | SEO → 404 Monitor |
| `src/Admin/LinksPage.php` | SEO → Internal Links |
| `src/Admin/BulkEditorPage.php` | SEO → Bulk Editor |
| `src/Sitemap/Sitemap.php` | Shapes the core XML sitemap |
| `src/SEO/Breadcrumbs.php` | Trail builder, renderer, shortcode, block |
| `src/Social/Social.php` | Open Graph and X/Twitter tags |
| `src/functions.php` | Template functions for theme authors |
| `src/API/Routes.php` | Authenticated REST endpoints |
| `src/API/SchemaRoutes.php` | Public schema aggregation endpoints |
| `templates/admin/` | Admin screen markup |

## On-page SEO

Every public post type gets a **SEO** panel on its edit screen: focus keyphrase,
SEO title, meta description, a search-result preview, and an Advanced tab with
canonical URL and noindex/nofollow. It is a classic meta box, so it renders in
the block editor, the classic editor and page builders alike, with no build step.

Meta keys, all registered through `register_post_meta` and therefore available
over the REST API with the same sanitization and capability checks:

`_wpcseo_title`, `_wpcseo_description`, `_wpcseo_focus_keyword`,
`_wpcseo_canonical`, `_wpcseo_noindex`, `_wpcseo_nofollow`.

Front-end output goes through native hooks — `pre_get_document_title`,
`wp_robots`, `get_canonical_url` and `wp_head` — so themes and other plugins
keep their usual chance to filter it. Everything is gated behind
**Settings → General → Enable SEO output**.

### The optimization score

`Analyzer` scores 16 on-page checks: title length and keyphrase placement,
description length and keyphrase, content length, keyphrase distribution and
placement in the introduction, subheadings, heading hierarchy, images and their
alt text, internal links, external references, and the URL slug.

Each check reports what was detected, why it matters and what to do about it.
The score is this plugin's own measure of how completely a page follows
established on-page practice. **It is not Google's algorithm and does not
predict rankings.**

#### The model is editable

Because the score is a checklist and not a measurement, you get to disagree
with it. **Settings → Score model** gives every check a weight from 0 to 5, and
`Weights::defaults()` is the single table those weights and labels come from —
they used to sit inline at each check, which made the model a fact about the
code rather than a decision anyone could see.

**A weight of zero excludes a check from the score without hiding its advice.**
The check still runs and still says what it found, marked *not counted* in the
editor. Silencing a recommendation and disagreeing with its importance are
different things, and a site whose pages are short by design should not be told
forever that its content is too short.

The fields are ordinary settings, so they are registered, sanitized, saved and
capability-checked by the same code as everything else. A check added through
`wpcseo_analysis_checks` that is not in the model is worth 1 rather than
nothing, so a third-party check is never silently ignored; add it to
`wpcseo_score_model` to give it a weight and a settings field of its own.

## Entities and the schema graph

The plugin describes a site as entities with stable identifiers, and emits one
connected `@graph` per page rather than isolated JSON-LD blocks. An entity is
stated once and referenced by `@id` everywhere else, so a consumer can resolve
author, publisher and page into a single description.

```
Organization ──publisher── WebSite
     │                        │
     │                     isPartOf
     │                        │
     └──publisher──── Article ──author──► Person
                         │
                    mainEntityOfPage
                         ▼
                      WebPage ──primaryImageOfPage──► ImageObject
```

Identifiers are derived from the site URL — `…/#/schema/organization`,
`…/#/schema/person/12`, `<permalink>#webpage`, `<permalink>#article` — so they
stay stable across requests without a table to keep in sync.

**Nothing is invented.** Every builder omits a property it cannot establish
from real data, and returns nothing at all when the entity does not exist. The
organization trust properties (`publishingPrinciples`, `correctionsPolicy`,
`ownershipFundingInfo`, `actionableFeedbackPolicy`, `diversityPolicy`) are
emitted only when you supply a URL for a page that genuinely exists. Invalid
URLs in any list are discarded rather than published.

### Validation

`SEO → Schema` validates the graph for a chosen post or the front page:
unique `@id`s, resolvable references, absolute URLs, present `@type`, and the
properties each type needs to be usable. Errors, warnings and notices are
distinguished — and **a graph with errors is withheld from the front end
rather than published**, because wrong structured data is worse than none.

The screen also lists other active structured-data emitters (Yoast, Rank Math,
AIOSEO, SEOPress, Schema Pro, WooCommerce). Nothing is ever disabled
automatically; you choose which source to turn off.

Per-post schema type lives on the editor's **Schema** tab. `HowTo` is
deliberately absent: it requires the visible page to actually contain that
content, and will be offered once the module can verify it.

### FAQPage is derived, never chosen

`FAQPage` is not on the type list either, for the same reason — but it does not
need to be, because the plugin can verify it. Content is read for a real
question-and-answer structure: a heading ending in a question mark followed by
its answer, or a `<details>`/`<summary>` disclosure block. Find two or more and
the page node gains `FAQPage` alongside whatever it already was, carrying the
questions as `mainEntity`. Find fewer and nothing is emitted.

A heading that merely starts with an interrogative word ("How we work") is a
statement, not a question, so the question mark is required rather than
inferred. Generating FAQ text with the AI assistant does not switch this on:
the text has to be on the page first.

## Site audit

**SEO → Site Audit** reports on the whole site. **It uses no AI and costs
nothing to run.**

That is a deliberate design decision, not a gap. Look at the specification's
own examples of what an audit should say — *"42 pages have missing meta
descriptions"*, *"18 pages have weak internal linking"*, *"7 pages target
similar keywords"*. Every one of those is an exact count over data this plugin
already holds: the meta keys, the link graph, the 404 log. Counting them is
free, instant, and **cannot be wrong about a number**. Asking a model to
estimate what a `COUNT(*)` can answer would be slower, cost money, and
introduce the possibility of a confidently wrong figure.

On the development site the full audit builds in **34ms across 22 queries**. It
never requests a URL — nothing in it makes an HTTP call — and the report is
cached for an hour.

### Findings, not a score

Findings are ranked by what the decision actually is, rather than collapsed
into one colour:

| Level | Meaning |
| --- | --- |
| **Critical** | May stop pages being crawled or indexed at all |
| **Important** | Likely worth fixing, and usually quick |
| **Opportunity** | Would probably help, but nothing is broken |
| **Good** | Already in order — listed so you can see it was checked |

Every finding states the problem, **why it matters**, what to do, and carries
the actual rows it is based on so you can check the claim rather than take it
on trust.

### Keyword cannibalization

Focus keyphrases are normalised — lowercased, stop words dropped, plurals
folded, words sorted — so *"roof insulation"*, *"insulation for roofs"* and
*"the best roof insulation"* are recognised as one target, which an exact
string match would miss. `glass` is not mistaken for a plural of `glas`, and
`roof insulation cost` stays a separate target from `roof insulation`.

The remedy is offered as **four options**, not one instruction: merge, change
one keyphrase, canonicalise, or differentiate the intent. Which is right
depends on whether the pages serve different intents, and the plugin cannot
know that.

### Content freshness

**This does not detect decay, and does not claim to.** Detecting decay needs
traffic or ranking data over time; no such source is connected, so nothing
here says a page is declining.

Per §27, **age alone never raises a page.** Evergreen content that was right
five years ago is still right, and telling someone to rewrite it because a
date is old would be advice with nothing behind it. A page appears only when
age is combined with a second signal — other pages link to it, or it is
substantial enough that being stale would matter. A thin, old, unlinked page
is correctly left alone.

## AI

### Configuration

1. **Settings → AI** — choose a provider (Anthropic, OpenAI or Google Gemini)
   and optionally a model. Leave the model empty to use the provider default.
2. **SEO → AI** — paste an API key. Keys are entered here, not in the settings
   form, because the Settings API round-trips values through the page and a
   credential must never be rendered into HTML.

Until both are done, AI features are off and the editor's **AI** tab says so.

### What is sent, and when

**Nothing is sent automatically.** There is no request on save, on page load,
or in the background — the only code path that reaches a provider starts with
someone pressing a button in the editor. The AI tab states, before you press
anything, exactly what would leave the site: the page's title, focus
keyphrase, existing SEO fields, and up to roughly 400 words of its content.

Generated text is never written to the post. A suggestion is applied to a
field only when you click **Apply**, and saved only when you save the post.

### Credentials

Keys live in their own non-autoloaded option, encrypted with
`sodium_crypto_secretbox` under a key derived from the site's `AUTH_SALT`.

**What that does and does not protect.** The salt lives in `wp-config.php` on
the same server, so this defends against a leaked *database* — a stolen SQL
dump, a backup on shared storage, a compromised read-only replica — which is
how API keys usually escape. It does not defend against an attacker who
already has the filesystem, because they have the salt too. Claiming otherwise
would be worse than not encrypting.

A saved key is never rendered back into the page, never sent to JavaScript, and
never written to the log; the screen shows only its last four characters. If
the site's salts are rotated, saved keys become unreadable and must be
re-entered — there is nothing to recover.

### Temperature is per-model, not global

§39 of the specification asks for a configurable temperature. **Several current
models reject `temperature` outright with a 400** rather than ignoring it —
Claude Opus 5 and Sonnet 5 among them. Sending it anyway would break every
request, so each provider declares per model whether it is accepted, and the
parameter is omitted where it is not. The setting still applies everywhere it
is supported.

### What it does

The editor's **AI** tab offers six actions:

| Action | Returns |
| --- | --- |
| Suggest SEO titles | Five options, each applicable with one click |
| Suggest meta descriptions | Four options across informational, CTA, comparison and ready-to-act framings |
| Suggest keywords | A primary keyphrase plus secondary, long-tail, question, entity and semantic terms |
| Review this content | Search intent, missing topics, weak sections, heading improvements, unanswered questions, linking opportunities |
| Suggest internal links | Real pages on this site to link to, with anchor text, a reason and a confidence figure |
| Draft an FAQ | Questions the page answers, with the wording each answer came from, and questions it does not answer |

#### Internal link suggestions

**The model is never asked which pages exist.** Before the request is made, the
plugin finds candidate pages itself — published posts whose titles share at
least two significant words with this page, excluding anything it already links
to — and the model is asked only which of *those* genuinely belong and how to
phrase the link. It replies with candidate ids; a suggestion whose id was not
on the list is discarded rather than shown, and the title and URL displayed
come from the site, not from the reply. A page with no candidates never
triggers a request at all.

Each suggestion says whether its anchor wording is already on the page. The
anchor is editable, and accepting a suggestion means copying the `<a>` markup
into your content: **no link is ever written into a post automatically.**

#### FAQ drafts

Answers must come from the page. Each one is returned with the phrase it was
drawn from, and the plugin checks that phrase against the content — an answer
it cannot trace back is shown with a warning rather than quietly dropped or
quietly trusted. Questions the page does not answer are listed separately and
deliberately left unanswered; filling them in would be invention.

**SEO → Content Brief** plans a page that does not exist yet: give it a topic,
audience and market, and it returns a title, H1, section outline, questions,
entities, related keywords, FAQ topics, a schema type and a depth
recommendation. The brief is rendered and not saved; no draft is created.

Two things the model suggests can be applied with a click — the primary
keyphrase, and the detected search intent. Everything else is advice you read
and act on yourself; **nothing rewrites the page**.

### Search intent

The content review classifies intent with a confidence figure and an
explanation. That figure is **the model's own certainty, not a measurement of
anything external**, and the screen says so.

The **Search intent** field on the Advanced tab is yours and always wins. The
review can fill it in for you, but the person who wrote the page knows what it
is for.

### Guardrails

- Prompts instruct the model not to invent facts, statistics, prices,
  availability or guarantees that are not in the supplied content.
- **No search volume, difficulty or competition is ever requested or shown.**
  A language model does not have that data; anything it produced would be a
  plausible-looking number with nothing behind it, and an editor would
  reasonably act on it. Those columns appear only if a real keyword-data
  provider is connected.
- External references are suggested as **kinds of source** ("the
  manufacturer's installation specification"), never as specific URLs — the
  model cannot verify that a given page exists or says what it claims.
- Intents and schema types are constrained to the values the plugin
  recognises; an invented category is dropped rather than displayed as though
  it meant something.
- Structured replies are read tolerantly — a code fence or a sentence of
  preamble is recovered — but **genuinely broken output is reported, not
  guessed at**. One malformed row in an otherwise good list is dropped; a
  truncated object is an error.
- 100 requests per user per hour, so a stuck loop or a misclick cannot run up
  a large bill.
- Every request is logged with provider, model, action, token counts, duration
  and outcome. **Prompts and generated text are not stored** — they contain
  page content, and a log is not a place to keep a second copy of the site.
- **No cost estimate is shown.** Provider pricing changes and varies by
  account and negotiated rate; an invented figure would be misleading. Token
  counts are recorded so you can price them against your own bill.
- Error messages are translated into something actionable and never include
  the key or the raw provider body.

The editor states plainly that suggestions are drafts, not facts, and should be
read against the page before being applied.

### Adding a provider

```php
add_filter( 'wpcseo_ai_providers', function ( array $providers ): array {
    $providers['mine'] = new My_Provider(); // implements ProviderInterface
    return $providers;
} );
```

Prompts live in `src/AI/Prompts/`, one class each, and every one passes its
finished messages through `wpcseo_ai_prompt` before sending:

```php
add_filter( 'wpcseo_ai_prompt', function ( array $parts, string $action ): array {
    if ( 'title' === $action ) {
        $parts['system'] .= ' Always write in British English.';
    }
    return $parts;
}, 10, 2 );
```

## Local SEO

Enable under **Settings → Local & Brand**. Locations are a non-public post type
under the SEO menu — WordPress already supplies a list table, search and
capability handling for one, so multiple locations work without a bespoke
repeater. Each carries business type, address, phone, email, price range,
coordinates, image, profile URLs and a seven-day opening-hours grid.

Render details anywhere with `[wpcseo_business]`, or `[wpcseo_business id="12"]`
for one location.

**Nothing is guessed.** A location with no region produces no `addressRegion`;
one with no email produces no `email`. Coordinates are published only if both
values are numeric and inside the real ±90 / ±180 range. Times must be valid
24-hour clock values — `24:00`, `09:60` and `9:30am` are all discarded rather
than published as something a customer might arrive on.

A day left blank is simply not published. That is deliberately different from
marking it **closed all day**, which *is* published, because "we don't say" and
"we are shut" are different claims.

Location data appears on the front page by default — that is where a business
states who it is. Publishing a shop address on every article would not describe
those pages. Switch to every page in settings if that suits the site.

## Brand

A `Brand` node is published only when you give it a name, and it references the
organization as its parent rather than being conflated with it. Deriving a
brand from the site title would assert a relationship you never claimed.

## WooCommerce

Inert unless WooCommerce is actually loaded — `Integration::is_active()` gates
everything, so nothing in that namespace is reached on a site without it.
Checkout and cart behaviour is untouched; this module reads product data and
describes it.

Product structured data is built **only from data the shop holds**:

| Property | Emitted when |
| --- | --- |
| `offers` | The product has a numeric price. A non-numeric price such as "call us" yields no Offer at all |
| `availability` | Always with an Offer, reporting out-of-stock honestly |
| `aggregateRating` | Review count ≥ 1 **and** a non-zero average. A count with no average is incoherent and is dropped |
| `sku`, `brand`, `image`, `description` | Only when set |

Nothing invents a price, a stock level, a rating, a SKU or a brand.

WooCommerce publishes its own product markup. Leaving both on means two
descriptions of one product, so **Replace WooCommerce structured data** (off by
default) removes a single WooCommerce output hook — nothing else, and
unchecking it restores that output on the next request. WooCommerce itself is
never deactivated or modified.

## Internal links

**SEO → Internal Links** shows what points at what: most linked-to content,
under-linked content, and internal links that resolve to no post. The graph is
updated whenever a post is saved; a full rebuild runs in **cron batches of 50**
rather than one request, so a large site never stalls.

Links are recorded as one of three kinds, and the distinction is deliberate:

| Kind | Meaning |
| --- | --- |
| `internal` | Resolves to a post on this site |
| `unresolved` | Points at this site but not at any post |
| `external` | Points somewhere else |

**An unresolved link is not called broken.** A category archive, a date archive
or a page served by something other than a post all land here legitimately.
Confirming a link is broken needs an actual HTTP request, which this screen
does not make — so it does not make the claim.

Three spellings of the same destination — absolute URL, root-relative path, and
a `www.` variant of the site's own host — are resolved to the same post and
recorded **once**. Self-links, fragments, `mailto:`, `tel:`, `javascript:` and
`data:` are skipped.

### Orphans

Thresholds are configurable under **Settings → Internal Links**: a count at or
below *critical*, and at or below *warning*. Per §26, a page with no incoming
prose links is **not** blindly treated as a problem — content reachable from a
navigation menu, the front page and the posts page are excluded, because a page
in the main menu is discoverable however rarely an article links to it.

## Bulk editor

**SEO → Bulk Editor** edits SEO title, meta description, canonical and noindex
across many items at once, filtered by post type, search, or *missing SEO
title* / *missing meta description*.

Twenty rows load at a time and the page size is not user-controllable, so a
site with fifty thousand posts never ships more than a screenful to the browser.
`edit_post` is checked **per row, not once for the screen** — a user who can
reach the page is not thereby allowed to edit everything listed on it, and rows
they cannot edit render read-only.

## AI crawlers

**Settings → AI crawlers** writes rules into robots.txt asking named AI
crawlers to stay away. **Nothing is blocked by default.**

The controls are grouped by what the crawler is *for*, because "AI bot" is three
different things and treating them as one is the mistake this screen exists to
prevent:

| Purpose | Blocking it means | Crawlers |
| --- | --- | --- |
| **Model training** | Your pages are not used to train a model. **No effect on any search result**, Google's or an assistant's. | `GPTBot`, `ClaudeBot`, `Google-Extended`, `Applebot-Extended`, `Meta-ExternalAgent`, `CCBot` |
| **AI search and citation** | The site disappears from that assistant's answers. **This is the AI equivalent of noindex.** | `OAI-SearchBot`, `Claude-SearchBot`, `PerplexityBot` |
| **Fetches a person asked for** | Someone pasting your URL into an assistant is told the page cannot be read. | `Claude-User`, `Perplexity-User` |

Someone who blocks "the AI bots" to keep their writing out of training usually
does not mean to vanish from ChatGPT's citations too. So the two are never one
switch, and every control states what blocking it costs. `Google-Extended` in
particular says plainly that it has no effect on Google Search.

Only tokens the operator publicly documents are offered. OpenAI documents
`ChatGPT-User` as **not** governed by robots.txt, so there is no toggle for it —
a switch the operator says does nothing would be a lie in checkbox form.

**What robots.txt is worth.** It is a request, standardised as RFC 9309 and
honoured voluntarily. A crawler that ignores it, or lies about its user agent,
is unaffected by anything here; only server or edge rules stop that. The screen
says so rather than implying the file is a wall.

**If a physical `robots.txt` exists in the web root**, WordPress never serves
its own — so these rules would be written and never read. The site audit raises
that as a critical finding rather than leaving you to wonder why nothing
happened.

### On llms.txt

**Not implemented, deliberately.** As of 2026 no major AI company — OpenAI,
Google, Anthropic, Meta or Mistral — has committed to reading `llms.txt` in
production, and Google has said publicly that Search does not use it. Shipping
it as an AI-visibility feature would be exactly the invented functionality this
plugin avoids everywhere else. If that changes, it is a small file to add.

### On "AI schema"

There is no special schema type that gets a page into AI Overviews or an
assistant's answer. Those systems draw on the same index as ordinary search, so
what helps is what already helps: accurate structured data, clear headings,
direct answers. The plugin already emits `Article`, `FAQPage` and `Person`, and
the FAQ only when the page really shows one.

## Abilities API

When WordPress provides the Abilities API, the plugin registers six abilities
under `wp-custom-seo/` so an AI agent can use what this plugin knows. When it
does not, nothing is registered and nothing breaks — the integration is skipped
entirely rather than depended on.

| Ability | Does | Needs |
| --- | --- | --- |
| `analyze-post` | Runs the on-page analysis and returns every check | `edit_post` |
| `get-seo-meta` | Reads a page's stored SEO fields, and the effective title and description | `edit_post` |
| `update-seo-meta` | Writes the SEO title, meta description or focus keyphrase | `edit_post` |
| `link-candidates` | Real pages on this site the given page could link to | `edit_post` |
| `site-audit` | Everything the audit found, by severity | plugin capability |
| `search-performance` | What Google reports for one page | `edit_post` |

**None of them call a language model.** That is the deliberate part. The
consumer of an ability is usually already a model — offering it "ask a model to
write a title" adds a bill and a second opinion where it already had its own.
What a model *cannot* do without the site is know which pages exist, what they
currently say, and what search engines report about them. That is what is
offered. It also means an agent cannot run up an AI bill by calling an ability.

Exactly one ability writes, and it is annotated accordingly: `readonly: false`,
`destructive: false` (it replaces named fields, it does not remove anything),
`idempotent: true`. Only the SEO title, meta description and focus keyphrase are
writable, each validated by the same sanitizer the editor uses, and a value its
validator refuses is **named in the response** rather than silently dropped.

Every ability is permission-checked exactly as the equivalent screen is: an
agent acting for a user can do what that user could do and nothing more. A site
that would rather no agent could write SEO fields can remove that one through
the `wpcseo_abilities` filter.

## Email reports

**Settings → Reports** turns on a weekly or monthly email summarising what the
site audit found, and what Google reported if Search Console is connected.
**SEO → Tools → Send one now** sends immediately.

Three rules shape it:

- **A report with nothing in it is not sent.** An email that arrives every week
  saying "nothing to report" is one people filter to a folder — and then the one
  that matters gets filtered too. Sending manually overrides this, so you can
  see what arrives.
- **A section with no data is left out, not filled with zeroes.** A site with no
  Search Console connection gets no search section, rather than one reporting
  nought clicks, which reads as a catastrophe rather than an absence. If the API
  fails, the section is dropped too — a failing API is not a change in traffic,
  and the error belongs on the screen where there is something to do about it.
- **"Good" findings are left out.** They belong on the audit screen where the
  whole picture is useful; in an email they are padding around the things that
  need doing.

The subject leads with what needs doing — *"2 critical SEO issues on Example"* —
because a subject saying "SEO report" tells the reader nothing they can act on
from the inbox.

Sent as plain text. HTML would need inline styles to survive a mail client, a
text alternative anyway, and would still look wrong in half of them; a short
summary linking to the screen is readable everywhere, and the screen is where
anything is actually done.

The schedule is reconciled whenever settings are saved, not only at activation —
the usual failure of a feature like this is being switched on in the admin and
never actually scheduling anything. Reconciling twice does not push the next
send back. Delivery is WP-Cron, so a site with no traffic may send late.

## Search Console

**SEO → Search Performance** shows what Google reports about the site in
search: the queries it appeared for, the pages that were shown, clicks,
impressions and CTR, over 7, 28 or 90 days.

**Every figure comes from Google.** The plugin computes nothing here and
estimates nothing. With no connection there are no figures — not zeroes, not a
placeholder chart, not a demo. A screen that shows numbers it does not have
teaches an editor to trust numbers it does.

### Connecting

A **service account** is used rather than signing in with Google. That is a
deliberate trade: the OAuth redirect flow would need a registered redirect URI
per site, a state nonce, refresh-token storage and a refresh state machine —
a lot of moving parts, each one a place to leak a token. A service account
replaces all of it with a single signed assertion.

1. In the Google Cloud console, create a project and enable the Google Search
   Console API.
2. Create a service account in it and download a JSON key.
3. Paste the key file into the screen.
4. In Search Console, add the service account's email address as a user of the
   property. The screen shows you the address once the key is saved.

Step 4 is a real cost of this approach, and the screen says so rather than
pretending the connection is one click.

The key file is validated on paste — an OAuth client file, a truncated file, or
a private key that lost its line breaks in copying are each refused with a
message saying which — and stored through the same encrypted store as the AI
keys, with the same honest limits: it protects a leaked database, not a
compromised server.

No SDK is bundled. A JWT is two base64url segments, a signature and one call to
`openssl_sign`; pulling in a Google client library would drag a whole HTTP
stack into a plugin that already has WordPress's.

### What is and is not derived

Totals are a sum of clicks and impressions with CTR recomputed from them —
arithmetic over reported values. **No site-wide average position is shown.**
The mean of per-page average positions is not the site's average position, and
a number that looks like a measurement should be one.

The reporting window ends three days before today, because Search Console lags
live traffic by two to three days and a range ending today reads as a collapse
in traffic that never happened. Replies are cached twelve hours; the underlying
data updates about once a day.

### In the editor

The editor panel gains a **Search** tab showing what Google reports *this page*
was shown for: the queries, with clicks, impressions and average position.

**Nothing is fetched until you press the button.** An editor screen that made an
API call on every load would spend quota on pages nobody was looking at, and
would make opening a post wait on Google.

Under the table it compares the page's focus keyphrase against the queries
actually reported — which is the comparison worth making, because the phrase an
author targeted and the phrase Google shows the page for are frequently not the
same. When the keyphrase is absent the panel says so **as an observation, not a
verdict**: the list is the top rows only, so its absence is not proof it never
appears.

Unavailable cases answer with a reason rather than an error: not connected, not
published yet, or no data reported. "This page is a draft" is an ordinary fact
about a page, not a failed request, and a red error for it would misdescribe
what happened.

### Analytics

Search Console says how people found the site. **Analytics says what they did
next**, and the same screen shows it: organic sessions, engaged sessions,
engagement rate, and the landing pages organic search sent people to.

**The same service account covers both.** A key file can be granted access to a
Search Console property and a GA4 property alike, so asking for a second one
would be asking twice for the same thing. Add the account's address as a Viewer
under Analytics → Admin → Property access management, enable the Analytics Data
API for its project, and enter the property id.

Tokens are minted and cached **per scope**, not once for both. A single
assertion covering both scopes would fail entirely if only one of the two APIs
were enabled on the Google project — meaning switching on Analytics could break
Search Console, or the reverse. Kept apart, each degrades on its own.

The property id is the **numeric** one from Admin → Property details. The value
people have to hand is usually the measurement id (`G-ABC123`), and stripping
its non-digits would yield `123`: a number that looks like a property id, is not
one, and would fail later as a confusing 404. Anything that is not digits — with
an optional `properties/` prefix — is refused when you save it.

Figures are **organic search only**. A page that gets its visitors from a
newsletter says nothing about how the site performs in search, and mixing the
two produces a number that means neither. The engagement rate is recomputed
from the two session counts rather than read from the API's own rate, so the
three figures shown always agree.

Cached an hour — shorter than Search Console's twelve, because Analytics updates
through the day.

### Verification status

Both integrations share this. The credential handling, JWT signing, per-scope
tokens, response shaping, error translation, caching and every screen state are
covered by tests — including signing a real assertion with a throwaway key pair
and verifying the signature the way Google's end does.

**Neither live round trip to Google has been exercised**, because that needs a
Google Cloud project, a verified Search Console property and a GA4 property.
The request and response shapes follow the published APIs and are tested against
their documented payloads; treat the first real connection as the thing to
watch.

## Import, export and migration

**SEO → Tools** carries data in and out. Both halves are deterministic: no
external service, no AI, nothing that needs a key.

### Coming from another SEO plugin

The screen counts how many posts hold data from each supported plugin and
offers to copy it in. **The plugin does not need to be active** — its data sits
in postmeta and outlives it, which is the normal case, since people usually
switch the old one off first.

| Plugin | Read from |
| --- | --- |
| Yoast SEO | `_yoast_wpseo_*` post meta |
| Rank Math | `rank_math_*` post meta |
| SEOPress | `_seopress_*` post meta |
| The SEO Framework | `_genesis_*`, `_open_graph_*`, `_twitter_*` post meta |

Titles, meta descriptions, focus keyphrases, canonicals, breadcrumb titles,
robots settings and the Open Graph and Twitter fields are carried across.

Three rules govern the whole thing:

1. **The source is never modified or deleted.** If the result is wrong, the old
   plugin's data is still there to run again from.
2. **An existing value is never overwritten** unless you tick the box that says
   so, and the report counts what it left alone.
3. **Anything that could not be carried is reported, not dropped quietly.**

The third rule is mostly about template variables. Every plugin has its own
spelling — `%%title%%`, `%title%`, `%%post_title%%` — which is translated, and
its own variables that this one has no equivalent for. A `%%primary_category%%`
left in a title would be published as literal text in search results, so it is
removed and named in the report. Values are put through the same sanitizers the
editor uses, so a canonical that is not a real absolute URL is refused rather
than stored.

It runs 200 posts per press so it cannot time out, and asks you to continue
until it is done. **All in One SEO version 4 is not covered**: it keeps its data
in a table of its own rather than in post meta. If that table is present the
screen says so plainly, rather than importing a fraction and reporting success.

### Spreadsheets

Export writes every post's SEO fields as CSV — with a byte order mark, so Excel
does not turn accented characters into mojibake — and the import reads that same
format back.

Rows are matched on `post_id`; the `post_type`, `post_title` and `url` columns
are for orientation and are never written back, so a stale export cannot rename
anything. An emptied cell clears that field. A column deleted from the file is
left alone entirely, which is what makes it safe to export everything and edit
one column.

**The default press is a preview.** It reports exactly what would change and
writes nothing; applying is a separate tick. `edit_post` is checked per row, and
a value that fails its sanitizer is reported with its line number rather than
silently skipped.

## WP-CLI

```
wp seo audit [--level=<level>] [--fresh] [--format=<format>]
wp seo links
wp seo schema <post_id> [--format=json|yaml]
wp seo export [--file=<path>]
wp seo import-csv <file> [--apply]
wp seo import [<source>] [--overwrite]
wp seo flush
```

These exist for one reason: three of these jobs are bounded in the admin by
what fits in a single HTTP request, and ask you to press *Continue*. Here they
run straight through — `wp seo links` rebuilds the whole graph, and
`wp seo import yoast` migrates a site of any size in one command.

Nothing is computed differently. Every command calls the same code the screens
do, so a number here and a number on a screen cannot disagree.

- `wp seo import` with no source lists what other SEO plugins left behind, and
  how many posts each covers.
- `wp seo import-csv` is a **dry run** unless `--apply` is passed. Because the
  importer checks `edit_post` per row and WP-CLI runs as nobody by default, it
  stops and tells you to pass `--user=` rather than skipping every row and
  reporting a clean-looking zero.
- `wp seo schema` prints the graph and exits **non-zero if it has errors** —
  an error means the front end withholds that graph, which is a broken page,
  not an observation about one. `wp seo audit` always exits zero: findings are
  a judgement about content, and failing a pipeline on them would be wrong.

There are deliberately no per-post meta commands. `wp post meta get 12
_wpcseo_title` already reads and writes these fields; a second spelling of it
would be one more thing to keep correct.

## Redirects and the 404 monitor

**SEO → Redirects** manages 301, 302, 307 and 308 rules, literal or regular
expression, with search, sorting, bulk enable/disable/delete and hit counts.

Sources are normalised before they are stored — lowercased, trailing slash
removed, absolute URL reduced to its path, install subdirectory stripped — so
`/Old-Page/` and `https://example.com/old-page` are recognised as the same
rule and cannot be entered twice. Query strings are ignored when matching and
**carried over to the destination**, so one rule covers a URL however it was
tagged.

**Bad rules are rejected when you save, not discovered by a visitor:**

- a redirect pointing at itself
- a target that already redirects back to the source, at any depth up to ten
- a chain longer than ten steps
- a regular expression that does not compile
- a status code outside the four that make sense
- `javascript:` and `data:` targets, which are stripped to a path

**SEO → 404 Monitor** records URL, referrer, user agent, hit count and first
and last seen. A repeat of a known URL costs one indexed `UPDATE` rather than
a new row, so a site being scanned does not grow the table without bound.
Every row has a **Create redirect** action that prefills the form. A daily job
prunes entries past the configured retention.

Not every 404 needs fixing, and the screen says so: a mistyped URL or an old
scan is noise. Entries with a referrer or a high hit count are the ones worth
looking at.

### Performance

Matching runs on `template_redirect`, which WordPress has already excluded
admin, REST, cron and AJAX requests from. A cached count means a site with no
redirects performs **no query at all**; a site with them performs one indexed
lookup by hash. Regular-expression rules are cached, since those must each be
tested in turn.

### Tables

`{prefix}wpcseo_redirects` and `{prefix}wpcseo_not_found`, created through the
Phase 1 migration system at schema version `1.1.0`. These are the first tables
the plugin has needed: high-write records queried by exact URL on ordinary page
loads is the one shape post meta genuinely cannot serve. Both are dropped on
uninstall, and only when you opted in.

## Sitemap

**This plugin does not publish its own sitemap file.** WordPress has generated
`wp-sitemap.xml` since 5.5 — with an index, per-post-type and per-taxonomy
pages, pagination and a full filter API. Emitting a rival sitemap from the same
site would mean two sets of URLs disagreeing with each other, so this module
shapes the core one instead:

- **noindexed content is removed**, since a page you ask search engines to
  ignore does not belong in the file that invites them in
- **`lastmod` is added**, which core omits
- author and taxonomy sitemaps can be switched off
- `wpcseo_sitemap_query_args`, `wpcseo_sitemap_entry`,
  `wpcseo_sitemap_post_types` and `wpcseo_sitemap_taxonomies` for the rest

Turning off **Enable the XML sitemap** disables core's sitemap entirely.

Image sitemaps are not included: core's renderer cannot emit the extra XML
namespace they need, and adding unsupported tags to look more capable is the
opposite of useful.

## Breadcrumbs

Enable under **Settings → Sitemap & Breadcrumbs**. Four ways to output a trail:

```php
wpcseo_breadcrumbs();            // print
$html = wpcseo_get_breadcrumbs(); // markup
$trail = wpcseo_breadcrumb_trail(); // data
```

```
[wpcseo_breadcrumbs]
```

…plus a **Breadcrumbs** block, registered with a plain ES5 inline editor
script so the plugin still needs no build step, and a read-only
`wpcseo_breadcrumbs` field on post REST responses.

The trail follows real site structure — post type archive, page ancestors or
primary term, taxonomy parents — not the visitor's path. Each page can override
its own label with the **Breadcrumb title** field. The markup is a `<nav>` with
`aria-label`, an ordered list, `aria-current="page"` on the final crumb and the
separator hidden from screen readers. The stylesheet is registered but only
enqueued when a trail is actually printed.

When breadcrumbs are enabled, a matching `BreadcrumbList` joins the schema
graph and the page node references it. It is **not** emitted when breadcrumbs
are off, because structured data must describe what the page actually shows.

## Social metadata

Open Graph and X/Twitter tags, each value falling back the way a person would
expect: the social field, then the SEO field, then what the page contains.

| Tag | Falls back to |
| --- | --- |
| `og:title` | SEO title, then document title |
| `og:description` | meta description, then excerpt |
| `og:image` | featured image, then the site default |
| `twitter:*` | the matching Open Graph value |

`article:published_time` and `article:modified_time` appear on articles only.
A `summary_large_image` card is **downgraded to `summary`** when the page has
no image, since a large-image card without one renders as a summary anyway.
Any tag whose value cannot be resolved is not printed at all.

## Schema aggregation API

Public, read-only endpoints that republish structured data for content that is
already publicly readable, so tools and assistants can read a site without
crawling every page.

| Endpoint | Returns |
| --- | --- |
| `GET /wp-json/wp-custom-seo/v1/schema` | Index: site, entity links, post types with counts and page totals |
| `GET …/schema/sitemap` | Flat list of every aggregation page URL |
| `GET …/schema/<post_type>[/<page>]` | One page of merged schema, plus its validation report |
| `GET …/schema/entity/organization` | The organization entity |
| `GET …/schema/entity/website` | The website entity |
| `GET …/schema/entity/person/<user_id>` | An author entity |

**What is excluded, always:** drafts and any non-published status,
password-protected posts, non-viewable post types, and anything marked
noindex. A page the site asks search engines to ignore is not republished
here either. Person entities resolve only for users who have published
content, so the endpoint cannot enumerate accounts.

Site entities are stated once per response no matter how many posts it covers,
because the graph merges nodes sharing an identifier. Pagination is set by
**Settings → Schema & Entities → Posts per API page**; per-post-type overrides
go through `wpcseo_schema_api_batch`. Responses carry `X-WP-Total`,
`X-WP-TotalPages` and `Link` headers.

Turn the whole thing off with **Expose the aggregated schema API**.

### Caching

Responses are cached for twelve hours through transients — which write to a
persistent object cache when one is installed and fall back to the options
table when it is not, which is exactly the required behaviour.

Cache keys carry a version built from `get_lastpostmodified()` plus a counter,
so **an ordinary edit invalidates the cache without writing anything**. The
counter is bumped only for changes the timestamp cannot see: a deleted post, a
profile update, or a settings save. **SEO → Tools → Clear schema cache**
removes stored entries immediately.

## REST API

`POST /wp-json/wp-custom-seo/v1/ai/title`, `…/ai/meta-description`,
`…/ai/keywords`, `…/ai/content-analysis`, `…/ai/internal-links`, `…/ai/faq`

The first two return `suggestions[]`; the rest return typed structures. All
return the model used and token counts. `internal-links` also returns how many
candidates were offered and how many replies were discarded for naming a page
that was not among them. **POST only** — these have a
billable side effect and must not be reachable by a GET a browser or crawler
could follow. Requires `edit_post` on the target.

`GET /wp-json/wp-custom-seo/v1/performance/<post_id>` with optional `days`
(7, 28 or 90)

What Search Console reports for one post, plus whether its focus keyphrase is
among those queries. Requires `edit_post`. Returns 200 with `available: false`
and a `reason` when there is nothing to show — an unconnected site or an
unpublished post is an answer, not a failure.

`GET /wp-json/wp-custom-seo/v1/links/<post_id>`

Incoming and outgoing links for one post. Requires `edit_post`.

`GET /wp-json/wp-custom-seo/v1/redirects` and `…/404s`

Paginated lists with `page`, `per_page` and `search`. Both require the plugin
capability: 401 anonymous, 403 without it.

`GET /wp-json/wp-custom-seo/v1/schema/<post_id>`

Returns the graph and its validation report for one post, drafts included.
Requires `edit_post` — this is the authenticated sibling of the public
aggregation routes above.

`POST|GET /wp-json/wp-custom-seo/v1/analysis/<post_id>`

Returns `score`, `grade`, `word_count`, `checks[]` and `preview`. POST accepts
`title`, `description`, `keyword`, `slug` and `content` so the editor can
analyse unsaved changes; anything omitted falls back to what is stored.
Requires `edit_post` on the target: 401 anonymous, 403 without the capability,
404 for an unknown post.

### Prefixes

Namespace `WPCustomSeo\`, hooks and options `wpcseo_`, constants `WP_CUSTOM_SEO_`,
capability `wpcseo_manage_seo`, post meta `_wpcseo_`, text domain `wp-custom-seo`.

The `wpseo_*` prefix is not used: Yoast SEO already owns it, down to a filter
literally named `wpseo_title`. Sharing it would collide on any site running both.

## Extending

```php
// Add a settings section or field.
add_filter( 'wpcseo_settings_schema', function ( array $schema ): array {
    $schema['social']['title']                = 'Social';
    $schema['social']['fields']['og_enabled'] = [
        'type'    => 'checkbox',
        'label'   => 'Enable Open Graph',
        'default' => true,
    ];
    return $schema;
} );

// Add an admin screen.
add_filter( 'wpcseo_admin_pages', function ( array $pages ): array {
    $pages['wp-custom-seo-redirects'] = [
        'title'      => 'Redirects',
        'menu_title' => 'Redirects',
        'callback'   => 'my_render_redirects',
    ];
    return $pages;
} );

// Add a table. The callback receives the plugin table prefix and charset collate.
add_filter( 'wpcseo_migrations', function ( array $migrations ): array {
    $migrations['1.1.0'] = function ( string $prefix, string $collate ): void {
        dbDelta( "CREATE TABLE {$prefix}redirects ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id) ) {$collate}" );
    };
    return $migrations;
} );
```

```php
// Per-post-type title templates, which the settings screen keeps generic.
add_filter( 'wpcseo_title', function ( string $title, int $post_id ): string {
    return 'product' === get_post_type( $post_id ) ? $title . ' | Shop' : $title;
}, 10, 2 );

// Add your own analysis check.
add_filter( 'wpcseo_analysis_checks', function ( array $checks, array $input ): array {
    $checks[] = [
        'id'             => 'trailing_cta',
        'status'         => str_contains( $input['content'] ?? '', 'Get in touch' ) ? 'good' : 'warn',
        'weight'         => 1,
        'label'          => 'Call to action',
        'issue'          => 'No closing call to action was found.',
        'why'            => 'A page that answers a question should tell the reader what to do next.',
        'recommendation' => 'Close with a next step.',
    ];
    return $checks;
}, 10, 2 );
```

```php
// Add a node to every page's graph.
add_filter( 'wpcseo_schema', function ( $graph ) {
    $graph->add( [
        '@type' => 'Service',
        '@id'   => home_url( '/#/schema/service/roofing' ),
        'name'  => 'Roofing',
        'provider' => [ '@id' => home_url( '/#/schema/organization' ) ],
    ] );
    return $graph;
} );
```

Actions: `wpcseo_loaded`, `wpcseo_activated`, `wpcseo_deactivated`,
`wpcseo_before_analysis`, `wpcseo_after_analysis`, `wpcseo_before_schema`,
`wpcseo_after_schema`.

Filters: `wpcseo_settings_schema`, `wpcseo_sanitize_settings`,
`wpcseo_default_capable_roles`, `wpcseo_admin_pages`, `wpcseo_migrations`,
`wpcseo_post_types`, `wpcseo_meta_value`, `wpcseo_title`,
`wpcseo_title_replacements`, `wpcseo_meta_description`, `wpcseo_canonical`,
`wpcseo_robots`, `wpcseo_analysis_checks`, `wpcseo_score`, `wpcseo_schema`,
`wpcseo_schema_type`, `wpcseo_schema_validation`, `wpcseo_schema_conflicts`,
`wpcseo_entity_organization`, `wpcseo_entity_website`, `wpcseo_entity_person`,
`wpcseo_schema_api_post_types`, `wpcseo_schema_api_batch`,
`wpcseo_sitemap_query_args`, `wpcseo_sitemap_entry`, `wpcseo_sitemap_post_types`,
`wpcseo_sitemap_taxonomies`, `wpcseo_breadcrumb_trail`, `wpcseo_breadcrumb_html`,
`wpcseo_breadcrumb_primary_term`, `wpcseo_social_tags`, `wpcseo_redirect_target`,
`wpcseo_record_404`, `wpcseo_link_post_types`, `wpcseo_extracted_links`,
`wpcseo_entity_brand`, `wpcseo_entity_location`, `wpcseo_entity_product`,
`wpcseo_product_brand_taxonomy`, `wpcseo_ai_providers`, `wpcseo_ai_prompt`,
`wpcseo_ai_response`, `wpcseo_audit_findings`, `wpcseo_detected_faq`,
`wpcseo_import_sources`, `wpcseo_import_variable_map`, `wpcseo_score_model`,
`wpcseo_report`, `wpcseo_report_worth_sending`, `wpcseo_report_recipients`,
`wpcseo_report_body`.

`wpcseo_ai_crawlers` adds a crawler to the robots.txt controls.
`wpcseo_abilities` adds or withholds abilities; `wpcseo_score_model` adds a
check to the score with its own settings field.

`wpcseo_import_sources` adds an SEO plugin to migrate from: give it a label, a
`variables` style (`single`, `double` or `none`), a `fields` map of this
plugin's meta keys to its own, and a `flags` map for the robots keys.
`wpcseo_import_variable_map` maps that plugin's template variables to this
one's; anything left unmapped is removed from the text and reported rather than
published as literal `%%text%%`.

`wpcseo_detected_faq` receives the question-and-answer pairs found in a block
of content. Add to it if your theme renders an FAQ in markup the detector does
not recognise — the pairs it returns are what `FAQPage` is built from, so
adding a pair that is not visible on the page defeats the point of the check.

Action: `wpcseo_ai_request` fires with the request and provider immediately
before anything is sent.

Action: `wpcseo_redirect` fires with the matched rule, destination and
requested path immediately before a redirect is sent.

`wpcseo_schema`, `wpcseo_before_schema` and `wpcseo_after_schema` receive a
second argument: `page` for a single request, `aggregate` for the API.

## Data and uninstall

Deactivation removes nothing. Uninstall removes plugin data only when
**SEO → Settings → Advanced → Delete all plugin data on uninstall** is enabled,
and only touches `wpcseo_`-prefixed options, tables and `_wpcseo_`-prefixed meta.
Other plugins' data is never read or written.
