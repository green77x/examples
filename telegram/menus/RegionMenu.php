<?php

namespace app\telegram\menus;

use app\telegram\objects\MessageData,
	app\telegram\services\RegionService;

use Telegram\Bot\Objects\{Update, Message, CallbackQuery};
use Telegram\Bot\Keyboard\Keyboard;


/**
 * Занимается обработкой шагов, связанных с настройкой регионов
 */
class RegionMenu
{

	use \app\telegram\traits\PrefixTrait;


	# префикс, по которому можно понять, что команда относится к данному меню
	const PREFIX = 'regions_';

	# коды операций
	const DISTRICT_INDEX = 'regions_distict_index';
	const REGIONS_INDEX = 'regions_index_';
	const ADD = 'regions_add_';
	const REMOVE = 'regions_remove_';
	const DONE = 'regions_done';

	# символ выбора пользователя - жирная галочка
	# такой же символ есть в CategoryMenu
	const CHECK_SYMBOL = "\xE2\x9C\x94";



	public function process(SettingsInterface $subject, CallbackQuery $query): MessageData
	{
		$data = $query->data;


		# показываем индексную страницу со всеми федеральными округами
		if ($data == self::DISTRICT_INDEX) {

			/*$text = 'Выберите нужные категории' . PHP_EOL;
			$keyboard = $this->generateCategoriesKeyboard($this->subscriber->categoriesArray);
			$this->telega->editMessage($query->message, $text, $keyboard);*/


			return $this->processDistrictIndex($subject, $query);
		}


		# показываем страницу с регионами заданного федерального округа
		if ($this->hasPrefix($data, self::REGIONS_INDEX)) {

			return $this->processDistrictView($subject, $query);

		}


		# если пользователь добавил категорию
		if ($this->hasPrefix($data, self::ADD)) {

			return $this->processAdd($subject, $query);
		}


		# если пользователь убрал категорию
		if ($this->hasPrefix($data, self::REMOVE)) {

			return $this->processRemove($subject, $query);
		}


		# если пользователь закончил настройку категорий
		if ($this->hasPrefix($data, self::DONE)) {

			throw new \LogicException('Этот функционал должен быть реализован в вызывающем классе');
		}
	}


	/**
	 * Обрабатывает операцию отображения списка федеральных округов.
	 *
	 * @param SettingsInterface $subject
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processDistrictIndex(SettingsInterface $subject, CallbackQuery $query): MessageData
	{
		$keyboard = $this->generateDistrictsKeyboard($subject->regionsArray);
		return new MessageData('', $keyboard);
	}


	/**
	 * Обрабатывает операцию отображения списка регионов по конкретному федеральному округу
	 * @param SettingsInterface $subject
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processDistrictView(SettingsInterface $subject, CallbackQuery $query): MessageData
	{
		$districtCode = $this->getSuffix($query->data, self::REGIONS_INDEX);
		$keyboard = $this->generateRegionsKeyboard($districtCode, $subject->regionsArray);
		return new MessageData('', $keyboard);
	}



	/**
	 * Генерирует клавиатуру со списком регионов по указанному округу.
	 *
	 * Если задан параметр $chosenRegions, то такие регионы будут отмечены как выбранные.
	 * @param string $districtCode
	 * @param array $chosenRegions
	 * @return type
	 */
	private function generateRegionsKeyboard(string $districtCode, array $chosenRegions)
	{
		// $districtCode = $this->getSuffix($data, self::REGIONS_INDEX);
		$regions = RegionService::getRegionsByDistrictCode($districtCode);


		$buttonsArray = [];
		foreach ($regions as $key => $title) {

			# проверяем, входит ли данная категория в уже выбранные пользователем
			if (in_array($key, $chosenRegions)) {
				$text = self::CHECK_SYMBOL . ' ' . $title;
				$callbackData = self::REMOVE . $key;
			} else {
				$text = $title;
				$callbackData = self::ADD . $key;
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
			'callback_data' => self::DISTRICT_INDEX,
		]);

		# и если пользователь уже выбрал хотя бы один регион,
		# добавляем также кнопку "я выбрал нужные категории, давайте дальше"
		if (count($chosenRegions) > 0) {
			$anotherButtonsArray[] = Keyboard::inlineButton([
				'text' => 'Готово',
				'callback_data' => self::DONE,
			]);
		}


		$keyboard->row(...$anotherButtonsArray);

		return $keyboard;
	}





	/**
	 * Генерирует клавиатуру со списком федеральных округов.
	 * @return type
	 */
	private function generateDistrictsKeyboard(array $regionsArray)
	{
		$districts = RegionService::getDistricts();


		$buttonsArray = [];
		foreach ($districts as $key => $title) {

			$button = Keyboard::inlineButton([
				'text' => $title,
				'callback_data' => self::REGIONS_INDEX . $key,
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
		// if (count($existingCategories) > 0) {
		# если у субъекта есть хоть один выбранный регион, то в клавиатуре генерируем кнопку "Done"
		if (count($regionsArray) > 0) {
			$keyboard->row(
				Keyboard::inlineButton([
					'text' => 'Готово',
					'callback_data' => self::DONE,
				])
			);
		}
		// }


		return $keyboard;
	}



	/**
	 * Обрабатывает операцию добавления категории к данному субъекту.
	 *
	 * @param SettingsInterface $subject
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processAdd(SettingsInterface $subject, CallbackQuery $query)
	{
		$data = $query->data;

		# получаем номер региона
		$regionCode = $this->getSuffix($data, self::ADD);

		# добавляем его
		$subject->addRegion($regionCode);

		# получаем код округа, к которому этот регион принадлежит
		$districtCode = RegionService::getDistrictCodeByRegion($regionCode);

		// $text = $this->generateCategoriesList($subject->tgCategoriesArray);
		$keyboard = $this->generateRegionsKeyboard($districtCode, $subject->regionsArray);
		return new MessageData('', $keyboard);
	}


	/**
	 * Обрабатывает операцию удаления категории у данного субъекта.
	 *
	 * @param SettingsInterface $subject
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processRemove(SettingsInterface $subject, CallbackQuery $query)
	{
		$data = $query->data;

		# получаем номер региона
		$regionCode = $this->getSuffix($data, self::REMOVE);

		# убираем ее
		$subject->removeRegion($regionCode);

		# получаем код округа, к которому этот регион принадлежит
		$districtCode = RegionService::getDistrictCodeByRegion($regionCode);

		// $text = $this->generateCategoriesList($subject->tgCategoriesArray);
		$keyboard = $this->generateRegionsKeyboard($districtCode, $subject->regionsArray);
		return new MessageData('', $keyboard);
	}


	/**
	 * @todo: скопировано из SubscribeRequestInteraction. Удалить там
	 * @param array $categories
	 * @return type
	 */
	private function generateCategoriesList(array $tgCategories)
	{
		if (count($tgCategories) > 0) {

			$categoryTitles = CategoryService::getTgCategoryTitles($tgCategories);
			$text = 'Вами выбраны категории:' . PHP_EOL;

			foreach ($categoryTitles as $title) {
				$text .= '- ' . $title . PHP_EOL;
			}
		} else {
			$text = 'Не выбрана ни одна категория.' . PHP_EOL;
		}

		return $text;
	}
}
