<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Social\Model\ActivityPub\Activity;

use JsonSerializable;
use OCA\Social\Model\ActivityPub\ACore;

/**
 * Class Create
 *
 * @package OCA\Social\Model\ActivityPub\Activity
 */
class Create extends ACore implements JsonSerializable {
	public const TYPE = 'Create';


	/**
	 * Create constructor.
	 *
	 * @param ACore $parent
	 */
	public function __construct($parent = null) {
		parent::__construct($parent);

		$this->setType(self::TYPE);
	}


	/**
	 * @param array $data
	 */
	public function import(array $data) {
		parent::import($data);
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return parent::jsonSerialize();
	}
}
