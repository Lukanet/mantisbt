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
 * A webservice interface to Mantis Bug Tracker
 *
 * @package MantisBT
 * @copyright Copyright MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

use Mantis\Exceptions\ClientException;

/**
 * @var \Slim\App $g_app
 */
$g_app->group('/issues', function() use ( $g_app ) {
	# Bulk operations.
	# Registered before the '/{id}' routes so that the literal 'bulk' segment
	# is never captured as an issue id.
	$g_app->patch( '/bulk', 'rest_issue_bulk_update' );
	$g_app->patch( '/bulk/', 'rest_issue_bulk_update' );
	$g_app->post( '/bulk/notes', 'rest_issue_bulk_note_add' );
	$g_app->post( '/bulk/notes/', 'rest_issue_bulk_note_add' );

	$g_app->get( '', 'rest_issue_get' );
	$g_app->get( '/', 'rest_issue_get' );
	$g_app->get( '/{id}', 'rest_issue_get' );
	$g_app->get( '/{id}/', 'rest_issue_get' );
	$g_app->post( '', 'rest_issue_add' );
	$g_app->post( '/', 'rest_issue_add' );
	$g_app->delete( '', 'rest_issue_delete' );
	$g_app->delete( '/', 'rest_issue_delete' );
	$g_app->delete( '/{id}', 'rest_issue_delete' );
	$g_app->delete( '/{id}/', 'rest_issue_delete' );
	$g_app->patch( '', 'rest_issue_update' );
	$g_app->patch( '/', 'rest_issue_update' );
	$g_app->patch( '/{id}', 'rest_issue_update' );
	$g_app->patch( '/{id}/', 'rest_issue_update' );

	# Tags
	$g_app->post( '/{id}/tags', 'rest_issue_tag_attach' );
	$g_app->post( '/{id}/tags/', 'rest_issue_tag_attach' );
	$g_app->delete( '/{id}/tags/{tag_id}', 'rest_issue_tag_detach' );
	$g_app->delete( '/{id}/tags/{tag_id}/', 'rest_issue_tag_detach' );

	# Monitor
	$g_app->post( '/{id}/monitors/', 'rest_issue_monitor_add' );
	$g_app->post( '/{id}/monitors', 'rest_issue_monitor_add' );

	# Notes
	$g_app->post( '/{id}/notes/', 'rest_issue_note_add' );
	$g_app->post( '/{id}/notes', 'rest_issue_note_add' );
	$g_app->delete( '/{id}/notes/{note_id}/', 'rest_issue_note_delete' );
	$g_app->delete( '/{id}/notes/{note_id}', 'rest_issue_note_delete' );

	# Relationships
	$g_app->post( '/{id}/relationships/', 'rest_issue_relationship_add' );
	$g_app->post( '/{id}/relationships', 'rest_issue_relationship_add' );
	$g_app->delete( '/{id}/relationships/{relationship_id}/', 'rest_issue_relationship_delete' );
	$g_app->delete( '/{id}/relationships/{relationship_id}', 'rest_issue_relationship_delete' );

	# Files
	$g_app->post( '/{id}/files/', 'rest_issue_file_add' );
	$g_app->post( '/{id}/files', 'rest_issue_file_add' );
	$g_app->get( '/{id}/files/', 'rest_issue_files_get' );
	$g_app->get( '/{id}/files', 'rest_issue_files_get' );
	$g_app->get( '/{id}/files/{file_id}/', 'rest_issue_files_get' );
	$g_app->get( '/{id}/files/{file_id}', 'rest_issue_files_get' );
});

/**
 * The list of issue fields that can be requested via the 'select' query parameter.
 *
 * This is the single source of truth for the whitelist that is implicitly
 * implemented by mci_issue_data_as_array() in mc_issue_api.php.
 *
 * @return array The supported field names.
 */
function rest_issue_select_fields() {
	return array(
		'id', 'summary', 'description', 'steps_to_reproduce', 'additional_information',
		'project', 'category', 'version', 'fixed_in_version', 'target_version',
		'reporter', 'handler', 'status', 'resolution', 'view_state',
		'priority', 'severity', 'reproducibility', 'projection', 'build',
		'platform', 'os', 'os_build', 'eta', 'profile', 'sticky', 'due_date',
		'created_at', 'updated_at', 'attachments', 'notes', 'relationships',
		'custom_fields', 'tags', 'monitors', 'history',
	);
}

/**
 * Map of inline filter query parameter names to filter property constants.
 *
 * Uses the same naming convention as the SOAP API ($g_soap_api_to_filter_names).
 *
 * @return array
 */
function rest_issue_filter_param_map() {
	require_api( 'filter_constants_inc.php' );

	return array(
		'search'           => FILTER_PROPERTY_SEARCH,
		'handler_id'       => FILTER_PROPERTY_HANDLER_ID,
		'reporter_id'      => FILTER_PROPERTY_REPORTER_ID,
		'status_id'        => FILTER_PROPERTY_STATUS,
		'priority_id'      => FILTER_PROPERTY_PRIORITY,
		'severity_id'      => FILTER_PROPERTY_SEVERITY,
		'category'         => FILTER_PROPERTY_CATEGORY_ID,
		'resolution_id'    => FILTER_PROPERTY_RESOLUTION,
		'note_user_id'     => FILTER_PROPERTY_NOTE_USER_ID,
		'product_version'  => FILTER_PROPERTY_VERSION,
		'user_monitor_id'  => FILTER_PROPERTY_MONITOR_USER_ID,
		'hide_status_id'   => FILTER_PROPERTY_HIDE_STATUS,
		'sort'             => FILTER_PROPERTY_SORT_FIELD_NAME,
		'sort_direction'   => FILTER_PROPERTY_SORT_DIRECTION,
		'sticky'           => FILTER_PROPERTY_STICKY,
		'view_state'       => FILTER_PROPERTY_VIEW_STATE,
		'fixed_in_version' => FILTER_PROPERTY_FIXED_IN_VERSION,
		'target_version'   => FILTER_PROPERTY_TARGET_VERSION,
		'tag_string'       => FILTER_PROPERTY_TAG_STRING,
		'tag_select'       => FILTER_PROPERTY_TAG_SELECT,
	);
}

/**
 * Map of 'date submitted' query parameter names to filter property constants.
 *
 * @return array
 */
function rest_issue_date_submitted_param_map() {
	require_api( 'filter_constants_inc.php' );

	return array(
		'start_day'   => FILTER_PROPERTY_DATE_SUBMITTED_START_DAY,
		'start_month' => FILTER_PROPERTY_DATE_SUBMITTED_START_MONTH,
		'start_year'  => FILTER_PROPERTY_DATE_SUBMITTED_START_YEAR,
		'end_day'     => FILTER_PROPERTY_DATE_SUBMITTED_END_DAY,
		'end_month'   => FILTER_PROPERTY_DATE_SUBMITTED_END_MONTH,
		'end_year'    => FILTER_PROPERTY_DATE_SUBMITTED_END_YEAR,
	);
}

/**
 * Map of 'last updated' query parameter names to filter property constants.
 *
 * @return array
 */
function rest_issue_date_updated_param_map() {
	require_api( 'filter_constants_inc.php' );

	return array(
		'last_update_start_day'   => FILTER_PROPERTY_LAST_UPDATED_START_DAY,
		'last_update_start_month' => FILTER_PROPERTY_LAST_UPDATED_START_MONTH,
		'last_update_start_year'  => FILTER_PROPERTY_LAST_UPDATED_START_YEAR,
		'last_update_end_day'     => FILTER_PROPERTY_LAST_UPDATED_END_DAY,
		'last_update_end_month'   => FILTER_PROPERTY_LAST_UPDATED_END_MONTH,
		'last_update_end_year'    => FILTER_PROPERTY_LAST_UPDATED_END_YEAR,
	);
}

/**
 * Map of the ISO 8601 date query parameters (YYYY-MM-DD) to the legacy
 * day / month / year parameters and filter properties they expand to.
 *
 * @return array
 */
function rest_issue_iso_date_param_map() {
	require_api( 'filter_constants_inc.php' );

	return array(
		'created_after' => array(
			'legacy' => array( 'start_day', 'start_month', 'start_year' ),
			'props'  => array(
				'day'   => FILTER_PROPERTY_DATE_SUBMITTED_START_DAY,
				'month' => FILTER_PROPERTY_DATE_SUBMITTED_START_MONTH,
				'year'  => FILTER_PROPERTY_DATE_SUBMITTED_START_YEAR,
			),
			'flag'   => FILTER_PROPERTY_FILTER_BY_DATE_SUBMITTED,
		),
		'created_before' => array(
			'legacy' => array( 'end_day', 'end_month', 'end_year' ),
			'props'  => array(
				'day'   => FILTER_PROPERTY_DATE_SUBMITTED_END_DAY,
				'month' => FILTER_PROPERTY_DATE_SUBMITTED_END_MONTH,
				'year'  => FILTER_PROPERTY_DATE_SUBMITTED_END_YEAR,
			),
			'flag'   => FILTER_PROPERTY_FILTER_BY_DATE_SUBMITTED,
		),
		'updated_after' => array(
			'legacy' => array( 'last_update_start_day', 'last_update_start_month', 'last_update_start_year' ),
			'props'  => array(
				'day'   => FILTER_PROPERTY_LAST_UPDATED_START_DAY,
				'month' => FILTER_PROPERTY_LAST_UPDATED_START_MONTH,
				'year'  => FILTER_PROPERTY_LAST_UPDATED_START_YEAR,
			),
			'flag'   => FILTER_PROPERTY_FILTER_BY_LAST_UPDATED_DATE,
		),
		'updated_before' => array(
			'legacy' => array( 'last_update_end_day', 'last_update_end_month', 'last_update_end_year' ),
			'props'  => array(
				'day'   => FILTER_PROPERTY_LAST_UPDATED_END_DAY,
				'month' => FILTER_PROPERTY_LAST_UPDATED_END_MONTH,
				'year'  => FILTER_PROPERTY_LAST_UPDATED_END_YEAR,
			),
			'flag'   => FILTER_PROPERTY_FILTER_BY_LAST_UPDATED_DATE,
		),
	);
}

/**
 * The list of query parameters understood by the issues GET endpoint.
 *
 * @return array The supported query parameter names.
 */
function rest_issue_known_query_params() {
	return array_merge(
		array( 'id', 'project_id', 'filter_id', 'select', 'page', 'page_size',
			'count_only', 'fields' ),
		array_keys( rest_issue_filter_param_map() ),
		array_keys( rest_issue_date_submitted_param_map() ),
		array_keys( rest_issue_date_updated_param_map() ),
		array_keys( rest_issue_iso_date_param_map() )
	);
}

/**
 * Parse a date query parameter expressed in YYYY-MM-DD format.
 *
 * @param string $p_param_name The name of the query parameter.
 * @param string $p_value      The value to parse.
 *
 * @return array Array with 'year', 'month' and 'day' integer entries.
 *
 * @throws \Mantis\Exceptions\LegacyApiFaultException If the value is not a valid date.
 */
function rest_issue_parse_iso_date( $p_param_name, $p_value ) {
	$t_matches = array();
	if( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string)$p_value, $t_matches )
		&& checkdate( (int)$t_matches[2], (int)$t_matches[3], (int)$t_matches[1] ) ) {
		return array(
			'year'  => (int)$t_matches[1],
			'month' => (int)$t_matches[2],
			'day'   => (int)$t_matches[3],
		);
	}

	ApiObjectFactory::throwIfFault( ApiObjectFactory::faultBadRequest(
		"Invalid value '$p_value' for query parameter '$p_param_name', expected format 'YYYY-MM-DD'." ) );
}

/**
 * Check whether any part of a date bound (day / month / year) is present in the filter.
 *
 * @param array $p_filter The filter being built.
 * @param array $p_props  Map of date part => filter property constant.
 *
 * @return boolean
 */
function rest_issue_date_bound_is_set( array $p_filter, array $p_props ) {
	foreach( $p_props as $t_filter_prop ) {
		if( isset( $p_filter[$t_filter_prop] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Complete a half-open date range so that it means what it looks like it means.
 *
 * The filter core compares dates with a closed BETWEEN interval and, when one of
 * the bounds is missing, substitutes its own defaults (1st of the current month
 * for the lower bound, today for the upper one). An upper bound given on its own
 * therefore yields an inverted, empty interval, and a lower bound on its own gets
 * silently truncated at today. This fills in the open side instead:
 *
 * - upper bound only => lower bound becomes 1970-01-01, i.e. "anything up to X";
 * - lower bound only => upper bound becomes 2038-01-18, i.e. "anything from X on".
 *
 * The far upper bound is preferred over today's date because the core builds a
 * closed interval: using today would silently drop issues dated in the future
 * (clock skew, imported data), which is the same class of surprise being fixed
 * here. 2038-01-18 is used as the upper bound because it is safe for every
 * representation a timestamp may have on the way through the filter and the
 * database, while still being far enough in the future to never clip real data.
 *
 * Nothing is changed when both bounds, or neither, are present.
 *
 * @param array $p_filter      The filter being built, modified in place.
 * @param array $p_start_props Map of date part => filter property for the lower bound.
 * @param array $p_end_props   Map of date part => filter property for the upper bound.
 *
 * @return void
 */
function rest_issue_complete_date_range( array &$p_filter, array $p_start_props, array $p_end_props ) {
	$t_has_start = rest_issue_date_bound_is_set( $p_filter, $p_start_props );
	$t_has_end = rest_issue_date_bound_is_set( $p_filter, $p_end_props );

	if( $t_has_start == $t_has_end ) {
		return;
	}

	if( $t_has_end ) {
		$t_props = $p_start_props;
		$t_date = array( 'year' => 1970, 'month' => 1, 'day' => 1 );
	} else {
		$t_props = $p_end_props;
		$t_date = array( 'year' => 2038, 'month' => 1, 'day' => 18 );
	}

	foreach( $t_props as $t_date_part => $t_filter_prop ) {
		$p_filter[$t_filter_prop] = $t_date[$t_date_part];
	}
}

/**
 * The list of values accepted by the 'fields' query parameter.
 *
 * Unlike 'select', which trims down fully loaded issues, 'fields' selects a
 * cheaper code path that never loads the issues at all. Only the projections
 * that can be served straight from the filter query are listed here.
 *
 * @return array The supported 'fields' values.
 */
function rest_issue_fields_values() {
	return array( 'id' );
}

/**
 * Parse a boolean query parameter.
 *
 * @param string $p_param_name The name of the query parameter.
 * @param mixed  $p_value      The raw value.
 *
 * @return boolean
 *
 * @throws \Mantis\Exceptions\LegacyApiFaultException If the value is not a boolean.
 */
function rest_issue_parse_bool_param( $p_param_name, $p_value ) {
	$t_value = strtolower( trim( (string)$p_value ) );

	if( in_array( $t_value, array( '1', 'true', 'yes', 'on' ), true ) ) {
		return true;
	}

	if( in_array( $t_value, array( '0', 'false', 'no', 'off', '' ), true ) ) {
		return false;
	}

	ApiObjectFactory::throwIfFault( ApiObjectFactory::faultBadRequest(
		"Invalid value '$p_value' for query parameter '$p_param_name', expected a boolean." ) );
}

/**
 * Resolve the filter that a list request is asking for.
 *
 * Mirrors the filter resolution done by mc_filter_get_issues(), including its
 * access check, so that the lightweight code paths see exactly the same filter
 * (and therefore the same visibility rules) as the regular one.
 *
 * @param array|null     $p_inline_filter The inline filter, or null if none was given.
 * @param string|integer $p_filter_id     The requested filter id, may be empty.
 * @param integer        $p_project_id    The project id.
 * @param integer        $p_user_id       The user id.
 *
 * @return array The filter array, validated and ready for BugFilterQuery.
 *
 * @throws \Mantis\Exceptions\LegacyApiFaultException
 */
function rest_issue_resolve_filter( $p_inline_filter, $p_filter_id, $p_project_id, $p_user_id ) {
	require_api( 'filter_api.php' );

	if( $p_inline_filter !== null ) {
		return filter_ensure_valid_filter( $p_inline_filter );
	}

	if( !mci_has_readonly_access( $p_user_id, $p_project_id ) ) {
		ApiObjectFactory::throwIfFault( mci_fault_access_denied( $p_user_id ) );
	}

	$t_filter_id = is_blank( $p_filter_id ) ? FILTER_STANDARD_ANY : $p_filter_id;

	if( is_numeric( $t_filter_id ) ) {
		$t_filter = filter_get( $t_filter_id );
	} else {
		$t_filter = filter_standard_get( $t_filter_id, $p_user_id, $p_project_id );
	}

	if( $t_filter === null ) {
		ApiObjectFactory::throwIfFault( ApiObjectFactory::faultNotFound( "Unknown filter '$t_filter_id'" ) );
	}

	if( $t_filter === false ) {
		ApiObjectFactory::throwIfFault( ApiObjectFactory::faultServerError( "Invalid Filter '$t_filter_id'" ) );
	}

	return filter_ensure_valid_filter( $t_filter );
}

/**
 * Build a filter query of the requested type.
 *
 * All query types share the same FROM / JOIN / WHERE clauses (they only differ
 * in their SELECT list, see BugFilterQuery::helper_string_query_inner()), so the
 * project and view state restrictions applied to the regular listing apply here
 * unchanged.
 *
 * @param array   $p_filter     A validated filter array.
 * @param integer $p_project_id The project id.
 * @param integer $p_user_id    The user id.
 * @param integer $p_query_type One of the BugFilterQuery::QUERY_TYPE_* constants.
 *
 * @return BugFilterQuery
 */
function rest_issue_filter_query( array $p_filter, $p_project_id, $p_user_id, $p_query_type ) {
	return new BugFilterQuery( $p_filter, array(
		'query_type' => $p_query_type,
		'project_id' => $p_project_id,
		'user_id'    => $p_user_id,
		'use_sticky' => false,
	) );
}

/**
 * Number of pages a result set of the given size spans.
 *
 * Reproduces the pagination that filter_get_bug_rows() reports, so that the
 * lightweight code paths return the same page_count as the regular one.
 *
 * @param array   $p_filter    A validated filter array.
 * @param integer $p_bug_count The total number of matching issues.
 * @param integer $p_page_size The requested page size.
 *
 * @return array Array with 'page_count' and 'per_page' entries.
 */
function rest_issue_pagination( array $p_filter, $p_bug_count, $p_page_size ) {
	if( $p_bug_count == 0 ) {
		# filter_get_bug_rows() returns before computing pagination in this case
		return array( 'page_count' => 0, 'per_page' => (int)$p_page_size );
	}

	$t_per_page = filter_per_page( $p_filter, $p_bug_count, $p_page_size );

	return array(
		'page_count' => filter_page_count( $p_bug_count, $t_per_page ),
		'per_page'   => $t_per_page,
	);
}

/**
 * A method that does the work to handle getting an issue via REST API.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @throws \Mantis\Exceptions\LegacyApiFaultException
 */
function rest_issue_get( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = isset( $p_args['id'] ) ? $p_args['id'] : $p_request->getParam( 'id' );

	# Warnings about the request that don't prevent it from being served.
	$t_warnings = array();
	$t_known_query_params = rest_issue_known_query_params();
	foreach( array_keys( $p_request->getQueryParams() ) as $t_query_param ) {
		if( !in_array( $t_query_param, $t_known_query_params, true ) ) {
			$t_warnings[] = "Unknown query parameter '$t_query_param' was ignored.";
		}
	}

	$t_select = $p_request->getParam( 'select', null );
	if( $t_select !== null ) {
		$t_select = explode( ',', $t_select );
		$t_select = array_map( 'trim', $t_select );
		$t_select = array_values( array_filter( $t_select, 'strlen' ) );

		$t_unknown_fields = array_diff( $t_select, rest_issue_select_fields() );
		if( !empty( $t_unknown_fields ) ) {
			ApiObjectFactory::throwIfFault( ApiObjectFactory::faultBadRequest(
				"Unknown field(s) in 'select': " . implode( ', ', $t_unknown_fields )
				. '. Supported fields are: ' . implode( ', ', rest_issue_select_fields() ) . '.' ) );
		}
	}

	# Lightweight projections: 'count_only' returns just the totals, 'fields=id'
	# returns just the issue ids. Both skip loading the issues altogether.
	$t_count_only = false;
	if( $p_request->getParam( 'count_only', null ) !== null ) {
		$t_count_only = rest_issue_parse_bool_param( 'count_only', $p_request->getParam( 'count_only' ) );
	}

	$t_fields = $p_request->getParam( 'fields', null );
	if( $t_fields !== null ) {
		$t_fields = trim( $t_fields );
		if( !in_array( $t_fields, rest_issue_fields_values(), true ) ) {
			ApiObjectFactory::throwIfFault( ApiObjectFactory::faultBadRequest(
				"Unknown value '$t_fields' for query parameter 'fields'. Supported values are: "
				. implode( ', ', rest_issue_fields_values() )
				. ". To trim down fully loaded issues, use 'select' instead." ) );
		}
	}

	if( $t_count_only && $t_fields !== null ) {
		ApiObjectFactory::throwIfFault( ApiObjectFactory::faultBadRequest(
			"Query parameters 'count_only' and 'fields' are mutually exclusive." ) );
	}

	if( $t_fields !== null ) {
		# The id projection queries the ids directly and orders them by id descending,
		# it never goes through the filter's own sort columns. Say so instead of
		# returning a differently ordered page than the caller asked for.
		foreach( array( 'sort', 'sort_direction' ) as $t_sort_param ) {
			if( $p_request->getParam( $t_sort_param, null ) !== null ) {
				$t_warnings[] = "Query parameter '$t_sort_param' was ignored: "
					. "'fields=$t_fields' always returns ids ordered by id descending.";
			}
		}

		if( $t_select !== null ) {
			$t_warnings[] = "Query parameter 'select' was ignored: "
				. "'fields=$t_fields' returns issue ids only.";
		}
	}

	if( !is_blank( $t_issue_id ) && ( $t_count_only || $t_fields !== null ) ) {
		ApiObjectFactory::throwIfFault( ApiObjectFactory::faultBadRequest(
			"Query parameters 'count_only' and 'fields' are not supported together with 'id'." ) );
	}

	if( !is_blank( $t_issue_id ) ) {
		# Get Issue By Id

		# Username and password below are ignored, since middleware already done the auth.
		$t_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id, $t_select );
		ApiObjectFactory::throwIfFault( $t_issue );

		$t_result = array( 'issues' => array( $t_issue ) );
	} else {
		$t_page_number = $p_request->getParam( 'page', 1 );
		$t_page_size = $p_request->getParam( 'page_size', 50 );

		# Get a set of issues
		$t_project_id = (int)$p_request->getParam( 'project_id', ALL_PROJECTS );
		if( $t_project_id != ALL_PROJECTS ) {
			$t_message = "Project '$t_project_id' doesn't exist";
			if (!project_exists( $t_project_id ) ) {
				return $p_response->withStatus( HTTP_STATUS_NOT_FOUND, $t_message );
			}

			$t_user_id = auth_get_current_user_id();
			if( !access_has_project_level( VIEWER, $t_project_id, $t_user_id ) ) {
				return $p_response->withStatus( HTTP_STATUS_NOT_FOUND, $t_message );
			}
		}

		$t_filter_id = trim( $p_request->getParam( 'filter_id', '' ) );
		# set the current project to correctly account for user permissions
		helper_set_current_project( $t_project_id );

		# Check for inline filter parameters (handler_id, reporter_id, status_id, etc.)
		$t_inline_filter = rest_issue_build_inline_filter( $p_request, $t_project_id, $t_warnings );

		# Total number of matching issues and pages, populated by the filter calls below.
		$t_page_count = 0;
		$t_bug_count = 0;

		if( $t_count_only || $t_fields !== null ) {
			# Lightweight paths: resolve the filter once and query it directly,
			# without ever materializing BugData objects.
			$t_user_id = auth_get_current_user_id();
			$t_filter = rest_issue_resolve_filter(
				$t_inline_filter, $t_filter_id, $t_project_id, $t_user_id );

			# TODO: we should have a better way to do this.
			global $g_project_override;
			$g_project_override = $t_project_id;

			if( $t_count_only ) {
				$t_query = rest_issue_filter_query( $t_filter, $t_project_id, $t_user_id,
					BugFilterQuery::QUERY_TYPE_COUNT );
				$t_query->execute();
				$t_bug_count = (int)$t_query->value();

				$t_pagination = rest_issue_pagination( $t_filter, $t_bug_count, $t_page_size );

				$t_result = array(
					'total_count' => $t_bug_count,
					'page_count' => (int)$t_pagination['page_count'],
				);
			} else {
				$t_subquery = rest_issue_filter_query( $t_filter, $t_project_id, $t_user_id,
					BugFilterQuery::QUERY_TYPE_DISTINCT_IDS );
				$t_bug_count = $t_subquery->get_bug_count();

				$t_pagination = rest_issue_pagination( $t_filter, $t_bug_count, $t_page_size );
				$t_page_count = (int)$t_pagination['page_count'];
				$t_page_number = filter_valid_page_number( $t_page_number, $t_page_count );

				$t_ids = array();
				if( $t_bug_count > 0 ) {
					# The id list is ordered here rather than by the filter's own sort
					# columns: those live on joined tables that this query doesn't need,
					# and paging requires a deterministic, stable order.
					$t_ids_query = new DbQuery(
						'SELECT {bug}.id FROM {bug} WHERE {bug}.id IN :filter_ids'
						. ' ORDER BY {bug}.id DESC' );
					$t_ids_query->bind( 'filter_ids', $t_subquery );
					$t_ids_query->execute( array(), $t_pagination['per_page'],
						filter_offset( $t_page_number, $t_pagination['per_page'] ) );

					foreach( $t_ids_query->fetch_all() as $t_row ) {
						$t_ids[] = array( 'id' => (int)$t_row['id'] );
					}
				}

				$t_result = array(
					'issues' => $t_ids,
					'total_count' => $t_bug_count,
					'page_count' => $t_page_count,
				);
			}
		} else if( $t_inline_filter !== null ) {
			# Use inline filter built from query parameters
			$t_user_id = auth_get_current_user_id();
			$t_lang = mci_get_user_lang( $t_user_id );

			$t_orig_page_number = $t_page_number < 1 ? 1 : $t_page_number;
			$t_page_count = 0;
			$t_bug_count = 0;
			$t_show_sticky = false;

			global $g_project_override;
			$g_project_override = $t_project_id;

			$t_rows = filter_get_bug_rows(
				$t_page_number, $t_page_size, $t_page_count, $t_bug_count,
				$t_inline_filter, $t_project_id, $t_user_id, $t_show_sticky );

			$t_issues = array();
			if( $t_orig_page_number <= $t_page_number ) {
				foreach( $t_rows as $t_issue_data ) {
					$t_issues[] = mci_issue_data_as_array( $t_issue_data, $t_user_id, $t_lang, $t_select );
				}
			}
		} else if( !empty( $t_filter_id ) ) {
			$t_issues = mc_filter_get_issues(
				'', '', $t_project_id, $t_filter_id, $t_page_number, $t_page_size, $t_select,
				$t_page_count, $t_bug_count );
		} else {
			$t_issues = mc_filter_get_issues(
				'', '', $t_project_id, FILTER_STANDARD_ANY, $t_page_number, $t_page_size, $t_select,
				$t_page_count, $t_bug_count );
		}

		if( !isset( $t_result ) ) {
			$t_result = array(
				'issues' => $t_issues,
				'total_count' => (int)$t_bug_count,
				'page_count' => (int)$t_page_count,
			);
		}
	}

	if( !empty( $t_warnings ) ) {
		$t_result['warnings'] = $t_warnings;
	}

	$t_etag = mc_issue_hash( $t_issue_id, $t_result );
	if( $p_request->hasHeader( HEADER_IF_NONE_MATCH ) ) {
		$t_match_etag = $p_request->getHeaderLine( HEADER_IF_NONE_MATCH );
		if( $t_etag == $t_match_etag ) {
			return $p_response->withStatus( HTTP_STATUS_NOT_MODIFIED, 'Not Modified' )
				->withHeader( HEADER_ETAG, $t_etag );
		}
	}

	if( $t_result !== null ) {
		$p_response = $p_response->withStatus( HTTP_STATUS_SUCCESS )->withJson( $t_result );
	}

	return $p_response->withHeader( HEADER_ETAG, $t_etag );
}

/**
 * Build an inline filter array from REST API query parameters.
 *
 * Supports the same filter parameters as the SOAP API's mc_filter_search_issues,
 * allowing ad-hoc filtering without requiring a saved filter.
 *
 * Supported parameters: search, handler_id, reporter_id, status_id, priority_id,
 * severity_id, category, resolution_id, tag_string, and date range fields.
 *
 * @param \Slim\Http\Request $p_request    The request.
 * @param integer            $p_project_id The project id.
 * @param array              $p_warnings   Warnings about the request, appended to.
 * @return array|null Filter array or null if no inline filter params provided.
 *
 * @throws \Mantis\Exceptions\LegacyApiFaultException
 */
function rest_issue_build_inline_filter( \Slim\Http\Request $p_request, $p_project_id, array &$p_warnings = array() ) {
	require_api( 'filter_constants_inc.php' );

	$s_param_to_filter = rest_issue_filter_param_map();

	# Check if any inline filter parameters are present
	$t_has_filter_param = false;
	foreach( $s_param_to_filter as $t_param_name => $t_filter_prop ) {
		if( $p_request->getParam( $t_param_name ) !== null ) {
			$t_has_filter_param = true;
			break;
		}
	}

	# Also check date range parameters
	$s_date_params = array_merge(
		array_keys( rest_issue_date_submitted_param_map() ),
		array_keys( rest_issue_date_updated_param_map() ),
		array_keys( rest_issue_iso_date_param_map() )
	);

	if( !$t_has_filter_param ) {
		foreach( $s_date_params as $t_param_name ) {
			if( $p_request->getParam( $t_param_name ) !== null ) {
				$t_has_filter_param = true;
				break;
			}
		}
	}

	if( !$t_has_filter_param ) {
		return null;
	}

	# Build the filter array
	$t_filter = array( '_view_type' => FILTER_VIEW_TYPE_ADVANCED );
	$t_filter['project_id'] = array( $p_project_id );

	foreach( $s_param_to_filter as $t_param_name => $t_filter_prop ) {
		$t_value = $p_request->getParam( $t_param_name );
		if( $t_value !== null ) {
			# Handle comma-separated values for multi-value fields (e.g. status_id=10,50,80)
			if( is_string( $t_value ) && strpos( $t_value, ',' ) !== false
				&& $t_filter_prop !== FILTER_PROPERTY_SEARCH
				&& $t_filter_prop !== FILTER_PROPERTY_TAG_STRING ) {
				$t_value = array_map( 'trim', explode( ',', $t_value ) );
			}
			$t_filter[$t_filter_prop] = $t_value;
		}
	}

	# Date range: submitted date
	$s_date_submitted_map = rest_issue_date_submitted_param_map();

	$t_has_date_submitted = false;
	foreach( $s_date_submitted_map as $t_param_name => $t_filter_prop ) {
		$t_value = $p_request->getParam( $t_param_name );
		if( $t_value !== null ) {
			$t_filter[$t_filter_prop] = (int)$t_value;
			$t_has_date_submitted = true;
		}
	}
	if( $t_has_date_submitted ) {
		$t_filter[FILTER_PROPERTY_FILTER_BY_DATE_SUBMITTED] = 'on';
	}

	# Date range: last updated
	$s_date_updated_map = rest_issue_date_updated_param_map();

	$t_has_date_updated = false;
	foreach( $s_date_updated_map as $t_param_name => $t_filter_prop ) {
		$t_value = $p_request->getParam( $t_param_name );
		if( $t_value !== null ) {
			$t_filter[$t_filter_prop] = (int)$t_value;
			$t_has_date_updated = true;
		}
	}
	if( $t_has_date_updated ) {
		$t_filter[FILTER_PROPERTY_FILTER_BY_LAST_UPDATED_DATE] = 'on';
	}

	# Date range: ISO 8601 (YYYY-MM-DD) shorthands, expanded to the day/month/year
	# fields above; when both forms are given, the ISO one wins.
	foreach( rest_issue_iso_date_param_map() as $t_param_name => $t_param_info ) {
		$t_value = $p_request->getParam( $t_param_name );
		if( $t_value === null ) {
			continue;
		}

		$t_date = rest_issue_parse_iso_date( $t_param_name, $t_value );

		foreach( $t_param_info['legacy'] as $t_legacy_param ) {
			if( $p_request->getParam( $t_legacy_param ) !== null ) {
				$p_warnings[] = "Query parameter '$t_legacy_param' was overridden by '$t_param_name'.";
			}
		}

		foreach( $t_param_info['props'] as $t_date_part => $t_filter_prop ) {
			$t_filter[$t_filter_prop] = $t_date[$t_date_part];
		}
		$t_filter[$t_param_info['flag']] = 'on';
	}

	# A range given with only one of its two bounds would otherwise be completed
	# with the core's defaults, which makes an upper bound on its own match nothing.
	$t_iso_map = rest_issue_iso_date_param_map();
	rest_issue_complete_date_range( $t_filter,
		$t_iso_map['created_after']['props'], $t_iso_map['created_before']['props'] );
	rest_issue_complete_date_range( $t_filter,
		$t_iso_map['updated_after']['props'], $t_iso_map['updated_before']['props'] );

	return filter_ensure_valid_filter( $t_filter );
}

/**
 * Create an issue from a POST to the issues url.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @throws ClientException
 *
 * @noinspection PhpUnusedParameterInspection
 */
function rest_issue_add( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue = $p_request->getParsedBody();
	if( !$t_issue ) {
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST, "Invalid request body or format");
	}

	if( isset( $t_issue['files'] ) ) {
		$t_issue['files'] = files_base64_to_temp( $t_issue['files'] );
	}

	$t_data = array( 'payload' => array( 'issue' => $t_issue ) );
	$t_command = new IssueAddCommand( $t_data );
	$t_result = $t_command->execute();
	$t_issue_id = (int)$t_result['issue_id'];

	$t_created_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );

	return $p_response->withStatus( HTTP_STATUS_CREATED, "Issue Created with id $t_issue_id" )->
		withJson( array( 'issue' => $t_created_issue ) );
}

/**
 * Delete an issue given its id.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @throws \Mantis\Exceptions\LegacyApiFaultException
 */
function rest_issue_delete( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = isset( $p_args['id'] ) ? $p_args['id'] : $p_request->getParam( 'id' );

	$t_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );
	ApiObjectFactory::throwIfFault( $t_issue );

	$t_etag = mc_issue_hash( $t_issue_id, array( 'issues' => array( $t_issue ) ) );

	if( $p_request->hasHeader( HEADER_IF_MATCH ) ) {
		$t_match_etag = $p_request->getHeaderLine( HEADER_IF_MATCH );
		if( $t_etag != $t_match_etag ) {
			return $p_response->withStatus( HTTP_STATUS_PRECONDITION_FAILED, 'Precondition Failed' )
				->withHeader( HEADER_ETAG, $t_etag );
		}
	}

	$t_data = array( 'query' => array( 'id' => $t_issue_id ) );
	$t_command = new IssueDeleteCommand( $t_data );
	$t_command->execute();

	return $p_response->withStatus( HTTP_STATUS_NO_CONTENT )
		->withHeader( HEADER_ETAG, mc_issue_hash( $t_issue_id, null ) );
}

/**
 * Add issue file.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @throws ClientException
 */
function rest_issue_file_add( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = isset( $p_args['id'] ) ? $p_args['id'] : $p_request->getParam( 'id' );

	$t_data = array(
		'query' => array( 'issue_id' => $t_issue_id ),
		'payload' => $p_request->getParsedBody(),
	);

	if( isset( $t_data['payload']['files'] ) ) {
		$t_data['payload']['files'] = files_base64_to_temp( $t_data['payload']['files'] );
	}

	$t_command = new IssueFileAddCommand( $t_data );
	$t_command->execute();

	return $p_response->withStatus( HTTP_STATUS_CREATED, "Issue File(s) Attached" );
}

/**
 * Add issue note.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @throws ClientException
 */
function rest_issue_note_add( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = isset( $p_args['id'] ) ? $p_args['id'] : $p_request->getParam( 'id' );

	$t_payload = $p_request->getParsedBody();

	# The body is the note itself, so the flag is dropped before the payload
	# reaches the command, which would reject an unknown field.
	$t_suppress = rest_issue_suppress_requested( $p_request );
	if( is_array( $t_payload ) ) {
		unset( $t_payload['suppress_notifications'] );
	}

	$t_data = array(
		'query' => array( 'issue_id' => $t_issue_id ),
		'payload' => $t_payload,
	);

	if( isset( $t_data['payload']['files'] ) ) {
		$t_data['payload']['files'] = files_base64_to_temp( $t_data['payload']['files'] );
	}

	$t_command_response = rest_issue_with_notifications_suppressed( $t_suppress,
		function() use ( $t_data ) {
			$t_command = new IssueNoteAddCommand( $t_data );
			return $t_command->execute();
		}
	);

	# TODO: Move construction of response to the command and add options to allow callers to
	# determine whether the response is needed.  This will need refactoring of APIs that construct
	# notes and issues in responses.
	$t_note_id = $t_command_response['id'];

	$t_note = null;
	$t_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );
	foreach( $t_issue['notes'] as $t_current_note ) {
		if( $t_current_note['id'] == $t_note_id ) {
			$t_note = $t_current_note;
			break;
		}
	}

	return $p_response
		->withStatus( HTTP_STATUS_CREATED, "Issue Note Created with id $t_issue_id" )
		->withJson( array( 'note' => $t_note, 'issue' => $t_issue ) );
}

/**
 * Delete issue note.
 *
 * @param \Slim\Http\Request $p_request   The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 */
function rest_issue_note_delete( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = isset( $p_args['id'] ) ? $p_args['id'] : $p_request->getParam( 'id' );
	$t_issue_note_id = isset( $p_args['note_id'] ) ? $p_args['note_id'] : $p_request->getParam( 'note_id' );

	$t_data = array(
		'query' => array(
			'id' => $t_issue_note_id,
			'issue_id' => $t_issue_id )
	);

	$t_command = new IssueNoteDeleteCommand( $t_data );
	$t_command->execute();

	$t_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );
	return $p_response->withStatus( HTTP_STATUS_SUCCESS, 'Issue Note Deleted' )->
		withJson( array( 'issue' => $t_issue ) );
}

/**
 * Add relationship to issue.
 *
 * @param \Slim\Http\Request $p_request   The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 */
function rest_issue_relationship_add( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = $p_args['id'];

	$t_data = array(
		'query' => array( 'issue_id' => $t_issue_id ),
		'payload' => $p_request->getParsedBody(),
	);

	$t_command = new IssueRelationshipAddCommand( $t_data );
	$t_command_response = $t_command->execute();

	$t_relationship_id = $t_command_response['id'];

	$t_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );

	return $p_response->withStatus(
		HTTP_STATUS_CREATED,
		"Issue relationship created with id $t_relationship_id" )->
			withJson( array( 'issue' => $t_issue ) );
}

/**
 * Delete issue relationship.
 *
 * @param \Slim\Http\Request $p_request   The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @noinspection PhpUnusedParameterInspection
 */
function rest_issue_relationship_delete( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = $p_args['id'];
	$t_relationship_id = $p_args['relationship_id'];

	$t_data = array(
		'query' => array(
			'relationship_id' => $t_relationship_id,
			'issue_id' => $t_issue_id )
	);

	$t_command = new IssueRelationshipDeleteCommand( $t_data );
	$t_command->execute();

	$t_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );
	return $p_response->withStatus( HTTP_STATUS_SUCCESS, 'Issue relationship deleted' )->
		withJson( array( 'issue' => $t_issue ) );
}

/**
 * Update an issue from a PATCH to the issues url.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @throws \Mantis\Exceptions\LegacyApiFaultException
 */
function rest_issue_update( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = isset( $p_args['id'] ) ? $p_args['id'] : $p_request->getParam( 'id' );
	if( is_blank( $t_issue_id ) ) {
		$t_message = "Mandatory field 'id' is missing.";
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST, $t_message );
	}

	$t_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );
	ApiObjectFactory::throwIfFault( $t_issue );

	$t_etag = mc_issue_hash( $t_issue_id, array( 'issues' => array( $t_issue ) ) );

	if( $p_request->hasHeader( HEADER_IF_MATCH ) ) {
		$t_match_etag = $p_request->getHeaderLine( HEADER_IF_MATCH );
		if( $t_etag != $t_match_etag ) {
			return $p_response->withStatus( HTTP_STATUS_PRECONDITION_FAILED, 'Precondition Failed' )
				->withHeader( HEADER_ETAG, $t_etag );
		}
	}

	# Construct full issue from issue from db + patched info
	$t_issue_patch = $p_request->getParsedBody();
	if( !$t_issue_patch ) {
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST, "Invalid request body or format");
	}
	if( isset( $t_issue_patch['id'] ) && $t_issue_patch['id'] != $t_issue_id ) {
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST, 'Issue id mismatch' );
	}

	$t_issue = (object)array_merge( $t_issue, $t_issue_patch );

	# Trigger the issue update
	$t_result = rest_issue_with_notifications_suppressed(
		rest_issue_suppress_requested( $p_request ),
		function() use ( $t_issue_id, $t_issue ) {
			return mc_issue_update( /* username */ '', /* password */ '', $t_issue_id, $t_issue );
		}
	);
	ApiObjectFactory::throwIfFault( $t_result );

	$t_updated_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );
	$t_result = array( 'issues' => array( $t_updated_issue ) );

	return $p_response->withStatus( HTTP_STATUS_SUCCESS, "Issue with id $t_issue_id Updated" )
		->withHeader( HEADER_ETAG, mc_issue_hash( $t_issue_id, $t_result ) )
		->withJson( $t_result );
}

/**
 * Maximum number of issue ids accepted by a single bulk request.
 *
 * Bulk operations run every issue through the same code path as the single-issue
 * operation (history, events, notifications), so the batch size is capped to keep
 * a single request within reasonable time and memory limits.
 *
 * A bulk request is NOT atomic: issues are processed one by one and each one is
 * committed on its own. If the request is interrupted (timeout, connection drop,
 * fatal error), the issues already processed stay modified while the remaining
 * ones are left untouched, and no rollback takes place.
 */
if( !defined( 'REST_BULK_MAX_ISSUES' ) ) {
	define( 'REST_BULK_MAX_ISSUES', 100 );
}

/**
 * Extract and validate the 'ids' array of a bulk request body.
 *
 * Duplicate ids are collapsed so that each issue is processed exactly once,
 * while the order of first appearance is preserved.
 *
 * Only a JSON integer or a plain digit string is accepted. Anything else --
 * floats, exponent notation ('1e2'), hex, values with whitespace or a sign,
 * numbers beyond the integer range -- is rejected for the whole request rather
 * than silently cast, because a cast turns a bogus id into a real, unrelated
 * issue id ('1e2' becomes 100) and the update would hit the wrong issue.
 *
 * @param mixed  $p_body  The parsed request body.
 * @param string $p_error Set to the error message when validation fails.
 * @return array|null The list of unique issue ids, or null on error.
 */
function rest_issue_bulk_ids( $p_body, &$p_error ) {
	$p_error = null;

	if( !is_array( $p_body ) ) {
		$p_error = 'Invalid request body or format';
		return null;
	}

	if( !isset( $p_body['ids'] ) || !is_array( $p_body['ids'] ) ) {
		$p_error = "Mandatory field 'ids' is missing or is not an array.";
		return null;
	}

	if( count( $p_body['ids'] ) == 0 ) {
		$p_error = "Mandatory field 'ids' must contain at least one issue id.";
		return null;
	}

	$t_ids = array();
	$t_index = 0;
	foreach( $p_body['ids'] as $t_raw_id ) {
		$t_id = rest_issue_bulk_parse_id( $t_raw_id );
		if( $t_id === null ) {
			$p_error = "Invalid issue id at index $t_index: "
				. rest_issue_bulk_describe_value( $t_raw_id )
				. '. Expected a positive integer.';
			return null;
		}

		# Duplicates are processed once.
		$t_ids[$t_id] = $t_id;
		$t_index++;
	}

	$t_ids = array_values( $t_ids );

	if( count( $t_ids ) > REST_BULK_MAX_ISSUES ) {
		$p_error = 'A bulk request is limited to ' . REST_BULK_MAX_ISSUES
			. ' issues, ' . count( $t_ids ) . ' provided.';
		return null;
	}

	return $t_ids;
}

/**
 * Extract the per-issue entries of a bulk note request.
 *
 * Two shapes are accepted. The classic one pairs 'ids' with a single shared
 * 'note'. The second passes 'issues', a list of { id, note } pairs, so that every
 * issue gets its own text - which is what a mass close needs: the reason an issue
 * was closed is per issue, and a shared note throws exactly that away.
 *
 * @param mixed  $p_body  The parsed request body.
 * @param string $p_error Set to the error message when validation fails.
 * @return array|null List of array( id, note ) pairs, or null on error.
 */
function rest_issue_bulk_note_entries( $p_body, &$p_error ) {
	$p_error = null;

	if( !is_array( $p_body ) ) {
		$p_error = 'Invalid request body or format';
		return null;
	}

	# Shared note: 'ids' + 'note', validated by the same helper as every other
	# bulk endpoint.
	if( !isset( $p_body['issues'] ) ) {
		$t_ids = rest_issue_bulk_ids( $p_body, $p_error );
		if( $t_ids === null ) {
			return null;
		}

		if( !isset( $p_body['note'] ) || !is_array( $p_body['note'] ) || count( $p_body['note'] ) == 0 ) {
			$p_error = "Mandatory field 'note' is missing or empty.";
			return null;
		}

		if( isset( $p_body['note']['files'] ) ) {
			$p_error = "Field 'files' is not supported for bulk notes.";
			return null;
		}

		$t_entries = array();
		foreach( $t_ids as $t_id ) {
			$t_entries[] = array( $t_id, $p_body['note'] );
		}

		return $t_entries;
	}

	if( isset( $p_body['ids'] ) || isset( $p_body['note'] ) ) {
		$p_error = "Field 'issues' cannot be combined with 'ids' or 'note'.";
		return null;
	}

	if( !is_array( $p_body['issues'] ) || count( $p_body['issues'] ) == 0 ) {
		$p_error = "Field 'issues' must be a non-empty array.";
		return null;
	}

	$t_entries = array();
	$t_seen = array();
	$t_index = 0;
	foreach( $p_body['issues'] as $t_entry ) {
		if( !is_array( $t_entry ) || !isset( $t_entry['id'] ) ) {
			$p_error = "Entry at index $t_index must be an object with an 'id'.";
			return null;
		}

		$t_id = rest_issue_bulk_parse_id( $t_entry['id'] );
		if( $t_id === null ) {
			$p_error = "Invalid issue id at index $t_index: "
				. rest_issue_bulk_describe_value( $t_entry['id'] )
				. '. Expected a positive integer.';
			return null;
		}

		if( !isset( $t_entry['note'] ) || !is_array( $t_entry['note'] ) || count( $t_entry['note'] ) == 0 ) {
			$p_error = "Entry at index $t_index (issue $t_id) is missing a non-empty 'note'.";
			return null;
		}

		if( isset( $t_entry['note']['files'] ) ) {
			$p_error = "Field 'files' is not supported for bulk notes (issue $t_id).";
			return null;
		}

		# Unlike 'ids', a repeated id here is a mistake worth reporting: the two
		# entries carry different texts and silently dropping one loses a note.
		if( isset( $t_seen[$t_id] ) ) {
			$p_error = "Issue $t_id appears more than once in 'issues'.";
			return null;
		}

		$t_seen[$t_id] = true;
		$t_entries[] = array( $t_id, $t_entry['note'] );
		$t_index++;
	}

	if( count( $t_entries ) > REST_BULK_MAX_ISSUES ) {
		$p_error = 'A bulk request is limited to ' . REST_BULK_MAX_ISSUES
			. ' issues, ' . count( $t_entries ) . ' provided.';
		return null;
	}

	return $t_entries;
}

/**
 * Parse a single entry of a bulk 'ids' array into a positive issue id.
 *
 * @param mixed $p_raw_id The raw value as it came out of the JSON body.
 * @return integer|null The issue id, or null when the value is not a valid id.
 */
function rest_issue_bulk_parse_id( $p_raw_id ) {
	if( is_int( $p_raw_id ) ) {
		return $p_raw_id > 0 ? $p_raw_id : null;
	}

	# A digit-only string, no sign, no whitespace, no decimal point, no exponent.
	if( is_string( $p_raw_id ) && preg_match( '/^[0-9]+$/', $p_raw_id ) ) {
		# Reject values that don't survive the round-trip through an integer,
		# i.e. anything above PHP_INT_MAX.
		$t_id = (int)$p_raw_id;
		if( $t_id > 0 && (string)$t_id === ltrim( $p_raw_id, '0' ) ) {
			return $t_id;
		}
	}

	return null;
}

/**
 * Render a rejected value for an error message, without echoing back a large input.
 *
 * @param mixed $p_value The value to describe.
 * @return string
 */
function rest_issue_bulk_describe_value( $p_value ) {
	if( !is_scalar( $p_value ) ) {
		return gettype( $p_value );
	}

	if( is_bool( $p_value ) ) {
		return $p_value ? 'true' : 'false';
	}

	$t_value = (string)$p_value;
	if( mb_strlen( $t_value ) > 32 ) {
		$t_value = mb_substr( $t_value, 0, 32 ) . '...';
	}

	return "'" . $t_value . "'";
}

/**
 * Determine whether notifications should be suppressed for a bulk request.
 *
 * @param array $p_body The parsed request body.
 * @return boolean
 */
function rest_issue_bulk_suppress_notifications( array $p_body ) {
	if( !isset( $p_body['suppress_notifications'] ) ) {
		return false;
	}

	$t_value = $p_body['suppress_notifications'];
	if( is_bool( $t_value ) ) {
		return $t_value;
	}

	return in_array( strtolower( (string)$t_value ), array( '1', 'true', 'yes', 'on' ), true );
}

/**
 * Interpret a truthy flag as it arrives from JSON or from a query string.
 *
 * @param mixed $p_value The raw value.
 * @return boolean
 */
function rest_issue_flag_is_set( $p_value ) {
	if( is_bool( $p_value ) ) {
		return $p_value;
	}

	return in_array( strtolower( (string)$p_value ), array( '1', 'true', 'yes', 'on' ), true );
}

/**
 * Determine whether notifications should be suppressed for a single-issue request.
 *
 * The flag is read from the query string first, because on the note endpoint the
 * request body IS the note payload and must not carry unrelated fields.
 *
 * @param \Slim\Http\Request $p_request The request.
 * @return boolean
 */
function rest_issue_suppress_requested( \Slim\Http\Request $p_request ) {
	$t_param = $p_request->getQueryParam( 'suppress_notifications' );
	if( $t_param !== null ) {
		return rest_issue_flag_is_set( $t_param );
	}

	$t_body = $p_request->getParsedBody();
	if( is_array( $t_body ) && isset( $t_body['suppress_notifications'] ) ) {
		return rest_issue_flag_is_set( $t_body['suppress_notifications'] );
	}

	return false;
}

/**
 * Run a callback with e-mail notifications turned off.
 *
 * The switch is the global notification config, the same mechanism used by
 * project_delete(), so it silences EVERY notification raised while the callback
 * runs - not only those of the issues being processed. That is harmless within
 * one request, which touches only what the caller asked for, but it does mean
 * the flag must never be left set: the restore runs from a finally block so a
 * fatal error in the callback cannot leak a silenced instance.
 *
 * @param boolean  $p_suppress Whether to suppress.
 * @param callable $p_callback The work to run.
 * @return mixed The callback's return value.
 */
function rest_issue_with_notifications_suppressed( $p_suppress, callable $p_callback ) {
	if( !$p_suppress ) {
		return $p_callback();
	}

	$t_saved = config_get( 'enable_email_notification' );
	config_set_cache( 'enable_email_notification', OFF, CONFIG_TYPE_INT );

	try {
		return $p_callback();
	} finally {
		config_set_cache( 'enable_email_notification', $t_saved, CONFIG_TYPE_INT );
	}
}

/**
 * Determine whether a bulk request is a dry run.
 *
 * @param array $p_body The parsed request body.
 * @return boolean
 */
function rest_issue_bulk_dry_run( array $p_body ) {
	return isset( $p_body['dry_run'] ) && rest_issue_flag_is_set( $p_body['dry_run'] );
}

/**
 * Build the response of a dry run: everything the request resolved to, nothing applied.
 *
 * The point is to answer "what exactly would this do" before it does it, so the
 * ids are echoed in full - a count alone hides the one wrong id that matters.
 *
 * @param array $p_ids    The issue ids the request resolved to.
 * @param array $p_body   The parsed request body.
 * @param array $p_detail Endpoint-specific preview of the payload.
 * @return array
 */
function rest_issue_bulk_dry_run_result( array $p_ids, array $p_body, array $p_detail ) {
	return array_merge( array(
		'dry_run' => true,
		'issue_count' => count( $p_ids ),
		'ids' => $p_ids,
		'suppress_notifications' => rest_issue_bulk_suppress_notifications( $p_body ),
		'tag' => isset( $p_body['tag'] ) ? $p_body['tag'] : null,
	), $p_detail );
}

/**
 * Resolve the 'tag' field of a bulk request into a tag id, creating the tag when needed.
 *
 * A mass operation is only as safe as its undo. Stamping every issue it touched
 * with one tag turns "revert yesterday's sweep" into a filter, instead of a hunt
 * through note texts.
 *
 * @param array  $p_body  The parsed request body.
 * @param string $p_error Set to the error message when the tag cannot be resolved.
 * @return integer The tag id, or 0 when no tag was requested, or -1 on error.
 */
function rest_issue_bulk_tag_id( array $p_body, &$p_error ) {
	$p_error = null;

	if( !isset( $p_body['tag'] ) ) {
		return 0;
	}

	$t_name = $p_body['tag'];
	if( !is_string( $t_name ) || trim( $t_name ) === '' ) {
		$p_error = "Field 'tag' must be a non-empty string.";
		return -1;
	}

	$t_name = trim( $t_name );
	$t_matches = array();
	if( !tag_name_is_valid( $t_name, $t_matches ) ) {
		$p_error = "Invalid tag name '$t_name'.";
		return -1;
	}

	$t_tag = tag_get_by_name( $t_name );
	if( $t_tag !== false ) {
		return (int)$t_tag['id'];
	}

	if( !access_has_global_level( config_get( 'tag_create_threshold' ) ) ) {
		$p_error = "Tag '$t_name' does not exist and you may not create it.";
		return -1;
	}

	return (int)tag_create( $t_name );
}

/**
 * Attach the bulk tag to one issue, ignoring an attachment that is already there.
 *
 * @param integer $p_tag_id  The tag id, 0 when no tag was requested.
 * @param integer $p_issue_id The issue id.
 * @return void
 */
function rest_issue_bulk_tag_attach( $p_tag_id, $p_issue_id ) {
	if( $p_tag_id > 0 && !tag_bug_is_attached( $p_tag_id, $p_issue_id ) ) {
		tag_bug_attach( $p_tag_id, $p_issue_id );
	}
}

/**
 * Build the per-issue error entry of a bulk response from a caught exception.
 *
 * @param integer   $p_issue_id  The issue id.
 * @param Throwable $p_exception The exception raised while processing the issue.
 * @return array
 */
function rest_issue_bulk_error( $p_issue_id, Throwable $p_exception ) {
	if( $p_exception instanceof \Mantis\Exceptions\LegacyApiFaultException ) {
		# Legacy faults already carry the http status code.
		$t_code = (int)$p_exception->getCode();
		$t_message = $p_exception->getMessage();
	} else if( $p_exception instanceof \Mantis\Exceptions\MantisException ) {
		$t_code = error_map_mantis_error_to_http_code( $p_exception->getCode() );
		$t_message = $p_exception->getMessage();
	} else {
		# Anything else (TypeError, database errors, ...) carries internal detail
		# such as SQL text or file paths, so it is logged and not sent back.
		# Same treatment as the API's global errorHandler.
		error_log( 'Bulk operation failed on issue ' . (int)$p_issue_id . ': '
			. $p_exception->getMessage() . "\n" . error_stack_trace_as_string( $p_exception ) );

		$t_code = HTTP_STATUS_INTERNAL_SERVER_ERROR;
		$t_message = 'Internal error';
	}

	return array(
		'id' => (int)$p_issue_id,
		'status' => 'error',
		'code' => $t_code,
		'message' => $t_message,
	);
}

/**
 * Wrap the per-issue loop of a bulk operation.
 *
 * Applies the callback to every id, isolating failures so that one failing issue
 * doesn't prevent the remaining ones from being processed, and optionally disables
 * email notifications for the duration of the batch.
 *
 * @param array    $p_ids      The issue ids to process.
 * @param boolean  $p_suppress Whether to suppress email notifications.
 * @param callable $p_callback Receives an issue id, returns the 'ok' result entry.
 * @return array The bulk result payload.
 */
function rest_issue_bulk_run( array $p_ids, $p_suppress, callable $p_callback ) {
	return rest_issue_with_notifications_suppressed( $p_suppress, function() use ( $p_ids, $p_callback ) {
		$t_results = array();
		$t_ok_count = 0;
		$t_error_count = 0;

		foreach( $p_ids as $t_id ) {
			try {
				$t_entry = $p_callback( $t_id );
				$t_ok_count++;
			} catch( Throwable $e ) {
				$t_entry = rest_issue_bulk_error( $t_id, $e );
				$t_error_count++;
			}

			$t_results[] = $t_entry;
		}

		return array(
			'results' => $t_results,
			'ok_count' => $t_ok_count,
			'error_count' => $t_error_count,
		);
	} );
}

/**
 * The enum fields of an issue patch, mapped to the enum config they resolve against.
 *
 * These are the fields mc_issue_update() runs through mci_get_enum_id_from_objectref().
 *
 * @return array Map of issue field name => enum name.
 */
function rest_issue_bulk_enum_fields() {
	return array(
		'status'          => 'status',
		'resolution'      => 'resolution',
		'priority'        => 'priority',
		'severity'        => 'severity',
		'reproducibility' => 'reproducibility',
		'projection'      => 'projection',
		'eta'             => 'eta',
		'view_state'      => 'view_state',
	);
}

/**
 * Validate the enum fields of a bulk issue patch before anything is modified.
 *
 * An unknown enum name isn't an error further down: mci_get_enum_id_from_objectref()
 * falls back to the 'webservice_*_enum_default_when_not_found' config, which is 0 by
 * default, and the issue ends up with an out-of-range value while the response still
 * says 'ok'. In a bulk request that silently corrupts the whole batch, so the request
 * is rejected up front instead.
 *
 * @param array  $p_patch The 'issue' patch of the bulk request.
 * @param string $p_error Set to the error message when validation fails.
 * @return boolean True when every enum field resolves to a known value.
 */
function rest_issue_bulk_validate_enums( array $p_patch, &$p_error ) {
	$p_error = null;

	foreach( rest_issue_bulk_enum_fields() as $t_field => $t_enum ) {
		if( !isset( $p_patch[$t_field] ) ) {
			continue;
		}

		$t_ref = ApiObjectFactory::objectToArray( $p_patch[$t_field] );

		# An explicit id is passed straight through by the update path; only names
		# go through the lookup that can silently fall back.
		if( is_array( $t_ref ) && isset( $t_ref['id'] ) && (int)$t_ref['id'] != 0 ) {
			continue;
		}

		if( !is_array( $t_ref ) || !isset( $t_ref['name'] ) || is_blank( $t_ref['name'] ) ) {
			$p_error = "Field '$t_field' must be an object with an 'id' or a 'name'.";
			return false;
		}

		$t_enum_string = config_get( $t_enum . '_enum_string' );
		if( mci_get_enum_value_from_label( $t_enum_string, $t_ref['name'] ) == 0 ) {
			$p_error = 'Unknown ' . $t_field . ' '
				. rest_issue_bulk_describe_value( $t_ref['name'] )
				. '. Supported values are: '
				. implode( ', ', MantisEnum::getAssocArrayIndexedByValues( $t_enum_string ) )
				. '.';
			return false;
		}
	}

	return true;
}

/**
 * Apply the same update to multiple issues.
 *
 * Body: { "ids": [1, 2, 3], "issue": { ...same shape as a single issue PATCH... },
 *         "suppress_notifications": false }
 *
 * Each issue goes through the same update path as the single-issue PATCH, so
 * history, events and notifications behave identically. A failure on one issue
 * (missing, no access, conflict) doesn't stop the remaining ones; the response
 * reports the outcome of every requested issue.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @noinspection PhpUnusedParameterInspection
 */
function rest_issue_bulk_update( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_body = $p_request->getParsedBody();

	$t_ids = rest_issue_bulk_ids( $t_body, $t_error );
	if( $t_ids === null ) {
		# The reason phrase goes into the status line, so it is left generic; the
		# detail, which embeds request data, is carried by the JSON body only.
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST )
			->withJson( array( 'message' => $t_error ) );
	}

	if( !isset( $t_body['issue'] ) || !is_array( $t_body['issue'] ) || count( $t_body['issue'] ) == 0 ) {
		$t_message = "Mandatory field 'issue' is missing or empty.";
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST, $t_message )
			->withJson( array( 'message' => $t_message ) );
	}

	$t_patch = $t_body['issue'];
	if( isset( $t_patch['id'] ) ) {
		$t_message = "Field 'id' is not allowed in a bulk issue patch.";
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST, $t_message )
			->withJson( array( 'message' => $t_message ) );
	}

	# Resolve the enum fields once, before the first issue is touched: an unknown
	# name must fail the whole request rather than be applied to every issue.
	if( !rest_issue_bulk_validate_enums( $t_patch, $t_enum_error ) ) {
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST )
			->withJson( array( 'message' => $t_enum_error ) );
	}

	$t_tag_id = rest_issue_bulk_tag_id( $t_body, $t_tag_error );
	if( $t_tag_id < 0 ) {
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST )
			->withJson( array( 'message' => $t_tag_error ) );
	}

	if( rest_issue_bulk_dry_run( $t_body ) ) {
		return $p_response->withStatus( HTTP_STATUS_SUCCESS, 'Bulk update: dry run' )
			->withJson( rest_issue_bulk_dry_run_result( $t_ids, $t_body, array(
				'patch' => $t_patch,
			) ) );
	}

	$t_result = rest_issue_bulk_run(
		$t_ids,
		rest_issue_bulk_suppress_notifications( $t_body ),
		function( $p_issue_id ) use ( $t_patch, $t_tag_id ) {
			# Same sequence as rest_issue_update(): load the issue, merge the patch
			# on top of it and run it through mc_issue_update().
			$t_issue = mc_issue_get( /* username */ '', /* password */ '', $p_issue_id );
			ApiObjectFactory::throwIfFault( $t_issue );

			$t_issue = (object)array_merge( $t_issue, $t_patch );

			$t_update = mc_issue_update( /* username */ '', /* password */ '', $p_issue_id, $t_issue );
			ApiObjectFactory::throwIfFault( $t_update );

			rest_issue_bulk_tag_attach( $t_tag_id, $p_issue_id );

			return array( 'id' => (int)$p_issue_id, 'status' => 'ok' );
		}
	);

	return $p_response->withStatus( HTTP_STATUS_SUCCESS,
		"Bulk update: {$t_result['ok_count']} updated, {$t_result['error_count']} failed" )
		->withJson( $t_result );
}

/**
 * Add the same note to multiple issues.
 *
 * Body, one note for everyone:
 *   { "ids": [1, 2, 3], "note": { "text": "...", "view_state": { "name": "public" } },
 *     "suppress_notifications": true, "tag": "auto-close-2026-09-18", "dry_run": false }
 *
 * Body, a note per issue:
 *   { "issues": [ { "id": 1, "note": { "text": "..." } },
 *                 { "id": 2, "note": { "text": "..." } } ],
 *     "suppress_notifications": true, "tag": "auto-close-2026-09-18" }
 *
 * Each note is created through IssueNoteAddCommand, the same command used by the
 * single-issue note endpoint. A failure on one issue doesn't stop the remaining ones.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @noinspection PhpUnusedParameterInspection
 */
function rest_issue_bulk_note_add( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_body = $p_request->getParsedBody();

	# Accepts either 'ids' + a shared 'note', or 'issues' with a note per issue.
	# A base64 upload is moved to a temp file that is consumed by the first note
	# created from it, so 'files' is rejected in both shapes.
	$t_entries = rest_issue_bulk_note_entries( $t_body, $t_error );
	if( $t_entries === null ) {
		# The reason phrase goes into the status line, so it is left generic; the
		# detail, which embeds request data, is carried by the JSON body only.
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST )
			->withJson( array( 'message' => $t_error ) );
	}

	$t_tag_id = rest_issue_bulk_tag_id( $t_body, $t_tag_error );
	if( $t_tag_id < 0 ) {
		return $p_response->withStatus( HTTP_STATUS_BAD_REQUEST )
			->withJson( array( 'message' => $t_tag_error ) );
	}

	$t_ids = array_column( $t_entries, 0 );

	if( rest_issue_bulk_dry_run( $t_body ) ) {
		$t_preview = array();
		foreach( $t_entries as $t_entry ) {
			$t_preview[] = array(
				'id' => $t_entry[0],
				'text_length' => mb_strlen( isset( $t_entry[1]['text'] ) ? (string)$t_entry[1]['text'] : '' ),
			);
		}

		return $p_response->withStatus( HTTP_STATUS_SUCCESS, 'Bulk note: dry run' )
			->withJson( rest_issue_bulk_dry_run_result( $t_ids, $t_body, array(
				'notes' => $t_preview,
			) ) );
	}

	$t_notes_by_id = array();
	foreach( $t_entries as $t_entry ) {
		$t_notes_by_id[$t_entry[0]] = $t_entry[1];
	}

	$t_result = rest_issue_bulk_run(
		$t_ids,
		rest_issue_bulk_suppress_notifications( $t_body ),
		function( $p_issue_id ) use ( $t_notes_by_id, $t_tag_id ) {
			$t_command = new IssueNoteAddCommand( array(
				'query' => array( 'issue_id' => $p_issue_id ),
				'payload' => $t_notes_by_id[$p_issue_id],
			) );
			$t_command_response = $t_command->execute();

			rest_issue_bulk_tag_attach( $t_tag_id, $p_issue_id );

			return array(
				'id' => (int)$p_issue_id,
				'status' => 'ok',
				'note_id' => (int)$t_command_response['id'],
			);
		}
	);

	return $p_response->withStatus( HTTP_STATUS_SUCCESS,
		"Bulk note: {$t_result['ok_count']} added, {$t_result['error_count']} failed" )
		->withJson( $t_result );
}

/**
 * Add users to monitor an issue.
 *
 * @param \Slim\Http\Request $p_request   The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 */
function rest_issue_monitor_add( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = isset( $p_args['id'] ) ? $p_args['id'] : $p_request->getParam( 'id' );
	$t_data = array(
		'query' => array( 'issue_id' => $t_issue_id ),
		'payload' => $p_request->getParsedBody(),
	);

	$t_command = new MonitorAddCommand( $t_data );
	$t_command->execute();

	$t_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );			

	return $p_response->withStatus( HTTP_STATUS_CREATED, "Users are now monitoring issue $t_issue_id" )->
		withJson( array( 'issues' => array( $t_issue ) ) );
}

/**
 * Attach a tag to an issue.
 *
 * @param \Slim\Http\Request $p_request   The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 */
function rest_issue_tag_attach( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = $p_args['id'];
	$t_data = array(
		'query' => array( 'issue_id' => $t_issue_id ),
		'payload' => $p_request->getParsedBody(),
	);

	$t_command = new TagAttachCommand( $t_data );
	$t_command->execute();

	$t_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );			

	return $p_response->withStatus( HTTP_STATUS_CREATED, "Tag attached to issue $t_issue_id" )->
		withJson( array( 'issues' => array( $t_issue ) ) );
}

/**
 * Detach a tag from the issue
 *
 * @param \Slim\Http\Request $p_request   The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @noinspection PhpUnusedParameterInspection
 */
function rest_issue_tag_detach( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = $p_args['id'];
	$t_tag_id = $p_args['tag_id'];

	$t_data = array(
		'query' => array( 'issue_id' => $t_issue_id, 'tag_id' => $t_tag_id )
	);

	$t_command = new TagDetachCommand( $t_data );
	$t_command->execute();

	$t_issue = mc_issue_get( /* username */ '', /* password */ '', $t_issue_id );			

	return $p_response->withStatus( HTTP_STATUS_SUCCESS, "Tag detached from issue $t_issue_id" )->
		withJson( array( 'issues' => array( $t_issue ) ) );
}

/**
 * Get files associated with an issue.
 *
 * @param \Slim\Http\Request $p_request   The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 *
 * @noinspection PhpUnusedParameterInspection
 */
function rest_issue_files_get( \Slim\Http\Request $p_request, \Slim\Http\Response $p_response, array $p_args ) {
	$t_issue_id = $p_args['id'];
	$t_file_id = isset( $p_args['file_id'] ) ? $p_args['file_id'] : null;

	$t_data = array(
		'query' => array( 'issue_id' => $t_issue_id, 'file_id' => $t_file_id )
	);

	$t_command = new IssueFileGetCommand( $t_data );
	$t_internal_files = $t_command->execute();

	$t_files = array();
	foreach( $t_internal_files as $t_internal_file ) {
		$t_file = array(
			'id' => (int)$t_internal_file['id'],
			'reporter' => mci_account_get_array_by_id( $t_internal_file['user_id'] ),
			'created_at' => ApiObjectFactory::datetimeString( $t_internal_file['date_added'] ),
			'filename' => $t_internal_file['display_name'],
			'size' => (int)$t_internal_file['size'],
		);

		if( $t_internal_file['exists'] ) {
			$t_file['content_type'] = $t_internal_file['content_type'];
			$t_file['content'] = base64_encode( $t_internal_file['content'] );
		}

		$t_files[] = $t_file;
	}

	return $p_response->withStatus( HTTP_STATUS_SUCCESS )->
		withJson( array( 'files' => $t_files ) );
}

/**
 * Convert REST API base 64 files into expected format for browser file uploads.
 *
 * @param array $p_files The files in REST API format.
 * @return array The files in browser upload format.
 *
 * @throws ClientException
 */
function files_base64_to_temp( $p_files ) {
	$t_files = array();

	if( isset( $p_files ) && is_array( $p_files ) ) {
		foreach( $p_files as $t_file ) {
			if( !isset( $t_file['content'] ) ) {
				throw new ClientException(
					'File content not set',
					ERROR_INVALID_FIELD_VALUE,
					array( 'files' ) );
			}

			$t_raw_content = base64_decode( $t_file['content'] );

			do {
				$t_tmp_file = realpath( sys_get_temp_dir() ) . '/' . uniqid( 'mantisbt-file' );
			} while( file_exists( $t_tmp_file ) );

			file_put_contents( $t_tmp_file, $t_raw_content );
			$t_file['tmp_name'] = $t_tmp_file;
			$t_file['size'] = filesize( $t_tmp_file );
			$t_file['browser_upload'] = false;
			unset( $t_file['content'] );

			$t_files[] = $t_file;
		}
	}

	return $t_files;
}