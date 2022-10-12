<?php

namespace app\efrsb\services;

use app\efrsb\models\SyncLog;
use app\services\TelegramService;
use \DateTime;


class CronService
{

	private $parser;


	public function __construct(ParseService $parser)
	{
		$this->parser = $parser;
	}


	/**
	 * Запускает синхронизацию с ЕФРСБ по крону с учетом контроля предыдущего выполнения
	 *
	 * @param int $period период запуска по крону в минутах
	 * @param string $intervalBeginning время UTC, с какого момента надо начать синхронизацию
	 * @return type
	 */
	public function start(string $intervalBeginningStr = null, string $intervalEndStr = null)
	{

		# находим запись о последней синхронизации
		$lastSync = SyncLog::findLast();

		# если есть предыдущая синхронизация, и она не завершилась, то прекращаем выполнение
		if (!is_null($lastSync) && ($lastSync->isRunning)) {

			echo 'В записи о предыдущей синхронизации указан статус "Запущено". Возможно, предыдущая синхронизация еще не завершила работу. PID: ' . $lastSync->pid . PHP_EOL;
			return;

			throw new \LogicException();
		}


		# определяемся с началом интервала синхронизации
		if ($intervalBeginningStr) {

			# @todo: добавить проверку корректности строки
			# если установлен параметр intervalBeginning, используем его. Тут делать ничего не надо
			$format = 'Y-m-d\TH:i:s';
		} elseif ($lastSync) {

			# если есть запись о предыдущей синхронизации, используем ее
			$intervalBeginningStr = $lastSync->intervalEnd;
			$format = 'Y-m-d H:i:s';
		} else {

			# иначе бросаем исключение
			throw new \RuntimeException('Не удалось определить начало интервала синхронизации по крону. Запись о предыдущей ' .
				'синхронизации отсутствует, и не задано начало интервала');
		}

		$intervalBeginningDT = DateTime::createFromFormat($format, $intervalBeginningStr);
		if (false === $intervalBeginningDT) {
			throw new \RuntimeException('Не удалось создать объект DateTime из строки ' . $intervalBeginningStr . ', формат ' . $format);
		}


		# аналогично определяемся с концом интервала синхронизации.
		if ($intervalEndStr) {

			$intervalEndDT = DateTime::createFromFormat('Y-m-d\TH:i:s', $intervalEndStr);
		} else {
			$intervalEndDT = $this->getRoundedNowTime();
			$intervalEndStr = $intervalEndDT->format('Y-m-d\TH:i:s');
		}
		# в качестве времени окончания интервала используем текущее время, округленное в пол до периода

		# создаем запись в логе, чтобы следующий скрипт знал, что сейчас идет синхронизация
		$sync = new SyncLog();
		$sync->status = SyncLog::STATUS_RUNNING;
		$sync->intervalBeginning = $intervalBeginningStr;
		$sync->intervalEnd = $intervalEndStr;
		$sync->pid = getmypid();
		$sync->saveEx();

		# запускаем синхронизацию.
		# $report - это объект ReportData, который возвращает actualizeTrades.
		# в нем можно указать
		# количество лотов, по которым были сообщения
		# кол-во новых лотов
		# кол-во лотов, по которым были отправлены уведомления
		# кол-во отправленных уведомлений
		# длительность синхронизации
		# промежуток времени, за который происходила синхронизация
		// $report = $this->parseService->actualizeTrades($lastSyncEndTime, $roundNowTime, null, null, true);
		try {
			$this->parser->actualizeTrades($intervalBeginningDT, $intervalEndDT, null, null, true);
		} catch (\Throwable $e) {

			# сначала сохраняем статус (ошибка) и устанавливаем error = заглушку.
			$sync->status = SyncLog::STATUS_FAILED;
			$sync->error = 'Возникла ошибка. Подождите...';
			$sync->saveEx();


			# оповещаем об ошибке
			$message = $e->getMessage();
			TelegramService::notify(mb_substr($message, 0, 500));


			# теперь сохраняем сообщение об ошибке. Если оно превышает 16383 символов, обрезаем его
			if (mb_strlen($message) > 16383) {
				$message = mb_substr($message, 0, 16383);
			}

			$sync->error = $message;
			$sync->saveEx();
			throw $e;
		}

		// $sync->addReportData($report);
		$sync->status = SyncLog::STATUS_FINISHED;
		$sync->saveEx();
	}


	/**
	 * Возвращает текущее время, округленное до нижнего значения в зависимости от интервала.
	 * @param int $period
	 * @return type
	 */
	private function getRoundedNowTime(): DateTime
	{
		return $this->roundDownToMinuteInterval(new DateTime(), 1);
	}


	/**
	 * Округляет время к нижнему значению в зависимости от интервала.
	 *
	 * Например, если задано время 2018-02-20 22:57:46, и интервал 10, то получим 2018-02-20 22:50:00
	 *
	 * @param DateTime $dateTime
	 * @param type|int $minuteInterval
	 * @return type
	 * @see https://ourcodeworld.com/articles/read/756/how-to-round-up-down-to-nearest-10-or-5-minutes-of-datetime-in-php
	 */
	private function roundDownToMinuteInterval(DateTime $dateTime, int $minuteInterval): DateTime
	{
		return $dateTime->setTime(
			$dateTime->format('H'),
			floor($dateTime->format('i') / $minuteInterval) * $minuteInterval,
			0
		);
	}
}
