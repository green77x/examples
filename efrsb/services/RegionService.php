<?php 

namespace app\efrsb\services;


class RegionService 
{
	
	public static function getData()
	{
		return [
			'22' => 'Алтайский край',+
			'28' => 'Амурская область',+
			'29' => 'Архангельская область',+
			'30' => 'Астраханская область',+
			'31' => 'Белгородская область', +
			'32' => 'Брянская область', +
			'33' => 'Владимирская область', +
			'34' => 'Волгоградская область',+
			'35' => 'Вологодская область',+
			'36' => 'Воронежская область', +
			'77' => 'г. Москва', +
			'78' => 'г. Санкт-Петербург',+
			'92' => 'г. Севастополь',+
			'79' => 'Еврейская автономная область',+
			'75' => 'Забайкальский край',+
			'37' => 'Ивановская область', +
			'99' => 'Иные территории, включая г.Байконур',
			'38' => 'Иркутская область',+
			'7' => 'Кабардино-Балкарская Республика',+
			'39' => 'Калининградская область',+
			'40' => 'Калужская область', +
			'41' => 'Камчатский край',+
			'9' => 'Карачаево-Черкесская Республика',+
			'42' => 'Кемеровская область',+
			'43' => 'Кировская область',+
			'44' => 'Костромская область', +
			'23' => 'Краснодарский край',+
			'24' => 'Красноярский край',+
			'45' => 'Курганская область',+
			'46' => 'Курская область',+
			'47' => 'Ленинградская область',+
			'48' => 'Липецкая область',+
			'49' => 'Магаданская область',+
			'50' => 'Московская область', +
			'51' => 'Мурманская область',+
			'83' => 'Ненецкий автономный округ',+
			'52' => 'Нижегородская область',+
			'53' => 'Новгородская область',+
			'54' => 'Новосибирская область',+
			'55' => 'Омская область',+
			'56' => 'Оренбургская область',+
			'57' => 'Орловская область', +
			'58' => 'Пензенская область',+
			'59' => 'Пермский край',+
			'25' => 'Приморский край',+
			'60' => 'Псковская область',+
			'1' => 'Республика Адыгея',+
			'4' => 'Республика Алтай',+
			'2' => 'Республика Башкортостан',+
			'3' => 'Республика Бурятия',+
			'5' => 'Республика Дагестан',+
			'6' => 'Республика Ингушетия',
			'8' => 'Республика Калмыкия',+
			'10' => 'Республика Карелия',+
			'11' => 'Республика Коми',+
			'91' => 'Республика Крым',+
			'12' => 'Республика Марий Эл',+
			'13' => 'Республика Мордовия',+
			'14' => 'Республика Саха (Якутия)',+
			'15' => 'Республика Северная Осетия - Алания',+
			'16' => 'Республика Татарстан',+
			'17' => 'Республика Тыва',+
			'19' => 'Республика Хакасия',+
			'61' => 'Ростовская область',+
			'62' => 'Рязанская область', +
			'63' => 'Самарская область',+
			'64' => 'Саратовская область',+
			'65' => 'Сахалинская область',+
			'66' => 'Свердловская область',+
			'67' => 'Смоленская область',+
			'26' => 'Ставропольский край',+
			'68' => 'Тамбовская область',+
			'69' => 'Тверская область',+
			'70' => 'Томская область',+
			'71' => 'Тульская область',+
			'72' => 'Тюменская область',+
			'18' => 'Удмуртская Республика',+
			'73' => 'Ульяновская область',+
			'27' => 'Хабаровский край',+
			'86' => 'Ханты-Мансийский автономный округ - Югра',+
			'74' => 'Челябинская область',+
			'20' => 'Чеченская Республика',+
			'21' => 'Чувашская Республика - Чувашия',+
			'87' => 'Чукотский автономный округ',+
			'89' => 'Ямало-Ненецкий автономный округ',+
			'76' => 'Ярославская область',+
			'999' => 'Неизвестный регион',
		];
	}


	public function getCodeByTitle(string $title): int 
	{
		$code = array_search($title, self::getData());
		if (false === $code) {
		    $code = 999;
		}

		return $code;
	}


	public function getTitleByCode(string $code = ''): string 
	{
		return self::getData()[$code];
	}
}