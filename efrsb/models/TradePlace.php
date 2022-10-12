<?php

namespace app\efrsb\models;

use Yii;


/**
 * TradePlace - класс, обозначающий электронную торговую площадку (ЭТП)
 */
class TradePlace extends \app\models\ActiveRecord
{

    public static function tableName()
    {
        return 'efrsb_trade_place';
    }


    public function rules()
    {
        return [
            [['inn', 'title', 'site'], 'required'],
            [['inn'], 'string', 'max' => 12],
            [['title', 'site'], 'string', 'max' => 255],
            [['inn'], 'unique'],
            [['title'], 'unique'],
            [['site'], 'unique'],
        ];
    }


    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'inn' => 'ИНН',
            'title' => 'Название',
            'site' => 'Сайт',
            'tradeCount' => 'Количество торгов',
        ];
    }


    public static function create(string $inn, string $title, string $site): self
    {
        $self = new self([
            'inn' => $inn,
            'title' => $title,
            'site' => $site,
        ]);

        return $self;
    }


    public function createTrade(
        int $efrsbId,
        string $etpId,
        string $auctionMessageId,
        // string $auctionMessageText,
        string $caseNumber,
        \DateTime $eventTime,
        int $regionCode,
        string $auctionType,
        string $formPrice
    ): Trade {
        $trade = Trade::create($efrsbId, $etpId, $auctionMessageId, /* $auctionMessageText,*/ $caseNumber, $eventTime, $regionCode, $auctionType,
            $formPrice, $this->id);
        return $trade;
    }


    public function createPrototypeTrade(
        int $efrsbId,
        string $etpId
    ): Trade {
        $trade = Trade::createPrototypeTrade($efrsbId, $etpId, $this->id);
        return $trade;
    }


    public function getTrades()
    {
        return $this->hasMany(Trade::class, ['tradePlaceId' => 'id']);
    }


    public function getTradeCount()
    {
        return $this->getTrades()->count();
    }
}
