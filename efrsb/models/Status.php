<?php

namespace app\efrsb\models;


/**
 * Статусы отдельных лотов и торгов
 */
class Status
{

	const BIDDING_DECLARED = 'BiddingDeclared';	// объявлены торги
	const APPLICATION_SESSION_STARTED = 'ApplicationSessionStarted';	// открыт прием заявок
	const APPLICATION_SESSION_END = 'ApplicationSessionEnd';	// прием заявок завершен
	const BIDDING_IN_PROCESS = 'BiddingInProcess';	// идут торги
	const FINISHED = 'Finished';	// завершенные
	const ANNUL = 'Annul';	// аннулированные
	const BIDDING_CANCELLED = 'BiddingCancelled';	// торги отменены
	const BIDDING_FAIL = 'BiddingFail';	// торги не состоялись
	const BIDDING_PAUSED = 'BiddingPaused'; 	// торги приостановлены


	public static function generateStatusColumnSchema()
	{
		$array = self::getStatusArray();
		foreach ($array as $key => $el) {
			$array[$key] = '\'' . $el . '\'';
		}

		return "enum(" . implode(', ', $array) . ")";
	}


	public static function getStatusArray()
	{
		return [
			self::BIDDING_DECLARED,
			self::APPLICATION_SESSION_STARTED,
			self::APPLICATION_SESSION_END,
			self::BIDDING_IN_PROCESS,
			self::FINISHED,
			self::ANNUL,
			self::BIDDING_CANCELLED,
			self::BIDDING_FAIL,
			self::BIDDING_PAUSED,
		];
	}


	public static function getStatusArrayWithDescription()
	{
		return [
			self::BIDDING_DECLARED => 'Объявлены торги',
			self::APPLICATION_SESSION_STARTED => 'Начат прием заявок',
			self::APPLICATION_SESSION_END => 'Прием заявок завершен',
			self::BIDDING_IN_PROCESS => 'Идут торги',
			self::FINISHED => 'Завершенные',
			self::ANNUL => 'Аннулированные',
			self::BIDDING_CANCELLED => 'Торги отменены',
			self::BIDDING_FAIL => 'Торги не состоялись',
			self::BIDDING_PAUSED => 'Торги приостановлены',
		];
	}


	public static function getTextByStatus(string $status)
	{
		return self::getStatusArrayWithDescription()[$status];
	}
}
