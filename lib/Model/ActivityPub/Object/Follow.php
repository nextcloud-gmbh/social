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


namespace OCA\Social\Model\ActivityPub\Object;

use OCA\Social\Entity\Follow as FollowEntitiy;
use JsonSerializable;
use OCA\Social\Model\ActivityPub\ACore;

/**
 * Virtual rep
 * Class Follow
 *
 */
class Follow extends ACore implements JsonSerializable {
	public const TYPE = 'Follow';

	static public function create(FollowEntitiy $follow): self {
		$followActivity = new Follow();
		$followActivity->setId($follow->getUri() ?: $follow->getAccount()->getUri() . '#follows/' . $follow->getId());
		$followActivity->setActor($follow->getAccount());
		$followActivity->setVirtualObject();
		return $followActivity
	}
	public function __construct($parent = null) {
		parent::__construct($parent);
		$this->setType(self::TYPE);
	}


	/**
	 * @return string
	 */
	public function getFollowId(): string {
		return $this->followId;
	}

	/**
	 * @param string $followId
	 *
	 * @return Follow
	 */
	public function setFollowId(string $followId): Follow {
		$this->followId = $followId;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getFollowIdPrim(): string {
		return $this->followIdPrim;
	}

	/**
	 * @param string $followIdPrim
	 *
	 * @return Follow
	 */
	public function setFollowIdPrim(string $followIdPrim): Follow {
		$this->followIdPrim = $followIdPrim;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isAccepted(): bool {
		return $this->accepted;
	}

	/**
	 * @param bool $accepted
	 *
	 * @return Follow
	 */
	public function setAccepted(bool $accepted): Follow {
		$this->accepted = $accepted;

		return $this;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		$result = parent::jsonSerialize();

		if ($this->isCompleteDetails()) {
			$result = array_merge(
				$result,
				[
				]
			);
		}

		return $result;
	}
}
