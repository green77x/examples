<?php

namespace app\telegram\interactions;

use app\telegram\Telega;
use app\telegram\exceptions\InvalidUpdateTypeException;
use app\telegram\models\{Subscriber, SubscribeRequest};
use Yii;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Keyboard\Keyboard;


/**
 * Сервис, занимающийся ведением диалога с пользователем.
 */
class InteractionFactory
{
	/**
	 * Создает объект Interaction подходящего типа, который уже обрабатывает сообщение или коллбек от пользователя.
	 * @return type
	 */
	public static function createInteraction(Telega $telega)
	{
		$update = $telega->update;
		$updateType = $update->detectType();

		if ($updateType == 'message') {
			$chatId = $update->message->chat->id;
		} elseif ($updateType == 'callback_query') {
			$chatId = $update->callbackQuery->message->chat->id;
		} else {
			throw new InvalidUpdateTypeException($update);
		}


		# ищем данного пользователя. Определяем, в каком он состоянии (новый, в процессе оформления подписки, подписан).
		# если есть такой подписчик:
		if ($subscriber = Subscriber::findOne($chatId)) {
			return (new SubscriberInteraction($telega, $subscriber));
		}

		# если это пользователь в процессе оформления подписки
		if ($request = SubscribeRequest::findOne($chatId)) {
			return new SubscribeRequestInteraction($telega, $request);
		}

		# если это неизвестный пользователь
		return new NewUserInteraction($telega);

	}
}
