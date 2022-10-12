<?php

namespace app\efrsb\exceptions;


/**
 * Бросается при неизвестном коде региона. Например, см. в app\telegram\services\RegionService
 */
class UnknownRegionException extends \yii\base\Exception
{

	public function __construct(string $regionCode)
	{
		$mes = "Неизвестный код региона: $regionCode";
		parent::__construct($mes);
	}
}
