<?php


namespace Drakonia;


use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Bgs1InRace;
use FML\ManiaLink;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\TrackMania\OnScoresStructure;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Communication\CommunicationListener;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Utils\Formatter;
use Maniaplanet\DedicatedServer\InvalidArgumentException;
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

class KnockOutPlugin implements ManialinkPageAnswerListener, CallbackListener, CommandListener, TimerListener, CommunicationListener, Plugin {

	const PLUGIN_ID      = 125;
	const PLUGIN_VERSION = 2.0;
	const PLUGIN_NAME    = 'KnockOutPlugin';
	const PLUGIN_AUTHOR  = 'jonthekiller';


	const SETTING_KNOCKOUT_ACTIVATED          = 'KnockOut Plugin Activated:';
	const SETTING_KNOCKOUT_WIDGET_ACTIVATED   = 'KnockOut Plugin Widget Activated:';
	const SETTING_KNOCKOUT_AUTHLEVEL          = 'Auth level for the ko* commands:';
	const SETTING_KNOCKOUT_ROUNDSPERMAP       = 'S_RoundsPerMap';
	const SETTING_KNOCKOUT_DURATIONWARMUP     = 'S_WarmUpDuration';
	const SETTING_KNOCKOUT_FINISHTIMEOUT      = 'S_FinishTimeout';
	const SETTING_KNOCKOUT_MAPLIST            = 'Maplist to use';
	const SETTING_KNOCKOUT_SHUFFLEMAPS        = 'Shuffle Maplist';
	const SETTING_KNOCKOUT_ALLOWREPASWN       = 'S_AllowRespawn';
	const SETTING_KNOCKOUT_POINTSREPARTITION  = 'S_PointsRepartition';
	const SETTING_KNOCKOUT_PLAYER_PASSWORD    = 'Server player password during KO:';
	const SETTING_KNOCKOUT_SPECTATOR_PASSWORD = 'Server spectator password during KO:';
	const SETTING_KNOCKOUT_NBLIFES            = 'Number of lives:';
	const SETTING_KNOCKOUT_SPEC_OR_KICK       = 'Force Spec or Kick after KO:';
	const SETTING_KNOCKOUT_SOLO_OR_TEAM       = 'Solo or Team mode:';


	// const for TMNext
	const SETTING_KNOCKOUT_RESPAWNBEHAVIOUR  = 'S_RespawnBehaviour';
	const SETTING_KNOCKOUT_DECO_CHECKPOINT   = 'S_DecoImageUrl_Checkpoint';
	const SETTING_KNOCKOUT_DECO_SPONSOR      = 'S_DecoImageUrl_DecalSponsor4x1';
	const SETTING_KNOCKOUT_DECO_SCREEN16X9   = 'S_DecoImageUrl_Screen16x9';
	const SETTING_KNOCKOUT_DECO_SCREEN8X1    = 'S_DecoImageUrl_Screen8x1';
	const SETTING_KNOCKOUT_DECO_SCREEN16X1   = 'S_DecoImageUrl_Screen16x1';
	const SETTING_KNOCKOUT_USEDELAYEDVISUALS = 'S_UseDelayedVisuals';
	const SETTING_KNOCKOUT_TRUSTCLIENTSIMU   = 'S_TrustClientSimu';


	const MLID_KNOCKOUT_WIDGET         = 'KnockoutPlugin.Widget';
	const MLID_KNOCKOUT_WIDGETTIMES    = 'KnockoutPlugin.WidgetTimes';
	const SETTING_KNOCKOUT_POSX        = 'KnockoutPlugin-Widget-Position: X';
	const SETTING_KNOCKOUT_POSY        = 'KnockoutPlugin-Widget-Position: Y';
	const SETTING_KNOCKOUT_LINESCOUNT  = 'Widget Displayed Lines Count';
	const SETTING_KNOCKOUT_WIDTH       = 'KnockoutPlugin-Widget-Size: Width';
	const SETTING_KNOCKOUT_LINE_HEIGHT = 'KnockoutPlugin-Widget-Lines: Height';
	const KNOCKOUT_ACTION_SPEC         = 'Spec.Action';

	const SETTINGS_KNOCKOUT_MOVE_RACE_RANKING   = 'Race Ranking Default Position';
	const SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_X = 'Race Ranking Position X';
	const SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Y = 'Race Ranking Position Y';
	const SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Z = 'Race Ranking Position Z';

	const TABLE_RESULTS = 'drakonia_knockout';

	const SETTING_MATCH_TEAMSLIST                  = 'File with teams list';

	/*
* Private properties
*/
	/** @var ManiaControl $maniaControl */
	private $maniaControl         = null;
	private $koStarted            = false;
	private $koStarted2           = false;
	private $scriptsDir           = null;
	private $nbrounds             = 0;
	private $alreadydone          = false;
	private $nbplayers            = 0;
	private $nbplayers2           = 0;
	private $players              = array();
	private $nbplayersnotfinished = 0;
	private $nblifes              = 1;
	private $playerslifes         = array();
	private $widgetshown          = false;
	private $isTrackmania         = false;
	private $matchinit            = false;
	private $loadedSettings       = array();
	private $playerslist = array();

	/**
	 * @param ManiaControl $maniaControl
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
		return 'Plugin offers a KnockOut Plugin';
	}


	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		if ($this->maniaControl->getServer()->titleId == "Trackmania") {
			$this->isTrackmania = true;
		} else {
			$this->isTrackmania = false;
		}

		//Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_ACTIVATED, true, "Activate the plugin");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_WIDGET_ACTIVATED, true, "Activate the widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_SPEC_OR_KICK, array('Spec', 'Kick'), "Force Spec or Kick player if eliminated");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_SOLO_OR_TEAM, array('Solo', 'Team'), "Solo or Team gamemode");

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_DURATIONWARMUP, 10, "Warm-Up Duration in seconds");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_MAPLIST, "Drakonia_KO.txt", "Matchsettings file to load (file with the list of maps)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_ROUNDSPERMAP, 4, "Number of rounds per map");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_FINISHTIMEOUT, 10, "Finish Timeout (Time after the first finished)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_SHUFFLEMAPS, true, "Shuffle maps order");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_PLAYER_PASSWORD, "KODrakonia", "Server password for players when KO is in progress");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_SPECTATOR_PASSWORD, "KODrakonia", "Server password for spectators when KO is in progress");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_NBLIFES, 1, "Number of lives for each players at the start of the KO");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_LINE_HEIGHT, 4, "Height of a player line in the widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_LINESCOUNT, 20, "Number of players to display in the widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_POSX, -139, "Position of the widget (on X axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_POSY, 70, "Position of the widget (on Y axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_WIDTH, 42, "Width of the widget");

		if (!$this->isTrackmania) {
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING, true, "Move Nadeo Race Ranking widget (displayed at the end of the round)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_X, 100, "Position of the Race Ranking widget (on X axis)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Y, 50, "Position of the Race Ranking widget (on Y axis)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Z, 150, "Position of the Race Ranking widget (on Z axis)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_ALLOWREPASWN, true, "Allow Respawn");

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
		} else {
			// For TMNext
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_DECO_CHECKPOINT, "", "Deco URL for Checkpoint");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_DECO_SCREEN8X1, "", "Deco URL for Screen 8x1");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_DECO_SPONSOR, "", "Deco URL for Sponsor");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_DECO_SCREEN16X1, "", "Deco URL for Screen 16x1");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_DECO_SCREEN16X9, "", "Deco URL for Screen 16x9");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_RESPAWNBEHAVIOUR, array(0, 1, 2, 3,
			                                                                                                            4), "Respawn Behaviour (0: default, 1: normal, 2: do nothing, 3: give up before 1st CP, 4: always give up)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_USEDELAYEDVISUALS, true, "Activate the Delayed Visuals for ghosts cars (performances)");
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_TRUSTCLIENTSIMU, true, "Use Trust Client Simulation (performances)");

		}

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_AUTHLEVEL, AuthenticationManager::getPermissionLevelNameArray(AuthenticationManager::AUTH_LEVEL_ADMIN), "Admin level needed to use the plugin");


		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');


		$this->maniaControl->getCommandManager()->registerCommandListener('kostart', $this, 'onCommandKOStart', true, 'Start a KO');
		$this->maniaControl->getCommandManager()->registerCommandListener('kostop', $this, 'onCommandKOStop', true, 'Stop a KO');

		$this->maniaControl->getCommandManager()->registerCommandListener('koaddlives', $this, 'onCommandKOAddLives', true, 'Add lives for a player [login] [lives]');
		$this->maniaControl->getCommandManager()->registerCommandListener('koremovelives', $this, 'onCommandKORemoveLives', true, 'Remove lives for a player [login] [lives]');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::BEGINMAP, $this, 'handleBeginMapCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTROUNDSTART, $this, 'handleBeginRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_SCORES, $this, 'handleEndRoundCallback');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleSpec');

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_TEAMSLIST, '/data/ntwu/ManiaControl_Twitch/teams.txt');

		$this->init();


		$this->initTables();
	}

	public function init() {
		$file = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_TEAMSLIST);
		//		$server = $this->maniaControl->getServer()->login;
		//		$file   = $file . $server . ".txt";
		if (file_exists($file)) {
			$lines  = explode(PHP_EOL, file_get_contents($file));
			$lineid = 1;
			foreach ($lines as $line) {
				//				var_dump($line);
				$team            = explode('=', $line, 2);
				$players         = explode(',', $team[1]);
				foreach ($players as $player) {
					$player2                    = explode(':', $player);
					$this->playerslist[]        = $player2[0];
				}

				$lineid++;
			}
		} else {
			$this->maniaControl->getChat()->sendErrorToAdmins("Teams File doesn't exist: " . $file);

		}

		//		        var_dump($this->teams);
		//		        var_dump($this->teamsName);
	}

	/**
	 * Initialize needed database tables
	 */
	private function initTables() {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query  = "CREATE TABLE IF NOT EXISTS `" . self::TABLE_RESULTS . "` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`server` VARCHAR(60) NOT NULL,
				`mapname` VARCHAR(255) DEFAULT NULL,
				`round` INT(11) NOT NULL,
				`results` TEXT NOT NULL,
				PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}

	}


	public function onCommandKOStart(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		$this->KOStart();
	}

	public function onCommandKOStop(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		$this->KOStop();
	}

	public function onCommandKOAddLives(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		$text = $chatCallback[1][2];
		$text = explode(" ", $text);
		//        Logger::log($text[1] . " " . $text[2]);
		$player = $this->maniaControl->getPlayerManager()->getPlayer($text[1]);
		if ($player) {
			//            if (is_int($text[2])) {
			$this->playerslifes[$player->login] = $this->playerslifes[$player->login] + $text[2];
			Logger::log("Player " . $player->nickname . " has now " . $this->playerslifes[$player->login] . " lives");
			try {
				$this->maniaControl->getClient()->chatSend("Player " . $player->nickname . " \$zhas now " . $this->playerslifes[$player->login] . " lives");
			} catch (InvalidArgumentException $e) {
			}
			//            }
		}
		$this->displayKO($this->playerslifes);
	}

	public function onCommandKORemoveLives(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

			return;
		}
		$text = $chatCallback[1][2];
		$text = explode(" ", $text);
		//        Logger::log($text[1] . " " . $text[2]);
		$player = $this->maniaControl->getPlayerManager()->getPlayer($text[1]);
		if ($player) {
			//            if (is_int($text[2])) {
			$this->playerslifes[$player->login] = $this->playerslifes[$player->login] - $text[2];
			Logger::log("Player " . $player->nickname . " has now " . $this->playerslifes[$player->login] . " lives");
			try {
				$this->maniaControl->getClient()->chatSend("Player " . $player->nickname . " \$zhas now " . $this->playerslifes[$player->login] . " lives");
			} catch (InvalidArgumentException $e) {
			}
			if ($this->playerslifes[$player->login] < 1) {
				Logger::log("Player " . $player->nickname . " is eliminated");

				try {
					$this->maniaControl->getClient()->chatSend("Player " . $player->nickname . " \$z is eliminated");
				} catch (InvalidArgumentException $e) {
				}
				try {
					if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SPEC_OR_KICK) === "Kick") {
						$this->maniaControl->getClient()->kick($player->login);
					} else {
						$this->maniaControl->getClient()->forceSpectator($player->login, 1);
					}
				} catch (Exception $e) {
					//                    var_dump($e->getMessage());
				} catch (InvalidArgumentException $e) {
				}
			}
			//            }
		}

		$this->displayKO($this->playerslifes);
	}

	public function KOStart() {

		$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$player) {

			try {
				$this->maniaControl->getChat()->sendChat("$0f0KO match start!");
				Logger::log("KO match start!");


				$this->playerslifes = array();
				$this->nbrounds     = 0;

				//        var_dump($loadedSettings);
				$this->nblifes = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_NBLIFES);


				$maplist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_MAPLIST);
				$maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $maplist;


				$this->maniaControl->getClient()->loadMatchSettings($maplist);


				$this->maniaControl->getMapManager()->restructureMapList();

				$shufflemaplist = (boolean) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SHUFFLEMAPS);

				if ($shufflemaplist) {
					$this->maniaControl->getMapManager()->shuffleMapList();
				}

				$players = $this->maniaControl->getPlayerManager()->getPlayers();
				foreach ($players as $player) {

					if ($player->isSpectator) {
						$this->playerslifes[$player->login] = -1;
					} else {
						$this->playerslifes[$player->login] = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_NBLIFES);
					}
				}


				$this->maniaControl->getClient()->setServerPassword($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_PLAYER_PASSWORD));
				$this->maniaControl->getClient()->setServerPasswordForSpectator($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SPECTATOR_PASSWORD));

				$nbplayers         = $this->maniaControl->getPlayerManager()->getPlayerCount(true);
				$pointsrepartition = $nbplayers;
				foreach (range(($nbplayers - 1), 1) as $number) {
					$pointsrepartition .= "," . $number;
				}
				//				var_dump($pointsrepartition);


				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SOLO_OR_TEAM) == "Solo") {
					if (!$this->isTrackmania) {
						$scriptName     = 'Rounds.Script.txt';
						$loadedSettings = array("S_PointsLimit"                       => 999999, "S_WarmUpNb" => 1, "S_MapsPerMatch" => 9999,
						                        self::SETTING_KNOCKOUT_ROUNDSPERMAP   => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_ROUNDSPERMAP),
						                        self::SETTING_KNOCKOUT_ALLOWREPASWN   => (boolean) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_ALLOWREPASWN),
						                        self::SETTING_KNOCKOUT_DURATIONWARMUP => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DURATIONWARMUP),
						                        self::SETTING_KNOCKOUT_FINISHTIMEOUT  => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_FINISHTIMEOUT),

						);
					} else {
						$scriptName     = 'TM_Rounds_Online.Script.txt';
						$loadedSettings = array("S_PointsLimit"                          => 999999, "S_WarmUpNb" => 1, "S_MapsPerMatch" => 9999,
						                        self::SETTING_KNOCKOUT_ROUNDSPERMAP      => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_ROUNDSPERMAP),
						                        self::SETTING_KNOCKOUT_RESPAWNBEHAVIOUR  => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_RESPAWNBEHAVIOUR),
						                        self::SETTING_KNOCKOUT_DURATIONWARMUP    => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DURATIONWARMUP),
						                        self::SETTING_KNOCKOUT_FINISHTIMEOUT     => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_FINISHTIMEOUT),
						                        self::SETTING_KNOCKOUT_POINTSREPARTITION => (string) $pointsrepartition,
						                        self::SETTING_KNOCKOUT_DECO_SCREEN16X9   => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DECO_SCREEN16X9),
						                        self::SETTING_KNOCKOUT_DECO_SCREEN16X1   => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DECO_SCREEN16X1),
						                        self::SETTING_KNOCKOUT_DECO_SPONSOR      => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DECO_SPONSOR),
						                        self::SETTING_KNOCKOUT_DECO_SCREEN8X1    => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DECO_SCREEN8X1),
						                        self::SETTING_KNOCKOUT_DECO_CHECKPOINT   => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DECO_CHECKPOINT),
						                        self::SETTING_KNOCKOUT_USEDELAYEDVISUALS => (boolean) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_USEDELAYEDVISUALS),
						                        self::SETTING_KNOCKOUT_TRUSTCLIENTSIMU   => (boolean) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_TRUSTCLIENTSIMU),


						);
					}
				} else {
					if (!$this->isTrackmania) {
						$scriptName     = 'Team.Script.txt';
						$loadedSettings = array("S_PointsLimit"                       => 999999, "S_WarmUpNb" => 1, "S_MapsPerMatch" => 9999,
						                        self::SETTING_KNOCKOUT_ROUNDSPERMAP   => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_ROUNDSPERMAP),
						                        self::SETTING_KNOCKOUT_ALLOWREPASWN   => (boolean) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_ALLOWREPASWN),
						                        self::SETTING_KNOCKOUT_DURATIONWARMUP => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DURATIONWARMUP),
						                        self::SETTING_KNOCKOUT_FINISHTIMEOUT  => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_FINISHTIMEOUT),

						);
					} else {
						$scriptName     = 'TM_Teams_Online.Script.txt';
						$loadedSettings = array("S_PointsLimit"                          => 999999, "S_WarmUpNb" => 1, "S_MapsPerMatch" => 9999,
						                        self::SETTING_KNOCKOUT_ROUNDSPERMAP      => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_ROUNDSPERMAP),
						                        self::SETTING_KNOCKOUT_RESPAWNBEHAVIOUR  => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_RESPAWNBEHAVIOUR),
						                        self::SETTING_KNOCKOUT_DURATIONWARMUP    => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DURATIONWARMUP),
						                        self::SETTING_KNOCKOUT_FINISHTIMEOUT     => (int) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_FINISHTIMEOUT),
						                        self::SETTING_KNOCKOUT_POINTSREPARTITION => (string) $pointsrepartition,
						                        self::SETTING_KNOCKOUT_DECO_SCREEN16X9   => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DECO_SCREEN16X9),
						                        self::SETTING_KNOCKOUT_DECO_SCREEN16X1   => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DECO_SCREEN16X1),
						                        self::SETTING_KNOCKOUT_DECO_SPONSOR      => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DECO_SPONSOR),
						                        self::SETTING_KNOCKOUT_DECO_SCREEN8X1    => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DECO_SCREEN8X1),
						                        self::SETTING_KNOCKOUT_DECO_CHECKPOINT   => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DECO_CHECKPOINT),
						                        self::SETTING_KNOCKOUT_USEDELAYEDVISUALS => (boolean) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_USEDELAYEDVISUALS),
						                        self::SETTING_KNOCKOUT_TRUSTCLIENTSIMU   => (boolean) $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_TRUSTCLIENTSIMU),


						);
					}
				}

				$this->loadScript($scriptName, $loadedSettings);

				$this->koStarted  = true;
				$this->koStarted2 = true;

				$this->displayWidgets();



				if ($this->isTrackmania) {
					$this->loadedSettings = $loadedSettings;
					$this->matchinit      = true;
				} else {
					$this->moveRaceRanking();
					$pointsrepartition = explode(",", $pointsrepartition);
					$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPointsRepartition($pointsrepartition);
				}

				$this->maniaControl->getMapManager()->getMapActions()->skipMap();

			} catch (Exception $e) {
				$this->maniaControl->getChat()->sendError("Can not start KO: " . $e->getMessage());
			}
		}, 2000);

	}

	public function KOStop() {

		$this->koStarted = false;

		if ($this->isTrackmania) {
			$scriptName     = "TM_TimeAttack_Online.Script.txt";
			$loadedSettings = array('S_TimeLimit' => 600, "S_WarmUpNb" => 0);
		} else {
			$scriptName     = 'TimeAttack.Script.txt';
			$loadedSettings = array('S_TimeLimit' => 600, "S_WarmUpNb" => 0, self::SETTING_KNOCKOUT_ALLOWREPASWN => true, self::SETTING_KNOCKOUT_DURATIONWARMUP => 0);
		}


		$this->loadScript($scriptName, $loadedSettings);
		if ($this->isTrackmania) {
			$this->loadedSettings = $loadedSettings;
			$this->matchinit      = true;
		}
		$this->maniaControl->getChat()->sendSuccess("KO match stop");
		Logger::log("KO match stop");

		$this->maniaControl->getClient()->setServerPassword("");
		$this->maniaControl->getClient()->setServerPasswordForSpectator("");


		$this->closeWidget(self::MLID_KNOCKOUT_WIDGET);
		$this->closeWidget(self::MLID_KNOCKOUT_WIDGETTIMES);

		// Allow all players to choose play
		$players = $this->maniaControl->getPlayerManager()->getPlayers();
		foreach ($players as $player) {
			try {
				$this->maniaControl->getClient()->forceSpectator($player->login, 0);
			} catch (InvalidArgumentException $e) {
			}
		}

	}

	public function handlePlayerConnect(Player $player) {
		if ($this->koStarted) {
			if ($player->isSpectator) {
				$this->playerslifes[$player->login] = -1;
			} else {
				$this->playerslifes[$player->login] = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_NBLIFES);
			}
			$this->displayWidgets();
		}

	}

	public function handleBeginMapCallback() {

		if ($this->isTrackmania and $this->matchinit) {
			if ($this->koStarted) {
				if ($this->loadedSettings != null) {
					Logger::log("Load Settings for match");
					$this->maniaControl->getClient()->setModeScriptSettings($this->loadedSettings);
				}
			}
			$this->matchinit = false;
		}



	}

	public function handleBeginRoundCallback() {
		$this->alreadydone = false;
		$this->koStarted2  = false;
		$this->displayKO($this->playerslifes);
	}

	/**
	 * @param OnScoresStructure $structure
	 */
	public function handleEndRoundCallback(OnScoresStructure $structure) {
		$nbplayersdel     = 0;
		$this->nbplayers  = 0;
		$this->nbplayers2 = 0;
		$realSection      = false;
		$playerresults    = array();
		$scores           = array();
		$rank             = 1;
		if (!$this->koStarted2) {

			if ($this->koStarted) {
				if ($structure->getSection() == "PreEndRound") {
					$realSection = true;
				}

			}
		}
		if ($realSection) {


			if ($this->alreadydone === false) {
				$results = $structure->getPlayerScores();
				foreach ($results as $result) {
					$login  = $result->getPlayer()->login;
					$player = $result->getPlayer();
					if (in_array($player->login, $this->playerslist) && !$player->isSpectator) {

						$roundpoints = $result->getRoundPoints();
						Logger::log($player->nickname . " " . $roundpoints);

						// For database results
						$scores[$rank] = array("login" => $login, "KO" => 0);


						if ($roundpoints == 0) {
							$nbplayersdel++;
							if ($this->playerslifes[$login]) {
								$this->playerslifes[$login]--;
							} else {
								$this->playerslifes[$login] = 0;
							}
							//                            if ($this->playerslifes[$login] > 0) {
							//                                $this->playerslifes[$login]--;
							//                            }
							Logger::log($player->nickname . " has " . $this->playerslifes[$login] . " lives");
							if ($this->playerslifes[$login] == 0) {
								Logger::log("Player " . $result->getPlayer()->nickname . " is eliminated");
								$scores[$rank] = array("login" => $login, "KO" => 1);

								try {
									if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SOLO_OR_TEAM) == "Solo") {
										$this->maniaControl->getClient()->chatSend("Player " . $result->getPlayer()->nickname . " \$z is eliminated");
									} else {
										if ($result->getPlayer()->teamId == 0) {
											$this->maniaControl->getClient()->chatSend("$00fPlayer " . $result->getPlayer()->nickname . " \$z is eliminated");
										} elseif ($result->getPlayer()->teamId == 1) {
											$this->maniaControl->getClient()->chatSend("\$f00Player " . $result->getPlayer()->nickname . " \$z is eliminated");
										} else {
//											$this->maniaControl->getClient()->chatSend("Player " . $result->getPlayer()->nickname . " \$z is eliminated");
										}
									}
								} catch (InvalidArgumentException $e) {
								}
								try {
									if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SPEC_OR_KICK) === "Kick") {
										$this->maniaControl->getClient()->kick($login);
									} else {
										$this->maniaControl->getClient()->forceSpectator($login, 1);
									}
								} catch (Exception $e) {
									//                    var_dump($e->getMessage());
								} catch (InvalidArgumentException $e) {
								}
							} else {
								if ($this->playerslifes[$login] < 0) {

								} else {
									Logger::log("Player " . $result->getPlayer()->nickname . " has now " . $this->playerslifes[$login] . " lives");

									try {
										if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SOLO_OR_TEAM) == "Solo") {
											$this->maniaControl->getClient()->chatSend("Player " . $result->getPlayer()->nickname . " \$zhas now " . $this->playerslifes[$login] . " lives");
										} else {
											if ($result->getPlayer()->teamId == 0) {
												$this->maniaControl->getClient()->chatSend("$00fPlayer " . $result->getPlayer()->nickname . " \$zhas now " . $this->playerslifes[$login] . " lives");
											} elseif ($result->getPlayer()->teamId == 1) {
												$this->maniaControl->getClient()->chatSend("\$f00Player " . $result->getPlayer()->nickname . " \$zhas now " . $this->playerslifes[$login] . " lives");
											} else {
//												$this->maniaControl->getClient()->chatSend("Player " . $result->getPlayer()->nickname . " \$zhas now " . $this->playerslifes[$login] . " lives");
											}
										}

									} catch (InvalidArgumentException $e) {
									}
									$this->nbplayers2++;
								}
							}

						} else {
							$playerresults                   += array($login => $roundpoints);
							$this->players[$this->nbplayers] = $login;
							$this->nbplayers++;
							$this->nbplayers2++;

						}
					}
					$rank++;
				}


				Logger::log("Nb players who hasn't finished: " . $nbplayersdel);
				arsort($playerresults);

				$logintokick = "";
				$koplayer    = "";
				//                var_dump($playerresults);
				foreach ($playerresults as $key => $value) {
					$logintokick = $key;
				}
				//                $logintokick = $this->players[($this->nbplayers - 1)];

				if (count($playerresults) > 1 and $nbplayersdel == 0) {
					Logger::log($this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " last player");

					$this->playerslifes[$logintokick]--;
					if ($this->playerslifes[$logintokick] == 0) {

						$this->nbplayers--;
						Logger::log("Player " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " is eliminated");
						$koplayer = $logintokick;
						try {
							if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SOLO_OR_TEAM) == "Solo") {
								$this->maniaControl->getClient()->chatSend("Player " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " \$z is eliminated");
							} else {
								if ($this->maniaControl->getPlayerManager()->getPlayer($logintokick)->teamId == 0) {
									$this->maniaControl->getClient()->chatSend("$00fPlayer " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " \$z is eliminated");
								} elseif ($this->maniaControl->getPlayerManager()->getPlayer($logintokick)->teamId == 1) {
									$this->maniaControl->getClient()->chatSend("\$f00Player " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " \$z is eliminated");
								} else {
//									$this->maniaControl->getClient()->chatSend("Player " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " \$z is eliminated");
								}
							}

						} catch (InvalidArgumentException $e) {
						}
						try {
							if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SPEC_OR_KICK) === "Kick") {
								$this->maniaControl->getClient()->kick($logintokick);
							} else {
								$this->maniaControl->getClient()->forceSpectator($logintokick, 1);
							}
						} catch (Exception $e) {
							//                    var_dump($e->getMessage());
						} catch (InvalidArgumentException $e) {
						}
					} else {

						if ($this->playerslifes[$logintokick] < 0) {

						} else {
							Logger::log("Player " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " has now " . $this->playerslifes[$logintokick] . " lives");


							try {
								$this->maniaControl->getClient()->chatSend("Player " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " \$zhas now " . $this->playerslifes[$logintokick] . " lives");
							} catch (InvalidArgumentException $e) {
							}
							//                        $this->nbplayers2++;
						}
					}
				} else {
					//                    $this->nbplayers2++;
				}

				$tempscore = "";
				// Database storage of results
				foreach ($scores as $rank => $score) {
					$ko = $score["KO"];
					if ($koplayer == $score["login"]) {
						$ko = 1;
					}

					$tempscore .= $rank . "," . $this->maniaControl->getPlayerManager()->getPlayer($score["login"])->nickname . "," . $ko . "" . PHP_EOL;
				}

				$mapname = $this->maniaControl->getMapManager()->getCurrentMap()->name;

				$mapname = Formatter::stripCodes($mapname);
				$mysqli  = $this->maniaControl->getDatabase()->getMysqli();
				$server  = $this->maniaControl->getServer()->login;
				$query   = "INSERT INTO `" . self::TABLE_RESULTS . "`
                            (`server`,`mapname`,`round`,`results`)
                            VALUES
                            ('$server','$mapname'," . ($this->nbrounds + 1) . ",'$tempscore')";
				Logger::log($query);
				$mysqli->query($query);

				if ($mysqli->error) {
					trigger_error($mysqli->error);

					return false;
				}

				$this->nbrounds++;

				$this->alreadydone = true;

				if (($this->nbplayers - 1) < 1 && $this->nbplayers2 < 2) {
					$this->KOStop();
				}

			}


			$this->displayKO($this->playerslifes);
		}

		return true;
	}

	private function displayWidgets() {


		// Display KO Widget

		if ($this->koStarted) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_WIDGET_ACTIVATED)) {
				$this->displayKnockoutWidget();
			}
		}

	}

	/**
	 * Displays the Knockout Widget
	 *
	 * @param bool $login
	 */
	public function displayKnockoutWidget($login = false) {
		$posX       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_POSX);
		$posY       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_POSY);
		$width      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_WIDTH);
		$lineHeight = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_LINE_HEIGHT);
		$lines      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_LINESCOUNT);

		$height       = 7. + $lines * $lineHeight;
		$labelStyle   = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultLabelStyle();
		$quadSubstyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();
		$quadStyle    = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();


		// mainframe
		$frame = new Frame();
		$frame->setPosition($posX, $posY, 998);
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
		//		$titleLabel->setText("Knockout Live");
		//TODO: For Twitch Rivals
		$titleLabel->setText("KO Live");
		$titleLabel->setTranslate(true);

		$maniaLink = new ManiaLink(self::MLID_KNOCKOUT_WIDGET);
		$maniaLink->addChild($frame);
		// Send manialink
		$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
	}


	public function displayKO($ranking) {

		if ($this->koStarted) {
			$lines      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_LINESCOUNT);
			$lineHeight = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_LINE_HEIGHT);
			$posX       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_POSX);
			$posY       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_POSY);
			$width      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_WIDTH);


			$maniaLink = new ManiaLink(self::MLID_KNOCKOUT_WIDGETTIMES);
			$listFrame = new Frame();
			$maniaLink->addChild($listFrame);
			$listFrame->setPosition($posX, $posY, 999);

			// Obtain a list of columns

			arsort($ranking);

			$rank = 1;

			foreach ($ranking as $index => $record) {
				if ($rank >= $lines or $record < 1) {
					break;
				}

				$points = $record;

				//TODO: For Twitch Rivals
				if ($points == 1) {
					$points = "";
				}

				$player = $this->maniaControl->getPlayerManager()->getPlayer($index);

				$y = -10 - ($rank - 1) * $lineHeight;

				$recordFrame = new Frame();
				$listFrame->addChild($recordFrame);
				$recordFrame->setPosition(0, $y + $lineHeight / 2, 999);

				//Rank
				$rankLabel = new Label();
				$recordFrame->addChild($rankLabel);
				$rankLabel->setHorizontalAlign($rankLabel::LEFT);
				$rankLabel->setX($width * -0.47);
				$rankLabel->setSize($width * 0.06, $lineHeight);
				$rankLabel->setTextSize(1);
				$rankLabel->setTextPrefix('$o');
				//TODO : For Twitch Rivals
				//				$rankLabel->setText($rank);

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

				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SOLO_OR_TEAM) == "Team") {
					$quad->setStyles(Quad_Bgs1InRace::STYLE, Quad_Bgs1InRace::SUBSTYLE_BgCard);
					$quad->setOpacity(0.7);
					if ($player->teamId == 0) {
						$quad->setColorize('00f');
					} else {
						$quad->setColorize('f00');
					}

				} else {
					$quad->setStyles(Quad_Bgs1InRace::STYLE, Quad_Bgs1InRace::SUBSTYLE_BgCardList);
				}
				$quad->setSize($width - 1, $lineHeight);
				$quad->setAction(self::KNOCKOUT_ACTION_SPEC . '.' . $player->login);

				$rank++;

			}

			$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, false);
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
				case self::KNOCKOUT_ACTION_SPEC:
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
	 * Close a Widget
	 *
	 * @param string $widgetId
	 */
	public function closeWidget($widgetId) {
		$this->maniaControl->getManialinkManager()->hideManialink($widgetId);
	}


	public function updateWidget($ranking) {

		if ($ranking) {
			$this->displayKO($ranking);
		} else {
			$this->closeWidget(self::MLID_KNOCKOUT_WIDGETTIMES);
		}

	}

	/**
	 * Loads a script if possible, otherwise throws an exception.
	 * $scriptName will be set to a proper capitalized etc. name.
	 *
	 * @param       $scriptName
	 * @param array $loadedSettings
	 * @throws Exception
	 */
	private function loadScript(&$scriptName, array $loadedSettings) {
		if ($this->isTrackmania) {


			$this->maniaControl->getClient()->setScriptName("Trackmania/" . $scriptName);
		} else {
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
				$this->maniaControl->getClient()->setModeScriptSettings($loadedSettings);
			} else {
				throw new Exception('Scripts directory not found (' . $this->scriptsDir . ').');
			}
		}
	}

	public function moveRaceRanking() {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING)) {
			$properties = "<ui_properties>
    <round_scores pos='-158.5 40. 150.' visible='true' />
  </ui_properties>";
			Logger::log('Put Race Ranking Widget to the original place');
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaUIProperties($properties);

		} else {
			$properties = "<ui_properties>
    <round_scores pos='" . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_X) . " " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Y) . ". " . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Z) . ".' visible='true' />
  </ui_properties>";
			Logger::log('Put Race Ranking Widget to custom position');
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaUIProperties($properties);

		}
	}


	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->closeWidget(self::MLID_KNOCKOUT_WIDGET);
		$this->closeWidget(self::MLID_KNOCKOUT_WIDGETTIMES);
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->displayWidgets();

			$this->moveRaceRanking();
		}
	}

}