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

use OCA\Social\Exceptions\HashtagDoesNotExistException;
use OCA\Social\Tools\Traits\TArrayTools;

/**
 * Class HashtagsRequest
 *
 * @package OCA\Social\Db
 */
class HashtagsRequest extends HashtagsRequestBuilder {
	use TArrayTools;


	/**
	 * Insert a new Hashtag.
	 *
	 * @param string $hashtag
	 * @param array $trend
	 */
	public function save(string $hashtag, array $trend) {
		$qb = $this->getHashtagsInsertSql();
		$qb->setValue('hashtag', $qb->createNamedParameter($hashtag))
		   ->setValue('trend', $qb->createNamedParameter(json_encode($trend)));

		$qb->execute();
	}


	/**
	 * Insert a new Hashtag.
	 *
	 * @param string $hashtag
	 * @param array $trend
	 */
	public function update(string $hashtag, array $trend) {
		$qb = $this->getHashtagsUpdateSql();
		$qb->set('trend', $qb->createNamedParameter(json_encode($trend)));
		$this->limitToHashtag($qb, $hashtag);

		$qb->execute();
	}


	/**
	 * @return array
	 */
	public function getAll(): array {
		$qb = $this->getHashtagsSelectSql();

		$hashtags = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$hashtags[] = $this->parseHashtagsSelectSql($data);
		}
		$cursor->closeCursor();

		return $hashtags;
	}


	/**
	 * @param string $hashtag
	 *
	 * @return array
	 * @throws HashtagDoesNotExistException
	 */
	public function getHashtag(string $hashtag): array {
		$qb = $this->getHashtagsSelectSql();

		$this->limitToHashtag($qb, $hashtag);

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new HashtagDoesNotExistException();
		}

		return $this->parseHashtagsSelectSql($data);
	}


	/**
	 * @param string $hashtag
	 * @param bool $all
	 *
	 * @return array
	 */
	public function searchHashtags(string $hashtag, bool $all): array {
		$qb = $this->getHashtagsSelectSql();
		$this->searchInHashtag($qb, $hashtag, $all);
		$this->limitResults($qb, 25);

		$hashtags = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$hashtags[] = $this->parseHashtagsSelectSql($data);
		}
		$cursor->closeCursor();

		return $hashtags;
	}
}
