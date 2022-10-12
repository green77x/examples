<?php

namespace app\telegram\interactions;

use app\telegram\exceptions\InvalidUpdateTypeException;
use app\telegram\models\{Subscriber, SubscribeRequest};
use Yii;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Keyboard\Keyboard;


/**
 * Фабрика для создания объекта Interaction.
 */
final class InteractionFactory
{

	const _0_START = '/start';

	const _1_YES = '1-yes';
	const _1_NO = '1-no';


	/**
	 * Создает объект Interaction подходящего типа, который уже обрабатывает сообщение или коллбек от пользователя.
	 * @return type
	 */
	public static function createInteraction(Telega $telega): InteractionInterface
	{

		$update = $telega->update;
		$updateType = $update->getectType();

		if ($updateType == 'message') {
			$chatId = $update->message->chat->id;
		} elseif ($update->detectType() == 'callback_query') {
			$chatId = $this->update->callbackQuery->message->chat->id;
		} else {
			throw new InvalidUpdateTypeException($update);
		}


		# ищем данного пользователя. Определяем, в каком он состоянии (новый, в процессе оформления подписки, подписан).
		if ($subscriber = Subscriber::findOne($chatId)) {

			return new SubscriberInteraction($telega, $subscriber);
		}


		# если это пользователь в процессе оформления подписки
		if ($request = SubscribeRequest::findOne($chatId)) {

			return new SubscribeRequestInteraction($telega, $request);
		}

		# если это новый, неизвестный нам пользователь
		return new NewUserInteraction($telega);
	}
}
