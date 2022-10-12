<?php

namespace app\telegram\objects;

use Telegram\Bot\Keyboard\Keyboard;


/**
 * Символизирует ответ, который мы хотим послать пользователю.
 *
 * Состоит из опциональьных текста и клавиатуры
 */
class MessageData
{

	public $text;
	public $keyboard;


	public function __construct(string $text, ?Keyboard $keyboard = null)
	{
		$this->text = $text;
		$this->keyboard = $keyboard;
	}
}
