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
 * Tests for the bulk issue operations: PATCH /issues/bulk and
 * POST /issues/bulk/notes.
 *
 * @group REST
 */
class RestIssueBulkTest extends RestBase {
	/**
	 * An issue id that is not expected to exist.
	 */
	const MISSING_ISSUE_ID = 999999999;

	/**
	 * Create issues and return their ids, deleting them after the test.
	 *
	 * @param integer $p_count Number of issues to create.
	 * @return array The issue ids.
	 */
	private function createIssues( $p_count ) {
		$t_ids = array();

		for( $i = 0; $i < $p_count; $i++ ) {
			$t_response = $this->builder()->post( '/issues', $this->getIssueToAdd( 'bulk' ) )->send();
			$this->assertEquals( HTTP_STATUS_CREATED, $t_response->getStatusCode() );

			$t_body = json_decode( $t_response->getBody(), true );
			$t_issue_id = (int)$t_body['issue']['id'];
			$this->deleteIssueAfterRun( $t_issue_id );

			$t_ids[] = $t_issue_id;
		}

		return $t_ids;
	}

	/**
	 * Fetch an issue and return its decoded representation.
	 *
	 * @param integer $p_issue_id Issue identifier.
	 * @return array
	 */
	private function getIssue( $p_issue_id ) {
		$t_response = $this->builder()->get( '/issues/' . $p_issue_id )->send();
		$this->assertEquals( HTTP_STATUS_SUCCESS, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );

		return $t_body['issues'][0];
	}

	/**
	 * Index the 'results' array of a bulk response by issue id.
	 *
	 * @param array $p_body The decoded bulk response.
	 * @return array Map of issue id => result entry.
	 */
	private function resultsById( array $p_body ) {
		$t_map = array();
		foreach( $p_body['results'] as $t_entry ) {
			$t_map[$t_entry['id']] = $t_entry;
		}

		return $t_map;
	}

	/**
	 * The same update is applied to every issue of the batch.
	 *
	 * @return void
	 */
	public function testBulkUpdateAppliesToAllIssues() {
		$t_ids = $this->createIssues( 3 );

		$t_response = $this->builder()->patch( '/issues/bulk', array(
			'ids' => $t_ids,
			'issue' => array( 'status' => array( 'name' => 'acknowledged' ) ),
		) )->send();

		$this->assertEquals( HTTP_STATUS_SUCCESS, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );
		$this->assertEquals( count( $t_ids ), $t_body['ok_count'] );
		$this->assertEquals( 0, $t_body['error_count'] );
		$this->assertCount( count( $t_ids ), $t_body['results'] );

		foreach( $t_ids as $t_id ) {
			$this->assertEquals( 'acknowledged', $this->getIssue( $t_id )['status']['name'] );
		}
	}

	/**
	 * A failing issue doesn't prevent the others from being updated.
	 *
	 * @return void
	 */
	public function testBulkUpdateIsPartialOnFailure() {
		$t_ids = $this->createIssues( 3 );
		$t_requested = array_merge( $t_ids, array( self::MISSING_ISSUE_ID ) );

		$t_response = $this->builder()->patch( '/issues/bulk', array(
			'ids' => $t_requested,
			'issue' => array( 'status' => array( 'name' => 'confirmed' ) ),
		) )->send();

		$this->assertEquals( HTTP_STATUS_SUCCESS, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );
		$this->assertEquals( count( $t_ids ), $t_body['ok_count'] );
		$this->assertEquals( 1, $t_body['error_count'] );

		$t_results = $this->resultsById( $t_body );
		$this->assertEquals( 'error', $t_results[self::MISSING_ISSUE_ID]['status'] );
		$this->assertEquals( HTTP_STATUS_NOT_FOUND, $t_results[self::MISSING_ISSUE_ID]['code'] );

		foreach( $t_ids as $t_id ) {
			$this->assertEquals( 'ok', $t_results[$t_id]['status'] );
			$this->assertEquals( 'confirmed', $this->getIssue( $t_id )['status']['name'] );
		}
	}

	/**
	 * A bulk update leaves the same history entry as a single issue PATCH.
	 *
	 * @return void
	 */
	public function testBulkUpdateLeavesSameHistoryAsSingleUpdate() {
		list( $t_single_id, $t_bulk_id ) = $this->createIssues( 2 );

		$t_patch = array( 'status' => array( 'name' => 'acknowledged' ) );

		$t_response = $this->builder()->patch( '/issues/' . $t_single_id, $t_patch )->send();
		$this->assertEquals( HTTP_STATUS_SUCCESS, $t_response->getStatusCode() );

		$t_response = $this->builder()->patch( '/issues/bulk', array(
			'ids' => array( $t_bulk_id ),
			'issue' => $t_patch,
		) )->send();
		$this->assertEquals( HTTP_STATUS_SUCCESS, $t_response->getStatusCode() );

		$t_single_history = $this->historySignature( $t_single_id );
		$t_bulk_history = $this->historySignature( $t_bulk_id );

		$this->assertNotEmpty( $t_single_history );
		$this->assertEquals( $t_single_history, $t_bulk_history );
	}

	/**
	 * Build a comparable signature of an issue's history.
	 *
	 * @param integer $p_issue_id Issue identifier.
	 * @return array
	 */
	private function historySignature( $p_issue_id ) {
		$t_response = $this->builder()->get( '/issues/' . $p_issue_id, 'select=history' )->send();
		$this->assertEquals( HTTP_STATUS_SUCCESS, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );

		$t_signature = array();
		foreach( $t_body['issues'][0]['history'] as $t_entry ) {
			$t_signature[] = array(
				'type' => $t_entry['type'] ?? null,
				'field' => $t_entry['field'] ?? null,
				'old_value' => $t_entry['old_value'] ?? null,
				'new_value' => $t_entry['new_value'] ?? null,
			);
		}

		return $t_signature;
	}

	/**
	 * The same note is added to every issue of the batch.
	 *
	 * @return void
	 */
	public function testBulkNoteAddsNoteToAllIssues() {
		$t_ids = $this->createIssues( 3 );
		$t_text = 'bulk note ' . rand( 1, 1000000 );

		$t_response = $this->builder()->post( '/issues/bulk/notes', array(
			'ids' => $t_ids,
			'note' => array( 'text' => $t_text, 'view_state' => array( 'name' => 'public' ) ),
		) )->send();

		$this->assertEquals( HTTP_STATUS_SUCCESS, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );
		$this->assertEquals( count( $t_ids ), $t_body['ok_count'] );
		$this->assertEquals( 0, $t_body['error_count'] );

		foreach( $t_ids as $t_id ) {
			$t_issue = $this->getIssue( $t_id );
			$t_texts = array_column( $t_issue['notes'], 'text' );
			$this->assertEquals( 1, count( array_keys( $t_texts, $t_text, true ) ) );
		}
	}

	/**
	 * Duplicate ids are processed exactly once.
	 *
	 * @return void
	 */
	public function testBulkNoteProcessesDuplicateIdsOnce() {
		list( $t_id ) = $this->createIssues( 1 );
		$t_text = 'bulk dup note ' . rand( 1, 1000000 );

		$t_response = $this->builder()->post( '/issues/bulk/notes', array(
			'ids' => array( $t_id, $t_id, $t_id ),
			'note' => array( 'text' => $t_text ),
		) )->send();

		$this->assertEquals( HTTP_STATUS_SUCCESS, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );
		$this->assertCount( 1, $t_body['results'] );
		$this->assertEquals( 1, $t_body['ok_count'] );

		$t_issue = $this->getIssue( $t_id );
		$t_texts = array_column( $t_issue['notes'], 'text' );
		$this->assertEquals( 1, count( array_keys( $t_texts, $t_text, true ) ) );
	}

	/**
	 * An empty 'ids' array is rejected.
	 *
	 * @return void
	 */
	public function testBulkUpdateRejectsEmptyIds() {
		$t_response = $this->builder()->patch( '/issues/bulk', array(
			'ids' => array(),
			'issue' => array( 'status' => array( 'name' => 'confirmed' ) ),
		) )->send();

		$this->assertEquals( HTTP_STATUS_BAD_REQUEST, $t_response->getStatusCode() );
	}

	/**
	 * An empty 'ids' array is rejected by the notes endpoint too.
	 *
	 * @return void
	 */
	public function testBulkNoteRejectsEmptyIds() {
		$t_response = $this->builder()->post( '/issues/bulk/notes', array(
			'ids' => array(),
			'note' => array( 'text' => 'nothing to add to' ),
		) )->send();

		$this->assertEquals( HTTP_STATUS_BAD_REQUEST, $t_response->getStatusCode() );
	}

	/**
	 * A missing 'issue' payload is rejected.
	 *
	 * @return void
	 */
	public function testBulkUpdateRejectsMissingPayload() {
		$t_response = $this->builder()->patch( '/issues/bulk', array(
			'ids' => array( 1 ),
		) )->send();

		$this->assertEquals( HTTP_STATUS_BAD_REQUEST, $t_response->getStatusCode() );
	}

	/**
	 * More ids than the batch limit is rejected.
	 *
	 * @return void
	 */
	public function testBulkUpdateRejectsTooManyIds() {
		$t_ids = range( 1, REST_BULK_MAX_ISSUES + 1 );

		$t_response = $this->builder()->patch( '/issues/bulk', array(
			'ids' => $t_ids,
			'issue' => array( 'status' => array( 'name' => 'confirmed' ) ),
		) )->send();

		$this->assertEquals( HTTP_STATUS_BAD_REQUEST, $t_response->getStatusCode() );
	}

	/**
	 * Access is checked per issue: an issue the caller can't see fails on its
	 * own, without affecting the rest of the batch and without being modified.
	 *
	 * @return void
	 */
	public function testBulkUpdateChecksAccessPerIssue() {
		$t_username = $GLOBALS['MANTIS_TESTSUITE_RESTRICTED_USERNAME'] ?? '';
		if( empty( $t_username ) ) {
			$this->markTestSkipped(
				"Define 'MANTIS_TESTSUITE_RESTRICTED_USERNAME' (a user without access"
				. " to the test project) to run this test." );
		}

		list( $t_id ) = $this->createIssues( 1 );
		$t_before = $this->getIssue( $t_id )['status']['name'];

		$t_response = $this->builder()
			->impersonate( $t_username )
			->patch( '/issues/bulk', array(
				'ids' => array( $t_id ),
				'issue' => array( 'status' => array( 'name' => 'closed' ) ),
			) )->send();

		$this->assertEquals( HTTP_STATUS_SUCCESS, $t_response->getStatusCode() );

		$t_body = json_decode( $t_response->getBody(), true );
		$this->assertEquals( 0, $t_body['ok_count'] );
		$this->assertEquals( 1, $t_body['error_count'] );
		$this->assertEquals( 'error', $t_body['results'][0]['status'] );
		$this->assertEquals( HTTP_STATUS_FORBIDDEN, $t_body['results'][0]['code'] );

		# The issue must be untouched.
		$this->assertEquals( $t_before, $this->getIssue( $t_id )['status']['name'] );
	}
}
