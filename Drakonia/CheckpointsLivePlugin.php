<?php

namespace Drakonia;

use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Bgs1;
use FML\Controls\Quads\Quad_Bgs1InRace;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\Common\BasePlayerTimeStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Utils\Formatter;


/**
 * Checkpoints Live Plugin
 *
 * @author    jonthekiller
 * @copyright 2020 Drakonia Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class CheckpointsLivePlugin implements ManialinkPageAnswerListener, CallbackListener, TimerListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID      = 111;
	const PLUGIN_VERSION = 2.11;
	const PLUGIN_NAME    = 'CheckpointsLivePlugin';
	const PLUGIN_AUTHOR  = 'jonthekiller';

	// CheckpointsLiveWidget Properties
	const MLID_CHECKPOINTS_LIVE_WIDGET        = 'CheckpointsLivePlugin.Widget';
	const MLID_CHECKPOINTS_LIVE_WIDGETTIMES   = 'CheckpointsLivePlugin.WidgetTimes';
	const SETTING_CHECKPOINTS_LIVE_ACTIVATED  = 'CheckpointsLive-Widget Activated';
	const SETTING_CHECKPOINTS_LIVE_POSX       = 'CheckpointsLive-Widget-Position: X';
	const SETTING_CHECKPOINTS_LIVE_POSY       = 'CheckpointsLive-Widget-Position: Y';
	const SETTING_CHECKPOINTS_LIVE_LINESCOUNT = 'Widget Displayed Lines Count';
	const SETTING_CHECKPOINTS_LIVE_WIDTH      = 'CheckpointsLive-Widget-Size: Width';
	//    const SETTING_CHECKPOINTS_LIVE_HEIGHT    = 'CheckpointsLive-Widget-Size: Height';
	const SETTING_CHECKPOINTS_LIVE_LINE_HEIGHT     = 'CheckpointsLive-Widget-Lines: Height';
	const SETTING_CHECKPOINTS_LIVE_CHANGE_POSITION = 'Change position shown';

const SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING   = 'Race Ranking Default Position';


	const SETTINGS_MATCHWIDGET_HIDE_SPEC         = 'Hide Spec Icon';
	const SETTINGS_MATCHWIDGET_HIDE_RACE_RANKING = 'Hide Race Ranking';

	const ACTION_SPEC = 'Spec.Action';


	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;
	// $ranking = array ($playerlogin, $nbCPs, $CPTime)
	private $ranking = array();
	// Gamemodes supported by the plugin
	private $gamemodes = array("Cup.Script.txt", "Rounds.Script.txt", "Team.Script.txt", "Laps.Script.txt", "Champion.Script.txt", "Trackmania/TM_Rounds_Online.Script.txt", "Trackmania/TM_Cup_Online.Script.txt", "Trackmania/TM_Teams_Online.Script.txt");
	private $script    = array();
	private $active    = false;
	private $nbCPs     = 0;
	private $countCPs  = 0;

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
		return 'Display a widget to show the Checkpoints Live information for Rounds/Team/Cup/Laps/Champion mode';
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERINFOCHANGED, $this, 'handlePlayerInfoChanged');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONWAYPOINT, $this, 'handleCheckpointCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONLAPFINISH, $this, 'handleCheckpointCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONFINISHLINE, $this, 'handleFinishCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_SCORES, $this, 'handleEndRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTROUNDSTART, $this, 'handleBeginRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_WARMUP_START, $this, 'handleBeginWarmUpCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONGIVEUP, $this, 'handlePlayerGiveUpCallback');


		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle10Seconds', 10000);
		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle1Second', 2000);

		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleSpec');

		//        $callback = $this->maniaControl->getModeScriptEventManager()->getListOfDisabledCallbacks();
		//        var_dump($callback);
		//$this->maniaControl->getModeScriptEventManager()->blockCallback("Trackmania.Event.WayPoint");
		//        $callback = $this->maniaControl->getModeScriptEventManager()->getListOfDisabledCallbacks();
		//        var_dump($callback);

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_ACTIVATED, true, "Activate the widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_POSX, -139, "Position of the widget (on X axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_POSY, 40, "Position of the widget (on Y axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_LINESCOUNT, 4, "Number of players to display");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_WIDTH, 42, "Width of the widget");
		//        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_HEIGHT, 40);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_LINE_HEIGHT, 4, "Height of a player line");

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING, false, "Move Nadeo Race Ranking widget (displayed at the end of the round)");
   
		if ($this->maniaControl->getServer()->titleId != "Trackmania") {
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_MATCHWIDGET_HIDE_SPEC, true, "Hide the Spectator icon for players");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_CHANGE_POSITION, false, "Display the icon when a player earn of lose a position (beta)");
			$this->hideSpecIcon();
		}
		$script       = $this->maniaControl->getClient()->getScriptName();
		$this->script = $script['CurrentValue'];
		if (in_array($this->script, $this->gamemodes)) {
			$this->active = true;
		} else {
			$this->active = false;
		}

		$this->displayWidgets();

//		$this->moveRaceRanking();




		return true;
	}

	/**
	 * Display the Widget
	 */
	private function displayWidgets() {


		// Display Checkpoints Live Widget

		if ($this->active) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_ACTIVATED)) {
				$this->displayCheckpointsLiveWidget();
			}
		}

	}

	/**
	 * Displays the Checkpoints Live Widget
	 *
	 * @param bool $login
	 */
	public function displayCheckpointsLiveWidget($login = false) {
		$posX       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_POSX);
		$posY       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_POSY);
		$width      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_WIDTH);
		$lines      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_LINESCOUNT);
		$lineHeight = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_LINE_HEIGHT);
		//        $height       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_HEIGHT);
		$height = 7. + $lines * $lineHeight;

		$labelStyle   = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultLabelStyle();
		$quadSubstyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();
		$quadStyle    = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();


		// mainframe
		$frame = new Frame();
		//		$maniaLink->addChild($frame);
		//		$frame->setSize($width, $height);
		$frame->setPosition($posX, $posY);

		// Background Quad
		$backgroundQuad = new Quad();
		$frame->addChild($backgroundQuad);
		$backgroundQuad->setVerticalAlign($backgroundQuad::TOP);
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_CHANGE_POSITION)) {
			$backgroundQuad->setSize(($width + 5), $height);
		} else {
			$backgroundQuad->setSize($width, $height);
		}
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);

		$titleLabel = new Label();
		$frame->addChild($titleLabel);
		$titleLabel->setPosition(0, $lineHeight * -0.9);
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_CHANGE_POSITION)) {
			$titleLabel->setWidth(($width + 2.5));
		} else {
			$titleLabel->setWidth($width);
		}
		$titleLabel->setStyle($labelStyle);
		$titleLabel->setTextSize(2);
//		$titleLabel->setText("CP Live");
  	$titleLabel->setText("Checkpoints");
    
		$titleLabel->setTranslate(true);

		$maniaLink = new ManiaLink(self::MLID_CHECKPOINTS_LIVE_WIDGET);
		$maniaLink->addChild($frame);
		// Send manialink
		$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
	}

	/**
	 * Handle ManiaControl After Init
	 */
	public function handle10Seconds() {
		$script       = $this->maniaControl->getClient()->getScriptName();
		$this->script = $script['CurrentValue'];
		if (in_array($this->script, $this->gamemodes)) {
			$this->active = true;
			$this->displayWidgets();
		} else {
			$this->active = false;
			$this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGETTIMES);
			$this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGET);
		}
	}

	/**
	 * Close a Widget
	 *
	 * @param string $widgetId
	 */
	public function closeWidget($widgetId) {
		$this->maniaControl->getManialinkManager()->hideManialink($widgetId);
	}

	/**
	 * Handle ManiaControl After Init
	 */
	public function handle1Second() {
		if ($this->active) {
			$this->updateWidget($this->ranking);
		}
	}

	public function updateWidget($ranking) {

		if ($ranking) {
			$this->displayTimes($ranking);
		} else {
			$this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGETTIMES);
		}

	}

	public function displayTimes($ranking) {

		$lines      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_LINESCOUNT);
		$lineHeight = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_LINE_HEIGHT);
		$posX       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_POSX);
		$posY       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_POSY);
		$width      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_WIDTH);


		$maniaLink = new ManiaLink(self::MLID_CHECKPOINTS_LIVE_WIDGETTIMES);
		$frame     = new Frame();
		$maniaLink->addChild($frame);
		$frame->setPosition($posX, $posY);


		// Obtain a list of columns
		$nbCPs         = array();
		$CPTime        = array();
		$previousPlace = array();
		foreach ($ranking as $key => $row) {
			$nbCPs[$key]         = $row['nbCPs'];
			$CPTime[$key]        = $row['CPTime'];
			$previousPlace[$key] = $row['previousPlace'];
		}

		// Sort the data with nbCPs descending, CPTime ascending
		array_multisort($nbCPs, SORT_DESC, $CPTime, SORT_ASC, $ranking);


		$rank       = 1;
		$bestNbCPs  = 0;
		$bestCPTime = 0;
		foreach ($ranking as $index => $record) {
			if ($index >= $lines) {
				break;
			}

			// If Laps, show number of CPs
			if ($this->script == "Laps.Script.txt" || $this->script == "Champion.Script.txt") {
				$time = Formatter::formatTime($record['CPTime']) . " / " . ($record['nbCPs'] + 1) . " CPs";
			} else {
				$time = Formatter::formatTime($record['CPTime']);
			}

			if ($rank == 1 && $record['CPTime'] != 999999999) {
				$bestNbCPs  = $record['nbCPs'];
				$bestCPTime = $record['CPTime'];
			} else {
				if ($record['CPTime'] == 999999999) {
					$time = "DNF";
				} elseif ($bestNbCPs != $record['nbCPs']) {
					$time = "+" . ($bestNbCPs - $record['nbCPs']) . " CPs";
				} else {
					$time = "+" . Formatter::formatTime($record['CPTime'] - $bestCPTime);
				}
			}
			$player = $this->maniaControl->getPlayerManager()->getPlayer($record['login']);


			$y = -10 - ($index) * $lineHeight;

			$recordFrame = new Frame();
			$frame->addChild($recordFrame);
			$recordFrame->setPosition(0, $y + $lineHeight / 2);

			//Rank
			$rankLabel = new Label();
			$recordFrame->addChild($rankLabel);
			$rankLabel->setHorizontalAlign($rankLabel::LEFT);
			$rankLabel->setX($width * -0.47);
			$rankLabel->setSize($width * 0.06, $lineHeight);
			$rankLabel->setTextSize(1);
			$rankLabel->setTextPrefix('$o');
			$rankLabel->setText($rank);
			$rankLabel->setTextEmboss(true);
      $rankLabel->setZ(1);

			//Name
			$nameLabel = new Label();
			$recordFrame->addChild($nameLabel);
			$nameLabel->setHorizontalAlign($nameLabel::LEFT);
			$nameLabel->setX($width * -0.4);
			$nameLabel->setSize($width * 0.6, $lineHeight);
			$nameLabel->setTextSize(1);
			$nameLabel->setText($player->nickname);
			$nameLabel->setTextEmboss(true);
      $nameLabel->setZ(1);

			//Time
			$timeLabel = new Label();
			$recordFrame->addChild($timeLabel);
			$timeLabel->setHorizontalAlign($timeLabel::RIGHT);
			$timeLabel->setX($width * 0.47);
			$timeLabel->setSize($width * 0.25, $lineHeight);
			$timeLabel->setTextSize(1);
			$timeLabel->setText($time);
      $timeLabel->setZ(1);

			$timeLabel->setTextEmboss(true);

			//Quad with Spec action
			$quad = new Quad();
			$recordFrame->addChild($quad);
			if ($this->script == "Trackmania/TM_Teams_Online.Script.txt" OR $this->script == "Team.Script.txt") {
				$quad->setStyles(Quad_Bgs1InRace::STYLE, Quad_Bgs1InRace::SUBSTYLE_BgCard);
				$quad->setOpacity(0.7);
				if ($player->teamId == 0) {
					$quad->setColorize('00f');
				} else {
					$quad->setColorize('f00');
				}

			}else{
				$quad->setStyles(Quad_Bgs1InRace::STYLE, Quad_Bgs1InRace::SUBSTYLE_BgCardList);
			}
			$quad->setSize($width-1, $lineHeight);
			$quad->setAction(self::ACTION_SPEC . '.' . $player->login);

			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_CHANGE_POSITION)) {
				//Quad for change place (green if better, red if worse)
				$quad = new Quad();
				$recordFrame->addChild($quad);
				if ($rank < $record['previousPlace']) {
					//                $quad->setStyles(Quad_Bgs1InRace::STYLE, Quad_Bgs1::SUBSTYLE_BgTitle3_4);
					$quad->setBackgroundColor('0f0');
				} elseif ($rank > $record['previousPlace']) {
					//                $quad->setStyles(Quad_Bgs1::STYLE, Quad_Bgs1::SUBSTYLE_BgTitle3_4);
					$quad->setBackgroundColor('f00');
				} else {
					//                $quad->setStyles(Quad_Bgs1::STYLE, Quad_Bgs1::SUBSTYLE_BgTitleShadow);
				}
				$quad->setSize(2, $lineHeight);
				$quad->setX($width * 0.525);
			}
			$rank++;

		}

		$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, false);
	}

	public function handleBeginRoundCallback() {

		$this->nbCPs = $this->maniaControl->getMapManager()->getCurrentMap()->nbCheckpoints;
		//        Logger::log($this->nbCPs);
		$this->ranking  = array();
		$this->countCPs = 0;
		//$this->updateWidget($this->ranking);
		if (!$this->active) {
			$this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGETTIMES);
			$this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGET);

		} else {
			$this->displayWidgets();
	//		$this->moveRaceRanking();

			$this->hideSpecIcon();

			//$this->updateWidget($this->ranking);
		}
	}

	public function handleBeginWarmUpCallback() {

		$this->nbCPs   = $this->maniaControl->getMapManager()->getCurrentMap()->nbCheckpoints;
		$this->ranking = array();
		if (!$this->active) {
			$this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGETTIMES);
			$this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGET);

		} else {
			$this->displayWidgets();
		}
	}

	public function handleEndRoundCallback() {
		$this->ranking = array();
//		Logger::log("CPs cross during the round: " . $this->countCPs);
		$this->countCPs = 0;
	}

	public function handlePlayerGiveUpCallback(BasePlayerTimeStructure $structure) {

		if ($this->active) {
			$this->PlayerGiveUpRanking($structure);
		}
	}

	public function PlayerGiveUpRanking(BasePlayerTimeStructure $structure) {
		// At least one player pass a Checkpoint
		if ($this->ranking) {

			//            var_dump($this->ranking);
			$previousRank = $this->ranking;
			// Obtain a list of columns
			$giveUp        = 0;
			$nbCPs         = array();
			$CPTime        = array();
			$previousPlace = array();
			foreach ($previousRank as $key => $row) {
				$nbCPs[$key]         = $row['nbCPs'];
				$CPTime[$key]        = $row['CPTime'];
				$previousPlace[$key] = $row['previousPlace'];
				$currentPlace[$key]  = $row['currentPlace'];

				//                Logger::log($row['nbCPs']);

			}


			// Sort the data with nbCPs descending, CPTime ascending
			array_multisort($nbCPs, SORT_DESC, $CPTime, SORT_ASC, $previousRank);

			$nbrank   = 1;
			$prevRank = array();
			foreach ($previousRank as $index => $record) {
				$prevRank[$record['login']] = $nbrank;
				$nbrank++;
			}
			$rank = $nbrank;

			$rankexist = $this->recursive_array_search($structure->getLogin(), $this->ranking);

			$nbrank   = 1;
			$prevRank = array();
			$currRank = array();
			$ranking  = array();
			// Reset the ladder
			//            unset($this->ranking);

			foreach ($previousRank as $index => $record) {
				$prevRank[$record['login']] = $nbrank;
				$currRank[$record['login']] = $record['currentPlace'];
				//                $prevCPTime[$record['login']] = $record['CPTime'];
				//                $prevnbCPs[$record['login']] = $record['nbCPs'];

				$ranking[] = array("login" => $record['login'], "nbCPs" => $record['nbCPs'], "CPTime" => $record['CPTime'], "previousPlace" => $nbrank, "currentPlace" => $record['currentPlace']);
				$nbrank++;
			}

			if ($rankexist) {
				// At least 2nd checkpoint for the player
				$currentRank = 255;
				// Remove old record
				foreach ($this->ranking as $key => $val) {


					if ($val['login'] == $structure->getLogin()) {
						//                        var_dump($key);
						if (isset($nbCPs[$key])) {
							if ($nbCPs[$key] != ($this->nbCPs - 1)) {
								unset($this->ranking[$key]);
								//                        $rank = $prevRank[$structure->getLogin()];
								$currentRank = $currRank[$structure->getLogin()];
							} else {
								$giveUp = 1;
							}
						} else {
							$giveUp = 1;
						}
					}
				}

				foreach ($ranking as $key => $val) {

					if ($val['login'] == $structure->getLogin()) {
						$rank = $val['previousPlace'];
					}
				}
				// Add new one
				if ($giveUp == 0) {
					$this->ranking[] = array("login" => $structure->getLogin(), "nbCPs" => 0, "CPTime" => 999999999, "previousPlace" => $rank, "currentPlace" => $currentRank);
				}
			} else {
				// First checkpoint for the player
				if ($giveUp == 0) {
					$this->ranking[] = array("login" => $structure->getLogin(), "nbCPs" => 0, "CPTime" => 999999999, "previousPlace" => $rank, "currentPlace" => $rank);
				}
			}

			$nbrank   = 1;
			$prevRank = array();

			$previousRank = $this->ranking;
			//            var_dump($this->ranking);
			// Obtain a list of columns
			$nbCPs         = array();
			$CPTime        = array();
			$previousPlace = array();
			foreach ($previousRank as $key => $row) {
				$nbCPs[$key]         = $row['nbCPs'];
				$CPTime[$key]        = $row['CPTime'];
				$previousPlace[$key] = $row['previousPlace'];
				$currentPlace[$key]  = $row['currentPlace'];
			}
			// Sort the data with nbCPs descending, CPTime ascending
			array_multisort($nbCPs, SORT_DESC, $CPTime, SORT_ASC, $previousRank);

			// Reset the ladder
			unset($this->ranking);

			foreach ($previousRank as $index => $record) {
				//                $prevRank[$record['login']] = $nbrank;
				//                $prevCPTime[$record['login']] = $record['CPTime'];
				//                $prevnbCPs[$record['login']] = $record['nbCPs'];

				$this->ranking[] = array("login"        => $record['login'], "nbCPs" => $record['nbCPs'], "CPTime" => $record['CPTime'], "previousPlace" => $record['previousPlace'],
				                         "currentPlace" => $nbrank);
				$nbrank++;
			}
		} else {
			//If first player arrives on first Checkpoint

			$this->ranking[] = array("login" => $structure->getLogin(), "nbCPs" => 0, "CPTime" => 999999999, "previousPlace" => 1, "currentPlace" => 1);
		}
	}

	function recursive_array_search($needle, $haystack, $currentKey = '') {
		foreach ($haystack as $key => $value) {
			if (is_array($value)) {
				$nextKey = $this->recursive_array_search($needle, $value, $currentKey . '[' . $key . ']');
				if ($nextKey) {
					return $nextKey;
				}
			} else if ($value == $needle) {
				return is_numeric($key) ? $currentKey . '[' . $key . ']' : $currentKey;
			}
		}
		return false;
	}

	public function handleFinishCallback(OnWayPointEventStructure $structure) {

		if ($this->active) {
			$this->updateRanking($structure);
		}

	}

	public function updateRanking(OnWayPointEventStructure $structure) {

		// At least one player pass a Checkpoint
		if ($this->ranking) {

			$previousRank = $this->ranking;
			// Obtain a list of columns
			$nbCPs         = array();
			$CPTime        = array();
			$previousPlace = array();
			$currentPlace  = array();
			foreach ($previousRank as $key => $row) {
				$nbCPs[$key]         = $row['nbCPs'];
				$CPTime[$key]        = $row['CPTime'];
				$previousPlace[$key] = $row['previousPlace'];
				$currentPlace[$key]  = $row['currentPlace'];
			}
			// Sort the data with nbCPs descending, CPTime ascending
			array_multisort($nbCPs, SORT_DESC, $CPTime, SORT_ASC, $previousRank);

			$nbrank   = 1;
			$prevRank = array();
			$currRank = array();

			$ranking = array();
			// Reset the ladder
			//            unset($this->ranking);

			foreach ($previousRank as $index => $record) {
				$prevRank[$record['login']] = $nbrank;
				$currRank[$record['login']] = $record['currentPlace'];
				//                $prevCPTime[$record['login']] = $record['CPTime'];
				//                $prevnbCPs[$record['login']] = $record['nbCPs'];

				$ranking[] = array("login" => $record['login'], "nbCPs" => $record['nbCPs'], "CPTime" => $record['CPTime'], "previousPlace" => $nbrank, "currentPlace" => $record['currentPlace']);
				$nbrank++;
			}
			$rankexist = $this->recursive_array_search($structure->getLogin(), $this->ranking);

			$rank        = $nbrank;
			$currentRank = $nbrank;
			//            Logger::log($structure->getLogin() . " " .$structure->getCheckPointInRace());
			if ($rankexist) {
				// At least 2nd checkpoint for the player


				// Remove old record
				foreach ($this->ranking as $key => $val) {

					if ($val['login'] == $structure->getLogin()) {
						unset($this->ranking[$key]);
						//                        $rank = $prevRank[$structure->getLogin()];
						$currentRank = $currRank[$structure->getLogin()];
					}
				}

				foreach ($ranking as $key => $val) {

					if ($val['login'] == $structure->getLogin()) {
						$rank = $val['previousPlace'];
					}
				}

				// Add new one
				$this->ranking[] = array("login"        => $structure->getLogin(), "nbCPs" => $structure->getCheckPointInRace(), "CPTime" => $structure->getRaceTime(), "previousPlace" => $rank,
				                         "currentPlace" => $currentRank);
			} else {
				// First checkpoint for the player
				$this->ranking[] = array("login"        => $structure->getLogin(), "nbCPs" => $structure->getCheckPointInRace(), "CPTime" => $structure->getRaceTime(), "previousPlace" => $rank,
				                         "currentPlace" => $rank);
			}

			$nbrank   = 1;
			$prevRank = array();

			$previousRank = $this->ranking;
			//            var_dump($this->ranking);
			// Obtain a list of columns
			$nbCPs         = array();
			$CPTime        = array();
			$previousPlace = array();
			foreach ($previousRank as $key => $row) {
				$nbCPs[$key]         = $row['nbCPs'];
				$CPTime[$key]        = $row['CPTime'];
				$previousPlace[$key] = $row['previousPlace'];
				$currentPlace[$key]  = $row['currentPlace'];
			}
			// Sort the data with nbCPs descending, CPTime ascending
			array_multisort($nbCPs, SORT_DESC, $CPTime, SORT_ASC, $previousRank);

			// Reset the ladder
			unset($this->ranking);

			foreach ($previousRank as $index => $record) {
				//                $prevRank[$record['login']] = $nbrank;
				//                $prevCPTime[$record['login']] = $record['CPTime'];
				//                $prevnbCPs[$record['login']] = $record['nbCPs'];

				$this->ranking[] = array("login"        => $record['login'], "nbCPs" => $record['nbCPs'], "CPTime" => $record['CPTime'], "previousPlace" => $record['previousPlace'],
				                         "currentPlace" => $nbrank);
				$nbrank++;
			}
		} else {
			//If first player arrives on first Checkpoint
			//            Logger::log($structure->getLogin() . " " .$structure->getCheckPointInRace());
			$this->ranking[] = array("login" => $structure->getLogin(), "nbCPs" => $structure->getCheckPointInRace(), "CPTime" => $structure->getRaceTime(), "previousPlace" => 1, "currentPlace" => 1);
		}

		//        var_dump($this->ranking);
	}

	public function handleCheckpointCallback(OnWayPointEventStructure $structure) {

		//        Logger::log("CP");
		$this->countCPs++;
		if ($this->active) {
			$this->updateRanking($structure);
		}
	}

	public function handleSpec(array $callback) {
		$actionId    = $callback[1][2];
		$actionArray = explode('.', $actionId, 3);
		if (count($actionArray) < 2) {
			return;
		}
		$action = $actionArray[0] . '.' . $actionArray[1];

		if (count($actionArray) > 2) {

			switch ($action) {
				case self::ACTION_SPEC:
					$adminLogin  = $callback[1][1];
					$targetLogin = $actionArray[2];
					foreach ($this->maniaControl->getPlayerManager()->getPlayers() as $players) {
						if ($targetLogin == $players->login && !$players->isSpectator && !$players->isTemporarySpectator && !$players->isFakePlayer() && $players->isConnected) {

							$player = $this->maniaControl->getPlayerManager()->getPlayer($adminLogin);
							if ($player->isSpectator) {
								$this->maniaControl->getClient()->forceSpectatorTarget($adminLogin, $targetLogin, -1);
							}
						}
					}
			}
		}
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {

		$this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGET);
		$this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGETTIMES);

	}

	/**
	 * Handle PlayerConnect callback
	 *
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {

		if ($this->active) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_ACTIVATED)) {
				$this->displayCheckpointsLiveWidget($player->login);
			}
		}
	}

	/**
	 * Handle PlayerConnect callback
	 *
	 * @param Player $player
	 */
	public function handlePlayerDisconnect(Player $player) {

		if ($this->active) {

			if ($this->ranking) {
				// At least one player pass a Checkpoint
				$rankexist = $this->recursive_array_search($player->login, $this->ranking);

				if ($rankexist) {
					// At least 2nd checkpoint for the player

					// Remove old record
					foreach ($this->ranking as $key => $val) {

						if ($val['login'] == $player->login) {
							unset($this->ranking[$key]);

						}
					}
					$this->updateWidget($this->ranking);
				}
			}
		}

	}

	public function handlePlayerInfoChanged(Player $player) {

		//        if($this->active){
		//
		//            if ($this->ranking) {
		//                // At least one player pass a Checkpoint
		//                $newSpecStatus = $player->isSpectator;
		//                if($newSpecStatus) {
		//                    $rankexist = $this->recursive_array_search($player->login, $this->ranking);
		//
		//                    if ($rankexist) {
		//                        // At least 2nd checkpoint for the player
		//
		//                        // Remove old record
		//                        foreach ($this->ranking as $key => $val) {
		//
		//                            if ($val['login'] == $player->login) {
		//                                    unset($this->ranking[$key]);
		//                            }
		//                        }
		//                        $this->updateWidget($this->ranking);
		//                    }
		//                }
		//            }
		//        }

	}

  public function moveRaceRanking() {


			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING)) {
				Logger::log('Put Race Ranking Widget to the original place');
				$data = '{ "uimodules": [ "Rounds_SmallScoresTable" ] }';
				$this->maniaControl->getModeScriptEventManager()->resetTrackmania2020UIProperties($data);
			}else{

				$data = '{ "uimodules": [ { "id": "Rounds_SmallScoresTable", "position": [112.0, 70.0], "position_update": true } ] }';
				$this->maniaControl->getModeScriptEventManager()->setTrackmania2020UIProperties($data);
				Logger::log('Put Race Ranking Widget to custom position');
			}
		
	}

	public function hideSpecIcon() {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_MATCHWIDGET_HIDE_SPEC)) {
			$properties = "<ui_properties><viewers_count visible='false'/></ui_properties>";

//			Logger::log('Hide Spec Icon');
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaUIProperties($properties);

		} else {
			$properties = "<ui_properties>
    <viewers_count visible='true' pos='157. -40. 5.\' />
  </ui_properties>";
//			Logger::log('Show Spec Icon');
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaUIProperties($properties);

		}
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->displayWidgets();
//			$this->moveRaceRanking();

			$this->hideSpecIcon();

		}
	}


}
