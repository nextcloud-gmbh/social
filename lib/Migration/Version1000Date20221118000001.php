<?php

declare(strict_types=1);

/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2022, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Social\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20221118000001 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createActions($schema);
		$this->createActors($schema);
		$this->createCacheActors($schema);
		$this->createCacheDocuments($schema);
		$this->createClient($schema);
		$this->createFollows($schema);
		$this->createHashtags($schema);
		$this->createInstance($schema);
		$this->createRequestQueue($schema);
		$this->createStreams($schema);
		$this->createStreamActions($schema);
		$this->createStreamDest($schema);
		$this->createStreamQueue($schema);
		$this->createStreamTags($schema);


		return $schema;
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createActions(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_action')) {
			return;
		}

		$table = $schema->createTable('social_action');
		$table->addColumn(
			'id', Types::TEXT,
			[
				'notnull' => false
			]
		);
		$table->addColumn(
			'id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32
			]
		);
		$table->addColumn(
			'type', Types::STRING,
			[
				'notnull' => false,
				'length' => 31,
				'default' => ''
			]
		);
		$table->addColumn(
			'actor_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'actor_id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'object_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'object_id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'creation', Types::DATETIME,
			[
				'notnull' => false,
			]
		);

		$table->setPrimaryKey(['id_prim']);
		$table->addUniqueIndex(['actor_id_prim', 'object_id_prim', 'type'], 'apopt');
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createActors(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_actor')) {
			return;
		}

		$table = $schema->createTable('social_actor');

		$table->addColumn(
			'id', Types::TEXT,
			[
				'notnull' => false
			]
		);
		$table->addColumn(
			'id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32
			]
		);
		$table->addColumn(
			'user_id', Types::STRING,
			[
				'notnull' => false,
				'length' => 63,
			]
		);
		$table->addColumn(
			'preferred_username', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
			]
		);
		$table->addColumn(
			'name', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);
		$table->addColumn(
			'summary', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'public_key', Types::TEXT,
			[
				'notnull' => false
			]
		);
		$table->addColumn(
			'private_key', Types::TEXT,
			[
				'notnull' => false
			]
		);
		$table->addColumn(
			'avatar_version', Types::INTEGER,
			[
				'notnull' => false,
				'length' => 2,
			]
		);
		$table->addColumn(
			'creation', Types::DATETIME,
			[
				'notnull' => false,
			]
		);
		$table->addColumn(
			'deleted', Types::DATETIME,
			[
				'notnull' => false,
			]
		);

		$table->setPrimaryKey(['id_prim']);
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createFollows(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_follow')) {
			return;
		}

		$table = $schema->createTable('social_follow');
		$table->addColumn(
			'id', Types::TEXT,
			[
				'notnull' => false
			]
		);
		$table->addColumn(
			'id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32
			]
		);
		$table->addColumn(
			'type', Types::STRING,
			[
				'notnull' => false,
				'length' => 31,
				'default' => ''
			]
		);
		$table->addColumn(
			'actor_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'actor_id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'object_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'object_id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'follow_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'follow_id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'accepted', Types::BOOLEAN,
			[
				'notnull' => true,
				'default' => false
			]
		);
		$table->addColumn(
			'creation', Types::DATETIME,
			[
				'notnull' => false,
			]
		);

		$table->setPrimaryKey(['id_prim']);
		$table->addUniqueIndex(['accepted', 'follow_id_prim', 'object_id_prim', 'actor_id_prim'], 'afoa');
		$table->addUniqueIndex(['accepted', 'object_id_prim', 'actor_id_prim'], 'aoa');
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createHashtags(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_hashtag')) {
			return;
		}

		$table = $schema->createTable('social_hashtag');
		$table->addColumn(
			'hashtag', Types::STRING,
			[
				'notnull' => false,
				'length' => 63
			]
		);
		$table->addColumn(
			'trend', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);

		$table->setPrimaryKey(['hashtag']);
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createInstance(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_instance')) {
			return;
		}

		$table = $schema->createTable('social_instance');
		$table->addColumn(
			'local', Types::SMALLINT,
			[
				'notnull' => false,
				'length' => 1,
				'default' => 0,
				'unsigned' => true
			]
		);
		$table->addColumn(
			'uri', Types::STRING,
			[
				'notnull' => false,
				'length' => 255,
			]
		);
		$table->addColumn(
			'title', Types::STRING,
			[
				'notnull' => false,
				'length' => 255,
				'default' => ''
			]
		);
		$table->addColumn(
			'version', Types::STRING,
			[
				'notnull' => false,
				'length' => 31,
				'default' => ''
			]
		);
		$table->addColumn(
			'short_description', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'description', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'email', Types::STRING,
			[
				'notnull' => false,
				'length' => 255,
				'default' => ''
			]
		);
		$table->addColumn(
			'urls', Types::TEXT,
			[
				'notnull' => false,
				'default' => '[]'
			]
		);
		$table->addColumn(
			'stats', Types::TEXT,
			[
				'notnull' => false,
				'default' => '[]'
			]
		);
		$table->addColumn(
			'usage', Types::TEXT,
			[
				'notnull' => false,
				'default' => '[]'
			]
		);
		$table->addColumn(
			'image', Types::STRING,
			[
				'notnull' => false,
				'length' => 255,
				'default' => ''
			]
		);
		$table->addColumn(
			'languages', Types::TEXT,
			[
				'notnull' => false,
				'default' => '[]'
			]
		);
		$table->addColumn(
			'contact', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);
		$table->addColumn(
			'account_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'creation', Types::DATETIME,
			[
				'notnull' => false,
			]
		);

		$table->setPrimaryKey(['uri']);
		$table->addIndex(['local', 'uri', 'account_prim']);
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createStreams(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_stream')) {
			return;
		}

		$table = $schema->createTable('social_stream');

		$table->addColumn(
			'nid', Types::BIGINT,
			[
				'length' => 20,
				'unsigned' => true
			]
		);
		$table->addColumn(
			'id', Types::TEXT,
			[
				'notnull' => false
			]
		);
		$table->addColumn(
			'id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32
			]
		);
		$table->addColumn(
			'type', Types::STRING,
			[
				'notnull' => false,
				'length' => 31,
				'default' => ''
			]
		);
		$table->addColumn(
			'subtype', Types::STRING,
			[
				'notnull' => false,
				'length' => 31,
				'default' => ''
			]
		);
		$table->addColumn(
			'visibility', Types::STRING,
			[
				'notnull' => false,
				'length' => 31,
				'default' => ''
			]
		);
		$table->addColumn(
			'to', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'to_array', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'cc', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'bcc', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'content', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'summary', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'published', Types::STRING,
			[
				'notnull' => false,
				'length' => 31,
				'default' => ''
			]
		);
		$table->addColumn(
			'published_time', Types::DATETIME,
			[
				'notnull' => false,
			]
		);
		$table->addColumn(
			'attributed_to', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'attributed_to_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'in_reply_to', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'in_reply_to_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'activity_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'object_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'object_id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'hashtags', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'details', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'source', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'instances', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'attachments', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'cache', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'creation', Types::DATETIME,
			[
				'notnull' => false,
			]
		);
		$table->addColumn(
			'local', Types::BOOLEAN,
			[
				'notnull' => false,
				'default' => false
			]
		);
		$table->addColumn(
			'filter_duplicate', Types::BOOLEAN,
			[
				'notnull' => false,
				'default' => false
			]
		);

		$table->setPrimaryKey(['nid']);
		$table->addUniqueIndex(['id_prim']);
		$table->addUniqueIndex(['nid']);
		$table->addUniqueIndex(
			[
				'id_prim',
				'published_time',
				'object_id_prim',
				'filter_duplicate',
				'attributed_to_prim'
			],
			'ipoha'
		);
		$table->addIndex(['object_id_prim'], 'object_id_prim');
		$table->addIndex(['in_reply_to_prim'], 'in_reply_to_prim');
		$table->addIndex(['attributed_to_prim'], 'attributed_to_prim');
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createCacheActors(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_cache_actor')) {
			return;
		}

		$table = $schema->createTable('social_cache_actor');
		$table->addColumn(
			'nid', Types::BIGINT,
			[
				'autoincrement' => true,
				'notnull' => true,
				'length' => 14,
				'unsigned' => true,
			]
		);
		$table->addColumn(
			'id', Types::TEXT,
			[
				'notnull' => false
			]
		);
		$table->addColumn(
			'id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32
			]
		);
		$table->addColumn(
			'type', Types::STRING,
			[
				'notnull' => false,
				'length' => 31,
				'default' => ''
			]
		);
		$table->addColumn(
			'account', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);
		$table->addColumn(
			'local', Types::BOOLEAN,
			[
				'notnull' => true,
				'default' => false
			]
		);
		$table->addColumn(
			'following', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'followers', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'inbox', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'shared_inbox', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'outbox', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'featured', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'url', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'preferred_username', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);
		$table->addColumn(
			'name', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);
		$table->addColumn(
			'icon_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'summary', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'public_key', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'source', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'details', Types::TEXT,
			[
				'notnull' => false
			]
		);
		$table->addColumn(
			'creation', Types::DATETIME,
			[
				'notnull' => false,
			]
		);
		$table->addColumn(
			'details_update', Types::DATETIME,
			[
				'notnull' => false,
			]
		);

		$table->setPrimaryKey(['nid']);
		$table->addUniqueIndex(['id_prim']);
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createCacheDocuments(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_cache_doc')) {
			return;
		}

		$table = $schema->createTable('social_cache_doc');
		$table->addColumn(
			'nid', Types::BIGINT,
			[
				'autoincrement' => true,
				'notnull' => true,
				'length' => 14,
				'unsigned' => true,
			]
		);
		$table->addColumn(
			'id', Types::TEXT,
			[
				'notnull' => false
			]
		);
		$table->addColumn(
			'id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32
			]
		);
		$table->addColumn(
			'type', Types::STRING,
			[
				'notnull' => false,
				'length' => 31,
				'default' => ''
			]
		);
		$table->addColumn(
			'parent_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => '',
			]
		);
		$table->addColumn(
			'parent_id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => '',
			]
		);
		$table->addColumn(
			'media_type', Types::STRING,
			[
				'notnull' => false,
				'length' => 63,
				'default' => '',
			]
		);
		$table->addColumn(
			'mime_type', Types::STRING,
			[
				'notnull' => false,
				'length' => 63,
				'default' => ''
			]
		);
		$table->addColumn(
			'url', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'local_copy', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'resized_copy', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'meta', Types::TEXT,
			[
				'notnull' => true,
				'default' => '[]'
			]
		);
		$table->addColumn(
			'blurhash', Types::STRING,
			[
				'notnull' => true,
				'length' => 63,
				'default' => ''
			]
		);
		$table->addColumn(
			'description', Types::TEXT,
			[
				'notnull' => true,
				'default' => ''
			]
		);
		$table->addColumn(
			'public', Types::BOOLEAN,
			[
				'notnull' => false,
				'default' => false
			]
		);
		$table->addColumn(
			'error', Types::SMALLINT,
			[
				'notnull' => false,
				'length' => 1,
			]
		);
		$table->addColumn(
			'creation', Types::DATETIME,
			[
				'notnull' => false,
			]
		);
		$table->addColumn(
			'caching', Types::DATETIME,
			[
				'notnull' => false,
			]
		);

		$table->setPrimaryKey(['nid']);
	}

	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createClient(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_client')) {
			return;
		}

		$table = $schema->createTable('social_client');
		$table->addColumn(
			'id', Types::INTEGER,
			[
				'autoincrement' => true,
				'notnull' => true,
				'length' => 7,
				'unsigned' => true,
			]
		);
		$table->addColumn(
			'app_name', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);
		$table->addColumn(
			'app_website', Types::STRING,
			[
				'notnull' => false,
				'length' => 255,
				'default' => ''
			]
		);
		$table->addColumn(
			'app_redirect_uris', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'app_client_id', Types::STRING,
			[
				'notnull' => false,
				'length' => 63,
				'default' => ''
			]
		);
		$table->addColumn(
			'app_client_secret', Types::STRING,
			[
				'notnull' => false,
				'length' => 63,
				'default' => ''
			]
		);
		$table->addColumn(
			'app_scopes', Types::TEXT,
			[
				'notnull' => false
			]
		);

		$table->addColumn(
			'auth_scopes', Types::TEXT,
			[
				'notnull' => false
			]
		);
		$table->addColumn(
			'auth_account', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);
		$table->addColumn(
			'auth_user_id', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);
		$table->addColumn(
			'auth_code', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);
		$table->addColumn(
			'token', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);
		$table->addColumn(
			'last_update', Types::DATETIME,
			[
				'notnull' => false,
			]
		);
		$table->addColumn(
			'creation', Types::DATETIME,
			[
				'notnull' => false,
			]
		);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['auth_code', 'token', 'app_client_id', 'app_client_secret']);
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createRequestQueue(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_req_queue')) {
			return;
		}

		$table = $schema->createTable('social_req_queue');
		$table->addColumn(
			'id', Types::BIGINT,
			[
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]
		);
		$table->addColumn(
			'token', Types::STRING,
			[
				'notnull' => false,
				'length' => 63,
			]
		);
		$table->addColumn(
			'author', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'author_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'activity', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'instance', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'priority', Types::SMALLINT,
			[
				'notnull' => false,
				'length' => 1,
				'default' => 0,
			]
		);
		$table->addColumn(
			'status', Types::SMALLINT,
			[
				'notnull' => false,
				'length' => 1,
				'default' => 0,
			]
		);
		$table->addColumn(
			'tries', Types::SMALLINT,
			[
				'notnull' => false,
				'length' => 2,
				'default' => 0,
			]
		);
		$table->addColumn(
			'last', Types::DATETIME,
			[
				'notnull' => false,
			]
		);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['token']);
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createStreamActions(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_stream_act')) {
			return;
		}

		$table = $schema->createTable('social_stream_act');

		$table->addColumn(
			'id', Types::INTEGER,
			[
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true
			]
		);
		$table->addColumn(
			'actor_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'actor_id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'stream_id', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);
		$table->addColumn(
			'stream_id_prim', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn('liked', Types::BOOLEAN, ['default' => false]);
		$table->addColumn('boosted', Types::BOOLEAN, ['default' => false]);
		$table->addColumn('replied', Types::BOOLEAN, ['default' => false]);
		$table->addColumn(
			'values', Types::TEXT,
			[
				'notnull' => false,
				'default' => ''
			]
		);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['stream_id_prim', 'actor_id_prim'], 'sa');
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createStreamDest(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_stream_dest')) {
			return;
		}

		$table = $schema->createTable('social_stream_dest');
		$table->addColumn(
			'stream_id', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'actor_id', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'type', Types::STRING,
			[
				'notnull' => false,
				'length' => 15,
				'default' => ''
			]
		);
		$table->addColumn(
			'subtype', Types::STRING,
			[
				'notnull' => false,
				'length' => 7,
				'default' => ''
			]
		);

		$table->addUniqueIndex(['stream_id', 'actor_id', 'type'], 'sat');
		$table->addIndex(['type', 'subtype'], 'ts');
	}


	/**
	 * @param ISchemaWrapper $schema
	 *
	 * @throws SchemaException
	 */
	private function createStreamQueue(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_stream_queue')) {
			return;
		}

		$table = $schema->createTable('social_stream_queue');
		$table->addColumn(
			'id', Types::BIGINT,
			[
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]
		);
		$table->addColumn(
			'token', Types::STRING,
			[
				'notnull' => false,
				'length' => 63
			]
		);
		$table->addColumn(
			'stream_id', Types::STRING,
			[
				'notnull' => false,
				'length' => 255,
				'default' => ''
			]
		);
		$table->addColumn(
			'type', Types::STRING,
			[
				'notnull' => false,
				'length' => 31,
				'default' => ''
			]
		);
		$table->addColumn(
			'status', Types::SMALLINT,
			[
				'notnull' => false,
				'length' => 1,
				'default' => 0,
			]
		);
		$table->addColumn(
			'tries', Types::SMALLINT,
			[
				'notnull' => false,
				'length' => 2,
				'default' => 0,
			]
		);
		$table->addColumn(
			'last', Types::DATETIME,
			[
				'notnull' => false,
			]
		);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['token']);
	}


	/**
	 * @param ISchemaWrapper $schema
	 */
	private function createStreamTags(ISchemaWrapper $schema) {
		if ($schema->hasTable('social_stream_tag')) {
			return;
		}

		$table = $schema->createTable('social_stream_tag');

		$table->addColumn(
			'stream_id', Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'default' => ''
			]
		);
		$table->addColumn(
			'hashtag', Types::STRING,
			[
				'notnull' => false,
				'length' => 127,
				'default' => ''
			]
		);

		$table->addUniqueIndex(['stream_id', 'hashtag'], 'sh');
	}
}
