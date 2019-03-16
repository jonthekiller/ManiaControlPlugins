<?php
/**
 * Created by PhpStorm.
 * User: Jonathan
 * Date: 11/05/2018
 * Time: 19:57
 */

namespace Drakonia;

use FML\Controls\Frame;
use FML\Controls\Labels\Label_Button;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quads\Quad_BgRaceScore2;
use FML\Controls\Quads\Quad_BgsPlayerCard;
use FML\ManiaLink;
use FML\Script\Features\Paging;
use FML\Script\Script;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\LabelLine;
use ManiaControl\Manialinks\ManialinkManager;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use Maniaplanet\DedicatedServer\InvalidArgumentException;
use Maniaplanet\DedicatedServer\Xmlrpc\NotInListException;
use Maniaplanet\DedicatedServer\Xmlrpc\UnknownPlayerException;

class ModerationSyncPlugin implements CallbackListener, TimerListener, CommandListener, Plugin
{

    /*
* Constants
*/
    const PLUGIN_ID = 130;
    const PLUGIN_VERSION = 0.4;
    const PLUGIN_NAME = 'ModerationSyncPlugin';
    const PLUGIN_AUTHOR = 'jonthekiller';

    const SETTING_MODERATIONSYNC_ACTIVATED = 'ModerationSyncPlugin Activated';
    const SETTING_MODO_AUTHLEVEL = 'Auth level for the modo* commands:';
    const SHOWN_MAIN_WINDOW           = -1;
    const MAX_PLAYERS_PER_PAGE        = 15;
    const MAX_PAGES_PER_CHUNK         = 2;
    const ACTION_PAGING_CHUNKS        = 'PlayerList.PagingChunks';
    const ACTION_UNBAN   = 'ModoList.UnbanPlayer';
    const ACTION_UNIGNORE   = 'ModoList.UnignorePlayer';

    /** @var ManiaControl $maniaControl */
    private $maniaControl = null;
    private $ignorelist = array();
    private $banlist = array();

    /**
     * @see \ManiaControl\Plugins\Plugin::prepare()
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
        return 'Plugin to synchronize the ignore and ban list between servers';
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::load()
     */
    public function load(ManiaControl $maniaControl)
    {
        $this->maniaControl = $maniaControl;

        // Settings
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MODERATIONSYNC_ACTIVATED, true);

        $this->maniaControl->getSettingManager()->initSetting(
            $this,
            self::SETTING_MODO_AUTHLEVEL,
            AuthenticationManager::AUTH_LEVEL_ADMIN
        );

        // Callbacks
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            PlayerManager::CB_PLAYERCONNECT,
            $this,
            'handlePlayerConnect'
        );

        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            CallbackManager::CB_MP_PLAYERCHAT,
            $this,
            'handlePlayerChat'
        );

        $this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleManialinkPageAnswer');

        $this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

        $this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle60Seconds', 60000);

        $this->initTables();
        $this->init();

        $this->maniaControl->getClient()->chatEnableManualRouting(true, true);


        $this->maniaControl->getCommandManager()->registerCommandListener(
            'modobanlist',
            $this,
            'onCommandModoBanList',
            true,
            'Show ban sync list'
        );

        $this->maniaControl->getCommandManager()->registerCommandListener(
            'modomutelist',
            $this,
            'onCommandModoIgnoreList',
            true,
            'Show mute sync list'
        );

        return true;

    }

    /**
     * Initialize needed database tables
     */
    private function initTables()
    {
        $mysqli = $this->maniaControl->getDatabase()->getMysqli();
        $query = "CREATE TABLE IF NOT EXISTS `drakonia_ignorelist` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`playerIndex` int(11) NOT NULL,
				`ignored` TINYINT(1) NOT NULL DEFAULT '1',
				PRIMARY KEY (`id`),
				UNIQUE KEY `player_index` (`playerIndex`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
        $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error, E_USER_ERROR);
        }

        $query = "CREATE TABLE IF NOT EXISTS `drakonia_banlist` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
				`playerIndex` int(11) NOT NULL,
				`banned` TINYINT(1) NOT NULL DEFAULT '1',
				PRIMARY KEY (`id`),
				UNIQUE KEY `player_index` (`playerIndex`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
        $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error, E_USER_ERROR);
        }
    }

    private function init()
    {

    }

    public function handle60Seconds()
    {

        $this->manageIgnoreList();
        $this->manageBanList();

    }


    public function handlePlayerChat($callback) {
        $args = $callback[1];
        $nick = $this->maniaControl->getPlayerManager()->getPlayer($args[1])->nickname;

        if (substr($args[2], 0, 1) != '/') {
            if ($args[0] != 0) {
                try {
                    $this->maniaControl->getClient()->chatForward('$z' . $args[2] . '$z', $args[1]);
                } catch (InvalidArgumentException $e) {
                    var_dump($e);
                }
            }
        }

    }

    public function handlePlayerConnect(Player $player)
    {
        // Check if force ignore
        $mysqli = $this->maniaControl->getDatabase()->getMysqli();
        // Get the ignorelist from the database
        $query = "SELECT `playerIndex` FROM `drakonia_ignorelist`
					WHERE `ignored` = 1 AND `playerIndex` = $player->index;";
//        Logger::log($query);
        $result = $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error);
            return null;
        }
        while ($ignore = $result->fetch_object()) {
            try {
                if (!$player->isMuted()) {
                    $this->maniaControl->getClient()->ignore($player);
                }
            } catch (UnknownPlayerException $e) {
                return false;
            } catch (InvalidArgumentException $e) {
            }
        }

        // Check if force ban
        $mysqli = $this->maniaControl->getDatabase()->getMysqli();
        // Get the ignorelist from the database
        $query = "SELECT `playerIndex` FROM `drakonia_banlist`
					WHERE `banned` = 1 AND `playerIndex` = $player->index;";
//        Logger::log($query);
        $result = $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error);
            return null;
        }
        while ($ignore = $result->fetch_object()) {
            try {
                $this->maniaControl->getClient()->ban($player->login);
            } catch (UnknownPlayerException $e) {
                return false;
            } catch (InvalidArgumentException $e) {
            }
        }
    }

    public function manageIgnoreList()
    {
//        Logger::log("Refresh Ignorelist");
        $mysqli = $this->maniaControl->getDatabase()->getMysqli();
        // Get the ignorelist from the database
        $query = "SELECT `playerIndex` FROM `drakonia_ignorelist`
					WHERE `ignored` = 0;";
//        Logger::log($query);
        $result = $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error);
            return null;
        }
        $unignores = array();
        while ($unignore = $result->fetch_object()) {
            array_push($unignores, $unignore);
        }
        $result->free();

        $serverignorelist = $this->maniaControl->getClient()->getIgnoreList();
        $ignorelist = array();
        foreach ($serverignorelist as $ignoredPlayers) {
            array_push($ignorelist, $ignoredPlayers->login);
        }


        // Add current list in the ignorelist

        foreach ($ignorelist as $ignoreplayer) {

            if($ignoreplayer != "") {
                $player = $this->maniaControl->getPlayerManager()->getPlayer($ignoreplayer);

                $query = "INSERT INTO `drakonia_ignorelist` (
				`playerIndex`,
				`ignored`
				) VALUES (
				{$player->index},
				1) ON DUPLICATE KEY UPDATE
				`ignored` = VALUES(`ignored`);";
//            Logger::log($query);
                $mysqli->query($query);
                if ($mysqli->error) {
                    trigger_error($mysqli->error);
                    return null;
                }
            }
        }

        // Unignore players


        foreach ($unignores as $unignoreplayer) {

            $query = "INSERT INTO `drakonia_ignorelist` (
				`playerIndex`,
				`ignored`
				) VALUES (
				{$unignoreplayer->playerIndex},
				0) ON DUPLICATE KEY UPDATE
				`ignored` = VALUES(`ignored`);";
//            Logger::log($query);
            $mysqli->query($query);
            if ($mysqli->error) {
                trigger_error($mysqli->error);
                return null;
            }

            $target = $this->maniaControl->getPlayerManager()->getPlayerByIndex($unignoreplayer->playerIndex);


            try {
                $this->maniaControl->getClient()->unIgnore($target);
            } catch (NotInListException $e) {
                return false;
            }
        }


        // Get the ignorelist from the database
        $query = "SELECT `playerIndex` FROM `drakonia_ignorelist`
					WHERE `ignored` = 1;";
//        Logger::log($query);
        $result = $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error);
            return null;
        }
        $ignores = array();
        while ($ignore = $result->fetch_object()) {
            array_push($ignores, $ignore);
        }
        $result->free();


        // Ignore players
        foreach ($ignores as $ignore) {

            $target = $this->maniaControl->getPlayerManager()->getPlayerByIndex($ignore->playerIndex);

//            Logger::log("Mute player: ".$target->login);
            if (!$target->isMuted()) {
                Logger::log("Mute player: ".$target->login);
                try {
                    $this->maniaControl->getClient()->ignore($target);
                } catch (UnknownPlayerException $e) {
                    return false;
                }
            }
        }
        return true;

    }

    public function manageBanList()
    {
//        Logger::log("Refresh Banlist");
        $mysqli = $this->maniaControl->getDatabase()->getMysqli();
        // Get the banlist from the database
        $query = "SELECT `playerIndex` FROM `drakonia_banlist`
					WHERE `banned` = 0;";
//        Logger::log($query);
        $result = $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error);
            return null;
        }
        $unbans = array();
        while ($unban = $result->fetch_object()) {
            array_push($unbans, $unban);
        }
        $result->free();

        $serverbanlist = $this->maniaControl->getClient()->getBanList();
        $banlist = array();
        foreach ($serverbanlist as $bannedPlayers) {
            array_push($banlist, $bannedPlayers->login);
        }


        // Add current list in the banlist

        foreach ($banlist as $banplayer) {

            if($banplayer != "") {
                $player = $this->maniaControl->getPlayerManager()->getPlayer($banplayer);

                $query = "INSERT INTO `drakonia_banlist` (
				`playerIndex`,
				`banned`
				) VALUES (
				{$player->index},
				1) ON DUPLICATE KEY UPDATE
				`banned` = VALUES(`banned`);";
//            Logger::log($query);
                $mysqli->query($query);
                if ($mysqli->error) {
                    trigger_error($mysqli->error);
                    return null;
                }
            }
        }

        // Unban players


        foreach ($unbans as $unbanplayer) {

            $query = "INSERT INTO `drakonia_banlist` (
				`playerIndex`,
				`banned`
				) VALUES (
				{$unbanplayer->playerIndex},
				0) ON DUPLICATE KEY UPDATE
				`banned` = VALUES(`banned`);";
//            Logger::log($query);
            $mysqli->query($query);
            if ($mysqli->error) {
                trigger_error($mysqli->error);
                return null;
            }

            $target = $this->maniaControl->getPlayerManager()->getPlayerByIndex($unbanplayer->playerIndex);


            try {
                $this->maniaControl->getClient()->unBan($target);
            } catch (NotInListException $e) {
                return false;
            }
        }


        // Get the banlist from the database
        $query = "SELECT `playerIndex` FROM `drakonia_banlist`
					WHERE `banned` = 1;";
//        Logger::log($query);
        $result = $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error);
            return null;
        }
        $bans = array();
        while ($ban = $result->fetch_object()) {
            array_push($bans, $ban);
        }
        $result->free();


        // Bans players
        foreach ($bans as $ban) {

            $target = $this->maniaControl->getPlayerManager()->getPlayerByIndex($ban->playerIndex);


            if ($target->isConnected) {
                Logger::log("Ban player: ".$target->login);
                try {
                    $this->maniaControl->getClient()->ban($target->login);
                } catch (UnknownPlayerException $e) {
                    return false;
                }
            }
        }


        return true;

    }


    public function onCommandModoBanList(array $chatCallback, Player $player)
    {
        $authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MODO_AUTHLEVEL);
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

            return;
        }
        $this->ModoBanList($player);
    }

    public function onCommandModoIgnoreList (array $chatCallback, Player $player)
    {
        $authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MODO_AUTHLEVEL);
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);

            return;
        }
        $this->ModoIgnoreList($player);
    }


    public function ModoBanList(Player $player)
    {
        $width  = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsWidth();
        $height = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsHeight();

        $mysqli = $this->maniaControl->getDatabase()->getMysqli();
        $query = "SELECT `playerIndex` FROM `drakonia_banlist`
					WHERE `banned` = 1;";
//        Logger::log($query);
        $result = $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error);
            return null;
        }
        $bans = array();
        while ($ban = $result->fetch_object()) {
            array_push($bans, $ban);
        }
        $result->free();


        //Create ManiaLink
        $maniaLink = new ManiaLink(ManialinkManager::MAIN_MLID);
        $paging    = new Paging();
        $script    = new Script();
        $script->addFeature($paging);
        $maniaLink->setScript($script);

        // Main frame
        $frame = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultListFrame($script, $paging);
        $maniaLink->addChild($frame);

        // Start offsets
        $posX = -$width / 2;
        $posY = $height / 2;

        //Predefine description Label
        $descriptionLabel = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultDescriptionLabel();
        $frame->addChild($descriptionLabel);

        // Headline
        $headFrame = new Frame();
        $frame->addChild($headFrame);
        $headFrame->setY($posY - 5);

        $labelLine = new LabelLine($headFrame);
        $labelLine->addLabelEntryText('Id', $posX + 5);
        $labelLine->addLabelEntryText('Nickname', $posX + 18);
        $labelLine->addLabelEntryText('Login', $posX + 70);
        $labelLine->addLabelEntryText('Actions', $posX + 120);
        $labelLine->render();

        $index     = 1;
        $posY      -= 10;
        $pageFrame = null;

        foreach ($bans as $ban) {

            $target = $this->maniaControl->getPlayerManager()->getPlayerByIndex($ban->playerIndex);

            if ($index % self::MAX_PLAYERS_PER_PAGE === 1) {
                $pageFrame = new Frame();
                $frame->addChild($pageFrame);

                $paging->addPageControl($pageFrame);
                $posY = $height / 2 - 10;
            }

            $playerFrame = new Frame();
            $pageFrame->addChild($playerFrame);
            $playerFrame->setY($posY);

            if ($index % 2 !== 0) {
                $lineQuad = new Quad_BgsPlayerCard();
                $playerFrame->addChild($lineQuad);
                $lineQuad->setSize($width, 4);
                $lineQuad->setSubStyle($lineQuad::SUBSTYLE_BgPlayerCardBig);
                $lineQuad->setZ(-0.1);
            }

            $labelLine = new LabelLine($playerFrame);
            $labelLine->addLabelEntryText($index, $posX + 5, 13);
            $labelLine->addLabelEntryText($target->nickname, $posX + 18, 52);
            $labelLine->addLabelEntryText($target->login, $posX + 70, 48);
            $labelLine->render();

            // Level Quad
            $rightQuad = new Quad_BgRaceScore2();
            $playerFrame->addChild($rightQuad);
            $rightQuad->setX($posX + 13);
            $rightQuad->setZ(5);
            $rightQuad->setSubStyle($rightQuad::SUBSTYLE_CupFinisher);
            $rightQuad->setSize(7, 3.5);

            $rightLabel = new Label_Text();
            $playerFrame->addChild($rightLabel);
            $rightLabel->setX($posX + 13.9);
            $rightLabel->setTextSize(0.8);
            $rightLabel->setZ(10);
            $rightLabel->setText($this->maniaControl->getAuthenticationManager()->getAuthLevelAbbreviation($target));
            $description = $this->maniaControl->getAuthenticationManager()->getAuthLevelName($target) . " " . $target->nickname;
            $rightLabel->addTooltipLabelFeature($descriptionLabel, $description);

                //Settings
                $style      = Label_Text::STYLE_TextCardSmall;
                $textColor  = 'FFF';
                $quadWidth  = 24;
                $quadHeight = 3.4;

                // Quad
                $quad = new Quad_BgsPlayerCard();
                $playerFrame->addChild($quad);
                $quad->setZ(11);
                $quad->setX($posX + 130);
                $quad->setSubStyle($quad::SUBSTYLE_BgPlayerCardBig);
                $quad->setSize($quadWidth, $quadHeight);
                $quad->setAction(self::ACTION_UNBAN . "." . $target->login);

                //Label
                $label = new Label_Button();
                $playerFrame->addChild($label);
                $label->setX($posX + 130);
                $quad->setZ(12);
                $label->setStyle($style);
                $label->setTextSize(1);
                $label->setTextColor($textColor);
                $label->setText("Unban");

            $posY -= 4;
            $index++;
        }

        // Render and display xml
        $this->maniaControl->getManialinkManager()->displayWidget($maniaLink, $player, 'ModoBanList');


    }

    public function ModoIgnoreList(Player $player)
    {
        $width  = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsWidth();
        $height = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsHeight();

        $mysqli = $this->maniaControl->getDatabase()->getMysqli();
        $query = "SELECT `playerIndex` FROM `drakonia_ignorelist`
					WHERE `ignored` = 1;";
//        Logger::log($query);
        $result = $mysqli->query($query);
        if ($mysqli->error) {
            trigger_error($mysqli->error);
            return null;
        }
        $ignores = array();
        while ($ignore = $result->fetch_object()) {
            array_push($ignores, $ignore);
        }
        $result->free();


        //Create ManiaLink
        $maniaLink = new ManiaLink(ManialinkManager::MAIN_MLID);
        $paging    = new Paging();
        $script    = new Script();
        $script->addFeature($paging);
        $maniaLink->setScript($script);

        // Main frame
        $frame = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultListFrame($script, $paging);
        $maniaLink->addChild($frame);

        // Start offsets
        $posX = -$width / 2;
        $posY = $height / 2;

        //Predefine description Label
        $descriptionLabel = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultDescriptionLabel();
        $frame->addChild($descriptionLabel);

        // Headline
        $headFrame = new Frame();
        $frame->addChild($headFrame);
        $headFrame->setY($posY - 5);

        $labelLine = new LabelLine($headFrame);
        $labelLine->addLabelEntryText('Id', $posX + 5);
        $labelLine->addLabelEntryText('Nickname', $posX + 18);
        $labelLine->addLabelEntryText('Login', $posX + 70);
        $labelLine->addLabelEntryText('Actions', $posX + 120);
        $labelLine->render();

        $index     = 1;
        $posY      -= 10;
        $pageFrame = null;

        foreach ($ignores as $ignore) {

            $target = $this->maniaControl->getPlayerManager()->getPlayerByIndex($ignore->playerIndex);

            if ($index % self::MAX_PLAYERS_PER_PAGE === 1) {
                $pageFrame = new Frame();
                $frame->addChild($pageFrame);

                $paging->addPageControl($pageFrame);
                $posY = $height / 2 - 10;
            }

            $playerFrame = new Frame();
            $pageFrame->addChild($playerFrame);
            $playerFrame->setY($posY);

            if ($index % 2 !== 0) {
                $lineQuad = new Quad_BgsPlayerCard();
                $playerFrame->addChild($lineQuad);
                $lineQuad->setSize($width, 4);
                $lineQuad->setSubStyle($lineQuad::SUBSTYLE_BgPlayerCardBig);
                $lineQuad->setZ(-0.1);
            }

            $labelLine = new LabelLine($playerFrame);
            $labelLine->addLabelEntryText($index, $posX + 5, 13);
            $labelLine->addLabelEntryText($target->nickname, $posX + 18, 52);
            $labelLine->addLabelEntryText($target->login, $posX + 70, 48);
            $labelLine->render();

            // Level Quad
            $rightQuad = new Quad_BgRaceScore2();
            $playerFrame->addChild($rightQuad);
            $rightQuad->setX($posX + 13);
            $rightQuad->setZ(5);
            $rightQuad->setSubStyle($rightQuad::SUBSTYLE_CupFinisher);
            $rightQuad->setSize(7, 3.5);

            $rightLabel = new Label_Text();
            $playerFrame->addChild($rightLabel);
            $rightLabel->setX($posX + 13.9);
            $rightLabel->setTextSize(0.8);
            $rightLabel->setZ(10);
            $rightLabel->setText($this->maniaControl->getAuthenticationManager()->getAuthLevelAbbreviation($target));
            $description = $this->maniaControl->getAuthenticationManager()->getAuthLevelName($target) . " " . $target->nickname;
            $rightLabel->addTooltipLabelFeature($descriptionLabel, $description);

            //Settings
            $style      = Label_Text::STYLE_TextCardSmall;
            $textColor  = 'FFF';
            $quadWidth  = 24;
            $quadHeight = 3.4;

            // Quad
            $quad = new Quad_BgsPlayerCard();
            $playerFrame->addChild($quad);
            $quad->setZ(11);
            $quad->setX($posX + 130);
            $quad->setSubStyle($quad::SUBSTYLE_BgPlayerCardBig);
            $quad->setSize($quadWidth, $quadHeight);
            $quad->setAction(self::ACTION_UNIGNORE . "." . $target->login);

            //Label
            $label = new Label_Button();
            $playerFrame->addChild($label);
            $label->setX($posX + 130);
            $quad->setZ(12);
            $label->setStyle($style);
            $label->setTextSize(1);
            $label->setTextColor($textColor);
            $label->setText("Unmute");

            $posY -= 4;
            $index++;
        }

        // Render and display xml
        $this->maniaControl->getManialinkManager()->displayWidget($maniaLink, $player, 'ModoIgnoreList');


    }



    /**
     * Called on ManialinkPageAnswer
     *
     * @internal
     * @param array $callback
     */
    public function handleManialinkPageAnswer(array $callback) {
        $actionId    = $callback[1][2];
        $actionArray = explode('.', $actionId, 3);
        if (count($actionArray) <= 2) {
            return;
        }

        $action      = $actionArray[0] . '.' . $actionArray[1];
        $targetLogin = $actionArray[2];

        $player = $this->maniaControl->getPlayerManager()->getPlayer($targetLogin);
        switch ($action) {
            case self::ACTION_UNBAN:
                $mysqli = $this->maniaControl->getDatabase()->getMysqli();
                $query = "UPDATE `drakonia_banlist` set `banned` = 0 where `playerIndex` = " .$player->index;
                $result = $mysqli->query($query);
                if ($mysqli->error) {
                    trigger_error($mysqli->error);
                    return null;
                }
                $this->maniaControl->getChat()->sendSuccessToAdmins("Player ". $player->getEscapedNickname() . " has been unbanned!");
                break;
            case self::ACTION_UNIGNORE:
                $mysqli = $this->maniaControl->getDatabase()->getMysqli();
                $query = "UPDATE `drakonia_ignorelist` set `ignored` = 0 where `playerIndex` = " .$player->index;
                $result = $mysqli->query($query);
                if ($mysqli->error) {
                    trigger_error($mysqli->error);
                    return null;
                }
                $this->maniaControl->getChat()->sendSuccessToAdmins("Player ". $player->getEscapedNickname() . " has been unmuted!");
                break;
        }
    }

    /**
     * Update Widgets on Setting Changes
     *
     * @param Setting $setting
     */
    public function updateSettings(Setting $setting)
    {
        if ($setting->belongsToClass($this)) {
            $this->init();
        }
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::unload()
     */
    public function unload()
    {
        $this->maniaControl->getClient()->chatEnableManualRouting(false);
    }
}
