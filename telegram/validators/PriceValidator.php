<?php

namespace app\telegram\validators;


class PriceValidator extends \yii\validators\Validator
{

	const PATTERN = '/^[\d]{1,10}$/';	// 7 1234567890


	public $message = '{attribute} должен содержать от 1 до 10 цифр. Получено значение: {value}';


	protected function validateValue($value)
	{
		if (preg_match(self::PATTERN, $value)) {
			return null;
		}

		return [$this->message, []];
	}
}
