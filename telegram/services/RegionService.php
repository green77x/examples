<?php

namespace app\telegram\services;

use app\telegram\exceptions\UnknownRegionException;


/**
 * Отвечает за работу с округами и регионами в боте.
 *
 * В том числе за связи между ними - получить регионы по указанному округу, или получить округ по указанному региону.
 */
final class RegionService
{

	private static $data = [

		'centr' => [
			'title' => 'Центр',
			'regions' => [
				'77' => 'Москва',
				'50' => 'Московская обл.',
				'31' => 'Белгородская обл.',
				'32' => 'Брянская обл.',
				'33' => 'Владимирская обл.',
				'36' => 'Воронежская обл.',
				'37' => 'Ивановская обл.',
				'40' => 'Калужская обл.',
				'44' => 'Костромская обл.',
				'46' => 'Курская обл.',
				'48' => 'Липецкая обл.',
				'57' => 'Орловская обл.',
				'62' => 'Рязанская обл.',
				'67' => 'Смоленская обл.',
				'68' => 'Тамбовская обл.',
				'69' => 'Тверская обл.',
				'71' => 'Тульская обл.',
				'76' => 'Ярославская обл.',
			],
		],

		'north_west' => [
			'title' => 'Северо-Запад',
			'regions' => [
				'78' => 'Санкт-Петербург',
				'47' => 'Ленинградская обл.',
				'29' => 'Архангельская обл.',
				'35' => 'Вологодская обл.',
				'39' => 'Калининградская обл.',
				'10' => 'Респ. Карелия',
				'11' => 'Респ. Коми',
				'51' => 'Мурманская обл.',
				'83' => 'Ненецкий АО',
				'53' => 'Новгородская обл.',
				'60' => 'Псковская обл.',

			],
		],

		'volga' => [
			'title' => 'Приволжье',
			'regions' => [
				'2' => 'Респ. Башкортостан',
				'43' => 'Кировская обл.',
				'12' => 'Респ. Марий Эл',
				'13' => 'Респ. Мордовия',
				'52' => 'Нижегородская обл.',
				'56' => 'Оренбургская обл.',
				'58' => 'Пензенская обл.',
				'59' => 'Пермский край',
				'63' => 'Самарская обл.',
				'64' => 'Саратовская обл.',
				'16' => 'Респ. Татарстан',
				'18' => 'Удмуртская респ.',
				'73' => 'Ульяновская обл.',
				'21' => 'Чувашская респ.',
			],
		],

		'south' => [
			'title' => 'Юг',
			'regions' => [
				'1' => 'Респ. Адыгея',
				'8' => 'Респ. Калмыкия',
				'91' => 'Респ. Крым',
				'23' => 'Краснодарский край',
				'30' => 'Астраханская обл.',
				'34' => 'Волгоградская обл.',
				'61' => 'Ростовская обл.',
				'92' => 'г. Севастополь',
			],
		],

		'syberia' => [
			'title' => 'Сибирь',
			'regions' => [
				'4' => 'Респ. Алтай',
				'17' => 'Респ. Тыва',
				'19' => 'Респ. Хакасия',
				'22' => 'Алтайский край',
				'24' => 'Красноярский край',
				'38' => 'Иркутская обл.',
				'42' => 'Кемеровская обл.',
				'54' => 'Новосибирская обл.',
				'55' => 'Омская обл.',
				'70' => 'Томская обл.',
			],
		],

		'far_east' => [
			'title' => 'Дальний Восток',
			'regions' => [
				'3' => 'Респ. Бурятия',
				'14' => 'Респ. Саха (Якутия)',
				'75' => 'Забайкальский край',
				'41' => 'Камчатский край',
				'25' => 'Приморский край',
				'27' => 'Хабаровский край',
				'28' => 'Амурская обл.',
				'49' => 'Магаданская обл.',
				'65' => 'Сахалинская обл.',
				'79' => 'Еврейская автономная обл.',
				'87' => 'Чукотский АО',
			],
		],

		'north_caucasus' => [
			'title' => 'Северный Кавказ',
			'regions' => [
				'5' => 'Респ. Дагестан',
				'6' => 'Респ. Ингушетия',
				'7' => 'Кабардино-Балкарская респ.',
				'9' => 'Карачаево-Черкесская респ.',
				'15' => 'Респ. Северная Осетия - Алания',
				'20' => 'Чеченская респ.',
				'26' => 'Ставропольский край',
			],
		],

		'ural' => [
			'title' => 'Урал',
			'regions' => [
				'45' => 'Курганская обл.',
				'66' => 'Свердловская обл.',
				'72' => 'Тюменская обл.',
				'74' => 'Челябинская обл.',
				'86' => 'Ханты-Мансийский АО - Югра',
				'89' => 'Ямало-Ненецкий АО',
			],
		],

		'other' => [
			'title' => 'Прочее',
			'regions' => [
				'99' => 'Иные территории, включая г.Байконур',
			],
		],
	];


	/**
	 * Возвращает все округи
	 * @return type
	 */
	public static function getDistricts()
	{
		$data = self::$data;
		$districts = [];
		foreach ($data as $key => $element) {
			$districts[$key] = $element['title'];
		}

		return $districts;
	}


	/**
	 * Возвращает массив с названиями регионов
	 *
	 * @param array $keys
	 * @return type
	 */
	public static function getRegionTitles(array $keys)
	{
		$titles = [];
		$allRegions = self::getAllRegions();
		\Yii::trace($allRegions);
		foreach ($keys as $key) {
			if (array_key_exists($key, $allRegions)) {
				$titles[] = $allRegions[$key];
			}
		}

		return $titles;
	}


	public static function getRegionTitle($key)
	{
		$allRegions = self::getAllRegions();
		if (array_key_exists($key, $allRegions)) {
			return $allRegions[$key];
		}

		return 'Неизвестный регион';
	}


	/**
	 * Возвращает все регионы в одном массиве
	 * @return type
	 */
	private static function getAllRegions()
	{
		$data = self::$data;
		$regions = [];

		foreach ($data as $regs) {
			$regions += $regs['regions'];
		}

		return $regions;
	}


	/**
	 * Возвращает массив регионов в зависимости от округа.
	 *
	 * Если указан параметр $existing, то такие регионы, выбранные пользователем, не будут возвращены.
	 * Это для того, чтобы не предлагать пользователю уже выбранные им регионы
	 *
	 * @param string $districtCode
	 * @return type
	 */
	public static function getRegionsByDistrictCode(string $districtCode)
	{
		if (array_key_exists($districtCode, self::$data)) {
			# регионы по данному округу
			return self::$data[$districtCode]['regions'];
		}

		return [];

	}



	/**
	 * Возвращает массив регионов в зависимости от округа.
	 *
	 * Если указан параметр $existing, то такие регионы, выбранные пользователем, не будут возвращены.
	 * Это для того, чтобы не предлагать пользователю уже выбранные им регионы
	 *
	 * @param string $districtCode
	 * @return type
	 */
	public static function getRegionsByDistrictCodeExceptExisting(string $districtCode, array $existing = [])
	{

		# регионы по данному округу
		$regions = self::$data[$districtCode]['regions'];

		foreach ($existing as $existingRegion) {
			if (array_key_exists($existingRegion, $regions)) {
				unset($regions[$existingRegion]);
			}
		}

		return $regions;

	}


	/**
	 * Возвращает код округа по коду региона
	 * @param string $regionCode
	 * @return type
	 */
	public static function getDistrictCodeByRegion(string $regionCode)
	{

		# берем каждый округ и перебираем - не в нем ли находится указанный регион?
		$data = self::$data;

		foreach ($data as $districtKey => $element) {

			if (self::isDistrictHasRegion($districtKey, $regionCode)) {
				return $districtKey;
			}
		}

		throw new UnknownRegionException($regionCode);
	}


	/**
	 * Определяет, входит ли в данный округ указанный регион
	 * @param string $districtCode
	 * @param string $regionCode
	 * @return type
	 */
	private static function isDistrictHasRegion(string $districtCode, string $regionCode)
	{
		$districtRegions = self::$data[$districtCode]['regions'];
		if (array_key_exists($regionCode, $districtRegions)) {
			return true;
		}

		return false;

	}
}
