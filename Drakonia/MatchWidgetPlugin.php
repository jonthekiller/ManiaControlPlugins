<?php

namespace Drakonia;

use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Bgs1InRace;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\Common\BasePlayerTimeStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnScoresStructure;
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
 * Match Live Plugin
 *
 * @author    jonthekiller
 * @copyright 2020 Drakonia Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchWidgetPlugin implements ManialinkPageAnswerListener, CallbackListener, TimerListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID      = 127;
	const PLUGIN_VERSION = 2.0;
	const PLUGIN_NAME    = 'MatchWidgetPlugin';
	const PLUGIN_AUTHOR  = 'jonthekiller';

	// MatchWidgetPlugin Properties
	const MLID_MATCHWIDGET_LIVE_WIDGET        = 'MatchWidgetPlugin.Widget';
	const MLID_MATCHWIDGET_LIVE_WIDGETTIMES   = 'MatchWidgetPlugin.WidgetTimes';
	const SETTING_MATCHWIDGET_LIVE_ACTIVATED  = 'MatchWidgetPlugin-Widget Activated';
	const SETTING_MATCHWIDGET_LIVE_POSX       = 'MatchWidgetPlugin-Widget-Position: X';
	const SETTING_MATCHWIDGET_LIVE_POSY       = 'MatchWidgetPlugin-Widget-Position: Y';
	const SETTING_MATCHWIDGET_LIVE_LINESCOUNT = 'Widget Displayed Lines Count';
	const SETTING_MATCHWIDGET_LIVE_WIDTH      = 'MatchWidgetPlugin-Widget-Size: Width';
	//    const SETTING_MATCHWIDGET_LIVE_HEIGHT = 'MatchWidgetPlugin-Widget-Size: Height';
	const SETTING_MATCHWIDGET_LIVE_LINE_HEIGHT     = 'MatchWidgetPlugin-Widget-Lines: Height';
	const DEFAULT_MATCH_PLUGIN                     = 'Drakonia\MatchPlugin';
	const SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING   = 'Race Ranking Default Position';
	const SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING_X = 'Race Ranking Position X';
	const SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING_Y = 'Race Ranking Position Y';
	const SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING_Z = 'Race Ranking Position Z';
	const SETTING_MATCHWIDGET_SHOWPLAYERS          = 'Show for Players';
	const SETTING_MATCHWIDGET_SHOWSPECTATORS       = 'Show for Spectators';
	const SETTINGS_MATCHWIDGET_HIDE_SPEC           = 'Hide Spec Icon';
	const SETTINGS_MATCHWIDGET_MOVE_COUNTDOWN           = 'Move Countdown';
	const SETTINGS_MATCHWIDGET_COUNTDOWN_POS           = 'Countdown Position';
	const SETTINGS_MATCHWIDGET_COUNTDOWN_SIZE          = 'Countdown Size';

	const MATCH_ACTION_SPEC = 'Spec.Action';


	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;
	// $ranking = array ($playerlogin, $nbCPs, $CPTime)
	private $ranking = array();
	// Gamemodes supported by the plugin
	private $gamemodes   = array("Cup.Script.txt", "Rounds.Script.txt", "Team.Script.txt", "Trackmania/TM_Rounds_Online.Script.txt", "Trackmania/TM_Cup_Online.Script.txt");
	private $script      = array();
	private $active      = false;
	public  $matchPlugin = "";

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
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
		return 'Display a widget to show the Match Live information for Rounds/Team/Cup mode';
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');


		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_SCORES, $this, 'handleEndRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTROUNDSTART, $this, 'handleBeginRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_WARMUP_START, $this, 'handleBeginWarmUpCallback');


		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle10Seconds', 10000);


		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleSpec');


		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHWIDGET_LIVE_ACTIVATED, true, "Active the widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHWIDGET_SHOWPLAYERS, true, "Display widget for players");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHWIDGET_SHOWSPECTATORS, true, "Display widget for spectators");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHWIDGET_LIVE_POSX, -139, "Position of the widget (on X axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHWIDGET_LIVE_POSY, 40, "Position of the widget (on Y axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHWIDGET_LIVE_LINESCOUNT, 4, "Number of players to display");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHWIDGET_LIVE_WIDTH, 42, "Width of the widget");
		//        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHWIDGET_LIVE_HEIGHT, 25);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHWIDGET_LIVE_LINE_HEIGHT, 4, "Height of a player line");

		if ($this->maniaControl->getServer()->titleId != "Trackmania") {
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_MATCHWIDGET_HIDE_SPEC, true, "Hide Spectator icon for players");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING, false, "Move Nadeo Race Ranking widget (displayed at the end of the round)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING_X, 100, "Position of the Race Ranking widget (on X axis)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING_Y, 50, "Position of the Race Ranking widget (on Y axis)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING_Z, 150, "Position of the Race Ranking widget (on Z axis)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_MATCHWIDGET_COUNTDOWN_POS, '153 -7 5', "Position of the Countdown (X Y Z)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_MATCHWIDGET_COUNTDOWN_SIZE, 1, "Size of the Countdown");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_MATCHWIDGET_MOVE_COUNTDOWN, false, "Move Countdown");

			$this->moveRaceRanking();

			$this->hideSpecIcon();

			$this->moveCountdown();
		}

		$script       = $this->maniaControl->getClient()->getScriptName();
		$this->script = $script['CurrentValue'];
//		var_dump($this->script);
		if (in_array($this->script, $this->gamemodes)) {
			$this->active = true;
		} else {
			$this->active  = false;
			$this->ranking = array();
		}

		$this->matchPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_MATCH_PLUGIN);

		$this->displayWidgets();



		return true;
	}

	/**
	 * Display the Widget
	 */
	private function displayWidgets() {


		// Display Checkpoints Live Widget

		if ($this->active) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_ACTIVATED)) {
				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_SHOWPLAYERS)) {
					$players = $this->maniaControl->getPlayerManager()->getPlayers(true);
					$pl      = array();
					foreach ($players as $player) {
						$pl[] = $player->login;
					}
					$pl = array_merge([], $pl);
					$this->displayMatchLiveWidget($pl);
				}
				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_SHOWSPECTATORS)) {

					$players = $this->maniaControl->getPlayerManager()->getSpectators();
					$pl      = array();
					foreach ($players as $player) {
						$pl[] = $player->login;
					}
					$pl = array_merge([], $pl);
					$this->displayMatchLiveWidget($pl);
				}
			}
		}


	}

	/**
	 * Displays the Match Live Widget
	 *
	 * @param bool $login
	 */
	public function displayMatchLiveWidget($login = false) {
		$posX       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_POSX);
		$posY       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_POSY);
		$width      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_WIDTH);
		$lineHeight = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_LINE_HEIGHT);
		$lines      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_LINESCOUNT);
		//        $height = $this->maniaControl->getSettingManager()->getSettingValue(
		//            $this,
		//            self::SETTING_MATCHWIDGET_LIVE_HEIGHT
		//        );
		$height       = 7. + $lines * $lineHeight;
		$labelStyle   = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultLabelStyle();
		$quadSubstyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();
		$quadStyle    = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();


		// mainframe
		$frame = new Frame();
		$frame->setPosition($posX, $posY);
		//        $maniaLink->addChild($frame);
		//        $frame->setSize($width, $height);


		// Background Quad
		$backgroundQuad = new Quad();
		$frame->addChild($backgroundQuad);
		$backgroundQuad->setVerticalAlign($backgroundQuad::TOP);
		$backgroundQuad->setSize($width, $height);
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);

		$titleLabel = new Label();
		$frame->addChild($titleLabel);
		$titleLabel->setPosition(0, $lineHeight * -0.9);
		$titleLabel->setWidth($width);
		$titleLabel->setStyle($labelStyle);
		$titleLabel->setTextSize(2);
		$titleLabel->setText("Match Live");
		$titleLabel->setTranslate(true);

		$maniaLink = new ManiaLink(self::MLID_MATCHWIDGET_LIVE_WIDGET);
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
			//Reset ranking
			$this->ranking = array();
			$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGETTIMES);
			$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGET);
		}
	}

	/**
	 * Close a Widget
	 *
	 * @param string $widgetId
	 */
	public function closeWidget($widgetId, $login = null) {
		$this->maniaControl->getManialinkManager()->hideManialink($widgetId, $login);
	}


	public function updateWidget($ranking) {

		if ($ranking) {
			$this->displayTimes($ranking);
		} else {
			$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGETTIMES);
		}

	}

	public function displayTimes($ranking) {

		$lines      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_LINESCOUNT);
		$lineHeight = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_LINE_HEIGHT);
		$posX       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_POSX);
		$posY       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_POSY);
		$width      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_WIDTH);


		$maniaLink = new ManiaLink(self::MLID_MATCHWIDGET_LIVE_WIDGETTIMES);
		$listFrame = new Frame();
		$maniaLink->addChild($listFrame);
		$listFrame->setPosition($posX, $posY);

		// Obtain a list of columns
		$finalist = array();
		$points   = array();
		foreach ($ranking as $key => $row) {
			$finalist[$key] = $row['finalist'];
			$points[$key]   = $row['points'];
		}

		// Sort the data with nbCPs descending, CPTime ascending
		array_multisort($finalist, SORT_DESC, $points, SORT_DESC, $ranking);

		$rank = 1;

		foreach ($ranking as $index => $record) {
			if ($index >= $lines) {
				break;
			}

			if ($record['finalist'] == 2) $points = '$0f0Winner'; elseif ($record['finalist'] == 1) $points = '$f00Finalist';
			else
				$points = $record['points'];

			if ($points == '0') $points = '$z0';

			$player = $this->maniaControl->getPlayerManager()->getPlayer($record['login']);

			$y = -10 - ($index) * $lineHeight;

			$recordFrame = new Frame();
			$listFrame->addChild($recordFrame);
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

			//Name
			$nameLabel = new Label();
			$recordFrame->addChild($nameLabel);
			$nameLabel->setHorizontalAlign($nameLabel::LEFT);
			$nameLabel->setX($width * -0.4);
			$nameLabel->setSize($width * 0.6, $lineHeight);
			$nameLabel->setTextSize(1);
			$nameLabel->setText($player->nickname);
			$nameLabel->setTextEmboss(true);

			//Time
			$timeLabel = new Label();
			$recordFrame->addChild($timeLabel);
			$timeLabel->setHorizontalAlign($timeLabel::RIGHT);
			$timeLabel->setX($width * 0.47);
			$timeLabel->setSize($width * 0.25, $lineHeight);
			$timeLabel->setTextSize(1);
			$timeLabel->setText($points);
			$timeLabel->setTextEmboss(true);

			//Quad with Spec action
			$quad = new Quad();
			$recordFrame->addChild($quad);
			$quad->setStyles(Quad_Bgs1InRace::STYLE, Quad_Bgs1InRace::SUBSTYLE_BgCardList);
			$quad->setSize($width, $lineHeight);
			$quad->setAction(self::MATCH_ACTION_SPEC . '.' . $player->login);

			$rank++;

		}

		$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, false);
	}

	public function handleBeginRoundCallback() {


		//$this->updateWidget($this->ranking);
		if (!$this->active) {
			$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGETTIMES);
			$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGET);

		} else {
			$this->displayWidgets();
			//$this->updateWidget($this->ranking);
		}

		if ($this->maniaControl->getServer()->titleId != "Trackmania") {
			$this->moveRaceRanking();
			$this->hideSpecIcon();
		}
	}

	public function handleBeginWarmUpCallback() {


		if (!$this->active) {
			$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGETTIMES);
			$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGET);

		} else {
			$this->displayWidgets();
		}
	}

	public function handleEndRoundCallback(OnScoresStructure $structure) {


		if ($structure->getSection() == "PreEndRound" && in_array($this->script, $this->gamemodes)) {


			$scriptSettings = $this->maniaControl->getClient()->getModeScriptSettings();
			$this->ranking  = array();
			$results        = $structure->getPlayerScores();


			$pointsrepartition[0] = 10;

			if ($this->matchPlugin) {
				$pointsrepartition = $this->matchPlugin->getPointsRepartition();
				$pointsrepartition = explode(',', $pointsrepartition);
			}

			foreach ($results as $result) {

				$login  = $result->getPlayer()->login;
				$rank   = $result->getRank();
				$player = $result->getPlayer();
				//                    Logger::log($player->login . " PureSpec " . $player->isPureSpectator . "  Spec " . $player->isSpectator . "  Temp " . $player->isTemporarySpectator . "   Leave " . $player->isFakePlayer() . "  BestRaceTime " . $result->getBestRaceTime() . "  MatchPoints " . $result->getMatchPoints() . "  Roundpoints " . $result->getRoundPoints());
				if (($player->isSpectator && $result->getMatchPoints() == 0) || ($player->isFakePlayer() && $result->getMatchPoints() == 0)) {
				} else {

					if ($this->script == "Cup.Script.txt" OR $this->script == "Trackmania/TM_Cup_Online.Script.txt") {
						$roundpoints = $result->getRoundPoints();
						$points      = $result->getMatchPoints();

						//                            Logger::log($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS));
						//                            Logger::log($roundpoints);
						//                            Logger::log($points);
						if (($points > $scriptSettings['S_PointsLimit']) AND ($roundpoints == 0)) {
							//Logger::log("Winner");
							$this->ranking[] = array("login" => $login, "points" => ($points + $roundpoints), "finalist" => 2, "rank" => $rank);
						} elseif (($points == $scriptSettings['S_PointsLimit']) AND ($roundpoints == $pointsrepartition[0])) {
							//                                Logger::log("Winner");
							$this->ranking[] = array("login" => $login, "points" => ($points + $roundpoints), "finalist" => 2, "rank" => $rank);

						} elseif (($points + $roundpoints) >= $scriptSettings['S_PointsLimit']) {
							//                                Logger::log("Finalist");
							$this->ranking[] = array("login" => $login, "points" => ($points + $roundpoints), "finalist" => 1, "rank" => $rank);
						} else {
							$this->ranking[] = array("login" => $login, "points" => ($points + $roundpoints), "finalist" => 0, "rank" => $rank);
						}


					} elseif ($this->script == "Rounds.Script.txt" OR $this->script == "Trackmania/TM_Rounds_Online.Script.txt") {
						$roundpoints = $result->getRoundPoints();
						$points      = $result->getMatchPoints();
//						Logger::log($login);
//						                            Logger::log($roundpoints);
//						                            Logger::log($points);
						$this->ranking[] = array("login" => $login, "points" => ($points + $roundpoints), "finalist" => 0, "rank" => $rank);


					}elseif ($this->script == "Team.Script.txt" OR $this->script == "Trackmania/TM_Team_Online.Script.txt") {
						$roundpoints = $result->getRoundPoints();
						$points      = $result->getMatchPoints();

						$this->ranking[] = array("login" => $login, "points" => ($points + $roundpoints), "finalist" => 0, "rank" => $rank);


					}


				}


			}

		}

//		var_dump($this->ranking);
		$this->updateWidget($this->ranking);
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
				case self::MATCH_ACTION_SPEC:
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

		$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGET);
		$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGETTIMES);

	}

	/**
	 * Handle PlayerConnect callback
	 *
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {

		if ($this->active) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHWIDGET_LIVE_ACTIVATED)) {
				$this->displayMatchLiveWidget($player->login);
			}
		}
	}

	public function handlePlayerInfoChanged(Player $player) {
		$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGET, $player->login);
		$this->closeWidget(self::MLID_MATCHWIDGET_LIVE_WIDGETTIMES, $player->login);

	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->displayWidgets();
			if ($this->maniaControl->getServer()->titleId != "Trackmania") {
				$this->moveRaceRanking();
				$this->hideSpecIcon();
				$this->moveCountdown();
			}

		}
	}

	public function moveRaceRanking() {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING)) {
			$properties = "<ui_properties>
    <round_scores pos='-158.5 40. 150.' visible='true' />
  </ui_properties>";
//			Logger::log('Put Race Ranking Widget to the original place');
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaUIProperties($properties);

		} else {
			$properties = "<ui_properties>
    <round_scores pos='" . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING_X) . " " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING_Y) . ". " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_MATCHWIDGET_MOVE_RACE_RANKING_Z) . ".' visible='true' />
  </ui_properties>";
//			Logger::log('Put Race Ranking Widget to custom position');
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaUIProperties($properties);

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


	public function moveCountdown() {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_MATCHWIDGET_MOVE_COUNTDOWN)) {
			$properties = "<ui_properties><countdown visible='true' pos='".$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_MATCHWIDGET_COUNTDOWN_POS)."'/></ui_properties>";

//			Logger::log('Move Countdown');
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaUIProperties($properties);

		} else {
			$properties = "<ui_properties>
    <countdown visible='true' pos='153. -7. 5.\' />
  </ui_properties>";
//			Logger::log('Countdown initial place');
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaUIProperties($properties);

		}
	}

}
