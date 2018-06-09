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
 * @copyright 2017 Drakonia Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class CheckpointsLivePlugin implements ManialinkPageAnswerListener, CallbackListener, TimerListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID      = 111;
	const PLUGIN_VERSION = 0.3;
	const PLUGIN_NAME    = 'CheckpointsLivePlugin';
	const PLUGIN_AUTHOR  = 'jonthekiller';

	// CheckpointsLiveWidget Properties
	const MLID_CHECKPOINTS_LIVE_WIDGET              = 'CheckpointsLivePlugin.Widget';
	const MLID_CHECKPOINTS_LIVE_WIDGETTIMES              = 'CheckpointsLivePlugin.WidgetTimes';
	const SETTING_CHECKPOINTS_LIVE_ACTIVATED = 'CheckpointsLive-Widget Activated';
	const SETTING_CHECKPOINTS_LIVE_POSX      = 'CheckpointsLive-Widget-Position: X';
	const SETTING_CHECKPOINTS_LIVE_POSY      = 'CheckpointsLive-Widget-Position: Y';
    const SETTING_CHECKPOINTS_LIVE_LINESCOUNT    = 'Widget Displayed Lines Count';
    const SETTING_CHECKPOINTS_LIVE_WIDTH     = 'CheckpointsLive-Widget-Size: Width';
//    const SETTING_CHECKPOINTS_LIVE_HEIGHT    = 'CheckpointsLive-Widget-Size: Height';
    const SETTING_CHECKPOINTS_LIVE_LINE_HEIGHT    = 'CheckpointsLive-Widget-Lines: Height';

    const ACTION_SPEC = 'Spec.Action';


	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl         = null;
	// $ranking = array ($playerlogin, $nbCPs, $CPTime)
	private $ranking = array();
	// Gamemodes supported by the plugin
	private $gamemodes = array("Cup.Script.txt", "Rounds.Script.txt", "Team.Script.txt");
	private $script = array();
	private $active = false;

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
		return 'Display a widget to show the Checkpoints Live information for Rounds/Team/Cup mode';
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
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONFINISHLINE, $this, 'handleFinishCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_SCORES, $this, 'handleEndRoundCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTROUNDSTART, $this, 'handleBeginRoundCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_WARMUP_START, $this, 'handleBeginWarmUpCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONGIVEUP, $this, 'handlePlayerGiveUpCallback');


        $this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle10Seconds', 10000);
        $this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle1Second', 1000);

        $this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleSpec');

//        $callback = $this->maniaControl->getModeScriptEventManager()->getListOfDisabledCallbacks();
//        var_dump($callback);
        //$this->maniaControl->getModeScriptEventManager()->blockCallback("Trackmania.Event.WayPoint");
//        $callback = $this->maniaControl->getModeScriptEventManager()->getListOfDisabledCallbacks();
//        var_dump($callback);

        // Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_ACTIVATED, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_POSX, -139);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_POSY, 40);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_LINESCOUNT, 4);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_WIDTH, 42);
//        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_HEIGHT, 40);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECKPOINTS_LIVE_LINE_HEIGHT, 4);

        $script = $this->maniaControl->getClient()->getScriptName();
        $this->script = $script['CurrentValue'];
        if(in_array($this->script, $this->gamemodes )){
            $this->active = true;
        }else{
            $this->active = false;
        }

        $this->displayWidgets();

		return true;
	}

	/**
	 * Display the Widget
	 */
	private function displayWidgets() {


		// Display Checkpoints Live Widget

        if($this->active){
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
		$posX         = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_POSX);
		$posY         = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_POSY);
        $width        = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_WIDTH);
        $lines       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_LINESCOUNT);
        $lineHeight   = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_LINE_HEIGHT);
//        $height       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_HEIGHT);
        $height = 7. + $lines * $lineHeight;

        $labelStyle         = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultLabelStyle();
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
		$backgroundQuad->setSize($width, $height);
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);

        $titleLabel = new Label();
        $frame->addChild($titleLabel);
        $titleLabel->setPosition(0, $lineHeight * -0.9);
        $titleLabel->setWidth($width);
        $titleLabel->setStyle($labelStyle);
        $titleLabel->setTextSize(2);
        $titleLabel->setText("CP Live");
        $titleLabel->setTranslate(true);

        $maniaLink = new ManiaLink(self::MLID_CHECKPOINTS_LIVE_WIDGET);
        $maniaLink->addChild($frame);
		// Send manialink
		$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
	}

    /**
     * Handle ManiaControl After Init
     */
    public function handle10Seconds()
    {
        $script = $this->maniaControl->getClient()->getScriptName();
        $this->script = $script['CurrentValue'];
        if(in_array($this->script, $this->gamemodes )){
            $this->active = true;
            $this->displayWidgets();
        }else{
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
    public function handle1Second()
    {
        if($this->active){
            $this->updateWidget($this->ranking);
        }
    }

    public function updateWidget($ranking){

        if($ranking)
        {
            $this->displayTimes($ranking);
        }else{
            $this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGETTIMES);
        }

    }

    public function displayTimes($ranking){

        $lines       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_LINESCOUNT);
        $lineHeight   = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_LINE_HEIGHT);
        $posX         = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_POSX);
        $posY         = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_POSY);
        $width        = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECKPOINTS_LIVE_WIDTH);


        $maniaLink = new ManiaLink(self::MLID_CHECKPOINTS_LIVE_WIDGETTIMES);
        $frame     = new Frame();
        $maniaLink->addChild($frame);
        $frame->setPosition($posX, $posY);


        // Obtain a list of columns
        $nbCPs = array();
        $CPTime = array();
        foreach ($ranking as $key => $row) {
            $nbCPs[$key]  = $row['nbCPs'];
            $CPTime[$key] = $row['CPTime'];
        }

        // Sort the data with nbCPs descending, CPTime ascending
        array_multisort($nbCPs, SORT_DESC, $CPTime, SORT_ASC, $ranking);


        $rank = 1;
        $bestNbCPs = 0;
        $bestCPTime = 0;
        foreach ($ranking as $index => $record) {
            if ($index >= $lines) {
                break;
            }

            $time = Formatter::formatTime($record['CPTime']);

            if($rank == 1 && $record['CPTime'] != 999999999){
                $bestNbCPs = $record['nbCPs'];
                $bestCPTime = $record['CPTime'];
            }else{
                if($record['CPTime'] == 999999999) {
                    $time = "DNF";
                }elseif($bestNbCPs != $record['nbCPs']){
                    $time = "+" . ($bestNbCPs - $record['nbCPs']). " CPs";
                }else{
                    $time = "+".Formatter::formatTime($record['CPTime'] - $bestCPTime);
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
            $timeLabel->setText($time);
            $timeLabel->setTextEmboss(true);

            //Quad with Spec action
            $quad = new Quad();
            $recordFrame->addChild($quad);
            $quad->setStyles(Quad_Bgs1InRace::STYLE, Quad_Bgs1InRace::SUBSTYLE_BgCardList);
            $quad->setSize($width, $lineHeight);
            $quad->setAction(self::ACTION_SPEC . '.' . $player->login);

            $rank ++;

        }

        $this->maniaControl->getManialinkManager()->sendManialink($maniaLink, false);
    }

	public function handleBeginRoundCallback(){

	    $this->ranking = array();
        //$this->updateWidget($this->ranking);
        if(!$this->active){
            $this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGETTIMES);
            $this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGET);

        }else{
            $this->displayWidgets();
            //$this->updateWidget($this->ranking);
        }
	}

	public function handleBeginWarmUpCallback(){

	    $this->ranking = array();
        if(!$this->active){
            $this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGETTIMES);
            $this->closeWidget(self::MLID_CHECKPOINTS_LIVE_WIDGET);

        }else{
            $this->displayWidgets();
        }
	}

    public function handleEndRoundCallback(){
        $this->ranking = array();
    }

    public function handlePlayerGiveUpCallback(BasePlayerTimeStructure $structure){

        if($this->active){
            $this->PlayerGiveUpRanking($structure);
        }
    }

    public function PlayerGiveUpRanking(BasePlayerTimeStructure $structure){
        if ($this->ranking) {
            // At least one player pass a Checkpoint
            $rankexist = $this->recursive_array_search($structure->getLogin(), $this->ranking);

            if ($rankexist) {
                // At least 2nd checkpoint for the player

                // Remove old record
                foreach ($this->ranking as $key => $val) {

                    if ($val['login'] == $structure->getLogin()) {
                        unset($this->ranking[$key]);

                    }
                }
                // Add new one
                $this->ranking[] = array("login" => $structure->getLogin(), "nbCPs" => 0, "CPTime" => 999999999);
            } else {
                // First checkpoint for the player
                $this->ranking[] = array("login" => $structure->getLogin(), "nbCPs" => 0, "CPTime" => 999999999);
            }
        } else {
            //If first player arrives on first Checkpoint

            $this->ranking[] = array("login" => $structure->getLogin(), "nbCPs" => 0, "CPTime" => 999999999);
        }
    }

    function recursive_array_search($needle, $haystack, $currentKey = '') {
        foreach($haystack as $key=>$value) {
            if (is_array($value)) {
                $nextKey = $this->recursive_array_search($needle,$value, $currentKey . '[' . $key . ']');
                if ($nextKey) {
                    return $nextKey;
                }
            }
            else if($value==$needle) {
                return is_numeric($key) ? $currentKey . '[' .$key . ']' : $currentKey;
            }
        }
        return false;
    }

    public function handleFinishCallback(OnWayPointEventStructure $structure){

        if($this->active){
            $this->updateRanking($structure);
        }

    }

    public function updateRanking(OnWayPointEventStructure $structure){
        if ($this->ranking) {
            // At least one player pass a Checkpoint
            $rankexist = $this->recursive_array_search($structure->getLogin(), $this->ranking);

            if ($rankexist) {
                // At least 2nd checkpoint for the player

                // Remove old record
                foreach ($this->ranking as $key => $val) {

                    if ($val['login'] == $structure->getLogin()) {
                        unset($this->ranking[$key]);

                    }
                }
                // Add new one
                $this->ranking[] = array("login" => $structure->getLogin(), "nbCPs" => $structure->getCheckPointInRace(), "CPTime" => $structure->getRaceTime());
            } else {
                // First checkpoint for the player
                $this->ranking[] = array("login" => $structure->getLogin(), "nbCPs" => $structure->getCheckPointInRace(), "CPTime" => $structure->getRaceTime());
            }
        } else {
            //If first player arrives on first Checkpoint

            $this->ranking[] = array("login" => $structure->getLogin(), "nbCPs" => $structure->getCheckPointInRace(), "CPTime" => $structure->getRaceTime());
        }
    }

    public function handleCheckpointCallback(OnWayPointEventStructure $structure){

        Logger::log("CP");
        if($this->active){
            $this->updateRanking($structure);
        }
    }

    public function handleSpec(array $callback)
    {
        $actionId    = $callback[1][2];
        $actionArray = explode('.', $actionId, 3);
        if(count($actionArray) < 2){
            return;
        }
        $action      = $actionArray[0] . '.' . $actionArray[1];

        if (count($actionArray) > 2) {

            switch ($action) {
                case self::ACTION_SPEC:
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

        if($this->active){
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

        if($this->active){

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

        if($this->active){

            if ($this->ranking) {
                // At least one player pass a Checkpoint
                $newSpecStatus = $player->isSpectator;
                if($newSpecStatus) {
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

    }
	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->displayWidgets();
		}
	}


}
