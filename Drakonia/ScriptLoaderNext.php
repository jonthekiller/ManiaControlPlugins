<?php

namespace Drakonia;

use Exception;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Commands\CommandListener;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;
use ManiaControl\Plugins\Plugin;

/**
 * Adds commands to load scripts or restarting the current one.
 * Based on ScriptLoader done by TGYoshi to propose a compatible version with TM2020
 *
 * @author    jonthekiller
 * @copyright 2020
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class ScriptLoaderNext implements Plugin, CommandListener {
	// Constants
	const PLUGIN_ID                     = 150;
	const PLUGIN_VERSION                = 1.0;
	const PLUGIN_NAME                   = 'ScriptLoaderNext';
	const PLUGIN_AUTHOR                 = 'jonthekiller';
	const SETTING_SCRIPTRELOADAUTHLEVEL = 'Auth level for the scriptreload command';
	const SETTING_SCRIPTLOADAUTHLEVEL   = 'Auth level for the scriptload command';

	const CB_SCRIPT_CHANGED  = 'ScriptLoader.ScriptChanged';
	const CB_SCRIPT_RELOADED = 'ScriptLoader.ScriptReloaded';

	/** @var maniaControl $maniaControl */
	private $maniaControl = null;
	private $scriptsDir   = null;

	/**
	 * Prepares the Plugin
	 *
	 * @param ManiaControl $maniaControl
	 * @return mixed
	 */
	public static function prepare(ManiaControl $maniaControl) {
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SCRIPTRELOADAUTHLEVEL, AuthenticationManager::getPermissionLevelNameArray(AuthenticationManager::AUTH_LEVEL_ADMIN));
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SCRIPTLOADAUTHLEVEL, AuthenticationManager::getPermissionLevelNameArray(AuthenticationManager::AUTH_LEVEL_ADMIN));

		// Register for callbacks
		$this->maniaControl->getCommandManager()->registerCommandListener('scriptreload', $this, 'onCommandScriptReload', true);
		$this->maniaControl->getCommandManager()->registerCommandListener('reloadscript', $this, 'onCommandScriptReload', true);
		$this->maniaControl->getCommandManager()->registerCommandListener('scriptload', $this, 'onCommandScriptLoad', true);
		$this->maniaControl->getCommandManager()->registerCommandListener('loadscript', $this, 'onCommandScriptLoad', true);

		$scriptsDataDir = $maniaControl->getServer()->getDirectory()->getScriptsFolder();

		if ($maniaControl->getServer()->checkAccess($scriptsDataDir)) {
			if ($this->maniaControl->getServer()->titleId == "Trackmania") {
				$game           = "TrackMania";
				$scriptsDataDir = $maniaControl->getServer()->getDirectory()->getUserDataFolder() . "/Scripts/";
			} else {
				$gameShort = $this->maniaControl->getMapManager()->getCurrentMap()->getGame();
				$game      = '';
				switch ($gameShort) {
					case 'tm':
						$game = 'TrackMania';
						break;
					case 'sm':
						$game = 'ShootMania';
						break;
					case 'qm':
						$game = 'QuestMania';
						break;
				}
			}

			if ($game != '') {
				$this->scriptsDir = $scriptsDataDir . 'Modes' . DIRECTORY_SEPARATOR . $game . DIRECTORY_SEPARATOR;
				//				var_dump($this->scriptsDir);
				// Final assertion
				if (!$maniaControl->getServer()->checkAccess($this->scriptsDir)) {
					$this->scriptsDir = null;
				}
			}
		}

		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getId()
	 */
	public static function getId() {
		return self::PLUGIN_ID;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getName()
	 */
	public static function getName() {
		return self::PLUGIN_NAME;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getVersion()
	 */
	public static function getVersion() {
		return self::PLUGIN_VERSION;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getAuthor()
	 */
	public static function getAuthor() {
		return self::PLUGIN_AUTHOR;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getDescription()
	 */
	public static function getDescription() {
		return 'The ScriptLoader plugin can reload the current script (useful for development) or load other scripts by issuing commands. Requires the controller to have access to the UserData directory. Only work with Nadeo gamemodes in TM2020.';
	}

	/**
	 * Handles the //scriptreload and //reloadscript commands.
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function onCommandScriptReload(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SCRIPTRELOADAUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}

		$scriptNameArr = $this->maniaControl->getClient()->getScriptName();
		$scriptName    = $scriptNameArr['CurrentValue'];

		// Workaround for a 'bug' in setModeScriptText.
		if ($scriptName === '<in-development>') {
			$scriptName = $scriptNameArr['NextValue'];
		}

		try {
			$this->loadScript($scriptName);
			$this->maniaControl->getChat()->sendSuccess("Reloaded script " . $scriptName . '!', $player->login);
			$this->maniaControl->getClient()->restartMap();
			$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_SCRIPT_RELOADED);
		} catch (Exception $e) {
			$this->maniaControl->getChat()->sendError("Can not reload script: " . $e->getMessage(), $player->login);
		}
	}

	/**
	 * Handles the //scriptload and //loadscript commands.
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function onCommandScriptLoad(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SCRIPTLOADAUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}

		$params = explode(' ', $chatCallback[1][2], 2);
		if (count($params) === 2) {
			$scriptName = $params[1];


			try {
				$this->loadScript($scriptName);
				$this->maniaControl->getChat()->sendSuccess("Loaded script " . $scriptName . '!', $player->login);
				$this->maniaControl->getClient()->restartMap();
				$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_SCRIPT_CHANGED);
			} catch (Exception $e) {
				$this->maniaControl->getChat()->sendError("Can not reload script: " . $e->getMessage(), $player->login);
			}
		} else {
			$this->maniaControl->getChat()->sendError("Usage: /scriptload [scriptname], scriptname relative to the /Scripts/Modes/[game]/ folder.", $player->login);
		}
	}

	/**
	 * Loads a script if possible, otherwise throws an exception.
	 * $scriptName will be set to a proper capitalized etc. name.
	 *
	 * @param $scriptName
	 * @throws Exception
	 */
	private function loadScript(&$scriptName) {

		if ($this->maniaControl->getServer()->titleId == "Trackmania") {
			$scriptName = "Trackmania/TM_" . $scriptName . "_Online.Script.txt";
			$this->maniaControl->getClient()->setScriptName($scriptName);

		} else {
			if ($this->scriptsDir !== null) {
				// Append .Script.txt if left out
				if (strtolower(substr($scriptName, -11)) !== '.script.txt') {
					$scriptName .= '.Script.txt';
				}
				$scriptPath = $this->scriptsDir . $scriptName;

				if (!file_exists($scriptPath)) {
					throw new Exception('Script not found (' . $scriptPath . ').');
				}
				// Get 'real' script name (mainly nice for Windows)
				$scriptName = pathinfo(realpath($scriptPath))['basename'];

				$script = file_get_contents($scriptPath);
				$this->maniaControl->getClient()->setScriptName($scriptName);
				$this->maniaControl->getClient()->setModeScriptText($script);
			} else {
				throw new Exception('Scripts directory not found (' . $this->scriptsDir . ').');
			}
		}

	}
}
