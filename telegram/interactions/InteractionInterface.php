<?php

namespace app\telegram\interactions;


interface InteractionInterface
{
	/**
	 * В этом методе должна быть обработка поступившего от пользователя запроса.
	 * @return void
	 */
	public function process(): void;
}
