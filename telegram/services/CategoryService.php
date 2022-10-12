<?php

namespace app\telegram\services;

use app\efrsb\services\ClassificationService;
use app\telegram\exceptions\UnknownCategoryException;


/**
 * Отвечает за работу с категориями в боте.
 *
 * В том числе обеспечивает связь категорий в боте и реальных категорий ЕФРСБ.
 * Например, к категории "Коммерческая недвижимость" в боте могут относиться несколько категорий ЕФРСБ.
 */
final class CategoryService
{

	/**
	 * Обозначает связь между категориями в боте и категориями в ЕФРСБ.
	 * К одной категории в боте может относиться 1 или более категорий ЕФРСБ.
	 */
	private static $data = [
		'cars' => [
			'title' => 'Автомобили',
			'efrsb' => ['0106008'],
		],
		'motorcycles' => [
			'title' => 'Мотоциклы',
			'efrsb' => ['0106001'],
		],
		'water_vehicles' => [
			'title' => 'Водный транспорт',
			'efrsb' => ['0106005'],
		],
		'house_re' => [
			'title' => 'Жилая недвижимость',
			'efrsb' => ['0101016', '0101017'],
		],
		'commerc_re' => [
			'title' => 'Коммерческая недвижимость',
			'efrsb' => ['0101001', '0101002', '0101003', '0101004', '0101005', '0101006', '0101007', '0101008'],
		],
	];


	/**
	 * Возвращает массив категорий для бота
	 *
	 * Автомобили
	 * Мотоциклы
	 * Водные средства
	 * Жилая недвижка
	 * Коммерческая недвижка
	 * Оборудование
	 *
	 * @return type
	 */
	// public static function getTgCategories()
	// {
	// 	$categories = self::$data;
	// 	$array = [];
	// 	foreach ($categories as $key => $category) {
	// 		$array[$key] = $category['title'];
	// 	}

	// 	return $array;
	// }


	/**
	 * Возвращает массив с названиями категорий
	 *
	 * @param array $keys
	 * @return type
	 */
	public static function getTgCategoryTitles(array $keys)
	{
		$titles = [];
		foreach ($keys as $key) {
			$titles[] = self::getTgCategoryTitle($key);
			// if (array_key_exists($key, self::$data)) {
			// 	$titles[] = self::$data[$key]['title'];
			// }
		}

		return $titles;
	}


	public static function getTgCategoryTitle($key)
	{
		if (array_key_exists($key, self::$data)) {
			return self::$data[$key]['title'];
		}

		return 'Неизвестная категория';
	}


	/**
	 * Возвращает массив категорий, за исключением уже выбранных пользователем.
	 * @param array|array $existingCategories
	 * @return type
	 */
	public static function getTgCategories(array $existingCategories = [])
	{
		$data = self::$data;
		$categories = [];
		foreach ($data as $key => $category) {
			$categories[$key] = $category['title'];
		}

		foreach ($existingCategories as $key) {
			unset($categories[$key]);
		}

		return $categories;
	}


	/**
	 * Возвращает массив из категорий ЕФРСБ по категориям бота.
	 *
	 * Например, если в параметре указываем ['cars', 'mororcycles'], в ответе получаем ['0106008', '0106001'].
	 * @param array $botCategories
	 * @return type
	 */
	public static function getEfrsbCategories(array $tgCategories)
	{
		$efrsbCategories = [];

		# на всякий случай оставляю только уникальные значения
		$tgCategories = array_unique($tgCategories);


		foreach ($tgCategories as $tgCat) {

			# @todo: здесь, скорее всего, можно сделать через array_merge
			$categories = self::$data[$tgCat]['efrsb'];
			$efrsbCategories += $categories;
		}

		return $efrsbCategories;


		/*
		$realCategories = [];
		foreach ($requestCategories as $req) {
			switch ($req) {
				case 'house_re':
					$realCategories[] = '0101016';
					break;

				case 'commerc_re':
					$realCategories[] = '0101007';
					$realCategories[] = '0101006';
					break;

				case 'cars':
					$realCategories[] = '0106008';
					break;

				case 'receivables':
					$realCategories[] = '0301';
					break;

				case 'other':
					$realCategories[] = '99';
					break;
			}
		}


		return $realCategories;
		*/
	}


	/**
	 * Возвращает категорию лота в Телеграме по категории лота в ЕФРСБ
	 * @param string $efrsb
	 * @return type
	 */
	public static function getTgCategoryByEfrsbCategory(string $efrsbCategory)
	{
		# берем каждую Телеграм-категорию и перебираем - не в неё ли находится указанная ЕФРСБ-категория?
		echo $efrsbCategory . PHP_EOL;
		$data = self::$data;

		foreach ($data as $tgCategoryKey => $element) {

			if (self::isTgCategoryHasEfrsbCategory($tgCategoryKey, $efrsbCategory)) {
				return $tgCategoryKey;
			}
		}

		return null;
	}


	/**
	 * Определяет, принадлежит ли данная ЕФРСБ-категория указанной Телеграм-категории
	 * @param string $districtCode
	 * @param string $regionCode
	 * @return type
	 */
	private static function isTgCategoryHasEfrsbCategory(string $tgCategory, string $efrsbCategory)
	{
		echo 'Поиск внутри ' . $tgCategory . ' ефрсб ' . $efrsbCategory . PHP_EOL;
		$efrsbCategories = self::$data[$tgCategory]['efrsb'];
		var_dump($efrsbCategories) . PHP_EOL;
		if (in_array($efrsbCategory, $efrsbCategories)) {
			return true;
		}

		return false;
	}

}
