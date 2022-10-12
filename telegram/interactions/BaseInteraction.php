<?php

namespace app\telegram\interactions;

use app\services\Log;
use app\telegram\exceptions\InvalidUpdateTypeException;
use app\telegram\Telega;
use app\telegram\services\{CategoryService, RegionService, PriceService};
use Telegram\Bot\Objects\{Update, Message, CallbackQuery};
use Telegram\Bot\Keyboard\Keyboard;


abstract class BaseInteraction
{

	const START = '/start';
	const HELP = '/help';

	# @todo: удалить здесь. Он тут не нужен, см. CategoryMenu и RegionMenu
	const CHECK = "\xE2\x9C\x94";


	protected $telega;
	protected $update;
	protected $updateType;

	// protected $categoryService;
	// protected $regionService;


	public function __construct(Telega $telega)
	{
		$this->telega = $telega;
		$this->update = $telega->update;
		$this->updateType = $this->update->detectType();
		// $this->categoryService = new CategoryService();
		// $this->regionService = new RegionService();
	}


	public function process(): void
	{
		if ($this->updateType == 'message') {
			$this->processMessage($this->update->message);
		} elseif ($this->updateType == 'callback_query') {
			$this->processCallbackQuery($this->update->callbackQuery);
		} else {
			throw new InvalidUpdateTypeException($this->update);
		}
	}


	protected function getBotShortDescription()
	{
		return 'Приветствую! Я - бот Лотплюс. ' . PHP_EOL .
			'Я умею отправлять интересные лоты банкротов в соответствии с вашими пожеланиями!' . PHP_EOL;
	}


	protected function generateUnknownMessageAnswer($text)
	{
		$answer = 'Неизвестная команда: ' . PHP_EOL .
			'"' . $text . '"';
		$keyboard = null;

		return [$answer, $keyboard];
	}


	abstract protected function processMessage(Message $message);
	abstract protected function processStartMessage(Message $message);
	abstract protected function processHelpMessage(Message $message);
	abstract protected function processUnknownMessage(Message $message);

	abstract protected function processCallbackQuery(CallbackQuery $query);


	/**
	 * Обрабатывает callback query с неизвестными данными.
	 * В текущей реализации просто логирует. Но вообще, если мы получили неизвестные данные, это означает,
	 * что что-то в нашем боте работает не так, что мы не учитываем какой-то ответ.
	 * @param CallbackQuery $query
	 * @return type
	 */
	protected function processUnknownQuery(CallbackQuery $query)
	{
		$message = 'chatId: ' . $query->from->id . ', data: ' . $query->data;
		Log::logTelegramUnknownResponse($message);
		$this->telega->answerCallbackQuery($query);
	}


	/**
	 * Генерирует клавиатуру для выбора категорий.
	 *
	 * На входе можно указать массив категорий, которые пользователь уже выбрал -
	 * таких категорий не будет в клавиатуре.
	 * И если этот массив не пуст, то в клавиатуру будет добавлена кнопка "Я закончил выбор категорий"
	 *
	 * @param array|array $existingCategories
	 * @return type
	 */
	protected function generateCategoryKeyboard(array $existingCategories = [])
	{
		# нужные нам ключи, которые мы хотим показать
		$categories = CategoryService::getTgCategories();
		$buttonsArray = [];

		foreach ($categories as $key => $title) {

			# проверяем, входит ли данная категория в уже выбранные пользователем
			if (in_array($key, $existingCategories)) {
				$text = self::CHECK . ' ' . $title;
				$callbackData = 'sr_cat_rem_' . $key;
			} else {
				$text = $title;
				$callbackData = 'sr_cat_add_' . $key;
			}

			$button = Keyboard::inlineButton([
				'text' =>  $text,
				'callback_data' => $callbackData,
			]);

			$buttonsArray[] = $button;
		}

		$rowsArray = array_chunk($buttonsArray, 2, true);
		$keyboard = Keyboard::make()->inline();

		foreach ($rowsArray as $rowArray) {
			$keyboard->row(...$rowArray);
		}


		# если пользователь к этому моменту выбрал хотя бы одну категорию,
		# добавляем последней строкой кнопку "я выбрал нужные категории, давайте дальше"
		if (count($existingCategories) > 0) {
			$keyboard->row(
				Keyboard::inlineButton([
					'text' => 'Готово',
					'callback_data' => 'sr_cat_done',
				])
			);
		}


		return $keyboard;
	}



	/**
	 * Генерирует клавиатуру со списком федеральных округов.
	 * @return type
	 */
	protected function generateFederalDistrictKeyboard(bool $showDoneButton)
	{
		$districts = RegionService::getDistricts();


		$buttonsArray = [];
		foreach ($districts as $key => $title) {

			$button = Keyboard::inlineButton([
				'text' => $title,
				'callback_data' => 'sr_goto_distr_' . $key,
			]);

			$buttonsArray[] = $button;
		}

		$rowsArray = array_chunk($buttonsArray, 3, true);
		$keyboard = Keyboard::make()->inline();

		foreach ($rowsArray as $rowArray) {
			$keyboard->row(...$rowArray);
		}


		# если пользователь к этому моменту выбрал хотя бы одну категорию,
		# добавляем последней строкой кнопку "я выбрал нужные категории, давайте дальше"
		if ($showDoneButton) {
			$keyboard->row(
				Keyboard::inlineButton([
					'text' => 'Готово',
					'callback_data' => 'sr_reg_done',
				])
			);
		}


		return $keyboard;
	}


	/**
	 * Генерирует клавиатуру с регионами данного округа.
	 *
	 * Если указан параметр alreadyChosenRegions, то по таким регионам кнопки не создаются
	 *
	 * @param string $districtCode
	 * @param array|array $alreadyChosenRegions
	 * @return type
	 */
	protected function generateRegionsKeyboard(string $districtCode, array $chosenRegions = [])
	{
		$regions = RegionService::getRegionsByDistrictCode($districtCode);


		$buttonsArray = [];
		foreach ($regions as $key => $title) {

			# проверяем, входит ли данная категория в уже выбранные пользователем
			if (in_array($key, $chosenRegions)) {
				$text = self::CHECK . ' ' . $title;
				$callbackData = 'sr_reg_rem_' . $key;
			} else {
				$text = $title;
				$callbackData = 'sr_reg_add_' . $key;
			}

			$button = Keyboard::inlineButton([
				'text' =>  $text,
				'callback_data' => $callbackData,
			]);

			$buttonsArray[] = $button;
		}

		$rowsArray = array_chunk($buttonsArray, 3, true);
		$keyboard = Keyboard::make()->inline();

		foreach ($rowsArray as $rowArray) {
			$keyboard->row(...$rowArray);
		}


		# добавляем последней строкой кнопку "Назад", возвращающую обратно к списку округов
		$anotherButtonsArray = [];
		$anotherButtonsArray[] = Keyboard::inlineButton([
			'text' => 'Назад',
			'callback_data' => 'sr_distr_back',
		]);

		# и если пользователь уже выбрал хотя бы один регион,
		# добавляем также кнопку "я выбрал нужные категории, давайте дальше"
		if (count($chosenRegions) > 0) {
			$anotherButtonsArray[] = Keyboard::inlineButton([
				'text' => 'Готово',
				'callback_data' => 'sr_reg_done',
			]);
		}


		$keyboard->row(...$anotherButtonsArray);

		return $keyboard;
	}


	/**
	 * Формирует текстовое описание диапазона начальной цены на основании заданных мин. и макс. цены
	 * @param type $minStartPrice
	 * @param type $maxStartPrice
	 * @return type
	 */
	protected function getStartPriceText($minStartPrice, $maxStartPrice)
	{
		return PriceService::getPriceText($minStartPrice, $maxStartPrice);
	}

}
