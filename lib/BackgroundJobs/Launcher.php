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

namespace OCA\WorkflowGPSRemove\BackgroundJobs;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ILogger;
use OCP\ITempManager;

class Launcher extends \OC\BackgroundJob\QueuedJob {

	/** @var ILogger */
	protected $logger;
	/** @var ITempManager */
	private $tempManager;
	/** @var IRootFolder */
	private $rootFolder;

	/**
	 * BackgroundJob constructor.
	 *
	 * @param ILogger $logger
	 */
	public function __construct(ILogger $logger, ITempManager $tempManager, IRootFolder $rootFolder) {
		$this->logger = $logger;
		$this->tempManager = $tempManager;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * @param mixed $argument
	 */
	protected function run($argument) {
		try {
			$user = $argument['user'];
			\OC_Util::setupFS($user);
			$path = isset($argument['path']) ? (string)$argument['path'] : '';
			file_put_contents('/tmp/outdebug2.txt','  run  '.$path."\n",FILE_APPEND);
			$view = new \OC\Files\View(dirname($path));
			$tmpFile = $view->toTmpFile(basename($path));
			shell_exec('exiftool -overwrite_original_in_place "-gps*=" '.$tmpFile);
			$view->fromTmpFile($tmpFile,basename($path));
			file_put_contents('/tmp/outdebug2.txt','  finish  '.$path."\n",FILE_APPEND);
		} catch (\Exception $e) {
			$this->logger->logException($e, ['level' => ILogger::WARN, 'app' => 'workflow_gpsremove']);
			return;
		}
	}
}
