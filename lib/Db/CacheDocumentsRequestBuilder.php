<?php

declare(strict_types=1);


/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
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

namespace OCA\Social\Db;

use OCA\Social\Model\ActivityPub\Object\Document;
use OCA\Social\Tools\Traits\TArrayTools;

class CacheDocumentsRequestBuilder extends CoreRequestBuilder {
	use TArrayTools;

	protected function getCacheDocumentsInsertSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->insert(self::TABLE_CACHE_DOCUMENTS);

		return $qb;
	}

	/**
	 * Base of the Sql Update request
	 */
	protected function getCacheDocumentsUpdateSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->update(self::TABLE_CACHE_DOCUMENTS);

		return $qb;
	}

	/**
	 * Base of the Sql Select request for Shares
	 */
	protected function getCacheDocumentsSelectSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();

		$qb->select(
			'cd.nid', 'cd.id', 'cd.type', 'cd.parent_id', 'cd.account',
			'cd.media_type', 'cd.mime_type', 'cd.url', 'cd.local_copy', 'cd.public',
			'cd.error', 'cd.creation', 'cd.caching', 'cd.resized_copy', 'cd.meta',
			'cd.blurhash', 'cd.description'
		)
		   ->from(self::TABLE_CACHE_DOCUMENTS, 'cd');

		$this->defaultSelectAlias = 'cd';
		$qb->setDefaultSelectAlias('cd');

		return $qb;
	}


	/**
	 * Base of the Sql Delete request
	 */
	protected function getCacheDocumentsDeleteSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->delete(self::TABLE_CACHE_DOCUMENTS);

		return $qb;
	}

	public function parseCacheDocumentsSelectSql(array $data): Document {
		$document = new Document();
		$document->importFromDatabase($data);

		return $document;
	}
}
