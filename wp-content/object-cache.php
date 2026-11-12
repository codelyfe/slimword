<?php
/**
 * Slim WordPress file object cache.
 *
 * Stored under private/cache/object. Not served by the web server.
 */

class Slimword_File_Cache {
	public $blog_id = 1;

	private $memory = array();

	private $global_groups = array();

	private $non_persistent = array();

	public function init() {
		global $blog_id;
		$this->blog_id = isset( $blog_id ) ? (int) $blog_id : 1;
		if ( $this->blog_id < 1 ) {
			$this->blog_id = 1;
		}
		$dir = $this->root();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
	}

	public function switch_to_blog( $blog_id ) {
		$this->blog_id = (int) $blog_id;
		if ( $this->blog_id < 1 ) {
			$this->blog_id = 1;
		}
		$this->memory = array();
	}

	public function add_global_groups( $groups ) {
		foreach ( (array) $groups as $group ) {
			$this->global_groups[ $group ] = true;
		}
	}

	public function add_non_persistent_groups( $groups ) {
		foreach ( (array) $groups as $group ) {
			$this->non_persistent[ $group ] = true;
		}
	}

	public function add( $key, $data, $group = 'default', $expire = 0 ) {
		if ( wp_suspend_cache_addition() ) {
			return false;
		}
		if ( $this->exists( $key, $group ) ) {
			return false;
		}
		return $this->set( $key, $data, $group, $expire );
	}

	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
		if ( ! $this->exists( $key, $group ) ) {
			return false;
		}
		return $this->set( $key, $data, $group, $expire );
	}

	public function set( $key, $data, $group = 'default', $expire = 0 ) {
		if ( $this->invalidation_suspended() ) {
			return false;
		}
		$group = $this->group( $group );
		$id    = $this->id( $key, $group );
		$ttl   = (int) $expire;
		$row   = array(
			'key'     => (string) $key,
			'group'   => $group,
			'expires' => $ttl > 0 ? time() + $ttl : 0,
			'saved'   => time(),
		);
		$this->memory[ $id ] = array(
			'data' => $data,
			'row'  => $row,
		);
		if ( isset( $this->non_persistent[ $group ] ) ) {
			return true;
		}
		$payload = json_encode( $row ) . "\n" . serialize( $data );
		return $this->write( $this->path( $key, $group ), $payload );
	}

	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		$group = $this->group( $group );
		$id    = $this->id( $key, $group );
		if ( ! $force && isset( $this->memory[ $id ] ) ) {
			$found = true;
			return $this->memory[ $id ]['data'];
		}
		if ( isset( $this->non_persistent[ $group ] ) ) {
			$found = false;
			return false;
		}
		$path = $this->path( $key, $group );
		$row  = $this->read( $path );
		if ( ! $row ) {
			$found = false;
			unset( $this->memory[ $id ] );
			return false;
		}
		$found = true;
		$this->memory[ $id ] = $row;
		return $row['data'];
	}

	public function delete( $key, $group = 'default' ) {
		if ( $this->invalidation_suspended() ) {
			return false;
		}
		$group = $this->group( $group );
		$id    = $this->id( $key, $group );
		unset( $this->memory[ $id ] );
		if ( isset( $this->non_persistent[ $group ] ) ) {
			return true;
		}
		$path = $this->path( $key, $group );
		if ( ! is_file( $path ) ) {
			return false;
		}
		return unlink( $path );
	}

	public function incr( $key, $offset = 1, $group = 'default' ) {
		return $this->step( $key, (int) $offset, $group );
	}

	public function decr( $key, $offset = 1, $group = 'default' ) {
		return $this->step( $key, -1 * (int) $offset, $group );
	}

	public function flush() {
		if ( $this->invalidation_suspended() ) {
			return false;
		}
		$this->memory = array();
		$this->delete_tree( $this->root() );
		wp_mkdir_p( $this->root() );
		return true;
	}

	public function flush_runtime() {
		$this->memory = array();
		return true;
	}

	public function flush_group( $group ) {
		if ( $this->invalidation_suspended() ) {
			return false;
		}
		$group = $this->group( $group );
		foreach ( array_keys( $this->memory ) as $id ) {
			if ( isset( $this->memory[ $id ]['row']['group'] ) && $this->memory[ $id ]['row']['group'] === $group ) {
				unset( $this->memory[ $id ] );
			}
		}
		$dir = $this->root() . '/' . $this->safe( $group );
		$this->delete_tree( $dir );
		return true;
	}

	public function exists( $key, $group ) {
		$found = false;
		$this->get( $key, $group, false, $found );
		return $found;
	}

	public function stats() {
		$list  = $this->entries();
		$bytes = 0;
		foreach ( $list as $row ) {
			$bytes += $row['bytes'];
		}
		return array(
			'count' => count( $list ),
			'bytes' => $bytes,
			'dir'   => $this->root(),
		);
	}

	public function entries() {
		$root = $this->root();
		if ( ! is_dir( $root ) ) {
			return array();
		}
		$rows = array();
		$dirs = scandir( $root );
		if ( ! is_array( $dirs ) ) {
			return array();
		}
		foreach ( $dirs as $group_dir ) {
			if ( '.' === $group_dir || '..' === $group_dir ) {
				continue;
			}
			$dir = $root . '/' . $group_dir;
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$files = scandir( $dir );
			if ( ! is_array( $files ) ) {
				continue;
			}
			foreach ( $files as $file ) {
				if ( substr( $file, -6 ) !== '.cache' ) {
					continue;
				}
				$path = $dir . '/' . $file;
				$meta = $this->meta( $path );
				if ( ! $meta ) {
					continue;
				}
				if ( ! empty( $meta['expires'] ) && (int) $meta['expires'] < time() ) {
					unlink( $path );
					continue;
				}
				$meta['bytes'] = (int) filesize( $path );
				$meta['file']  = $path;
				$rows[]        = $meta;
			}
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) $b['saved'] - (int) $a['saved'];
			}
		);
		return $rows;
	}

	private function step( $key, $delta, $group ) {
		$found = false;
		$value = $this->get( $key, $group, false, $found );
		if ( ! $found || ! is_numeric( $value ) ) {
			return false;
		}
		$value += $delta;
		if ( $value < 0 ) {
			$value = 0;
		}
		if ( ! $this->set( $key, $value, $group, 0 ) ) {
			return false;
		}
		return $value;
	}

	private function read( $path ) {
		if ( ! is_file( $path ) ) {
			return null;
		}
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return null;
		}
		$line = fgets( $handle );
		$data = stream_get_contents( $handle );
		fclose( $handle );
		$meta = json_decode( (string) $line, true );
		if ( ! is_array( $meta ) ) {
			return null;
		}
		if ( ! empty( $meta['expires'] ) && (int) $meta['expires'] < time() ) {
			unlink( $path );
			return null;
		}
		$value = unserialize( (string) $data );
		return array(
			'data' => $value,
			'row'  => $meta,
		);
	}

	private function meta( $path ) {
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return null;
		}
		$line = fgets( $handle );
		fclose( $handle );
		$meta = json_decode( (string) $line, true );
		if ( ! is_array( $meta ) || ! isset( $meta['key'], $meta['group'] ) ) {
			return null;
		}
		return $meta;
	}

	private function write( $path, $payload ) {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$tmp = $path . '.' . uniqid( '', true ) . '.tmp';
		if ( false === file_put_contents( $tmp, $payload, LOCK_EX ) ) {
			return false;
		}
		if ( ! rename( $tmp, $path ) ) {
			unlink( $tmp );
			return false;
		}
		return true;
	}

	private function path( $key, $group ) {
		return $this->root() . '/' . $this->safe( $group ) . '/' . sha1( $this->id( $key, $group ) ) . '.cache';
	}

	private function id( $key, $group ) {
		$blog = isset( $this->global_groups[ $group ] ) ? 0 : $this->blog_id;
		$salt = defined( 'WP_CACHE_KEY_SALT' ) ? WP_CACHE_KEY_SALT : '';
		return $salt . '|' . $blog . '|' . $group . '|' . $key;
	}

	private function group( $group ) {
		$group = (string) $group;
		return '' === $group ? 'default' : $group;
	}

	private function safe( $group ) {
		$safe = preg_replace( '/[^A-Za-z0-9._-]/', '_', $group );
		return '' === $safe ? 'default' : $safe;
	}

	private function root() {
		return ABSPATH . 'private/cache/object';
	}

	private function invalidation_suspended() {
		global $_wp_suspend_cache_invalidation;
		return ! empty( $_wp_suspend_cache_invalidation );
	}

	private function delete_tree( $path ) {
		if ( ! is_dir( $path ) ) {
			return;
		}
		$items = scandir( $path );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$target = $path . '/' . $item;
			if ( is_dir( $target ) ) {
				$this->delete_tree( $target );
				rmdir( $target );
			} else {
				unlink( $target );
			}
		}
	}
}

$GLOBALS['slimword_file_cache'] = new Slimword_File_Cache();

function wp_cache_init() {
	$GLOBALS['slimword_file_cache']->init();
}

function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['slimword_file_cache']->add( $key, $data, $group, $expire );
}

function wp_cache_add_multiple( array $data, $group = '', $expire = 0 ) {
	$results = array();
	foreach ( $data as $key => $value ) {
		$results[ $key ] = wp_cache_add( $key, $value, $group, $expire );
	}
	return $results;
}

function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['slimword_file_cache']->replace( $key, $data, $group, $expire );
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['slimword_file_cache']->set( $key, $data, $group, $expire );
}

function wp_cache_set_multiple( array $data, $group = '', $expire = 0 ) {
	$results = array();
	foreach ( $data as $key => $value ) {
		$results[ $key ] = wp_cache_set( $key, $value, $group, $expire );
	}
	return $results;
}

function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	return $GLOBALS['slimword_file_cache']->get( $key, $group, $force, $found );
}

function wp_cache_get_multiple( $keys, $group = '', $force = false ) {
	$results = array();
	foreach ( $keys as $key ) {
		$found            = false;
		$results[ $key ] = wp_cache_get( $key, $group, $force, $found );
	}
	return $results;
}

function wp_cache_delete( $key, $group = '' ) {
	return $GLOBALS['slimword_file_cache']->delete( $key, $group );
}

function wp_cache_delete_multiple( array $keys, $group = '' ) {
	$results = array();
	foreach ( $keys as $key ) {
		$results[ $key ] = wp_cache_delete( $key, $group );
	}
	return $results;
}

function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	return $GLOBALS['slimword_file_cache']->incr( $key, $offset, $group );
}

function wp_cache_decr( $key, $offset = 1, $group = '' ) {
	return $GLOBALS['slimword_file_cache']->decr( $key, $offset, $group );
}

function wp_cache_flush() {
	return $GLOBALS['slimword_file_cache']->flush();
}

function wp_cache_flush_runtime() {
	return $GLOBALS['slimword_file_cache']->flush_runtime();
}

function wp_cache_flush_group( $group ) {
	return $GLOBALS['slimword_file_cache']->flush_group( $group );
}

function wp_cache_supports( $feature ) {
	return in_array(
		$feature,
		array( 'add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple', 'flush_runtime', 'flush_group' ),
		true
	);
}

function wp_cache_close() {
	return true;
}

function wp_cache_add_global_groups( $groups ) {
	$GLOBALS['slimword_file_cache']->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {
	$GLOBALS['slimword_file_cache']->add_non_persistent_groups( $groups );
}

function wp_cache_switch_to_blog( $blog_id ) {
	$GLOBALS['slimword_file_cache']->switch_to_blog( $blog_id );
}

function wp_cache_reset() {
	wp_cache_switch_to_blog( get_current_blog_id() );
}
