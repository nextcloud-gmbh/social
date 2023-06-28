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

use DateTime;
use Exception;
use OCA\Social\Exceptions\CacheDocumentDoesNotExistException;
use OCA\Social\Model\ActivityPub\Object\Document;
use OCP\DB\QueryBuilder\IQueryBuilder;

class CacheDocumentsRequest extends CacheDocumentsRequestBuilder {
	public const CACHING_TIMEOUT = 5; // 5 min

	public function save(Document $document): void {
		$qb = $this->getCacheDocumentsInsertSql();
		$qb->setValue('id', $qb->createNamedParameter($document->getId()))
		   ->setValue('id_prim', $qb->createNamedParameter($qb->prim($document->getId())))
		   ->setValue('account', $qb->createNamedParameter($document->getAccount()))
		   ->setValue('type', $qb->createNamedParameter($document->getType()))
		   ->setValue('url', $qb->createNamedParameter($document->getUrl()))
		   ->setValue('media_type', $qb->createNamedParameter($document->getMediaType()))
		   ->setValue('mime_type', $qb->createNamedParameter($document->getMimeType()))
		   ->setValue('error', $qb->createNamedParameter($document->getError()))
		   ->setValue('local_copy', $qb->createNamedParameter($document->getLocalCopy()))
		   ->setValue('resized_copy', $qb->createNamedParameter($document->getResizedCopy()))
		   ->setValue('blurhash', $qb->createNamedParameter($document->getBlurHash()))
		   ->setValue('description', $qb->createNamedParameter($document->getDescription()))
		   ->setValue('parent_id', $qb->createNamedParameter($document->getParentId()))
		   ->setValue('parent_id_prim', $qb->createNamedParameter($qb->prim($document->getParentId())))
		   ->setValue('public', $qb->createNamedParameter(($document->isPublic()) ? '1' : '0'));

		// generate Meta
		$document->convertToMediaAttachment();
		if ($document->getMeta() !== null) {
			$qb->setValue('meta', $qb->createNamedParameter(json_encode($document->getMeta())));
		}

		try {
			$qb->setValue(
				'creation',
				$qb->createNamedParameter(new DateTime('now'), IQueryBuilder::PARAM_DATE)
			);
		} catch (Exception $e) {
		}

		$qb->executeStatement();
		$document->setNid($qb->getLastInsertId());
	}


	/**
	 * Insert cache about an Actor in database.
	 */
	public function update(Document $document): void {
		$qb = $this->getCacheDocumentsUpdateSql();
		$qb->set('type', $qb->createNamedParameter($document->getType()))
		   ->set('url', $qb->createNamedParameter($document->getUrl()))
		   ->set('media_type', $qb->createNamedParameter($document->getMediaType()))
		   ->set('mime_type', $qb->createNamedParameter($document->getMimeType()))
		   ->set('error', $qb->createNamedParameter($document->getError()))
		   ->set('local_copy', $qb->createNamedParameter($document->getLocalCopy()))
		   ->set('resized_copy', $qb->createNamedParameter($document->getResizedCopy()))
		   ->set('blurhash', $qb->createNamedParameter($document->getBlurHash()))
		   ->set('description', $qb->createNamedParameter($document->getDescription()))
		   ->set('parent_id', $qb->createNamedParameter($document->getParentId()))
		   ->set('parent_id_prim', $qb->createNamedParameter($qb->prim($document->getParentId())))
		   ->set('public', $qb->createNamedParameter(($document->isPublic()) ? '1' : '0'));

		try {
			$qb->set(
				'creation',
				$qb->createNamedParameter(new DateTime('now'), IQueryBuilder::PARAM_DATE)
			);
		} catch (Exception $e) {
		}

		$qb->limitToIdPrim($qb->prim($document->getId()));
		$qb->executeStatement();
	}


	/**
	 * @throws \OCP\DB\Exception
	 */
	public function initCaching(Document $document): void {
		$qb = $this->getCacheDocumentsUpdateSql();
		$this->limitToIdString($qb, $document->getId());

		try {
			$qb->set(
				'caching', $qb->createNamedParameter(new DateTime('now'), IQueryBuilder::PARAM_DATE)
			);
		} catch (Exception $e) {
		}

		$qb->executeStatement();
	}


	/**
	 * @throws \OCP\DB\Exception
	 */
	public function endCaching(Document $document): void {
		$qb = $this->getCacheDocumentsUpdateSql();
		$this->limitToIdString($qb, $document->getId());
		$qb->set('local_copy', $qb->createNamedParameter($document->getLocalCopy()));
		$qb->set('resized_copy', $qb->createNamedParameter($document->getResizedCopy()));
		$qb->set('blurhash', $qb->createNamedParameter($document->getBlurHash()));
		$qb->set('description', $qb->createNamedParameter($document->getDescription()));
		$qb->set('error', $qb->createNamedParameter($document->getError()));

		$qb->executeStatement();
	}


	/**
	 * @param string $url
	 *
	 * @return Document
	 * @throws CacheDocumentDoesNotExistException
	 */
	public function getByUrl(string $url) {
		$qb = $this->getCacheDocumentsSelectSql();
		$this->limitToUrl($qb, $url);

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new CacheDocumentDoesNotExistException();
		}

		return $this->parseCacheDocumentsSelectSql($data);
	}


	/**
	 * @param array $mediaIds
	 * @param string $account - limit to account
	 *
	 * @return Document[]
	 */
	public function getFromArray(array $mediaIds, string $account = ''): array {
		$qb = $this->getCacheDocumentsSelectSql();
		$qb->limitToDBFieldArray('nid', $mediaIds);
		$qb->limitToAccount($account);

		$documents = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$documents[] = $this->parseCacheDocumentsSelectSql($data);
		}
		$cursor->closeCursor();

		return $documents;
	}

	/**
	 * @param string $id
	 * @param bool $public
	 * @param bool $useNid
	 *
	 * @return Document
	 * @throws CacheDocumentDoesNotExistException
	 */
	public function getById(string $id, bool $public = false) {
		$qb = $this->getCacheDocumentsSelectSql();
		$qb->limitToIdPrim($qb->prim($id));

		if ($public === true) {
			$this->limitToPublic($qb);
		}

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new CacheDocumentDoesNotExistException();
		}

		return $this->parseCacheDocumentsSelectSql($data);
	}


	/**
	 * @param Document $item
	 *
	 * @return bool
	 */
	public function isDuplicate(Document $item): bool {
		$qb = $this->getCacheDocumentsSelectSql();
		$this->limitToUrl($qb, $item->getUrl());
		$this->limitToParentId($qb, $item->getParentId());

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		return ($data !== false);
	}


	/**
	 * @return Document[]
	 * @throws Exception
	 */
	public function getNotCachedDocuments() {
		$qb = $this->getCacheDocumentsSelectSql();
		$this->limitToDBFieldEmpty($qb, 'local_copy');
		$this->limitToCaching($qb, self::CACHING_TIMEOUT);
		$this->limitToDBFieldInt($qb, 'error', 0);

		$documents = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$documents[] = $this->parseCacheDocumentsSelectSql($data);
		}
		$cursor->closeCursor();

		return $documents;
	}


	/**
	 * @param string $url
	 */
	public function deleteByUrl(string $url) {
		$qb = $this->getCacheDocumentsDeleteSql();
		$this->limitToUrl($qb, $url);

		$qb->execute();
	}


	/**
	 * @param string $id
	 */
	public function deleteById(string $id) {
		$qb = $this->getCacheDocumentsDeleteSql();
		$this->limitToIdString($qb, $id);

		$qb->execute();
	}

	public function deleteByParent(string $parentId): void {
		$qb = $this->getCacheDocumentsDeleteSql();
		$qb->limitToDBField('parent_id_prim', $qb->prim($parentId));

		$qb->executeStatement();
	}


	public function moveAccount(string $actorId, string $newId): void {
		$qb = $this->getCacheDocumentsUpdateSql();
		$qb->set('parent_id', $qb->createNamedParameter($newId))
		   ->set('parent_id_prim', $qb->createNamedParameter($qb->prim($newId)));

		$qb->limitToDBField('parent_id_prim', $qb->prim($actorId));

		$qb->executeStatement();
	}

	/**
	 * @return Document[]
	 * @deprecated in 0.7.x
	 */
	public function getOldFormatCopies(): array {
		$qb = $this->getCacheDocumentsSelectSql();

		$expr = $qb->expr();
		$qb->andWhere(
			$expr->orX(
				$expr->iLike('local_copy', $qb->createNamedParameter('%/%')),
				$expr->iLike('resized_copy', $qb->createNamedParameter('%/%'))
			)
		);

		$documents = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$documents[] = $this->parseCacheDocumentsSelectSql($data);
		}
		$cursor->closeCursor();

		return $documents;
	}

	/**
	 * @deprecated in 0.7.x
	 */
	public function updateCopies(Document $document): void {
		$qb = $this->getCacheDocumentsUpdateSql();
		$qb->set('local_copy', $qb->createNamedParameter($document->getLocalCopy()))
		   ->set('resized_copy', $qb->createNamedParameter($document->getResizedCopy()));

		$qb->limitToIdPrim($qb->prim($document->getId()));
		$qb->executeStatement();
	}
}
