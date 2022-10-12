<?php

namespace app\telegram\interactions;

use app\services\Log;
use app\telegram\models\{SubscribeRequest, Subscriber, SubscriberCategoryXref, SubscriberRegionXref};
use app\telegram\Telega;
use app\telegram\services\{CategoryService, RegionService, PriceService};
use Yii;
use Telegram\Bot\Objects\{Update, Message, CallbackQuery};
use Telegram\Bot\Keyboard\Keyboard;


final class SubscribeRequestInteraction extends BaseInteraction implements InteractionInterface
{

	private $request;


	public function __construct(Telega $telega, SubscribeRequest $request)
	{
		parent::__construct($telega);
		$this->request = $request;
	}


	/**
	 * На этапе оформления подписки от пользователя не требуется ввод каких-то сообщений,
	 * всё взаимодействие происходит посредством нажатия на те или иные кнопки. Поэтому в данном методе
	 * можно обработать разве что сообщения /start и /help
	 *
	 * @todo Добавить обработку неизвестных сообщений
	 * @param Message $message
	 * @return type
	 */
	protected function processMessage(Message $message)
	{
		$text = $message->text;
		Yii::trace('lala' . $text, __METHOD__);
		Log::logTelegramGotMessage($message);


		if ($text == self::START) {
			$this->processStartMessage($message);
		} elseif ($text == self::HELP) {
			$this->processHelpMessage($message);
		} elseif ($this->request->minStartPrice == 'expect') {
			$this->processMinPriceSet($message);
		} elseif ($this->request->maxStartPrice == 'expect') {
			$this->processMaxPriceSet($message);
		} else {
			$this->processUnknownMessage($message);
		}
	}


	/**
	 * Принимает сообщение от пользователя с указанной им мин. ценой и устанавливает ее
	 * @param Message $message
	 * @return type
	 */
	private function processMinPriceSet(Message $message)
	{
		$price = $message->text;

		# если это пустое сообщение, устанавливаем пустую цену
		if (PriceService::validate($price, '')) {

			if ($price == '0') {
				$this->request->minStartPrice = null;
				$priceText = 'любую';
			} else {
				$this->request->minStartPrice = $price;
				$priceText = $price . ' руб.';
			}

			# устанавливаем в БД в заявке признак того, что мы ожидаем ответа на вопрос о мин. цене
			$this->request->maxStartPrice = 'expect';
			$this->request->saveEx();

			# предлагаем пользователю отправить теперь макс. цену

			# формируем новое сообщение

			$text = "Вы указали минимальную цену $priceText . Теперь укажите максимальную цену. Она должна быть не меньше минимальной цены" . PHP_EOL .
				'Допускаются только цифры, например, 20000. Если подходит любая макс. цена, укажите 0.' . PHP_EOL;


			$keyboard = Keyboard::forceReply();
			$this->telega->sendMessage($this->request->chatId, $text, $keyboard);


		} else {

			$keyboard = Keyboard::forceReply();
			$text = 'Пожалуйста, укажите корректную минимальную цену - число, состоящее не более чем из 10 цифр' . PHP_EOL .
				'Если подходит любая мин. цена, укажите 0.';
			$this->telega->sendMessage($this->request->chatId, $text, $keyboard);

		}
	}


	/**
	 * Принимает сообщение от пользователя с указанной им мин. ценой и устанавливает ее
	 * @param Message $message
	 * @return type
	 */
	private function processMaxPriceSet(Message $message)
	{
		$price = $message->text;
		$isMaxPriceValid = PriceService::validate($price, '');
		$isMaxPriceGreaterThanMinPrice = (
			($price >= $this->request->minStartPrice)
			||
			is_null($this->request->minStartPrice)
			||
			($price == '0')
		);

		# если это пустое сообщение, устанавливаем пустую цену
		if (( $isMaxPriceValid && $isMaxPriceGreaterThanMinPrice )) {

			if ($price == '0') {
				$this->request->maxStartPrice = null;
				$priceText = 'любую';
			} else {
				$this->request->maxStartPrice = $price;
				$priceText = $price . ' руб.';
			}

			$this->request->saveEx();

			$text2 = "Вы указали максимальную цену $priceText ." . PHP_EOL;
			$this->telega->sendMessage($this->request->chatId, $text2);


			# формируем сообщение для окончательной проверки
			$text = 'Итак, давайте проверим указанные вами данные: ' . PHP_EOL . PHP_EOL;


			# текст по выбранным категориям
			$text .= $this->generateCategoriesList($this->request->categoriesArray);
			$text .= PHP_EOL;


			# текст по выбранным регионам
			$text .= $this->generateRegionsList($this->request->regionsArray);
			$text .= PHP_EOL;


			# текст по выбранной начальной цене лота
			$text .= $this->getStartPriceText($this->request->minStartPrice, $this->request->maxStartPrice);
			$text .= PHP_EOL . PHP_EOL;


			# просим пользователя подтвердить оформление подписки
			$text .= 'Ну что, подписываемся на обновления?' . PHP_EOL;
			$text .= 'Если передумали или хотите произвести настройки заново - нажмите "Нет"' . PHP_EOL;

			$keyboard = $this->generateLastCheckKeyboard();
			$this->telega->sendMessage($this->request->chatId, $text, $keyboard);


		} elseif (!$isMaxPriceGreaterThanMinPrice) {

			# если макс. цена валидна, но меньше мин. цены - просим ввести заново
			$keyboard = Keyboard::forceReply();
			$text = 'Макс. цена должна быть больше указанной вами мин. цены ' . $this->request->minStartPrice . ' руб.' . PHP_EOL .
				'Если подходит любая макс. цена, укажите 0';
			$this->telega->sendMessage($this->request->chatId, $text, $keyboard);

		}
		else {

			$keyboard = Keyboard::forceReply();
			$text = 'Пожалуйста, укажите корректную цену - число, состоящее не более чем из 10 цифр' . PHP_EOL .
				'Если подходит любая макс. цена, укажите 0.';
			$this->telega->sendMessage($this->request->chatId, $text, $keyboard);

		}
	}


	/**
	 * Обрабатывает сообщение /start от пользователя.
	 *
	 * Т.к. мы находимся в процессе оформления подписки, то получение этого сообщения означает, что что-то идет не так.
	 * Либо пользователь просто по приколу ввел это сообщение, либо же он запутался и хочет прервать процесс или
	 * начать заново.
	 *
	 * @param Message $message
	 * @return type
	 */
	protected function processStartMessage(Message $message)
	{
		$text = 'Если вы хотите отменить или начать заново оформление подписки, нажмите кнопку "Отменить".' . PHP_EOL .
			'В противном случае вы можете продолжить процесс оформления.' . PHP_EOL;

		$keyboard = Keyboard::make()->inline()
			->row(
				Keyboard::inlineButton([
					'text' => 'Отменить',
					'callback_data' => 'sr_cancel',
				])
			);

		$this->telega->sendMessage($message->chat->id, $text, $keyboard);
	}


	/**
	 * Обрабатывает сообщение /help.
	 *
	 * Я сделал точно то же самое, что и в обработке /start. Возможно, в будущем стоит сделать как-то покрасивее.
	 * @param Message $message
	 * @return type
	 */
	protected function processHelpMessage(Message $message)
	{
		$this->processStartMessage($message);
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


	/**
	 * Front controller для обработки callback query
	 * @param CallbackQuery $query
	 * @return type
	 */
	protected function processCallbackQuery(CallbackQuery $query)
	{

		# отправляем ответ на callback query
		# возможно, не нужно прям так обязательно отправлять ответ в начале. Ведь в некоторых методах есть не пустые
		# answerCallbackQuery. Надо проверить, можно ли два раза ответить на callback query. А то у меня подозрение, что
		# второй ответ не учитывается, и уведомление не показывается
		// $this->telega->answerCallbackQuery($query);


		# разбираемся с тем, что это за запрос
		# поле "data" - это данные в коллбеке. По его значению мы определяем, что сделать дальше
		$data = $query->data;


		if (substr($data, 0, 11) == 'sr_cat_add_') {

			# если это запрос на добавление какой-то категории, устанавливаем ее
			$this->processCategoryAdd($query);

		} elseif (substr($data, 0, 11) == 'sr_cat_rem_') {

			$this->processCategoryRemove($query);

		} elseif ($data == 'sr_cat_done') {

			# если это запрос "я выбрал все категории, давайте дальше!", переходим к выбору округов и регионов
			$this->processCategoriesDone($query);

		} elseif (substr($data, 0, 14) == 'sr_goto_distr_') {

			# этап диалога "переход к такому-то округу"
			$this->processRegionGoToDistrict($query);

		} elseif (substr($data, 0, 11) == 'sr_reg_add_') {

			# этап диалога "выбор такого-то региона"
			$this->processRegionAdd($query);

		} elseif (substr($data, 0, 11) == 'sr_reg_rem_') {

			# этап диалога "выбор такого-то региона"
			$this->processRegionRemove($query);

		} elseif ($data == 'sr_distr_back') {

			# этап диалога "назад к выбору округа"
			$this->processRegionBackToDistricts($query);

		} elseif ($data == 'sr_reg_done') {

			# этап диалога "я выбрал все регионы, давайте дальше!"
			$this->processRegionsDone($query);

		} elseif ($data == 'sr_price_') {

			# обрабатываем установку цены - минимальной или максимальной
			$this->processPriceSet($query);

		}


		/* elseif ($data == 'sr_stprice_no') {

			# если нажали "нет" в ответ на предложение выбрать диапазон стартовых цен
			$this->processLastCheck($query);

		} elseif ($data == 'sr_stprice_yes') {

			# если нажали "да" в ответ на предложение указать диапазон цен
			$this->processSetStartPrice($query);

		} elseif (substr($data, 0, 14) == 'sr_minstprice_') {

			# если пользователь выбрал минимальную нач. цену
			$this->processSetMinStartPrice($query);

		} elseif (substr($data, 0, 14) == 'sr_maxstprice_') {

			# если пользователь выбрал максимальную нач. цену
			$this->processSetMaxStartPrice($query);

		} */ elseif ($data == 'sr_lastcheck_no') {

			# если пользователь ответил "нет" на окончательное предложение подписаться
			$this->processLastCheckNo($query);

		} elseif ($data == 'sr_lastcheck_yes') {

			# если пользователь окончательно подтвердил "да, подписываемся!"
			$this->processLastCheckYes($query);

		} elseif ($data == 'sr_cancel') {

			# если пользователь нажал кнопку "Отменить"
			$this->processCancel($query);

		} else {

			$this->processUnknownQuery($query);
		}

	}


	/**
	 * Если пользователь нажал "Отменить" - удаляем заявку на подписку, и выводим сообщение с предложением
	 * оформить заявку и кнопками "да" и "нет".
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processCancel(CallbackQuery $query)
	{
		$this->request->delete();

		$text = 'Оформление подписки прекращено. Если снова захотите подписаться - напишите /start :)';
		$this->telega->sendMessage($query->from->id, $text);

		$this->telega->removeKeyboard($query->from->id, $query->message->messageId);
		$this->telega->answerCallbackQuery($query);
	}


	/**
	 * Обрабатывает добавление пользователем новой категории.
	 *
	 * При этом нужно:
	 * 1) в сообщении изменить текст - показать, какие категории он уже выбрал;
	 * 2) в клавиатуре убрать те категории, которые уже выбраны - чтобы нельзя было два раза добавить одну и ту же;
	 * 3) и в клавиатуре должна быть кнопка "Я закончил выбор категорий"
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processCategoryAdd(CallbackQuery $query)
	{

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
	}


	/**
	 * Обрабатывает удаление категории
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processCategoryRemove(CallbackQuery $query)
	{

		$data = $query->data;
		$user = $query->from;

		# получаем номер категории
		$categoryNumber = substr($data, 11);

		# добавляем ее
		$this->request->removeCategory($categoryNumber);
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
		$this->telega->answerCallbackQuery($query, 'Вы убрали категорию "' . CategoryService::getTgCategoryTitle($categoryNumber) . '"');
	}


	/**
	 * Обрабатывает завершение пользователем добавления категорий.
	 *
	 * 1) Убирает кнопки у окна выбора категорий. Там остается только текст с выбранными категориями.
	 * 2) Отправляет сообщение с выбором округов/регионов.
	 */
	private function processCategoriesDone(CallbackQuery $query)
	{
		$text = $this->getRegionsStageText();
		$keyboard = $this->generateFederalDistrictKeyboard(false);
		$this->telega->sendMessage($this->request->chatId, $text, $keyboard);

		$this->telega->removeKeyboard($this->request->chatId, $query->message->messageId);
		$this->telega->answerCallbackQuery($query);
	}


	private function getRegionsStageText()
	{
		return 'Настройка категорий завершена! ' . $this->generateCategoriesList($this->request->categoriesArray) . PHP_EOL .
			'Теперь нужно выбрать регионы, которые вас интересуют.' . PHP_EOL;
	}


	/**
	 * Обрабатывает переход пользователем к такому-то округу.
	 *
	 * 1) редактирует сообщение, отображая выбранные пользователем регионы;
	 * 2) отображает клавиатуру с регионами данного округа. Если какой-то регион из них уже выбран, то его не отображает.
	 *
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processRegionGoToDistrict(CallbackQuery $query)
	{
		$data = $query->data;
		$user = $query->from;
		$message = $query->message;

		# получаем код округа
		$districtCode = substr($data, 14);

		# генерируем клавиатуру с учетом регионов, которые пользователь уже выбрал
		$keyboard = $this->generateRegionsKeyboard($districtCode, $this->request->regionsArray);


		# генерируем текст для вывода пользователю, какие категории он выбрал
		// $text = $this->getRegionsStageText();
		// $text .= $this->generateRegionsList($this->request->regionsArray) . PHP_EOL;


		# редактируем текущее сообщение
		$this->telega->editMessageKeyboard($message, $keyboard);


		# отправляем ответ на коллбек
		$this->telega->answerCallbackQuery($query);
	}


	private function processRegionBackToDistricts(CallbackQuery $query)
	{
		$alreadyChosenRegions = $this->request->getRegionsArray();

		# генерируем клавиатуру с округами
		if (count($alreadyChosenRegions) > 0) {
			$showDoneButton = true;
		} else {
			$showDoneButton = false;
		}

		$keyboard = $this->generateFederalDistrictKeyboard($showDoneButton);


		# генерируем текст для вывода пользователю, какие регионы он выбрал
		$text = $this->getRegionsStageText();

		$text .= $this->generateRegionsList($this->request->regionsArray) . PHP_EOL;


		$this->telega->editMessageKeyboard($query->message, $keyboard);
		$this->telega->answerCallbackQuery($query);
	}


	private function generateRegionsList(array $regions)
	{

		if (count($regions) > 0) {

			$regionTitles = RegionService::getRegionTitles($regions);
			$text = 'Вами выбраны регионы:' . PHP_EOL;

			foreach ($regionTitles as $title) {
				$text .= '- ' . $title . PHP_EOL;
			}
		} else {
			$text = 'Не выбран ни один регион.' . PHP_EOL;
		}

		return $text;
	}


	private function generateCategoriesList(array $categories)
	{
		if (count($categories) > 0) {

			$categoryTitles = CategoryService::getTgCategoryTitles($categories);
			$text = 'Вами выбраны категории:' . PHP_EOL;

			foreach ($categoryTitles as $title) {
				$text .= '- ' . $title . PHP_EOL;
			}
		} else {
			$text = 'Не выбрана ни одна категория.' . PHP_EOL;
		}

		return $text;
	}


	/**
	 * Обрабатывает добавление пользователем региона.
	 *
	 * При этом нужно:
	 * 1) в сообщении изменить текст - показать, какие регионы он уже выбрал;
	 * 2) в клавиатуре убрать те регионы, которые уже выбраны - чтобы нельзя было два раза добавить один и тот же;
	 * 3) и в клавиатуре должна быть кнопка "Я закончил выбор регионов" и кнопка "Назад к списку округов"
	 *
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processRegionAdd(CallbackQuery $query)
	{
		$data = $query->data;
		$user = $query->from;

		# получаем номер региона
		$regionCode = substr($data, 11);
		$districtCode = RegionService::getDistrictCodeByRegion($regionCode);

		# добавляем регион
		$this->request->addRegion($regionCode);
		$this->request->saveEx();

		# изменяем сообщение бота, чтобы пользователю вывело перечень выбранных им регионов,
		# а также показало кнопки с регионами, которые он еще может выбрать
		$message = $query->message;
		$regions = $this->request->regionsArray;

		$text = $this->getRegionsStageText() .
			$this->generateRegionsList($regions);

		# генерируем клавиатуру с учетом уже выбранных регионов. По этим регионам не будут создаваться кнопки
		$keyboard = $this->generateRegionsKeyboard($districtCode, $regions);

		# генерируем текст для вывода пользователю, какие регионы он выбрал
		// foreach ($regions as $region) {
		// 	$text .= '- ' . $region . PHP_EOL;
		// }

		$this->telega->editMessageKeyboard($message, $keyboard);
		$this->telega->answerCallbackQuery($query, 'Вы добавили регион "' . RegionService::getRegionTitle($regionCode) . '"');
	}


	/**
	 * Удаляет регион
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processRegionRemove(CallbackQuery $query)
	{
		$data = $query->data;
		$user = $query->from;

		# получаем номер региона
		$regionCode = substr($data, 11);
		$districtCode = RegionService::getDistrictCodeByRegion($regionCode);

		# удаляем регион
		$this->request->removeRegion($regionCode);
		$this->request->saveEx();

		# изменяем сообщение бота, чтобы пользователю вывело перечень выбранных им регионов,
		# а также показало кнопки с регионами, которые он еще может выбрать
		$message = $query->message;
		$regions = $this->request->regionsArray;

		$text = $this->getRegionsStageText() .
			$this->generateRegionsList($regions);

		# генерируем клавиатуру с учетом уже выбранных регионов. По этим регионам не будут создаваться кнопки
		$keyboard = $this->generateRegionsKeyboard($districtCode, $regions);

		# генерируем текст для вывода пользователю, какие регионы он выбрал
		// foreach ($regions as $region) {
		// 	$text .= '- ' . $region . PHP_EOL;
		// }

		$this->telega->editMessageKeyboard($message, $keyboard);
		$this->telega->answerCallbackQuery($query, 'Вы убрали регион "' . RegionService::getRegionTitle($regionCode) . '"');
	}


	/**
	 * Переход к этапу "Выбор минимальной цены лота"
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processRegionsDone(CallbackQuery $query)
	{

		# формируем новое сообщение
		$this->telega->removeKeyboard($query->from->id, $query->message->messageId);
		$this->telega->answerCallbackQuery($query);

		# формируем сообщение для окончательной проверки
		$text = 'Итак, давайте проверим указанные вами данные: ' . PHP_EOL . PHP_EOL;


		# текст по выбранным категориям
		$text .= $this->generateCategoriesList($this->request->categoriesArray);
		$text .= PHP_EOL;


		# текст по выбранным регионам
		$text .= $this->generateRegionsList($this->request->regionsArray);
		$text .= PHP_EOL;


		# текст по выбранной начальной цене лота
		// $text .= $this->getStartPriceText($this->request->minStartPrice, $this->request->maxStartPrice);
		// $text .= PHP_EOL . PHP_EOL;


		# просим пользователя подтвердить оформление подписки
		$text .= 'Ну что, подписываемся на обновления?' . PHP_EOL;
		$text .= 'Если передумали или хотите произвести настройки заново - нажмите "Нет"' . PHP_EOL;

		$keyboard = $this->generateLastCheckKeyboard();
		$this->telega->sendMessage($this->request->chatId, $text, $keyboard);



		# --- вырезал установку цены
		/*
		$text = 'Отлично! ' . $this->generateRegionsList($this->request->regionsArray) . PHP_EOL .
			'Осталось указать подходящий для вас диапазон начальной цены лотов.' . PHP_EOL;
		$this->telega->sendMessage($this->request->chatId, $text);

		$text2 = 'Укажите минимальную цену в виде числа без букв, точек и запятых, например 10000. Если подходит любая минимальная цена, укажите 0.' . PHP_EOL;
		// $keyboard = $this->generateStartPriceKeyboard();
		$this->telega->removeKeyboard($query->from->id, $query->message->messageId);
		$this->telega->answerCallbackQuery($query);

		$keyboard = Keyboard::forceReply();
		$sentMessage = $this->telega->sendMessage($this->request->chatId, $text2, $keyboard);

		# устанавливаем в БД в заявке признак того, что мы ожидаем ответа на вопрос о мин. цене
		$this->request->minStartPrice = 'expect';
		$this->request->saveEx();*/

	}


	private function processSetStartPrice(CallbackQuery $query)
	{

		# формируем новое сообщение
		$text = 'Укажите нижнюю границу начальной стоимости лота:' . PHP_EOL;
		$keyboard = $this->generateMinStartPriceKeyboard();
		$this->telega->sendMessage($this->request->chatId, $text, $keyboard);

		$this->telega->removeKeyboard($query->from->id, $query->message->messageId);
		$this->telega->answerCallbackQuery($query);
	}


	/**
	 * Устанавливает минимальную цену лота
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processSetMinStartPrice(CallbackQuery $query)
	{
		$minPrice = substr($query->data, 14);
		$this->setRequestMinPrice($minPrice);

		$text = 'Вы указали минимальную начальную цену: ' . $minPrice . PHP_EOL . PHP_EOL .
			'Теперь давайте укажем верхнюю границу начальной стоимости лота: ' . PHP_EOL;
		$keyboard = $this->generateMaxStartPriceKeyboard($minPrice);

		$this->telega->editMessage($query->message, $text, $keyboard);
	}


	/**
	 * Устанавливает максимальную цену лота. И потом переходит к завершающей проверке
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processSetMaxStartPrice(CallbackQuery $query)
	{
		$maxPrice = substr($query->data, 14);
		$this->setRequestMaxPrice($maxPrice);
		$this->processLastCheck($query);
	}


	/**
	 * Обрабатывает установку цены.
	 *
	 * Если в заявке нет минимальной цены - принимаем цену из коллбека за минимальную.
	 * Иначе - принимаем цену из коллбека за максимальную.
	 *
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processPriceSet(CallbackQuery $query)
	{
		$price = substr($query->data, 9);

		# проверяем, установлена ли мин. цена. Если нет - устанавливаем
		if (is_null($this->request->minStartPrice)) {
			$this->request->minStartPrice = $price;

			$keyboard = $this->generateStartPriceKeyboard($price);
			$this->telega->editMessageKeyboard($query->message, $keyboard);

		} else {


		}

		$this->request->saveEx();
	}


	/**
	 * Обрабатывает ответ пользователя "Нет" на завершающей проверке.
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processLastCheckNo(CallbackQuery $query)
	{
		# удаляем заявку
		$this->request->delete();

		# говорим "ничего страшного, можете попозже передумать"
		$this->telega->sendMessage($this->request->chatId, 'Хорошо. Если в будущем захотите подписаться, напишите /start :)');

		$this->telega->removeKeyboard($query->from->id, $query->message->messageId);
		$this->telega->answerCallbackQuery($query);
	}


	private function setRequestMinPrice(string $minPrice)
	{
		switch ($minPrice) {
			case '25t':
				$this->request->minStartPrice = 25000;
				break;

			case '50t':
				$this->request->minStartPrice = 50000;
				break;

			case '100t':
				$this->request->minStartPrice = 100000;
				break;

			case '250t':
				$this->request->minStartPrice = 250000;
				break;

			case '500t':
				$this->request->minStartPrice = 500000;
				break;

			case '1mln':
				$this->request->minStartPrice = 1000000;
				break;

			case 'any':
				// $this->request->minStartPrice = 25000;
				break;

		}

		$this->request->saveEx();
	}


	private function setRequestMaxPrice(string $maxPrice)
	{
		switch ($maxPrice) {
			case '25t':
				$this->request->maxStartPrice = 25000;
				break;

			case '50t':
				$this->request->maxStartPrice = 50000;
				break;

			case '100t':
				$this->request->maxStartPrice = 100000;
				break;

			case '250t':
				$this->request->maxStartPrice = 250000;
				break;

			case '500t':
				$this->request->maxStartPrice = 500000;
				break;

			case '1mln':
				$this->request->maxStartPrice = 1000000;
				break;

			case 'any':
				// $this->request->maxStartPrice = 25000;
				break;

		}

		$this->request->saveEx();
	}


	private function generateMinStartPriceKeyboard()
	{
		$keyboard = Keyboard::make()->inline()
			->row(
				Keyboard::inlineButton([
					'text' => 'От 25 тыс.',
					'callback_data' => 'sr_minstprice_25t',
				]),
				Keyboard::inlineButton([
					'text' => 'От 50 тыс.',
					'callback_data' => 'sr_minstprice_50t',
				]),
				Keyboard::inlineButton([
					'text' => 'От 100 тыс.',
					'callback_data' => 'sr_minstprice_100t',
				])
			)
			->row(
				Keyboard::inlineButton([
					'text' => 'От 250 тыс.',
					'callback_data' => 'sr_minstprice_250t',
				]),
				Keyboard::inlineButton([
					'text' => 'От 500 тыс.',
					'callback_data' => 'sr_minstprice_500t',
				]),
				Keyboard::inlineButton([
					'text' => 'От 1 млн.',
					'callback_data' => 'sr_minstprice_1mln',
				])
			)
			->row(
				Keyboard::inlineButton([
					'text' => 'Любая',
					'callback_data' => 'sr_minstprice_any',
				])
			);

		return $keyboard;
	}


	private function generateMaxStartPriceKeyboard($minPrice)
	{
		$buttonsArray = [];

		switch ($minPrice) {
			case 'any':
				$buttonsArray[] = Keyboard::inlineButton([
					'text' => 'До 25 тыс.',
					'callback_data' => 'sr_maxstprice_25t',
				]);

			case '25t':
				$buttonsArray[] = Keyboard::inlineButton([
					'text' => 'До 50 тыс.',
					'callback_data' => 'sr_maxstprice_50t',
				]);

			case '50t':
				$buttonsArray[] = Keyboard::inlineButton([
					'text' => 'До 100 тыс.',
					'callback_data' => 'sr_maxstprice_100t',
				]);

			case '100t':
				$buttonsArray[] = Keyboard::inlineButton([
					'text' => 'До 250 тыс.',
					'callback_data' => 'sr_maxstprice_250t',
				]);

			case '250t':
				$buttonsArray[] = Keyboard::inlineButton([
					'text' => 'До 500 тыс.',
					'callback_data' => 'sr_maxstprice_500t',
				]);

			case '500t':
				$buttonsArray[] = Keyboard::inlineButton([
					'text' => 'До 1 млн.',
					'callback_data' => 'sr_maxstprice_1mln',
				]);

			case '1mln':
				$buttonsArray[] = Keyboard::inlineButton([
					'text' => 'Любая',
					'callback_data' => 'sr_maxstprice_any',
				]);

		}


		$array = array_chunk($buttonsArray, 3, true);
		$keyboard = Keyboard::make()->inline();
		foreach ($array as $a) {
			$keyboard->row(...$a);
		}

		return $keyboard;
	}




	/**
	 * Вызывается, когда пользователь ввел все что надо, и сейчас надо вывести ему окончательное подтверждение.
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processLastCheck(CallbackQuery $query)
	{

		# формируем новое сообщение
		$text = 'Итак, давайте проверим указанные вами данные: ' . PHP_EOL . PHP_EOL;


		# текст по выбранным категориям
		$text .= $this->generateCategoriesList($this->request->categoriesArray);
		$text .= PHP_EOL;


		# текст по выбранным регионам
		$text .= $this->generateRegionsList($this->request->regionsArray);
		$text .= PHP_EOL;


		# текст по выбранной начальной цене лота
		$text .= $this->getStartPriceText($this->request->minStartPrice, $this->request->maxStartPrice);
		$text .= PHP_EOL . PHP_EOL;


		# просим пользователя подтвердить оформление подписки
		$text .= 'Ну что, подписываемся на обновления?' . PHP_EOL;
		$text .= 'Если передумали или хотите произвести настройки заново - нажмите "Нет"' . PHP_EOL;

		$keyboard = $this->generateLastCheckKeyboard();
		$this->telega->sendMessage($this->request->chatId, $text, $keyboard);

		$this->telega->removeKeyboard($query->from->id, $query->message->messageId);
		$this->telega->answerCallbackQuery($query);
	}


	/**
	 * Обрабатывает ответ "Да, оформляем подписку" на завершающей проверке.
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processLastCheckYes(CallbackQuery $query)
	{

		# создаем подписку на основе заявки, заявку удаляем
		$this->createSubscriber($this->request);
		$this->request->delete();

		# отправялем сообщение "круто, вы подписались на лоты, ждите"
		$text = 'Подписка оформлена. Ожидайте уведомлений о новых лотах! :)' . PHP_EOL .
			'Если захотите изменить настройки или отписаться, напишите /settings';
		$this->telega->sendMessage($this->request->chatId, $text);

		$this->telega->removeKeyboard($query->from->id, $query->message->messageId);
		$this->telega->answerCallbackQuery($query);
	}


	/**
	 * Создаем подписку на основе заявки
	 * @param SubscribeRequest $request
	 * @return type
	 */
	private function createSubscriber(SubscribeRequest $request)
	{
		$subscriber = new Subscriber([
			'chatId' => $request->chatId,

			# информация о подписчике
			'username' => $request->username,
			'firstName' => $request->firstName,
			'lastName' => $request->lastName,

			# настройки фильтров подписчика (кроме регионов и категорий, они в отдельных таблицах, код по ним идет ниже)
			'workingHoursStart' => $request->workingHoursStart,
			'workingHoursEnd' => $request->workingHoursEnd,
			'minStartPrice' => $request->minStartPrice,
			'maxStartPrice' => $request->maxStartPrice,
		]);

		$subscriber->saveEx();


		# создаем связи с категориями. Нужно учесть, что "категории" в заявке - это не коды категорий лотов
		# получаем категории
		$requestCategories = $request->categoriesArray;
		// $efrsbCategories = CategoryService::getEfrsbCategories($requestCategories);

		foreach ($requestCategories as $cat) {
			$xref = new SubscriberCategoryXref([
				'chatId' => $subscriber->chatId,
				'categoryCode' => $cat,
			]);
			$xref->saveEx();
		}


		# создаем связи с регионами. Тут все просто
		$regions = $request->regionsArray;
		foreach ($regions as $reg) {
			$xref = new SubscriberRegionXref([
				'chatId' => $subscriber->chatId,
				'regionCode' => $reg,
			]);
			$xref->saveEx();
		}
	}



	/**
	 * Создает клавиатуру с кнопкми "Да, подписываемся!" и "Нет, не хочу".
	 * @return type
	 */
	private function generateLastCheckKeyboard()
	{
		$keyboard = Keyboard::make()->inline()
			->row(
				Keyboard::inlineButton([
					'text' => 'Да, подписываемся!',
					'callback_data' => 'sr_lastcheck_yes',
				]),
				Keyboard::inlineButton([
					'text' => 'Нет, пока не хочу',
					'callback_data' => 'sr_lastcheck_no',
				])
			);

		return $keyboard;
	}


	/**
	 * Генерирует клавиатуру с кнопками выбора диапазона цен
	 * @param ?string|null $chosenMinPrice
	 * @return type
	 */
	private function generateStartPriceKeyboard(?string $chosenMinPrice = null)
	{
		$prices = PriceService::getPrices();
		$buttonsArray = [];

		foreach ($prices as $key => $title) {
			$button = Keyboard::inlineButton([
				'text' => $title,
				'callback_data' => 'sr_price_' . $key,
			]);
			$buttonsArray[] = $button;
		}

		$array = array_chunk($buttonsArray, 3, true);
		$keyboard = Keyboard::make()->inline();
		foreach ($array as $a) {
			$keyboard->row(...$a);
		}

		return $keyboard;
	}


	/**
	 * Генерирует кнопки "Да, хочу" и "Нет, не хочу" в ответ на предложение указать мин. и макс. цены лота
	 * @return type
	 */
	/*private function generateStartPriceKeyboard()
	{
		$keyboard = Keyboard::make()->inline()
			->row(
				Keyboard::inlineButton([
					'text' => 'Да, хочу',
					'callback_data' => 'sr_stprice_yes',
				]),
				Keyboard::inlineButton([
					'text' => 'Нет, спасибо',
					'callback_data' => 'sr_stprice_no',
				])
			);

		return $keyboard;
	}*/

}
