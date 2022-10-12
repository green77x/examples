<?php

namespace app\efrsb\models;

use app\efrsb\services\ClassificationService;
use Yii;

/**
 * This is the model class for table "efrsb_auction_message_lot".
 *
 * @property int $id
 * @property int $auctionMessageEfrsbId
 * @property int $order
 * @property string $startPrice
 * @property string $description
 * @property string $classificationId
 * @property string $dateCreate
 * @property string $dateUpdate
 *
 * @property EfrsbAuctionMessage $auctionMessageEfrsb
 */
class AuctionMessageLot extends \app\models\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'efrsb_auction_message_lot';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['auctionMessageEfrsbId', 'order', 'startPrice'], 'required'],
            [['auctionMessageEfrsbId', 'order'], 'integer'],
            [['startPrice'], 'number'],
            [['description'], 'string'],
            [['dateCreate', 'dateUpdate'], 'safe'],
            [['classificationId'], 'string', 'max' => 255],
            [['auctionMessageEfrsbId'], 'exist', 'skipOnError' => true, 'targetClass' => AuctionMessage::className(), 'targetAttribute' => ['auctionMessageEfrsbId' => 'efrsbId']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'auctionMessageEfrsbId' => 'Auction Message Efrsb ID',
            'order' => 'Номер лота',
            'startPrice' => 'Начальная цена',
            'description' => 'Описание',
            'classificationId' => 'Классификация',
            'dateCreate' => 'Date Create',
            'dateUpdate' => 'Date Update',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAuctionMessage()
    {
        return $this->hasOne(AuctionMessage::className(), ['efrsbId' => 'auctionMessageEfrsbId']);
    }


    public function getClassificationText()
    {
        return ClassificationService::getTextByCode($this->classificationId, true);
    }
}
