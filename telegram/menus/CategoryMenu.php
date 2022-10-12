<?php

namespace app\telegram\menus;

use app\telegram\Telega,
	app\telegram\objects\MessageData,
	app\telegram\services\CategoryService;

use Telegram\Bot\Objects\{Update, Message, CallbackQuery};
use Telegram\Bot\Keyboard\Keyboard;


/**
 * Занимается обработкой шагов, связанных с настройкой категорий
 */
class CategoryMenu
{

	use \app\telegram\traits\PrefixTrait;


	# префикс, по которому можно понять, что команда относится к данному меню
	const PREFIX = 'categories_';

	# коды операций
	const INDEX = 'categories_index';
	const ADD = 'categories_add_';
	const REMOVE = 'categories_remove_';
	const DONE = 'categories_done';

	# символ выбора пользователя - жирная галочка
	# такой же символ есть в RegionMenu
	const CHECK_SYMBOL = "\xE2\x9C\x94";


	public function process(SettingsInterface $subject, CallbackQuery $query): MessageData
	{
		$data = $query->data;


		# если пользователь нажал кнопку "Категории" в главном меню, показываем ему список выбранных ему категорий
		if ($data == self::INDEX) {

			/*$text = 'Выберите нужные категории' . PHP_EOL;
			$keyboard = $this->generateCategoriesKeyboard($this->subscriber->categoriesArray);
			$this->telega->editMessage($query->message, $text, $keyboard);*/


			return $this->processIndex($subject, $query);
		}


		# если пользователь добавил категорию
		if ($this->hasPrefix($data, self::ADD)) {


			// $data = $query->data;
			// $user = $query->from;

			// # получаем номер категории
			// $categoryNumber = substr($data, 11);

			// # добавляем ее
			// $this->request->addCategory($categoryNumber);
			// $this->request->saveEx();

			// # изменяем сообщение бота, чтобы пользователю вывело перечень выбранных им категорий,
			// # а также показало кнопки с категориями, которые он еще может выбрать
			// $message = $query->message;


			// # генерируем текст для вывода пользователю, какие категории он выбрал
			// $text = 'Отлично! Давайте выберем категории лотов, по которым вы хотите получать уведомления.' . PHP_EOL . PHP_EOL;
			// $text .= $this->generateCategoriesList($this->request->categoriesArray);

			// # генерируем клавиатуру с учетом уже выбранных категорий. По этим категориям не будут создаваться кнопки
			// $keyboard = $this->generateCategoriesKeyboard($this->request->categoriesArray);

			// $this->telega->editMessageKeyboard($message, $keyboard);
			// $this->telega->answerCallbackQuery($query, 'Вы добавили категорию "' . CategoryService::getCategoryTitle($categoryNumber) . '"');

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
	 * Обрабатывает операцию отображения списка категорий.
	 *
	 * Категории, которые установлены у субъекта, отмечаются галочкой
	 * @param SettingsInterface $subject
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processIndex(SettingsInterface $subject, CallbackQuery $query): MessageData
	{
		$text = 'Выберите нужные категории' . PHP_EOL;
		$keyboard = $this->generateCategoriesKeyboard($subject->tgCategoriesArray);
		return new MessageData($text, $keyboard);
		// $this->telega->editMessage($query->message, $text, $keyboard);
	}



	/**
	 * Обрабатывает операцию добавления категории к данному субъекту.
	 *
	 * @param SettingsInterface $subject
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processAdd(SettingsInterface $subject, CallbackQuery $query): MessageData
	{
		$data = $query->data;

		# получаем номер категории
		$categoryNumber = $this->getSuffix($data, self::ADD);

		# добавляем ее
		$subject->addTgCategory($categoryNumber);

		// $text = $this->generateCategoriesList($subject->tgCategoriesArray);
		$keyboard = $this->generateCategoriesKeyboard($subject->tgCategoriesArray);
		return new MessageData('', $keyboard);
	}


	/**
	 * Обрабатывает операцию удаления категории у данного субъекта.
	 *
	 * @param SettingsInterface $subject
	 * @param CallbackQuery $query
	 * @return type
	 */
	private function processRemove(SettingsInterface $subject, CallbackQuery $query): MessageData
	{
		$data = $query->data;

		# получаем номер категории
		$categoryNumber = $this->getSuffix($data, self::REMOVE);

		# убираем ее
		$subject->removeTgCategory($categoryNumber);

		// $text = $this->generateCategoriesList($subject->tgCategoriesArray);
		$keyboard = $this->generateCategoriesKeyboard($subject->tgCategoriesArray);
		return new MessageData('', $keyboard);
	}


	/**
	 * @todo: скопировано из SubscribeRequestInteraction. Удалить там
	 * @param array $categories
	 * @return type
	 */
	private function generateCategoriesList(array $tgCategories): string
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


	/**
	 * Генерирует клавиатуру для выбора категорий.
	 *
	 * На входе можно указать массив категорий, которые пользователь уже выбрал -
	 * таких категорий не будет в клавиатуре.
	 * И если этот массив не пуст, то в клавиатуру будет добавлена кнопка "Я закончил выбор категорий"
	 *
	 * @param array|array $chosenTgCategories
	 * @return type
	 */
	private function generateCategoriesKeyboard(array $chosenTgCategories): Keyboard
	{
		# нужные нам ключи, которые мы хотим показать
		$categories = CategoryService::getTgCategories();
		$buttonsArray = [];

		foreach ($categories as $key => $title) {

			# проверяем, входит ли данная категория в уже выбранные пользователем
			if (in_array($key, $chosenTgCategories)) {
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

		$rowsArray = array_chunk($buttonsArray, 2, true);
		$keyboard = Keyboard::make()->inline();

		foreach ($rowsArray as $rowArray) {
			$keyboard->row(...$rowArray);
		}


		# если пользователь к этому моменту выбрал хотя бы одну категорию,
		# добавляем последней строкой кнопку "я выбрал нужные категории, давайте дальше"
		if (count($chosenTgCategories) > 0) {
			$keyboard->row(
				Keyboard::inlineButton([
					'text' => 'Готово',
					'callback_data' => self::DONE,
				])
			);
		}


		return $keyboard;
	}
}
