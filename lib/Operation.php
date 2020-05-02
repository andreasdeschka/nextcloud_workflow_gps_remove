<?php
/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
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

namespace OCA\WorkflowGPSRemove;

use OC\Files\View;
use OCA\WorkflowEngine\Entity\File;
use OCA\WorkflowGPSRemove\BackgroundJobs\Launcher;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\SystemTag\MapperEvent;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IRuleMatcher;
use OCP\WorkflowEngine\ISpecificOperation;
use Symfony\Component\EventDispatcher\GenericEvent as LegacyGenericEvent;

class Operation implements ISpecificOperation {

	/** @var IManager */
	private $workflowEngineManager;
	/** @var IJobList */
	private $jobList;
	/** @var IL10N */
	private $l;
	/** @var IUserSession */
	private $session;
	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(IManager $workflowEngineManager, IJobList $jobList, IL10N $l, IUserSession $session, IRootFolder $rootFolder) {
		$this->workflowEngineManager = $workflowEngineManager;
		$this->jobList = $jobList;
		$this->l = $l;
		$this->session = $session;
		$this->rootFolder = $rootFolder;
	}
	
	public function validateOperation(string $name, array $checks, string $exifparameters): void {
		$which = shell_exec('command -v exiftool');
		if (empty($which)) {
			throw new \UnexpectedValueException($this->l->t('exiftool is not excecutable'));
		}
	}

	public function getDisplayName(): string {
		return $this->l->t('workflow_gpsremove');
	}

	public function getDescription(): string {
		return $this->l->t('workflow_gpsremove');
	}

	public function getIcon(): string {
		return \OC::$server->getURLGenerator()->imagePath('workflow_gpsremove', 'app.svg');
	}

	public function isAvailableForScope(int $scope): bool {
		return $scope === IManager::SCOPE_ADMIN;
	}

	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		if (!$event instanceof GenericEvent
			&& !$event instanceof LegacyGenericEvent
			&& !$event instanceof MapperEvent) {
			return;
		}
		try {
			if ($event instanceof MapperEvent) {
				if ($event->getObjectType() !== 'files') {
					return;
				}
				$nodes = $this->rootFolder->getById($event->getObjectId());
				if (!isset($nodes[0])) {
					return;
				}
				$node = $nodes[0];
				unset($nodes);
			} else {
				$node = $event->getSubject();
			}

			// TODO: better document
			// '', admin, 'files', 'path/to/file.txt'
			list(, , $folder,) = explode('/', $node->getPath(), 4);
			if ($folder !== 'files' || $node instanceof Folder) {
				return;
			}

			$matches = $ruleMatcher->getFlows(false);

			foreach ($matches as $match) {
				$args = array();
				// not needed yet
				//$args['params'] = $match['operation'];
				$args['path'] = $node->getPath();
				$user = $this->session->getUser();
				$userID = '';
				if ($user instanceof IUser) {
					$userID = $user->getUID();
				}
				$args[ 'user' ] = $userID;
				$this->jobList->add(Launcher::class, $args);
			}
		} catch (NotFoundException $e) {
		}
	}

	public function getEntityId(): string {
		return File::class;
	}
}
