<?php

namespace app\efrsb\models;

use Yii;


class AuctionMessage extends \app\models\ActiveRecord
{

	const IS_REPEAT_NO = 0;
	const IS_REPEAT_YES = 1;


	const TRADE_TYPE_OPENED_AUCTION = 'OpenedAuction';
	const TRADE_TYPE_OPENED_CONCOURS = 'OpenedConcours';
	const TRADE_TYPE_PUBLIC_OFFER = 'PublicOffer';
	const TRADE_TYPE_CLOSED_AUCTION = 'ClosedAuction';
	const TRADE_TYPE_CLOSED_CONCOURS = 'ClosedConcours';
	const TRADE_TYPE_CLOSE_PUBLIC_OFFER = 'ClosePublicOffer';


    const PRICE_TYPE_PUBLIC = 'Public';
    const PRICE_TYPE_PRIVATE = 'Private';


	public static function tableName()
	{
		return 'efrsb_auction_message';
	}


	public function rules()
	{
		return [
			[['efrsbId'], 'required'],
			[['efrsbId'], 'integer'],
			[['text'], 'string'],
			[['dateCreate', 'dateUpdate'], 'safe'],
			[['efrsbId'], 'unique'],
		];
	}


	public function attributeLabels()
	{
		return [
			'efrsbId' => 'Efrsb ID',
			'text' => 'Текст',
			'parsedPhones' => 'Телефонные номера',
			'caseNumber' => 'Номер судебного дела',
			'publishDate' => 'Дата публикации',
			'textShortened' => 'Текст',
			'bankruptId' => 'Идентификатор должника на сайте',
			'messageGUID' => 'Идентификатор сообщения на сайте',
			'messageUrlList' => 'Перечень прикрепленных файлов',
			'isRepeat' => 'Признак повторных торгов',
			'isRepeatText' => 'Признак повторных торгов',
			'date' => 'Дата и время торгов',
			'tradeType' => 'Вид торгов',
			'tradeTypeText' => 'Вид торгов',
			'priceType' => 'Форма предложения о цене',
			'priceTypeText' => 'Форма предложения о цене',
			'tradeSite' => 'Место проведения торгов',
			'additionalText' => 'Дополнительная информация',
			'applicationTimeBegin' => 'Дата и время начала подачи заявок',
			'applicationTimeEnd' => 'Дата и время окончания подачи заявок',



			'dateCreate' => 'Дата добавления в базу',
			'dateUpdate' => 'Дата обновления',
		];
	}


	public function getTrades()
	{
		return $this->hasMany(Trade::class, ['auctionMessageId' => 'efrsbId']);
	}


	public function getAuctionMessageLots()
	{
		return $this->hasMany(AuctionMessageLot::class, ['auctionMessageEfrsbId' => 'efrsbId']);
	}


	public static function create(int $efrsbId, string $text)
	{
		$m = new self(['efrsbId' => $efrsbId, 'text' => $text]);
		return $m;
	}


	public static function getTradeTypeColumnSchema()
	{
		$array = self::getTradeTypeArray();
		foreach ($array as $key => $el) {
			$array[$key] = '\'' . $el . '\'';
		}

		return "enum(" . implode(', ', $array) . ") not null";
	}


	public static function getTradeTypeArray()
	{
		return [
			self::TRADE_TYPE_OPENED_AUCTION,
			self::TRADE_TYPE_OPENED_CONCOURS,
			self::TRADE_TYPE_PUBLIC_OFFER,
			self::TRADE_TYPE_CLOSED_AUCTION,
			self::TRADE_TYPE_CLOSED_CONCOURS,
			self::TRADE_TYPE_CLOSE_PUBLIC_OFFER,
		];
	}


	public static function getTradeTypeArrayWithText()
	{
		return [
			self::TRADE_TYPE_OPENED_AUCTION => 'Открытый аукцион',
			self::TRADE_TYPE_OPENED_CONCOURS => 'Открытый конкурс',
			self::TRADE_TYPE_PUBLIC_OFFER => 'Публичное предложение',
			self::TRADE_TYPE_CLOSED_AUCTION => 'Закрытый аукцион',
			self::TRADE_TYPE_CLOSED_CONCOURS => 'Закрытый конкурс',
			self::TRADE_TYPE_CLOSE_PUBLIC_OFFER => 'Закрытое публичное предложение',
		];
	}


	public static function getPriceTypeColumnSchema()
	{
		$array = self::getPriceTypeArray();
		foreach ($array as $key => $el) {
			$array[$key] = '\'' . $el . '\'';
		}

		return "enum(" . implode(', ', $array) . ") not null";
	}


	public static function getPriceTypeArray()
	{
		return [
			self::PRICE_TYPE_PUBLIC,
			self::PRICE_TYPE_PRIVATE,
		];
	}


	public static function getPriceTypeArrayWithText()
	{
		return [
			self::PRICE_TYPE_PUBLIC => 'Открытая форма',
			self::PRICE_TYPE_PRIVATE => 'Закрытая форма',
		];
	}


	public function getTextShortened($length = 200)
	{
		return mb_substr($this->text, 0, $length) . '...';
	}


	public function getTradeTypeText()
    {
    	$values = self::getTradeTypeArrayWithText();
    	return $values[$this->tradeType] ?? 'Неизвестный вид торгов';
    }


    public function getPriceTypeText()
    {
    	$values = self::getPriceTypeArrayWithText();
    	return $values[$this->priceType] ?? 'Неизвестная форма предложения о цене';
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
}
