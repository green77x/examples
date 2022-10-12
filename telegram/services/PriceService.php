<?php

namespace app\telegram\services;

use app\telegram\validators\PriceValidator;


class PriceService
{

	private static $data = [
		'0' => '0 руб.',
		'10000' => '10 тыс.',
		'20000' => '20 тыс.',
		'50000' => '50 тыс.',
		'100000' => '100 тыс.',
		'200000' => '200 тыс.',
		'500000' => '500 тыс.',
		'1000000' => '1 млн.',
		'2000000' => '2 млн.',
		'5000000' => '5 млн.',
		'more' => 'Свыше 5 млн.',
	];


	public static function getPriceText(?string $minPrice, ?string $maxPrice)
	{

		if ($minPrice && $maxPrice) {
			return "Начальная цена лота от $minPrice руб. до $maxPrice руб.";
		}

		if ($minPrice) {
			return "Начальная цена лота от $minPrice руб..";
		}

		if ($maxPrice) {
			return "Начальная цена лота до $maxPrice руб.";
		}

		return "Начальная цена лота - любая.";
	}


	public static function getPrices()
	{
		return self::$data;
	}


	public static function validate(string $price, string $error)
	{
		return (new PriceValidator())->validate($price, $error);
	}
}
