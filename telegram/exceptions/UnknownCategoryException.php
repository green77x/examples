<?php

namespace app\telegram\exceptions;


/**
 * Бросается при неизвестном коде ЕФРСБ-категории. Например, см. в app\telegram\services\CategoryService
 */
class UnknownCategoryException extends \yii\base\Exception
{

	public function __construct(string $efrsbCategory)
	{
		$mes = "Неизвестный код ЕФРСБ-категории: $efrsbCategory";
		parent::__construct($mes);
	}
}
