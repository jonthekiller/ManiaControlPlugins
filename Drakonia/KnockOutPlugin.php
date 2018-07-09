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
use Maniaplanet\DedicatedServer\InvalidArgumentException;
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

class KnockOutPlugin implements ManialinkPageAnswerListener, CallbackListener, CommandListener, TimerListener, CommunicationListener, Plugin
{

    const PLUGIN_ID = 125;
    const PLUGIN_VERSION = 0.3;
    const PLUGIN_NAME = 'KnockOutPlugin';
    const PLUGIN_AUTHOR = 'jonthekiller';


    const SETTING_KNOCKOUT_ACTIVATED = 'KnockOut Plugin Activated:';
    const SETTING_KNOCKOUT_AUTHLEVEL = 'Auth level for the ko* commands:';
    const SETTING_KNOCKOUT_ROUNDSPERMAP = 'S_RoundsPerMap';
    const SETTING_KNOCKOUT_DURATIONWARMUP = 'S_WarmUpDuration';
    const SETTING_KNOCKOUT_FINISHTIMEOUT = 'S_FinishTimeout';
    const SETTING_KNOCKOUT_MAPLIST = 'Maplist to use';
    const SETTING_KNOCKOUT_SHUFFLEMAPS = 'Shuffle Maplist';
    const SETTING_KNOCKOUT_ALLOWREPASWN = 'S_AllowRespawn';
    const SETTING_KNOCKOUT_PLAYER_PASSWORD = 'Server player password during KO:';
    const SETTING_KNOCKOUT_SPECTATOR_PASSWORD = 'Server spectator password during KO:';
    const SETTING_KNOCKOUT_NBLIFES = 'Number of lives:';
    const SETTING_KNOCKOUT_POINTSREPARTITION = 'PointsRepartition';

    const MLID_KNOCKOUT_WIDGET = 'KnockoutPlugin.Widget';
    const MLID_KNOCKOUT_WIDGETTIMES = 'KnockoutPlugin.WidgetTimes';
    const SETTING_KNOCKOUT_POSX = 'KnockoutPlugin-Widget-Position: X';
    const SETTING_KNOCKOUT_POSY = 'KnockoutPlugin-Widget-Position: Y';
    const SETTING_KNOCKOUT_LINESCOUNT = 'Widget Displayed Lines Count';
    const SETTING_KNOCKOUT_WIDTH = 'KnockoutPlugin-Widget-Size: Width';
    const SETTING_KNOCKOUT_LINE_HEIGHT = 'KnockoutPlugin-Widget-Lines: Height';
    const KNOCKOUT_ACTION_SPEC = 'Spec.Action';

    const SETTINGS_KNOCKOUT_MOVE_RACE_RANKING = 'Race Ranking Default Position';
    const SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_X = 'Race Ranking Position X';
    const SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Y = 'Race Ranking Position Y';
    const SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Z = 'Race Ranking Position Z';

    /*
* Private properties
*/
    /** @var ManiaControl $maniaControl */
    private $maniaControl = null;
    private $koStarted = false;
    private $koStarted2 = false;
    private $scriptsDir = null;
    private $nbrounds = 0;
    private $alreadydone = false;
    private $nbplayers = 0;
    private $nbplayers2 = 0;
    private $players = array();
    private $nbplayersnotfinished = 0;
    private $nblifes = 1;
    private $playerslifes = array();
    private $widgetshown = false;

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
        return 'Plugin offers a KnockOut Plugin';
    }


    public function load(ManiaControl $maniaControl)
    {
        $this->maniaControl = $maniaControl;

        //Settings
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_ACTIVATED, true);

        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_ALLOWREPASWN, true);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_DURATIONWARMUP, 10);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_MAPLIST, "Drakonia_KO.txt");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_ROUNDSPERMAP, 4);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_FINISHTIMEOUT, 10);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_SHUFFLEMAPS, true);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_PLAYER_PASSWORD, "KODrakonia");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_SPECTATOR_PASSWORD, "KODrakonia");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_NBLIFES, 1);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_LINE_HEIGHT, 4);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_LINESCOUNT, 20);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_POSX, -139);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_POSY, 70);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_WIDTH, 42);

        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING, true);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_X, 100);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Y, 50);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Z, 150);

        $this->maniaControl->getSettingManager()->initSetting(
            $this,
            self::SETTING_KNOCKOUT_POINTSREPARTITION,
            "100,99,98,97,96,95,94,93,92,91,90,89,88,87,86,85,84,83,82,81,80,79,78,77,76,75,74,73,72,71,70,69,68,67,66,65,64,63,62,61,60,59,58,57,56,55,54,53,52,51,50,49,48,47,46,45,44,43,42,41,40,39,38,37,36,35,34,33,32,31,30,29,28,27,26,25,24,23,22,21,20,19,18,17,16,15,14,13,12,11,10,9,8,7,6,5,4,3,2,1"
        );

        $this->maniaControl->getSettingManager()->initSetting(
            $this,
            self::SETTING_KNOCKOUT_AUTHLEVEL,
            AuthenticationManager::AUTH_LEVEL_ADMIN
        );


        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            SettingManager::CB_SETTING_CHANGED,
            $this,
            'updateSettings'
        );


        $this->maniaControl->getCommandManager()->registerCommandListener(
            'kostart',
            $this,
            'onCommandKOStart',
            true,
            'Start a KO'
        );
        $this->maniaControl->getCommandManager()->registerCommandListener(
            'kostop',
            $this,
            'onCommandKOStop',
            true,
            'Stop a KO'
        );

        $this->maniaControl->getCommandManager()->registerCommandListener(
            'koaddlives',
            $this,
            'onCommandKOAddLives',
            true,
            'Add lives for a player [login] [lives]'
        );
        $this->maniaControl->getCommandManager()->registerCommandListener(
            'koremovelives',
            $this,
            'onCommandKORemoveLives',
            true,
            'Remove lives for a player [login] [lives]'
        );

        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            Callbacks::MP_STARTROUNDSTART,
            $this,
            'handleBeginRoundCallback'
        );
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            Callbacks::TM_SCORES,
            $this,
            'handleEndRoundCallback'
        );

        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER,
            $this,
            'handleSpec'
        );

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
                $this->scriptsDir = $scriptsDataDir . 'Modes' . DIRECTORY_SEPARATOR . $game . DIRECTORY_SEPARATOR;
                // Final assertion
                if (!$maniaControl->getServer()->checkAccess($this->scriptsDir)) {
                    $this->scriptsDir = null;
                }
            }
        }

    }


    public function onCommandKOStart(array $chatCallback, Player $player)
    {
        $authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_AUTHLEVEL);
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

            return;
        }
        $this->KOStart();
    }

    public function onCommandKOStop(array $chatCallback, Player $player)
    {
        $authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_AUTHLEVEL);
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

            return;
        }
        $this->KOStop();
    }

    public function onCommandKOAddLives(array $chatCallback, Player $player)
    {
        $authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_AUTHLEVEL);
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
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

    public function onCommandKORemoveLives(array $chatCallback, Player $player)
    {
        $authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_AUTHLEVEL);
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
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
                    $this->maniaControl->getClient()->kick($player->login);
                } catch (Exception $e) {
                    //                    var_dump($e->getMessage());
                }
            }
//            }
        }

        $this->displayKO($this->playerslifes);
    }

    public function KOStart()
    {
        $this->maniaControl->getChat()->sendChat("$0f0KO match start!");
        Logger::log("KO match start!");

        $loadedSettings = array(
            "S_PointsLimit" => 99999,
            "S_WarmUpNb" => 1,
            "S_MapsPerMatch" => 9999,
            self::SETTING_KNOCKOUT_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_ROUNDSPERMAP),
            self::SETTING_KNOCKOUT_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_ALLOWREPASWN),
            self::SETTING_KNOCKOUT_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_DURATIONWARMUP),
            self::SETTING_KNOCKOUT_FINISHTIMEOUT => (int)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_FINISHTIMEOUT),

        );

//        var_dump($loadedSettings);
        $this->nblifes = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_NBLIFES);


        $maplist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_MAPLIST);
        $maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $maplist;

        $this->maniaControl->getClient()->loadMatchSettings($maplist);
        $this->maniaControl->getMapManager()->restructureMapList();

        $shufflemaplist = (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SHUFFLEMAPS);

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


        $scriptName = 'Rounds.Script.txt';
        $this->loadScript($scriptName, $loadedSettings);
        $this->koStarted = true;
        $this->koStarted2 = true;

        $this->maniaControl->getClient()->setServerPassword($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_PLAYER_PASSWORD));
        $this->maniaControl->getClient()->setServerPasswordForSpectator($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SPECTATOR_PASSWORD));

        $pointsrepartition = explode(
            ",",
            $this->maniaControl->getSettingManager()->getSettingValue(
                $this,
                self::SETTING_KNOCKOUT_POINTSREPARTITION
            )
        );

        $this->maniaControl->getModeScriptEventManager()->setTrackmaniaPointsRepartition($pointsrepartition);

        $this->displayWidgets();

        $this->moveRaceRanking();

        $this->maniaControl->getTimerManager()->registerOneTimeListening(
            $this,
            function () use (&$player) {
//                Logger::log("Skip map");
                $this->maniaControl->getMapManager()->getMapActions()->skipMap();
            },
            2000
        );

    }

    public function KOStop()
    {

        $this->koStarted = false;

        $scriptName = 'TimeAttack.Script.txt';

        $loadedSettings = array(
            'S_TimeLimit' => 600,
            "S_WarmUpNb" => 0,
            self::SETTING_KNOCKOUT_ALLOWREPASWN => true,
            self::SETTING_KNOCKOUT_DURATIONWARMUP => 0
        );


        $this->loadScript($scriptName, $loadedSettings);
        $this->maniaControl->getChat()->sendSuccess("KO match stop");
        Logger::log("KO match stop");

        $this->maniaControl->getClient()->setServerPassword("");
        $this->maniaControl->getClient()->setServerPasswordForSpectator("");


        $this->closeWidget(self::MLID_KNOCKOUT_WIDGET);
        $this->closeWidget(self::MLID_KNOCKOUT_WIDGETTIMES);

    }

    public function handlePlayerConnect(Player $player)
    {
        if ($this->koStarted) {
            if ($player->isSpectator) {
                $this->playerslifes[$player->login] = -1;
            } else {
                $this->playerslifes[$player->login] = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_NBLIFES);
            }
            $this->displayWidgets();
        }

    }

    public function handleBeginRoundCallback()
    {
        $this->alreadydone = false;
        $this->koStarted2 = false;
    }

    /**
     * @param OnScoresStructure $structure
     */
    public function handleEndRoundCallback(OnScoresStructure $structure)
    {
        $nbplayersdel = 0;
        $this->nbplayers = 0;
        $this->nbplayers2 = 0;
        $realSection = false;
        $playerresults = array();
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
                    $login = $result->getPlayer()->login;
                    $player = $result->getPlayer();
                    if (!$player->isSpectator AND $player->isConnected) {

                        $roundpoints = $result->getRoundPoints();
                        Logger::log($login . " " . $roundpoints);


                        if ($roundpoints == 0) {
                            $nbplayersdel++;
                            $this->playerslifes[$login]--;
//                            if ($this->playerslifes[$login] > 0) {
//                                $this->playerslifes[$login]--;
//                            }
                            if ($this->playerslifes[$login] == 0) {
                                Logger::log("Player " . $result->getPlayer()->nickname . " is eliminated");

                                try {
                                    $this->maniaControl->getClient()->chatSend("Player " . $result->getPlayer()->nickname . " \$z is eliminated");
                                } catch (InvalidArgumentException $e) {
                                }
                                try {
                                    $this->maniaControl->getClient()->kick($login);
                                } catch (Exception $e) {
                                    //                    var_dump($e->getMessage());
                                }
                            } else {
                                if ($this->playerslifes[$login] < 0) {

                                } else {
                                    Logger::log("Player " . $result->getPlayer()->nickname . " has now " . $this->playerslifes[$login] . " lives");

                                    try {
                                        $this->maniaControl->getClient()->chatSend("Player " . $result->getPlayer()->nickname . " \$zhas now " . $this->playerslifes[$login] . " lives");
                                    } catch (InvalidArgumentException $e) {
                                    }
                                    $this->nbplayers2++;
                                }
                            }

                        } else {
                            $playerresults += array($login => $roundpoints);
                            $this->players[$this->nbplayers] = $login;
                            $this->nbplayers++;
                            $this->nbplayers2++;
                        }
                    }

                }


                Logger::log("Nb players who hasn't finished: ".$nbplayersdel);
                arsort($playerresults);

//                var_dump($playerresults);
                foreach ($playerresults as $key => $value) {
                    $logintokick = $key;
                }
//                $logintokick = $this->players[($this->nbplayers - 1)];

                if(count($playerresults) > 1 AND $nbplayersdel == 0) {
                    Logger::log($logintokick . " last player");

                    $this->playerslifes[$logintokick]--;
                    if ($this->playerslifes[$logintokick] == 0) {

                        $this->nbplayers--;
                        Logger::log("Player " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " is eliminated");

                        try {
                            $this->maniaControl->getClient()->chatSend("Player " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " \$z is eliminated");
                        } catch (InvalidArgumentException $e) {
                        }
                        try {
                            $this->maniaControl->getClient()->kick($logintokick);
                        } catch (Exception $e) {
                            //                    var_dump($e->getMessage());
                        }
                    } else {

                        if ($this->playerslifes[$logintokick] < 0)
                        {

                        }else {
                            Logger::log("Player " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " has now " . $this->playerslifes[$logintokick] . " lives");


                            try {
                                $this->maniaControl->getClient()->chatSend("Player " . $this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname . " \$zhas now " . $this->playerslifes[$logintokick] . " lives");
                            } catch (InvalidArgumentException $e) {
                            }
//                        $this->nbplayers2++;
                        }
                    }
                }else{
//                    $this->nbplayers2++;
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

    private function displayWidgets()
    {


        // Display KO Widget

        if ($this->koStarted) {
            if ($this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTING_KNOCKOUT_NBLIFES
                ) > 1) {
                $this->displayKnockoutWidget();
            }
        }


    }

    /**
     * Displays the Knockout Widget
     *
     * @param bool $login
     */
    public function displayKnockoutWidget($login = false)
    {
        $posX = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_POSX);
        $posY = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_POSY);
        $width = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_WIDTH);
        $lineHeight = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_LINE_HEIGHT);
        $lines = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_LINESCOUNT);

        $height = 7. + $lines * $lineHeight;
        $labelStyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultLabelStyle();
        $quadSubstyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();
        $quadStyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();


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
        $titleLabel->setText("Knockout Live");
        $titleLabel->setTranslate(true);

        $maniaLink = new ManiaLink(self::MLID_KNOCKOUT_WIDGET);
        $maniaLink->addChild($frame);
        // Send manialink
        $this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
    }


    public function displayKO($ranking)
    {

        $lines = $this->maniaControl->getSettingManager()->getSettingValue(
            $this,
            self::SETTING_KNOCKOUT_LINESCOUNT
        );
        $lineHeight = $this->maniaControl->getSettingManager()->getSettingValue(
            $this,
            self::SETTING_KNOCKOUT_LINE_HEIGHT
        );
        $posX = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_POSX);
        $posY = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_POSY);
        $width = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_WIDTH);


        $maniaLink = new ManiaLink(self::MLID_KNOCKOUT_WIDGETTIMES);
        $listFrame = new Frame();
        $maniaLink->addChild($listFrame);
        $listFrame->setPosition($posX, $posY);

        // Obtain a list of columns

        arsort($ranking);

        $rank = 1;

        foreach ($ranking as $index => $record) {
            if ($rank >= $lines OR $record < 1) {
                break;
            }

            $points = $record;


            $player = $this->maniaControl->getPlayerManager()->getPlayer($index);

            $y = -10 - ($rank - 1) * $lineHeight;

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
            $quad->setAction(self::KNOCKOUT_ACTION_SPEC . '.' . $player->login);

            $rank++;

        }

        $this->maniaControl->getManialinkManager()->sendManialink($maniaLink, false);
    }


    public function handleSpec(array $callback)
    {
        $actionId = $callback[1][2];
        $actionArray = explode('.', $actionId, 3);
        if (count($actionArray) < 2) {
            return;
        }
        $action = $actionArray[0] . '.' . $actionArray[1];

        if (count($actionArray) > 2) {

            switch ($action) {
                case self::KNOCKOUT_ACTION_SPEC:
                    $adminLogin = $callback[1][1];
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
    public function closeWidget($widgetId)
    {
        $this->maniaControl->getManialinkManager()->hideManialink($widgetId);
    }


    public function updateWidget($ranking)
    {

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
     * @param $scriptName
     * @param array $loadedSettings
     * @throws Exception
     */
    private function loadScript(&$scriptName, array $loadedSettings)
    {
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

    public function moveRaceRanking()
    {
        if ($this->maniaControl->getSettingManager()->getSettingValue(
            $this,
            self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING
        )) {
            $properties = "<ui_properties>
    <round_scores pos='-158.5 40. 150.' visible='true' />
  </ui_properties>";
            Logger::log('Put Race Ranking Widget to the original place');
            $this->maniaControl->getModeScriptEventManager()->setTrackmaniaUIProperties($properties);

        } else {
            $properties = "<ui_properties>
    <round_scores pos='" . $this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_X
                ) . " " . $this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Y
                ) . ". " . $this->maniaControl->getSettingManager()->getSettingValue(
                    $this,
                    self::SETTINGS_KNOCKOUT_MOVE_RACE_RANKING_Z
                ) . ".' visible='true' />
  </ui_properties>";
            Logger::log('Put Race Ranking Widget to custom position');
            $this->maniaControl->getModeScriptEventManager()->setTrackmaniaUIProperties($properties);

        }
    }


    /**
     * @see \ManiaControl\Plugins\Plugin::unload()
     */
    public function unload()
    {
        $this->closeWidget(self::MLID_KNOCKOUT_WIDGET);
        $this->closeWidget(self::MLID_KNOCKOUT_WIDGETTIMES);
    }

    /**
     * Update Widgets on Setting Changes
     *
     * @param Setting $setting
     */
    public function updateSettings(Setting $setting)
    {
        if ($setting->belongsToClass($this)) {
            $this->displayWidgets();

            $this->moveRaceRanking();
        }
    }

}