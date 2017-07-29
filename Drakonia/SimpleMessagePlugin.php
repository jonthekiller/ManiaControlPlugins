<?php


namespace Drakonia;


use cURL\Exception;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;

class SimpleMessagePlugin implements CallbackListener, Plugin
{

    /*
    * Constants
    */
    const PLUGIN_ID = 999;
    const PLUGIN_VERSION = 0.1;
    const PLUGIN_NAME = 'SimpleMessagePlugin';
    const PLUGIN_AUTHOR = 'jonthekiller';


    const SETTING_SIMPLEMESSAGE_ACTIVATED = 'SimpleMessagePlugin Activated';
    const SETTING_SIMPLEMESSAGE_MESSAGE = 'SimpleMessage';
    const SETTING_SIMPLEMESSAGE_MESSAGE2 = 'SimpleMessage2';

    /** @var ManiaControl $maniaControl */
    private $maniaControl = null;
    private $firstmessage = false;
    private $message = "";
    private $tempmessage = "";
    private $number = 0;
    private $countuser = "http://zrt-subscription.drakonia.eu:8003/count-user";

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
        return 'Plugin to display simple message at each map';
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::load()
     */
    public function load(ManiaControl $maniaControl)
    {
        $this->maniaControl = $maniaControl;

        // Settings
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SIMPLEMESSAGE_ACTIVATED, true);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SIMPLEMESSAGE_MESSAGE, 'Don\'t forget to register to the ZrT Cup 2017 on $lhttps://zerator.com$z');
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SIMPLEMESSAGE_MESSAGE2, '$o$0f0<number> $zplayers already registered. And you?');

        // Callbacks
        $this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::BEGINMAP, $this, 'handleBeginMapCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::ENDMAP, $this, 'handleEndMapCallback');


        $this->initMessage();

        return true;

    }

    public function initMessage()
    {
        $this->tempmessage = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SIMPLEMESSAGE_MESSAGE2);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->countuser);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json')); // Assuming you're requesting JSON
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        $data = json_decode($response);

        $this->message = str_replace("<number>", $data->count, $this->tempmessage);
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::unload()
     */
    public function unload()
    {
    }

    public function handleBeginMapCallback()
    {
        if ($this->firstmessage === true) {

            $preTxt = '$FF0» $0FFServer info: $fff';
            $this->maniaControl->getChat()->sendInformation($preTxt . (string)$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SIMPLEMESSAGE_MESSAGE), null, false);
            $this->maniaControl->getChat()->sendInformation((string)$this->message, null, false);
            $this->firstmessage = false;
        }
    }

    public function handleEndMapCallback()
    {
        $this->firstmessage = true;
        $this->initMessage();
    }

    /**
     * Update Widgets on Setting Changes
     *
     * @param Setting $setting
     */
    public function updateSettings(Setting $setting)
    {
        if ($setting->belongsToClass($this)) {
            $this->initMessage();
        }
    }

}