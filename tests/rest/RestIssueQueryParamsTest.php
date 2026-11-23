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
 * Tests for the query parameter validation of the Issues REST API endpoint:
 * the 'select' whitelist, the warnings about unknown parameters and the
 * ISO 8601 date parameters.
 *
 * @group REST
 */
class RestIssueQueryParamsTest extends RestBase {
	/**
	 * Issues the GET /issues request and returns the decoded body.
	 *
	 * @param string  $p_query_string    The query string, without leading '?'.
	 * @param integer $p_expected_status The expected http status code.
	 *
	 * @return array The decoded response body.
	 */
	private function getIssues( $p_query_string, $p_expected_status = HTTP_STATUS_SUCCESS ) {
		$t_response = $this->builder()->get( '/issues', $p_query_string )->send();

		$this->assertEquals( $p_expected_status, $t_response->getStatusCode(),
			"GET /issues?$p_query_string" );

		return json_decode( $t_response->getBody(), true );
	}

	/**
	 * A known 'select' field returns the requested data.
	 *
	 * @return void
	 */
	public function testSelectWithKnownFieldsSucceeds() {
		$t_issue_id = $this->createIssue();

		$t_body = $this->getIssues( 'id=' . $t_issue_id . '&select=id,summary' );

		$this->assertArrayHasKey( 'issues', $t_body );
		$this->assertArrayHasKey( 'id', $t_body['issues'][0] );
		$this->assertArrayHasKey( 'summary', $t_body['issues'][0] );
		$this->assertArrayNotHasKey( 'description', $t_body['issues'][0] );
	}

	/**
	 * An unknown 'select' field is rejected with a message listing the
	 * supported fields.
	 *
	 * @return void
	 */
	public function testSelectWithUnknownFieldFails() {
		$t_body = $this->getIssues( 'select=no_such_field', HTTP_STATUS_BAD_REQUEST );

		$this->assertStringContainsString( 'no_such_field', $t_body['message'] );
		$this->assertStringContainsString( 'steps_to_reproduce', $t_body['message'] );
	}

	/**
	 * A valid field mixed with an invalid one is still rejected.
	 *
	 * @return void
	 */
	public function testSelectWithOneUnknownFieldFails() {
		$t_body = $this->getIssues( 'select=id,no_such_field', HTTP_STATUS_BAD_REQUEST );

		$this->assertStringContainsString( 'no_such_field', $t_body['message'] );
	}

	/**
	 * An unknown query parameter is reported as a warning but doesn't fail
	 * the request.
	 *
	 * @return void
	 */
	public function testUnknownQueryParameterReturnsWarning() {
		$t_body = $this->getIssues( 'last_updated_end=2026-07-01&page_size=1' );

		$this->assertArrayHasKey( 'warnings', $t_body );
		$this->assertCount( 1, $t_body['warnings'] );
		$this->assertStringContainsString( 'last_updated_end', $t_body['warnings'][0] );
	}

	/**
	 * A request that only uses known parameters has no 'warnings' key.
	 *
	 * @return void
	 */
	public function testKnownQueryParametersReturnNoWarnings() {
		$t_body = $this->getIssues( 'page_size=1&page=1&select=id' );

		$this->assertArrayNotHasKey( 'warnings', $t_body );
	}

	/**
	 * The ISO date parameters are equivalent to the legacy day/month/year ones.
	 *
	 * @return void
	 */
	public function testIsoDateParamsMatchLegacyDateParams() {
		$t_iso = $this->getIssues( 'updated_before=2026-07-17&page_size=1' );
		$t_legacy = $this->getIssues(
			'last_update_end_year=2026&last_update_end_month=07&last_update_end_day=17&page_size=1' );

		$this->assertArrayNotHasKey( 'warnings', $t_iso );
		$this->assertEquals( $t_legacy['total_count'], $t_iso['total_count'] );
	}

	/**
	 * An ISO date parameter narrows down the result set.
	 *
	 * @return void
	 */
	public function testIsoDateParamFiltersIssues() {
		$t_issue_id = $this->createIssue();

		# search_engine=like: the substring search finds the issue by its id; a full-text
		# index (Sphinx) doesn't know an issue created by the test.
		$t_all = $this->getIssues( 'search=' . $t_issue_id . '&search_engine=like&page_size=1' );
		$t_filtered = $this->getIssues(
			'search=' . $t_issue_id . '&search_engine=like&updated_before=2000-01-01&page_size=1' );

		$this->assertGreaterThan( $t_filtered['total_count'], $t_all['total_count'] );
	}

	/**
	 * An invalid ISO date is rejected.
	 *
	 * @param string $p_value The invalid date value.
	 *
	 * @return void
	 *
	 * @dataProvider providerInvalidDates
	 */
	public function testInvalidIsoDateFails( $p_value ) {
		$t_body = $this->getIssues(
			'updated_before=' . urlencode( $p_value ), HTTP_STATUS_BAD_REQUEST );

		$this->assertStringContainsString( 'updated_before', $t_body['message'] );
		$this->assertStringContainsString( 'YYYY-MM-DD', $t_body['message'] );
	}

	/**
	 * Invalid date values.
	 *
	 * @return array
	 */
	public function providerInvalidDates() {
		return array(
			'not a date' => array( 'nonsense' ),
			'wrong separator' => array( '2026/07/17' ),
			'not zero padded' => array( '2026-7-1' ),
			'non existing day' => array( '2026-02-30' ),
			'empty' => array( '' ),
		);
	}

	/**
	 * When both the ISO and the legacy form are given, the ISO one wins and a
	 * warning is returned.
	 *
	 * @return void
	 */
	public function testIsoDateOverridesLegacyDateWithWarning() {
		$t_body = $this->getIssues(
			'updated_before=2026-07-17&last_update_end_day=3&page_size=1' );

		$this->assertArrayHasKey( 'warnings', $t_body );
		$this->assertStringContainsString( 'last_update_end_day', $t_body['warnings'][0] );
		$this->assertStringContainsString( 'updated_before', $t_body['warnings'][0] );

		$t_iso_only = $this->getIssues( 'updated_before=2026-07-17&page_size=1' );
		$this->assertEquals( $t_iso_only['total_count'], $t_body['total_count'] );
	}

	/**
	 * An upper bound given on its own covers everything up to that date, instead
	 * of being completed with a lower bound that makes the range impossible.
	 *
	 * @return void
	 */
	public function testUpperBoundOnlyIsNotAnImpossibleRange() {
		$this->createIssue();

		$t_open = $this->getIssues( 'updated_before=2037-12-31&page_size=1' );
		$t_explicit = $this->getIssues(
			'updated_after=1970-01-01&updated_before=2037-12-31&page_size=1' );
		$t_unfiltered = $this->getIssues( 'search=&page_size=1' );

		$this->assertGreaterThan( 0, $t_open['total_count'] );
		$this->assertEquals( $t_explicit['total_count'], $t_open['total_count'] );
		$this->assertEquals( $t_unfiltered['total_count'], $t_open['total_count'] );
	}

	/**
	 * A lower bound given on its own covers everything from that date on.
	 *
	 * @return void
	 */
	public function testLowerBoundOnlyIsNotTruncated() {
		$this->createIssue();

		$t_open = $this->getIssues( 'updated_after=1970-01-01&page_size=1' );
		$t_explicit = $this->getIssues(
			'updated_after=1970-01-01&updated_before=2038-01-18&page_size=1' );
		$t_unfiltered = $this->getIssues( 'search=&page_size=1' );

		$this->assertGreaterThan( 0, $t_open['total_count'] );
		$this->assertEquals( $t_explicit['total_count'], $t_open['total_count'] );
		$this->assertEquals( $t_unfiltered['total_count'], $t_open['total_count'] );
	}

	/**
	 * The same applies to the 'date submitted' pair.
	 *
	 * @return void
	 */
	public function testCreatedUpperBoundOnlyIsNotAnImpossibleRange() {
		$this->createIssue();

		$t_open = $this->getIssues( 'created_before=2037-12-31&page_size=1' );
		$t_explicit = $this->getIssues(
			'created_after=1970-01-01&created_before=2037-12-31&page_size=1' );
		$t_unfiltered = $this->getIssues( 'search=&page_size=1' );

		$this->assertGreaterThan( 0, $t_open['total_count'] );
		$this->assertEquals( $t_explicit['total_count'], $t_open['total_count'] );
		$this->assertEquals( $t_unfiltered['total_count'], $t_open['total_count'] );
	}

	/**
	 * The legacy day/month/year parameters get the same treatment as the ISO ones.
	 *
	 * @return void
	 */
	public function testLegacyOpenEndedBoundsMatchIsoBounds() {
		$this->createIssue();

		$t_legacy_end = $this->getIssues(
			'last_update_end_year=2037&last_update_end_month=12&last_update_end_day=31&page_size=1' );
		$t_iso_end = $this->getIssues( 'updated_before=2037-12-31&page_size=1' );
		$this->assertEquals( $t_iso_end['total_count'], $t_legacy_end['total_count'] );

		$t_legacy_start = $this->getIssues(
			'start_year=1970&start_month=01&start_day=01&page_size=1' );
		$t_iso_start = $this->getIssues( 'created_after=1970-01-01&page_size=1' );
		$this->assertEquals( $t_iso_start['total_count'], $t_legacy_start['total_count'] );
	}

	/**
	 * A range with both bounds given is left alone.
	 *
	 * @return void
	 */
	public function testCompleteRangeIsNotModified() {
		$t_range = $this->getIssues(
			'updated_after=2026-01-01&updated_before=2026-03-01&page_size=1' );
		$t_legacy_range = $this->getIssues(
			'last_update_start_year=2026&last_update_start_month=01&last_update_start_day=01'
			. '&last_update_end_year=2026&last_update_end_month=03&last_update_end_day=01&page_size=1' );
		$t_open = $this->getIssues( 'updated_before=2037-12-31&page_size=1' );

		$this->assertEquals( $t_legacy_range['total_count'], $t_range['total_count'] );
		$this->assertLessThanOrEqual( $t_open['total_count'], $t_range['total_count'] );
	}

	/**
	 * Creates an issue to be used by a test, it is deleted at the end of the run.
	 *
	 * @return integer The issue id.
	 */
	private function createIssue() {
		$t_response = $this->builder()->post( '/issues', $this->getIssueToAdd() )->send();
		$this->assertEquals( HTTP_STATUS_CREATED, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );
		$t_issue_id = $t_body['issue']['id'];
		$this->deleteIssueAfterRun( $t_issue_id );

		return $t_issue_id;
	}
}
