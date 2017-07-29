<?php

namespace Drakonia;


use FML\Controls\Frame;
use FML\Controls\Label;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Utils\Formatter;

class LocalRecordsCPLivePlugin implements CallbackListener, TimerListener, Plugin {

    /*
    * Constants
    */
    const PLUGIN_ID      = 115;
    const PLUGIN_VERSION = 0.13;
    const PLUGIN_NAME    = 'LocalRecordsCPLivePlugin';
    const PLUGIN_AUTHOR  = 'jonthekiller';


    const SETTING_LRCPLIVE_ACTIVATED = 'LocalRecordsCPLivePlugin Activated';
    const MLID_LRCPLIVE_WIDGET              = 'LocalRecordsCPLivePlugin.Widget';
    const SETTING_LRCPLIVE_WIDGET1_POSX      = 'LocalRecordsCPLivePlugin-Top1-Position: X';
    const SETTING_LRCPLIVE_WIDGET1_POSY      = 'LocalRecordsCPLivePlugin-Top1-Position: Y';
    const SETTING_LRCPLIVE_WIDGET2_POSX      = 'LocalRecordsCPLivePlugin-MyTop-Position: X';
    const SETTING_LRCPLIVE_WIDGET2_POSY      = 'LocalRecordsCPLivePlugin-MyTop-Position: Y';
    const SETTING_LRCPLIVE_TEXT_SIZE      = 'LocalRecordsCPLivePlugin-Text Size';


    const DEFAULT_LOCALRECORDS_PLUGIN = 'MCTeam\LocalRecordsPlugin';

    /** @var ManiaControl $maniaControl */
    private $maniaControl         = null;
    private $active = false;
    private $LocalRecordsPlugin = "";
    private $toprecord = array();
    private $playersrecords = array();

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
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_WIDGET1_POSX, -30);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_WIDGET1_POSY, 70);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_WIDGET2_POSX, 30);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LRCPLIVE_WIDGET2_POSY, 70);


        // Callbacks

        $this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
        $this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle2Seconds', 2000);

        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONEVENTSTARTLINE, $this, 'handleBeginRoundCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONFINISHLINE, $this, 'handleFinishCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::BEGINMAP, $this, 'handleBeginMapCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONWAYPOINT, $this, 'handleCheckpointCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERINFOCHANGED, $this, 'handlePlayerInfoChanged');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::ENDMAP, $this, 'handleEndMapCallback');


        $this->init();


        return true;

    }


    public function init()
    {

        $this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$player) {
            $this->LocalRecordsPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::DEFAULT_LOCALRECORDS_PLUGIN);

            if($this->LocalRecordsPlugin)
            {
                $this->active = true;
            }else{
                $this->maniaControl->getChat()->sendErrorToAdmins('Please activate first the LocalRecords plugin');
            }

            $players = $this->maniaControl->getPlayerManager()->getPlayers();

            foreach ($players as $player) {
                $this->initTimes($player);
            }

        }, 500);

        return true;
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

    public function handlePlayerInfoChanged(Player $player) {
        $this->closeWidget(self::MLID_LRCPLIVE_WIDGET, $player->login);
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
            $notimeplayer = false;
            if (empty($playerbesttime)) {
                $notimeplayer = true;
            }
            $notime = false;
            if (empty($toptime)) {
                $notime = true;
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

            $playergaptime = $playercptime - $playerbesttime;
            $playertopgaptime = $playercptime - $toptime;

            $notimeplayer = false;
            if ($playerbesttime == 0 ) {
                $notimeplayer = true;
            }
            $notime = false;
            if ($toptime == 0) {
                $notime = true;
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

        $playertopgaptime = Formatter::formatTime(abs($playertopgaptime));
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

        // Send manialink
        $this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $player->login);
    }

    public function handleCheckpointCallback(OnWayPointEventStructure $structure){

        if($this->active){
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
        if ($setting->belongsToClass($this)) {

        }
    }
}