<?php
/**
 * Created by PhpStorm.
 * User: Jonathan
 * Date: 11/05/2018
 * Time: 19:57
 */

namespace Drakonia;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use Maniaplanet\DedicatedServer\InvalidArgumentException;
use Maniaplanet\DedicatedServer\Xmlrpc\AlreadyInListException;
use Maniaplanet\DedicatedServer\Xmlrpc\NotInListException;
use Maniaplanet\DedicatedServer\Xmlrpc\UnknownPlayerException;

class ModerationSyncPlugin implements CallbackListener, TimerListener, Plugin
{

    /*
* Constants
*/
    const PLUGIN_ID = 130;
    const PLUGIN_VERSION = 0.2;
    const PLUGIN_NAME = 'ModerationSyncPlugin';
    const PLUGIN_AUTHOR = 'jonthekiller';

    const SETTING_MODERATIONSYNC_ACTIVATED = 'ModerationSyncPlugin Activated';

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

        // Callbacks
        $this->maniaControl->getCallbackManager()->registerCallbackListener(
            PlayerManager::CB_PLAYERCONNECT,
            $this,
            'handlePlayerConnect'
        );
        $this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

        //$this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle60Seconds', 60000);

        $this->initTables();
        $this->init();

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
        Logger::log("Refresh Ignorelist");
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
        Logger::log("Refresh Banlist");
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
    }
}