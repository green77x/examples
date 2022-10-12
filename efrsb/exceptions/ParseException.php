<?php

namespace app\efrsb\exceptions;


/**
 * Исключение при парсинге XML, полученного от ЕФРСБ
 */
class ParseException extends BaseException
{

	public $progress;	// процент выполнения, на котором вылетело исключение


	public function __construct(
		$percent,
		$message = 'Исключение при выполнении парсинга сообщения от сервиса',
		$code = 0,
		$previousException = null)
	{
		$this->progress = $percent;
		parent::__construct($message, $code, $previousException);
	}
}
