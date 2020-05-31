<?php

namespace Drakonia;

use FML\Controls\Frame;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;


/**
 * Message Widget Plugin
 *
 * @author    jonthekiller
 * @copyright 2020 Drakonia Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MessageWidgetPlugin implements CallbackListener, TimerListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID      = 108;
	const PLUGIN_VERSION = 1.52;
	const PLUGIN_NAME    = 'MessageWidgetPlugin';
	const PLUGIN_AUTHOR  = 'jonthekiller';

	// MapWidget Properties
	const MLID_MESSAGE_WIDGET              = 'MessageWidgetPlugin.Widget';
	const SETTING_MESSAGE_WIDGET_ACTIVATED = 'Message-Widget Activated';
	const SETTING_MESSAGE_WIDGET_POSX      = 'Message-Widget-Position: X';
	const SETTING_MESSAGE_WIDGET_POSY      = 'Message-Widget-Position: Y';
	const SETTING_MESSAGE_WIDGET_WIDTH     = 'Message-Widget-Size: Width';
	const SETTING_MESSAGE_WIDGET_HEIGHT    = 'Message-Widget-Size: Height';
	const SETTING_MESSAGE_WIDGET_MESSAGE   = 'Message-Widget-Message:';
	const SETTING_MESSAGE_WIDGET_POSITIONX = 'Message-Widget-Text Position: X';
	const SETTING_MESSAGE_WIDGET_POSITIONY = 'Message-Widget-Text Position: Y';
	const SETTING_MESSAGE_WIDGET_POSITIONZ = 'Message-Widget-Text Position: Z';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl         = null;
	private $lastWidgetUpdateTime = 0;

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
		return 'Plugin offers a simple Message Widget';
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MESSAGE_WIDGET_ACTIVATED, true, "Activate the widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MESSAGE_WIDGET_POSX, 125, "Position of the widget (on X axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MESSAGE_WIDGET_POSY, 69, "Position of the widget (on Y axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MESSAGE_WIDGET_WIDTH, 70, "Width of the widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MESSAGE_WIDGET_HEIGHT, 15, "Height of the widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MESSAGE_WIDGET_POSITIONX, 0, "Position of the message (on X axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MESSAGE_WIDGET_POSITIONY, 6, "Position of the message (on Y axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MESSAGE_WIDGET_POSITIONZ, 0.2, "Position of the message (on Z axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MESSAGE_WIDGET_MESSAGE, "Here is your custom message", "Message to display");

		$this->displayWidgets();

		return true;
	}

	/**
	 * Display the Widgets
	 */
	private function displayWidgets() {
		// Display Message Widget
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MESSAGE_WIDGET_ACTIVATED)) {
			$this->displayMessageWidget();
		}

	}

	/**
	 * Displays the Message Widget
	 *
	 * @param bool $login
	 */
	public function displayMessageWidget($login = false) {
		$posX         = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MESSAGE_WIDGET_POSX);
		$posY         = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MESSAGE_WIDGET_POSY);
		$width        = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MESSAGE_WIDGET_WIDTH);
		$height       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MESSAGE_WIDGET_HEIGHT);
		$message      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MESSAGE_WIDGET_MESSAGE);
		$positionx    = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MESSAGE_WIDGET_POSITIONX);
		$positiony    = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MESSAGE_WIDGET_POSITIONY);
		$positionz    = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MESSAGE_WIDGET_POSITIONZ);
		$quadStyle    = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();
		$quadSubstyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();

		$maniaLink = new ManiaLink(self::MLID_MESSAGE_WIDGET);

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

		$label = new Label_Text();
		$frame->addChild($label);
		$label->setPosition($positionx, $positiony, $positionz);
		$label->setVerticalAlign($label::TOP);
		$label->setTextSize(1);
		$label->setTextColor('fff');
		$label->setText($message);

		// Send manialink
		$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
	}


	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {

		$this->closeWidget(self::MLID_MESSAGE_WIDGET);

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
	 * Handle PlayerConnect callback
	 *
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		// Display Message Widget

		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MESSAGE_WIDGET_ACTIVATED)) {
			$this->displayMessageWidget($player->login);
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
