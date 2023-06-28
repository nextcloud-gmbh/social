<?php

declare(strict_types=1);

/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2023, Maxence Lange <maxence@artificial-owl.com>
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

use OCA\Social\Db\CacheDocumentsRequest;
use OCA\Social\Service\ConfigService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * @deprecated in 0.7.x
 */
class RenameDocumentLocalCopy implements IRepairStep {
	private ConfigService $configService;
	private CacheDocumentsRequest $cacheDocumentsRequest;

	public function __construct(
		ConfigService $configService,
		CacheDocumentsRequest $cacheDocumentsRequest
	) {
		$this->configService = $configService;
		$this->cacheDocumentsRequest = $cacheDocumentsRequest;
	}

	public function getName(): string {
		return 'Rename document local/resized copies';
	}

	public function run(IOutput $output): void {
		if ($this->configService->getAppValueInt('migration_rename_document_copy') === 1) {
			return;
		}

		$oldCopies = $this->cacheDocumentsRequest->getOldFormatCopies();

		$output->startProgress(count($oldCopies));
		foreach ($oldCopies as $copy) {
			$copy->setLocalCopy($this->reformat($copy->getLocalCopy()));
			$copy->setResizedCopy($this->reformat($copy->getResizedCopy()));
			$this->cacheDocumentsRequest->updateCopies($copy);
			$output->advance();
		}
		$output->finishProgress();

		$this->configService->setAppValue('migration_rename_document_copy', '1');
	}

	private function reformat(string $old): string {
		$pos = strrpos($old, '/');
		if (!$pos) {
			return $old;
		}

		return substr($old, $pos + 1);
	}
}
