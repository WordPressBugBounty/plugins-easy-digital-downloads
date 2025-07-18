<?php
/**
 * Logs Schema Class.
 *
 * @package     EDD\Database\Schemas
 * @copyright   Copyright (c) 2018, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.0
 */

namespace EDD\Database\Schemas;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use EDD\Database\Schema;

/**
 * Logs Schema Class.
 *
 * @since 3.0
 */
class Logs extends Schema {

	/**
	 * Array of database column objects
	 *
	 * @since 3.0
	 * @access public
	 * @var array
	 */
	public $columns = array(

		// id
		array(
			'name'     => 'id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'extra'    => 'auto_increment',
			'primary'  => true,
			'sortable' => true,
		),

		// object_id
		array(
			'name'      => 'object_id',
			'type'      => 'bigint',
			'length'    => '20',
			'unsigned'  => true,
			'default'   => '0',
			'sortable'  => true,
			'cache_key' => true,
		),

		// object_type
		array(
			'name'       => 'object_type',
			'type'       => 'varchar',
			'length'     => '20',
			'default'    => '',
			'sortable'   => true,
			'cache_key'  => true,
			'allow_null' => true,
		),

		// user_id
		array(
			'name'      => 'user_id',
			'type'      => 'bigint',
			'length'    => '20',
			'unsigned'  => true,
			'default'   => '0',
			'sortable'  => true,
			'cache_key' => true,
		),

		// type
		array(
			'name'     => 'type',
			'type'     => 'varchar',
			'length'   => '20',
			'default'  => '',
			'sortable' => true,
		),

		// title
		array(
			'name'       => 'title',
			'type'       => 'varchar',
			'length'     => '200',
			'default'    => '',
			'searchable' => true,
			'sortable'   => true,
			'in'         => false,
			'not_in'     => false,
		),

		// content
		array(
			'name'       => 'content',
			'type'       => 'longtext',
			'default'    => '',
			'searchable' => true,
			'in'         => false,
			'not_in'     => false,
		),

		// date_created
		array(
			'name'       => 'date_created',
			'type'       => 'datetime',
			'default'    => '', // Defaults to current time in query class
			'created'    => true,
			'date_query' => true,
			'sortable'   => true,
		),

		// date_modified
		array(
			'name'       => 'date_modified',
			'type'       => 'datetime',
			'default'    => '', // Defaults to current time in query class
			'modified'   => true,
			'date_query' => true,
			'sortable'   => true,
		),

		// uuid
		array(
			'uuid' => true,
		),
	);
}
