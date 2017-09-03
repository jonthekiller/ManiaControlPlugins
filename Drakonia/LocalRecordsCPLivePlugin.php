<?php

namespace Drakonia;


use FML\Controls\Frame;
use FML\Controls\Label;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Utils\Formatter;
use ManiaControl\Commands\CommandListener;

class LocalRecordsCPLivePlugin implements CallbackListener, TimerListener, Plugin, CommandListener {

	/*
	* Constants
	*/
	const PLUGIN_ID      = 115;
	const PLUGIN_VERSION = 0.33;
	const PLUGIN_NAME    = 'LocalRecordsCPLivePlugin';
	const PLUGIN_AUTHOR  = 'jonthekiller, Jaka Vrhovec';


	const SETTING_LRCPLIVE_ACTIVATED = 'LocalRecordsCPLivePlugin Activated';
	const MLID_LRCPLIVE_WIDGET              = 'LocalRecordsCPLivePlugin.Widget';
	const SETTING_LRCPLIVE_WIDGET1_POSX      = 'Top1-Position: X';
	const SETTING_LRCPLIVE_WIDGET1_POSY      = 'Top1-Position: Y';
	const SETTING_LRCPLIVE_WIDGET2_POSX      = 'MyTop-Position: X';
	const SETTING_LRCPLIVE_WIDGET2_POSY      = 'MyTop-Position: Y';
	const SETTING_LRCPLIVE_WIDGET3_POSX      = 'DediTop1-Position: X';
	const SETTING_LRCPLIVE_WIDGET3_POSY      = 'DediTop1-Position: Y';
	const SETTING_LRCPLIVE_TEXT_SIZE      = 'Text Size';
	const SETTING_LRCPLIVE_SHOWPLAYERS      = 'Show for Players';
	const SETTING_LRCPLIVE_SHOWSPECTATORS     = 'Show for Spectators';


	const DEFAULT_LOCALRECORDS_PLUGIN = 'MCTeam\LocalRecordsPlugin';
	const DEFAULT_DEDIMANIA_PLUGIN = 'MCTeam\Dedimania\DedimaniaPlugin';

	/** @var ManiaControl $maniaControl */
	private $maniaControl         = null;
	private $active = false;
	private $dediactive = false;
	private $LocalRecordsPlugin = "";
	private $DedimaniaPlugin = "";
	private $localrecord = array();
	private $toprecord = array();
	private $playersrecords = array();
	private $spectateview = array();
	private $topdedimania = array();

	private $currentPlayerCps = array();

	/**
	 * @see \ManiaControl\Plugins\Plugin::prepare()
	 */
	public static function prepare(ManiaControl $maniaControl) {
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
		return 'Plugin to display CP gap time with Local Records';
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_ACTIVATED, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_TEXT_SIZE, 2);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_WIDGET1_POSX, -45);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_WIDGET1_POSY, 70);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_WIDGET2_POSX, 45);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_WIDGET2_POSY, 70);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_WIDGET3_POSX, 0);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_WIDGET3_POSY, 70);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_SHOWPLAYERS, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_SHOWSPECTATORS, true);

		// Callbacks

		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle2Seconds', 2000);
		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle60Seconds', 60000);

		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONEVENTSTARTLINE, $this, 'handleBeginRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONFINISHLINE, $this, 'handleFinishCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::BEGINMAP, $this, 'handleBeginMapCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONWAYPOINT, $this, 'handleCheckpointCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERINFOCHANGED, $this, 'handlePlayerInfoChanged');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::ENDMAP, $this, 'handleEndMapCallback');

		$this->maniaControl->getCommandManager()->registerCommandListener('cpdedi', $this, 'playerCpsComparedToDedi', false, 'Writes player cps compared to dedi1 time');
		$this->maniaControl->getCommandManager()->registerCommandListener('cplocal', $this, 'playerCpsComparedToLocal', false, 'Writes player cps compared to local1 time');
		$this->maniaControl->getCommandManager()->registerCommandListener('cplocalprev', $this, 'playerCurrentCpsComparedToLocal', false, 'Writes player current cps compared to local1 time');
		$this->maniaControl->getCommandManager()->registerCommandListener('cpdediprev', $this, 'playerCurrentCpsComparedToDedi', false, 'Writes player current cps compared to dedi1 time');
		$this->maniaControl->getCommandManager()->registerCommandListener('cpme', $this, 'playerCurrentCpsComparedHisBestLocal', false, 'Writes player current cps compared to his local1');

		$this->init();

		return true;

	}


	public function init()
	{

		$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$player) {
			$this->LocalRecordsPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_LOCALRECORDS_PLUGIN);
			$this->DedimaniaPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_DEDIMANIA_PLUGIN);

			if($this->LocalRecordsPlugin)
			{
				$this->active = true;
				Logger::log("Can load LRCPLive plugin");
			}else{
				$this->maniaControl->getChat()->sendErrorToAdmins('Please activate first the LocalRecords plugin');
			}

			if($this->DedimaniaPlugin)
			{
				$this->dediactive = true;
				Logger::log("Can load LRCPLive Dedimania plugin");
			}else{
				$this->maniaControl->getChat()->sendErrorToAdmins('Please activate first the Dedimania plugin');
			}

			$players = $this->maniaControl->getPlayerManager()->getPlayers();

			foreach ($players as $player) {
				$this->initTimes($player);
			}

			//            Logger::log("Init LRCPLive finished");
		}, 500);


	}


	private function initTimes(Player $player)
	{
		if($this->active)
		{
			$map = $this->maniaControl->getMapManager()->getCurrentMap();
			if(empty($this->toprecord))
			{
				$this->toprecord = $this->LocalRecordsPlugin->getLocalRecords($map, 1);
			}
			$this->localrecord = $this->LocalRecordsPlugin->getLocalRecord($map, $player);

			//            if($this->dediactive) {
			//                Logger::log("Start get Dedimania Records");
			//                $this->topdedimania = $this->DedimaniaPlugin->getDedimaniaRecords();
			//
			//                if (isset($this->topdedimania[0]))
			//                    var_dump($this->topdedimania[0]->checkpoints);
			//            }
			//var_dump($this->localrecord);
			if($this->localrecord) {
				$this->playersrecords[$player->login]["checkpoints"] = $this->localrecord->checkpoints;
				$this->playersrecords[$player->login]["time"] = $this->localrecord->time;
			}
		}
	}

	public function handle2Seconds()
	{
		if($this->active) {
			$map = $this->maniaControl->getMapManager()->getCurrentMap();
			$this->toprecord = $this->LocalRecordsPlugin->getLocalRecords($map, 1);
		}
	}

	public function handle60Seconds()
	{
		if($this->active) {
			if($this->dediactive) {
				//                Logger::log("Start get Dedimania Records");
				$this->topdedimania = $this->DedimaniaPlugin->getDedimaniaRecords();

				//                if (isset($this->topdedimania[0]))
				//                    var_dump($this->topdedimania[0]->checkpoints);
			}
		}
	}

	/**
	 * Handle PlayerConnect callback
	 *
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player)
	{
		if($this->active){
			$this->initTimes($player);
		}
	}

	public function handlePlayerDisconnect(Player $player)
	{
		if($this->active){
			unset($this->spectateview[$player->login]);
		}
	}

	public function handlePlayerInfoChanged(Player $player) {
		$this->closeWidget(self::MLID_LRCPLIVE_WIDGET, $player->login);
		//        Logger::log("Current Target: " . $player->currentTargetId . " for " . $player->pid);
		$this->spectateview[$player->login] = $player->currentTargetId;

	}

	public function handleBeginMapCallback()
	{
		if($this->active) {
			$this->playersrecords = array();
			$this->toprecord = array();
			$players = $this->maniaControl->getPlayerManager()->getPlayers();
			foreach ($players as $player) {
				$this->initTimes($player);
			}

			if($this->dediactive) {
				//                Logger::log("Start get Dedimania Records");
				$this->topdedimania = $this->DedimaniaPlugin->getDedimaniaRecords();

				//                if (isset($this->topdedimania[0]))
				//                    var_dump($this->topdedimania[0]->checkpoints);
			}

			$this->currentPlayerCps = array();

		}
	}


	public function handleBeginRoundCallback()
	{
		//$this->initTimes($player);
	}

	public function handleEndMapCallback()
	{
		$this->playersrecords = array();
		$this->toprecord = array();
	}

	public function handleFinishCallback(OnWayPointEventStructure $structure){

		if($this->active){
			$this->displayWidgets($structure, 1);
			$player = $structure->getPlayer();
			$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$player) {
				$this->initTimes($player);
			}, 1000);
			if(array_key_exists($structure->getPlayer()->login, $this->currentPlayerCps)) {
				unset($this->currentPlayerCps[$structure->getPlayer()->login]);
			}
			$this->currentPlayerCps[$structure->getPlayer()->login] = $structure->getPlainJsonObject()->curlapcheckpoints;
		}

	}

	/**
	 * Display the Widget
	 */
	private function displayWidgets(OnWayPointEventStructure $structure, $finish = 0) {

		// Display Local Records Checkpoints Live Widget

		if($this->active){
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LRCPLIVE_ACTIVATED)) {
				$this->displayLRCPLiveWidget($structure, $finish);
			}
		}


	}

	private function timeDifferenceWithColor($playerGapTime) {
		if ($playerGapTime < 0) {
			return "\$00f".Formatter::formatTime(-($playerGapTime));
		} elseif ($playerGapTime > 0) {
			return "\$f00".Formatter::formatTime($playerGapTime);
		} else {
			return "\$fff0";
		}
	}

	/**
	 * Get player current cps compared to his best local
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function playerCurrentCpsComparedHisBestLocal(array $chat, Player $player) {
		$message = "\$f00Check that LOCAL record plugin is enabled, that there are local records and that you yourself have a local record!";

		if($this->playersrecords && array_key_exists($player->login, $this->currentPlayerCps)) {
			if(isset($this->playersrecords[$player->login])) {
				$map            = $this->maniaControl->getMapManager()->getCurrentMap();
				$currentPlayerCps = $this->currentPlayerCps[$player->login];
				$playerBestTime = $currentPlayerCps[sizeof($currentPlayerCps) - 1];
				$currentPlayerCps = array_slice($currentPlayerCps, 0 , count($currentPlayerCps) - 1);

				if(isset($playerBestTime)) {
					$playerBestCps = $currentPlayerCps;

					$topLocalTime = $this->LocalRecordsPlugin->getLocalRecord($map, $player)->time;
					$topLocalCps  = explode(",", $this->LocalRecordsPlugin->getLocalRecord($map, $player)->checkpoints);

					if(!empty($topLocalCps) && !empty($topLocalCps)) {
						$message = "\$fffPrevious CPS to your best LOCAL: ";
						for($i = 0; $i < count($playerBestCps); $i++) {
							if(isset($playerBestCps[$i]) && isset($topLocalCps[$i])) {
								$playerGapTime = ($playerBestCps[$i] - intval($topLocalCps[$i]));
								$message .= "\$fff[".($i + 1)."]".$this->timeDifferenceWithColor($playerGapTime)."\$fff, ";
							}
						}

						if(isset($topLocalTime) && isset($playerBestTime)) {
							$playerGapTime = $playerBestTime - intval($topLocalTime);
							$message .= "\$fff[F]".$this->timeDifferenceWithColor($playerGapTime)."\$fff.";
						}
					}
				}
			}
		}

		$this->maniaControl->getChat()->sendChat($message, $player);
	}

	/**
	 * Get player cps compared to top 1 dedi
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function playerCpsComparedToDedi(array $chat, Player $player) {

		$message = "\$f00Check that DEDIMANIA plugin is enabled, that there are dedimania records and that you yourself have a local record!";

		if ($this->dediactive && $this->playersrecords) {
			if (isset($this->playersrecords[$player->login])) {
				$map            = $this->maniaControl->getMapManager()->getCurrentMap();
				$playerBestTime = $this->LocalRecordsPlugin->getLocalRecord($map, $player)->time;
				$playerBestCps  = explode(",", $this->LocalRecordsPlugin->getLocalRecord($map, $player)->checkpoints);

				$dediBestCps = array();
				if (isset($this->topdedimania[0]->checkpoints)) {
					$dediBestCps = explode(",", $this->topdedimania[0]->checkpoints);
				}
				if (count($dediBestCps) != 0 && count($playerBestCps) != 0) {
					$message      = "\$fffCPS to DEDI 1: ";
					$dediBestTime = $dediBestCps[count($dediBestCps) - 1];

					for ($i = 0; $i < count($playerBestCps); $i++) {
						if (isset($playerBestCps[$i]) && isset($dediBestCps[$i])) {
							$playerGapTime = $playerBestCps[$i] - $dediBestCps[$i];

							$message .= "\$fff[" .($i + 1 ). "]" . $this->timeDifferenceWithColor($playerGapTime) . "\$fff, ";
						}
					}

					if (isset($dediBestTime) && isset($playerBestTime)) {
						$playerGapTime = $playerBestTime - $dediBestTime;
						$message       .= "\$fff[F]" . $this->timeDifferenceWithColor($playerGapTime) . "\$fff.";
					}
				}
			}
		}

		$this->maniaControl->getChat()->sendChat($message, $player);

	}

	/**
	 * Get player current cps compared to top 1 dedi
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function playerCurrentCpsComparedToDedi(array $chat, Player $player) {

		$message = "\$f00Check that DEDIMANIA plugin is enabled, that there are dedimania records and that you yourself have a local record!";

		if ($this->dediactive && $this->playersrecords && array_key_exists($player->login, $this->currentPlayerCps)) {
			if (isset($this->playersrecords[$player->login])) {
				$currentPlayerCps = $this->currentPlayerCps[$player->login];
				$playerBestTime = $currentPlayerCps[sizeof($currentPlayerCps) - 1];
				$currentPlayerCps = array_slice($currentPlayerCps, 0 , count($currentPlayerCps) - 1);

				$dediBestCps = array();
				if (isset($this->topdedimania[0]->checkpoints)) {
					$dediBestCps = explode(",", $this->topdedimania[0]->checkpoints);
				}
				if (count($dediBestCps) != 0 && count($currentPlayerCps) != 0) {
					$message      = "\$fffPrevious CPS to DEDI 1: ";
					$dediBestTime = $dediBestCps[count($dediBestCps) - 1];

					for ($i = 0; $i < count($currentPlayerCps); $i++) {
						if (isset($currentPlayerCps[$i]) && isset($dediBestCps[$i])) {
							$playerGapTime = $currentPlayerCps[$i] - $dediBestCps[$i];

							$message .= "\$fff[" .($i + 1 ). "]" . $this->timeDifferenceWithColor($playerGapTime) . "\$fff, ";
						}
					}

					if (isset($dediBestTime) && isset($playerBestTime)) {
						$playerGapTime = $playerBestTime - $dediBestTime;
						$message       .= "\$fff[F]" . $this->timeDifferenceWithColor($playerGapTime) . "\$fff.";
					}
				}
			}
		}

		$this->maniaControl->getChat()->sendChat($message, $player);
	}


	/**
	 * Get player current cps compared to top 1 local
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function playerCurrentCpsComparedToLocal(array $chat, Player $player) {
		$message = "\$f00Check that LOCAL record plugin is enabled, that there are local records and that you yourself have a local record!";

		if($this->playersrecords && array_key_exists($player->login, $this->currentPlayerCps)) {
			if(isset($this->playersrecords[$player->login])) {
				$currentPlayerCps = $this->currentPlayerCps[$player->login];
				$playerBestTime = $currentPlayerCps[sizeof($currentPlayerCps) - 1];
				$currentPlayerCps = array_slice($currentPlayerCps, 0 , count($currentPlayerCps) - 1);

				if(isset($playerBestTime)) {
					$playerBestCps = $currentPlayerCps;
					$topLocalCps = array();
					$topLocalTime = "";

					foreach ($this->toprecord as $toprecord) {
						$topLocalCps = explode(",", $toprecord->checkpoints);
						$topLocalTime = explode(",", $toprecord->time);
					}

					if(count($topLocalCps) != 0 && count($playerBestCps) != 0) {
						$message = "\$fffPrevious CPS to LOCAL 1: ";
						for($i = 0; $i < count($playerBestCps); $i++) {
							if(isset($playerBestCps[$i]) && isset($topLocalCps[$i])) {
								$playerGapTime = ($playerBestCps[$i] - intval($topLocalCps[$i]));
								$message .= "\$fff[".($i + 1)."]".$this->timeDifferenceWithColor($playerGapTime)."\$fff, ";
							}
						}

						if(isset($topLocalTime[0]) && isset($playerBestTime)) {
							$playerGapTime = $playerBestTime - intval($topLocalTime[0]);
							$message .= "\$fff[F]".$this->timeDifferenceWithColor($playerGapTime)."\$fff.";
						}
					}
				}
			}
		}

		$this->maniaControl->getChat()->sendChat($message, $player);

	}

	/**
	 * Get player cps compared to top 1 local
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function playerCpsComparedToLocal(array $chat, Player $player) {
		$message = "\$f00Check that LOCAL record plugin is enabled, that there are local records and that you yourself have a local record!";

		if($this->playersrecords) {
			if(isset($this->playersrecords[$player->login])) {
				$map = $this->maniaControl->getMapManager()->getCurrentMap();
				$playerBestTime = $this->LocalRecordsPlugin->getLocalRecord($map, $player)->time;

				if(isset($playerBestTime)) {
					$playerBestCps = explode(",",$this->LocalRecordsPlugin->getLocalRecord($map, $player)->checkpoints);

					$topLocalCps = array();
					$topLocalTime = "";

					foreach ($this->toprecord as $toprecord) {
						$topLocalCps = explode(",", $toprecord->checkpoints);
						$topLocalTime = explode(",", $toprecord->time);
					}

					if(count($topLocalCps) != 0 && count($playerBestCps) != 0) {
						$message = "\$fffCPS to LOCAL 1: ";
						for($i = 0; $i < count($playerBestCps); $i++) {
							if(isset($playerBestCps[$i]) && isset($topLocalCps[$i])) {
								$playerGapTime = intval($playerBestCps[$i]) - intval($topLocalCps[$i]);
								$message .= "\$fff[".($i + 1)."]".$this->timeDifferenceWithColor($playerGapTime)."\$fff, ";
							}
						}

						if(isset($topLocalTime[0]) && isset($playerBestTime)) {
							$playerGapTime = $playerBestTime - intval($topLocalTime[0]);
							$message .= "\$fff[F]".$this->timeDifferenceWithColor($playerGapTime)."\$fff.";
						}
					}
				}
			}
		}
		$this->maniaControl->getChat()->sendChat($message, $player);
	}

	/**
	 * Displays the LocalRecords Checkpoints Live Widget
	 *
	 */
	public function displayLRCPLiveWidget(OnWayPointEventStructure $structure, $finish)
	{
		$pos1X = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LRCPLIVE_WIDGET1_POSX);
		$pos1Y = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LRCPLIVE_WIDGET1_POSY);
		$pos2X = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LRCPLIVE_WIDGET2_POSX);
		$pos2Y = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LRCPLIVE_WIDGET2_POSY);
		$pos3X = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LRCPLIVE_WIDGET3_POSX);
		$pos3Y = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LRCPLIVE_WIDGET3_POSY);
		$textsize = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LRCPLIVE_TEXT_SIZE);

		$labelStyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultLabelStyle();

		$player = $structure->getPlayer();
		if ($finish == 0) {

			$checkpoint = $structure->getCheckPointInLap();
			$playercptime = $structure->getLapTime();

			$playerbesttime = array();
			if($this->playersrecords)
			{
				if(isset($this->playersrecords[$player->login])) {
					$playerbesttime = explode(",", $this->playersrecords[$player->login]["checkpoints"]);
				}
			}
			$toptime = array();
			foreach ($this->toprecord as $toprecord) {
				$toptime = explode(",", $toprecord->checkpoints);
			}

			$deditime = array();
			if($this->dediactive) {
				if (isset($this->topdedimania[0]->checkpoints))
					$deditime = explode(",", $this->topdedimania[0]->checkpoints);
			}

			$playergaptime = 0;
			if (isset($playerbesttime)) {
				if (isset($playerbesttime[$checkpoint])) {
					$playergaptime = $playercptime - $playerbesttime[$checkpoint];
				}
			}
			$playertopgaptime = 0;
			if (isset($toptime)) {
				if (isset($toptime[$checkpoint])) {
					$playertopgaptime = $playercptime - $toptime[$checkpoint];
				}
			}
			$playerdeditopgaptime = 0.1;
			if (isset($deditime)) {
				if (isset($deditime[$checkpoint])) {
					$playerdeditopgaptime = $playercptime - $deditime[$checkpoint];
				}
			}

			$notimeplayer = false;
			if (empty($playerbesttime)) {
				$notimeplayer = true;
			}
			$notime = false;
			if (empty($toptime)) {
				$notime = true;
			}
			$nodeditime = false;
			if ($deditime == 0) {
				$nodeditime = true;
			}
		} else {


			$playercptime = $structure->getLapTime();

			$playerbesttime = 0;
			if($this->playersrecords) {
				if (isset($this->playersrecords[$player->login])) {
					$playerbesttime = $this->playersrecords[$player->login]["time"];
				}
			}

			$toptime = 0;
			foreach ($this->toprecord as $toprecord) {
				$toptime = $toprecord->time;
			}
			$deditime = 0;
			if($this->dediactive) {
				if (isset($this->topdedimania[0]->best))
					$deditime = $this->topdedimania[0]->best;
			}

			$playergaptime = $playercptime - $playerbesttime;
			$playertopgaptime = $playercptime - $toptime;
			$playerdeditopgaptime = $playercptime - $deditime;

			$notimeplayer = false;
			if ($playerbesttime == 0 ) {
				$notimeplayer = true;
			}
			$notime = false;
			if ($toptime == 0) {
				$notime = true;
			}
			$nodeditime = false;
			if ($deditime == 0) {
				$nodeditime = true;
			}
		}

		$color1 = "fff";
		if ($playertopgaptime < 0) {
			$color1 = "00f-";
		} elseif ($playertopgaptime > 0) {
			$color1 = "f00+";
		}

		$color2 = "fff";
		if ($playergaptime < 0) {
			$color2 = "00f-";
		} elseif ($playergaptime > 0) {
			$color2 = "f00+";
		}

		$color3 = "fff";
		if ($playerdeditopgaptime < 0) {
			$color3 = "00f-";
		} elseif ($playerdeditopgaptime > 0) {
			$color3 = "f00+";
		}

		$testdediok = $playerdeditopgaptime;

		$playertopgaptime = Formatter::formatTime(abs($playertopgaptime));
		$playerdeditopgaptime = Formatter::formatTime(abs($playerdeditopgaptime));
		$playergaptime = Formatter::formatTime(abs($playergaptime));

		$maniaLink = new ManiaLink(self::MLID_LRCPLIVE_WIDGET);

		// mainframe
		$frame = new Frame();
		$maniaLink->addChild($frame);
		if(!$notime) {

			$titleLabel = new Label();
			$frame->addChild($titleLabel);
			$titleLabel->setPosition($pos1X, $pos1Y);
			$titleLabel->setStyle($labelStyle);
			$titleLabel->setTextSize($textsize);
			$titleLabel->setText("Top1: $" . $color1 . "" . $playertopgaptime);
		}

		if(!$notimeplayer) {
			$titleLabel = new Label();
			$frame->addChild($titleLabel);
			$titleLabel->setPosition($pos2X, $pos2Y);
			$titleLabel->setStyle($labelStyle);
			$titleLabel->setTextSize($textsize);
			$titleLabel->setText("Me: $" . $color2 . "" . $playergaptime);
		}
		if(!$nodeditime && $this->dediactive && $testdediok != 0.1) {
			$titleLabel = new Label();
			$frame->addChild($titleLabel);
			$titleLabel->setPosition($pos3X, $pos3Y);
			$titleLabel->setStyle($labelStyle);
			$titleLabel->setTextSize($textsize);
			$titleLabel->setText("Top1Dedi: $" . $color3 . "" . $playerdeditopgaptime);
		}

		if($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LRCPLIVE_SHOWPLAYERS)) {
			// Send manialink
			$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $player->login);
		}

		if($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LRCPLIVE_SHOWSPECTATORS)) {
			//Send to spectators

			foreach ($this->spectateview as $spectatorlogin => $targetId) {

				//            Logger::log($spectatorlogin . " spec " .$targetId);
				if ($targetId == $player->pid) {
					$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $spectatorlogin);
				}
			}
		}
	}

	public function handleCheckpointCallback(OnWayPointEventStructure $structure){

		//        Logger::log("CP Start");
		if($this->active){
			//            Logger::log("CP Start");
			$this->displayWidgets($structure);
		}
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {

		$this->closeWidget(self::MLID_LRCPLIVE_WIDGET);

	}

	/**
	 * Close a Widget
	 *
	 * @param string $widgetId
	 */
	public function closeWidget($widgetId, $login = null) {
		$this->maniaControl->getManialinkManager()->hideManialink($widgetId,$login);
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {

	}
}