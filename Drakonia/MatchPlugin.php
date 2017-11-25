<?php


namespace Drakonia;


use Exception;
use FML\Controls\Frame;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\ManiaLink;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
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

/**
 * Match Plugin
 *
 * @author    jonthekiller
 * @copyright 2017 Drakonia Team
 */
class MatchPlugin implements ManialinkPageAnswerListener, CallbackListener, CommandListener, TimerListener, CommunicationListener, Plugin
{

    const PLUGIN_ID = 119;
    const PLUGIN_VERSION = 0.3;
    const PLUGIN_NAME = 'MatchPlugin';
    const PLUGIN_AUTHOR = 'jonthekiller';

    //Properties
    const SETTING_MATCH_ACTIVATED = 'Match Plugin Activated:';
    const SETTING_MATCH_AUTHLEVEL = 'Auth level for the match* commands:';
    const SETTING_MATCH_MATCHSETTINGS_CONF = 'Match settings from matchsettings only:';
    const SETTING_MATCH_MATCH_MODE = 'Gamemode used during match:';
    const SETTING_MATCH_NOMATCH_MODE = 'Gamemode used after match:';
    const SETTING_MATCH_MATCH_POINTS = 'S_PointsLimit';
    const SETTING_MATCH_MATCH_TIMELIMIT = 'S_TimeLimit';
    const SETTING_MATCH_MATCH_ALLOWREPASWN = 'S_AllowRespawn';
    const SETTING_MATCH_MATCH_DISPLAYTIMEDIFF = 'S_DisplayTimeDiff';
    const SETTING_MATCH_MATCH_NBWARMUP = 'S_WarmUpNb';
    const SETTING_MATCH_MATCH_DURATIONWARMUP = 'S_WarmUpDuration';
    const SETTING_MATCH_MATCH_FINISHTIMEOUT = 'S_FinishTimeout';
    const SETTING_MATCH_MATCH_POINTSREPARTITION = 'PointsRepartition';
    const SETTING_MATCH_MATCH_ROUNDSPERMAP = 'S_RoundsPerMap';
    const SETTING_MATCH_MATCH_NBWINNERS = 'S_NbOfWinners';
    const SETTING_MATCH_MATCH_NBMAPS = 'S_MapsPerMatch';
    const SETTING_MATCH_MATCH_SHUFFLEMAPS = 'Shuffle Maplist';
    const SETTING_MATCH_READY_MODE = 'Ready Button';
    const MLID_MATCH_READY_WIDGET = 'Ready ButtonWidget';
    const SETTING_MATCH_READY_POSX = 'Ready Button-Position: X';
    const SETTING_MATCH_READY_POSY = 'Ready Button-Position: Y';
    const SETTING_MATCH_READY_WIDTH = 'Ready Button-Size: Width';
    const SETTING_MATCH_READY_HEIGHT = 'Ready Button-Size: Height';
    const SETTING_MATCH_MAPLIST = 'Maplist to use';
    const SETTING_MATCH_READY_NBPLAYERS = 'Ready Button-Minimal number of players before start';
    const ACTION_READY = 'ReadyButton.Action';
    const TABLE_ROUNDS = 'drakonia_rounds';
    const TABLE_MATCHS = 'drakonia_matchs';
    const SETTING_MATCH_FORCESHOWOPPONENTS = 'Force Show Opponents';

    const DEFAULT_TRACKMANIALIVE_PLUGIN = 'Drakonia\TrackmaniaLivePlugin';

    /*
     * Private properties
     */
    /** @var ManiaControl $maniaControl */
    private $maniaControl = null;
    private $scriptsDir = null;
    public $matchStarted = false;
    private $times = array();
    private $nbmaps = 0;
    private $nbrounds = 0;
    private $playerstate = array();
    private $nbplayers = 0;
    private $alreadydone = false;
    public $livePlugin = false;
    private $alreadyDoneTA = false;

    // Settings to keep in memory
    private $settings_nbplayers = 0;
    private $settings_nbroundsbymap = 5;
    private $settings_nbwinners = 2;
    private $settings_nbmapsbymatch = 0;
    private $matchrecover = false;
    private $matchrecover2 = false;
    private $restorepoints = false;
    private $fakeround = false;
    private $scores = "";
    private $type = "";

    private $player1_playerlogin = "";
    private $player2_playerlogin = "";
    private $player3_playerlogin = "";
    private $player4_playerlogin = "";

    private $player1_points = 0;
    private $player2_points = 0;
    private $player3_points = 0;
    private $player4_points = 0;

    private $playercount = 0;
    private $nbwinners = 0;
    private $matchSettings = array();
    private $playerswinner = array();
    private $playersfinalist = array();
    private $scriptSettings = array();

    private $currentscore = array();

    /**
     * @see \ManiaControl\Plugins\Plugin::prepare()
     * @param ManiaControl $maniaControl
     */
    public static function prepare(ManiaControl $maniaControl)
    {
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getId()
     */
    public static function getId()
    {
        return self::PLUGIN_ID;
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getName()
     */
    public static function getName()
    {
        return self::PLUGIN_NAME;
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getVersion()
     */
    public static function getVersion()
    {
        return self::PLUGIN_VERSION;
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getAuthor()
     */
    public static function getAuthor()
    {
        return self::PLUGIN_AUTHOR;
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getDescription()
     */
    public static function getDescription()
    {
        return 'Plugin offers a match Plugin';
    }


    public function load(ManiaControl $maniaControl)
    {
        $this->maniaControl = $maniaControl;
        $this->initTables();

        //Settings
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_ACTIVATED, true);
        $this->maniaControl->getSettingManager()->initSetting(
            $this,
            self::SETTING_MATCH_MATCH_MODE,
            array("Cup", "Rounds", "TimeAttack")
        );
        $this->maniaControl->getSettingManager()->initSetting(
            $this,
            self::SETTING_MATCH_NOMATCH_MODE,
            array("TimeAttack", "Cup", "Rounds")
        );
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_POINTS, 100);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_TIMELIMIT, 600);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN, true);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCHSETTINGS_CONF, false);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF, true);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_NBWARMUP, 1);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP, -1);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT, 10);
        $this->maniaControl->getSettingManager()->initSetting(
            $this,
            self::SETTING_MATCH_MATCH_POINTSREPARTITION,
            "10,6,4,3,2,1"
        );
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP, 5);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_NBWINNERS, 1);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_NBMAPS, 3);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCH_SHUFFLEMAPS, true);
        $this->maniaControl->getSettingManager()->initSetting(
            $this,
            self::SETTING_MATCH_AUTHLEVEL,
            AuthenticationManager::AUTH_LEVEL_ADMIN
        );

        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_MODE, true);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_POSX, 152.5);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_POSY, 40);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_WIDTH, 15);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_HEIGHT, 6);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_NBPLAYERS, 2);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MAPLIST, "Drakonia_Cup.txt");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_FORCESHOWOPPONENTS, true);

        $this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(
            self::ACTION_READY,
            $this,
            'handleReady'
        );
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            SettingManager::CB_SETTING_CHANGED,
            $this,
            'updateSettings'
        );

        //Register Callbacks
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            PlayerManager::CB_PLAYERCONNECT,
            $this,
            'handlePlayerConnect'
        );
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            PlayerManager::CB_PLAYERDISCONNECT,
            $this,
            'handlePlayerDisconnect'
        );
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            PlayerManager::CB_PLAYERINFOCHANGED,
            $this,
            'handlePlayerInfoChanged'
        );

        $this->maniaControl->getCommandManager()->registerCommandListener(
            'matchstart',
            $this,
            'onCommandMatchStart',
            true,
            'Start a match'
        );
        $this->maniaControl->getCommandManager()->registerCommandListener(
            'matchstop',
            $this,
            'onCommandMatchStop',
            true,
            'Stop a match'
        );
        $this->maniaControl->getCommandManager()->registerCommandListener(
            'matchendround',
            $this,
            'onCommandMatchEndRound',
            true,
            'Force end a round during a match'
        );
        $this->maniaControl->getCommandManager()->registerCommandListener(
            'matchrecover',
            $this,
            'onCommandMatchRecover',
            true,
            'Recover match, args: {nb of current map} {nb of current round} [match_id] (Cup only)'
        );

        $this->maniaControl->getCommandManager()->registerCommandListener(
            'matchsetpoints',
            $this,
            'onCommandSetPoints',
            true,
            'Sets points to a player.'
        );

        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            Callbacks::TM_SCORES,
            $this,
            'handleEndRoundCallback'
        );
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            Callbacks::MP_STARTROUNDSTART,
            $this,
            'handleBeginRoundCallback'
        );
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            Callbacks::BEGINMAP,
            $this,
            'handleBeginMapCallback'
        );
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            Callbacks::ENDMAP,
            $this,
            'handleEndMapCallback'
        );
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            Callbacks::TM_ONFINISHLINE,
            $this,
            'handleFinishCallback'
        );

        $this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle5Seconds', 5000);


        $this->maniaControl->getCommunicationManager()->registerCommunicationListener(
            "Match.GetMatchStatus",
            $this,
            function ($data) {
                return new CommunicationAnswer($this->getMatchStatus());
            }
        );

        $this->maniaControl->getCommunicationManager()->registerCommunicationListener(
            "Match.GetCurrentScore",
            $this,
            function ($data) {
                return new CommunicationAnswer($this->getCurrentScore());
            }
        );

        $this->maniaControl->getCommunicationManager()->registerCommunicationListener(
            "Match.GetPlayers",
            $this,
            function ($data) {
                return new CommunicationAnswer($this->getPlayers());
            }
        );

        $this->maniaControl->getCommunicationManager()->registerCommunicationListener(
            "Match.MatchStart",
            $this,
            function ($data) {
                return new CommunicationAnswer($this->MatchStart());
            }
        );

        $this->maniaControl->getCommunicationManager()->registerCommunicationListener(
            "Match.MatchStop",
            $this,
            function ($data) {
                return new CommunicationAnswer($this->MatchStop());
            }
        );

        $this->maniaControl->getCommunicationManager()->registerCommunicationListener(
            "Match.GetMatchOptions",
            $this,
            function ($data) {
                return new CommunicationAnswer($this->getMatchOptions());
            }
        );


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
            $game = '';
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
                $this->scriptsDir = $scriptsDataDir.'Modes'.DIRECTORY_SEPARATOR.$game.DIRECTORY_SEPARATOR;
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
    private function initTables()
    {
        $mysqli = $this->maniaControl->getDatabase()->getMysqli();
        $query = "CREATE TABLE IF NOT EXISTS `".self::TABLE_ROUNDS."` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`server` VARCHAR(60) NOT NULL,
				`rounds` TEXT NOT NULL,
				PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
        $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error, E_USER_ERROR);
        }

        $query = "CREATE TABLE IF NOT EXISTS `".self::TABLE_MATCHS."` (
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

    /**
     * @see \ManiaControl\Plugins\Plugin::unload()
     */
    public function unload()
    {
        $this->closeWidget();
    }

    /**
     * Handle ManiaControl After Init
     */
    public function handle5Seconds()
    {

        //Logger::log($this->matchStarted ? 'true' : 'false');
        if ($this->matchStarted === false) {
            if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_MODE)) {
                //Logger::log("Ready Mode enabled");
                $nbplayers = $this->maniaControl->getPlayerManager()->getPlayerCount();
                //Logger::log($nbplayers);
                if ($nbplayers >= $this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_READY_NBPLAYERS
                    )
                ) {
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


    public function getScriptSettings()
    {
        return $this->maniaControl->getClient()->getModeScriptSettings();
    }

    public function getMatchOptions()
    {
        $options = array(
            "Nb Winners" => (!isset($this->scriptSettings['S_NbOfWinners']) || is_null($this->scriptSettings['S_NbOfWinners'])) ? '0' : $this->scriptSettings['S_NbOfWinners'],
            "Points limit" => (!isset($this->scriptSettings['S_PointsLimit']) || is_null($this->scriptSettings['S_PointsLimit'])) ? '0' : $this->scriptSettings['S_PointsLimit'],
            "Points Repartition" => $this->maniaControl->getSettingManager()->getSettingValue(
                $this,
                self::SETTING_MATCH_MATCH_POINTSREPARTITION
            ),
            "Allow Respawn" => (!isset($this->scriptSettings['S_AllowRespawn']) || is_null($this->scriptSettings['S_AllowRespawn'])) ? '0' : $this->scriptSettings['S_AllowRespawn'],
            "Gamemode" => $this->maniaControl->getClient()->getModeScriptInfo()->name,
            "Rounds per map" => (!isset($this->scriptSettings['S_RoundsPerMap']) || is_null($this->scriptSettings['S_RoundsPerMap'])) ? '0' : $this->scriptSettings['S_RoundsPerMap'],

        );


        return $options;
    }

    public function getMatchSettings()
    {
        return $this->matchSettings;
    }

    public function getPointsRepartition()
    {
        return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
    }

    public function getPlayers()
    {
        return $this->playerstate;
    }

    public function getMatchStatus()
    {
        return $this->matchStarted;
    }

    public function getRoundNumber()
    {
        return $this->nbrounds;
    }

    public function getPlayersWinner()
    {
        return $this->playerswinner;
    }

    public function getPlayersFinalist()
    {
        return $this->playersfinalist;
    }

    public function getCurrentScore()
    {
        return $this->currentscore;
    }

    public function getMatchPointsLimit()
    {
        return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS);
    }

    public function getNbWinners()
    {
        return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWINNERS);
    }

    public function getMatchMode()
    {
        return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE);
    }

    public function onCommandMatchEndRound(array $chatCallback)
    {
        $this->maniaControl->getModeScriptEventManager()->forceTrackmaniaRoundEnd();

    }

    public function onCommandSetPoints(array $chatCallback)
    {
        $text = $chatCallback[1][2];
        $text = explode(" ", $text);
        Logger::log($text[1]." ".$text[2]);
        $player = $this->maniaControl->getPlayerManager()->getPlayer($text[1]);
        if ($player) {
            $this->maniaControl->getModeScriptEventManager()->setTrackmaniaPlayerPoints($player, "", "", $text[2]);
            $this->maniaControl->getChat()->sendChat(
                '$<$0f0$o Player $z'.$player->nickname.' $z$o$0f0has now '.$text[2].' points!$>'
            );
            $this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_TRACKMANIALIVE_PLUGIN);
            if ($this->livePlugin) {
                $this->livePlugin->SetPoints($player, $text[2]);
            }
        } else {
            $this->maniaControl->getChat()->sendError("Login ".$text[1]." doesn't exist");
        }


    }


    /**
     * @param OnScoresStructure $structure
     */
    public function handleEndRoundCallback(OnScoresStructure $structure)
    {
        //$this->maniaControl->getModeScriptEventManager()->getAllApiVersions()->setCallable(function (AllApiVersionsStructure $structure) {var_dump($structure->getVersions());} );
//        Logger::log($structure->getSection());
        $realSection = false;
        if ($this->matchStarted === true) {
            if ($structure->getSection() == "PreEndRound" && ($this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCH_MODE
                    ) == "Rounds" OR $this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCH_MODE
                    ) == "Cup")
            ) {
                $realSection = true;
            } elseif ($structure->getSection() == "EndRound" && ($this->maniaControl->getSettingManager(
                    )->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "TimeAttack")
            ) {
                $realSection = true;
            } else {
                $realSection = false;
            }
        }

        if ($realSection) {


            if ($this->alreadydone === false AND $this->nbmaps != 0 AND $this->nbrounds != $this->settings_nbroundsbymap) {
                $database = "";
                $this->currentscore = array();
                $this->nbwinners = 0;
                $results = $structure->getPlayerScores();
                $realRound = false;

                $pointsrepartition = $this->getPointsRepartition();
                $pointsrepartition = explode(',', $pointsrepartition);

                foreach ($results as $result) {
                    $login = $result->getPlayer()->login;
                    $rank = $result->getRank();
                    $player = $result->getPlayer();
//                    Logger::log($player->login . " PureSpec " . $player->isPureSpectator . "  Spec " . $player->isSpectator . "  Temp " . $player->isTemporarySpectator . "   Leave " . $player->isFakePlayer() . "  BestRaceTime " . $result->getBestRaceTime() . "  MatchPoints " . $result->getMatchPoints() . "  Roundpoints " . $result->getRoundPoints());

                    if ($this->maniaControl->getSettingManager()->getSettingValue($this,self::SETTING_MATCH_MATCH_MODE) == "Cup" OR
                        $this->maniaControl->getSettingManager()->getSettingValue($this,self::SETTING_MATCH_MATCH_MODE) == "Rounds") {
                        if (($player->isSpectator && $result->getMatchPoints() == 0) || ($player->isFakePlayer(
                                ) && $result->getMatchPoints() == 0)) {
                        } else {

                            if ($this->maniaControl->getSettingManager()->getSettingValue(
                                    $this,
                                    self::SETTING_MATCH_MATCH_MODE
                                ) == "Cup"
                            ) {
                                $roundpoints = $result->getRoundPoints();
                                $points = $result->getMatchPoints();

//                            Logger::log($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS));
//                            Logger::log($roundpoints);
//                            Logger::log($points);
                                if (($points > $this->maniaControl->getSettingManager()->getSettingValue(
                                            $this,
                                            self::SETTING_MATCH_MATCH_POINTS
                                        )) AND ($roundpoints == 0)
                                ) {
                                    //Logger::log("Winner");
                                    $points = ($points + ($this->maniaControl->getSettingManager()->getSettingValue(
                                                    $this,
                                                    self::SETTING_MATCH_MATCH_NBWINNERS
                                                ) - $this->nbwinners)) - $roundpoints;
                                    $this->nbwinners++;
                                } elseif (($points == $this->maniaControl->getSettingManager()->getSettingValue(
                                            $this,
                                            self::SETTING_MATCH_MATCH_POINTS
                                        )) AND ($roundpoints == $pointsrepartition[0])
                                ) {
//                                Logger::log("Winner");
                                    $points = ($points + ($this->maniaControl->getSettingManager()->getSettingValue(
                                                    $this,
                                                    self::SETTING_MATCH_MATCH_NBWINNERS
                                                ) - $this->nbwinners)) - $roundpoints;
                                    $this->nbwinners++;


                                    $this->playerswinner = array_merge($this->playerswinner, array("$login"));
                                    $this->playerswinner = array_unique($this->playerswinner);
                                    $this->playersfinalist = array_diff($this->playersfinalist, ["$login"]);


                                } elseif (($points + $roundpoints) >= $this->maniaControl->getSettingManager(
                                    )->getSettingValue(
                                        $this,
                                        self::SETTING_MATCH_MATCH_POINTS
                                    ) AND ($points != $this->maniaControl->getSettingManager()->getSettingValue(
                                            $this,
                                            self::SETTING_MATCH_MATCH_POINTS
                                        ))
                                ) {
//                                Logger::log("Finalist");
                                    $points = $this->maniaControl->getSettingManager()->getSettingValue(
                                            $this,
                                            self::SETTING_MATCH_MATCH_POINTS
                                        ) - $roundpoints;
                                    $this->playersfinalist = array_merge($this->playersfinalist, array("$login"));
                                    $this->playersfinalist = array_unique($this->playersfinalist);
                                }

                                Logger::log($rank.", ".$login.", ".($points + $roundpoints).", ".$roundpoints);
                                $database .= $rank.",".$login.",".($points + $roundpoints)."".PHP_EOL;

                                if (!$realRound && $roundpoints > 0) {
                                    $realRound = true;
                                }


                                $this->currentscore = array_merge(
                                    $this->currentscore,
                                    array(
                                        array(
                                            $rank,
                                            $result->getPlayer()->login,
                                            $result->getPlayer()->nickname,
                                            ($points + $roundpoints),
                                        ),
                                    )
                                );

                                $this->type = "Cup";

                            } elseif ($this->maniaControl->getSettingManager()->getSettingValue(
                                    $this,
                                    self::SETTING_MATCH_MATCH_MODE
                                ) == "Rounds") {
                                $roundpoints = $result->getRoundPoints();
                                $points = $result->getMatchPoints();


                                Logger::log($rank.", ".$login.", ".($points + $roundpoints).", ".$roundpoints);
                                $database .= $rank.",".$login.",".($points + $roundpoints)."".PHP_EOL;

                                $this->currentscore = array_merge(
                                    $this->currentscore,
                                    array(
                                        array(
                                            $rank,
                                            $result->getPlayer()->login,
                                            $result->getPlayer()->nickname,
                                            ($points + $roundpoints),
                                        ),
                                    )
                                );

                                $this->type = "Rounds";
                            }

                        }
                    }elseif ($this->maniaControl->getSettingManager()->getSettingValue(
                                $this,
                                self::SETTING_MATCH_MATCH_MODE
                            ) == "TimeAttack"
                        ) {

                        if (($player->isSpectator && $result->getBestRaceTime() == 0) || ($player->isSpectator && $result->getBestRaceTime() == -1) ||
                            ($player->isFakePlayer() && $result->getBestRaceTime() == 0) || ($player->isFakePlayer() && $result->getBestRaceTime() == -1)) {
                        } else {
                            $time = $result->getBestRaceTime();
                            Logger::log($rank.", ".$login.", ".$time);
                            $database .= $rank.",".$login.",".$time."".PHP_EOL;

                            $this->type = "TA";
                        }


                    }

                }
                //Logger::log('Count number of players finish: '. count($this->times));

                if (($realRound && $this->maniaControl->getSettingManager()->getSettingValue(
                            $this,
                            self::SETTING_MATCH_MATCH_MODE
                        ) == "Cup") || $this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCH_MODE
                    ) == "Rounds"
                ) {
                    $this->nbrounds++;
                }


                $this->scores = $database;

                if ($this->type == "TA" AND !$this->alreadyDoneTA) {
                    $mapname = $this->maniaControl->getMapManager()->getCurrentMap()->name;

                    $mapname = Formatter::stripCodes($mapname);
                    $mysqli = $this->maniaControl->getDatabase()->getMysqli();
                    $server = $this->maniaControl->getServer()->login;
                    $query = "INSERT INTO `".self::TABLE_MATCHS."`
                            (`type`,`score`,`srvlogin`,`mapname`)
                            VALUES
                            ('$this->type','$this->scores','$server','$mapname')";
                    Logger::log($query);
                    $mysqli->query($query);
                    if($this->livePlugin) {
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


                $query = "INSERT INTO `".self::TABLE_ROUNDS."`
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


                //Logger::log("Rounds finished: " . $this->nbrounds);
            }

            // In case of points reset after 1st map in Recovery mode
            if ($this->matchStarted === true && $this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_MODE
                ) == "Cup" && $this->matchrecover === true
            ) {
                if ($this->nbrounds == $this->settings_nbroundsbymap) {
                    $results = $structure->getPlayerScores();
                    $i = 1;
                    foreach ($results as $result) {
                        if ($result->getPlayer()->isSpectator) {

                        } else {
                            $this->{"player".$i."_playerlogin"} = $result->getPlayer()->login;
                            $this->{"player".$i."_points"} = $result->getMatchPoints() + $result->getRoundPoints();
                            $i++;
                        }
                    }

                    $this->playercount = ($i - 1);
                    $this->restorepoints = true;
                    $this->fakeround = true;
                    $this->maniaControl->getMapManager()->getMapActions()->skipMap();

                }
            }

        }

        return true;
    }


    public function handleBeginRoundCallback()
    {
        $this->alreadyDoneTA = false;
        $this->alreadydone = false;
        $this->times = array();
        if ($this->matchStarted === true && $this->fakeround === false) {
            if ($this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_MODE
                ) == "Rounds" OR $this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_MODE
                ) == "Cup"
            ) {
                $this->maniaControl->getChat()->sendChat(
                    '$<$ffd$o Round>'.($this->nbrounds + 1).' / '.$this->settings_nbroundsbymap.'  $w$i$F00GO GO GO  $F66!$F88!$F99!$FBB!$FDD!$FFF!$>'
                );
                Logger::log("Round: ".($this->nbrounds + 1).' / '.$this->settings_nbroundsbymap);
            } elseif ($this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_MODE
                ) == "TimeAttack"
            ) {
                if ($this->nbmaps > 0) {
                    $this->maniaControl->getChat()->sendChat(
                        '$<$ffd$o Map>'.($this->nbmaps).'   $w$i$F00GO GO GO  $F66!$F88!$F99!$FBB!$FDD!$FFF!$>'
                    );
                    Logger::log("Map: ".$this->nbmaps);
                }
            }
        }


        // Recovering mode (Nadeo's Cup script can reset points after 1st map)
        if ($this->maniaControl->getSettingManager()->getSettingValue(
                $this,
                self::SETTING_MATCH_MATCH_MODE
            ) == "Cup" && $this->matchrecover === true && $this->restorepoints === true && $this->fakeround === false
        ) {

            for ($i = 1; $i <= $this->playercount; $i++) {
                if ($this->{"player".$i."_playerlogin"} != "") {
                    $player = $this->maniaControl->getPlayerManager()->getPlayer($this->{"player".$i."_playerlogin"});
                    $this->maniaControl->getModeScriptEventManager()->setTrackmaniaPlayerPoints(
                        $player,
                        "",
                        "",
                        $this->{"player".$i."_points"}
                    );
                    $this->maniaControl->getChat()->sendChat(
                        '$<$0f0$o Player $z'.$player->nickname.' $z$o$0f0has now '.$this->{"player".$i."_points"}.' points!$>'
                    );
                    Logger::log('Player '.$player->login.' has now '.$this->{"player".$i."_points"}.' points!');
                }
            }
            $this->restorepoints = false;
            $this->matchrecover = false;
        }
    }


    public function handleEndMapCallback()
    {

        if ($this->matchStarted) {
            if ($this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_MODE
                ) == "Rounds"
            ) {

                if ($this->settings_nbmapsbymatch == $this->nbmaps) {

                    if ($this->nbrounds == $this->settings_nbroundsbymap) {

                        $this->MatchEnd();
                    }
                }
            } elseif ($this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_MODE
                ) == "TimeAttack"
            ) {
                if ($this->nbmaps == $this->settings_nbmapsbymatch) {
                    $this->MatchEnd();
                }

            } elseif ($this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_MODE
                ) == "Cup"
            ) {

                if ($this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCH_NBWINNERS
                    ) == $this->nbwinners
                ) {
                    $this->MatchEnd();
                } elseif (count($this->playerswinner) == $this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCH_NBWINNERS
                    ) AND count($this->playerswinner) > 0) {
                    $this->MatchEnd();
                }

            }
            $this->fakeround = false;
        }
    }

    public function MatchEnd()
    {
        $scriptName = $this->maniaControl->getSettingManager()->getSettingValue(
            $this,
            self::SETTING_MATCH_NOMATCH_MODE
        );
        $scriptName .= '.Script.txt';

        $loadedSettings = array(
            'S_TimeLimit' => 600,
            self::SETTING_MATCH_MATCH_ALLOWREPASWN => true,
            self::SETTING_MATCH_MATCH_NBWARMUP => 0,
        );

        try {
            $this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_TRACKMANIALIVE_PLUGIN);
            if ($this->livePlugin) {
                $this->livePlugin->MatchEnd();
            }


            if ($this->type == "Cup") {
                $mysqli = $this->maniaControl->getDatabase()->getMysqli();
                $server = $this->maniaControl->getServer()->login;
                $query = "INSERT INTO `".self::TABLE_MATCHS."`
				(`type`,`score`,`srvlogin`)
				VALUES
				('$this->type','$this->scores','$server')";
                //Logger::log($query);
                $mysqli->query($query);
                if ($mysqli->error) {
                    trigger_error($mysqli->error);

                    return false;
                }
            }elseif($this->type == "TA")
            {
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
            $this->maniaControl->getChat()->sendError("Can not finish match: ".$e->getMessage());
        }
    }

    public function MatchStop()
    {
        $scriptName = $this->maniaControl->getSettingManager()->getSettingValue(
            $this,
            self::SETTING_MATCH_NOMATCH_MODE
        );
        $scriptName .= '.Script.txt';

        $loadedSettings = array(
            'S_TimeLimit' => 600,
            self::SETTING_MATCH_MATCH_ALLOWREPASWN => true,
            self::SETTING_MATCH_MATCH_NBWARMUP => 0,
        );

        try {
            $this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_TRACKMANIALIVE_PLUGIN);
            if ($this->livePlugin) {
                $this->livePlugin->MatchStop();
            }
            $this->loadScript($scriptName, $loadedSettings);
            $this->maniaControl->getChat()->sendSuccess("Match stop");
            Logger::log("Match stop");
            $this->matchStarted = false;
            $this->matchSettings = $loadedSettings;

            $players = $this->maniaControl->getPlayerManager()->getPlayers();

            foreach ($players as $player) {
                $this->handlePlayerConnect($player);
            }

        } catch (Exception $e) {
            $this->maniaControl->getChat()->sendError("Can not stop match: ".$e->getMessage());
        }
    }

    /**
     * Loads a script if possible, otherwise throws an exception.
     * $scriptName will be set to a proper capitalized etc. name.
     *
     * @param $scriptName
     * @param array $loadedSettings
     * @throws Exception
     */
    private function loadScript(&$scriptName, array $loadedSettings = null)
    {
        if ($this->scriptsDir !== null) {
            $scriptPath = $this->scriptsDir.$scriptName;

            if (!file_exists($scriptPath)) {
                throw new Exception('Script not found ('.$scriptPath.').');
            }

            // Get 'real' script name (mainly nice for Windows)
            $scriptName = pathinfo(realpath($scriptPath))['basename'];
            //var_dump($loadedSettings);
            $script = file_get_contents($scriptPath);
            $this->maniaControl->getClient()->setModeScriptText($script);
            $this->maniaControl->getClient()->setScriptName($scriptName);
            if($loadedSettings != null)
                $this->maniaControl->getClient()->setModeScriptSettings($loadedSettings);

        } else {
            throw new Exception('Scripts directory not found ('.$this->scriptsDir.').');
        }
    }

    public function handleBeginMapCallback()
    {

        if ($this->matchStarted === true) {
            $this->nbmaps++;
            if (!$this->matchrecover2) {
                $this->nbrounds = 0;
            }else{
                $this->matchrecover2 = false;
            }
            if ($this->nbmaps > 0) {
                Logger::log("Map: ".$this->nbmaps);

                $maps = $this->maniaControl->getMapManager()->getMaps();
                $i = 1;
                $this->maniaControl->getChat()->sendChat('$<$fff$o$i>>>Current Map$>');
                $currentmap = $this->maniaControl->getMapManager()->getCurrentMap();
                $this->maniaControl->getChat()->sendChat(
                    '$<$fff$o$i'.Formatter::stripCodes($currentmap->name).'$>'
                );
                Logger::log("Current Map: ".Formatter::stripCodes($currentmap->name));
                $continue = false;
                $current = false;
                foreach ($maps as $map) {

                    if ($currentmap->uid == $map->uid) {
                        $continue = true;
                        $current = true;
                    }

                    if ($continue && $this->nbmaps < $this->settings_nbmapsbymatch) {
                        if ($current) {
                            $this->maniaControl->getChat()->sendChat('$<$fff$o$i>>>Next Maps$>');
                            $current = false;
                        } elseif (($this->nbmaps + $i) <= ($this->settings_nbmapsbymatch)) {
                            if ($i > 0) {
                                Logger::log("Map ".$i.": ".Formatter::stripCodes($map->name));
                                $this->maniaControl->getChat()->sendChat(
                                    '$<$fff$o$i'.$i.': '.Formatter::stripCodes($map->name).'$>'
                                );
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

    public function onCommandMatchStart(array $chatCallback, Player $player)
    {
        $authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

            return;
        }
        $this->MatchStart();
    }

    public function MatchStart()
    {
        $scriptName = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE);
        $scriptName .= '.Script.txt';

        $this->maniaControl->getChat()->sendChat("$0f0Match start with script ".$scriptName.'!');
        Logger::log("Match start with script ".$scriptName.'!');


        $this->maniaControl->getTimerManager()->registerOneTimeListening(
            $this,
            function () use (&$player) {

//                Logger::log("Check Script Name");

                $scriptName = $this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_MODE
                );
                $scriptName .= '.Script.txt';


//                Logger::log("1");


                if ($this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCH_MODE
                    ) == "Cup"
                ) {

//                    Logger::log("Cup mode");
                    $loadedSettings = array(
                        self::SETTING_MATCH_MATCH_POINTS => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS),
                        self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN),
                        self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWARMUP),
                        self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP),
                        self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF => (boolean)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF),
                        self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT),
                        self::SETTING_MATCH_MATCH_NBWINNERS => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWINNERS),
                        self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP),
                    );
                } elseif ($this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCH_MODE
                    ) == "Rounds"
                ) {
//                    Logger::log("Rounds mode");
                    $loadedSettings = array(
                        self::SETTING_MATCH_MATCH_POINTS => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTS),
                        self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN),
                        self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_NBWARMUP),
                        self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP),
                        self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF => (boolean)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF),
                        self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT),
                        self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP),
                        self::SETTING_MATCH_MATCH_NBMAPS => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_NBMAPS),
                    );
                } elseif ($this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCH_MODE
                    ) == "TimeAttack"
                ) {
//                    Logger::log("TA mode");
                    $loadedSettings = array(
                        self::SETTING_MATCH_MATCH_TIMELIMIT => (int)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_TIMELIMIT),
                        self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager(
                        )->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN),
                        self::SETTING_MATCH_MATCH_NBWARMUP => 0,
                    );

                } else {
//                    Logger::log("Other mode");
                    $loadedSettings = array();
                }

//                Logger::log("Set variables");
                $this->settings_nbplayers = (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_READY_NBPLAYERS
                );
                $this->settings_nbroundsbymap = (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_ROUNDSPERMAP
                );
                $this->settings_nbmapsbymatch = (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_NBMAPS
                );
                $this->settings_nbwinners = (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_NBWINNERS
                );

//                Logger::log("Points repartition");
                $pointsrepartition = explode(
                    ",",
                    $this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCH_POINTSREPARTITION
                    )
                );


                try {




//                    Logger::log("Begin try");
                    $this->playerswinner = array();

                    $this->livePlugin = $this->maniaControl->getPluginManager()->getPlugin(
                        self::DEFAULT_TRACKMANIALIVE_PLUGIN
                    );
                    if ($this->livePlugin) {
//                Logger::log("Call MatchStart Live");
                        $this->livePlugin->MatchStart();
                    }

//                    Logger::log("2");


                    // Script settings managed by Plugin else managed by matchsettings (name of the server.txt)
                    if (!$this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCHSETTINGS_CONF
                    )) {
                        $maplist = $this->maniaControl->getSettingManager()->getSettingValue(
                            $this,
                            self::SETTING_MATCH_MAPLIST
                        );
                        $maplist = 'MatchSettings'.DIRECTORY_SEPARATOR.$maplist;

                        //                    Logger::log("Load matchsettings");
                        $this->maniaControl->getClient()->loadMatchSettings($maplist);
//                    Logger::log("Restructure maplist");
                        $this->maniaControl->getMapManager()->restructureMapList();
                        //                    Logger::log("Load Script");
                        $this->loadScript($scriptName, $loadedSettings);
                    } else {

                        Logger::log("Conf from matchsettings file");
                        $server = $this->maniaControl->getServer()->login;
                        $maplist = 'MatchSettings'.DIRECTORY_SEPARATOR.$server.".txt";
                        Logger::log($maplist);

                        //                    Logger::log("Load Script");
                        $this->loadScript($scriptName);
                        //                    Logger::log("Load matchsettings");
                        $this->maniaControl->getClient()->loadMatchSettings($maplist);
//                    Logger::log("Restructure maplist");
                        $this->maniaControl->getMapManager()->restructureMapList();
                    }



//                    Logger::log("3");
//                    Logger::log("Shuffle maplist");
                    $shufflemaplist = (boolean)$this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_MATCH_SHUFFLEMAPS
                    );

                    if ($shufflemaplist) {
                        $this->maniaControl->getMapManager()->shuffleMapList();
                    }


//                    Logger::log("Set Points Repartition");
                    $this->maniaControl->getModeScriptEventManager()->setTrackmaniaPointsRepartition(
                        $pointsrepartition
                    );

                    $this->matchStarted = true;
                    $this->nbmaps = -1;
                    $this->nbrounds = 0;
                    $this->nbwinners = 0;

                    $this->matchSettings = $this->maniaControl->getClient()->getModeScriptSettings();

                    //                    Logger::log("Check force show opponents");
                    $forceshowopponents = (boolean)$this->maniaControl->getSettingManager()->getSettingValue(
                        $this,
                        self::SETTING_MATCH_FORCESHOWOPPONENTS
                    );


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



                    // Stock Script Settings inside variable
                    $this->scriptSettings = $this->getScriptSettings();

                    //var_dump($this->scriptSettings);

                    $script = $this->maniaControl->getClient()->getScriptName();
                    if ($script['CurrentValue'] == "<in-development>") {
                        $script['CurrentValue'] = $script['NextValue'];
                    }

                    $matchdetail = "Gamemode: ".$script['CurrentValue'].", Rules:";
                    Logger::log($matchdetail);
                    $this->maniaControl->getChat()->sendInformation($matchdetail);

                    $respawn = (!isset($this->scriptSettings['S_AllowRespawn']) || is_null($this->scriptSettings['S_AllowRespawn'])) ? '0' : $this->scriptSettings['S_AllowRespawn'];

                    if ($script['CurrentValue'] == "Cup.Script.txt") {
                        $matchdetail = "S_PointsLimit => ".$this->scriptSettings['S_PointsLimit'].", S_NbOfWinners => " .$this->scriptSettings['S_NbOfWinners'].", S_AllowRespawn => ".$respawn.
                        ",S_RoundsPerMap => ".$this->scriptSettings['S_RoundsPerMap'].", S_MapsPerMatch => " .(int)$this->maniaControl->getSettingManager()->getSettingValue($this,self::SETTING_MATCH_MATCH_NBMAPS).
                        ",S_WarmUpNb => ".$this->scriptSettings['S_WarmUpNb'].", S_FinishTimeout => " .$this->scriptSettings['S_FinishTimeout'];
                        $matchdetail = $matchdetail.", PointsRepartition: ".$this->maniaControl->getSettingManager(
                            )->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
                    }elseif ($script['CurrentValue'] == "Rounds.Script.txt") {
                        $matchdetail = "S_PointsLimit => ".$this->scriptSettings['S_PointsLimit'].", S_NbOfWinners => " .$this->scriptSettings['S_NbOfWinners'].", S_AllowRespawn => ".$respawn.
                            ",S_RoundsPerMap => ".$this->scriptSettings['S_RoundsPerMap'].", S_MapsPerMatch => " .$this->scriptSettings['S_MapsPerMatch'].
                            ",S_WarmUpNb => ".$this->scriptSettings['S_WarmUpNb'].", S_FinishTimeout => " .$this->scriptSettings['S_FinishTimeout'];
                        $matchdetail = $matchdetail.", PointsRepartition: ".$this->maniaControl->getSettingManager(
                            )->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
                    }elseif ($script['CurrentValue'] == "TimeAttack.Script.txt"){
                        $matchdetail = "S_TimeLimit => ".$this->scriptSettings['S_TimeLimit'].", S_MapsPerMatch => " .$this->maniaControl->getSettingManager()->getSettingValue(
                            $this,self::SETTING_MATCH_MATCH_NBMAPS).", S_AllowRespawn => ".$respawn
                        .",S_WarmUpDuration => ".$this->scriptSettings['S_WarmUpDuration'];
                    }

                    Logger::log($matchdetail);
                    $this->maniaControl->getChat()->sendInformation($matchdetail);

                } catch (Exception $e) {
                    $this->maniaControl->getChat()->sendError("Can not start match: ".$e->getMessage());
                }
            },
            2000
        );

        $this->maniaControl->getTimerManager()->registerOneTimeListening(
            $this,
            function () use (&$player) {
//                Logger::log("Skip map");
                $this->maniaControl->getMapManager()->getMapActions()->skipMap();
            },
            4000
        );
    }

    public function onCommandMatchRecover(array $chatCallback, Player $player)
    {
        $authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

            return;
        }
        $text = $chatCallback[1][2];
        $text = explode(" ", $text);
        $nbmaps = $text[1];
        $nbrounds = $text[2];
        $id = null;
        if ($text[3]) {
            $id = $text[3];
        }
        $this->MatchRecover($nbmaps, $nbrounds, $id);
    }


    /**
     * @param $nbmaps
     * @param $nbrounds
     * @param null $id
     */
    public function MatchRecover($nbmaps, $nbrounds, $id = null)
    {
        $scriptName = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE);
        $scriptName .= '.Script.txt';

        if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCH_MODE) == "Cup") {
            $loadedSettings = array(
                self::SETTING_MATCH_MATCH_POINTS => (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_POINTS
                ),
                self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN),
                self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_NBWARMUP
                ),
                self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP),
                self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF => (boolean)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF),
                self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT),
                self::SETTING_MATCH_MATCH_NBWINNERS => (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_NBWINNERS
                ),
                self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP),
            );
        } elseif ($this->maniaControl->getSettingManager()->getSettingValue(
                $this,
                self::SETTING_MATCH_MATCH_MODE
            ) == "Rounds"
        ) {
            $loadedSettings = array(
                self::SETTING_MATCH_MATCH_POINTS => (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_POINTS
                ),
                self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN),
                self::SETTING_MATCH_MATCH_NBWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_NBWARMUP
                ),
                self::SETTING_MATCH_MATCH_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_DURATIONWARMUP),
                self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF => (boolean)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_DISPLAYTIMEDIFF),
                self::SETTING_MATCH_MATCH_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_FINISHTIMEOUT),
                self::SETTING_MATCH_MATCH_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_ROUNDSPERMAP),
                self::SETTING_MATCH_MATCH_NBMAPS => (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_NBMAPS
                ),
            );
        } elseif ($this->maniaControl->getSettingManager()->getSettingValue(
                $this,
                self::SETTING_MATCH_MATCH_MODE
            ) == "TimeAttack"
        ) {
            $loadedSettings = array(
                self::SETTING_MATCH_MATCH_TIMELIMIT => (int)$this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_MATCH_MATCH_TIMELIMIT
                ),
                self::SETTING_MATCH_MATCH_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager(
                )->getSettingValue($this, self::SETTING_MATCH_MATCH_ALLOWREPASWN),
                self::SETTING_MATCH_MATCH_NBWARMUP => 0,
            );

        } else {
            $loadedSettings = array();
        }

        $this->settings_nbplayers = (int)$this->maniaControl->getSettingManager()->getSettingValue(
            $this,
            self::SETTING_MATCH_READY_NBPLAYERS
        );
        $this->settings_nbroundsbymap = (int)$this->maniaControl->getSettingManager()->getSettingValue(
            $this,
            self::SETTING_MATCH_MATCH_ROUNDSPERMAP
        );
        $this->settings_nbmapsbymatch = (int)$this->maniaControl->getSettingManager()->getSettingValue(
            $this,
            self::SETTING_MATCH_MATCH_NBMAPS
        );
        $this->settings_nbwinners = (int)$this->maniaControl->getSettingManager()->getSettingValue(
            $this,
            self::SETTING_MATCH_MATCH_NBWINNERS
        );

        $pointsrepartition = explode(
            ",",
            $this->maniaControl->getSettingManager()->getSettingValue(
                $this,
                self::SETTING_MATCH_MATCH_POINTSREPARTITION
            )
        );
        //var_dump($pointsrepartition);
        try {

//                Logger::log("Call MatchStart Live");

//            $this->maniaControl->getMapManager()->shuffleMapList();
            $this->loadScript($scriptName, $loadedSettings);
            $this->maniaControl->getModeScriptEventManager()->setTrackmaniaPointsRepartition($pointsrepartition);
            $this->maniaControl->getChat()->sendSuccess("Match recover with script ".$scriptName.'!');
            Logger::log("Match recover with script ".$scriptName.'!');
            $this->matchStarted = true;
            $this->nbmaps = ($nbmaps - 1);
            $this->nbrounds = ($nbrounds - 1);
            $this->matchrecover = true;
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

            $matchdetail = "Gamemode: ".$script['CurrentValue'].", Rules:";
            Logger::log($matchdetail);
            $this->maniaControl->getChat()->sendInformation($matchdetail);

            $respawn = (!isset($this->scriptSettings['S_AllowRespawn']) || is_null($this->scriptSettings['S_AllowRespawn'])) ? '0' : $this->scriptSettings['S_AllowRespawn'];

            if ($script['CurrentValue'] == "Cup.Script.txt") {
                $matchdetail = "S_PointsLimit => ".$this->scriptSettings['S_PointsLimit'].", S_NbOfWinners => " .$this->scriptSettings['S_NbOfWinners'].", S_AllowRespawn => ".$respawn.
                    ",S_RoundsPerMap => ".$this->scriptSettings['S_RoundsPerMap'].", S_MapsPerMatch => " .(int)$this->maniaControl->getSettingManager()->getSettingValue($this,self::SETTING_MATCH_MATCH_NBMAPS).
                    ",S_WarmUpNb => ".$this->scriptSettings['S_WarmUpNb'].", S_FinishTimeout => " .$this->scriptSettings['S_FinishTimeout'];
                $matchdetail = $matchdetail.", PointsRepartition: ".$this->maniaControl->getSettingManager(
                    )->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
            }elseif ($script['CurrentValue'] == "Rounds.Script.txt") {
                $matchdetail = "S_PointsLimit => ".$this->scriptSettings['S_PointsLimit'].", S_NbOfWinners => " .$this->scriptSettings['S_NbOfWinners'].", S_AllowRespawn => ".$respawn.
                    ",S_RoundsPerMap => ".$this->scriptSettings['S_RoundsPerMap'].", S_MapsPerMatch => " .$this->scriptSettings['S_MapsPerMatch'].
                    ",S_WarmUpNb => ".$this->scriptSettings['S_WarmUpNb'].", S_FinishTimeout => " .$this->scriptSettings['S_FinishTimeout'];
                $matchdetail = $matchdetail.", PointsRepartition: ".$this->maniaControl->getSettingManager(
                    )->getSettingValue($this, self::SETTING_MATCH_MATCH_POINTSREPARTITION);
            }elseif ($script['CurrentValue'] == "TimeAttack.Script.txt"){
                $matchdetail = "S_TimeLimit => ".$this->scriptSettings['S_TimeLimit'].", S_MapsPerMatch => " .$this->maniaControl->getSettingManager()->getSettingValue(
                        $this,self::SETTING_MATCH_MATCH_NBMAPS).", S_AllowRespawn => ".$respawn
                    .",S_WarmUpDuration => ".$this->scriptSettings['S_WarmUpDuration'];
            }

            Logger::log($matchdetail);
            $this->maniaControl->getChat()->sendInformation($matchdetail);

        } catch (Exception $e) {
            $this->maniaControl->getChat()->sendError("Can not recover match: ".$e->getMessage());
        }
    }

    /**
     * @param array $chatCallback
     * @param \ManiaControl\Players\Player $player
     */
    public function onCommandMatchStop(array $chatCallback, Player $player)
    {
        $authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
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
    public function handleFinishCallback(OnWayPointEventStructure $structure)
    {

        if ($this->matchStarted) {
            //Logger::log("Enter Finish");
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

    public function handlePlayerConnect(Player $player)
    {
        if ($player->isSpectator) {
            $this->playerstate[$player->login] = -1;
        } else {
            $this->playerstate[$player->login] = 0;
            $this->nbplayers++;
            $this->displayWidgets($player->login);
        }
    }

    public function handlePlayerDisconnect(Player $player)
    {
        $this->playerstate[$player->login] = -1;
        $this->nbplayers--;
        $this->closeWidget($player->login);
    }

    public function handlePlayerInfoChanged(Player $player)
    {

        $this->handlePlayerConnect($player);

        if ($this->playerstate[$player->login]) {
            $newSpecStatus = $player->isSpectator;

            if ($newSpecStatus) {
                if ($this->playerstate[$player->login] == 0 OR $this->playerstate[$player->login] == 1) {
                    $this->nbplayers--;
                }

                $this->playerstate[$player->login] = -1;
                $this->closeWidget($player->login);
            } else {
                if ($this->playerstate[$player->login] == -1) {
                    $this->nbplayers++;
                }

                $this->playerstate[$player->login] = 0;

                $this->displayWidgets($player->login);
            }
        }

    }

    private function displayWidgets($login)
    {
        if ($this->maniaControl->getSettingManager()->getSettingValue(
                $this,
                self::SETTING_MATCH_READY_MODE
            ) && (!$this->matchStarted)
        ) {
            $this->displayReadyWidget($login);
        } else {
            $this->playerstate[$login] = 0;
            $this->closeWidget($login);
        }
    }

    /**
     * Displays the Ready Widget
     *
     * @param bool $login
     */
    public function displayReadyWidget($login)
    {
        $posX = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_POSX);
        $posY = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_POSY);
        $width = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_WIDTH);
        $height = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_HEIGHT);
        $quadStyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();
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

    public function handleReady(array $callback, Player $player)
    {
        if ($this->playerstate[$player->login] == 0) {
            $this->playerstate[$player->login] = 1;
            $this->nbplayers++;
            $this->displayWidgets($player->login);
            $this->maniaControl->getChat()->sendChat("Match: ".$player->nickname.' $<$z$0f0Ready$>');
        } elseif ($this->playerstate[$player->login] == 1) {
            $this->playerstate[$player->login] = 0;
            $this->nbplayers--;
            $this->displayWidgets($player->login);
            $this->maniaControl->getChat()->sendChat("Match: ".$player->nickname.' $<$z$f00Not Ready$>');
        }
    }

    /**
     * Close Ready Widget
     *
     */
    public function closeWidget($login = null)
    {
        $this->maniaControl->getManialinkManager()->hideManialink(self::MLID_MATCH_READY_WIDGET, $login);
    }

    /**
     * Update Widgets on Setting Changes
     *
     * @param Setting $setting
     */
    public function updateSettings(Setting $setting)
    {
        if ($setting->belongsToClass($this)) {
            $players = $this->maniaControl->getPlayerManager()->getPlayers();

            foreach ($players as $player) {
                $this->displayWidgets($player->login);
            }
        }
    }
}