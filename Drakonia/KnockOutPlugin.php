<?php


namespace Drakonia;


use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
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
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

class KnockOutPlugin implements ManialinkPageAnswerListener, CallbackListener, CommandListener, TimerListener, CommunicationListener, Plugin
{

    const PLUGIN_ID = 125;
    const PLUGIN_VERSION = 0.1;
    const PLUGIN_NAME = 'KnockOutPlugin';
    const PLUGIN_AUTHOR = 'jonthekiller';


    const SETTING_KNOCKOUT_ACTIVATED = 'KnockOut Plugin Activated:';
    const SETTING_KNOCKOUT_AUTHLEVEL = 'Auth level for the ko* commands:';
    const SETTING_KNOCKOUT_ROUNDSPERMAP = 'S_RoundsPerMap';
    const SETTING_KNOCKOUT_DURATIONWARMUP = 'S_WarmUpDuration';
    const SETTING_KNOCKOUT_MAPLIST = 'Maplist to use';
    const SETTING_KNOCKOUT_SHUFFLEMAPS = 'Shuffle Maplist';
    const SETTING_KNOCKOUT_ALLOWREPASWN = 'S_AllowRespawn';
    const SETTING_KNOCKOUT_PASSWORD = 'Server password during KO:';

    /*
* Private properties
*/
    /** @var ManiaControl $maniaControl */
    private $maniaControl = null;
    private $koStarted = false;
    private $scriptsDir = null;
    private $nbrounds = 0;
    private $alreadydone = false;
    private $nbplayers = 0;
    private $players = array();
    private $nbplayersnotfinished = 0;

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
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_SHUFFLEMAPS, true);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_KNOCKOUT_PASSWORD, "KODrakonia");

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


    public function KOStart()
    {
        $this->maniaControl->getChat()->sendChat("$0f0KO match start!");
        Logger::log("KO match start!");

        $loadedSettings = array(
            "S_PointsLimit" => 99999,
            self::SETTING_KNOCKOUT_ROUNDSPERMAP => (int)$this->maniaControl->getSettingManager(
            )->getSettingValue($this, self::SETTING_KNOCKOUT_ROUNDSPERMAP),
            self::SETTING_KNOCKOUT_ALLOWREPASWN => (boolean)$this->maniaControl->getSettingManager(
            )->getSettingValue($this, self::SETTING_KNOCKOUT_ALLOWREPASWN),
            self::SETTING_KNOCKOUT_DURATIONWARMUP => (int)$this->maniaControl->getSettingManager(
            )->getSettingValue($this, self::SETTING_KNOCKOUT_DURATIONWARMUP),

        );

        $maplist = $this->maniaControl->getSettingManager()->getSettingValue($this,self::SETTING_KNOCKOUT_MAPLIST);
        $maplist = 'MatchSettings'.DIRECTORY_SEPARATOR.$maplist;

        $this->maniaControl->getClient()->loadMatchSettings($maplist);
        $this->maniaControl->getMapManager()->restructureMapList();

        $shufflemaplist = (boolean)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_SHUFFLEMAPS);

        if($shufflemaplist) {
            $this->maniaControl->getMapManager()->shuffleMapList();
        }


        $scriptName = 'Rounds.Script.txt';
        $this->loadScript($scriptName, $loadedSettings);
        $this->koStarted = true;

        $this->maniaControl->getClient()->setServerPassword($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_PASSWORD));
        $this->maniaControl->getClient()->setServerPasswordForSpectator($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_KNOCKOUT_PASSWORD));

        $this->maniaControl->getTimerManager()->registerOneTimeListening(
            $this,
            function () use (&$player) {
                Logger::log("Skip map");
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
            self::SETTING_KNOCKOUT_ALLOWREPASWN => true,
            self::SETTING_KNOCKOUT_DURATIONWARMUP => 0
        );


            $this->loadScript($scriptName, $loadedSettings);
            $this->maniaControl->getChat()->sendSuccess("KO match stop");
            Logger::log("KO match stop");

            $this->maniaControl->getClient()->setServerPassword("");
            $this->maniaControl->getClient()->setServerPasswordForSpectator("");

    }

    public function handleBeginRoundCallback()
    {
        $this->alreadydone = false;

    }

    /**
     * @param OnScoresStructure $structure
     */
    public function handleEndRoundCallback(OnScoresStructure $structure)
    {


        $this->nbplayers = 0;
        $this->nbplayersnotfinished =0;
        $realSection = false;
        if ($this->koStarted) {
            if ($structure->getSection() == "PreEndRound") {
                $realSection = true;
            }

        }

        if ($realSection) {


            if ($this->alreadydone === false) {


                $results = $structure->getPlayerScores();
                foreach ($results as $result) {
                    $login = $result->getPlayer()->login;
                    $player = $result->getPlayer();
                    if (!$player->isSpectator)  {

                            $roundpoints = $result->getRoundPoints();
                            Logger::log($login." ".$roundpoints);

                            if($roundpoints == 0)
                            {
                                Logger::log("Player ".$result->getPlayer()->nickname." is eliminated");
//                                $this->maniaControl->getClient()->chatSend("Player ".$result->getPlayer()->nickname." $z is eliminated");
                                try {
                                    $this->maniaControl->getClient()->kick($login);
                                } catch (Exception $e) {
//                                    var_dump($e->getMessage());
                                }
                                $this->nbplayersnotfinished++;
                            }

                        $this->players[$this->nbplayers] = $login;
                        $this->nbplayers++;
                    }

                }


                $logintokick = $this->players[($this->nbplayers - $this->nbplayersnotfinished - 1)];

                Logger::log("Player ".$this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname." is eliminated");
//                $this->maniaControl->getClient()->chatSend("Player ".$this->maniaControl->getPlayerManager()->getPlayer($logintokick)->nickname." $\z is eliminated");
                try {
                    $this->maniaControl->getClient()->kick($logintokick);
                } catch (Exception $e) {
//                    var_dump($e->getMessage());
                }

                    $this->nbrounds++;

                $this->alreadydone = true;

                if(($this->nbplayers - $this->nbplayersnotfinished - 1) <= 1)
                {
                    $this->KOStop();
                }

            }


        }

        return true;
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
            $this->maniaControl->getClient()->setModeScriptSettings($loadedSettings);
        } else {
            throw new Exception('Scripts directory not found ('.$this->scriptsDir.').');
        }
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::unload()
     */
    public function unload()
    {

    }

    /**
     * Update Widgets on Setting Changes
     *
     * @param Setting $setting
     */
    public function updateSettings(Setting $setting)
    {

    }

}