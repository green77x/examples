<?php

namespace app\telegram\exceptions;

use Telegram\Bot\Objects\Update;


class InvalidUpdateTypeException extends \yii\base\Exception
{

	public function __construct(Update $update)
	{
		$mes = 'Некорректный тип объекта Update. Ожидалось message или callback_query, получено ' . $update->detectType() .
			PHP_EOL . var_export($update, true);
		parent::__construct($mes);
	}
}
