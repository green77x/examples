<?php

namespace app\efrsb\services;

use app\efrsb\exceptions\{
	ApiException,
	MessageNotFoundApiException,
	TradeNotFoundApiException,
	EtpNotFoundApiException,
	WrongXmlException,
	FaultApiException,
	WrongResponseXmlApiException};
use yii\helpers\Console;
use Yii;


/**
 * Получает XML-ответы от Апи ЕФРСБ
 */
class ApiService
{

	use \app\traits\PrintMessageTrait;


	private $url = 'https://bankrot.fedresurs.ru/MessageService/WebService.svc';
	private $login = 'lala';
	private $password = 'lala';
	private $ch;


	public function __construct()
	{
		$this->ch = curl_init();
	}


	/**
	 * Обращается к методу SearchDebtorByCode - получает данные о должнике по его коду
	 * Принимает параметр $code - код должника. На основании количества его цифр определяет тип -
	 * ИНН физ. лица, ИНН юр. лица или СНИЛС физ. лица
	 *
	 * @param string $code Код должника
	 * @return SimpleXMLElement XML-объект ответа
	 */
	public function searchDebtorByCode(string $code): \SimpleXMLElement
	{
		switch (strlen($code)) {
			case 12: $codeType = 'PersonInn'; break;
			case 11: $codeType = 'Snils'; break;
			case 10: $codeType = 'CompanyInn'; break;
			default: throw new \InvalidArgumentException('Аргумент $code должен содержать 10, 11 или 12 цифр');
		}

		$requestXml = '
		<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
			<s:Body
				xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
				xmlns:xsd="http://www.w3.org/2001/XMLSchema">
				<SearchDebtorByCode xmlns="http://tempuri.org/">
					<codeType>' . $codeType . '</codeType>
					<codeValue>' . $code . '</codeValue>
				</SearchDebtorByCode>
			</s:Body>
		</s:Envelope>';


		$responseXml = $this->sendRequest('SearchDebtorByCode', $requestXml);
		$data = $responseXml->children('http://schemas.xmlsoap.org/soap/envelope/')
			->Body->children()
			->SearchDebtorByCodeResponse->SearchDebtorByCodeResult;

		return $data;
	}


	/**
	 * Обращается к методу GetMessageContent - получает контент сообщения
	 * @param string $id идентификатор сообщения
	 */
	public function getMessageContent(string $id): \SimpleXMLElement
	{
		$requestXml = '
		<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
			<s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
				<GetMessageContent xmlns="http://tempuri.org/">
					<id>' . $id . '</id>
				</GetMessageContent>
			</s:Body>
		</s:Envelope>';

		$responseXml = $this->sendRequest('GetMessageContent', $requestXml);
		$data = $responseXml->children('http://schemas.xmlsoap.org/soap/envelope/')
			->Body->children()
			->GetMessageContentResponse->children()
		 	->GetMessageContentResult;

		return $data;
	}


	/**
	 * Обращается к методу GetTradeMessages - получает сообщения о торгах за указанный промежуток времени
	 * @param string $startDate - дата начала, формат YYYY-MM-DDTHH:MM:SS
	 * @param string $endDate - дата конца, формат YYYY-MM-DDTHH:MM:SS
	 */
	public function getTradeMessages(\DateTime $startDateTime, \DateTime $endDateTime): \SimpleXMLElement
	{

		$format = 'Y-m-d\TH:i:s';
		$startDate = $startDateTime->format($format);
		$endDate = $endDateTime->format($format);

		$requestXml = '
		<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
			<s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
				<GetTradeMessages xmlns="http://tempuri.org/">
					<startFrom>' . $startDate . '</startFrom>
					<endTo>' . $endDate . '</endTo>
				</GetTradeMessages>
			</s:Body>
		</s:Envelope>';

		$responseXml = $this->sendRequest('GetTradeMessages', $requestXml);
		$data = $responseXml->children('http://schemas.xmlsoap.org/soap/envelope/')
			->Body->children()
			->GetTradeMessagesResponse->children()
		 	->GetTradeMessagesResult->children();

		return $data;
	}


	/**
	 * Обращается к методу GetTradeMessagesByTrade - получает сообщения по конкретным торгам за указанный промежуток времени
	 * @param string $efrsbId идентификатор торгов на ЭТП
	 * @param string $tradePlaceInn ИНН ЭТП
	 * @param string $startDate - дата начала, формат YYYY-MM-DDTHH:MM:SS
	 * @param string $endDate - дата конца, формат YYYY-MM-DDTHH:MM:SS
	 */
	public function getTradeMessagesByTrade(
		string $tradeEtpId,
		string $tradePlaceInn,
		\DateTime $startDateTime,
		\DateTime $endDateTime
	): \SimpleXMLElement
	{
		$format = 'Y-m-d\TH:i:s';
		$startDate = $startDateTime->format($format);
		$endDate = $endDateTime->format($format);

		$requestXml = '
		<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
			<s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
				<GetTradeMessagesByTrade xmlns="http://tempuri.org/">
					<id>' . $tradeEtpId . '</id>
					<tradePlaceInn>' . $tradePlaceInn . '</tradePlaceInn>
					<startFrom>' . $startDate . '</startFrom>
					<endTo>' . $endDate . '</endTo>
				</GetTradeMessagesByTrade>
			</s:Body>
		</s:Envelope>';

		$responseXml = $this->sendRequest('GetTradeMessagesByTrade', $requestXml);
		$data = $responseXml->children('http://schemas.xmlsoap.org/soap/envelope/')
			->Body->children()
			->GetTradeMessagesByTradeResponse->children()
		 	->GetTradeMessagesByTradeResult->children();

		return $data;
	}


	/**
	 * Обращается к методу GetTradeMessageContent - получает данные о сообщении с ЭТП
	 * @param int $id Идентификатор сообщения
	 * @return SimpleXMLElement XML-объект ответа
	 */
	public function getTradeMessageContent(int $id): \SimpleXMLElement
	{
		$requestXml = '
		<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
			<s:Body
				xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
				xmlns:xsd="http://www.w3.org/2001/XMLSchema">
				<GetTradeMessageContent xmlns="http://tempuri.org/">
					<idTradeMessage>' . $id . '</idTradeMessage>
				</GetTradeMessageContent>
			</s:Body>
		</s:Envelope>';

		$responseXml = $this->sendRequest('GetTradeMessageContent', $requestXml);
		// var_dump($requestXml); die();

		/* Попробовал убрать ограничение на размер входной строки, но при этом скрипт завис на час и не отвис.
		Возможно, нужно сделать как-то иначе */
		// $xml = simplexml_load_string($output, 'SimpleXMLElement', LIBXML_PARSEHUGE);

		$str = strval($responseXml->children('http://schemas.xmlsoap.org/soap/envelope/')
			->Body->children()
			->GetTradeMessageContentResponse->GetTradeMessageContentResult);

		// Gets rid of all namespace definitions
		// $str = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $str);

		// Gets rid of all namespace references
		$str = preg_replace('#(</?)[a-zA-Z0-9-]+:#', '$1', $str);

		// var_dump($str); die();

		$xml = $this->loadXmlFromString($str);
		$xml2 = $xml->children()
				->Body->children();

		// var_dump($xml2);

		return $xml2;
	}

	/**
	 * Отправляет SOAP-запрос на URL ЕФРСБ с указанными названием метода и xml-телом.
	 * Возвращает ответ от ЕФРСБ.
	 */
	private function sendSoapRequest($methodName, $requestXml)
	{
		$headers = [];
		$headers[] = 'SOAPAction: "http://tempuri.org/IMessageService/' . $methodName . '"';
		$headers[] = 'Content-Type: text/xml; charset=utf-8';
		$headers[] = 'Accept: text/xml';


		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->ch, CURLOPT_URL, $this->url);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_USERPWD, "$this->login:$this->password");
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $requestXml);
		$output = curl_exec($this->ch);
		return $output;
	}


	/**
	 * Создает SimpleXMLElement из строки.
	 * Действует как simplexml_load_string, но бросает исключение при некорректной строке.
	 *
	 * @param type $string
	 * @throws WrongXmlException если не удалось создать XML из строки
	 * @return type
	 */
	private function loadXmlFromString(string $string)
	{
		// var_dump($string);
		if (false === ($xml = simplexml_load_string($string, 'SimpleXMLElement', LIBXML_PARSEHUGE))) {
			throw new WrongXmlException("Не удалось создать XML на основе строки $string");
		}

		return $xml;
	}



	/**
	 * Отправляет запрос к api ЕФРСБ
	 *
	 * @param type $methodName
	 * @param type $requestXml
	 * @return type
	 * @throws WrongResponseXmlApiException если ответ, полученный от ЕФРСБ, содержит некорректный XML
	 * @throws FaultApiException если ответ, полученный от ЕФРСБ, содержит элемент Fault
	 */
	private function sendRequest($methodName, $requestXml)
	{
		while (true) {

			$output = $this->sendSoapRequest($methodName, $requestXml);
			file_put_contents(Yii::getAlias('@runtime/xml') . '/output.php', $output);

			# если не получится создать XML из ответа, бросаем WrongXmlApiResponseException
			try {
				$responseXml = $this->loadXmlFromString($output);
			} catch (WrongXmlException $e) {
				throw new WrongResponseXmlApiException($output);
			}


			# если есть элемент Fault, значит, что-то не так
			if ($fault = ($responseXml->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->Fault)) {
				$message = (string)$fault->children()->faultstring;

				if ($message == 'В процессе обработки запроса произошла ошибка.') {
					# засыпаем на 10 секунд и повторяем запрос еще раз
					$this->printWarning('Не удалось получить корректный ответ, пробуем еще раз...' . PHP_EOL . $message . PHP_EOL .
						var_export($output, true) . PHP_EOL, Console::FG_YELLOW, __METHOD__);
					sleep(10);
					continue;
				} elseif (false !== mb_strpos($message, 'Сообщение не найдено по идентификатору')) {
					throw new MessageNotFoundApiException($message);
				} elseif ($message == 'В системе нет площадки с запрашиваемым ИНН.') {
					throw new EtpNotFoundApiException($message);
				} elseif ($message == 'В системе нет торгов с запрашиваемым идентификатором.') {
					throw new TradeNotFoundApiException($message);
				} else {
					throw new FaultApiException($message);
				}
			}

			return $responseXml;
		}

	}
}
