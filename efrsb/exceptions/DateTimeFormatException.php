<?php

namespace app\efrsb\exceptions;


class DateTimeFormatException extends \yii\base\Exception
{

	public function __construct($expectedFormat, $value)
	{
		parent::__construct("Неверный формат даты. Ожидалось: $expectedFormat, получено: $value");
	}
}
