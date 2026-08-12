=== WP Custom SEO ===
Contributors: manpreetsingh
Tags: seo, schema, sitemap, redirects, search console
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

On-page analysis, structured data, redirects, a site audit, Search Console and Analytics reporting, and an AI assistant that never invents what it cannot check.

== Description ==

WP Custom SEO manages the SEO of a WordPress site: titles and meta
descriptions, a connected schema.org graph, sitemaps, breadcrumbs, social
metadata, redirects with a 404 monitor, an internal link graph, a whole-site
audit, and reporting from Google Search Console and Analytics.

The governing rule is that nothing is invented. Where a figure cannot be
established, none is shown.

* **The optimization score is this plugin's own checklist.** It is not any
  search engine's ranking algorithm and does not predict rankings. Every check
  explains what it found, why it matters and what to do about it — and the
  weight of each one is editable, because a checklist is something you are
  allowed to disagree with.
* **Structured data describes what the page actually shows.** FAQ markup is
  emitted only when the page really contains questions and answers; a graph
  that fails validation is withheld rather than published.
* **No search volume, difficulty or competition figures.** A language model does
  not have that data, and a plausible-looking number with nothing behind it is
  worse than no number.
* **AI suggestions are drafts.** Internal link suggestions choose from real
  pages on the site, so a suggested target cannot be a page that was never
  written. FAQ answers are checked against the page they came from.
* **Analytics and Search Console show what Google reports.** With no connection
  there are no figures — not zeroes, not a placeholder chart.

= AI providers =

Anthropic, OpenAI and Google models are supported. You supply your own API key;
the plugin stores it encrypted, throttles requests, and logs metadata only —
never your prompts or the generated text.

AI features are entirely optional. Everything else, including the whole site
audit, works without a key and makes no external request.

= Privacy =

The plugin makes no outbound request unless you connect something that requires
one:

* AI features send the page content you ask about to the provider you chose.
* Search Console and Analytics reporting send your property id to Google.

No data is sent to the plugin author. Nothing is phoned home.

== Installation ==

1. Upload the folder to `wp-content/plugins/`.
2. Activate it.
3. Visit **SEO → Settings**.

Composer is optional. Without `vendor/`, a small PSR-4 fallback autoloader takes
over.

== Frequently Asked Questions ==

= Does this replace my current SEO plugin? =

It can. **SEO → Tools** imports titles, descriptions, keyphrases, canonicals,
robots settings and social fields from Yoast SEO, Rank Math, SEOPress and The
SEO Framework. The other plugin does not need to be active, and its data is read
rather than changed — if the result is not what you wanted, it is all still
there.

= Will it conflict with another SEO plugin? =

Two plugins both writing titles and structured data will conflict. The Schema
screen lists other active structured-data emitters so you can see the overlap,
but nothing is ever disabled automatically — that choice is yours.

= Does the score mean my page will rank? =

No, and the plugin says so wherever the score appears. It measures how
completely a page follows well-established on-page practices. Ranking depends on
a great deal this plugin cannot see.

= Do I need an API key? =

Only for the AI features. The analysis, audit, schema, sitemaps, redirects and
link graph are all computed locally.

== Screenshots ==

1. The editor SEO panel, with live analysis and a search-result preview.
2. The site audit.
3. Search performance from Google Search Console.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
