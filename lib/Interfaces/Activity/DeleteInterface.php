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


namespace OCA\Social\Interfaces\Activity;


use OCA\Social\AP;
use OCA\Social\Exceptions\InvalidOriginException;
use OCA\Social\Exceptions\ItemNotFoundException;
use OCA\Social\Exceptions\ItemUnknownException;
use OCA\Social\Interfaces\IActivityPubInterface;
use OCA\Social\Model\ActivityPub\ACore;
use OCA\Social\Service\MiscService;

class DeleteInterface implements IActivityPubInterface {


	/** @var MiscService */
	private $miscService;


	/**
	 * UndoService constructor.
	 *
	 * @param MiscService $miscService
	 */
	public function __construct(MiscService $miscService) {
		$this->miscService = $miscService;
	}


	/**
	 * @param ACore $item
	 *
	 * @throws InvalidOriginException
	 */
	public function processIncomingRequest(ACore $item) {
		$item->checkOrigin($item->getId());

		if (!$item->gotObject()) {

			if ($item->getObjectId() !== '') {
				$item->checkOrigin($item->getObjectId());

				// TODO: migrate to activity() !!
				$types = ['Note', 'Person'];
				foreach ($types as $type) {
					try {
						$item->checkOrigin($item->getObjectId());
						$interface = AP::$activityPub->getInterfaceFromType($type);
						$object = $interface->getItemById($item->getObjectId());
						$interface->delete($object);

						return;
					} catch (InvalidOriginException $e) {
					} catch (ItemNotFoundException $e) {
					} catch (ItemUnknownException $e) {
					}
				}
			}

			return;
		}

		$object = $item->getObject();
		try {
			$item->checkOrigin($object->getId());
			// FIXME: needed ? better use activity()
//			$interface = AP::$activityPub->getInterfaceForItem($object);
//			$interface->delete($object);
		} catch (InvalidOriginException $e) {
		} catch (ItemUnknownException $e) {
		}
	}


	/**
	 * @param ACore $item
	 */
	public function processResult(ACore $item) {
	}


	/**
	 * @param ACore $item
	 *
	 * @return ACore
	 * @throws ItemNotFoundException
	 */
	public function getItem(ACore $item): ACore {
		throw new ItemNotFoundException();
	}


	/**
	 * @param string $id
	 *
	 * @return ACore
	 * @throws ItemNotFoundException
	 */
	public function getItemById(string $id): ACore {
		throw new ItemNotFoundException();
	}


	/**
	 * @param ACore $activity
	 * @param ACore $item
	 */
	public function activity(Acore $activity, ACore $item) {
	}


	/**
	 * @param ACore $item
	 */
	public function save(ACore $item) {
	}


	/**
	 * @param ACore $item
	 */
	public function update(ACore $item) {
	}


	/**
	 * @param ACore $item
	 */
	public function delete(ACore $item) {
	}


	/**
	 * @param ACore $item
	 * @param string $source
	 */
	public function event(ACore $item, string $source) {
	}


}

