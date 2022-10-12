<?php

namespace app\efrsb\models;

use app\efrsb\exceptions\WrongMessageOrderException;
use app\efrsb\services\RegionService;
use Yii;


class Trade extends \app\models\ActiveRecord
{

    # *** КОНСТАНТЫ ***

    const SCENARIO_PROTOTYPE = 'prototype';
    const SCENARIO_POPULATE = 'populate';

    const POPULATED_NO = 'No';
    const POPULATED_YES = 'Yes';
    const POPULATED_FAIL = 'Fail';

    const AUCTION_TYPE_OPEN_AUCTION = 'OpenAuction';
    const AUCTION_TYPE_OPEN_CONCOURS = 'OpenConcours';
    const AUCTION_TYPE_PUBLIC_OFFER = 'PublicOffer';
    const AUCTION_TYPE_CLOSE_AUCTION = 'CloseAuction';
    const AUCTION_TYPE_CLOSE_CONCOURS = 'CloseConcours';
    const AUCTION_TYPE_CLOSE_PUBLIC_OFFER = 'ClosePublicOffer';

    const FORM_PRICE_OPEN_FORM = 'OpenForm';
    const FORM_PRICE_CLOSE_FORM = 'CloseForm';

    const IS_REPEAT_YES = 'Yes';
    const IS_REPEAT_NO = 'No';

    const DEFECT_YES = 1;



    # *** БАЗОВЫЕ МЕТОДЫ ActiveRecord


    public static function tableName()
    {
        return 'efrsb_trade';
    }


    public function rules()
    {
        return [

            # идентификатор торгов на ЕФРСБ
            ['efrsbId', 'integer'],
            ['efrsbId', 'unique'],
            ['efrsbId', 'required'],

            # идентификатор торгов на их торговой площадке
            ['etpId', 'string', 'max' => 255],
            ['etpId', 'required'],

            # связь с ЭТП в нашей БД
            ['tradePlaceId', 'integer'],
            ['tradePlaceId', 'exist', 'skipOnError' => true,
                'targetClass' => TradePlace::class, 'targetAttribute' => ['tradePlaceId' => 'id']],
            ['tradePlaceId', 'required'],

            # статус заполнения
            ['populatedStatus', 'string'],
            ['populatedStatus', function () {
                if (!in_array($this->populatedStatus, self::getPopulatedStatusArray())) {
                    $this->addError($this->populatedStatus, 'Недопустимое значение populatedStatus: ' . $this->populatedStatus);
                }},
                'skipOnError' => true,
            ],
            ['populatedStatus', 'required'],

            ['defect', 'integer'],

            # id объявления АУ о торгах
            ['auctionMessageId', 'integer'],
            // ['auctionMessageId', 'required', 'except' => self::SCENARIO_PROTOTYPE],

            # id должника - физ. лица или компании
            ['debtorPersonId', 'integer'],
            ['debtorCompanyId', 'integer'],
            ['debtorPersonId', function () {
                if ((empty($this->debtorPersonId) and empty($this->debtorCompanyId)) or
                    (is_numeric($this->debtorPersonId)) and (is_numeric($this->debtorCompanyId))) {
                    $this->addError($this->debtorPersonId, 'Должно быть установлено одно из двух значений - debtorPersonId или debtorCompanyId. Получено: ' . $this->debtorPersonId . ' и ' . $this->debtorCompanyId);
                }},
                'except' => self::SCENARIO_PROTOTYPE,
            ],

            # id арбитражного/конкурсного управляющего - физ. лица или компании (АСВ)
            ['arbitrManagerPersonId', 'integer'],
            ['arbitrManagerCompanyId', 'integer'],
            ['arbitrManagerPersonId', function () {
                if ((empty($this->arbitrManagerPersonId) and empty($this->arbitrManagerCompanyId)) or
                    (is_numeric($this->arbitrManagerPersonId)) and (is_numeric($this->arbitrManagerCompanyId))) {
                    $this->addError($this->arbitrManagerPersonId, 'Должно быть установлено одно из двух значений - arbitrManagerPersonId или arbitrManagerCompanyId. Получено: ' . $this->arbitrManagerPersonId . ' и ' . $this->arbitrManagerCompanyId);
                }},
                'except' => self::SCENARIO_PROTOTYPE,
            ],

            # id организатора торгов - физ. лица или компании
            ['tradeOrganizerPersonId', 'integer'],
            ['tradeOrganizerCompanyId', 'integer'],
            ['tradeOrganizerPersonId', function () {
                if ((empty($this->tradeOrganizerPersonId) and empty($this->tradeOrganizerCompanyId)) or
                    (is_numeric($this->tradeOrganizerPersonId)) and (is_numeric($this->tradeOrganizerCompanyId))) {
                    $this->addError($this->tradeOrganizerPersonId, 'Должно быть установлено одно из двух значений - tradeOrganizerPersonId или tradeOrganizerCompanyId. Получено: ' . $this->tradeOrganizerPersonId . ' и ' . $this->tradeOrganizerCompanyId);
                }},
                'except' => self::SCENARIO_PROTOTYPE,
            ],

            # id судебного решения
            ['legalCaseId', 'integer'],
            // ['legalCaseId', 'required', 'except' => self::SCENARIO_PROTOTYPE],
            ['legalCaseId', 'exist', 'skipOnError' => true,
                'targetClass' => LegalCase::class, 'targetAttribute' => ['legalCaseId' => 'id']],

            # тип аукциона
            ['auctionType', 'string'],
            ['auctionType', function () {
                if (!in_array($this->auctionType, self::getAuctionTypeArray())) {
                    $this->addError($this->auctionType, 'Недопустимое значение auctionType: ' . $this->auctionType);
                }},
                'except' => self::SCENARIO_PROTOTYPE,
            ],

            # форма подачи заявок
            ['formPrice', 'string'],
            ['formPrice', function () {
                if (!in_array($this->formPrice, self::getFormPriceArray())) {
                    $this->addError($this->formPrice, 'Недопустимое значение formPrice: ' . $this->formPrice);
                }
            }],
            ['formPrice', 'required', 'except' => self::SCENARIO_PROTOTYPE],

            # признак повторных торгов
            ['isRepeat', 'string'],
            ['isRepeat', function () {
                if (!in_array($this->isRepeat, self::getIsRepeatArray())) {
                    $this->addError($this->isRepeat, 'Недопустимое значение isRepeat: ' . $this->isRepeat);
                }},
                'except' => self::SCENARIO_PROTOTYPE,
            ],

            # дата и время публикации в СМИ
            ['datePublishSMI', 'date', 'format' => 'php: Y-m-d'],

            # дата и время публикации в ЕФИР
            ['datePublishEFIR', 'date', 'format' => 'php: Y-m-d'],

            # код региона должника
            ['regionCode', 'integer'],
            ['regionCode', function () {
                if (false === (RegionService::getTitleByCode($this->regionCode))) {
                    $this->addError($this->regionCode, 'Недопустимое значение regionCode: ' . $this->regionCode);
                }
            }],
            ['regionCode', 'required', 'except' => self::SCENARIO_PROTOTYPE],

            # дата и время начала торгов при открытой форме
            ['openFormTimeBegin', 'datetime', 'format' => 'php: Y-m-d H:i:s'],
            ['openFormTimeBegin', function () {
                if ($this->formPrice == self::FORM_PRICE_OPEN_FORM) {
                    if (empty($this->openFormTimeBegin)) {
                        $this->addError('openFormTimeBegin', 'Отсутствует свойство openFormTimeBegin при открытой форме подачи заявок');
                    }
                }
            }],

            # дата и время окончания торгов при открытой форме
            ['openFormTimeEnd', 'datetime', 'format' => 'php: Y-m-d H:i:s'],
            ['openFormTimeEnd', function () {
                if ($this->formPrice == self::FORM_PRICE_OPEN_FORM) {
                    if (empty($this->openFormTimeEnd)) {
                        $this->addError('openFormTimeEnd', 'Отсутствует свойство openFormTimeEnd при открытой форме подачи заявок');
                    }
                }
            }],

            # дата и время объявления результатов торгов при закрытой форме
            ['closeFormTimeResult', 'datetime', 'format' => 'php: Y-m-d H:i:s'],
            ['closeFormTimeResult', function () {
                if ($this->formPrice == self::FORM_PRICE_CLOSE_FORM) {
                    if (empty($this->closeFormTimeResult)) {
                        $this->addError('closeFormTimeResult', 'Отсутствует свойство closeFormTimeResult при закрытой форме подачи заявок');
                    }
                }
            }],

            # дата и время начала подачи заявок
            ['applicationTimeBegin', 'datetime', 'format' => 'php: Y-m-d H:i:s'],
            ['applicationTimeBegin', 'required', 'except' => self::SCENARIO_PROTOTYPE,],

            # дата и время окончания подачи заявок
            ['applicationTimeEnd', 'datetime', 'format' => 'php: Y-m-d H:i:s'],
            ['applicationTimeEnd', 'required', 'except' => self::SCENARIO_PROTOTYPE,],

            # правила подачи заявок
            ['applicationRules', 'string'],
            ['applicationRules', 'required', 'except' => self::SCENARIO_PROTOTYPE,],

            # статус
            ['status', 'string'],
            ['status', function () {
                if (!in_array($this->status, Status::getStatusArray())) {
                    $this->addError('status', 'Недопустимое значение status: ' . $this->status);
                }
            }],
            ['status', 'required', 'except' => self::SCENARIO_PROTOTYPE],

            # история сообщений
            ['messagesHistory', 'string'],

            # id последнего сообщения
            ['lastMessageId', 'integer'],

            # дата последнего сообщения
            ['lastParsingDate', 'datetime', 'format' => 'php: Y-m-d H:i:s'],

            ['errorMessage', 'string'],

        ];
    }


    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tradePlaceId' => 'ЭТП',
            'etpId' => 'ID на ЭТП',
            'efrsbId' => 'ID на ЕФРСБ',
            'legalCaseId' => 'Номер дела',
            'lotCount' => 'Кол-во лотов',
            'regionText'=> 'Регион должника',
            'populatedStatus' => 'Обработан',
            'populatedStatusText' => 'Обработан',
            'status' => 'Статус',
            'statusText' => 'Статус',
            'dateCreate' => 'Дата добавления в базу',
            'errorMessage' => 'Ошибки при парсинге',
            'debtorText' => 'Должник',
            'arbitrManagerText' => 'Арбитражный управляющий',
            'tradeOrganizerText' => 'Организатор торгов',
            'caseNumber' => 'Судебное дело',
            'auctionTypeText' => 'Вид торгов',
            'formPriceText' => 'Форма подачи предложения о цене',
          	'isRepeatText' => 'Повторные торги?',
          	'datePublishSMI' => 'Дата публикации сообщения о проведении торгов в официальном издании',
          	'datePublishEFIR' => 'Дата размещения сообщения о проведении открытых торгов на сайте данного официального издания в сети "Интернет" и Едином федеральном реестре сведений о банкротстве',
          	'applicationTimeBegin' => 'Дата начала подачи заявок',
          	'applicationTimeEnd' => 'Дата завершения подачи заявок',
          	'applicationRules' => 'Правила подачи заявок',
          	'openFormTimeBegin' => 'Дата начала торгов (для открытой формы)',
          	'openFormTimeEnd' => 'Дата окончания торгов (для открытой формы)',
          	'closeFormTimeResult' => 'Дата объявления результатов торгов (для закрытой формы)',

        ];
    }



    # *** СВЯЗИ С ПРОЧИМИ СУЩНОСТЯМИ ***


    public function getLots()
    {
        return $this->hasMany(Lot::class, ['tradeId' => 'id']);
    }


    public function getAuctionMessage()
    {
        return $this->hasOne(AuctionMessage::class, ['efrsbId' => 'auctionMessageId']);
    }


    public function getDebtorPerson()
    {
        return $this->hasOne(Person::class, ['id' => 'debtorPersonId']);
    }


    public function getDebtorCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'debtorCompanyId']);
    }


    public function getArbitrManagerPerson() { return $this->hasOne(Person::class, ['id' => 'arbitrManagerPersonId']); }
    public function getArbitrManagerCompany() { return $this->hasOne(Company::class, ['id' => 'arbitrManagerCompanyId']); }

    public function getTradeOrganizerPerson() { return $this->hasOne(Person::class, ['id' => 'tradeOrganizerPersonId']); }
    public function getTradeOrganizerCompany() { return $this->hasOne(Company::class, ['id' => 'tradeOrganizerCompanyId']); }


    public function getLotCount()
    {
        return $this->getLots()->count();
    }


    public function getTradePlace()
    {
        return $this->hasOne(TradePlace::className(), ['id' => 'tradePlaceId']);
    }


    public function getLegalCase()
    {
        return $this->hasOne(LegalCase::class, ['id' => 'legalCaseId']);
    }



    # *** ПОРОЖДАЮЩИЕ МЕТОДЫ ***

    public static function create(
        int $efrsbId,
        string $etpId,
        string $auctionMessageId,
        // string $auctionMessageText,
        string $legalCaseId,
        \DateTime $biddingInvitationTime,
        int $regionCode,
        string $auctionType,
        string $formPrice,
        int $tradePlaceId = null
    ) : self
    {
        $trade = new self([
            'efrsbId' => $efrsbId,
            'etpId' => $etpId,
            'populatedStatus' => self::POPULATED_YES,
            'auctionMessageId' => $auctionMessageId,
            // 'auctionMessageText' => $auctionMessageText,
            'legalCaseId' => $legalCaseId,
            'biddingInvitationTime' => $biddingInvitationTime->format('Y-m-d H:i:s'),
            'regionCode' => $regionCode,
            'auctionType' => $auctionType,
            'formPrice' => $formPrice,
            'tradePlaceId' => $tradePlaceId,
            'status' => Status::BIDDING_DECLARED,
        ]);

        return $trade;
    }


    public static function createPrototypeTrade(
        int $efrsbId,
        string $etpId,
        int $tradePlaceId = null
    ) {
        $trade = new self([
            'scenario' => Trade::SCENARIO_PROTOTYPE,
            'efrsbId' => $efrsbId,
            'etpId' => $etpId,
            'tradePlaceId' => $tradePlaceId,
            'populatedStatus' => self::POPULATED_NO,
        ]);

        return $trade;
    }


    public function populate(array $values)
    {
        // $this->scenario = self::SCENARIO_POPULATE;

        foreach ($values as $key => $value) {
            $this->$key = $value;
        }
    }


    public function createLot(
        int $lotNumber,
        float $startPrice,
        string $tradeObjectHtml,
        string $classificationId,
        \DateTime $biddingInvitationTime,
        string $paymentInfo = null,
        string $saleAgreement = null
    ) {
        $lot = new Lot([
            'lotNumber' => $lotNumber,
            'processedStatus' => Lot::PROCESSED_NO,
            'actualPrice' => $startPrice,
            'startPrice' => $startPrice,
            'tradeObjectHtml' => $tradeObjectHtml,
            'classificationId' => $classificationId,
            'tradeId' => $this->id,
            'paymentInfo' => $paymentInfo,
            'saleAgreement' => $saleAgreement,
            'status' => Status::BIDDING_DECLARED,
            'previousStatus' => null,
            'biddingInvitationTime' => $biddingInvitationTime->format('Y-m-d H:i:s')
        ]);

        return $lot;
    }


    # *** МЕТОДЫ ОТОБРАЖЕНИЯ ***


    public function getRegionText()
    {
        if ($this->regionCode) {
            return RegionService::getTitleByCode($this->regionCode);
        }
        return '';
    }


    public static function generateAuctionTypeColumnSchema()
    {
        $array = self::getAuctionTypeArray();
        foreach ($array as $key => $el) {
            $array[$key] = '\'' . $el . '\'';
        }

        return "enum(" . implode(', ', $array) . ")";
    }


    public static function getAuctionTypeArray()
    {
        return [
            self::AUCTION_TYPE_OPEN_AUCTION,
            self::AUCTION_TYPE_OPEN_CONCOURS,
            self::AUCTION_TYPE_PUBLIC_OFFER,
            self::AUCTION_TYPE_CLOSE_AUCTION,
            self::AUCTION_TYPE_CLOSE_CONCOURS,
            self::AUCTION_TYPE_CLOSE_PUBLIC_OFFER,
        ];
    }


    public static function getAuctionTypeArrayWithText()
    {
        return [
            self::AUCTION_TYPE_OPEN_AUCTION => 'Открытый аукцион',
            self::AUCTION_TYPE_OPEN_CONCOURS => 'Открытый конкурс',
            self::AUCTION_TYPE_PUBLIC_OFFER => 'Публичное предложение',
            self::AUCTION_TYPE_CLOSE_AUCTION => 'Закрытый аукцион',
            self::AUCTION_TYPE_CLOSE_CONCOURS => 'Закрытый конкурс',
            self::AUCTION_TYPE_CLOSE_PUBLIC_OFFER => 'Закрытое публичное предложение',
        ];
    }


    public static function generateFormPriceColumnSchema()
    {
        $array = self::getFormPriceArray();
        foreach ($array as $key => $el) {
            $array[$key] = '\'' . $el . '\'';
        }

        return "enum(" . implode(', ', $array) . ")";
    }


    public static function getFormPriceArray()
    {
        return [
            self::FORM_PRICE_OPEN_FORM,
            self::FORM_PRICE_CLOSE_FORM,
        ];
    }


    public static function getFormPriceArrayWithText()
    {
        return [
            self::FORM_PRICE_OPEN_FORM => 'Открытая форма',
            self::FORM_PRICE_CLOSE_FORM => 'Закрытая форма',
        ];
    }


    public function getLotsCountByStatus(string $status)
    {
        return $this->getLots()->andWhere(['status' => $status])->count();
    }


    public static function generatePopulatedStatusColumnSchema()
    {
        $array = self::getPopulatedStatusArray();
        foreach ($array as $key => $el) {
            $array[$key] = '\'' . $el . '\'';
        }

        return "enum(" . implode(', ', $array) . ") not null default 'No'";
    }


    public static function getPopulatedStatusArray()
    {
        return [
            self::POPULATED_YES,
            self::POPULATED_NO,
            self::POPULATED_FAIL,
        ];
    }


    public function getIsPopulated()
    {
        return $this->populatedStatus == self::POPULATED_YES;
    }


    public function getPopulatedStatusText()
    {
        switch ($this->populatedStatus) {
            case self::POPULATED_YES: return 'Да';
            case self::POPULATED_NO: return 'Нет';
            case self::POPULATED_FAIL: return 'Ошибка';
        }
    }


    public static function generateIsRepeatColumnSchema()
    {
        $array = self::getIsRepeatArray();
        foreach ($array as $key => $el) {
            $array[$key] = '\'' . $el . '\'';
        }

        return "enum(" . implode(', ', $array) . ")";
    }


    public static function getIsRepeatArray()
    {
        return [
            self::IS_REPEAT_YES,
            self::IS_REPEAT_NO,
        ];
    }


    public function getStatusText()
    {
        return Status::getTextByStatus($this->status);
    }


    public function updateErrorMessage($text)
    {
        if (is_null($this->errorMessage)) {
            $this->errorMessage = $text;
        } else {
            $this->errorMessage .= "; $text";
        }
    }


    public function getParsedPhones()
    {
        if (($message = $this->auctionMessage) && !empty($phones = $message->parsedPhones)) {
            return $phones;
        }

        return null;
    }


    public function getCaseNumber()
    {
        if ($case = $this->legalCase) {
            return $case->caseNumber;
        }

        return null;
    }


    public function getDebtorText()
    {
        if ($this->debtorPersonId) {
            return $this->debtorPerson->initials;
        }

        if ($this->debtorCompanyId) {
            return $this->debtorCompany->shortName;
        }

        return null;
    }


    public function getArbitrManagerText()
    {
        if ($this->arbitrManagerPersonId) {
            return $this->arbitrManagerPerson->initials;
        }

        if ($this->arbitrManagerCompanyId) {
            return $this->arbitrManagerCompany->shortName;
        }

        return null;
    }


    public function getTradeOrganizerText()
    {
        if ($this->tradeOrganizerPersonId) {
            return $this->tradeOrganizerPerson->initials;
        }

        if ($this->tradeOrganizerCompanyId) {
            return $this->tradeOrganizerCompany->shortName;
        }

        return null;
    }


    public function getAuctionTypeText()
    {
    	$values = self::getAuctionTypeArrayWithText();
    	return $values[$this->auctionType] ?? 'Неизвестный тип аукциона';
    }


    public function getFormPriceText()
    {
    	$values = self::getFormPriceArrayWithText();
    	return $values[$this->formPrice] ?? 'Неизвестная форма предложения';
    }


    public function getIsRepeatText()
    {
    	if ($this->isRepeat == self::IS_REPEAT_NO) {
    		return 'Первоначальные';
    	}

		if ($this->isRepeat == self::IS_REPEAT_YES) {
    		return 'Повторные';
    	}

    	return 'Неизвестное значение';
    }


    public function getIsOpenFormPrice()
    {
    	return $this->formPrice == self::FORM_PRICE_OPEN_FORM;
    }


    /**
     * Добавляет в messagesHistory новое сообщение (его идентификатор).
     * Проверяет, что данное сообщение действительно нужно добавить в конец.
     * Если новое сообщение должно быть не в конце, то бросает исключение.
     *
     * Поясню. Например, мы по данным торгам уже получили сообщения 10, 20, 30, 40.
     * Если мы добавляем новое сообщение 50, всё ок - оно встает в конец.
     * Но если мы хотим добавить сообщение 25, то возникнет ошибка - оказывается, мы уже спарсили сообщения после него (30,40,50),
     * а так быть не должно. Бросаем исключение.
     * @param int $messageId
     * @return type
     */
    public function addNewMessageToHistory(int $messageId)
    {
        if (empty($this->messagesHistory)) {
            $this->messagesHistory = (string)$messageId;
            return;
        }

        // $array = explode(',', $this->messagesHistory);
        $lastMessageId = $this->getLastMessageId();
        if ($messageId <= $lastMessageId) {
            throw new WrongMessageOrderException('Нельзя добавить сообщение, которое не является крайним для торгов');
        }

        $this->messagesHistory .= ",$messageId";
    }


    public function getLastMessageId()
    {
        // $array = explode(',', $this->messagesHistory);
        // return $array[count($array)-1];
        $commaPosition = strrchr($this->messagesHistory, ',');
        if (false === $commaPosition) {
            return $this->messagesHistory;
        }

        return substr($commaPosition, 1);
    }


    public function updateLastParsingDate(\DateTime $newDateTime)
    {
        $lastParsingDateTime = $this->lastParsingDateTime;

        if (is_null($lastParsingDateTime) || $newDateTime > $lastParsingDateTime) {
            $this->lastParsingDate = $newDateTime->format('Y-m-d H:i:s');
        }
    }


    public function getLastParsingDateTime()
    {
        if (is_null($this->lastParsingDate)) {
            return null;
        }

        return new \DateTime($this->lastParsingDate);
    }


    public function getIsDefect()
    {
    	return $this->defect == self::DEFECT_YES;
    }
}
