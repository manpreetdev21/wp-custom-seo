<?php
/**
 * Dependency-free self-check for the pure logic in Phase 1.
 *
 * Run: php tests/self-check.php
 *
 * WordPress is stubbed rather than loaded, so this covers sanitization,
 * defaults and migration ordering only. Integration behaviour (menus,
 * capabilities, dbDelta) needs the WordPress test suite.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ );

$GLOBALS['wpcseo_test_options'] = array();
$GLOBALS['wpcseo_test_filters'] = array();

/**
 * Stub.
 *
 * @param string $text Text.
 */
function __( string $text ): string {
	return $text;
}

/**
 * Stub. English plural rules are enough to exercise the callers.
 *
 * @param string $single Singular form.
 * @param string $plural Plural form.
 * @param int    $number Count deciding which is used.
 */
function _n( string $single, string $plural, int $number ): string {
	return 1 === $number ? $single : $plural;
}

/**
 * Stub.
 *
 * @param string $key Raw key.
 */
function sanitize_key( string $key ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
}

/**
 * Stub.
 *
 * @param string   $hook     Filter name.
 * @param callable $callback Callback.
 */
function add_filter( string $hook, callable $callback ): void {
	$GLOBALS['wpcseo_test_filters'][ $hook ][] = $callback;
}

/**
 * Stub. Actions are recorded like filters; nothing here fires them.
 *
 * @param string   $hook     Action name.
 * @param callable $callback Callback.
 */
function add_action( string $hook, callable $callback ): void {
	$GLOBALS['wpcseo_test_filters'][ $hook ][] = $callback;
}

/**
 * Stub. The checks below exercise registration, which is context-free.
 */
function is_admin(): bool {
	return false;
}

/**
 * Stub.
 *
 * @param string $hook  Filter name.
 * @param mixed  $value Value.
 * @param mixed  ...$args Extra args.
 */
function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
	foreach ( $GLOBALS['wpcseo_test_filters'][ $hook ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}

	return $value;
}

/**
 * Stub.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default.
 */
function get_option( string $name, mixed $default = false ): mixed {
	return $GLOBALS['wpcseo_test_options'][ $name ] ?? $default;
}

/**
 * Stub.
 *
 * @param string $name  Option name.
 * @param mixed  $value Value.
 */
function update_option( string $name, mixed $value ): bool {
	$GLOBALS['wpcseo_test_options'][ $name ] = $value;

	return true;
}

/**
 * Stub.
 *
 * @param string $value Value.
 */
function sanitize_text_field( string $value ): string {
	return trim( strip_tags( $value ) );
}

/**
 * Stub.
 *
 * @param string $value Value.
 */
function sanitize_textarea_field( string $value ): string {
	return trim( strip_tags( $value ) );
}

/**
 * Stub.
 *
 * @param string $hook Action name.
 * @param mixed  ...$args Arguments.
 */
function do_action( string $hook, mixed ...$args ): void {
}

/**
 * Stub.
 *
 * @param string $value HTML.
 */
function wp_strip_all_tags( string $value ): string {
	return strip_tags( $value );
}

/**
 * Stub.
 *
 * @param string $url       URL.
 * @param int    $component Component constant.
 */
function wp_parse_url( string $url, int $component = -1 ): mixed {
	return parse_url( $url, $component );
}

/**
 * Stub.
 *
 * @param mixed $data  Data.
 * @param int   $flags Encoding flags.
 */
function wp_json_encode( mixed $data, int $flags = 0 ): string|false {
	return json_encode( $data, $flags );
}

/**
 * Stub.
 *
 * @param string $timezone Timezone.
 */
function get_lastpostmodified( string $timezone = 'server' ): string {
	return $GLOBALS['wpcseo_test_lastmodified'] ?? '2026-01-01 00:00:00';
}

define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

// Credential encryption is keyed from this. A throwaway value is enough to
// exercise the round trip; it is not a real salt and never leaves this file.
define( 'AUTH_SALT', 'self-check-salt-not-a-real-one' );

/**
 * Stub.
 *
 * @param string $name Option name.
 */
function delete_option( string $name ): bool {
	unset( $GLOBALS['wpcseo_test_options'][ $name ] );

	return true;
}

/**
 * Stub.
 *
 * @param string $name Transient name.
 */
function get_transient( string $name ): mixed {
	return $GLOBALS['wpcseo_test_transients'][ $name ] ?? false;
}

/**
 * Stub.
 *
 * @param string $name       Transient name.
 * @param mixed  $value      Value.
 * @param int    $expiration Lifetime in seconds.
 */
function set_transient( string $name, mixed $value, int $expiration = 0 ): bool {
	$GLOBALS['wpcseo_test_transients'][ $name ] = $value;

	return true;
}

/**
 * Stub.
 *
 * @param string $name Transient name.
 */
function delete_transient( string $name ): bool {
	unset( $GLOBALS['wpcseo_test_transients'][ $name ] );

	return true;
}

/**
 * Stub.
 *
 * @param string $path Path appended to the site URL.
 */
function home_url( string $path = '' ): string {
	return 'https://example.test' . $path;
}

/**
 * Stub.
 *
 * @param string $url URL.
 */
function sanitize_url( string $url ): string {
	$clean = filter_var( $url, FILTER_SANITIZE_URL );

	return false === $clean ? '' : $clean;
}

/**
 * Stub.
 *
 * @param string $content Content.
 */
function do_shortcode( string $content ): string {
	return $content;
}

/**
 * Stub. Resolves only the URLs the test registers.
 *
 * @param string $url URL.
 */
function url_to_postid( string $url ): int {
	return (int) ( $GLOBALS['wpcseo_test_urls'][ $url ] ?? 0 );
}

/**
 * Stub.
 *
 * @param int|object $post Post.
 */
function get_permalink( $post = 0 ): string {
	return 'https://example.test/product';
}

/**
 * Stub.
 *
 * @param string $taxonomy Taxonomy name.
 */
function taxonomy_exists( string $taxonomy ): bool {
	return false;
}

/**
 * Stub.
 *
 * @param mixed $value    Value.
 * @param int   $decimals Decimal places.
 */
function wc_format_decimal( $value, int $decimals = 2 ): string {
	return number_format( (float) $value, $decimals, '.', '' );
}

/**
 * Stub.
 */
function wc_get_price_decimals(): int {
	return 2;
}

/**
 * Stub.
 */
function get_woocommerce_currency(): string {
	return 'GBP';
}

/**
 * Stub. Returns whatever the test registered.
 *
 * @param int $post_id Product id.
 */
function wc_get_product( int $post_id ) {
	return $GLOBALS['wpcseo_test_product'] ?? false;
}

/**
 * A WooCommerce product stand-in exposing only what Product::node() reads.
 */
class WPCSEO_Fake_Product {

	/**
	 * @param array<string, mixed> $data Product values.
	 */
	public function __construct( private array $data = array() ) {
	}

	/** @return string */
	public function get_name(): string {
		return (string) ( $this->data['name'] ?? 'Widget' ); }

	/** @return string */
	public function get_short_description(): string {
		return (string) ( $this->data['short'] ?? '' ); }

	/** @return string */
	public function get_description(): string {
		return (string) ( $this->data['description'] ?? '' ); }

	/** @return string */
	public function get_sku(): string {
		return (string) ( $this->data['sku'] ?? '' ); }

	/** @return int */
	public function get_image_id(): int {
		return (int) ( $this->data['image_id'] ?? 0 ); }

	/** @return mixed */
	public function get_price() {
		return $this->data['price'] ?? ''; }

	/** @return bool */
	public function is_in_stock(): bool {
		return (bool) ( $this->data['in_stock'] ?? true ); }

	/** @return mixed */
	public function get_date_on_sale_to() {
		return $this->data['sale_to'] ?? null; }

	/** @return int */
	public function get_rating_count(): int {
		return (int) ( $this->data['rating_count'] ?? 0 ); }

	/** @return float */
	public function get_average_rating(): float {
		return (float) ( $this->data['average_rating'] ?? 0 ); }
}

/**
 * Minimal stand-in for the WordPress error object.
 */
/**
 * Enough of a post for the pieces that only read content.
 */
class WP_Post {

	/**
	 * Post content.
	 *
	 * @var string
	 */
	public string $post_content = '';

	/**
	 * Construct.
	 *
	 * @param string $content Post content.
	 */
	public function __construct( string $content = '' ) {
		$this->post_content = $content;
	}
}

class WP_Error {

	/**
	 * @param string $code    Error code.
	 * @param string $message Human-readable message.
	 */
	public function __construct( private string $code = '', private string $message = '' ) {
	}

	/**
	 * The error code.
	 */
	public function get_error_code(): string {
		return $this->code;
	}

	/**
	 * The error message.
	 */
	public function get_error_message(): string {
		return $this->message;
	}
}

require_once __DIR__ . '/../src/Core/Autoloader.php';
\WPCustomSeo\Core\Autoloader::register();

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Database\Migrator;
use WPCustomSeo\AI\AnthropicProvider;
use WPCustomSeo\Audit\Cannibalization;
use WPCustomSeo\Audit\Decay;
use WPCustomSeo\Audit\Finding;
use WPCustomSeo\AI\Json as AIJson;
use WPCustomSeo\AI\OpenAIProvider;
use WPCustomSeo\AI\Prompts\ContentAnalysisPrompt;
use WPCustomSeo\AI\Prompts\ContentBriefPrompt;
use WPCustomSeo\AI\Prompts\FaqPrompt;
use WPCustomSeo\AI\Prompts\InternalLinkPrompt;
use WPCustomSeo\AI\Prompts\KeywordPrompt;
use WPCustomSeo\API\AIRoutes;
use WPCustomSeo\Schema\Faq;
use WPCustomSeo\AI\Request as AIRequest;
use WPCustomSeo\AI\Response as AIResponse;
use WPCustomSeo\Entities\Registry;
use WPCustomSeo\Links\Links;
use WPCustomSeo\Links\Scanner;
use WPCustomSeo\Local\Locations;
use WPCustomSeo\Redirects\Redirects as RedirectRules;
use WPCustomSeo\WooCommerce\Product;
use WPCustomSeo\Schema\Aggregator;
use WPCustomSeo\Schema\Cache;
use WPCustomSeo\Analytics\Client as AnalyticsClient;
use WPCustomSeo\Reports\Mailer;
use WPCustomSeo\Reports\Report;
use WPCustomSeo\Reports\Schedule;
use WPCustomSeo\SearchConsole\Account;
use WPCustomSeo\SearchConsole\Performance;
use WPCustomSeo\SearchConsole\Token;
use WPCustomSeo\Schema\Graph\Graph;
use WPCustomSeo\Schema\Graph\Pieces;
use WPCustomSeo\Schema\Validator;
use WPCustomSeo\SEO\Analyzer;
use WPCustomSeo\SEO\Meta;
use WPCustomSeo\Transfer\Csv;
use WPCustomSeo\Transfer\Sources;
use WPCustomSeo\SEO\Templates;
use WPCustomSeo\SEO\Weights;

$failures = 0;

/**
 * Tiny assertion helper.
 *
 * @param string $label     What is being checked.
 * @param bool   $condition Result.
 */
function wpcseo_check( string $label, bool $condition ): void {
	global $failures;

	if ( ! $condition ) {
		++$failures;
		echo "FAIL  {$label}\n";

		return;
	}

	echo "ok    {$label}\n";
}

// Defaults are applied when nothing is stored.
wpcseo_check( 'default: enable_seo is on', true === Settings::get( 'enable_seo' ) );
wpcseo_check( 'default: enable_breadcrumbs is off', false === Settings::get( 'enable_breadcrumbs' ) );
wpcseo_check( 'default: uninstall keeps data', false === Settings::get( 'delete_data_on_uninstall' ) );
wpcseo_check( 'unknown key returns fallback', 'x' === Settings::get( 'nope', 'x' ) );

// Sanitization.
$clean = Settings::sanitize(
	array(
		'enable_seo'  => '1',
		'enable_schema' => 'yes',
		'evil'        => '<script>alert(1)</script>',
	)
);

wpcseo_check( 'sanitize: truthy checkbox becomes true', true === $clean['enable_seo'] );
wpcseo_check( 'sanitize: absent checkbox becomes false', false === $clean['enable_analysis'] );
wpcseo_check( 'sanitize: unknown keys dropped', ! array_key_exists( 'evil', $clean ) );
wpcseo_check( 'sanitize: every schema field present', count( $clean ) === count( Settings::fields() ) );

// Stored values win over defaults.
$GLOBALS['wpcseo_test_options'][ Settings::OPTION ] = array( 'enable_seo' => false );
Settings::flush();
wpcseo_check( 'stored value overrides default', false === Settings::get( 'enable_seo' ) );
wpcseo_check( 'unset field falls back to default', true === Settings::get( 'enable_schema' ) );

// Schema filter extends the field set.
add_filter(
	'wpcseo_settings_schema',
	static function ( array $schema ): array {
		$schema['general']['fields']['tone'] = array(
			'type'    => 'select',
			'label'   => 'Tone',
			'default' => 'neutral',
			'options' => array(
				'neutral' => 'Neutral',
				'bold'    => 'Bold',
			),
		);

		return $schema;
	}
);
Settings::flush();

wpcseo_check( 'filter adds a field', 'neutral' === Settings::get( 'tone' ) );
$clean = Settings::sanitize( array( 'tone' => 'wat' ) );
wpcseo_check( 'select rejects values outside its options', 'neutral' === $clean['tone'] );
$clean = Settings::sanitize( array( 'tone' => 'bold' ) );
wpcseo_check( 'select accepts a listed value', 'bold' === $clean['tone'] );

// Migrations.
wpcseo_check( 'no migrations means version 0', '0' === Migrator::target_version() );
wpcseo_check( 'nothing pending at version 0', false === Migrator::is_pending() );

add_filter(
	'wpcseo_migrations',
	static function ( array $migrations ): array {
		$migrations['1.10.0'] = static fn() => null;
		$migrations['1.2.0']  = static fn() => null;

		return $migrations;
	}
);

wpcseo_check( 'migrations sort by version, not string', array( '1.2.0', '1.10.0' ) === array_keys( Migrator::migrations() ) );
wpcseo_check( 'target is the highest version', '1.10.0' === Migrator::target_version() );
wpcseo_check( 'pending when stored version is behind', true === Migrator::is_pending() );

$GLOBALS['wpcseo_test_options'][ Migrator::VERSION_OPTION ] = '1.10.0';
wpcseo_check( 'not pending once current', false === Migrator::is_pending() );

// --- Phase 2: title templates -------------------------------------------------

wpcseo_check(
	'template: variables expand',
	'Widgets - Acme' === Templates::expand(
		'%%title%% %%sep%% %%sitename%%',
		array(
			'title'    => 'Widgets',
			'sep'      => '-',
			'sitename' => 'Acme',
		)
	)
);

wpcseo_check(
	'template: empty variable does not leave a dangling separator',
	'Acme' === Templates::expand(
		'%%title%% %%sep%% %%sitename%%',
		array(
			'title'    => '',
			'sep'      => '-',
			'sitename' => 'Acme',
		)
	)
);

wpcseo_check(
	'template: unknown variable is removed',
	'Acme' === Templates::expand( '%%nope%% %%sitename%%', array( 'sitename' => 'Acme' ) )
);

wpcseo_check( 'truncate: short text untouched', 'Hello there' === Templates::truncate( 'Hello there', 60 ) );
wpcseo_check( 'truncate: cuts on a word boundary', 'Hello…' === Templates::truncate( 'Hello enormous world', 12 ) );
wpcseo_check( 'truncate: collapses whitespace', 'a b' === Templates::truncate( "a \n b", 60 ) );

// --- Phase 2: analysis engine -------------------------------------------------

/**
 * Pull one check out of an analysis result.
 *
 * @param array  $result Analysis result.
 * @param string $id     Check id.
 */
function wpcseo_find_check( array $result, string $id ): ?array {
	foreach ( $result['checks'] as $check ) {
		if ( $check['id'] === $id ) {
			return $check;
		}
	}

	return null;
}

$empty = Analyzer::analyze( array() );
// An empty page keeps a few points because "no images" is only a warning.
wpcseo_check( 'analysis: empty page scores very low', $empty['score'] < 30 );
wpcseo_check( 'analysis: empty page graded bad', 'bad' === $empty['grade'] );
wpcseo_check( 'analysis: missing keyphrase flagged', 'bad' === wpcseo_find_check( $empty, 'focus_keyword' )['status'] );
wpcseo_check( 'analysis: keyword checks skipped without a keyphrase', null === wpcseo_find_check( $empty, 'title_keyword' ) );

$body = '<p>Roof insulation keeps a house warm. ' . str_repeat( 'This paragraph explains the practical detail of the work in plain terms. ', 30 )
	. '</p><h2>Choosing roof insulation</h2><p>' . str_repeat( 'More useful guidance follows here for the reader. ', 10 )
	. '</p><p><img src="a.jpg" alt="Loft insulation being fitted"> '
	. '<a href="https://example.com/costs">costs</a> <a href="https://gov.uk/standards">standards</a></p>';

$good = Analyzer::analyze(
	array(
		'title'       => 'Roof insulation: a practical guide for older houses',
		'description' => 'Roof insulation explained for older houses: which materials suit which roof, what the work involves, what it costs, and how to tell when it has been done badly.',
		'keyword'     => 'roof insulation',
		'content'     => $body,
		'slug'        => 'roof-insulation',
		'host'        => 'example.com',
	)
);

wpcseo_check( 'analysis: well-optimized page scores high', $good['score'] >= 90 );
wpcseo_check( 'analysis: graded good', 'good' === $good['grade'] );
wpcseo_check( 'analysis: word count counted', $good['word_count'] > 300 );
wpcseo_check( 'analysis: keyphrase found in title', 'good' === wpcseo_find_check( $good, 'title_keyword' )['status'] );
wpcseo_check( 'analysis: subheading with keyphrase found', 'good' === wpcseo_find_check( $good, 'keyword_subheading' )['status'] );
wpcseo_check( 'analysis: slug match found', 'good' === wpcseo_find_check( $good, 'slug_keyword' )['status'] );
wpcseo_check( 'analysis: internal link counted', 'good' === wpcseo_find_check( $good, 'internal_links' )['status'] );
wpcseo_check( 'analysis: external link counted', 'good' === wpcseo_find_check( $good, 'external_links' )['status'] );
wpcseo_check( 'analysis: alt text present', 'good' === wpcseo_find_check( $good, 'image_alt' )['status'] );
wpcseo_check( 'analysis: every check explains itself', ! array_filter( $good['checks'], static fn ( array $c ): bool => '' === $c['why'] || '' === $c['recommendation'] ) );

$stuffed = Analyzer::analyze(
	array(
		'title'   => 'Roof insulation',
		'keyword' => 'roof insulation',
		'content' => '<p>' . str_repeat( 'roof insulation ', 60 ) . '</p>',
		'host'    => 'example.com',
	)
);
wpcseo_check( 'analysis: keyword stuffing flagged', 'bad' === wpcseo_find_check( $stuffed, 'keyword_density' )['status'] );

$no_alt = Analyzer::analyze( array( 'content' => '<img src="a.jpg"><img src="b.jpg" alt="described">' ) );
wpcseo_check( 'analysis: missing alt text flagged', 'bad' === wpcseo_find_check( $no_alt, 'image_alt' )['status'] );

$empty_alt = Analyzer::analyze( array( 'content' => '<img src="a.jpg" alt="">' ) );
wpcseo_check( 'analysis: empty alt counts as missing', 'bad' === wpcseo_find_check( $empty_alt, 'image_alt' )['status'] );

$relative = Analyzer::analyze(
	array(
		'content' => '<a href="/about">about</a><a href="#skip">skip</a><a href="mailto:a@b.c">mail</a>',
		'host'    => 'example.com',
	)
);
wpcseo_check( 'analysis: relative link counts as internal', 'good' === wpcseo_find_check( $relative, 'internal_links' )['status'] );
wpcseo_check( 'analysis: anchors and mailto ignored', 'warn' === wpcseo_find_check( $relative, 'external_links' )['status'] );

$www = Analyzer::analyze(
	array(
		'content' => '<a href="https://www.example.com/x">x</a>',
		'host'    => 'example.com',
	)
);
wpcseo_check( 'analysis: www host treated as internal', 'good' === wpcseo_find_check( $www, 'internal_links' )['status'] );

$long_title = Analyzer::analyze( array( 'title' => str_repeat( 'a', 90 ) ) );
wpcseo_check( 'analysis: overlong title warned', 'warn' === wpcseo_find_check( $long_title, 'title_length' )['status'] );

$multi_h1 = Analyzer::analyze( array( 'content' => '<h1>One</h1><h1>Two</h1><h2>Sub</h2>' ) );
wpcseo_check( 'analysis: duplicate H1 flagged', 'warn' === wpcseo_find_check( $multi_h1, 'single_h1' )['status'] );
wpcseo_check( 'analysis: single H1 not flagged', null === wpcseo_find_check( Analyzer::analyze( array( 'content' => '<h1>One</h1>' ) ), 'single_h1' ) );

wpcseo_check( 'analysis: script contents excluded from text', ! str_contains( Analyzer::to_text( '<p>hi</p><script>var x = "spam";</script>' ), 'spam' ) );
wpcseo_check( 'grade: boundaries', 'good' === Analyzer::grade( 80 ) && 'warn' === Analyzer::grade( 50 ) && 'bad' === Analyzer::grade( 49 ) );

// --- Phase 3: schema graph ----------------------------------------------------

$graph = new Graph();
$graph->add( array( '@type' => 'WebSite', '@id' => 'https://x.test/#site', 'url' => 'https://x.test/', 'name' => 'X' ) );
$graph->add( array( '@type' => 'WebSite', '@id' => 'https://x.test/#site', 'description' => 'merged' ) );

wpcseo_check( 'graph: same @id merges instead of duplicating', 1 === count( $graph->nodes() ) );
wpcseo_check( 'graph: merged node keeps both properties', 'merged' === $graph->nodes()[0]['description'] && 'X' === $graph->nodes()[0]['name'] );
wpcseo_check( 'graph: node without @id is ignored', ( static function () {
	$g = new Graph();
	$g->add( array( '@type' => 'Thing' ) );
	$g->add( null );
	return 0 === count( $g->nodes() );
} )() );
wpcseo_check( 'graph: has() finds a node', $graph->has( 'https://x.test/#site' ) );
wpcseo_check( 'graph: json escapes slashes so it cannot break out of a script tag', ! str_contains( $graph->to_json(), '</' ) );
wpcseo_check( 'graph: document carries @context and @graph', array( '@context', '@graph' ) === array_keys( $graph->to_array() ) );

// --- Phase 3: validation ------------------------------------------------------

/**
 * Build a graph from raw nodes.
 *
 * @param array $nodes Nodes.
 */
function wpcseo_graph( array $nodes ): Graph {
	$g = new Graph();
	$g->add_all( $nodes );

	return $g;
}

/**
 * Whether any issue matches a level and substring.
 *
 * @param array  $issues   Issues.
 * @param string $level    Level.
 * @param string $fragment Substring to look for.
 */
function wpcseo_has_issue( array $issues, string $level, string $fragment ): bool {
	foreach ( $issues as $issue ) {
		if ( $issue['level'] === $level && str_contains( $issue['message'], $fragment ) ) {
			return true;
		}
	}

	return false;
}

wpcseo_check( 'validator: empty graph reports a notice', 'notice' === Validator::validate( new Graph() )[0]['level'] );

$valid = wpcseo_graph(
	array(
		array( '@type' => 'Organization', '@id' => 'https://x.test/#/schema/organization', 'name' => 'X Ltd', 'url' => 'https://x.test/' ),
		array( '@type' => 'WebSite', '@id' => 'https://x.test/#/schema/website', 'url' => 'https://x.test/', 'name' => 'X', 'publisher' => array( '@id' => 'https://x.test/#/schema/organization' ) ),
	)
);
wpcseo_check( 'validator: a consistent graph reports nothing', array() === Validator::validate( $valid ) );
wpcseo_check( 'validator: no errors on a clean graph', false === Validator::has_errors( Validator::validate( $valid ) ) );

$dangling = wpcseo_graph(
	array(
		array( '@type' => 'WebSite', '@id' => 'https://x.test/#site', 'url' => 'https://x.test/', 'name' => 'X', 'publisher' => array( '@id' => 'https://x.test/#missing' ) ),
	)
);
wpcseo_check( 'validator: unresolved reference warned', wpcseo_has_issue( Validator::validate( $dangling ), 'warning', 'not in the graph' ) );

$bad_url = wpcseo_graph(
	array(
		array( '@type' => 'ImageObject', '@id' => 'https://x.test/#img', 'url' => '/relative/logo.png' ),
	)
);
wpcseo_check( 'validator: relative URL is an error', wpcseo_has_issue( Validator::validate( $bad_url ), 'error', 'absolute http(s) URL' ) );
wpcseo_check( 'validator: URL error blocks output', Validator::has_errors( Validator::validate( $bad_url ) ) );

// A nested ImageObject is an object, not a URL string, and must not be
// flattened into its values and checked as one.
$inline_image = wpcseo_graph(
	array(
		array(
			'@type' => 'Person',
			'@id'   => 'https://x.test/#p',
			'name'  => 'A',
			'image' => array( '@type' => 'ImageObject', '@id' => 'https://x.test/#pi', 'url' => 'https://x.test/a.png' ),
		),
	)
);
wpcseo_check( 'validator: nested node is not treated as a URL', array() === Validator::validate( $inline_image ) );

wpcseo_check(
	'validator: a list of URLs is still checked',
	wpcseo_has_issue(
		Validator::validate_nodes(
			array( array( '@type' => 'Person', '@id' => 'https://x.test/#p', 'name' => 'A', 'sameAs' => array( 'https://ok.test/', 'nope' ) ) )
		),
		'error',
		'absolute http(s) URL'
	)
);

$untyped = wpcseo_graph( array( array( '@id' => 'https://x.test/#thing' ) ) );
wpcseo_check( 'validator: missing @type is an error', wpcseo_has_issue( Validator::validate( $untyped ), 'error', '@type' ) );

$thin_article = wpcseo_graph(
	array(
		array( '@type' => 'Article', '@id' => 'https://x.test/p#article', 'headline' => 'Hi' ),
	)
);
$thin_issues = Validator::validate( $thin_article );
wpcseo_check( 'validator: article missing datePublished warned', wpcseo_has_issue( $thin_issues, 'warning', 'datePublished' ) );
wpcseo_check( 'validator: article missing author warned', wpcseo_has_issue( $thin_issues, 'warning', 'author' ) );
wpcseo_check( 'validator: missing properties are warnings, not errors', false === Validator::has_errors( $thin_issues ) );

// Graph::add merges by @id, so duplicates can only reach the validator from a
// filter or the aggregation endpoint handing over raw nodes.
$twin = array( '@type' => 'Person', '@id' => 'https://x.test/#p', 'name' => 'A' );
wpcseo_check(
	'validator: duplicate @id in raw nodes is an error',
	wpcseo_has_issue( Validator::validate_nodes( array( $twin, $twin ) ), 'error', 'Duplicate @id' )
);

// --- Phase 3b: URL handling ---------------------------------------------------

wpcseo_check( 'url: absolute https accepted', Registry::is_url( 'https://example.com/a' ) );
wpcseo_check( 'url: relative rejected', false === Registry::is_url( '/relative' ) );
wpcseo_check( 'url: javascript scheme rejected', false === Registry::is_url( 'javascript:alert(1)' ) );
wpcseo_check( 'url: ftp scheme rejected', false === Registry::is_url( 'ftp://example.com/a' ) );
wpcseo_check( 'url: empty rejected', false === Registry::is_url( '' ) );

wpcseo_check(
	'urls: invalid lines dropped, duplicates collapsed, whitespace trimmed',
	array( 'https://a.test/', 'https://b.test/' ) === Registry::urls( "  https://a.test/  \nnope\n\nhttps://b.test/\nhttps://a.test/" )
);
wpcseo_check( 'urls: empty input yields nothing', array() === Registry::urls( '' ) );

// --- Phase 3b: aggregation batching -------------------------------------------

$GLOBALS['wpcseo_test_options'][ Settings::OPTION ] = array( 'schema_api_batch' => '50' );
Settings::flush();
wpcseo_check( 'batch: uses the configured size', 50 === Aggregator::batch( 'post' ) );

add_filter(
	'wpcseo_schema_api_batch',
	static fn ( int $batch, string $post_type ): int => 'product' === $post_type ? 10 : $batch
);
wpcseo_check( 'batch: filter can shrink one post type', 10 === Aggregator::batch( 'product' ) );
wpcseo_check( 'batch: other post types unaffected', 50 === Aggregator::batch( 'post' ) );

add_filter( 'wpcseo_schema_api_batch', static fn (): int => 100000 );
wpcseo_check( 'batch: clamped to the ceiling', 500 === Aggregator::batch( 'post' ) );

$GLOBALS['wpcseo_test_filters']['wpcseo_schema_api_batch'] = array( static fn (): int => 0 );
wpcseo_check( 'batch: never drops below one', 1 === Aggregator::batch( 'post' ) );
unset( $GLOBALS['wpcseo_test_filters']['wpcseo_schema_api_batch'] );

// --- Phase 3b: cache keys -----------------------------------------------------

$GLOBALS['wpcseo_test_options'][ Cache::VERSION_OPTION ] = '1';
$GLOBALS['wpcseo_test_lastmodified'] = '2026-01-01 00:00:00';

$key_a = Cache::key( 'page', 'post', '1' );
wpcseo_check( 'cache: key is stable for the same inputs', $key_a === Cache::key( 'page', 'post', '1' ) );
wpcseo_check( 'cache: different page means a different key', $key_a !== Cache::key( 'page', 'post', '2' ) );
wpcseo_check( 'cache: different post type means a different key', $key_a !== Cache::key( 'page', 'page', '1' ) );
wpcseo_check( 'cache: key fits the transient name limit', strlen( $key_a ) <= 172 );

$GLOBALS['wpcseo_test_lastmodified'] = '2026-06-01 12:00:00';
wpcseo_check( 'cache: content change invalidates without a write', $key_a !== Cache::key( 'page', 'post', '1' ) );

$GLOBALS['wpcseo_test_lastmodified'] = '2026-01-01 00:00:00';
wpcseo_check( 'cache: reverting the timestamp restores the key', $key_a === Cache::key( 'page', 'post', '1' ) );

Cache::bump();
wpcseo_check( 'cache: bump invalidates everything', $key_a !== Cache::key( 'page', 'post', '1' ) );
wpcseo_check( 'cache: bump increments the stored counter', '2' === $GLOBALS['wpcseo_test_options'][ Cache::VERSION_OPTION ] );

// --- Phase 3c: noindex exclusion clause ---------------------------------------

$clause = Meta::exclude_noindex_clause();
wpcseo_check( 'noindex clause: is an OR relation', 'OR' === $clause['relation'] );
wpcseo_check( 'noindex clause: admits posts without the meta', 'NOT EXISTS' === $clause[0]['compare'] );
wpcseo_check( 'noindex clause: admits posts whose value is not 1', '!=' === $clause[1]['compare'] && '1' === $clause[1]['value'] );
wpcseo_check( 'noindex clause: targets the noindex key', Meta::NOINDEX === $clause[0]['key'] && Meta::NOINDEX === $clause[1]['key'] );

// --- Phase 3c: schema types stay honest ---------------------------------------

$types = Meta::schema_types();
wpcseo_check( 'schema types: FAQPage not offered without verifiable content', ! isset( $types['FAQPage'] ) );
wpcseo_check( 'schema types: HowTo not offered without verifiable content', ! isset( $types['HowTo'] ) );
wpcseo_check( 'schema types: automatic and none are available', isset( $types['auto'], $types['none'] ) );
wpcseo_check( 'schema types: unknown value rejected on save', '' === Meta::sanitize_schema_type( 'Recipe' ) );
wpcseo_check( 'schema types: known value accepted', 'NewsArticle' === Meta::sanitize_schema_type( 'NewsArticle' ) );

// --- Phase 3c: social meta keys registered ------------------------------------

$keys = array_keys( Meta::keys() );

foreach ( array( Meta::OG_TITLE, Meta::OG_DESCRIPTION, Meta::OG_IMAGE, Meta::TWITTER_TITLE, Meta::TWITTER_DESCRIPTION, Meta::TWITTER_IMAGE, Meta::BREADCRUMB_TITLE ) as $social_key ) {
	wpcseo_check( 'meta: ' . $social_key . ' is registered', in_array( $social_key, $keys, true ) );
}

foreach ( array( Meta::OG_IMAGE, Meta::TWITTER_IMAGE, Meta::CANONICAL ) as $url_key ) {
	wpcseo_check( 'meta: ' . $url_key . ' sanitizes as an absolute URL', array( Meta::class, 'sanitize_url_field' ) === Meta::keys()[ $url_key ]['sanitize'] );
}

// sanitize_url() prepends a scheme to anything, so a typo would otherwise be
// stored as a URL and published as a canonical.
wpcseo_check( 'meta: an absolute URL survives', 'https://example.test/page/' === Meta::sanitize_url_field( 'https://example.test/page/' ) );
wpcseo_check( 'meta: a bare word is not a URL', '' === Meta::sanitize_url_field( 'not-a-url' ) );
wpcseo_check( 'meta: a schemeless host is not accepted', '' === Meta::sanitize_url_field( 'example.test/page' ) );
wpcseo_check( 'meta: a relative path is not accepted', '' === Meta::sanitize_url_field( '/page/' ) );
wpcseo_check( 'meta: surrounding whitespace ignored', 'https://example.test/' === Meta::sanitize_url_field( '  https://example.test/  ' ) );

// --- Phase 4: redirect normalisation and validation ---------------------------

wpcseo_check( 'redirect: trailing slash removed', '/old-page' === RedirectRules::normalize( '/old-page/' ) );
wpcseo_check( 'redirect: case folded', '/old-page' === RedirectRules::normalize( '/Old-Page' ) );
wpcseo_check( 'redirect: absolute URL reduced to its path', '/old-page' === RedirectRules::normalize( 'https://example.test/old-page' ) );
wpcseo_check( 'redirect: query string dropped from the match', '/old-page' === RedirectRules::normalize( '/old-page?utm_source=x' ) );
wpcseo_check( 'redirect: leading slash added', '/old-page' === RedirectRules::normalize( 'old-page' ) );
wpcseo_check( 'redirect: root survives slash trimming', '/' === RedirectRules::normalize( '/' ) );
wpcseo_check( 'redirect: encoded path decoded', '/a b' === RedirectRules::normalize( '/a%20b' ) );

wpcseo_check( 'target: absolute URL kept', 'https://elsewhere.test/x' === RedirectRules::sanitize_target( 'https://elsewhere.test/x' ) );
wpcseo_check( 'target: relative path kept', '/new-page' === RedirectRules::sanitize_target( 'new-page' ) );
wpcseo_check( 'target: javascript scheme stripped', ! str_contains( RedirectRules::sanitize_target( 'javascript:alert(1)' ), 'javascript:' ) );
wpcseo_check( 'target: data scheme stripped', ! str_contains( RedirectRules::sanitize_target( 'data:text/html,<script>' ), 'data:' ) );

wpcseo_check( 'regex: valid pattern accepted', RedirectRules::is_valid_regex( '^/blog/(.+)$' ) );
wpcseo_check( 'regex: unbalanced bracket rejected', false === RedirectRules::is_valid_regex( '/[unclosed' ) );
wpcseo_check( 'regex: delimiter inside the pattern is escaped', RedirectRules::is_valid_regex( '^/a#b$' ) );

wpcseo_check( 'redirect: status codes limited to the four that make sense', array( 301, 302, 307, 308 ) === array_keys( RedirectRules::types() ) );

$bad_type = RedirectRules::validate( array( 'source' => '/a', 'target' => '/b', 'type' => 418 ) );
wpcseo_check( 'redirect: bogus status code rejected', $bad_type instanceof WP_Error );

$no_source = RedirectRules::validate( array( 'source' => '', 'target' => '/b', 'type' => 301 ) );
wpcseo_check( 'redirect: empty source rejected', $no_source instanceof WP_Error );

$no_target = RedirectRules::validate( array( 'source' => '/a', 'target' => '', 'type' => 301 ) );
wpcseo_check( 'redirect: empty target rejected', $no_target instanceof WP_Error );

// --- Phase 4b: link extraction ------------------------------------------------

$GLOBALS['wpcseo_test_urls'] = array(
	'https://example.test/hub'  => 42,
	'https://example.test/self' => 7,
);

$html = '<p>'
	. '<a href="https://example.test/hub">hub page</a> '
	. '<a href="/hub">hub again by path</a> '
	. '<a href="https://www.example.test/hub">hub via www</a> '
	. '<a href="/category/news/">a category</a> '
	. '<a href="https://elsewhere.test/x">external</a> '
	. '<a href="#skip">anchor</a> '
	. '<a href="mailto:a@b.c">mail</a> '
	. '<a href="tel:123">phone</a> '
	. '<a href="javascript:alert(1)">js</a> '
	. '<a href="data:text/html,x">data</a> '
	. '<a href="https://example.test/self">myself</a>'
	. '</p>';

$extracted = Scanner::extract( $html, 7 );
$by_type   = array_count_values( array_column( $extracted, 'type' ) );

wpcseo_check( 'links: anchors, mailto, tel, javascript and data skipped', 3 === count( $extracted ) );
// Path, absolute and www spellings of one page must collapse to a single row.
wpcseo_check( 'links: internal resolved once despite three spellings', 1 === ( $by_type['internal'] ?? 0 ) );
wpcseo_check( 'links: same-site non-post recorded as unresolved', 1 === ( $by_type['unresolved'] ?? 0 ) );
wpcseo_check( 'links: off-site recorded as external', 1 === ( $by_type['external'] ?? 0 ) );
wpcseo_check( 'links: self-link excluded', ! in_array( 7, array_column( $extracted, 'target_id' ), true ) );

$internal = null;

foreach ( $extracted as $link ) {
	if ( 'internal' === $link['type'] ) {
		$internal = $link;
		break;
	}
}

wpcseo_check( 'links: internal target resolved to its post id', 42 === $internal['target_id'] );
wpcseo_check( 'links: anchor text captured', 'hub page' === $internal['anchor'] );
wpcseo_check( 'links: unresolved never claims a target', 0 === $extracted[ array_search( 'unresolved', array_column( $extracted, 'type' ), true ) ]['target_id'] );

wpcseo_check( 'links: markup inside the anchor is stripped', 'bold text' === Scanner::extract( '<a href="https://elsewhere.test/">bold <strong>text</strong></a>' )[0]['anchor'] );
wpcseo_check( 'links: content without anchors yields nothing', array() === Scanner::extract( '<p>No links here.</p>' ) );
wpcseo_check( 'links: an empty href is ignored', array() === Scanner::extract( '<a href="">nothing</a>' ) );

// The three link kinds must stay distinct: an unresolved link is not broken.
wpcseo_check( 'links: three kinds are distinct constants', 3 === count( array_unique( array( Links::INTERNAL, Links::UNRESOLVED, Links::EXTERNAL ) ) ) );

// --- Phase 5: opening hours -----------------------------------------------

wpcseo_check( 'hours: valid 24-hour time accepted', '09:30' === Locations::time( '09:30' ) );
wpcseo_check( 'hours: midnight accepted', '00:00' === Locations::time( '00:00' ) );
wpcseo_check( 'hours: last minute of the day accepted', '23:59' === Locations::time( '23:59' ) );
wpcseo_check( 'hours: 24:00 rejected', '' === Locations::time( '24:00' ) );
wpcseo_check( 'hours: 09:60 rejected', '' === Locations::time( '09:60' ) );
wpcseo_check( 'hours: free text rejected', '' === Locations::time( 'morning' ) );
wpcseo_check( 'hours: 12-hour format rejected', '' === Locations::time( '9:30am' ) );
wpcseo_check( 'hours: seven days offered in schema order', array( 'Monday', 'Sunday' ) === array( array_key_first( Locations::days() ), array_key_last( Locations::days() ) ) );
wpcseo_check( 'business types: general type is offered first', 'LocalBusiness' === array_key_first( Locations::business_types() ) );

// --- Phase 5: product data is never invented ----------------------------------

$GLOBALS['wpcseo_test_options'][ Settings::OPTION ] = array( 'brand_name' => '' );
Settings::flush();

$GLOBALS['wpcseo_test_product'] = new WPCSEO_Fake_Product( array( 'name' => 'Roof Vent' ) );
$bare = Product::node( 1 );

wpcseo_check( 'product: name and url always present', 'Roof Vent' === $bare['name'] && isset( $bare['url'] ) );
wpcseo_check( 'product: no price means no Offer', ! isset( $bare['offers'] ) );
wpcseo_check( 'product: no reviews means no aggregateRating', ! isset( $bare['aggregateRating'] ) );
wpcseo_check( 'product: no SKU means no sku property', ! isset( $bare['sku'] ) );
wpcseo_check( 'product: no brand configured means no brand property', ! isset( $bare['brand'] ) );
wpcseo_check( 'product: no image means no image property', ! isset( $bare['image'] ) );
wpcseo_check( 'product: no description means no description property', ! isset( $bare['description'] ) );

$GLOBALS['wpcseo_test_product'] = new WPCSEO_Fake_Product(
	array(
		'name'           => 'Roof Vent',
		'sku'            => 'RV-100',
		'price'          => '24.5',
		'in_stock'       => false,
		'rating_count'   => 12,
		'average_rating' => 4.25,
		'short'          => '<p>A vent.</p>',
	)
);
$full = Product::node( 1 );

wpcseo_check( 'product: price emitted when the shop holds one', '24.50' === $full['offers']['price'] );
wpcseo_check( 'product: currency comes from the shop', 'GBP' === $full['offers']['priceCurrency'] );
wpcseo_check( 'product: out of stock reported honestly', 'https://schema.org/OutOfStock' === $full['offers']['availability'] );
wpcseo_check( 'product: rating emitted only with a real review count', '4.25' === $full['aggregateRating']['ratingValue'] && 12 === $full['aggregateRating']['reviewCount'] );
wpcseo_check( 'product: sku emitted when set', 'RV-100' === $full['sku'] );
wpcseo_check( 'product: description stripped of markup', 'A vent.' === $full['description'] );

// A rating count with no average is incoherent and must not be published.
$GLOBALS['wpcseo_test_product'] = new WPCSEO_Fake_Product( array( 'rating_count' => 5, 'average_rating' => 0 ) );
wpcseo_check( 'product: rating count without an average is dropped', ! isset( Product::node( 1 )['aggregateRating'] ) );

$GLOBALS['wpcseo_test_product'] = new WPCSEO_Fake_Product( array( 'price' => 'call us' ) );
wpcseo_check( 'product: non-numeric price yields no Offer', ! isset( Product::node( 1 )['offers'] ) );

$GLOBALS['wpcseo_test_product'] = false;
wpcseo_check( 'product: unreadable product yields no node', null === Product::node( 1 ) );

// --- Phase 6: AI response parsing ---------------------------------------------

$numbered = new AIResponse( "1. First option\n2. Second option\n3. Third option", 'm' );
wpcseo_check( 'ai: numbered list parsed', array( 'First option', 'Second option', 'Third option' ) === $numbered->lines() );

$dashed = new AIResponse( "- One\n* Two\n• Three", 'm' );
wpcseo_check( 'ai: dash, asterisk and bullet markers stripped', array( 'One', 'Two', 'Three' ) === $dashed->lines() );

$quoted = new AIResponse( "\"Quoted title\"\n'Single quoted'\n“Curly quoted”", 'm' );
wpcseo_check( 'ai: surrounding quotes stripped', array( 'Quoted title', 'Single quoted', 'Curly quoted' ) === $quoted->lines() );

$spaced = new AIResponse( "  Padded  \n\n\n  Another  \n", 'm' );
wpcseo_check( 'ai: blank lines dropped and whitespace trimmed', array( 'Padded', 'Another' ) === $spaced->lines() );

wpcseo_check( 'ai: empty text yields no suggestions', array() === ( new AIResponse( '', 'm' ) )->lines() );
wpcseo_check( 'ai: a hyphen inside a title survives', array( 'Roof insulation - a guide' ) === ( new AIResponse( '1. Roof insulation - a guide', 'm' ) )->lines() );

// --- Phase 6: request shaping -------------------------------------------------

$request = new AIRequest( 'title', 'sys', 'user', 'model-a', 500, 0.7, 12 );
wpcseo_check( 'ai: length counts system plus prompt', ( mb_strlen( 'sys' ) + mb_strlen( 'user' ) ) === $request->length() );

$rebound = $request->with_model( 'model-b', null );
wpcseo_check( 'ai: with_model swaps model', 'model-b' === $rebound->model );
wpcseo_check( 'ai: with_model can drop the temperature', null === $rebound->temperature );
wpcseo_check( 'ai: with_model preserves the rest', 'title' === $rebound->action && 12 === $rebound->post_id && 500 === $rebound->max_tokens );

// --- Phase 6: temperature compatibility ---------------------------------------

// Sending temperature to a model that rejects it is a hard 400, so this is a
// correctness check, not a preference.
$anthropic = new AnthropicProvider();
wpcseo_check( 'ai: temperature withheld from claude-opus-5', false === $anthropic->supports_temperature( 'claude-opus-5' ) );
wpcseo_check( 'ai: temperature withheld from claude-sonnet-5', false === $anthropic->supports_temperature( 'claude-sonnet-5' ) );
wpcseo_check( 'ai: temperature allowed on claude-haiku-4-5', $anthropic->supports_temperature( 'claude-haiku-4-5' ) );
wpcseo_check( 'ai: default model is the current flagship', 'claude-opus-5' === $anthropic->default_model() );
wpcseo_check( 'ai: openai accepts temperature', ( new OpenAIProvider() )->supports_temperature( 'gpt-4o-mini' ) );
wpcseo_check( 'ai: providers that do not publish a model list say so', array() === ( new OpenAIProvider() )->models() );

// --- Phase 6b: reading structured model output --------------------------------

wpcseo_check( 'json: bare object decoded', array( 'a' => 1 ) === AIJson::decode( '{"a":1}' ) );
wpcseo_check( 'json: code fence stripped', array( 'a' => 2 ) === AIJson::decode( "```json\n{\"a\":2}\n```" ) );
wpcseo_check( 'json: unlabelled fence stripped', array( 'a' => 2 ) === AIJson::decode( "```\n{\"a\":2}\n```" ) );
wpcseo_check( 'json: preamble and trailing prose ignored', array( 'a' => 3 ) === AIJson::decode( 'Sure! {"a":3} hope that helps' ) );

// Broken output is reported, never guessed at.
wpcseo_check( 'json: truncated object rejected', AIJson::decode( '{"a": ' ) instanceof WP_Error );
wpcseo_check( 'json: reply with no object rejected', AIJson::decode( 'I cannot do that.' ) instanceof WP_Error );
wpcseo_check( 'json: a bare list is not accepted as an object', AIJson::decode( '["a","b"]' ) instanceof WP_Error );
wpcseo_check( 'json: missing required key reported', AIJson::decode( '{"a":1}', array( 'b' ) ) instanceof WP_Error );
wpcseo_check( 'json: present required key accepted', is_array( AIJson::decode( '{"a":1,"b":2}', array( 'a', 'b' ) ) ) );

// One malformed entry must not discard an otherwise good list.
$mixed = AIJson::rows( array( array( 'k' => 'one' ), 'garbage', array( 'k' => '' ), array( 'k' => 'two' ) ), array( 'k' ), 10 );
wpcseo_check( 'json: malformed and empty rows dropped', array( array( 'k' => 'one' ), array( 'k' => 'two' ) ) === $mixed );
wpcseo_check( 'json: missing fields filled with empty strings', array( 'k' => 'x', 'j' => '' ) === AIJson::rows( array( array( 'k' => 'x' ) ), array( 'k', 'j' ) )[0] );
wpcseo_check( 'json: row limit honoured', 2 === count( AIJson::rows( array( array( 'k' => '1' ), array( 'k' => '2' ), array( 'k' => '3' ) ), array( 'k' ), 2 ) ) );
wpcseo_check( 'json: rows from a non-array yield nothing', array() === AIJson::rows( 'nope', array( 'k' ) ) );

wpcseo_check( 'json: non-scalar and empty strings dropped', array( 'a', 'b' ) === AIJson::strings( array( 'a', array( 'nested' ), '', 'b' ) ) );
wpcseo_check( 'json: string limit honoured', 2 === count( AIJson::strings( array( 'a', 'b', 'c' ), 2 ) ) );

// --- Phase 6b: search intent stays inside the known set -----------------------

wpcseo_check( 'intent: known value accepted', 'commercial' === Meta::sanitize_search_intent( 'commercial' ) );
wpcseo_check( 'intent: invented category rejected', '' === Meta::sanitize_search_intent( 'viral-buzz' ) );
wpcseo_check( 'intent: empty stays empty', '' === Meta::sanitize_search_intent( '' ) );
wpcseo_check( 'intent: the six intents plus unset are offered', 7 === count( Meta::search_intents() ) );
wpcseo_check( 'intent: registered as a meta key', array_key_exists( Meta::SEARCH_INTENT, Meta::keys() ) );

// --- Phase 6b: prompts refuse to ask for data a model cannot have -------------

$keywords = new KeywordPrompt();
$built    = $keywords->build( array( 'title' => 'Roof insulation', 'content' => 'text' ) );
wpcseo_check( 'prompt: keywords forbids volume and difficulty', str_contains( $built->system, 'Never include search volume' ) );
wpcseo_check( 'prompt: keywords constrains the intent vocabulary', str_contains( $built->system, 'informational, navigational, commercial, transactional, local' ) );
wpcseo_check( 'prompt: keywords asks for JSON only', str_contains( $built->system, 'single JSON object' ) );

$analysis = ( new ContentAnalysisPrompt() )->build( array( 'title' => 't' ) );
wpcseo_check( 'prompt: analysis demands a reason per recommendation', str_contains( $analysis->system, '"issue":"","why":"","recommendation":""' ) );
wpcseo_check( 'prompt: analysis forbids inventing sources', str_contains( $analysis->system, 'Do not invent statistics' ) );
wpcseo_check( 'prompt: analysis allows an empty list rather than a made-up gap', str_contains( $analysis->system, 'empty list rather than inventing' ) );

$brief = ( new ContentBriefPrompt() )->build( array( 'topic' => 'Roofs', 'audience' => 'Homeowners' ) );
wpcseo_check( 'prompt: brief asks for kinds of source, not URLs', str_contains( $brief->system, 'rather than naming a specific URL' ) );
wpcseo_check( 'prompt: brief limits schema to types a page can carry', str_contains( $brief->system, 'Article, BlogPosting, NewsArticle, WebPage' ) );
wpcseo_check( 'prompt: brief inputs reach the message', str_contains( $brief->prompt, 'Roofs' ) && str_contains( $brief->prompt, 'Homeowners' ) );
wpcseo_check( 'prompt: brief allows a longer reply than a title', $brief->max_tokens > $keywords->max_tokens() );

// --- Phase 7: keyword cannibalization ----------------------------------------

// Word order must not matter: these target the same thing.
wpcseo_check( 'cannibalization: word order ignored', Cannibalization::signature( 'roof insulation' ) === Cannibalization::signature( 'insulation roof' ) );
wpcseo_check( 'cannibalization: stop words ignored', Cannibalization::signature( 'roof insulation' ) === Cannibalization::signature( 'the best insulation for a roof' ) );
wpcseo_check( 'cannibalization: plurals folded', Cannibalization::signature( 'roof insulation' ) === Cannibalization::signature( 'roofs insulation' ) );
wpcseo_check( 'cannibalization: case ignored', Cannibalization::signature( 'Roof Insulation' ) === Cannibalization::signature( 'roof insulation' ) );
wpcseo_check( 'cannibalization: punctuation ignored', Cannibalization::signature( 'roof-insulation' ) === Cannibalization::signature( 'roof insulation' ) );
wpcseo_check( 'cannibalization: duplicate words collapse', Cannibalization::signature( 'roof roof insulation' ) === Cannibalization::signature( 'roof insulation' ) );

// Genuinely different targets must not be merged.
wpcseo_check( 'cannibalization: different topics stay apart', Cannibalization::signature( 'loft ladder' ) !== Cannibalization::signature( 'roof insulation' ) );
wpcseo_check( 'cannibalization: a superset is not the same target', Cannibalization::signature( 'roof insulation cost' ) !== Cannibalization::signature( 'roof insulation' ) );

// "ss" endings are not plurals.
wpcseo_check( 'cannibalization: glass is not glas', array( 'glass' ) === Cannibalization::tokens( 'glass' ) );
wpcseo_check( 'cannibalization: short words not truncated', array( 'gas' ) === Cannibalization::tokens( 'gas' ) );
wpcseo_check( 'cannibalization: an all-stop-word phrase has no signature', '' === Cannibalization::signature( 'the best of it' ) );
wpcseo_check( 'cannibalization: tokens are sorted', array( 'insulation', 'roof' ) === Cannibalization::tokens( 'roof insulation' ) );
wpcseo_check( 'cannibalization: four remedies offered, not one instruction', 4 === count( Cannibalization::remedies() ) );

// --- Phase 7: freshness ------------------------------------------------------

wpcseo_check( 'decay: months since an unparseable date is zero', 0 === Decay::months_since( 'not a date' ) );
wpcseo_check( 'decay: roughly a year ago reads as about twelve months', 12 === Decay::months_since( gmdate( 'Y-m-d H:i:s', time() - ( 365 * 86400 ) ) ) );
wpcseo_check( 'decay: just now reads as zero months', 0 === Decay::months_since( gmdate( 'Y-m-d H:i:s' ) ) );

// --- Phase 7: findings carry their reasoning ---------------------------------

wpcseo_check( 'audit: four severity levels, worst first', array( 'critical', 'important', 'opportunity', 'good' ) === array_keys( Finding::levels() ) );
wpcseo_check( 'audit: every level explains what it means', 4 === count( array_filter( Finding::level_descriptions() ) ) );

$finding = new Finding( 'x', Finding::IMPORTANT, 'Title', 'Why', 'Action', 3, array( array( 'a' => 1 ) ), 'https://example.test/' );
wpcseo_check( 'audit: a finding carries problem, why and action', 'Title' === $finding->title && 'Why' === $finding->why && 'Action' === $finding->action );
wpcseo_check( 'audit: a finding carries its evidence', 3 === $finding->count && 1 === count( $finding->items ) );

// --- Phase 7b: FAQ schema follows the visible page ----------------------------

$faq_html = '<h2>What does it cost?</h2><p>It starts at fifty pounds.</p><h2>How long does it take?</h2><p>About a week.</p>';

wpcseo_check( 'faq: two heading pairs detected', 2 === count( Faq::detect( $faq_html ) ) );
wpcseo_check( 'faq: the answer is the text under the heading', 'About a week.' === Faq::detect( $faq_html )[1]['answer'] );
wpcseo_check( 'faq: a details block counts', 2 === count( Faq::detect( '<details><summary>Is it safe?</summary><p>Yes.</p></details><details><summary>Why?</summary><p>Because.</p></details>' ) ) );

// A statement heading is not a question, and one pair is not an FAQ.
wpcseo_check( 'faq: headings without a question mark ignored', array() === Faq::detect( '<h2>How we work</h2><p>Carefully.</p><h2>Our prices</h2><p>Fair.</p>' ) );
wpcseo_check( 'faq: a single pair is not an FAQ', ! Faq::qualifies( '<h2>What does it cost?</h2><p>Fifty pounds.</p>' ) );
wpcseo_check( 'faq: a question with no answer is not a pair', array() === Faq::detect( '<h2>What does it cost?</h2><h2>How long?</h2>' ) );
wpcseo_check( 'faq: two pairs qualify', Faq::qualifies( $faq_html ) );
wpcseo_check( 'faq: prose alone never qualifies', ! Faq::qualifies( '<p>We answer questions. Do we? Yes.</p>' ) );

$entities = Faq::entities( new WP_Post( $faq_html ) );
wpcseo_check( 'faq: entities are Question nodes', 'Question' === $entities[0]['@type'] );
wpcseo_check( 'faq: each question carries its accepted answer', 'It starts at fifty pounds.' === $entities[0]['acceptedAnswer']['text'] );
wpcseo_check( 'faq: a page with no visible FAQ produces no entities', array() === Faq::entities( new WP_Post( '<p>Nothing here.</p>' ) ) );

// --- Phase 7b: a suggested link target can never be invented -------------------

$offered = array(
	'candidates' => array(
		array( 'id' => 7, 'title' => 'Roof insulation', 'url' => 'https://example.test/roof-insulation/' ),
	),
	'content'    => 'Insulating a roof is the cheapest way to cut a heating bill.',
);

$reply = array(
	'links' => array(
		array( 'id' => 7, 'anchor' => 'Insulating a roof', 'reason' => 'Explains the method', 'confidence' => '80', 'placement' => 'Intro' ),
		array( 'id' => 999, 'anchor' => 'loft ladders', 'reason' => 'Invented', 'confidence' => '95', 'placement' => 'End' ),
	),
);

$shaped = AIRoutes::shape_links( $reply, $offered );

wpcseo_check( 'links: a target that was not offered is discarded', 1 === count( $shaped['links'] ) && 1 === $shaped['discarded'] );
wpcseo_check( 'links: title and URL come from the site, not the reply', 'https://example.test/roof-insulation/' === $shaped['links'][0]['url'] );
wpcseo_check( 'links: confidence is clamped to a percentage', 80 === $shaped['links'][0]['confidence'] );
wpcseo_check( 'links: an anchor already in the page is marked as such', true === $shaped['links'][0]['in_content'] );

$absent = AIRoutes::shape_links(
	array( 'links' => array( array( 'id' => 7, 'anchor' => 'underfloor heating', 'confidence' => '150' ) ) ),
	$offered
);

wpcseo_check( 'links: an anchor not in the page is flagged for writing', false === $absent['links'][0]['in_content'] );
wpcseo_check( 'links: an over-100 confidence is capped', 100 === $absent['links'][0]['confidence'] );
wpcseo_check( 'links: a suggestion with no anchor is dropped', array() === AIRoutes::shape_links( array( 'links' => array( array( 'id' => 7, 'anchor' => '' ) ) ), $offered )['links'] );

// --- Phase 7b: an FAQ answer is checked against the page ----------------------

$faq_context = array( 'content' => "The service starts at fifty pounds\nand takes about a week." );

$faq_shaped = AIRoutes::shape_faq(
	array(
		'answered'   => array(
			array( 'question' => 'What does it cost?', 'answer' => 'Fifty pounds.', 'source' => 'starts at fifty pounds' ),
			array( 'question' => 'Do you offer a guarantee?', 'answer' => 'Ten years.', 'source' => 'a ten year guarantee' ),
			array( 'question' => 'Empty?', 'answer' => '' ),
		),
		'unanswered' => array( array( 'question' => 'Do you travel?', 'why' => 'Readers outside the area need to know' ) ),
	),
	$faq_context
);

wpcseo_check( 'faq: an answer quoting the page is marked grounded', true === $faq_shaped['answered'][0]['grounded'] );
wpcseo_check( 'faq: an answer quoting nothing on the page is not', false === $faq_shaped['answered'][1]['grounded'] );
wpcseo_check( 'faq: grounding survives a line break in the source', true === AIRoutes::shape_faq( array( 'answered' => array( array( 'question' => 'q', 'answer' => 'a', 'source' => 'fifty pounds and takes' ) ) ), $faq_context )['answered'][0]['grounded'] );
wpcseo_check( 'faq: an answerless entry is dropped', 2 === count( $faq_shaped['answered'] ) );
wpcseo_check( 'faq: unanswered questions are kept unanswered', 1 === count( $faq_shaped['unanswered'] ) && ! array_key_exists( 'answer', $faq_shaped['unanswered'][0] ) );

// --- Phase 7b: the prompts constrain what a model may return ------------------

$links_prompt = ( new InternalLinkPrompt() )->build(
	array(
		'title'      => 'Heating bills',
		'candidates' => array( array( 'id' => 7, 'title' => 'Roof insulation', 'excerpt' => 'How to insulate a roof.' ) ),
	)
);

wpcseo_check( 'prompt: links forbid inventing a destination', str_contains( $links_prompt->system, 'Never invent an id, a title or a URL' ) );
wpcseo_check( 'prompt: links allow an empty answer', str_contains( $links_prompt->system, 'empty list is a valid answer' ) );
wpcseo_check( 'prompt: candidates reach the message with their ids', str_contains( $links_prompt->prompt, 'id 7: Roof insulation' ) );

$faq_prompt = ( new FaqPrompt() )->build( array( 'title' => 'Roofs' ) );
wpcseo_check( 'prompt: faq refuses outside knowledge', str_contains( $faq_prompt->system, 'If the page does not say it, it is unanswered' ) );
wpcseo_check( 'prompt: faq asks where each answer came from', str_contains( $faq_prompt->system, '"source" quotes the phrase' ) );

// --- Phase 8: reading another plugin's template variables ---------------------

$moved = Sources::translate( '%%title%% %%sep%% %%sitename%%' );
wpcseo_check( 'transfer: matching variables pass through', '%%title%% %%sep%% %%sitename%%' === $moved['value'] );

$renamed = Sources::translate( '%%post_title%% %%sep%% %%blogname%%' );
wpcseo_check( 'transfer: differently spelled variables are renamed', '%%title%% %%sep%% %%sitename%%' === $renamed['value'] );

$single = Sources::translate( '%title% %sep% %sitename%', 'single' );
wpcseo_check( 'transfer: single-percent variables understood', '%%title%% %%sep%% %%sitename%%' === $single['value'] );

// A variable with no equivalent must not be published as literal text.
$unknown = Sources::translate( '%%title%% %%primary_category%% %%sep%% %%sitename%%' );
wpcseo_check( 'transfer: an unsupported variable is removed', ! str_contains( $unknown['value'], 'primary_category' ) );
wpcseo_check( 'transfer: and it is reported, not swallowed', array( 'primary_category' ) === $unknown['dropped'] );
wpcseo_check( 'transfer: removing it does not leave a double space', '%%title%% %%sep%% %%sitename%%' === $unknown['value'] );
wpcseo_check( 'transfer: a custom field variable is dropped', array( 'cf_price' ) === Sources::translate( '%%cf_price%%' )['dropped'] );

// Each plugin spells "noindex" its own way.
wpcseo_check( 'transfer: Yoast 1 means noindex', true === Sources::flag( 'yoast', Meta::NOINDEX, '1' ) );
wpcseo_check( 'transfer: Yoast 2 means the opposite, not noindex', false === Sources::flag( 'yoast', Meta::NOINDEX, '2' ) );
wpcseo_check( 'transfer: Rank Math keeps directives in a list', true === Sources::flag( 'rankmath', Meta::NOINDEX, array( 'noindex', 'noimageindex' ) ) );
wpcseo_check( 'transfer: a list without the directive is not set', false === Sources::flag( 'rankmath', Meta::NOFOLLOW, array( 'noindex' ) ) );
wpcseo_check( 'transfer: SEOPress uses yes', true === Sources::flag( 'seopress', Meta::NOINDEX, 'yes' ) );
wpcseo_check( 'transfer: SEOPress empty is not set', false === Sources::flag( 'seopress', Meta::NOINDEX, '' ) );

// A comma separated keyword list becomes one focus keyphrase.
wpcseo_check( 'transfer: the first keyword is taken', 'roof insulation' === Sources::text( 'yoast', Meta::FOCUS_KEYWORD, 'roof insulation, loft, attic' )['value'] );
wpcseo_check( 'transfer: a keyword list is not run through the template engine', 'roof %%x%%' === Sources::text( 'yoast', Meta::FOCUS_KEYWORD, 'roof %%x%%' )['value'] );
wpcseo_check( 'transfer: an empty value stays empty', '' === Sources::text( 'yoast', Meta::TITLE, '' )['value'] );
wpcseo_check( 'transfer: a source with no variables is left alone', '%%title%%' === Sources::text( 'tsf', Meta::TITLE, '%%title%%' )['value'] );

wpcseo_check( 'transfer: every source maps a title and a description', 4 === count( array_filter( Sources::all(), static fn ( array $s ): bool => isset( $s['fields'][ Meta::TITLE ], $s['fields'][ Meta::DESCRIPTION ] ) ) ) );
wpcseo_check( 'transfer: an unknown source is refused', null === Sources::get( 'made-up-plugin' ) );

// --- Phase 8: the spreadsheet format ------------------------------------------

wpcseo_check( 'csv: every editable meta key has a column', count( Csv::columns() ) === count( Meta::keys() ) );
wpcseo_check( 'csv: every column maps to a registered key', array() === array_diff( array_values( Csv::columns() ), array_keys( Meta::keys() ) ) );
wpcseo_check( 'csv: post_id leads the header', 'post_id' === Csv::header()[0] );
wpcseo_check( 'csv: reference columns are not importable fields', array() === array_intersect( Csv::reference_columns(), array_keys( Csv::columns() ) ) );

foreach ( array( '1', 'yes', 'YES', 'true', 'on', ' y ' ) as $affirmative ) {
	wpcseo_check( 'csv: "' . trim( $affirmative ) . '" reads as yes', true === Csv::boolean( $affirmative ) );
}

foreach ( array( '0', 'no', '', 'false', 'off', 'maybe' ) as $negative ) {
	wpcseo_check( 'csv: "' . $negative . '" does not read as yes', false === Csv::boolean( $negative ) );
}

// --- Phase 9: node identifiers carry one fragment -----------------------------

// A URL has one fragment, so "#webpage#breadcrumb" was never a valid identifier.
wpcseo_check( 'schema: a fragment replaces, not appends', 'https://example.test/page/#breadcrumb' === Pieces::fragment( 'https://example.test/page/#webpage', 'breadcrumb' ) );
wpcseo_check( 'schema: an identifier with no fragment gains one', 'https://example.test/page/#faq' === Pieces::fragment( 'https://example.test/page/', 'faq' ) );
wpcseo_check( 'schema: the path is left intact', 'https://example.test/a/b/#x' === Pieces::fragment( 'https://example.test/a/b/#webpage', 'x' ) );

// --- Phase 10: the score model is a decision, not a constant -------------------

$model = Weights::defaults();

wpcseo_check( 'weights: every check has a label and a group', count( $model ) === count( array_filter( $model, static fn ( array $c ): bool => '' !== $c['label'] && isset( Weights::groups()[ $c['group'] ] ) ) ) );
wpcseo_check( 'weights: no check defaults to nothing', array() === array_filter( $model, static fn ( array $c ): bool => $c['weight'] <= 0 ) );
wpcseo_check( 'weights: the model has a setting per check', count( $model ) === count( Weights::section()['fields'] ) );
wpcseo_check( 'weights: settings ids are prefixed', isset( Weights::section()['fields'][ Weights::PREFIX . 'title_length' ] ) );
wpcseo_check( 'weights: zero is offered, so a check can be excluded', isset( Weights::section()['fields'][ Weights::PREFIX . 'title_length' ]['options']['0'] ) );

// The score is not presented as anything a search engine computes.
wpcseo_check( 'weights: the screen says whose score this is not', str_contains( Weights::section()['description'], 'not Google' ) );

wpcseo_check( 'weights: an unset weight falls back to the default', 3.0 === Weights::get( 'title_length' ) );
wpcseo_check( 'weights: a check outside the model is still worth something', 1.0 === Weights::get( 'invented_by_a_third_party' ) );
wpcseo_check( 'weights: a default weight counts', Weights::counts( 'title_length' ) );

$GLOBALS['wpcseo_test_options']['wpcseo_settings'] = array( Weights::PREFIX . 'title_length' => '0' );
Settings::flush();

wpcseo_check( 'weights: a stored weight wins', 0.0 === Weights::get( 'title_length' ) );
wpcseo_check( 'weights: a zeroed check does not count', ! Weights::counts( 'title_length' ) );

$zeroed = Analyzer::analyze( array( 'title' => 'x' ) );
$title_check = wpcseo_find_check( $zeroed, 'title_length' );

// Excluded from the score, but still telling the editor what it found.
wpcseo_check( 'weights: a zeroed check still reports', null !== $title_check && '' !== $title_check['issue'] );
wpcseo_check( 'weights: and is marked as not counted', null !== $title_check && false === $title_check['counts'] );

$GLOBALS['wpcseo_test_options']['wpcseo_settings'] = array( Weights::PREFIX . 'title_length' => '5' );
Settings::flush();

$raised = wpcseo_find_check( Analyzer::analyze( array( 'title' => 'x' ) ), 'title_length' );
wpcseo_check( 'weights: a raised weight reaches the check', null !== $raised && 5.0 === $raised['weight'] );

unset( $GLOBALS['wpcseo_test_options']['wpcseo_settings'] );
Settings::flush();

// --- Phase 11: Search Console credentials and reporting -----------------------

wpcseo_check( 'gsc: nothing is connected to begin with', ! Account::is_connected() );

// The key file must be a service account key, and a readable one.
wpcseo_check( 'gsc: prose is not a key file', Account::save( 'hello' ) instanceof WP_Error );
wpcseo_check( 'gsc: an OAuth client file is refused', Account::save( '{"installed":{"client_id":"x"}}' ) instanceof WP_Error );
wpcseo_check( 'gsc: a key without an address is refused', Account::save( '{"type":"service_account","private_key":"-----BEGIN PRIVATE KEY-----"}' ) instanceof WP_Error );
wpcseo_check( 'gsc: a key whose PEM was mangled is refused', Account::save( '{"type":"service_account","client_email":"a@b.test","private_key":"oops"}' ) instanceof WP_Error );
wpcseo_check( 'gsc: a refused key leaves nothing connected', ! Account::is_connected() );

$fake_key = wp_json_encode(
	array(
		'type'         => 'service_account',
		'client_email' => 'reader@example-project.iam.gserviceaccount.com',
		'private_key'  => "-----BEGIN PRIVATE KEY-----\nnot-a-real-key\n-----END PRIVATE KEY-----\n",
	)
);

// A private key is only accepted if it can be encrypted at rest. WordPress
// always provides sodium — the extension or its bundled polyfill — but this
// harness loads no WordPress, so both outcomes are worth pinning.
if ( \WPCustomSeo\AI\Credentials::can_encrypt() ) {
	wpcseo_check( 'gsc: a well-formed key file is accepted', true === Account::save( $fake_key ) );
	wpcseo_check( 'gsc: and reports the address to authorise', 'reader@example-project.iam.gserviceaccount.com' === Account::email() );

	$stored = (string) wp_json_encode( $GLOBALS['wpcseo_test_options'][ \WPCustomSeo\AI\Credentials::OPTION ] ?? '' );
	wpcseo_check( 'gsc: the key is not stored in the clear', ! str_contains( $stored, 'reader@example-project' ) );

	wpcseo_check( 'gsc: an empty string disconnects', true === Account::save( '' ) );
	wpcseo_check( 'gsc: and it is gone', ! Account::is_connected() && '' === Account::email() );
} else {
	$refused = Account::save( $fake_key );

	wpcseo_check( 'gsc: without encryption the key is refused, not stored in the clear', $refused instanceof WP_Error );
	wpcseo_check( 'gsc: and the refusal says why', $refused instanceof WP_Error && str_contains( $refused->get_error_message(), 'encrypt' ) );
	wpcseo_check( 'gsc: nothing is connected after a refusal', ! Account::is_connected() );
}

// JWT segments are base64url: no padding, and none of base64's unsafe bytes.
wpcseo_check( 'gsc: base64url drops padding', 'YQ' === Token::base64url( 'a' ) );
wpcseo_check( 'gsc: base64url has no plus or slash', '' === trim( str_replace( array( 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '-', '_' ), '', Token::base64url( random_bytes( 128 ) ) ) ) );

// The reporting window stops short of today: Search Console lags by a few days,
// and a range ending today reads as a collapse in traffic that never happened.
$gsc_range = Performance::range( 28 );
wpcseo_check( 'gsc: the range ends before today', strtotime( $gsc_range['end'] ) < strtotime( gmdate( 'Y-m-d' ) ) );
wpcseo_check( 'gsc: the range covers the days asked for', 28 === (int) round( ( strtotime( $gsc_range['end'] ) - strtotime( $gsc_range['start'] ) ) / DAY_IN_SECONDS ) );
wpcseo_check( 'gsc: three periods are offered', array( 7, 28, 90 ) === Performance::PERIODS );

// Without a connection there are no figures — not zeroes, not placeholders.
wpcseo_check( 'gsc: a report without a connection is an error', Performance::report() instanceof WP_Error );

// --- Phase 13: saving one setting must not clear the others -------------------

// sanitize() reads a missing checkbox as unchecked, so an update that merged
// over the raw stored option would switch off every default-on setting that had
// never been explicitly saved — one screen storing one value would disable SEO
// output site-wide.
unset( $GLOBALS['wpcseo_test_options']['wpcseo_settings'] );
Settings::flush();

wpcseo_check( 'settings: SEO output defaults on', Settings::enabled( 'enable_seo' ) );

Settings::update( array( 'report_recipients' => 'someone@example.test' ) );
Settings::flush();

wpcseo_check( 'settings: saving one value leaves default-on checkboxes on', Settings::enabled( 'enable_seo' ) );
wpcseo_check( 'settings: and every other one too', Settings::enabled( 'enable_analysis' ) && Settings::enabled( 'enable_schema' ) && Settings::enabled( 'enable_sitemap' ) );
wpcseo_check( 'settings: while the saved value is stored', 'someone@example.test' === Settings::get( 'report_recipients' ) );

// Turning something off must still work.
Settings::update( array( 'enable_seo' => false ) );
Settings::flush();

wpcseo_check( 'settings: an explicit false is honoured', ! Settings::enabled( 'enable_seo' ) );

Settings::update( array( 'enable_seo' => true ) );
Settings::flush();

wpcseo_check( 'settings: and an explicit true turns it back on', Settings::enabled( 'enable_seo' ) );

unset( $GLOBALS['wpcseo_test_options']['wpcseo_settings'] );
Settings::flush();

// --- Phase 13: the report says only what it knows ------------------------------

$quiet_report = array(
	'audit'     => array(
		'totals'   => array( 'critical' => 0, 'important' => 0, 'opportunity' => 0, 'good' => 6 ),
		'findings' => array(),
	),
	'search'    => null,
	'not_found' => 0,
);

wpcseo_check( 'report: a site with nothing to say sends nothing', ! Report::is_worth_sending( $quiet_report ) );

$noisy_report          = $quiet_report;
$noisy_report['audit']['totals']['important'] = 2;
wpcseo_check( 'report: findings make it worth sending', Report::is_worth_sending( $noisy_report ) );

$found_report              = $quiet_report;
$found_report['not_found'] = 5;
wpcseo_check( 'report: logged 404s make it worth sending', Report::is_worth_sending( $found_report ) );

wpcseo_check( 'report: one critical issue reads singular', str_contains( Mailer::subject( array( 'site' => 'Example', 'audit' => array( 'totals' => array( 'critical' => 1 ) ) ) ), '1 critical SEO issue on Example' ) );
wpcseo_check( 'report: three read plural', str_contains( Mailer::subject( array( 'site' => 'Example', 'audit' => array( 'totals' => array( 'critical' => 3 ) ) ) ), '3 critical SEO issues' ) );
wpcseo_check( 'report: important issues lead the subject when nothing is critical', str_contains( Mailer::subject( array( 'site' => 'Example', 'audit' => array( 'totals' => array( 'important' => 2 ) ) ) ), '2 SEO issues worth looking at' ) );
wpcseo_check( 'report: a clean site gets a plain subject', 'SEO report for Example' === Mailer::subject( array( 'site' => 'Example', 'audit' => array( 'totals' => array() ) ) ) );

wpcseo_check( 'report: two frequencies are offered', array( 'weekly', 'monthly' ) === array_keys( Schedule::frequencies() ) );
wpcseo_check( 'report: a monthly interval is added, longer than core offers', 30 * DAY_IN_SECONDS === Schedule::add_interval( array() )['wpcseo_monthly']['interval'] );

// --- Phase 18: a tabbed save must not clear the tabs it did not show -----------

// options.php posts only the section on screen. An unchecked box and a field
// that was never rendered look identical in the POST, so the form declares
// which sections it covered — without that, saving one tab would switch off
// every checkbox on all the others.
unset( $GLOBALS['wpcseo_test_options']['wpcseo_settings'] );
Settings::flush();

wpcseo_check( 'tabs: fields_in is scoped to the section asked for', in_array( 'enable_seo', Settings::fields_in( array( 'general' ) ), true ) && ! in_array( 'enable_sitemap', Settings::fields_in( array( 'general' ) ), true ) );
wpcseo_check( 'tabs: an unknown section has no fields', array() === Settings::fields_in( array( 'not-a-section' ) ) );

// The screen has the Settings API's registered id, not the schema key. Both
// must resolve, or a tabbed save would find no fields in scope and quietly
// save nothing at all.
wpcseo_check( 'tabs: the registered section id resolves too', Settings::fields_in( array( 'general' ) ) === Settings::fields_in( array( Settings::SECTION_PREFIX . 'general' ) ) );

$prefixed = Settings::sanitize(
	array(
		'wpcseo_sections' => array( Settings::SECTION_PREFIX . 'general' ),
		'enable_seo'      => '1',
	)
);

wpcseo_check( 'tabs: a save using the registered id takes effect', ! empty( $prefixed['enable_seo'] ) && empty( $prefixed['enable_analysis'] ) );
wpcseo_check( 'tabs: and still leaves other sections alone', ! empty( $prefixed['enable_sitemap'] ) );

$tabbed = Settings::sanitize(
	array(
		'wpcseo_sections' => array( 'general' ),
		'enable_seo'      => '1',
	)
);

wpcseo_check( 'tabs: a tick on the saved tab is kept', ! empty( $tabbed['enable_seo'] ) );
wpcseo_check( 'tabs: an unticked box on the saved tab is cleared', empty( $tabbed['enable_analysis'] ) );
wpcseo_check( 'tabs: a default-on checkbox elsewhere survives', ! empty( $tabbed['enable_sitemap'] ) );
wpcseo_check( 'tabs: and so does another one', ! empty( $tabbed['social_open_graph'] ) );
wpcseo_check( 'tabs: the marker itself is not stored', ! array_key_exists( 'wpcseo_sections', $tabbed ) );

// A save with no marker — a programmatic update — still covers everything.
$whole = Settings::sanitize( array_merge( Settings::all(), array( 'enable_seo' => false ) ) );
wpcseo_check( 'tabs: a full save still clears what it is told to', empty( $whole['enable_seo'] ) );
wpcseo_check( 'tabs: without disturbing the rest', ! empty( $whole['enable_sitemap'] ) );

unset( $GLOBALS['wpcseo_test_options']['wpcseo_settings'] );
Settings::flush();

// --- Phase 17: AI crawler controls ---------------------------------------------

$crawlers = \WPCustomSeo\Crawlers\AiCrawlers::bots();
$agents   = array_column( $crawlers, 'agent' );

// Tokens verified against each operator's own published documentation.
foreach ( array( 'GPTBot', 'OAI-SearchBot', 'ClaudeBot', 'Claude-SearchBot', 'Google-Extended', 'PerplexityBot' ) as $agent ) {
	wpcseo_check( 'crawlers: ' . $agent . ' is offered', in_array( $agent, $agents, true ) );
}

// OpenAI documents ChatGPT-User as not governed by robots.txt, so a toggle for
// it would be a switch that quietly does nothing.
wpcseo_check( 'crawlers: a token robots.txt does not govern is not offered', ! in_array( 'ChatGPT-User', $agents, true ) );

// Asking not to be trained on and vanishing from an assistant's citations are
// different decisions, and must never be one control.
$by_purpose = array();

foreach ( $crawlers as $bot ) {
	$by_purpose[ $bot['purpose'] ][] = $bot['agent'];
}

wpcseo_check( 'crawlers: training and search are separate groups', isset( $by_purpose['training'], $by_purpose['search'] ) && array() === array_intersect( $by_purpose['training'], $by_purpose['search'] ) );
wpcseo_check( 'crawlers: GPTBot is training, OAI-SearchBot is search', in_array( 'GPTBot', $by_purpose['training'], true ) && in_array( 'OAI-SearchBot', $by_purpose['search'], true ) );
wpcseo_check( 'crawlers: blocking a search crawler is described as costing visibility', str_contains( \WPCustomSeo\Crawlers\AiCrawlers::purposes()['search']['consequence'], 'noindex' ) );

// Nothing is blocked unless asked for.
unset( $GLOBALS['wpcseo_test_options']['wpcseo_settings'] );
Settings::flush();

wpcseo_check( 'crawlers: nothing is blocked by default', array() === \WPCustomSeo\Crawlers\AiCrawlers::blocked() );
wpcseo_check( 'crawlers: so robots.txt gains nothing', '' === \WPCustomSeo\Crawlers\AiCrawlers::rules() );

$GLOBALS['wpcseo_test_options']['wpcseo_settings'] = array( \WPCustomSeo\Crawlers\AiCrawlers::PREFIX . 'gptbot' => true );
Settings::flush();

$crawler_rules = \WPCustomSeo\Crawlers\AiCrawlers::rules();

wpcseo_check( 'crawlers: a chosen crawler is written', str_contains( $crawler_rules, "User-agent: GPTBot\nDisallow: /" ) );
wpcseo_check( 'crawlers: and only that one', 1 === substr_count( $crawler_rules, 'User-agent:' ) );
wpcseo_check( 'crawlers: the block is attributed', str_contains( $crawler_rules, 'WP Custom SEO' ) );

unset( $GLOBALS['wpcseo_test_options']['wpcseo_settings'] );
Settings::flush();

// --- Phase 16: nothing is left scheduled behind --------------------------------

// The list was literal strings once, and it had already drifted: the report
// event added later was missing, so it would have stayed scheduled forever
// after the plugin was switched off. Built from the modules' constants now.
$cron_hooks = \WPCustomSeo\Core\Activator::cron_hooks();

wpcseo_check( 'lifecycle: the report event is cleared', in_array( \WPCustomSeo\Reports\Schedule::HOOK, $cron_hooks, true ) );
wpcseo_check( 'lifecycle: the 404 pruner is cleared', in_array( \WPCustomSeo\Redirects\NotFound::CRON_HOOK, $cron_hooks, true ) );
wpcseo_check( 'lifecycle: the link rebuild is cleared', in_array( \WPCustomSeo\Links\Scanner::REBUILD_HOOK, $cron_hooks, true ) );
wpcseo_check( 'lifecycle: every entry is one of ours', count( $cron_hooks ) === count( array_filter( $cron_hooks, static fn ( string $h ): bool => str_starts_with( $h, 'wpcseo_' ) ) ) );
wpcseo_check( 'lifecycle: no duplicates', count( $cron_hooks ) === count( array_unique( $cron_hooks ) ) );

// --- Phase 15: the Analytics property id --------------------------------------

// A measurement id is what people have to hand, and stripping its non-digits
// would produce "123" — a number that looks like a property id, is not one, and
// would fail later as a confusing 404.
wpcseo_check( 'ga4: a measurement id is refused', '' === AnalyticsClient::normalize_property( 'G-ABC123' ) );
wpcseo_check( 'ga4: a numeric id is kept', '123456789' === AnalyticsClient::normalize_property( '123456789' ) );
wpcseo_check( 'ga4: surrounding whitespace ignored', '123456789' === AnalyticsClient::normalize_property( '  123456789  ' ) );
wpcseo_check( 'ga4: a pasted resource name is accepted', '987654321' === AnalyticsClient::normalize_property( 'properties/987654321' ) );
wpcseo_check( 'ga4: prose is refused', '' === AnalyticsClient::normalize_property( 'my property' ) );
wpcseo_check( 'ga4: an empty value stays empty', '' === AnalyticsClient::normalize_property( '' ) );

// The two Google APIs are asked for separately, so a project with only one of
// them enabled does not break the other.
wpcseo_check( 'ga4: the scopes are distinct', Token::SCOPE_ANALYTICS !== Token::SCOPE_SEARCH_CONSOLE );
wpcseo_check( 'ga4: the analytics scope is read-only', str_ends_with( Token::SCOPE_ANALYTICS, '.readonly' ) );
wpcseo_check( 'ga4: the search console scope is read-only', str_ends_with( Token::SCOPE_SEARCH_CONSOLE, '.readonly' ) );

// --- Phase 14: what is offered to an AI agent ---------------------------------

$abilities = \WPCustomSeo\Abilities\Abilities::definitions();

wpcseo_check( 'abilities: six are defined', 6 === count( $abilities ) );

foreach ( $abilities as $ability_name => $ability ) {
	wpcseo_check( 'abilities: ' . $ability_name . ' declares a category', \WPCustomSeo\Abilities\Abilities::PREFIX === 'wp-custom-seo/' && 'wp-custom-seo' === $ability['category'] );
	wpcseo_check( 'abilities: ' . $ability_name . ' explains itself', '' !== $ability['label'] && '' !== $ability['description'] );
	wpcseo_check( 'abilities: ' . $ability_name . ' is permission checked', is_callable( $ability['permission_callback'] ) );
	wpcseo_check( 'abilities: ' . $ability_name . ' is executable', is_callable( $ability['execute_callback'] ) );
}

// Exactly one ability writes, and it is the one that says so.
$writers = array_filter( $abilities, static fn ( array $a ): bool => empty( $a['meta']['annotations']['readonly'] ) );
wpcseo_check( 'abilities: only one of them writes', array( 'update-seo-meta' ) === array_keys( $writers ) );
wpcseo_check( 'abilities: and it is marked non-destructive', false === $writers['update-seo-meta']['meta']['annotations']['destructive'] );

// Nothing here spends money: an agent calling an ability must not be able to
// run up an AI bill, so no ability routes to a model.
wpcseo_check( 'abilities: none of them call a language model', 0 === count( array_filter( $abilities, static fn ( array $a ): bool => str_contains( (string) wp_json_encode( $a['execute_callback'] ), 'AI' ) ) ) );

// --- Phase 12: the focus keyphrase against the queries actually reported ------

$gsc_mentions = new ReflectionMethod( \WPCustomSeo\API\Routes::class, 'mentions' );
$gsc_mentions->setAccessible( true );

$gsc_rows = array(
	array( 'key' => 'roof insulation cost' ),
	array( 'key' => 'loft ladders' ),
);

wpcseo_check( 'performance: a keyphrase inside a longer query counts', true === $gsc_mentions->invoke( null, $gsc_rows, 'roof insulation' ) );
wpcseo_check( 'performance: matching ignores case', true === $gsc_mentions->invoke( null, $gsc_rows, 'Roof Insulation' ) );
wpcseo_check( 'performance: an unrelated keyphrase is not claimed as found', false === $gsc_mentions->invoke( null, $gsc_rows, 'underfloor heating' ) );
wpcseo_check( 'performance: no reported queries means no match', false === $gsc_mentions->invoke( null, array(), 'roof' ) );

// --- Advanced robots directives -----------------------------------------------

use WPCustomSeo\SEO\Robots;

// noindex and index in one tag is a contradiction; the overruled one has to go.
$robots_out = Robots::apply(
	array(
		'index'  => true,
		'follow' => true,
	),
	array( 'noindex' => true )
);

wpcseo_check( 'robots: noindex removes index', ! isset( $robots_out['index'] ) && true === $robots_out['noindex'] );
wpcseo_check( 'robots: follow is left alone when only noindex is set', true === $robots_out['follow'] );

$robots_out = Robots::apply( array(), array( 'nofollow' => true ) );
wpcseo_check( 'robots: nofollow is emitted', true === $robots_out['nofollow'] );

$robots_out = Robots::apply(
	array(),
	array(
		'max_snippet'       => '50',
		'max_image_preview' => 'large',
		'max_video_preview' => '15',
	)
);

wpcseo_check( 'robots: max-snippet uses the tag spelling', '50' === $robots_out['max-snippet'] );
wpcseo_check( 'robots: max-image-preview is emitted', 'large' === $robots_out['max-image-preview'] );
wpcseo_check( 'robots: max-video-preview is emitted', '15' === $robots_out['max-video-preview'] );

// A value not in the offered list is a value nothing honours, so it is dropped
// rather than written into the tag.
$robots_out = Robots::apply( array(), array( 'max_image_preview' => 'enormous' ) );
wpcseo_check( 'robots: an unknown preview size is discarded', ! isset( $robots_out['max-image-preview'] ) );

$robots_out = Robots::apply( array(), array( 'max_snippet' => '' ) );
wpcseo_check( 'robots: an empty value says nothing', ! isset( $robots_out['max-snippet'] ) );

// nosnippet already forbids the snippet; a length beside it is a second answer.
$robots_out = Robots::apply(
	array(),
	array(
		'nosnippet'   => true,
		'max_snippet' => '50',
	)
);

wpcseo_check( 'robots: nosnippet drops a redundant max-snippet', true === $robots_out['nosnippet'] && ! isset( $robots_out['max-snippet'] ) );

wpcseo_check( 'robots: sanitize keeps an offered value', '160' === Robots::sanitize( 'max_snippet', '160' ) );
wpcseo_check( 'robots: sanitize rejects anything else', '' === Robots::sanitize( 'max_snippet', '999' ) );
wpcseo_check( 'robots: sanitize rejects an unknown directive', '' === Robots::sanitize( 'max_teapot', 'large' ) );

// --- robots.txt: the rule set that costs a site everything --------------------

use WPCustomSeo\Crawlers\RobotsTxt;

wpcseo_check(
	'robots.txt: a wildcard disallow of the root is caught',
	RobotsTxt::blocks_entire_site( "User-agent: *\nDisallow: /" )
);

wpcseo_check(
	'robots.txt: the check ignores case and spacing',
	RobotsTxt::blocks_entire_site( "user-agent:*\ndisallow:   /  " )
);

// Blocking one named crawler entirely is a normal thing to write.
wpcseo_check(
	'robots.txt: a named crawler blocked in full is not flagged',
	! RobotsTxt::blocks_entire_site( "User-agent: BadBot\nDisallow: /" )
);

wpcseo_check(
	'robots.txt: disallowing a directory is not flagged',
	! RobotsTxt::blocks_entire_site( "User-agent: *\nDisallow: /private/" )
);

// The wildcard group ends where the next user agent begins.
wpcseo_check(
	'robots.txt: a later group does not inherit the wildcard',
	! RobotsTxt::blocks_entire_site( "User-agent: *\nDisallow: /private/\nUser-agent: BadBot\nDisallow: /" )
);

wpcseo_check(
	'robots.txt: a commented-out rule is not a rule',
	! RobotsTxt::blocks_entire_site( "User-agent: *\n# Disallow: /" )
);

wpcseo_check( 'robots.txt: sanitize normalises line endings', "a\nb" === RobotsTxt::sanitize( "a\r\nb\r\n" ) );
wpcseo_check( 'robots.txt: sanitize keeps the newlines the format needs', 2 === substr_count( RobotsTxt::sanitize( "a\nb\nc" ), "\n" ) );

// --- AI answer readiness ------------------------------------------------------

use WPCustomSeo\GEO\Readiness;

wpcseo_check( 'geo: six dimensions are reported', 6 === count( Readiness::dimensions() ) );

$geo_empty = Readiness::analyze( array() );

wpcseo_check( 'geo: an empty page scores something', is_int( $geo_empty['score'] ) && $geo_empty['score'] >= 0 );
wpcseo_check( 'geo: every dimension is returned even for an empty page', 6 === count( $geo_empty['dimensions'] ) );

// Every dimension has to explain itself: what it measured, why it matters, and
// what to do. A bare number is not actionable.
$geo_explained = true;

foreach ( $geo_empty['dimensions'] as $geo_dimension ) {
	if ( '' === $geo_dimension['measured'] || '' === $geo_dimension['why'] || ! isset( $geo_dimension['fixes'] ) ) {
		$geo_explained = false;
	}
}

wpcseo_check( 'geo: every dimension says what it measured and why', $geo_explained );

$geo_thin = Readiness::analyze(
	array(
		'title'   => 'Heat pumps',
		'content' => '<p>It depends.</p>',
	)
);

$geo_strong = Readiness::analyze(
	array(
		'title'        => 'Heat pump running costs',
		'description'  => 'What a heat pump costs to run, measured over a year.',
		'content'      => '<h1>Heat pump running costs</h1>'
			. '<p>A heat pump is a device that moves heat rather than generating it. Heat pump running costs depend on the price of electricity and the efficiency of the unit.</p>'
			. '<h2>What does a heat pump cost to run?</h2><p>We measured 2400 kWh over 12 months, which came to 38% less than the gas boiler it replaced.</p>'
			. '<h2>How does that compare to gas?</h2><p>In our testing the difference held at roughly 30 percent across the year.</p>'
			. '<h2>Why does efficiency vary?</h2><p>We found flow temperature to be the largest single factor, worth about 15% either way.</p>'
			. '<h2>When is it not worth it?</h2><p>We compared four poorly insulated houses and none of them paid back inside 20 years.</p>'
			. '<h2>Where do the figures come from?</h2><p>Every number here comes from meters we read ourselves over 24 months.</p>'
			. '<table><tr><td>Gas</td><td>Heat pump</td></tr></table>'
			. '<ul><li>Flow temperature</li><li>Insulation</li></ul>'
			. '<img src="a.jpg" alt="Meter readings">'
			. '<p>Sources: <a href="https://example.org/a">one</a>, <a href="https://example.org/b">two</a>, <a href="https://example.org/c">three</a>.</p>',
		'author_bio'   => 'Installs heat pumps for a living.',
		'author_links' => 2,
		'has_org'      => true,
		'has_dates'    => true,
		'schema_type'  => 'BlogPosting',
	)
);

wpcseo_check( 'geo: a well-structured page outscores a thin one', $geo_strong['score'] > $geo_thin['score'] );
wpcseo_check( 'geo: the score stays within 0-100', $geo_strong['score'] <= 100 && $geo_thin['score'] >= 0 );

// A page with nothing wrong in a dimension offers no fixes for it; a page with
// problems does. Advice that appears either way is advice nobody reads.
$geo_structure_strong = null;
$geo_structure_thin   = null;

foreach ( $geo_strong['dimensions'] as $geo_dimension ) {
	if ( 'structure' === $geo_dimension['id'] ) {
		$geo_structure_strong = $geo_dimension;
	}
}

foreach ( $geo_thin['dimensions'] as $geo_dimension ) {
	if ( 'structure' === $geo_dimension['id'] ) {
		$geo_structure_thin = $geo_dimension;
	}
}

wpcseo_check( 'geo: a structured page scores well on structure', $geo_structure_strong['score'] >= 80 );
wpcseo_check( 'geo: a thin page gets structural advice', ! empty( $geo_structure_thin['fixes'] ) );

// --- Image SEO ----------------------------------------------------------------

use WPCustomSeo\Media\ImageSeo;

foreach ( array( 'img_4021', 'DSC00417', '20240817-113256', 'screenshot-2024-08-17', 'untitled', '1234', 'a-b' ) as $image_name ) {
	wpcseo_check( 'images: "' . $image_name . '" reads as opaque', ImageSeo::is_opaque_filename( $image_name ) );
}

foreach ( array( 'heat-pump-outdoor-unit', 'roof-insulation-detail', 'meter-readings-january' ) as $image_name ) {
	wpcseo_check( 'images: "' . $image_name . '" reads as descriptive', ! ImageSeo::is_opaque_filename( $image_name ) );
}

wpcseo_check( 'images: an empty filename is opaque', ImageSeo::is_opaque_filename( '' ) );

// --- Video structured data ----------------------------------------------------

use WPCustomSeo\Schema\Video;

wpcseo_check( 'video: an iframe embed is detected', Video::has_embed( '<iframe src="https://www.youtube.com/embed/abc"></iframe>' ) );
wpcseo_check( 'video: a native video element is detected', Video::has_embed( '<video src="a.mp4"></video>' ) );
wpcseo_check( 'video: a bare oEmbed URL is detected', Video::has_embed( "Some text\nhttps://youtu.be/abc123\nMore text" ) );
wpcseo_check( 'video: a block comment embed is detected', Video::has_embed( '<!-- wp:video {"id":4} -->' ) );

// A page merely mentioning a video is not a page carrying one.
wpcseo_check( 'video: prose about video is not an embed', ! Video::has_embed( '<p>We made a video about heat pumps.</p>' ) );
wpcseo_check( 'video: an unrelated iframe is not an embed', ! Video::has_embed( '<iframe src="https://example.com/map"></iframe>' ) );

wpcseo_check( 'video: a valid ISO 8601 duration is kept', 'PT4M13S' === Video::sanitize_duration( 'pt4m13s' ) );
wpcseo_check( 'video: a plain number is not a duration', '' === Video::sanitize_duration( '253' ) );
wpcseo_check( 'video: an empty duration stays empty', '' === Video::sanitize_duration( '' ) );
wpcseo_check( 'video: PT alone is rejected', '' === Video::sanitize_duration( 'PT' ) );

wpcseo_check( 'video: a plain date is kept', '2024-08-17' === Video::sanitize_date( '2024-08-17' ) );
wpcseo_check( 'video: a full timestamp is kept', '2024-08-17T11:32:00Z' === Video::sanitize_date( '2024-08-17T11:32:00Z' ) );
wpcseo_check( 'video: a non-ISO date is discarded', '' === Video::sanitize_date( '17/08/2024' ) );
wpcseo_check( 'video: an impossible date is discarded', '' === Video::sanitize_date( '2024-13-45' ) );

// --- Settings contributed through the filter ----------------------------------

// Settings::schema() caches itself on first call, so a module registering its
// fields after something has already read a setting adds them to a schema
// nobody builds again — the fields vanish with no error anywhere. Plugin::boot()
// registers these three before the first reader for that reason. This asserts
// the fields actually arrive, which is the symptom that ordering bug produces.
Settings::flush();

\WPCustomSeo\SEO\Hreflang::init();
RobotsTxt::init();
Video::init();

$contributed = Settings::fields();

wpcseo_check( 'settings: the hreflang toggle is registered', isset( $contributed[ \WPCustomSeo\SEO\Hreflang::SETTING ] ) );
wpcseo_check( 'settings: the robots.txt rules field is registered', isset( $contributed[ RobotsTxt::SETTING ] ) );
wpcseo_check( 'settings: the sitemap declaration field is registered', isset( $contributed[ RobotsTxt::SETTING_SITEMAP ] ) );
wpcseo_check( 'settings: the video schema toggle is registered', isset( $contributed[ Video::SETTING ] ) );

// The robots.txt rules are stored, not typed into the generic settings form —
// the dedicated screen owns them, so they must not appear twice.
wpcseo_check( 'settings: the robots.txt rules field is hidden from the settings form', ! empty( $contributed[ RobotsTxt::SETTING ]['hidden'] ) );

// Every contributed field has to survive sanitization, or saving any tab would
// quietly reset it.
$contributed_clean = Settings::sanitize( array( RobotsTxt::SETTING => "User-agent: *\nDisallow: /private/" ) );

wpcseo_check( 'settings: contributed fields survive sanitization', array_key_exists( RobotsTxt::SETTING, $contributed_clean ) );
wpcseo_check( 'settings: multi-line rules keep their newlines', str_contains( (string) $contributed_clean[ RobotsTxt::SETTING ], "\n" ) );

Settings::flush();

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll checks passed\n";
exit( $failures ? 1 : 0 );
