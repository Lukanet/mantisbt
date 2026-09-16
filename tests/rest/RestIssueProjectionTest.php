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
 * Tests for the lightweight projections of the Issues REST API endpoint:
 * 'count_only', which returns only the totals, and 'fields=id', which returns
 * only the issue ids. Both are expected to agree with the regular listing.
 *
 * @group REST
 */
class RestIssueProjectionTest extends RestBase {
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
	 * Create an issue and return its id, deleting it after the test.
	 *
	 * @return integer
	 */
	private function createIssue() {
		$t_response = $this->builder()->post( '/issues', $this->getIssueToAdd() )->send();
		$this->assertEquals( HTTP_STATUS_CREATED, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );
		$t_issue_id = $t_body['issue']['id'];
		$this->deleteIssueAfterRun( $t_issue_id );

		return $t_issue_id;
	}

	/**
	 * 'count_only' returns the totals and nothing else.
	 *
	 * @return void
	 */
	public function testCountOnlyReturnsTotalsWithoutIssues() {
		$this->createIssue();

		$t_query = 'project_id=' . $this->getProjectId();
		$t_body = $this->getIssues( $t_query . '&count_only=1' );

		$this->assertArrayNotHasKey( 'issues', $t_body );
		$this->assertArrayHasKey( 'total_count', $t_body );
		$this->assertArrayHasKey( 'page_count', $t_body );
		$this->assertGreaterThan( 0, $t_body['total_count'] );
	}

	/**
	 * 'count_only' reports the same total as the regular listing.
	 *
	 * @return void
	 */
	public function testCountOnlyMatchesRegularListing() {
		$this->createIssue();

		$t_query = 'project_id=' . $this->getProjectId() . '&status_id=' . NEW_;

		$t_regular = $this->getIssues( $t_query . '&page_size=1' );
		$t_count_only = $this->getIssues( $t_query . '&page_size=1&count_only=1' );

		$this->assertEquals( $t_regular['total_count'], $t_count_only['total_count'] );
		$this->assertEquals( $t_regular['page_count'], $t_count_only['page_count'] );
	}

	/**
	 * 'count_only' also works without any filter parameter, and on a saved filter.
	 *
	 * @return void
	 */
	public function testCountOnlyWorksWithoutInlineFilter() {
		$this->createIssue();

		$t_regular = $this->getIssues( 'page_size=1' );
		$t_count_only = $this->getIssues( 'page_size=1&count_only=1' );

		$this->assertEquals( $t_regular['total_count'], $t_count_only['total_count'] );
		$this->assertEquals( $t_regular['page_count'], $t_count_only['page_count'] );
	}

	/**
	 * A false-ish 'count_only' leaves the response untouched.
	 *
	 * @return void
	 */
	public function testCountOnlyDisabledReturnsRegularResponse() {
		$this->createIssue();

		$t_body = $this->getIssues( 'page_size=2&count_only=0' );

		$this->assertArrayHasKey( 'issues', $t_body );
		$this->assertArrayHasKey( 'summary', $t_body['issues'][0] );
	}

	/**
	 * A non boolean 'count_only' is rejected.
	 *
	 * @return void
	 */
	public function testCountOnlyWithInvalidValueFails() {
		$this->getIssues( 'count_only=maybe', HTTP_STATUS_BAD_REQUEST );
	}

	/**
	 * 'fields=id' returns issues that carry nothing but their id.
	 *
	 * @return void
	 */
	public function testFieldsIdReturnsIdsOnly() {
		$this->createIssue();

		$t_body = $this->getIssues( 'project_id=' . $this->getProjectId() . '&fields=id&page_size=5' );

		$this->assertArrayHasKey( 'issues', $t_body );
		$this->assertNotEmpty( $t_body['issues'] );
		foreach( $t_body['issues'] as $t_issue ) {
			$this->assertEquals( array( 'id' ), array_keys( $t_issue ) );
			$this->assertIsInt( $t_issue['id'] );
		}
		$this->assertArrayHasKey( 'total_count', $t_body );
		$this->assertArrayHasKey( 'page_count', $t_body );
	}

	/**
	 * 'fields=id' returns the same issues as the regular listing.
	 *
	 * @return void
	 */
	public function testFieldsIdMatchesRegularListing() {
		$this->createIssue();

		$t_query = 'project_id=' . $this->getProjectId() . '&status_id=' . NEW_ . '&page_size=50';

		$t_regular = $this->getIssues( $t_query );
		$t_ids_only = $this->getIssues( $t_query . '&fields=id' );

		$this->assertEquals( $t_regular['total_count'], $t_ids_only['total_count'] );

		$t_regular_ids = array_column( $t_regular['issues'], 'id' );
		$t_ids = array_column( $t_ids_only['issues'], 'id' );
		sort( $t_regular_ids );
		sort( $t_ids );

		$this->assertEquals( $t_regular_ids, $t_ids );
	}

	/**
	 * 'fields=id' honours page and page_size.
	 *
	 * @return void
	 */
	public function testFieldsIdIsPaged() {
		$this->createIssue();
		$this->createIssue();
		$this->createIssue();

		$t_query = 'project_id=' . $this->getProjectId() . '&fields=id&page_size=1';

		$t_page1 = $this->getIssues( $t_query . '&page=1' );
		$t_page2 = $this->getIssues( $t_query . '&page=2' );

		$this->assertCount( 1, $t_page1['issues'] );
		$this->assertCount( 1, $t_page2['issues'] );
		$this->assertNotEquals( $t_page1['issues'][0]['id'], $t_page2['issues'][0]['id'] );
	}

	/**
	 * An unsupported 'fields' value is rejected.
	 *
	 * @return void
	 */
	public function testFieldsWithUnknownValueFails() {
		$this->getIssues( 'fields=summary', HTTP_STATUS_BAD_REQUEST );
	}

	/**
	 * 'count_only' and 'fields' cannot be combined.
	 *
	 * @return void
	 */
	public function testCountOnlyAndFieldsAreMutuallyExclusive() {
		$this->getIssues( 'count_only=1&fields=id', HTTP_STATUS_BAD_REQUEST );
	}

	/**
	 * Neither projection applies to a single issue lookup.
	 *
	 * @return void
	 */
	public function testProjectionsAreRejectedWithIssueId() {
		$t_issue_id = $this->createIssue();

		$this->getIssues( 'id=' . $t_issue_id . '&count_only=1', HTTP_STATUS_BAD_REQUEST );
		$this->getIssues( 'id=' . $t_issue_id . '&fields=id', HTTP_STATUS_BAD_REQUEST );
	}

	/**
	 * The new parameters are known, so they don't raise a warning.
	 *
	 * @return void
	 */
	public function testProjectionParametersDontWarn() {
		$this->createIssue();

		$t_body = $this->getIssues( 'page_size=1&count_only=1' );
		$this->assertArrayNotHasKey( 'warnings', $t_body );

		$t_body = $this->getIssues( 'page_size=1&fields=id' );
		$this->assertArrayNotHasKey( 'warnings', $t_body );
	}
}
