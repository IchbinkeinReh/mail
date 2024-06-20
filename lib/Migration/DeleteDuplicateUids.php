<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Migration;

use OCA\Mail\Db\MessageMapper;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class DeleteDuplicateUids implements IRepairStep {
	public function __construct(
		private MessageMapper $messageMapper,
	) {
	}

	public function getName(): string {
		return 'Delete duplicated cached messages';
	}

	public function run(IOutput $output): void {
		$this->messageMapper->deleteDuplicateUids();
	}
}
