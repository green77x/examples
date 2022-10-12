<?php

namespace app\efrsb\services;

use app\efrsb\models\{Trade, TradePlace, Lot, Status, AuctionMessage, AuctionMessageLot, LegalCase, Person, Company};
use app\exceptions\ActiveRecordSaveException;
use app\efrsb\exceptions\{
	AnnulmentMessageWithoutPairException,
	DateTimeFormatException,
	MessageNotFoundApiException,
	ParseException,
	TooManyMessagesException,
	UnexpectedFirstMessageException,
	UnknownAuctionMessageTypeException,
	WrongMessageOrderException,
};
use app\services\{TextService, Log};
use yii\helpers\Console;
use Yii;


/**
 * Класс ParseService - занимается обработкой XML-сообщений, полученных от Апи ЕФРСБ
 * Принимает разные типы сообщений - BiddingInvitation и проч.
 */
class ParseService
{

	use \app\traits\PrintMessageTrait;

	const MAX_MESSAGES_COUNT = 100000;

	const CREATE_TRADE = 'create';
	const UPDATE_TRADE = 'update';


	const MESSAGE_BIDDING_INVITATION = 'BiddingInvitation';
	const MESSAGE_APPLICATION_SESSION_START = 'ApplicationSessionStart';
	const MESSAGE_APPLICATION_SESSION_END = 'ApplicationSessionEnd';
	const MESSAGE_BIDDING_START = 'BiddingStart';
	const MESSAGE_BIDDING_PROCESS_INFO = 'BiddingProcessInfo';
	const MESSAGE_BIDDING_END = 'BiddingEnd';
	const MESSAGE_APPLICATION_SESSION_STATISTIC = 'ApplicationSessionStatistic';
	const MESSAGE_BIDDING_RESULT = 'BiddingResult';
	const MESSAGE_BIDDING_CANCEL = 'BiddingCancel';
	const MESSAGE_BIDDING_FAIL = 'BiddingFail';
	const MESSAGE_ANNULMENT = 'AnnulmentMessage';
	const MESSAGE_ANNULLED = 'AnnulledMessage';	# мой тип сообщения - аннулированное
	const MESSAGE_BIDDING_PAUSE = 'BiddingPause';
	const MESSAGE_BIDDING_RESUME = 'BiddingResume';


	private $api;


	public function __construct(ApiService $api)
	{
		$this->api = $api;
	}



	/**
	 * Получает торговые сообщения за указанный период времени.
	 * @return type
	 */
	public function actualizeTrades(
		\DateTime $startParsingDateTime,
		\DateTime $endParsingDateTime,
		?string $tradeEtpId,
		?string $etpInn,
		bool $telegramNotifyFlag
	) {

		$this->printInfo('Запущен парсинг за период ' . $startParsingDateTime->format('Y-m-d\TH:i:s') . ' : ' . $endParsingDateTime->format('Y-m-d\TH:i:s'), __METHOD__);
		# Получаем все сообщения за указанный период
		if (is_null($tradeEtpId) && is_null($etpInn)) {
			$tradePlacesXml = $this->api->getTradeMessages($startParsingDateTime, $endParsingDateTime);
		} elseif (!is_null($tradeEtpId) && !is_null($etpInn)) {
			$tradePlacesXml = $this->api->getTradeMessagesByTrade($tradeEtpId, $etpInn, $startParsingDateTime, $endParsingDateTime);
		} else {
			throw new \InvalidArgumentException("Параметры \$tradeEtpId и \$etpInn должны быть оба null или не null. Получено: $tradeEtpId, $etpInn");
		}


		# подсчитываем количество торгов
		$tradeCount = 0;

		foreach ($tradePlacesXml->TradePlace as $tp) {
			foreach ($tp->TradeList->Trade as $tr) {
				$tradeCount++;
			}
		}


		# проходимся по ЭТП
		$tradeCounter = 0;

		foreach ($tradePlacesXml->TradePlace as $tp) {
			$this->stdout('ЭТП: ' . $tp['Name'] . ', ИНН: ' . $tp['INN'] . PHP_EOL);

			# ищем такую ЭТП у нас в базе. Если нет, то создаем
			if (is_null($tradePlace = TradePlace::findOne(['inn' => (string)$tp['INN']]))) {
				$tradePlace = TradePlace::create(
					(string)$tp['INN'],
					(string)$tp['Name'],
					(string)$tp['Site']
				);

				$tradePlace->saveEx();
				$this->stdout("Сохранена новая ЭТП: $tradePlace->title" . PHP_EOL, Console::FG_GREEN);
			}


			# проходимся по торгам данной ЭТП
			foreach ($tp->TradeList->Trade as $tr) {

				$this->stdout('-- ' . $tradeCounter . ' из ' . $tradeCount . '. Торги ' . $tr['ID_External'] . ' (' . $tradePlace->title . ' ИНН ' . $tradePlace->inn . ')' . PHP_EOL);

				try {
					$this->actualizeTrade($tr, $tradePlace, $startParsingDateTime, $endParsingDateTime, $telegramNotifyFlag);
				} catch (\Throwable $e) {

				}
				$tradeCounter++;

			}
		}

		$this->printInfo('Завершен парсинг за период ' . $startParsingDateTime->format('Y-m-d\TH:i:s') . ' : ' . $endParsingDateTime->format('Y-m-d\TH:i:s') . PHP_EOL . 'Обработано ' . $tradeCounter . ' торгов.', __METHOD__);
	}


	/**
	 * Description
	 * @param \SimpleXMLElement $tr группа сообщений по торгам
	 * @return type
	 */
	public function actualizeTrade(
		\SimpleXMLElement $tr,
		TradePlace $tradePlace,
		\DateTime $startParsingDateTime,
		\DateTime $endParsingDateTime,
		bool $telegramNotifyFlag
	) {

		$etpId = (string)$tr['ID_External'];

		# сейчас нужно получить XML-объект сообщений, которые мы будем обрабатывать по указанным торгам.
		# Это могут быть сообщения от startDate до endDate;
		# это могут быть сообщения от начала 2017 или 2015 года до endDate - если торги новые;
		# и это могут быть сообщения от даты последнего парсинга торгов до endDate - если торги уже есть в базе,
		# и по ним раньше производили парсинг


		# проверяем, есть ли у нас в базе такие торги
		$trade = Trade::find()
			->andWhere(['etpId' => $etpId])
			->andWhere(['tradePlaceId' => $tradePlace->id])
			->one();

		if ($trade && $trade->isDefect) {
			$this->printWarning('Торги ' . $etpId . ': установлен флаг defect, не синхронизируем.', __METHOD__);
			return;
		}

		try {
			# если таких торгов нет, запрашиваем по ним все сообщения, начиная с 2017 года по указанное время окончания
			if (is_null($trade)) {
				$time1 = time();
				$this->stdout('Получаем список сообщений... ');

				$allTradeMessagesXml = $this->api->getTradeMessagesByTrade(
					$etpId,
					$tradePlace->inn,
					\DateTime::createFromFormat('Y-m-d\TH:i:s', '2017-01-01T00:00:00'),
					$endParsingDateTime
				);
				$tr = $allTradeMessagesXml->TradePlace->TradeList->Trade;
				$this->stdout('Заняло ' . (time() - $time1) . ' секунд' . PHP_EOL);

				try {
					$this->parseTradeMessages($tr, $tradePlace, $trade, $endParsingDateTime, $telegramNotifyFlag);
				} catch (UnexpectedFirstMessageException $e) {
					$this->printWarning($e->getMessage() . PHP_EOL . 'Пробуем запросить сообщения начиная с 2015 года...' . PHP_EOL, __METHOD__);

					$time1 = time();
					$this->stdout('Получаем список сообщений...');

					$allTradeMessagesXml = $this->api->getTradeMessagesByTrade(
						$etpId,
						$tradePlace->inn,
						\DateTime::createFromFormat('Y-m-d\TH:i:s', '2015-01-01T00:00:00'),
						$endParsingDateTime
					);
					$tr = $allTradeMessagesXml->TradePlace->TradeList->Trade;
					$this->stdout('Заняло ' . (time() - $time1) . ' секунд' . PHP_EOL);
					$this->parseTradeMessages($tr, $tradePlace, $trade, $endParsingDateTime, $telegramNotifyFlag);

				}
			} else {

				# иначе - т.к. такие торги есть, надо убедиться, что нет разрыва между старыми сообщениями,
				# которые мы уже обрабатывали по ним, и новыми, которые мы только что получили.

				# для этого сравниваем время последнего сообщения по этим торгам, и начальное время текущего парсинга
				$lastParsingDateTime = $trade->getLastParsingDateTime();

				if (!is_null($lastParsingDateTime) && ($lastParsingDateTime < $startParsingDateTime)) {
					$tradeMessagesXml = $this->api->getTradeMessagesByTrade(
						$etpId,
						$tradePlace->inn,
						$lastParsingDateTime,
						$endParsingDateTime
					);
					$tr = $tradeMessagesXml->TradePlace->TradeList->Trade;
				}

				try {
					$this->parseTradeMessages($tr, $tradePlace, $trade, $endParsingDateTime, $telegramNotifyFlag);
				} catch (AnnulmentMessageWithoutPairException $e) {
					# это исключение может возникнуть, если мы ранее спарсили торги, все типа ок, но при текущем парсинге попалось
					# аннулирующее сообщение без пары (или аннулирующее сообщение на первом месте).
					# нужно уничтожить торги и спарсить их полностью заново

					Yii::error($e->getMessage(), AnnulmentMessageWithoutPairException::class);
					$this->printWarning($e->getMessage() . PHP_EOL, __METHOD__);

					# сохраняем идентификаторы торгов и лотов
					$tradeId = $trade->id;
					$lotIds = [];
					foreach ($trade->lots as $lot) {
						$lotIds[$lot->lotNumber] = $lot->id;
					}

					# удаляем торги
					$trade->delete();

					$time1 = time();
					$allTradeMessagesXml = $this->api->getTradeMessagesByTrade(
						$etpId,
						$tradePlace->inn,
						\DateTime::createFromFormat('Y-m-d\TH:i:s', '2017-01-01T00:00:00'),
						$endParsingDateTime
					);
					$tr = $allTradeMessagesXml->TradePlace->TradeList->Trade;
					$this->stdout('Заняло ' . (time() - $time1) . ' секунд' . PHP_EOL);

					try {
						$trade = $this->parseTradeMessages($tr, $tradePlace, null, $endParsingDateTime, $telegramNotifyFlag);
					} catch (UnexpectedFirstMessageException $e) {
						$this->printWarning($e->getMessage() . PHP_EOL . 'Пробуем запросить сообщения начиная с 2015 года...' . PHP_EOL, __METHOD__);

						$time1 = time();
						$this->stdout('Получаем список сообщений...');

						$allTradeMessagesXml = $this->api->getTradeMessagesByTrade(
							$etpId,
							$tradePlace->inn,
							\DateTime::createFromFormat('Y-m-d\TH:i:s', '2015-01-01T00:00:00'),
							$endParsingDateTime
						);
						$tr = $allTradeMessagesXml->TradePlace->TradeList->Trade;
						$this->stdout('Заняло ' . (time() - $time1) . ' секунд' . PHP_EOL);
						$trade = $this->parseTradeMessages($tr, $tradePlace, $trade, $endParsingDateTime, $telegramNotifyFlag);
					}

					# возвращаем идентификаторы для торгов и лотов
					if (!is_null($trade)) {
						$lots = $trade->lots;
						$trade->id = $tradeId;
						$trade->saveEx();
						foreach ($lots as $lot) {
							$lot->id = $lotIds[$lot->lotNumber];
							$lot->tradeId = $tradeId;
							$lot->saveEx();
						}
					}


				}
			}
		} catch (\Throwable $e) {
			// $this->printError($e->getMessage(), TooManyMessagesException::class);

			Log::logSyncError($etpId, $tradePlace->inn, $e->getMessage());
		}
	}



	/**
	 * Обрабатывает все сообщения по определенным торгам, начиная с первого.
	 * Вначале сортирует их по идентификатору сообщения, а потом обрабатывает по одному.
	 *
	 * От наличия третьего параметра - $trade - зависит, как должен себя вести метод -
	 * либо считать, что все эти сообщения - это полный список сообщений по торгам, которых пока что нет в нашей БД,
	 * либо, что это новые сообщения, которые нужно применить к уже имеющимся торгам
	 *
	 * @param \SimpleXMLElement $tradeXml XML-объект торгов с идентификаторами сообщений
	 * @param TradePlace $tradePlace ЭТП
	 * @param int $messageCounter
	 * @param int $messageCount
	 * @param int $startTime
	 * @return int измененный $messageCounter
	 */
	public function parseTradeMessages(
		\SimpleXMLElement $tradeXml,
		TradePlace $tradePlace,
		?Trade $trade,
		\DateTime $endParsingDateTime,
		bool $telegramNotifyFlag
	) {
		# проверяем третий аргумент - что это, Trade или null? А может, ни то, ни другое?
		if ($trade === null) {
			$scenario = self::CREATE_TRADE;
		} else {
			$scenario = self::UPDATE_TRADE;
		}


		# сначала сортируем сообщения по порядку их идентификатора
		$messageIdsArray = [];
		$count = 0;
		foreach ($tradeXml->MessageList->TradeMessage as $tm) {
			$messageIdsArray[] = (int)$tm['ID'];
			$count++;
		}

		# если количество сообщений превышает максимальное - кидаем исключение
		if ($count > self::MAX_MESSAGES_COUNT) {
			throw new TooManyMessagesException("Торги " . $tradeXml['ID_External'] . " Количество сообщений $count превышает максимально допустимое " .
				self::MAX_MESSAGES_COUNT);
		}

		sort($messageIdsArray, SORT_NUMERIC);
		$help = $messageIdsArray;


		# если это обновление существующих торгов, нужно убедиться, что последнее старое сообщение имеет идентификатор меньше,
		# чем первое из новых. Если это не так, то нужно вырезать из новых сообщений все сообщения с ранними идентификаторами
		if ($scenario == self::UPDATE_TRADE) {
			$lastOldMessageId = $trade->lastMessageId;
			reset($messageIdsArray);
			$firstNewMessageId = key($messageIdsArray);

			if ($lastOldMessageId >= $firstNewMessageId) {

				$tempArray = [];
				foreach ($messageIdsArray as $id) {
					if ($id <= $lastOldMessageId) {
						continue;
					}

					$tempArray[] = $id;
				}

				$messageIdsArray = $tempArray;
			}
		}


		# заносим сообщения в массив
		$time1 = time();
		$messagesArray = [];
		$i = 1;

		if (($count = count($messageIdsArray)) > 0) {

			echo "Получаем сообщения, $count штук: ";
			foreach ($messageIdsArray as $id) {

				$messageXml = $this->api->getTradeMessageContent($id);

				if ($i % 10 != 0) {
					echo '.';
				} else {
					echo $i;
				}

				foreach ($messageXml->children() as $type => $content) {
					$messagesArray[$id] = ['type' => $type, 'content' => $content];
					break;
				}

				$i++;

			}
			echo PHP_EOL;

			# сортируем сообщения по порядку их идентификаторов
			$time2 = time();


			# убираем аннулирующие и аннулируемые сообщения
			$time3 = time();
			$correctMessagesArray = [];
			$firstMessageFlag = true;

			foreach ($messagesArray as $id => $message) {
				$type = $message['type'];
				$content = $message['content'];


				# проверка на первое сообщение по новым торгам - оно должно быть BiddingInvitation
				# Если нет, то, скорее всего, первое сообщение было слишком давно, и оно не попало в запрашиваемый диапазон
				# Достаточно запустить парсинг по какой-то давней дате, например, начиная с 2015 года
				if ($firstMessageFlag && ($scenario == self::CREATE_TRADE) && ($type != self::MESSAGE_BIDDING_INVITATION)) {
					throw new UnexpectedFirstMessageException('Первое сообщение по новым торгам должно быть BiddingInvitation. Торги: ' .
						$tradeXml['ID_External'] . ', ЭТП: ' . $tradePlace->inn . ', сообщение :' . $id . PHP_EOL .
						var_export($help, true));
				}


				# проверка на сообщение об аннулировании
				if ($type != self::MESSAGE_ANNULMENT) {
					$correctMessagesArray[$id] = ['type' => $type, 'content' => $content];
				} else {

					# если аннулирующее сообщение на первом месте, нужно проверить, создаем мы торги или обновляем
					if ($firstMessageFlag) {
						# если создаем, то такого быть вообще не должно
						# на самом деле, сюда выполнение вообще не должно прийти, т.к. это мы проверяли выше -
						# первое сообщение на BiddingInvitation. Но пока оставлю
						if ($scenario == self::CREATE_TRADE) {
							throw new WrongMessageOrderException('Аннулирующее сообщение не должно быть на первом месте. Торги: ' .
								$tradeXml['ID_External'] . ', ЭТП: ' . $tradePlace->inn . ', сообщение :' . $id);
						} else {
							# временно оставляю здесь исключение-заглушку, но вообще это нормальная ситуация -
							# нужно в будущем сделать сброс торгов и репарсинг всех сообщений
							throw new AnnulmentMessageWithoutPairException('Аннулирующее сообщение на первом месте. Торги: ' .
								$tradeXml['ID_External'] . ', ЭТП: ' . $tradePlace->inn . ', сообщение :' . $id);
						}
					} else {
						# итак, попалось аннулирующее сообщение. Нужно узнать id аннулируемого сообщения, и сделать его
						# типом "Аннулируемое", независимо от его настоящего типа
						$annulledId = (int)$content->ID_Annulment;

						if (array_key_exists($annulledId, $correctMessagesArray)) {
							$correctMessagesArray[$id] = ['type' => $type, 'content' => $content];
							$correctMessagesArray[$annulledId]['type'] = self::MESSAGE_ANNULLED;
						} else {
							throw new AnnulmentMessageWithoutPairException('Аннулирующее сообщение без пары. Торги: ' . $tradeXml['ID_External'] . ', ЭТП: ' . $tradePlace->inn . ', сообщение :' . $id);
						}
					}
				}

				# убираем флаг первого сообщения
				if ($firstMessageFlag) {
					$firstMessageFlag = false;
				}
			}



			# применяем все полученные сообщения по порядку
			$time4 = time();
			foreach ($correctMessagesArray as $id => $message) {

				$this->stdout(' ---- Сообщение ' . $id);
				$this->stdout(': ' . $message['type'] . ', время: ' . $message['content']['EventTime'] . PHP_EOL);

				# обрабатываем сообщение
				try {
					$this->parseMessage(
						$message['type'],
						$message['content'],
						$id,
						$tradeXml,
						$tradePlace,
						$telegramNotifyFlag
					);
				} catch (ParseException $e) {
					if (YII_ENV_DEV) {
						file_put_contents(Yii::getAlias('@runtime') . '/xml/' . date('Y-m-d\TH:i:s') . '_' . $id . '.php',
							'<?php' . PHP_EOL . var_export($message['content'], true));
					}

					throw $e;
				}
			}

			$time5 = time();

			$timeA = $time2 - $time1;
			$timeB = $time5 - $time2;
			$timeC = $time5 - $time1;

			echo "Получение данных - $timeA с; обработка сообщений - $timeB с; всего - $timeC с." . PHP_EOL;
		} else {
			echo 'Нет новых сообщений' . PHP_EOL;
		}


		# итак, мы успешно обработали все сообщения.
		# устанавливаем у торгов дату парсинга
		if ($trade || $trade = Trade::findOne(['efrsbId' => (string)$tradeXml['ID_EFRSB']])) {
			$trade->updateLastParsingDate($endParsingDateTime);
			$trade->saveEx();
		}

		return $trade;
	}



	/**
	 * Вызывает подходящую функцию-обработчик для сообщения с типом $messageType и контентом $messageXml по торгам $trade
	 * Торги могут быть null, если их не существует в нашей БД. В таком случае будет взято в работу сообщение BiddingInvitation,
	 * а остальные типы не будут обрабатываться
	 *
	 * @param string $messageType тип сообщения (BiddingInvitation и проч.)
	 * @param SimpleXMLElement $messageXml XML-объект сообщения
	 * @param app\models\efrsb\Trade|null $trade ActiveRecord-объект торгов
	 * @param SimpleXMLElement $tradeXml XML-объект торгов
	 * @param app\models\efrsb\TradePlace $tradePlace ActiveRecord-объект торговой площадки
	 */
	private function parseMessage(
		string $messageType,
		?\SimpleXMLElement $messageXml,
		int $messageId,
		\SimpleXMLElement $tradeXml,
		TradePlace $tradePlace,
		bool $telegramNotifyFlag
	) {

		# ищем торги
		$trade = Trade::findOne(['efrsbId' => (integer)$tradeXml['ID_EFRSB']]);

		try {
			switch ($messageType) {

				# если BiddingInvitation - создаем торги и лоты
				case self::MESSAGE_BIDDING_INVITATION:
					$this->parseBiddingInvitationMessage($trade, $messageXml, $messageId, $tradeXml, $tradePlace, $telegramNotifyFlag);
					break;

				case self::MESSAGE_APPLICATION_SESSION_START:
					if (!is_null($trade)) {
						$this->parseApplicationSessionStartMessage($trade, $messageXml, $messageId);
					}
					break;

				case self::MESSAGE_APPLICATION_SESSION_END:
					if (!is_null($trade)) {
						$this->parseApplicationSessionEndMessage($trade, $messageXml, $messageId);
					}
					break;

				case self::MESSAGE_ANNULMENT:
					if (!is_null($trade)) {
						$this->parseAnnulmentMessage($trade, $messageId);
					}
					break;

				case self::MESSAGE_ANNULLED:
					if (!is_null($trade)) {
						$this->parseAnnulledMessage($trade, $messageId);
					}
					break;

					// throw new ParseException(0, 'Исключение при парсинге: попалось сообщение AnnulmentMessage');

				case self::MESSAGE_BIDDING_START:
					if (!is_null($trade)) {
						$this->parseBiddingStartMessage($trade, $messageXml, $messageId);
					}
					break;

				case self::MESSAGE_BIDDING_PROCESS_INFO:
					if (!is_null($trade)) {
						$this->parseBiddingProcessInfoMessage($trade, $messageXml, $messageId);
					}
					break;

				case self::MESSAGE_BIDDING_END:
					if (!is_null($trade)) {
						$this->parseBiddingEndMessage($trade, $messageXml, $messageId);
					}
					break;

				// case self::MESSAGE_APPLICATION_SESSION_STATISTIC:
				// 	# не обрабатываем
				// 	break;

				case self::MESSAGE_BIDDING_RESULT:
					if (!is_null($trade)) {
						$this->parseBiddingResultMessage($trade, $messageXml, $messageId);
					}
					break;

				case self::MESSAGE_BIDDING_CANCEL:
					if (!is_null($trade)) {
						$this->parseBiddingCancelMessage($trade, $messageXml, $messageId);
					}
					break;

				case self::MESSAGE_BIDDING_FAIL:
					if (!is_null($trade)) {
						$this->parseBiddingFailMessage($trade, $messageXml, $messageId);
					}
					break;

				case self::MESSAGE_BIDDING_PAUSE:
					if (!is_null($trade)) {
						$this->parseBiddingPauseMessage($trade, $messageXml, $messageId);
					}
					break;

				case self::MESSAGE_BIDDING_RESUME:
					if (!is_null($trade)) {
						$this->parseBiddingResumeMessage($trade, $messageXml, $messageId);
					}
					break;

				# принял решение сохранить хотя бы номер необрабатываемого сообщения. Это нужно, чтобы при повторном парсинге
				# не запрашивать это сообщение снова
				default:
					if (!is_null($trade)) {
						$this->parseDefaultMessage($trade, $messageId);
					}

					// $trade->saveEx();
					break;
			}

		} catch (\Throwable $e) {
			# если возникла ошибка при парсинге, проверяем - удалось ли сохранить торги в БД. Если да, пишем в них ошибку
			if ($trade = Trade::findOne(['efrsbId' => (integer)$tradeXml['ID_EFRSB']])) {
				$message = $e->getMessage() . ', file: ' . $e->getFile() . ', line: ' . $e->getLine();
				$this->printError($message, __METHOD__);
				$trade->updateErrorMessage($message);
				$trade->saveEx();
			} else {
				# если торгов нет, то вылетаем - чтобы не пропустить ошибку
				throw $e;
			}
		}
	}


	/**
	 * Обрабатывает сообщение BiddingInvitation - создает торги и лоты по нему
	 */
	private function parseBiddingInvitationMessage(
		?Trade $trade,
		\SimpleXMLElement $messageXml,
		int $messageId,
		\SimpleXMLElement $tradeXml,
		TradePlace $tradePlace,
		bool $telegramNotifyFlag
	) {

		$this->printInfo('Обработка BiddingInvitation', __METHOD__);
		file_put_contents(Yii::getAlias('@runtime/xml/biddingInvitation.php'), var_export($messageXml, true));

		// var_dump($messageXml); die();
		# получаем время заявки
		// $dateTime = new \DateTime((string)$messageXml['EventTime']);



		### СОЗДАНИЕ ТОРГОВ
		{
			if (is_null($trade) or !$trade->isPopulated) {

				# узнаем код должника - ИНН компании, ИНН физлица или СНИЛС физлица. По нему мы узнаем регион
				if (!empty($personInn = (string)$messageXml->Debtor->DebtorPerson['INN'])) {
					$debtorCode = $personInn;
				} elseif (!empty($personSnils = (string)$messageXml->Debtor->DebtorPerson['SNILS'])) {
					$debtorCode = $personSnils;
				} elseif (!empty($companyInn = (string)$messageXml->Debtor->DebtorCompany['INN'])) {
					$debtorCode = $companyInn;
				} else {
					$debtorCode = '';
					$this->printWarning('Неизвестный код должника по торгам ' . $tr['ID_EFRSB'], __METHOD__);
				}

				# получаем данные о должнике
				$debtor = $this->api->searchDebtorByCode($debtorCode)->DebtorList;

				# узнаем код региона должника
				$region = $debtor->DebtorCompany['Region'];

				if (is_null($region)) {
					$region = $debtor->DebtorPerson['Region'];
				}

				if (is_null($region)) {
					$this->printWarning('Не удалось установить регион должника. Возможно, должник не был найден. Его код: ' .
						$debtorCode, __METHOD__);
					$region = 'Неизвестный регион';
				}

				$regionCode = RegionService::getCodeByTitle($region);

				# получаем сообщение АУ об объявлении торгов, вытаскиваем из него описание
				$auctionMessageId = (string)$messageXml->IDEFRSB;

				if (is_null($trade)) {
					$trade = $tradePlace->createPrototypeTrade(
						(integer)$tradeXml['ID_EFRSB'],
						(string)$tradeXml['ID_External']
					);
				}

				try {
					$openFormTimeBegin = $this->formatDateTime((string)$messageXml->TradeInfo->OpenForm['TimeBegin']);
					$openFormTimeEnd = $this->formatDateTime((string)$messageXml->TradeInfo->OpenForm->TimeEnd);
					$closeFormTimeResult = $this->formatDateTime((string)$messageXml->TradeInfo->CloseForm['TimeResult']);
					$applicationTimeBegin = $this->formatDateTime((string)$messageXml->TradeInfo->Application['TimeBegin']);
					$applicationTimeEnd = $this->formatDateTime((string)$messageXml->TradeInfo->Application['TimeEnd']);
					$datePublishSMI = $this->formatDate((string)$messageXml->TradeInfo->DatePublishSMI);
					$datePublishEFIR = $this->formatDate((string)$messageXml->TradeInfo->DatePublishEFIR);
				} catch (DateTimeFormatException $e) {
					throw new ParseException(0, $e->getMessage(), 0, $e);
				}
				$isRepeat = (string)$messageXml->TradeInfo->ISRepeat;
				if ($isRepeat == 'true' or $isRepeat == 1) {
					$isRepeat = Trade::IS_REPEAT_YES;
				} else {
					$isRepeat = Trade::IS_REPEAT_NO;
				}


				$trade->populate([
					'populatedStatus' => Trade::POPULATED_YES,
					'auctionMessageId' => (string)$messageXml->IDEFRSB,
					'auctionType' => (string)$messageXml->TradeInfo['AuctionType'],
					'formPrice' => (string)$messageXml->TradeInfo['FormPrice'],
					'isRepeat' => $isRepeat,
					'regionCode' => $regionCode,
					'datePublishSMI' => $datePublishSMI,
					'datePublishEFIR' => $datePublishEFIR,
					'openFormTimeBegin'=> $openFormTimeBegin,
					'openFormTimeEnd'=> $openFormTimeEnd,
					'closeFormTimeResult' => $closeFormTimeResult,
					'applicationTimeBegin' => $applicationTimeBegin,
					'applicationTimeEnd' => $applicationTimeEnd,
					'applicationRules' => (string)$messageXml->TradeInfo->Application->Rules,
					'status' => Status::BIDDING_DECLARED,
				]);

				$trade->addNewMessageToHistory((string)$messageId);
				$trade->saveEx();
				// if ($trade->save()) {
					$this->printInfo('Сохранены новые торги: ' . $trade->efrsbId, __METHOD__);
				// } else {
				// 	throw new ParseException(0, 'Не удалось сохранить торги ' . $trade->efrsbId . ': ' .
				// 		implode(', ', $trade->getErrorSummary(true)));
				// }

			} else {
				$this->printWarning('Такие торги уже есть в базе...' . $tradeXml['ID_EFRSB'], __METHOD__);
			}
		}



		### СОХРАНЕНИЕ СУДЕБНОГО ДЕЛА
		{
			$caseNumber = (string)$messageXml->LegalCase['CaseNumber'];

			if (!$legalCase = LegalCase::findOne(['caseNumber' => $caseNumber])) {
				$legalCase = new LegalCase([
					'caseNumber' => $caseNumber,
					'courtName' => (string)$messageXml->LegalCase['CourtName'],
					'base' => (string)$messageXml->LegalCase['Base'],
				]);

				try {
					$legalCase->saveEx();
					$trade->legalCaseId = $legalCase->id;
					$this->printInfo('Сохранено новое судебное дело: ' . $legalCase->caseNumber, __METHOD__);
				} catch (ActiveRecordSaveException $e) {
					$trade->updateErrorMessage($e->getMessage());
					$this->printError('Не удалось сохранить новое судебное дело: ' . $legalCase->caseNumber . PHP_EOL .
						'Ошибки: ' . $e->getMessage() , __METHOD__);
				}

				// if ($legalCase->save()) {
				// } else {
				// 	throw new ParseException(0, 'Не удалось сохранить судебное дело ' . $legalCase->caseNumber . ': ' .
				// 		implode(', ', $legalCase->getErrorSummary(true)));
				// }
			} else {
				$trade->legalCaseId = $legalCase->id;
			}

			$trade->saveEx();

			// if (!$trade->save()) {
			// 	throw new ParseException(0, 'Не удалось обновить торги ' . $trade->efrsbId . ': ' .
			// 		implode(', ', $trade->getErrorSummary(true)));
			// }
		}



		### СОХРАНЕНИЕ ДОЛЖНИКА - ФИЗЛИЦА ИЛИ КОМПАНИИ
		{
			if ($xml = $messageXml->Debtor->DebtorPerson) {
				$debtorPerson = Person::findOne(['INN' => (string)$xml['INN']]);
				if (!$debtorPerson) {
					$debtorPerson = new Person([
						'firstName' => (string)$xml['FirstName'],
						'middleName' => (string)$xml['MiddleName'],
						'lastName' => (string)$xml['LastName'],
						'INN' => (string)$xml['INN'],
						'SNILS' => (string)$xml['SNILS'],
					]);
				}

				$debtorPerson->isDebtor = Person::TYPE_YES;
				try {
					$debtorPerson->saveEx();
					$trade->debtorPersonId = $debtorPerson->id;
					$this->printInfo('Установлен должник-физлицо: ' . $debtorPerson->INN, __METHOD__);
				} catch (ActiveRecordSaveException $e) {
					$trade->updateErrorMessage($e->getMessage());
					$this->printError('Не удалось сохранить должника-физлицо: ' . $debtorPerson->INN . PHP_EOL .
						'Ошибки: ' . $e->getMessage() , __METHOD__);
				}

				// if (!$debtorPerson->save()) {
				// 	throw new ParseException(0, 'Не удалось сохранить должника-физлицо ' . $debtorPerson->INN . ': ' .
				// 	implode(', ', $debtorPerson->getErrorSummary(true)));
				// }

				$trade->saveEx();


				// if ($trade->save()) {
				// 	$this->printInfo('Установлен должник-физлицо: ' . $debtorPerson->INN, __METHOD__);
				// } else {
				// 	throw new ParseException(0, 'Не удалось обновить торги ' . $trade->efrsbId . ': ' .
				// 		implode(', ', $trade->getErrorSummary(true)));
				// }
			} elseif ($xml = $messageXml->Debtor->DebtorCompany) {
				$debtorCompany = Company::findOne(['INN' => (string)$xml['INN']]);
				if (!$debtorCompany) {
					$debtorCompany = new Company([
						'fullName' => (string)$xml['FullName'],
						'shortName' => (string)$xml['ShortName'],
						'INN' => (string)$xml['INN'],
						'OGRN' => (string)$xml['OGRN'],
					]);
				}

				$debtorCompany->isDebtor = Company::TYPE_YES;
				try {
					$debtorCompany->saveEx();
					$trade->debtorCompanyId = $debtorCompany->id;
					$this->printInfo('Установлен должник-компания: ' . $debtorCompany->INN, __METHOD__);
				} catch (ActiveRecordSaveException $e) {
					$trade->updateErrorMessage($e->getMessage());
					$this->printError('Не удалось сохранить должника-компанию: ' . $debtorCompany->INN . PHP_EOL .
						'Ошибки: ' . $e->getMessage() , __METHOD__);
				}

				$trade->saveEx();

				// if (!$debtorCompany->save()) {
				// 	throw new ParseException(0, 'Не удалось сохранить должника-компанию ' . $debtorCompany->INN . ': ' .
				// 	implode(', ', $debtorCompany->getErrorSummary(true)));
				// }

				// $trade->debtorCompanyId = $debtorCompany->id;
				// if ($trade->save()) {
				// 	$this->printInfo('Установлен должник-компания: ' . $debtorCompany->INN, __METHOD__);
				// } else {
				// 	throw new ParseException(0, 'Не удалось обновить торги ' . $trade->efrsbId . ': ' .
				// 		implode(', ', $trade->getErrorSummary(true)));
				// }

			} else {
				throw new ParseException(0, 'В сообщении не удалось распознать должника - физлицо или компанию.');
			}
		}



		### СОХРАНЕНИЕ АРБИТРАЖНОГО УПРАВЛЯЮЩЕГО - ФИЗЛИЦА ИЛИ КОМПАНИИ (АСВ)
		{
			if ($xml = $messageXml->ArbitrManager) {
				$arbitrManagerPerson = Person::findOne(['INN' => (string)$xml['INN']]);
				if (!$arbitrManagerPerson) {
					$arbitrManagerPerson = new Person([
						'firstName' => (string)$xml['FirstName'],
						'middleName' => (string)$xml['MiddleName'],
						'lastName' => (string)$xml['LastName'],
						'INN' => (string)$xml['INN'],
						'SNILS' => (string)$xml['SNILS'],
						'arbitrSROName' => (string)$xml['SROName'],
						'arbitrRegNum' => (string)$xml['RegNum'],
					]);
				}

				$arbitrManagerPerson->isArbitrManager = Person::TYPE_YES;
				// if (!$arbitrManagerPerson->save()) {
				// 	throw new ParseException(0, 'Не удалось сохранить АУ-физлицо ' . $arbitrManagerPerson->INN . ': ' .
				// 	implode(', ', $arbitrManagerPerson->getErrorSummary(true)));
				// }

				try {
					$arbitrManagerPerson->saveEx();
					$trade->arbitrManagerPersonId = $arbitrManagerPerson->id;
					$this->printInfo('Установлен АУ-физлицо: ' . $arbitrManagerPerson->INN, __METHOD__);
				} catch (ActiveRecordSaveException $e) {
					$trade->updateErrorMessage($e->getMessage());
					$this->printError('Не удалось сохранить АУ-физлицо: ' . $arbitrManagerPerson->INN . PHP_EOL .
						'Ошибки: ' . $e->getMessage() , __METHOD__);
				}

				$trade->saveEx();

				// if ($trade->save()) {
				// 	$this->printInfo('Установлен АУ-физлицо: ' . $arbitrManagerPerson->INN, __METHOD__);
				// } else {
				// 	throw new ParseException(0, 'Не удалось обновить торги ' . $trade->efrsbId . ': ' .
				// 		implode(', ', $trade->getErrorSummary(true)));
				// }
			} elseif ($xml = $messageXml->CompanyBankrCommis) {
				$amCompany = Company::findOne(['INN' => (string)$xml['INN']]);
				if (!$amCompany) {
					$amCompany = new Company([
						'fullName' => (string)$xml['FullName'],
						'shortName' => (string)$xml['ShortName'],
						'INN' => (string)$xml['INN'],
						'OGRN' => (string)$xml['OGRN'],
					]);
				}

				$amCompany->isArbitrManager = Company::TYPE_YES;

				try {
					$amCompany->saveEx();
					$trade->arbitrManagerCompanyId = $amCompany->id;
					$this->printInfo('Установлен АУ-компания: ' . $amCompany->INN, __METHOD__);
				} catch (ActiveRecordSaveException $e) {
					$trade->updateErrorMessage($e->getMessage());
					$this->printError('Не удалось сохранить АУ-компанию: ' . $amCompany->INN . PHP_EOL .
						'Ошибки: ' . $e->getMessage() , __METHOD__);
				}

				$trade->saveEx();

				// if (!$amCompany->save()) {
				// 	throw new ParseException(0, 'Не удалось сохранить АУ-компанию ' . $amCompany->INN . ': ' .
				// 	implode(', ', $amCompany->getErrorSummary(true)));
				// }

				// $trade->arbitrManagerCompanyId = $amCompany->id;
				// if ($trade->save()) {
				// 	$this->printInfo('Установлен АУ-компания: ' . $amCompany->INN, __METHOD__);
				// } else {
				// 	throw new ParseException(0, 'Не удалось обновить торги ' . $trade->efrsbId . ': ' .
				// 		implode(', ', $trade->getErrorSummary(true)));
				// }

			} else {
				throw new ParseException(0, 'В сообщении не удалось распознать АУ - физлицо или компанию.');
			}
		}



		### СОХРАНЕНИЕ ОРГАНИЗАТОРА ТОРГОВ - ФИЗЛИЦА ИЛИ КОМПАНИИ
		{
			if ($xml = $messageXml->TradeOrganizer->TradeOrganizerPerson) {
				$toPerson = Person::findOne(['INN' => (string)$xml['INN']]);
				if (!$toPerson) {
					$toPerson = new Person([
						'firstName' => (string)$xml['FirstName'],
						'middleName' => (string)$xml['MiddleName'],
						'lastName' => (string)$xml['LastName'],
						'INN' => (string)$xml['INN'],
						'SNILS' => (string)$xml['SNILS'],
					]);
				}

				$toPerson->isTradeOrganizer = Person::TYPE_YES;
				try {
					$toPerson->saveEx();
					$trade->tradeOrganizerPersonId = $toPerson->id;
					$this->printInfo('Установлен ОТ-физлицо: ' . $toPerson->INN, __METHOD__);
				} catch (ActiveRecordSaveException $e) {
					$trade->updateErrorMessage($e->getMessage());
					$this->printError('Не удалось сохранить ТО-физлицо: ' . $toPerson->INN . PHP_EOL .
						'Ошибки: ' . $e->getMessage() , __METHOD__);
				}

				$trade->saveEx();

				// if (!$toPerson->save()) {
				// 	throw new ParseException(0, 'Не удалось сохранить ОТ-физлицо ' . $toPerson->INN . ': ' .
				// 	implode(', ', $toPerson->getErrorSummary(true)));
				// }

				// $trade->tradeOrganizerPersonId = $toPerson->id;
				// if ($trade->save()) {
				// 	$this->printInfo('Установлен ОТ-физлицо: ' . $toPerson->INN, __METHOD__);
				// } else {
				// 	throw new ParseException(0, 'Не удалось обновить торги ' . $trade->efrsbId . ': ' .
				// 		implode(', ', $trade->getErrorSummary(true)));
				// }
			} elseif ($xml = $messageXml->TradeOrganizer->TradeOrganizerCompany) {
				$toCompany = Company::findOne(['INN' => (string)$xml['INN']]);
				if (!$toCompany) {
					$toCompany = new Company([
						'fullName' => (string)$xml['FullName'],
						'shortName' => (string)$xml['ShortName'],
						'INN' => (string)$xml['INN'],
						'OGRN' => (string)$xml['OGRN'],
					]);
				}

				$toCompany->isTradeOrganizer = Company::TYPE_YES;

				try {
					$toCompany->saveEx();
					$trade->tradeOrganizerCompanyId = $toCompany->id;
					$this->printInfo('Установлен ОТ-компания: ' . $toCompany->INN, __METHOD__);
				} catch (ActiveRecordSaveException $e) {
					$trade->updateErrorMessage($e->getMessage());
					$this->printError('Не удалось сохранить ТО-компанию: ' . $toCompany->INN . PHP_EOL .
						'Ошибки: ' . $e->getMessage() , __METHOD__);
				}

				$trade->saveEx();

				// if (!$toCompany->save()) {
				// 	throw new ParseException(0, 'Не удалось сохранить ОТ-компанию ' . $toCompany->INN . ': ' .
				// 	implode(', ', $toCompany->getErrorSummary(true)));
				// }

				// $trade->tradeOrganizerCompanyId = $toCompany->id;
				// if ($trade->save()) {
				// 	$this->printInfo('Установлен ОТ-компания: ' . $toCompany->INN, __METHOD__);
				// } else {
				// 	throw new ParseException(0, 'Не удалось обновить торги ' . $trade->efrsbId . ': ' .
				// 		implode(', ', $trade->getErrorSummary(true)));
				// }

			} else {
				# а здесь мы исключение не бросаем, т.к. судя по документации ЕФРСБ, организатор торгов может и не быть указан
				# throw new ParseException(0, 'В сообщении не удалось распознать должника - физлицо или компанию.');
				$this->printWarning('В сообщении не указан организатор торгов', __METHOD__);
			}
		}



		### СОХРАНЕНИЕ ЛОТОВ
		foreach ($messageXml->TradeInfo->LotList->Lot as $Lot) {

			$category = $Lot->Classification->IDClass;
			// $this->output('---- ---- ---- ---- Категория лота ' . $category);

			if (is_null($lot = Lot::findOne([
				'tradeId' => $trade->id,
				'lotNumber' => (int)$Lot['LotNumber']]))
			) {
				// $lot = $trade->createLot(
				// 	(int)$Lot['LotNumber'],
				// 	(float)$Lot->StartPrice,
				// 	(string)$Lot->TradeObjectHtml,
				// 	(string)$Lot->Classification->IDClass,
				// 	$dateTime
				// );

				$lot = new Lot([
					'tradeId' => $trade->id,
					'lotNumber' => (int)$Lot['LotNumber'],
					'processedStatus' => Lot::PROCESSED_NO,
					'actualPrice' => (float)$Lot->StartPrice,
					'startPrice' => (float)$Lot->StartPrice,
					'stepPrice' => (float)$Lot->StepPrice,
					'stepPricePercent' => (float)$Lot->StepPricePercent,
					'tradeObjectHtml' => (string)$Lot->TradeObjectHtml,
					'priceReduction' => (string)$Lot->PriceReduction,
					'advance' => (float)$Lot->Advance,
					'advancePercent' => (float)$Lot->AdvancePercent,
					'concours' => (string)$Lot->Concours,
					'participants' => (string)$Lot->Participants,
					'paymentInfo' => (string)$Lot->PaymentInfo,
					'saleAgreement' => (string)$Lot->SaleAgreement,
					'classificationId' => (string)$Lot->Classification->IDClass,
					'status' => Status::BIDDING_DECLARED,
            		'previousStatus' => null,
				]);

				$lot->addNewMessageToHistory((string)$messageId);
				$lot->saveEx();
				$this->printInfo('Сохранен новый лот: ' . $trade->id . ' ' . $lot->lotNumber, __METHOD__);

				# уведомляем подписчиков о новом лоте
				if ($telegramNotifyFlag) {
					Yii::$app->telega->notifySubscribersAboutLot($lot);
				}

				// if ($lot->save()) {
				// } else {
				// 	throw new ParseException(0, 'Не удалось сохранить лот номер ' . $lot->lotNumber .
				// 		' по торгам ' . $trade->efrsbId . ': ' . implode(', ', $lot->getErrorSummary(true)));
				// }
			} else {
				$this->printWarning('Такой лот уже есть в базе...' . $trade->efrsbId . ' ' . $Lot['LotNumber'], __METHOD__);
			}
		}



		### СОХРАНЕНИЕ ОБЪЯВЛЕНИЯ О НАЧАЛЕ ТОРГОВ ОТ АУ
		if (!empty($amId = $trade->auctionMessageId)) {
			do {
				if (is_null(AuctionMessage::findOne(['efrsbId' => $amId]))) {
					try {
						$auctionMessageXml = $this->api->getMessageContent($amId);
					} catch (MessageNotFoundApiException $e) {
						$this->printWarning("Объявление АУ о начале торгов не существует. Id: $amId", __METHOD__);
						break;
					}
					file_put_contents(Yii::getAlias('@runtime/xml/auctionMessage.php'), var_export($auctionMessageXml, true));
					$str = (string)$auctionMessageXml;
					$MessageData = simplexml_load_string($str);

					## СБОР ДАННЫХ


					$Auction = $MessageData->MessageInfo->Auction;
					if (empty($Auction)) {
						$Auction = $MessageData->MessageInfo->ChangeAuction;
					}

					$data = [];

					# если сообщение типа Auction или ChangeAuction:
					if (!empty($Auction)) {
						# собираем данные из сообщения Auction
						$data['isRepeat'] = (int)$Auction->IsRepeat;
						$date = (string)$Auction->Date;
						$data['tradeType'] = (string)$Auction->TradeType;
						$data['priceType'] = (string)$Auction->PriceType;
						$data['tradeSite'] = (string)$Auction->TradeSite;
						$data['text'] = (string)$Auction->Text;
						$data['additionalText'] = (string)$Auction->AdditionalText;

						# дата
						$dateDateTime = new \DateTime($date);
						$dateDateTime->setTimezone(new \DateTimeZone('UTC'));
						$data['date'] = $dateDateTime->format('Y-m-d H:i:s');

						# список файлов, прикрепленных к объявлению АУ
						$messageUrlArray = [];
						if (!empty($MessageData->MessageURLList)) {
							foreach ($MessageData->MessageURLList->MessageURL as $m) {
								$url = (string)$m['URL'];
								$name = (string)$m['URLName'];
								$messageUrlArray[$url] = $name;
							}
						}
						$data['messageUrlList'] = json_encode($messageUrlArray);


						if (!empty($Auction->Application)) {
							$applicationTimeBegin = (string)$Auction->Application->TimeBegin;
							$applicationTimeEnd = (string)$Auction->Application->TimeEnd;
						} else {
							$applicationTimeBegin = null;
							$applicationTimeEnd = null;
						}
						$data['applicationTimeBegin'] = $applicationTimeBegin;
						$data['applicationTimeEnd'] = $applicationTimeEnd;
					} else {
						# иначе - если типа Other (бывает, когда организатором торгов является АСВ)
						$Other = $MessageData->MessageInfo->Other;

						if (!empty($Other)) {
							# собираем данные из сообщения Other
							$data['text'] = (string)$Other->Text;
						} else {
							throw new UnknownAuctionMessageTypeException($str);
						}
					}

					$data['efrsbId'] = $amId;
					$data['caseNumber'] = (string)$MessageData->CaseNumber;
					$data['publishDate'] = (string)$MessageData->PublishDate;
					$data['bankruptId'] = (string)$MessageData->BankruptId;
					$data['messageGUID'] = (string)$MessageData->MessageGUID;


					$auctionMessage = new AuctionMessage($data);
					$auctionMessage->saveEx();

					$auctionMessage->text = TextService::fixAuctionMessageText($auctionMessage->text);
					$phones = TextService::findPhoneNumbers($auctionMessage->text);
					$auctionMessage->parsedPhones = implode(', ', $phones);
					$auctionMessage->text = TextService::highlightPhoneBlocks($auctionMessage->text, $phones);
					$auctionMessage->saveEx();

					# создаем лоты
					if (!empty($Auction) && !empty($Auction->LotTable)) {
						foreach ($Auction->LotTable->AuctionLot as $AuctionLot) {
							$auctionLot = new AuctionMessageLot([
								'auctionMessageEfrsbId' => $auctionMessage->efrsbId,
								'order' => (int)$AuctionLot->Order,
								'startPrice' => (string)$AuctionLot->StartPrice,
								'description' => (string)$AuctionLot->Description,
								'classificationId' => (string)$AuctionLot->ClassifierCollection->AuctionLotClassifier->Code,
							]);

							$auctionLot->saveEx();
						}
					}


					$this->printInfo('Сохранено новое сообщение от АУ: ' . $auctionMessage->efrsbId, __METHOD__);
				}
			} while (0);
		}
	}


	/**
	 * Обрабатывает сообщение ApplicationSessionStart - изменяет статус торгов и лотов.
	 * Перейти в статус ApplicationSessionStarted можно только из статуса BiddingDeclared независимо от типа аукциона
	 * и формы подачи предложения о цене
	 */
	private function parseApplicationSessionStartMessage(Trade $trade, \SimpleXMLElement $messageXml, int $messageId)
	{
		# выставляем статус ApplicationSessionStarted и время ApplicationSessionStartTime для лотов, указанных в сообщении.
		# Если ни один лот не указан, то применяем ко всем лотам, по которым еще не получали ApplicationSessionStart

		$this->printInfo('Обработка ApplicationSessionStart', __METHOD__);

		# получаем время начала приема заявок
		// $applicationSessionStartTime = (new \DateTime((string)$messageXml['EventTime']))->format('Y-m-d H:i:s');

		# получаем список лотов, если есть
		$lotsArray = [];

		if (isset($messageXml->LotList)) {
			foreach ($messageXml->LotList->LotInfo as $lotInfo) {
				$lotsArray[] = $lotInfo['LotNumber'];
			}
		}

		# если в сообщении были указаны номера лотов
		if (count($lotsArray) > 0) {

			# забираем их
			$lots = $trade->getLots()->andWhere(['in', 'lotNumber', $lotsArray])->all();
		} else {

			$lots = $trade->getLots()
				->andWhere(['status' => Status::BIDDING_DECLARED])
				->all();
		}

		foreach ($lots as $lot) {
			$lot->previousStatus = $lot->status;
			$lot->status = Status::APPLICATION_SESSION_STARTED;
			$lot->addNewMessageToHistory($messageId);

			// $lot->applicationSessionStartTime = $applicationSessionStartTime;

			$lot->saveEx();
		}

		$trade->status = $this->calculateTradeStatus($trade);
		$trade->addNewMessageToHistory($messageId);


		$trade->saveEx();
	}


	/**
	 * Обрабатывает сообщение ApplicationSessionEnd - изменяет статус торгов и лотов.
	 * Перейти в статус ApplicationSessionEnd можно только из статуса ApplicationSessionStarted независимо от типа аукциона
	 * и формы подачи предложения о цене
	 */
	private function parseApplicationSessionEndMessage(Trade $trade, \SimpleXMLElement $messageXml, int $messageId)
	{
		# выставляем статус ApplicationSessionEnd и время ApplicationSessionEndTime для лотов, указанных в сообщении.
		# Если ни один лот не указан, то применяем ко всем лотам, по которым еще не получали ApplicationSessionEnd

		$this->printInfo('Обработка ApplicationSessionEnd', __METHOD__);

		# получаем время начала приема заявок
		// $applicationSessionEndTime = (new \DateTime((string)$messageXml['EventTime']))->format('Y-m-d H:i:s');

		# получаем список лотов, если есть
		$lotsArray = [];

		if (isset($messageXml->LotList)) {
			foreach ($messageXml->LotList->LotInfo as $lotInfo) {
				$lotsArray[] = $lotInfo['LotNumber'];
			}
		}

		# если в сообщении были указаны номера лотов
		if (count($lotsArray) > 0) {

			# забираем их
			$lots = $trade->getLots()->andWhere(['in', 'lotNumber', $lotsArray])->all();
		} else {

			# иначе забираем все лоты, по которым еще не выдавалось сообщение об окончании приема заявок
			# это можно определить по свойству applicationSessionStartTime - если оно пустое у лота, значит, по нему еще
			# не выдавалось это сообщение
			# @see Service ETP 2.42 page 24

			$lots = $trade->getLots()
				->andWhere(['status' => Status::APPLICATION_SESSION_STARTED])
				->all();
		}

		foreach ($lots as $lot) {
			$lot->previousStatus = $lot->status;
			$lot->status = Status::APPLICATION_SESSION_END;
			$lot->addNewMessageToHistory($messageId);

			$lot->saveEx();
		}

		$trade->status = $this->calculateTradeStatus($trade);
		$trade->addNewMessageToHistory($messageId);


		$trade->saveEx();
	}


	/**
	 * Обрабатывает сообщение "Начаты торги" - изменяет статус торгов и лотов.
	 * Перейти в статус BiddingInProcess можно только из статуса ApplicationSessionEnd и только для типов аукциона "Конкурс"
	 * и "Аукцион"
	 * @param Trade $trade
	 * @param \SimpleXMLElement $messageXml
	 */
	private function parseBiddingStartMessage(Trade $trade, \SimpleXMLElement $messageXml, int $messageId)
	{
		# выставляем статус BiddingInProcess и время BiddingInProcess для лотов, указанных в сообщении.
		# Если ни один лот не указан, то применяем ко всем лотам, по которым еще не получали BiddingInProcess

		$this->printInfo('Обработка BiddingStart', __METHOD__);

		# получаем время
		// $biddingStartTime = (new \DateTime((string)$messageXml['EventTime']))->format('Y-m-d H:i:s');

		# получаем список лотов, если есть
		$lotsArray = [];

		if (isset($messageXml->LotList)) {
			foreach ($messageXml->LotList->LotInfo as $lotInfo) {
				$lotsArray[] = $lotInfo['LotNumber'];
			}
		}

		# если в сообщении были указаны номера лотов
		if (count($lotsArray) > 0) {

			# забираем их
			$lots = $trade->getLots()->andWhere(['in', 'lotNumber', $lotsArray])->all();
		} else {

			# иначе забираем все лоты со статусом "Прием заявок закончен"
			# @see Service ETP 2.42 page 24

			$lots = $trade->getLots()
				->andWhere(['status' => Status::APPLICATION_SESSION_END])
				->all();
		}

		foreach ($lots as $lot) {
			$lot->previousStatus = $lot->status;
			$lot->status = Status::BIDDING_IN_PROCESS;
			$lot->addNewMessageToHistory($messageId);

			$lot->saveEx();
		}

		$trade->status = $this->calculateTradeStatus($trade);
		$trade->addNewMessageToHistory($messageId);

		$trade->saveEx();
	}


	/**
	 * Обрабатывает сообщение "Предложение о цене".
	 * Допускается для статуса "Идут торги" по аукциону и конкурсу и для статуса "Открыт прием заявок"" по публ. предл-ю
	 * @param Trade $trade
	 * @param \SimpleXMLElement $messageXml
	 */
	private function parseBiddingProcessInfoMessage(Trade $trade, \SimpleXMLElement $messageXml, int $messageId)
	{
		$this->printInfo('Обработка BiddingProcessInfo', __METHOD__);

		// $eventTime = (new \DateTime($messageXml['EventTime']))->format('Y-m-d H:i:s');
		$lotNumber = (int)$messageXml->PriceInfo['LotNumber'];
		$newPrice = (float)$messageXml->PriceInfo['NewPrice'];

		$lot = $trade->getLots()->andWhere(['lotNumber' => $lotNumber])->one();
		// $lot->biddingProcessInfoTime = $eventTime;
		$lot->actualPrice = $newPrice;
		$lot->addNewMessageToHistory($messageId);

		$lot->saveEx();

		$trade->addNewMessageToHistory($messageId);

		$trade->saveEx();
	}


	/**
	 * Обрабатывает сообщение "Торги завершены" - изменяет статус торгов и лотов.
	 * Поступает только для аукциона и конкурса с открытой формой. Из статуса "Идут торги"
	 * @param Trade $trade
	 * @param \SimpleXMLElement $messageXml
	 */
	private function parseBiddingEndMessage(Trade $trade, \SimpleXMLElement $messageXml, int $messageId)
	{
		# выставляем статус BiddingInProcess и время BiddingInProcess для лотов, указанных в сообщении.
		# Если ни один лот не указан, то применяем ко всем лотам, по которым еще не получали BiddingInProcess

		$this->printInfo('Обработка BiddingEnd', __METHOD__);

		# получаем время
		// $biddingEndTime = (new \DateTime((string)$messageXml['EventTime']))->format('Y-m-d H:i:s');

		# получаем список лотов, если есть
		$lotsArray = [];

		if (isset($messageXml->LotList)) {
			foreach ($messageXml->LotList->LotInfo as $lotInfo) {
				$lotsArray[] = $lotInfo['LotNumber'];
			}
		}

		# если в сообщении были указаны номера лотов
		if (count($lotsArray) > 0) {

			# забираем их
			$lots = $trade->getLots()->andWhere(['in', 'lotNumber', $lotsArray])->all();
		} else {

			# иначе забираем все лоты со статусом "Идут торги"
			# @see Service ETP 2.42 page 24

			$lots = $trade->getLots()
				->andWhere(['status' => Status::BIDDING_IN_PROCESS])
				->all();
		}

		foreach ($lots as $lot) {
			$lot->previousStatus = $lot->status;
			$lot->status = Status::FINISHED;
			$lot->addNewMessageToHistory($messageId);

			$lot->saveEx();
		}

		$trade->status = $this->calculateTradeStatus($trade);
		$trade->addNewMessageToHistory($messageId);

		$trade->saveEx();
	}


	/**
	 * Обрабатывает сообщение "Результаты торгов" - изменяет статус торгов и лотов.
	 * @param Trade $trade
	 * @param \SimpleXMLElement $messageXml
	 */
	private function parseBiddingResultMessage(Trade $trade, \SimpleXMLElement $messageXml, int $messageId)
	{
		# выставляем статус BiddingInProcess и время BiddingInProcess для лотов, указанных в сообщении.
		# Если ни один лот не указан, то применяем ко всем лотам, по которым еще не получали BiddingInProcess

		$this->printInfo('Обработка BiddingResult', __METHOD__);

		# получаем время
		// $biddingResultTime = (new \DateTime((string)$messageXml['EventTime']))->format('Y-m-d H:i:s');

		foreach ($messageXml->LotList->LotTradeResult as $lotTradeResult) {
			$lot = $trade->getLots()->andWhere(['lotNumber' => (int)$lotTradeResult['LotNumber']])->one();
			$lot->previousStatus = $lot->status;
			$lot->status = Status::FINISHED;
			$lot->addNewMessageToHistory($messageId);


			if (!empty($lotTradeResult->SuccessTradeResult)) {
				$lot->result = Lot::RESULT_SUCCESS;
			} elseif (!empty($lotTradeResult->FailureTradeResult)) {
				$lot->result = Lot::RESULT_FAILURE;
			} else {
				throw new ParseException(0, 'Ошибка в логике - нет установленного статуса у лота ' . $lot->id);
			}

			$lot->saveEx();
		}

		$trade->status = $this->calculateTradeStatus($trade);
		$trade->addNewMessageToHistory($messageId);

		$trade->saveEx();
	}


	/**
	 * Обрабатывает сообщение "Отмена торгов" - изменяет статус торгов и лотов.
	 * Допустим во всех типах аукционов, для лотов со статусами "Объявлены торги", "Торги приостановлены",
	 * "Открыт прием заявок", "Идут торги", "Прием заявок завершен", "Завершенные"
	 * @param Trade $trade
	 * @param \SimpleXMLElement $messageXml
	 */
	private function parseBiddingCancelMessage(Trade $trade, \SimpleXMLElement $messageXml, int $messageId)
	{
		$this->printInfo('Обработка BiddingCancel', __METHOD__);

		# получаем время
		// $biddingCancelTime = (new \DateTime((string)$messageXml['EventTime']))->format('Y-m-d H:i:s');

		# получаем список лотов, если есть
		$lotsArray = [];

		if (isset($messageXml->LotList)) {
			foreach ($messageXml->LotList->LotInfo as $lotInfo) {
				$lotsArray[] = $lotInfo['LotNumber'];
			}
		}

		# если в сообщении были указаны номера лотов
		if (count($lotsArray) > 0) {

			# забираем их
			$lots = $trade->getLots()->andWhere(['in', 'lotNumber', $lotsArray])->all();
		} else {

			# иначе забираем все лоты с вышеуказанными статусами
			# @see Service ETP 2.42 page 24

			$lots = $trade->getLots()
				->andWhere(['in', 'status', [
					Status::BIDDING_DECLARED,
					Status::BIDDING_PAUSED,
					Status::APPLICATION_SESSION_STARTED,
					Status::BIDDING_IN_PROCESS,
					Status::APPLICATION_SESSION_END,
					Status::FINISHED,
				]])
				->all();
		}

		foreach ($lots as $lot) {
			$lot->previousStatus = $lot->status;
			$lot->status = Status::BIDDING_CANCELLED;
			$lot->addNewMessageToHistory($messageId);

			$lot->saveEx();
		}

		$trade->status = $this->calculateTradeStatus($trade);
		$trade->addNewMessageToHistory($messageId);

		$trade->saveEx();
	}


	/**
	 * Обрабатывает сообщение "Торги не состоялись" - изменяет статус торгов и лотов.
	 * Допустим во всех типах аукционов, для лотов со статусами "Прием заявок завершен", "Открыт прием заявок",
	 * "Объявлены торги"
	 * @param Trade $trade
	 * @param \SimpleXMLElement $messageXml
	 */
	private function parseBiddingFailMessage(Trade $trade, \SimpleXMLElement $messageXml, int $messageId)
	{
		$this->printInfo('Обработка BiddingFail', __METHOD__);

		# получаем время
		// $biddingFailTime = (new \DateTime((string)$messageXml['EventTime']))->format('Y-m-d H:i:s');

		# получаем список лотов, если есть
		$lotsArray = [];

		if (isset($messageXml->LotList)) {
			foreach ($messageXml->LotList->LotInfo as $lotInfo) {
				$lotsArray[] = $lotInfo['LotNumber'];
			}
		}

		# если в сообщении были указаны номера лотов
		if (count($lotsArray) > 0) {

			# забираем их
			$lots = $trade->getLots()->andWhere(['in', 'lotNumber', $lotsArray])->all();
		} else {

			$lots = $trade->getLots()
				->andWhere(['in', 'status', [
					Status::BIDDING_DECLARED,
					Status::APPLICATION_SESSION_STARTED,
					Status::APPLICATION_SESSION_END,
				]])
				->all();
		}

		foreach ($lots as $lot) {
			$lot->previousStatus = $lot->status;
			$lot->status = Status::BIDDING_FAIL;
			$lot->addNewMessageToHistory($messageId);

			$lot->saveEx();
		}

		$trade->status = $this->calculateTradeStatus($trade);
		$trade->addNewMessageToHistory($messageId);

		$trade->saveEx();
	}


	/**
	 * Обрабатывает сообщение "Торги приостановлены" - изменяет статус торгов и лотов.
	 * Допустим во всех типах аукционов, для лотов со статусами: "Объявлены торги", "Открыт прием заявок",
	 * "Прием заявок завершен", "Идут торги"
	 * @param Trade $trade
	 * @param \SimpleXMLElement $messageXml
	 */
	private function parseBiddingPauseMessage(Trade $trade, \SimpleXMLElement $messageXml, int $messageId)
	{
		$this->printInfo('Обработка BiddingPause', __METHOD__);

		# получаем время
		// $biddingPauseTime = (new \DateTime((string)$messageXml['EventTime']))->format('Y-m-d H:i:s');

		# получаем список лотов, если есть
		$lotsArray = [];

		if (isset($messageXml->LotList)) {
			foreach ($messageXml->LotList->LotInfo as $lotInfo) {
				$lotsArray[] = $lotInfo['LotNumber'];
			}
		}

		# если в сообщении были указаны номера лотов
		if (count($lotsArray) > 0) {

			# забираем их
			$lots = $trade->getLots()->andWhere(['in', 'lotNumber', $lotsArray])->all();
		} else {

			$lots = $trade->getLots()
				->andWhere(['in', 'status', [
					Status::BIDDING_DECLARED,
					Status::APPLICATION_SESSION_STARTED,
					Status::APPLICATION_SESSION_END,
					Status::BIDDING_IN_PROCESS,
				]])
				->all();
		}

		foreach ($lots as $lot) {
			$lot->previousStatus = $lot->status;
			$lot->status = Status::BIDDING_PAUSED;
			$lot->addNewMessageToHistory($messageId);

			$lot->saveEx();
		}

		$trade->status = $this->calculateTradeStatus($trade);
		$trade->addNewMessageToHistory($messageId);

		$trade->saveEx();
	}


	/**
	 * Обрабатывает сообщение "Торги возобновлены" - изменяет статус торгов и лотов.
	 * Допустим во всех типах аукционов, для лотов со статусами: "Торги приостановлены"
	 * @param Trade $trade
	 * @param \SimpleXMLElement $messageXml
	 */
	private function parseBiddingResumeMessage(Trade $trade, \SimpleXMLElement $messageXml, int $messageId)
	{
		$this->printInfo('Обработка BiddingResume', __METHOD__);

		# получаем время
		// $biddingResumeTime = (new \DateTime((string)$messageXml['EventTime']))->format('Y-m-d H:i:s');

		# получаем список лотов, если есть
		$lotsArray = [];

		if (isset($messageXml->LotList)) {
			foreach ($messageXml->LotList->LotInfo as $lotInfo) {
				$lotsArray[] = $lotInfo['LotNumber'];
			}
		}

		# если в сообщении были указаны номера лотов
		if (count($lotsArray) > 0) {

			# забираем их
			$lots = $trade->getLots()->andWhere(['in', 'lotNumber', $lotsArray])->all();
		} else {

			$lots = $trade->getLots()
				->andWhere(['in', 'status', [
					Status::BIDDING_PAUSED,
				]])
				->all();
		}

		foreach ($lots as $lot) {
			$lot->previousStatus = Status::BIDDING_PAUSED;
			$lot->status = $lot->previousStatus;
			$lot->addNewMessageToHistory($messageId);

			$lot->saveEx();
		}

		// $trade->biddingResumeTime = $biddingResumeTime;
		$trade->status = $this->calculateTradeStatus($trade);
		$trade->addNewMessageToHistory($messageId);

		$trade->saveEx();
	}


	private function parseAnnulmentMessage(Trade $trade, int $messageId)
	{
		$this->printInfo('Обработка AnnulmentMessage', __METHOD__);
		$trade->addNewMessageToHistory($messageId);
		$trade->saveEx();
	}


	private function parseAnnulledMessage(Trade $trade, int $messageId)
	{
		$this->printInfo('Обработка AnnulledMessage', __METHOD__);
		$trade->addNewMessageToHistory($messageId);
		$trade->saveEx();
	}


	private function parseDefaultMessage(Trade $trade, int $messageId)
	{
		$this->printInfo('Обработка прочего сообщения', __METHOD__);
		$trade->addNewMessageToHistory($messageId);
		$trade->saveEx();
	}


	/**
	 * Вычисляет статус торгов на основании статусов всех его лотов -
	 * @see Документ о сообщениях ЭТП, Service ETP 2.42, стр. 29, rule 5.1
	 */
	private function calculateTradeStatus(Trade $trade)
	{
		if ($trade->getLotsCountByStatus(Status::BIDDING_IN_PROCESS) > 0) {
			return Status::BIDDING_IN_PROCESS;
		} elseif ($trade->getLotsCountByStatus(Status::BIDDING_PAUSED) > 0) {
			return Status::BIDDING_PAUSED;
		} elseif ($trade->getLotsCountByStatus(Status::APPLICATION_SESSION_STARTED) > 0) {
			return Status::APPLICATION_SESSION_STARTED;
		} elseif ($trade->getLotsCountByStatus(Status::APPLICATION_SESSION_END) > 0) {
			return Status::APPLICATION_SESSION_END;
		} elseif ($trade->getLotsCountByStatus(Status::BIDDING_DECLARED) > 0) {
			return Status::BIDDING_DECLARED;
		} elseif ($trade->getLotsCountByStatus(Status::FINISHED) > 0) {
			return Status::FINISHED;
		} elseif ($trade->getLotsCountByStatus(Status::BIDDING_CANCELLED) > 0) {
			return Status::BIDDING_CANCELLED;
		} elseif ($trade->getLotsCountByStatus(Status::BIDDING_FAIL) > 0) {
			return Status::BIDDING_FAIL;
		} else {
			throw new \LogicException('Не удалось определить статус торгов в соответствии с документацией');
		}
	}


	### СТАРОЕ


	private function formatDateTime($string)
	{
		// $format = 'Y-m-d\TH:i:sP';
		// $format2 = 'Y-m-d\TH:i:s.uP';

		// if ($date = \DateTime::createFromFormat($format, $string)) {
		// 	return $date->format('Y-m-d H:i:s');
		// } elseif ($date = \DateTime::createFromFormat($format2, $string)) {
		// 	return $date->format('Y-m-d H:i:s');
		// } elseif (empty($date)) {
		// 	return null;
		// }

		// throw new DateTimeFormatException($format . ' или ' . $format2, $string);
		if (empty($string)) {
			return null;
		}

		if (!$date = new \DateTime($string)) {
			throw new DateTimeFormatException('Произвольный', $string);
		}

		return $date->format('Y-m-d H:i:s');
	}


	private function formatDate($string)
	{
		// $format = 'Y-m-dP';

		// if ($date = \DateTime::createFromFormat($format, $string)) {
		// 	return $date->format('Y-m-d');
		// } elseif (empty($date)) {
		// 	return null;
		// }

		// throw new DateTimeFormatException($format, $string);
		if (empty($string)) {
			return null;
		}

		if (!$date = new \DateTime($string)) {
			throw new DateTimeFormatException('Произвольный', $string);
		}

		return $date->format('Y-m-d');
	}




	/**
	 * Обрабатывает данные торги -
	 * Вначале сортирует их по дате события, а потом обрабатывает по одному.
	 *
	 * @param \SimpleXMLElement $tradeXml XML-объект торгов с сообщениями
	 * @param TradePlace $tradePlace ЭТП
	 * @param int $messageCounter
	 * @param int $messageCount
	 * @param int $startTime
	 * @return int измененный $messageCounter
	 */
	public function parseTradeId(
		\SimpleXMLElement $tradeXml,
		TradePlace $tradePlace,
		int $tradeCounter,
		int $tradeCount,
		int $startTime
	): int
	{
		throw new ParseException(0, 'Этот метод не используется');
		$trade = $tradePlace->createPrototypeTrade(
			(integer)$tradeXml['ID_EFRSB'],
			(string)$tradeXml['ID_External']
		);

		if ($trade->save()) {
			// $this->printInfo('Сохранены новые торги: ' . $trade->efrsbId, __METHOD__);
		} else {
			$this->printError('Не удалось сохранить торги ' . $trade->efrsbId . ': ' . implode(', ', $trade->getErrorSummary(true)), __METHOD__);
		}

		# считаем проценты, время выполнения и примерно сколько осталось
		$tradeCounter++;
		$this->printProgress($tradeCounter, $tradeCount, $startTime);

		return $tradeCounter;
	}




	/**
	 * Получает все сообщения по указанным торгам за указанный период.
	 * Если такие торги уже есть в базе, то выбрасывает исключение
	 * @param string $etpId
	 * @param string $tradePlaceInn
	 * @param string $startDate
	 * @param string $endDate
	 * @return type
	 */
	public function parseFullTrade(
		string $etpId,
		string $tradePlaceInn,
		string $startDateString,
		string $endDateString
	) {

		// throw new \LogicException('После создания оповещения по ТГ данный метод работает некорректно');


		# Ищем, есть ли такие торги в нашей БД. Если есть, выбрасываем исключение
		if ($tradePlace = TradePlace::findOne(['inn' => $tradePlaceInn])) {
			if ($trade = Trade::find()
				->andWhere(['tradePlaceId' => $tradePlace->id])
				->andWhere(['etpId' => $etpId])
				->one()
			) {
				throw new ParseException(0, 'Нельзя вызвать метод ' . __METHOD__ . ' по уже существующим торгам: ' . $trade->id);
			}
		}


		# Получаем даты начала и завершения парсинга
		{
			$startDate = new \DateTime($startDateString);
			$endDate = new \DateTime($endDateString);
			if ($startDate >= $endDate) {
				throw new ParseException(0, 'Дата начала парсинга должна быть меньше даты окончания. Получено: ' . $startDateString .
					', ' . $endDateString);
			}

			// $startDate = $startDate->format('Y-m-d\TH:i:s');
			// $endDate = $endDate->format('Y-m-d\TH:i:s');

			echo 'StartDate: ' . $startDateString . PHP_EOL;
			echo 'EndDate: ' . $endDateString . PHP_EOL;
		}


		# Получаем XML сообщений по этим торгам
		$xml = $this->api->getTradeMessagesByTrade($etpId, $tradePlaceInn, $startDate, $endDate);
		if ($xml->count() == 0) {
			throw new ParseException(0, "Не удалось получить данные по запросу с параметрами: etpId = $etpId, tradePlaceInn = $tradePlaceInn, startDate = $startDateString, endDate = $endDateString");
		}


		# Если у нас нет такой ЭТП, то сохраняем
		if (is_null($tradePlace)) {
			// var_dump($xml); die();
			$tradePlace = new TradePlace([
				'title' => (string)$xml->TradePlace['Name'],
				'inn' => (string)$xml->TradePlace['INN'],
				'site' => (string)$xml->TradePlace['Site'],
			]);

			$tradePlace->saveEx();
		}

		$tradeMessagesXml = $xml->TradePlace->TradeList->Trade;


		# Запускаем парсинг
		$this->parseTradeMessages($tradeMessagesXml, $tradePlace, null, $endDate, false);

	}
}
