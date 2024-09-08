<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Social\Interfaces\Actor;

use OCA\Social\Interfaces\IActivityPubInterface;

/**
 * Class ApplicationInterface
 *
 * @package OCA\Social\Service\ActivityPub
 */
class ApplicationInterface extends PersonInterface implements IActivityPubInterface {
}
