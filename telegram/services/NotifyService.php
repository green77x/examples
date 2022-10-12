<?php 

namespace app\telegram\services;

use app\efrsb\models\Lot;


class NotifyService 
{
	
	/**
	 * Уведомляет подписчиков о лоте.
	 * Подразумевается, что этот метод будет запускаться по новым лотам. Но вообще его можно использовать по любым.
	 * 
	 * В текущем виде данный метод не учитывает рабочие часы подписчиков, а уведомляет их прямо сейчас.
	 * @param Lot $lot 
	 * @return type
	 */
	public static function notifySubscribersAboutLot(Lot $lot)
	{
		$subscribers = self::getSubscribersByLot($lot);
		foreach ($subscribers as $subscriber) {
			self::sendTelegramNotificationAboutLot($subscriber, $lot);
		}
	}	


	/**
	 * Возвращает всех подписчиков, которым подходит этот лот. 
	 * Пока что без учета рабочего времени.
	 * @param Lot $lot 
	 * @return type
	 */
	private static function getSubscribersByLot(Lot $lot)
	{

	}


	/**
	 * Отправляет уведомление данному подписчику по указанному лоту.
	 * @param Subscriber $subscriber 
	 * @param Lot $lot 
	 * @return type
	 */
	private static function sendTelegramNotificationAboutLot(Subscriber $subscriber, Lot $lot)
	{
		Yii::$app->telega->sendMessage($subscriber->chatId, "Новый лот $lot->id.");
	}
}