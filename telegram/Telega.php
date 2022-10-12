<?php

namespace app\telegram;

use app\efrsb\models\Lot;
use app\services\Log;
use app\telegram\models\{Subscriber, SubscriberRegionXref, SubscriberCategoryXref};
use app\telegram\services\CategoryService;
use yii\helpers\{ArrayHelper, Html, Url};
use Yii;

use Telegram\Bot\{
	Api,
	Keyboard\Keyboard,
	Objects\Message,
	Objects\Update,
	Objects\CallbackQuery,
	Exceptions\TelegramResponseException
};


final class Telega extends \yii\base\BaseObject
{

	public $token = '';		# токен бота
	public $proxy = '';		# прокси для связи с Телегой

	public $api;


	public function init()
	{
		Yii::info('telegram init', __METHOD__);

		if (!empty($this->proxy)) {
			$client = new \Telegram\Bot\HttpClients\GuzzleHttpClient(new \GuzzleHttp\Client(['proxy' => $this->proxy]));
			$this->api = new Api($this->token, false, $client);
		} else {
			$this->api = new Api($this->token);
		}

	}


	/**
	 * "Пустой" ответ на callback query.
	 *
	 * Зачем это нужно - по документации Телеграма на любую callback query нужно отвечать, хотя бы пустым сообщением.
	 * Этот метод и реализует такой "пустой" ответ.
	 *
	 * В будущем можно будет этот метод переработать так, чтобы он принимал необязательные данные для ответа. Тогда
	 * ответ будет не пустым.
	 *
	 * @see https://core.telegram.org/bots/api#answercallbackquery
	 * @param type $queryId
	 * @return type
	 */
	public function answerCallbackQuery(CallbackQuery $query, ?string $text = null)
	{
		$options = [
			'callback_query_id' => $query->id,
		];

		if ($text) {
			$options['text'] = $text;
		}

		$this->api->answerCallbackQuery($options);
	}


	/**
	 * Отправляет сообщение на указанный диалог $chatId с указанным текстом $text.
	 *
	 * @param int $chatId
	 * @param string $text
	 * @return type
	 */
	public function sendMessage(int $chatId, string $text, $keyboard = null)
	{
		$options = [
			'chat_id' => $chatId,
			'text' => $text,
			// 'reply_markup' => $keyboard,
			'parse_mode' => 'HTML',
		];

		if (!is_null($keyboard)) {
			$options['reply_markup'] = $keyboard;
		}

		return $this->api->sendMessage($options);
	}


	/**
	 * Кастомная функция.
	 *
	 * Отвечает на сообщение $message - текстом $text и клавиатурой $keyboard
	 * @param Message $message
	 * @param string $text
	 * @param type|null $keyboard
	 * @return type
	 */
	public function answerMessage(Message $message, string $text, $keyboard = null)
	{
		$this->sendMessage($message->chat->id, $text, $keyboard);
	}


	/**
	 * Правит сообщение.
	 * @param int $chatId
	 * @param int $messageId
	 * @param string $text
	 * @param type|null $keyboard
	 * @return type
	 */
	public function editMessageText(int $chatId, int $messageId, string $text, $keyboard = null)
	{
		$options = [
			'chat_id' => $chatId,
			'message_id' => $messageId,
			'text' => $text,
		];

		if ($keyboard) {
			$options['reply_markup'] = $keyboard;
		}

		try {
			$this->api->editMessageText($options);
		} catch (TelegramResponseException $e) {
			if ($e->getMessage() == 'Bad Request: message is not modified') {
				# всё ок, это бывает, если пользователь нажимает одну кнопку много раз
			} else {
				throw $e;
			}
		}
	}


	/**
	 * Правит сообщение. Это не метод АПИ, а моя обертка над ним.
	 * @param Message $message
	 * @param string $text
	 * @param type|null $keyboard
	 * @return type
	 */
	public function editMessage(Message $message, string $text, $keyboard = null)
	{
		$this->editMessageText($message->chat->id, $message->messageId, $text, $keyboard);
	}


	/**
	 * Правит клавиатуру в сообщении
	 * @param Message $message
	 * @param type $keyboard
	 * @return type
	 */
	public function editMessageKeyboard(Message $message, $keyboard)
	{
		try {
			$this->api->editMessageReplyMarkup([
				'chat_id' => $message->chat->id,
				'message_id' => $message->messageId,
				'reply_markup' => $keyboard,
			]);
		} catch (TelegramResponseException $e) {
			if ($e->getMessage() == 'Bad Request: message is not modified') {
				# всё ок, это бывает, если пользователь нажимает одну кнопку много раз
			} else {
				throw $e;
			}
		}

	}


	/**
	 * Удаляет клавиатуру у заданного чата и сообщения
	 * @param type $chatId
	 * @param type $messageId
	 * @return type
	 */
	public function removeKeyboard($chatId, $messageId)
	{
		$this->api->editMessageReplyMarkup([
			'chat_id' => $chatId,
			'message_id' => $messageId,
		]);
	}


	/**
	 * Устанавливает вебхук по моему боту. Протестировал, работает.
	 *
	 * @param string $ngrokUrl с https
	 * @return type
	 */
	public function setWebhook(string $url)
	{
		return $this->api->setWebhook(['url' => "$url/bot29012019/index"]);
	}


	/**
	 * Возвращает инфу по установленному вебхуку
	 * @return type
	 */
	public function getWebhookInfo()
	{
		return $this->api->getWebhookInfo();
	}


	/**
	 * Выбирает подписчиков, которым подходит этот лот, и оповещает их.
	 *
	 * @param Lot $lot
	 * @return type
	 */
	public function notifySubscribersAboutLot(Lot $lot)
	{
		echo 'Ищем подписчиков по лоту ' . $lot->id . PHP_EOL;
		echo 'Категория лота: ' . $lot->classificationId . ', регион: ' . $lot->trade->regionCode . PHP_EOL;

		$subIds = $this->getSubscriberIdsByLot($lot);

		# логируем факт оповещения таких-то подписчиков
		Log::logTelegramNotifyAboutLot($lot->id, $subIds);

		# если есть подписчики, которым этот лот подходит, формируем описание лота и отправляем его всем им
		if (count($subIds) > 0) {
			echo 'Есть подписчики: ' . implode('; ', $subIds);
			$description = $this->generateLotDescription($lot);

			foreach ($subIds as $subId) {
				$this->sendMessage($subId, $description);
			}
		}

		return $subIds;
	}


	/**
	 * Оповещает заданного подписчика о заданном лоте.
	 * В этом методе никаких проверок на то, удовлетворяет ли данный лот условиям подписчика, нет.
	 * Эта логика должна быть реализована в вышестоящем методе. Поэтому вызывать данный метод напрямую из контроллера
	 * не стоит. Я сделал этот метод публичным в основном для тестирования, а так он скорее должен быть private.
	 *
	 * @param int $chatId
	 * @param Lot $lot
	 * @return type
	 */
	public function notifyChatAboutLot(int $chatId, Lot $lot)
	{
		$url = Url::to(['page/index', 'id' => $lot->id], true);
		Yii::trace('Url: ' . $url, __METHOD__);

		$keyboard = Keyboard::make()->inline()
			->row(
				Keyboard::inlineButton([
					'text' => 'Тоже ссылка на лот',
					'url' => $url,
				])
			);

		$this->api->sendMessage([
			'chat_id' => $chatId,
			'text' => $this->generateLotDescription($lot),
			'reply_markup' => $keyboard,
			// 'parse_mode' => 'HTML',
		]);
	}


	/**
	 * Возвращает полученное от пользователя сообщение.
	 * @return type
	 */
	public function getMessage(): Message
	{
		return $this->api->getWebhookUpdate()->message;
	}


	/**
	 * Возвращает объект типа Update
	 * @return type
	 */
	public function getUpdate(): Update
	{
		return $this->api->getWebhookUpdate();
	}


	/**
	 * Возвращает идентификатор чата по текущему сообщению
	 * @return type
	 */
	public function getChatId()
	{
		return $this->api->getWebhookUpdate()->getMessage()->getChat()->getId();
	}


	/**
	 * Формирует описание лота для его отправки подписчику.
	 * @param Lot $lot
	 * @return type
	 */
	private function generateLotDescription(Lot $lot): string
	{
		/*return "Лот $lot->id." . PHP_EOL .
			'Категория ' . $lot->classificationId . ', регион ' . $lot->trade->regionCode . PHP_EOL .
			"Начальная цена $lot->startPrice, текущая цена $lot->actualPrice." . PHP_EOL .
			substr($lot->tradeObjectHtml, 0, 300);*/

		$trade = $lot->trade;
		$text[] = '⚡️ Лот ' . $lot->id . ' (' . $trade->auctionTypeText . ')' . PHP_EOL . PHP_EOL;
		$text[] = 'Предмет торгов: ' . PHP_EOL . mb_substr($lot->tradeObjectHtml, 0, 500) . PHP_EOL . PHP_EOL;
		$text[] = 'Категория: ' . $lot->classificationText . PHP_EOL;
		$text[] = 'Регион: ' . $lot->regionText . PHP_EOL;
		$text[] = 'Начальная цена: ' . $lot->startPrice . ' руб.' . PHP_EOL;
		$text[] = 'Прием заявок: с ' . $trade->applicationTimeBegin . ' по ' . $trade->applicationTimeEnd . PHP_EOL . PHP_EOL;
		$text[] = 'Ссылка на 👉🏻 лот: ' . Url::to(['page/index', 'id' => $lot->id], true) . PHP_EOL . PHP_EOL;
		$text[] = '📋Чтобы узнать, как выгодно купить квартиру или другое имущество - записывайтесь на наш 👉🏻 тренинг, который мы проведём в прямом эфире уже сегодня! 😉' . PHP_EOL . PHP_EOL;
		$text[] = 'Получить случайный лот: /random' . PHP_EOL;
		$text[] = 'Открыть меню настроек: /settings' . PHP_EOL . PHP_EOL;
		// $text[] = '#ЛотыНаркоты' . PHP_EOL;

		return implode('', $text);

	}


	/**
	 * Возвращает всех подписчиков, которым этот лот подходит.
	 * В дальнейшем сюда можно вставить какую-нибудь обработку рабочего времени, например, чтобы возвращал два массива -
	 * первый с теми, кому этот лот может отправить сейчас, а второй с теми, кому его надо отправить потом.
	 *
	 * @param Lot $lot
	 * @return type
	 */
	private function getSubscriberIdsByLot(Lot $lot)
	{
		# регион
		$region = $lot->trade->regionCode;

		$category = CategoryService::getTgCategoryByEfrsbCategory($lot->classificationId);

		# если у нас такая категория ЕФРСБ не установлена, возвращаем пустой массив
		if (!$category) {
			echo 'Неизвестная категория ' . $lot->classificationId . PHP_EOL;
			return [];
		}

		echo 'Категория ' . $category . PHP_EOL;

		$startPrice = $lot->startPrice;

		# итак, по каким критериям мы фильтруем подписчиков. Это регион, категория, начальная цена.
		$rows = (new \yii\db\Query)
			->select(['s.chatId'])

			->from(Subscriber::tableName() . ' s')
			->innerJoin(SubscriberRegionXref::tableName() . ' srx', 'srx.chatId = s.chatId')
			->innerJoin(SubscriberCategoryXref::tableName() . ' scx', 'scx.chatId = s.chatId')

			->andWhere([
				'or',
				['<=', 's.minStartPrice', $startPrice],	# либо минимальная цена, нужная подписчику, меньше цены данного лота
				['is', 's.minStartPrice', null], 		# либо подписчик ее не устанавливал
			])
			->andWhere([
				'or',
				['>=', 's.maxStartPrice', $startPrice],
				['is', 's.maxStartPrice', null],
			])
			->andWhere(['srx.regionCode' => $region])
			->andWhere(['scx.categoryCode' => $category])
			->all();

		$ids = ArrayHelper::map($rows, 'chatId', 'chatId');
		// var_dump($ids);
		// Log::log(implode(', ', $ids));
		return $ids;
	}
}
