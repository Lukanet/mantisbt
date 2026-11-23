<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Mantis Webservice Tests
 *
 * @package Tests
 * @copyright Copyright MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link https://mantisbt.org
 */

namespace Mantis\tests\rest;

/**
 * Regression tests for the full-text search of GET /issues combined with other
 * criteria, with a fake full-text provider (no search daemon needed).
 *
 * A full-text engine returns a capped number of best matches. The other criteria
 * of the request must be applied before that cap - by passing the issues matching
 * them to the provider as 'candidate_ids' - otherwise issues matching everything
 * are crowded out by better matches that the other criteria then drop.
 *
 * These tests build the inline filter in-process (the provider can't be swapped on
 * the server), the issues are created through the REST API.
 *
 * @group REST
 */
class RestIssueFulltextCandidatesTest extends RestBase {
	/** @var array Issue ids the fake engine matches, best match first. */
	public static $matches = array();

	/** @var integer The fake engine's match cap. */
	public static $cap = 1000;

	/** @var array The options the fake provider was called with. */
	public static $calls = array();

	/**
	 * Fake full-text provider: the best matches among the candidates (or among all
	 * its matches when no candidates are given), capped.
	 *
	 * @param string $p_search  The search string.
	 * @param array  $p_options Provider options ('candidate_ids').
	 *
	 * @return array
	 */
	public static function provider( $p_search, array $p_options = array() ) {
		self::$calls[] = $p_options;
		$t_matches = self::$matches;
		if( isset( $p_options['candidate_ids'] ) ) {
			$t_matches = array_values( array_intersect( $t_matches, $p_options['candidate_ids'] ) );
		}
		return array(
			'ids' => array_slice( $t_matches, 0, self::$cap ),
			'engine' => 'fake',
			'total_found' => count( $t_matches ),
			'truncated' => count( $t_matches ) > self::$cap,
		);
	}

	/**
	 * Load the REST issue functions in-process and use the fake provider.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if( !function_exists( 'rest_issue_build_inline_filter' ) ) {
			$g_app = new class {
				/** @return void */
				public function group() {}
			};
			require_once dirname( __DIR__, 2 ) . '/api/soap/mc_core.php';
			require_once dirname( __DIR__, 2 ) . '/api/rest/restcore/issues_rest.php';
		}

		global $g_rest_issue_search_provider, $g_rest_issue_search_indexes_private_notes, $g_cache_config_eval;
		$g_rest_issue_search_provider = __CLASS__ . '::provider';
		$g_rest_issue_search_indexes_private_notes = OFF;
		$g_cache_config_eval = array();

		current_user_set( $this->userId );
		self::$calls = array();
		self::$cap = 1000;
	}

	/**
	 * Builds the inline filter for a query string.
	 *
	 * @param string $p_query_string The query string.
	 *
	 * @return array Array with 'filter', 'extra' and 'warnings'.
	 */
	private function build( $p_query_string ) {
		$t_request = \Slim\Http\Request::createFromEnvironment(
			\Slim\Http\Environment::mock( array( 'QUERY_STRING' => $p_query_string, 'CONTENT_TYPE' => 'text/plain' ) ) );
		$t_warnings = array();
		$t_extra = array();
		$t_filter = rest_issue_build_inline_filter( $t_request, array( $this->getProjectId() ), $t_warnings, $t_extra );

		return array( 'filter' => $t_filter, 'extra' => $t_extra, 'warnings' => $t_warnings );
	}

	/**
	 * The ids a built filter matches.
	 *
	 * @param array $p_built Result of build().
	 *
	 * @return array
	 */
	private function matchedIds( array $p_built ) {
		$t_query = new \BugFilterQuery( $p_built['filter'], array_merge( $p_built['extra']['options'], array(
			'query_type' => \BugFilterQuery::QUERY_TYPE_DISTINCT_IDS,
			'project_id' => $this->getProjectId(),
		) ) );
		return array_map( 'intval', array_column( $t_query->fetch_all(), 'id' ) );
	}

	/**
	 * Creates an issue through the REST API, deleted after the test.
	 *
	 * @return integer The issue id.
	 */
	private function createIssue() {
		$t_response = $this->builder()->post( '/issues', $this->getIssueToAdd() )->send();
		$this->assertEquals( HTTP_STATUS_CREATED, $t_response->getStatusCode() );
		$t_issue_id = (int)json_decode( $t_response->getBody(), true )['issue']['id'];
		$this->deleteIssueAfterRun( $t_issue_id );
		return $t_issue_id;
	}

	/**
	 * A few existing issue ids older than the given one.
	 *
	 * @param integer $p_issue_id The reference issue id.
	 * @param integer $p_count    How many.
	 *
	 * @return array
	 */
	private function olderIssueIds( $p_issue_id, $p_count ) {
		$t_result = db_query( 'SELECT id FROM {bug} WHERE id < ' . db_param() . ' ORDER BY id DESC',
			array( (int)$p_issue_id ), $p_count );
		$t_ids = array();
		while( $t_row = db_fetch_array( $t_result ) ) {
			$t_ids[] = (int)$t_row['id'];
		}
		return $t_ids;
	}

	/**
	 * An issue matching the other criteria is found even when better matches that
	 * don't match them fill up the engine's cap.
	 *
	 * @return void
	 */
	public function testOtherCriteriaApplyBeforeTheCap() {
		$t_issue_id = $this->createIssue();
		$t_created = (int)bug_get_field( $t_issue_id, 'date_submitted' );
		$t_others = $this->olderIssueIds( $t_issue_id, 5 );

		# the new issue is the worst match, the cap only holds the better ones
		self::$matches = array_merge( $t_others, array( $t_issue_id ) );
		self::$cap = count( $t_others );

		$t_built = $this->build( 'search=whatever&search_engine=fulltext&created_after='
			. urlencode( date( 'Y-m-d\TH:i:s', $t_created - 1 ) ) );

		$this->assertCount( 1, self::$calls );
		$this->assertIsArray( self::$calls[0]['candidate_ids'], 'the other criteria must be passed to the engine' );
		$this->assertContains( $t_issue_id, self::$calls[0]['candidate_ids'] );
		$this->assertEquals( 'fake', $t_built['extra']['response']['search_engine'] );
		$this->assertContains( $t_issue_id, $this->matchedIds( $t_built ) );
		$this->assertEmpty( $t_built['warnings'] );
	}

	/**
	 * When the matches among the candidates still exceed the cap: 'auto' falls back
	 * to the substring search, 'fulltext' keeps the best matches with a warning.
	 *
	 * @return void
	 */
	public function testCappedResultAmongCandidates() {
		$t_issue_id = $this->createIssue();
		$t_created = (int)bug_get_field( $t_issue_id, 'date_submitted' );
		$t_second = $this->createIssue();

		self::$matches = array( $t_second, $t_issue_id );
		self::$cap = 1;
		$t_query = 'search=whatever&created_after=' . urlencode( date( 'Y-m-d\TH:i:s', $t_created - 1 ) );

		$t_auto = $this->build( $t_query );
		$this->assertEquals( 'like', $t_auto['extra']['response']['search_engine'] );
		$this->assertEquals( 'whatever', $t_auto['filter'][FILTER_PROPERTY_SEARCH] );
		$this->assertStringContainsString( 'substring search was used', implode( ' ', $t_auto['warnings'] ) );

		$t_fulltext = $this->build( $t_query . '&search_engine=fulltext' );
		$this->assertEquals( 'fake', $t_fulltext['extra']['response']['search_engine'] );
		$this->assertEquals( array( $t_second ), $this->matchedIds( $t_fulltext ) );
		$this->assertStringContainsString( 'incomplete', implode( ' ', $t_fulltext['warnings'] ) );
	}

	/**
	 * Criteria that match nothing (an unknown tag) don't even reach the engine's
	 * candidates: the result is empty.
	 *
	 * @return void
	 */
	public function testNoCandidatesMatchNothing() {
		self::$matches = $this->olderIssueIds( PHP_INT_MAX, 3 );

		$t_built = $this->build( 'search=whatever&search_engine=fulltext&tag_string='
			. urlencode( 'no-such-tag-' . rand( 1, 1000000 ) ) );

		$this->assertSame( array(), self::$calls[0]['candidate_ids'] );
		$this->assertSame( array(), $this->matchedIds( $t_built ) );
	}
}
