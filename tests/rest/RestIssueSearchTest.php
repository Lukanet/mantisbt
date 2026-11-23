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
 * Tests for the extended search parameters of GET /issues: sorting by several and
 * by any column, unknown tags, project lists and sub-projects, custom fields, date
 * and time bounds, offset / limit, notes trimming, status changes and the search
 * engine selection.
 *
 * @group REST
 */
class RestIssueSearchTest extends RestBase {
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
	 * The ids of the issues in a response.
	 *
	 * @param array $p_body The decoded response body.
	 *
	 * @return array
	 */
	private function ids( array $p_body ) {
		return array_column( $p_body['issues'], 'id' );
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

	/**
	 * Several sort columns are accepted (they used to fail with an error 500).
	 *
	 * @return void
	 */
	public function testSortBySeveralColumns() {
		$t_body = $this->getIssues( 'sort=status,last_updated&sort_direction=ASC,DESC&page_size=20&select=id,status,updated_at' );

		$this->assertArrayNotHasKey( 'warnings', $t_body );
		$t_previous = null;
		foreach( $t_body['issues'] as $t_issue ) {
			if( $t_previous !== null ) {
				$this->assertGreaterThanOrEqual( $t_previous['status']['id'], $t_issue['status']['id'] );
				if( $t_previous['status']['id'] == $t_issue['status']['id'] ) {
					$this->assertLessThanOrEqual( $t_previous['updated_at'], $t_issue['updated_at'] );
				}
			}
			$t_previous = $t_issue;
		}
	}

	/**
	 * Sorting by a column that is not on the View Issues page is applied, not
	 * silently replaced with the default order.
	 *
	 * @return void
	 */
	public function testSortByDateSubmittedAscending() {
		$t_body = $this->getIssues( 'sort=date_submitted&sort_direction=ASC&page_size=10&select=id,created_at' );
		$t_dates = array_column( $t_body['issues'], 'created_at' );
		$t_sorted = $t_dates;
		sort( $t_sorted );
		$this->assertEquals( $t_sorted, $t_dates );

		# 'created_at' is an alias of 'date_submitted'
		$t_alias = $this->getIssues( 'sort=created_at&sort_direction=ASC&page_size=10&select=id' );
		$this->assertEquals( $this->ids( $t_body ), $this->ids( $t_alias ) );
	}

	/**
	 * Sorting by id ascending returns increasing ids.
	 *
	 * @return void
	 */
	public function testSortByIdAscending() {
		$t_ids = $this->ids( $this->getIssues( 'sort=id&sort_direction=asc&page_size=10&select=id' ) );
		$t_sorted = $t_ids;
		sort( $t_sorted );
		$this->assertEquals( $t_sorted, $t_ids );
	}

	/**
	 * An unknown sort column is rejected.
	 *
	 * @return void
	 */
	public function testUnknownSortColumnFails() {
		$t_body = $this->getIssues( 'sort=no_such_column', HTTP_STATUS_BAD_REQUEST );
		$this->assertStringContainsString( 'no_such_column', $t_body['message'] );
		$this->assertStringContainsString( 'date_submitted', $t_body['message'] );
	}

	/**
	 * An invalid sort direction is rejected.
	 *
	 * @return void
	 */
	public function testInvalidSortDirectionFails() {
		$this->getIssues( 'sort=id&sort_direction=sideways', HTTP_STATUS_BAD_REQUEST );
	}

	/**
	 * A tag that doesn't exist matches no issue, with a warning.
	 *
	 * @return void
	 */
	public function testUnknownTagMatchesNothing() {
		$t_tag = 'no-such-tag-' . rand( 1, 1000000 );
		$t_body = $this->getIssues( 'tag_string=' . urlencode( $t_tag ) . '&page_size=1' );

		$this->assertEquals( 0, $t_body['total_count'] );
		$this->assertCount( 0, $t_body['issues'] );
		$this->assertStringContainsString( $t_tag, $t_body['warnings'][0] );

		$t_body = $this->getIssues( 'tag_select=2147483000&count_only=1' );
		$this->assertEquals( 0, $t_body['total_count'] );
		$this->assertArrayHasKey( 'warnings', $t_body );
	}

	/**
	 * Excluding a tag that doesn't exist excludes nothing, with a warning.
	 *
	 * @return void
	 */
	public function testUnknownExcludedTagIsIgnored() {
		$t_all = $this->getIssues( 'count_only=1' );
		$t_body = $this->getIssues( 'tag_string=' . urlencode( '-no-such-tag-' . rand( 1, 1000000 ) ) . '&count_only=1' );

		$this->assertEquals( $t_all['total_count'], $t_body['total_count'] );
		$this->assertArrayHasKey( 'warnings', $t_body );
	}

	/**
	 * A list of projects searches all of them; sub-projects are added on request.
	 *
	 * @return void
	 */
	public function testProjectListAndSubprojects() {
		$t_project_id = $this->getProjectId();
		$t_single = $this->getIssues( 'project_id=' . $t_project_id . '&status_id=10,20,30,40,50,60,70,80,85,90&count_only=1' );
		$t_list = $this->getIssues( 'project_id=' . $t_project_id . ',' . $t_project_id . '&status_id=10,20,30,40,50,60,70,80,85,90&count_only=1' );
		$t_with_sub = $this->getIssues( 'project_id=' . $t_project_id . '&include_subprojects=1&status_id=10,20,30,40,50,60,70,80,85,90&count_only=1' );

		$this->assertEquals( $t_single['total_count'], $t_list['total_count'] );
		$this->assertGreaterThanOrEqual( $t_single['total_count'], $t_with_sub['total_count'] );

		$this->getIssues( 'project_id=' . $t_project_id . ',2147483000', HTTP_STATUS_NOT_FOUND );
		$this->getIssues( 'project_id=' . $t_project_id . '&include_subprojects=maybe', HTTP_STATUS_BAD_REQUEST );
	}

	/**
	 * Malformed and unknown custom field criteria are rejected.
	 *
	 * @return void
	 */
	public function testCustomFieldParameterValidation() {
		$this->getIssues( 'custom_fields=value', HTTP_STATUS_BAD_REQUEST );
		$t_body = $this->getIssues( 'custom_fields' . urlencode( '[no such field ' . rand( 1, 1000000 ) . ']' ) . '=x',
			HTTP_STATUS_BAD_REQUEST );
		$this->assertStringContainsString( 'no such field', $t_body['message'] );
	}

	/**
	 * A custom field criterion narrows down the result set; a value nobody has
	 * matches nothing, and the substring match finds at least what the exact one does.
	 *
	 * @return void
	 */
	public function testCustomFieldCriteria() {
		$t_field = $this->firstReadableCustomField();
		if( $t_field === null ) {
			$this->markTestSkipped( 'No custom field with a value is visible to the test user.' );
		}

		$t_param = urlencode( '[' . $t_field['id'] . ']' );
		$t_exact = $this->getIssues( 'custom_fields' . $t_param . '=' . urlencode( $t_field['value'] ) . '&count_only=1' );
		$t_contains = $this->getIssues( 'custom_fields_contains' . $t_param . '=' . urlencode( $t_field['value'] ) . '&count_only=1' );
		$t_by_name = $this->getIssues( 'custom_fields' . urlencode( '[' . $t_field['name'] . ']' ) . '='
			. urlencode( $t_field['value'] ) . '&count_only=1' );
		$t_none = $this->getIssues( 'custom_fields' . $t_param . '=' . urlencode( 'no value ' . rand( 1, 1000000 ) ) . '&count_only=1' );

		$this->assertGreaterThan( 0, $t_exact['total_count'] );
		$this->assertEquals( $t_exact['total_count'], $t_by_name['total_count'] );
		$this->assertGreaterThanOrEqual( $t_exact['total_count'], $t_contains['total_count'] );
		$this->assertEquals( 0, $t_none['total_count'] );
	}

	/**
	 * A date and time bound is exact to the second.
	 *
	 * @return void
	 */
	public function testDateTimeBounds() {
		$t_issue_id = $this->createIssue();
		$t_issue = $this->getIssues( 'id=' . $t_issue_id . '&select=id,created_at' )['issues'][0];
		$t_created = strtotime( $t_issue['created_at'] );

		$t_after = gmdate( 'Y-m-d\TH:i:s\Z', $t_created - 1 );
		$t_later = gmdate( 'Y-m-d\TH:i:s\Z', $t_created + 1 );

		$t_included = $this->ids( $this->getIssues( 'created_after=' . urlencode( $t_after )
			. '&created_before=' . urlencode( $t_later ) . '&select=id&page_size=50' ) );
		$this->assertContains( $t_issue_id, $t_included );

		$t_excluded = $this->ids( $this->getIssues( 'created_after=' . urlencode( $t_later ) . '&select=id&page_size=50' ) );
		$this->assertNotContains( $t_issue_id, $t_excluded );

		$this->getIssues( 'created_after=2026-01-01T25:00', HTTP_STATUS_BAD_REQUEST );
	}

	/**
	 * 'offset' + 'limit' return the same issues as the matching slice of a page.
	 *
	 * @return void
	 */
	public function testOffsetAndLimit() {
		$t_page = $this->ids( $this->getIssues( 'sort=id&sort_direction=DESC&page_size=10&select=id' ) );
		$t_slice = $this->getIssues( 'sort=id&sort_direction=DESC&offset=3&limit=4&select=id' );

		$this->assertEquals( array_slice( $t_page, 3, 4 ), $this->ids( $t_slice ) );
		$this->assertArrayNotHasKey( 'warnings', $t_slice );

		# without an inline filter, too
		$t_page = $this->ids( $this->getIssues( 'page_size=6&select=id' ) );
		$this->assertEquals( array_slice( $t_page, 2, 3 ), $this->ids( $this->getIssues( 'offset=2&limit=3&select=id' ) ) );

		$t_beyond = $this->getIssues( 'sort=id&offset=2147483000&limit=5&select=id' );
		$this->assertCount( 0, $t_beyond['issues'] );

		$this->getIssues( 'offset=-1', HTTP_STATUS_BAD_REQUEST );
		$this->getIssues( 'limit=0', HTTP_STATUS_BAD_REQUEST );
	}

	/**
	 * 'notes_limit' keeps the newest notes and reports how many there are.
	 *
	 * @return void
	 */
	public function testNotesLimit() {
		$t_issue_id = $this->createIssue();
		foreach( array( 'first', 'second', 'third' ) as $t_text ) {
			$t_response = $this->builder()->post( '/issues/' . $t_issue_id . '/notes',
				array( 'text' => $t_text, 'view_state' => array( 'name' => 'public' ) ) )->send();
			$this->assertEquals( HTTP_STATUS_CREATED, $t_response->getStatusCode() );
		}

		$t_all = $this->getIssues( 'id=' . $t_issue_id . '&select=id,notes' )['issues'][0];
		$this->assertCount( 3, $t_all['notes'] );
		$this->assertArrayNotHasKey( 'notes_count', $t_all );

		$t_last = $this->getIssues( 'id=' . $t_issue_id . '&select=id,notes&notes_limit=2&notes_order=desc' )['issues'][0];
		$this->assertEquals( 3, $t_last['notes_count'] );
		$this->assertEquals( array( 'third', 'second' ), array_column( $t_last['notes'], 'text' ) );

		$t_list = $this->getIssues( 'search=' . $t_issue_id . '&search_engine=like&select=id,notes&notes_limit=1' );
		foreach( $t_list['issues'] as $t_issue ) {
			if( $t_issue['id'] == $t_issue_id ) {
				$this->assertEquals( array( 'third' ), array_column( $t_issue['notes'], 'text' ) );
			}
		}

		$this->getIssues( 'id=' . $t_issue_id . '&notes_order=sideways', HTTP_STATUS_BAD_REQUEST );
	}

	/**
	 * The status change filter only matches issues whose history has the change.
	 *
	 * @return void
	 */
	public function testStatusChangedFilter() {
		$t_issue_id = $this->createIssue();
		$t_response = $this->builder()->patch( '/issues/' . $t_issue_id,
			array( 'status' => array( 'name' => 'acknowledged' ) ) )->send();
		$this->assertEquals( HTTP_STATUS_SUCCESS, $t_response->getStatusCode() );

		$t_today = date( 'Y-m-d' );
		$t_ids = $this->ids( $this->getIssues( 'status_changed_to=30&status_changed_after=' . $t_today
			. '&search=' . $t_issue_id . '&search_engine=like&select=id' ) );
		$this->assertContains( $t_issue_id, $t_ids );

		$t_ids = $this->ids( $this->getIssues( 'status_changed_to=80&status_changed_after=' . $t_today
			. '&search=' . $t_issue_id . '&search_engine=like&select=id' ) );
		$this->assertNotContains( $t_issue_id, $t_ids );

		$this->getIssues( 'status_changed_to=closed', HTTP_STATUS_BAD_REQUEST );
	}

	/**
	 * The search engine can be chosen; the response says which one was used.
	 *
	 * @return void
	 */
	public function testSearchEngineSelection() {
		$t_body = $this->getIssues( 'search=test&search_engine=like&count_only=1' );
		$this->assertEquals( 'like', $t_body['search_engine'] );

		# auto (the default) never fails because of the full-text backend: it uses Sphinx,
		# or falls back to the substring search with a warning.
		$t_auto = $this->getIssues( 'search=test&count_only=1' );
		$this->assertContains( $t_auto['search_engine'], array( 'like', 'sphinx' ) );
		if( $t_auto['search_engine'] == 'like' ) {
			$this->assertArrayHasKey( 'warnings', $t_auto );
		}

		# fulltext either answers from Sphinx or says why it can't
		$t_response = $this->builder()->get( '/issues', 'search=test&search_engine=fulltext&count_only=1' )->send();
		$this->assertContains( $t_response->getStatusCode(),
			array( HTTP_STATUS_SUCCESS, HTTP_STATUS_BAD_REQUEST, HTTP_STATUS_FORBIDDEN, HTTP_STATUS_UNAVAILABLE ) );
		if( $t_response->getStatusCode() == HTTP_STATUS_SUCCESS ) {
			$this->assertEquals( 'sphinx', json_decode( $t_response->getBody(), true )['search_engine'] );
		}

		$this->getIssues( 'search=test&search_engine=bogus', HTTP_STATUS_BAD_REQUEST );

		$t_body = $this->getIssues( 'status_id=10&search_engine=like&count_only=1' );
		$this->assertArrayHasKey( 'warnings', $t_body );
	}

	/**
	 * The new parameters are known: no "unknown parameter" warning.
	 *
	 * @return void
	 */
	public function testNewParametersAreKnown() {
		$t_body = $this->getIssues( 'project_id=' . $this->getProjectId()
			. '&include_subprojects=0&offset=0&limit=1&notes_limit=1&notes_order=asc&select=id' );
		$this->assertArrayNotHasKey( 'warnings', $t_body );
	}

	/**
	 * Finds a custom field with a value on an issue the test user can see.
	 *
	 * @return array|null Array with 'id', 'name' and 'value', or null.
	 */
	private function firstReadableCustomField() {
		$t_body = $this->getIssues( 'select=id,custom_fields&page_size=50&sort=id&sort_direction=DESC' );
		foreach( $t_body['issues'] as $t_issue ) {
			if( empty( $t_issue['custom_fields'] ) ) {
				continue;
			}
			foreach( $t_issue['custom_fields'] as $t_custom_field ) {
				$t_value = isset( $t_custom_field['value'] ) ? (string)$t_custom_field['value'] : '';
				if( $t_value !== '' && strpos( $t_value, '|' ) === false ) {
					return array(
						'id' => $t_custom_field['field']['id'],
						'name' => $t_custom_field['field']['name'],
						'value' => $t_value,
					);
				}
			}
		}
		return null;
	}
}
