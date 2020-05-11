<?php /** @noinspection SqlResolve */
/** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUnusedPrivateFieldInspection */


namespace Drakonia;


use Exception;
use FML\Controls\Frame;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\ManiaLink;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\Common\StatusCallbackStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnScoresStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Communication\CommunicationAnswer;
use ManiaControl\Communication\CommunicationListener;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\ManiaControl;
use \ManiaControl\Logger;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Utils\Formatter;
use Maniaplanet\DedicatedServer\InvalidArgumentException;

/**
 * Match Plugin
 *
 * @author    jonthekiller
 * @copyright 2019 Drakonia Team
 */
class MatchPlugin implements ManialinkPageAnswerListener, CallbackListener, CommandListener, TimerListener, CommunicationListener, Plugin {

	const PLUGIN_ID      = 119;
	const PLUGIN_VERSION = 0.89;
	const PLUGIN_NAME    = 'MatchPlugin';
	const PLUGIN_AUTHOR  = 'jonthekiller';

	//Properties
	const SETTING_MATCH_ACTIVATED                     = 'Match Plugin Activated:';
	const SETTING_MATCH_AUTHLEVEL                     = 'Auth level for the match* commands:';
	const SETTING_MATCH_MATCHSETTINGS_CONF            = 'Match settings from matchsettings only:';
	const SETTING_MATCH_MATCH_MODE                    = 'Gamemode used during match:';
	const SETTING_MATCH_NOMATCH_MODE                  = 'Gamemode used after match:';
	const SETTING_MATCH_MATCH_POINTS                  = 'S_PointsLimit';
	const SETTING_MATCH_MATCH_TIMELIMIT               = 'S_TimeLimit';
	const SETTING_MATCH_MATCH_ALLOWREPASWN            = 'S_AllowRespawn';
	const SETTING_MATCH_MATCH_HIDEOPPONENTS           = 'S_HideOpponents';
	const SETTING_MATCH_MATCH_DISABLEGIVEUP           = 'S_DisableGiveUp';
	const SETTING_MATCH_MATCH_DISPLAYTIMEDIFF         = 'S_DisplayTimeDiff';
	const SETTING_MATCH_MATCH_NBWARMUP                = 'S_WarmUpNb';
	const SETTING_MATCH_MATCH_DURATIONWARMUP          = 'S_WarmUpDuration';
	const SETTING_MATCH_MATCH_FINISHTIMEOUT           = 'S_FinishTimeout';
	const SETTING_MATCH_MATCH_POINTSREPARTITION       = 'PointsRepartition';
	const SETTING_MATCH_MATCH_CUSTOMPOINTSREPARTITION = 'S_UseCustomPointsRepartition';
	const SETTING_MATCH_MATCH_CUMULATEPOINTS          = 'S_CumulatePoints';
	const SETTING_MATCH_MATCH_POINTSGAP               = 'S_PointsGap';
	const SETTING_MATCH_MATCH_USETIEBREAK             = 'S_UseTieBreak';
	const SETTING_MATCH_MATCH_ROUNDSPERMAP            = 'S_RoundsPerMap';
	const SETTING_MATCH_MATCH_NBWINNERS               = 'S_NbOfWinners';
	const SETTING_MATCH_MATCH_NBMAPS                  = 'S_MapsPerMatch';
	const SETTING_MATCH_MATCH_NBLAPS                  = 'S_ForceLapsNb';
	const SETTING_MATCH_MATCH_SHUFFLEMAPS             = 'Shuffle Maplist';
	const SETTING_MATCH_READY_MODE                    = 'Ready Button';
	const MLID_MATCH_READY_WIDGET                     = 'Ready ButtonWidget';
	const SETTING_MATCH_READY_POSX                    = 'Ready Button-Position: X';
	const SETTING_MATCH_READY_POSY                    = 'Ready Button-Position: Y';
	const SETTING_MATCH_READY_WIDTH                   = 'Ready Button-Size: Width';
	const SETTING_MATCH_READY_HEIGHT                  = 'Ready Button-Size: Height';
	const SETTING_MATCH_MAPLIST                       = 'Maplist to use';
	const SETTING_MATCH_LOAD_MAPLIST_FILE             = 'Load maps from maplist file';
	const SETTING_MATCH_READY_NBPLAYERS               = 'Ready Button-Minimal number of players before start';
	const ACTION_READY                                = 'ReadyButton.Action';
	const TABLE_ROUNDS                                = 'drakonia_rounds';
	const TABLE_MATCHS                                = 'drakonia_matchs';
	//    const SETTING_MATCH_FORCESHOWOPPONENTS = 'Force Show Opponents';
	const SETTING_MATCH_PAUSE_DURATION  = 'Pause Duration in seconds';
//	const SETTING_MATCH_NADEO_PAUSE     = 'Nadeo Pause System';
	const SETTING_MATCH_PAUSE_MAXNUMBER = 'Pause Max per Player';
	const SETTING_MATCH_PAUSE_POSX      = 'Pause Widget-Position: X';
	const SETTING_MATCH_PAUSE_POSY      = 'Pause Widget-Position: Y';
	const MLID_MATCH_PAUSE_WIDGET       = 'Pause Widget';

	const DEFAULT_TRACKMANIALIVE_PLUGIN = 'Drakonia\TrackmaniaLivePlugin';
	const DEFAULT_DUOWIDGET_PLUGIN      = 'Drakonia\DuoWidgetPlugin';

	/*
	 * Private properties
	 */
	public $matchStarted = false;
	public $livePlugin   = false;
	/** @var ManiaControl $maniaControl */
	private $maniaControl  = null;
	private $scriptsDir    = null;
	private $times         = array();
	private $nbmaps        = 0;
	private $nbrounds      = 0;
	private $playerstate   = array();
	private $nbplayers     = 0;
	private $alreadydone   = false;
	private $alreadyDoneTA = false;

	// Settings to keep in memory
	private $settings_nbplayers     = 0;
	private $settings_nbroundsbymap = 5;
	private $settings_nbwinners     = 2;
	private $settings_nbmapsbymatch = 0;
	private $settings_pointlimit    = 100;
	private $matchrecover           = false;
	private $matchrecover2          = false;
	private $restorepoints          = false;
	private $fakeround              = false;
	private $scores                 = "";
	private $type                   = "";

	private $player1_playerlogin = "";
	private $player2_playerlogin = "";
	private $player3_playerlogin = "";
	private $player4_playerlogin = "";

	private $player1_points = 0;
	private $player2_points = 0;
	private $player3_points = 0;
	private $player4_points = 0;

	private $playercount     = 0;
	private $nbwinners       = 0;
	private $matchSettings   = array();
	private $playerswinner   = array();
	private $playersfinalist = array();
	private $scriptSettings  = array();

	private $currentscore       = array();
	private $playerpause        = array();
	private $pausetimer         = 0;
	private $pauseasked         = false;
	private $pauseon            = false;
	private $activeplayers      = array();
	private $pauseaskedbyplayer = "";
	private $numberpause        = 0;

	/**
	 * @see \ManiaControl\Plugins\Plugin::prepare()
	 * @param ManiaControl $maniaControl
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
		return 'Plugin offers a match Plugin';
	}


	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->initTables();

		//Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_ACTIVATED, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_MODE, array("Cup", "Rounds", "TimeAttack", "Laps", "Team"));
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_NOMATCH_MODE, array("TimeAttack"));
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_POINTS, 100);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_TIMELIMIT, 600);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN, false);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_HIDEOPPONENTS, false);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_DISABLEGIVEUP, false);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCHSETTINGS_CONF, false);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_NBWARMUP, 1);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP, -1);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT, 10);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_NBLAPS, -1);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION, "10,6,4,3,2,1");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP, 5);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_NBWINNERS, 1);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_NBMAPS, 3);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_SHUFFLEMAPS, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_AUTHLEVEL, AuthenticationManager::getPermissionLevelNameArray(AuthenticationManager::AUTH_LEVEL_ADMIN));

		//Custom Points for Team mode
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_CUSTOMPOINTSREPARTITION, true);

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_USETIEBREAK, false);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_CUMULATEPOINTS, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_POINTSGAP, 999999);

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_MODE, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_POSX, 152.5);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_POSY, 40);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_WIDTH, 15);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_HEIGHT, 6);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_NBPLAYERS, 2);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MAPLIST, "Drakonia_Cup.txt");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_LOAD_MAPLIST_FILE, true);
		//        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_FORCESHOWOPPONENTS, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_DURATION, 120);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_MAXNUMBER, 1);
//		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_NADEO_PAUSE, true);

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_POSX, 0);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_POSY, 0);

		$this->maniaControl->getModeScriptEventManager()->unblockCallback("Trackmania.Event.WayPoint");
		$this->maniaControl->getModeScriptEventManager()->unBlockAllCallbacks();
		$this->maniaControl->getModeScriptEventManager()->enableCallbacks();

		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACTION_READY, $this, 'handleReady');

		$this->maniaControl->getCommandManager()->registerCommandListener('ready', $this, 'onCommandSetReadyPlayer', false, 'Change status to Ready.');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

		//Register Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERINFOCHANGED, $this, 'handlePlayerInfoChanged');

		$this->maniaControl->getCommandManager()->registerCommandListener('matchstart', $this, 'onCommandMatchStart', true, 'Start a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchstop', $this, 'onCommandMatchStop', true, 'Stop a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchendround', $this, 'onCommandMatchEndRound', true, 'Force end a round during a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchendwu', $this, 'onCommandMatchEndWU', true, 'Force end a WU during a match');
		//        $this->maniaControl->getCommandManager()->registerCommandListener(
		//            'matchrecover',
		//            $this,
		//            'onCommandMatchRecover',
		//            true,
		//            'Recover match, args: {nb of current map} {nb of current round} [match_id] (Cup only)'
		//        );

		$this->maniaControl->getCommandManager()->registerCommandListener('matchsetpoints', $this, 'onCommandSetPoints', true, 'Sets points to a player.');

		$this->maniaControl->getCommandManager()->registerCommandListener('matchpause', $this, 'onCommandSetPause', true, 'Set pause during a match. [time] in seconds can be added to force another value');

		$this->maniaControl->getCommandManager()->registerCommandListener('matchendpause', $this, 'onCommandUnsetPause', true, 'End the pause during a match.');

		$this->maniaControl->getCommandManager()->registerCommandListener('pause', $this, 'onCommandSetPausePlayer', false, 'Set pause during a match.');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_SCORES, $this, 'handleEndRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTROUNDSTART, $this, 'handleBeginRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::BEGINMAP, $this, 'handleBeginMapCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::ENDMAP, $this, 'handleEndMapCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONFINISHLINE, $this, 'handleFinishCallback');

		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle5Seconds', 5000);


		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetMatchStatus", $this, function () {
			return new CommunicationAnswer($this->getMatchStatus());
		});

		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetCurrentScore", $this, function () {
			return new CommunicationAnswer($this->getCurrentScore());
		});

		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetPlayers", $this, function () {
			return new CommunicationAnswer($this->getPlayers());
		});

		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.MatchStart", $this, function () {
			return new CommunicationAnswer($this->MatchStart());
		});

		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.MatchStop", $this, function () {
			return new CommunicationAnswer($this->MatchStop());
		});

		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetMatchOptions", $this, function () {
			return new CommunicationAnswer($this->getMatchOptions());
		});


		// Trackmania Live Plugin

		$this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_TRACKMANIALIVE_PLUGIN);

		// Ready mode

		$players = $this->maniaControl->getPlayerManager()->getPlayers();

		foreach ($players as $player) {
			$this->handlePlayerConnect($player);
		}


		$scriptsDataDir = $maniaControl->getServer()->getDirectory()->getScriptsFolder();

		if ($maniaControl->getServer()->checkAccess($scriptsDataDir)) {
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
			if ($game != '') {
				$this->scriptsDir = $scriptsDataDir . 'Modes' . DIRECTORY_SEPARATOR . $game . DIRECTORY_SEPARATOR;
				// Final assertion
				if (!$maniaControl->getServer()->checkAccess($this->scriptsDir)) {
					$this->scriptsDir = null;
				}
			}
		}
	}


	/**
	 * Initialize needed database tables
	 */
	private function initTables() {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query  = "CREATE TABLE IF NOT EXISTS `" . self::TABLE_ROUNDS . "` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`server` VARCHAR(60) NOT NULL,
				`rounds` TEXT NOT NULL,
				PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}

		$query = "CREATE TABLE IF NOT EXISTS `" . self::TABLE_MATCHS . "` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
                `type` VARCHAR(10) NOT NULL DEFAULT 'Cup',
                `score` TEXT,
                `srvlogin` VARCHAR(40) DEFAULT NULL,
                `mapname` VARCHAR(255) DEFAULT NULL,
                `synchro` TINYINT(1) NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
	}

	public function getMatchStatus() {
		return $this->matchStarted;
	}

	public function getCurrentRound() {
		return $this->nbrounds . "/" . $this->settings_nbroundsbymap;
	}

	public function getCurrentMap() {
		return $this->nbmaps . "/" . $this->settings_nbmapsbymatch;
	}

	public function getCurrentScore() {
		return $this->currentscore;
	}

	public function getPlayers() {
		return $this->playerstate;
	}

	public function MatchStart() {
		$scriptName = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE);
		$scriptName .= '.Script.txt';

		$this->maniaControl->getChat()->sendChat("$0f0Match start with script " . $scriptName . '!');
		Logger::log("Match start with script " . $scriptName . '!');


		$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$player) {

			//                Logger::log("Check Script Name");

			$scriptName = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE);
			$scriptName .= '.Script.txt';


			//                Logger::log("1");
			$nbwinners = 0;


			try {


				//                    Logger::log("Begin try");
				$this->playerswinner   = array();
				$this->playersfinalist = array();
				$this->currentscore    = array();

				$this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_TRACKMANIALIVE_PLUGIN);
				if ($this->livePlugin) {
					//                Logger::log("Call MatchStart Live");
					$this->livePlugin->MatchStart();
				}

				//                    Logger::log("2");


				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup") {

					if ($this->maniaControl->getPlayerManager()->getPlayerCount() < ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWINNERS) + 1)) {
						$nbwinners = ($this->maniaControl->getPlayerManager()->getPlayerCount() - 1);
					} else {
						$nbwinners = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWINNERS);
					}

					$pointlimit = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS);

					if ($this->livePlugin) {
						//Get custom settings from Live
						$temppointlimit = $this->livePlugin->getPointLimit();
						$tempnbwinners  = $this->livePlugin->getNbWinners();
						if ($tempnbwinners > 0) {
							Logger::log("Override the default number of winners: " . $tempnbwinners);
							$nbwinners = $tempnbwinners;
						}

						if ($temppointlimit > 0) {
							Logger::log("Override the default number of points: " . $temppointlimit);
							$pointlimit = $temppointlimit;
						}
					}

					$this->settings_pointlimit = $pointlimit;
					$this->settings_nbwinners  = $nbwinners;
					//                    Logger::log("Cup mode");
					$loadedSettings = array(self::SETTING_MATCH_MATCH_POINTS => (int)$pointlimit, self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN), self::SETTING_MATCH_MATCH_HIDEOPPONENTS => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_HIDEOPPONENTS), self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWARMUP), self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP), self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF), self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT), self::SETTING_MATCH_MATCH_NBWINNERS => (int)$nbwinners, self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP),);
				} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Laps") {
					//                    Logger::log("Laps mode");
					$loadedSettings = array(self::SETTING_MATCH_MATCH_TIMELIMIT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_TIMELIMIT), self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN), self::SETTING_MATCH_MATCH_HIDEOPPONENTS => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_HIDEOPPONENTS), self::SETTING_MATCH_MATCH_DISABLEGIVEUP => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DISABLEGIVEUP), self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWARMUP), self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP), self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT), self::SETTING_MATCH_MATCH_NBLAPS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBLAPS),);


				} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds") {
					//                    Logger::log("Rounds mode");
					$loadedSettings = array(self::SETTING_MATCH_MATCH_POINTS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS), self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN), self::SETTING_MATCH_MATCH_HIDEOPPONENTS => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_HIDEOPPONENTS), self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWARMUP), self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP), self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF), self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT), self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP), self::SETTING_MATCH_MATCH_NBMAPS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS),);

					$this->settings_pointlimit = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS);

				} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "TimeAttack") {
					//                    Logger::log("TA mode");
					$loadedSettings = array(self::SETTING_MATCH_MATCH_TIMELIMIT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_TIMELIMIT), self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN), self::SETTING_MATCH_MATCH_HIDEOPPONENTS => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_HIDEOPPONENTS), self::SETTING_MATCH_MATCH_NBWARMUP => 0,);
				} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Team") {
					//                    Logger::log("Rounds mode");
					$loadedSettings = array(self::SETTING_MATCH_MATCH_CUSTOMPOINTSREPARTITION => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_CUSTOMPOINTSREPARTITION), self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN), self::SETTING_MATCH_MATCH_HIDEOPPONENTS => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_HIDEOPPONENTS), self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWARMUP), self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP), self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF), self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT), self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP), self::SETTING_MATCH_MATCH_NBMAPS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS), self::SETTING_MATCH_MATCH_USETIEBREAK => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_USETIEBREAK), self::SETTING_MATCH_MATCH_CUMULATEPOINTS => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_CUMULATEPOINTS), self::SETTING_MATCH_MATCH_POINTS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS), self::SETTING_MATCH_MATCH_POINTSGAP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSGAP),);

					$this->settings_pointlimit = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS);
				} else {
					//                    Logger::log("Other mode");
					$loadedSettings = array();
				}

				//                Logger::log("Set variables");
				$this->settings_nbplayers     = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_NBPLAYERS);
				$this->settings_nbroundsbymap = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP);
				$this->settings_nbmapsbymatch = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS);
				$this->settings_nbwinners     = (int)$nbwinners;


				// Script settings managed by Plugin else managed by matchsettings (name of the server.txt)
				if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCHSETTINGS_CONF)) {
					if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_LOAD_MAPLIST_FILE)) {

						$maplist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MAPLIST);
						$maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $maplist;

						//                    Logger::log("Load matchsettings");
						$this->maniaControl->getClient()->loadMatchSettings($maplist);
						//                    Logger::log("Restructure maplist");
						$this->maniaControl->getMapManager()->restructureMapList();
					}
					//                    Logger::log("Load Script");
					$this->loadScript($scriptName, $loadedSettings);


					//                Logger::log("Points repartition");
					if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_CUSTOMPOINTSREPARTITION)) {
						$pointsrepartition = explode(",", $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION));
						//                    Logger::log("Set Points Repartition");
						$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPointsRepartition($pointsrepartition);
					}

				} else {

					Logger::log("Conf from matchsettings file");
					$server  = $this->maniaControl->getServer()->login;
					$maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $server . ".txt";
					Logger::log($maplist);

					//                    Logger::log("Load Script");
					$this->loadScript($scriptName);

					if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_LOAD_MAPLIST_FILE)) {
						//                    Logger::log("Load matchsettings");
						$this->maniaControl->getClient()->loadMatchSettings($maplist);
						//                    Logger::log("Restructure maplist");
						$this->maniaControl->getMapManager()->restructureMapList();
					}
				}


				//                    Logger::log("3");
				//                    Logger::log("Shuffle maplist");
				$shufflemaplist = (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_SHUFFLEMAPS);

				if ($shufflemaplist) {
					$this->maniaControl->getMapManager()->shuffleMapList();
				}




				$this->matchStarted = true;
				$this->nbmaps       = -1;
				$this->nbrounds     = 0;
				$this->nbwinners    = 0;

				$this->matchSettings = $this->maniaControl->getClient()->getModeScriptSettings();

				//                    Logger::log("Check force show opponents");
				//                    $forceshowopponents = (boolean)$this->maniaControl->getSettingManager()->getSettingValue(
				//                        $this,
				//                        self::SETTING_MATCH_FORCESHOWOPPONENTS
				//                    );


				//                    if($forceshowopponents)
				//                    {
				//                        Logger::log("Force Show Opponents");
				//                        $this->maniaControl->getClient()->setForceShowAllOpponents(4);
				//                    }else{
				//                        Logger::log("Allow Hide Opponents");
				//                        $this->maniaControl->getClient()->setForceShowAllOpponents(0);
				//                    }

				//                    Logger::log("Get Players");
				$players = $this->maniaControl->getPlayerManager()->getPlayers();

				//                    Logger::log("Player State");
				foreach ($players as $player) {

					$this->handlePlayerConnect($player);
				}

				//                    Logger::log("4");


				//Reset Pause
				foreach ($players as $player) {
					if (!$player->isSpectator) {
						$this->playerpause[$player->login] = 0;
					}
				}
				$this->pauseasked         = false;
				$this->pauseaskedbyplayer = "";

				// Stock Script Settings inside variable
				$this->scriptSettings = $this->getScriptSettings();

				//var_dump($this->scriptSettings);

				$script = $this->maniaControl->getClient()->getScriptName();
				if ($script['CurrentValue'] == "<in-development>") {
					$script['CurrentValue'] = $script['NextValue'];
				}

				$matchdetail = "Gamemode: " . $script['CurrentValue'] . ", Rules:";
				Logger::log($matchdetail);
				$this->maniaControl->getChat()->sendInformation($matchdetail);

				$respawn = (!isset($this->scriptSettings['S_AllowRespawn']) || is_null($this->scriptSettings['S_AllowRespawn'])) ? '0' : $this->scriptSettings['S_AllowRespawn'];

				$hide = (!isset($this->scriptSettings['S_HideOpponents']) || is_null($this->scriptSettings['S_HideOpponents'])) ? '0' : $this->scriptSettings['S_HideOpponents'];

				$disablegiveup = (!isset($this->scriptSettings['S_DisableGiveUp']) || is_null($this->scriptSettings['S_DisableGiveUp'])) ? '0' : $this->scriptSettings['S_DisableGiveUp'];

				if ($script['CurrentValue'] == "Cup.Script.txt") {
					$matchdetail = "S_PointsLimit => " . $this->scriptSettings['S_PointsLimit'] . ", S_NbOfWinners => " . $this->scriptSettings['S_NbOfWinners'] . ", S_AllowRespawn => " . $respawn . ", S_HideOpponents => " . $hide . ",S_RoundsPerMap => " . $this->scriptSettings['S_RoundsPerMap'] . ", S_MapsPerMatch => " . (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS) . ",S_WarmUpNb => " . $this->scriptSettings['S_WarmUpNb'] . ", S_FinishTimeout => " . $this->scriptSettings['S_FinishTimeout'];
					$matchdetail = $matchdetail . ", PointsRepartition: " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
				} elseif ($script['CurrentValue'] == "Rounds.Script.txt") {
					$matchdetail = "S_PointsLimit => " . $this->scriptSettings['S_PointsLimit'] . ", S_AllowRespawn => " . $respawn . ", S_HideOpponents => " . $hide . ",S_RoundsPerMap => " . $this->scriptSettings['S_RoundsPerMap'] . ", S_MapsPerMatch => " . $this->scriptSettings['S_MapsPerMatch'] . ",S_WarmUpNb => " . $this->scriptSettings['S_WarmUpNb'] . ", S_FinishTimeout => " . $this->scriptSettings['S_FinishTimeout'];
					$matchdetail = $matchdetail . ", PointsRepartition: " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
				} elseif ($script['CurrentValue'] == "Team.Script.txt") {
					$matchdetail = "S_PointsLimit => " . $this->scriptSettings['S_PointsLimit'] . ", S_CumulatePoints => " . $this->scriptSettings['S_CumulatePoints'] . ", S_AllowRespawn => " . $respawn . ", S_HideOpponents => " . $hide . ",S_RoundsPerMap => " . $this->scriptSettings['S_RoundsPerMap'] . ", S_MapsPerMatch => " . $this->scriptSettings['S_MapsPerMatch'] . ",S_WarmUpNb => " . $this->scriptSettings['S_WarmUpNb'] . ", S_FinishTimeout => " . $this->scriptSettings['S_FinishTimeout'] . ", S_UseCustomPointsRepartition => " . $this->scriptSettings['S_UseCustomPointsRepartition'];
					$matchdetail = $matchdetail . ", PointsRepartition: " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
				} elseif ($script['CurrentValue'] == "TimeAttack.Script.txt") {
					$matchdetail = "S_TimeLimit => " . $this->scriptSettings['S_TimeLimit'] . ", S_MapsPerMatch => " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS) . ", S_AllowRespawn => " . $respawn . ", S_HideOpponents => " . $hide . ",S_WarmUpDuration => " . $this->scriptSettings['S_WarmUpDuration'];
				} elseif ($script['CurrentValue'] == "Laps.Script.txt") {
					$matchdetail = "S_TimeLimit => " . $this->scriptSettings['S_TimeLimit'] . ",S_ForceLapsNb => " . $this->scriptSettings['S_ForceLapsNb'] . ", S_MapsPerMatch => " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS) . ", S_AllowRespawn => " . $respawn . ", S_HideOpponents => " . $hide . ", S_DisableGiveUp => " . $disablegiveup . ",S_WarmUpNb => " . $this->scriptSettings['S_WarmUpNb'] . ",S_WarmUpDuration => " . $this->scriptSettings['S_WarmUpDuration'];
				}

				Logger::log($matchdetail);
				$this->maniaControl->getChat()->sendInformation($matchdetail);


				if ($this->livePlugin) {
					//                Logger::log("Call MatchStart Live");
					if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup" OR $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds") {
						$this->livePlugin->MatchUpdateMatchPoints();
					}
				}

			} catch (Exception $e) {
				$this->maniaControl->getChat()->sendError("Can not start match: " . $e->getMessage());
			}
		}, 2000);

		$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$player) {
			//                Logger::log("Skip map");
			$this->maniaControl->getMapManager()->getMapActions()->skipMap();
		}, 4000);
	}

	/**
	 * Loads a script if possible, otherwise throws an exception.
	 * $scriptName will be set to a proper capitalized etc. name.
	 *
	 * @param       $scriptName
	 * @param array $loadedSettings
	 * @throws Exception
	 */
	private function loadScript(&$scriptName, array $loadedSettings = null) {
		if ($this->scriptsDir !== null) {
			$scriptPath = $this->scriptsDir . $scriptName;

			if (!file_exists($scriptPath)) {
				throw new Exception('Script not found (' . $scriptPath . ').');
			}

			// Get 'real' script name (mainly nice for Windows)
			$scriptName = pathinfo(realpath($scriptPath))['basename'];
			//var_dump($loadedSettings);
			$script = file_get_contents($scriptPath);
			$this->maniaControl->getClient()->setModeScriptText($script);
			$this->maniaControl->getClient()->setScriptName($scriptName);
			if ($loadedSettings != null) {
				$this->maniaControl->getClient()->setModeScriptSettings($loadedSettings);
			}

		} else {
			throw new Exception('Scripts directory not found (' . $this->scriptsDir . ').');
		}
	}

	public function handlePlayerConnect(Player $player) {
		if ($player->isSpectator) {
			$this->playerstate[$player->login] = -1;
		} else {
			$this->playerstate[$player->login] = 0;
			$this->nbplayers++;
			$this->displayWidgets($player->login);
		}

		//        if ($this->pauseon) {
		//            $this->displayPauseWidget($player->login);
		//        }

		if ($this->matchStarted) {
			if (!isset($this->playerpause[$player->login])) {
				$this->playerpause[$player->login] = 0;
			}
		}
	}

	private function displayWidgets($login) {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_MODE) && (!$this->matchStarted)) {
			$this->displayReadyWidget($login);
		} else {
			$this->playerstate[$login] = 0;
			$this->closeReadyWidget($login);
		}
	}

	/**
	 * Displays the Ready Widget
	 *
	 * @param bool $login
	 */
	public function displayReadyWidget($login) {
		$posX         = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_POSX);
		$posY         = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_POSY);
		$width        = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_WIDTH);
		$height       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_HEIGHT);
		$quadStyle    = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();
		$quadSubstyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();

		$maniaLink = new ManiaLink(self::MLID_MATCH_READY_WIDGET);

		// mainframe
		$frame = new Frame();
		$maniaLink->addChild($frame);
		$frame->setSize($width, $height);
		$frame->setPosition($posX, $posY);

		// Background Quad
		$backgroundQuad = new Quad();
		$frame->addChild($backgroundQuad);
		$backgroundQuad->setSize($width, $height);
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);
		$backgroundQuad->setAction(self::ACTION_READY);

		$label = new Label_Text();
		$frame->addChild($label);
		$label->setPosition(0, 1.5, 0.2);
		$label->setVerticalAlign($label::TOP);
		$label->setTextSize(2);

		if ($this->playerstate[$login] == 1) {
			$label->setTextColor('0f0');
		} else {
			$label->setTextColor('f00');
		}
		$label->setText("Ready?");

		// Send manialink
		$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
	}

	/**
	 * Close Ready Widget
	 *
	 */
	public function closeReadyWidget($login = null) {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_MATCH_READY_WIDGET, $login);
	}

	public function getScriptSettings() {
		return $this->maniaControl->getClient()->getModeScriptSettings();
	}

	public function MatchStop() {
		$scriptName = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_NOMATCH_MODE);
		$scriptName .= '.Script.txt';

		$loadedSettings = array(self::SETTING_MATCH_MATCH_TIMELIMIT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_TIMELIMIT), self::SETTING_MATCH_MATCH_ALLOWREPASWN => true, self::SETTING_MATCH_MATCH_NBWARMUP => 0,);

		try {
			$this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_TRACKMANIALIVE_PLUGIN);
			if ($this->livePlugin) {
				$this->livePlugin->MatchStop();
			}
			$this->loadScript($scriptName, $loadedSettings);
			$this->maniaControl->getChat()->sendSuccess("Match stop");
			Logger::log("Match stop");
			$this->matchStarted  = false;
			$this->matchSettings = $loadedSettings;

			$players = $this->maniaControl->getPlayerManager()->getPlayers();

			foreach ($players as $player) {
				$this->handlePlayerConnect($player);
			}

		} catch (Exception $e) {
			$this->maniaControl->getChat()->sendError("Can not stop match: " . $e->getMessage());
		}
	}

	public function getMatchOptions() {
		$options = array("Nb Winners" => (!isset($this->scriptSettings['S_NbOfWinners']) || is_null($this->scriptSettings['S_NbOfWinners'])) ? '0' : $this->scriptSettings['S_NbOfWinners'], "Points limit" => (!isset($this->scriptSettings['S_PointsLimit']) || is_null($this->scriptSettings['S_PointsLimit'])) ? '0' : $this->scriptSettings['S_PointsLimit'], "Points Repartition" => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION), "Allow Respawn" => (!isset($this->scriptSettings['S_AllowRespawn']) || is_null($this->scriptSettings['S_AllowRespawn'])) ? '0' : $this->scriptSettings['S_AllowRespawn'], "Gamemode" => $this->maniaControl->getClient()->getModeScriptInfo()->name, "Rounds per map" => (!isset($this->scriptSettings['S_RoundsPerMap']) || is_null($this->scriptSettings['S_RoundsPerMap'])) ? '0' : $this->scriptSettings['S_RoundsPerMap'],

		);


		return $options;
	}

	public function setPointsRepartition() {
		$pointsrepartition = explode(",", $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION));
		Logger::log("Set Points Repartition");
		$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPointsRepartition($pointsrepartition);

		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->closeReadyWidget();
	}

	/**
	 * Handle ManiaControl After Init
	 */
	public function handle5Seconds() {

		//        $this->maniaControl->getModeScriptEventManager()->getCallbacksList()->setCallable(function (CallbackListStructure $structure){
		//            var_dump($structure->getCallbacks());
		//        });
		//
		//
		//        $this->maniaControl->getModeScriptEventManager()->getListOfEnabledCallbacks()->setCallable(function (CallbackListStructure $structure){
		//            var_dump($structure->getCallbacks());
		//        });
		//
		//        $this->maniaControl->getModeScriptEventManager()->getListOfDisabledCallbacks()->setCallable(function (CallbackListStructure $structure){
		//            var_dump($structure->getCallbacks());
		//        });


		//Logger::log($this->matchStarted ? 'true' : 'false');
		if ($this->matchStarted === false) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_MODE)) {
				//Logger::log("Ready Mode enabled");
				$nbplayers = $this->maniaControl->getPlayerManager()->getPlayerCount();
				//Logger::log($nbplayers);
				if ($nbplayers >= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_NBPLAYERS)) {
					$ready = 1;

					foreach ($this->playerstate as $playertate) {
						if ($playertate == 0) {
							//Logger::log('At least one player not ready');
							$ready = 0;
						}
					}
					if ($ready == 1) {
						$players = $this->maniaControl->getPlayerManager()->getPlayers();

						foreach ($players as $player) {
							$this->playerstate[$player->login] = 0;
						}
						//Logger::log('Start Match');
						$this->MatchStart();

					}
				}
			}
		}
	}

	public function getMatchSettings() {
		return $this->matchSettings;
	}

	public function getRoundNumber() {
		return $this->nbrounds;
	}

	public function getPlayersWinner() {
		return $this->playerswinner;
	}

	public function getPlayersFinalist() {
		return $this->playersfinalist;
	}

	public function getMatchPointsLimit() {
		return $this->settings_pointlimit;
	}

	public function getNbWinners() {
		return $this->settings_nbwinners;
	}

	public function onCommandMatchEndRound(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		$this->maniaControl->getModeScriptEventManager()->forceTrackmaniaRoundEnd();

	}

	public function onCommandMatchEndWU(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		try {
			$this->maniaControl->getModeScriptEventManager()->triggerModeScriptEvent("Trackmania.WarmUp.ForceStop");
		} catch (InvalidArgumentException $e) {
		}
	}

	public function onCommandSetPoints(array $chatCallback, Player $adminplayer) {

		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($adminplayer, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($adminplayer);

			return;
		}
		$text = $chatCallback[1][2];
		$text = explode(" ", $text);
		Logger::log($text[1] . " " . $text[2]);
		$player = $this->maniaControl->getPlayerManager()->getPlayer($text[1]);
		if ($player) {
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPlayerPoints($player, "", "", $text[2]);
			$this->maniaControl->getChat()->sendChat('$<$0f0$o Player $z' . $player->nickname . ' $z$o$0f0has now ' . $text[2] . ' points!$>');
			$this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_TRACKMANIALIVE_PLUGIN);
			if ($this->livePlugin) {
				$this->livePlugin->SetPoints($player, $text[2]);
			}
		} else {
			$this->maniaControl->getChat()->sendError("Login " . $text[1] . " doesn't exist");
		}


	}

	public function SetPoints(Player $player, $points) {
		Logger::log($player->login . " " . $points);
		if ($player) {
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPlayerPoints($player, "", "", $points);
			$this->maniaControl->getChat()->sendChat('$<$0f0$o Player $z' . $player->nickname . ' $z$o$0f0has now ' . $points . ' points!$>');
			$this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_TRACKMANIALIVE_PLUGIN);
			if ($this->livePlugin) {
				$this->livePlugin->SetPoints($player, $points);
			}
		} else {
			$this->maniaControl->getChat()->sendError("Login " . $player->login . " doesn't exist");
		}


	}

	public function onCommandSetPause(array $chatCallback, Player $player) {

		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		if (($this->matchStarted) && ($this->getMatchMode() != "TimeAttack")) {
			$text = $chatCallback[1][2];
			$text = explode(" ", $text);

			if (isset($text[1]) && $text[1] != "") {
				$this->maniaControl->getChat()->sendChat('$<$0f0$o Admin force a break for ' . $text[1] . ' seconds!$>');
//				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_NADEO_PAUSE)) {
					$this->setNadeoPause(true, $text[1]);
//				} else {
//					$this->setPause(true, $text[1]);
//				}
			} else {
				$this->maniaControl->getChat()->sendChat('$<$0f0$o Admin force a break for ' . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_DURATION) . ' seconds!$>');
//				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_NADEO_PAUSE)) {
					$this->setNadeoPause(true);
//				} else {
//					$this->setPause(true);
//				}
			}
		}
	}

	public function getMatchMode() {
		return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE);
	}

	private function setPause($admin = false, $time = null) {
		Logger::log("Pause");

		if ($time === null) {
			$this->pausetimer = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_DURATION);
		} else {
			$this->pausetimer = $time;
		}

		$players = $this->maniaControl->getPlayerManager()->getPlayers();

		foreach ($players as $player) {
			if ($player->isSpectator || $player->isPureSpectator) {
				unset($players[$player->login]);
			} else {
				//                    Logger::log($player->login);

				$pl = $this->maniaControl->getPlayerManager()->getPlayer($player);
				if ($pl->isConnected) {
					try {
						$this->activeplayers = array_merge($this->activeplayers, array($player->login));
						$this->activeplayers = array_unique($this->activeplayers);
						$this->maniaControl->getClient()->forceSpectator($player->login, 1);
					} catch (InvalidArgumentException $e) {
					}
				}
			}
		}

		$this->numberpause++;
		$numberpause   = $this->numberpause;
		$this->pauseon = true;


		$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$numberpause) {

			if ($numberpause == $this->numberpause) {
				$this->unsetPause();
			} else {
				Logger::log("Pause not stopped because another is in progress!");
			}
		}, $this->pausetimer * 1000);


		$this->displayPauseWidget($numberpause);

	}

	private function unsetPause() {
		if ($this->pauseon) {
			Logger::log("End Pause");
			//            $players = $this->maniaControl->getPlayerManager()->getPlayers();

			foreach ($this->activeplayers as $player) {
				//                    Logger::log($player);
				$pl = $this->maniaControl->getPlayerManager()->getPlayer($player);
				if ($pl->isConnected) {
					try {
						$this->maniaControl->getClient()->forceSpectator($player, 2);
					} catch (InvalidArgumentException $e) {
					}
				}
			}

			$this->pauseon = false;
		}

	}

	public function displayPauseWidget($numberpause) {
		$posX = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_POSX);
		$posY = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_POSY);

		$maniaLink = new ManiaLink(self::MLID_MATCH_PAUSE_WIDGET);

		// mainframe
		$frame = new Frame();
		$maniaLink->addChild($frame);
		$frame->setSize(30, 20);
		$frame->setPosition($posX, $posY);


		$label = new Label_Text();
		$frame->addChild($label);

		$label->setPosition(0, 2, 0.2);

		$label->setVerticalAlign($label::TOP);
		$label->setTextSize(12);

		if ($this->pausetimer < 10) {
			$label->setTextColor('f00');
		} else {
			$label->setTextColor('fff');
		}
		$label->setText($this->pausetimer);

		Logger::log("Pause: " . $this->pausetimer);
		// Send manialink
		$this->maniaControl->getManialinkManager()->sendManialink($maniaLink);

		$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$numberpause) {
			if ($numberpause == $this->numberpause) {
				if ($this->pausetimer > 0 && $this->pauseon) {
					$this->pausetimer--;
					$this->displayPauseWidget($numberpause);
				} else {
					$this->closePauseWidget();
				}
			}
		}, 1000);
	}

	/**
	 * Close Pause Widget
	 *
	 */
	public function closePauseWidget($login = null) {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_MATCH_PAUSE_WIDGET, $login);
	}

	private function setNadeoPause($admin = false, $time = null) {
		Logger::log("Nadeo Pause");

		if ($time === null) {
			$this->pausetimer = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_DURATION);
		} else {
			$this->pausetimer = $time;
		}

		$this->maniaControl->getModeScriptEventManager()->startPause();

		//        var_dump($pausestatus);
		$this->numberpause++;
		$numberpause   = $this->numberpause;
		$this->pauseon = true;


		$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$numberpause) {

			if ($numberpause == $this->numberpause) {
				$this->unsetNadeoPause();
			} else {
				Logger::log("Pause not stopped because another is in progress!");
			}
		}, $this->pausetimer * 1000);


		$this->displayPauseWidget($numberpause);
	}

	private function unsetNadeoPause() {
		if ($this->pauseon) {
			Logger::log("End Pause");

			$this->maniaControl->getModeScriptEventManager()->endPause();

			$this->pauseon = false;
		}

	}

	public function onCommandUnsetPause(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		if (($this->matchStarted) && ($this->getMatchMode() != "TimeAttack") && $this->pauseon) {
			$this->maniaControl->getChat()->sendChat('$<$f00$o Admin stop the break!$>');
//			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_NADEO_PAUSE) === false) {
//				$this->unsetPause();
//			} else {
				$this->unsetNadeoPause();
//			}
		} else {
			$this->maniaControl->getChat()->sendChat('$<$f00$o No pause in progress!$>', $player->login);
		}
	}

	public function onCommandSetPausePlayer(array $chatCallback, Player $player) {
		if (($this->matchStarted) && ($this->getMatchMode() != "TimeAttack") && (!$player->isSpectator && !$player->isPureSpectator)) {
			if ($this->playerpause[$player->login] < $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_MAXNUMBER)) {

				if ($this->pauseasked === false && $this->pauseon === false) {

					$this->maniaControl->getChat()->sendChat('$<$0f0$o Player $z' . $player->nickname . ' $z$o$0f0ask for a break of ' . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_DURATION) . ' seconds! It will start after this round.$>');
					$this->playerpause[$player->login] = $this->playerpause[$player->login] + 1;
					Logger::log($this->playerpause[$player->login] . " pause for " . $player->login);
					$this->pauseasked         = true;
					$this->pauseaskedbyplayer = $player->login;
					//                $this->setPause();
				} else {
					$this->maniaControl->getChat()->sendChat('$<$f00$o Pause already asked by a player!$>', $player->login);
				}
			} else {
				$this->maniaControl->getChat()->sendChat('$<$f00$o Player $z' . $player->nickname . ' $z$o$f00already ask too many breaks!$>', $player->login);

			}
		}
	}

	/**
	 * @param OnScoresStructure $structure
	 */
	public function handleEndRoundCallback(OnScoresStructure $structure) {
		//$this->maniaControl->getModeScriptEventManager()->getAllApiVersions()->setCallable(function (AllApiVersionsStructure $structure) {var_dump($structure->getVersions());} );
		//        Logger::log($structure->getSection());
		$realSection = false;
		//        if ($this->matchStarted === true) {
		if ($structure->getSection() == "PreEndRound" && ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds" OR $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup" OR $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Team")) {
			$realSection = true;
		} elseif ($structure->getSection() == "EndRound" && ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "TimeAttack" OR $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Laps")) {
			$realSection = true;
		} else {
			$realSection = false;
		}
		//        }

		if ($realSection) {


			if ($this->alreadydone === false AND $this->nbmaps != 0 AND $this->nbrounds != $this->settings_nbroundsbymap) {
				$database           = "";
				$this->currentscore = array();
				$this->nbwinners    = 0;
				$results            = $structure->getPlayerScores();
				$teamresults        = $structure->getTeamScores();
				$realRound          = false;
				$scores             = array();

				$pointsrepartition = $this->getPointsRepartition();
				$pointsrepartition = explode(',', $pointsrepartition);

				foreach ($results as $result) {
					$login           = $result->getPlayer()->login;
					$nickname        = $result->getPlayer()->getEscapedNickname();
					$nickname_simple = Formatter::stripCodes($nickname);
					$rank            = $result->getRank();
					$player          = $result->getPlayer();
					//                    Logger::log($player->login . " PureSpec " . $player->isPureSpectator . "  Spec " . $player->isSpectator . "  Temp " . $player->isTemporarySpectator . "   Leave " . $player->isFakePlayer() . "  BestRaceTime " . $result->getBestRaceTime() . "  MatchPoints " . $result->getMatchPoints() . "  Roundpoints " . $result->getRoundPoints());

					if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup" OR $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds" OR $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Team") {
						if (($player->isSpectator && $result->getMatchPoints() == 0) || ($player->isFakePlayer() && $result->getMatchPoints() == 0)) {
						} else {

							if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup") {
								$roundpoints = $result->getRoundPoints();
								$points      = $result->getMatchPoints();

								//                            Logger::log($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS));
								//                            Logger::log($roundpoints);
								//                            Logger::log($points);
								if (($points > $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS)) AND ($roundpoints == 0)) {
									//Logger::log("Winner");
									$points = ($points + ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWINNERS) - $this->nbwinners)) - $roundpoints;
									$this->nbwinners++;
								} elseif (($points == $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS)) AND ($roundpoints == $pointsrepartition[0])) {
									//                                Logger::log("Winner");
									$points = ($points + ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWINNERS) - $this->nbwinners)) - $roundpoints;
									$this->nbwinners++;


									$this->playerswinner   = array_merge($this->playerswinner, array("$login"));
									$this->playerswinner   = array_unique($this->playerswinner);
									$this->playersfinalist = array_diff($this->playersfinalist, ["$login"]);


								} elseif (($points + $roundpoints) >= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS) AND ($points != $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS))) {
									//                                Logger::log("Finalist");
									$points                = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS) - $roundpoints;
									$this->playersfinalist = array_merge($this->playersfinalist, array("$login"));
									$this->playersfinalist = array_unique($this->playersfinalist);
								}

								//                                Logger::log(
								//                                    $rank . ", " . $login . ", " . $nickname_simple . ", " . ($points + $roundpoints) . ", " . $roundpoints
								//                                );
								$database .= $rank . "," . $login . "," . ($points + $roundpoints) . "" . PHP_EOL;

								if (!$realRound && $roundpoints > 0) {
									$realRound = true;
								}


								$this->currentscore = array_merge($this->currentscore, array(array($rank, $result->getPlayer()->path, $result->getPlayer()->login, $result->getPlayer()->nickname, ($points + $roundpoints),),));

								$this->type = "Cup";

							} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds") {
								$roundpoints = $result->getRoundPoints();
								$points      = $result->getMatchPoints();


								//                                Logger::log(
								//                                    $rank . ", " . $login . ", " . $nickname_simple . ", " . ($points + $roundpoints) . ", " . $roundpoints
								//                                );
								$database .= $rank . "," . $login . "," . ($points + $roundpoints) . "" . PHP_EOL;

								$this->currentscore = array_merge($this->currentscore, array(array($rank, $result->getPlayer()->path, $result->getPlayer()->login, $result->getPlayer()->nickname, ($points + $roundpoints),),));

								$this->type = "Rounds";
							} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Team") {
								$roundpoints = $result->getRoundPoints();
								$points      = $result->getMatchPoints();

								$scores[$login] = array($roundpoints, ($points + $roundpoints));
								$logneutre[]    = $login . ", " . ($points + $roundpoints);

								$this->currentscore = array_merge($this->currentscore, array(array($rank, $result->getPlayer()->login, $result->getPlayer()->nickname, ($points + $roundpoints),),));

								$this->type = "Team";
							}

						}
					} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "TimeAttack") {

						if (($player->isSpectator && $result->getBestRaceTime() == 0) || ($player->isSpectator && $result->getBestRaceTime() == -1) || ($player->isFakePlayer() && $result->getBestRaceTime() == 0) || ($player->isFakePlayer() && $result->getBestRaceTime() == -1)) {
						} else {
							$time = $result->getBestRaceTime();
							Logger::log($rank . ", " . $login . ", " . $nickname_simple . ", " . $time);
							$database .= $rank . "," . $login . "," . $time . "" . PHP_EOL;

							$this->type = "TA";
						}


					} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Laps") {

						if (($player->isSpectator && $result->getBestRaceTime() == 0) || ($player->isSpectator && $result->getBestRaceTime() == -1) || ($player->isFakePlayer() && $result->getBestRaceTime() == 0) || ($player->isFakePlayer() && $result->getBestRaceTime() == -1)) {
						} else {
							$time = $result->getBestRaceTime();
							$cps  = $result->getBestRaceCheckpoints();
							$nbcp = count($cps);
							Logger::log($rank . ", " . $login . ", " . $nickname_simple . ", " . $nbcp . ", " . $time);
							$database .= $rank . "," . $login . "," . $time . "" . PHP_EOL;

							$this->type = "Laps";
						}


					}

				}

				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Team") {

					foreach ($logneutre as $log) {
						Logger::log($log);
					}

					foreach ($teamresults as $teamresult) {
						Logger::log("Team " . $teamresult->getName() . "," . $teamresult->getRoundPoints() . "," . $teamresult->getMapPoints() . "," . $teamresult->getMatchPoints());
					}

					$duoWidgetPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_DUOWIDGET_PLUGIN);

					if ($duoWidgetPlugin) $duoWidgetPlugin->handleEndRoundCallback($scores);
				}
				//Logger::log('Count number of players finish: '. count($this->times));

				if (($realRound && $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup") || $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds" || $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Team") {
					$this->nbrounds++;
				}


				$this->scores = $database;

				if ($this->type == "TA" AND !$this->alreadyDoneTA) {
					$mapname = $this->maniaControl->getMapManager()->getCurrentMap()->name;

					$mapname = Formatter::stripCodes($mapname);
					$mysqli  = $this->maniaControl->getDatabase()->getMysqli();
					$server  = $this->maniaControl->getServer()->login;
					$query   = "INSERT INTO `" . self::TABLE_MATCHS . "`
                            (`type`,`score`,`srvlogin`,`mapname`)
                            VALUES
                            ('$this->type','$this->scores','$server','$mapname')";
					Logger::log($query);
					$mysqli->query($query);
					if ($this->livePlugin) {
						$this->livePlugin->updateTATimes($this->scores, $this->maniaControl->getMapManager()->getCurrentMap(), $this->nbmaps);
					}

					if ($mysqli->error) {
						trigger_error($mysqli->error);

						return false;
					}

					$this->alreadyDoneTA = true;
				}
				// Store round result in database for logs management
				$mysqli = $this->maniaControl->getDatabase()->getMysqli();
				$server = $this->maniaControl->getServer()->login;


				// Re-order the results
				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup" OR $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds") {
					$temp_results = explode(PHP_EOL, $database);
					$temp_array   = array();

					foreach ($temp_results as $temp_result) {
						if ($temp_result != null AND $temp_result != "") {
							$temp                 = explode(",", $temp_result);
							$temp_array[$temp[1]] = $temp[2];
						}
					}
					arsort($temp_array);

					$rank     = 1;
					$database = "";
					foreach ($temp_array as $login => $points) {
						$database .= $rank . "," . $login . "," . $points . "" . PHP_EOL;
						$rank++;
					}
				}

				$query = "INSERT INTO `" . self::TABLE_ROUNDS . "`
				(`server`, `rounds`)
				VALUES
				('$server','$database')";
				//Logger::log($query);
				$mysqli->query($query);
				if ($mysqli->error) {
					trigger_error($mysqli->error);

					return false;
				}
				$this->alreadydone = true;

				$this->scores = $database;

				//Logger::log("Rounds finished: " . $this->nbrounds);
			}

			// In case of points reset after 1st map in Recovery mode
			if ($this->matchStarted === true && $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup" && $this->matchrecover === true) {
				if ($this->nbrounds == $this->settings_nbroundsbymap) {
					$results = $structure->getPlayerScores();
					$i       = 1;
					foreach ($results as $result) {
						if ($result->getPlayer()->isSpectator) {

						} else {
							$this->{"player" . $i . "_playerlogin"} = $result->getPlayer()->login;
							$this->{"player" . $i . "_points"}      = $result->getMatchPoints() + $result->getRoundPoints();
							$i++;
						}
					}

					$this->playercount   = ($i - 1);
					$this->restorepoints = true;
					$this->fakeround     = true;
					$this->maniaControl->getMapManager()->getMapActions()->skipMap();

				}
			}

		}

		return true;
	}

	public function getPointsRepartition() {
		return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
	}

	public function handleBeginRoundCallback() {
		$this->alreadyDoneTA = false;
		$this->alreadydone   = false;
		$this->times         = array();

		if ($this->pauseasked) {
			$this->pauseasked = false;
			Logger::log("Set Pause");

//			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_NADEO_PAUSE)) {
				$this->setNadeoPause();
//			} else {
//				$this->setPause();
//			}
		}

		if ($this->matchStarted === true && $this->fakeround === false) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds" OR $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup" OR $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Team") {
				$this->maniaControl->getModeScriptEventManager()->getPauseStatus()->setCallable(function (StatusCallbackStructure $structure) {
					if ($structure->getActive()) {
						$this->maniaControl->getChat()->sendChat('$w$i$F00Pause');
						Logger::log("Pause ");
					} else {
						$this->maniaControl->getChat()->sendChat('$<$ffd$o Round>' . ($this->nbrounds + 1) . ' / ' . $this->settings_nbroundsbymap . '  $w$i$F00GO GO GO  $F66!$F88!$F99!$FBB!$FDD!$FFF!$>');
						Logger::log("Round: " . ($this->nbrounds + 1) . ' / ' . $this->settings_nbroundsbymap);
					}
				});

			} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "TimeAttack") {
				if ($this->nbmaps > 0) {
					$this->maniaControl->getChat()->sendChat('$<$ffd$o Map>' . ($this->nbmaps) . '   $w$i$F00GO GO GO  $F66!$F88!$F99!$FBB!$FDD!$FFF!$>');
					Logger::log("Map: " . $this->nbmaps);
				}
			}
		}


		// Recovering mode (Nadeo's Cup script can reset points after 1st map)
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup" && $this->matchrecover === true && $this->restorepoints === true && $this->fakeround === false) {

			for ($i = 1; $i <= $this->playercount; $i++) {
				if ($this->{"player" . $i . "_playerlogin"} != "") {
					$player = $this->maniaControl->getPlayerManager()->getPlayer($this->{"player" . $i . "_playerlogin"});
					$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPlayerPoints($player, "", "", $this->{"player" . $i . "_points"});
					$this->maniaControl->getChat()->sendChat('$<$0f0$o Player $z' . $player->nickname . ' $z$o$0f0has now ' . $this->{"player" . $i . "_points"} . ' points!$>');
					Logger::log('Player ' . $player->login . ' has now ' . $this->{"player" . $i . "_points"} . ' points!');
				}
			}
			$this->restorepoints = false;
			$this->matchrecover  = false;
		}
	}

	public function handleEndMapCallback() {

		if ($this->matchStarted) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds" OR $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Team") {

				if ($this->settings_nbmapsbymatch == $this->nbmaps) {

					if ($this->nbrounds == $this->settings_nbroundsbymap) {

						$this->MatchEnd();
					}
				}
			} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "TimeAttack") {
				if ($this->nbmaps == $this->settings_nbmapsbymatch) {
					$this->MatchEnd();
				}
			} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Laps") {
				if ($this->nbmaps == $this->settings_nbmapsbymatch) {
					$this->MatchEnd();
				}

			} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup") {

				if ($this->settings_nbwinners == $this->nbwinners) {
					$this->MatchEnd();
				} elseif (count($this->playerswinner) == $this->settings_nbwinners AND count($this->playerswinner) > 0) {
					$this->MatchEnd();
				}

			}
			$this->fakeround = false;

			if (($this->pauseasked) && $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWARMUP) > 0) {
				$this->pauseasked = false;
				$this->playerpause[$this->pauseaskedbyplayer]--;
				$this->pauseaskedbyplayer = "";
				$this->maniaControl->getChat()->sendChat('$<$f00$o Pause cancelled because of a WarmUp!$>');
			}
		}
	}

	public function MatchEnd() {
		$scriptName = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_NOMATCH_MODE);
		$scriptName .= '.Script.txt';

		$loadedSettings = array(self::SETTING_MATCH_MATCH_TIMELIMIT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_TIMELIMIT), self::SETTING_MATCH_MATCH_ALLOWREPASWN => true, self::SETTING_MATCH_MATCH_NBWARMUP => 0,);

		try {
			$this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_TRACKMANIALIVE_PLUGIN);
			if ($this->livePlugin) {
				$this->livePlugin->MatchEnd();
			}


			if ($this->type == "Cup") {
				$mysqli = $this->maniaControl->getDatabase()->getMysqli();
				$server = $this->maniaControl->getServer()->login;
				$query  = "INSERT INTO `" . self::TABLE_MATCHS . "`
				(`type`,`score`,`srvlogin`)
				VALUES
				('$this->type','$this->scores','$server')";
				//Logger::log($query);
				$mysqli->query($query);
				if ($mysqli->error) {
					trigger_error($mysqli->error);

					return false;
				}
				Logger::log($this->scores);
			} elseif ($this->type == "TA") {
				if ($this->livePlugin) {
					$this->livePlugin->endTA();
				}
			}

			$this->loadScript($scriptName, $loadedSettings);
			$this->maniaControl->getChat()->sendSuccess("Match finished");
			Logger::log("Match finished");
			$this->matchStarted = false;

			$this->matchSettings = $loadedSettings;

			$players = $this->maniaControl->getPlayerManager()->getPlayers();

			foreach ($players as $player) {
				$this->handlePlayerConnect($player);
			}

			$this->currentscore = "";

		} catch (Exception $e) {
			$this->maniaControl->getChat()->sendError("Can not finish match: " . $e->getMessage());
		}
	}

	public function handleBeginMapCallback() {

		if ($this->matchStarted === true) {
			$this->nbmaps++;
			if (!$this->matchrecover2) {
				$this->nbrounds = 0;
			} else {
				$this->matchrecover2 = false;
			}
			if ($this->nbmaps > 0) {
				Logger::log("Map: " . $this->nbmaps);

				$maps = $this->maniaControl->getMapManager()->getMaps();
				$i    = 1;
				$this->maniaControl->getChat()->sendChat('$<$fff$o$i>>>Current Map$>');
				$currentmap = $this->maniaControl->getMapManager()->getCurrentMap();
				$this->maniaControl->getChat()->sendChat('$<$fff$o$i' . Formatter::stripCodes($currentmap->name) . '$>');
				Logger::log("Current Map: " . Formatter::stripCodes($currentmap->name));
				$continue = false;
				$current  = false;
				foreach ($maps as $map) {

					if ($currentmap->uid == $map->uid) {
						$continue = true;
						$current  = true;
					}

					if ($continue && $this->nbmaps < $this->settings_nbmapsbymatch) {
						if ($current) {
							$this->maniaControl->getChat()->sendChat('$<$fff$o$i>>>Next Maps$>');
							$current = false;
						} elseif (($this->nbmaps + $i) <= ($this->settings_nbmapsbymatch)) {
							if ($i > 0) {
								Logger::log("Map " . $i . ": " . Formatter::stripCodes($map->name));
								$this->maniaControl->getChat()->sendChat('$<$fff$o$i' . $i . ': ' . Formatter::stripCodes($map->name) . '$>');
							}
							$nbmaps = $this->maniaControl->getMapManager()->getMapsCount();
							$i++;
							if ($this->nbmaps == ($nbmaps - ($i - 1) + 1) OR $this->settings_nbmapsbymatch == (($i - 1) + $this->nbmaps - 1)) {
								$continue = false;
							}


						}
					}
				}
			}

		}
	}

	public function onCommandMatchStart(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		$this->MatchStart();
	}

	public function onCommandMatchRecover(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		$text     = $chatCallback[1][2];
		$text     = explode(" ", $text);
		$nbmaps   = $text[1];
		$nbrounds = $text[2];
		$id       = null;
		if ($text[3]) {
			$id = $text[3];
		}
		$this->MatchRecover($nbmaps, $nbrounds, $id);
	}

	/**
	 * @param      $nbmaps
	 * @param      $nbrounds
	 * @param null $id
	 */
	public function MatchRecover($nbmaps, $nbrounds, $id = null) {
		$scriptName = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE);
		$scriptName .= '.Script.txt';

		$this->nbwinners = 0;

		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup") {
			$loadedSettings = array(self::SETTING_MATCH_MATCH_POINTS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS), self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN), self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWARMUP), self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP), self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF), self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT), self::SETTING_MATCH_MATCH_NBWINNERS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWINNERS), self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP),);
		} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds") {
			$loadedSettings = array(self::SETTING_MATCH_MATCH_POINTS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS), self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN), self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWARMUP), self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP), self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF), self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT), self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP), self::SETTING_MATCH_MATCH_NBMAPS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS),);
		} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "TimeAttack") {
			$loadedSettings = array(self::SETTING_MATCH_MATCH_TIMELIMIT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_TIMELIMIT), self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN), self::SETTING_MATCH_MATCH_NBWARMUP => 0,);
		} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Laps") {
			//                    Logger::log("Laps mode");
			$loadedSettings = array(self::SETTING_MATCH_MATCH_TIMELIMIT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_TIMELIMIT), self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN), self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWARMUP), self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP), self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT), self::SETTING_MATCH_MATCH_NBLAPS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBLAPS),);


		} else {
			$loadedSettings = array();
		}

		$this->settings_nbplayers     = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_NBPLAYERS);
		$this->settings_nbroundsbymap = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP);
		$this->settings_nbmapsbymatch = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS);
		$this->settings_nbwinners     = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWINNERS);
		$this->settings_pointlimit    = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS);

		$pointsrepartition = explode(",", $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION));
		//var_dump($pointsrepartition);
		try {

			//                Logger::log("Call MatchStart Live");

			//            $this->maniaControl->getMapManager()->shuffleMapList();
			$this->loadScript($scriptName, $loadedSettings);
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPointsRepartition($pointsrepartition);
			$this->maniaControl->getChat()->sendSuccess("Match recover with script " . $scriptName . '!');
			Logger::log("Match recover with script " . $scriptName . '!');
			$this->matchStarted  = true;
			$this->nbmaps        = ($nbmaps - 1);
			$this->nbrounds      = ($nbrounds - 1);
			$this->matchrecover  = true;
			$this->matchrecover2 = true;
			$this->matchSettings = $loadedSettings;

			$players = $this->maniaControl->getPlayerManager()->getPlayers();

			foreach ($players as $player) {
				$this->handlePlayerConnect($player);
			}
			$this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_TRACKMANIALIVE_PLUGIN);
			if ($this->livePlugin) {
				if ($id == null) {
					$this->livePlugin->MatchStart();
				} else {
					$this->livePlugin->MatchRecover($id);
				}
			}


			// Stock Script Settings inside variable
			$this->scriptSettings = $this->getScriptSettings();

			//var_dump($this->scriptSettings);

			$script = $this->maniaControl->getClient()->getScriptName();
			if ($script['CurrentValue'] == "<in-development>") {
				$script['CurrentValue'] = $script['NextValue'];
			}

			$matchdetail = "Gamemode: " . $script['CurrentValue'] . ", Rules:";
			Logger::log($matchdetail);
			$this->maniaControl->getChat()->sendInformation($matchdetail);

			$respawn = (!isset($this->scriptSettings['S_AllowRespawn']) || is_null($this->scriptSettings['S_AllowRespawn'])) ? '0' : $this->scriptSettings['S_AllowRespawn'];

			if ($script['CurrentValue'] == "Cup.Script.txt") {
				$matchdetail = "S_PointsLimit => " . $this->scriptSettings['S_PointsLimit'] . ", S_NbOfWinners => " . $this->scriptSettings['S_NbOfWinners'] . ", S_AllowRespawn => " . $respawn . ",S_RoundsPerMap => " . $this->scriptSettings['S_RoundsPerMap'] . ", S_MapsPerMatch => " . (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS) . ",S_WarmUpNb => " . $this->scriptSettings['S_WarmUpNb'] . ", S_FinishTimeout => " . $this->scriptSettings['S_FinishTimeout'];
				$matchdetail = $matchdetail . ", PointsRepartition: " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
			} elseif ($script['CurrentValue'] == "Rounds.Script.txt") {
				$matchdetail = "S_PointsLimit => " . $this->scriptSettings['S_PointsLimit'] . ", S_NbOfWinners => " . $this->scriptSettings['S_NbOfWinners'] . ", S_AllowRespawn => " . $respawn . ",S_RoundsPerMap => " . $this->scriptSettings['S_RoundsPerMap'] . ", S_MapsPerMatch => " . $this->scriptSettings['S_MapsPerMatch'] . ",S_WarmUpNb => " . $this->scriptSettings['S_WarmUpNb'] . ", S_FinishTimeout => " . $this->scriptSettings['S_FinishTimeout'];
				$matchdetail = $matchdetail . ", PointsRepartition: " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
			} elseif ($script['CurrentValue'] == "TimeAttack.Script.txt") {
				$matchdetail = "S_TimeLimit => " . $this->scriptSettings['S_TimeLimit'] . ", S_MapsPerMatch => " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS) . ", S_AllowRespawn => " . $respawn . ",S_WarmUpDuration => " . $this->scriptSettings['S_WarmUpDuration'];
			} elseif ($script['CurrentValue'] == "Laps.Script.txt") {
				$matchdetail = "S_TimeLimit => " . $this->scriptSettings['S_TimeLimit'] . ",S_ForceLapsNb => " . $this->scriptSettings['S_ForceLapsNb'] . ", S_MapsPerMatch => " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS) . ", S_AllowRespawn => " . $respawn . ",S_WarmUpNb => " . $this->scriptSettings['S_WarmUpNb'] . ",S_WarmUpDuration => " . $this->scriptSettings['S_WarmUpDuration'];
			}

			Logger::log($matchdetail);
			$this->maniaControl->getChat()->sendInformation($matchdetail);

		} catch (Exception $e) {
			$this->maniaControl->getChat()->sendError("Can not recover match: " . $e->getMessage());
		}
	}

	/**
	 * @param array                        $chatCallback
	 * @param \ManiaControl\Players\Player $player
	 */
	public function onCommandMatchStop(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		$this->MatchStop();
	}

	/**
	 * Handle Finish Callback
	 *
	 * @param \ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure $structure
	 */
	public function handleFinishCallback(OnWayPointEventStructure $structure) {


		if ($this->matchStarted) {
			//            Logger::log("Enter Finish");
			if ($structure->getRaceTime() <= 0) {
				// Invalid time
				return;
			}

			//$map = $this->maniaControl->getMapManager()->getCurrentMap();

			$player = $structure->getPlayer();

			if (!$player) {
				return;
			}

			$this->times[] = array($player->login, $structure->getRaceTime());

			//var_dump($this->times);

		}

	}

	public function handlePlayerDisconnect(Player $player) {
		$this->playerstate[$player->login] = -1;
		$this->nbplayers--;
		$this->closeReadyWidget($player->login);
		$this->closePauseWidget($player->login);
	}

	public function handlePlayerInfoChanged(Player $player) {

		$this->handlePlayerConnect($player);

		if ($this->playerstate[$player->login]) {
			$newSpecStatus = $player->isSpectator;

			if ($newSpecStatus) {
				if ($this->playerstate[$player->login] == 0 OR $this->playerstate[$player->login] == 1) {
					$this->nbplayers--;
				}

				$this->playerstate[$player->login] = -1;
				$this->closeReadyWidget($player->login);
			} else {
				if ($this->playerstate[$player->login] == -1) {
					$this->nbplayers++;
				}

				$this->playerstate[$player->login] = 0;

				$this->displayWidgets($player->login);
			}
		}

	}

	public function onCommandSetReadyPlayer(array $chatCallback, Player $player) {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_MODE) && (!$this->matchStarted)) {
			$this->handleReady($chatCallback, $player);
		}
	}

	public function handleReady(array $callback, Player $player) {
		if ($this->playerstate[$player->login] == 0) {
			$this->playerstate[$player->login] = 1;
			$this->nbplayers++;
			$this->displayWidgets($player->login);
			$this->maniaControl->getChat()->sendChat("Match: " . $player->nickname . ' $<$z$0f0Ready$>');
		} elseif ($this->playerstate[$player->login] == 1) {
			$this->playerstate[$player->login] = 0;
			$this->nbplayers--;
			$this->displayWidgets($player->login);
			$this->maniaControl->getChat()->sendChat("Match: " . $player->nickname . ' $<$z$f00Not Ready$>');
		}
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$players = $this->maniaControl->getPlayerManager()->getPlayers();

			foreach ($players as $player) {
				$this->displayWidgets($player->login);
			}


			// Update settings

			$this->settings_pointlimit    = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS);
			$this->settings_nbroundsbymap = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP);
			$this->settings_nbmapsbymatch = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS);
			$this->settings_nbwinners     = (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWINNERS);


			if ($this->matchStarted) {

				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup") {
					$loadedSettings = array(self::SETTING_MATCH_MATCH_POINTS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS), self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP), self::SETTING_MATCH_MATCH_NBWINNERS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWINNERS));
					try {
						$this->maniaControl->getClient()->setModeScriptSettings($loadedSettings);
						Logger::log("Parameters updated");
						$this->maniaControl->getChat()->sendSuccessToAdmins("Parameters updated");
					} catch (InvalidArgumentException $e) {
						Logger::log("Parameters not updated");
						$this->maniaControl->getChat()->sendErrorToAdmins("Parameters not updated");
					}
				} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Rounds") {
					$loadedSettings = array(self::SETTING_MATCH_MATCH_POINTS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS), self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP), self::SETTING_MATCH_MATCH_NBMAPS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS),);
					try {
						$this->maniaControl->getClient()->setModeScriptSettings($loadedSettings);
						Logger::log("Parameters updated");
						$this->maniaControl->getChat()->sendSuccessToAdmins("Parameters updated");
					} catch (InvalidArgumentException $e) {
						Logger::log("Parameters not updated");
						$this->maniaControl->getChat()->sendErrorToAdmins("Parameters not updated");
					}

				} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "TimeAttack") {
					$loadedSettings = array(self::SETTING_MATCH_MATCH_TIMELIMIT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_TIMELIMIT), self::SETTING_MATCH_MATCH_NBMAPS => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS));
					try {
						$this->maniaControl->getClient()->setModeScriptSettings($loadedSettings);
						Logger::log("Parameters updated");
						$this->maniaControl->getChat()->sendSuccessToAdmins("Parameters updated");
					} catch (InvalidArgumentException $e) {
						Logger::log("Parameters not updated");
						$this->maniaControl->getChat()->sendErrorToAdmins("Parameters not updated");
					}
				}
			}

		}
	}


}