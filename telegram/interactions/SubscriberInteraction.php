<?php

namespace app\telegram\interactions;

use app\efrsb\models\{Lot, Trade};
use app\services\Log;
use app\telegram\models\{SubscribeRequest, Subscriber, SubscriberCategoryXref, SubscriberRegionXref};
use app\telegram\Telega;
use app\telegram\menus\{CategoryMenu, RegionMenu};

use yii\db\Query;

use Telegram\Bot\Objects\{Update, Message, CallbackQuery};
use Telegram\Bot\Keyboard\Keyboard;


final class SubscriberInteraction extends BaseInteraction implements InteractionInterface
{

	use \app\telegram\traits\PrefixTrait;


	const SETTINGS = '/settings';
	const RANDOM = '/random';

	const UNSUBSCRIBE_PREFIX = 'unsubscribe';
	const UNSUBSCRIBE_CONFIRM = 'unsubscribe_confirm';
	const UNSUBSCRIBE_CANCEL = 'unsubscribe_cancel';


	private $subscriber;


	public function __construct(Telega $telega, Subscriber $subscriber)
	{
		parent::__construct($telega);
		$this->subscriber = $subscriber;
	}


	/**
	 * Точка входа для обработки сообщений от пользователя.
	 *
	 * На данном этапе пользователь уже оформил подписку, поэтому ему нужно предоставить возможность вводить команды:
	 * /start и /help - показывают хелп;
	 * /settings - отображение меню с настройками
	 *
	 * @param Message $message
	 * @return type
	 */
	protected function processMessage(Message $message)
	{
		$text = $message->text;
		Log::logTelegramGotMessage($message);


		if ($text == self::START) {
			$this->processStartMessage($message);
			return;
		}


		if ($text == self::HELP) {
			$this->processHelpMessage($message);
			return;
		}


		if ($text == self::SETTINGS) {
			$this->processSettingsMessage($message);
			return;
		}


		if ($text == self::RANDOM) {
			$this->processRandomMessage($message);
			return;
		}


		$this->processUnknownMessage($message);
		return;
	}


	/**
	 * Возвращает случайно выбранный лот, удовлетворяющий фильтрам подписчика
	 * @param Message $message
	 * @return type
	 */
	private function processRandomMessage(Message $message)
	{
		$efrsbCategories = $this->subscriber->efrsbCategoriesArray;
		$efrsbRegions = $this->subscriber->regionsArray;

		$lotId = (new Query)
			->select('l.id')
			->from(Lot::tableName() . ' l')
			->innerJoin(Trade::tableName() . ' t', 'l.tradeId = t.id')
			->andWhere(['l.classificationId' => $efrsbCategories])
			->andWhere(['t.regionCode' => $efrsbRegions])
			->orderBy(new \yii\db\Expression('rand()'))
			->limit(1)
			->one();

		# если подходящего лота нет, отправляем об этом сообщение
		if (false === $lotId) {
			$this->telega->sendMessage($message->chat->id, 'По указанными вами настройкам нет ни одного подходящего лота.' . PHP_EOL .
				'Изменить настройки можно командой /settings' . PHP_EOL);
			return;
		}

		$lot = Lot::findOneEx($lotId);
		$this->telega->notifyChatAboutLot($message->chat->id, $lot);

	}



	/**
	 * Точка входа для обработки callback query
	 * @param CallbackQuery $query
	 * @return type
	 */
	protected function processCallbackQuery(CallbackQuery $query)
	{

		# отправляем ответ на callback query
		// $this->telega->answerCallbackQuery($query);
		$data = $query->data;


		# если связано с отменой подписки
		if ($this->hasPrefix($data, self::UNSUBSCRIBE_PREFIX)) {

			# если это запрос на "Отписаться", просим подтверждения
			$this->processUnsubscribeStep($query);
			return;

		}


		# если связано с настройками категорий
		if ($this->hasPrefix($data, CategoryMenu::PREFIX)) {

			# если пользователь нажал кнопку "Готово" в меню категорий, возвращаемся к главному меню
			if ($this->hasPrefix($data, CategoryMenu::DONE)) {

				list($text, $keyboard) = $this->generateSettingsMenu();
				$this->telega->editMessage($query->message, $text, $keyboard);

			} else {

				# иначе - передаем обработку запроса
				$menu = new CategoryMenu();
				$messageData = $menu->process($this->subscriber, $query);
				$this->telega->editMessageKeyboard($query->message, $messageData->keyboard);

			}

			return;
		}


		# если связано с настройками регионов
		if ($this->hasPrefix($data, RegionMenu::PREFIX)) {

			# если пользователь нажал кнопку "Готово" в меню категорий, возвращаемся к главному меню
			if ($this->hasPrefix($data, RegionMenu::DONE)) {

				list($text, $keyboard) = $this->generateSettingsMenu();
				$this->telega->editMessage($query->message, $text, $keyboard);

			} else {

				# иначе - передаем обработку запроса
				$menu = new RegionMenu();
				$messageData = $menu->process($this->subscriber, $query);

				if ($this->hasPrefix($data, RegionMenu::DISTRICT_INDEX)) {
					$text = $this->generateSettingsText();
					$this->telega->editMessage($query->message, $text, $messageData->keyboard);
				} else {
					$this->telega->editMessageKeyboard($query->message, $messageData->keyboard);
				}


			}

			return;
		}


		# если связано с настройками цены
		if ($this->hasPrefix($data, PriceMenu::PREFIX)) {

			# если пользователь нажал кнопку "Готово" в меню категорий, возвращаемся к главному меню
			if ($this->hasPrefix($data, PriceMenu::DONE)) {

				list($text, $keyboard) = $this->generateSettingsMenu();
				$this->telega->editMessage($query->message, $text, $keyboard);

			} else {

				# иначе - передаем обработку запроса
				$menu = new PriceMenu();
				$messageData = $menu->process($this->subscriber, $query);
				$this->telega->editMessageKeyboard($query->message, $messageData->keyboard);

			}

			return;
		}


		# если нераспознанные данные
		$this->processUnknownQuery($query);

	}



	/**
	 * Обрабатывает сообщение /settings.
	 * @param type $message
	 * @return type
	 */
	private function processSettingsMessage($message)
	{
		list($text, $keyboard) = $this->generateSettingsMenu();
		$this->telega->sendMessage($message->chat->id, $text, $keyboard);
	}


	private function generateSettingsMenu()
	{
		$text = $this->generateSettingsText();
		$keyboard = $this->generateSettingsKeyboard();
		return [$text, $keyboard];
	}


	/**
	 * Генерирует клавиатуру с меню настроек:
	 * - категории;
	 * - регионы;
	 * - начальная цена лота;
	 * - возможность отписаться
	 * @return type
	 */
	private function generateSettingsKeyboard()
	{
		$keyboard = Keyboard::make()->inline()
			->row(
				Keyboard::inlineButton([
					'text' => 'Категории',
					'callback_data' => CategoryMenu::INDEX,
				]),
				Keyboard::inlineButton([
					'text' => 'Регионы',
					'callback_data' => RegionMenu::DISTRICT_INDEX,
				]),
				Keyboard::inlineButton([
					'text' => 'Отписаться',
					'callback_data' => self::UNSUBSCRIBE_PREFIX,
				])
			);
			// )->row(
			// 	// Keyboard::inlineButton([
			// 	// 	'text' => 'Цена лотов',
			// 	// 	'callback_data' => 'set_price',
			// 	// ]),

			// );

		return $keyboard;
	}


	/**
	 * Текст в меню
	 */
	private function generateSettingsText()
	{
		# получаем категории, регионы и настройку цен по данному подписчику
		$categories = $this->subscriber->tgCategoryTitles;
		$regions = $this->subscriber->regionTitles;

		$text = 'Ваши текущие настройки:' . PHP_EOL . PHP_EOL;

		$text .= 'Категории: ' . PHP_EOL;
		foreach ($categories as $category) {
			$text .= '- ' . $category . PHP_EOL;
		}
		$text .= PHP_EOL;

		$text .= 'Регионы: ' . PHP_EOL;
		foreach ($regions as $region) {
			$text .= '- ' . $region . PHP_EOL;
		}
		$text .= PHP_EOL;


		$text .= 'Вы отправили команду /settings' . PHP_EOL;
		return $text;
	}


	/**
	 * Определяет, относятся ли данные к настройке "отписаться"
	 * @param string $data
	 * @return type
	 */
	private function hasUnsubscribePrefix(string $data)
	{
		return $this->hasPrefix($data, self::UNSUBSCRIBE_PREFIX);
	}


	/**
	 * Обработка настройки "отписаться"
	 * @param type $query
	 * @return type
	 */
	private function processUnsubscribeStep($query)
	{
		$data = $query->data;


		# если пользователь нажал кнопку "Отписаться" в меню настороек, запрашиваем подтверждение
		if ($data == self::UNSUBSCRIBE_PREFIX) {

			# правим предыдущее сообщение с меню
			$text = 'Вы уверены, что хотите отменить подписку на получение новых лотов?' . PHP_EOL;
			$keyboard = Keyboard::make()->inline()
				->row(
					Keyboard::inlineButton([
						'text' => 'Да, хочу отписаться',
						'callback_data' => self::UNSUBSCRIBE_CONFIRM,
					]),
					Keyboard::inlineButton([
						'text' => 'Нет, я передумал',
						'callback_data' => self::UNSUBSCRIBE_CANCEL,
					])
				);

			$this->telega->editMessage($query->message, $text, $keyboard);
			return;
		}


		# если пользователь подтвердил, что хочет отписаться
		if ($data == self::UNSUBSCRIBE_CONFIRM) {

			Log::logTelegramUnsubscribe($this->subscriber);
			$this->subscriber->delete();
			$text = 'Подписка отменена. Если захотите снова подписаться - напишите /start :)' . PHP_EOL;
			$this->telega->editMessage($query->message, $text);
			return;
		}


		# если пользователь решил не отписываться
		if ($data == self::UNSUBSCRIBE_CANCEL) {

			$text = $this->generateSettingsText();
			$keyboard = $this->generateSettingsKeyboard();
			$this->telega->editMessage($query->message, $text, $keyboard);
			return;
		}
	}


	/**
	 * Обрабатывает настройки категорий
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processCategoryStep(CallbackQuery $query)
	{
		$data = $query->data;


		# если пользователь нажал кнопку "Категории" в главном меню, показываем ему список выбранных ему категорий
		if ($data == self::CATEGORIES_PREFIX) {

			$text = 'Выберите нужные категории' . PHP_EOL;
			$keyboard = $this->generateCategoryKeyboard($this->subscriber->tgCategoriesArray);
			$this->telega->editMessage($query->message, $text, $keyboard);

			return;
		}


		# если пользователь добавил категорию
		if ($this->hasPrefix($data, self::CATEGORIES_ADD)) {

			$data = $query->data;
			$user = $query->from;

			# получаем номер категории
			$categoryNumber = substr($data, 11);

			# добавляем ее
			$this->request->addCategory($categoryNumber);
			$this->request->saveEx();

			# изменяем сообщение бота, чтобы пользователю вывело перечень выбранных им категорий,
			# а также показало кнопки с категориями, которые он еще может выбрать
			$message = $query->message;


			# генерируем текст для вывода пользователю, какие категории он выбрал
			$text = 'Отлично! Давайте выберем категории лотов, по которым вы хотите получать уведомления.' . PHP_EOL . PHP_EOL;
			$text .= $this->generateCategoriesList($this->request->categoriesArray);

			# генерируем клавиатуру с учетом уже выбранных категорий. По этим категориям не будут создаваться кнопки
			$keyboard = $this->generateCategoryKeyboard($this->request->categoriesArray);

			$this->telega->editMessageKeyboard($message, $keyboard);
			$this->telega->answerCallbackQuery($query, 'Вы добавили категорию "' . CategoryService::getTgCategoryTitle($categoryNumber) . '"');

			return;
		}


		# если пользователь убрал категорию
		if ($this->hasPrefix($data, self::CATEGORIES_REMOVE)) {
			return;
		}


		# если пользователь закончил настройку категорий
		if ($this->hasPrefix($data, self::CATEGORIES_DONE)) {
			return;
		}
	}


	/**
	 * Просит пользователя подтвердить его намерение отписаться
	 * @param CallbackQuery $query
	 * @return type
	 */
	/*
	private function processUnsubscribe(CallbackQuery $query)
	{
		# правим предыдущее сообщение с меню
		$text = 'Вы уверены, что хотите отменить подписку на получение новых лотов?' . PHP_EOL;
		$keyboard = Keyboard::make()->inline()
			->row(
				Keyboard::inlineButton([
					'text' => 'Да, хочу отписаться',
					'callback_data' => 'set_unsubscribe_yes',
				]),
				Keyboard::inlineButton([
					'text' => 'Назад',
					'callback_data' => 'set_unsubscribe_no',
				])
			);

		$this->telega->editMessage($query->message, $text, $keyboard);
	}
	*/



	/**
	 * Отмена запроса на "Отписаться". Возвращаемся обратно к меню настроек
	 * @param CallbackQuery $query
	 * @return type
	 */
	/*
	private function processUnsubscribeNo(CallbackQuery $query)
	{
		$text = $this->generateSettingsText();
		$keyboard = $this->generateSettingsKeyboard();
		$this->telega->editMessage($query->message, $text, $keyboard);
	}
	*/


	/**
	 * Подтверждение запроса на "Отписаться".
	 * @param CallbackQuery $query
	 * @return type
	 */
	/*
	private function processUnsubscribeYes(CallbackQuery $query)
	{
		Log::logTelegramUnsubscribe($this->subscriber);
		$this->subscriber->delete();
		$text = 'Подписка отменена. Если захотите снова подписаться - напишите /start :)' . PHP_EOL;
		$this->telega->editMessage($query->message, $text);
	}
	*/



	/**
	 * Обрабатывает сообщение /start от пользователя.
	 *
	 * Т.к. пользователь уже является подписчиком, то по этой команде не нужно ничего нового запускать, просто отображаем то
	 * же, что и по команде /help
	 *
	 * @param Message $message
	 * @return type
	 */
	protected function processStartMessage(Message $message)
	{
		$this->processHelpMessage($message);
	}


	/**
	 * Обрабатывает сообщение /help.
	 *
	 * Выводит доступные на данном этапе команды.
	 *
	 * @param Message $message
	 * @return type
	 */
	protected function processHelpMessage(Message $message)
	{
		$subCategories = implode(', ', $this->subscriber->tgCategoriesArray);
		$subRegions = implode(', ', $this->subscriber->regionsArray);

		# текст по выбранной начальной цене лота
		$startPriceText = $this->getStartPriceText($this->subscriber->minStartPrice, $this->subscriber->maxStartPrice);


		$text = 'У вас оформлена подписка на получение лотов по указанным параметрам: ' . PHP_EOL .
			"Категории: $subCategories" . PHP_EOL .
			"Регионы: $subRegions" . PHP_EOL .
			$startPriceText . PHP_EOL .
			PHP_EOL .
			'Для изменения настроек или отмены подписки наберите команду /settings' . PHP_EOL;


		// $keyboard = Keyboard::make()->inline()
		// 	->row(
		// 		Keyboard::inlineButton([
		// 			'text' => 'Отменить',
		// 			'callback_data' => 'sr_cancel',
		// 		])
		// 	);

		$this->telega->sendMessage($message->chat->id, $text);
	}


	/**
	 * Обрабатывает неизвестное сообщение.
	 * @param Message $message
	 * @return type
	 */
	protected function processUnknownMessage(Message $message)
	{
		$text = 'Извините, ваша команда нераспознана.' . PHP_EOL;
		$this->telega->sendMessage($message->chat->id, $text);
		$this->processStartMessage($message);
	}

}
