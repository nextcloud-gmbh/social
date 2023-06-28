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

use OCA\Social\Model\ActivityPub\Object\Note;
use OCA\Social\Model\ActivityPub\Stream;
use OCA\Social\Tools\Traits\TStringTools;
use OCP\DB\Exception as DBException;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Class StreamTagsRequest
 *
 * @package OCA\Social\Db
 */
class StreamTagsRequest extends StreamTagsRequestBuilder {
	use TStringTools;

	public function generateStreamTags(Stream $stream): void {
		if ($stream->getType() !== Note::TYPE) {
			return;
		}

		/** @var Note $stream */
		foreach ($stream->getHashTags() as $hashtag) {
			$qb = $this->getStreamTagsInsertSql();
			$streamId = $qb->prim($stream->getId());
			$qb->setValue('stream_id', $qb->createNamedParameter($streamId));
			$qb->setValue('hashtag', $qb->createNamedParameter($hashtag));
			try {
				$qb->executeStatement();
			} catch (DBException $e) {
				Server::get(LoggerInterface::class)
							->log(1, 'Social - Duplicate hashtag on Stream ' . json_encode($stream));
			}
		}
	}

	public function emptyStreamTags(): void {
		$qb = $this->getQueryBuilder();
		$qb->delete(self::TABLE_STREAM_TAGS);

		$qb->executeStatement();
	}
}
