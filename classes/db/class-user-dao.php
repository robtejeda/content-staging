<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\DB\Mappers\User_Mapper;
use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Models\User;

class User_DAO extends DAO {

	private $table;

	public function __construct( $wpdb ) {
		parent::__constuct( $wpdb );
		$this->table = $wpdb->users;
	}

	/**
	 * @param int $user_id
	 * @return User
	 */
	public function get_user_by_id( $user_id ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->table . ' WHERE ID = %d',
			$user_id
		);

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['ID'] ) ) {
			return $this->create_object( $result );
		}

		return null;
	}

	/**
	 * @param string $user_login
	 * @return User
	 */
	public function get_user_by_user_login( $user_login ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->table . ' WHERE user_login = %s',
			$user_login
		);

		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( isset( $result['ID'] ) ) {
			return $this->create_object( $result );
		}

		return null;
	}

	/**
	 * Get users by user IDs.
	 *
	 * To fetch meta data on the users as well, set $lazy to false.
	 *
	 * @param array $user_ids
	 * @param bool $lazy
	 * @return array
	 */
	public function get_users_by_ids( $user_ids, $lazy = true ) {
		$users        = array();
		$placeholders = $this->in_clause_placeholders( $user_ids, '%d' );

		if ( ! $placeholders ) {
			return $users;
		}

		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->table . ' WHERE ID IN (' . $placeholders . ')',
			$user_ids
		);

		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $user ) {
			if ( isset( $user['ID'] ) ) {
				$obj = $this->create_object( $user );

				// Add user meta to user object.
				if ( ! $lazy ) {
					$this->get_user_meta( $obj );
				}

				$users[] = $obj;
			}
		}

		return $users;
	}

	/**
	 * @param User $user
	 */
	public function get_user_meta( User $user ) {
		$query = $this->wpdb->prepare(
			'SELECT * FROM ' . $this->wpdb->usermeta . ' WHERE user_id = %d',
			$user->get_id()
		);

		$user->set_meta( $this->wpdb->get_results( $query, ARRAY_A ) );
	}

	/**
	 * @param User $user
	 */
	public function insert_user( User $user ) {
		$data   = $this->create_array( $user );
		$format = $this->format();

		$this->wpdb->insert( $this->table, $data, $format );
		$user->set_id( $this->wpdb->insert_id );
		$this->insert_user_meta( $user );
	}

	/**
	 * @param User $user
	 */
	public function update_user( User $user ) {
		$data         = $this->create_array( $user );
		$where        = array( 'ID' => $user->get_id() );
		$format       = $this->format();
		$where_format = array( '%d' );

		$this->wpdb->update( $this->table, $data, $where, $format, $where_format );
		$this->delete_user_meta( $user );
		$this->insert_user_meta( $user );
	}

	/**
	 * @param User $user
	 */
	public function insert_user_meta( User $user ) {
		$placeholders = '';
		$values       = array();

		foreach ( $user->get_meta() as $index => $meta ) {
			if ( $index !== 0 ) {
				$placeholders .= ',';
			}
			$placeholders .= '(%d,%s,%s)';
			$values[] = $user->get_id();
			$values[] = $meta['meta_key'];
			$values[] = $meta['meta_value'];
		}

		if ( ! empty( $values ) ) {
			$query = $this->wpdb->prepare(
				'INSERT INTO ' . $this->wpdb->usermeta . ' (user_id, meta_key, meta_value) ' .
				'VALUES ' . $placeholders,
				$values
			);

			$this->wpdb->query( $query );
		}
	}

	/**
	 * @param User $user
	 */
	public function delete_user_meta( User $user ) {
		$this->wpdb->delete(
			$this->wpdb->usermeta,
			array( 'user_id' => $user->get_id() ),
			array( '%d' )
		);
	}

	/**
	 * @param array $raw
	 * @return User
	 */
	protected function do_create_object( array $raw ) {
		$obj = new User( $raw['ID'] );
		$obj->set_login( $raw['user_login'] );
		$obj->set_password( $raw['user_pass'] );
		$obj->set_nicename( $raw['user_nicename'] );
		$obj->set_email( $raw['user_email'] );
		$obj->set_url( $raw['user_url'] );
		$obj->set_registered( $raw['user_registered'] );
		$obj->set_activation_key( $raw['user_activation_key'] );
		$obj->set_status( $raw['user_status'] );
		$obj->set_display_name( $raw['display_name'] );
		return $obj;
	}

	/**
	 * @param Model $obj
	 * @return array
	 */
	protected function do_create_array( Model $obj ) {
		return array(
			'user_login'          => $obj->get_login(),
			'user_pass'           => $obj->get_password(),
			'user_nicename'       => $obj->get_nicename(),
			'user_email'          => $obj->get_email(),
			'user_url'            => $obj->get_url(),
			'user_registered'     => $obj->get_registered(),
			'user_activation_key' => $obj->get_activation_key(),
			'user_status'         => $obj->get_status(),
			'display_name'        => $obj->get_display_name(),
		);
	}

	/**
	 * Format of each of the values in the result set.
	 *
	 * Important! Must mimic the array returned by the
	 * 'do_create_array' method.
	 *
	 * @return array
	 */
	private function format() {
		return array(
			'%s', // user_login
			'%s', // user_pass
			'%s', // user_nicename
			'%s', // user_email
			'%s', // user_url
			'%s', // user_registered
			'%s', // user_activation_key
			'%d', // user_status
			'%s', // display_name
		);
	}
}